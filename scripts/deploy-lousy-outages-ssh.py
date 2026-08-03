#!/usr/bin/env python3
"""Deploy the Lousy Outages plugin (and the theme files it renders inside) over SFTP.

Reads credentials from /workspace/.env.deploy.local:
    LOUSY_SSH_HOST, LOUSY_SSH_PORT, LOUSY_SSH_USER, LOUSY_SSH_PASSWORD

Usage:
    python3 scripts/deploy-lousy-outages-ssh.py            # plugin + theme files
    python3 scripts/deploy-lousy-outages-ssh.py --plugin   # plugin only
    python3 scripts/deploy-lousy-outages-ssh.py --theme    # theme files only
"""
import os
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parent.parent
ENV = ROOT / ".env.deploy.local"
DEPLOY_ENV_KEYS = (
    "LOUSY_SSH_HOST",
    "LOUSY_SSH_PORT",
    "LOUSY_SSH_USER",
    "LOUSY_SSH_PASSWORD",
)
ZIP = Path("/tmp/lousy-outages-deploy.zip")
DIST_ZIP = ROOT / "dist" / "lousy-outages.zip"
HOME = "/home/uquklkik"
REMOTE_ZIP = f"{HOME}/tmp/lousy-outages-deploy.zip"
PLUGIN_REL = "public_html/wp-content/plugins"
PLUGIN_DIR = f"{HOME}/{PLUGIN_REL}/lousy-outages"
THEME_DIR = f"{HOME}/public_html/wp-content/themes/suzyeastonca-main"

# Theme files the board renders inside. Keep this list tight: the whole repo is the
# theme, but only these files affect the Lousy Outages page.
THEME_FILES = [
    "functions.php",
    "inc/home-translink-alerts.php",
    "inc/home-yvr-broadcaster.php",
    "inc/home-yvr-audio-channels.php",
    "page-home.php",
    "page-lousy-outages.php",
    "parts/lousy-outages-teaser.php",
    "parts/home-yvr-channel-buttons.php",
    "assets/css/home-hero-cabinet.css",
    "assets/css/home-yvr-radar-deck.css",
    "assets/audio/yvr/rain-loop.mp3",
    "assets/audio/yvr/skytrain-rumble.mp3",
    "assets/audio/yvr/ferry-horn.mp3",
    "js/home-hero-map.js",
    "js/home-yvr-broadcaster.js",
    "assets/css/lousy-outages-page.css",
    "assets/css/lousy-outages-theme-isolation.css",
    "assets/css/lousy-outages-teaser.css",
    "assets/js/lousy-outages-teaser.js",
]


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
            k, v = line.split("=", 1)
            data.setdefault(k.strip(), v.strip())
    if not data.get("LOUSY_SSH_HOST") or not data.get("LOUSY_SSH_USER") or not data.get("LOUSY_SSH_PASSWORD"):
        hint = (
            "For GitHub Actions, add LOUSY_SSH_* as repository secrets "
            "(Settings → Secrets and variables → Actions). "
            "See docs/deploy-production.md."
        )
        raise SystemExit(
            f"Missing deploy credentials. Set {', '.join(DEPLOY_ENV_KEYS)} in the environment "
            f"or in {path}. {hint}"
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


def deploy_plugin(client, sftp, stamp: str) -> None:
    if DIST_ZIP.is_file():
        ZIP.write_bytes(DIST_ZIP.read_bytes())
    elif not ZIP.is_file():
        raise SystemExit(f"Build ZIP missing: {ZIP}. Run scripts/build-lousy-outages-release.sh first.")

    print(f"Uploading plugin zip ({ZIP.stat().st_size} bytes)...")
    sftp.put(str(ZIP), REMOTE_ZIP)

    backup = f"{HOME}/plugin-backups/lousy-outages-{stamp}"
    run(client, f"test -d {PLUGIN_DIR} && cp -a {PLUGIN_DIR} {backup} || echo 'no existing plugin dir'")
    # Remove first so files deleted from the release do not linger in the install.
    run(client, f"rm -rf {PLUGIN_DIR}")
    run(client, f"cd {HOME}/{PLUGIN_REL} && unzip -oq {REMOTE_ZIP} && echo 'plugin extracted'")
    run(client, f"grep -m1 'Version:' {PLUGIN_DIR}/lousy-outages.php")
    run(client, f"rm -f {REMOTE_ZIP}")
    print(f"Plugin backup: {backup}")


def deploy_theme(client, sftp, stamp: str) -> None:
    backup = f"{HOME}/theme-backups/{stamp}"
    run(client, f"mkdir -p {backup}")
    for relative in THEME_FILES:
        local = ROOT / relative
        if not local.is_file():
            print(f"SKIP missing {relative}")
            continue
        remote = f"{THEME_DIR}/{relative}"
        run(client, f"mkdir -p $(dirname {remote}) && cp -a {remote} {backup}/$(basename {remote}) 2>/dev/null || true")
        print(f"Uploading {relative}...")
        sftp.put(str(local), remote)
    print(f"Theme backup: {backup}")


def main() -> None:
    args = set(sys.argv[1:])
    do_plugin = "--theme" not in args
    do_theme = "--plugin" not in args

    env = load_env(ENV)
    stamp = time.strftime("%Y%m%d-%H%M%S")

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
        run(client, f"mkdir -p {HOME}/tmp {HOME}/plugin-backups {HOME}/theme-backups")
        if do_plugin:
            deploy_plugin(client, sftp, stamp)
        if do_theme:
            deploy_theme(client, sftp, stamp)

        # Warm the runtime so schema upgrades and a fresh collection run immediately.
        run(
            client,
            "cd /home/uquklkik/public_html && php -r \"define('DOING_CRON', true); require 'wp-load.php'; "
            "do_action('init'); if (function_exists('wp_cron')) { wp_cron(); } echo 'runtime warmed';\" 2>&1 | tail -3",
        )
        run(client, "cd /home/uquklkik/public_html && ls -d wp-content/*/litespeed* 2>/dev/null | head -1 || true")
    finally:
        sftp.close()
        client.close()

    print("Done.")


if __name__ == "__main__":
    main()
