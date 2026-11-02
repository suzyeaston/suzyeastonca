#!/usr/bin/env python3
"""Apply/verify the published Meanwhile dates using one WordPress bootstrap."""
from __future__ import annotations

import json
import os
import re
import shlex
import tempfile
import uuid
from datetime import datetime
from pathlib import Path

import paramiko
import yaml
from publish_meanwhile import run_command, select_php, ROOT, REMOTE_HOME

POSTS = [
    ("content/meanwhile/2026-11-the-toaster-was-a-decoy.md", "the-toaster-was-a-decoy"),
    ("content/meanwhile/2026-11-an-orchestra-in-8gb.md", "an-orchestra-in-8gb"),
]


def load_updates() -> list[dict]:
    updates = []
    for filename, slug in POSTS:
        source = (ROOT / filename).read_text(encoding="utf-8")
        match = re.match(r"\A---\s*\n(.*?)\n---\s*\n", source, re.S)
        if not match:
            raise RuntimeError(f"Missing frontmatter in {filename}")
        front = yaml.safe_load(match.group(1))
        date = front.get("published_at")
        if front.get("slug") != slug or front.get("status") != "publish":
            raise RuntimeError(f"Unexpected post identity/status in {filename}")
        if not isinstance(date, str) or datetime.strptime(date, "%Y-%m-%d %H:%M:%S").strftime("%Y-%m-%d %H:%M:%S") != date:
            raise RuntimeError(f"Invalid published_at date for {slug}")
        updates.append({"slug": slug, "published_at": date})
    if updates[1]["published_at"] <= updates[0]["published_at"]:
        raise RuntimeError("Part II must be published later than Part I")
    return updates


def main() -> None:
    updates = load_updates()
    missing = [name for name in ("LOUSY_SSH_HOST", "LOUSY_SSH_USER", "LOUSY_SSH_PASSWORD") if not os.getenv(name)]
    if missing:
        raise RuntimeError("Missing GitHub Actions deployment secrets: " + ", ".join(missing))

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    known_hosts = os.getenv("LOUSY_SSH_KNOWN_HOSTS", "").strip()
    if known_hosts:
        client.set_missing_host_key_policy(paramiko.RejectPolicy())
        with tempfile.NamedTemporaryFile(mode="w", encoding="utf-8", suffix=".known_hosts") as keys:
            keys.write(known_hosts + "\n")
            keys.flush()
            client.load_host_keys(keys.name)
    else:
        # Same trust policy as the current, functioning production deploy.
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        hostname=os.environ["LOUSY_SSH_HOST"],
        port=int(os.getenv("LOUSY_SSH_PORT") or "22"),
        username=os.environ["LOUSY_SSH_USER"],
        password=os.environ["LOUSY_SSH_PASSWORD"],
        timeout=40,
        allow_agent=False,
        look_for_keys=False,
    )
    stage = REMOTE_HOME + "/tmp/meanwhile-date-repair-" + uuid.uuid4().hex
    try:
        php = select_php(client)
        run_command(client, "mkdir -p " + shlex.quote(stage))
        with client.open_sftp() as sftp:
            sftp.put(str(ROOT / "scripts/wp_repair_meanwhile_dates.php"), stage + "/repair.php")
            with tempfile.NamedTemporaryFile(mode="w", encoding="utf-8", suffix=".json") as tmp:
                json.dump({"updates": updates}, tmp)
                tmp.flush()
                sftp.put(tmp.name, stage + "/dates.json")
        print(run_command(client, shlex.quote(php) + " -l " + shlex.quote(stage + "/repair.php")).strip())
        output = run_command(client, shlex.quote(php) + " " + shlex.quote(stage + "/repair.php") + " " + shlex.quote(stage + "/dates.json"))
        try:
            result = json.loads(output)
        except json.JSONDecodeError as exc:
            raise RuntimeError("WordPress did not return JSON verification: " + output[-300:]) from exc
        if (not result.get("verified")
            or result.get("homepage_latest") != "an-orchestra-in-8gb"
            or result.get("blog_order", [])[:2] != ["an-orchestra-in-8gb", "the-toaster-was-a-decoy"]
            or result.get("part_ii_date") != updates[1]["published_at"]
            or result.get("part_i_date") != updates[0]["published_at"]):
            raise RuntimeError("WordPress date repair did not pass the read-back check: " + output)
        print("WordPress homepage and Meanwhile archive chronology VERIFIED: " + json.dumps(result))
    finally:
        try:
            run_command(client, "rm -rf " + shlex.quote(stage))
        finally:
            client.close()


if __name__ == "__main__":
    main()
