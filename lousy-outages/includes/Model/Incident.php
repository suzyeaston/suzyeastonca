<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Model;

class Incident
{
    public string $provider;
    public string $id;
    public string $title;
    public string $status;
    public string $url;
    public ?string $component;
    public ?string $impact;
    public int $detected_at;
    public ?int $resolved_at;

    public function __construct(
        string $provider,
        string $id,
        string $title,
        string $status,
        string $url,
        ?string $component,
        ?string $impact,
        int $detected_at,
        ?int $resolved_at
    ) {
        $this->provider   = $provider;
        $this->id         = $id;
        $this->title      = $title;
        $this->status     = $status;
        $this->url        = $url;
        $this->component  = $component;
        $this->impact     = $impact;
        $this->detected_at = $detected_at;
        $this->resolved_at = $resolved_at;
    }

    public function isResolved(): bool
    {
        return 'resolved' === $this->status;
    }
}
