#!/usr/bin/env python3
"""Use the existing GitHub Actions SSH credentials to create real WordPress Pages.

This does not upload or change public web files. It runs a short, CLI-only PHP
importer against the deployed WordPress installation, validates its read-back
JSON, and deletes the staged PHP file on every outcome.
"""
import json
import os
import shlex
import sys
import uuid
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parent.parent
LOCAL_SCRIPT = ROOT / "scripts" / "wp_ensure_public_pages.php"
REMOTE_HOME = "/home/uquklkik"
REMOTE_ROOT = REMOTE_HOME + "/public_html"
REMOTE_PHP = "/opt/cpanel/ea-php83/root/usr/bin/php"
MARKER = "SUZY_WP_PAGE:"


def main() -> int:
    required = ("LOUSY_SSH_HOST", "LOUSY_SSH_USER", "LOUSY_SSH_PASSWORD")
    missing = [name for name in required if not os.getenv(name)]
    if missing:
        raise RuntimeError("Missing SSH deployment secrets: " + ", ".join(missing))
    if not LOCAL_SCRIPT.is_file():
        raise RuntimeError("WordPress page importer not found in the repository.")

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    # Same SSH trust policy as the established production theme deploy script.
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        hostname=os.environ["LOUSY_SSH_HOST"],
        port=int(os.getenv("LOUSY_SSH_PORT") or "22"),
        username=os.environ["LOUSY_SSH_USER"],
        password=os.environ["LOUSY_SSH_PASSWORD"],
        timeout=60,
        look_for_keys=False,
        allow_agent=False,
    )
    stage = REMOTE_HOME + "/tmp/suzy-wp-page-" + uuid.uuid4().hex + ".php"
    try:
        sftp = client.open_sftp()
        try:
            sftp.put(str(LOCAL_SCRIPT), stage)
            sftp.chmod(stage, 0o600)
        finally:
            sftp.close()

        command = "cd " + shlex.quote(REMOTE_ROOT) + " && " + " ".join(
            map(shlex.quote, (REMOTE_PHP, stage, REMOTE_ROOT))
        )
        _, stdout, stderr = client.exec_command(command, timeout=180)
        code = stdout.channel.recv_exit_status()
        output = stdout.read().decode("utf-8", errors="replace")
        errors = stderr.read().decode("utf-8", errors="replace")
        if code:
            raise RuntimeError("WordPress page registration failed: " + (errors or output)[-1000:])
        lines = [line[len(MARKER):] for line in output.splitlines() if line.startswith(MARKER)]
        if len(lines) != 1:
            raise RuntimeError(
                "WordPress did not return a verified Page confirmation. "
                "Possible WordPress bootstrap/rate limit error: " + output[-700:]
            )
        try:
            result = json.loads(lines[0])
        except ValueError as exc:
            raise RuntimeError("WordPress returned malformed Page confirmation.") from exc
        if (result.get("slug") != "vancouver-events"
            or result.get("status") != "publish"
            or result.get("template") != "page-vancouver-events.php"
            or not str(result.get("url", "")).rstrip("/").endswith("/vancouver-events")
            or not isinstance(result.get("id"), int) or result["id"] <= 0):
            raise RuntimeError("Unexpected WordPress Page read-back values: " + repr(result))
        print(
            "Verified WordPress Page: "
            + str(result["id"]) + " / " + result["action"] + " / " + result["url"]
            + " (template=" + result["template"] + ")"
        )
        return 0
    finally:
        # The importer is staged outside public_html, and is never retained.
        try:
            client.exec_command("rm -f " + shlex.quote(stage), timeout=20)
        finally:
            client.close()


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, ValueError, RuntimeError, paramiko.SSHException) as exc:
        print("ERROR: " + str(exc), file=sys.stderr)
        sys.exit(1)
