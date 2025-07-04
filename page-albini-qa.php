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
      <button id="albini-random" title="Random quote">🎲</button>
    </div>

    <!-- Response box -->
      <div id="albini-response" class="qa-response">
        <!-- Albini’s answer will appear here -->
      </div>
      <!-- Speech now plays automatically -->

  </section>

</main>


<script>
document.addEventListener('DOMContentLoaded', () => {
  const qEl = document.getElementById('albini-question');
  const btn = document.getElementById('albini-submit');
  const randomBtn = document.getElementById('albini-random');
  const resp = document.getElementById('albini-response');

  const albiniQuotes = [
    "Record like you mean it. Edit like you don’t care.",
    "The best mic is the one you already have plugged in.",
    "Don’t EQ it. Move the mic.",
    "Analog tape never froze during a plugin update.",
    "Turn it up until it scares you, then back it off a little.",
    "You don’t need a compressor. You need to play tighter.",
    "No one ever said, 'Man, that mix needed more automation.'",
    "The click track isn’t the problem. You are.",
    "Stop asking what mic to use. Point something at it and hit record.",
    "If you’re waiting for Spotify royalties to pay rent, I have bad news.",
    "Managers are parasites. Work with people, not leeches.",
    "A record deal is a loan shark with a press kit.",
    "You’ll earn more playing one honest gig than from 10,000 streams.",
    "The label will take the master. Keep the friends.",
    "You don’t own your music if you owe someone money for it.",
    "Nobody owes you attention.",
    "Be good to your bandmates. They’re the only ones who’ll carry your amp.",
    "DIY doesn’t mean amateur. It means accountable.",
    "Art isn’t a competition unless you’re insecure.",
    "Being broke together is better than selling out alone.",
    "Sure, put another fuzz pedal on it. That’ll fix your songwriting.",
    "Stop tweaking the hi-hat and write a chorus.",
    "Do you think Fugazi worried about their social media engagement?",
    "Your bedroom demo has more heart than your $1000/day studio session.",
    "Loud guitars solve everything except your relationship problems.",
    "No one cares what DAW you use except you.",
    "Pro Tools isn’t your enemy. Your taste is."
  ];

  const preambles = [
    "Look.",
    "Honestly?",
    "No.",
    "Sure, fine.",
    "Why are you wasting your time asking me that?",
    "You already know the answer.",
    "Stop overthinking it.",
    "Hell if I know, but here’s what I’d do:",
    ""
  ];

  function randomQuote() {
    const pre = preambles[Math.floor(Math.random() * preambles.length)];
    const quote = albiniQuotes[Math.floor(Math.random() * albiniQuotes.length)];
    return `${pre} ${quote}`.trim();
  }

  function typeText(id, text) {
    const el = document.getElementById(id);
    el.textContent = '';
    let i = 0;
    const typing = setInterval(() => {
      el.textContent += text[i++];
      if (i >= text.length) clearInterval(typing);
    }, 30);
  }

  function showQuote() {
    const text = randomQuote();
    speakAlbini(text);
    typeText('albini-response', text);
  }

  function speakAlbini(text) {
    if (!text) return;
    const synth = window.speechSynthesis;
    const voices = synth.getVoices();
    const utterance = new SpeechSynthesisUtterance(text);
    const preferred = voices.find(v => v.name.includes('Google UK English Male') || v.name.includes('Microsoft David'));
    utterance.voice = preferred || voices.find(v => /en/i.test(v.lang));
    utterance.pitch = 0.7;
    utterance.rate = 0.7;
    synth.cancel();
    synth.speak(utterance);
  }

  btn.addEventListener('click', () => {
    if (!qEl.value.trim()) return;
    showQuote();
  });

  randomBtn.addEventListener('click', showQuote);
});
</script>
<p style="text-align:center;">🎶 <a href="https://suzyeaston.bandcamp.com" target="_blank">Support my music on Bandcamp</a></p>
<p style="text-align:center;">New demo drops this weekend. Stay noisy.</p>
<?php get_footer(); ?>
