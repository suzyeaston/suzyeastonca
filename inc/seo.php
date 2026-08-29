<?php
/**
 * SEO, structured data, and conversion analytics helpers.
 *
 * Single source of truth for page meta and JSON-LD. header.php consumes this;
 * do not add a parallel SEO plugin layer without removing the manual tags first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string
 */
function se_default_og_image() {
	return 'https://suzyeaston.ca/arcade/og-image.png';
}

/**
 * @return array<int, string>
 */
function se_person_same_as() {
	return [
		'https://www.linkedin.com/in/suzyeaston/',
		'https://github.com/suzyeaston',
		'https://suzyeaston.bandcamp.com',
		'https://soundcloud.com/suzyeaston',
		'https://instagram.com/suzyeaston',
		'https://youtube.com/@suzyeaston',
	];
}

/**
 * @return array<int, string>
 */
function se_person_knows_about() {
	return [
		'Artificial intelligence consulting',
		'Workflow automation',
		'Systems integration',
		'Technical consulting',
		'API integration',
		'WordPress development',
		'Software debugging',
		'Creative technology',
		'QA automation',
	];
}

/**
 * @return array<string, mixed>
 */
function se_person_schema() {
	return [
		'@type'       => 'Person',
		'name'        => 'Suzy Easton',
		'url'         => home_url( '/' ),
		'jobTitle'    => 'AI, Automation & Creative Technology Consultant',
		'description' => 'Vancouver-based AI strategist, solutions engineer and creative technologist helping teams debug systems, automate workflows, integrate tools and build useful technology.',
		'knowsAbout'  => se_person_knows_about(),
		'homeLocation' => [
			'@type' => 'Place',
			'name'  => 'East Vancouver',
			'address' => [
				'@type'           => 'PostalAddress',
				'addressLocality' => 'Vancouver',
				'addressRegion'   => 'BC',
				'addressCountry'  => 'CA',
			],
		],
		'sameAs' => se_person_same_as(),
	];
}

/**
 * @return array<string, mixed>
 */
function se_website_schema( $description = '' ) {
	$graph = [
		'@type'       => 'WebSite',
		'name'        => 'Suzy Easton',
		'alternateName' => 'suzyeaston.ca',
		'url'         => home_url( '/' ),
		'description' => $description ?: get_bloginfo( 'description' ),
		'inLanguage'  => get_bloginfo( 'language' ),
		'publisher'   => [
			'@type' => 'Person',
			'name'  => 'Suzy Easton',
			'url'   => home_url( '/' ),
		],
		'potentialAction' => [
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		],
	];

	return $graph;
}

/**
 * @return array<string, string>
 */
function se_home_meta() {
	return [
		'title'       => 'Suzy Easton | AI, Automation & Creative Technology Consultant in Vancouver',
		'description' => 'Vancouver-based AI strategist, solutions engineer and creative technologist helping teams debug systems, automate workflows, integrate tools and build useful technology. Based in East Vancouver and available now.',
		'keywords'    => 'Suzy Easton, AI consultant Vancouver, automation consultant Vancouver, creative technologist Vancouver, technical consulting Vancouver, workflow automation',
	];
}

/**
 * @return array<string, string>
 */
function se_work_with_suzy_meta() {
	return [
		'title'       => 'Hire Suzy Easton | AI & Automation Consultant in Vancouver, BC',
		'description' => 'Hire Suzy Easton for AI strategy, workflow automation, integrations, debugging and technical consulting in Vancouver or remotely. Based in East Vancouver and available for projects now.',
		'keywords'    => 'hire Suzy Easton, AI consultant Vancouver, automation consultant Vancouver, technical consultant Vancouver, workflow automation Vancouver, East Vancouver consultant',
	];
}

/**
 * @return array<string, mixed>
 */
