#!/usr/bin/env bash
set -euo pipefail

# Restore resume tooling into private-resume/ from git history.
# Nothing in private-resume/ is committed — keep resume content local only.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SOURCE_COMMIT="${1:-9e5d024}"
DEST="$ROOT/private-resume"

FILES=(
  page-resume.php
  inc/resume-data.example.php
  assets/css/resume.css
  js/resume.js
  assets/resume/README.md
  scripts/generate-resume-pdf.js
  scripts/export-resume-local.js
  scripts/deploy-resume-ssh.py
  scripts/setup-github-resume-secrets.sh
  .github/workflows/deploy-resume.yml
)

mkdir -p "$DEST"

for file in "${FILES[@]}"; do
  target="$DEST/$file"
  mkdir -p "$(dirname "$target")"
  git show "${SOURCE_COMMIT}:${file}" > "$target"
  echo "Wrote $target"
done

cat > "$DEST/README.md" <<'EOF'
# Private resume (local only)

This folder is gitignored. Nothing here is published to GitHub.

## Setup

1. Copy `inc/resume-data.example.php` to `inc/resume-data.php` and fill in your details.
2. Add your HTML resume to `assets/resume/resume.html` (or keep your existing filename and update scripts).
3. Generate PDF: `npm run build:resume-pdf` (from this folder's package.json if you add one, or run the script directly).

## Deploy to production

Upload files via SFTP using `scripts/deploy-resume-ssh.py` — credentials stay in `.env.deploy.local` (also gitignored).

Do not commit resume content, PDFs, or contact details to the public repo.
EOF

echo ""
echo "Private resume tooling restored to: $DEST"
echo "Edit files there and deploy manually. This folder never goes to GitHub."
