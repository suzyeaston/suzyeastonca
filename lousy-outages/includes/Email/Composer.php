<?php
declare(strict_types=1);

namespace LousyOutages\Email;

use LousyOutages\Model\Incident;

class Composer
{
    private const STATUS_LABELS = [
        'degraded'       => 'Degraded',
        'partial_outage' => 'Partial Outage',
        'major_outage'   => 'Major Outage',
        'maintenance'    => 'Maintenance',
    ];

    public static function subjectForIncident(Incident $incident, ?string $fallbackStatus = null): string
    {
        $shortTitle = self::shortTitle($incident->title);
        $provider   = trim($incident->provider) ?: 'Provider';

        if ($incident->isResolved()) {
            return sprintf('[Resolved] %s: %s', $provider, $shortTitle);
        }

        $label = self::STATUS_LABELS[$incident->status] ?? null;
        if (! $label && $fallbackStatus) {
            $fallback = strtolower($fallbackStatus);
            $label = self::STATUS_LABELS[$fallback] ?? ucfirst($fallbackStatus);
        }
        if (! $label) {
            $label = self::STATUS_LABELS['degraded'];
        }

        return sprintf('[Outage Alert] %s: %s — %s', $provider, $label, $shortTitle);
    }

    public static function subjectForProvider(string $provider, string $status, string $title = ''): string
    {
        $provider = trim($provider) ?: 'Provider';
        $status   = strtolower(trim($status));
        if ('resolved' === $status) {
            $short = self::shortTitle($title ?: 'Status Restored');
            return sprintf('[Resolved] %s: %s', $provider, $short);
        }

        $label = self::STATUS_LABELS[$status] ?? ucfirst($status ?: 'Degraded');
        $short = self::shortTitle($title ?: $label);

        return sprintf('[Outage Alert] %s: %s — %s', $provider, $label, $short);
    }

    public static function shortTitle(string $title): string
    {
        $stripped = preg_replace('/^(Update:|Identified:|Monitoring:|Resolved:|Investigating:)\s*/i', '', $title);
        if (null === $stripped) {
            $stripped = $title;
        }
        $clean = trim($stripped);
        $clean = rtrim($clean);
        $clean = preg_replace('/[\-–—:]+$/u', '', $clean ?? '');
        if (null === $clean) {
            $clean = '';
        }
        $clean = rtrim($clean);
        return $clean ?: 'Incident';
    }
}
