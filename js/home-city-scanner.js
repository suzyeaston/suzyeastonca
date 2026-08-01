(function () {
  'use strict';

  /**
   * YVR band scanner — live voice via Broadcastify + live TransLink alert text.
   * BC haulers use LADD VHF (154.100), not CB — no public LADD internet stream exists.
   * These are legit repeater / dispatch feeds with highway-adjacent chatter.
   */
  var AUDIO_CHANNELS = [
    {
      type: 'broadcastify',
      label: 'LADD 1 HWY',
      freq: '154.100',
      feedId: 33907,
      caption: 'Live VE7RPT ham repeater. BC truckers run LADD VHF — not CB.'
    },
    {
      type: 'broadcastify',
      label: 'HWY CH19',
      freq: '27.185',
      feedId: 43789,
      caption: 'Live Fraser Valley ham repeater — hauler corridor east of Vancouver.'
    },
    {
      type: 'broadcastify',
      label: 'FIRE DISP',
      freq: '154.400',
      feedId: 38213,
      caption: 'Live Burnaby Fire dispatch on 154.4 MHz.'
    },
    {
      type: 'broadcastify',
      label: 'VE7RPT HAM',
      freq: '146.940',
      feedId: 33907,
      caption: 'Live Lower Mainland amateur repeater hub.'
    },
    {
      type: 'broadcastify',
      label: 'FVARESS',
      freq: '147.120',
      feedId: 43789,
      caption: 'Live VE7RVA Fraser Valley repeater network.'
    }
  ];

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

  var STANDBY_CAPTION =
    'Live bands only. BC haulers use LADD VHF — scan locks to ham repeaters and fire dispatch.';
  var SCAN_CAPTION = 'Sweeping live VHF bands…';
  var ATTRIBUTION_TRANSIT = ' Alert data: TransLink.';

  var SCAN_MS = 1400;
  var AUTO_SCAN_MS = 50000;
  var AUDIO_BIAS = 0.88;

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
    this.embedEl = root.querySelector('[data-scanner-embed]');
    this.freqEl = root.querySelector('[data-scanner-freq]');
    this.channelEl = root.querySelector('[data-scanner-channel]');
    this.captionEl = root.querySelector('[data-scanner-caption]');
    this.scanBtn = root.querySelector('[data-scanner-scan]');
    this.muteBtn = root.querySelector('[data-scanner-mute]');
    this.bars = root.querySelectorAll('[data-scanner-bar]');
    this.audioChannels = AUDIO_CHANNELS.slice();
    this.transitChannels = TRANSIT_CHANNELS.slice();
    this.alerts = [];
    this.skytrainAlerts = [];
    this.muted = false;
    this.scanning = false;
    this.static = null;
    this.autoTimer = null;
    this.current = null;
    this.embedIframe = null;
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

  HomeCityScanner.prototype.pickChannel = function () {
    var audio = this.audioChannels;
    var transit = this.transitChannels;
    if (audio.length && Math.random() < AUDIO_BIAS) {
      return pick(audio);
    }
    if (transit.length) {
      return pick(transit);
    }
    return pick(audio);
  };

  HomeCityScanner.prototype.loadAlerts = function () {
    var self = this;
    fetch(this.alertsUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        if (!payload) return;
        self.alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
        self.skytrainAlerts = Array.isArray(payload.skytrain) ? payload.skytrain : [];
        if (self.current && self.current.type === 'translink' && self.captionEl) {
          self.captionEl.textContent = self.alertCaption(self.current) + ATTRIBUTION_TRANSIT;
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
    if (line.length > 200) line = line.slice(0, 197) + '…';
    return line;
  };

  HomeCityScanner.prototype.channelCaption = function (channel) {
    if (channel.type === 'translink') {
      return this.alertCaption(channel) + ATTRIBUTION_TRANSIT;
    }
    return channel.caption || STANDBY_CAPTION;
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

  HomeCityScanner.prototype.clearEmbed = function () {
    if (this.embedIframe) {
      this.embedIframe.remove();
      this.embedIframe = null;
    }
    if (this.embedEl) {
      this.embedEl.hidden = true;
      this.embedEl.innerHTML = '';
    }
    this.root.classList.remove('is-audio-live');
  };

  HomeCityScanner.prototype.playBroadcastify = function (channel) {
    if (!this.embedEl || !channel.feedId || this.muted) return;

    this.clearEmbed();
    this.embedEl.hidden = false;
    this.root.classList.add('is-audio-live');

    var iframe = document.createElement('iframe');
    iframe.className = 'home-city-scanner__iframe';
    iframe.title = channel.label + ' live feed';
    iframe.setAttribute(
      'src',
      'https://api.broadcastify.com/embed/player/?feedId=' + encodeURIComponent(String(channel.feedId))
    );
    iframe.setAttribute('allow', 'autoplay');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');

    this.embedEl.appendChild(iframe);
    this.embedIframe = iframe;
  };

  HomeCityScanner.prototype.stopAudio = function () {
    this.clearEmbed();
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
    this.root.classList.toggle('is-broadcastify', channel.type === 'broadcastify');
  };

  HomeCityScanner.prototype.scan = function (userInitiated) {
    var self = this;
    if (this.scanning) return;
    this.scanning = true;
    this.stopAudio();
    this.updateDisplay(
      { label: 'SCANNING…', freq: this.freqEl ? this.freqEl.textContent : '000.000', type: 'broadcastify' },
      true,
      SCAN_CAPTION
    );
    this.setBars(true);

    if (!this.muted) this.setStatic(0.22);

    window.setTimeout(function () {
      var channel = self.pickChannel();
      self.current = channel;
      self.scanning = false;
      self.setStatic(0);
      var caption = self.channelCaption(channel);
      self.updateDisplay(channel, false, caption);
      self.setBars(!self.muted);

      if (!self.muted && channel.type === 'broadcastify') {
        self.playBroadcastify(channel);
      }

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
      this.stopAudio();
      this.setBars(false);
      if (this.captionEl) {
        this.captionEl.textContent = 'Muted. Hit SCAN to tune a live channel.';
      }
    } else if (this.current) {
      this.setBars(true);
      if (this.current.type === 'broadcastify') {
        this.playBroadcastify(this.current);
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
