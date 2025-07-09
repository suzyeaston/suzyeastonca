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

    if ( ! file_exists( $filepath ) ) {
        error_log( 'Whisper API: file missing - ' . $filepath );
        return __( 'Uploaded file missing on server.', 'suzys-music-theme' );
    }

    if ( ! function_exists( 'curl_file_create' ) ) {
        error_log( 'curl_file_create function missing.' );
        return __( 'Server misconfiguration: cURL support missing.', 'suzys-music-theme' );
    }

    $file_resource = curl_file_create( $filepath, 'audio/mpeg', basename( $filepath ) );

    $ch = curl_init( 'https://api.openai.com/v1/audio/transcriptions' );
    curl_setopt_array( $ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [ 'Authorization: Bearer ' . OPENAI_API_KEY ],
        CURLOPT_POSTFIELDS     => [
            'model'           => 'whisper-1',
            'file'            => $file_resource,
            'response_format' => 'json',
        ],
    ] );

    $body = curl_exec( $ch );
    if ( false === $body ) {
        error_log( 'Whisper cURL error: ' . curl_error( $ch ) );
        curl_close( $ch );
        return __( 'Failed to transcribe audio.', 'suzys-music-theme' );
    }

    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );
    error_log( 'Whisper response (' . $code . '): ' . substr( $body, 0, 200 ) );

    if ( 200 !== $code ) {
        error_log( 'Whisper API HTTP ' . $code . ': ' . $body );
        return sprintf( __( 'Audio transcription failed (HTTP %s).', 'suzys-music-theme' ), $code );
    }


    $data = json_decode( $body, true );
    if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
        error_log( 'Whisper JSON error: ' . json_last_error_msg() . ' - ' . $body );
        return __( 'Invalid transcription response.', 'suzys-music-theme' );
    }
    $text = $data['text'] ?? '';

    if ( ! $text ) {
        error_log( 'Whisper API missing text: ' . $body );
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
        error_log( 'GPT-4 request error: ' . $response->get_error_message() );
        return __( 'Analysis request failed.', 'suzys-music-theme' );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    error_log( 'GPT response (' . $code . '): ' . substr( $body, 0, 200 ) );
    if ( 200 !== $code ) {
        error_log( 'GPT-4 HTTP ' . $code . ': ' . $body );
        return sprintf( __( 'Analysis failed (HTTP %s).', 'suzys-music-theme' ), $code );
    }

    $data = json_decode( $body, true );
    if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
        error_log( 'GPT JSON error: ' . json_last_error_msg() . ' - ' . $body );
        return __( 'Invalid analysis response.', 'suzys-music-theme' );
    }
    $analysis = $data['choices'][0]['message']['content'] ?? '';

    if ( ! $analysis ) {
        error_log( 'GPT-4 missing content: ' . $body );
        return __( 'Could not generate analysis.', 'suzys-music-theme' );
    }

    return $analysis;
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
                $error_msg = isset( $upload['error'] ) ? $upload['error'] : __( 'There was an error uploading your file.', 'suzys-music-theme' );
                error_log( 'File upload error: ' . $error_msg );
                $error = $error_msg;
            }
        } else {
            $error = __( 'Please upload a valid MP3 file.', 'suzys-music-theme' );
        }
    } else {
        error_log( 'Upload failed code: ' . $file['error'] );
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
      <p id="loading-message" style="display:none;" class="pixel-font">Analyzing your track...</p>
    </form>
  </section>
</main>

<script>
  document.querySelector('.analyzer-form').addEventListener('submit', function(){
    var msg = document.getElementById('loading-message');
    if(msg){ msg.style.display = 'block'; }
  });
</script>

<?php get_footer(); ?>
