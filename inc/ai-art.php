<?php
/**
 * MACHINE VISIONS — AI art gallery content model and helpers.
 *
 * Content lives in assets/data/ai-art/works.json. Drafts under assets/data/ai-art/drafts/
 * never render publicly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string Public gallery title — rename in one place. */
const SE_AI_ART_PUBLIC_TITLE = 'MACHINE VISIONS';

/** @var string Workshop app name — rename in one place. */
const SE_AI_ART_APP_NAME = 'Moving Picture Machine';

/** @var string Public route. */
const SE_AI_ART_ROUTE = '/ai-art/';

/**
 * Absolute path to the gallery catalog JSON.
 *
 * @return string
 */
function se_ai_art_catalog_path() {
	return get_template_directory() . '/assets/data/ai-art/works.json';
}

/**
 * Absolute path to the publish-bundle JSON Schema.
 *
 * @return string
 */
function se_ai_art_bundle_schema_path() {
	return get_template_directory() . '/schemas/ai-art-publish-bundle.schema.json';
}

/**
 * Load and decode the gallery catalog.
 *
 * @return array{schemaVersion?: string, gallery: array<string, mixed>, works: array<int, array<string, mixed>>}
 */
function se_ai_art_load_catalog() {
	$path = se_ai_art_catalog_path();
	$fallback = array(
		'schemaVersion' => '1.0.0',
		'gallery'       => se_ai_art_default_gallery_copy(),
		'works'         => array(),
	);

	if ( ! is_readable( $path ) ) {
		return $fallback;
	}

	$raw = file_get_contents( $path );
	if ( ! is_string( $raw ) || $raw === '' ) {
		return $fallback;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return $fallback;
	}

	$gallery = isset( $data['gallery'] ) && is_array( $data['gallery'] )
		? array_merge( se_ai_art_default_gallery_copy(), $data['gallery'] )
		: se_ai_art_default_gallery_copy();

	$works = array();
	if ( isset( $data['works'] ) && is_array( $data['works'] ) ) {
		foreach ( $data['works'] as $work ) {
			if ( is_array( $work ) ) {
				$works[] = $work;
			}
		}
	}

	return array(
		'schemaVersion' => isset( $data['schemaVersion'] ) ? (string) $data['schemaVersion'] : '1.0.0',
		'gallery'       => $gallery,
		'works'         => $works,
	);
}

/**
 * Default public copy for the gallery shell.
 *
 * @return array<string, string>
 */
function se_ai_art_default_gallery_copy() {
	return array(
		'publicTitle' => SE_AI_ART_PUBLIC_TITLE,
		'appName'     => SE_AI_ART_APP_NAME,
		'route'       => SE_AI_ART_ROUTE,
		'eyebrow'     => 'SYNTHETIC IMAGE LAB // VANCOUVER',
		'intro'       => 'AI-assisted films, stills and visual experiments by Suzy Easton — made through prompting, editing, sound, code, iteration and the occasional beautiful failure.',
		'processNote' => 'These works use generative AI as material, not autopilot. Each project records the tools, inputs and human choices behind it.',
		'emptyState'  => 'FIRST TRANSMISSION IN RENDER QUEUE',
		'ctaHeading'  => 'Make something strange and useful.',
		'ctaButton'   => 'Work with Suzy',
		'ctaHref'     => '/work-with-suzy/',
	);
}

/**
 * Validate one artwork record. Returns a list of error strings (empty = ok).
 *
 * @param array<string, mixed> $work
 * @return array<int, string>
 */
function se_ai_art_validate_work( array $work ) {
	$errors = array();
	$required = array(
		'id',
		'slug',
		'status',
		'title',
		'date',
		'kind',
		'description',
		'alt',
		'src',
		'thumbnailSrc',
		'width',
		'height',
		'tags',
		'tools',
		'humanContribution',
		'featured',
		'sortOrder',
	);

	foreach ( $required as $key ) {
		if ( ! array_key_exists( $key, $work ) ) {
			$errors[] = "missing field: {$key}";
		}
	}

	if ( isset( $work['status'] ) && ! in_array( $work['status'], array( 'draft', 'published' ), true ) ) {
		$errors[] = 'status must be draft or published';
	}

	if ( isset( $work['kind'] ) && ! in_array( $work['kind'], array( 'film', 'still', 'loop', 'process' ), true ) ) {
		$errors[] = 'kind must be film, still, loop, or process';
	}

	if ( isset( $work['slug'] ) && ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $work['slug'] ) ) {
		$errors[] = 'slug must be lowercase kebab-case';
	}

	if ( isset( $work['width'] ) && ( ! is_numeric( $work['width'] ) || (int) $work['width'] < 1 ) ) {
		$errors[] = 'width must be a positive integer';
	}

	if ( isset( $work['height'] ) && ( ! is_numeric( $work['height'] ) || (int) $work['height'] < 1 ) ) {
		$errors[] = 'height must be a positive integer';
	}

	if ( isset( $work['alt'] ) && trim( (string) $work['alt'] ) === '' ) {
		$errors[] = 'alt text is required';
	}

	if ( isset( $work['humanContribution'] ) ) {
		if ( ! is_array( $work['humanContribution'] ) || count( $work['humanContribution'] ) < 1 ) {
			$errors[] = 'humanContribution needs at least one entry';
		}
	}

	if ( isset( $work['tools'] ) && ! is_array( $work['tools'] ) ) {
		$errors[] = 'tools must be an array';
	}

	if ( isset( $work['tags'] ) && ! is_array( $work['tags'] ) ) {
		$errors[] = 'tags must be an array';
	}

	return $errors;
}

