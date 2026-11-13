#!/usr/bin/env python3
"""Publish opted-in Meanwhile Markdown entries via the existing WordPress SSH connection.

Posts are never published from an unmerged pull request. The GitHub Action runs
only on main (or an explicitly dispatched workflow). Secrets stay in Actions.
"""
from __future__ import annotations

import argparse
import json
import io
import zipfile
import os
import re
import shlex
import sys
import time
import uuid
from datetime import datetime
from pathlib import Path

import markdown
import paramiko
import yaml

ROOT = Path(__file__).resolve().parent.parent
ENTRIES = ROOT / "content" / "meanwhile"
REMOTE_HOME = os.getenv("SE_WP_HOME", "/home/uquklkik")
PHOTO_PATTERN = re.compile(r"<!--\s*PHOTO:\s*([^|>]+?)\s*\|\s*([^|>]+?)\s*\|\s*([^>]*?)\s*-->", re.I)
META_PATTERN = re.compile(r"\A---\s*\n(.*?)\n---\s*\n", re.S)


def prepare(path: Path) -> dict | None:
    source = path.read_text(encoding="utf-8")
    frontmatter = META_PATTERN.match(source)
    if not frontmatter:
        raise ValueError(f"{path}: missing YAML frontmatter")
    meta = yaml.safe_load(frontmatter.group(1))
    if not isinstance(meta, dict):
        raise ValueError(f"{path}: invalid frontmatter")
    if str(meta.get("status", "")).lower() != "publish":
        print(f"Skipping {path.name} (status is not publish)")
        return None

    slug = str(meta.get("slug", "")).strip()
    if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug):
        raise ValueError(f"{path}: invalid slug")
    title = str(meta.get("title", "")).strip()
    if not title:
        raise ValueError(f"{path}: missing title")
    image_dir = ENTRIES / "media" / slug
    archive_name = str(meta.get("media_archive") or (slug + ".zip")).strip()
    if archive_name != Path(archive_name).name or not archive_name.lower().endswith(".zip"):
        raise ValueError(f"{path}: invalid media_archive filename")
    image_zip = ENTRIES / "media" / archive_name
    photos: list[dict] = []
    used_names: set[str] = set()
    body = source[frontmatter.end():]
    if "<!-- EDITORIAL:" in body or "<!-- PHOTO:" not in body:
        raise ValueError(f"{path}: remove editorial placeholders and provide photo markers")

    def substitute(match: re.Match) -> str:
        filename, alt, caption = (s.strip() for s in match.groups())
        if filename != Path(filename).name or Path(filename).suffix.lower() not in (".jpg", ".jpeg", ".png", ".webp"):
            raise ValueError(f"{path}: unsafe photo name {filename!r}")
        if filename in used_names:
            raise ValueError(f"{path}: duplicate photo marker for {filename}")
        used_names.add(filename)
        photo_path = image_dir / filename
        if not photo_path.is_file():
            if not image_zip.is_file():
                raise ValueError(f"{path}: missing image {filename}; add {image_zip.relative_to(ROOT)}")
            with zipfile.ZipFile(image_zip) as zf:
                if filename not in zf.namelist():
                    raise ValueError(f"{path}: {image_zip.name} does not contain {filename}")
        if not alt:
            raise ValueError(f"{path}: missing alt text for {filename}")
        token = f"MEANWHILE_PHOTO_{len(photos)}_TOKEN"
        photos.append({"name": filename, "alt": alt, "caption": caption if caption != "-" else "", "token": token, "path": str(photo_path) if photo_path.is_file() else "", "zip": str(image_zip) if not photo_path.is_file() else ""})
        return f"\n\n{token}\n\n"

    body = PHOTO_PATTERN.sub(substitute, body)
    if re.search(r"<!--\s*PHOTO:", body, re.I):
        raise ValueError(f"{path}: malformed photo marker")
    html = markdown.markdown(body, extensions=["extra", "sane_lists"])
    for photo in photos:
        html = html.replace(f"<p>{photo['token']}</p>", photo["token"])
    if any(photo["token"] not in html for photo in photos):
        raise ValueError(f"{path}: photo token disappeared during Markdown conversion")

    featured = str(meta.get("featured_image", "")).strip()
    if featured and featured not in used_names:
        raise ValueError(f"{path}: featured image must appear in the article")
    published_at = meta.get("published_at", "")
    if published_at:
        if not isinstance(published_at, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}", published_at):
            raise ValueError(f"{path}: published_at must be quoted YYYY-MM-DD HH:MM:SS in WordPress local time")
        try:
            datetime.strptime(published_at, "%Y-%m-%d %H:%M:%S")
        except ValueError as exc:
            raise ValueError(f"{path}: invalid published_at: {published_at}") from exc

    return {
        "published_at": published_at,
        "title": title,
        "slug": slug,
        "excerpt": str(meta.get("excerpt", "")).strip(),
        "category": str(meta.get("category", "notes / ideas")).strip(),
        "html": html,
        "featured_image": featured,
        "photos": photos,
    }


