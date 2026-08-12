<?php
/**
 * Signal Log — blog helpers, queries, and asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intro copy for the blog archive hero.
 */
function se_blog_intro_copy() {
	return 'notes from East Vancouver on AI, systems, music, code, public infrastructure, open source and whatever I\'m building instead of sleeping.';
}

/**
 * @return string
 */
function se_blog_archive_url() {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	if ( $posts_page_id > 0 ) {
		return get_permalink( $posts_page_id );
	}

	return home_url( '/blog/' );
}

/**
 * @return bool
 */
function se_blog_is_active() {
	return is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author();
}

/**
 * @return array<string, string>
 */
function se_blog_category_tones() {
	return [
		'ai-systems'         => 'ai-systems',
		'things-im-building' => 'building',
		'music-sound'        => 'music',
		'vancouver'          => 'vancouver',
		'open-source'        => 'open-source',
		'notes-ideas'        => 'notes',
	];
}

/**
 * @param string $slug
 * @return string
 */
function se_blog_category_tone_class( $slug ) {
	$tones = se_blog_category_tones();
	$slug  = sanitize_title( (string) $slug );

	if ( isset( $tones[ $slug ] ) ) {
		return 'se-signal-log__cat--' . $tones[ $slug ];
	}

	return 'se-signal-log__cat--default';
}

/**
 * @param int $post_id
 * @return WP_Term|null
 */
function se_blog_primary_category( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$cats    = get_the_category( $post_id );

	if ( empty( $cats ) || is_wp_error( $cats ) ) {
		return null;
	}

	return $cats[0];
}

/**
 * @param int $post_id
 * @return string
 */
function se_blog_format_date( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$time    = get_post_time( 'U', true, $post_id );

	if ( ! $time ) {
		return '';
	}

	return wp_date( 'Y.m.d', $time );
}

/**
 * @param int $post_id
 * @param int $words
 * @return string
 */
function se_blog_excerpt( $post_id = 0, $words = 32 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$post    = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	if ( has_excerpt( $post ) ) {
		return wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), $words, '…' );
	}

	return wp_trim_words( wp_strip_all_tags( $post->post_content ), $words, '…' );
}

/**
 * @return array<string, string>
 */
function se_blog_archive_meta() {
	return [
		'title'       => 'Signal Log | Suzy Easton',
		'description' => se_blog_intro_copy(),
		'keywords'    => 'Signal Log, Suzy Easton blog, East Vancouver, AI systems, open source, creative technology',
	];
}

/**
 * @return array<string, string>
 */
function se_blog_category_meta() {
	$term = get_queried_object();
	$name = ( $term instanceof WP_Term ) ? $term->name : 'Category';

	return [
		'title'       => $name . ' | Signal Log | Suzy Easton',
		'description' => 'Notes from Suzy Easton on ' . strtolower( $name ) . '. Field notes from East Vancouver on AI, systems, music, code, and whatever is being built instead of sleeping.',
		'keywords'    => 'Signal Log, Suzy Easton, ' . $name . ', East Vancouver',
	];
}

/**
 * @param WP_Post $post
 * @return array<string, string>
 */
function se_blog_post_meta( WP_Post $post ) {
	$title = get_the_title( $post );
	$desc  = se_blog_excerpt( $post->ID, 40 );

	return [
		'title'       => $title . ' | Signal Log | Suzy Easton',
		'description' => $desc ?: se_blog_intro_copy(),
		'keywords'    => 'Signal Log, Suzy Easton, ' . $title,
	];
}

/**
 * @return array<string, mixed>
 */
