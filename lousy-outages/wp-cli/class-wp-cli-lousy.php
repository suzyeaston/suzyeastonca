<?php
declare(strict_types=1);

namespace LousyOutages\CLI;

use LousyOutages\Fetcher;
use LousyOutages\Providers;
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
