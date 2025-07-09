<?php
/*
Template Name: Track Analyzer
*/

get_header();

/**
 * Analyze an uploaded MP3 using the OpenAI API.
 *
 * Requires the OPENAI_API_KEY constant to be defined in wp-config.php or
 * the theme's functions.php file:
 *
 *     define( 'OPENAI_API_KEY', 'your-openai-api-key' );
 *
 * @param string $filepath Absolute path to the uploaded MP3.
 * @return string Friendly critique text or an error message.
 */
function analyze_audio( $filepath ) {
    if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
        return __( 'OpenAI API key is not configured.', 'suzys-music-theme' );
    }

    $file_resource = curl_file_create( $filepath, 'audio/mpeg', basename( $filepath ) );

    $transcription = wp_remote_post( 'https://api.openai.com/v1/audio/transcriptions', [
        'headers' => [ 'Authorization' => 'Bearer ' . OPENAI_API_KEY ],
        'body'    => [
            'model' => 'whisper-1',
            'file'  => $file_resource,
        ],
        'timeout' => 60,
    ] );

    if ( is_wp_error( $transcription ) ) {
        return __( 'Failed to transcribe audio.', 'suzys-music-theme' );
    }

    $body = wp_remote_retrieve_body( $transcription );
    $data = json_decode( $body, true );
    $text = $data['text'] ?? '';

    if ( ! $text ) {
        return __( 'Audio transcription failed.', 'suzys-music-theme' );
    }

    $prompt = 'Provide a playful yet insightful critique of this track. Channel the futuristic vibes of Grimes with the dance-floor savvy of James Murphy. Transcript: ' . $text;

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . OPENAI_API_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( [
            'model'    => 'gpt-4o-mini',
            'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens' => 150,
        ] ),
        'timeout' => 60,
    ] );

    if ( is_wp_error( $response ) ) {
        return __( 'Analysis request failed.', 'suzys-music-theme' );
    }

    $body     = wp_remote_retrieve_body( $response );
    $data     = json_decode( $body, true );
    $analysis = $data['choices'][0]['message']['content'] ?? '';

    return $analysis ? $analysis : __( 'Could not generate analysis.', 'suzys-music-theme' );
}

$analysis = '';
$error    = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['track_file'] ) ) {
    $file = $_FILES['track_file'];

    if ( UPLOAD_ERR_OK === $file['error'] ) {
        $filetype = wp_check_filetype( $file['name'], [ 'mp3' => 'audio/mpeg' ] );

        if ( $filetype['ext'] ) {
            $upload = wp_handle_upload( $file, [ 'test_form' => false ] );

            if ( isset( $upload['file'] ) ) {
                $analysis = analyze_audio( $upload['file'] );
                @unlink( $upload['file'] );
            } else {
                $error = isset( $upload['error'] ) ? $upload['error'] : __( 'There was an error uploading your file.', 'suzys-music-theme' );
            }
        } else {
            $error = __( 'Please upload a valid MP3 file.', 'suzys-music-theme' );
        }
    } else {
        $error = __( 'Upload failed. Please try again.', 'suzys-music-theme' );
    }
}
?>

<main id="main-content">
  <section class="page-content track-analyzer">
    <h1 class="pixel-font">Track Analyzer</h1>
    <p>Curious how your song stacks up? Drop an MP3 below and I’ll deliver a quick vibe check—think shimmering synths, fuzzy guitars and friendly tips.</p>

    <?php if ( $error ) : ?>
      <p class="error-message"><?php echo esc_html( $error ); ?></p>
    <?php endif; ?>

    <?php if ( $analysis ) : ?>
      <div class="analysis-result">
        <p><?php echo esc_html( $analysis ); ?></p>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="analyzer-form">
      <label for="track_file">Upload MP3 File</label>
      <input type="file" name="track_file" id="track_file" accept=".mp3,audio/mpeg" required>
      <button type="submit" class="pixel-button">Analyze Track</button>
    </form>
  </section>
</main>

<?php get_footer(); ?>
