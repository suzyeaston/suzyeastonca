<?php
/* Template Name: Lousy Outages */
get_header();

$sub_status = isset($_GET['sub']) ? sanitize_key(wp_unslash($_GET['sub'])) : '';
if (isset($_COOKIE['lo_sub_msg'])) {
    $cookie_status = sanitize_key(wp_unslash($_COOKIE['lo_sub_msg']));
    if ('' === $sub_status && '' !== $cookie_status) {
        $sub_status = $cookie_status;
    }
    if (!headers_sent()) {
        setcookie('lo_sub_msg', '', time() - HOUR_IN_SECONDS, '/', '', is_ssl(), false);
    }
}

$banner = '';
$tone   = 'info';

if (isset($_GET['lo_unsub_success']) && absint(wp_unslash($_GET['lo_unsub_success']))) {
    $banner = 'Done. No more alerts.';
    $tone   = 'ok';
} else {
    switch ($sub_status) {
        case 'confirmed':
            $banner = 'You’re in. Alerts start with the next incident.';
            $tone   = 'ok';
            break;
        case 'check-email':
            $banner = 'Check your inbox. One more click and you’re on the list.';
            $tone   = 'info';
            break;
        case 'invalid':
            $banner = 'That link is dead. Sign up again.';
            $tone   = 'error';
            break;
        case 'unsubscribed':
            $banner = 'Done. No more alerts.';
            $tone   = 'warn';
            break;
    }
}
?>

<main class="lousy-outages-page">
  <div class="lox-page">
    <header class="lox-page__intro">
      <h1 class="lox-page__title">LOUSY OUTAGES</h1>
      <p class="lox-page__tagline">Official status pages, read for you every 15 minutes. No spin, no vibes.</p>
    </header>

    <?php if ($banner) : ?>
      <p class="lox-page__banner lox-page__banner--<?php echo esc_attr($tone); ?>"><?php echo esc_html($banner); ?></p>
    <?php endif; ?>

    <?php echo do_shortcode('[lousy_outages]'); ?>
    <?php echo do_shortcode('[lousy_outages_report]'); ?>
    <?php echo do_shortcode('[lousy_outages_subscribe]'); ?>

    <footer class="lox-page__foot">
      <p>Built in Vancouver. Runs on the providers' own feeds — nothing scraped, nothing guessed.</p>
      <p>
        <a href="<?php echo esc_url(home_url('/lousy-outages/pricing/')); ?>">Watchlists and alert routing</a>
        · <a href="https://buymeacoffee.com/wi0amge" target="_blank" rel="noopener noreferrer">Keep the machine running</a>
      </p>
    </footer>
  </div>
</main>

<?php get_footer(); ?>
