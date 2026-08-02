(function () {
  'use strict';

  var CKNW_STREAM = 'https://live.leanstream.co/CKNWAM';

  var CHANNELS = {
    translink: { label: 'SKYTRAIN', freq: '410.287', key: 'translink' },
    drivers: { label: 'DRIVE BC', freq: '154.100', key: 'drivers' },
    ferries: { label: 'BC FERRIES', freq: '156.800', key: 'ferries' },
    weather: { label: 'WEATHER', freq: '162.550', key: 'weather' },
    wildfire: { label: 'WILDFIRE', freq: '168.050', key: 'wildfire' },
    air: { label: 'AIR QUALITY', freq: '153.785', key: 'air' },
    cknw: { label: 'CKNW 980', freq: '980.000', key: 'cknw', stream: true }
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

  function HomeYvrBroadcaster(root) {
    this.root = root;
    this.audio = root.querySelector('[data-broadcaster-audio]');
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
    this.wildfireMap = window.HomeYvrWildfireMap ? new window.HomeYvrWildfireMap(root) : null;
    this.feedsUrl = (window.HomeYvrBroadcasterConfig && HomeYvrBroadcasterConfig.feedsUrl) ||
      '/wp-json/se/v1/broadcaster/feeds';
    this.feeds = null;
    this.activeKey = null;
    this.speechUtterance = null;
    this.wordSpans = [];
    this.fallbackTimer = null;
    this.voice = null;
    this.mouthTimer = null;
    this.autoMuteTimer = null;
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
      var self = this;
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
    this.autoMuteTimer = setTimeout(function () {
      if (self.activeKey === channelKey) {
        self.stopAll(true);
      }
    }, 900);
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

    if (this.wildfireMap && this.activeKey === 'wildfire') {
      var matched = this.wildfireMap.matchMarkerByText(this.wordSpans[idx].textContent);
      if (matched) {
        this.wildfireMap.highlightMarker(matched.id);
      }
    }
  };

  HomeYvrBroadcaster.prototype.renderMeta = function (pack) {
    if (!this.metaEl) return;

    var html = '';

    if (pack && pack.fetched_label) {
      html += '<li class="home-yvr-teleprompt__item home-yvr-teleprompt__item--fetched">';
      html += '<span class="home-yvr-teleprompt__time">' + escapeHtml(pack.fetched_label) + '</span>';
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
    this.renderScript('Tap a channel. Dave reads the feed — words light up.', 'plain');
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.remove('is-live', 'is-radio', 'is-wildfire');
    }
    if (this.wildfireMap) {
      this.wildfireMap.hide();
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
        if (data) self.feeds = data;
        return self.feeds;
      })
      .catch(function () { return self.feeds; });
  };

  HomeYvrBroadcaster.prototype.loadFeeds = function () {
    this.fetchFeeds(false);
  };

  HomeYvrBroadcaster.prototype.ensureFeeds = function (refresh) {
    if (this.feeds && !refresh) return Promise.resolve(this.feeds);
    return this.fetchFeeds(true);
  };

  HomeYvrBroadcaster.prototype.stopSpeech = function () {
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
    if (this.audio) {
      this.audio.pause();
      this.audio.removeAttribute('src');
    }
    this.root.classList.remove('is-radio');
    if (this.telepromptRoot) this.telepromptRoot.classList.remove('is-radio');
    this.setMouthIdle();
  };

  HomeYvrBroadcaster.prototype.stopAll = function (toStandby) {
    this.clearAutoMuteTimer();
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
      if (!boundarySeen && self.wordSpans.length) {
        self.startFallbackHighlight(text);
      }
    };

    utterance.onend = function () {
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
      self.clearFallbackTimer();
      self.root.classList.remove('is-speaking');
      self.setBars(false);
      self.setMouthIdle();
      self.scheduleAutoMute(channel.key);
    };

    this.speechUtterance = utterance;
    window.speechSynthesis.speak(utterance);
  };

  HomeYvrBroadcaster.prototype.playRadio = function (channel, pack) {
    var self = this;
    if (!this.audio) return;

    this.stopRadio();
    this.updateDisplay(channel);
    this.renderMeta(pack || {
      source: 'CKNW 980',
      source_url: 'https://www.cknw.com/',
      fetched_label: 'Live now',
      items: [{
        url: 'https://www.cknw.com/',
        link_label: 'cknw.com'
      }]
    });
    this.renderScript('Live CKNW 980. Dave goes quiet — hit STOP to silence.', 'plain');
    if (this.telepromptRoot) {
      this.telepromptRoot.classList.add('is-live', 'is-radio');
    }
    this.root.classList.add('is-live', 'is-radio');
    this.setMouthTalking('radio');
    this.audio.src = CKNW_STREAM;
    this.audio.volume = 0.55;

    var playPromise = this.audio.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise
        .then(function () {
          self.setBars(true);
        })
        .catch(function () {
          self.renderScript('CKNW blocked autoplay — tap CKNW 980 again or STOP then retry.', 'plain');
          self.setBars(false);
          self.setMouthIdle();
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
    this.updateDisplay(channel);
    this.renderScript('Tuning ' + channel.label + '…', 'plain');
    this.root.classList.add('is-live');
    this.setBars(true);

    if (channel.stream) {
      this.playRadio(channel);
      return;
    }

    this.ensureFeeds(true).then(function (feeds) {
      var pack = feeds && feeds[channel.key] ? feeds[channel.key] : null;
      if (!pack) {
        self.renderScript('Feed still loading — try again in a moment.', 'plain');
        self.setBars(false);
        if (self.wildfireMap) self.wildfireMap.hide();
        return;
      }

      if (channel.key === 'wildfire' && self.wildfireMap) {
        if (self.telepromptRoot) self.telepromptRoot.classList.add('is-wildfire');
        self.wildfireMap.render(pack.map || null);
      } else if (self.wildfireMap) {
        if (self.telepromptRoot) self.telepromptRoot.classList.remove('is-wildfire');
        self.wildfireMap.hide();
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
