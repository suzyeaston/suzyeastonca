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
  <?php echo do_shortcode('[lousy_outages]'); ?>
</main>

<?php get_footer(); ?>
