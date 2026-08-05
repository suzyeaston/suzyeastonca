#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v gh >/dev/null 2>&1; then
  echo "Install GitHub CLI (gh) and run: gh auth login"
  exit 1
fi

for file in inc/resume-data.php assets/resume/suzy-easton-bsa-resume.html; do
  if [[ ! -f "$file" ]]; then
    echo "Missing $file"
    echo "Copy inc/resume-data.example.php to inc/resume-data.php and fill it in, then run: npm run build:resume-pdf"
    exit 1
  fi
done

gh secret set RESUME_DATA_PHP < inc/resume-data.php
gh secret set RESUME_HTML < assets/resume/suzy-easton-bsa-resume.html

echo "Stored RESUME_DATA_PHP and RESUME_HTML in GitHub Actions secrets."
echo "Deploy with: gh workflow run deploy-resume.yml"
