#!/usr/bin/env python3
"""Run a snippet of PHP (with WordPress loaded) or a shell command on the live host.

Usage:
  python3 scripts/lo-remote.py sh "ls -la"
  python3 scripts/lo-remote.py php /path/to/local-snippet.php
Reads credentials from /workspace/.env.deploy.local
"""
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parent.parent
ENV = ROOT / ".env.deploy.local"
WP_ROOT = "/home/uquklkik/public_html"
REMOTE_TMP = "/home/uquklkik/tmp"


def load_env() -> dict:
    data = {}
    for line in ENV.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        data[k.strip()] = v.strip()
    return data


def connect():
    env = load_env()
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        env["LOUSY_SSH_HOST"],
        port=int(env.get("LOUSY_SSH_PORT", "22")),
        username=env["LOUSY_SSH_USER"],
        password=env["LOUSY_SSH_PASSWORD"],
        timeout=30,
    )
    return client


def run(client, cmd: str, timeout: int = 300) -> int:
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out:
        print(out, end="" if out.endswith("\n") else "\n")
    if err.strip():
        print("stderr:", err, file=sys.stderr)
    return code


def main() -> None:
    if len(sys.argv) < 3:
        raise SystemExit(__doc__)
    mode, arg = sys.argv[1], sys.argv[2]
    client = connect()
    try:
        if mode == "sh":
            code = run(client, arg)
        elif mode == "php":
            local = Path(arg)
            remote = f"{REMOTE_TMP}/lo-snippet.php"
            run(client, f"mkdir -p {REMOTE_TMP}")
            sftp = paramiko.SFTPClient.from_transport(client.get_transport())
            sftp.put(str(local), remote)
            sftp.close()
            code = run(client, f"cd {WP_ROOT} && php {remote} 2>&1")
            run(client, f"rm -f {remote}")
        else:
            raise SystemExit("mode must be sh or php")
    finally:
        client.close()
    sys.exit(code)


if __name__ == "__main__":
    main()
