const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const header = fs.readFileSync('header.php', 'utf8');
const seo = fs.readFileSync('inc/seo.php', 'utf8');
const shop = fs.readFileSync('inc/shop.php', 'utf8');
const workWithSuzy = fs.readFileSync('page-work-with-suzy.php', 'utf8');
const hireStrip = fs.readFileSync('parts/home-hire-strip.php', 'utf8');
const analytics = fs.readFileSync('assets/js/seo-analytics.js', 'utf8');
const contactModal = fs.readFileSync('assets/js/header-contact-modal.js', 'utf8');
const functions = fs.readFileSync('functions.php', 'utf8');

test('header delegates meta and structured data to inc/seo.php', () => {
  assert.match(header, /se_page_meta\(\)/);
  assert.match(header, /se_page_structured_data\(\)/);
  assert.doesNotMatch(header, /<title>.*AI Strategist, Musician/s);
});

test('commercial pages have dedicated SEO titles and descriptions', () => {
  assert.match(seo, /Suzy Easton \| AI, Automation & Creative Technology Consultant in Vancouver/);
  assert.match(seo, /Hire Suzy Easton \| AI & Automation Consultant in Vancouver, BC/);
  assert.match(shop, /Technical Consulting Sessions in Vancouver \| Suzy Easton/);
});

test('structured data includes Person and WebSite without street address', () => {
  assert.match(seo, /'@type'\s*=>\s*'Person'/);
  assert.match(seo, /'@type'\s*=>\s*'WebSite'/);
  assert.match(seo, /homeLocation/);
  assert.match(seo, /East Vancouver/);
  assert.doesNotMatch(seo, /streetAddress/);
  assert.doesNotMatch(seo, /LocalBusiness/);
});

test('work with suzy page surfaces Vancouver location and availability', () => {
  assert.match(workWithSuzy, /East Vancouver/);
  assert.match(workWithSuzy, /available/i);
  assert.match(workWithSuzy, /<h1[^>]*id="work-with-suzy-title"/);
  assert.match(hireStrip, /Based in East Vancouver/);
});

test('conversion analytics events are wired', () => {
  assert.match(analytics, /hire_cta_click/);
  assert.match(shop, /shop_view/);
  assert.match(shop, /consulting_product_view/);
  assert.match(shop, /checkout_click/);
  assert.match(seo, /work_with_suzy_view/);
  assert.match(contactModal, /contact_open/);
  assert.match(contactModal, /contact_submit/);
});

test('stale outages feed autodiscovery hook is removed', () => {
  assert.doesNotMatch(functions, /\/outages\/feed/);
  assert.match(functions, /lousy_outages_feed_autodiscovery/);
});

test('header emits a single title and canonical block', () => {
  assert.equal((header.match(/<title>/g) || []).length, 1);
  assert.equal((header.match(/rel="canonical"/g) || []).length, 1);
  assert.equal((header.match(/name="description"/g) || []).length, 1);
});
