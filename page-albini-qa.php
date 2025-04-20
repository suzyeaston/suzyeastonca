<?php
/* Template Name: Albini Q&A */
get_header();
?>

<main id="albini-main" class="albini-qa-page">

  <!-- Header Section -->
  <section class="albini-header">
    <h1 class="albini-title">What Would Steve Albini Do?</h1>
    <p class="albini-subtitle">Ask the legend anything. He’ll answer in his signature no‑BS style.</p>
  </section>

  <!-- Q&A Widget Section -->
  <section class="albini-qa-container">
    <?php echo do_shortcode('[albini_qa]'); ?>
  </section>

</main>

<?php get_footer(); ?>

