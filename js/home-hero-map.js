(function () {
  'use strict';

  var LEAFLET_CSS = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
  var LEAFLET_JS = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
  var TILE_URL = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
  var TILE_ATTR = '&copy; OpenStreetMap &copy; CARTO';

  var ANCHOR_COLORS = {
    dave: '#39ff14',
    translink: '#39ff14',
    drivers: '#ffe66d',
    ferries: '#57f3ff',
    weather: '#c8a0ff',
    wildfire: '#ff4b4b',
    air: '#7effc6',
    cknw: '#ffb347',
    cbc: '#ffe66d',
    yvr_tower: '#57f3ff',
    yvr_ground: '#57f3ff',
    yvr_combo: '#57f3ff',
    yvr_dep2: '#57f3ff',
    vzvr_acc: '#57f3ff',
    burnaby_fire: '#ff4b4b',
    marine_vhf: '#7effc6',
    hydro_bush: '#39ff14',
    hydro_mast: '#39ff14',
    sound_skytrain: '#c8a0ff',
    sound_rain: '#57f3ff',
    sound_ferry: '#ffb347',
    radio: '#ffb347',
    atc: '#57f3ff',
    marine: '#7effc6',
    hydro: '#39ff14',
    bed: '#c8a0ff'
  };

  var TIER_COLORS = {
    ooc: '#ff4b4b',
    held: '#ffe66d',
    other: '#57f3ff',
    alert: '#ffe66d',
    air: '#7effc6',
    ferry: '#57f3ff'
  };

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[src="' + src + '"]');
      if (existing && existing.dataset.loaded === '1') {
        resolve();
        return;
      }
      if (existing) {
        existing.addEventListener('load', function () { resolve(); });
        existing.addEventListener('error', reject);
        return;
      }
      var script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = function () {
        script.dataset.loaded = '1';
        resolve();
      };
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function loadStyle(href) {
    if (document.querySelector('link[href="' + href + '"]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  function readMetroConfig() {
    var cfg = window.HomeYvrBroadcasterConfig;
    if (!cfg || !cfg.metroMap || !cfg.metroMap.bounds) return null;
    return cfg.metroMap;
  }

  function fetchMetroConfigFromFeeds() {
    var cfg = window.HomeYvrBroadcasterConfig || {};
    var url = cfg.feedsUrl || '/wp-json/se/v1/broadcaster/feeds';
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data && data.metro_map && data.metro_map.bounds) {
          return {
            bounds: data.metro_map.bounds,
            anchors: data.metro_map.anchors || []
          };
        }
        return null;
      })
      .catch(function () { return null; });
  }

  function HeroMap(stage, onChannelSelect) {
    this.stage = stage;
    this.onChannelSelect = onChannelSelect || null;
    this.bounds = null;
    this.anchors = [];
    this.overlays = {};
    this.activeChannel = null;
    this.overlayMarkers = [];
    this.map = null;
    this.layer = null;
    this.booted = false;
    this.booting = false;
  }

  HeroMap.prototype.makeIcon = function (color, large) {
    var mobile = window.matchMedia('(max-width: 768px)').matches;
    var visual = large ? (mobile ? 20 : 14) : (mobile ? 14 : 10);
    var hit = large ? (mobile ? 40 : 24) : (mobile ? 28 : 16);
    return window.L.divIcon({
      className: 'home-yvr-leaflet-pin' + (large ? ' home-yvr-leaflet-pin--anchor' : ''),
      html: '<span style="background:' + color + ';width:' + visual + 'px;height:' + visual + 'px"></span>',
      iconSize: [hit, hit],
      iconAnchor: [hit / 2, hit / 2]
    });
  };

  HeroMap.prototype.syncMarkers = function () {
    if (!this.map || !window.L) return;
    var self = this;

    if (this.layer) {
      this.map.removeLayer(this.layer);
    }

    this.layer = window.L.layerGroup().addTo(this.map);

    this.anchors.forEach(function (anchor) {
      var key = anchor.key || '';
      var tier = anchor.tier || '';
      var color = ANCHOR_COLORS[key] || ANCHOR_COLORS[tier] || '#57f3ff';
      var marker = window.L.marker([anchor.lat, anchor.lon], {
        icon: self.makeIcon(color, true),
        zIndexOffset: key === self.activeChannel ? 500 : 100
      });
      marker.bindTooltip(anchor.label + ' — ' + anchor.hint, { direction: 'top' });
      if (key !== 'dave') {
        marker.on('click', function () {
          if (self.onChannelSelect) self.onChannelSelect(key);
        });
      }
      marker.addTo(self.layer);
    });

    var overlay = self.overlays[self.activeChannel] || {};
    (overlay.markers || []).forEach(function (m) {
      var tier = m.tier || 'other';
      var color = TIER_COLORS[tier] || TIER_COLORS.other;
      var om = window.L.marker([m.lat, m.lon], {
        icon: self.makeIcon(color, false),
        zIndexOffset: 200
      });
      om.bindTooltip((m.name || '') + (m.detail ? ' — ' + m.detail : ''));
      if (m.url) {
        om.on('click', function () {
          window.open(m.url, '_blank', 'noopener,noreferrer');
        });
      }
      om.addTo(self.layer);
    });
  };

  HeroMap.prototype.scheduleInvalidate = function () {
    var self = this;
    if (!this.map) return;
    var delays = [0, 80, 240, 600, 1200];
    delays.forEach(function (ms) {
      setTimeout(function () {
        if (self.map) self.map.invalidateSize({ animate: false });
      }, ms);
    });
  };

  HeroMap.prototype.showMapError = function (message) {
    if (!this.stage) return;
    this.stage.classList.add('is-map-error');
    this.stage.setAttribute('data-map-error', message);
  };

  HeroMap.prototype.initMap = function () {
    if (!this.stage || !window.L || this.map) return;

    var bounds = this.bounds;
    this.stage.classList.remove('is-map-error');
    this.stage.removeAttribute('data-map-error');

    this.map = window.L.map(this.stage, {
      zoomControl: true,
      attributionControl: true,
      minZoom: 9,
      maxZoom: 15,
      maxBoundsViscosity: 0.25
    });

    window.L.tileLayer(TILE_URL, {
      attribution: TILE_ATTR,
      subdomains: 'abcd',
      maxZoom: 20
    }).addTo(this.map);

    var latLngBounds = window.L.latLngBounds(
      [bounds.south, bounds.west],
      [bounds.north, bounds.east]
    );
    this.map.fitBounds(latLngBounds, { padding: [28, 28], animate: false });
    this.map.setMaxBounds(latLngBounds.pad(0.4));

    this.syncMarkers();
    this.booted = true;
    this.stage.classList.add('is-map-ready');

    this.scheduleInvalidate();

    var self = this;
    if (typeof ResizeObserver !== 'undefined') {
      var observeTarget = self.stage.parentElement || self.stage;
      var observer = new ResizeObserver(function () {
        self.scheduleInvalidate();
      });
      observer.observe(observeTarget);
      if (observeTarget.parentElement && observeTarget.parentElement !== observeTarget) {
        observer.observe(observeTarget.parentElement);
      }
    }

    window.addEventListener('orientationchange', function () {
      self.scheduleInvalidate();
    });
  };

  HeroMap.prototype.boot = function (config) {
    if (!this.stage || !config || !config.bounds || this.booted || this.booting) {
      return Promise.resolve();
    }
    var self = this;
    this.booting = true;

    this.bounds = config.bounds;
    this.anchors = config.anchors || [];

    loadStyle(LEAFLET_CSS);
    return loadScript(LEAFLET_JS)
      .then(function () {
        self.initMap();
      })
      .catch(function () {
        self.showMapError('Map tiles failed to load — try a refresh.');
      })
      .finally(function () {
        self.booting = false;
      });
  };

  HeroMap.prototype.ensureBoot = function () {
    var self = this;
    if (this.booted || this.booting) return Promise.resolve();

    var config = readMetroConfig();
    if (config) {
      return this.boot(config);
    }

    return fetchMetroConfigFromFeeds().then(function (fetched) {
      if (fetched) {
        return self.boot(fetched);
      }
      self.showMapError('Radar map config missing — try refreshing.');
      return null;
    });
  };

  HeroMap.prototype.setActiveChannel = function (channelKey) {
    this.activeChannel = channelKey || null;
    if (this.booted) {
      this.syncMarkers();
    }
  };

  HeroMap.prototype.setOverlays = function (overlays) {
    this.overlays = overlays || {};
    if (this.activeChannel && this.booted) {
      this.syncMarkers();
    }
  };

  HeroMap.prototype.matchMarkerByText = function (text) {
    if (!text) return null;
    var needle = text.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (!needle || needle.length < 4) return null;

    var best = null;
    var bestLen = 0;
    this.overlayMarkers = (this.overlays[this.activeChannel] || {}).markers || [];
    this.overlayMarkers.forEach(function (marker) {
      var name = (marker.name || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
      if (name.indexOf(needle) !== -1 || needle.indexOf(name) !== -1) {
        if (name.length > bestLen) {
          bestLen = name.length;
          best = marker;
        }
      }
    });
    return best;
  };

  HeroMap.prototype.highlightMarker = function () {
    /* leaflet pins — highlight handled via tooltip on sync */
  };

  function initWanderHint() {
    var storageKey = 'se-yvr-wander-seen';
    if (localStorage.getItem(storageKey)) return;

    var hint = document.querySelector('[data-yvr-wander-hint]');
    var mapWrap = document.querySelector('.home-yvr-radar-deck__map-wrap');
    if (!hint) return;

    hint.hidden = false;
    if (mapWrap) mapWrap.classList.add('is-discover');

    var dismiss = hint.querySelector('[data-yvr-wander-dismiss]');
    if (dismiss) {
      dismiss.addEventListener('click', function () {
        localStorage.setItem(storageKey, '1');
        hint.hidden = true;
        if (mapWrap) mapWrap.classList.remove('is-discover');
      });
    }
  }

  function init() {
    var stage = document.querySelector('[data-home-hero-map]');
    if (!stage || stage.dataset.heroMapReady === '1') return;
    stage.dataset.heroMapReady = '1';

    var heroMap = new HeroMap(stage);
    window.HomeHeroMap = heroMap;

    heroMap.ensureBoot().then(function () {
      initWanderHint();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
