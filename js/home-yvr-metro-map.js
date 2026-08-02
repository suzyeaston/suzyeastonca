(function () {
  'use strict';

  var MAP_WIDTH = 360;
  var MAP_HEIGHT = 200;

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

  // Greater Vancouver land mask (lon, lat).
  var METRO_LAND = [
    [-123.40, 49.38],
    [-123.36, 49.34],
    [-123.28, 49.30],
    [-123.18, 49.28],
    [-123.08, 49.27],
    [-122.28, 49.22],
    [-122.28, 49.02],
    [-123.02, 48.97],
    [-123.40, 48.97],
    [-123.40, 49.38]
  ];

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function project(lon, lat, bounds) {
    var x = ((lon - bounds.west) / (bounds.east - bounds.west)) * MAP_WIDTH;
    var y = ((bounds.north - lat) / (bounds.north - bounds.south)) * MAP_HEIGHT;
    return { x: x, y: y };
  }

  function MetroMap(root, onChannelSelect) {
    this.root = root;
    this.wrap = root.querySelector('[data-broadcaster-map-wrap]');
    this.stage = root.querySelector('[data-broadcaster-map-stage]');
    this.svg = root.querySelector('[data-broadcaster-map-svg]');
    this.tooltip = root.querySelector('[data-broadcaster-map-tooltip]');
    this.legendEl = root.querySelector('[data-broadcaster-map-legend]');
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
    this._boundWheel = this.onWheel.bind(this);
    this._boundPointerDown = this.onPointerDown.bind(this);
    this._boundPointerMove = this.onPointerMove.bind(this);
    this._boundPointerUp = this.onPointerUp.bind(this);
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

  MetroMap.prototype.onPointerUp = function () {
    this.dragging = false;
    this.dragStart = null;
  };

  MetroMap.prototype.hideTooltip = function () {
    if (!this.tooltip) return;
    this.tooltip.hidden = true;
    this.tooltip.textContent = '';
  };

  MetroMap.prototype.showTooltip = function (parts, clientX, clientY) {
    if (!this.tooltip || !this.stage) return;
    this.tooltip.innerHTML = escapeHtml(parts.filter(Boolean).join(' · '));
    this.tooltip.hidden = false;
    var rect = this.stage.getBoundingClientRect();
    var left = Math.max(4, Math.min(clientX - rect.left + 8, rect.width - this.tooltip.offsetWidth - 4));
    var top = Math.max(4, Math.min(clientY - rect.top - 28, rect.height - this.tooltip.offsetHeight - 4));
    this.tooltip.style.left = left + 'px';
    this.tooltip.style.top = top + 'px';
  };

  MetroMap.prototype.setLegend = function (text) {
    if (!this.legendEl) return;
    this.legendEl.innerHTML = text || '';
    this.legendEl.setAttribute('aria-hidden', text ? 'false' : 'true');
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

    parts.push(
      '<rect x="0" y="0" width="' + MAP_WIDTH + '" height="' + MAP_HEIGHT + '" fill="#030810"></rect>'
    );

    var landPts = METRO_LAND.map(function (pt) {
      var p = project(pt[0], pt[1], bounds);
      return p.x.toFixed(1) + ',' + p.y.toFixed(1);
    }).join(' ');
    parts.push(
      '<polygon points="' + landPts + '" fill="#0a1418" stroke="#1a3a32" stroke-width="1.2"></polygon>'
    );

    for (var gx = 0; gx <= MAP_WIDTH; gx += 36) {
      parts.push('<line x1="' + gx + '" y1="0" x2="' + gx + '" y2="' + MAP_HEIGHT + '" stroke="rgba(57,255,20,0.06)" stroke-width="1"></line>');
    }
    for (var gy = 0; gy <= MAP_HEIGHT; gy += 40) {
      parts.push('<line x1="0" y1="' + gy + '" x2="' + MAP_WIDTH + '" y2="' + gy + '" stroke="rgba(57,255,20,0.06)" stroke-width="1"></line>');
    }

    parts.push(
      '<rect x="1" y="1" width="' + (MAP_WIDTH - 2) + '" height="' + (MAP_HEIGHT - 2) + '" fill="none" stroke="rgba(87,243,255,0.22)" stroke-width="1"></rect>'
    );

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
          '<text class="home-yvr-map__anchor-label" y="-10" text-anchor="middle">' + escapeHtml(anchor.label || key) + '</text>' +
        '</g>'
      );
    });
    parts.push('</g>');

    parts.push('<g data-map-overlay></g>');
    parts.push('</g>');

    this.svg.innerHTML = parts.join('');

    var self = this;
    this.anchors.forEach(function (anchor) {
      var key = anchor.key || '';
      var el = self.svg.querySelector('[data-map-anchor="' + CSS.escape(key) + '"]');
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
      el.addEventListener('click', function () {
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

    this.applyTransform();
    this.setActiveChannel(this.activeChannel);
  };

  MetroMap.prototype.renderOverlay = function (channelKey) {
    var layer = this.svg && this.svg.querySelector('[data-map-overlay]');
    if (!layer || !this.bounds) return;

    var overlay = this.overlays[channelKey] || {};
    var markers = overlay.markers || [];
    this.overlayMarkers = markers;
    this.overlayEls = {};

    var bounds = this.bounds;
    var html = '';

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

    var self = this;
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
      el.addEventListener('click', function () {
        if (marker.url) window.open(marker.url, '_blank', 'noopener,noreferrer');
      });
    });

    if (markers.length) {
      this.setLegend('<span class="home-yvr-broadcaster__map-legend-item is-other">' + markers.length + ' on map</span>');
    } else if (channelKey) {
      this.setLegend('<span class="home-yvr-broadcaster__map-legend-item">no pins in metro</span>');
    } else {
      this.setLegend('');
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
  };

  MetroMap.prototype.setOverlays = function (overlays) {
    this.overlays = overlays || {};
    if (this.activeChannel) {
      this.renderOverlay(this.activeChannel);
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

  MetroMap.prototype.boot = function (config) {
    if (!this.wrap) return;
    this.wrap.hidden = false;
    this.bindStage();
    this.buildBase(config);
    this.setLegend('tap a pin · scroll zoom');
  };

  window.HomeYvrMetroMap = MetroMap;
}());
