const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class ClassList {
  constructor(el) {
    this.el = el;
    this.set = new Set((el.className || '').split(/\s+/).filter(Boolean));
  }
  add(c) { this.set.add(c); this.el.className = [...this.set].join(' '); }
  remove(c) { this.set.delete(c); this.el.className = [...this.set].join(' '); }
  contains(c) { return this.set.has(c); }
}

class El {
  constructor(tag = 'div') {
    this.tagName = tag;
    this.children = [];
    this.attributes = {};
    this.dataset = {};
    this._className = '';
    this.textContent = '';
    this.parent = null;
    this.classList = new ClassList(this);
  }
  set className(v) { this._className = String(v); this.classList = new ClassList(this); }
  get className() { return this._className; }
  append(...els) { els.forEach((e) => this.appendChild(e)); }
  appendChild(e) { e.parent = this; this.children.push(e); return e; }
  setAttribute(k, v) {
    this.attributes[k] = String(v);
    if (k === 'id') this.id = String(v);
    if (k === 'class') { this.className = String(v); this.classList = new ClassList(this); }
    if (k === 'hidden') this.hidden = true;
  }
  removeAttribute(k) { delete this.attributes[k]; if (k === 'hidden') this.hidden = false; }
  getAttribute(k) { return this.attributes[k]; }
  hasAttribute(k) { return k in this.attributes; }
  querySelector(sel) {
    const all = this.querySelectorAll(sel);
    return all[0] || null;
  }
  querySelectorAll(sel) {
    const out = [];
    const attrOnly = sel.match(/^\[([^=\]]+)\]$/);
    const attrMatch = sel.match(/^(\w+)?\[([^=]+)="([^"]+)"\](?:\s+(\w+))?$/);
    const walk = (e) => {
      let matched = false;
      if (attrOnly) {
        matched = e.hasAttribute(attrOnly[1]);
      } else if (attrMatch) {
        const tag = attrMatch[1];
        const attr = attrMatch[2];
        const value = attrMatch[3];
        const childTag = attrMatch[4];
        if ((!tag || e.tagName === tag) && e.getAttribute(attr) === value) {
          if (childTag) {
            e.children.forEach((child) => {
              if (child.tagName === childTag) out.push(child);
            });
            return;
          }
          matched = true;
        }
      } else {
        const match = (node) => sel.startsWith('.') ? node.classList.contains(sel.slice(1)) : sel.startsWith('#') ? node.id === sel.slice(1) : node.tagName === sel;
        matched = match(e);
      }
      if (matched) out.push(e);
      e.children.forEach(walk);
    };
    this.children.forEach(walk);
    return out;
  }
}

function makeContainer() {
  const c = new El('section');
  c.setAttribute('id', 'lousy-outages-teaser');
  c.className = 'lo-home-teaser lo-home-teaser--warn';
  c.dataset.loEndpoint = 'https://example.test/wp-json/lousy-outages/v1/summary';
  c.dataset.loRefreshInterval = '60000';
  const screen = new El('div');
  screen.className = 'lo-home-teaser__screen';
  ['down', 'degraded', 'advisory', 'up'].forEach((key) => {
    const stat = new El('a');
    stat.setAttribute('data-lo-stat', key);
    const strong = new El('strong');
    strong.textContent = '00';
    stat.append(strong);
    screen.append(stat);
  });
  const lead = new El('div');
  lead.setAttribute('data-lo-lead', '');
  const chip = new El('span');
  chip.setAttribute('data-lo-lead-label', '');
  const title = new El('strong');
  title.setAttribute('data-lo-lead-title', '');
  const summary = new El('span');
  summary.setAttribute('data-lo-lead-summary', '');
  const provider = new El('span');
  provider.setAttribute('data-lo-lead-provider', '');
  lead.append(chip, title, summary, provider);
  screen.append(lead);
  const also = new El('ul');
  also.setAttribute('data-lo-also', '');
  also.setAttribute('hidden', 'hidden');
  screen.append(also);
  const sync = new El('p');
  sync.setAttribute('data-lo-sync', '');
  screen.append(sync);
  const verdict = new El('p');
  verdict.setAttribute('data-lo-verdict-line', '');
  const verdictSub = new El('p');
  verdictSub.setAttribute('data-lo-verdict-sub', '');
  c.append(verdict, verdictSub, screen);
  return c;
}

function load({ fetchImpl = () => Promise.resolve({ ok: true, json: () => Promise.resolve({ teaser: sampleTeaser() }) }) } = {}) {
  const container = makeContainer();
  const doc = {
    hidden: false,
    createElement: (t) => new El(t),
    addEventListener: () => {},
    getElementById: (id) => id === 'lousy-outages-teaser' ? container : null
  };
  const sandbox = {
    window: {},
    document: doc,
    location: { href: 'https://example.test/' },
    console,
    URL,
    Date,
    fetch: fetchImpl,
    setInterval: () => 1,
    clearInterval: () => {}
  };
  sandbox.window = sandbox;
  vm.runInNewContext(fs.readFileSync('assets/js/lousy-outages-teaser.js', 'utf8'), sandbox);
  return { teaser: sandbox.window.LousyOutagesTeaser, container };
}

function sampleTeaser() {
  return {
    tone: 'warn',
    verdict_line: 'DEGRADED',
    verdict_sub: '1 provider flagged trouble without filing an incident.',
    counts: { down: 0, degraded: 1, advisory: 1, up: 17, tracked: 19 },
    lead: {
      kind: 'warn',
      label: 'DEGRADED',
      title: 'Cloudflare — yellow status, no incident filed',
      summary: '46 components not operational.',
      provider: 'Cloudflare',
      provider_id: 'cloudflare',
      url: 'https://cloudflarestatus.com',
      section_url: '/lousy-outages/#degraded'
    },
    also: [{ label: 'AWS', title: 'Bahrain advisory', tone: 'advisory', url: '/lousy-outages/#advisories' }],
    fetched_label: '9m ago',
    delayed: false,
    urls: { active: '/lousy-outages/#active', degraded: '/lousy-outages/#degraded', advisories: '/lousy-outages/#advisories', matrix: '/lousy-outages/#providers', full: '/lousy-outages/' }
  };
}

function textOf(el) {
  if (!el) return '';
  return [el.textContent].concat(el.children.map((c) => textOf(c))).join(' ');
}

test('render uses teaser payload counts and lead', () => {
  const { teaser, container } = load();
  teaser.render(container, { teaser: sampleTeaser() }, { dashboardUrl: '/lousy-outages/' });
  const text = textOf(container);
  assert.match(text, /\b00\b/);
  assert.match(text, /\b01\b/);
  assert.match(text, /\b17\b/);
  assert.ok(container.classList.contains('lo-home-teaser--warn'));
});

test('init fetches summary endpoint', async () => {
  const urls = [];
  const { teaser, container } = load({
    fetchImpl: (u) => {
      urls.push(u);
      return Promise.resolve({ ok: true, json: () => Promise.resolve({ teaser: sampleTeaser() }) });
    }
  });
  teaser.init(container);
  await new Promise((r) => setImmediate(r));
  assert.match(urls[0], /\/summary/);
  assert.match(textOf(container), /01/);
});
