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
      <p class="vanops-eyebrow">Vancouver SMB operations dashboard</p>
      <h1>VanOps Radar</h1>
      <p class="vanops-lede">A lightweight local disruption board for Vancouver businesses that need to know what might affect customers, staff, deliveries, bookings, and events.</p>
      <p class="vanops-support">Built for cafés, venues, offices, agencies, retailers, coworking spaces, and local operators who do not have time to babysit twelve tabs before opening.</p>
      <div class="vanops-actions">
        <a class="vanops-button" href="<?php echo esc_url($cta_url); ?>">Ask about a custom dashboard</a>
        <a class="vanops-button vanops-button--secondary" href="#vanops-preview">See the Vancouver ops preview</a>
      </div>
    </section>

    <section class="vanops-section vanops-card">
      <header>
        <h2>What it watches</h2>
        <p>Local-first disruption categories for business planning, staffing, and continuity.</p>
      </header>
      <div class="vanops-grid vanops-grid--watch">
        <article class="vanops-tile"><h3>Roads and access</h3><p>Monitor route friction that can affect delivery timing, customer arrivals, and staff punctuality.</p><span class="vanops-status-pill">Planned connector</span></article>
        <article class="vanops-tile"><h3>Transit and commute</h3><p>Track commute reliability patterns to inform shift planning and service-hour decisions.</p><span class="vanops-status-pill">Planned connector</span></article>
        <article class="vanops-tile"><h3>Weather and seasonal risk</h3><p>Prepare for rain, heat, snow, wind, and seasonal disruptions that change demand and safety posture.</p><span class="vanops-status-pill">Planned connector</span></article>
        <article class="vanops-tile"><h3>Power and utilities</h3><p>Plan around utility risk that can impact POS systems, refrigeration, lighting, and Wi-Fi reliability.</p><span class="vanops-status-pill">Planned connector</span></article>
        <article class="vanops-tile"><h3>Major events and crowd pressure</h3><p>Model high-demand windows and congestion risk around concerts, matches, festivals, and surge days.</p><span class="vanops-status-pill vanops-status-pill--demo">Demo signal</span></article>
        <article class="vanops-tile"><h3>Public-realm / neighbourhood issues</h3><p>Surface nearby conditions that influence customer confidence, access comfort, and staff safety.</p><span class="vanops-status-pill vanops-status-pill--roadmap">Roadmap</span></article>
      </div>
    </section>

    <section id="vanops-preview" class="vanops-section vanops-card vanops-status-board">
      <header>
        <h2>Vancouver ops board</h2>
        <p>A local-first preview of the disruptions VanOps Radar is designed to track for businesses. Live civic connectors are being added carefully; demo and planned signals are labelled clearly.</p>
      </header>
      <div class="vanops-grid">
        <article class="vanops-signal-card">
          <h3>Road and access disruptions</h3>
          <p class="vanops-signal-meta"><strong>Data status:</strong> <span class="vanops-status-pill">Planned connector</span></p>
          <p><strong>Business impact:</strong> Delivery routes, customer access, staff arrival times.</p>
          <p><strong>Operator action:</strong> Check access routes before peak hours and post customer-facing notes if needed.</p>
        </article>
        <article class="vanops-signal-card">
          <h3>Transit and commute impacts</h3>
          <p class="vanops-signal-meta"><strong>Data status:</strong> <span class="vanops-status-pill">Planned connector</span></p>
          <p><strong>Business impact:</strong> Staff scheduling, customer arrival patterns, event-day delays.</p>
          <p><strong>Operator action:</strong> Watch for commute disruptions before opening, closing, or event windows.</p>
        </article>
        <article class="vanops-signal-card">
          <h3>Weather weirdness</h3>
          <p class="vanops-signal-meta"><strong>Data status:</strong> <span class="vanops-status-pill">Planned connector</span></p>
          <p><strong>Business impact:</strong> Patio decisions, staffing, foot traffic, safety prep, and cancellation risk.</p>
          <p><strong>Operator action:</strong> Prepare rain, heat, snow, or wind contingencies and update booking notes.</p>
        </article>
        <article class="vanops-signal-card">
          <h3>Power and utility issues</h3>
          <p class="vanops-signal-meta"><strong>Data status:</strong> <span class="vanops-status-pill">Planned connector</span></p>
          <p><strong>Business impact:</strong> POS systems, refrigeration, Wi-Fi, lighting, and service continuity.</p>
          <p><strong>Operator action:</strong> Keep offline payment, signage, and customer communication backups ready.</p>
        </article>
        <article class="vanops-signal-card">
          <h3>Major event / crowd pressure</h3>
          <p class="vanops-signal-meta"><strong>Data status:</strong> <span class="vanops-status-pill vanops-status-pill--demo">Demo signal</span></p>
          <p><strong>Business impact:</strong> Demand spikes, street congestion, lineups, delivery timing, and customer-flow pressure.</p>
          <p><strong>Operator action:</strong> Plan staffing, inventory, signage, and service windows around major events.</p>
        </article>
        <article class="vanops-signal-card">
          <h3>Local public-realm issues</h3>
          <p class="vanops-signal-meta"><strong>Data status:</strong> <span class="vanops-status-pill vanops-status-pill--demo">Demo signal</span></p>
          <p><strong>Business impact:</strong> Construction, public-space issues, access concerns, and nearby neighbourhood conditions.</p>
          <p><strong>Operator action:</strong> Track nearby conditions that may affect customer confidence and staff safety.</p>
        </article>
      </div>
    </section>

    <section class="vanops-section vanops-card">
      <header>
        <h2>Built for Vancouver operators</h2>
      </header>
      <div class="vanops-grid vanops-use-case-grid">
        <article class="vanops-tile"><h3>Storefronts and cafés</h3><p>Adjust opening checklists, staffing, and customer messaging when road access or weather shifts demand.</p></article>
        <article class="vanops-tile"><h3>Venues and event teams</h3><p>Plan service windows around crowd spikes, transit delays, and event-adjacent neighbourhood pressure.</p></article>
        <article class="vanops-tile"><h3>Offices and agencies</h3><p>Set practical hybrid-day and client-meeting expectations when commute and utility conditions change.</p></article>
        <article class="vanops-tile"><h3>Coworking spaces</h3><p>Provide member updates on access, commute, and local disruptions before arrival peaks.</p></article>
        <article class="vanops-tile"><h3>Retail and service businesses</h3><p>Protect conversion and service continuity with early signals for staffing, delivery, and neighbourhood risks.</p></article>
        <article class="vanops-tile"><h3>WC26 / major event readiness</h3><p>Prepare for citywide demand surges with staffing, inventory, queue flow, and customer communications plans.</p></article>
      </div>
    </section>

    <section class="vanops-section vanops-card vanops-data-roadmap">
      <header>
        <h2>Data roadmap</h2>
      </header>
      <p>VanOps Radar is designed to connect local operational signals into one readable board. The MVP starts with clearly labelled demo/planned signals, then grows into a real local ops layer.</p>
      <ul class="vanops-skill-list">
        <li>City of Vancouver open data</li>
        <li>Road and access disruption feeds</li>
        <li>Transit advisories</li>
        <li>Weather alerts</li>
        <li>Power/utility outage notices</li>
        <li>Major event calendars</li>
        <li>Plain-language AI summaries</li>
        <li>Custom dashboards for individual businesses or neighbourhoods</li>
      </ul>
    </section>

    <section class="vanops-section vanops-card vanops-cta">
      <h2>Need a status board for your business?</h2>
      <p>I can build lightweight dashboards that pull together local disruption signals and plain-language operational notes for Vancouver teams.</p>
      <a class="vanops-button" href="<?php echo esc_url($cta_url); ?>">Work with Suzy</a>
    </section>

    <p class="vanops-disclaimer">VanOps Radar is an informational operations dashboard, not an emergency alert system. Always check official sources for safety-critical updates.</p>
  </div>
</main>

<?php get_footer(); ?>
