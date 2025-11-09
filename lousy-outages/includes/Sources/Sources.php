<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

/**
 * Registry for incident sources.
 */
class Sources
{
    /** @var array<string, object> */
    private static array $sources = [];

    public static function register(string $key, object $source): void
    {
        self::$sources[$key] = $source;
    }

    /**
     * @return array<string, object>
     */
    public static function all(): array
    {
        return self::$sources;
    }
}
