<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

interface SignalSourceInterface {
    public function id(): string;
    public function label(): string;
    public function is_configured(): bool;
    /** @return array<int,array<string,mixed>> */
    public function collect(array $options = []): array;
}
