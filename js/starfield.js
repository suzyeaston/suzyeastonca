(function () {
  'use strict';

  var canvas = document.getElementById('starfield');
  if (!canvas) return;

  var ctx = canvas.getContext('2d');
  var stars = [];
  var width = 0;
  var height = 0;
  var parallaxRange = 50;
  var starSpeed = 2;
  var offsetX = 0;
  var offsetY = 0;
  var maxDepth = 0;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var mediaQuery = window.matchMedia('(max-width: 720px)');

  function init() {
    resize();
    seedStars();
    if (reduceMotion) {
      drawStatic();
      return;
    }
    requestAnimationFrame(update);
  }

  function seedStars() {
    var target = getStarCount();
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
      z: reduceMotion ? maxDepth * 0.5 : Math.random() * maxDepth,
      o: 0.45 + Math.random() * 0.55
    };
  }

  function resize() {
    var ratio = Math.min(window.devicePixelRatio || 1, 2);
    width = canvas.clientWidth || window.innerWidth;
    height = canvas.clientHeight || window.innerHeight;
    maxDepth = Math.max(width, height);
    parallaxRange = mediaQuery.matches ? 26 : 50;
    starSpeed = mediaQuery.matches ? 1.15 : 2;

    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    seedStars();
    if (reduceMotion) drawStatic();
  }

  function getStarCount() {
    var density = mediaQuery.matches ? 0.5 : 1;
    var area = Math.max(width * height, 1);
    var scaled = Math.round((area / 9000) * density);
    return Math.min(Math.max(scaled, 160), 380);
  }

  function drawStarAt(s) {
    var k = 120 / Math.max(s.z, 0.2);
    var px = (s.x - width / 2 + offsetX) * k + width / 2;
    var py = (s.y - height / 2 + offsetY) * k + height / 2;
    var size = Math.max((1 - s.z / maxDepth) * 3.2, 0.75);

    ctx.beginPath();
    ctx.fillStyle = 'rgba(255,255,255,' + s.o.toFixed(2) + ')';
    ctx.arc(px, py, size, 0, Math.PI * 2);
    ctx.fill();
  }

  function drawStatic() {
    ctx.clearRect(0, 0, width, height);
    stars.forEach(drawStarAt);
  }

  function update() {
    ctx.clearRect(0, 0, width, height);
    for (var i = 0; i < stars.length; i++) {
      var s = stars[i];
      s.z -= starSpeed;
      if (s.z <= 0.2) {
        Object.assign(s, randomStar());
        s.z = maxDepth;
      }
      drawStarAt(s);
    }
    requestAnimationFrame(update);
  }

  function onMove(e) {
    if (reduceMotion) return;
    var rect = canvas.getBoundingClientRect();
    var x = (e.clientX - rect.left) / rect.width;
    var y = (e.clientY - rect.top) / rect.height;
    offsetX = (x - 0.5) * parallaxRange;
    offsetY = (y - 0.5) * parallaxRange;
  }

  function onTilt(e) {
    if (reduceMotion) return;
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
