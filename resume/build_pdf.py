#!/usr/bin/env python3
"""Build Suzy_Easton_Resume.pdf via Chrome headless print-to-PDF."""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
HTML = ROOT / "resume.html"
PDF = ROOT / "Suzy_Easton_Resume.pdf"
CHROME = Path("/usr/bin/google-chrome-stable")
if not CHROME.exists():
    CHROME = Path("/usr/bin/google-chrome")


def main() -> int:
    if not HTML.exists():
        print(f"missing {HTML}", file=sys.stderr)
        return 1
    if not CHROME.exists():
        print("google-chrome not found", file=sys.stderr)
        return 1

    PDF.unlink(missing_ok=True)
    # Allow local @font-face files; brief settle so font-display:block paints.
    cmd = [
        str(CHROME),
        "--headless=new",
        "--disable-gpu",
        "--allow-file-access-from-files",
        "--no-pdf-header-footer",
        "--no-margins",
        "--virtual-time-budget=5000",
        f"--print-to-pdf={PDF}",
        HTML.as_uri(),
    ]
    print(" ".join(cmd))
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        print(result.stdout)
        print(result.stderr, file=sys.stderr)
        return result.returncode
    if not PDF.exists():
        print("PDF was not created", file=sys.stderr)
        print(result.stderr, file=sys.stderr)
        return 1
    print(f"wrote {PDF} ({PDF.stat().st_size} bytes)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
