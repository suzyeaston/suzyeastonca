(() => {
  'use strict';
  const receivers = document.querySelectorAll('[data-pirate-radio]');
  receivers.forEach((root) => {
    const find = (name) => root.querySelector('[data-radio-' + name + ']');
    const audio = find('audio'), play = find('play'), stop = find('stop');
    const status = find('status-text'), message = find('message');
    const playback = find('playback'), listeners = find('listeners');
    const volume = find('volume'), volumeValue = find('volume-value');
    const streamUrl = root.dataset.streamUrl || '';
    const statusUrl = root.dataset.statusUrl || '';
    if (!audio || !play || !stop || !status || !playback) return;
    let online = streamUrl ? null : false;
    let count = null;
    let state = 'stopped';
    let failure = '';
    let attempt = 0;
    audio.volume = 0.8;

    function render() {
      const broadcast = !streamUrl ? 'unconfigured' : online === true ? 'online' : online === false ? 'offline' : 'unknown';
      root.dataset.broadcastState = broadcast;
      root.dataset.playerState = state;
      status.textContent = {
        unconfigured: 'TRANSMITTER NOT CONNECTED',
        online: 'ON AIR · TRANSMITTER CONNECTED',
        offline: 'OFF AIR · NO LIVE TRANSMISSION',
        unknown: statusUrl ? 'SIGNAL NOT VERIFIED' : 'TRANSMITTER STATUS UNAVAILABLE'
      }[broadcast];
      play.disabled = !streamUrl || online === false;
      stop.disabled = state === 'stopped';
      play.textContent = state === 'playing' ? 'Pause' : state === 'connecting' ? 'Connecting…' : state === 'paused' ? 'Resume' : state === 'error' || state === 'ended' ? 'Retry listening' : 'Listen live';
      if (state === 'connecting') play.disabled = true;
      if (message) {
        message.textContent = !streamUrl
          ? 'The station is being wired up. There is no public stream connected yet.'
          : online === true ? 'The source is transmitting. Press Listen to hear the live feed.'
          : online === false ? 'The source is offline. This receiver will update when a new broadcast begins.'
          : 'The public stream is configured, but transmitter status cannot currently be verified.';
      }
      const notes = {
        stopped: 'Playback is stopped. Nothing starts automatically.',
        connecting: 'Connecting to the live stream…',
        playing: 'Your browser is playing the stream. Live content may include quiet passages.',
        paused: 'Paused locally. Resume to reconnect to the live signal.',
        ended: 'The live stream ended. Try again when the transmitter is online.',
        error: failure || 'Could not play the stream. Check your connection and try again.'
      };
      playback.textContent = notes[state];
      if (listeners) listeners.textContent = online === true && Number.isInteger(count) && count >= 0
        ? count + (count === 1 ? ' listener connected' : ' listeners connected')
        : '';
    }

    function disconnect(nextState = 'stopped') {
      attempt += 1;
      audio.pause();
      audio.removeAttribute('src');
      audio.load();
      state = nextState;
      render();
    }

    async function start() {
      if (!streamUrl || online === false) return;
      const ticket = ++attempt;
      state = 'connecting'; failure = ''; render();
      audio.src = streamUrl;
      audio.load();
      try {
        await audio.play();
        if (ticket !== attempt) return;
        state = 'playing'; render();
      } catch (err) {
        if (ticket !== attempt) return;
        state = 'error';
        failure = err && err.name === 'NotAllowedError'
          ? 'Your browser prevented audio playback. Try Listen again.'
          : 'The receiver could not play the stream. It may be offline or blocked.';
        render();
      }
    }

    play.addEventListener('click', () => {
      if (state === 'playing') {
        audio.pause();
        // A live stream may not buffer indefinitely; resume reconnects.
        disconnect('paused');
      } else if (state !== 'connecting') {
        start();
      }
    });
    stop.addEventListener('click', () => disconnect());
    audio.addEventListener('playing', () => { if (state === 'connecting') { state = 'playing'; render(); } });
    audio.addEventListener('waiting', () => { if (state === 'playing') { state = 'connecting'; render(); } });
    audio.addEventListener('ended', () => { attempt += 1; state = 'ended'; render(); });
    audio.addEventListener('error', () => {
      if (state === 'stopped' || state === 'paused') return;
      attempt += 1; state = 'error';
      failure = 'The connection dropped or the stream could not be decoded. Retry when ready.';
      render();
    });
    if (volume) volume.addEventListener('input', () => {
      const value = Number(volume.value);
      audio.volume = Math.max(0, Math.min(1, Number.isFinite(value) ? value : 0.8));
      if (volumeValue) volumeValue.textContent = Math.round(audio.volume * 100) + '%';
    });

    async function checkTransmitter() {
      if (!statusUrl) return;
      try {
        const response = await fetch(statusUrl, {cache:'no-store', mode:'cors'});
        if (!response.ok) throw new Error('Transmitter status unavailable');
        const data = await response.json();
        if (typeof data.online !== 'boolean') throw new Error('Unrecognized status');
        online = data.online;
        count = Number.isInteger(data.listeners) ? data.listeners : null;
      } catch (_) {
        online = null;
        count = null;
      }
      render();
    }
    render();
    checkTransmitter();
    if (statusUrl) {
      const poll = setInterval(() => {
        if (document.visibilityState === 'visible') checkTransmitter();
      }, 15000);
      window.addEventListener('pagehide', () => clearInterval(poll), {once:true});
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkTransmitter();
      });
    }
  });
})();
