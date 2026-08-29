const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const { normalizeFilter } = require('../js/ai-art.js');
const {
  validateArtwork,
  validateBundle,
  previewDiff
} = require('../scripts/import-ai-art-bundle.js');

const ROOT = path.join(__dirname, '..');

test('ai-art catalog ships with empty published works and gallery copy', () => {
  const catalog = JSON.parse(
    fs.readFileSync(path.join(ROOT, 'assets/data/ai-art/works.json'), 'utf8')
  );
  assert.equal(catalog.gallery.publicTitle, 'MACHINE VISIONS');
  assert.equal(catalog.gallery.appName, 'Moving Picture Machine');
  assert.equal(catalog.gallery.route, '/ai-art/');
  assert.equal(catalog.gallery.emptyState, 'FIRST TRANSMISSION IN RENDER QUEUE');
  assert.ok(Array.isArray(catalog.works));
  assert.equal(catalog.works.length, 0);
});

test('publish bundle schema exists and declares schemaVersion 1.0.0', () => {
  const schema = JSON.parse(
    fs.readFileSync(path.join(ROOT, 'schemas/ai-art-publish-bundle.schema.json'), 'utf8')
  );
  assert.equal(schema.properties.schemaVersion.const, '1.0.0');
  assert.ok(schema.properties.artwork);
});

test('draft fixtures are not inside the published catalog', () => {
  const catalog = JSON.parse(
    fs.readFileSync(path.join(ROOT, 'assets/data/ai-art/works.json'), 'utf8')
  );
  const draft = JSON.parse(
    fs.readFileSync(
      path.join(ROOT, 'assets/data/ai-art/drafts/fixture-signal-study.json'),
      'utf8'
    )
  );
  assert.equal(draft.status, 'draft');
  assert.equal(
    catalog.works.some((w) => w.slug === draft.slug),
    false
  );
});

test('validateArtwork accepts a minimal valid still', () => {
  const errors = validateArtwork({
    id: 'w1',
    slug: 'night-desk',
    status: 'published',
    title: 'Night Desk',
    date: '2026-08-01',
    kind: 'still',
    description: 'Test',
    alt: 'A dark desk with green signal lamps',
    src: 'night-desk/primary.webp',
    thumbnailSrc: 'night-desk/thumb.webp',
    width: 1280,
    height: 720,
    tags: ['still'],
    tools: [{ name: 'MockProvider' }],
    humanContribution: ['prompt', 'edit'],
    featured: false,
    sortOrder: 1
  });
  assert.deepEqual(errors, []);
});

test('validateArtwork rejects secrets and missing alt', () => {
  const errors = validateArtwork({
    id: 'w1',
    slug: 'bad',
    status: 'published',
    title: 'Bad',
    date: '2026-08-01',
    kind: 'still',
    description: 'Test',
    alt: '',
    src: '/Users/suzy/secret.png',
    thumbnailSrc: 'x.png',
    width: 1,
    height: 1,
    tags: [],
    tools: [],
    humanContribution: ['edit'],
    featured: false,
    sortOrder: 1,
    note: 'api_key=sk-test'
  });
  assert.ok(errors.some((e) => /alt/i.test(e)));
  assert.ok(errors.some((e) => /private paths or secrets/i.test(e)));
});

test('normalizeFilter falls back to all', () => {
  assert.equal(normalizeFilter('film'), 'film');
  assert.equal(normalizeFilter('NOPE'), 'all');
});

test('page template and assets exist', () => {
  assert.ok(fs.existsSync(path.join(ROOT, 'page-ai-art.php')));
  assert.ok(fs.existsSync(path.join(ROOT, 'inc/ai-art.php')));
  assert.ok(fs.existsSync(path.join(ROOT, 'assets/css/ai-art.css')));
  assert.ok(fs.existsSync(path.join(ROOT, 'js/ai-art.js')));
  assert.ok(fs.existsSync(path.join(ROOT, 'docs/ai-art.md')));
});

test('previewDiff reports create vs update', () => {
  const artwork = { id: 'a', slug: 'a', status: 'published', title: 'A' };
  assert.equal(previewDiff({ works: [] }, artwork).action, 'create');
  assert.equal(
    previewDiff({ works: [{ id: 'a', slug: 'a', status: 'draft', title: 'Old' }] }, artwork)
      .action,
    'update'
  );
});

test('validateBundle requires manifest artwork and schema version', () => {
  const tmp = fs.mkdtempSync(path.join(require('os').tmpdir(), 'ai-art-bundle-'));
  fs.writeFileSync(
    path.join(tmp, 'manifest.json'),
    JSON.stringify({ schemaVersion: '0.0.0', artwork: {} })
  );
  const errors = validateBundle(tmp, {
    schemaVersion: '0.0.0',
    artwork: {}
  });
  assert.ok(errors.length > 0);
});