function se_work_with_suzy_structured_data() {
	$meta     = se_work_with_suzy_meta();
	$services = [];

	foreach ( se_get_shop_products() as $product ) {
		$services[] = [
			'@type'       => 'Service',
			'name'        => (string) ( $product['title'] ?? '' ) . ' — ' . (string) ( $product['subtitle'] ?? '' ),
			'description' => (string) ( $product['description'] ?? '' ),
			'provider'    => [
				'@type' => 'Person',
				'name'  => 'Suzy Easton',
				'url'   => home_url( '/' ),
			],
			'serviceType' => (string) ( $product['subtitle'] ?? 'Technical consulting' ),
			'areaServed'  => se_service_area_served(),
			'offers'      => [
				'@type'         => 'Offer',
				'price'         => (string) ( $product['price'] ?? 0 ),
				'priceCurrency' => (string) ( $product['currency'] ?? 'CAD' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => home_url( '/shop/#' . (string) ( $product['slug'] ?? '' ) ),
			],
		];
	}

	return [
		'@context' => 'https://schema.org',
		'@graph'   => array_merge(
			[
				se_website_schema( $meta['description'] ),
				se_person_schema(),
				[
					'@type'       => 'WebPage',
					'name'        => $meta['title'],
					'url'         => home_url( '/work-with-suzy/' ),
					'description' => $meta['description'],
				],
				[
					'@type'       => 'Service',
					'name'        => 'AI, Automation & Technical Consulting',
					'description' => $meta['description'],
					'provider'    => [
						'@type' => 'Person',
						'name'  => 'Suzy Easton',
						'url'   => home_url( '/' ),
					],
					'serviceType' => [
						'AI consulting',
						'workflow automation',
						'technical consulting',
						'systems integration',
						'debugging',
						'creative technology',
					],
					'areaServed' => se_service_area_served(),
					'url'        => home_url( '/work-with-suzy/' ),
				],
			],
			$services
		),
	];
}

/**
 * @return array<int, array<string, mixed>>
 */
function se_service_area_served() {
	return [
		[
			'@type' => 'City',
			'name'  => 'Vancouver',
			'containedInPlace' => [
				'@type' => 'AdministrativeArea',
				'name'  => 'British Columbia',
				'containedInPlace' => [
					'@type' => 'Country',
					'name'  => 'Canada',
				],
			],
		],
		[
			'@type' => 'Country',
			'name'  => 'Canada',
		],
	];
}

/**
 * @return array<string, mixed>
 */
function se_home_structured_data( $description ) {
	$graph = [
		se_website_schema( $description ),
		se_person_schema(),
		[
			'@type'       => 'ProfilePage',
			'name'        => 'Suzy Easton',
			'url'         => home_url( '/' ),
			'description' => $description,
			'mainEntity'  => se_person_schema(),
		],
	];

	return [
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	];
}

/**
 * Resolve page meta for the current request.
 *
 * @return array{title: string, description: string, keywords: string, image: string, url: string}
 */
function se_page_meta() {
	global $wp;

	$site_name   = 'Suzy Easton';
	$default_img = se_default_og_image();
	$keywords    = 'Suzy Easton, musician, creative technologist';

	if ( is_front_page() ) {
		$home       = se_home_meta();
		$meta_title = $home['title'];
		$meta_desc  = $home['description'];
		$keywords   = $home['keywords'];
	} elseif ( is_page_template( 'page-work-with-suzy.php' ) || is_page( 'work-with-suzy' ) ) {
		$wws        = se_work_with_suzy_meta();
		$meta_title = $wws['title'];
		$meta_desc  = $wws['description'];
		$keywords   = $wws['keywords'];
	} elseif ( is_page_template( 'page-asmr-lab.php' ) ) {
		$meta_title = 'ASMR Lab – experimental predecessor now under major redevelopment';
		$meta_desc  = 'ASMR Lab is Suzy\'s earlier audio/visual prototype that inspired the Gastown simulator and is now being rebuilt in public.';
		$keywords   = 'ASMR Lab, Gastown simulator predecessor, creative tech prototype, Suzy Easton';
	} elseif ( is_page_template( 'page-ai-art.php' ) || is_page( 'ai-art' ) ) {
		$ai_art     = function_exists( 'se_ai_art_meta' ) ? se_ai_art_meta() : [];
		$meta_title = (string) ( $ai_art['title'] ?? 'MACHINE VISIONS | Suzy Easton' );
		$meta_desc  = (string) ( $ai_art['description'] ?? 'AI-assisted films, stills and visual experiments by Suzy Easton.' );
		$keywords   = (string) ( $ai_art['keywords'] ?? 'MACHINE VISIONS, AI art, Suzy Easton' );
	} elseif ( is_page_template( 'page-lousy-outages.php' ) ) {
		$meta_title = 'Lousy Outages | Status dashboard for modern chaos';
		$meta_desc  = 'A retro status dashboard for provider incidents, SaaS weirdness, community reports, and alerts when things go sideways.';
		$keywords   = 'lousy outages status dashboard, outage tracker, retro status board';
	} elseif ( is_page_template( 'page-vancouver-tech-events.php' ) || is_page( 'vancouver-tech-events' ) ) {
		$meta_title = 'Vancouver Tech Events | Meetup, Luma, BC Tech in one feed';
		$meta_desc  = 'Upcoming Vancouver tech events from Meetup, Luma, and BC Tech — plus a Futureproof Festival spotlight.';
		$keywords   = 'Vancouver tech events, Meetup Vancouver, Luma Vancouver, Futureproof Festival, BC Tech events, Suzy Easton';
	} elseif ( is_page_template( 'page-track-analyzer.php' ) ) {
		$meta_title = "Suzy's Track Analyzer | AI-assisted song feedback";
		$meta_desc  = 'Upload an MP3 and get direct AI-assisted notes on lyrics, structure, feel, and production direction.';
		$keywords   = 'track analyzer, music AI tool, Suzy Easton, mix feedback';
	} elseif ( is_page_template( 'page-arcade.php' ) ) {
		$meta_title = 'Canucks Puck Bash - Retro Hockey Arcade';
		$meta_desc  = "Shoot, score, and hear 'Don't You Forget About Me' in this 80s-style hockey arcade game.";
		$keywords   = 'Canucks arcade game, retro hockey game, Suzy Easton arcade';
	} elseif ( is_page_template( 'page-coffee-for-builders.php' ) ) {
		$meta_title = 'Coffee for Builders in Vancouver | Suzy Easton';
		$meta_desc  = 'Coffee chats in Vancouver for people building things—tech, music, civic projects, and sports takes. Low-key, public, and intentional.';
		$keywords   = 'coffee chats Vancouver, builders, Suzy Easton, tech, music, civic projects, sports takes';
	} elseif ( is_page_template( 'page-shop.php' ) || is_page( 'shop' ) ) {
		$shop_meta  = function_exists( 'se_shop_meta' ) ? se_shop_meta() : [];
		$meta_title = (string) ( $shop_meta['title'] ?? 'Shop Suzy | Hire Suzy Easton' );
		$meta_desc  = (string) ( $shop_meta['description'] ?? 'Book focused consulting time with Suzy Easton.' );
		$keywords   = (string) ( $shop_meta['keywords'] ?? 'hire Suzy Easton, tech consulting Vancouver' );
	} elseif ( is_home() && ! is_front_page() ) {
		$blog_meta  = se_blog_archive_meta();
		$meta_title = $blog_meta['title'];
		$meta_desc  = $blog_meta['description'];
		$keywords   = $blog_meta['keywords'];
	} elseif ( is_category() ) {
		$blog_meta  = se_blog_category_meta();
		$meta_title = $blog_meta['title'];
		$meta_desc  = $blog_meta['description'];
		$keywords   = $blog_meta['keywords'];
	} elseif ( is_singular( 'post' ) ) {
		$post       = get_queried_object();
		$blog_meta  = ( $post instanceof WP_Post ) ? se_blog_post_meta( $post ) : se_blog_archive_meta();
		$meta_title = $blog_meta['title'];
		$meta_desc  = $blog_meta['description'];
		$keywords   = $blog_meta['keywords'];
		if ( $post instanceof WP_Post && has_post_thumbnail( $post ) ) {
			$thumb = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'large' );
			if ( is_string( $thumb ) && $thumb !== '' ) {
				$default_img = $thumb;
			}
		}
	} else {
		$meta_title = wp_title( '|', false, 'right' ) . $site_name;
		$meta_desc  = get_bloginfo( 'description' );
		$keywords   = 'Suzy Easton, musician, creative technologist, Vancouver artist';
	}

	if ( is_singular() ) {
		$meta_url = get_permalink();
	} else {
		$meta_url = home_url( add_query_arg( [], $wp->request ) );
	}

	return [
		'title'       => $meta_title,
		'description' => $meta_desc,
		'keywords'    => $keywords,
		'image'       => $default_img,
		'url'         => $meta_url,
	];
}

