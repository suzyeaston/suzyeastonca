<?php
/*
Template Name: Vancouver Tech Events
*/

get_header();
?>
<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                $classes = implode( ' ', get_post_class() );
                ?>
                <article id="post-<?php echo esc_attr( get_the_ID() ); ?>" class="<?php echo esc_attr( $classes ); ?>">
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </article>
                <?php
            endwhile;
        endif;

        echo suzy_render_vancouver_tech_events_html();
        ?>
    </main>
</div>
<?php
get_footer();
