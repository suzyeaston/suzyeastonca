<?php
/*
Template Name: Resume
*/
get_header();

$resume_data_path = get_template_directory() . '/inc/resume-data.php';
$resume           = file_exists( $resume_data_path ) ? require $resume_data_path : null;

$resume_dir      = get_template_directory() . '/assets/resume';
$resume_uri      = get_template_directory_uri() . '/assets/resume';
$download_html   = $resume_uri . '/suzy-easton-bsa-resume.html';
$download_pdf    = $resume_uri . '/Suzy_Easton_BSA_Resume.pdf';
$has_html        = file_exists( $resume_dir . '/suzy-easton-bsa-resume.html' );
$has_pdf         = file_exists( $resume_dir . '/Suzy_Easton_BSA_Resume.pdf' );
?>

<main id="main-content" class="resume-page">
    <?php if ( ! is_array( $resume ) ) : ?>
        <div class="resume-toolbar">
            <span class="resume-toolbar__label">Resume</span>
        </div>
        <article class="resume-sheet" style="display:block;padding:2rem;">
            <p>Resume content is not configured on this environment. Copy <code>inc/resume-data.example.php</code> to <code>inc/resume-data.php</code> and add your private resume files under <code>assets/resume/</code>.</p>
        </article>
    <?php else : ?>
    <div class="resume-toolbar" aria-label="Resume actions">
        <span class="resume-toolbar__label"><?php echo esc_html( $resume['name'] ); ?> // BSA Resume</span>
        <div class="resume-toolbar__actions">
            <?php if ( $has_pdf ) : ?>
                <a class="resume-btn resume-btn--primary" href="<?php echo esc_url( $download_pdf ); ?>" download="Suzy_Easton_BSA_Resume.pdf">Download PDF</a>
            <?php endif; ?>
            <button type="button" class="resume-btn<?php echo $has_pdf ? '' : ' resume-btn--primary'; ?>" data-resume-print>Print<?php echo $has_pdf ? '' : ' / Save PDF'; ?></button>
            <?php if ( $has_html ) : ?>
                <a class="resume-btn" href="<?php echo esc_url( $download_html ); ?>" download="Suzy_Easton_BSA_Resume.html">Download HTML</a>
            <?php endif; ?>
            <a class="resume-btn" href="<?php echo esc_url( home_url( '/work-with-suzy/' ) ); ?>">Work With Suzy</a>
        </div>
    </div>

    <article class="resume-sheet" id="resume-document" aria-label="<?php echo esc_attr( $resume['name'] ); ?> resume">
        <aside class="resume-sidebar">
            <header>
                <h1 class="resume-sidebar__name"><?php echo esc_html( $resume['name'] ); ?></h1>
                <p class="resume-sidebar__title"><?php echo esc_html( $resume['title'] ); ?></p>
                <p class="resume-sidebar__tags"><?php echo esc_html( $resume['tags'] ); ?></p>
            </header>

            <div class="resume-availability"><?php echo esc_html( $resume['availability'] ); ?></div>

            <nav class="resume-contact" aria-label="Contact information">
                <?php foreach ( $resume['contact'] as $item ) : ?>
                    <?php if ( ! empty( $item['url'] ) ) : ?>
                        <a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
                    <?php else : ?>
                        <span><?php echo esc_html( $item['label'] ); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <section class="resume-sidebar-block resume-sidebar-block--toolkit" aria-labelledby="resume-toolkit-title">
                <h3 id="resume-toolkit-title">03 // Core Toolkit</h3>
                <?php foreach ( $resume['toolkit_groups'] as $group ) : ?>
                    <div class="toolkit-group">
                        <strong><?php echo esc_html( $group['label'] ); ?></strong>
                        <p><?php echo esc_html( $group['items'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
        </aside>

        <div class="resume-main">
            <section class="resume-section" aria-labelledby="resume-profile-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">01 //</span>
                    <h2 class="resume-section__title" id="resume-profile-title">Profile</h2>
                </div>
                <p><?php echo esc_html( $resume['profile'] ); ?></p>
            </section>

            <section class="resume-section resume-alignment" aria-labelledby="resume-alignment-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">02 //</span>
                    <h2 class="resume-section__title" id="resume-alignment-title">Role Alignment</h2>
                </div>
                <ul>
                    <?php foreach ( $resume['alignment_items'] as $item ) : ?>
                        <li><?php echo esc_html( $item ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="resume-section" aria-labelledby="resume-experience-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">04 //</span>
                    <h2 class="resume-section__title" id="resume-experience-title">Professional Experience</h2>
                </div>
                <?php foreach ( $resume['experience'] as $role ) : ?>
                    <article class="resume-role">
                        <div class="resume-role__header">
                            <h3 class="resume-role__company"><?php echo esc_html( $role['company'] ); ?> <span style="font-weight:500;color:var(--resume-muted);">| <?php echo esc_html( $role['role'] ); ?></span></h3>
                            <span class="resume-role__meta"><?php echo esc_html( $role['dates'] ); ?></span>
                        </div>
                        <p class="resume-role__location"><?php echo esc_html( $role['location'] ); ?><?php echo ! empty( $role['subtitle'] ) ? ' · ' . esc_html( $role['subtitle'] ) : ''; ?></p>
                        <ul>
                            <?php foreach ( $role['bullets'] as $bullet ) : ?>
                                <li><?php echo esc_html( $bullet ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="resume-section" aria-labelledby="resume-additional-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">05 //</span>
                    <h2 class="resume-section__title" id="resume-additional-title">Additional Technical Experience</h2>
                </div>
                <?php foreach ( $resume['additional_experience'] as $role ) : ?>
                    <article class="resume-role">
                        <div class="resume-role__header">
                            <h3 class="resume-role__company"><?php echo esc_html( $role['company'] ); ?> <span style="font-weight:500;color:var(--resume-muted);">| <?php echo esc_html( $role['role'] ); ?></span></h3>
                            <span class="resume-role__meta"><?php echo esc_html( $role['dates'] ); ?></span>
                        </div>
                        <p class="resume-role__location"><?php echo esc_html( $role['location'] ); ?></p>
                        <ul>
                            <?php foreach ( $role['bullets'] as $bullet ) : ?>
                                <li><?php echo esc_html( $bullet ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
                <?php if ( ! empty( $resume['earlier_roles'] ) ) : ?>
                <article class="resume-role">
                    <h3 class="resume-role__company" style="font-size:0.82rem;">Earlier technical roles</h3>
                    <ul>
                        <?php foreach ( $resume['earlier_roles'] as $role ) : ?>
                            <li><?php echo esc_html( $role ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <?php endif; ?>
            </section>

            <section class="resume-section resume-builds" aria-labelledby="resume-builds-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">06 //</span>
                    <h2 class="resume-section__title" id="resume-builds-title">Independent Practice and Selected Builds</h2>
                </div>
                <ul>
                    <?php foreach ( $resume['builds'] as $build ) : ?>
                        <li>
                            <strong><?php echo esc_html( $build['title'] ); ?></strong>
                            <?php if ( ! empty( $build['meta'] ) ) : ?>
                                <span> — <?php echo esc_html( $build['meta'] ); ?></span>
                            <?php endif; ?>
                            <?php echo ! empty( $build['meta'] ) ? '<br>' : ': '; ?>
                            <?php echo esc_html( $build['body'] ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="resume-section resume-education" aria-labelledby="resume-education-title">
                <div class="resume-section__head">
                    <span class="resume-section__num">07 //</span>
                    <h2 class="resume-section__title" id="resume-education-title">Education and Creative Signal</h2>
                </div>
                <ul class="resume-education__list">
                    <?php foreach ( $resume['education'] as $item ) : ?>
                        <li><?php echo esc_html( $item ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="resume-education__note"><?php echo esc_html( $resume['education_note'] ); ?></p>
            </section>

            <footer class="resume-footer"><?php echo esc_html( $resume['footer'] ); ?></footer>
        </div>
    </article>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
