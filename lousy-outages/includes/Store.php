<?php
namespace LousyOutages;

class Store {
    private string $option = 'lousy_outages_states';
    private string $log_option = 'lousy_outages_log';

    public function get_all(): array {
        return get_option( $this->option, [] );
    }

    public function get( string $id ): ?array {
        $all = $this->get_all();
        return $all[ $id ] ?? null;
    }

    public function update( string $id, array $data ): void {
        $all        = $this->get_all();
        $all[ $id ] = $data;
        update_option( $this->option, $all, false );
        $this->log( $id, $data['status'] );
    }

    private function log( string $id, string $status ): void {
        $log   = get_option( $this->log_option, [] );
        $log[] = [ 'id' => $id, 'status' => $status, 'time' => time() ];
        if ( count( $log ) > 50 ) {
            $log = array_slice( $log, -50 );
        }
        update_option( $this->log_option, $log, false );
    }
}
