<?php
/* Template Name: Albini Q&A */
get_header();
?>

<main id="albini-main" class="albini-qa-page">

  <!-- Header Section -->
  <section class="albini-header">
    <h1 class="albini-title">What Would Steve Albini Do?</h1>
    <p class="albini-subtitle">Ask the legend anything. He’ll answer in his signature no‑BS style.</p>
  </section>

  <!-- Q&A Widget Section -->
  <section class="albini-qa-container">

    <!-- Input area -->
    <div class="qa-input">
      <textarea id="albini-question"
                placeholder="Type your question here…"
                rows="4"></textarea>
      <button id="albini-submit">Ask Albini</button>
    </div>

    <!-- Response box -->
    <div id="albini-response" class="qa-response">
      <!-- Albini’s answer will appear here -->
    </div>

  </section>

</main>

<?php get_footer(); ?>

<!-- Inline script to handle the AJAX call -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const qEl = document.getElementById('albini-question');
  const btn = document.getElementById('albini-submit');
  const resp = document.getElementById('albini-response');

  btn.addEventListener('click', async () => {
    const question = qEl.value.trim();
    if (!question) return;

    btn.disabled = true;
    btn.textContent = 'Thinking…';
    resp.innerHTML = '';

    try {
      const r = await fetch('<?php echo esc_url( rest_url('albini/v1/ask') ); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question })
      });
      const data = await r.json();
      const answer = data.answer || 'Steve’s probably busy in the studio.';
      resp.innerHTML = '<p>' + answer.replace(/\n/g,'<br/>') + '</p>';
    } catch (err) {
      resp.textContent = 'Uh‑oh, something broke.';
    }

    btn.disabled = false;
    btn.textContent = 'Ask Albini';
  });
});
</script>
