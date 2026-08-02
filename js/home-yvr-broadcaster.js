(function () {
  'use strict';

  var CKNW_STREAM = 'https://live.leanstream.co/CKNWAM';

  var CHANNELS = {
    translink: { label: 'TRANSLINK', freq: '410.287', key: 'translink' },
    cknw: { label: 'CKNW 980', freq: '980.000', key: 'cknw', stream: true },
    drivers: { label: 'DRIVE BC', freq: '154.100', key: 'drivers' },
    ferries: { label: 'BC FERRIES', freq: '156.800', key: 'ferries' }
  };

  function pickComputerVoice() {
    if (!('speechSynthesis' in window)) return null;
    var voices = window.speechSynthesis.getVoices();
    return voices.find(function (v) {
      return v.name.indexOf('Google UK English Male') !== -1 ||
        v.name.indexOf('Microsoft David') !== -1 ||
        /en/i.test(v.lang);
    }) || voices[0] || null;
  }

  function HomeYvrBroadcaster(root) {
    this.root = root;
    this.audio = root.querySelector('[data-broadcaster-audio]');
    this.freqEl = root.querySelector('[data-broadcaster-freq]');
    this.channelEl = root.querySelector('[data-broadcaster-channel]');
    this.captionEl = root.querySelector('[data-broadcaster-caption]');
    this.stopBtn = root.querySelector('[data-broadcaster-stop]');
    this.bars = root.querySelectorAll('[data-broadcaster-bar]');
    this.channelBtns = root.querySelectorAll('[data-broadcaster-channel-btn]');
    this.feedsUrl = (window.HomeYvrBroadcasterConfig && HomeYvrBroadcasterConfig.feedsUrl) ||
      '/wp-json/se/v1/broadcaster/feeds';
    this.feeds = null;
    this.activeKey = null;
    this.speechUtterance = null;
  }

  HomeYvrBroadcaster.prototype.init = function () {
    var self = this;

    this.channelBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-broadcaster-channel-btn');
        if (!key || !CHANNELS[key]) return;
        self.activate(key);
      });
    });

    if (this.stopBtn) {
      this.stopBtn.addEventListener('click', function () {
        self.stopAll(true);
      });
    }

    if ('speechSynthesis' in window) {
      window.speechSynthesis.addEventListener('voiceschanged', function () { pickComputerVoice(); });
    }

    this.loadFeeds();
    this.setStandby();
  };

  HomeYvrBroadcaster.prototype.setStandby = function () {
    if (this.freqEl) this.freqEl.textContent = '000.000';
    if (this.channelEl) this.channelEl.textContent = 'STANDBY';
    if (this.captionEl) {
      this.captionEl.textContent = 'Pick a channel. Computer voice reads live feeds — CKNW is live radio.';
    }
    this.root.classList.remove('is-live', 'is-speaking', 'is-radio');
    this.setBars(false);
    this.highlightChannel(null);
  };

  HomeYvrBroadcaster.prototype.highlightChannel = function (key) {
    this.channelBtns.forEach(function (btn) {
      var match = btn.getAttribute('data-broadcaster-channel-btn') === key;
      btn.classList.toggle('is-active', match);
      btn.setAttribute('aria-pressed', match ? 'true' : 'false');
    });
  };

  HomeYvrBroadcaster.prototype.loadFeeds = function () {
    var self = this;
    fetch(this.feedsUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data) self.feeds = data;
      })
      .catch(function () { /* standby */ });
  };

  HomeYvrBroadcaster.prototype.ensureFeeds = function () {
    var self = this;
    if (this.feeds) return Promise.resolve(this.feeds);
    return fetch(this.feedsUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data) self.feeds = data;
        return self.feeds;
      });
  };

  HomeYvrBroadcaster.prototype.stopSpeech = function () {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    this.speechUtterance = null;
    this.root.classList.remove('is-speaking');
  };

  HomeYvrBroadcaster.prototype.stopRadio = function () {
    if (this.audio) {
      this.audio.pause();
      this.audio.removeAttribute('src');
    }
    this.root.classList.remove('is-radio');
  };

  HomeYvrBroadcaster.prototype.stopAll = function (toStandby) {
    this.stopSpeech();
    this.stopRadio();
    this.activeKey = null;
    this.setBars(false);
    this.root.classList.remove('is-live');
    if (toStandby) this.setStandby();
  };

  HomeYvrBroadcaster.prototype.setBars = function (on) {
    this.bars.forEach(function (bar, i) {
      bar.classList.toggle('is-live', on);
      bar.style.animationDelay = (i * 0.1) + 's';
    });
  };

  HomeYvrBroadcaster.prototype.updateDisplay = function (channel, caption) {
    if (this.freqEl) this.freqEl.textContent = channel.freq;
    if (this.channelEl) this.channelEl.textContent = channel.label;
    if (this.captionEl && caption) this.captionEl.textContent = caption;
    this.highlightChannel(channel.key || null);
  };

  HomeYvrBroadcaster.prototype.speak = function (text, channel, caption) {
    var self = this;
    if (!text || !('speechSynthesis' in window)) {
      if (this.captionEl) this.captionEl.textContent = caption || text || 'Voice not available in this browser.';
      return;
    }

    this.stopSpeech();
    this.updateDisplay(channel, caption || text);
    this.root.classList.add('is-live', 'is-speaking');
    this.setBars(true);

    var utterance = new SpeechSynthesisUtterance(text);
    var voice = pickComputerVoice();
    if (voice) utterance.voice = voice;
    utterance.rate = 0.92;
    utterance.pitch = 0.88;

    utterance.onend = function () {
      self.root.classList.remove('is-speaking');
      if (self.activeKey === channel.key) self.setBars(false);
    };
    utterance.onerror = function () {
      self.root.classList.remove('is-speaking');
      self.setBars(false);
    };

    this.speechUtterance = utterance;
    window.speechSynthesis.speak(utterance);
  };

  HomeYvrBroadcaster.prototype.playRadio = function (channel) {
    var self = this;
    if (!this.audio) return;

    this.stopRadio();
    this.updateDisplay(channel, 'Live CKNW 980 — Vancouver AM. Hit STOP to silence.');
    this.root.classList.add('is-live', 'is-radio');
    this.audio.src = CKNW_STREAM;
    this.audio.volume = 0.55;

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise
        .then(function () {
          self.setBars(true);
        })
        .catch(function () {
          if (self.captionEl) {
            self.captionEl.textContent = 'CKNW blocked autoplay — tap CKNW 980 again or STOP then retry.';
          }
          self.setBars(false);
        });
    } else {
      this.setBars(true);
    }
  };

  HomeYvrBroadcaster.prototype.activate = function (key) {
    var self = this;
    var channel = CHANNELS[key];
    if (!channel) return;

    if (this.activeKey === key) {
      this.stopAll(true);
      return;
    }

    this.stopAll(false);
    this.activeKey = key;
    this.updateDisplay(channel, 'Tuning ' + channel.label + '…');
    this.root.classList.add('is-live');
    this.setBars(true);

    if (channel.stream) {
      this.playRadio(channel);
      return;
    }

    this.ensureFeeds().then(function (feeds) {
      var pack = feeds && feeds[channel.key] ? feeds[channel.key] : null;
      if (!pack) {
        self.updateDisplay(channel, 'Feed still loading — try again in a moment.');
        return;
      }
      var script = pack.script || pack.caption || '';
      var caption = pack.caption || script;
      if (pack.source) caption += ' Source: ' + pack.source + '.';
      self.speak(script, channel, caption);
    });
  };

  function init() {
    var root = document.querySelector('[data-yvr-broadcaster]');
    if (!root || root.dataset.broadcasterReady === '1') return;
    root.dataset.broadcasterReady = '1';
    var broadcaster = new HomeYvrBroadcaster(root);
    broadcaster.init();
    window.HomeYvrBroadcaster = broadcaster;
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