/**
 * @return array<string, mixed>
 */
function se_page_structured_data() {
	$meta = se_page_meta();

	if ( is_front_page() ) {
		return se_home_structured_data( $meta['description'] );
	}

	if ( is_page_template( 'page-work-with-suzy.php' ) || is_page( 'work-with-suzy' ) ) {
		return se_work_with_suzy_structured_data();
	}

	if ( ( is_page_template( 'page-ai-art.php' ) || is_page( 'ai-art' ) ) && function_exists( 'se_ai_art_structured_data' ) ) {
		return se_ai_art_structured_data();
	}

	if ( ( is_page_template( 'page-shop.php' ) || is_page( 'shop' ) ) && function_exists( 'se_shop_structured_data' ) ) {
		return se_shop_structured_data();
	}

	if ( is_home() && ! is_front_page() ) {
		return se_blog_archive_structured_data();
	}

	if ( is_singular( 'post' ) ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			return se_blog_post_structured_data( $post );
		}
	}

	if ( is_category() ) {
		$meta = se_blog_category_meta();
		return [
			'@context' => 'https://schema.org',
			'@graph'   => [
				se_website_schema( $meta['description'] ),
				se_person_schema(),
				[
					'@type'       => 'CollectionPage',
					'name'        => $meta['title'],
					'url'         => get_term_link( get_queried_object() ),
					'description' => $meta['description'],
				],
			],
		];
	}

	return [
		'@context' => 'https://schema.org',
		'@graph'   => [
			se_website_schema( $meta['description'] ),
			se_person_schema(),
		],
	];
}

