# Meanwhile: publishing WordPress posts from GitHub

## What happens

The main-branch GitHub Action `.github/workflows/publish-meanwhile.yml`
imports Markdown posts and their photos into the actual WordPress database and
Media Library over the already established SSH hosting connection. This is
**separate** from the theme deploy and does not require a WordPress API password.

- Edit a Markdown file in `content/meanwhile/` and set `status: publish` only when ready.
- Add its original photos as individual files under
  `content/meanwhile/media/<slug>/`, **or** put them into one ZIP at
  `content/meanwhile/media/<slug>.zip`, with filenames at the ZIP root.
- Use markers `<!-- PHOTO: filename.jpg | descriptive alt text | optional caption -->`.
- `featured_image` must be one of those markers.
- Merge the reviewed change into `main`. GitHub Actions validates every photo,
  imports it to the WordPress Media Library, publishes/updates the post, sets
  its featured image, assigns a category, and prints its live permalink.

Posts with `status: pending-wordpress-import` (or any status other than
`publish`) are **not published**, even when pushed to main. Never put SSH
passwords, database credentials, WordPress app passwords, or configuration files
with secrets into the repository.

## One-time hosting access configuration

The workflow uses existing secrets `LOUSY_SSH_HOST`, `LOUSY_SSH_USER`,
`LOUSY_SSH_PASSWORD`, and the optional `LOUSY_SSH_PORT`. GitHub deployment
documentation warns that these may not yet be configured in Actions, even if
a local deploy worked. Confirm in **Settings > Secrets and variables > Actions**.
Secrets are write-only; never paste their values into a ChatGPT message.

**Additional safety check:** add `LOUSY_SSH_KNOWN_HOSTS`, containing the
server's verified OpenSSH `known_hosts` entry. Obtain the entry from
`ssh-keyscan -p PORT HOSTNAME` and verify its key fingerprint with your
hosting provider using a trusted channel before adding it as a repository
secret. Do not blindly trust the first key returned by an unknown host.

The publishing script refuses unknown SSH host keys. It locates WordPress at
`/home/uquklkik/public_html`, consistent with the existing production deploy.
If hosting layout changes, set `SE_WP_HOME` in the action/script environment.

## Current post: The Toaster Was a Decoy

File: `content/meanwhile/2026-11-the-toaster-was-a-decoy.md`

1. Put all eleven supplied photographs (the matching filenames) in the single
   archive `content/meanwhile/media/the-toaster-was-a-decoy.zip`.
2. When the archive is committed, set `status: publish` in the article.
3. Merge the PR to `main`, or manually run **Publish Meanwhile to WordPress**
   from GitHub Actions. A second run updates the post with the same slug,
   rather than making duplicates.
4. Verify the post permalink, the inline images, and its social preview.

## Troubleshooting

The Action runs validation before connecting to hosting. A missing photograph
is a hard error and WordPress remains unchanged. Missing deployment secrets or
an unknown SSH host also abort before posting. WordPress image upload failures
stop the import rather than publishing an article with broken image references.

The article's title, slug, category, content, excerpt, and featured image come
from the Markdown file. WordPress media assets are deduplicated by
`_se_meanwhile_asset_key`, based on post slug and source filename. To replace
an existing photo, use a new filename (the old asset is intentionally preserved
in the Media Library). GitHub stores source image archives; WordPress stores
the published images.
