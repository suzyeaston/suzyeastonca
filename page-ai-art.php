<?php
/**
 * Template Name: AI Art / MACHINE VISIONS
 * Public exhibition layer for AI-assisted films, stills, loops, and process notes.
 */

get_header();

$catalog = se_ai_art_load_catalog();
$gallery = $catalog['gallery'];
$works   = se_ai_art_published_works();
$featured = se_ai_art_featured_work();
$cta_href = home_url( (string) ( $gallery['ctaHref'] ?? '/work-with-suzy/' ) );
$filters  = array(
	'all'     => 'ALL',
	'film'    => 'FILM',
	'stills'  => 'STILLS',
	'loops'   => 'LOOPS',
	'process' => 'PROCESS',
);

/**
 * @param array<string, mixed> $work
 */
$render_work_card = static function ( array $work ) {
	$slug   = (string) ( $work['slug'] ?? '' );
	$kind   = (string) ( $work['kind'] ?? 'still' );
	$filter = $kind === 'still' ? 'stills' : ( $kind === 'loop' ? 'loops' : $kind );
	$thumb  = se_ai_art_media_url( (string) ( $work['thumbnailSrc'] ?? $work['posterSrc'] ?? $work['src'] ?? '' ) );
	$width  = max( 1, (int) ( $work['width'] ?? 16 ) );
	$height = max( 1, (int) ( $work['height'] ?? 9 ) );
	$year   = substr( (string) ( $work['date'] ?? '' ), 0, 4 );
	$tags   = isset( $work['tags'] ) && is_array( $work['tags'] ) ? $work['tags'] : array();
	?>
	<article
		class="ai-art-card"
		data-kind="<?php echo esc_attr( $filter ); }"
		data-slug="<?php echo esc_attr( $slug ); ?>"
		style="--ai-art-aspect: <?php echo esc_attr( (string) $width ); ?> / <?php echo esc_attr( (string) $height ); ?>;"
	>
		<button
			type="button"
			class="ai-art-card__hit"
			data-ai-art-open="<?php echo esc_attr( $slug ); ?>"
			aria-haspopup="dialog"
			aria-controls="ai-art-detail"
		>
			<span class="ai-art-card__frame">
				<img
					src="<?php echo esc_url( $thumb ); ?>"
					alt="<?php echo esc_attr( (string) ( $work['alt'] ?? '' ) ); ?>"
					width="<?php echo esc_attr( (string) $width ); ?>"
					height="<?php echo esc_attr( (string) $height ); ?>"
					loading="lazy"
					decoding="async"
				>
			</span>
			<span class="ai-art-card__meta">
				<span class="ai-art-card__title pixel-font"><?php echo esc_html( (string) ( $work['title'] ?? '' ) ); ?></span>
				<span class="ai-art-card__sub">
					<span><?php echo esc_html( strtoupper( $kind ) ); ?></span>
					<?php if ( $year !== '' ) : ?>
						<span aria-hidden="true"> · </span>
						<span><?php echo esc_html( $year ); ?></span>
					<?php endif; ?>
				</span>
				<span class="ai-art-card__note"><?php echo esc_html( (string) ( $work['description'] ?? '' ) ); ?></span>
				<?php if ( ! empty( $tags ) ) : ?>
					<span class="ai-art-card__tags">
						<?php foreach ( array_slice( $tags, 0, 4 ) as $tag ) : ?>
							<span class="ai-art-tag"><?php echo esc_html( (string) $tag ); ?></span>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</span>
		</button>
	</article>
	<?php
};
?>
<main id="main-content" class="ai-art-page" data-ai-art-root>
	<div class="ai-art-shell">
		<header class="ai-art-hero" aria-labelledby="ai-art-title">
			<p class="ai-art-eyebrow pixel-font"><?php echo esc_html( (string) $gallery['eyebrow'] ); ?></p>
			<h1 id="ai-art-title" class="ai-art-title pixel-font"><?php echo esc_html( (string) $gallery['publicTitle'] ); ?></h1>
			<p class="ai-art-intro"><?php echo esc_html( (string) $gallery['intro'] ); ?></p>
			<p class="ai-art-process"><?php echo esc_html( (string) $gallery['processNote'] ); ?></p>
		</header>

		<?php if ( $featured ) : ?>
			<section class="ai-art-featured" aria-labelledby="ai-art-featured-title">
				<div class="ai-art-section-head">
					<p class="ai-art-kicker pixel-font">featured transmission</p>
					<h2 id="ai-art-featured-title" class="pixel-font"><?php echo esc_html( (string) $featured['title'] ); ?></h2>
				</div>
				<?php
				$feat_kind = (string) ( $featured['kind'] ?? 'still' );
				$feat_src  = se_ai_art_media_url( (string) ( $featured['src'] ?? '' ) );
				$feat_poster = se_ai_art_media_url( (string) ( $featured['posterSrc'] ?? $featured['thumbnailSrc'] ?? '' ) );
				$feat_w = max( 1, (int) ( $featured['width'] ?? 16 ) );
				$feat_h = max( 1, (int) ( $featured['height'] ?? 9 ) );
				?>
				<figure
					class="ai-art-featured__media"
					style="--ai-art-aspect: <?php echo esc_attr( (string) $feat_w ); ?> / <?php echo esc_attr( (string) $feat_h ); ?>;"
				>
					<?php if ( in_array( $feat_kind, array( 'film', 'loop' ), true ) ) : ?>
						<video
							controls
							playsinline
							preload="metadata"
							poster="<?php echo esc_url( $feat_poster ); ?>"
							width="<?php echo esc_attr( (string) $feat_w ); ?>"
							height="<?php echo esc_attr( (string) $feat_h ); ?>"
							<?php echo $feat_kind === 'loop' ? 'loop muted' : ''; ?>
						>
							<source src="<?php echo esc_url( $feat_src ); ?>">
							<?php if ( ! empty( $featured['captionsSrc'] ) ) : ?>
								<track kind="captions" src="<?php echo esc_url( se_ai_art_media_url( (string) $featured['captionsSrc'] ) ); ?>" srclang="en" label="English">
							<?php endif; ?>
						</video>
					<?php else : ?>
						<img
							src="<?php echo esc_url( $feat_src ); ?>"
							alt="<?php echo esc_attr( (string) ( $featured['alt'] ?? '' ) ); ?>"
							width="<?php echo esc_attr( (string) $feat_w ); ?>"
							height="<?php echo esc_attr( (string) $feat_h ); ?>"
							decoding="async"
						>
					<?php endif; ?>
					<figcaption class="ai-art-featured__caption">
						<span class="pixel-font"><?php echo esc_html( strtoupper( $feat_kind ) ); ?></span>
						<span><?php echo esc_html( (string) ( $featured['description'] ?? '' ) ); ?></span>
					</figcaption>
				</figure>
			</section>
		<?php endif; ?>

		<section class="ai-art-gallery" aria-labelledby="ai-art-gallery-title">
			<div class="ai-art-section-head">
				<p class="ai-art-kicker pixel-font">archive</p>
				<h2 id="ai-art-gallery-title" class="pixel-font">Works</h2>
			</div>

			<div class="ai-art-filters" role="toolbar" aria-label="Filter works by medium">
				<?php foreach ( $filters as $key => $label ) : ?>
					<button
						type="button"
						class="ai-art-filter<?php echo $key === 'all' ? ' is-active' : ''; ?>"
						data-ai-art-filter="<?php echo esc_attr( $key ); ?>"
						aria-pressed="<?php echo $key === 'all' ? 'true' : 'false'; ?>"
					>
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $works ) ) : ?>
				<div class="ai-art-empty" role="status">
					<p class="ai-art-empty__signal pixel-font"><?php echo esc_html( (string) $gallery['emptyState'] ); ?></p>
					<p class="ai-art-empty__copy">The gallery is wired. The first finished transmission is still in the machine. Draft fixtures stay private until a publish bundle lands.</p>
				</div>
			<?php else : ?>
				<div class="ai-art-grid" data-ai-art-grid>
					<?php foreach ( $works as $work ) : ?>
						<?php $render_work_card( $work ); ?>
					<?php endforeach; ?>
				</div>
				<p class="ai-art-filter-empty" data-ai-art-filter-empty hidden>Nothing in that lane yet.</p>
			<?php endif; ?>
		</section>

		<section class="ai-art-authorship" aria-labelledby="ai-art-authorship-title">
			<div class="ai-art-section-head">
				<p class="ai-art-kicker pixel-font">authorship</p>
				<h2 id="ai-art-authorship-title" class="pixel-font">Human in the loop</h2>
			</div>
			<p>These are AI-assisted works. Models generate material. Suzy writes the briefs, steers continuity, edits, scores, rejects the garbage, and owns the cut. Tool and model names show up per piece when published. Full private prompts stay in the workshop unless an excerpt is chosen on purpose.</p>
		</section>

		<section class="ai-art-cta" aria-labelledby="ai-art-cta-title">
			<h2 id="ai-art-cta-title" class="pixel-font"><?php echo esc_html( (string) $gallery['ctaHeading'] ); ?></h2>
			<a class="pixel-button" href="<?php echo esc_url( se_preserve_utm_url( $cta_href ) ); ?>" data-hire-cta data-hire-cta-label="ai_art_cta">
				<?php echo esc_html( (string) $gallery['ctaButton'] ); ?>
			</a>
		</section>
	</div>

	<dialog class="ai-art-detail" id="ai-art-detail" data-ai-art-detail aria-labelledby="ai-art-detail-title">
		<div class="ai-art-detail__inner">
			<button type="button" class="ai-art-detail__close" data-ai-art-close aria-label="Close work detail">Close</button>
			<div data-ai-art-detail-body></div>
		</div>
	</dialog>

	<script type="application/json" id="ai-art-works-data"><?php
		echo wp_json_encode(
			array_map(
				static function ( $work ) {
					$work['srcUrl'] = se_ai_art_media_url( (string) ( $work['src'] ?? '' ) );
					$work['thumbnailUrl'] = se_ai_art_media_url( (string) ( $work['thumbnailSrc'] ?? '' ) );
					$work['posterUrl'] = se_ai_art_media_url( (string) ( $work['posterSrc'] ?? $work['thumbnailSrc'] ?? '' ) );
					if ( ! empty( $work['captionsSrc'] ) ) {
						$work['captionsUrl'] = se_ai_art_media_url( (string) $work['captionsSrc'] );
					}
					return $work;
				},
				$works
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	?></script>
</main>
<?php
get_footer();