def run_command(
    client: paramiko.SSHClient, command: str, stderr_sink: list[str] | None = None
) -> str:
    _, stdout, stderr = client.exec_command(command, timeout=180)
    code = stdout.channel.recv_exit_status()
    output = stdout.read().decode("utf-8", errors="replace")
    error = stderr.read().decode("utf-8", errors="replace")
    if stderr_sink is not None:
        stderr_sink.append(error)
    if code:
        raise RuntimeError(f"Remote publisher failed (exit {code}): {error[-1200:]} {output[-1200:]}")
    return output


# Rate-limit responses can be printed by WordPress during bootstrap and
# terminate PHP with exit code 0, before our importer ever emits JSON.
# Respect the server's limit with bounded, increasingly spaced retries.
RATE_LIMIT_RETRY_DELAYS = (60, 180, 300)


def run_publisher_with_backoff(client: paramiko.SSHClient, command: str, slug: str) -> str:
    for attempt in range(len(RATE_LIMIT_RETRY_DELAYS) + 1):
        diagnostics: list[str] = []
        output = run_command(client, command, stderr_sink=diagnostics)
        if not re.search(r"\brate limit exceeded\b", output, re.IGNORECASE):
            return output
        stage = (
            "after WordPress bootstrap"
            if diagnostics and "Meanwhile WordPress loaded" in diagnostics[0]
            else "during WordPress bootstrap"
        )
        print(f"WordPress emitted rate-limit text {stage}.", flush=True)
        if attempt >= len(RATE_LIMIT_RETRY_DELAYS):
            raise RuntimeError(
                f"WordPress rate limit persisted for {slug} after "
                f"{attempt + 1} attempts; stop and investigate hosting/plugin limits. "
                "No further automatic retries."
            )
        delay = RATE_LIMIT_RETRY_DELAYS[attempt]
        print(
            f"WordPress rate limited {slug} (attempt {attempt + 1}); "
            f"respecting a {delay}s cooldown before retry.",
            flush=True,
        )
        time.sleep(delay)
    raise AssertionError("Unreachable retry state")


def select_php(client: paramiko.SSHClient) -> str:
    """Use a hosting-provider PHP CLI matching the site's modern PHP runtime.

    Many cPanel/CloudLinux hosts default 'php' to 7.x even when WordPress
    runs under PHP 8.x in the web server. Their plugins cannot bootstrap
    through the old CLI.
    """
    candidates = [
        "/opt/cpanel/ea-php84/root/usr/bin/php",
        "/opt/cpanel/ea-php83/root/usr/bin/php",
        "/opt/cpanel/ea-php82/root/usr/bin/php",
        "/opt/cpanel/ea-php81/root/usr/bin/php",
        "/opt/alt/php84/usr/bin/php",
        "/opt/alt/php83/usr/bin/php",
        "/opt/alt/php82/usr/bin/php",
        "/opt/alt/php81/usr/bin/php",
        "php8.4",
        "php8.3",
        "php8.2",
        "php8.1",
        "php",
    ]
    available: list[tuple[int, str]] = []
    for name in candidates:
        try:
            result = run_command(
                client, shlex.quote(name) + " -r 'echo PHP_VERSION_ID;'"
            ).strip()
            if result.isdecimal() and int(result) >= 80100:
                available.append((int(result), name))
        except RuntimeError:
            continue
    if not available:
        raise RuntimeError(
            "No compatible PHP 8.1+ CLI found on hosting server; "
            "please configure the path to the web server's PHP 8.x binary."
        )
    version, binary = max(available)
    print(f"Remote PHP CLI selected: {binary} (version id {version})", flush=True)
    return binary


