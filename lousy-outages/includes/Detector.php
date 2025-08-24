<?php
namespace LousyOutages;

class Detector {
    private Store $store;

    public function __construct( Store $store ) {
        $this->store = $store;
    }

    /**
     * Compare new data with stored to find transitions.
     */
    public function detect( string $id, array $data ): ?array {
        $existing = $this->store->get( $id );
        $old      = $existing['status'] ?? 'unknown';
        $new      = $data['status'];
        if ( $old === $new ) {
            return null;
        }
        return [ 'old' => $old, 'new' => $new ];
    }
}
