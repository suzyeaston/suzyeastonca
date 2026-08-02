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
