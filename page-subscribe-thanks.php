<?php
/* Template Name: Lousy Outages Subscribe Thanks */
get_header();
?>
<main class="subscribe-thanks">
  <section class="lo-thanks-card crt-block" style="max-width:600px;margin:80px auto;padding:48px;border-radius:18px;background:#0d130d;color:#e8ffee;box-shadow:0 16px 48px rgba(0,0,0,0.35);text-align:center;">
    <h1 class="retro-title glow-lite" style="font-size:2rem;margin-bottom:24px;">Subscribed to Lousy Outages</h1>
    <?php if (!empty($_GET['error'])) : ?>
      <p style="font-size:1.1rem;margin-bottom:16px;">We couldn’t add that email address. Please head back and try again.</p>
    <?php else : ?>
      <p style="font-size:1.1rem;margin-bottom:16px;">Check your inbox for a confirmation email. Once you click the link you’ll start receiving outage alerts.</p>
      <p style="font-size:1rem;margin-bottom:18px;">Heads-up: add <strong>suzyeaston.ca</strong> to your safe senders so the briefings don’t hide in spam or junk folders.</p>
    <?php endif; ?>
    <p style="font-size:0.95rem;margin-bottom:22px;">Need to bail later? Every Lousy Outages email ships with a one-click unsubscribe link.</p>
    <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>">Return to dashboard →</a>
  </section>
</main>
<?php get_footer(); ?>
