/* Canvas FX engine shared by the live full-grid preview and final export. */
(function(root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.MosaicEffects = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function() {
  'use strict';
  const PRESETS = Object.freeze([
    { id: 'clean', name: 'Clean / original' },
    { id: 'noir', name: 'Noir / silver screen' },
    { id: 'ultraviolet', name: 'Ultraviolet / synth glow' },
    { id: 'daydream', name: 'Daydream / warm haze' },
    { id: 'vhs', name: 'VHS / magnetic ghosts' },
  ]);
  const ids = PRESETS.map(item => item.id);
  function preset(value) { return ids.includes(value) ? value : 'clean'; }
  function strength(value) {
    const n = Number(value);
    return Number.isFinite(n) ? Math.min(100, Math.max(0, Math.round(n))) : 65;
  }
  function filterFor(id, value) {
    const amount = strength(value) / 100;
    switch(preset(id)) {
      case 'noir': return `grayscale(${Math.round(amount * 100)}%) contrast(${(1 + amount * .42).toFixed(2)}) brightness(${(1 - amount * .06).toFixed(2)})`;
      case 'ultraviolet': return `saturate(${(1 + amount * 1.8).toFixed(2)}) hue-rotate(${Math.round(amount * 25)}deg) contrast(${(1 + amount * .28).toFixed(2)})`;
      case 'daydream': return `sepia(${Math.round(amount * 48)}%) saturate(${(1 + amount * .34).toFixed(2)}) brightness(${(1 + amount * .11).toFixed(2)})`;
      case 'vhs': return `saturate(${(1 - amount * .25).toFixed(2)}) contrast(${(1 + amount * .24).toFixed(2)})`;
      default: return 'none';
    }
  }
  // The entire finished grid is treated as one image. Raw recordings stay untouched.
  function draw(ctx, source, options = {}) {
    const w = ctx.canvas.width, h = ctx.canvas.height;
    const id = preset(options.preset);
    const amount = strength(options.strength) / 100;
    const seconds = Number.isFinite(options.seconds) ? options.seconds : 0;
    ctx.save();
    ctx.clearRect(0, 0, w, h);
    ctx.filter = filterFor(id, options.strength);
    ctx.drawImage(source, 0, 0, w, h);
    ctx.filter = 'none';
    if (amount > 0 && id === 'ultraviolet') {
      ctx.globalCompositeOperation = 'screen';
      const glow = ctx.createLinearGradient(0, 0, w, h);
      glow.addColorStop(0, `rgba(84,23,221,${(.22 * amount).toFixed(3)})`);
      glow.addColorStop(.55, 'rgba(0,0,0,0)');
      glow.addColorStop(1, `rgba(255,43,143,${(.28 * amount).toFixed(3)})`);
      ctx.fillStyle = glow; ctx.fillRect(0, 0, w, h);
      ctx.globalCompositeOperation = 'source-over';
    }
    if (amount > 0 && id === 'daydream') {
      const haze = ctx.createLinearGradient(0, 0, w, h);
      haze.addColorStop(0, `rgba(255,187,173,${(.14 * amount).toFixed(3)})`);
      haze.addColorStop(1, `rgba(248,219,255,${(.20 * amount).toFixed(3)})`);
      ctx.fillStyle = haze; ctx.fillRect(0, 0, w, h);
    }
    if (amount > 0 && id === 'vhs') {
      ctx.fillStyle = `rgba(5,9,25,${(.23 * amount).toFixed(3)})`;
      const lineSpacing = Math.max(4, Math.round(h / 170));
      for(let y = 0; y < h; y += lineSpacing) ctx.fillRect(0, y, w, 1);
      const drift = ((seconds * 26) % (h + 70) + h + 70) % (h + 70);
      ctx.fillStyle = `rgba(240,233,255,${(.15 * amount).toFixed(3)})`;
      ctx.fillRect(0, drift - 35, w, Math.max(2, Math.round(h / 130)));
      ctx.fillStyle = `rgba(74,205,234,${(.09 * amount).toFixed(3)})`;
      ctx.fillRect(3, 0, Math.max(2, Math.round(w / 220)), h);
    }
    if (amount > 0 && id !== 'clean') {
      const vignette = ctx.createRadialGradient(w/2, h/2, w*.13, w/2, h/2, w*.76);
      vignette.addColorStop(0, 'rgba(0,0,0,0)');
      vignette.addColorStop(1, `rgba(5,3,18,${(.38 * amount).toFixed(3)})`);
      ctx.fillStyle = vignette; ctx.fillRect(0, 0, w, h);
    }
    ctx.restore();
  }
  return { PRESETS, preset, strength, filterFor, draw };
});
