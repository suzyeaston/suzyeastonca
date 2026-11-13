#!/usr/bin/env python3
"""Deploy the Lousy Outages plugin and selected production theme files over SFTP.

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
sys.path.insert(0, str(ROOT / "scripts"))
from theme_deploy_manifest import THEME_FILES, validate_theme_manifest

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
# The cPanel default CLI is PHP 7.4, but this WordPress plugin requires PHP 8+.
REMOTE_PHP = "/opt/cpanel/ea-php83/root/usr/bin/php"
PLUGIN_REL = "public_html/wp-content/plugins"
PLUGIN_DIR = f"{HOME}/{PLUGIN_REL}/lousy-outages"
THEME_DIR = f"{HOME}/public_html/wp-content/themes/suzyeastonca-main"


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


def deploy_plugin(client, sftp, stamp: str) -> str:
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
    return backup


def deploy_theme(client, sftp, stamp: str) -> None:
    errors = validate_theme_manifest()
    if errors:
        raise SystemExit("\n".join(errors))

    backup = f"{HOME}/theme-backups/{stamp}"
    run(client, f"mkdir -p {backup}")
    for relative in THEME_FILES:
        local = ROOT / relative
        remote = f"{THEME_DIR}/{relative}"
        run(client, f"mkdir -p $(dirname {remote}) && cp -a {remote} {backup}/$(basename {remote}) 2>/dev/null || true")
        print(f"Uploading {relative}...")
        sftp.put(str(local), remote)
    print(f"Theme backup: {backup}")


def purge_litespeed_cache(client) -> None:
    run(
        client,
        f"cd /home/uquklkik/public_html && {REMOTE_PHP} -r \""
        "require 'wp-load.php'; "
        "if (class_exists('LiteSpeed\\\\Cache\\\\Purge')) { "
        "  \\LiteSpeed\\Cache\\Purge::purge_all(); echo 'LiteSpeed purged (Purge class)'; "
        "} elseif (class_exists('LiteSpeed_Cache_API')) { "
        "  LiteSpeed_Cache_API::purge_all(); echo 'LiteSpeed purged (API)'; "
        "} elseif (function_exists('do_action')) { "
        "  do_action('litespeed_purge_all'); echo 'LiteSpeed purge action dispatched'; "
        "} else { echo 'LiteSpeed purge API not found; CSS uses filemtime cache busting'; }"
        "\" 2>&1",
    )


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
        backup = deploy_plugin(client, sftp, stamp) if do_plugin else ""
        if do_theme:
            deploy_theme(client, sftp, stamp)

        # WordPress can leave this flag behind when an update is interrupted.
        run(client, f"rm -f {HOME}/public_html/.maintenance && echo 'maintenance flag cleared'")

        # WordPress itself fires init during wp-load. Do not fire it twice.
        # The default PHP CLI is 7.4, which cannot parse plugin PHP 8 syntax.
        code = run(
            client,
            f"cd {HOME}/public_html && {REMOTE_PHP} -r \""
            "define('DOING_CRON', true); require 'wp-load.php'; echo 'runtime warmed';\" 2>&1",
        )
        if code != 0:
            if backup:
                print("Runtime bootstrap failed; restoring previous plugin directory...")
                run(client, f"test -d {backup} && rm -rf {PLUGIN_DIR} && cp -a {backup} {PLUGIN_DIR}")
            raise RuntimeError("WordPress bootstrap failed; plugin deployment rolled back where a backup exists")

        # Plugin-only deploys also need cache invalidation for the version/asset URLs.
        if do_plugin or do_theme:
            purge_litespeed_cache(client)
    finally:
        sftp.close()
        client.close()

    print("Done.")


if __name__ == "__main__":
    main()
