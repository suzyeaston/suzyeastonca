(function () {
  'use strict';

  var DATA_CHANNELS = {
    translink: { label: 'SKYTRAIN', freq: '410.287', key: 'translink' },
    drivers: { label: 'DRIVE BC', freq: '154.100', key: 'drivers' },
    ferries: { label: 'BC FERRIES', freq: '156.800', key: 'ferries' },
    weather: { label: 'WEATHER', freq: '162.550', key: 'weather' },
    wildfire: { label: 'WILDFIRE', freq: '168.050', key: 'wildfire' },
    air: { label: 'AIR QUALITY', freq: '153.785', key: 'air' }
  };

  var VOICE_PREFS = [
    'Microsoft Aria Online (Natural)',
    'Microsoft Aria',
    'Microsoft Jenny Online (Natural)',
    'Microsoft Jenny',
    'Google US English',
    'Samantha (Enhanced)',
    'Samantha',
    'Microsoft Zira',
    'Karen',
    'Moira'
  ];

  var VOICE_BLOCK = [
    'David',
    'UK English Male',
    'Fred',
    'Albert',
    'Bad News',
    'Cellos',
    'Trinoids',
    'Whisper'
  ];

  function scoreVoice(voice) {
    if (!voice || !voice.lang || !/^en/i.test(voice.lang)) {
      return -1000;
    }

    var name = voice.name || '';
    var lang = voice.lang.toLowerCase();
    var score = 0;

    if (lang === 'en-us') score += 24;
    else if (lang === 'en-gb') score -= 18;
    else if (lang.indexOf('en') !== -1) score += 8;

    if (/natural|neural|online|premium|enhanced/i.test(name)) score += 42;

    VOICE_PREFS.forEach(function (pref, i) {
      if (name.indexOf(pref) !== -1) score += 36 - i;
    });

    VOICE_BLOCK.forEach(function (bad) {
      if (name.indexOf(bad) !== -1) score -= 80;
    });

    if (voice.localService) score += 4;

    return score;
  }

  function pickModernVoice() {
    if (!('speechSynthesis' in window)) return null;
    var voices = window.speechSynthesis.getVoices();
    if (!voices.length) return null;

    var best = null;
    var bestScore = -9999;
    voices.forEach(function (v) {
      var s = scoreVoice(v);
      if (s > bestScore) {
        bestScore = s;
        best = v;
      }
    });
    return best;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function splitWords(text) {
    var words = [];
    var regex = /\S+/g;
    var match;
    while ((match = regex.exec(text)) !== null) {
      words.push({
        word: match[0],
        start: match.index,
        end: match.index + match[0].length
      });
    }
    return words;
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
    this.bedAudio = root.querySelector('[data-broadcaster-bed-audio]');
    this.attributionEl = root.querySelector('[data-broadcaster-attribution]');
    this.freqEl = root.querySelector('[data-broadcaster-freq]');
    this.channelEl = root.querySelector('[data-broadcaster-channel]');
    this.stopBtn = root.querySelector('[data-broadcaster-stop]');
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
    this.feeds = null;
    this.audioChannels = {};
    this.ambientCycleKeys = (window.HomeYvrBroadcasterConfig && HomeYvrBroadcasterConfig.daveAmbientCycle) || [];
    this.dataChannelBeds = {};
    this.activeKey = null;
    this.speechUtterance = null;
    this.wordSpans = [];
    this.fallbackTimer = null;
    this.voice = null;
    this.mouthTimer = null;
    this.autoMuteTimer = null;
    this.hls = null;
    this.audioCtx = null;
    this.analyser = null;
    this.meterActive = false;
    this.meterRaf = null;
    this.audioSourceNode = null;
    this.bedHls = null;
  }

  HomeYvrBroadcaster.prototype.isKnownChannel = function (key) {
    return DATA_CHANNELS[key] || (this.audioChannels && this.audioChannels[key]);
  };

  HomeYvrBroadcaster.prototype.getDisplayChannel = function (key) {
    if (DATA_CHANNELS[key]) return DATA_CHANNELS[key];
    var ac = this.audioChannels[key];
    if (!ac) return null;
    return { label: ac.label, freq: ac.freq, key: key };
  };

  HomeYvrBroadcaster.prototype.getAudioChannel = function (key) {
    return this.audioChannels[key] || null;
  };

  HomeYvrBroadcaster.prototype.init = function () {
    var self = this;

    this.heroMap = window.HomeHeroMap || null;
    if (this.heroMap) {
      this.heroMap.onChannelSelect = function (key) {
        if (self.isKnownChannel(key)) self.activate(key);
      };
    }

    this.channelBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-broadcaster-channel-btn');
        if (!key || !self.isKnownChannel(key)) return;
        self.activate(key);
      });
    });

    if (this.stopBtn) {
      this.stopBtn.addEventListener('click', function () {
        self.stopAll(true);
      });
    }

    if (this.faceEl) {
      this.faceEl.style.cursor = 'pointer';
      this.faceEl.addEventListener('click', function () {
        self.cycleDaveAmbient();
      });
    }

    if ('speechSynthesis' in window) {
      var primeVoice = function () {
        self.voice = pickModernVoice();
      };
      primeVoice();
      window.speechSynthesis.addEventListener('voiceschanged', primeVoice);
    }

    this.loadFeeds();
    this.setStandby();

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) self.stopAll(true);
    });
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

  HomeYvrBroadcaster.prototype.setMouthTalking = function (mode) {
    if (this.faceEl) {
      this.faceEl.classList.add('is-talking');
    }
    if (!this.mouthEl) return;
    this.mouthEl.classList.toggle('is-flap', mode === 'radio');
    if (mode !== 'radio') {
      this.mouthEl.classList.remove('is-flap');
    }
  };

  HomeYvrBroadcaster.prototype.pulseMouth = function () {
    var self = this;
    if (!this.mouthEl) return;
    this.mouthEl.classList.add('is-open');
    if (this.mouthTimer) clearTimeout(this.mouthTimer);
    this.mouthTimer = setTimeout(function () {
      if (self.mouthEl) self.mouthEl.classList.remove('is-open');
    }, 110);
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

  HomeYvrBroadcaster.prototype.clearFallbackTimer = function () {
    if (this.fallbackTimer) {
      clearInterval(this.fallbackTimer);
      this.fallbackTimer = null;
    }
  };

  HomeYvrBroadcaster.prototype.clearWordHighlights = function () {
    this.wordSpans.forEach(function (span) {
      span.classList.remove('is-current', 'is-spoken');
    });
  };

  HomeYvrBroadcaster.prototype.highlightWordAt = function (charIndex) {
    var idx = -1;
    for (var i = 0; i < this.wordSpans.length; i++) {
      var start = parseInt(this.wordSpans[i].getAttribute('data-start'), 10);
      if (start <= charIndex) idx = i;
      if (start > charIndex) break;
    }
    if (idx < 0) return;

    for (var j = 0; j < this.wordSpans.length; j++) {
      var span = this.wordSpans[j];
      span.classList.toggle('is-spoken', j < idx);
      span.classList.toggle('is-current', j === idx);
    }

    var current = this.wordSpans[idx];
    if (current && this.scriptEl) {
      var box = this.scriptEl;
      var top = current.offsetTop - box.offsetTop;
      var target = top - (box.clientHeight / 2) + (current.clientHeight / 2);
      box.scrollTop = Math.max(0, target);
    }

    if (this.heroMap && this.activeKey) {
      var matched = this.heroMap.matchMarkerByText(this.wordSpans[idx].textContent);
      if (matched && matched.id) {
        this.heroMap.highlightMarker(matched.id);
      }
    }
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

  HomeYvrBroadcaster.prototype.renderScript = function (text, mode) {
    if (!this.scriptEl) return;

    this.clearFallbackTimer();
    this.clearWordHighlights();
    this.wordSpans = [];

    if (!text) {
      this.scriptEl.textContent = '';
      return;
    }

    if (mode === 'plain') {
      this.scriptEl.textContent = text;
      return;
    }

    var words = splitWords(text);
    var frag = document.createDocumentFragment();

    words.forEach(function (w, i) {
      var span = document.createElement('span');
      span.className = 'home-yvr-teleprompt__word';
      span.setAttribute('data-start', String(w.start));
      span.textContent = w.word;
      frag.appendChild(span);
      if (i < words.length - 1) {
        frag.appendChild(document.createTextNode(' '));
      }
      this.wordSpans.push(span);
    }, this);

    this.scriptEl.innerHTML = '';
    this.scriptEl.appendChild(frag);
    this.scriptEl.scrollTop = 0;
  };

  HomeYvrBroadcaster.prototype.setTelepromptStandby = function () {
    this.renderMeta(null);
    this.renderScript('drag the map. tap a pin. dave reads feeds — listen row is live audio.', 'plain');
    this.renderAttribution(null);
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.remove('is-live', 'is-radio');
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
          self.feeds = data;
          if (data.channels) self.audioChannels = data.channels;
          if (data.dave_ambient_cycle) self.ambientCycleKeys = data.dave_ambient_cycle;
          if (data.data_channel_beds) self.dataChannelBeds = data.data_channel_beds;
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
    if (this.activeKey) {
      this.heroMap.setActiveChannel(this.activeKey);
    }
  };

  HomeYvrBroadcaster.prototype.loadFeeds = function () {
    var self = this;
    this.fetchFeeds(false).then(function (feeds) {
      self.updateHeroMapFromFeeds(feeds);
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
    if (!this.audio) return;
    try {
      if (!this.audioCtx) {
        this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        this.analyser = this.audioCtx.createAnalyser();
        this.analyser.fftSize = 64;
        this.audioSourceNode = this.audioCtx.createMediaElementSource(this.audio);
        this.audioSourceNode.connect(this.analyser);
        this.analyser.connect(this.audioCtx.destination);
      }
      if (this.audioCtx.state === 'suspended') {
        this.audioCtx.resume();
      }
    } catch (e) {
      /* meter optional */
    }
  };

  HomeYvrBroadcaster.prototype.stopBed = function () {
    if (this.bedHls) {
      this.bedHls.destroy();
      this.bedHls = null;
    }
    if (this.bedAudio) {
      this.bedAudio.pause();
      this.bedAudio.removeAttribute('src');
      this.bedAudio.loop = false;
    }
  };

  HomeYvrBroadcaster.prototype.startSpeechBed = function (dataKey) {
    var self = this;
    var bedKey = this.dataChannelBeds[dataKey];
    if (!bedKey || !this.bedAudio) return;

    var bedChannel = this.getAudioChannel(bedKey);
    if (!bedChannel || !bedChannel.stream_ok || !bedChannel.stream_url) return;

    this.stopBed();
    this.bedAudio.loop = true;
    this.bedAudio.volume = 0.14;

    if (bedChannel.format === 'hls') {
      this.bedHls = attachHls(this.bedAudio, bedChannel.stream_url);
    } else {
      this.bedAudio.src = bedChannel.stream_url;
    }

    var playPromise = this.bedAudio.play();
    if (playPromise && typeof playPromise.catch === 'function') {
      playPromise.catch(function () { /* bed optional */ });
    }
  };

  HomeYvrBroadcaster.prototype.stopSpeech = function () {
    this.stopBed();
    this.clearFallbackTimer();
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    this.speechUtterance = null;
    this.root.classList.remove('is-speaking');
    if (!this.root.classList.contains('is-radio')) {
      this.setMouthIdle();
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
    this.stopSpeech();
    this.stopRadio();
    this.stopBed();
    this.activeKey = null;
    this.setBars(false);
    this.root.classList.remove('is-live');
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
    this.highlightChannel(channel.key || null);
  };

  HomeYvrBroadcaster.prototype.startFallbackHighlight = function (text) {
    var self = this;
    var words = splitWords(text);
    if (!words.length) return;

    var msPerWord = 340;
    var idx = 0;
    self.highlightWordAt(words[0].start);

    this.fallbackTimer = setInterval(function () {
      idx += 1;
      if (idx >= words.length) {
        self.clearFallbackTimer();
        return;
      }
      self.highlightWordAt(words[idx].start);
      self.pulseMouth();
    }, msPerWord);
  };

  HomeYvrBroadcaster.prototype.speak = function (text, channel, pack) {
    var self = this;
    if (!text || !('speechSynthesis' in window)) {
      this.renderScript(text || 'Voice not available in this browser.', 'plain');
      return;
    }

    this.stopSpeech();
    this.updateDisplay(channel);
    this.renderMeta(pack);
    this.renderAttribution(channel);
    this.renderScript(text);
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live');
      this.telepromptRoot.classList.remove('is-radio');
    }
    this.root.classList.add('is-live', 'is-speaking');
    this.setBars(true);
    this.setMouthTalking('speech');

    var utterance = new SpeechSynthesisUtterance(text);
    var voice = this.voice || pickModernVoice();
    if (voice) {
      utterance.voice = voice;
      this.voice = voice;
    }
    utterance.rate = 1.02;
    utterance.pitch = 1.0;

    var boundarySeen = false;

    utterance.onboundary = function (event) {
      if (event.name === 'word' || event.charIndex > 0) {
        boundarySeen = true;
        self.clearFallbackTimer();
        self.highlightWordAt(event.charIndex);
        self.pulseMouth();
      }
    };

    utterance.onstart = function () {
      self.setMouthTalking('speech');
      self.startSpeechBed(channel.key);
      if (!boundarySeen && self.wordSpans.length) {
        self.startFallbackHighlight(text);
      }
    };

    utterance.onend = function () {
      self.stopBed();
      self.clearFallbackTimer();
      self.clearWordHighlights();
      if (self.wordSpans.length) {
        self.wordSpans.forEach(function (span) { span.classList.add('is-spoken'); });
      }
      self.root.classList.remove('is-speaking');
      self.setMouthIdle();
      if (self.activeKey === channel.key) {
        self.setBars(false);
        self.scheduleAutoMute(channel.key);
      }
    };

    utterance.onerror = function () {
      self.stopBed();
      self.clearFallbackTimer();
      self.root.classList.remove('is-speaking');
      self.setBars(false);
      self.setMouthIdle();
      self.scheduleAutoMute(channel.key);
    };

    this.speechUtterance = utterance;
    window.speechSynthesis.speak(utterance);
  };

  HomeYvrBroadcaster.prototype.playStream = function (channel, pack) {
    var self = this;
    if (!this.audio || !channel.stream_url) return;

    this.stopRadio();
    this.updateDisplay(this.getDisplayChannel(channel.key));
    this.renderMeta(pack);
    this.renderAttribution(channel);
    this.renderScript(
      channel.mode === 'soundscape'
        ? 'Ambient bed on loop. Dave quiet — STOP to cut it.'
        : 'Live audio. Dave quiet — STOP to silence.',
      'plain'
    );
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live', 'is-radio');
    }
    this.root.classList.add('is-live', 'is-radio');
    this.setMouthTalking('radio');

    this.audio.loop = !!channel.loop;
    this.audio.volume = channel.mode === 'soundscape' ? 0.42 : 0.55;

    if (channel.format === 'hls') {
      this.hls = attachHls(this.audio, channel.stream_url);
    } else {
      this.audio.src = channel.stream_url;
    }

    this.setupAudioMeter();

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise
        .then(function () {
          self.setBars(true);
          self.startMeter();
        })
        .catch(function () {
          self.renderScript('Autoplay blocked — tap channel again or hit STOP then retry.', 'plain');
          self.setBars(false);
          self.setMouthIdle();
        });
    } else {
      this.setBars(true);
      this.startMeter();
    }
  };

  HomeYvrBroadcaster.prototype.playLinkOut = function (channel, pack) {
    this.stopRadio();
    this.updateDisplay(this.getDisplayChannel(channel.key));
    this.renderMeta(pack);
    this.renderAttribution(channel);
    this.renderScript(
      'Third-party live feed — opens off-site. Dave stays quiet on purpose.',
      'plain'
    );
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live');
      this.telepromptRoot.classList.remove('is-radio');
    }
    this.root.classList.add('is-live');
    this.setBars(true);
  };

  HomeYvrBroadcaster.prototype.activateAudioChannel = function (channel) {
    var self = this;
    var pack = this.packFromAudioChannel(channel);
    var display = this.getDisplayChannel(channel.key);

    if (channel.mode === 'link_out') {
      if (this.heroMap) this.heroMap.setActiveChannel(channel.key);
      this.playLinkOut(channel, pack);
      if (channel.link_url) {
        window.open(channel.link_url, '_blank', 'noopener,noreferrer');
      }
      return;
    }

    if (channel.mode === 'broadcastify') {
      if (channel.stream_ok && channel.stream_url) {
        if (this.heroMap) this.heroMap.setActiveChannel(channel.key);
        this.playStream(channel, pack);
        return;
      }
      if (this.heroMap) this.heroMap.setActiveChannel(channel.key);
      this.playLinkOut(channel, pack);
      this.renderScript('Scanner feed offline or blocked — opening Broadcastify.', 'plain');
      if (channel.link_url) {
        window.open(channel.link_url, '_blank', 'noopener,noreferrer');
      }
      return;
    }

    if (channel.mode === 'stream' || channel.mode === 'soundscape') {
      if (!channel.stream_ok || !channel.stream_url) {
        this.renderScript('Stream not available right now — try again soon.', 'plain');
        this.setBars(false);
        return;
      }
      if (this.heroMap) this.heroMap.setActiveChannel(channel.key);
      this.playStream(channel, pack);
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

    if (this.activeKey === key) {
      this.stopAll(true);
      return;
    }

    this.stopAll(false);
    this.activeKey = key;

    var audioChannel = this.getAudioChannel(key);
    if (audioChannel) {
      var display = this.getDisplayChannel(key);
      this.updateDisplay(display);
      this.renderScript('Tuning ' + display.label + '…', 'plain');
      this.root.classList.add('is-live');
      this.setBars(true);

      this.ensureFeeds(true).then(function (feeds) {
        var fresh = feeds && feeds.channels && feeds.channels[key];
        if (fresh) self.audioChannels[key] = fresh;
        self.activateAudioChannel(self.audioChannels[key] || audioChannel);
      });
      return;
    }

    var channel = DATA_CHANNELS[key];
    this.updateDisplay(channel);
    this.renderScript('Tuning ' + channel.label + '…', 'plain');
    this.root.classList.add('is-live');
    this.setBars(true);

    this.ensureFeeds(true).then(function (feeds) {
      var pack = feeds && feeds[channel.key] ? feeds[channel.key] : null;
      if (!pack) {
        self.renderScript('Feed still loading — try again in a moment.', 'plain');
        self.setBars(false);
        return;
      }

      self.updateHeroMapFromFeeds(feeds);
      if (self.heroMap) {
        self.heroMap.setActiveChannel(channel.key);
      }

      var script = pack.script || pack.caption || '';
      self.speak(script, channel, pack);
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