def publish(prepared: list[dict]) -> None:
    missing = [name for name in ("LOUSY_SSH_HOST", "LOUSY_SSH_USER", "LOUSY_SSH_PASSWORD") if not os.getenv(name)]
    if missing:
        raise RuntimeError("Missing GitHub Actions SSH secrets: " + ", ".join(missing))
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    # Prefer the verified host key pin when configured. If absent, use the
    # trust-on-first-use policy already used by this repository's production
    # theme deploy (deploy-lousy-outages-ssh.py). Pinning is recommended.
    known_hosts = os.getenv("LOUSY_SSH_KNOWN_HOSTS", "").strip()
    if known_hosts:
        client.set_missing_host_key_policy(paramiko.RejectPolicy())
        import tempfile
        with tempfile.NamedTemporaryFile(mode="w", suffix=".known_hosts", encoding="utf-8") as f:
            f.write(known_hosts + "\n")
            f.flush()
            client.load_host_keys(f.name)
    else:
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        print("WARNING: SSH host key not pinned. Using existing theme deploy trust-on-first-use policy.", flush=True)
    client.connect(
        hostname=os.environ["LOUSY_SSH_HOST"],
        port=int(os.getenv("LOUSY_SSH_PORT") or "22"),
        username=os.environ["LOUSY_SSH_USER"],
        password=os.environ["LOUSY_SSH_PASSWORD"],
        timeout=40,
        look_for_keys=False,
        allow_agent=False,
    )
    php_bin = select_php(client)
    stage = f"{REMOTE_HOME}/tmp/meanwhile-{uuid.uuid4().hex}"
    try:
        run_command(client, "mkdir -p " + shlex.quote(stage + "/media"))
        sftp = client.open_sftp()
        try:
            sftp.put(str(ROOT / "scripts" / "wp_publish_meanwhile.php"), stage + "/publisher.php")
            sftp.put(str(ROOT / "scripts" / "wp_verify_meanwhile_order.php"), stage + "/verify-order.php")
            print(run_command(client, shlex.quote(php_bin) + " -l " + shlex.quote(stage + "/publisher.php")).strip())
            for post in prepared:
                manifest = {key: value for key, value in post.items()}
                manifest["photos"] = []
                for photo in post["photos"]:
                    if photo["path"]:
                        sftp.put(photo["path"], stage + "/media/" + photo["name"])
                    else:
                        with zipfile.ZipFile(photo["zip"]) as zf:
                            payload = zf.read(photo["name"])
                        sftp.putfo(io.BytesIO(payload), stage + "/media/" + photo["name"], file_size=len(payload))
                    manifest["photos"].append({key: photo[key] for key in ("name", "alt", "caption", "token")})
                import tempfile
                with tempfile.NamedTemporaryFile(mode="w", encoding="utf-8", suffix=".json") as tmp:
                    json.dump(manifest, tmp, ensure_ascii=False)
                    tmp.flush()
                    sftp.put(tmp.name, stage + "/post.json")
                output = run_publisher_with_backoff(
                    client,
                    shlex.quote(php_bin) + " " + shlex.quote(stage + "/publisher.php") + " " + shlex.quote(stage + "/post.json"),
                    post["slug"],
                )
                try:
                    result = json.loads(output.strip())
                except json.JSONDecodeError as exc:
                    raise RuntimeError(
                        "WordPress returned a non-JSON response for "
                        + post["slug"] + ": " + output[-320:]
                    ) from exc
                if (not isinstance(result, dict)
                    or result.get("status") != "publish"
                    or result.get("images") != len(post["photos"])
                    or result.get("post_date") != post.get("published_at")
                    or not str(result.get("url", "")).rstrip("/").endswith("/" + post["slug"])):
                    raise RuntimeError(
                        "WordPress confirmation did not match "
                        + post["slug"] + ": " + json.dumps(result)
                    )
                print(post["slug"] + ": " + json.dumps(result))
            # The importer has already read back the post's status, date and
            # permalink. A second, immediate WordPress CLI bootstrap can trip
            # the host's rate limiter even after a successful update.
        finally:
            sftp.close()
    finally:
        try:
            run_command(client, "rm -rf " + shlex.quote(stage))
        finally:
            client.close()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--slug", default="", help="Only publish one named entry")
    parser.add_argument("--validate-only", action="store_true")
    args = parser.parse_args()
    entries = sorted(path for path in ENTRIES.glob("*.md") if path.name != "README.md")
    if args.slug:
        entries = [
            path for path in entries
            if (fm := META_PATTERN.match(path.read_text(encoding="utf-8")))
            and (yaml.safe_load(fm.group(1)) or {}).get("slug") == args.slug
        ]
        if not entries:
            raise ValueError(f"No Meanwhile entry has slug {args.slug!r}")
    prepared = [x for path in entries if (x := prepare(path)) is not None]
    if not prepared:
        print("No entries marked for publication.")
        return 0
    for entry in prepared:
        print(f"Validated {entry['slug']}: {len(entry['photos'])} images")
    if not args.validate_only:
        publish(prepared)
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (RuntimeError, ValueError, OSError, paramiko.SSHException) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(1)
