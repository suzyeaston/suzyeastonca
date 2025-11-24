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
    $prompt = 'You are legendary producer Rick Rubin offering thoughtful insight. Analyze this song with a focus on emotional resonance, creativity, production quality and artist development advice. Transcript: ' . $transcript;

    $response = se_openai_chat(
        [ [ 'role' => 'user', 'content' => $prompt ] ],
        [
            'model'       => 'gpt-4o',
            'max_tokens'  => 150,
            'timeout'     => 60,
            'temperature' => 0.7,
        ]
    );

    if ( is_wp_error( $response ) ) {
        error_log( 'GPT-4 request error: ' . $response->get_error_message() );
        return new WP_Error( 'gpt_request', __( 'Analysis request failed.', 'suzys-music-theme' ) );
    }

    $analysis = $response['choices'][0]['message']['content'] ?? '';
    if ( ! $analysis ) {
        error_log( 'GPT-4 missing content: ' . wp_json_encode( $response ) );
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
    </div>
  </header>
  <div id="loading-overlay" class="loading-overlay pixel-font" style="display:none;">Analyzing track<span class="loading-dots"></span></div>
  <section class="page-content track-analyzer">
    <h1 class="pixel-font title-flicker">Suzy's Track Analyzer &ndash; Sonic Intel Console</h1>
    <div class="intro-text pixel-font">
      <p>Curious how your song stacks up?</p>
      <p>Drop your track, and let\xE2\x80\x99s tap into the vibe—discover what resonates, what elevates, and what could transform your sound.</p>
    </div>
<?php
  $quotes = array(
    "\"The future's a mix tape.\" - Grimes",
    "\"Keep your frequencies weird.\" - DJ-3000",
    "\"Albini would hate this — perfect.\" - Club Oracle"
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
      <label for="track_file">Upload MP3 File</label>
      <input type="file" name="track_file" id="track_file" accept=".mp3,audio/mpeg" required>
      <button type="submit" class="pixel-button">Analyze Track</button>
      <p id="loading-message" style="display:none;" class="pixel-font">Decrypting audio stream<span class="loading-dots"></span></p>
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
    var overlay = document.getElementById('loading-overlay');

    if (form) {
      form.addEventListener('submit', function() {
        if (msg) {
          msg.style.display = 'block';
          msg.classList.add('hacking');
        }
        var btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
        if (overlay) overlay.style.display = 'flex';
        if (msg) {
          window.hexInterval = setInterval(function(){
            msg.textContent = 'Decrypting 0x' + Math.floor(Math.random()*0xffffff).toString(16);
          }, 200);
        }
      });
    }

      if (result) {
        if (window.hexInterval) clearInterval(window.hexInterval);
        if (overlay) overlay.style.display = 'none';
        result.classList.add('glow');
        result.scrollIntoView({ behavior: 'smooth' });
        var utter = new SpeechSynthesisUtterance(result.textContent);
        utter.pitch = 0.6;
        function setVoice(){
          for (var i = 0; i < voices.length; i++) {
            if (/UK.*Male|English.*Male/.test(voices[i].name)) { utter.voice = voices[i]; break; }
          }
        }
        var voices = speechSynthesis.getVoices();
        if (!voices.length) {
          speechSynthesis.addEventListener('voiceschanged', function handler(){
            voices = speechSynthesis.getVoices();
            setVoice();
            speechSynthesis.speak(utter);
            speechSynthesis.removeEventListener('voiceschanged', handler);
          });
        } else {
          setVoice();
          speechSynthesis.speak(utter);
        }
      }

    if (reset) {
      reset.addEventListener('click', function() {
        window.location.reload();
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
        var amp = 20 + 10*Math.sin(t/50);
        for (var x=0; x<width; x++) {
          var y = height/2 + Math.sin((x + t)/20)*amp;
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
var konami = [38,38,40,40,37,39,37,39,66,65];
    var buffer = [];
    window.addEventListener("keydown", function(e){
      buffer.push(e.keyCode);
      if(buffer.toString().indexOf(konami) >= 0){
        if(!document.getElementById("secret-track")){
          var s = document.createElement("input");
          s.type = "text";
          s.id = "secret-track";
          s.name = "track_name";
          s.placeholder = "Secret Track Name";
          s.className = "pixel-button";
          if(form) form.prepend(s);
        }
      }
      if(buffer.length > konami.length) buffer.shift();
    });
  });
</script>

<?php get_footer(); ?>
