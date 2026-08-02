(function () {
  'use strict';

  var MAP_WIDTH = 360;
  var MAP_HEIGHT = 200;

  var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  var LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
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
    cknw: '#ffb347'
  };

  var TIER_COLORS = {
    ooc: '#ff4b4b',
    held: '#ffe66d',
    other: '#57f3ff',
    alert: '#ffe66d',
    air: '#7effc6',
    ferry: '#57f3ff'
  };

  // Hand-tuned Greater Vancouver land regions (lon, lat).
  var METRO_REGIONS = [
    {
      id: 'north-shore',
      label: 'North Shore',
      points: [
        [-123.40, 49.38], [-123.40, 49.33], [-123.32, 49.325], [-123.18, 49.318],
        [-123.08, 49.312], [-122.92, 49.308], [-122.78, 49.304],
        [-122.78, 49.32], [-122.88, 49.34], [-123.05, 49.36], [-123.25, 49.37], [-123.40, 49.38]
      ]
    },
    {
      id: 'vancouver',
      label: 'Vancouver',
      points: [
        [-123.40, 49.29], [-123.36, 49.31], [-123.28, 49.315], [-123.22, 49.312],
        [-123.18, 49.308], [-123.15, 49.302], [-123.14, 49.288], [-123.13, 49.272],
        [-123.11, 49.262], [-123.04, 49.258], [-122.86, 49.248], [-122.78, 49.238],
        [-122.78, 49.22], [-122.92, 49.218], [-123.08, 49.232], [-123.18, 49.255],
        [-123.28, 49.275], [-123.36, 49.285], [-123.40, 49.29]
      ]
    },
    {
      id: 'stanley-park',
      label: '',
      points: [
        [-123.22, 49.308], [-123.17, 49.312], [-123.14, 49.304], [-123.15, 49.292],
        [-123.19, 49.295], [-123.22, 49.308]
      ]
    },
    {
      id: 'burnaby',
      label: 'Burnaby',
      points: [
        [-122.78, 49.238], [-122.78, 49.304], [-122.92, 49.308], [-123.04, 49.288],
        [-123.04, 49.258], [-122.90, 49.242], [-122.78, 49.238]
      ]
    },
    {
      id: 'richmond',
      label: 'Richmond',
      points: [
        [-123.40, 49.26], [-123.34, 49.23], [-123.22, 49.18], [-123.10, 49.155],
        [-123.02, 49.158], [-123.04, 49.19], [-123.10, 49.22], [-123.22, 49.245],
        [-123.34, 49.255], [-123.40, 49.26]
      ]
    },
    {
      id: 'surrey-delta',
      label: 'Surrey / Delta',
      points: [
        [-122.78, 49.238], [-122.28, 49.20], [-122.28, 48.97], [-123.02, 48.97],
        [-123.06, 49.10], [-123.02, 49.155], [-122.92, 49.20], [-122.78, 49.238]
      ]
    }
  ];

  var FRASER_RIVER = [
    [-123.22, 49.24], [-123.12, 49.18], [-123.06, 49.12], [-123.08, 48.98]
  ];

  var WATER_LABELS = [
    { text: 'Burrard Inlet', lon: -123.14, lat: 49.305 },
    { text: 'Strait of Georgia', lon: -123.34, lat: 49.22 },
    { text: 'Fraser River', lon: -123.10, lat: 49.14 }
  ];

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function project(lon, lat, bounds, width, height) {
    width = width || MAP_WIDTH;
    height = height || MAP_HEIGHT;
    var x = ((lon - bounds.west) / (bounds.east - bounds.west)) * width;
    var y = ((bounds.north - lat) / (bounds.north - bounds.south)) * height;
    return { x: x, y: y };
  }

  function polyPoints(coords, bounds, width, height) {
    return coords.map(function (pt) {
      var p = project(pt[0], pt[1], bounds, width, height);
      return p.x.toFixed(1) + ',' + p.y.toFixed(1);
    }).join(' ');
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[src="' + src + '"]');
      if (existing) {
        if (existing.dataset.loaded === '1') resolve();
        else existing.addEventListener('load', resolve);
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

  function MetroMap(root, onChannelSelect) {
    this.root = root;
    this.wrap = root.querySelector('[data-broadcaster-map-wrap]');
    this.stage = root.querySelector('[data-broadcaster-map-stage]');
    this.svg = root.querySelector('[data-broadcaster-map-svg]');
    this.tooltip = root.querySelector('[data-broadcaster-map-tooltip]');
    this.legendEl = root.querySelector('[data-broadcaster-map-legend]');
    this.expandBtn = root.querySelector('[data-broadcaster-map-expand]');
    this.modal = root.querySelector('[data-broadcaster-map-modal]');
    this.modalStage = root.querySelector('[data-broadcaster-map-modal-stage]');
    this.modalTooltip = root.querySelector('[data-broadcaster-map-modal-tooltip]');
    this.onChannelSelect = onChannelSelect || null;
    this.bounds = null;
    this.anchors = [];
    this.overlays = {};
    this.activeChannel = null;
    this.overlayMarkers = [];
    this.overlayEls = {};
    this.scale = 1;
    this.tx = 0;
    this.ty = 0;
    this.dragging = false;
    this.dragStart = null;
    this.leafletMap = null;
    this.leafletLayer = null;
    this._boundWheel = this.onWheel.bind(this);
    this._boundPointerDown = this.onPointerDown.bind(this);
    this._boundPointerMove = this.onPointerMove.bind(this);
    this._boundPointerUp = this.onPointerUp.bind(this);
    this._boundKeydown = this.onKeydown.bind(this);
  }

  MetroMap.prototype.bindStage = function () {
    if (!this.stage) return;
    this.stage.addEventListener('wheel', this._boundWheel, { passive: false });
    this.stage.addEventListener('pointerdown', this._boundPointerDown);
    this.stage.addEventListener('pointermove', this._boundPointerMove);
    this.stage.addEventListener('pointerup', this._boundPointerUp);
    this.stage.addEventListener('pointerleave', this._boundPointerUp);
  };

  MetroMap.prototype.applyTransform = function () {
    var world = this.svg && this.svg.querySelector('[data-map-world]');
    if (!world) return;
    world.setAttribute(
      'transform',
      'translate(' + this.tx + ',' + this.ty + ') scale(' + this.scale + ')'
    );
  };

  MetroMap.prototype.onWheel = function (event) {
    event.preventDefault();
    var delta = event.deltaY > 0 ? -0.12 : 0.12;
    this.scale = Math.min(2.6, Math.max(1, this.scale + delta));
    if (this.scale === 1) {
      this.tx = 0;
      this.ty = 0;
    }
    this.applyTransform();
  };

  MetroMap.prototype.onPointerDown = function (event) {
    if (event.target.closest('[data-map-anchor]') || event.target.closest('[data-map-marker]')) return;
    this.dragging = true;
    this.dragStart = { x: event.clientX, y: event.clientY, tx: this.tx, ty: this.ty };
    if (this.stage) this.stage.setPointerCapture(event.pointerId);
  };

  MetroMap.prototype.onPointerMove = function (event) {
    if (!this.dragging || !this.dragStart || this.scale <= 1) return;
    this.tx = this.dragStart.tx + (event.clientX - this.dragStart.x);
    this.ty = this.dragStart.ty + (event.clientY - this.dragStart.y);
    this.applyTransform();
  };

  MetroMap.prototype.onPointerUp = function (event) {
    if (this.dragging && this.dragStart && this.scale <= 1) {
      var dx = event.clientX - this.dragStart.x;
      var dy = event.clientY - this.dragStart.y;
      if (Math.abs(dx) < 6 && Math.abs(dy) < 6) {
        this.openExpand();
      }
    }
    this.dragging = false;
    this.dragStart = null;
  };

  MetroMap.prototype.onKeydown = function (event) {
    if (event.key === 'Escape' && this.modal && !this.modal.hidden) {
      this.closeExpand();
    }
  };

  MetroMap.prototype.hideTooltip = function (el) {
    var node = el || this.tooltip;
    if (!node) return;
    node.hidden = true;
    node.textContent = '';
  };

  MetroMap.prototype.showTooltip = function (parts, clientX, clientY, container, tooltipEl) {
    var tip = tooltipEl || this.tooltip;
    var box = container || this.stage;
    if (!tip || !box) return;
    tip.innerHTML = escapeHtml(parts.filter(Boolean).join(' · '));
    tip.hidden = false;
    var rect = box.getBoundingClientRect();
    var left = Math.max(4, Math.min(clientX - rect.left + 8, rect.width - tip.offsetWidth - 4));
    var top = Math.max(4, Math.min(clientY - rect.top - 28, rect.height - tip.offsetHeight - 4));
    tip.style.left = left + 'px';
    tip.style.top = top + 'px';
  };

  MetroMap.prototype.setLegend = function (text) {
    if (!this.legendEl) return;
    this.legendEl.innerHTML = text || '';
    this.legendEl.setAttribute('aria-hidden', text ? 'false' : 'true');
  };

  MetroMap.prototype.buildGeographySvg = function (bounds, width, height) {
    var parts = [];
    parts.push(
      '<rect x="0" y="0" width="' + width + '" height="' + height + '" fill="#020610"></rect>'
    );

    METRO_REGIONS.forEach(function (region) {
      parts.push(
        '<polygon class="home-yvr-map__land" data-region="' + region.id + '" points="' +
          polyPoints(region.points, bounds, width, height) +
          '" fill="#0c1814" stroke="#1e4a38" stroke-width="1" stroke-linejoin="round"></polygon>'
      );
      if (region.label) {
        var c = region.points[Math.floor(region.points.length / 2)];
        var lp = project(c[0], c[1], bounds, width, height);
        parts.push(
          '<text class="home-yvr-map__region-label" x="' + lp.x.toFixed(1) + '" y="' + lp.y.toFixed(1) +
            '" text-anchor="middle">' + escapeHtml(region.label) + '</text>'
        );
      }
    });

    var riverPts = FRASER_RIVER.map(function (pt, i) {
      var p = project(pt[0], pt[1], bounds, width, height);
      return (i === 0 ? 'M' : 'L') + p.x.toFixed(1) + ',' + p.y.toFixed(1);
    }).join(' ');
    parts.push(
      '<path class="home-yvr-map__river" d="' + riverPts + '" fill="none" stroke="rgba(87,243,255,0.35)" stroke-width="2.5" stroke-linecap="round"></path>'
    );

    WATER_LABELS.forEach(function (label) {
      var p = project(label.lon, label.lat, bounds, width, height);
      parts.push(
        '<text class="home-yvr-map__water-label" x="' + p.x.toFixed(1) + '" y="' + p.y.toFixed(1) +
          '" text-anchor="middle">' + escapeHtml(label.text) + '</text>'
      );
    });

    parts.push(
      '<rect x="1" y="1" width="' + (width - 2) + '" height="' + (height - 2) +
        '" fill="none" stroke="rgba(87,243,255,0.18)" stroke-width="1"></rect>'
    );

    return parts.join('');
  };

  MetroMap.prototype.buildBase = function (config) {
    if (!this.svg || !config) return;
    this.bounds = config.bounds;
    this.anchors = config.anchors || [];
    this.scale = 1;
    this.tx = 0;
    this.ty = 0;

    var bounds = this.bounds;
    var parts = [];
    parts.push('<g data-map-world>');
    parts.push(this.buildGeographySvg(bounds, MAP_WIDTH, MAP_HEIGHT));

    parts.push('<g data-map-anchors>');
    this.anchors.forEach(function (anchor) {
      var pt = project(anchor.lon, anchor.lat, bounds);
      var key = anchor.key || '';
      var color = ANCHOR_COLORS[key] || '#57f3ff';
      var isDave = key === 'dave';
      parts.push(
        '<g class="home-yvr-map__anchor" data-map-anchor="' + escapeHtml(key) + '" transform="translate(' + pt.x.toFixed(1) + ',' + pt.y.toFixed(1) + ')" tabindex="0" role="button" aria-label="' + escapeHtml(anchor.label || key) + '">' +
          (isDave ? '<circle class="home-yvr-map__yvr-ring" r="7" fill="none" stroke="rgba(57,255,20,0.5)" stroke-width="1"></circle>' : '') +
          '<circle class="home-yvr-map__anchor-pulse" r="8" fill="' + color + '" opacity="0.12"></circle>' +
          '<circle class="home-yvr-map__anchor-dot" r="3.8" fill="' + color + '" stroke="#020308" stroke-width="1.2"></circle>' +
          '<text class="home-yvr-map__anchor-label">' + escapeHtml(anchor.label || key) + '</text>' +
        '</g>'
      );
    });
    parts.push('</g>');
    parts.push('<g data-map-overlay></g>');
    parts.push('</g>');

    this.svg.innerHTML = parts.join('');
    this.bindAnchorEvents(this.svg);
    this.applyTransform();
    this.setActiveChannel(this.activeChannel);
  };

  MetroMap.prototype.bindAnchorEvents = function (root) {
    var self = this;
    this.anchors.forEach(function (anchor) {
      var key = anchor.key || '';
      var el = root.querySelector('[data-map-anchor="' + CSS.escape(key) + '"]');
      if (!el) return;

      el.addEventListener('pointerenter', function (event) {
        self.showTooltip([anchor.label, anchor.hint], event.clientX, event.clientY);
      });
      el.addEventListener('pointermove', function (event) {
        self.showTooltip([anchor.label, anchor.hint], event.clientX, event.clientY);
      });
      el.addEventListener('pointerleave', function () {
        self.hideTooltip();
      });
      el.addEventListener('click', function (event) {
        event.stopPropagation();
        if (key === 'dave') return;
        if (self.onChannelSelect) self.onChannelSelect(key);
      });
      el.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          if (key !== 'dave' && self.onChannelSelect) self.onChannelSelect(key);
        }
      });
    });
  };

  MetroMap.prototype.renderOverlay = function (channelKey, root) {
    var svg = root || this.svg;
    var layer = svg && svg.querySelector('[data-map-overlay]');
    if (!layer || !this.bounds) return;

    var overlay = this.overlays[channelKey] || {};
    var markers = overlay.markers || [];
    this.overlayMarkers = markers;
    this.overlayEls = {};

    var bounds = this.bounds;
    var html = '';
    var self = this;

    markers.forEach(function (marker) {
      var pt = project(marker.lon, marker.lat, bounds);
      var tier = marker.tier || 'other';
      var color = TIER_COLORS[tier] || TIER_COLORS.other;
      var id = marker.id || marker.name;
      html +=
        '<g class="home-yvr-map__marker is-' + tier + '" data-map-marker="' + escapeHtml(id) + '" transform="translate(' + pt.x.toFixed(1) + ',' + pt.y.toFixed(1) + ')" tabindex="0" role="button">' +
          '<circle class="home-yvr-map__pulse" r="9" fill="' + color + '" opacity="0.2"></circle>' +
          '<circle class="home-yvr-map__dot" r="4.5" fill="' + color + '" stroke="#020308" stroke-width="1.2"></circle>' +
        '</g>';
    });

    layer.innerHTML = html;

    markers.forEach(function (marker) {
      var id = marker.id || marker.name;
      var el = layer.querySelector('[data-map-marker="' + CSS.escape(id) + '"]');
      if (!el) return;
      self.overlayEls[id] = el;

      el.addEventListener('pointerenter', function (event) {
        self.showTooltip([marker.name, marker.detail], event.clientX, event.clientY);
      });
      el.addEventListener('pointermove', function (event) {
        self.showTooltip([marker.name, marker.detail], event.clientX, event.clientY);
      });
      el.addEventListener('pointerleave', function () {
        self.hideTooltip();
      });
      el.addEventListener('click', function (event) {
        event.stopPropagation();
        if (marker.url) window.open(marker.url, '_blank', 'noopener,noreferrer');
      });
    });

    if (!root) {
      if (markers.length) {
        this.setLegend('<span class="home-yvr-broadcaster__map-legend-item is-other">' + markers.length + ' on map</span>');
      } else if (channelKey) {
        this.setLegend('<span class="home-yvr-broadcaster__map-legend-item">no pins in metro</span>');
      } else {
        this.setLegend('<span class="home-yvr-broadcaster__map-legend-item">tap map to expand</span>');
      }
    }
  };

  MetroMap.prototype.setActiveChannel = function (channelKey) {
    this.activeChannel = channelKey || null;
    if (!this.svg) return;

    var anchors = this.svg.querySelectorAll('[data-map-anchor]');
    anchors.forEach(function (el) {
      var key = el.getAttribute('data-map-anchor');
      var on = key === channelKey;
      el.classList.toggle('is-active', on);
      el.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    this.renderOverlay(channelKey);
    if (this.leafletMap) {
      this.syncLeafletMarkers();
    }
  };

  MetroMap.prototype.setOverlays = function (overlays) {
    this.overlays = overlays || {};
    if (this.activeChannel) {
      this.renderOverlay(this.activeChannel);
    }
    if (this.leafletMap) {
      this.syncLeafletMarkers();
    }
  };

  MetroMap.prototype.matchMarkerByText = function (text) {
    if (!text) return null;
    var needle = text.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (!needle || needle.length < 4) return null;

    var best = null;
    var bestLen = 0;
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

  MetroMap.prototype.highlightMarker = function (id) {
    Object.keys(this.overlayEls).forEach(function (key) {
      var el = this.overlayEls[key];
      if (el) el.classList.toggle('is-active', key === id);
    }, this);
  };

  MetroMap.prototype.makeLeafletIcon = function (color, large) {
    var size = large ? 14 : 10;
    return window.L.divIcon({
      className: 'home-yvr-leaflet-pin',
      html: '<span style="background:' + color + ';width:' + size + 'px;height:' + size + 'px"></span>',
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2]
    });
  };

  MetroMap.prototype.syncLeafletMarkers = function () {
    if (!this.leafletMap || !window.L) return;
    var self = this;

    if (this.leafletLayer) {
      this.leafletMap.removeLayer(this.leafletLayer);
    }

    this.leafletLayer = window.L.layerGroup().addTo(this.leafletMap);

    this.anchors.forEach(function (anchor) {
      var key = anchor.key || '';
      var color = ANCHOR_COLORS[key] || '#57f3ff';
      var marker = window.L.marker([anchor.lat, anchor.lon], {
        icon: self.makeLeafletIcon(color, true),
        zIndexOffset: key === self.activeChannel ? 500 : 100
      });
      marker.bindTooltip(anchor.label + ' — ' + anchor.hint);
      if (key !== 'dave') {
        marker.on('click', function () {
          if (self.onChannelSelect) self.onChannelSelect(key);
        });
      }
      marker.addTo(self.leafletLayer);
    });

    var overlay = self.overlays[self.activeChannel] || {};
    (overlay.markers || []).forEach(function (m) {
      var tier = m.tier || 'other';
      var color = TIER_COLORS[tier] || TIER_COLORS.other;
      var om = window.L.marker([m.lat, m.lon], {
        icon: self.makeLeafletIcon(color, false),
        zIndexOffset: 200
      });
      om.bindTooltip((m.name || '') + (m.detail ? ' — ' + m.detail : ''));
      if (m.url) {
        om.on('click', function () {
          window.open(m.url, '_blank', 'noopener,noreferrer');
        });
      }
      om.addTo(self.leafletLayer);
    });
  };

  MetroMap.prototype.initLeaflet = function () {
    if (!this.modalStage || !window.L || this.leafletMap) return;

    var bounds = this.bounds;
    this.leafletMap = window.L.map(this.modalStage, {
      zoomControl: true,
      attributionControl: true,
      minZoom: 9,
      maxZoom: 15
    });

    window.L.tileLayer(TILE_URL, {
      attribution: TILE_ATTR,
      subdomains: 'abcd',
      maxZoom: 20
    }).addTo(this.leafletMap);

    var latLngBounds = window.L.latLngBounds(
      [bounds.south, bounds.west],
      [bounds.north, bounds.east]
    );
    this.leafletMap.fitBounds(latLngBounds, { padding: [24, 24] });
    this.leafletMap.setMaxBounds(latLngBounds.pad(0.15));

    this.syncLeafletMarkers();

    setTimeout(function () {
      if (this.leafletMap) this.leafletMap.invalidateSize();
    }.bind(this), 80);
  };

  MetroMap.prototype.openExpand = function () {
    var self = this;
    if (!this.modal) return;

    this.modal.hidden = false;
    document.body.classList.add('home-yvr-map-modal-open');
    document.addEventListener('keydown', this._boundKeydown);

    loadStyle(LEAFLET_CSS);
    loadScript(LEAFLET_JS)
      .then(function () {
        self.initLeaflet();
      })
      .catch(function () {
        /* widget SVG remains usable */
      });
  };

  MetroMap.prototype.closeExpand = function () {
    if (!this.modal) return;
    this.modal.hidden = true;
    document.body.classList.remove('home-yvr-map-modal-open');
    document.removeEventListener('keydown', this._boundKeydown);
  };

  MetroMap.prototype.boot = function (config) {
    if (!this.wrap) return;
    this.wrap.hidden = false;
    this.bindStage();
    this.buildBase(config);
    this.setLegend('tap map to expand · pins switch channels');

    var self = this;
    if (this.expandBtn) {
      this.expandBtn.addEventListener('click', function (event) {
        event.stopPropagation();
        self.openExpand();
      });
    }

    if (this.modal) {
      var closeBtns = this.modal.querySelectorAll('[data-broadcaster-map-close]');
      closeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          self.closeExpand();
        });
      });
    }
  };

  window.HomeYvrMetroMap = MetroMap;
}());
