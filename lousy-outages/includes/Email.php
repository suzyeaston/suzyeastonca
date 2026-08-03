<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Email\Composer;

class Email {
    private const STATUS_LABELS = [
        'degraded'       => 'degraded performance',
        'partial_outage' => 'partial outage',
        'major_outage'   => 'major outage',
        'maintenance'    => 'maintenance window',
        'operational'    => 'operational',
        'resolved'       => 'resolved',
        'recovered'      => 'recovered',
        'partial'        => 'partial outage',
        'major'          => 'major outage',
        'critical'       => 'critical incident',
        'incident'       => 'incident',
    ];

    public function send_alert(string $provider, string $status, string $message, string $link): void {
        $status_slug  = strtolower(trim($status)) ?: 'degraded';
        $status_label = $this->describe_status($status_slug);
        $subject      = Composer::subjectForProvider($provider, $status_slug, $message);

        [$text_body, $html_body] = $this->build_bodies($provider, $status_label, $message, $link, false);
        $this->dispatch($subject, $text_body, $html_body);
    }

    public function send_recovery(string $provider, string $link): void {
        $status_label = $this->describe_status('recovered');
        $subject      = Composer::subjectForProvider($provider, 'resolved', '');

        [$text_body, $html_body] = $this->build_bodies($provider, $status_label, 'Systems stabilized — back to nominal performance.', $link, true);
        $this->dispatch($subject, $text_body, $html_body);
    }

