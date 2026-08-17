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

  const USSR = {
    name: 'USSR RED ARMY',
    tag: '1987 RENDEZ-VOUS · KLM LINE',
    colors: ['#c8102e', '#ffffff', '#ffd700'],
    goalie: { name: 'TRETIAK', num: '20' },
    line: [
      { name: 'KRUTOV', num: '9', pos: 'LW' },
      { name: 'LARIONOV', num: '11', pos: 'C' },
      { name: 'MAKAROV', num: '24', pos: 'RW' },
      { name: 'FETISOV', num: '2', pos: 'D' },
      { name: 'KASATONOV', num: '5', pos: 'D' }
    ]
  };

  const CALLS = [
    'COLISEUM CROWD WAKES UP',
    'RUSSIAN ROCKET BREAKAWAY',
    'THE SAVE',
    'CAPTAIN TO THE NET',
    'OT HERO',
    'BOARD SHAKE',
    'HASTINGS PARK NOISE',
    'POWER PLAY HOT',
    'KLM LINE ON THE ICE',
    'LARIONOV THREADS THE NEEDLE',
    'GREEN UNIT CLOSES THE LANE',
    'TRETIAK STONEWALLS',
    'CCCP COUNTERATTACK',
    'RENDEZ-VOUS RINK HEATS UP'
  ];

  const T = {
    bg: '#0c0c0c',
    bar: '#141414',
    text: '#f5f0e6',
    line: '#fdb827',
    zone: 'rgba(253,184,39,.55)',
    accent: '#e03a3e',
    puck: '#fdb827',
    feed: 'CANUCKS vs USSR · PACIFIC COLISEUM',
    soviet: USSR.colors
  };

  function drawHockeyPlayer(x, y, opts) {
    const colors = opts.colors || ['#0a0a0a', '#fdb827', '#e03a3e'];
    const facing = opts.facing == null ? 1 : opts.facing;
    const scale = opts.scale || 1;
    const soviet = !!opts.soviet;
    const goalie = !!opts.goalie;
    const num = opts.num || '';
    const bodyY = goalie ? -10 : -16;

    ctx.save();
    ctx.translate(x, y);
    ctx.scale(facing * scale, scale);

    ctx.fillStyle = '#111';
    ctx.fillRect(-11, 15, 9, 3);
    ctx.fillRect(2, 15, 9, 3);
    ctx.fillStyle = '#9aa3ad';
    ctx.fillRect(-10, 17, 7, 2);
    ctx.fillRect(3, 17, 7, 2);

    ctx.fillStyle = colors[0];
    ctx.fillRect(-9, 5, 7, 12);
    ctx.fillRect(2, 5, 7, 12);

    if (goalie) {
      ctx.fillStyle = colors[0];
      ctx.fillRect(-14, bodyY + 2, 28, 24);
      ctx.fillStyle = colors[1];
      ctx.fillRect(-16, bodyY + 8, 32, 6);
      ctx.fillRect(-18, bodyY + 14, 8, 14);
      ctx.fillRect(10, bodyY + 14, 8, 14);
      ctx.fillStyle = colors[2];
      ctx.fillRect(-20, bodyY + 20, 10, 8);
      ctx.fillRect(10, bodyY + 20, 10, 8);
    } else {
      ctx.fillRect(-10, bodyY + 6, 20, 18);
      ctx.fillStyle = colors[1];
      ctx.fillRect(-12, bodyY + 10, 24, 5);
      ctx.fillRect(-14, bodyY + 4, 6, 8);
      ctx.fillRect(8, bodyY + 4, 6, 8);
      ctx.fillStyle = colors[0];
      ctx.fillRect(-16, bodyY + 8, 5, 12);
      ctx.fillRect(11, bodyY + 6, 5, 14);
      ctx.fillStyle = colors[2];
      ctx.fillRect(-18, bodyY + 18, 6, 5);
      ctx.fillRect(12, bodyY + 18, 6, 5);
    }

    ctx.strokeStyle = '#7a5c18';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(goalie ? -16 : 14, bodyY + (goalie ? 22 : 20));
    ctx.lineTo(goalie ? -30 : 30, bodyY + (goalie ? 34 : 32));
    ctx.stroke();
    ctx.fillStyle = '#7a5c18';
    ctx.fillRect(goalie ? -32 : 28, bodyY + (goalie ? 32 : 30), 4, 8);

    ctx.fillStyle = colors[2];
    ctx.fillRect(-8, bodyY - 10, 16, 12);
    ctx.fillStyle = '#05070d';
    ctx.fillRect(-6, bodyY - 6, 12, 4);
    ctx.fillStyle = colors[1];
    ctx.fillRect(-8, bodyY - 10, 16, 3);

    if (num) {
      ctx.fillStyle = colors[1];
      ctx.font = (goalie ? '7px' : '8px') + ' monospace';
      ctx.textAlign = 'center';
      ctx.fillText(num, 0, bodyY + (goalie ? 20 : 22));
    }
    if (soviet) {
      ctx.fillStyle = '#fff';
      ctx.font = '5px monospace';
      ctx.textAlign = 'center';
      ctx.fillText('CCCP', 0, bodyY + (goalie ? 14 : 16));
    }

    ctx.restore();
  }

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
      '<section class="power-play-attract" data-attract><h3>PACIFIC POWER PLAY</h3><p>1993–94 CANUCKS vs USSR RED ARMY</p><strong>INSERT COIN / PICK YOUR SKATER</strong><small>KLM LINE AWAITS · LARIONOV #11<br>CLICK OR TAP A SKATER<br>ARROWS SWAP · ENTER LOCKS IN<br>SPACE SHOOT · E ABILITY</small><button type="button" class="pixel-button" data-open-select>Select Skater</button></section><section class="power-play-select" data-select hidden></section><section class="power-play-vs" data-vs hidden></section><section class="power-play-ready" data-pause hidden><h3>BENCH DOOR OPEN</h3><p>Paused between periods.</p><button type="button" class="pixel-button" data-resume>Resume</button></section>';

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
        '<p class="power-play-swap__meta"><span data-line></span><small>CLICK OR TAP A SKATER</small></p>' +
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
        '<aside class="power-play-select__opponent" aria-label="Opponent lineup">' +
        '<span>OPPONENT</span><b>VS</b><p>' +
        USSR.name +
        '</p><i>' +
        USSR.tag +
        '</i><ul class="power-play-opponent-line">' +
        USSR.line
          .map(
            (sk) =>
              '<li><b>#' +
              sk.num +
              '</b> ' +
              sk.name +
              ' <i>' +
              sk.pos +
              '</i></li>'
          )
          .join('') +
        '<li class="power-play-opponent-line__goalie"><b>#' +
        USSR.goalie.num +
        '</b> ' +
        USSR.goalie.name +
        ' <i>G</i></li></ul></aside>' +
        '<div class="power-play-ready"><span>READY?</span><button type="button" class="pixel-button" data-confirm>Drop The Puck</button></div>';

      els.select.querySelector('[data-prev]').onclick = () => pick(S.sel - 1);
      els.select.querySelector('[data-next]').onclick = () => pick(S.sel + 1);
      els.select.querySelectorAll('.power-play-roster [data-i]').forEach((btn) => {
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
        '"><i aria-hidden="true"></i><span class="power-play-card__num">' +
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
      els.select.querySelectorAll('.power-play-roster__chip').forEach((b) => {
        const i = Number(b.dataset.i);
        b.classList.toggle('is-selected', i === S.sel);
        b.setAttribute('aria-pressed', i === S.sel ? 'true' : 'false');
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
        if (e.target.closest('[data-confirm], .power-play-roster, [data-prev], [data-next]')) return;
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
      S.blockers = USSR.line.slice(0, 3).map((sk, i) => ({
        x: S.w * (0.52 + i * 0.1),
        y: S.h * (0.34 + i * 0.14),
        r: 16,
        vx: -45 - i * 8,
        vy: 42 - i * 12,
        num: sk.num,
        name: sk.name
      }));
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
      els.vs.innerHTML =
        '<h3>#' +
        S.p.num +
        ' ' +
        S.p.name +
        '</h3><b>VS</b><p>' +
        USSR.name +
        '</p><small>' +
        USSR.tag +
        '</small><ul class="power-play-vs-line">' +
        USSR.line
          .map((sk) => '<li>#' + sk.num + ' ' + sk.name + '</li>')
          .join('') +
        '</ul>';
      mode('versus');
      setTimeout(
        () => {
          reset();
          mode('playing');
          stage.focus({ preventScroll: true });
          call('COLISEUM CROWD WAKES UP · USSR LINE ON ICE');
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
        call('TRETIAK STONEWALLS');
        puck();
      } else if (!S.puck.attached && hitB) {
        S.signal -= 5;
        call('GREEN UNIT CLOSES THE LANE');
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
      drawHockeyPlayer(S.player.x, S.player.y, { colors: S.p.colors, num: S.p.num, facing: 1 });
      ctx.fillStyle = T.puck;
      ctx.fillRect(S.puck.x - 4, S.puck.y - 3, 8, 6);
      drawHockeyPlayer(S.goalie.x, S.goalie.y, {
        colors: T.soviet,
        num: USSR.goalie.num,
        facing: -1,
        soviet: true,
        goalie: true,
        scale: 1.15
      });
      S.blockers.forEach(drawBlocker);
      if (S.flash > 0) {
        ctx.fillStyle = 'rgba(255,240,80,' + S.flash + ')';
        ctx.fillRect(0, 0, w, h);
        S.flash -= 0.035;
      }
      ctx.restore();
    }

    function drawBlocker(b) {
      drawHockeyPlayer(b.x, b.y, {
        colors: T.soviet,
        num: b.num,
        facing: -1,
        soviet: true,
        scale: 0.92
      });
      ctx.fillStyle = T.text;
      ctx.font = '5px monospace';
      ctx.textAlign = 'center';
      ctx.fillText(b.name.split(' ')[0], b.x, b.y - 28);
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
