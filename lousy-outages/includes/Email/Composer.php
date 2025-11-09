<?php
declare(strict_types=1);

namespace LousyOutages\Email;

use LousyOutages\Model\Incident;

class Composer
{
    public static function subjectForIncident(Incident $incident, ?string $fallbackStatus = null): string
    {
        $shortTitle = self::shortTitle($incident->title);
        $provider   = trim($incident->provider) ?: 'Provider';

        if ($incident->isResolved()) {
            return sprintf('[Resolved] %s: %s', $provider, $shortTitle);
        }

        $statusSlug = strtolower($incident->status ?: 'incident');
        if (! $statusSlug && $fallbackStatus) {
            $statusSlug = strtolower($fallbackStatus);
        }

        $headline = self::statusHeadline($statusSlug);
        $short    = self::shortTitle($incident->title ?: $headline);

        return sprintf('[Outage Alert] %s: %s', $provider, $short ?: $headline);
    }

    public static function subjectForProvider(string $provider, string $status, string $title = ''): string
    {
        $provider = trim($provider) ?: 'Provider';
        $status   = strtolower(trim($status));
        if ('resolved' === $status) {
            $short = self::shortTitle($title ?: 'Status Restored');
            return sprintf('[Resolved] %s: %s', $provider, $short);
        }

        $headline = self::statusHeadline($status);
        $short    = self::shortTitle($title ?: $headline);

        return sprintf('[Outage Alert] %s: %s', $provider, $short ?: $headline);
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

    private static function statusHeadline(string $status): string
    {
        $status = strtolower(trim($status));
        $map = [
            'degraded'       => 'Degraded service',
            'partial_outage' => 'Partial outage',
            'partial'        => 'Partial outage',
            'major_outage'   => 'Major outage',
            'major'          => 'Major outage',
            'critical'       => 'Critical incident',
            'maintenance'    => 'Maintenance window',
            'incident'       => 'Connectivity incident',
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        if ('' === $status) {
            return 'Incident';
        }

        return ucfirst(str_replace('_', ' ', $status));
    }
}
