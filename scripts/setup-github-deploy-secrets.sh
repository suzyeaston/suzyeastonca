#!/usr/bin/env bash
# Push LOUSY_SSH_* from .env.deploy.local to GitHub Actions repository secrets.
# Run locally after: gh auth login (needs repo admin / secrets permission).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env.deploy.local"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE — copy scripts/env.deploy.example to .env.deploy.local and fill in credentials." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

for key in LOUSY_SSH_HOST LOUSY_SSH_PORT LOUSY_SSH_USER LOUSY_SSH_PASSWORD; do
  val="${!key:-}"
  if [[ -z "$val" ]]; then
    echo "Missing $key in $ENV_FILE" >&2
    exit 1
  fi
done

echo "Setting GitHub Actions secrets for $(gh repo view --json nameWithOwner -q .nameWithOwner)..."
gh secret set LOUSY_SSH_HOST --body "$LOUSY_SSH_HOST"
gh secret set LOUSY_SSH_PORT --body "$LOUSY_SSH_PORT"
gh secret set LOUSY_SSH_USER --body "$LOUSY_SSH_USER"
gh secret set LOUSY_SSH_PASSWORD --body "$LOUSY_SSH_PASSWORD"
echo "Done. Re-run Deploy to Production in GitHub Actions."
