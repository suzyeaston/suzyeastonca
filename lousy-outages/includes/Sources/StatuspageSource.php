<?php
// Compat class (no namespaces, no typed properties).
if ( ! class_exists('LO_StatuspageSource') ) {
  class LO_StatuspageSource {
    /** @var string */ public $name;
    /** @var string */ public $api;
    /** @var string */ public $home;

    function __construct($name, $api, $home) {
      $this->name = (string)$name;
      $this->api  = (string)$api;
      $this->home = (string)$home;
    }

    /** Return shape expected by callers: ['status'=>string,'incidents'=>LO_Incident[],'updated'=>int] */
    function fetch() {
      $payload   = $this->fetch_payload();
      $data      = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : null;
      $incidents = is_array($data) ? $this->fetch_incidents($data) : array();
      $overall   = 'unknown';
      $updated   = time();

      if (is_array($data)) {
        $ind = isset($data['status']['indicator']) ? strtolower((string)$data['status']['indicator']) : 'none';
        // 'none','minor','major','critical','maintenance'
        $overall = ($ind==='none') ? 'operational'
          : ($ind==='minor' ? 'degraded'
          : ($ind==='major' ? 'partial_outage'
          : ($ind==='critical' ? 'major_outage' : 'maintenance')));
        if (!empty($data['page']['updated_at'])) {
          $ts = strtotime((string)$data['page']['updated_at']);
          if ($ts) $updated = $ts;
        }
      }

      $result = array(
        'status'    => $overall,
        'incidents' => $incidents,
        'updated'   => $updated,
      );

      if (!empty($payload['stale'])) {
        $result['stale'] = true;
      }

      if (!empty($payload['message'])) {
        $result['message'] = $payload['message'];
      }

      return $result;
    }

    /** @return array LO_Incident[] */
    function fetch_incidents($payload = null) {
      if (!is_array($payload)) {
        $payload_response = $this->fetch_payload();
        $payload = isset($payload_response['data']) && is_array($payload_response['data']) ? $payload_response['data'] : null;
      }
      if (!is_array($payload)) {
        return array();
      }

      $out = array();

      if (isset($payload['incidents']) && is_array($payload['incidents'])) {
        foreach ($payload['incidents'] as $i) {
          if (!is_array($i)) continue;
          $raw   = isset($i['status']) ? strtolower((string)$i['status']) : '';
          $impact = isset($i['impact']) ? strtolower((string)$i['impact']) : '';
          $map   = array('investigating'=>'degraded','identified'=>'degraded','monitoring'=>'degraded','postmortem'=>'resolved','resolved'=>'resolved');
          $state = isset($map[$raw]) ? $map[$raw] : 'degraded';
          if ($state !== 'resolved' && $impact) {
            $impact_map = array('minor'=>'degraded','major'=>'partial_outage','critical'=>'major_outage','maintenance'=>'maintenance');
            if (isset($impact_map[$impact])) {
              $state = $impact_map[$impact];
            }
          }

          $title = isset($i['name']) ? (string)$i['name'] : 'Incident';
          $url   = !empty($i['shortlink']) ? (string)$i['shortlink'] : $this->home;
          $det   = !empty($i['started_at']) ? strtotime((string)$i['started_at'])
                : (!empty($i['created_at']) ? strtotime((string)$i['created_at']) : time());
          $resd  = ($state==='resolved' && !empty($i['resolved_at'])) ? strtotime((string)$i['resolved_at']) : null;

          $out[] = LO_Incident::create(
            $this->name,
            LO_Incident::make_id($this->name, $title, $det ?: time()),
            $title,
            $state,
            $url,
            null,
            null,
            $det ?: time(),
            $resd
          );
        }
      }

      // If no listed incidents, still show a card (operational/degraded by indicator)
      if (empty($out) && isset($payload['status']['indicator'])) {
        $ind = strtolower((string)$payload['status']['indicator']);
        if ($ind === 'none') {
          $out[] = LO_Incident::operational($this->name, $this->home);
        } else {
          $title = 'Provider reports ' . $ind . ' impact';
          $state = ($ind==='minor' ? 'degraded' : ($ind==='major' ? 'partial_outage' : ($ind==='critical' ? 'major_outage' : 'maintenance')));
          $slug = sanitize_key($this->name);
          $id = $slug . ':indicator:' . $ind;
          $updated = time();
          if (!empty($payload['page']['updated_at'])) {
            $ts = strtotime((string)$payload['page']['updated_at']);
            if ($ts) $updated = $ts;
          }
          $out[] = LO_Incident::create($this->name, $id, $title, $state, $this->home, null, null, $updated, null);
        }
      }

      return $out;
    }

    /** @return array{data:?array,stale?:bool,message?:string} */
    private function fetch_payload() {
      $cache_key = $this->cache_key();
      $response  = $this->request_json($this->api);

      if ($response['ok']) {
        $data = $response['data'];
        $this->store_cache($cache_key, $data);
        return array('data' => $data);
      }

      $fallback = $this->fetch_fallback_payload();
      if ($fallback) {
        $this->store_cache($cache_key, $fallback);
        return array('data' => $fallback);
      }

      $cached = get_transient($cache_key);
      if (is_array($cached) && !empty($cached['data']) && is_array($cached['data'])) {
        return array(
          'data'  => $cached['data'],
          'stale' => true,
          'message' => 'Status temporarily unavailable (using cached data).',
        );
      }

      return array(
        'data' => null,
        'message' => 'Status temporarily unavailable.',
      );
    }

    private function fetch_fallback_payload() {
      $base = $this->statuspage_base();
      if (!$base) {
        return null;
      }

      $base = trailingslashit($base);
      $status_response = $this->request_json($base . 'api/v2/status.json');
      if (! $status_response['ok']) {
        return null;
      }

      $status_data = $status_response['data'];
      $incidents_response = $this->request_json($base . 'api/v2/incidents/unresolved.json');
      $incidents_data = $incidents_response['ok'] ? $incidents_response['data'] : null;

      if (!is_array($status_data)) {
        return null;
      }

      $payload = $status_data;
      $payload['incidents'] = is_array($incidents_data) && isset($incidents_data['incidents']) && is_array($incidents_data['incidents'])
        ? $incidents_data['incidents']
        : [];

      return $payload;
    }

    private function request_json($url) {
      $res = wp_remote_get($url, array(
        'timeout' => 10,
        'headers' => $this->request_headers(),
      ));

      if (is_wp_error($res)) {
        return array('ok' => false, 'code' => 0, 'data' => null);
      }

      $code = (int) wp_remote_retrieve_response_code($res);
      if ($code < 200 || $code >= 300) {
        return array('ok' => false, 'code' => $code, 'data' => null);
      }

      $body = (string) wp_remote_retrieve_body($res);
      if ('' === trim($body)) {
        return array('ok' => false, 'code' => $code, 'data' => null);
      }

      $json = json_decode($body, true);
      if (!is_array($json)) {
        return array('ok' => false, 'code' => $code, 'data' => null);
      }

      return array('ok' => true, 'code' => $code, 'data' => $json);
    }

    private function request_headers() {
      return array(
        'Accept'     => 'application/json',
        'User-Agent' => $this->user_agent(),
      );
    }

    private function statuspage_base() {
      $base = preg_replace('#/api/v\\d+(?:\\.\\d+)?/summary\\.json$#', '/', (string)$this->api);
      if ($base && $base !== $this->api) {
        return $base;
      }
      if (!empty($this->home)) {
        return $this->home;
      }
      return null;
    }

    private function cache_key() {
      $slug = sanitize_key($this->name);
      return 'lo_statuspage_cache_' . ($slug ?: md5($this->api));
    }

    private function store_cache($key, $data) {
      $ttl = defined('HOUR_IN_SECONDS') ? 6 * HOUR_IN_SECONDS : 6 * 3600;
      set_transient($key, array('data' => $data, 'cached_at' => time()), $ttl);
    }

    private function user_agent() {
      $site = home_url();
      return 'LousyOutagesStatus/1.0 (+'.$site.')';
    }
  }
}

// Bridge the namespaced calls to this compat class.
if ( ! class_exists('SuzyEaston\\LousyOutages\\Sources\\StatuspageSource') ) {
  class_alias('LO_StatuspageSource', 'SuzyEaston\\LousyOutages\\Sources\\StatuspageSource');
}

if ( ! class_exists('LousyOutages\\Sources\\StatuspageSource') ) {
  class_alias('LO_StatuspageSource', 'LousyOutages\\Sources\\StatuspageSource');
}

// Incident compat (if not already present)
if ( ! class_exists('LO_Incident') ) {
  $compat = __DIR__ . '/../compat.php';
  if ( file_exists($compat) ) {
    require_once $compat;
  }
}
if ( ! class_exists('LO_Incident') ) {
  require_once __DIR__ . '/../Model/Incident.php';
}
