# MACHINE VISIONS (`/ai-art/`)

Public exhibition layer for AI-assisted films, stills, loops, and process notes.

Workshop app: **Moving Picture Machine** (sibling repo, local-first).  
Public title / route constants live in `inc/ai-art.php` and `assets/data/ai-art/works.json`.

## What ships

- WordPress page template: `page-ai-art.php` (Template Name: **AI Art / MACHINE VISIONS**)
- Catalog: `assets/data/ai-art/works.json` (published works only render)
- Drafts: `assets/data/ai-art/drafts/` (never public)
- Media: `assets/data/ai-art/media/<slug>/`
- Publish schema: `schemas/ai-art-publish-bundle.schema.json`
- Import CLI: `scripts/import-ai-art-bundle.js`

## Create the WP page

In wp-admin (or local Docker):

1. Pages → Add New
2. Title: `MACHINE VISIONS` (or `AI Art`)
3. Slug: `ai-art`
4. Template: **AI Art / MACHINE VISIONS**
5. Publish

Local helper: after `npm run local:setup`, create the page once if it is missing.

## Add the first real artwork

Do **not** invent finished pieces. Prefer exporting a publish bundle from Moving Picture Machine.

```bash
# Preview (no writes)
node scripts/import-ai-art-bundle.js /path/to/publish/<artwork-slug>

# Apply catalog + copy media
node scripts/import-ai-art-bundle.js /path/to/publish/<artwork-slug> --apply
```

Manual path:

1. Put web-ready media under `assets/data/ai-art/media/<slug>/`
2. Append a validated record to `assets/data/ai-art/works.json` with `"status": "published"`
3. Keep private prompts out unless you intentionally set `promptExcerpt`

## Bundle layout

```text
publish/<artwork-slug>/
  manifest.json
  media/<web-ready-primary-file>
  media/<poster>
  media/<thumbnail>
  captions/<optional-vtt>
  provenance/<optional-sidecar>
```

Public metadata only. No API keys, absolute local paths, full private prompts, or rejected takes.

## Empty state

Production catalog starts with `"works": []`. The page shows **FIRST TRANSMISSION IN RENDER QUEUE** until a published work exists.
