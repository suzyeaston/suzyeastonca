(function () {
  const PLAYERS = [
    {
      id: 'bure',
      name: 'PAVEL BURE',
      nick: 'THE RUSSIAN ROCKET',
      pos: 'RW',
      num: '10',
      set: '1993–94 COLISEUM',
      note: '60 goals. Breakaway after The Save.',
      stats: [['SPEED', 99], ['SHOT', 96], ['CHAOS', 88]],
      ability: 'ROCKET BURST',
      colors: ['#0a0a0a', '#fdb827', '#e03a3e'],
      speed: 250,
      control: 0.82,
      decay: 1,
      aim: 0.9
    },
    {
      id: 'linden',
      name: 'TREVOR LINDEN',
      nick: 'CAPTAIN',
      pos: 'C',
      num: '16',
      set: '1993–94 COLISEUM',
      note: 'Two goals in Game 7 at the Garden.',
      stats: [['GRIT', 99], ['SHOT', 86], ['LEAD', 95]],
      ability: "CAPTAIN'S PUSH",
      colors: ['#141414', '#f5f0e6', '#fdb827'],
      speed: 205,
      control: 1.12,
      decay: 0.55,
      aim: 0.55
    },
    {
      id: 'mclean',
      name: 'KIRK MCLEAN',
      nick: 'THE SAVE',
      pos: 'G',
      num: '1',
      set: '1993–94 COLISEUM',
      note: 'Game 7 vs Calgary. Then Bure went.',
      stats: [['SAVE', 99], ['NERVE', 94], ['GLOVE', 90]],
      ability: 'THE BIG SAVE',
      colors: ['#f5f0e6', '#fdb827', '#e03a3e'],
      speed: 185,
      control: 1,
      decay: 0.75,
      aim: 0.7
    },
    {
      id: 'courtnall',
      name: 'GEOFF COURTNALL',
      nick: 'WEST COAST SNAP',
      pos: 'LW',
      num: '14',
      set: '1993–94 COLISEUM',
      note: '70 points. OT vs Calgary in Game 5.',
      stats: [['SHOT', 90], ['SPEED', 84], ['CLUTCH', 88]],
      ability: 'OT SNAP',
      colors: ['#1a1a1a', '#fdb827', '#f5f0e6'],
      speed: 215,
      control: 0.9,
      decay: 0.9,
      aim: 0.65
    },
    {
      id: 'ronning',
      name: 'CLIFF RONNING',
      nick: 'THE PROFESSOR',
      pos: 'C',
      num: '7',
      set: '1993–94 COLISEUM',
      note: 'Tiny. Mean hands. 68 points.',
      stats: [['VISION', 96], ['PASS', 94], ['HANDS', 91]],
      ability: 'THREAD THE NEEDLE',
      colors: ['#0a0a0a', '#fdb827', '#e03a3e'],
      speed: 200,
      control: 1.25,
      decay: 0.75,
      aim: 0.35,
      curve: 1
    },
    {
      id: 'odjick',
      name: 'GINO ODJICK',
      nick: 'THE CHIEF',
      pos: 'LW',
      num: '29',
      set: '1993–94 COLISEUM',
      note: '271 PIM. Bure’s bodyguard.',
      stats: [['HIT', 99], ['FEAR', 91], ['HEART', 93]],
      ability: 'BOARD SHAKE',
      colors: ['#0a0a0a', '#e03a3e', '#fdb827'],
      speed: 165,
      control: 0.95,
      decay: 0.85,
      aim: 0.75
    }
  ];

  const CALLS = [
    'COLISEUM CROWD WAKES UP',
    'RUSSIAN ROCKET BREAKAWAY',
    'THE SAVE',
    'CAPTAIN TO THE NET',
    'OT HERO',
    'BOARD SHAKE',
    'HASTINGS PARK NOISE',
    'POWER PLAY HOT',
    'PUCK LOST IN GRANVILLE FOG'
  ];

  const T = {
    bg: '#0c0c0c',
    bar: '#141414',
    text: '#f5f0e6',
    line: '#fdb827',
    zone: 'rgba(253,184,39,.55)',
    accent: '#e03a3e',
    puck: '#fdb827',
    feed: 'PACIFIC COLISEUM FEED',
    goalie: '#e03a3e',
    blocker: 'rgba(253,184,39,.85)',
    blockerDot: '#e03a3e'
  };

  function init() {
    const stage = document.querySelector('[data-arcade-stage]');
    const screen = stage && stage.querySelector('.hero-game-stage__screen');
    const starts = document.querySelectorAll('[data-arcade-start]');
    if (!stage || !screen || stage.dataset.powerPlayReady) return;

    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const count = PLAYERS.length;
    stage.classList.add('has-galaga', 'is-galaga', 'is-power-play');
    stage.dataset.powerPlayReady = 'true';
    stage.dataset.powerPlayState = 'attract';
    stage.dataset.galagaState = 'attract';
    stage.tabIndex = 0;
    stage.setAttribute('role', 'application');
    stage.setAttribute(
      'aria-label',
      'Pacific Power Play. Tap a 1993-94 Canucks jersey to pick your skater, then skate with WASD or arrows, shoot with Space, use ability with E, and pause with Escape.'
    );

    const canvas = document.createElement('canvas');
    canvas.className = 'hero-galaga-canvas pacific-power-play-canvas';
    canvas.textContent =
      'Pacific Power Play canvas fallback: tap a 1993-94 Canucks roster card, then use WASD or arrows, Space, E, and Escape.';

    const ui = document.createElement('div');
    ui.className = 'hero-galaga-ui pacific-power-play-ui';
    ui.innerHTML =
      '<p class="hero-galaga-status">PLAYER: <span data-player>---</span> // PERIOD 1</p><p class="hero-galaga-scoreline">GOALS <span data-goals>0</span> // SHOTS <span data-shots>0</span> // SIGNAL <span data-signal>100</span>% // ABILITY <span data-ability>100</span>%</p><p class="hero-galaga-wavecall" data-call hidden></p><div class="power-play-mini-card" data-mini hidden></div><div class="power-play-goal-flash" data-flash hidden>GOAL LAMP</div>';

    const overlay = document.createElement('div');
    overlay.className = 'hero-galaga-overlay pacific-power-play-overlay';
    overlay.innerHTML =
      '<section class="power-play-attract" data-attract><h3>PACIFIC POWER PLAY</h3><p>1993–94 // PACIFIC COLISEUM</p><strong>INSERT COIN / PICK YOUR SKATER</strong><small>TAP A JERSEY TO SWAP<br>WASD / ARROWS SKATE<br>SPACE SHOOT · E ABILITY</small><button type="button" class="pixel-button" data-open-select>Open Roster</button></section><section class="power-play-select" data-select hidden></section><section class="power-play-vs" data-vs hidden></section><section class="power-play-ready" data-pause hidden><h3>BENCH DOOR OPEN</h3><p>Paused between periods.</p><button type="button" class="pixel-button" data-resume>Resume</button></section>';

    screen.append(canvas, ui, overlay);
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.imageSmoothingEnabled = false;

    const $ = (q) => overlay.querySelector(q);
    const els = {
      player: ui.querySelector('[data-player]'),
      goals: ui.querySelector('[data-goals]'),
      shots: ui.querySelector('[data-shots]'),
      signal: ui.querySelector('[data-signal]'),
      ability: ui.querySelector('[data-ability]'),
      call: ui.querySelector('[data-call]'),
      mini: ui.querySelector('[data-mini]'),
      flash: ui.querySelector('[data-flash]'),
      attract: $('[data-attract]'),
      select: $('[data-select]'),
      vs: $('[data-vs]'),
      pause: $('[data-pause]')
    };

    const keys = new Set();
    const S = {
      mode: 'attract',
      w: 640,
      h: 420,
      sel: 0,
      p: PLAYERS[0],
      goals: 0,
      shots: 0,
      signal: 100,
      ability: 100,
      callT: 0,
      flash: 0,
      shake: 0,
      player: { x: 120, y: 210 },
      puck: { x: 140, y: 215, vx: 0, vy: 0, attached: true, trail: [] },
      goalie: { x: 585, y: 210, vy: 105, h: 70 },
      blockers: []
    };

    function pick(i) {
      S.sel = (i + count) % count;
      S.p = PLAYERS[S.sel];
      renderSelect();
    }

    function cards() {
      els.select.innerHTML =
        '<header class="power-play-swap">' +
        '<button type="button" class="power-play-swap__btn" data-prev aria-label="Previous skater">◀</button>' +
        '<p class="power-play-swap__meta"><span data-line></span><small>TAP A NAME TO SWAP LINES</small></p>' +
        '<button type="button" class="power-play-swap__btn" data-next aria-label="Next skater">▶</button>' +
        '</header>' +
        '<div class="power-play-select__hero" data-big></div>' +
        '<nav class="power-play-roster" aria-label="1993-94 Canucks roster">' +
        '<p class="power-play-roster__label">1993–94 ROSTER</p>' +
        PLAYERS.map(
          (skater, i) =>
            '<button type="button" class="power-play-roster__chip" data-i="' +
            i +
            '"><b>#' +
            skater.num +
            '</b><span>' +
            skater.name +
            '</span><i>' +
            skater.pos +
            '</i></button>'
        ).join('') +
        '</nav>' +
        '<div class="power-play-select__grid">' +
        PLAYERS.map((skater, i) => cardMarkup(skater, i, 'button', '')).join('') +
        '</div>' +
        '<aside class="power-play-select__opponent"><b>VS</b><span>STATIC BLOCKERS</span><i>PACIFIC COLISEUM ICE</i></aside>' +
        '<div class="power-play-ready"><span>READY?</span><button type="button" class="pixel-button" data-confirm>Drop The Puck</button></div>';

      els.select.querySelector('[data-prev]').onclick = () => pick(S.sel - 1);
      els.select.querySelector('[data-next]').onclick = () => pick(S.sel + 1);
      els.select.querySelectorAll('.power-play-select__grid [data-i], .power-play-roster [data-i]').forEach((btn) => {
        btn.onclick = () => pick(Number(btn.dataset.i));
        btn.ondblclick = confirm;
      });
      els.select.querySelector('[data-confirm]').onclick = confirm;
      renderSelect();
    }

    function cardMarkup(p, i, tag, extraClass) {
      return (
        '<' +
        tag +
        (tag === 'button' ? ' type="button"' : '') +
        ' class="power-play-card' +
        (extraClass ? ' ' + extraClass : '') +
        '" data-i="' +
        i +
        '"><span class="power-play-card__set">' +
        p.set +
        '</span><span class="power-play-card__portrait" style="--c1:' +
        p.colors[0] +
        ';--c2:' +
        p.colors[1] +
        ';--c3:' +
        p.colors[2] +
        '"><span class="power-play-card__num">' +
        p.num +
        '</span></span><span class="power-play-card__name">' +
        p.name +
        '</span><span class="power-play-card__nick">' +
        p.nick +
        '</span><span class="power-play-card__pos">' +
        p.pos +
        '</span><span class="power-play-card__stats">' +
        p.stats.map((s) => '<em>' + s[0] + '<b style="width:' + s[1] + '%"></b></em>').join('') +
        '</span><span class="power-play-card__ability">' +
        p.ability +
        '</span><span class="power-play-card__note">' +
        p.note +
        '</span></' +
        tag +
        '>'
      );
    }

    function renderSelect() {
      if (!els.select.querySelector('[data-big]')) return;
      const p = PLAYERS[S.sel];
      els.select.querySelectorAll('.power-play-select__grid .power-play-card, .power-play-roster__chip').forEach((b) => {
        const i = Number(b.dataset.i);
        b.classList.toggle('is-selected', i === S.sel);
        b.setAttribute('aria-pressed', i === S.sel ? 'true' : 'false');
        b.tabIndex = i === S.sel ? 0 : -1;
      });
      els.select.querySelector('[data-big]').innerHTML =
        cardMarkup(p, S.sel, 'article', 'is-selected power-play-card--big') + '<span class="power-play-p1">P1</span>';
      const line = els.select.querySelector('[data-line]');
      if (line) line.textContent = '#' + p.num + '  ' + p.name + '  ' + (S.sel + 1) + '/' + count;
      const selectedChip = els.select.querySelector('.power-play-roster__chip.is-selected');
      if (selectedChip) selectedChip.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }

    function bindSwipe(el) {
      let startX = 0;
      el.addEventListener(
        'pointerdown',
        (e) => {
          startX = e.clientX;
        },
        { passive: true }
      );
      el.addEventListener('pointerup', (e) => {
        if (S.mode !== 'characterSelect') return;
        const dx = e.clientX - startX;
        if (Math.abs(dx) < 40) return;
        if (e.target.closest('[data-confirm], .power-play-roster, .power-play-select__grid, [data-prev], [data-next]')) return;
        pick(S.sel + (dx < 0 ? 1 : -1));
      });
    }

    function mode(m) {
      const valid = ['attract', 'characterSelect', 'versus', 'playing', 'goal', 'paused', 'gameOver'];
      if (!valid.includes(m)) m = 'attract';
      S.mode = m;
      stage.dataset.powerPlayState = m;
      stage.dataset.galagaState = m;
      overlay.hidden = m === 'playing' || m === 'goal';
      els.attract.hidden = m !== 'attract';
      els.select.hidden = m !== 'characterSelect';
      els.vs.hidden = m !== 'versus';
      els.pause.hidden = m !== 'paused';
      if (m === 'characterSelect') {
        cards();
        els.select.hidden = false;
      }
      if (m === 'attract' || m === 'characterSelect' || m === 'versus' || m === 'paused') {
        stage.focus({ preventScroll: true });
      }
      updateHud();
    }

    function reset() {
      S.goals = 0;
      S.shots = 0;
      S.signal = 100;
      S.ability = 100;
      S.player.x = 115;
      S.player.y = S.h / 2;
      S.goalie.y = S.h / 2;
      S.blockers = [
        { x: S.w * 0.52, y: S.h * 0.34, r: 14, vx: -45, vy: 42 },
        { x: S.w * 0.68, y: S.h * 0.66, r: 16, vx: -65, vy: -38 },
        { x: S.w * 0.78, y: S.h * 0.45, r: 12, vx: -55, vy: 50 }
      ];
      puck();
      updateHud();
    }

    function puck() {
      S.puck.attached = true;
      S.puck.vx = 0;
      S.puck.vy = 0;
      S.puck.x = S.player.x + 18;
      S.puck.y = S.player.y + 5;
      S.puck.trail = [];
    }

    function confirm() {
      S.p = PLAYERS[S.sel];
      els.vs.innerHTML = '<h3>#' + S.p.num + ' ' + S.p.name + '</h3><b>VS</b><p>STATIC BLOCKERS</p>';
      mode('versus');
      setTimeout(
        () => {
          reset();
          mode('playing');
          stage.focus({ preventScroll: true });
          call('COLISEUM CROWD WAKES UP');
        },
        reduced.matches ? 650 : 1200
      );
    }

    function call(t) {
      els.call.textContent = t || CALLS[(Math.random() * CALLS.length) | 0];
      els.call.hidden = false;
      S.callT = 1.6;
    }

    function shoot() {
      if (S.mode !== 'playing' || !S.puck.attached) return;
      S.puck.attached = false;
      S.shots++;
      S.puck.vx = 430;
      S.puck.vy = (S.player.y - S.h / 2) * S.p.aim;
      call(S.p.id === 'bure' ? 'RUSSIAN ROCKET BREAKAWAY' : S.p.id === 'ronning' ? 'THREAD THE NEEDLE' : 'WEST COAST SNAP');
    }

    function ability() {
      if (S.mode !== 'playing' || S.ability < 100) return;
      S.ability = 0;
      if (S.p.id === 'bure') {
        S.player.x += 90;
        call('ROCKET BURST');
      }
      if (S.p.id === 'linden') {
        S.signal = Math.min(100, S.signal + 20);
        S.puck.vx += 100;
        call("CAPTAIN'S PUSH");
      }
      if (S.p.id === 'mclean') {
        S.blockers.pop();
        S.signal = Math.min(100, S.signal + 15);
        call('THE BIG SAVE');
      }
      if (S.p.id === 'odjick') {
        S.blockers.shift();
        S.shake = reduced.matches ? 0 : 14;
        call('BOARD SHAKE');
      }
      if (S.p.id === 'ronning') {
        if (!S.puck.attached) {
          S.puck.vx += 130;
          S.puck.vy *= 0.25;
        }
        call('THREAD THE NEEDLE');
      }
      if (S.p.id === 'courtnall') {
        S.player.x += 70;
        S.puck.vx += 80;
        call('OT SNAP');
      }
    }

    function update(dt) {
      if (S.mode !== 'playing') return;
      let dx = 0;
      let dy = 0;
      if (keys.has('arrowleft') || keys.has('a')) dx--;
      if (keys.has('arrowright') || keys.has('d')) dx++;
      if (keys.has('arrowup') || keys.has('w')) dy--;
      if (keys.has('arrowdown') || keys.has('s')) dy++;
      const l = Math.hypot(dx, dy) || 1;
      S.player.x = Math.max(45, Math.min(S.w * 0.62, S.player.x + (dx / l) * S.p.speed * dt));
      S.player.y = Math.max(70, Math.min(S.h - 40, S.player.y + (dy / l) * S.p.speed * dt));
      S.goalie.y += S.goalie.vy * dt;
      if (S.goalie.y < 95 || S.goalie.y > S.h - 85) S.goalie.vy *= -1;
      S.blockers.forEach((b) => {
        b.x += b.vx * dt;
        b.y += b.vy * dt;
        if (b.x < S.w * 0.35 || b.x > S.w * 0.83) b.vx *= -1;
        if (b.y < 82 || b.y > S.h - 48) b.vy *= -1;
      });
      if (S.puck.attached) {
        S.puck.x = S.player.x + 18;
        S.puck.y = S.player.y + 5;
      } else {
        S.puck.trail.unshift([S.puck.x, S.puck.y]);
        S.puck.trail = S.puck.trail.slice(0, 8);
        S.puck.x += S.puck.vx * dt;
        S.puck.y += S.puck.vy * dt;
        if (S.p.curve) S.puck.vy += (S.h / 2 - S.puck.y) * dt * 0.55;
        S.puck.vx *= 0.997;
      }
      const hitB = S.blockers.some((b) => Math.hypot(S.puck.x - b.x, S.puck.y - b.y) < b.r + 6);
      const hitG = Math.abs(S.puck.x - S.goalie.x) < 15 && Math.abs(S.puck.y - S.goalie.y) < S.goalie.h / 2;
      if (!S.puck.attached && hitG) {
        S.signal -= 7;
        call('STATIC BLOCKER SAVE');
        puck();
      } else if (!S.puck.attached && hitB) {
        S.signal -= 5;
        call('PUCK LOST IN GRANVILLE FOG');
        puck();
      } else if (!S.puck.attached && S.puck.x > S.w - 38 && Math.abs(S.puck.y - S.h / 2) < 48) {
        S.goals++;
        S.flash = 0.55;
        S.shake = reduced.matches ? 0 : 10;
        els.flash.hidden = false;
        setTimeout(() => (els.flash.hidden = true), 420);
        call('GOAL LAMP GLITCH');
        puck();
      } else if (!S.puck.attached && (S.puck.x > S.w + 20 || S.puck.y < 45 || S.puck.y > S.h + 20)) {
        puck();
      }
      S.signal = Math.max(0, Math.min(100, S.signal - dt * S.p.decay));
      S.ability = Math.min(100, S.ability + dt * 18);
      if (S.callT > 0 && (S.callT -= dt) <= 0) els.call.hidden = true;
      updateHud();
    }

    function updateHud() {
      els.player.textContent = '#' + S.p.num + ' ' + S.p.name;
      els.goals.textContent = S.goals;
      els.shots.textContent = S.shots;
      els.signal.textContent = Math.round(S.signal);
      els.ability.textContent = Math.round(S.ability);
      els.mini.hidden = S.mode === 'attract';
      els.mini.innerHTML = '<b>#' + S.p.num + ' ' + S.p.pos + '</b><span>' + S.p.name + '</span><i>' + S.p.ability + '</i>';
    }

    function draw() {
      const w = S.w;
      const h = S.h;
      const s = S.shake ? (Math.random() - 0.5) * S.shake : 0;
      ctx.clearRect(0, 0, w, h);
      ctx.save();
      ctx.translate(s, 0);
      ctx.fillStyle = T.bg;
      ctx.fillRect(0, 0, w, h);
      ctx.fillStyle = T.bar;
      ctx.fillRect(0, 0, w, 32);
      ctx.fillStyle = T.text;
      ctx.font = '10px monospace';
      ctx.fillText('PACIFIC POWER PLAY   PERIOD 1   ' + T.feed, 18, 21);
      ctx.strokeStyle = T.line;
      ctx.lineWidth = 2;
      ctx.strokeRect(24, 48, w - 48, h - 70);
      ctx.strokeStyle = T.zone;
      [0.33, 0.66].forEach((x) => {
        ctx.beginPath();
        ctx.moveTo(w * x, 48);
        ctx.lineTo(w * x, h - 22);
        ctx.stroke();
      });
      ctx.strokeStyle = T.accent;
      ctx.beginPath();
      ctx.moveTo(w / 2, 48);
      ctx.lineTo(w / 2, h - 22);
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(w / 2, h / 2, 36, 0, 7);
      ctx.stroke();
      ctx.strokeStyle = T.line;
      ctx.strokeRect(w - 38, h / 2 - 50, 20, 100);
      ctx.fillStyle = T.accent;
      ctx.fillRect(w - 34, h / 2 - 65, 12, 10);
      S.puck.trail.forEach((p, i) => {
        ctx.fillStyle = 'rgba(253,184,39,' + (0.8 - i * 0.08) + ')';
        ctx.fillRect(p[0] - 4, p[1] - 2, 8, 4);
      });
      drawSkater(S.player.x, S.player.y, S.p.colors);
      ctx.fillStyle = T.puck;
      ctx.fillRect(S.puck.x - 4, S.puck.y - 3, 8, 6);
      drawGoalie();
      S.blockers.forEach(drawBlocker);
      if (S.flash > 0) {
        ctx.fillStyle = 'rgba(255,240,80,' + S.flash + ')';
        ctx.fillRect(0, 0, w, h);
        S.flash -= 0.035;
      }
      ctx.restore();
    }

    function drawSkater(x, y, c) {
      ctx.fillStyle = c[0];
      ctx.fillRect(x - 8, y - 16, 16, 22);
      ctx.fillStyle = c[1];
      ctx.fillRect(x - 12, y - 7, 24, 6);
      ctx.fillStyle = c[2];
      ctx.fillRect(x - 7, y - 25, 14, 9);
      ctx.fillStyle = '#05070d';
      ctx.fillRect(x - 4, y - 22, 8, 3);
      ctx.fillStyle = c[1];
      ctx.font = '8px monospace';
      ctx.fillText(S.p.num, x - (S.p.num.length > 1 ? 6 : 3), y + 2);
      ctx.strokeStyle = T.text;
      ctx.beginPath();
      ctx.moveTo(x + 10, y - 5);
      ctx.lineTo(x + 28, y + 13);
      ctx.stroke();
      ctx.fillRect(x - 14, y + 12, 13, 3);
      ctx.fillRect(x + 2, y + 12, 16, 3);
    }

    function drawGoalie() {
      ctx.fillStyle = T.goalie;
      ctx.fillRect(S.goalie.x - 8, S.goalie.y - S.goalie.h / 2, 16, S.goalie.h);
      ctx.fillStyle = '#fff';
      ctx.fillRect(S.goalie.x - 13, S.goalie.y - 13, 26, 22);
      ctx.fillStyle = T.bg;
      ctx.fillRect(S.goalie.x - 7, S.goalie.y - 4, 14, 4);
    }

    function drawBlocker(b) {
      ctx.fillStyle = T.blocker;
      ctx.fillRect(b.x - b.r, b.y - b.r, b.r * 2, b.r * 2);
      ctx.fillStyle = T.bg;
      ctx.fillRect(b.x - 6, b.y - 6, 12, 12);
      ctx.fillStyle = T.blockerDot;
      ctx.fillRect(b.x - 2, b.y - b.r - 7, 4, 6);
    }

    function resize() {
      const r = screen.getBoundingClientRect();
      const d = Math.min(devicePixelRatio || 1, 2);
      S.w = Math.max(320, Math.round(r.width));
      S.h = Math.max(300, Math.round(r.height));
      canvas.width = S.w * d;
      canvas.height = S.h * d;
      ctx.setTransform(d, 0, 0, d, 0, 0);
    }

    let last = performance.now();
    function loop(n) {
      const dt = Math.min(0.033, (n - last) / 1000);
      last = n;
      update(dt);
      draw();
      requestAnimationFrame(loop);
    }

    function moveSel(k) {
      if (k === 'arrowright' || k === 'd') pick(S.sel + 1);
      if (k === 'arrowleft' || k === 'a') pick(S.sel - 1);
      if (k === 'arrowdown' || k === 's') pick(S.sel + 1);
      if (k === 'arrowup' || k === 'w') pick(S.sel - 1);
    }

    document.addEventListener('keydown', (e) => {
      const k = e.key.toLowerCase();
      if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright', ' ', 'spacebar', 'w', 'a', 's', 'd', 'escape', 'enter', 'e'].includes(k)) {
        e.preventDefault();
      }
      if (S.mode === 'characterSelect') {
        if (k === 'escape') mode('attract');
        else if (k === 'enter' || k === ' ' || k === 'spacebar') confirm();
        else moveSel(k);
        return;
      }
      if (k === 'escape') {
        mode(S.mode === 'playing' ? 'paused' : S.mode === 'paused' ? 'playing' : 'attract');
        return;
      }
      if (k === 'e') ability();
      else if (k === ' ' || k === 'spacebar') shoot();
      else keys.add(k);
    });
    document.addEventListener('keyup', (e) => keys.delete(e.key.toLowerCase()));
    screen.addEventListener('pointerdown', (e) => {
      if (S.mode !== 'playing') return;
      const r = screen.getBoundingClientRect();
      S.player.x = Math.min(S.w * 0.62, Math.max(45, e.clientX - r.left));
      S.player.y = Math.max(70, Math.min(S.h - 40, e.clientY - r.top));
      shoot();
    });

    $('[data-open-select]').onclick = () => mode('characterSelect');
    $('[data-resume]').onclick = () => mode('playing');
    bindSwipe(els.select);
    starts.forEach((b) =>
      b.addEventListener('click', (e) => {
        e.preventDefault();
        mode('characterSelect');
      })
    );
    addEventListener('resize', resize);
    resize();
    mode(matchMedia('(pointer: coarse)').matches || innerWidth < 900 ? 'characterSelect' : 'attract');
    requestAnimationFrame(loop);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
