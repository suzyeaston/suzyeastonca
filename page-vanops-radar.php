<?php
/* Template Name: VanOps Radar */
get_header();

$work_with_suzy_page = get_page_by_path('work-with-suzy');
$cta_url = ($work_with_suzy_page instanceof WP_Post)
    ? get_permalink($work_with_suzy_page)
    : 'mailto:suzyeaston@icloud.com?subject=VanOps%20Radar%20Custom%20Dashboard';
?>

<main class="vanops-radar-page">
  <div class="vanops-radar">
    <section class="vanops-hero vanops-card">
      <p class="vanops-eyebrow">Vancouver operations intelligence for storefronts, venues, offices, and local teams.</p>
      <h1>VanOps Radar</h1>
      <p class="vanops-lede">A lightweight status and disruption board for Vancouver businesses that need to know what might affect customers, staff, deliveries, bookings, and events.</p>
      <a class="vanops-button" href="<?php echo esc_url($cta_url); ?>">Ask about a custom dashboard</a>
    </section>

    <section class="vanops-section vanops-card">
      <header>
        <h2>What it watches</h2>
        <p>Live today + planned connectors for broader local operations context.</p>
      </header>
      <div class="vanops-grid vanops-grid--watch">
        <article class="vanops-tile vanops-tile--live"><h3>Internet/service outages</h3><p>Live now via provider status and incident feed monitoring.</p><span>Live signal</span></article>
        <article class="vanops-tile"><h3>Road and access disruptions</h3><p>Traffic constraints around customer access, deliveries, and staffing routes.</p><span>Planned connector</span></article>
        <article class="vanops-tile"><h3>Weather weirdness</h3><p>Localized weather patterns that trigger operational changes.</p><span>Coming next</span></article>
        <article class="vanops-tile"><h3>Transit/service advisories</h3><p>Service disruptions that impact shift coverage and commute reliability.</p><span>Planned connector</span></article>
        <article class="vanops-tile"><h3>Power/utility issues</h3><p>Utility incidents and restoration windows relevant to business continuity.</p><span>Coming next</span></article>
        <article class="vanops-tile"><h3>Event-day operational risks</h3><p>Major crowd and schedule days including sports, concerts, and city events.</p><span>Planned connector</span></article>
      </div>
    </section>

    <section class="vanops-section vanops-card vanops-live-preview">
      <header>
        <h2>Live provider signal preview</h2>
        <p>Current production feed from the Lousy Outages status engine.</p>
      </header>
      <?php echo do_shortcode('[lousy_outages]'); ?>
    </section>

    <section class="vanops-section vanops-card">
      <header>
        <h2>Business use cases</h2>
      </header>
      <div class="vanops-grid vanops-grid--cases">
        <article class="vanops-tile"><h3>Cafés and restaurants</h3><p>Plan staffing, deliveries, and POS contingency during provider instability.</p></article>
        <article class="vanops-tile"><h3>Venues and event teams</h3><p>Track service risk before doors open and during guest-heavy windows.</p></article>
        <article class="vanops-tile"><h3>Agencies and studios</h3><p>Prevent deadline surprises with visible signal checks for critical SaaS.</p></article>
        <article class="vanops-tile"><h3>Retail storefronts</h3><p>Adapt staffing and customer messaging when service disruptions hit.</p></article>
        <article class="vanops-tile"><h3>Coworking spaces</h3><p>Give members quick heads-up context on internet and platform reliability.</p></article>
        <article class="vanops-tile"><h3>Local operators for WC26 surge days</h3><p>Prepare ahead for demand spikes and citywide operational pressure.</p></article>
      </div>
    </section>

    <section class="vanops-section vanops-card">
      <header>
        <h2>Built by Suzanne Easton</h2>
      </header>
      <ul class="vanops-skill-list">
        <li>IT Operations</li>
        <li>QA automation</li>
        <li>WordPress custom theme development</li>
        <li>JavaScript dashboard UI</li>
        <li>REST/API integration</li>
        <li>civic/open-data product thinking</li>
        <li>AI-assisted summarization roadmap</li>
      </ul>
    </section>

    <section class="vanops-section vanops-card vanops-cta">
      <h2>Need a status board for your business?</h2>
      <p>I can build lightweight dashboards that pull together service health, public disruption feeds, and plain-language operational notes — without making your team babysit twelve tabs.</p>
      <a class="vanops-button" href="<?php echo esc_url($cta_url); ?>">Work with Suzy</a>
    </section>

    <p class="vanops-disclaimer">VanOps Radar is an informational operations dashboard, not an emergency alert system. Always check official sources for safety-critical updates.</p>
  </div>
</main>

<?php get_footer(); ?>
