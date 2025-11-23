<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

Sources::register('openai', new StatuspageSource('OpenAI', 'https://status.openai.com/api/v2/summary.json', 'https://status.openai.com'));
Sources::register('teamviewer', new StatuspageSource('TeamViewer', 'https://status.teamviewer.com/api/v2/summary.json', 'https://status.teamviewer.com'));
Sources::register('zscaler', new StatuspageSource('Zscaler', 'https://status.zscaler.com/api/v2/summary.json', 'https://status.zscaler.com'));
Sources::register('cloudflare', new StatuspageSource('Cloudflare', 'https://www.cloudflarestatus.com/api/v2/summary.json', 'https://www.cloudflarestatus.com'));
Sources::register('zoom', new StatuspageSource('Zoom', 'https://status.zoom.us/api/v2/summary.json', 'https://status.zoom.us'));
