# Security Policy

## Secret Handling
- Never commit API keys, tokens, passwords, or other secrets to this repository.
- Store production secrets in `wp-config.php` or environment variables.
- If any secret reaches GitHub, rotate/revoke it immediately.

## Expected Secrets
The current application expects these secrets to be configured outside tracked source:
- `OPENAI_API_KEY`
- `THE_ODDS_API_KEY`
- `CLOUDFLARE_TURNSTILE_SITE_KEY`
- `CLOUDFLARE_TURNSTILE_SECRET_KEY`

## Pre-push Checks
- Run a secret scan before pushing (for example, with `gitleaks` or GitGuardian CLI).
- Enable GitHub Secret Scanning and Push Protection where available.

## Safe Collaboration Rules
- Do not paste real secrets into Codex prompts, commit messages, README/docs files, screenshots, or issues.
- Documentation examples must use placeholders only.
