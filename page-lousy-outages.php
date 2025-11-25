<?php
/* Template Name: Lousy Outages */
get_header();

$sub_status = isset($_GET['sub']) ? sanitize_key(wp_unslash($_GET['sub'])) : '';
$cookie_status = '';
if (isset($_COOKIE['lo_sub_msg'])) {
    $cookie_status = sanitize_key(wp_unslash($_COOKIE['lo_sub_msg']));
    if ('' === $sub_status && '' !== $cookie_status) {
        $sub_status = $cookie_status;
    }
    if (!headers_sent()) {
        setcookie('lo_sub_msg', '', time() - HOUR_IN_SECONDS, '/', '', is_ssl(), false);
    }
}
$banner        = '';
$tone          = 'info';
$unsub_success = 0;
if (isset($_GET['lo_unsub_success'])) {
    $unsub_success = absint(wp_unslash($_GET['lo_unsub_success']));
}

if ($unsub_success) {
    $banner = 'You’ve been unsubscribed. All the best!';
    $tone   = 'success';
} else {
    switch ($sub_status) {
        case 'confirmed':
            $banner = "You're in! You'll get outage alerts soon.";
            $tone   = 'success';
            break;
        case 'check-email':
            $banner = 'Check your inbox for a confirmation link to finish subscribing.';
            $tone   = 'info';
            break;
        case 'invalid':
            $banner = 'That link is invalid or has expired. Please try subscribing again.';
            $tone   = 'error';
            break;
        case 'unsubscribed':
            $banner = "You have been unsubscribed from Lousy Outages alerts.";
            $tone   = 'warning';
            break;
    }
}
?>

<main class="lousy-outages-page">
  <h1 class="retro-title glow-lite">Lousy Outages</h1>
  <?php if ($banner) : ?>
    <div class="lo-banner lo-banner--<?php echo esc_attr($tone); ?>">
      <p><?php echo esc_html($banner); ?></p>
    </div>
  <?php endif; ?>
  <section class="lo-panel lo-panel--report" data-lo-report>
    <header class="lo-panel__header">
      <h2 class="lo-panel__title">Report a problem</h2>
      <p class="lo-panel__subtitle">
        If you’re seeing issues with a provider that aren’t reflected below, send a quick community report.
      </p>
    </header>

    <form class="lo-report__form" data-lo-report-form novalidate>
      <div class="lo-field">
        <label class="lo-label" for="lo-report-provider">Provider</label>
        <select id="lo-report-provider" name="provider_id" class="lo-input" data-lo-report-provider>
          <!-- Options will be populated by JS from the summary API -->
        </select>
      </div>

      <div class="lo-field">
        <label class="lo-label" for="lo-report-summary">What are you seeing?</label>
        <textarea
          id="lo-report-summary"
          name="summary"
          rows="3"
          class="lo-input lo-input--textarea"
          data-lo-report-summary
          placeholder="Short description of the issue (timeouts, errors, etc.)"
        ></textarea>
      </div>

      <div class="lo-field">
        <label class="lo-label" for="lo-report-contact">Contact (optional)</label>
        <input
          id="lo-report-contact"
          name="contact"
          type="text"
          class="lo-input"
          data-lo-report-contact
          placeholder="Email or handle (optional)"
        />
      </div>

      <div class="lo-report__actions">
        <button type="submit" class="lo-button" data-lo-report-submit>Submit report</button>
        <p class="lo-report__status" data-lo-report-status aria-live="polite"></p>
      </div>
    </form>
  </section>
  <?php echo do_shortcode('[lousy_outages]'); ?>
  <footer class="lo-support">
    <p class="lo-support__lead">If this dashboard makes your on-call a little less lousy, you can help keep it running:</p>
    <ul class="lo-support__links">
      <li>☕ <a class="lo-link" href="https://buymeacoffee.com/wi0amge" target="_blank" rel="noopener">Buy Me a Coffee</a></li>
      <li>💖 <a class="lo-link" href="https://paypal.me/suzyeaston" target="_blank" rel="noopener">Donate via PayPal</a></li>
      <li>🎵 <a class="lo-link" href="https://suzyeaston.bandcamp.com/" target="_blank" rel="noopener">Listen on Bandcamp (new release coming soon!)</a></li>
    </ul>
    <p class="lo-support__note">Thanks for fueling the retro outage radar.</p>
  </footer>
</main>

<?php get_footer(); ?>
