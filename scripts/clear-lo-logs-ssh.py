#!/usr/bin/env python3
"""Truncate oversized WordPress/Lousy Outages logs on WHC via SSH."""
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
ENV = ROOT / ".env.deploy.local"


def load_env(path: Path) -> dict:
    data = {}
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
    return code, stdout.read().decode(), stderr.read().decode()


def main() -> None:
    if not ENV.is_file():
        sys.exit(f"Missing {ENV}")

    env = load_env(ENV)
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        env["LOUSY_SSH_HOST"],
        port=int(env.get("LOUSY_SSH_PORT", "22")),
        username=env["LOUSY_SSH_USER"],
        password=env["LOUSY_SSH_PASSWORD"],
        timeout=30,
    )

    public = "/home/uquklkik/public_html"
    logs = [
        f"{public}/wp-content/debug.log",
        f"{public}/error_log",
        "/home/uquklkik/error_log",
    ]

    print("=== Log sizes BEFORE ===")
    for path in logs:
        code, out, _ = run(client, f"test -f {path} && ls -lh {path} || echo 'missing: {path}'")
        print(out.strip())

    print("\n=== Truncating debug.log and rotating large error logs ===")
    cmds = [
        # Backup then truncate debug.log (primary culprit)
        f"if [ -f {public}/wp-content/debug.log ]; then "
        f"cp {public}/wp-content/debug.log /home/uquklkik/tmp/debug.log.bak-$(date +%Y%m%d-%H%M%S) 2>/dev/null; "
        f": > {public}/wp-content/debug.log; echo 'truncated debug.log'; fi",
        # Truncate public error_log if > 1MB
        f"if [ -f {public}/error_log ] && [ $(stat -c%s {public}/error_log 2>/dev/null || echo 0) -gt 1048576 ]; then "
        f"cp {public}/error_log /home/uquklkik/tmp/error_log.bak-$(date +%Y%m%d-%H%M%S); : > {public}/error_log; echo 'truncated public error_log'; fi",
        f"if [ -f /home/uquklkik/error_log ] && [ $(stat -c%s /home/uquklkik/error_log 2>/dev/null || echo 0) -gt 1048576 ]; then "
        f": > /home/uquklkik/error_log; echo 'truncated home error_log'; fi",
    ]
    for cmd in cmds:
        code, out, err = run(client, cmd)
        if out.strip():
            print(out.strip())
        if err.strip():
            print("stderr:", err.strip())

    print("\n=== Log sizes AFTER ===")
    for path in logs:
        code, out, _ = run(client, f"test -f {path} && ls -lh {path} || echo 'missing: {path}'")
        print(out.strip())

    print("\n=== Trigger wp-cron via HTTP ===")
    code, out, err = run(
        client,
        f"curl -sS -o /dev/null -w 'wp-cron http:%{{http_code}}' 'https://www.suzyeaston.ca/wp-cron.php?doing_wp_cron=1' --max-time 120",
        timeout=130,
    )
    print(out.strip() or err.strip())

    print("\n=== Run canonical refresh (ea-php83 inline; cPanel php wrapper cats .php files) ===")
    php_bin = "/opt/cpanel/ea-php83/root/usr/bin/php"
    refresh_inline = (
        f"{php_bin} -d memory_limit=768M -r "
        "'"
        "$_SERVER['HTTP_HOST'] = 'www.suzyeaston.ca'; "
        "$_SERVER['REQUEST_URI'] = '/'; "
        "$_SERVER['SERVER_NAME'] = 'www.suzyeaston.ca'; "
        "define('DOING_CRON', true); "
        "define('WP_USE_THEMES', false); "
        f"require '{public}/wp-load.php'; "
        "if (!function_exists('lousy_outages_refresh_official_providers')) { echo 'no refresh fn'; exit(1); } "
        "$r = lousy_outages_refresh_official_providers(true); "
        "echo json_encode($r) . PHP_EOL; "
        "echo 'last_fetched_iso: ' . get_option('lousy_outages_last_fetched_iso') . PHP_EOL; "
        "echo 'last_refresh_finish: ' . get_option('lousy_outages_last_refresh_finish') . PHP_EOL; "
        "' 2>&1"
    )
    code, out, err = run(client, refresh_inline, timeout=600)
    print(out[:4000] if out else "")
    if err.strip():
        print("stderr:", err[:2000])
    if code != 0:
        print(f"refresh exit code: {code}")

    print("\n=== Last error_log lines ===")
    code, out, _ = run(client, f"tail -15 {public}/error_log 2>/dev/null || tail -15 /home/uquklkik/error_log")
    print(out.strip())

    client.close()
    print("\nDone.")


if __name__ == "__main__":
    main()
