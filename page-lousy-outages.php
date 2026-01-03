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
  <div class="lousy-outages-root">
    <div class="lo-atmosphere">
      <h1 class="retro-title glow-lite">Lousy Outages</h1>
      <p class="lo-atmosphere__lede">Retro radar for when the internet goes sideways.</p>
    </div>
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

      <form
        class="lo-report__form"
        data-lo-report-form
        data-lo-report-phrase-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        novalidate
      >
        <div class="lo-field">
          <label class="lo-label" for="lo-report-provider">Provider</label>
          <select id="lo-report-provider" name="provider_id" class="lo-input" data-lo-report-provider>
            <!-- Options will be populated by JS from the summary API -->
          </select>
        </div>

        <div class="lo-field lo-report__other" data-lo-report-other hidden>
          <label class="lo-label" for="lo-report-provider-name">Provider name</label>
          <input
            id="lo-report-provider-name"
            name="provider_name"
            type="text"
            class="lo-input"
            data-lo-report-provider-name
            maxlength="80"
            placeholder="e.g., Telus, Shaw, Discord, Notion"
          />
          <p class="lo-report__help">Required when “Other (not listed)” is selected.</p>
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

        <div class="lo-field lo-report__captcha" data-lo-report-captcha>
          <p class="lo-report__prompt">Type the 3-word phrase shown below</p>
          <div class="lo-report__captcha-display" data-lo-report-captcha-phrase aria-live="polite">Loading phrase…</div>
          <div class="lo-report__captcha-controls">
            <label class="lo-label" for="lo-report-captcha">Type the phrase</label>
            <input
              id="lo-report-captcha"
              name="captcha_answer"
              type="text"
              class="lo-input"
              data-lo-report-captcha-input
              placeholder="Type the phrase"
            />
            <input type="hidden" name="captcha_token" data-lo-report-captcha-token />
            <button type="button" class="lo-report__captcha-refresh" data-lo-report-captcha-refresh>New phrase</button>
          </div>
          <p class="lo-report__help">Case doesn’t matter, punctuation optional.</p>
        </div>

        <div class="lo-report__actions">
          <button type="submit" class="lo-button lo-report-submit" data-lo-report-submit>Submit report</button>
          <p class="lo-report__status" data-lo-report-status aria-live="polite"></p>
        </div>
      </form>
    </section>
    <section class="lo-status-board">
      <div class="lo-status-board__frame">
        <header class="lo-status-board__header">
          <div class="lo-status-board__lights" aria-hidden="true">
            <span class="lo-led lo-led--green"></span>
            <span class="lo-led lo-led--amber"></span>
            <span class="lo-led lo-led--red"></span>
          </div>
          <div class="lo-status-board__titles">
            <p class="lo-status-board__eyebrow">System Status Console</p>
            <h2 class="lo-status-board__title">Current Outages</h2>
          </div>
          <div class="lo-status-board__badge" aria-hidden="true">v3.0 arcade build</div>
        </header>
        <div class="lo-status-board__body">
          <div class="lo-scanline" aria-hidden="true"></div>
          <?php echo do_shortcode('[lousy_outages]'); ?>
        </div>
      </div>
    </section>
    <footer class="lo-support">
      <p class="lo-support__lead">If this dashboard makes your on-call a little less lousy, you can help keep it running:</p>
      <ul class="lo-support__links">
        <li>☕ <a class="lo-link" href="https://buymeacoffee.com/wi0amge" target="_blank" rel="noopener">Buy Me a Coffee</a></li>
        <li>💖 <a class="lo-link" href="https://paypal.me/suzyeaston" target="_blank" rel="noopener">Donate via PayPal</a></li>
        <li>🎵 <a class="lo-link" href="https://suzyeaston.bandcamp.com/" target="_blank" rel="noopener">Listen on Bandcamp (new release coming soon!)</a></li>
      </ul>
      <p class="lo-support__note">Thanks for fueling the retro outage radar.</p>
    </footer>
  </div>
</main>

<?php get_footer(); ?>
