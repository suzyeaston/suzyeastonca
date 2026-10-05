(function () {
  'use strict';
  const root = document.getElementById('yvr-radio');
  if (!root || !window.YvrRadio) return;
  const el = id => document.getElementById('yr-' + id);
  const channels = YvrRadio.channels;
  let selected = null, audio = el('audio'), hls = null, generation = 0;
  let state = 'idle', timer = null, lastTime = 0, lastProgress = 0;
  let favourites = [];
  try { const saved = JSON.parse(localStorage.getItem('yvr-radio-favourites') || '[]'); if (Array.isArray(saved)) favourites = saved.filter(x => typeof x === 'string'); } catch (_) {}
  audio.volume = 0.8;
  const messages = {idle:'READY', selected:'READY TO TUNE', connecting:'CONNECTING', buffering:'BUFFERING', playing:'PLAYING', paused:'PAUSED', stopped:'STOPPED', error:'CONNECTION FAILED', external:'EXTERNAL PLAYER', ended:'STREAM ENDED'};
  function setState(next, detail) {
    state = next;
    el('status').dataset.state = next;
    const muted = next === 'playing' && (audio.muted || audio.volume === 0) ? ' · MUTED' : '';
    el('status').textContent = messages[next] + muted + ' · ' + (detail || (selected ? selected.label : 'Pick a station'));
    const pending = next === 'connecting' || next === 'buffering';
    el('play').textContent = pending ? 'Cancel connection' : next === 'playing' ? 'Pause' : 'Play';
    el('play').disabled = !selected || selected.mode === 'link_out';
    el('stop').disabled = !selected || selected.mode === 'link_out';
    el('retry').hidden = !['error','ended'].includes(next);
    el('diagnostics').textContent = 'Status: ' + next + '\nSource: ' + (selected ? selected.label : 'none') + '\nFormat: ' + (selected ? selected.format || 'external' : 'none') + '\nBrowser readyState: ' + audio.readyState + '\nBrowser networkState: ' + audio.networkState + '\nMedia error: ' + (audio.error ? audio.error.code : 'none') + '\nNo audio is relayed through WordPress.';
  }
  function clearTimer() { if (timer) clearTimeout(timer); timer = null; }
  function armTimeout(token) {
    clearTimer();
    timer = setTimeout(() => { if (token === generation && ['connecting','buffering'].includes(state)) fail('No playback after 20 seconds. Retry or open the official player.'); }, 20000);
  }
  function release() {
    generation++;
    clearTimer();
    if (hls) { hls.destroy(); hls = null; }
    // Fresh element isolates late events and play promises from the previous station.
    const old = audio;
    audio = old.cloneNode(false);
    audio.volume = old.volume;
    audio.muted = old.muted;
    old.replaceWith(audio);
    old.pause(); old.removeAttribute('src'); old.load();
    lastTime = 0; lastProgress = 0;
  }
  function fail(message) { release(); setState('error', message); }
  function bindAudio(token) {
    const current = audio;
    const on = (name, fn) => current.addEventListener(name, () => { if (generation === token && current === audio) fn(); });
    on('playing', () => { clearTimer(); lastProgress = Date.now(); setState('playing'); });
    on('timeupdate', () => {
      if (current.currentTime > lastTime && !current.paused) {
        lastProgress = Date.now();
        if (state === 'buffering') { clearTimer(); setState('playing'); }
      }
      lastTime = current.currentTime;
    });
    on('waiting', () => { if (!current.paused) { setState('buffering'); armTimeout(token); } });
    // stalled alone can occur while buffered audio keeps playing; watchdog checks progress.
    on('pause', () => { if (['playing','buffering','connecting'].includes(state)) { clearTimer(); setState('paused'); } });
    on('ended', () => { clearTimer(); setState('ended'); });
    on('error', () => fail('The stream could not be played. Retry or use the official player.'));
    on('volumechange', () => { if (state === 'playing') setState('playing'); });
  }
  function start() {
    if (!selected || selected.mode === 'link_out') return;
    release();
    const token = generation, current = audio;
    bindAudio(token);
    setState('connecting'); armTimeout(token);
    current.loop = !!selected.loop;
    const play = () => {
      if (token !== generation) return;
      const promise = current.play();
      if (promise) promise.catch(error => {
        if (token !== generation || error.name === 'AbortError') return;
        fail(error.name === 'NotAllowedError' ? 'Browser blocked playback. Press Retry to allow audio.' : 'Unable to start this stream. Try its official player.');
      });
    };
    if (selected.format === 'hls' && !current.canPlayType('application/vnd.apple.mpegurl')) {
      if (!window.Hls || !window.Hls.isSupported()) { fail('This browser cannot play this HLS stream. Use the official player.'); return; }
      hls = new window.Hls();
      hls.on(window.Hls.Events.MANIFEST_PARSED, play);
      hls.on(window.Hls.Events.ERROR, (_event, data) => { if (token === generation && data.fatal) fail('The live playlist failed. Retry or use the official player.'); });
      hls.loadSource(selected.stream_url); hls.attachMedia(current);
    } else { current.src = selected.stream_url; play(); }
  }
  function select(channel, autoplay) {
    release(); selected = channel;
    el('title').textContent = channel.label;
    el('description').textContent = channel.hint || '';
    el('note').textContent = channel.mode === 'soundscape' ? 'RECORDED LOOP · ' + (channel.credit || 'Not a live feed.') : channel.mode === 'link_out' ? 'Opens the provider’s player. Its playback state is not visible here.' : 'LIVE STREAM · Quiet periods can be normal. No automatic station switching.';
    const link = channel.link_url || channel.source_url;
    el('source').hidden = !link;
    if (link) el('source').href = link;
    el('share').disabled = false;
    el('share-result').textContent = '';
    const url = new URL(location.href); url.searchParams.set('station', channel.key); history.replaceState(null, '', url);
    setState(channel.mode === 'link_out' ? 'external' : 'selected');
    render();
    if (autoplay && channel.mode !== 'link_out') start();
  }
  function render() {
    const q = el('search').value.toLowerCase().trim(), group = el('category').value;
    const visible = channels.filter(c => (!group || (group === 'Favourites' ? favourites.includes(c.key) : c.group === group)) && [c.label,c.hint,c.group].join(' ').toLowerCase().includes(q));
    const list = el('stations'); list.replaceChildren();
    el('count').textContent = visible.length + ' signals' + (visible.length ? '' : ' · Try another search or save a favourite.');
    visible.forEach(c => {
      const row = document.createElement('article'); row.className = 'yr-station' + (selected && selected.key === c.key ? ' is-selected' : '');
      const body = document.createElement('div');
      const title = document.createElement('h3'); title.textContent = c.label;
      const hint = document.createElement('p'); hint.textContent = c.hint;
      const type = document.createElement('p'); type.textContent = c.mode === 'link_out' ? c.group + ' · Official player ↗' : c.mode === 'soundscape' ? 'Recorded loop · In this page' : c.group + ' · In-page stream';
      const tune = document.createElement(c.mode === 'link_out' ? 'a' : 'button'); tune.className='yr-tune'; tune.textContent = c.mode === 'link_out' ? 'Open official player ↗' : 'Tune in';
      if(c.mode === 'link_out') { tune.href = c.link_url || c.source_url; tune.target='_blank'; tune.rel='noopener noreferrer'; }
      tune.addEventListener('click', () => select(c, true));
      const star=document.createElement('button'); star.className='yr-star'; star.textContent=favourites.includes(c.key)?'★':'☆'; star.setAttribute('aria-label','Favourite '+c.label); star.setAttribute('aria-pressed',String(favourites.includes(c.key)));
      star.addEventListener('click', () => { favourites=favourites.includes(c.key)?favourites.filter(k=>k!==c.key):favourites.concat(c.key); try { localStorage.setItem('yvr-radio-favourites',JSON.stringify(favourites)); } catch (_) {} render(); const replacement=Array.from(list.querySelectorAll('.yr-star')).find(b=>b.getAttribute('aria-label')==='Favourite '+c.label); if(replacement) replacement.focus(); else el('category').focus(); });
      body.append(title,hint,type,tune); row.append(body,star); list.append(row);
    });
  }
  el('search').addEventListener('input',render); el('category').addEventListener('change',render);
  el('play').addEventListener('click',() => {
    if (['connecting','buffering'].includes(state)) { release(); setState('stopped'); }
    else if (state === 'playing') audio.pause();
    else start();
  });
  el('stop').addEventListener('click',() => {release();setState('stopped');});
  el('retry').addEventListener('click',start);
  el('volume').addEventListener('input',() => { audio.volume=Number(el('volume').value); el('volume-value').textContent=Math.round(audio.volume*100)+'%'; });
  el('share').addEventListener('click',async () => {
    try {await navigator.clipboard.writeText(location.href);el('share-result').textContent='Station link copied.';} catch (_) {el('share-result').textContent='Copy the station URL from your address bar.';}
  });
  // Detect a connection that stopped making playback progress without a waiting event.
  setInterval(() => {if(state === 'playing' && lastProgress && Date.now()-lastProgress>15000 && !audio.paused) {setState('buffering');armTimeout(generation);}},3000);
  window.addEventListener('pagehide',() => { release();setState('stopped'); });
  render();
  const requested=channels.find(c=>c.key===new URL(location.href).searchParams.get('station'));
  if(requested) select(requested,false);
})();
