#!/usr/bin/env python3
"""Build Suzy_Easton_Resume.pdf locally via WeasyPrint (no browser/network)."""

from pathlib import Path

from weasyprint import HTML

ROOT = Path(__file__).resolve().parent
HTML(string=(ROOT / "resume.html").read_text(encoding="utf-8"), base_url=str(ROOT)).write_pdf(
    ROOT / "Suzy_Easton_Resume.pdf"
)
print(f"Wrote {ROOT / 'Suzy_Easton_Resume.pdf'}")
