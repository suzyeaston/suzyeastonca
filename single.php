<?php
get_header();
?>

<main id="main-content">
    <?php 
    while ( have_posts() ) : the_post(); ?>
    
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
            </header>

            <div class="entry-content">
                <?php
                the_content();
                ?>
            </div>

            <footer class="entry-footer">
                <?php
                ?>
            </footer>
        </article>

        <?php
        if ( comments_open() || get_comments_number() ) :
            comments_template();
        endif;

    endwhile; 
    ?>
</main>
<?php
get_footer();
?>
