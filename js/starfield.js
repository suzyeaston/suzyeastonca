(function() {
  const canvas = document.getElementById('starfield');
  const ctx = canvas.getContext('2d');
  let stars = [], width, height;
  let offsetX = 0,
      offsetY = 0;

  function init() {
    resize();
    // create 250 stars
    for (let i = 0; i < 250; i++) {
      stars.push({
        x: Math.random() * width,
        y: Math.random() * height,
        z: Math.random() * width,      // depth
        o: Math.random()               // opacity variation
      });
    }
    requestAnimationFrame(update);
  }

  function resize() {
    width  = canvas.width  = window.innerWidth;
    height = canvas.height = window.innerHeight;
  }

  function update() {
    ctx.clearRect(0, 0, width, height);
    for (let s of stars) {
      // move toward viewer:
      s.z -= 2;
      if (s.z <= 0) {
        s.z = width;
        s.x = Math.random() * width;
        s.y = Math.random() * height;
      }
      // project 3D to 2D with parallax offset
      const k = 128.0 / s.z;
      const px = (s.x - width/2 + offsetX) * k + width/2;
      const py = (s.y - height/2 + offsetY) * k + height/2;
      const size = Math.max((1 - s.z/width) * 3, 0);
      ctx.beginPath();
      ctx.fillStyle = `rgba(255,255,255,${s.o})`;
      ctx.arc(px, py, size, 0, Math.PI*2);
      ctx.fill();
    }
    requestAnimationFrame(update);
  }

  function onMove(e) {
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    offsetX = (x - 0.5) * 50;
    offsetY = (y - 0.5) * 50;
  }

  function onTilt(e) {
    if (e.beta == null || e.gamma == null) return;
    offsetX = (e.gamma / 45) * 30;
    offsetY = (e.beta / 45) * 30;
  }

  window.addEventListener('resize', resize);
  canvas.addEventListener('mousemove', onMove);
  window.addEventListener('deviceorientation', onTilt);
  document.addEventListener('DOMContentLoaded', init);
})();
