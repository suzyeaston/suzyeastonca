(function () {
  function initPacificPowerPlay() {
    const stage = document.querySelector('[data-arcade-stage]');
    const screen = stage ? stage.querySelector('.hero-game-stage__screen') : null;
    const externalStartButtons = document.querySelectorAll('[data-arcade-start]');
    if (!stage || !screen) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    window.__SE_GALAGA_ACTIVE = false;
    window.__SE_POWER_PLAY_ACTIVE = false;

    stage.classList.add('has-galaga', 'is-galaga', 'is-power-play');
    stage.dataset.galagaReady = 'true';
    stage.dataset.galagaActive = 'false';
    stage.dataset.galagaState = 'idle';
    stage.dataset.powerPlayReady = 'true';
    stage.dataset.powerPlayState = 'idle';
    stage.tabIndex = 0;
    stage.setAttribute('role', 'application');
    stage.setAttribute('aria-label', 'Pacific Power Play hockey arcade game. Use WASD or arrow keys to skate, Space to shoot, and Escape to pause.');

    const canvas = document.createElement('canvas');
    canvas.className = 'hero-galaga-canvas pacific-power-play-canvas';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'Dark neon Vancouver hockey rink with pixel skater, static blockers, goalie, puck, scoreboard, and goal lamp.');
    canvas.textContent = 'Pacific Power Play canvas game. Use WASD or arrow keys to skate, Space to shoot, and Escape to pause.';

    const ui = document.createElement('div');
    ui.className = 'hero-galaga-ui pacific-power-play-ui';
    ui.innerHTML = '<p class="hero-galaga-status" data-hockey-status>PERIOD 1</p><p class="hero-galaga-scoreline">GOALS <span data-hockey-goals>0</span> // SHOTS <span data-hockey-shots>0</span></p><p class="hero-galaga-vessel">SIGNAL <span data-hockey-signal>100</span>% // <span data-hockey-power>POWER PLAY READY</span></p><p class="hero-galaga-help">WASD / ARROWS SKATE // SPACE SHOOT // ESC PAUSE</p><p class="hero-galaga-wavecall" data-hockey-call hidden></p>';

    const overlay = document.createElement('div');
    overlay.className = 'hero-galaga-overlay pacific-power-play-overlay';
    overlay.innerHTML = '<div class="hero-galaga-panel pacific-power-play-panel" data-hockey-idle-panel><p class="hero-galaga-hint-text">DROP THE PUCK<br>WASD / ARROWS SKATE<br>SPACE SHOOT<br>ESC PAUSE</p><button type="button" class="hero-galaga-reboot" data-hockey-start>Drop The Puck</button></div><div class="hero-galaga-panel pacific-power-play-panel" data-hockey-pause-panel hidden><p class="hero-galaga-gameover__title">BENCH DOOR OPEN</p><p class="hero-galaga-gameover__line">Paused in the rain city rink.</p><button type="button" class="hero-galaga-reboot" data-hockey-resume>Resume Power Play</button><p class="hero-galaga-gameover__exit">Esc to resume</p></div>';

    screen.append(canvas, ui, overlay);
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.imageSmoothingEnabled = false;

    const els = {
      goals: ui.querySelector('[data-hockey-goals]'), shots: ui.querySelector('[data-hockey-shots]'), signal: ui.querySelector('[data-hockey-signal]'), power: ui.querySelector('[data-hockey-power]'), call: ui.querySelector('[data-hockey-call]'),
      idle: overlay.querySelector('[data-hockey-idle-panel]'), pause: overlay.querySelector('[data-hockey-pause-panel]'), start: overlay.querySelector('[data-hockey-start]'), resume: overlay.querySelector('[data-hockey-resume]')
    };
    const keys = new Set();
    const state = { mode: 'idle', w: 640, h: 360, goals: 0, shots: 0, signal: 100, callTimer: 0, flash: 0, shake: 0, player: { x: 155, y: 180, r: 9, speed: 190 }, puck: { x: 170, y: 180, r: 4, vx: 0, vy: 0, attached: true }, goalie: { x: 560, y: 180, w: 10, h: 62, vy: 120 }, blockers: [] };
    const resetBlockers = () => { state.blockers = [{ x: 360, y: 115, r: 12, vx: -55, vy: 42 }, { x: 430, y: 245, r: 13, vx: -70, vy: -36 }]; };
    resetBlockers();

    function resize() { const rect = screen.getBoundingClientRect(); const dpr = Math.min(window.devicePixelRatio || 1, 2); state.w = Math.max(320, Math.round(rect.width)); state.h = Math.max(220, Math.round(rect.height)); canvas.width = Math.round(state.w * dpr); canvas.height = Math.round(state.h * dpr); ctx.setTransform(dpr, 0, 0, dpr, 0, 0); }
    function setMode(mode) { state.mode = mode; stage.dataset.galagaState = mode === 'paused' ? 'idle' : mode; stage.dataset.powerPlayState = mode; stage.dataset.galagaActive = mode === 'playing' ? 'true' : 'false'; window.__SE_GALAGA_ACTIVE = mode === 'playing'; window.__SE_POWER_PLAY_ACTIVE = mode === 'playing'; els.idle.hidden = mode !== 'idle'; els.pause.hidden = mode !== 'paused'; overlay.hidden = mode === 'playing'; }
    function call(text) { els.call.textContent = text; els.call.hidden = false; state.callTimer = 1.5; }
    function resetPuck() { state.puck.attached = true; state.puck.vx = 0; state.puck.vy = 0; state.puck.x = state.player.x + 15; state.puck.y = state.player.y + 5; }
    function start() { resize(); state.goals = 0; state.shots = 0; state.signal = 100; state.player.x = 155; state.player.y = state.h / 2; resetBlockers(); resetPuck(); setMode('playing'); call('HASTINGS PARK FACE-OFF'); stage.focus({ preventScroll: true }); }
    function shoot() { if (state.mode !== 'playing' || !state.puck.attached) return; state.puck.attached = false; state.puck.vx = 430; state.puck.vy = (state.player.y - state.h / 2) * 0.28; state.shots += 1; call('ONE TIMER THROUGH THE STATIC'); }

    function update(dt) {
      if (state.mode !== 'playing') return;
      let dx = 0, dy = 0; if (keys.has('arrowleft') || keys.has('a')) dx--; if (keys.has('arrowright') || keys.has('d')) dx++; if (keys.has('arrowup') || keys.has('w')) dy--; if (keys.has('arrowdown') || keys.has('s')) dy++;
      const len = Math.hypot(dx, dy) || 1; state.player.x = Math.max(45, Math.min(state.w * 0.62, state.player.x + dx / len * state.player.speed * dt)); state.player.y = Math.max(58, Math.min(state.h - 38, state.player.y + dy / len * state.player.speed * dt));
      state.goalie.y += state.goalie.vy * dt; if (state.goalie.y < 88 || state.goalie.y > state.h - 88) state.goalie.vy *= -1;
      state.blockers.forEach(b => { b.x += b.vx * dt; b.y += b.vy * dt; if (b.x < state.w * 0.28 || b.x > state.w * 0.78) b.vx *= -1; if (b.y < 70 || b.y > state.h - 45) b.vy *= -1; });
      if (state.puck.attached) { state.puck.x = state.player.x + 17; state.puck.y = state.player.y + 6; } else { state.puck.x += state.puck.vx * dt; state.puck.y += state.puck.vy * dt; state.puck.vx *= 0.998; }
      const goal = { x: state.w - 35, y: state.h / 2 - 42, w: 18, h: 84 };
      const hitGoalie = Math.abs(state.puck.x - state.goalie.x) < 13 && Math.abs(state.puck.y - state.goalie.y) < state.goalie.h / 2;
      const hitBlocker = state.blockers.some(b => Math.hypot(state.puck.x - b.x, state.puck.y - b.y) < b.r + state.puck.r + 3);
      if (!state.puck.attached && hitGoalie) { state.signal = Math.max(0, state.signal - 7); call('STATIC BLOCKER SAVE'); resetPuck(); }
      else if (!state.puck.attached && hitBlocker) { state.signal = Math.max(0, state.signal - 4); call('PUCK LOST IN GRANVILLE FOG'); resetPuck(); }
      else if (!state.puck.attached && state.puck.x > goal.x && state.puck.y > goal.y && state.puck.y < goal.y + goal.h) { state.goals++; state.flash = reducedMotion.matches ? .15 : .45; state.shake = reducedMotion.matches ? 0 : 8; call('GOAL LAMP AT THE COLISEUM'); resetPuck(); }
      else if (!state.puck.attached && (state.puck.x > state.w + 20 || state.puck.y < 35 || state.puck.y > state.h + 20)) { call('WIDE OF THE FERRY TERMINAL'); resetPuck(); }
      if (state.callTimer > 0) { state.callTimer -= dt; if (state.callTimer <= 0) els.call.hidden = true; }
      state.signal = Math.max(0, Math.min(100, state.signal + dt * 1.4)); els.goals.textContent = state.goals; els.shots.textContent = state.shots; els.signal.textContent = Math.round(state.signal); els.power.textContent = state.goals % 3 === 2 ? 'POWER PLAY HOT' : 'POWER PLAY READY'; if (state.flash > 0) state.flash -= dt; if (state.shake > 0) state.shake -= 40 * dt;
    }
    function draw() { const w = state.w, h = state.h; const sx = state.shake ? (Math.random() - .5) * state.shake : 0; ctx.clearRect(0, 0, w, h); ctx.save(); ctx.translate(sx, 0); ctx.fillStyle = '#020713'; ctx.fillRect(0,0,w,h); ctx.strokeStyle = '#57f3ff'; ctx.lineWidth = 2; ctx.strokeRect(25, 35, w-50, h-55); ctx.strokeStyle = 'rgba(57,255,20,.7)'; ctx.beginPath(); ctx.moveTo(w*.33,35); ctx.lineTo(w*.33,h-20); ctx.moveTo(w*.66,35); ctx.lineTo(w*.66,h-20); ctx.stroke(); ctx.strokeStyle = '#ff4fd8'; ctx.beginPath(); ctx.moveTo(w/2,35); ctx.lineTo(w/2,h-20); ctx.stroke(); ctx.strokeStyle = 'rgba(255,255,255,.55)'; ctx.beginPath(); ctx.arc(w/2,h/2,34,0,7); ctx.stroke(); ctx.fillStyle = 'rgba(255,255,255,.8)'; [[w*.24,h*.32],[w*.24,h*.68],[w*.74,h*.32],[w*.74,h*.68]].forEach(p=>{ctx.beginPath();ctx.arc(p[0],p[1],4,0,7);ctx.fill();}); ctx.strokeStyle = '#8ef6ff'; ctx.strokeRect(w-35,h/2-42,18,84); ctx.strokeStyle = '#39ff14'; ctx.beginPath(); ctx.arc(w-50,h/2,28,-1.55,1.55);ctx.stroke(); ctx.fillStyle = '#0b1b36'; ctx.beginPath(); ctx.moveTo(40,30);ctx.lineTo(90,8);ctx.lineTo(135,30);ctx.lineTo(190,12);ctx.lineTo(242,30);ctx.fill(); ctx.strokeStyle='rgba(57,255,20,.18)'; for(let i=-h;i<w;i+=42){ctx.beginPath();ctx.moveTo(i, h);ctx.lineTo(i+170, 0);ctx.stroke();} drawSkater(state.player.x,state.player.y,'#39ff14','#57f3ff'); ctx.fillStyle='#e9ffff'; ctx.fillRect(state.puck.x-4,state.puck.y-3,8,6); drawGoalie(); state.blockers.forEach(drawBlocker); if(state.flash>0){ctx.fillStyle='rgba(255,32,80,'+Math.min(.5,state.flash)+')';ctx.fillRect(w-60,h/2-55,35,110);ctx.fillStyle='rgba(255,255,180,'+Math.min(.45,state.flash)+')';ctx.fillRect(0,0,w,h);} ctx.restore(); }
    function drawSkater(x,y,a,b){ctx.fillStyle=a;ctx.fillRect(x-6,y-12,12,18);ctx.fillStyle=b;ctx.fillRect(x-9,y-3,18,5);ctx.fillStyle='#fff';ctx.fillRect(x-5,y-20,10,8);ctx.fillStyle='#111';ctx.fillRect(x-12,y+10,11,3);ctx.fillRect(x+2,y+10,13,3);ctx.strokeStyle='#d8ffef';ctx.beginPath();ctx.moveTo(x+8,y-4);ctx.lineTo(x+23,y+11);ctx.stroke();}
    function drawGoalie(){ctx.fillStyle='#ff4fd8';ctx.fillRect(state.goalie.x-7,state.goalie.y-state.goalie.h/2,14,state.goalie.h);ctx.fillStyle='#fff';ctx.fillRect(state.goalie.x-10,state.goalie.y-8,20,16);}
    function drawBlocker(b){ctx.fillStyle='rgba(87,243,255,.8)';ctx.fillRect(b.x-b.r,b.y-b.r,b.r*2,b.r*2);ctx.fillStyle='#020713';ctx.fillRect(b.x-5,b.y-5,10,10);}
    let last = performance.now(); function loop(now){ const dt = Math.min(.033,(now-last)/1000); last=now; update(dt); draw(); requestAnimationFrame(loop); }
    window.addEventListener('resize', resize); document.addEventListener('keydown', e => { const k=e.key.toLowerCase(); if(['arrowup','arrowdown','arrowleft','arrowright',' ','spacebar','w','a','s','d','escape'].includes(k)) e.preventDefault(); if(k==='escape') setMode(state.mode==='playing'?'paused':'playing'); else if(k===' ' || k==='spacebar') shoot(); else keys.add(k); }); document.addEventListener('keyup', e => keys.delete(e.key.toLowerCase()));
    screen.addEventListener('pointerdown', e => { if(state.mode !== 'playing') start(); else { const r=screen.getBoundingClientRect(); state.player.x=Math.min(state.w*.62,Math.max(45,e.clientX-r.left)); state.player.y=Math.max(58,Math.min(state.h-38,e.clientY-r.top)); shoot(); }});
    els.start.addEventListener('click', start); els.resume.addEventListener('click', () => setMode('playing')); externalStartButtons.forEach(btn => btn.addEventListener('click', start)); resize(); setMode('idle'); requestAnimationFrame(loop);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPacificPowerPlay); else initPacificPowerPlay();
})();
