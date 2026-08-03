(function () {
  'use strict';

  /** Map pin keys — label/freq for CRT; audio resolves via dataChannelLive. */
  var PIN_CHANNELS = {
    translink: { label: 'SKYTRAIN', freq: '410.287', key: 'translink' },
    drivers: { label: 'DRIVE BC', freq: '154.100', key: 'drivers' },
    ferries: { label: 'BC FERRIES', freq: '156.800', key: 'ferries' },
    weather: { label: 'WEATHER', freq: '162.550', key: 'weather' },
    wildfire: { label: 'WILDFIRE', freq: '168.050', key: 'wildfire' },
    air: { label: 'AIR QUALITY', freq: '153.785', key: 'air' }
  };

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function attachHls(audio, url) {
    if (window.Hls && window.Hls.isSupported()) {
      var hls = new window.Hls({ enableWorker: true, lowLatencyMode: true });
      hls.loadSource(url);
      hls.attachMedia(audio);
      return hls;
    }
    if (audio.canPlayType('application/vnd.apple.mpegurl')) {
      audio.src = url;
    }
    return null;
  }

  function HomeYvrBroadcaster(root) {
    this.root = root;
    this.audio = root.querySelector('[data-broadcaster-audio]');
    this.attributionEl = root.querySelector('[data-broadcaster-attribution]');
    this.freqEl = root.querySelector('[data-broadcaster-freq]');
    this.channelEl = root.querySelector('[data-broadcaster-channel]');
    this.stopBtn = root.querySelector('[data-broadcaster-stop]');
    this.playBtn = root.querySelector('[data-broadcaster-play]');
    this.speedLabelEl = root.querySelector('[data-broadcaster-speed-label]');
    this.speedBtns = root.querySelectorAll('[data-broadcaster-speed]');
    this.bars = root.querySelectorAll('[data-broadcaster-bar]');
    this.channelBtns = root.querySelectorAll('[data-broadcaster-channel-btn]');
    this.telepromptRoot = root.querySelector('[data-broadcaster-teleprompt]');
    this.scriptEl = root.querySelector('[data-broadcaster-script]');
    this.metaEl = root.querySelector('[data-broadcaster-meta]');
    this.mouthEl = root.querySelector('[data-broadcaster-mouth]');
    this.faceEl = root.querySelector('[data-broadcaster-face]');
    this.heroMap = window.HomeHeroMap || null;
    this.feedsUrl = (window.HomeYvrBroadcasterConfig && HomeYvrBroadcasterConfig.feedsUrl) ||
      '/wp-json/se/v1/broadcaster/feeds';
    var bootCfg = window.HomeYvrBroadcasterConfig || {};
    this.feeds = null;
    this.audioChannels = bootCfg.channels || {};
    this.ambientCycleKeys = bootCfg.daveAmbientCycle || [];
    this.dataChannelPins = bootCfg.dataChannelPins || {};
    this.ringLabelEl = document.querySelector('[data-yvr-ring-label]');
    this.mapWrapEl = document.querySelector('.home-yvr-radar-deck__map-wrap');
    this.audioUnlocked = false;
    this.activeKey = null;
    this.activePinKey = null;
    this.activeAudioKey = null;
    this.mouthTimer = null;
    this.autoMuteTimer = null;
    this.hls = null;
    this.audioCtx = null;
    this.analyser = null;
    this.meterActive = false;
    this.meterRaf = null;
    this.audioSourceNode = null;
    this.meterWired = false;
    this.playbackRate = 1;
    this.focusTimer = null;
    this.pendingMapSelectKey = null;
  }

  HomeYvrBroadcaster.prototype.isKnownChannel = function (key) {
    return this.audioChannels[key] || PIN_CHANNELS[key];
  };

  HomeYvrBroadcaster.prototype.getPinConfig = function (key) {
    return this.dataChannelPins[key] || null;
  };

  HomeYvrBroadcaster.prototype.getDisplayChannel = function (key) {
    if (PIN_CHANNELS[key]) return PIN_CHANNELS[key];
    var ac = this.audioChannels[key];
    if (!ac) return null;
    return { label: ac.label, freq: ac.freq, key: key };
  };

  HomeYvrBroadcaster.prototype.getAudioChannel = function (key) {
    return this.audioChannels[key] || null;
  };

  HomeYvrBroadcaster.prototype.wireHeroMap = function () {
    var self = this;
    this.heroMap = window.HomeHeroMap || null;
    if (!this.heroMap) return;

    this.heroMap.onChannelSelect = function (key) {
      self.handleMapSelect(key);
    };

    if (this.pendingMapSelectKey) {
      var pending = this.pendingMapSelectKey;
      this.pendingMapSelectKey = null;
      self.handleMapSelect(pending);
    }
  };

  HomeYvrBroadcaster.prototype.handleMapSelect = function (key) {
    if (!this.isKnownChannel(key)) return;
    this.unlockAudio();
    this.focusDavePlayer();
    this.activate(key);
    this.updatePlayButton();
  };

  HomeYvrBroadcaster.prototype.focusDavePlayer = function () {
    var self = this;
    this.root.classList.add('is-focused');
    if (this.focusTimer) clearTimeout(this.focusTimer);
    this.focusTimer = setTimeout(function () {
      self.root.classList.remove('is-focused');
    }, 2600);

    try {
      this.root.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
      this.root.scrollIntoView(true);
    }

    if (this.faceEl) {
      try {
        this.faceEl.focus({ preventScroll: true });
      } catch (err) {
        this.faceEl.focus();
      }
    }
  };

  HomeYvrBroadcaster.prototype.resumeAudioContext = function () {
    if (this.audioCtx && this.audioCtx.state === 'suspended') {
      return this.audioCtx.resume();
    }
    return Promise.resolve();
  };

  HomeYvrBroadcaster.prototype.updatePlayButton = function () {
    if (!this.playBtn) return;
    var playing = this.isAudioPlaying();
    this.playBtn.classList.toggle('is-playing', playing);
    this.playBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
    var label = this.playBtn.querySelector('[data-broadcaster-play-label]');
    if (label) {
      label.textContent = playing ? 'PAUSE' : 'PLAY';
    }
    var hint = this.playBtn.querySelector('[data-broadcaster-play-hint]');
    if (hint) {
      hint.textContent = playing ? 'live on deck' : 'start live audio';
    }
  };

  HomeYvrBroadcaster.prototype.setPlaybackRate = function (rate) {
    this.playbackRate = rate;
    if (this.audio) {
      this.audio.playbackRate = rate;
    }
    if (this.speedLabelEl) {
      this.speedLabelEl.textContent = rate === 1 ? '1×' : rate.toFixed(2).replace(/\.?0+$/, '') + '×';
    }
    this.speedBtns.forEach(function (btn) {
      var step = parseFloat(btn.getAttribute('data-broadcaster-speed') || '1');
      btn.classList.toggle('is-active', Math.abs(step - rate) < 0.01);
    });
  };

  HomeYvrBroadcaster.prototype.startPlayback = function () {
    var self = this;
    this.unlockAudio();

    if (this.activePinKey) {
      var config = this.getPinConfig(this.activePinKey);
      if (config) {
        this.tryPlayAudioChain(config.audio_keys || [], this.activePinKey);
        if (!this.isAudioPlaying()) {
          this.refreshFeedsInBackground(this.activePinKey, config);
        }
        return;
      }
    }

    var audioKey = this.activeAudioKey || this.activeKey;
    if (!audioKey) return;

    var channel = this.audioChannels[audioKey];
    if (channel) {
      this.activateAudioChannel(channel, this.activePinKey);
      return;
    }

    this.fetchFeeds(true).then(function (feeds) {
      if (!feeds) return;
      self.mergeFeeds(feeds);
      var fresh = self.audioChannels[audioKey];
      if (fresh) self.activateAudioChannel(fresh, self.activePinKey);
    });
  };

  HomeYvrBroadcaster.prototype.init = function () {
    var self = this;

    this.wireHeroMap();
    if (!this.heroMap) {
      this.pendingMapSelectKey = null;
      var mapRetries = 0;
      var mapRetryTimer = setInterval(function () {
        mapRetries += 1;
        self.wireHeroMap();
        if (self.heroMap || mapRetries > 40) clearInterval(mapRetryTimer);
      }, 100);
    }

    this.channelBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-broadcaster-channel-btn');
        if (!key || !self.isKnownChannel(key)) return;
        self.unlockAudio();
        self.focusDavePlayer();
        self.activate(key);
        self.updatePlayButton();
      });
    });

    if (this.playBtn) {
      this.playBtn.addEventListener('click', function () {
        self.unlockAudio();
        if (self.isAudioPlaying()) {
          self.audio.pause();
          self.setBars(false);
          self.stopMeter();
          self.setListeningUi(false);
          self.setMouthIdle();
          self.updatePlayButton();
          return;
        }
        self.startPlayback();
      });
    }

    if (this.stopBtn) {
      this.stopBtn.addEventListener('click', function () {
        self.stopAll(true);
      });
    }

    if (this.faceEl) {
      this.faceEl.style.cursor = 'pointer';
      this.faceEl.addEventListener('click', function () {
        self.unlockAudio();
        self.cycleDaveAmbient();
      });
    }

    this.setPlaybackRate(1);

    if (this.speedBtns.length) {
      this.speedBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var step = parseFloat(btn.getAttribute('data-broadcaster-speed') || '1');
          if (!step || step < 0.5 || step > 2) return;
          self.setPlaybackRate(step);
        });
      });
    }

    this.loadFeeds();
    this.setStandby();
    this.updatePlayButton();

    if (this.audio) {
      this.audio.addEventListener('playing', function () {
        self.setupAudioMeter();
        self.setListeningUi(true);
        self.setBars(true);
        self.startMeter();
        self.updatePlayButton();
        self.root.classList.remove('is-armed');
      });
      this.audio.addEventListener('pause', function () {
        self.updatePlayButton();
      });
    }

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) self.stopAll(true);
    });
  };

  HomeYvrBroadcaster.prototype.unlockAudio = function () {
    if (!this.audio) return;

    if (this.audioUnlocked) {
      this.resumeAudioContext();
      return;
    }

    try {
      this.audio.muted = true;
      var playPromise = this.audio.play();
      var finish = function () {
        if (!this.audio) return;
        this.audio.pause();
        this.audio.muted = false;
        this.audioUnlocked = true;
        this.resumeAudioContext();
      }.bind(this);

      if (playPromise && typeof playPromise.then === 'function') {
        playPromise.then(finish).catch(function () {
          this.audioUnlocked = true;
        }.bind(this));
      } else {
        finish();
      }
    } catch (e) {
      this.audioUnlocked = true;
    }
  };

  HomeYvrBroadcaster.prototype.isAudioPlaying = function () {
    return this.audio && !this.audio.paused && !this.audio.ended;
  };

  HomeYvrBroadcaster.prototype.setListeningUi = function (on) {
    this.root.classList.toggle('is-listening', on);
    if (this.mapWrapEl) {
      this.mapWrapEl.classList.toggle('is-listening', on);
    }
    if (this.ringLabelEl) {
      this.ringLabelEl.textContent = on ? 'live audio on deck' : 'drag · tap pins';
    }
  };

  HomeYvrBroadcaster.prototype.clearAutoMuteTimer = function () {
    if (this.autoMuteTimer) {
      clearTimeout(this.autoMuteTimer);
      this.autoMuteTimer = null;
    }
  };

  HomeYvrBroadcaster.prototype.setMouthIdle = function () {
    if (this.mouthTimer) {
      clearTimeout(this.mouthTimer);
      this.mouthTimer = null;
    }
    if (this.mouthEl) {
      this.mouthEl.classList.remove('is-open', 'is-flap');
    }
    if (this.faceEl) {
      this.faceEl.classList.remove('is-talking');
    }
  };

  HomeYvrBroadcaster.prototype.setMouthTalking = function () {
    if (this.faceEl) {
      this.faceEl.classList.add('is-talking');
    }
    if (!this.mouthEl) return;
    this.mouthEl.classList.add('is-flap');
  };

  HomeYvrBroadcaster.prototype.scheduleAutoMute = function (channelKey) {
    var self = this;
    this.clearAutoMuteTimer();
    var ac = this.getAudioChannel(channelKey);
    var delay = ac && ac.loop ? 0 : 900;
    if (!delay) return;
    this.autoMuteTimer = setTimeout(function () {
      if (self.activeKey === channelKey) {
        self.stopAll(true);
      }
    }, delay);
  };

  HomeYvrBroadcaster.prototype.renderAttribution = function (channel) {
    if (!this.attributionEl) return;

    var text = channel && channel.attribution ? channel.attribution : '';
    var url = channel && channel.attribution_url ? channel.attribution_url : '';

    if (!text) {
      this.attributionEl.hidden = true;
      this.attributionEl.innerHTML = '';
      return;
    }

    this.attributionEl.hidden = false;
    if (url) {
      this.attributionEl.innerHTML =
        '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' +
        escapeHtml(text) + '</a>';
    } else {
      this.attributionEl.textContent = text;
    }
  };

  HomeYvrBroadcaster.prototype.packFromAudioChannel = function (channel) {
    var items = [];
    if (channel.link_url) {
      items.push({
        url: channel.link_url,
        link_label: channel.link_label || 'Open feed'
      });
    }
    if (channel.credit) {
      items.push({
        url: channel.source_url || '',
        link_label: channel.credit
      });
    }
    return {
      source: channel.source || channel.label,
      source_url: channel.source_url || '',
      fetched_label: channel.mode === 'link_out' ? 'Opens off-site' : 'Live now',
      attribution: channel.attribution || '',
      attribution_url: channel.attribution_url || '',
      items: items
    };
  };

  HomeYvrBroadcaster.prototype.renderMeta = function (pack) {
    if (!this.metaEl) return;

    var html = '';

    if (pack && pack.fetched_label) {
      html += '<li class="home-yvr-teleprompt__item home-yvr-teleprompt__item--fetched">';
      html += '<span class="home-yvr-teleprompt__time">' + escapeHtml(pack.fetched_label) + '</span>';
      html += '</li>';
    }

    if (pack && pack.attribution) {
      html += '<li class="home-yvr-teleprompt__item home-yvr-teleprompt__item--attribution">';
      if (pack.attribution_url) {
        html += '<a class="home-yvr-teleprompt__link" href="' + escapeHtml(pack.attribution_url) + '" target="_blank" rel="noopener noreferrer">';
        html += escapeHtml(pack.attribution) + '</a>';
      } else {
        html += '<span class="home-yvr-teleprompt__time">' + escapeHtml(pack.attribution) + '</span>';
      }
      html += '</li>';
    }

    var items = (pack && pack.items) ? pack.items : [];
    items.forEach(function (item, index) {
      if (!item.url) return;
      var linkLabel = escapeHtml(item.link_label || 'Source ' + (index + 1));
      html += '<li class="home-yvr-teleprompt__item home-yvr-teleprompt__item--link">';
      html += '<a class="home-yvr-teleprompt__link" href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener noreferrer">' + linkLabel + '</a>';
      html += '</li>';
    });

    if (pack && pack.source_url) {
      html += '<li class="home-yvr-teleprompt__item home-yvr-teleprompt__item--source">';
      html += '<a class="home-yvr-teleprompt__link" href="' + escapeHtml(pack.source_url) + '" target="_blank" rel="noopener noreferrer">';
      html += escapeHtml(pack.source || 'Source') + '</a>';
      html += '</li>';
    }

    if (html) {
      this.metaEl.innerHTML = html;
      this.metaEl.hidden = false;
      this.metaEl.classList.add('is-footer');
    } else {
      this.metaEl.innerHTML = '';
      this.metaEl.hidden = true;
      this.metaEl.classList.remove('is-footer');
    }
  };

  HomeYvrBroadcaster.prototype.renderScript = function (text) {
    if (!this.scriptEl) return;
    this.scriptEl.textContent = text || '';
  };

  HomeYvrBroadcaster.prototype.packFromFeedPack = function (pack) {
    if (!pack) return null;
    var items = [];
    (pack.items || []).forEach(function (item) {
      if (item.url) {
        items.push({
          url: item.url,
          link_label: item.link_label || item.title || 'Source'
        });
      }
    });
    return {
      source: pack.source || '',
      source_url: pack.source_url || '',
      fetched_label: pack.fetched_label || '',
      items: items
    };
  };

  HomeYvrBroadcaster.prototype.renderBulletin = function (pack, config, pinKey) {
    if (!this.scriptEl) return;

    var tier = (config && config.tier) || pinKey || 'data';
    var label = (config && config.bulletin_label) || 'Territory bulletin';
    var html = '<div class="home-yvr-bulletin home-yvr-bulletin--' + escapeHtml(tier) + '">';
    html += '<p class="home-yvr-bulletin__kicker pixel-font">' + escapeHtml(label) + '</p>';

    if (pack && pack.caption) {
      html += '<p class="home-yvr-bulletin__lead">' + escapeHtml(pack.caption) + '</p>';
    }

    var items = (pack && pack.items) ? pack.items : [];
    if (items.length) {
      html += '<ul class="home-yvr-bulletin__list">';
      items.forEach(function (item) {
        html += '<li class="home-yvr-bulletin__row">';
        if (item.title) {
          html += '<span class="home-yvr-bulletin__title">' + escapeHtml(item.title) + '</span>';
        }
        if (item.text) {
          html += '<span class="home-yvr-bulletin__text">' + escapeHtml(item.text) + '</span>';
        }
        if (item.posted_label) {
          html += '<span class="home-yvr-bulletin__time">' + escapeHtml(item.posted_label) + '</span>';
        }
        if (item.url) {
          html += '<a class="home-yvr-bulletin__link" href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener noreferrer">';
          html += escapeHtml(item.link_label || 'Open incident') + '</a>';
        }
        html += '</li>';
      });
      html += '</ul>';
    } else if (pack && pack.script) {
      html += '<p class="home-yvr-bulletin__text">' + escapeHtml(pack.script) + '</p>';
    } else {
      html += '<p class="home-yvr-bulletin__text">No active incidents in this feed right now.</p>';
    }

    html += '</div>';
    this.scriptEl.innerHTML = html;
    this.scriptEl.scrollTop = 0;
  };

  HomeYvrBroadcaster.prototype.appendBulletinAudioNote = function (message) {
    if (!this.scriptEl) return;
    var note = document.createElement('p');
    note.className = 'home-yvr-bulletin__audio-note pixel-font';
    note.textContent = message;
    this.scriptEl.appendChild(note);
  };

  HomeYvrBroadcaster.prototype.setTelepromptStandby = function () {
    this.renderMeta(null);
    if (this.scriptEl) {
      this.scriptEl.innerHTML = '';
      this.scriptEl.textContent = 'tap a pin — bulletin lands here. PLAY for live audio.';
    }
    this.renderAttribution(null);
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.remove('is-live', 'is-radio', 'is-bulletin');
    }
    if (this.heroMap) {
      this.heroMap.setActiveChannel(null);
    }
  };

  HomeYvrBroadcaster.prototype.setStandby = function () {
    if (this.freqEl) this.freqEl.textContent = '000.000';
    if (this.channelEl) this.channelEl.textContent = 'STANDBY';
    this.setTelepromptStandby();
    this.setMouthIdle();
    this.clearAutoMuteTimer();
    this.root.classList.remove('is-live', 'is-speaking', 'is-radio', 'is-bulletin', 'is-bulletin-only', 'is-armed');
    this.setBars(false);
    this.highlightChannel(null);
    this.updatePlayButton();
  };

  HomeYvrBroadcaster.prototype.highlightChannel = function (key) {
    this.channelBtns.forEach(function (btn) {
      var match = btn.getAttribute('data-broadcaster-channel-btn') === key;
      btn.classList.toggle('is-active', match);
      btn.setAttribute('aria-pressed', match ? 'true' : 'false');
    });
  };

  HomeYvrBroadcaster.prototype.fetchFeeds = function (refresh) {
    var self = this;
    var url = this.feedsUrl;
    if (refresh) {
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'refresh=1';
    }
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data) {
          self.mergeFeeds(data);
        }
        return self.feeds;
      })
      .catch(function () { return self.feeds; });
  };

  HomeYvrBroadcaster.prototype.updateHeroMapFromFeeds = function (feeds) {
    if (!this.heroMap || !feeds) return;
    if (feeds.metro_map && feeds.metro_map.overlays) {
      this.heroMap.setOverlays(feeds.metro_map.overlays);
    }
    if (this.activePinKey) {
      this.heroMap.setActiveChannel(this.activePinKey);
    }
  };

  HomeYvrBroadcaster.prototype.loadFeeds = function () {
    var self = this;
    this.fetchFeeds(false).then(function (feeds) {
      if (feeds) self.mergeFeeds(feeds);
      self.updateHeroMapFromFeeds(self.feeds);
    });
  };

  HomeYvrBroadcaster.prototype.ensureFeeds = function (refresh) {
    if (this.feeds && !refresh) return Promise.resolve(this.feeds);
    return this.fetchFeeds(true);
  };

  HomeYvrBroadcaster.prototype.stopMeter = function () {
    this.meterActive = false;
    if (this.meterRaf) {
      cancelAnimationFrame(this.meterRaf);
      this.meterRaf = null;
    }
    this.bars.forEach(function (bar) {
      bar.style.transform = '';
    });
  };

  HomeYvrBroadcaster.prototype.startMeter = function () {
    var self = this;
    if (!this.audio || !this.analyser) return;

    this.meterActive = true;
    var data = new Uint8Array(this.analyser.frequencyBinCount);

    function tick() {
      if (!self.meterActive) return;
      self.analyser.getByteFrequencyData(data);
      var sum = 0;
      for (var i = 0; i < data.length; i++) sum += data[i];
      var avg = sum / data.length;
      var level = 0.35 + (avg / 255) * 0.65;
      self.bars.forEach(function (bar, i) {
        var jitter = 0.85 + (i * 0.04);
        bar.style.transform = 'scaleY(' + Math.min(1, level * jitter).toFixed(3) + ')';
      });
      self.meterRaf = requestAnimationFrame(tick);
    }

    tick();
  };

  HomeYvrBroadcaster.prototype.setupAudioMeter = function () {
    if (!this.audio || this.meterWired) return;
    try {
      if (!this.audioCtx) {
        this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        this.analyser = this.audioCtx.createAnalyser();
        this.analyser.fftSize = 64;
        this.audioSourceNode = this.audioCtx.createMediaElementSource(this.audio);
        this.audioSourceNode.connect(this.analyser);
        this.analyser.connect(this.audioCtx.destination);
        this.meterWired = true;
      }
      this.resumeAudioContext();
    } catch (e) {
      /* meter optional — keep native element output */
    }
  };

  HomeYvrBroadcaster.prototype.stopRadio = function () {
    this.clearAutoMuteTimer();
    this.stopMeter();
    if (this.hls) {
      this.hls.destroy();
      this.hls = null;
    }
    if (this.audio) {
      this.audio.pause();
      this.audio.removeAttribute('src');
      this.audio.loop = false;
    }
    this.root.classList.remove('is-radio');
    if (this.telepromptRoot) this.telepromptRoot.classList.remove('is-radio');
    this.setMouthIdle();
  };

  HomeYvrBroadcaster.prototype.stopAll = function (toStandby) {
    this.clearAutoMuteTimer();
    this.stopRadio();
    this.activeKey = null;
    this.activePinKey = null;
    this.activeAudioKey = null;
    this.setBars(false);
    this.setListeningUi(false);
    this.root.classList.remove('is-live', 'is-bulletin', 'is-bulletin-only');
    if (this.heroMap) {
      this.heroMap.setActiveChannel(null);
    }
    if (toStandby) this.setStandby();
  };

  HomeYvrBroadcaster.prototype.setBars = function (on) {
    this.bars.forEach(function (bar, i) {
      bar.classList.toggle('is-live', on);
      bar.style.animationDelay = (i * 0.1) + 's';
    });
    if (!on) this.stopMeter();
  };

  HomeYvrBroadcaster.prototype.updateDisplay = function (channel) {
    if (this.freqEl) this.freqEl.textContent = channel.freq;
    if (this.channelEl) this.channelEl.textContent = channel.label;
  };

  HomeYvrBroadcaster.prototype.startBulletinAudio = function (channel) {
    var self = this;
    if (!this.audio || !channel.stream_url) return false;

    this.stopRadio();
    this.renderAttribution(channel);
    this.root.classList.add('is-radio');
    this.root.classList.remove('is-bulletin-only');
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-radio');
    }
    this.setMouthTalking();

    this.audio.loop = !!channel.loop;
    this.audio.volume = channel.mode === 'soundscape' ? 0.35 : 0.5;
    this.audio.playbackRate = this.playbackRate;

    if (channel.format === 'hls') {
      this.hls = attachHls(this.audio, channel.stream_url);
    } else {
      this.audio.src = channel.stream_url;
    }

    var self = this;
    this.resumeAudioContext();

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise
        .then(function () {
          self.setBars(true);
          self.startMeter();
          self.setListeningUi(true);
        })
        .catch(function () {
          self.setListeningUi(false);
          self.root.classList.add('is-armed');
          self.appendBulletinAudioNote('Hit PLAY below — live audio is armed.');
          self.updatePlayButton();
        });
    } else {
      this.setBars(true);
      this.startMeter();
      this.setListeningUi(true);
    }
    return true;
  };

  HomeYvrBroadcaster.prototype.tryPlayAudioChain = function (keys, pinKey) {
    var self = this;
    var tried = [];

    if (!keys || !keys.length) {
      this.root.classList.add('is-bulletin-only');
      this.setBars(true);
      this.appendBulletinAudioNote('Bulletin only — no matched live scanner for this pin.');
      return;
    }

    for (var i = 0; i < keys.length; i++) {
      var audioKey = keys[i];
      var channel = this.audioChannels[audioKey];
      if (!channel) continue;

      tried.push(channel.label || audioKey);

      if (channel.mode === 'link_out') {
        continue;
      }

      if (channel.mode === 'broadcastify') {
        if (channel.stream_ok && channel.stream_url) {
          this.activeAudioKey = audioKey;
          this.highlightChannel(audioKey);
          this.startBulletinAudio(channel);
          this.appendBulletinAudioNote('Live scan: ' + (channel.label || audioKey) + '.');
          return;
        }
        continue;
      }

      if ((channel.mode === 'stream' || channel.mode === 'soundscape') && channel.stream_ok && channel.stream_url) {
        this.activeAudioKey = audioKey;
        this.highlightChannel(audioKey);
        this.startBulletinAudio(channel);
        this.appendBulletinAudioNote('Live audio: ' + (channel.label || audioKey) + '.');
        return;
      }
    }

    this.root.classList.add('is-bulletin-only');
    this.setBars(true);
    this.appendBulletinAudioNote(
      'Scanners offline (' + tried.join(', ') + ') — bulletin text is still current.'
    );
  };

  HomeYvrBroadcaster.prototype.mergeFeeds = function (data) {
    if (!data) return this.feeds;
    this.feeds = data;
    if (data.channels) {
      this.audioChannels = data.channels;
    }
    if (data.dave_ambient_cycle) {
      this.ambientCycleKeys = data.dave_ambient_cycle;
    }
    if (data.data_channel_pins) {
      this.dataChannelPins = data.data_channel_pins;
    }
    return this.feeds;
  };

  HomeYvrBroadcaster.prototype.refreshFeedsInBackground = function (pinKey, config) {
    var self = this;
    this.fetchFeeds(true).then(function (feeds) {
      if (!feeds) return;
      self.mergeFeeds(feeds);
      self.updateHeroMapFromFeeds(feeds);

      if (!pinKey || !config) return;
      if (self.activePinKey !== pinKey) return;

      var feedKey = config.feed_key || pinKey;
      var pack = feeds[feedKey] ? feeds[feedKey] : null;
      self.renderBulletin(pack, config, pinKey);
      self.renderMeta(self.packFromFeedPack(pack));

      if (!self.isAudioPlaying()) {
        self.tryPlayAudioChain(config.audio_keys || [], pinKey);
      }
    });
  };

  HomeYvrBroadcaster.prototype.activatePin = function (pinKey) {
    var self = this;
    var config = this.getPinConfig(pinKey);
    if (!config) return;

    this.stopAll(false);
    this.activeKey = pinKey;
    this.activePinKey = pinKey;

    var display = this.getDisplayChannel(pinKey);
    if (display) this.updateDisplay(display);
    this.highlightChannel(null);

    this.root.classList.add('is-live', 'is-bulletin', 'is-armed');
    this.root.classList.remove('is-bulletin-only', 'is-radio');
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live', 'is-bulletin');
      this.telepromptRoot.classList.remove('is-radio');
    }

    if (this.heroMap) {
      this.heroMap.setActiveChannel(pinKey);
    }

    this.setBars(true);
    this.setMouthTalking();

    var feeds = this.feeds;
    var feedKey = config.feed_key || pinKey;
    var pack = feeds && feeds[feedKey] ? feeds[feedKey] : null;

    if (pack) {
      this.renderBulletin(pack, config, pinKey);
      this.renderMeta(this.packFromFeedPack(pack));
    } else {
      this.renderScript('Pulling bulletin… hit PLAY for live audio.');
    }
    this.renderAttribution(null);

    var audioKeys = config.audio_keys || [];
    this.tryPlayAudioChain(audioKeys, pinKey);
    this.updatePlayButton();

    if (!this.isAudioPlaying()) {
      this.refreshFeedsInBackground(pinKey, config);
    } else {
      var bgConfig = config;
      this.fetchFeeds(true).then(function (fresh) {
        if (!fresh || self.activePinKey !== pinKey) return;
        self.mergeFeeds(fresh);
        self.updateHeroMapFromFeeds(fresh);
        var freshPack = fresh[feedKey] ? fresh[feedKey] : null;
        self.renderBulletin(freshPack, bgConfig, pinKey);
        self.renderMeta(self.packFromFeedPack(freshPack));
      });
    }
  };

  HomeYvrBroadcaster.prototype.playStream = function (channel, pack, pinKey) {
    var self = this;
    if (!this.audio || !channel.stream_url) return;

    this.stopRadio();
    var display = pinKey ? this.getDisplayChannel(pinKey) : this.getDisplayChannel(channel.key);
    if (display) this.updateDisplay(display);
    this.highlightChannel(channel.key);
    this.renderMeta(pack);
    this.renderAttribution(channel);
    this.renderScript(
      channel.mode === 'soundscape'
        ? 'Field audio bed on loop. STOP to cut.'
        : (channel.attribution
          ? 'Live feed on deck. Attribution on screen — STOP to cut.'
          : 'Live audio on deck. STOP to silence.')
    );
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live', 'is-radio');
    }
    this.root.classList.add('is-live', 'is-radio');
    this.setMouthTalking();

    this.audio.loop = !!channel.loop;
    this.audio.volume = channel.mode === 'soundscape' ? 0.42 : 0.55;
    this.audio.playbackRate = this.playbackRate;

    if (channel.format === 'hls') {
      this.hls = attachHls(this.audio, channel.stream_url);
    } else {
      this.audio.src = channel.stream_url;
    }

    this.resumeAudioContext();

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise
        .then(function () {
          self.setBars(true);
          self.startMeter();
          self.setListeningUi(true);
        })
        .catch(function () {
          self.renderScript('Hit PLAY below — channel is armed on deck.');
          self.setBars(false);
          self.setMouthIdle();
          self.setListeningUi(false);
          self.root.classList.add('is-armed');
          self.updatePlayButton();
        });
    } else {
      this.setBars(true);
      this.startMeter();
      this.setListeningUi(true);
    }
  };

  HomeYvrBroadcaster.prototype.playLinkOut = function (channel, pack, pinKey) {
    this.stopRadio();
    var display = pinKey ? this.getDisplayChannel(pinKey) : this.getDisplayChannel(channel.key);
    if (display) this.updateDisplay(display);
    this.highlightChannel(channel.key);
    this.renderMeta(pack);
    this.renderAttribution(channel);
    this.renderScript('Live feed opens off-site — no robot voice on this deck.');
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live');
      this.telepromptRoot.classList.remove('is-radio');
    }
    this.root.classList.add('is-live');
    this.setBars(true);
  };

  HomeYvrBroadcaster.prototype.activateAudioChannel = function (channel, pinKey) {
    var pack = this.packFromAudioChannel(channel);

    if (channel.mode === 'link_out') {
      if (this.heroMap && pinKey) this.heroMap.setActiveChannel(pinKey);
      this.playLinkOut(channel, pack, pinKey);
      if (channel.link_url) {
        window.open(channel.link_url, '_blank', 'noopener,noreferrer');
      }
      return;
    }

    if (channel.mode === 'broadcastify') {
      if (channel.stream_ok && channel.stream_url) {
        if (this.heroMap && pinKey) this.heroMap.setActiveChannel(pinKey);
        this.playStream(channel, pack, pinKey);
        return;
      }
      if (this.heroMap && pinKey) this.heroMap.setActiveChannel(pinKey);
      this.playLinkOut(channel, pack, pinKey);
      this.renderScript('Scanner offline — opening Broadcastify in a new tab.');
      if (channel.link_url) {
        window.open(channel.link_url, '_blank', 'noopener,noreferrer');
      }
      return;
    }

    if (channel.mode === 'stream' || channel.mode === 'soundscape') {
      if (!channel.stream_ok || !channel.stream_url) {
        this.renderScript('Live feed not available right now — try another channel.');
        this.setBars(false);
        return;
      }
      if (this.heroMap && pinKey) this.heroMap.setActiveChannel(pinKey);
      this.playStream(channel, pack, pinKey);
      return;
    }
  };

  HomeYvrBroadcaster.prototype.cycleDaveAmbient = function () {
    if (!this.ambientCycleKeys.length) return;

    var idx = -1;
    if (this.activeKey) {
      idx = this.ambientCycleKeys.indexOf(this.activeKey);
    }

    var nextIdx = idx + 1;
    if (nextIdx >= this.ambientCycleKeys.length) {
      this.stopAll(true);
      return;
    }

    this.activate(this.ambientCycleKeys[nextIdx]);
  };

  HomeYvrBroadcaster.prototype.activate = function (key) {
    var self = this;

    if (!this.isKnownChannel(key)) return;

    if (PIN_CHANNELS[key]) {
      if (this.activePinKey === key) {
        this.stopAll(true);
        return;
      }
      this.activatePin(key);
      return;
    }

    if (this.activeKey === key) {
      this.stopAll(true);
      return;
    }

    this.stopAll(false);
    this.activeKey = key;
    this.activePinKey = null;
    this.activeAudioKey = key;

    var display = this.getDisplayChannel(key);
    if (display) this.updateDisplay(display);
    this.renderScript('Tuning live audio…');
    this.root.classList.add('is-live', 'is-armed');
    this.setBars(true);
    this.setMouthTalking();

    var channel = this.audioChannels[key];
    if (channel) {
      this.activateAudioChannel(channel, null);
      this.fetchFeeds(true).then(function (feeds) {
        if (!feeds || self.activeKey !== key) return;
        self.mergeFeeds(feeds);
        self.updateHeroMapFromFeeds(feeds);
        if (!self.isAudioPlaying()) {
          var fresh = self.audioChannels[key];
          if (fresh) self.activateAudioChannel(fresh, null);
        }
      });
      return;
    }

    this.fetchFeeds(true).then(function (feeds) {
      if (!feeds || self.activeKey !== key) return;
      self.mergeFeeds(feeds);
      self.updateHeroMapFromFeeds(feeds);

      var freshChannel = self.audioChannels[key];
      if (!freshChannel) {
        self.renderScript('Live feed still loading — try again in a moment.');
        self.setBars(false);
        self.setMouthIdle();
        return;
      }

      self.activateAudioChannel(freshChannel, null);
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
