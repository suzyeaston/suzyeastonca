#!/usr/bin/env python3
"""Deploy lousy-outages plugin via SFTP + SSH. Reads /workspace/.env.deploy.local"""
import os
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path("/workspace")
ENV = ROOT / ".env.deploy.local"
ZIP = Path("/tmp/lousy-outages-deploy.zip")
REMOTE_ZIP = "/home/uquklkik/tmp/lousy-outages-deploy.zip"
PLUGIN_REL = "public_html/wp-content/plugins"
PLUGIN_DIR = f"/home/uquklkik/{PLUGIN_REL}/lousy-outages"


def load_env(path: Path) -> dict:
    data = {}
    if not path.is_file():
        raise SystemExit(f"Missing {path}")
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        data[k.strip()] = v.strip()
    return data


def run(client, cmd: str, timeout: int = 120) -> tuple[int, str, str]:
    stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode()
    err = stderr.read().decode()
    return code, out, err


def main() -> None:
    if not ZIP.is_file():
        raise SystemExit(f"Build ZIP missing: {ZIP}")

    env = load_env(ENV)
    host = env.get("LOUSY_SSH_HOST", "")
    port = int(env.get("LOUSY_SSH_PORT", "22"))
    user = env.get("LOUSY_SSH_USER", "")
    password = env.get("LOUSY_SSH_PASSWORD", "")

    stamp = time.strftime("%Y%m%d-%H%M%S")
    backup = f"/home/uquklkik/plugin-backups/lousy-outages-{stamp}"

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(host, port=port, username=user, password=password, timeout=30)

    transport = client.get_transport()
    sftp = paramiko.SFTPClient.from_transport(transport)
    print(f"Uploading {ZIP} ({ZIP.stat().st_size} bytes)...")
    sftp.put(str(ZIP), REMOTE_ZIP)
    sftp.close()

    steps = [
        f"mkdir -p /home/uquklkik/tmp /home/uquklkik/plugin-backups",
        f"test -d {PLUGIN_DIR} && cp -a {PLUGIN_DIR} {backup} || echo 'no existing plugin dir'",
        f"cd /home/uquklkik/{PLUGIN_REL} && unzip -o {REMOTE_ZIP}",
        f"grep -n 'callRefreshEndpoint' {PLUGIN_DIR}/assets/lousy-outages.js | head -3",
        f"grep 'LOUSY_OUTAGES_VERSION' {PLUGIN_DIR}/lousy-outages.php | head -1",
        f"cd /home/uquklkik/public_html && php -r \"define('DOING_CRON', true); require 'wp-load.php'; if (function_exists('wp_cron')) {{ wp_cron(); echo 'wp_cron done'; }} if (function_exists('do_action')) {{ do_action('lousy_outages_refresh_official_providers'); echo ' refresh hook'; }}\" 2>&1 | tail -5",
        f"rm -f {REMOTE_ZIP}",
    ]

    for cmd in steps:
        print(f"\n>>> {cmd}")
        code, out, err = run(client, cmd, timeout=300)
        if out:
            print(out)
        if err.strip():
            print("stderr:", err)
        if code != 0:
            print(f"WARNING: exit {code}")

    client.close()
    print(f"\nDone. Backup: {backup}")


if __name__ == "__main__":
    main()
