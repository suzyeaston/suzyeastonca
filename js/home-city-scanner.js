(function () {
  'use strict';

  /**
   * YVR scanner — one live audio stream (CKNW 980 Vancouver) + TransLink alert text on SCAN.
   * Broadcastify embeds are deprecated; leanstream MP3/AAC is browser-safe.
   */
  var LIVE_STREAM = 'https://live.leanstream.co/CKNWAM';
  var LIVE_CHANNEL = {
    label: 'CKNW 980',
    freq: '980.000',
    caption: 'Live CKNW 980 — Vancouver AM news and traffic.'
  };

  var TRANSIT_CHANNELS = [
    {
      type: 'translink',
      label: 'ST EXPO LINE',
      freq: '410.287',
      match: function (a) { return /expo/i.test(a.route + a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'CANADA LINE',
      freq: '410.350',
      match: function (a) { return /canada line/i.test(a.route + a.header + a.text); }
    },
    {
      type: 'translink',
      label: 'ST MILLENNIUM',
      freq: '410.062',
      match: function (a) { return /millennium|vcc-clark|lafarge/i.test(a.route + a.header + a.text); }
    }
  ];

  var STANDBY_CAPTION = LIVE_CHANNEL.caption;
  var SCAN_CAPTION = 'Sweeping live bands…';
  var ATTRIBUTION_TRANSIT = ' Alert data: TransLink.';

  var SCAN_MS = 1400;
  var AUTO_SCAN_MS = 55000;

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

  function HomeCityScanner(root) {
    this.root = root;
    this.audio = root.querySelector('[data-scanner-audio]');
    this.freqEl = root.querySelector('[data-scanner-freq]');
    this.channelEl = root.querySelector('[data-scanner-channel]');
    this.captionEl = root.querySelector('[data-scanner-caption]');
    this.scanBtn = root.querySelector('[data-scanner-scan]');
    this.muteBtn = root.querySelector('[data-scanner-mute]');
    this.bars = root.querySelectorAll('[data-scanner-bar]');
    this.alerts = [];
    this.skytrainAlerts = [];
    this.muted = false;
    this.scanning = false;
    this.static = null;
    this.autoTimer = null;
    this.showingTransit = false;
    this.currentTransit = null;
    this.alertsUrl = (window.HomeCityScannerConfig && HomeCityScannerConfig.alertsUrl) || '/wp-json/se/v1/translink-alerts';
  }

  HomeCityScanner.prototype.init = function () {
    var self = this;
    this.loadAlerts();
    this.showLiveChannel(false);

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

    window.setTimeout(function () {
      if (!self.muted) self.playLiveStream();
    }, 1200);
  };

  HomeCityScanner.prototype.loadAlerts = function () {
    var self = this;
    fetch(this.alertsUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        if (!payload) return;
        self.alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
        self.skytrainAlerts = Array.isArray(payload.skytrain) ? payload.skytrain : [];
        if (self.showingTransit && self.captionEl) {
          self.captionEl.textContent = self.transitCaption(self.currentTransit) + ATTRIBUTION_TRANSIT;
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

  HomeCityScanner.prototype.transitCaption = function (channel) {
    var pool = this.alertPool(channel);
    if (!pool.length) {
      return 'No active TransLink alerts — audio stays on CKNW 980.';
    }
    var alert = pick(pool);
    var line = alert.header || alert.text || 'TransLink alert';
    if (alert.header && alert.text && alert.text !== alert.header) {
      line = alert.header + ' — ' + alert.text;
    }
    if (line.length > 200) line = line.slice(0, 197) + '…';
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

  HomeCityScanner.prototype.showLiveChannel = function (scanning) {
    this.showingTransit = false;
    this.currentTransit = null;
    this.updateDisplay(LIVE_CHANNEL, scanning, scanning ? SCAN_CAPTION : LIVE_CHANNEL.caption);
    this.root.classList.toggle('is-live-audio', !scanning && !this.muted);
  };

  HomeCityScanner.prototype.updateDisplay = function (channel, scanning, caption) {
    if (this.freqEl) this.freqEl.textContent = formatFreq(channel.freq || '0');
    if (this.channelEl) {
      this.channelEl.textContent = scanning ? 'SCANNING…' : channel.label || 'STANDBY';
      this.channelEl.classList.toggle('is-lock', !scanning);
    }
    if (this.captionEl && caption) {
      this.captionEl.textContent = caption;
    }
    this.root.classList.toggle('is-scanning', scanning);
    this.root.classList.toggle('is-locked', !scanning);
    this.root.classList.toggle('is-translink', channel.type === 'translink');
  };

  HomeCityScanner.prototype.playLiveStream = function () {
    var self = this;
    if (!this.audio || this.muted) return;

    if (!this.audio.src) {
      this.audio.src = LIVE_STREAM;
      this.audio.volume = 0.55;
    }

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise
        .then(function () {
          self.setBars(true);
          self.root.classList.add('is-live-audio');
        })
        .catch(function () {
          if (self.captionEl && !self.showingTransit) {
            self.captionEl.textContent = LIVE_CHANNEL.caption + ' (tap SCAN or unmute to retry playback)';
          }
          self.setBars(false);
          self.root.classList.remove('is-live-audio');
        });
    } else {
      this.setBars(true);
      this.root.classList.add('is-live-audio');
    }
  };

  HomeCityScanner.prototype.stopLiveStream = function () {
    if (!this.audio) return;
    this.audio.pause();
    this.root.classList.remove('is-live-audio');
  };

  HomeCityScanner.prototype.scan = function (userInitiated) {
    var self = this;
    if (this.scanning) return;
    this.scanning = true;
    this.showLiveChannel(true);
    this.setBars(true);

    if (!this.muted) this.setStatic(0.22);

    window.setTimeout(function () {
      var transit = pick(TRANSIT_CHANNELS);
      self.scanning = false;
      self.setStatic(0);
      self.showingTransit = true;
      self.currentTransit = transit;
      self.updateDisplay(transit, false, self.transitCaption(transit) + ATTRIBUTION_TRANSIT);
      self.root.classList.toggle('is-translink', true);
      self.setBars(!self.muted);
      self.playLiveStream();

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
      this.stopLiveStream();
      this.setBars(false);
      if (this.captionEl) {
        this.captionEl.textContent = 'Muted. CKNW 980 stays off until you unmute.';
      }
    } else {
      this.setBars(true);
      this.playLiveStream();
      if (this.captionEl) {
        if (this.showingTransit && this.currentTransit) {
          this.captionEl.textContent = this.transitCaption(this.currentTransit) + ATTRIBUTION_TRANSIT;
        } else {
          this.captionEl.textContent = LIVE_CHANNEL.caption;
        }
      }
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
