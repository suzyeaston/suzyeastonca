(function () {
  'use strict';

  /**
   * SkyTrain / TransLink scanner — live alert text from TransLink's public API.
   * BCRTC ops voice is encrypted on E-Comm; this dial reads real service alerts.
   * Channel labels mirror documented SkyTrain talkgroups (RadioReference / legacy freqs).
   */
  var TRANSIT_CHANNELS = [
    {
      type: 'translink',
      label: 'ST CH1 EXPO-W',
      freq: '410.287',
      match: function (a) { return /expo/i.test(a.route) || /expo line/i.test(a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'ST CH2 EXPO-E',
      freq: '410.288',
      match: function (a) { return /expo/i.test(a.route) || /expo line/i.test(a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'ST CH3 MIL-W',
      freq: '410.062',
      match: function (a) {
        var hay = a.route + a.header + a.text;
        return /millennium/i.test(hay) || /vcc-clark/i.test(hay) || /lafarge/i.test(hay);
      }
    },
    {
      type: 'translink',
      label: 'ST CH4 MIL-E',
      freq: '410.063',
      match: function (a) {
        var hay = a.route + a.header + a.text;
        return /millennium/i.test(hay) || /vcc-clark/i.test(hay) || /lafarge/i.test(hay);
      }
    },
    {
      type: 'translink',
      label: 'ST CH5 MAINT',
      freq: '410.487',
      match: function (a) { return a.group === 6 || /maintenance|escalator|elevator|out of service/i.test(a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'CANADA LINE',
      freq: '410.350',
      match: function (a) { return /canada line/i.test(a.route + a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'ST SEABUS',
      freq: '156.800',
      match: function (a) { return /seabus/i.test(a.route + a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'ST WCE',
      freq: '161.475',
      match: function (a) { return /west coast express/i.test(a.route + a.header + a.text); }
    }
  ];

  var STANDBY_CAPTION =
    'SkyTrain voice ops are encrypted. This dial reads live TransLink service alerts.';
  var SCAN_CAPTION = 'Tuning SkyTrain data bands…';
  var ATTRIBUTION = ' Alert data: TransLink.';

  var SCAN_MS = 1400;
  var AUTO_SCAN_MS = 42000;

  function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
  }

  function formatFreq(value) {
    var n = parseFloat(value);
    if (Number.isNaN(n)) return String(value);
    return n.toFixed(3);
  }

  function createStaticNoise() {
    var ctx = new (window.AudioContext || window.webkitAudioContext)();
    var bufferSize = 2 * ctx.sampleRate;
    var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
    var data = buffer.getChannelData(0);
    for (var i = 0; i < bufferSize; i++) {
      data[i] = (Math.random() * 2 - 1) * 0.28;
    }
    var source = ctx.createBufferSource();
    source.buffer = buffer;
    source.loop = true;
    var gain = ctx.createGain();
    gain.gain.value = 0;
    source.connect(gain);
    gain.connect(ctx.destination);
    source.start(0);
    return { ctx: ctx, gain: gain, source: source };
  }

  function playSkytrainPass() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var duration = 2.2;
      var now = ctx.currentTime;
      var master = ctx.createGain();
      master.gain.value = 0.22;
      master.connect(ctx.destination);

      function tone(freq, start, len, peak, type, pan) {
        var osc = ctx.createOscillator();
        var g = ctx.createGain();
        var p = ctx.createStereoPanner();
        osc.type = type || 'sawtooth';
        osc.frequency.setValueAtTime(freq, start);
        g.gain.setValueAtTime(0, start);
        g.gain.linearRampToValueAtTime(peak, start + 0.2);
        g.gain.linearRampToValueAtTime(peak * 0.7, start + len * 0.5);
        g.gain.linearRampToValueAtTime(0, start + len);
        p.pan.value = pan || 0;
        osc.connect(g);
        g.connect(p);
        p.connect(master);
        osc.start(start);
        osc.stop(start + len + 0.05);
      }

      tone(58, now, duration, 0.04, 'sawtooth', -0.2);
      tone(140, now + 0.05, duration * 0.9, 0.015, 'triangle', 0.15);
      window.setTimeout(function () { ctx.close(); }, (duration + 1) * 1000);
    } catch (e) { /* optional ambience */ }
  }

  function HomeCityScanner(root) {
    this.root = root;
    this.freqEl = root.querySelector('[data-scanner-freq]');
    this.channelEl = root.querySelector('[data-scanner-channel]');
    this.captionEl = root.querySelector('[data-scanner-caption]');
    this.scanBtn = root.querySelector('[data-scanner-scan]');
    this.muteBtn = root.querySelector('[data-scanner-mute]');
    this.bars = root.querySelectorAll('[data-scanner-bar]');
    this.channels = TRANSIT_CHANNELS.slice();
    this.alerts = [];
    this.skytrainAlerts = [];
    this.alertsLoaded = false;
    this.muted = false;
    this.scanning = false;
    this.static = null;
    this.autoTimer = null;
    this.current = null;
    this.alertsUrl = (window.HomeCityScannerConfig && HomeCityScannerConfig.alertsUrl) || '/wp-json/se/v1/translink-alerts';
  }

  HomeCityScanner.prototype.init = function () {
    var self = this;
    this.loadAlerts();

    if (this.scanBtn) {
      this.scanBtn.addEventListener('click', function () { self.scan(true); });
    }
    if (this.muteBtn) {
      this.muteBtn.addEventListener('click', function () { self.toggleMute(); });
    }

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      this.autoTimer = window.setInterval(function () {
        if (!self.muted && !self.scanning) self.scan(false);
      }, AUTO_SCAN_MS);
    }

    window.setTimeout(function () { self.scan(false); }, 1800);
  };

  HomeCityScanner.prototype.loadAlerts = function () {
    var self = this;
    fetch(this.alertsUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        if (!payload) return;
        self.alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
        self.skytrainAlerts = Array.isArray(payload.skytrain) ? payload.skytrain : [];
        self.alertsLoaded = true;
        if (self.current && self.captionEl) {
          self.captionEl.textContent = self.alertCaption(self.current) + ATTRIBUTION;
        }
      })
      .catch(function () { /* standby copy only */ });
  };

  HomeCityScanner.prototype.alertPool = function (channel) {
    if (!channel || !channel.match) return this.skytrainAlerts.length ? this.skytrainAlerts : this.alerts;
    var matched = this.skytrainAlerts.filter(function (a) { return channel.match(a); });
    if (matched.length) return matched;
    if (this.skytrainAlerts.length) return this.skytrainAlerts;
    return this.alerts;
  };

  HomeCityScanner.prototype.pickAlert = function (channel) {
    var pool = this.alertPool(channel);
    if (!pool.length) {
      return {
        header: 'No active TransLink alerts on this channel.',
        text: 'System clear — or feed still loading.'
      };
    }
    return pick(pool);
  };

  HomeCityScanner.prototype.alertCaption = function (channel) {
    var alert = this.pickAlert(channel);
    var line = alert.header || alert.text || 'TransLink alert';
    if (alert.header && alert.text && alert.text !== alert.header) {
      line = alert.header + ' — ' + alert.text;
    }
    if (line.length > 220) line = line.slice(0, 217) + '…';
    return line;
  };

  HomeCityScanner.prototype.ensureStatic = function () {
    if (this.static) return this.static;
    try {
      this.static = createStaticNoise();
    } catch (e) {
      this.static = null;
    }
    return this.static;
  };

  HomeCityScanner.prototype.setStatic = function (level) {
    var s = this.ensureStatic();
    if (!s) return;
    if (s.ctx.state === 'suspended') s.ctx.resume();
    s.gain.gain.setTargetAtTime(level, s.ctx.currentTime, 0.04);
  };

  HomeCityScanner.prototype.setBars = function (active) {
    this.bars.forEach(function (bar, i) {
      bar.classList.toggle('is-live', active);
      bar.style.animationDelay = (i * 0.12) + 's';
    });
  };

  HomeCityScanner.prototype.updateDisplay = function (channel, scanning, caption) {
    if (this.freqEl) this.freqEl.textContent = formatFreq(channel.freq || '0');
    if (this.channelEl) {
      this.channelEl.textContent = scanning ? 'SCANNING…' : channel.label || 'STANDBY';
      this.channelEl.classList.toggle('is-lock', !scanning);
    }
    if (this.captionEl) {
      this.captionEl.textContent = caption || channel.caption || STANDBY_CAPTION;
    }
    this.root.classList.toggle('is-scanning', scanning);
    this.root.classList.toggle('is-locked', !scanning);
    this.root.classList.toggle('is-translink', channel.type === 'translink');
  };

  HomeCityScanner.prototype.scan = function (userInitiated) {
    var self = this;
    if (this.scanning) return;
    this.scanning = true;
    this.updateDisplay(
      { label: 'SCANNING…', freq: this.freqEl ? this.freqEl.textContent : '000.000', type: 'translink' },
      true,
      SCAN_CAPTION
    );
    this.setBars(true);

    if (!this.muted) this.setStatic(0.22);

    window.setTimeout(function () {
      var channel = pick(self.channels);
      self.current = channel;
      self.scanning = false;
      self.setStatic(0);
      var caption = self.alertCaption(channel) + ATTRIBUTION;
      self.updateDisplay(channel, false, caption);
      self.setBars(!self.muted);

      if (!self.muted) playSkytrainPass();

      if (userInitiated && self.scanBtn) {
        self.scanBtn.textContent = 'LOCKED';
        window.setTimeout(function () { self.scanBtn.textContent = 'SCAN'; }, 900);
      }
    }, SCAN_MS);
  };

  HomeCityScanner.prototype.toggleMute = function () {
    this.muted = !this.muted;
    if (this.muteBtn) {
      this.muteBtn.setAttribute('aria-pressed', this.muted ? 'true' : 'false');
      this.muteBtn.textContent = this.muted ? 'UNMUTE' : 'MUTE';
    }
    if (this.muted) {
      this.setStatic(0);
      this.setBars(false);
      if (this.captionEl) {
        this.captionEl.textContent = 'Muted. Hit SCAN to read live SkyTrain alerts.';
      }
    } else if (this.current) {
      this.setBars(true);
    }
  };

  function init() {
    var root = document.querySelector('[data-city-scanner]');
    if (!root || root.dataset.scannerReady === '1') return;
    root.dataset.scannerReady = '1';
    var scanner = new HomeCityScanner(root);
    scanner.init();
    window.HomeCityScanner = scanner;
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
