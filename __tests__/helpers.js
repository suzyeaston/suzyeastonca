const { TextEncoder, TextDecoder } = require('util');

if (typeof global.TextEncoder === 'undefined') {
  global.TextEncoder = TextEncoder;
}
if (typeof global.TextDecoder === 'undefined') {
  global.TextDecoder = TextDecoder;
}
if (typeof global.matchMedia === 'undefined') {
  global.matchMedia = () => ({
    matches: false,
    addListener() {},
    removeListener() {}
  });
}

function defaultResponse() {
  return {
    ok: true,
    json: () =>
      Promise.resolve({
        providers: [],
        meta: { fetchedAt: new Date().toISOString() }
      })
  };
}

function createClassList(element) {
  const classes = new Set();
  const sync = value => {
    classes.clear();
    (value || '')
      .split(/\s+/)
      .filter(Boolean)
      .forEach(cls => classes.add(cls));
  };

  return {
    _syncFromString: sync,
    add(cls) {
      if (!classes.has(cls)) {
        classes.add(cls);
        element._className = Array.from(classes).join(' ');
      }
    },
    remove(cls) {
      if (classes.has(cls)) {
        classes.delete(cls);
        element._className = Array.from(classes).join(' ');
      }
    },
    toggle(cls, force) {
      if (force === true) {
        this.add(cls);
        return true;
      }
      if (force === false) {
        this.remove(cls);
        return false;
      }
      if (classes.has(cls)) {
        this.remove(cls);
        return false;
      }
      this.add(cls);
      return true;
    },
    contains(cls) {
      return classes.has(cls);
    }
  };
}

class TestElement {
  constructor(tagName, options = {}) {
    this.tagName = tagName.toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.dataset = options.dataset ? { ...options.dataset } : {};
    this._listeners = {};
    this._className = '';
    this.classList = createClassList(this);
    this.textContent = options.textContent || '';
    if (options.id) {
      this.setAttribute('id', options.id);
    }
    if (options.className) {
      this.className = options.className;
    }
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  set className(value) {
    this._className = value || '';
    this.classList._syncFromString(this._className);
  }

  get className() {
    return this._className;
  }

  set textContent(value) {
    this._textContent = String(value || '');
  }

  get textContent() {
    return this._textContent || '';
  }

  setAttribute(name, value) {
    const stringValue = String(value);
    this.attributes[name] = stringValue;
    if (name === 'id') {
      this.id = stringValue;
    }
    if (name === 'class') {
      this.className = stringValue;
    }
    if (name === 'title') {
      this.title = stringValue;
    }
    if (name.startsWith('data-')) {
      const key = name
        .slice(5)
        .replace(/-([a-z])/g, (_, chr) => chr.toUpperCase());
      this.dataset[key] = stringValue;
    }
  }

  removeAttribute(name) {
    delete this.attributes[name];
    if (name === 'id') {
      delete this.id;
    }
    if (name === 'title') {
      delete this.title;
    }
    if (name.startsWith('data-')) {
      const key = name
        .slice(5)
        .replace(/-([a-z])/g, (_, chr) => chr.toUpperCase());
      delete this.dataset[key];
    }
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name)
      ? this.attributes[name]
      : null;
  }

  addEventListener(type, handler) {
    if (!this._listeners[type]) {
      this._listeners[type] = [];
    }
    this._listeners[type].push(handler);
  }

  dispatchEvent(event) {
    const listeners = this._listeners[event.type] || [];
    listeners.forEach(listener => listener.call(this, event));
    return true;
  }

  querySelector(selector) {
    return querySelectorFrom(this, selector);
  }

  querySelectorAll(selector) {
    return querySelectorAllFrom(this, selector);
  }
}

function matches(element, selector) {
  if (!selector) {
    return false;
  }
  if (selector.startsWith('.')) {
    const attrIndex = selector.indexOf('[');
    const className = attrIndex >= 0 ? selector.slice(1, attrIndex) : selector.slice(1);
    const hasClass = element.className.split(/\s+/).includes(className);
    if (!hasClass) {
      return false;
    }
    if (attrIndex >= 0) {
      const attrPart = selector.slice(attrIndex);
      const attrMatch = attrPart.match(/\[data-id="([^"]+)"\]/i);
      if (!attrMatch) {
        return hasClass;
      }
      const value = attrMatch[1];
      return element.dataset.id === value || element.attributes['data-id'] === value;
    }
    return true;
  }
  const attrMatch = selector.match(/^([a-z]+)\[data-id="([^"]+)"\]$/i);
  if (attrMatch) {
    const [, tag, value] = attrMatch;
    return (
      element.tagName === tag.toUpperCase() &&
      (element.dataset.id === value || element.attributes['data-id'] === value)
    );
  }
  if (/^[a-z]+$/i.test(selector)) {
    return element.tagName === selector.toUpperCase();
  }
  return false;
}

