<?php
/*
Template Name: Track Analyzer
*/

get_header();

/**
 * Track Analyzer helpers
 *
 * Requires the OPENAI_API_KEY constant to be defined in wp-config.php or the
 * theme's functions.php file:
 *
 *     define( 'OPENAI_API_KEY', 'your-openai-api-key' );
 */

/**
 * Upload the file and return its path or WP_Error on failure.
 */
function upload_track_file( array $file ) {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( UPLOAD_ERR_OK !== $file['error'] ) {
        return new WP_Error( 'upload_error', __( 'Upload failed. Please try again.', 'suzys-music-theme' ) );
    }

    $type = wp_check_filetype( $file['name'], [ 'mp3' => 'audio/mpeg' ] );
    if ( ! $type['ext'] ) {
        return new WP_Error( 'invalid_format', __( 'Please upload a valid MP3 file.', 'suzys-music-theme' ) );
    }

    $upload = wp_handle_upload( $file, [ 'test_form' => false ] );
    if ( isset( $upload['file'] ) ) {
        return $upload['file'];
    }

    $msg = isset( $upload['error'] ) ? $upload['error'] : __( 'There was an error uploading your file.', 'suzys-music-theme' );
    return new WP_Error( 'upload_error', $msg );
}

/**
 * Send audio file to Whisper API and return the transcript or WP_Error.
 */
function whisper_transcribe( $filepath ) {
    if ( ! file_exists( $filepath ) ) {
        return new WP_Error( 'file_missing', __( 'Uploaded file missing on server.', 'suzys-music-theme' ) );
    }

    if ( ! function_exists( 'curl_file_create' ) ) {
        return new WP_Error( 'curl_missing', __( 'Server misconfiguration: cURL support missing.', 'suzys-music-theme' ) );
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
        $err = curl_error( $ch );
        curl_close( $ch );
        error_log( 'Whisper cURL error: ' . $err );
        return new WP_Error( 'whisper_curl', __( 'Failed to transcribe audio.', 'suzys-music-theme' ) . ' ' . $err );
    }

    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );
    error_log( 'Whisper response (' . $code . '): ' . substr( $body, 0, 200 ) );

    if ( 200 !== $code ) {
        error_log( 'Whisper API HTTP ' . $code . ': ' . $body );
        return new WP_Error( 'whisper_http', sprintf( __( 'Audio transcription failed (HTTP %s).', 'suzys-music-theme' ), $code ) );
    }

    $data = json_decode( $body, true );
    if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
        error_log( 'Whisper JSON error: ' . json_last_error_msg() . ' - ' . $body );
        return new WP_Error( 'whisper_json', __( 'Invalid transcription response.', 'suzys-music-theme' ) );
    }

    $text = $data['text'] ?? '';
    if ( ! $text ) {
        error_log( 'Whisper API missing text: ' . $body );
        return new WP_Error( 'whisper_empty', __( 'Audio transcription failed.', 'suzys-music-theme' ) );
    }

    return $text;
}

/**
 * Send transcript to GPT-4 and return analysis or WP_Error.
 */
function gpt4_analyze( $transcript ) {
    $prompt = 'Provide a playful yet insightful critique of this track. Channel the futuristic vibes of Grimes with the dance-floor savvy of James Murphy. Transcript: ' . $transcript;

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
        return new WP_Error( 'gpt_request', __( 'Analysis request failed.', 'suzys-music-theme' ) );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    error_log( 'GPT response (' . $code . '): ' . substr( $body, 0, 200 ) );

    if ( 200 !== $code ) {
        error_log( 'GPT-4 HTTP ' . $code . ': ' . $body );
        return new WP_Error( 'gpt_http', sprintf( __( 'Analysis failed (HTTP %s).', 'suzys-music-theme' ), $code ) );
    }

    $data = json_decode( $body, true );
    if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
        error_log( 'GPT JSON error: ' . json_last_error_msg() . ' - ' . $body );
        return new WP_Error( 'gpt_json', __( 'Invalid analysis response.', 'suzys-music-theme' ) );
    }

    $analysis = $data['choices'][0]['message']['content'] ?? '';
    if ( ! $analysis ) {
        error_log( 'GPT-4 missing content: ' . $body );
        return new WP_Error( 'gpt_missing', __( 'Could not generate analysis.', 'suzys-music-theme' ) );
    }

    return $analysis;
}

