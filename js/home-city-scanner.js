(function () {
  'use strict';

  /**
   * Curated live channels only — no simulated dispatch text.
   * Metro Vancouver police / SkyTrain ops use encrypted P25 and are not public.
   */
  var LIVE_CHANNELS = [
    {
      type: 'broadcastify',
      label: 'BURNABY FIRE',
      freq: '154.400',
      caption: 'Live fire dispatch — Burnaby Fire (Broadcastify public feed).',
      feedId: 38213
    },
    {
      type: 'broadcastify',
      label: 'VE7RPT HAM',
      freq: '146.940',
      caption: 'Live amateur repeater hub — VE7RPT AllStar, Lower Mainland.',
      feedId: 33907
    },
    {
      type: 'broadcast',
      label: 'CKNW 980',
      freq: '980.000',
      caption: 'Live AM — CKNW news talk, New Westminster.',
      stream: 'http://live.leanstream.co/CKNWAM'
    },
    {
      type: 'broadcast',
      label: 'NEWS 1130',
      freq: '1130.000',
      caption: 'Live AM news — CKWX Vancouver.',
      stream: 'https://rogers-hls.leanstream.co/rogers/van1130.stream/playlist.m3u8'
    },
    {
      type: 'broadcast',
      label: 'CFOX 99.3',
      freq: '99.300',
      caption: 'Live FM — CFOX Vancouver.',
      stream: 'http://live.leanstream.co/CFOXFM-MP3'
    },
    {
      type: 'broadcast',
      label: 'ROCK 101',
      freq: '101.100',
      caption: 'Live FM — CFMI Rock 101, New Westminster.',
      stream: 'http://live.leanstream.co/CFMIFM-MP3'
    },
    {
      type: 'broadcast',
      label: 'THE BREEZE',
      freq: '104.300',
      caption: 'Live FM — CHLG 104.3 The Breeze, Vancouver.',
      stream: 'http://newcap.leanstream.co/CHLGFM'
    },
    {
      type: 'broadcast',
      label: 'CBC R1 YVR',
      freq: '88.100',
      caption: 'Live FM — CBC Radio One Vancouver.',
      stream: 'https://cbcradiolive.akamaized.net/hls/live/2041050/ES_R1PVC/master.m3u8'
    }
  ];

  var RADIO_BROWSER_URL =
    'https://de1.api.radio-browser.info/json/stations/search?state=British%20Columbia&countrycode=CA&limit=12&order=clickcount&reverse=true&lastcheckok=true';

  var STANDBY_CAPTION =
    'Live public bands only. Metro police and SkyTrain ops are encrypted — not on this dial.';
  var SCAN_CAPTION = 'Sweeping authorized public streams…';

  var SCAN_MS = 1400;
  var AUTO_SCAN_MS = 45000;

  function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
  }

  function formatFreq(value) {
    var n = parseFloat(value);
    if (Number.isNaN(n)) return String(value);
    return n.toFixed(3);
  }

  function normalizeLabel(name) {
    return String(name || 'BC RADIO')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 20)
      .toUpperCase();
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
    this.embedEl = root.querySelector('[data-scanner-embed]');
    this.freqEl = root.querySelector('[data-scanner-freq]');
    this.channelEl = root.querySelector('[data-scanner-channel]');
    this.captionEl = root.querySelector('[data-scanner-caption]');
    this.scanBtn = root.querySelector('[data-scanner-scan]');
    this.muteBtn = root.querySelector('[data-scanner-mute]');
    this.bars = root.querySelectorAll('[data-scanner-bar]');
    this.channels = LIVE_CHANNELS.slice();
    this.streamUrls = new Set(
      LIVE_CHANNELS.filter(function (c) { return c.stream; }).map(function (c) { return c.stream; })
    );
    this.muted = false;
    this.scanning = false;
    this.static = null;
    this.autoTimer = null;
    this.current = null;
    this.embedIframe = null;
  }

  HomeCityScanner.prototype.init = function () {
    var self = this;
    this.loadStations();

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

  HomeCityScanner.prototype.mergeStation = function (row) {
    if (!row || !row.url_resolved || row.lastcheckok !== 1) return;
    var stream = String(row.url_resolved);
    if (this.streamUrls.has(stream)) return;

    var codec = String(row.codec || '').toLowerCase();
    if (
      codec &&
      codec.indexOf('mp3') === -1 &&
      codec.indexOf('aac') === -1 &&
      codec.indexOf('ogg') === -1 &&
      codec.indexOf('m3u') === -1
    ) {
      return;
    }

    this.streamUrls.add(stream);
    this.channels.push({
      type: 'broadcast',
      label: normalizeLabel(row.name),
      freq: formatFreq(row.frequency || 88 + Math.random() * 20),
      caption: 'Live public broadcast — ' + String(row.name || 'BC station').trim() + '.',
      stream: stream
    });
  };

  HomeCityScanner.prototype.loadStations = function () {
    var self = this;
    fetch(RADIO_BROWSER_URL, { cache: 'force-cache' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (rows) {
        if (!Array.isArray(rows)) return;
        rows.forEach(function (row) { self.mergeStation(row); });
      })
      .catch(function () { /* curated list is enough */ });
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

  HomeCityScanner.prototype.updateDisplay = function (channel, scanning) {
    if (this.freqEl) this.freqEl.textContent = formatFreq(channel.freq || '0');
    if (this.channelEl) {
      this.channelEl.textContent = scanning ? 'SCANNING…' : channel.label || 'STANDBY';
      this.channelEl.classList.toggle('is-lock', !scanning);
    }
    if (this.captionEl) {
      this.captionEl.textContent = channel.caption || STANDBY_CAPTION;
    }
    this.root.classList.toggle('is-scanning', scanning);
    this.root.classList.toggle('is-locked', !scanning);
    this.root.classList.toggle('is-broadcastify', channel.type === 'broadcastify');
    this.root.classList.toggle('is-broadcast', channel.type === 'broadcast');
  };

  HomeCityScanner.prototype.stopStream = function () {
    if (this.audio) {
      this.audio.pause();
      this.audio.removeAttribute('src');
    }
    this.clearEmbed();
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
  };

  HomeCityScanner.prototype.playBroadcastify = function (channel) {
    if (!this.embedEl || !channel.feedId || this.muted) return;

    this.clearEmbed();
    this.embedEl.hidden = false;

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

  HomeCityScanner.prototype.playStream = function (channel) {
    var self = this;
    if (!this.audio || !channel.stream || this.muted) return;

    this.clearEmbed();
    this.audio.pause();
    this.audio.src = channel.stream;
    this.audio.volume = 0.55;

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.catch === 'function') {
      playPromise.catch(function () {
        if (self.captionEl) {
          self.captionEl.textContent =
            channel.caption + ' (browser blocked playback — try SCAN for another feed)';
        }
        self.setBars(false);
      });
    }
  };

  HomeCityScanner.prototype.scan = function (userInitiated) {
    var self = this;
    if (this.scanning) return;
    this.scanning = true;
    this.stopStream();
    this.updateDisplay(
      {
        label: 'SCANNING…',
        freq: this.freqEl ? this.freqEl.textContent : '000.000',
        caption: SCAN_CAPTION,
        type: 'broadcast'
      },
      true
    );
    this.setBars(true);

    if (!this.muted) this.setStatic(0.22);

    window.setTimeout(function () {
      var channel = pick(self.channels);
      self.current = channel;
      self.scanning = false;
      self.setStatic(0);
      self.updateDisplay(channel, false);

      if (!self.muted) {
        if (channel.type === 'broadcastify') {
          self.playBroadcastify(channel);
          self.setBars(true);
        } else if (channel.type === 'broadcast' && channel.stream) {
          self.playStream(channel);
          self.setBars(true);
        } else {
          self.setBars(false);
        }
      } else {
        self.setBars(false);
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
      this.stopStream();
      this.setBars(false);
      if (this.captionEl) {
        this.captionEl.textContent = 'Muted. Hit SCAN to tune a live channel.';
      }
    } else if (this.current) {
      if (this.current.type === 'broadcastify') {
        this.playBroadcastify(this.current);
        this.setBars(true);
      } else if (this.current.type === 'broadcast' && this.current.stream) {
        this.playStream(this.current);
        this.setBars(true);
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
