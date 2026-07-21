#!/usr/bin/env bash
set -euo pipefail
mode="${1:-clean}"
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose is required for the real WordPress $mode test." >&2
  exit 2
fi
scripts/build-lousy-outages-release.sh >/tmp/lo-build.log
work="$(mktemp -d)"
trap 'cd /workspace/suzyeastonca 2>/dev/null || true; docker compose -f "$work/compose.yml" down -v >/dev/null 2>&1 || true; rm -rf "$work"' EXIT
cat > "$work/compose.yml" <<YML
services:
  db:
    image: mariadb:11
    environment:
      MARIADB_DATABASE: wordpress
      MARIADB_USER: wordpress
      MARIADB_PASSWORD: wordpress
      MARIADB_ROOT_PASSWORD: root
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 3s
      retries: 20
  wp:
    image: wordpress:cli-php8.3
    depends_on:
      db:
        condition: service_healthy
    user: "33:33"
    volumes:
      - wp:/var/www/html
      - ${PWD}:/repo
    working_dir: /var/www/html
    entrypoint: ["sh", "-c", "sleep infinity"]
volumes:
  wp:
YML
docker compose -f "$work/compose.yml" up -d --quiet-pull
wp() { docker compose -f "$work/compose.yml" exec -T wp wp --allow-root "$@"; }
wp core download --force
wp config create --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=db --skip-check
wp core install --url=http://example.test --title=LO --admin_user=admin --admin_password=password --admin_email=admin@example.test
if [[ "$mode" == "upgrade" ]]; then
  mkdir -p "$work/fixture/lousy-outages"
  { printf '%s\n' '<?php'; printf '%s\n' '/* Plugin Name: Lousy Out'"ages"; printf '%s\n' 'Version: 0.3.0 */'; } > "$work/fixture/lousy-outages/lousy-outages.php"
  docker compose -f "$work/compose.yml" cp "$work/fixture/lousy-outages" wp:/var/www/html/wp-content/plugins/lousy-outages
  for opt in lo_event_log lo_event_log_compacted_v1 lousy_outages_history lousy_outages_log lousy_outages_states lo_event_log_v2 lo_history_migration_backup_v2 lo_history_migration_v2_marker; do wp option update "$opt" '{"seed":"preserve"}' --format=json; done
  for hook in lousy_outages_poll lousy_outages_cron_refresh lousy_outages_refresh lo_check_statuses lo_refresh_snapshot; do wp cron event schedule "$hook" now hourly; done
fi
wp plugin install /repo/dist/lousy-outages.zip --force
wp plugin activate lousy-outages
wp plugin status lousy-outages | tee "$work/status.txt"
wp eval 'if (plugin_basename(LOUSY_OUTAGES_FILE)!=="lousy-outages/lousy-outages.php") { exit(1); } if (LOUSY_OUTAGES_VERSION!=="0.3.2") { exit(2); } echo "version-ok\n";'
wp eval 'do_action("init"); global $shortcode_tags; foreach (["lousy_outages","lousy_outages_teaser"] as $s) { if (empty($shortcode_tags[$s])) exit(3); } echo "shortcodes-ok\n";'
wp eval 'do_action("rest_api_init"); $r=rest_get_server()->get_routes(); foreach (["/lousy-outages/v1/summary","/lousy-outages/v1/history"] as $route) { if (empty($r[$route])) exit(4); } echo "routes-ok\n";'
wp eval '$s=rest_do_request(new WP_REST_Request("GET","/lousy-outages/v1/summary")); $h=rest_do_request(new WP_REST_Request("GET","/lousy-outages/v1/history")); if($s->get_status()!==200||$h->get_status()!==200) exit(5); echo "rest-ok\n";'
wp eval '$events=_get_cron_array(); $count=0; foreach($events as $ts=>$hooks){ if(isset($hooks["lousy_outages_refresh_official_providers"])) $count += count($hooks["lousy_outages_refresh_official_providers"]); foreach(["lousy_outages_poll","lousy_outages_cron_refresh","lousy_outages_refresh","lo_check_statuses","lo_refresh_snapshot"] as $old){ if(isset($hooks[$old])) exit(6); } } if($count!==1) exit(7); echo "cron-ok\n";'
if [[ "$mode" == "upgrade" ]]; then
  wp eval 'foreach(["lo_event_log","lo_event_log_compacted_v1","lousy_outages_history","lousy_outages_log","lousy_outages_states","lo_event_log_v2","lo_history_migration_backup_v2","lo_history_migration_v2_marker"] as $o){ if(get_option($o)===false) exit(8); } echo "history-preserved\n";'
  wp eval 'if (is_dir(WP_PLUGIN_DIR."/lousy-outages-0.3.2") || is_dir(WP_PLUGIN_DIR."/lousy-outages/lousy-outages")) exit(9); echo "layout-ok\n";'
fi
