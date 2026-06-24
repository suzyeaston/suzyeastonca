#!/usr/bin/env bash
set -euo pipefail

DC="${DC:-docker compose}"
LOCAL_WP_URL="${LOCAL_WP_URL:-http://localhost:${LOCAL_WP_PORT:-8080}}"
LOCAL_WP_TITLE="${LOCAL_WP_TITLE:-Suzy Easton Local QA}"
LOCAL_WP_ADMIN_USER="${LOCAL_WP_ADMIN_USER:-admin}"
LOCAL_WP_ADMIN_PASSWORD="${LOCAL_WP_ADMIN_PASSWORD:-password}"
LOCAL_WP_ADMIN_EMAIL="${LOCAL_WP_ADMIN_EMAIL:-suzy.local@example.test}"
THEME_SLUG="${LOCAL_WP_THEME_SLUG:-suzyeastonca}"
PLUGIN_SLUG="${LOCAL_WP_LOUSY_PLUGIN:-lousy-outages}"

wp_cli() {
  ${DC} run --rm wpcli "$@"
}

wait_for_wordpress() {
  local attempts=60
  echo "Waiting for WordPress at ${LOCAL_WP_URL}..."
  until curl -fsS "${LOCAL_WP_URL}/wp-admin/install.php" >/dev/null 2>&1 || curl -fsS "${LOCAL_WP_URL}" >/dev/null 2>&1; do
    attempts=$((attempts - 1))
    if [[ ${attempts} -le 0 ]]; then
      echo "WordPress did not become reachable at ${LOCAL_WP_URL}." >&2
      exit 1
    fi
    sleep 2
  done
}

create_or_update_page() {
  local title="$1"
  local slug="$2"
  local template="$3"
  local content="${4:-}"
  local id

  id="$(wp_cli post list --post_type=page --name="${slug}" --field=ID --allow-root 2>/dev/null || true)"
  if [[ -z "${id}" ]]; then
    id="$(wp_cli post create \
      --post_type=page \
      --post_status=publish \
      --post_title="${title}" \
      --post_name="${slug}" \
      --post_content="${content}" \
      --porcelain \
      --allow-root)"
  else
    wp_cli post update "${id}" \
      --post_status=publish \
      --post_title="${title}" \
      --post_content="${content}" \
      --allow-root >/dev/null
  fi

  if [[ -n "${template}" ]]; then
    wp_cli post meta update "${id}" _wp_page_template "${template}" --allow-root >/dev/null
  fi

  echo "${id}"
}

wait_for_wordpress

if ! wp_cli core is-installed --allow-root >/dev/null 2>&1; then
  wp_cli core install \
    --url="${LOCAL_WP_URL}" \
    --title="${LOCAL_WP_TITLE}" \
    --admin_user="${LOCAL_WP_ADMIN_USER}" \
    --admin_password="${LOCAL_WP_ADMIN_PASSWORD}" \
    --admin_email="${LOCAL_WP_ADMIN_EMAIL}" \
    --skip-email \
    --allow-root
fi

wp_cli theme activate "${THEME_SLUG}" --allow-root

if wp_cli plugin is-installed "${PLUGIN_SLUG}" --allow-root >/dev/null 2>&1; then
  wp_cli plugin activate "${PLUGIN_SLUG}" --allow-root || true
fi

home_id="$(create_or_update_page "Home" "home" "page-home.php")"
create_or_update_page "Lousy Outages" "lousy-outages" "page-lousy-outages.php" >/dev/null
create_or_update_page "Work With Suzy" "work-with-suzy" "page-work-with-suzy.php" >/dev/null
create_or_update_page "Projects" "projects" "page-projects.php" >/dev/null
create_or_update_page "Suzy's Track Analyzer" "suzys-track-analyzer" "page-track-analyzer.php" >/dev/null
create_or_update_page "Gastown Simulator" "gastown-sim" "page-gastown-sim.php" >/dev/null
create_or_update_page "ASMR Lab" "asmr-lab" "page-asmr-lab.php" >/dev/null
create_or_update_page "Albini Q&A" "albini-qa" "page-albini-qa.php" >/dev/null
create_or_update_page "Music Releases" "music-releases" "page-music-releases.php" >/dev/null
create_or_update_page "Riff Generator" "riff-generator" "page-riff-generator.php" >/dev/null
create_or_update_page "Bio" "bio" "page-bio.php" >/dev/null
create_or_update_page "VanOps Radar" "vanops-radar" "page-vanops-radar.php" >/dev/null

wp_cli option update show_on_front page --allow-root >/dev/null
wp_cli option update page_on_front "${home_id}" --allow-root >/dev/null
wp_cli rewrite structure '/%postname%/' --allow-root >/dev/null
wp_cli rewrite flush --hard --allow-root >/dev/null

cat <<SUMMARY

Local WordPress QA environment is ready.
URL:      ${LOCAL_WP_URL}
Admin:    ${LOCAL_WP_URL}/wp-admin/
Username: ${LOCAL_WP_ADMIN_USER}
Password: ${LOCAL_WP_ADMIN_PASSWORD}

Run FE checks with:
  npm run test:e2e

SUMMARY
