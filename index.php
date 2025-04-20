<?php
get_header();
?>

<main id="main-content">
  <?php if ( is_home() && ! is_front_page() ) : ?>

    <h1 class="page-title"><?php single_post_title(); ?></h1>
    <?php
    if ( have_posts() ) :
      while ( have_posts() ) : the_post();
        get_template_part( 'content', get_post_format() );
      endwhile;
      the_posts_pagination();
    else :
      get_template_part( 'content', 'none' );
    endif;
    ?>

  <?php else : ?>

    <?php
    // For static pages (home, about, etc.), let page.php or page-*.php handle it:
    while ( have_posts() ) : the_post();
      the_content();
    endwhile;
    ?>

  <?php endif; ?>
</main>

<?php
get_footer();
