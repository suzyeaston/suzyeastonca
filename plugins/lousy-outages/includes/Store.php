<?php
namespace SuzyEaston\LousyOutages;

class Store {
    private string $option = 'lousy_outages_states';
    private string $log_option = 'lousy_outages_log';
    private string $history_option = 'lousy_outages_history';
    private int $history_retention = 3 * YEAR_IN_SECONDS; // retain 3 years of daily signals.

    public function get_all(): array {
        $stored = get_option( $this->option, [] );

        if ( ! is_array( $stored ) || empty( $stored ) ) {
            return [];
        }

        $enabled = Providers::enabled();
        if ( ! is_array( $enabled ) || empty( $enabled ) ) {
            return [];
        }

        $allowed = array_fill_keys( array_keys( $enabled ), true );

        return array_intersect_key( $stored, $allowed );
    }

    public function get( string $id ): ?array {
        $all = $this->get_all();
        return $all[ $id ] ?? null;
    }

    public function update( string $id, array $data ): void {
        $status = $data['status'] ?? 'unknown';
        if ( empty( $data['status_label'] ) ) {
            $data['status_label'] = Fetcher::status_label( $status );
        }
        $all        = $this->get_all();
        $all[ $id ] = $data;
        update_option( $this->option, $all, false );
        $this->log( $id, $status );
    }

    private function log( string $id, string $status ): void {
        $log   = get_option( $this->log_option, [] );
        $log[] = [ 'id' => $id, 'status' => $status, 'time' => time() ];
        update_option( $this->log_option, $log, false );
        $this->append_history( $id, $status, time() );
    }

    public function get_history_log(): array {
        $history = get_option( $this->history_option, [] );
        if ( ! is_array( $history ) ) {
            return [];
        }

        return array_values( $history );
    }

    /**
     * Return all history entries within a specific window.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_history_window( int $start, int $end ): array {
        $history = $this->get_history_log();

        return array_values( array_filter( $history, static function ( $entry ) use ( $start, $end ) {
            if ( ! is_array( $entry ) || ! isset( $entry['time'] ) ) {
                return false;
            }

            $timestamp = (int) $entry['time'];

            return $timestamp >= $start && $timestamp <= $end;
        } ) );
    }

    /**
     * Provide current and previous-year windows to support year-over-year comparisons.
     */
    public function get_year_over_year_history( int $days ): array {
        $now           = time();
        $currentStart  = $now - ( $days * DAY_IN_SECONDS );
        $previousStart = $currentStart - YEAR_IN_SECONDS;
        $previousEnd   = $now - YEAR_IN_SECONDS;

        return [
            'current'  => $this->get_history_window( $currentStart, $now ),
            'previous' => $this->get_history_window( $previousStart, $previousEnd ),
        ];
    }

    private function append_history( string $id, string $status, int $timestamp ): void {
        $history = get_option( $this->history_option, [] );
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        $history[] = [
            'id'     => $id,
            'status' => $status,
            'time'   => $timestamp,
        ];

        $cutoff = time() - $this->history_retention;
        $history = array_values( array_filter( $history, static function ( $entry ) use ( $cutoff ) {
            if ( ! is_array( $entry ) || ! isset( $entry['time'] ) ) {
                return false;
            }

            return (int) $entry['time'] >= $cutoff;
        } ) );

        update_option( $this->history_option, $history, false );
    }
}
