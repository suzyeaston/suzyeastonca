<?php
/* Template Name: Albini Q&A */
get_header();
$albini_rest_nonce = wp_create_nonce( 'wp_rest' );
se_ai_enqueue_turnstile_script();
?>

<main id="albini-main" class="albini-qa-page">

  <!-- Header Section -->
  <section class="albini-header">
    <h1 class="albini-title">What Would Steve Albini Do?</h1>
    <p class="albini-subtitle">Ask your question and we’ll surface real Steve Albini quotes plus a bit of context.</p>
  </section>

  <!-- Q&A Widget Section -->
  <section class="albini-qa-container">

    <!-- Input area -->
    <div class="qa-input">
      <textarea id="albini-question"
                placeholder="Type your question here…"
                rows="4"></textarea>
      <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" autocomplete="off" tabindex="-1" />
      </div>
      <?php echo se_ai_get_turnstile_widget_html( 'albini_ask' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <button id="albini-submit">Ask Albini</button>
      <button id="albini-random" title="Try a sample question">🎲</button>
    </div>

    <!-- Response box -->
      <div id="albini-response" class="qa-response">
        <!-- Albini’s answer will appear here -->
      </div>
      <!-- Speech now plays automatically -->

  </section>

</main>


<script>
// Albini Q&A client-side behavior (pulls curated quotes + neutral commentary)
document.addEventListener('DOMContentLoaded', () => {
  const albiniNonce = '<?php echo esc_js( $albini_rest_nonce ); ?>';
  const qEl = document.getElementById('albini-question');
  const btn = document.getElementById('albini-submit');
  const randomBtn = document.getElementById('albini-random');
  const resp = document.getElementById('albini-response');
  const honeypotEl = document.getElementById('website');

  function getTurnstileToken() {
    const input = document.querySelector('input[name="cf-turnstile-response"]');
    return input ? (input.value || '') : '';
  }

  function resetTurnstileIfPresent() {
    if (!window.turnstile || typeof window.turnstile.reset !== 'function') return;
    const widget = document.querySelector('.cf-turnstile');
    if (widget && widget.id) {
      try { window.turnstile.reset(widget.id); return; } catch (e) {}
    }
    try { window.turnstile.reset(); } catch (e) {}
  }

  const sampleQuestions = [
    'How should my band think about signing with a label?',
    'Is it worth chasing streaming royalties or should we focus on shows?',
    'What does Albini think about engineers acting like producers?',
    'How do we keep our recordings honest without big budgets?'
  ];

  function escapeHTML(str) {
    const safe = (str === undefined || str === null) ? '' : String(str);
    return safe.replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[c]));
  }

  function setLoading(message) {
    resp.innerHTML = `<p class="albini-loading">${message}</p>`;
  }

  function renderQuotes(quotes) {
    if (!quotes || !quotes.length) {
      return '<p class="albini-empty">No quotes matched, but here are some to explore.</p>';
    }

    const items = quotes.map((q) => {
      const source = q.source ? ` — Steve Albini, ${escapeHTML(q.source)}` : ' — Steve Albini';
      const year = q.year ? ` (${escapeHTML(String(q.year))})` : '';
      return `
        <li class="albini-quote">
          <blockquote>“${escapeHTML(q.quote)}”</blockquote>
          <p class="albini-quote-meta">${source}${year}</p>
        </li>
      `;
    }).join('');

    return `<ul class="albini-quote-list">${items}</ul>`;
  }

  function speakResponse(text) {
    if (!text || !('speechSynthesis' in window)) return;
    const synth = window.speechSynthesis;
    const utterance = new SpeechSynthesisUtterance(text);
    let voices = synth.getVoices();
    const pickVoice = () => {
      voices = synth.getVoices();
      const preferred = voices.find(v =>
        v.name.includes('Google UK English Male') ||
        v.name.includes('Microsoft David') ||
        /en/i.test(v.lang)
      );
      utterance.voice = preferred || null;
      utterance.rate = 0.9;
      utterance.pitch = 0.9;
      synth.cancel();
      synth.speak(utterance);
    };
    if (!voices || voices.length === 0) {
      synth.addEventListener('voiceschanged', pickVoice, { once: true });
    } else {
      pickVoice();
    }
  }

  function renderResponse(data) {
    const quotes = data.quotes || [];
    const commentary = data.commentary || '';

    resp.innerHTML = `
      <section class="albini-response">
        <h3 class="albini-response-heading">From the archives: Steve Albini on this</h3>
        ${renderQuotes(quotes)}
        ${commentary ? `<div class="albini-commentary"><h4>Why these quotes</h4><p>${escapeHTML(commentary)}</p></div>` : ''}
      </section>
    `;
    if (commentary) {
      speakResponse(commentary);
    } else if (quotes && quotes.length) {
      speakResponse(quotes[0].quote);
    }
  }

  async function askAlbini(question) {
    setLoading('Looking up real Albini quotes…');
    btn.disabled = true;
    randomBtn.disabled = true;

    try {
      const result = await fetch('/wp-json/albini/v1/ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': albiniNonce },
        body: JSON.stringify({
          question,
          website: honeypotEl ? honeypotEl.value : '',
          'cf-turnstile-response': getTurnstileToken()
        })
      });

      if (!result.ok) {
        if (result.status === 429) {
          throw new Error('This tool is cooling down for a bit. Try again later.');
        }
        throw new Error('The AI layer is offline, but the fallback version still works.');
      }

      const data = await result.json();
      renderResponse(data);
      resetTurnstileIfPresent();
    } catch (err) {
      resp.innerHTML = `<p class="albini-error">${err.message}</p>`;
      resetTurnstileIfPresent();
    } finally {
      btn.disabled = false;
      randomBtn.disabled = false;
    }
  }

  btn.addEventListener('click', () => {
    const question = qEl.value.trim();
    if (!question) {
      resp.innerHTML = '<p class="albini-error">Please enter a question.</p>';
      return;
    }
    askAlbini(question);
  });

  randomBtn.addEventListener('click', () => {
    const example = sampleQuestions[Math.floor(Math.random() * sampleQuestions.length)];
    qEl.value = example;
    askAlbini(example);
  });
});
</script>
<p style="text-align:center;">🎶 <a href="https://suzyeaston.bandcamp.com" target="_blank">Support my music on Bandcamp</a></p>
<p style="text-align:center;">New demo drops this weekend. Stay noisy.</p>
<?php get_footer(); ?>
