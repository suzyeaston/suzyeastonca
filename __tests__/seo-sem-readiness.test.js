const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = (path) => fs.readFileSync(path, 'utf8');
const header = read('header.php');
const footer = read('footer.php');
const home = read('page-home.php');
const seo = read('inc/seo.php');
const contactModal = read('assets/js/header-contact-modal.js');
const functions = read('functions.php');

test('header delegates metadata and structured data to inc/seo.php', () => {
  assert.match(header, /se_page_meta\(\)/);
  assert.match(header, /se_page_structured_data\(\)/);
  assert.equal((header.match(/<title>/g) || []).length, 1);
  assert.equal((header.match(/rel="canonical"/g) || []).length, 1);
});

test('public identity is music, systems and experiments, not a consulting storefront', () => {
  assert.match(seo, /Suzy Easton \| Music, Software & Experiments/);
  assert.match(seo, /Musician, Systems Engineer and Creative Technologist/);
  assert.doesNotMatch(seo, /Hire Suzy Easton \|/);
  assert.match(home, /music \/\/ software \/\/ human signal/);
  assert.doesNotMatch(home, /home-hire-strip/);
  assert.match(read('parts/home-hero-pitch.php'), /people and machines improvise together/);
});

test('structured data contains Person and WebSite with Vancouver-only location', () => {
  assert.match(seo, /'@type'\s*=>\s*'Person'/);
  assert.match(seo, /'@type'\s*=>\s*'WebSite'/);
  assert.match(seo, /homeLocation/);
  assert.match(seo, /Vancouver, British Columbia/);
  assert.doesNotMatch(seo, /Jericho Beach/);
  assert.doesNotMatch(seo, /streetAddress/);
  assert.doesNotMatch(seo, /LocalBusiness/);
});

test('header and footer navigate to creative work without shop or hire links', () => {
  for (const source of [header, footer]) {
    assert.match(source, /\/blog\//);
    assert.match(source, /\/projects\//);
    assert.match(source, /\/music-releases\//);
    assert.doesNotMatch(source, /home_url\( '\/shop\/' \)/);
    assert.doesNotMatch(source, /home_url\( '\/work-with-suzy\/' \)/);
  }
  assert.match(header, /SAY HELLO/);
  assert.match(contactModal, /contact_open/);
  assert.match(contactModal, /contact_submit/);
});

test('retired sales URLs redirect permanently to current pages', () => {
  assert.match(functions, /template_redirect', 'se_redirect_retired_sales_pages'/);
  assert.match(functions, /is_page\( 'shop' \)/);
  assert.match(functions, /is_page\( 'work-with-suzy' \)/);
  assert.match(read('page-shop.php'), /home_url\( '\/projects\/' \), 301/);
  assert.match(read('page-work-with-suzy.php'), /home_url\( '\/bio\/' \), 301/);
});

test('gallery invitation leads to projects rather than consulting', () => {
  const gallery = JSON.parse(read('assets/data/ai-art/works.json')).gallery;
  assert.equal(gallery.ctaHref, '/projects/');
  assert.equal(gallery.ctaButton, 'Explore projects');
  assert.doesNotMatch(read('page-ai-art.php'), /data-hire-cta/);
});

test('outages feed autodiscovery is still present', () => {
  assert.doesNotMatch(functions, /\/outages\/feed/);
  assert.match(functions, /lousy_outages_feed_autodiscovery/);
});
