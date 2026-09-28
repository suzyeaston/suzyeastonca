(function () {
  'use strict';

  var MAPLIBRE_CSS = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css';
  var MAPLIBRE_JS = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js';
  var DEFAULT_STYLE = 'https://tiles.openfreemap.org/styles/dark';

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

  var CITY_GEO = {
    place: 'lower mainland',
    line: 'Five posts. Tap one. The stack is the band.'
  };

  var SALISH_GEO = {
    place: 'salish sea',
    line: 'Same water. City up top, Whidbey and Puget Sound down.'
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
            anchors: data.metro_map.anchors || [],
            sites: data.metro_map.sites || [],
            scopes: data.metro_map.scopes || null,
            tiles: data.metro_map.tiles || null
          };
        }
        return null;
      })
      .catch(function () { return null; });
  }

  function indexScopes(raw, fallbackBounds) {
    var out = {};
    if (Array.isArray(raw)) {
      raw.forEach(function (scope) {
        if (scope && scope.id) out[scope.id] = scope;
      });
    } else if (raw && typeof raw === 'object') {
      Object.keys(raw).forEach(function (key) {
        var scope = raw[key];
        if (!scope || typeof scope !== 'object') return;
        if (!scope.id) scope.id = key;
        out[scope.id] = scope;
      });
    }
    if (!out.city && fallbackBounds) {
      out.city = { id: 'city', label: 'city', bounds: fallbackBounds };
    }
    return out;
  }

  function listSites(raw) {
    if (Array.isArray(raw)) return raw;
    if (!raw || typeof raw !== 'object') return [];
    return Object.keys(raw).map(function (key) {
      var site = raw[key];
      if (site && !site.id) site.id = key;
      return site;
    });
  }

  function memberRank(member) {
    if (member.tier === 'bulletin') return 0;
    if (member.tier === 'bed') return 2;
    return 1;
  }

  function groupPosts(anchors, sites) {
    var siteById = {};
    listSites(sites).forEach(function (site) {
      if (site && site.id) siteById[site.id] = site;
    });

    var groups = {};
    var loose = [];
    (anchors || []).forEach(function (anchor) {
      if (!anchor || !anchor.key || anchor.key === 'dave') return;
      if (anchor.site && siteById[anchor.site]) {
        if (!groups[anchor.site]) groups[anchor.site] = [];
        groups[anchor.site].push(anchor);
      } else {
        loose.push(anchor);
      }
    });

    var posts = [];
    Object.keys(groups).forEach(function (id) {
      var site = siteById[id];
      posts.push({
        id: id,
        label: site.label || id,
        place: site.place || site.label || id,
        geo: site.geo || '',
        lat: site.lat,
        lon: site.lon,
        color: site.color || '#57f3ff',
        scope: site.scope || 'city',
        members: groups[id].slice().sort(function (a, b) {
          return memberRank(a) - memberRank(b);
        })
      });
    });

    loose.forEach(function (anchor) {
      posts.push({
        id: anchor.key,
        label: anchor.label || anchor.key,
        place: anchor.place || anchor.label || anchor.key,
        geo: anchor.geo || anchor.hint || '',
        lat: anchor.lat,
        lon: anchor.lon,
        color: ANCHOR_COLORS[anchor.key] || ANCHOR_COLORS[anchor.tier] || '#57f3ff',
        scope: 'city',
        members: [anchor]
      });
    });

    var order = ['sea-island', 'harbour', 'burnaby', 'cape-horn', 'tsawwassen', 'bush-point', 'mast'];
    posts.sort(function (a, b) {
      var ia = order.indexOf(a.id);
      var ib = order.indexOf(b.id);
      return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
    });

    return posts;
  }

  function HeroMap(stage, onChannelSelect) {
    this.stage = stage;
    this.onChannelSelect = onChannelSelect || null;
    this.bounds = null;
    this.anchors = [];
    this.sites = [];
    this.scopes = {};
    this.tileStyle = DEFAULT_STYLE;
    this.scopeId = 'city';
    this.overlays = {};
    this.activeChannel = null;
    this.activePostId = null;
    this.overlayMarkers = [];
    this.posts = [];
    this.markerObjs = [];
    this.map = null;
    this.booted = false;
    this.booting = false;
    this.scanning = false;
    this.scanTimer = null;
    this.scanIndex = 0;
    this.styled = false;
  }

  HeroMap.prototype.visiblePosts = function () {
    var scopeId = this.scopeId;
    return this.posts.filter(function (post) {
      if (scopeId === 'salish') return true;
      return post.scope !== 'salish';
    });
  };

  HeroMap.prototype.findPostByKey = function (key) {
    if (!key) return null;
    var found = null;
    this.posts.forEach(function (post) {
      if (found) return;
      post.members.forEach(function (member) {
        if (member.key === key) found = post;
      });
    });
    return found;
  };

  HeroMap.prototype.clearMarkers = function () {
    (this.markerObjs || []).forEach(function (entry) {
      if (entry.marker && entry.marker.remove) entry.marker.remove();
    });
    this.markerObjs = [];
  };

  HeroMap.prototype.highlightPost = function (id) {
    this.activePostId = id || null;
    (this.markerObjs || []).forEach(function (entry) {
      if (!entry.el || !entry.el.classList) return;
      entry.el.classList.toggle('is-active', !!id && entry.id === id);
    });
  };

  HeroMap.prototype.writeGeo = function (place, line) {
    var placeEl = document.querySelector('[data-yvr-geo-place]');
    var lineEl = document.querySelector('[data-yvr-geo-line]');
    if (placeEl && place) placeEl.textContent = place;
    if (lineEl && line) lineEl.textContent = line;
  };

  HeroMap.prototype.scopeGeo = function () {
    return this.scopeId === 'salish' ? SALISH_GEO : CITY_GEO;
  };

  HeroMap.prototype.fillStack = function (post) {
    var stack = document.querySelector('[data-yvr-stack]');
    if (!stack) return;
    stack.replaceChildren();
    if (!post || !post.members || post.members.length < 2) {
      stack.hidden = true;
      return;
    }
    var self = this;
    post.members.forEach(function (member) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pixel-font';
      var name = document.createElement('span');
      name.textContent = member.label || member.key;
      var freq = document.createElement('span');
      freq.className = 'home-yvr-radar-deck__stack-freq';
      freq.textContent = member.freq || member.hint || '';
      btn.appendChild(name);
      btn.appendChild(freq);
      btn.addEventListener('click', function () {
        self.tune(member.key);
      });
      stack.appendChild(btn);
    });
    stack.hidden = false;
  };

  HeroMap.prototype.preview = function (post) {
    if (!post) return;
    this.writeGeo(post.place, post.geo);
    var band = post.members.length > 1
      ? post.members.length + ' feeds on this post. tap one to listen.'
      : ((post.members[0] && post.members[0].hint) || 'tap to listen.');
    window.dispatchEvent(new CustomEvent('yvr-radar-preview', {
      detail: {
        label: post.label,
        place: post.place,
        geo: post.geo,
        band: band
      }
    }));
  };

  HeroMap.prototype.tune = function (key) {
    this.stopScan();
    this.setActiveChannel(key);
    if (window.HomeYvrBroadcaster && window.HomeYvrBroadcaster.handleMapSelect) {
      window.HomeYvrBroadcaster.handleMapSelect(key);
    } else if (this.onChannelSelect) {
      this.onChannelSelect(key);
    }
  };

  HeroMap.prototype.syncScopeButtons = function () {
    var self = this;
    document.querySelectorAll('[data-yvr-scope]').forEach(function (btn) {
      var id = btn.getAttribute('data-yvr-scope');
      btn.setAttribute('aria-pressed', id === self.scopeId ? 'true' : 'false');
    });
    var scanBtn = document.querySelector('[data-yvr-scan]');
    if (scanBtn) {
      scanBtn.setAttribute('aria-pressed', this.scanning ? 'true' : 'false');
      scanBtn.textContent = this.scanning ? 'stop' : 'scan';
    }
  };

  HeroMap.prototype.openPost = function (post) {
    if (!post || !post.members.length) return;
    this.highlightPost(post.id);
    this.preview(post);
    if (post.members.length === 1) {
      this.fillStack(null);
      this.tune(post.members[0].key);
      return;
    }
    this.stopScan();
    this.fillStack(post);
  };

  HeroMap.prototype.makePostMarker = function (post) {
    var self = this;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'home-yvr-scope-pin';
    btn.style.color = post.color;
    btn.setAttribute('aria-label', post.place + '. ' + post.label);
    if (post.id === this.activePostId) btn.classList.add('is-active');

    var dot = document.createElement('span');
    dot.className = 'home-yvr-scope-pin__dot';
    dot.style.background = post.color;
    var label = document.createElement('span');
    label.className = 'home-yvr-scope-pin__label';
    label.textContent = post.label;
    btn.appendChild(dot);
    btn.appendChild(label);
    btn.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      self.openPost(post);
    });

    var marker = new window.maplibregl.Marker({ element: btn, anchor: 'center' })
      .setLngLat([post.lon, post.lat])
      .addTo(this.map);
    this.markerObjs.push({ id: post.id, el: btn, marker: marker });
  };

  HeroMap.prototype.makeIncidentMarker = function (incident) {
    var self = this;
    var color = TIER_COLORS[incident.tier] || TIER_COLORS.other;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'home-yvr-leaflet-pin';
    btn.style.color = color;
    btn.title = (incident.name || '') + (incident.detail ? ' — ' + incident.detail : '');
    var dot = document.createElement('span');
    dot.style.background = color;
    dot.style.width = '8px';
    dot.style.height = '8px';
    btn.appendChild(dot);
    if (incident.url) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (window.HomeYvrBroadcaster && window.HomeYvrBroadcaster.showMapOverlay) {
          window.HomeYvrBroadcaster.showMapOverlay(incident);
        }
      });
    }
    var marker = new window.maplibregl.Marker({ element: btn, anchor: 'center' })
      .setLngLat([incident.lon, incident.lat])
      .addTo(this.map);
    this.markerObjs.push({ id: '', el: btn, marker: marker });
  };

  HeroMap.prototype.syncMarkers = function () {
    if (!this.map || !window.maplibregl) return;
    this.posts = groupPosts(this.anchors, this.sites);
    this.clearMarkers();
    var self = this;
    this.visiblePosts().forEach(function (post) {
      if (!isFinite(post.lat) || !isFinite(post.lon)) return;
      self.makePostMarker(post);
    });

    var overlay = this.overlays[this.activeChannel] || {};
    (overlay.markers || []).forEach(function (incident) {
      if (!isFinite(incident.lat) || !isFinite(incident.lon)) return;
      self.makeIncidentMarker(incident);
    });

    if (this.activePostId) {
      this.highlightPost(this.activePostId);
    } else if (this.activeChannel) {
      var post = this.findPostByKey(this.activeChannel);
      if (post) this.highlightPost(post.id);
    }
  };

  HeroMap.prototype.renderFallbackList = function () {
    var self = this;
    this.posts = groupPosts(this.anchors, this.sites);
    var stack = document.querySelector('[data-yvr-stack]');
    if (!stack) return;
    stack.replaceChildren();
    this.visiblePosts().forEach(function (post) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pixel-font';
      var name = document.createElement('span');
      name.textContent = post.label;
      var freq = document.createElement('span');
      freq.className = 'home-yvr-radar-deck__stack-freq';
      freq.textContent = post.place;
      btn.appendChild(name);
      btn.appendChild(freq);
      btn.addEventListener('click', function () {
        self.openPost(post);
      });
      stack.appendChild(btn);
    });
    stack.hidden = this.visiblePosts().length === 0;
  };

  HeroMap.prototype.boundsPair = function (bounds) {
    return [
      [bounds.west, bounds.south],
      [bounds.east, bounds.north]
    ];
  };

  HeroMap.prototype.applyScope = function (scopeId, animate) {
    var scope = this.scopes[scopeId] || this.scopes.city;
    if (!scope || !scope.bounds) return;
    this.scopeId = scope.id || scopeId || 'city';
    if (!this.map) {
      this.syncScopeButtons();
      this.renderFallbackList();
      if (!this.activePostId) {
        var idle = this.scopeGeo();
        this.writeGeo(idle.place, idle.line);
      }
      return;
    }
    var bounds = scope.bounds;
    this.map.setMaxBounds([
      [bounds.west - 0.2, bounds.south - 0.12],
      [bounds.east + 0.2, bounds.north + 0.12]
    ]);
    this.map.fitBounds(this.boundsPair(bounds), {
      padding: 18,
      animate: !!animate,
      duration: animate ? 700 : 0
    });
    this.syncMarkers();
    this.syncScopeButtons();
    if (!this.activePostId) {
      var geo = this.scopeGeo();
      this.writeGeo(geo.place, geo.line);
    }
  };

  HeroMap.prototype.stopScan = function () {
    this.scanning = false;
    if (this.scanTimer) {
      clearInterval(this.scanTimer);
      this.scanTimer = null;
    }
    this.syncScopeButtons();
  };

  HeroMap.prototype.toggleScan = function () {
    var self = this;
    if (this.scanning) {
      this.stopScan();
      var geo = this.scopeGeo();
      if (!this.activeChannel) this.writeGeo(geo.place, geo.line);
      return;
    }
    var posts = this.visiblePosts();
    if (!posts.length) return;
    this.scanning = true;
    this.scanIndex = 0;
    this.syncScopeButtons();
    var step = function () {
      var list = self.visiblePosts();
      if (!self.scanning || !list.length) return;
      var post = list[self.scanIndex % list.length];
      self.scanIndex += 1;
      self.highlightPost(post.id);
      self.preview(post);
      if (post.members.length > 1) self.fillStack(post);
      else self.fillStack(null);
    };
    step();
    this.scanTimer = setInterval(step, 2800);
  };

  HeroMap.prototype.wireScopeControls = function () {
    var self = this;
    if (this.controlsWired) return;
    this.controlsWired = true;
    document.querySelectorAll('[data-yvr-scope]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-yvr-scope');
        if (!id || id === self.scopeId) return;
        self.stopScan();
        self.activePostId = null;
        self.fillStack(null);
        self.applyScope(id, true);
      });
    });
    var scanBtn = document.querySelector('[data-yvr-scan]');
    if (scanBtn) {
      scanBtn.addEventListener('click', function () {
        self.toggleScan();
      });
    }
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') self.fillStack(null);
    });
  };

  HeroMap.prototype.scheduleResize = function () {
    var self = this;
    if (!this.map) return;
    [0, 80, 240, 600, 1200].forEach(function (ms) {
      setTimeout(function () {
        if (self.map) self.map.resize();
      }, ms);
    });
  };

  HeroMap.prototype.showMapError = function (message) {
    if (!this.stage) return;
    this.stage.classList.add('is-map-error');
    this.stage.setAttribute('data-map-error', message);
  };

  HeroMap.prototype.initMap = function () {
    if (!this.stage || !window.maplibregl || this.map) return;
    if (window.maplibregl.supported && !window.maplibregl.supported()) {
      this.showMapError('Scope needs WebGL. The band list still works.');
      this.renderFallbackList();
      this.wireScopeControls();
      this.booted = true;
      return;
    }

    var self = this;
    this.stage.classList.remove('is-map-error');
    this.stage.removeAttribute('data-map-error');

    var city = this.scopes.city;
    var bounds = (city && city.bounds) || this.bounds;
    this.map = new window.maplibregl.Map({
      container: this.stage,
      style: this.tileStyle || DEFAULT_STYLE,
      attributionControl: false,
      bounds: this.boundsPair(bounds),
      fitBoundsOptions: { padding: 18 },
      minZoom: 6,
      maxZoom: 14,
      dragRotate: false,
      pitchWithRotate: false,
      touchPitch: false,
      fadeDuration: 0
    });

    this.map.addControl(new window.maplibregl.NavigationControl({
      showCompass: false,
      visualizePitch: false
    }), 'top-right');

    this.map.on('load', function () {
      self.styled = true;
      self.stage.classList.add('is-map-ready');
      self.applyScope(self.scopeId || 'city', false);
      self.scheduleResize();
    });

    this.map.on('error', function (event) {
      var message = event && event.error && event.error.message ? String(event.error.message) : '';
      if (!self.styled && /style|sprite|glyph|ajaxerror/i.test(message)) {
        self.showMapError('Free map tiles failed — try a refresh.');
      }
    });

    setTimeout(function () {
      if (!self.styled && self.map) {
        self.showMapError('Free map tiles failed — try a refresh.');
        self.renderFallbackList();
      }
    }, 12000);

    this.booted = true;
    this.wireScopeControls();

    if (typeof ResizeObserver !== 'undefined') {
      var observeTarget = self.stage.parentElement || self.stage;
      var observer = new ResizeObserver(function () {
        self.scheduleResize();
      });
      observer.observe(observeTarget);
    }

    window.addEventListener('orientationchange', function () {
      self.scheduleResize();
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
    this.sites = listSites(config.sites);
    this.scopes = indexScopes(config.scopes, config.bounds);
    this.tileStyle = (config.tiles && config.tiles.style) || DEFAULT_STYLE;
    if (/cartocdn|carto\.com/i.test(this.tileStyle)) {
      this.tileStyle = DEFAULT_STYLE;
    }

    loadStyle(MAPLIBRE_CSS);
    return loadScript(MAPLIBRE_JS)
      .then(function () {
        self.initMap();
      })
      .catch(function () {
        self.showMapError('Map library failed to load — try a refresh.');
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
    var post = this.findPostByKey(channelKey);
    if (post) {
      this.writeGeo(post.place, post.geo);
      this.highlightPost(post.id);
    }
    if (this.booted && this.styled) {
      this.syncMarkers();
    }
  };

  HeroMap.prototype.setOverlays = function (overlays) {
    this.overlays = overlays || {};
    if (this.activeChannel && this.booted && this.styled) {
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
    /* post glow is handled in highlightPost */
  };

  function initWanderHint() {
    var storageKey = 'se-yvr-wander-seen';
    if (localStorage.getItem(storageKey)) return;

    var hint = document.querySelector('[data-yvr-wander-hint]');
    var mapWrap = document.querySelector('.home-yvr-radar-deck__map-wrap');
    if (!hint) return;

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    hint.hidden = false;
    if (mapWrap && !reduceMotion) mapWrap.classList.add('is-discover');

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