    private function dispatch(string $subject, string $text_body, string $html_body): void {
        $to = get_option('lousy_outages_email', get_option('admin_email'));
        if (!$to || !is_email($to)) {
            do_action('lousy_outages_log', 'email_skip', ['reason' => 'invalid_to']);
            return;
        }

        $ok = Mailer::send($to, $subject, $text_body, $html_body);

        update_option(
            'lousy_outages_last_email',
            [
                'to'      => $to,
                'subject' => $subject,
                'ok'      => $ok,
                'ts'      => gmdate('c'),
            ],
            false
        );

        do_action('lousy_outages_log', 'email_send', ['ok' => $ok, 'to' => $to, 'subject' => $subject]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function build_bodies(string $provider, string $status_label, string $message, string $link, bool $recovery): array {
        $provider_label = trim($provider) ?: 'Provider';
        $message_clean  = $this->clean_message($message);
        if ('' === $message_clean) {
            $message_clean = $recovery ? 'Everything is reading green on the console again.' : 'Details incoming — watch the console feed for live notes.';
        }

        $link = $this->ensure_link($link);

        $status_text = strtoupper($status_label);
        $cta_text    = $recovery ? 'Review status timeline' : 'Track live status';
        $summary     = $recovery
            ? sprintf('%s is back in a healthy state. Systems report nominal values.', $provider_label)
            : sprintf('%s just flipped to %s. Heads-up below.', $provider_label, $status_label);

        $text_lines = [
            sprintf('%s — %s', $provider_label, $status_text),
            $summary,
            '',
            $message_clean,
            '',
            $cta_text . ': ' . $link,
            '',
            'You will only get this one heads-up for the incident. Follow the provider status console for play-by-play updates.',
            '',
            'Stay laser-focused,',
            'Lousy Outages ops console',
        ];

        $text_body = implode("\n", $text_lines);

        $accent       = $recovery ? '#3be88f' : '#ffb81c';
        $accent_soft  = $recovery ? 'rgba(59,232,143,0.35)' : 'rgba(255,184,28,0.35)';
        $badge_bg     = $recovery ? 'rgba(59,232,143,0.18)' : 'rgba(255,184,28,0.18)';
        $panel_start  = $recovery ? '#021b11' : '#150022';
        $panel_end    = $recovery ? '#04331c' : '#23093b';
        $cta_bg       = $recovery ? '#3be88f' : '#ff6f61';
        $cta_color    = $recovery ? '#032015' : '#050211';
        $badge_label  = $recovery ? 'RESTORED' : 'ALERT';
        $meta_caption = $recovery ? 'restoration signal locked' : 'defense grid warning';

        $provider_html = esc_html($provider_label);
        $status_html   = esc_html($status_text);
        $summary_html  = nl2br(esc_html($summary));
        $message_html  = nl2br(esc_html($message_clean));
        $link_html     = esc_url($link);
        $cta_html      = sprintf(
            '<a href="%s" style="display:inline-block;padding:14px 22px;border-radius:999px;border:2px solid %s;background:%s;color:%s;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.08em;">%s</a>',
            $link_html,
            $accent,
            $cta_bg,
            $cta_color,
            esc_html($cta_text)
        );

        $html_body = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<style type="text/css">
:root { color-scheme: light only; supported-color-schemes: light only; }
u + .body .gmail-blend-screen { background:#000000; mix-blend-mode:screen; }
u + .body .gmail-blend-difference { background:#000000; mix-blend-mode:difference; }
</style>
</head>
<body class="body" style="margin:0;padding:0;background:{$panel_start};background-image:linear-gradient({$panel_start},{$panel_start});color:#ffffff;font-family:'Share Tech Mono','Lucida Console','Courier New',monospace;">
  <div style="max-width:640px;margin:0 auto;padding:32px 18px;background:{$panel_start};background-image:linear-gradient({$panel_start},{$panel_start});color:#ffffff;">
    <div style="background:{$panel_start};background-image:linear-gradient({$panel_start},{$panel_end});border:3px solid {$accent};border-radius:22px;padding:30px 28px;color:#ffffff;">
      <div class="gmail-blend-screen"><div class="gmail-blend-difference">
      <header style="margin-bottom:20px;text-transform:uppercase;">
        <span style="display:block;font-size:14px;letter-spacing:0.14em;color:#ffffff;">lousy outages ops</span>
        <span style="display:block;font-size:12px;color:#ffffff;">{$meta_caption}</span>
      </header>
      <div style="display:inline-block;padding:10px 16px;border-radius:999px;margin-bottom:18px;border:1px solid {$accent};">
        <span style="font-size:11px;letter-spacing:0.22em;color:#ffffff;">{$badge_label}</span>
        <span style="font-size:12px;letter-spacing:0.16em;color:#ffffff;"> {$status_html}</span>
      </div>
      <h1 style="margin:0 0 12px;font-size:26px;color:#ffffff;letter-spacing:0.08em;text-transform:uppercase;">{$provider_html}</h1>
      <p style="margin:0 0 14px;font-size:14px;line-height:1.7;color:#ffffff;">{$summary_html}</p>
      <div style="margin:0 0 20px;padding:16px 18px;border:1px dashed {$accent};border-radius:16px;color:#ffffff;">
        <p style="margin:0;font-size:13px;line-height:1.7;color:#ffffff;">{$message_html}</p>
      </div>
      <p style="margin:0 0 18px;font-size:12px;color:#ffffff;">One alert per incident. For live play-by-play, keep the provider status console open.</p>
      </div></div>
      <p style="margin:0 0 22px;">{$cta_html}</p>
      <div class="gmail-blend-screen"><div class="gmail-blend-difference">
      <p style="margin:0;font-size:11px;color:#ffffff;">If the button stalls, open this link: <a href="{$link_html}" style="color:#ffffff;text-decoration:underline;">{$link_html}</a></p>
      </div></div>
    </div>
  </div>
</body>
</html>
HTML;

        return [$text_body, $html_body];
    }

    private function describe_status(string $status): string {
        $status = strtolower(trim($status));
        if (isset(self::STATUS_LABELS[$status])) {
            return self::STATUS_LABELS[$status];
        }

        return $status ? $status : 'status update';
    }

    private function ensure_link(string $link): string {
        $link = trim($link);
        if ('' !== $link) {
            return $link;
        }

        return home_url('/lousy-outages/');
    }

    private function clean_message(string $message): string {
        return trim(wp_strip_all_tags($message));
    }

    private function rgbaFromHex(string $hex): string {
        $hex = ltrim($hex, '#');
        if (6 !== strlen($hex)) {
            return '255,255,255';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('%d,%d,%d', $r, $g, $b);
    }
}
