(function (window) {
  'use strict';

  const VISUAL_TYPES = [
    'scanline_field', 'pixel_grid_pulse', 'wireframe_horizon', 'radial_bloom', 'particle_trail',
    'glitch_flash', 'waveform_ring', 'macro_texture_drift', 'signal_bars', 'text_reveal'
  ];

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

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

    createRenderTarget(width, height) {
      const c = document.createElement('canvas');
      c.width = width;
      c.height = height;
      const ctx = c.getContext('2d');
      return { canvas: c, ctx };
    }

    normalizeVisualTimeline(pkg) {
      const runtime = clamp(Number(pkg.runtime_seconds || 20), 10, 30);
      const events = Array.isArray(pkg.visual_events) ? pkg.visual_events : [];
      const visualEvents = events
        .filter((e) => e && VISUAL_TYPES.includes(e.visual_type))
        .map((e) => ({
          time: clamp(Number(e.time || 0), 0, runtime + 0.5),
          duration: clamp(Number(e.duration || 0.5), 0.04, 8),
          visual_type: e.visual_type,
          intensity: clamp(Number(e.intensity || 0.5), 0, 1),
          params: (e.params && typeof e.params === 'object') ? e.params : {},
          sync_role: String(e.sync_role || '')
        }))
        .sort((a, b) => a.time - b.time);

      const syncPoints = Array.isArray(pkg.sync_points) ? pkg.sync_points
        .map((p) => ({ time: clamp(Number(p.time || 0), 0, runtime + 0.5), cue: String(p.cue || ''), importance: String(p.importance || '') }))
        .sort((a, b) => a.time - b.time) : [];

      return {
        runtime,
        visualEvents,
        syncPoints,
        endCard: pkg.end_card || {},
        title: String(pkg.title || 'ASMR LAB')
      };
    }

    loadTimeline(pkg) {
      this.timeline = this.normalizeVisualTimeline(pkg || {});
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
      this.ctx.fillStyle = '#03060f';
      this.ctx.fillRect(0, 0, w, h);
    }

    loop() {
      if (!this.running) return;
      this.currentTime = (performance.now() - this.startPerf) / 1000;
      this.render(this.currentTime);
      if (this.currentTime >= this.timeline.runtime + 1.25) {
        this.stop();
        return;
      }
      this.rafId = requestAnimationFrame(this.loop.bind(this));
    }

    eventProgress(event, t) {
      const start = Number(event.time || 0);
      const duration = Math.max(0.01, Number(event.duration || 0.5));
      return (t - start) / duration;
    }

    renderToContext(ctx, width, height, t) {
      if (!ctx || !this.timeline) return;
      const runtime = this.timeline.runtime;
      const normalized = clamp(t / Math.max(1, runtime), 0, 1.2);

      this.drawBaseChamber(ctx, width, height, t, normalized);

      this.timeline.visualEvents.forEach((event) => {
        const progress = this.eventProgress(event, t);
        if (progress <= -0.03 || progress >= 1.2) return;
        const intensity = Math.max(0.05, Math.min(1, Number(event.intensity || 0.5)));
        this.drawEvent(ctx, event, progress, intensity, width, height, normalized);
      });

      this.drawSyncMarkers(ctx, t, width, height);
      this.drawScanlines(ctx, width, height, normalized);
      this.drawGlow(ctx, width, height, normalized);

      if (this.timeline.endCard && this.timeline.endCard.use_end_card && t > runtime - 1.6) {
        const p = Math.max(0, Math.min(1, (t - (runtime - 1.6)) / 1.4));
        this.drawEndCard(ctx, this.timeline.endCard, p, width, height);
      }
    }

    drawBaseChamber(ctx, w, h, t, normalized) {
      const horizon = h * 0.6;
      const pulse = 0.5 + 0.5 * Math.sin(t * 1.9);

      const bg = ctx.createLinearGradient(0, 0, 0, h);
      bg.addColorStop(0, '#02050c');
      bg.addColorStop(0.58, '#070d1e');
      bg.addColorStop(1, '#050911');
      ctx.fillStyle = bg;
      ctx.fillRect(0, 0, w, h);

      const radial = ctx.createRadialGradient(w * 0.52, h * 0.52, 20, w * 0.52, h * 0.54, Math.max(w, h) * 0.72);
      radial.addColorStop(0, `rgba(74,112,255,${0.1 + pulse * 0.05})`);
      radial.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = radial;
      ctx.fillRect(0, 0, w, h);

      ctx.strokeStyle = 'rgba(110,150,255,0.12)';
      for (let i = 0; i < 14; i += 1) {
        const p = i / 13;
        const y = horizon + Math.pow(p, 1.8) * (h - horizon);
        ctx.beginPath();
        ctx.moveTo(0, y + Math.sin(t * 0.8 + i) * 0.35);
        ctx.lineTo(w, y);
        ctx.stroke();
      }

      ctx.fillStyle = `rgba(120,220,255,${0.06 + normalized * 0.1})`;
      for (let i = 0; i < 46; i += 1) {
        const x = (i * 53 + t * (8 + (i % 5))) % w;
        const y = (i * 31 + Math.sin(t + i) * 10) % (h * 0.9);
        ctx.fillRect(x, y, 1.6, 1.6);
      }
    }

    render(t) {
      if (!this.ctx || !this.canvas || !this.timeline) return;
      const w = this.canvas.clientWidth || 640;
      const h = this.canvas.clientHeight || 360;
      this.renderToContext(this.ctx, w, h, t);
    }

    drawEvent(ctx, event, progress, intensity, w, h, normalized) {
      const params = event.params || {};
      const p = clamp(progress, 0, 1);
      switch (event.visual_type) {
        case 'pixel_grid_pulse': {
          const spacing = Number(params.spacing || 22);
          ctx.strokeStyle = `rgba(105,170,255,${0.06 + intensity * 0.24 * (1 - p)})`;
          for (let x = 0; x < w; x += spacing) {
            for (let y = 0; y < h; y += spacing) ctx.strokeRect(x, y, 1.5 + intensity, 1.5 + intensity);
          }
          break;
        }
        case 'wireframe_horizon': {
          ctx.strokeStyle = `rgba(180,220,255,${0.18 + intensity * 0.18})`;
          const horizon = h * 0.58;
          for (let i = 0; i < 16; i += 1) {
            const lp = i / 16;
            const y = horizon + Math.pow(lp, 1.5) * (h - horizon);
            ctx.beginPath();
            ctx.moveTo(w * 0.5, horizon);
            ctx.lineTo((i / 15) * w, y);
            ctx.stroke();
          }
          break;
        }
        case 'radial_bloom': {
          const r = (60 + p * (Math.max(w, h) * 0.55));
          const grad = ctx.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, r);
          grad.addColorStop(0, `rgba(190,244,255,${0.2 * intensity})`);
          grad.addColorStop(0.6, `rgba(128,176,255,${0.12 * intensity * (1 - p * 0.5)})`);
          grad.addColorStop(1, 'rgba(0,0,0,0)');
          ctx.fillStyle = grad;
          ctx.beginPath();
          ctx.arc(w / 2, h / 2, r, 0, Math.PI * 2);
          ctx.fill();
          break;
        }
        case 'particle_trail': {
          const count = Math.max(4, Math.floor(8 * intensity));
          ctx.fillStyle = `rgba(158,212,255,${0.18 + 0.22 * intensity})`;
          for (let i = 0; i < count; i += 1) {
            const px = (w * (0.2 + (i / count) * 0.6) + Math.sin((normalized * 11) + i) * 24);
            const py = (h * (0.25 + (i / count) * 0.52) + Math.cos((normalized * 9) + i) * 18);
            ctx.fillRect(px, py, 2, 2);
          }
          break;
        }
        case 'glitch_flash': {
          ctx.fillStyle = `rgba(220,245,255,${0.09 * (1 - p) * intensity})`;
          for (let i = 0; i < 3; i += 1) {
            ctx.fillRect((Math.sin(i + normalized * 13) * 0.45 + 0.5) * w * 0.6, (i * 0.22 + (normalized % 0.3)) * h, w * 0.45, 2 + (i * 3));
          }
          break;
        }
        case 'waveform_ring': {
          const radius = 30 + p * 220;
          ctx.strokeStyle = `rgba(122,234,255,${0.2 + 0.25 * intensity * (1 - p)})`;
          ctx.lineWidth = 1 + intensity * 2;
          ctx.beginPath();
          for (let a = 0; a <= Math.PI * 2 + 0.1; a += 0.08) {
            const wobble = Math.sin(a * 7 + p * 14) * (5 + intensity * 11);
            const rr = radius + wobble;
            const x = w / 2 + Math.cos(a) * rr;
            const y = h / 2 + Math.sin(a) * rr;
            if (a === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
          }
          ctx.stroke();
          break;
        }
        case 'macro_texture_drift': {
          ctx.fillStyle = `rgba(90,120,180,${0.05 + 0.08 * intensity})`;
          for (let i = 0; i < 52; i += 1) {
            const x = (i * 37 + normalized * 210 + Math.sin(i + normalized * 9) * 8) % w;
            const y = (i * 21 + normalized * 90) % h;
            ctx.fillRect(x, y, 22, 1);
          }
          break;
        }
        case 'signal_bars': {
          const bars = 9;
          for (let i = 0; i < bars; i += 1) {
            const bh = (0.08 + Math.abs(Math.sin((p + normalized) * 8 + i)) * 0.85) * h * 0.22;
            ctx.fillStyle = `rgba(120,255,205,${0.16 + intensity * 0.26})`;
            ctx.fillRect(24 + i * 20, h - 16 - bh, 12, bh);
          }
          break;
        }
        case 'scanline_field': {
          ctx.fillStyle = `rgba(105,160,255,${0.03 + intensity * 0.06})`;
          for (let y = 0; y < h; y += 4) ctx.fillRect(0, y + Math.sin(y * 0.02 + normalized * 18) * 0.35, w, 1);
          break;
        }
        case 'text_reveal': {
          const txt = String(params.text || 'SYSTEM READY');
          ctx.save();
          ctx.globalAlpha = Math.max(0, Math.min(1, p * 1.6)) * intensity;
          ctx.fillStyle = '#d8f4ff';
          ctx.shadowColor = 'rgba(160,240,255,0.9)';
          ctx.shadowBlur = 12;
          ctx.font = 'bold 30px monospace';
          ctx.fillText(txt, Math.max(18, w * 0.09), h * 0.5);
          ctx.restore();
          break;
        }
        default:
          break;
      }
    }

    drawSyncMarkers(ctx, t, w, h) {
      this.timeline.syncPoints.forEach((point) => {
        const dt = Math.abs(t - Number(point.time || 0));
        if (dt > 0.14) return;
        const alpha = (0.14 - dt) * 4.2;
        ctx.fillStyle = `rgba(255,220,160,${alpha})`;
        ctx.fillRect(0, h - 8, w, 2);
      });
    }

    drawScanlines(ctx, w, h, normalized) {
      ctx.fillStyle = 'rgba(132,172,255,0.042)';
      for (let y = 0; y < h; y += 3) ctx.fillRect(0, y + Math.sin(normalized * 30 + y * 0.02) * 0.2, w, 1);
    }

    drawGlow(ctx, w, h, normalized) {
      const g = ctx.createRadialGradient(w / 2, h / 2, 24, w / 2, h / 2, Math.max(w, h) * 0.88);
      g.addColorStop(0, `rgba(80,120,255,${0.07 + normalized * 0.07})`);
      g.addColorStop(1, 'rgba(0,0,0,0.44)');
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, w, h);
    }

    drawEndCard(ctx, endCard, progress, w, h) {
      ctx.fillStyle = `rgba(1,5,15,${0.5 * progress})`;
      ctx.fillRect(0, 0, w, h);
      ctx.globalAlpha = progress;
      ctx.fillStyle = '#dcf7ff';
      ctx.shadowColor = 'rgba(120,236,255,0.85)';
      ctx.shadowBlur = 14;
      ctx.font = 'bold 34px monospace';
      ctx.fillText(String(endCard.text || 'END SIGNAL'), w * 0.1, h * 0.52);
      ctx.globalAlpha = 1;
      ctx.shadowBlur = 0;
    }
  }

  window.AsmrVisualEngine = AsmrVisualEngine;
})(window);