/**
 * @return array<string, string>
 */
function se_get_utm_query_args() {
	$utm = [];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	foreach ( $_GET as $key => $value ) {
		if ( preg_match( '/^utm_/', (string) $key ) && is_scalar( $value ) ) {
			$utm[ (string) $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}
	}

	return $utm;
}

/**
 * Append current-request UTM params to an internal URL.
 *
 * @param string $url
 */
function se_preserve_utm_url( $url ) {
	$utm = se_get_utm_query_args();

	if ( empty( $utm ) ) {
		return $url;
	}

	return add_query_arg( $utm, $url );
}

/**
 * @param string               $event_name
 * @param array<string, mixed> $payload
 */
function se_seo_track_event( $event_name, $payload = [] ) {
	$payload = array_merge(
		[
			'surface' => se_seo_current_surface(),
		],
		is_array( $payload ) ? $payload : []
	);

	/**
	 * Site-wide conversion analytics hook.
	 *
	 * @param string               $event_name
	 * @param array<string, mixed> $payload
	 */
	do_action( 'se_seo_event', $event_name, $payload );
}

/**
 * @return string
 */
function se_seo_current_surface() {
	if ( is_page_template( 'page-work-with-suzy.php' ) || is_page( 'work-with-suzy' ) ) {
		return 'work_with_suzy';
	}

	if ( is_page_template( 'page-shop.php' ) || is_page( 'shop' ) ) {
		return 'shop';
	}

	if ( is_front_page() ) {
		return 'home';
	}

	if ( is_home() || is_singular( 'post' ) || is_category() ) {
		return 'blog';
	}

	return 'site';
}

function se_enqueue_seo_analytics_assets() {
	if ( is_admin() ) {
		return;
	}

	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$js  = '/assets/js/seo-analytics.js';

	if ( ! file_exists( $dir . $js ) ) {
		return;
	}

	wp_enqueue_script(
		'se-seo-analytics',
		$uri . $js,
		[],
		filemtime( $dir . $js ),
		true
	);

	wp_localize_script(
		'se-seo-analytics',
		'SeSeoConfig',
		[
			'surface' => se_seo_current_surface(),
			'paths'   => [
				'shop'           => home_url( '/shop/' ),
				'workWithSuzy'   => home_url( '/work-with-suzy/' ),
			],
		]
	);
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_seo_analytics_assets', 20 );

function se_seo_analytics_bootstrap() {
	if ( is_admin() ) {
		return;
	}

	$surface = se_seo_current_surface();
	?>
	<script>
	(function () {
		function seTrackEvent(name, data) {
			var payload = Object.assign({ event: name, surface: <?php echo wp_json_encode( $surface ); ?> }, data || {});
			window.dispatchEvent(new CustomEvent('se-seo:event', { detail: payload }));
			if (window.dataLayer) {
				window.dataLayer.push(payload);
			}
		}
		window.seTrackEvent = seTrackEvent;

		<?php if ( 'work_with_suzy' === $surface ) : ?>
		seTrackEvent('work_with_suzy_view', { page: 'work_with_suzy' });
		<?php endif; ?>
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'se_seo_analytics_bootstrap', 98 );

function se_work_with_suzy_page_view() {
	if ( ! is_page_template( 'page-work-with-suzy.php' ) && ! is_page( 'work-with-suzy' ) ) {
		return;
	}

	se_seo_track_event( 'work_with_suzy_view', [ 'page' => 'work_with_suzy' ] );
}
add_action( 'wp', 'se_work_with_suzy_page_view' );
