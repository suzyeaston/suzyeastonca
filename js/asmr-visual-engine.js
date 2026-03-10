(function (window) {
  'use strict';

  const VISUAL_TYPES = [
    'scanline_field', 'pixel_grid_pulse', 'wireframe_horizon', 'radial_bloom', 'particle_trail',
    'glitch_flash', 'waveform_ring', 'macro_texture_drift', 'signal_bars', 'text_reveal'
  ];

  class AsmrVisualEngine {
    constructor(canvas) {
      this.canvas = canvas;
      this.ctx = canvas ? canvas.getContext('2d') : null;
      this.running = false;
      this.rafId = null;
      this.timeline = null;
      this.startPerf = 0;
      this.clockOffset = 0;
      this.currentTime = 0;
      this.particles = [];
    }

    setCanvas(canvas) {
      this.canvas = canvas;
      this.ctx = canvas ? canvas.getContext('2d') : null;
    }

    resize() {
      if (!this.canvas) return;
      const ratio = Math.max(1, window.devicePixelRatio || 1);
      const w = this.canvas.clientWidth || 640;
      const h = this.canvas.clientHeight || 360;
      this.canvas.width = Math.floor(w * ratio);
      this.canvas.height = Math.floor(h * ratio);
      if (this.ctx) this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    loadTimeline(pkg) {
      this.timeline = {
        runtime: Math.max(1, Number(pkg.runtime_seconds || 12)),
        visualEvents: Array.isArray(pkg.visual_events) ? pkg.visual_events.filter((e) => VISUAL_TYPES.includes(e.visual_type)) : [],
        syncPoints: Array.isArray(pkg.sync_points) ? pkg.sync_points : [],
        endCard: pkg.end_card || {}
      };
    }

    play(clockOffset) {
      if (!this.ctx || !this.timeline) return;
      this.stop();
      this.resize();
      this.clockOffset = Number(clockOffset || 0);
      this.startPerf = performance.now() - (this.clockOffset * 1000);
      this.running = true;
      this.loop();
    }

    stop() {
      this.running = false;
      if (this.rafId) {
        cancelAnimationFrame(this.rafId);
        this.rafId = null;
      }
      this.currentTime = 0;
      this.clearFrame();
    }

    seek(seconds) {
      this.currentTime = Math.max(0, Number(seconds || 0));
      this.render(this.currentTime);
    }

    clearFrame() {
      if (!this.ctx || !this.canvas) return;
      const w = this.canvas.clientWidth || 640;
      const h = this.canvas.clientHeight || 360;
      this.ctx.fillStyle = '#050812';
      this.ctx.fillRect(0, 0, w, h);
    }

    loop() {
      if (!this.running) return;
      this.currentTime = (performance.now() - this.startPerf) / 1000;
      this.render(this.currentTime);
      if (this.currentTime >= this.timeline.runtime + 1.2) {
        this.stop();
        return;
      }
      this.rafId = requestAnimationFrame(this.loop.bind(this));
    }

    eventProgress(event, t) {
      const start = Number(event.time || 0);
      const duration = Math.max(0.01, Number(event.duration || 0.5));
      const p = (t - start) / duration;
      return Math.max(0, Math.min(1, p));
    }

    render(t) {
      if (!this.ctx || !this.canvas || !this.timeline) return;
      const w = this.canvas.clientWidth || 640;
      const h = this.canvas.clientHeight || 360;
      const ctx = this.ctx;

      ctx.fillStyle = 'rgba(4,7,16,0.26)';
      ctx.fillRect(0, 0, w, h);

      this.timeline.visualEvents.forEach((event) => {
        const progress = this.eventProgress(event, t);
        if (progress <= 0 || progress >= 1.03) return;
        const intensity = Math.max(0.05, Math.min(1, Number(event.intensity || 0.5)));
        this.drawEvent(event, progress, intensity, w, h);
      });

      this.drawSyncMarkers(t, w, h);
      this.drawScanlines(w, h);
      this.drawGlow(w, h);

      if (this.timeline.endCard && this.timeline.endCard.use_end_card && t > this.timeline.runtime - 1.5) {
        const p = Math.max(0, Math.min(1, (t - (this.timeline.runtime - 1.5)) / 1.3));
        this.drawEndCard(this.timeline.endCard, p, w, h);
      }
    }

    drawEvent(event, progress, intensity, w, h) {
      const ctx = this.ctx;
      const params = event.params || {};
      switch (event.visual_type) {
        case 'pixel_grid_pulse': {
          const spacing = Number(params.spacing || 24);
          ctx.strokeStyle = `rgba(105,170,255,${0.08 + intensity * 0.25 * (1 - progress)})`;
          for (let x = 0; x < w; x += spacing) {
            for (let y = 0; y < h; y += spacing) ctx.strokeRect(x, y, 2, 2);
          }
          break;
        }
        case 'wireframe_horizon': {
          ctx.strokeStyle = `rgba(180,220,255,${0.25 * intensity})`;
          const horizon = h * 0.58;
          for (let i = 0; i < 18; i += 1) {
            const p = i / 18;
            const y = horizon + Math.pow(p, 1.6) * (h - horizon);
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y);
            ctx.stroke();
          }
          break;
        }
        case 'radial_bloom': {
          const r = (80 + progress * (Math.max(w, h) * 0.45));
          const grad = ctx.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, r);
          grad.addColorStop(0, `rgba(170,240,255,${0.2 * intensity})`);
          grad.addColorStop(1, 'rgba(0,0,0,0)');
          ctx.fillStyle = grad;
          ctx.beginPath();
          ctx.arc(w / 2, h / 2, r, 0, Math.PI * 2);
          ctx.fill();
          break;
        }
        case 'particle_trail': {
          for (let i = 0; i < 2; i += 1) {
            this.particles.push({ x: Math.random() * w, y: h * (0.2 + Math.random() * 0.6), vx: -1 + Math.random() * 2, life: 0.5 + Math.random() * 0.7 });
          }
          this.particles = this.particles.filter((p) => p.life > 0);
          ctx.fillStyle = `rgba(158,212,255,${0.4 * intensity})`;
          this.particles.forEach((p) => {
            p.x += p.vx;
            p.life -= 0.016;
            ctx.fillRect(p.x, p.y, 2, 2);
          });
          break;
        }
        case 'glitch_flash': {
          ctx.fillStyle = `rgba(220,245,255,${0.16 * (1 - progress) * intensity})`;
          ctx.fillRect(Math.random() * w * 0.2, Math.random() * h, w * (0.4 + Math.random() * 0.5), 3 + Math.random() * 12);
          break;
        }
        case 'waveform_ring': {
          const radius = 40 + progress * 180;
          ctx.strokeStyle = `rgba(122,234,255,${0.36 * intensity * (1 - progress)})`;
          ctx.lineWidth = 1 + intensity * 2;
          ctx.beginPath();
          for (let a = 0; a <= Math.PI * 2 + 0.1; a += 0.08) {
            const wobble = Math.sin(a * 8 + progress * 18) * (6 + intensity * 10);
            const rr = radius + wobble;
            const x = w / 2 + Math.cos(a) * rr;
            const y = h / 2 + Math.sin(a) * rr;
            if (a === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
          }
          ctx.stroke();
          break;
        }
        case 'macro_texture_drift': {
          ctx.fillStyle = `rgba(90,120,180,${0.08 * intensity})`;
          for (let i = 0; i < 40; i += 1) ctx.fillRect((i * 37 + progress * 80) % w, (i * 53) % h, 18, 1);
          break;
        }
        case 'signal_bars': {
          const bars = 8;
          for (let i = 0; i < bars; i += 1) {
            const bh = (0.1 + Math.abs(Math.sin(progress * 10 + i)) * 0.8) * h * 0.22;
            ctx.fillStyle = `rgba(120,255,205,${0.2 + intensity * 0.3})`;
            ctx.fillRect(24 + i * 22, h - 18 - bh, 14, bh);
          }
          break;
        }
        case 'scanline_field':
          break;
        case 'text_reveal': {
          const txt = String(params.text || 'SYSTEM READY');
          ctx.save();
          ctx.globalAlpha = Math.max(0, Math.min(1, progress * 1.5)) * intensity;
          ctx.fillStyle = '#d8f4ff';
          ctx.font = 'bold 28px monospace';
          ctx.fillText(txt, Math.max(18, w * 0.1), h * 0.52);
          ctx.restore();
          break;
        }
        default:
          break;
      }
    }

    drawSyncMarkers(t, w, h) {
      const ctx = this.ctx;
      this.timeline.syncPoints.forEach((p) => {
        const dt = Math.abs(t - Number(p.time || 0));
        if (dt > 0.16) return;
        ctx.fillStyle = `rgba(255,220,160,${(0.16 - dt) * 3})`;
        ctx.fillRect(0, h - 6, w, 2);
      });
    }

    drawScanlines(w, h) {
      const ctx = this.ctx;
      ctx.fillStyle = 'rgba(120,160,255,0.045)';
      for (let y = 0; y < h; y += 3) ctx.fillRect(0, y, w, 1);
    }

    drawGlow(w, h) {
      const ctx = this.ctx;
      const g = ctx.createRadialGradient(w / 2, h / 2, 30, w / 2, h / 2, Math.max(w, h) * 0.85);
      g.addColorStop(0, 'rgba(80,120,255,0.08)');
      g.addColorStop(1, 'rgba(0,0,0,0.35)');
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, w, h);
    }

    drawEndCard(endCard, progress, w, h) {
      const ctx = this.ctx;
      ctx.fillStyle = `rgba(1,5,15,${0.5 * progress})`;
      ctx.fillRect(0, 0, w, h);
      ctx.globalAlpha = progress;
      ctx.fillStyle = '#dcf7ff';
      ctx.font = 'bold 30px monospace';
      ctx.fillText(String(endCard.text || 'END SIGNAL'), w * 0.12, h * 0.52);
      ctx.globalAlpha = 1;
    }
  }

  window.AsmrVisualEngine = AsmrVisualEngine;
})(window);
