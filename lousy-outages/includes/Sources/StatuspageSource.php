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
      $incidents = $this->fetch_incidents();
      $overall   = 'operational';
      $updated   = time();

      $res = wp_remote_get($this->api, array('timeout'=>10));
      if ( ! is_wp_error($res) ) {
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code >= 200 && $code < 300) {
          $body = wp_remote_retrieve_body($res);
          $json = json_decode($body, true);
          if (is_array($json)) {
            $ind = isset($json['status']['indicator']) ? strtolower((string)$json['status']['indicator']) : 'none';
            // 'none','minor','major','critical','maintenance'
            $overall = ($ind==='none') ? 'operational'
              : ($ind==='minor' ? 'degraded'
              : ($ind==='major' ? 'partial_outage'
              : ($ind==='critical' ? 'major_outage' : 'maintenance')));
            if (!empty($json['page']['updated_at'])) {
              $ts = strtotime((string)$json['page']['updated_at']);
              if ($ts) $updated = $ts;
            }
          }
        }
      }

      return array(
        'status'    => $overall,
        'incidents' => $incidents,
        'updated'   => $updated,
      );
    }

    /** @return array LO_Incident[] */
    function fetch_incidents() {
      $res = wp_remote_get($this->api, array('timeout'=>10));
      if (is_wp_error($res)) return array();
      $code = (int) wp_remote_retrieve_response_code($res);
      if ($code < 200 || $code >= 300) return array();
      $json = json_decode((string) wp_remote_retrieve_body($res), true);
      if (!is_array($json)) return array();

      $out = array();

      if (isset($json['incidents']) && is_array($json['incidents'])) {
        foreach ($json['incidents'] as $i) {
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
      if (empty($out) && isset($json['status']['indicator'])) {
        $ind = strtolower((string)$json['status']['indicator']);
        if ($ind === 'none') {
          $out[] = LO_Incident::operational($this->name, $this->home);
        } else {
          $title = 'Provider reports ' . $ind . ' impact';
          $state = ($ind==='minor' ? 'degraded' : ($ind==='major' ? 'partial_outage' : ($ind==='critical' ? 'major_outage' : 'maintenance')));
          $slug = sanitize_key($this->name);
          $id = $slug . ':indicator:' . $ind;
          $updated = time();
          if (!empty($json['page']['updated_at'])) {
            $ts = strtotime((string)$json['page']['updated_at']);
            if ($ts) $updated = $ts;
          }
          $out[] = LO_Incident::create($this->name, $id, $title, $state, $this->home, null, null, $updated, null);
        }
      }

      return $out;
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
