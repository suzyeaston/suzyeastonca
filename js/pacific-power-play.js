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

  const LINE_SIZE = 3;
  const KLM = USSR.line.slice(0, 3);

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
    const alpha = opts.dim ? 0.55 : 1;

    ctx.save();
    ctx.globalAlpha = alpha;
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
      'Pacific Power Play. Pick three 1993-94 Canucks for your line, then face the USSR KLM line. Skate with WASD or arrows, shoot with Space, switch skaters with 1/2/3, ability with E, pause with Escape.'
    );

    const canvas = document.createElement('canvas');
    canvas.className = 'hero-galaga-canvas pacific-power-play-canvas';
    canvas.textContent =
      'Pacific Power Play canvas fallback: tap a 1993-94 Canucks roster card, then use WASD or arrows, Space, E, and Escape.';

    const ui = document.createElement('div');
    ui.className = 'hero-galaga-ui pacific-power-play-ui';
    ui.innerHTML =
      '<p class="hero-galaga-scoreline" data-hud-scoreline>CANUCKS <span data-goals>0</span> · SHOTS <span data-shots>0</span> · P1</p><p class="hero-galaga-status" data-hud-player>ON ICE: <span data-player>---</span></p><p class="hero-galaga-wavecall" data-call hidden></p><div class="power-play-goal-flash" data-flash hidden>GOAL!</div>';

    const overlay = document.createElement('div');
    overlay.className = 'hero-galaga-overlay pacific-power-play-overlay';
    overlay.innerHTML =
      '<section class="power-play-attract" data-attract><h3>PACIFIC POWER PLAY</h3><p>1993–94 CANUCKS vs USSR KLM LINE</p><strong>PICK YOUR LINE OF 3</strong><small>KRUTOV · LARIONOV · MAKAROV AWAIT<br>TAP SKATERS TO BUILD YOUR LINE<br>DRAG TO SKATE · TAP TO SHOOT · 1/2/3 SWITCH</small><button type="button" class="pixel-button" data-open-select>Build Your Line</button></section><section class="power-play-select" data-select hidden></section><section class="power-play-vs" data-vs hidden></section><section class="power-play-ready" data-pause hidden><h3>BENCH DOOR OPEN</h3><p>Paused between periods.</p><button type="button" class="pixel-button" data-resume>Resume</button></section>';

    screen.append(canvas, ui, overlay);
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.imageSmoothingEnabled = false;

    const $ = (q) => overlay.querySelector(q);
    const els = {
      player: ui.querySelector('[data-player]'),
      goals: ui.querySelector('[data-goals]'),
      shots: ui.querySelector('[data-shots]'),
      call: ui.querySelector('[data-call]'),
      flash: ui.querySelector('[data-flash]'),
      hudScoreline: ui.querySelector('[data-hud-scoreline]'),
      hudPlayer: ui.querySelector('[data-hud-player]'),
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
      line: [],
      active: 0,
      p: PLAYERS[0],
      goals: 0,
      shots: 0,
      signal: 100,
      ability: 100,
      callT: 0,
      flash: 0,
      shake: 0,
      faceoff: 0,
      goalT: 0,
      player: { x: 120, y: 210 },
      puck: { x: 140, y: 215, vx: 0, vy: 0, attached: true, trail: [] },
      goalie: { x: 585, y: 210, vy: 105, h: 70 },
      blockers: []
    };

    function linePlayers() {
      return S.line.map((i) => PLAYERS[i]);
    }

    function activePlayer() {
      return PLAYERS[S.line[S.active]] || PLAYERS[S.sel];
    }

    function pick(i) {
      S.sel = (i + count) % count;
      S.p = PLAYERS[S.sel];
      renderSelect();
    }

    function toggleLine(i) {
      const slot = S.line.indexOf(i);
      if (slot >= 0) {
        S.line.splice(slot, 1);
        if (S.active >= S.line.length) S.active = Math.max(0, S.line.length - 1);
      } else if (S.line.length < LINE_SIZE) {
        S.line.push(i);
      }
      S.sel = i;
      S.p = PLAYERS[i];
      renderSelect();
    }

    function canConfirm() {
      return S.line.length === LINE_SIZE;
    }

    function switchActive(i) {
      if (S.mode !== 'playing' || S.faceoff > 0 || i < 0 || i >= S.line.length) return;
      S.active = i;
      S.p = activePlayer();
      updateHud();
      call('#' + S.p.num + ' ' + S.p.name + ' ON THE PUCK');
    }

    function cards() {
      els.select.innerHTML =
        '<header class="power-play-swap">' +
        '<button type="button" class="power-play-swap__btn" data-prev aria-label="Previous skater">◀</button>' +
        '<p class="power-play-swap__meta"><span data-line></span><small>TAP TO ADD/REMOVE · LINE <span data-line-count>0</span>/' +
        LINE_SIZE +
        '</small></p>' +
        '<button type="button" class="power-play-swap__btn" data-next aria-label="Next skater">▶</button>' +
        '</header>' +
        '<div class="power-play-select__hero" data-big></div>' +
        '<nav class="power-play-roster" aria-label="1993-94 Canucks roster">' +
        '<p class="power-play-roster__label">BUILD YOUR LINE (' +
        LINE_SIZE +
        ' SKATERS)</p>' +
        '<div class="power-play-line-slots" data-line-slots aria-label="Selected Canucks line"></div>' +
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
        KLM.map(
          (sk) =>
            '<li><b>#' +
            sk.num +
            '</b> ' +
            sk.name +
            ' <i>' +
            sk.pos +
            '</i></li>'
        ).join('') +
        USSR.line.slice(3).map(
          (sk) =>
            '<li class="power-play-opponent-line__d"><b>#' +
            sk.num +
            '</b> ' +
            sk.name +
            ' <i>' +
            sk.pos +
            '</i></li>'
        ).join('') +
        '<li class="power-play-opponent-line__goalie"><b>#' +
        USSR.goalie.num +
        '</b> ' +
        USSR.goalie.name +
        ' <i>G</i></li></ul></aside>' +
        '<div class="power-play-ready"><span data-ready-msg>PICK ' +
        LINE_SIZE +
        ' CANUCKS</span><button type="button" class="pixel-button" data-confirm disabled>Drop The Puck</button></div>';

      els.select.querySelector('[data-prev]').onclick = () => pick(S.sel - 1);
      els.select.querySelector('[data-next]').onclick = () => pick(S.sel + 1);
      els.select.querySelectorAll('.power-play-roster [data-i]').forEach((btn) => {
        btn.onclick = () => toggleLine(Number(btn.dataset.i));
        btn.ondblclick = () => {
          if (canConfirm()) confirm();
        };
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
        const slot = S.line.indexOf(i);
        b.classList.toggle('is-selected', i === S.sel);
        b.classList.toggle('is-in-line', slot >= 0);
        b.setAttribute('aria-pressed', slot >= 0 ? 'true' : 'false');
        let badge = b.querySelector('.power-play-roster__slot');
        if (slot >= 0) {
          if (!badge) {
            badge = document.createElement('em');
            badge.className = 'power-play-roster__slot';
            b.appendChild(badge);
          }
          badge.textContent = 'P' + (slot + 1);
        } else if (badge) {
          badge.remove();
        }
      });
      els.select.querySelector('[data-big]').innerHTML =
        cardMarkup(p, S.sel, 'article', 'is-selected power-play-card--big') +
        (S.line.indexOf(S.sel) >= 0
          ? '<span class="power-play-p1">P' + (S.line.indexOf(S.sel) + 1) + '</span>'
          : '');
      const line = els.select.querySelector('[data-line]');
      if (line) line.textContent = '#' + p.num + '  ' + p.name;
      const lineCount = els.select.querySelector('[data-line-count]');
      if (lineCount) lineCount.textContent = String(S.line.length);
      const slots = els.select.querySelector('[data-line-slots]');
      if (slots) {
        slots.innerHTML = Array.from({ length: LINE_SIZE }, (_, i) => {
          const idx = S.line[i];
          if (idx == null) {
            return '<span class="power-play-line-slots__slot is-empty">P' + (i + 1) + '</span>';
          }
          const sk = PLAYERS[idx];
          return (
            '<span class="power-play-line-slots__slot is-filled" style="--c1:' +
            sk.colors[0] +
            ';--c2:' +
            sk.colors[1] +
            '"><b>P' +
            (i + 1) +
            '</b> #' +
            sk.num +
            ' ' +
            sk.name.split(' ').pop() +
            '</span>'
          );
        }).join('');
      }
      const confirmBtn = els.select.querySelector('[data-confirm]');
      const readyMsg = els.select.querySelector('[data-ready-msg]');
      if (confirmBtn) confirmBtn.disabled = !canConfirm();
      if (readyMsg) {
        readyMsg.textContent = canConfirm()
          ? 'LINE SET · DROP THE PUCK'
          : 'PICK ' + (LINE_SIZE - S.line.length) + ' MORE CANUCK' + (LINE_SIZE - S.line.length === 1 ? '' : 'S');
      }
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
      const prev = S.mode;
      S.mode = m;
      stage.dataset.powerPlayState = m;
      stage.dataset.galagaState = m;
      overlay.hidden = m === 'playing' || m === 'goal';
      els.attract.hidden = m !== 'attract';
      els.select.hidden = m !== 'characterSelect';
      els.vs.hidden = m !== 'versus';
      els.pause.hidden = m !== 'paused';
      if (m === 'characterSelect') {
        if (prev === 'attract') {
          S.line = [];
          S.active = 0;
        }
        cards();
        els.select.hidden = false;
      }
      if (m === 'attract' || m === 'characterSelect' || m === 'versus' || m === 'paused') {
        stage.focus({ preventScroll: true });
      }
      if (m === 'playing') {
        S.goalT = 0;
        els.flash.hidden = true;
      }
      updateHud();
    }

    function reset() {
      S.goals = 0;
      S.shots = 0;
      S.signal = 100;
      S.ability = 100;
      S.goalT = 0;
      S.flash = 0;
      els.flash.hidden = true;
      const cy = S.h / 2;
      S.player.x = S.w * 0.42;
      S.player.y = cy;
      S.goalie.x = S.w - 52;
      S.goalie.y = cy;
      S.blockers = KLM.map((sk, i) => ({
        x: S.w * (0.58 + i * 0.08),
        y: cy + (i - 1) * 52,
        r: 16,
        vx: -35 - i * 6,
        vy: 28 - i * 10,
        num: sk.num,
        name: sk.name
      }));
      S.active = 0;
      S.p = activePlayer();
      S.faceoff = 2.6;
      S.puck.attached = false;
      S.puck.vx = 0;
      S.puck.vy = 0;
      S.puck.x = S.w / 2;
      S.puck.y = cy;
      S.puck.trail = [];
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
      if (!canConfirm()) return;
      S.p = activePlayer();
      const canucks = linePlayers();
      els.vs.innerHTML =
        '<div class="power-play-vs-teams">' +
        '<div class="power-play-vs-side power-play-vs-side--canucks">' +
        '<h4>CANUCKS LINE</h4><ul class="power-play-vs-line">' +
        canucks.map((p) => '<li>#' + p.num + ' ' + p.name + '</li>').join('') +
        '</ul></div>' +
        '<b>VS</b>' +
        '<div class="power-play-vs-side power-play-vs-side--ussr">' +
        '<h4>KLM LINE</h4><ul class="power-play-vs-line power-play-vs-line--soviet">' +
        KLM.map((sk) => '<li>#' + sk.num + ' ' + sk.name + '</li>').join('') +
        '</ul></div></div>' +
        '<small>' +
        USSR.tag +
        ' · #' +
        USSR.goalie.num +
        ' ' +
        USSR.goalie.name +
        ' IN NET</small>';
      mode('versus');
      setTimeout(
        () => {
          reset();
          mode('playing');
          stage.focus({ preventScroll: true });
          call('FACE OFF · CENTER ICE');
        },
        reduced.matches ? 650 : 1200
      );
    }

    function call(t, opts) {
      if (S.goalT > 0 && !(opts && opts.force)) return;
      els.call.textContent = t || CALLS[(Math.random() * CALLS.length) | 0];
      els.call.hidden = false;
      S.callT = 1.6;
    }

    function celebrateGoal() {
      S.goals++;
      S.goalT = 1.1;
      S.flash = 0.55;
      S.shake = reduced.matches ? 0 : 10;
      els.call.hidden = true;
      els.hudScoreline.hidden = true;
      els.hudPlayer.hidden = true;
      els.flash.hidden = false;
      setTimeout(() => {
        els.flash.hidden = true;
        els.hudScoreline.hidden = false;
        els.hudPlayer.hidden = false;
      }, 520);
      puck();
    }

    function shoot() {
      if (S.mode !== 'playing' || S.faceoff > 0 || !S.puck.attached) return;
      S.puck.attached = false;
      S.shots++;
      S.puck.vx = 430;
      S.puck.vy = (S.player.y - S.h / 2) * S.p.aim;
      call(S.p.id === 'bure' ? 'RUSSIAN ROCKET BREAKAWAY' : S.p.id === 'ronning' ? 'THREAD THE NEEDLE' : 'WEST COAST SNAP');
    }

    function ability() {
      if (S.mode !== 'playing' || S.faceoff > 0 || S.ability < 100) return;
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
      if (S.goalT > 0) {
        S.goalT = Math.max(0, S.goalT - dt);
        if (S.flash > 0) S.flash = Math.max(0, S.flash - dt * 1.8);
        return;
      }
      S.p = activePlayer();
      if (S.faceoff > 0) {
        S.faceoff = Math.max(0, S.faceoff - dt);
        if (S.faceoff <= 0) {
          puck();
          call('PUCK IS LIVE');
        } else if (S.faceoff <= 1.2) {
          els.call.textContent = 'DROP THE PUCK';
          els.call.hidden = false;
        }
        S.signal = Math.max(0, Math.min(100, S.signal - dt * S.p.decay * 0.15));
        S.ability = Math.min(100, S.ability + dt * 18);
        if (S.callT > 0 && (S.callT -= dt) <= 0 && S.faceoff > 1.2) els.call.hidden = true;
        updateHud();
        return;
      }
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
        celebrateGoal();
      } else if (!S.puck.attached && (S.puck.x > S.w + 20 || S.puck.y < 45 || S.puck.y > S.h + 20)) {
        puck();
      }
      S.signal = Math.max(0, Math.min(100, S.signal - dt * S.p.decay));
      S.ability = Math.min(100, S.ability + dt * 18);
      if (S.callT > 0 && (S.callT -= dt) <= 0) els.call.hidden = true;
      updateHud();
    }

    function updateHud() {
      S.p = activePlayer();
      const lineLabel = linePlayers()
        .map((p, i) => (i === S.active ? p.num : p.num))
        .join(' · ');
      els.player.textContent = '#' + S.p.num + ' ' + S.p.name.split(' ').pop();
      els.goals.textContent = S.goals;
      els.shots.textContent = S.shots;
      if (S.mode === 'playing' && S.faceoff > 0) {
        els.hudPlayer.textContent = 'FACE OFF · ' + lineLabel;
      } else if (S.mode === 'playing') {
        els.hudPlayer.textContent = 'ON ICE: ' + lineLabel + ' · #' + S.p.num;
      } else {
        els.hudPlayer.textContent = 'ON ICE: ' + (lineLabel || '---');
      }
    }

    function teammatePos(i) {
      const cy = S.h / 2;
      const offsets = [
        [0, 0],
        [-36, -58],
        [-36, 58]
      ];
      const off = offsets[i] || [-24, 0];
      return {
        x: Math.max(45, S.player.x + off[0]),
        y: Math.max(70, Math.min(S.h - 40, i === 0 ? S.player.y : cy + off[1]))
      };
    }

    function drawCanucksLine() {
      linePlayers().forEach((p, i) => {
        const isActive = i === S.active;
        const pos = isActive ? S.player : teammatePos(i);
        drawHockeyPlayer(pos.x, pos.y, {
          colors: p.colors,
          num: p.num,
          facing: 1,
          scale: isActive ? 1 : 0.9,
          dim: !isActive
        });
      });
    }

    function draw() {
      if (S.mode !== 'playing' && S.mode !== 'goal') {
        ctx.clearRect(0, 0, S.w, S.h);
        return;
      }
      const w = S.w;
      const h = S.h;
      const s = S.shake ? (Math.random() - 0.5) * S.shake : 0;
      ctx.clearRect(0, 0, w, h);
      ctx.save();
      ctx.translate(s, 0);
      ctx.fillStyle = T.bg;
      ctx.fillRect(0, 0, w, h);
      const rinkTop = 36;
      const rinkBottom = h - 18;
      const rinkLeft = 24;
      const rinkRight = w - 24;
      ctx.fillStyle = '#101820';
      ctx.fillRect(rinkLeft, rinkTop, rinkRight - rinkLeft, rinkBottom - rinkTop);
      ctx.strokeStyle = T.line;
      ctx.lineWidth = 2;
      ctx.strokeRect(rinkLeft, rinkTop, rinkRight - rinkLeft, rinkBottom - rinkTop);
      ctx.strokeStyle = T.zone;
      [0.33, 0.66].forEach((x) => {
        ctx.beginPath();
        ctx.moveTo(w * x, rinkTop);
        ctx.lineTo(w * x, rinkBottom);
        ctx.stroke();
      });
      ctx.strokeStyle = T.accent;
      ctx.beginPath();
      ctx.moveTo(w / 2, rinkTop);
      ctx.lineTo(w / 2, rinkBottom);
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(w / 2, h / 2, 36, 0, 7);
      ctx.stroke();
      ctx.fillStyle = T.accent;
      ctx.beginPath();
      ctx.arc(w / 2, h / 2, 5, 0, 7);
      ctx.fill();
      ctx.strokeStyle = T.line;
      ctx.strokeRect(w - 38, h / 2 - 50, 20, 100);
      ctx.fillStyle = T.accent;
      ctx.fillRect(w - 34, h / 2 - 65, 12, 10);
      ctx.strokeStyle = 'rgba(245,240,230,.25)';
      ctx.strokeRect(rinkLeft + 8, rinkTop + 8, rinkRight - rinkLeft - 16, 18);
      ctx.fillStyle = T.text;
      ctx.font = '9px monospace';
      ctx.textAlign = 'left';
      ctx.fillText('CANUCKS ' + S.goals + '  ·  SHOTS ' + S.shots + '  ·  PERIOD 1', rinkLeft + 14, rinkTop + 20);
      ctx.textAlign = 'right';
      ctx.fillStyle = '#ffb4bc';
      ctx.fillText('USSR KLM  ·  #' + USSR.goalie.num + ' ' + USSR.goalie.name, rinkRight - 14, rinkTop + 20);
      if (S.faceoff > 0) {
        ctx.textAlign = 'center';
        ctx.fillStyle = 'rgba(253,184,39,.92)';
        ctx.font = 'bold 11px monospace';
        ctx.fillText(S.faceoff > 1.2 ? 'FACE OFF' : 'DROP THE PUCK', w / 2, h / 2 - 52);
      }
      S.puck.trail.forEach((p, i) => {
        ctx.fillStyle = 'rgba(253,184,39,' + (0.8 - i * 0.08) + ')';
        ctx.fillRect(p[0] - 4, p[1] - 2, 8, 4);
      });
      drawCanucksLine();
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
      const nw = Math.max(320, Math.round(r.width));
      const nh = Math.max(300, Math.round(r.height));
      if (S.w && S.h && S.mode === 'playing') {
        const sx = nw / S.w;
        const sy = nh / S.h;
        S.player.x *= sx;
        S.player.y *= sy;
        S.puck.x *= sx;
        S.puck.y *= sy;
        S.goalie.x = nw - 52;
        S.goalie.y *= sy;
        S.blockers.forEach((b) => {
          b.x *= sx;
          b.y *= sy;
        });
      }
      S.w = nw;
      S.h = nh;
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
      if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright', ' ', 'spacebar', 'w', 'a', 's', 'd', 'escape', 'enter', 'e', '1', '2', '3'].includes(k)) {
        e.preventDefault();
      }
      if (S.mode === 'characterSelect') {
        if (k === 'escape') mode('attract');
        else if ((k === 'enter' || k === ' ' || k === 'spacebar') && canConfirm()) confirm();
        else moveSel(k);
        return;
      }
      if (S.mode === 'playing' && (k === '1' || k === '2' || k === '3')) {
        switchActive(Number(k) - 1);
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

    let pointerActive = false;
    let pointerMoved = false;
    let pointerStartX = 0;
    let pointerStartY = 0;

    screen.addEventListener('pointerdown', (e) => {
      if (S.mode !== 'playing' || S.faceoff > 0 || S.goalT > 0) return;
      pointerActive = true;
      pointerMoved = false;
      pointerStartX = e.clientX;
      pointerStartY = e.clientY;
      const r = screen.getBoundingClientRect();
      const relX = (e.clientX - r.left) / r.width;
      if (relX < 0.22) switchActive(0);
      else if (relX > 0.78) switchActive(2);
      else if (relX > 0.4 && relX < 0.6 && S.line.length > 1) switchActive(1);
      S.player.x = Math.min(S.w * 0.62, Math.max(45, e.clientX - r.left));
      S.player.y = Math.max(70, Math.min(S.h - 40, e.clientY - r.top));
      screen.setPointerCapture(e.pointerId);
    });
    screen.addEventListener('pointermove', (e) => {
      if (!pointerActive || S.mode !== 'playing' || S.faceoff > 0 || S.goalT > 0) return;
      if (Math.hypot(e.clientX - pointerStartX, e.clientY - pointerStartY) > 8) pointerMoved = true;
      const r = screen.getBoundingClientRect();
      S.player.x = Math.min(S.w * 0.62, Math.max(45, e.clientX - r.left));
      S.player.y = Math.max(70, Math.min(S.h - 40, e.clientY - r.top));
    });
    screen.addEventListener('pointerup', (e) => {
      if (!pointerActive || S.mode !== 'playing' || S.faceoff > 0 || S.goalT > 0) return;
      pointerActive = false;
      if (!pointerMoved) shoot();
      try {
        screen.releasePointerCapture(e.pointerId);
      } catch (_) {}
    });
    screen.addEventListener('pointercancel', () => {
      pointerActive = false;
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