/**
 * Orchestrate upload -> transcription -> analysis.
 */
function analyze_audio( $filepath ) {
    if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
        return new WP_Error( 'no_key', __( 'OpenAI API key is not configured.', 'suzys-music-theme' ) );
    }

    $text     = whisper_transcribe( $filepath );
    if ( is_wp_error( $text ) ) {
        return $text;
    }

    return gpt4_analyze( $text );
}

$analysis = '';
$error    = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_FILES['track_file'] ) ) {
    $upload_result = upload_track_file( $_FILES['track_file'] );

    if ( is_wp_error( $upload_result ) ) {
        $error = $upload_result->get_error_message();
    } else {
        $analysis_result = analyze_audio( $upload_result );
        if ( is_wp_error( $analysis_result ) ) {
            $error = $analysis_result->get_error_message();
        } else {
            $analysis = $analysis_result;
        }
        @unlink( $upload_result );
    }
}
?>

<main id="main-content">
  <header class="analyzer-header pixel-font">
    <span class="page-title">Suzy's Track Analyzer</span>
    <div class="header-actions">
      <button id="reset-button" class="pixel-button">Reset</button>
      <button id="music-toggle" class="pixel-button">Play Music</button>
    </div>
    <audio id="bg-music" src="<?php echo get_template_directory_uri(); ?>/assets/bg-loop.mp3" loop></audio>
  </header>
  <section class="page-content track-analyzer">
    <h1 class="pixel-font">Suzy's Track Analyzer &ndash; AI Vibe Checker</h1>
    <div class="intro-text pixel-font">
      <p>Curious how your song stacks up?</p>
      <p>Drop an MP3 below and I’ll channel the data ghosts, sift through the dream logic and decode future club signals.</p>
    </div>

    <?php if ( $error ) : ?>
      <p class="error-message"><?php echo esc_html( $error ); ?></p>
    <?php endif; ?>

    <?php if ( $analysis ) : ?>
      <div class="analysis-result fade-in">
        <p><?php echo esc_html( $analysis ); ?></p>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="analyzer-form">
      <label for="track_file">Upload MP3 File</label>
      <input type="file" name="track_file" id="track_file" accept=".mp3,audio/mpeg" required>
      <button type="submit" class="pixel-button">Analyze Track</button>
      <p id="loading-message" style="display:none;" class="pixel-font">Analyzing your track<span class="loading-dots"></span></p>
    </form>
    <canvas id="analyzer-bg"></canvas>
  </section>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var form   = document.querySelector('.analyzer-form');
    var msg    = document.getElementById('loading-message');
    var result = document.querySelector('.analysis-result');
    var reset  = document.getElementById('reset-button');
    var music  = document.getElementById('music-toggle');
    var audio  = document.getElementById('bg-music');

    if (form) {
      form.addEventListener('submit', function() {
        if (msg) msg.style.display = 'block';
      });
    }

    if (result) {
      result.scrollIntoView({ behavior: 'smooth' });
    }

    if (reset) {
      reset.addEventListener('click', function() {
        if (form) form.reset();
        if (result) result.innerHTML = '';
        if (msg) msg.style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    if (music && audio) {
      music.addEventListener('click', function() {
        if (audio.paused) {
          audio.play();
          music.textContent = 'Stop Music';
        } else {
          audio.pause();
          music.textContent = 'Play Music';
        }
      });
    }

    // simple waveform background
    var canvas = document.getElementById('analyzer-bg');
    if (canvas) {
      var ctx = canvas.getContext('2d');
      var width, height, t = 0;
      function resize() {
        width = canvas.width  = canvas.parentElement.offsetWidth;
        height = canvas.height = canvas.parentElement.offsetHeight;
      }
      function draw() {
        ctx.clearRect(0,0,width,height);
        ctx.beginPath();
        for (var x=0; x<width; x++) {
          var y = height/2 + Math.sin((x + t)/20)*20;
          ctx.lineTo(x, y);
        }
        ctx.strokeStyle = 'rgba(0,255,255,0.4)';
        ctx.stroke();
        t += 2;
        requestAnimationFrame(draw);
      }
      window.addEventListener('resize', resize);
      resize();
      draw();
    }
  });
</script>

<?php get_footer(); ?>