function querySelectorFrom(root, selector) {
  if (!selector) {
    return null;
  }
  const parts = selector.trim().split(/\s+/);
  let scope = [root];
  for (const part of parts) {
    let found = null;
    for (const node of scope) {
      found = findInChildren(node, part);
      if (found) {
        break;
      }
    }
    if (!found) {
      return null;
    }
    scope = [found];
  }
  return scope[0] === root ? null : scope[0];
}

function querySelectorAllFrom(root, selector) {
  const results = [];
  if (!selector) {
    return results;
  }
  collectMatches(root, selector, results);
  return results;
}


function findInChildren(node, selector) {
  for (const child of node.children) {
    if (matches(child, selector)) {
      return child;
    }
    const nested = findInChildren(child, selector);
    if (nested) {
      return nested;
    }
  }
  return null;
}

function collectMatches(node, selector, results) {
  for (const child of node.children) {
    if (matches(child, selector)) {
      results.push(child);
    }
    collectMatches(child, selector, results);
  }
}


function buildDashboardDom(providers) {
  const container = new TestElement('div', {
    id: 'lousy-outages',
    className: 'lousy-outages-board'
  });
  const header = container.appendChild(new TestElement('div', { className: 'board-header' }));
  const lastUpdated = header.appendChild(new TestElement('p', { className: 'last-updated' }));
  const span = lastUpdated.appendChild(new TestElement('span'));
  span.setAttribute('data-initial', '');
  const button = header.appendChild(new TestElement('button', { className: 'coin-btn' }));
  button.setAttribute('data-loading-label', 'Refreshing…');
  button.appendChild(new TestElement('span', { className: 'label', textContent: 'Insert coin' }));
  button.appendChild(new TestElement('span', { className: 'loader' }));

  container.appendChild(new TestElement('p', { className: 'board-subtitle', textContent: '' }));

  const grid = container.appendChild(new TestElement('div', { className: 'providers-grid' }));

  providers.forEach(provider => {
    const article = grid.appendChild(new TestElement('article', { className: 'provider-card' }));
    article.setAttribute('data-id', provider.id);
    article.setAttribute('data-name', provider.name);

    const inner = article.appendChild(new TestElement('div', { className: 'provider-card__inner' }));
    const headerRow = inner.appendChild(new TestElement('header', { className: 'provider-card__header' }));
    headerRow.appendChild(new TestElement('h3', { className: 'provider-card__name', textContent: provider.name }));
    const status = headerRow.appendChild(new TestElement('span', {
      className: provider.statusClass || 'status-badge status--operational',
      textContent: provider.statusLabel || 'Operational'
    }));
    status.dataset.status = provider.statusCode || 'operational';

    inner.appendChild(new TestElement('p', {
      className: 'provider-card__summary',
      textContent: provider.message || ''
    }));
    inner.appendChild(new TestElement('p', { className: 'provider-card__snark', textContent: '' }));

    const toggle = inner.appendChild(new TestElement('button', { className: 'details-toggle' }));
    toggle.setAttribute('aria-expanded', 'false');
    toggle.appendChild(new TestElement('span', { className: 'toggle-label', textContent: 'Details' }));

    const details = inner.appendChild(new TestElement('section', { className: 'provider-details' }));
    details.setAttribute('id', 'lo-details-' + provider.id);
    details.setAttribute('hidden', '');

    const incidents = details.appendChild(new TestElement('div', { className: 'incidents' }));
    incidents.appendChild(new TestElement('p', {
      className: 'incident-empty',
      textContent: 'No active incidents. Go write a chorus.'
    }));

    details.appendChild(new TestElement('a', {
      className: 'provider-link',
      textContent: 'View provider status →'
    }));
  });

  container.appendChild(new TestElement('div', { className: 'ticker' }));
  container.appendChild(new TestElement('p', { className: 'microcopy', textContent: '' }));
  container.appendChild(new TestElement('p', { className: 'weather', textContent: '' }));

  const doc = {
    readyState: 'complete',
    getElementById(id) {
      return id === 'lousy-outages' ? container : null;
    },
    querySelector(selector) {
      return container.querySelector(selector);
    },
    querySelectorAll(selector) {
      return container.querySelectorAll(selector);
    },
    createElement(tag) {
      return new TestElement(tag);
    },
    addEventListener() {},
    body: {}
  };

  return { container, document: doc };
}
function installMockFetch() {
  const calls = [];
  const fetchFn = function (...args) {
    calls.push(args);
    return fetchFn.impl(...args);
  };
  fetchFn.impl = () => Promise.resolve(defaultResponse());
  fetchFn.mockImplementation = fn => {
    fetchFn.impl = fn;
  };
  fetchFn.mockClear = () => {
    calls.length = 0;
    fetchFn.impl = () => Promise.resolve(defaultResponse());
  };
  Object.defineProperty(fetchFn, 'calls', {
    get() {
      return calls.slice();
    }
  });
  fetchFn.callCount = () => calls.length;
  global.fetch = fetchFn;
  return fetchFn;
}

module.exports = {
  installMockFetch,
  defaultResponse,
  TestElement,
  buildDashboardDom
};
