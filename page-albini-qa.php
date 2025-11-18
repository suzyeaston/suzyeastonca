<?php
/* Template Name: Albini Q&A */
get_header();
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
  const qEl = document.getElementById('albini-question');
  const btn = document.getElementById('albini-submit');
  const randomBtn = document.getElementById('albini-random');
  const resp = document.getElementById('albini-response');

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
  }

  async function askAlbini(question) {
    setLoading('Looking up real Albini quotes…');
    btn.disabled = true;
    randomBtn.disabled = true;

    try {
      const result = await fetch('/wp-json/albini/v1/ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question })
      });

      if (!result.ok) {
        throw new Error('Request failed. Please try again.');
      }

      const data = await result.json();
      renderResponse(data);
    } catch (err) {
      resp.innerHTML = `<p class="albini-error">${err.message}</p>`;
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
