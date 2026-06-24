<?php
/*
Template Name: Track Analyzer
*/

get_header();
se_ai_enqueue_turnstile_script();

function se_track_analyzer_upload_track_file( array $file ) {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( UPLOAD_ERR_OK !== $file['error'] ) {
        return new WP_Error( 'upload_error', __( 'Upload failed. Please try again.', 'suzys-music-theme' ) );
    }

    $max_size = 8 * 1024 * 1024;
    if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_size ) {
        return new WP_Error( 'file_too_large', __( 'For now, uploads are limited to MP3 files under 8 MB.', 'suzys-music-theme' ) );
    }

    $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'mp3' => 'audio/mpeg' ) );
    if ( empty( $checked['ext'] ) || 'mp3' !== $checked['ext'] || empty( $checked['type'] ) || 'audio/mpeg' !== $checked['type'] ) {
        return new WP_Error( 'invalid_format', __( 'For now, uploads are limited to MP3 files under 8 MB.', 'suzys-music-theme' ) );
    }

    $upload = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => array( 'mp3' => 'audio/mpeg' ) ) );
    if ( isset( $upload['file'] ) ) {
        return $upload['file'];
    }

    return new WP_Error( 'upload_error', __( 'There was an error uploading your file.', 'suzys-music-theme' ) );
}

function se_track_analyzer_whisper_transcribe( $filepath ) {
    if ( ! file_exists( $filepath ) ) {
        return new WP_Error( 'file_missing', __( 'Uploaded file missing on server.', 'suzys-music-theme' ) );
    }
    if ( ! function_exists( 'curl_file_create' ) ) {
        return new WP_Error( 'curl_missing', __( 'Server misconfiguration: cURL support missing.', 'suzys-music-theme' ) );
    }

    $file_resource = curl_file_create( $filepath, 'audio/mpeg', basename( $filepath ) );
    $ch = curl_init( 'https://api.openai.com/v1/audio/transcriptions' );
    curl_setopt_array( $ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => array( 'Authorization: Bearer ' . OPENAI_API_KEY ),
        CURLOPT_POSTFIELDS     => array(
            'model'           => 'whisper-1',
            'file'            => $file_resource,
            'response_format' => 'json',
        ),
    ) );

    $body = curl_exec( $ch );
    if ( false === $body ) {
        curl_close( $ch );
        return new WP_Error( 'whisper_curl', __( 'Failed to transcribe audio.', 'suzys-music-theme' ) );
    }
    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    if ( 200 !== $code ) {
        return new WP_Error( 'whisper_http', __( 'Audio transcription failed.', 'suzys-music-theme' ) );
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) || empty( $data['text'] ) ) {
        return new WP_Error( 'whisper_empty', __( 'Audio transcription failed.', 'suzys-music-theme' ) );
    }

    return se_ai_trim_text( $data['text'], se_ai_get_text_limit( 'track_transcript' ) );
}