function se_blog_archive_structured_data() {
	$meta  = se_blog_archive_meta();
	$items = [];

	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			$items[] = se_blog_posting_schema( get_post() );
		}
		rewind_posts();
	}

	return [
		'@context' => 'https://schema.org',
		'@graph'   => array_merge(
			[
				se_website_schema( $meta['description'] ),
				se_person_schema(),
				[
					'@type'       => 'Blog',
					'name'        => 'Signal Log',
					'url'         => se_blog_archive_url(),
					'description' => $meta['description'],
					'author'      => [
						'@type' => 'Person',
						'name'  => 'Suzy Easton',
						'url'   => home_url( '/' ),
					],
					'blogPost' => $items,
				],
				[
					'@type'       => 'CollectionPage',
					'name'        => $meta['title'],
					'url'         => se_blog_archive_url(),
					'description' => $meta['description'],
				],
			]
		),
	];
}

/**
 * @param WP_Post $post
 * @return array<string, mixed>
 */
function se_blog_posting_schema( WP_Post $post ) {
	$image = se_default_og_image();

	if ( has_post_thumbnail( $post ) ) {
		$thumb = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'large' );
		if ( is_string( $thumb ) && $thumb !== '' ) {
			$image = $thumb;
		}
	}

	$schema = [
		'@type'            => 'BlogPosting',
		'headline'         => get_the_title( $post ),
		'url'              => get_permalink( $post ),
		'datePublished'    => get_the_date( DATE_W3C, $post ),
		'dateModified'     => get_the_modified_date( DATE_W3C, $post ),
		'description'      => se_blog_excerpt( $post->ID, 40 ),
		'author'           => [
			'@type' => 'Person',
			'name'  => 'Suzy Easton',
			'url'   => home_url( '/' ),
		],
		'publisher'        => [
			'@type' => 'Person',
			'name'  => 'Suzy Easton',
			'url'   => home_url( '/' ),
		],
		'mainEntityOfPage' => [
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post ),
		],
		'image'            => $image,
		'inLanguage'       => get_bloginfo( 'language' ),
	];

	$cat = se_blog_primary_category( $post->ID );
	if ( $cat instanceof WP_Term ) {
		$schema['articleSection'] = $cat->name;
	}

	return $schema;
}

/**
 * @param WP_Post $post
 * @return array<string, mixed>
 */
function se_blog_post_structured_data( WP_Post $post ) {
	$meta = se_blog_post_meta( $post );

	return [
		'@context' => 'https://schema.org',
		'@graph'   => [
			se_website_schema( $meta['description'] ),
			se_person_schema(),
			se_blog_posting_schema( $post ),
			[
				'@type'       => 'WebPage',
				'name'        => $meta['title'],
				'url'         => get_permalink( $post ),
				'description' => $meta['description'],
			],
		],
	];
}

/**
 * @return string
 */
function se_page_og_type() {
	if ( is_singular( 'post' ) ) {
		return 'article';
	}

	return 'website';
}

/**
 * @param int $count
 * @return WP_Query
 */
function se_blog_latest_query( $count = 3 ) {
	return new WP_Query(
		[
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $count ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		]
	);
}

/**
 * Register default blog categories on theme setup (idempotent).
 */
function se_blog_register_default_categories() {
	$categories = [
		'ai-systems'         => 'AI + systems',
		'things-im-building' => 'things I\'m building',
		'music-sound'        => 'music + sound',
		'vancouver'          => 'Vancouver',
		'open-source'        => 'open source',
		'notes-ideas'        => 'notes / ideas',
	];

	foreach ( $categories as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term(
				$name,
				'category',
				[
					'slug' => $slug,
				]
			);
		}
	}
}
add_action( 'after_setup_theme', 'se_blog_register_default_categories' );

function se_enqueue_blog_assets() {
	if ( ! se_blog_is_active() && ! is_front_page() && ! is_page_template( 'page-home.php' ) ) {
		return;
	}

	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$css = '/assets/css/blog.css';

	if ( ! file_exists( $dir . $css ) ) {
		return;
	}

	wp_enqueue_style(
		'se-signal-log',
		$uri . $css,
		[ 'main-styles' ],
		filemtime( $dir . $css )
	);
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_blog_assets', 26 );
