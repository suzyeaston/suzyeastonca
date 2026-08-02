(function () {
  'use strict';

  var MAP_WIDTH = 360;
  var MAP_HEIGHT = 200;

  var TIER_COLORS = {
    ooc: '#ff4b4b',
    held: '#ffe66d',
    other: '#57f3ff'
  };

  // Rough mainland / island silhouette for the southwest corridor (lon, lat).
  var COAST_PATH = [
    [-123.45, 48.88],
    [-123.42, 49.05],
    [-123.35, 49.18],
    [-123.28, 49.32],
    [-123.22, 49.48],
    [-123.18, 49.62],
    [-123.12, 49.78],
    [-123.05, 49.92],
    [-122.98, 50.08],
    [-122.88, 50.22],
    [-122.75, 50.35],
    [-122.55, 50.42],
    [-121.95, 50.38],
    [-121.35, 50.25],
    [-120.75, 50.05],
    [-120.15, 49.82],
    [-119.55, 49.55],
    [-119.25, 49.25],
    [-119.25, 48.88],
    [-123.45, 48.88]
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

  function tierCounts(markers) {
    var counts = { ooc: 0, held: 0, other: 0 };
    markers.forEach(function (m) {
      if (counts[m.tier] !== undefined) counts[m.tier] += 1;
    });
    return counts;
  }

  function WildfireMap(root) {
    this.root = root;
    this.wrap = root.querySelector('[data-broadcaster-map-wrap]');
    this.stage = root.querySelector('[data-broadcaster-map-stage]');
    this.svg = root.querySelector('[data-broadcaster-map-svg]');
    this.tooltip = root.querySelector('[data-broadcaster-map-tooltip]');
    this.legendEl = root.querySelector('[data-broadcaster-map-legend]');
    this.bounds = null;
    this.scale = 1;
    this.tx = 0;
    this.ty = 0;
    this.dragging = false;
    this.dragStart = null;
    this.markers = [];
    this.markerEls = {};
    this.activeId = null;
    this._boundWheel = this.onWheel.bind(this);
    this._boundPointerDown = this.onPointerDown.bind(this);
    this._boundPointerMove = this.onPointerMove.bind(this);
    this._boundPointerUp = this.onPointerUp.bind(this);
  }

  WildfireMap.prototype.unbindStage = function () {
    if (!this.stage) return;
    this.stage.removeEventListener('wheel', this._boundWheel);
    this.stage.removeEventListener('pointerdown', this._boundPointerDown);
    this.stage.removeEventListener('pointermove', this._boundPointerMove);
    this.stage.removeEventListener('pointerup', this._boundPointerUp);
    this.stage.removeEventListener('pointerleave', this._boundPointerUp);
  };

  WildfireMap.prototype.bindStage = function () {
    if (!this.stage) return;
    this.stage.addEventListener('wheel', this._boundWheel, { passive: false });
    this.stage.addEventListener('pointerdown', this._boundPointerDown);
    this.stage.addEventListener('pointermove', this._boundPointerMove);
    this.stage.addEventListener('pointerup', this._boundPointerUp);
    this.stage.addEventListener('pointerleave', this._boundPointerUp);
  };

  WildfireMap.prototype.hide = function () {
    if (this.wrap) {
      this.wrap.hidden = true;
    }
    this.hideTooltip();
    this.unbindStage();
  };

  WildfireMap.prototype.show = function () {
    if (this.wrap) {
      this.wrap.hidden = false;
    }
    this.bindStage();
  };

  WildfireMap.prototype.applyTransform = function () {
    var world = this.svg && this.svg.querySelector('[data-map-world]');
    if (!world) return;
    world.setAttribute(
      'transform',
      'translate(' + this.tx + ',' + this.ty + ') scale(' + this.scale + ')'
    );
  };

  WildfireMap.prototype.onWheel = function (event) {
    event.preventDefault();
    var delta = event.deltaY > 0 ? -0.12 : 0.12;
    this.scale = Math.min(2.8, Math.max(1, this.scale + delta));
    if (this.scale === 1) {
      this.tx = 0;
      this.ty = 0;
    }
    this.applyTransform();
  };

  WildfireMap.prototype.onPointerDown = function (event) {
    if (event.target.closest('[data-map-marker]')) return;
    this.dragging = true;
    this.dragStart = { x: event.clientX, y: event.clientY, tx: this.tx, ty: this.ty };
    if (this.stage) this.stage.setPointerCapture(event.pointerId);
  };

  WildfireMap.prototype.onPointerMove = function (event) {
    if (!this.dragging || !this.dragStart || this.scale <= 1) return;
    var dx = event.clientX - this.dragStart.x;
    var dy = event.clientY - this.dragStart.y;
    this.tx = this.dragStart.tx + dx;
    this.ty = this.dragStart.ty + dy;
    this.applyTransform();
  };

  WildfireMap.prototype.onPointerUp = function () {
    this.dragging = false;
    this.dragStart = null;
  };

  WildfireMap.prototype.hideTooltip = function () {
    if (!this.tooltip) return;
    this.tooltip.hidden = true;
    this.tooltip.textContent = '';
  };

  WildfireMap.prototype.showTooltip = function (marker, clientX, clientY) {
    if (!this.tooltip || !this.stage) return;
    var parts = [marker.name];
    if (marker.status) parts.push(marker.status);
    if (marker.age_label) parts.push(marker.age_label);
    this.tooltip.innerHTML = escapeHtml(parts.join(' · '));
    this.tooltip.hidden = false;

    var rect = this.stage.getBoundingClientRect();
    var left = clientX - rect.left + 8;
    var top = clientY - rect.top - 28;
    left = Math.max(4, Math.min(left, rect.width - this.tooltip.offsetWidth - 4));
    top = Math.max(4, Math.min(top, rect.height - this.tooltip.offsetHeight - 4));
    this.tooltip.style.left = left + 'px';
    this.tooltip.style.top = top + 'px';
  };

  WildfireMap.prototype.setLegend = function (markers) {
    if (!this.legendEl) return;
    var counts = tierCounts(markers);
    var bits = [];
    if (counts.ooc) bits.push('<span class="home-yvr-broadcaster__map-legend-item is-ooc">' + counts.ooc + ' OOC</span>');
    if (counts.held) bits.push('<span class="home-yvr-broadcaster__map-legend-item is-held">' + counts.held + ' held</span>');
    if (counts.other) bits.push('<span class="home-yvr-broadcaster__map-legend-item is-other">' + counts.other + ' other</span>');
    this.legendEl.innerHTML = bits.join('');
    this.legendEl.setAttribute('aria-hidden', bits.length ? 'false' : 'true');
  };

  WildfireMap.prototype.highlightMarker = function (id) {
    this.activeId = id || null;
    Object.keys(this.markerEls).forEach(function (key) {
      var el = this.markerEls[key];
      if (!el) return;
      var on = key === id;
      el.classList.toggle('is-active', on);
      el.setAttribute('aria-pressed', on ? 'true' : 'false');
    }, this);
  };

  WildfireMap.prototype.matchMarkerByText = function (text) {
    if (!text) return null;
    var needle = text.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (!needle || needle.length < 4) return null;

    var best = null;
    var bestLen = 0;
    this.markers.forEach(function (marker) {
      var name = marker.name.toLowerCase().replace(/[^a-z0-9]+/g, '');
      if (name.indexOf(needle) !== -1 || needle.indexOf(name) !== -1) {
        if (name.length > bestLen) {
          bestLen = name.length;
          best = marker;
        }
      }
    });
    return best;
  };

  WildfireMap.prototype.buildSvg = function (mapData) {
    if (!this.svg) return;

    var bounds = mapData.bounds;
    var markers = mapData.markers || [];
    var labels = mapData.labels || [];
    this.bounds = bounds;
    this.markers = markers;
    this.markerEls = {};
    this.scale = 1;
    this.tx = 0;
    this.ty = 0;

    var svgParts = [];
    svgParts.push('<g data-map-world>');

    // Ocean wash.
    svgParts.push(
      '<rect x="0" y="0" width="' + MAP_WIDTH + '" height="' + MAP_HEIGHT + '" fill="#030810"></rect>'
    );

    // Land mass.
    var coastPts = COAST_PATH.map(function (pt) {
      var p = project(pt[0], pt[1], bounds);
      return p.x.toFixed(1) + ',' + p.y.toFixed(1);
    }).join(' ');
    svgParts.push(
      '<polygon points="' + coastPts + '" fill="#0a1418" stroke="#1a3a32" stroke-width="1.2"></polygon>'
    );

    // Grid.
    for (var gx = 0; gx <= MAP_WIDTH; gx += 36) {
      svgParts.push(
        '<line x1="' + gx + '" y1="0" x2="' + gx + '" y2="' + MAP_HEIGHT + '" stroke="rgba(57,255,20,0.06)" stroke-width="1"></line>'
      );
    }
    for (var gy = 0; gy <= MAP_HEIGHT; gy += 40) {
      svgParts.push(
        '<line x1="0" y1="' + gy + '" x2="' + MAP_WIDTH + '" y2="' + gy + '" stroke="rgba(57,255,20,0.06)" stroke-width="1"></line>'
      );
    }

    // Border frame.
    svgParts.push(
      '<rect x="1" y="1" width="' + (MAP_WIDTH - 2) + '" height="' + (MAP_HEIGHT - 2) + '" fill="none" stroke="rgba(87,243,255,0.22)" stroke-width="1"></rect>'
    );

    // City labels.
    labels.forEach(function (label) {
      var pt = project(label.lon, label.lat, bounds);
      var isYvr = label.name === 'YVR';
      svgParts.push(
        '<g class="home-yvr-map__label' + (isYvr ? ' is-yvr' : '') + '" transform="translate(' + pt.x.toFixed(1) + ',' + pt.y.toFixed(1) + ')">' +
          (isYvr ? '<circle r="5" fill="none" stroke="rgba(57,255,20,0.55)" stroke-width="1" class="home-yvr-map__yvr-ring"></circle>' : '') +
          '<text y="-6" text-anchor="middle" class="home-yvr-map__label-text">' + escapeHtml(label.name) + '</text>' +
        '</g>'
      );
    });

    // Fire markers.
    markers.forEach(function (marker) {
      var pt = project(marker.lon, marker.lat, bounds);
      var tier = marker.tier || 'other';
      var color = TIER_COLORS[tier] || TIER_COLORS.other;
      var radius = tier === 'ooc' ? 5.5 : 4.2;
      var id = marker.id || marker.name;
      var classes = 'home-yvr-map__marker is-' + tier;
      if (marker.bulletin) classes += ' is-bulletin';

      svgParts.push(
        '<g class="' + classes + '" data-map-marker="' + escapeHtml(id) + '" transform="translate(' + pt.x.toFixed(1) + ',' + pt.y.toFixed(1) + ')" tabindex="0" role="button" aria-label="' + escapeHtml(marker.name) + '">' +
          '<circle class="home-yvr-map__pulse" r="' + (radius + 6) + '" fill="' + color + '" opacity="0.18"></circle>' +
          '<circle class="home-yvr-map__dot" r="' + radius + '" fill="' + color + '" stroke="#020308" stroke-width="1.2"></circle>' +
        '</g>'
      );
    });

    svgParts.push('</g>');
    this.svg.innerHTML = svgParts.join('');

    var self = this;
    markers.forEach(function (marker) {
      var id = marker.id || marker.name;
      var el = self.svg.querySelector('[data-map-marker="' + CSS.escape(id) + '"]');
      if (!el) return;
      self.markerEls[id] = el;

      el.addEventListener('pointerenter', function (event) {
        self.showTooltip(marker, event.clientX, event.clientY);
        self.highlightMarker(id);
      });
      el.addEventListener('pointermove', function (event) {
        self.showTooltip(marker, event.clientX, event.clientY);
      });
      el.addEventListener('pointerleave', function () {
        self.hideTooltip();
        self.highlightMarker(self.activeId);
      });
      el.addEventListener('click', function () {
        if (marker.url) {
          window.open(marker.url, '_blank', 'noopener,noreferrer');
        }
      });
      el.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          if (marker.url) window.open(marker.url, '_blank', 'noopener,noreferrer');
        }
      });
    });

    this.setLegend(markers);
    this.applyTransform();
  };

  WildfireMap.prototype.render = function (mapData) {
    if (!mapData || !mapData.markers || !mapData.markers.length) {
      this.hide();
      return;
    }
    this.show();
    this.buildSvg(mapData);
  };

  window.HomeYvrWildfireMap = WildfireMap;
}());