function se_track_analyzer_gpt_analyze( $transcript ) {
    $prompt = 'You are a blunt, thoughtful music producer offering useful feedback. Analyze this song with a focus on emotional resonance, lyrics, structure, production choices, and artist development advice. Keep it practical and respectful. Transcript: ' . $transcript;

    $response = se_openai_chat(
        array( array( 'role' => 'user', 'content' => $prompt ) ),
        array(
            'feature'     => 'track_analyzer',
            'model'       => 'gpt-4o-mini',
            'max_tokens'  => 300,
            'temperature' => 0.7,
        ),
        array( 'timeout' => 20 )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $analysis = $response['choices'][0]['message']['content'] ?? '';
    if ( ! $analysis ) {
        return new WP_Error( 'gpt_missing', __( 'Could not generate analysis.', 'suzys-music-theme' ) );
    }

    return $analysis;
}

$analysis = '';
$error    = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['track_file'] ) ) {
    if ( ! isset( $_POST['se_track_analyzer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['se_track_analyzer_nonce'] ) ), 'se_track_analyzer_upload' ) ) {
        $error = __( 'Security check failed. Please refresh and try again.', 'suzys-music-theme' );
    } elseif ( ! empty( $_POST['website'] ) ) {
        $analysis = __( 'Thanks! Your track is in the queue. Try again shortly for a fresh analysis.', 'suzys-music-theme' );
    } elseif ( ! se_ai_should_use_openai( 'track_analyzer' ) || ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
        $error = __( 'The AI layer is offline, but the fallback version still works.', 'suzys-music-theme' );
    } else {
        $turnstile_token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
        $turnstile = se_ai_verify_turnstile_token( $turnstile_token, 'track_analyzer' );
        if ( is_wp_error( $turnstile ) ) {
            $error = __( 'Please complete the human check and try again.', 'suzys-music-theme' );
        } else {
        $rl = se_ai_rate_limit( 'track_analyzer', 3, HOUR_IN_SECONDS );
        $daily = se_ai_daily_limit( 'track_analyzer', se_ai_get_daily_limit( 'track_analyzer', 20 ) );
        if ( is_wp_error( $rl ) || is_wp_error( $daily ) ) {
            $error = __( 'Track Analyzer is cooling down. Try again later, or email Suzy if you want help with a specific track.', 'suzys-music-theme' );
        } else {
            $upload_result = se_track_analyzer_upload_track_file( $_FILES['track_file'] );
            if ( is_wp_error( $upload_result ) ) {
                $error = $upload_result->get_error_message();
            } else {
                $analysis_result = null;
                try {
                    $text = se_track_analyzer_whisper_transcribe( $upload_result );
                    if ( is_wp_error( $text ) ) {
                        $analysis_result = $text;
                    } else {
                        $analysis_result = se_track_analyzer_gpt_analyze( $text );
                    }
                } finally {
                    if ( file_exists( $upload_result ) ) {
                        @unlink( $upload_result );
                    }
                }

                if ( is_wp_error( $analysis_result ) ) {
                    $show_debug = isset( $_GET['ta_debug'] ) && '1' === $_GET['ta_debug'] && current_user_can( 'manage_options' );
                    $error = $show_debug ? sprintf( '%s %s', __( 'Analysis request failed.', 'suzys-music-theme' ), $analysis_result->get_error_message() ) : __( 'Track Analyzer is cooling down. Try again later, or email Suzy if you want help with a specific track.', 'suzys-music-theme' );
                } else {
                    $analysis = $analysis_result;
                }
            }
        }
        }
    }
}
?>

<main id="main-content">
  <header class="analyzer-header pixel-font">
    <span class="page-title">Suzy’s Track Analyzer – Song Feedback Console</span>
    <div class="header-actions">
      <button id="reset-button" class="pixel-button">Reset</button>
    </div>
  </header>
  <div id="loading-overlay" class="loading-overlay pixel-font" style="display:none;">Reading the song-shaped object...<span class="loading-dots"></span></div>
  <section class="page-content track-analyzer">
    <h1 class="pixel-font title-flicker">Suzy’s Track Analyzer – Song Feedback Console</h1>
    <div class="intro-text pixel-font">
      <p>Got a rough mix that needs honest ears?</p>
      <p>Upload an MP3 and get practical notes on feel, lyrics, structure, production choices, and what might help the song land harder.</p>
      <p>For now, uploads are limited to MP3 files under 8 MB.</p>
    </div>
<?php
  $quotes = array(
    "The future is a mixtape with bad file names.",
    "Keep your frequencies weird, but label your tracks.",
    "Less polish, more intent."
  );
  $oracle_quote = $quotes[array_rand($quotes)];
?>
<p class="oracle-quote pixel-font"><?php echo esc_html( $oracle_quote ); ?></p>

    <?php if ( $error ) : ?>
      <p class="error-message"><?php echo esc_html( $error ); ?></p>
    <?php endif; ?>

    <?php if ( $analysis ) : ?>
      <div class="analysis-result fade-in">
        <p><?php echo nl2br( esc_html( $analysis ) ); ?></p>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="analyzer-form">
      <?php wp_nonce_field( 'se_track_analyzer_upload', 'se_track_analyzer_nonce' ); ?>
      <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" name="website" id="website" autocomplete="off" tabindex="-1">
      </div>
      <label for="track_file">Upload MP3 File</label>
      <input type="file" name="track_file" id="track_file" accept=".mp3,audio/mpeg" required>
      <?php echo se_ai_get_turnstile_widget_html( 'track_analyzer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <button type="submit" class="pixel-button">Analyze track</button>
      <p id="loading-message" style="display:none;" class="pixel-font">Reading the song-shaped object...<span class="loading-dots"></span></p>
    </form>
    <canvas id="analyzer-bg"></canvas>
  </section>
</main>

<?php get_footer(); ?>
