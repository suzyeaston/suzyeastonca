<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\CLI;

use SuzyEaston\LousyOutages\Fetcher;
use SuzyEaston\LousyOutages\IncidentAlerts;
use SuzyEaston\LousyOutages\Providers;
use WP_CLI;
use function WP_CLI\Utils\format_items;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class ProbeCommand {
    public function __invoke( array $args, array $assoc_args ): void {
        $id       = isset( $assoc_args['id'] ) ? (string) $assoc_args['id'] : '';
        $timeout  = (int) apply_filters( 'lousy_outages_fetch_timeout', 10 );
        $fetcher  = new Fetcher( $timeout );

        if ( '' !== $id ) {
            $providers = Providers::list();
            if ( ! isset( $providers[ $id ] ) ) {
                WP_CLI::error( sprintf( 'Provider "%s" not found.', $id ) );
            }

            $provider = $providers[ $id ];
            $state    = $fetcher->fetch( $provider );
            $line     = sprintf(
                '%s | %s | %s | updated:%s | error:%s',
                $state['name'] ?? $id,
                $state['status_label'] ?? $state['status'] ?? 'Unknown',
                preg_replace('/\s+/', ' ', (string) ( $state['summary'] ?? '' )),
                $state['updated_at'] ?? '-',
                $state['error'] ?? '-'
            );
            WP_CLI::line( $line );
            return;
        }

        $rows      = [];
        $providers = Providers::enabled();
        foreach ( $providers as $provider ) {
            $state      = $fetcher->fetch( $provider );
            $rows[] = [
                'id'      => $state['id'] ?? ( $provider['id'] ?? '' ),
                'name'    => $state['name'] ?? ( $provider['name'] ?? '' ),
                'status'  => $state['status_label'] ?? $state['status'] ?? 'Unknown',
                'summary' => preg_replace('/\s+/', ' ', (string) ( $state['summary'] ?? '' )),
                'updated' => $state['updated_at'] ?? '-',
                'error'   => $state['error'] ?? '-',
            ];
        }

        if ( empty( $rows ) ) {
            WP_CLI::warning( 'No enabled providers were found.' );
            return;
        }

        format_items( 'table', $rows, [ 'id', 'name', 'status', 'summary', 'updated', 'error' ] );
    }
}

WP_CLI::add_command( 'lousy:probe', ProbeCommand::class );

class RefreshCommand {
    /**
     * Run the full Lousy Outages refresh pipeline once.
     *
     * ## OPTIONS
     *
     * [--bypass-cache=<bool>]
     * : Whether to bypass any internal caching. Defaults to true.
     *
     * ## EXAMPLES
     *
     *     wp lousy:refresh
     *     wp lousy:refresh --bypass-cache=false
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $bypass = true;
        if ( isset( $assoc_args['bypass-cache'] ) ) {
            $value  = strtolower( (string) $assoc_args['bypass-cache'] );
            $bypass = ! in_array( $value, [ '0', 'false', 'no' ], true );
        }

        if ( ! function_exists( '\lousy_outages_refresh_data' ) ) {
            WP_CLI::error( 'lousy_outages_refresh_data() not available.' );
        }

        $result = \lousy_outages_refresh_data( $bypass );

        $ok      = ! empty( $result['ok'] );
        $skipped = ! empty( $result['skipped'] );
        $msg     = isset( $result['message'] ) ? (string) $result['message'] : '';

        $refreshedAt = isset( $result['refreshedAt'] ) ? (string) $result['refreshedAt'] : gmdate( 'c' );

        if ( $ok ) {
            WP_CLI::success( sprintf( 'Refresh completed. skipped=%s refreshedAt=%s %s', $skipped ? 'true' : 'false', $refreshedAt, $msg ) );
        } elseif ( $skipped ) {
            WP_CLI::warning( sprintf( 'Refresh skipped. refreshedAt=%s %s', $refreshedAt, $msg ) );
        } else {
            WP_CLI::error( sprintf( 'Refresh failed. refreshedAt=%s %s', $refreshedAt, $msg ) );
        }
    }
}

WP_CLI::add_command( 'lousy:refresh', RefreshCommand::class );

class AlertTestCommand {
    public function __invoke( array $args, array $assoc_args ): void {
        $recipient = isset( $assoc_args['recipient'] ) ? sanitize_email( (string) $assoc_args['recipient'] ) : '';
        $dry_run   = isset( $assoc_args['dry-run'] ) && in_array( strtolower( (string) $assoc_args['dry-run'] ), [ '1', 'true', 'yes' ], true );
        $fixed_id  = isset( $assoc_args['fixed-id'] ) ? (string) $assoc_args['fixed-id'] : '';
        $overrides = [];
        if ( '' !== $fixed_id ) {
            $overrides['id'] = $fixed_id;
        }
        if ( '' !== $recipient ) {
            update_option( 'lousy_outages_email', $recipient, false );
        }
        $incident = IncidentAlerts::make_synthetic_incident( $overrides );
        $result = IncidentAlerts::process_incidents( [ $incident ], [ 'synthetic' => true, 'mode' => 'cli_alert_test', 'notification_only' => true, 'dry_run' => $dry_run ] );
        WP_CLI::line( wp_json_encode( $result ) ?: '{}' );
        if ( (int) ( $result['failed'] ?? 0 ) > 0 ) {
            WP_CLI::warning( 'Synthetic alert test had failures.' );
        } else {
            WP_CLI::success( 'Synthetic alert test completed.' );
        }
    }
}
WP_CLI::add_command( 'lousy:alert-test', AlertTestCommand::class );

class AlertHealthCommand {
    public function __invoke( array $args, array $assoc_args ): void {
        $subscribers = get_option( 'lo_subscribers', [] );
        $rows = [
            [ 'key' => 'configured_notification_email', 'value' => (string) get_option( 'lousy_outages_email', get_option( 'admin_email' ) ) ],
            [ 'key' => 'subscriber_count', 'value' => is_array( $subscribers ) ? (string) count( $subscribers ) : '0' ],
            [ 'key' => 'last_modern_success', 'value' => wp_json_encode( get_option( 'lousy_outages_last_alert_success', [] ) ) ?: '{}' ],
            [ 'key' => 'last_modern_failure', 'value' => wp_json_encode( get_option( 'lousy_outages_last_alert_failure', [] ) ) ?: '{}' ],
            [ 'key' => 'last_synthetic_test', 'value' => wp_json_encode( get_option( 'lousy_outages_last_synthetic_alert', [] ) ) ?: '{}' ],
            [ 'key' => 'cron_lo_check_statuses_scheduled', 'value' => wp_next_scheduled( 'lo_check_statuses' ) ? 'yes' : 'no' ],
            [ 'key' => 'recent_failure_exists', 'value' => get_option( 'lousy_outages_alert_delivery_failure' ) ? 'yes' : 'no' ],
        ];
        format_items( 'table', $rows, [ 'key', 'value' ] );
    }
}
WP_CLI::add_command( 'lousy:alert-health', AlertHealthCommand::class );