/**
 * Published works only, sorted by sortOrder then date desc.
 *
 * @return array<int, array<string, mixed>>
 */
function se_ai_art_published_works() {
	$catalog = se_ai_art_load_catalog();
	$works   = array();

	foreach ( $catalog['works'] as $work ) {
		if ( ( $work['status'] ?? '' ) !== 'published' ) {
			continue;
		}
		$errors = se_ai_art_validate_work( $work );
		if ( ! empty( $errors ) ) {
			continue;
		}
		$works[] = $work;
	}

	usort(
		$works,
		static function ( $a, $b ) {
			$order = ( (int) ( $a['sortOrder'] ?? 0 ) ) <=> ( (int) ( $b['sortOrder'] ?? 0 ) );
			if ( 0 !== $order ) {
				return $order;
			}
			return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) );
		}
	);

	return $works;
}

/**
 * Featured published work, if any.
 *
 * @return array<string, mixed>|null
 */
function se_ai_art_featured_work() {
	foreach ( se_ai_art_published_works() as $work ) {
		if ( ! empty( $work['featured'] ) ) {
			return $work;
		}
	}
	$works = se_ai_art_published_works();
	return $works[0] ?? null;
}

/**
 * Resolve a public media URL for a catalog-relative or absolute path.
 *
 * @param string $src
 * @return string
 */
function se_ai_art_media_url( $src ) {
	$src = (string) $src;
	if ( $src === '' ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $src ) ) {
		return $src;
	}
	$trimmed = ltrim( $src, '/' );
	if ( str_starts_with( $trimmed, 'assets/data/ai-art/' ) || str_starts_with( $trimmed, 'assets/' ) || str_starts_with( $trimmed, 'data/ai-art/' ) ) {
		return get_template_directory_uri() . '/' . $trimmed;
	}
	return get_template_directory_uri() . '/assets/data/ai-art/media/' . $trimmed;
}

/**
 * Gallery SEO meta.
 *
 * @return array{title: string, description: string, keywords: string}
 */
function se_ai_art_meta() {
	$gallery = se_ai_art_load_catalog()['gallery'];
	$title   = (string) ( $gallery['publicTitle'] ?? SE_AI_ART_PUBLIC_TITLE );

	return array(
		'title'       => $title . ' | AI-assisted films & stills by Suzy Easton',
		'description' => (string) ( $gallery['intro'] ?? se_ai_art_default_gallery_copy()['intro'] ),
		'keywords'    => 'MACHINE VISIONS, AI art Vancouver, Suzy Easton, AI-assisted film, synthetic image lab',
	);
}

/**
 * CollectionPage + artwork structured data.
 *
 * @return array<string, mixed>
 */
function se_ai_art_structured_data() {
	$meta    = se_ai_art_meta();
	$works   = se_ai_art_published_works();
	$url     = home_url( SE_AI_ART_ROUTE );
	$has_part = array();

	foreach ( $works as $work ) {
		$kind = (string) ( $work['kind'] ?? 'still' );
		$item_url = $url . '#' . rawurlencode( (string) $work['slug'] );
		$media_url = se_ai_art_media_url( (string) ( $work['src'] ?? '' ) );

		if ( in_array( $kind, array( 'film', 'loop' ), true ) ) {
			$node = array(
				'@type'       => 'VideoObject',
				'name'        => (string) $work['title'],
				'description' => (string) $work['description'],
				'url'         => $item_url,
				'contentUrl'  => $media_url,
				'thumbnailUrl'=> se_ai_art_media_url( (string) ( $work['thumbnailSrc'] ?? $work['posterSrc'] ?? '' ) ),
				'uploadDate'  => (string) ( $work['date'] ?? '' ),
				'author'      => array(
					'@type' => 'Person',
					'name'  => 'Suzy Easton',
				),
			);
			if ( ! empty( $work['durationSeconds'] ) ) {
				$node['duration'] = 'PT' . (int) $work['durationSeconds'] . 'S';
			}
		} else {
			$node = array(
				'@type'       => 'VisualArtwork',
				'name'        => (string) $work['title'],
				'description' => (string) $work['description'],
				'url'         => $item_url,
				'image'       => $media_url,
				'artMedium'   => 'AI-assisted ' . $kind,
				'dateCreated' => (string) ( $work['date'] ?? '' ),
				'creator'     => array(
					'@type' => 'Person',
					'name'  => 'Suzy Easton',
				),
			);
			if ( ! empty( $work['width'] ) && ! empty( $work['height'] ) ) {
				$node['width']  = (string) (int) $work['width'];
				$node['height'] = (string) (int) $work['height'];
			}
		}

		$has_part[] = $node;
	}

	return array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			se_website_schema( $meta['description'] ),
			se_person_schema(),
			array(
				'@type'       => 'CollectionPage',
				'name'        => $meta['title'],
				'url'         => $url,
				'description' => $meta['description'],
				'about'       => 'AI-assisted visual experiments',
				'hasPart'     => $has_part,
			),
		),
	);
}

/**
 * Enqueue gallery assets.
 */
function se_enqueue_ai_art_assets() {
	if ( ! is_page_template( 'page-ai-art.php' ) && ! is_page( 'ai-art' ) ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	$css_path = '/assets/css/ai-art.css';
	if ( file_exists( $dir . $css_path ) ) {
		wp_enqueue_style( 'se-ai-art', $uri . $css_path, array(), filemtime( $dir . $css_path ) );
	}

	$js_path = '/js/ai-art.js';
	if ( file_exists( $dir . $js_path ) ) {
		wp_enqueue_script( 'se-ai-art', $uri . $js_path, array(), filemtime( $dir . $js_path ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_ai_art_assets' );
