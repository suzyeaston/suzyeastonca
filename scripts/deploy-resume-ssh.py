#!/usr/bin/env python3
"""Deploy resume theme files and private resume assets over SFTP.

Reads credentials from /workspace/.env.deploy.local or LOUSY_SSH_* env vars
(same as scripts/deploy-lousy-outages-ssh.py).

Usage:
    python3 scripts/deploy-resume-ssh.py
"""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parent.parent
ENV = ROOT / ".env.deploy.local"
THEME_DIR = "/home/uquklkik/public_html/wp-content/themes/suzyeastonca-main"

RESUME_FILES = [
    "page-resume.php",
    "assets/css/resume.css",
    "js/resume.js",
    "functions.php",
    "header.php",
    "inc/resume-data.php",
    "assets/resume/suzy-easton-bsa-resume.html",
    "assets/resume/Suzy_Easton_BSA_Resume.pdf",
]

DEPLOY_ENV_KEYS = (
    "LOUSY_SSH_HOST",
    "LOUSY_SSH_PORT",
    "LOUSY_SSH_USER",
    "LOUSY_SSH_PASSWORD",
)


def load_env(path: Path) -> dict:
    data = {}
    for key in DEPLOY_ENV_KEYS:
        value = os.environ.get(key)
        if value:
            data[key] = value
    if path.is_file():
        for line in path.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            data.setdefault(key.strip(), value.strip())
    if not data.get("LOUSY_SSH_HOST") or not data.get("LOUSY_SSH_USER") or not data.get("LOUSY_SSH_PASSWORD"):
        raise SystemExit(
            "Missing deploy credentials. Set LOUSY_SSH_* in the environment or .env.deploy.local "
            "(see docs/deploy-production.md)."
        )
    return data


def run(client, cmd: str, timeout: int = 300) -> int:
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out.strip():
        print(out.rstrip())
    if err.strip():
        print("stderr:", err.rstrip())
    if code != 0:
        print(f"WARNING: exit {code}")
    return code


def main() -> None:
    missing = [relative for relative in RESUME_FILES if not (ROOT / relative).is_file()]
    if missing:
        raise SystemExit(
            "Missing resume files:\n  - " + "\n  - ".join(missing) + "\n"
            "Ensure inc/resume-data.php exists and run: npm run build:resume-pdf"
        )

    env = load_env(ENV)
    stamp = time.strftime("%Y%m%d-%H%M%S")
    backup = f"/home/uquklkik/theme-backups/resume-{stamp}"

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        env.get("LOUSY_SSH_HOST", ""),
        port=int(env.get("LOUSY_SSH_PORT", "22")),
        username=env.get("LOUSY_SSH_USER", ""),
        password=env.get("LOUSY_SSH_PASSWORD", ""),
        timeout=60,
    )
    sftp = paramiko.SFTPClient.from_transport(client.get_transport())

    try:
        run(client, f"mkdir -p {backup}")
        for relative in RESUME_FILES:
            local = ROOT / relative
            remote = f"{THEME_DIR}/{relative}"
            run(client, f"mkdir -p $(dirname {remote})")
            run(client, f"test -f {remote} && cp -a {remote} {backup}/ 2>/dev/null || true")
            print(f"Uploading {relative}...")
            sftp.put(str(local), remote)

        run(
            client,
            "cd /home/uquklkik/public_html && php -r \"require 'wp-load.php'; "
            "flush_rewrite_rules(false); echo 'rewrite rules flushed';\" 2>&1 | tail -1",
        )
        print(f"Backup: {backup}")
        print("Resume download URL: https://suzyeaston.ca/resume/download/")
        print("Resume page: https://suzyeaston.ca/resume/ (assign Resume template in WP if needed)")
    finally:
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
