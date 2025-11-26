(function() {
  const canvas = document.getElementById('starfield');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  let stars = [];
  let width = 0;
  let height = 0;
  let parallaxRange = 50;
  let starSpeed = 2;
  let offsetX = 0;
  let offsetY = 0;
  let maxDepth = 0;

  const mediaQuery = window.matchMedia('(max-width: 720px)');

  function init() {
    resize();
    seedStars();
    requestAnimationFrame(update);
  }

  function seedStars() {
    const target = getStarCount();
    if (stars.length > target) {
      stars = stars.slice(0, target);
    }
    while (stars.length < target) {
      stars.push(randomStar());
    }
  }

  function randomStar() {
    return {
      x: Math.random() * width,
      y: Math.random() * height,
      z: Math.random() * maxDepth,
      o: 0.45 + Math.random() * 0.55 // opacity variation for softer glow
    };
  }

  function resize() {
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    width = canvas.clientWidth || window.innerWidth;
    height = canvas.clientHeight || window.innerHeight;
    maxDepth = Math.max(width, height);
    parallaxRange = mediaQuery.matches ? 26 : 50;
    starSpeed = mediaQuery.matches ? 1.15 : 2;

    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    seedStars();
  }

  function getStarCount() {
    const density = mediaQuery.matches ? 0.5 : 1;
    const area = Math.max(width * height, 1);
    const scaled = Math.round((area / 9000) * density);
    return Math.min(Math.max(scaled, 160), 380);
  }

  function update() {
    ctx.clearRect(0, 0, width, height);
    for (const s of stars) {
      s.z -= starSpeed;
      if (s.z <= 0.2) {
        Object.assign(s, randomStar());
        s.z = maxDepth;
      }

      const k = 120 / s.z;
      const px = (s.x - width / 2 + offsetX) * k + width / 2;
      const py = (s.y - height / 2 + offsetY) * k + height / 2;
      const size = Math.max((1 - s.z / maxDepth) * 3.2, 0.75);

      ctx.beginPath();
      ctx.fillStyle = `rgba(255,255,255,${s.o.toFixed(2)})`;
      ctx.arc(px, py, size, 0, Math.PI * 2);
      ctx.fill();
    }
    requestAnimationFrame(update);
  }

  function onMove(e) {
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    offsetX = (x - 0.5) * parallaxRange;
    offsetY = (y - 0.5) * parallaxRange;
  }

  function onTilt(e) {
    if (e.beta == null || e.gamma == null) return;
    offsetX = (e.gamma / 45) * (parallaxRange * 0.55);
    offsetY = (e.beta / 45) * (parallaxRange * 0.55);
  }

  mediaQuery.addEventListener('change', resize);
  window.addEventListener('resize', resize);
  canvas.addEventListener('mousemove', onMove);
  window.addEventListener('deviceorientation', onTilt);
  document.addEventListener('DOMContentLoaded', init);
})();
