# Production deploy (GitHub Actions + SFTP)

Pushes to `main` can deploy automatically when theme or plugin paths change.

## What runs

Workflow: `.github/workflows/deploy-production.yml`

It uses the same script as local deploys: `scripts/deploy-lousy-outages-ssh.py`

- **Theme paths** → `python3 scripts/deploy-lousy-outages-ssh.py --theme`
- **Plugin paths** → build ZIP, then `python3 scripts/deploy-lousy-outages-ssh.py --plugin`

The script uploads over SSH/SFTP to:

`public_html/wp-content/themes/suzyeastonca-main/`

and/or

`public_html/wp-content/plugins/lousy-outages/`

## One-time GitHub setup

In the repo: **Settings → Secrets and variables → Actions → New repository secret**

| Secret | Value |
|--------|--------|
| `LOUSY_SSH_HOST` | SFTP/SSH host (e.g. server IP) |
| `LOUSY_SSH_PORT` | SSH port (often `22` or a custom port) |
| `LOUSY_SSH_USER` | Hosting username |
| `LOUSY_SSH_PASSWORD` | Hosting password |

These match the local-only file `.env.deploy.local` (never commit that file).

**Important:** GitHub Actions does **not** read `.env.deploy.local`. If deploy fails with “Missing deploy credentials”, the secrets above are not set in GitHub yet — earlier workflow runs may have “succeeded” only because no theme/plugin paths changed and the deploy job was skipped.

## Troubleshooting

### `Missing deploy credentials` on GitHub Actions

1. Open the repo on GitHub → **Settings** → **Secrets and variables** → **Actions**
2. Create repository secrets (names must match exactly):
   - `LOUSY_SSH_HOST`
   - `LOUSY_SSH_PORT` (optional; defaults to `22`)
   - `LOUSY_SSH_USER`
   - `LOUSY_SSH_PASSWORD`
3. Copy values from your local `.env.deploy.local`
4. Re-run the failed workflow (Actions → Deploy to Production → Re-run jobs) or push again

Local deploys from Cursor/cloud agents can still work via `.env.deploy.local` even when GitHub Actions fails.

## Local deploy (same script)

```bash
# theme only
python3 scripts/deploy-lousy-outages-ssh.py --theme

# plugin only
python3 scripts/deploy-lousy-outages-ssh.py --plugin

# both
python3 scripts/deploy-lousy-outages-ssh.py
```

Credentials: `.env.deploy.local` or the `LOUSY_SSH_*` environment variables.

## Notes

- Deploy is **not** WordPress auto-sync — it replaces specific files on the server.
- Theme deploy backs up touched files under `theme-backups/` on the host before overwrite.
- Plugin deploy backs up the previous plugin directory under `plugin-backups/`.
- After deploy, the script warms WordPress cron/runtime and touches LiteSpeed cache.
