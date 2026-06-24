# Local WordPress + Front-End QA Environment

This repo can run as a local WordPress site through Docker Compose. The setup mounts the current repository as the active theme and mounts the standalone Lousy Outages plugin copy for plugin-flow testing.

## Requirements

- Docker with Docker Compose support
- Node.js 20+ recommended
- npm

Docker Desktop is free for personal use under Docker's current licensing. If you are using this inside a larger commercial organization, check Docker's current subscription terms.

## First-time setup

```bash
npm install
npm run local:start
npm run local:setup
```

The setup script installs WordPress if needed, activates the theme, activates the Lousy Outages plugin when available, creates the key QA pages, assigns templates, sets the homepage, and flushes permalinks.

## Local URLs

- Site: <http://localhost:8080>
- Admin: <http://localhost:8080/wp-admin/>
- Username: `admin`
- Password: `password`

You can override the defaults with environment variables:

```bash
LOCAL_WP_PORT=8090 \
LOCAL_WP_ADMIN_USER=suzy \
LOCAL_WP_ADMIN_PASSWORD='change-me' \
npm run local:start

LOCAL_WP_PORT=8090 \
LOCAL_WP_ADMIN_USER=suzy \
LOCAL_WP_ADMIN_PASSWORD='change-me' \
npm run local:setup
```

## Daily use

```bash
npm run local:start
npm run local:setup
```

Stop containers without deleting the database:

```bash
npm run local:stop
```

Reset WordPress and the database completely:

```bash
npm run local:reset
```

## Front-end smoke tests

Install Playwright browsers once:

```bash
npx playwright install chromium
```

Run the smoke checks:

```bash
npm run test:e2e
```

Run visibly in a browser:

```bash
npm run test:e2e:headed
```

The Playwright smoke suite checks critical pages, browser console/page errors, the contact modal, Lousy Outages dashboard rendering, and Track Analyzer form controls.

## Notes

- External AI/API-dependent features still need keys in your real environment; the local setup is intended to validate front-end behavior, WordPress routing/templates, REST surfaces, and graceful UI states.
- The WordPress container mounts this repository at `wp-content/themes/suzyeastonca`.
- The Lousy Outages plugin is mounted from `plugins/lousy-outages` at `wp-content/plugins/lousy-outages`.
