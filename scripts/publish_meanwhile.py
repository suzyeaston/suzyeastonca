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
import uuid
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
    image_zip = ENTRIES / "media" / (slug + ".zip")
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
    return {
        "title": title,
        "slug": slug,
        "excerpt": str(meta.get("excerpt", "")).strip(),
        "category": str(meta.get("category", "notes / ideas")).strip(),
        "html": html,
        "featured_image": featured,
        "photos": photos,
    }


def run_command(client: paramiko.SSHClient, command: str) -> str:
    _, stdout, stderr = client.exec_command(command, timeout=180)
    code = stdout.channel.recv_exit_status()
    output = stdout.read().decode("utf-8", errors="replace")
    error = stderr.read().decode("utf-8", errors="replace")
    if code:
        raise RuntimeError(f"Remote publisher failed (exit {code}): {error[-1200:]} {output[-1200:]}")
    return output


def publish(prepared: list[dict]) -> None:
    missing = [name for name in ("LOUSY_SSH_HOST", "LOUSY_SSH_USER", "LOUSY_SSH_PASSWORD") if not os.getenv(name)]
    if missing:
        raise RuntimeError("Missing GitHub Actions SSH secrets: " + ", ".join(missing))
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    # The existing deploy script uses the configured host directly; pin SSH host
    # keys through the GitHub secret below to avoid accepting impostor hosts.
    known_hosts = os.getenv("LOUSY_SSH_KNOWN_HOSTS", "").strip()
    if not known_hosts:
        raise RuntimeError("Set LOUSY_SSH_KNOWN_HOSTS in GitHub Actions secrets to the server's verified SSH host key")
    import tempfile
    with tempfile.NamedTemporaryFile(mode="w", suffix=".known_hosts", encoding="utf-8") as f:
        f.write(known_hosts + "\n")
        f.flush()
        client.load_host_keys(f.name)
        client.connect(
            hostname=os.environ["LOUSY_SSH_HOST"],
            port=int(os.getenv("LOUSY_SSH_PORT") or "22"),
            username=os.environ["LOUSY_SSH_USER"],
            password=os.environ["LOUSY_SSH_PASSWORD"],
            timeout=40,
            look_for_keys=False,
            allow_agent=False,
        )
    stage = f"{REMOTE_HOME}/tmp/meanwhile-{uuid.uuid4().hex}"
    try:
        run_command(client, "mkdir -p " + shlex.quote(stage + "/media"))
        sftp = client.open_sftp()
        try:
            sftp.put(str(ROOT / "scripts" / "wp_publish_meanwhile.php"), stage + "/publisher.php")
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
                output = run_command(
                    client, "php " + shlex.quote(stage + "/publisher.php") + " " + shlex.quote(stage + "/post.json")
                )
                print(post["slug"] + ": " + output.strip())
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
    entries = sorted(ENTRIES.glob("*.md"))
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
