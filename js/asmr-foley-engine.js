(function (window) {
  'use strict';

  const ENGINE_NAMES = [
    'glissando_rise', 'synth_bloom', 'sub_swell', 'filtered_noise_wash', 'ceramic_tick',
    'paper_crackle', 'steam_hiss', 'tape_stop_drop', 'bit_pulse', 'breath_pulse',
    'low_hum', 'digital_shimmer', 'glass_ping', 'glass_resonance', 'relay_click_cluster',
    'shimmer_swirl', 'crystal_chime', 'ghost_pad', 'spectral_suck', 'voltage_flutter',
    'halo_tone', 'particle_spark', 'ritual_bass_swell', 'reverse_bloom',
    'steam_clock_burst', 'distant_bell_toll', 'cold_air_hush', 'wet_street_shimmer',
    'harbor_fog_bed', 'metal_resonance', 'snow_muffle', 'city_electrical_hum',
    'footsteps_wet', 'footsteps_snow', 'rain_close', 'rain_roof', 'puddle_splash',
    'crowd_murmur', 'laughter_burst', 'skytrain_pass', 'bus_idle', 'car_horn_short'
  ];

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  class AsmrFoleyEngine {
    constructor() {
      this.ctx = null;
      this.masterNode = null;
      this.nodes = [];
      this.activeSources = [];
      this.activeTimers = [];
      this.isPlaying = false;
      this.lastPreview = null;
      this.defaultPrerollSeconds = 0.28;
    }

    async ensureContext() {
      if (!window.AudioContext && !window.webkitAudioContext) throw new Error('Web Audio is unavailable in this browser.');
      if (!this.ctx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        this.ctx = new Ctx();
      }
      if (this.ctx.state === 'suspended') await this.ctx.resume();
      return this.ctx;
    }

    clearActiveNodes() {
      this.activeTimers.forEach((timer) => window.clearTimeout(timer));
      this.activeTimers = [];
      this.activeSources.forEach((source) => { try { source.stop(); } catch (e) {} });
      this.activeSources = [];
      this.nodes.forEach((node) => { try { node.disconnect(); } catch (e) {} });
      this.nodes = [];
    }

    stop() {
      this.clearActiveNodes();
      this.isPlaying = false;
      this.masterNode = null;
      this.lastPreview = null;
    }

    normalizeAudioEvents(pkg) {
      if (!pkg || !Array.isArray(pkg.audio_events)) return [];
      const runtime = clamp(Number(pkg.runtime_seconds || 20), 10, 30);
      const maxEdge = runtime + 0.5;
      const normalized = pkg.audio_events
        .filter((event) => event && ENGINE_NAMES.includes(event.engine))
        .map((event) => {
          const time = clamp(Number(event.time || 0), 0, maxEdge);
          const duration = clamp(Number(event.duration || 0.2), 0.04, 4.5);
          const intensity = clamp(Number(event.intensity || 0.5), 0, 1);
          return {
            time,
            duration,
            intensity,
            engine: event.engine,
            params: (event.params && typeof event.params === 'object') ? event.params : {},
            sync_role: String(event.sync_role || '')
          };
        })
        .sort((a, b) => a.time - b.time)
        .filter((event) => event.time <= maxEdge);

      if (!normalized.length || normalized[0].time > 0.22) {
        normalized.unshift({
          time: 0,
          duration: Math.min(4, runtime * 0.3),
          intensity: 0.22,
          engine: 'ghost_pad',
          params: { from: 122, to: 178 },
          sync_role: 'ambient_lead_in'
        });
      }

      const minEvents = Math.max(7, Math.ceil(runtime * 0.42));
      while (normalized.length < minEvents) {
        const idx = normalized.length;
        normalized.push({
          time: clamp(0.24 + idx * (runtime / (minEvents + 1)), 0, runtime - 0.08),
          duration: 0.24 + (idx % 3) * 0.16,
          intensity: 0.16 + (idx % 5) * 0.06,
          engine: idx % 2 === 0 ? 'particle_spark' : 'shimmer_swirl',
          params: {},
          sync_role: 'support_texture'
        });
      }

      return normalized.sort((a, b) => a.time - b.time);
    }

    buildMasterChain(ctx, destination) {
      const input = ctx.createGain();
      input.gain.value = 0.68;

      const preHP = ctx.createBiquadFilter();
      preHP.type = 'highpass';
      preHP.frequency.value = 28;

      const toneLP = ctx.createBiquadFilter();
      toneLP.type = 'lowpass';
      toneLP.frequency.value = 10400;

      const comp = ctx.createDynamicsCompressor();
      comp.threshold.value = -26;
      comp.knee.value = 16;
      comp.ratio.value = 3.4;
      comp.attack.value = 0.01;
      comp.release.value = 0.25;

      const limiter = ctx.createDynamicsCompressor();
      limiter.threshold.value = -8;
      limiter.knee.value = 2;
      limiter.ratio.value = 14;
      limiter.attack.value = 0.002;
      limiter.release.value = 0.1;

      const stereo = ctx.createStereoPanner ? ctx.createStereoPanner() : null;
      if (stereo) stereo.pan.value = 0;

      const convolver = this.makeTinyReverb(ctx);
      const wet = ctx.createGain();
      wet.gain.value = 0.2;
      const dry = ctx.createGain();
      dry.gain.value = 0.8;

      input.connect(preHP);
      preHP.connect(toneLP);
      toneLP.connect(comp);
      comp.connect(dry);
      comp.connect(convolver);
      convolver.connect(wet);
      dry.connect(limiter);
      wet.connect(limiter);
      if (stereo) {
        limiter.connect(stereo);
        stereo.connect(destination);
      } else {
        limiter.connect(destination);
      }

      this.nodes.push(input, preHP, toneLP, comp, limiter, convolver, wet, dry);
      if (stereo) this.nodes.push(stereo);
      return input;
    }

    makeTinyReverb(ctx) {
      const c = ctx.createConvolver();
      const length = Math.floor(ctx.sampleRate * 1.1);
      const i = ctx.createBuffer(2, length, ctx.sampleRate);
      for (let ch = 0; ch < 2; ch += 1) {
        const d = i.getChannelData(ch);
        for (let n = 0; n < length; n += 1) {
          const env = Math.pow(1 - n / length, 2.4);
          d[n] = (Math.random() * 2 - 1) * env * (0.65 + (ch * 0.06));
        }
      }
      c.buffer = i;
      return c;
    }

    shapeEnvelope(gainParam, start, duration, peak) {
      const attack = Math.min(0.18, Math.max(0.025, duration * 0.24));
      const hold = Math.max(0.01, duration * 0.2);
      const releaseStart = start + attack + hold;
      gainParam.setValueAtTime(0.0001, start);
      gainParam.exponentialRampToValueAtTime(Math.max(0.0025, peak), start + attack);
      gainParam.exponentialRampToValueAtTime(Math.max(0.0015, peak * 0.75), releaseStart);
      gainParam.exponentialRampToValueAtTime(0.0001, start + duration);
    }

    scheduleTone(ctx, target, start, duration, opts) {
      const osc = ctx.createOscillator();
      osc.type = opts.type || 'sine';
      const pan = ctx.createStereoPanner ? ctx.createStereoPanner() : null;
      const panAmount = clamp(Number(opts.pan || 0), -1, 1);
      if (pan) pan.pan.setValueAtTime(panAmount, start);

      osc.frequency.setValueAtTime(Math.max(20, opts.from || 220), start);
      if (opts.to) osc.frequency.exponentialRampToValueAtTime(Math.max(20, opts.to), start + duration);
      const gain = ctx.createGain();
      this.shapeEnvelope(gain.gain, start, duration, opts.peak || 0.1);

      osc.connect(gain);
      if (pan) {
        gain.connect(pan);
        pan.connect(target);
      } else {
        gain.connect(target);
      }

      osc.start(start);
      osc.stop(start + duration + 0.05);
      this.activeSources.push(osc);
      this.nodes.push(osc, gain);
      if (pan) this.nodes.push(pan);
    }

    scheduleNoise(ctx, target, start, duration, opts) {
      const buffer = ctx.createBuffer(1, Math.max(1, Math.floor(ctx.sampleRate * duration)), ctx.sampleRate);
      const data = buffer.getChannelData(0);
      for (let i = 0; i < data.length; i += 1) data[i] = (Math.random() * 2 - 1) * (1 - (i / data.length) * 0.2);
      const source = ctx.createBufferSource();
      source.buffer = buffer;
      const filter = ctx.createBiquadFilter();
      filter.type = opts.filterType || 'bandpass';
      filter.frequency.value = opts.freq || 1400;
      filter.Q.value = opts.q || 1;
      const pan = ctx.createStereoPanner ? ctx.createStereoPanner() : null;
      if (pan) pan.pan.value = clamp(Number(opts.pan || 0), -1, 1);
      const gain = ctx.createGain();
      this.shapeEnvelope(gain.gain, start, duration, opts.peak || 0.08);

      source.connect(filter);
      filter.connect(gain);
      if (pan) {
        gain.connect(pan);
        pan.connect(target);
      } else {
        gain.connect(target);
      }

      source.start(start);
      source.stop(start + duration + 0.03);
      this.activeSources.push(source);
      this.nodes.push(source, filter, gain);
      if (pan) this.nodes.push(pan);
    }

    renderEvent(ctx, destination, event, atTime) {
      const duration = clamp(Number(event.duration || 0.2), 0.04, 8);
      const intensity = clamp(Number(event.intensity || 0.5), 0, 1);
      const p = event.params || {};

      switch (event.engine) {
        case 'glissando_rise':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 140, to: p.to || 1180, peak: 0.06 + intensity * 0.12, type: 'sawtooth', pan: -0.15 });
          break;
        case 'synth_bloom':
          this.scheduleTone(ctx, destination, atTime, duration * 0.94, { from: p.from || 240, to: p.to || 530, peak: 0.05 + intensity * 0.11, type: 'triangle', pan: 0.14 });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration, { from: (p.from || 240) * 1.52, to: (p.to || 530) * 1.52, peak: 0.03 + intensity * 0.08, type: 'sine', pan: -0.14 });
          break;
        case 'sub_swell':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.freq || 44, to: (p.freq || 44) * 1.05, peak: 0.08 + intensity * 0.1, type: 'sine' });
          break;
        case 'filtered_noise_wash':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 2300, q: 0.65, peak: 0.045 + intensity * 0.08, filterType: 'lowpass', pan: 0.24 });
          break;
        case 'ceramic_tick':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.08, duration * 0.4), { from: p.pitch || 840, to: 300, peak: 0.025 + intensity * 0.05, type: 'triangle', pan: -0.36 });
          break;
        case 'paper_crackle':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 1700, q: 1.2, peak: 0.025 + intensity * 0.05, filterType: 'highpass', pan: -0.2 });
          break;
        case 'steam_hiss':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 3900, q: 0.55, peak: 0.025 + intensity * 0.05, filterType: 'lowpass', pan: 0.2 });
          break;
        case 'tape_stop_drop':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 220, to: p.to || 32, peak: 0.05 + intensity * 0.09, type: 'square' });
          break;
        case 'bit_pulse':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.08, duration * 0.5), { from: p.freq || 360, to: p.freq || 360, peak: 0.035 + intensity * 0.06, type: 'square', pan: 0.1 });
          break;
        case 'breath_pulse':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 760, q: 0.8, peak: 0.03 + intensity * 0.05, filterType: 'bandpass' });
          break;
        case 'low_hum':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.freq || 66, to: p.freq || 66, peak: 0.035 + intensity * 0.08, type: 'sine' });
          break;
        case 'digital_shimmer':
          this.scheduleTone(ctx, destination, atTime, duration * 0.8, { from: p.from || 1000, to: p.to || 2280, peak: 0.02 + intensity * 0.05, type: 'triangle', pan: 0.3 });
          break;
        case 'glass_ping':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.1, duration * 0.35), { from: p.pitch || 1420, to: (p.pitch || 1420) * 0.66, peak: 0.02 + intensity * 0.045, type: 'triangle', pan: -0.24 });
          break;
        case 'glass_resonance':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 620, to: p.to || 470, peak: 0.028 + intensity * 0.05, type: 'sine', pan: 0.22 });
          this.scheduleTone(ctx, destination, atTime + 0.01, duration * 0.92, { from: (p.from || 620) * 1.51, to: (p.to || 470) * 1.41, peak: 0.01 + intensity * 0.03, type: 'triangle', pan: -0.2 });
          break;
        case 'relay_click_cluster': {
          const clicks = Math.max(2, Math.min(8, Math.round(2 + intensity * 6)));
          for (let i = 0; i < clicks; i += 1) {
            this.scheduleTone(ctx, destination, atTime + i * 0.035, 0.08, { from: 950 + i * 40, to: 320, peak: 0.012 + intensity * 0.02, type: 'square', pan: (i % 2 ? 0.22 : -0.22) });
          }
          break;
        }
        case 'shimmer_swirl':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 3100, q: 0.7, peak: 0.018 + intensity * 0.04, filterType: 'bandpass', pan: 0.28 });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration * 0.85, { from: p.from || 920, to: p.to || 1520, peak: 0.01 + intensity * 0.026, type: 'triangle', pan: -0.26 });
          break;
        case 'crystal_chime':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.14, duration * 0.46), { from: p.from || 1180, to: p.to || 790, peak: 0.014 + intensity * 0.03, type: 'sine', pan: 0.34 });
          this.scheduleTone(ctx, destination, atTime + 0.04, Math.max(0.16, duration * 0.52), { from: (p.from || 1180) * 1.34, to: (p.to || 790) * 1.25, peak: 0.009 + intensity * 0.024, type: 'triangle', pan: -0.3 });
          break;
        case 'ghost_pad':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 140, to: p.to || 210, peak: 0.03 + intensity * 0.05, type: 'sine', pan: 0 });
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: 1200, q: 0.6, peak: 0.007 + intensity * 0.02, filterType: 'lowpass', pan: -0.12 });
          break;
        case 'spectral_suck':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 1600, q: 0.85, peak: 0.02 + intensity * 0.04, filterType: 'bandpass', pan: -0.18 });
          this.scheduleTone(ctx, destination, atTime, duration * 0.8, { from: p.from || 380, to: p.to || 96, peak: 0.01 + intensity * 0.03, type: 'sawtooth', pan: 0.14 });
          break;
        case 'voltage_flutter': {
          const bursts = Math.max(3, Math.min(10, Math.round(3 + intensity * 7)));
          for (let i = 0; i < bursts; i += 1) {
            this.scheduleTone(ctx, destination, atTime + i * (duration / (bursts + 1)), 0.07, { from: 280 + i * 36, to: 240 + i * 10, peak: 0.008 + intensity * 0.018, type: 'square', pan: Math.sin(i) * 0.35 });
          }
          break;
        }
        case 'halo_tone':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 320, to: p.to || 420, peak: 0.025 + intensity * 0.045, type: 'triangle', pan: -0.08 });
          this.scheduleTone(ctx, destination, atTime, duration * 0.95, { from: (p.from || 320) * 2.01, to: (p.to || 420) * 1.98, peak: 0.008 + intensity * 0.02, type: 'sine', pan: 0.08 });
          break;
        case 'particle_spark':
          this.scheduleNoise(ctx, destination, atTime, Math.max(0.08, duration * 0.45), { freq: p.freq || 4600, q: 1.8, peak: 0.008 + intensity * 0.02, filterType: 'highpass', pan: 0.12 });
          break;
        case 'ritual_bass_swell':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 48, to: p.to || 72, peak: 0.045 + intensity * 0.065, type: 'sine', pan: 0 });
          this.scheduleTone(ctx, destination, atTime + 0.03, duration * 0.8, { from: p.from2 || 96, to: p.to2 || 110, peak: 0.018 + intensity * 0.035, type: 'triangle', pan: -0.06 });
          break;
        case 'reverse_bloom':
          this.scheduleNoise(ctx, destination, atTime, duration * 0.75, { freq: p.freq || 2100, q: 0.8, peak: 0.02 + intensity * 0.04, filterType: 'lowpass', pan: 0.18 });
          this.scheduleTone(ctx, destination, atTime + duration * 0.4, duration * 0.6, { from: p.from || 220, to: p.to || 860, peak: 0.016 + intensity * 0.03, type: 'triangle', pan: -0.14 });
          break;
        case 'steam_clock_burst':
          this.scheduleNoise(ctx, destination, atTime, duration * 0.92, { freq: p.freq || 2800, q: 0.44, peak: 0.03 + intensity * 0.07, filterType: 'bandpass', pan: 0.16 });
          this.scheduleTone(ctx, destination, atTime + 0.06, Math.max(0.1, duration * 0.34), { from: p.from || 620, to: p.to || 420, peak: 0.014 + intensity * 0.03, type: 'triangle', pan: -0.12 });
          break;
        case 'distant_bell_toll':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 540, to: p.to || 420, peak: 0.026 + intensity * 0.05, type: 'sine', pan: 0.05 });
          this.scheduleTone(ctx, destination, atTime + 0.03, duration * 0.86, { from: (p.from || 540) * 1.5, to: (p.to || 420) * 1.4, peak: 0.012 + intensity * 0.026, type: 'triangle', pan: -0.18 });
          break;
        case 'cold_air_hush':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 1200, q: 0.72, peak: 0.02 + intensity * 0.045, filterType: 'highpass', pan: -0.08 });
          break;
        case 'wet_street_shimmer':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 2400, q: 1.2, peak: 0.014 + intensity * 0.03, filterType: 'bandpass', pan: 0.24 });
          this.scheduleTone(ctx, destination, atTime + 0.04, duration * 0.7, { from: p.from || 700, to: p.to || 1060, peak: 0.008 + intensity * 0.018, type: 'triangle', pan: -0.2 });
          break;
        case 'harbor_fog_bed':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 74, to: p.to || 66, peak: 0.03 + intensity * 0.055, type: 'sine' });
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 620, q: 0.45, peak: 0.016 + intensity * 0.034, filterType: 'lowpass', pan: 0 });
          break;
        case 'metal_resonance':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.12, duration * 0.5), { from: p.from || 980, to: p.to || 340, peak: 0.02 + intensity * 0.04, type: 'triangle', pan: 0.22 });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration * 0.7, { from: (p.from || 980) * 1.28, to: (p.to || 340) * 1.5, peak: 0.007 + intensity * 0.02, type: 'sine', pan: -0.24 });
          break;
        case 'snow_muffle':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 840, q: 0.38, peak: 0.015 + intensity * 0.028, filterType: 'lowpass', pan: -0.04 });
          break;
        case 'city_electrical_hum':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 112, to: p.to || 118, peak: 0.022 + intensity * 0.04, type: 'sawtooth', pan: 0.03 });
          this.scheduleTone(ctx, destination, atTime + 0.01, duration * 0.9, { from: 224, to: 236, peak: 0.008 + intensity * 0.02, type: 'triangle', pan: -0.1 });
          break;

        case 'footsteps_wet':
        case 'footsteps_snow': {
          const wet = event.engine === 'footsteps_wet';
          this.scheduleNoise(ctx, destination, atTime, Math.max(0.08, duration * 0.8), { freq: wet ? 420 : 320, q: 1.25, peak: 0.02 + intensity * 0.05, filterType: 'bandpass', pan: (Math.random() * 0.26) - 0.13 });
          this.scheduleTone(ctx, destination, atTime + 0.01, Math.max(0.07, duration * 0.5), { from: 92, to: 74, peak: 0.012 + intensity * 0.03, type: 'sine', pan: wet ? 0.05 : -0.05 });
          if (wet) {
            this.scheduleNoise(ctx, destination, atTime + 0.02, Math.max(0.06, duration * 0.45), { freq: 1900, q: 1.9, peak: 0.008 + intensity * 0.018, filterType: 'highpass', pan: 0.08 });
          }
          break;
        }
        case 'rain_close':
        case 'rain_roof': {
          const roof = event.engine === 'rain_roof';
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: roof ? 2600 : 3200, q: 0.9, peak: 0.018 + intensity * 0.04, filterType: 'highpass', pan: 0.02 });
          const drops = Math.max(6, Math.min(36, Math.round(duration * (roof ? 4 : 7) * (0.7 + intensity))));
          for (let i = 0; i < drops; i += 1) {
            const dt = (i / drops) * duration;
            this.scheduleNoise(ctx, destination, atTime + dt, 0.045, { freq: 4600 + (i % 6) * 220, q: 2.6, peak: 0.004 + intensity * 0.012, filterType: 'bandpass', pan: Math.sin(i * 2.1) * 0.35 });
          }
          break;
        }
        case 'puddle_splash':
          this.scheduleNoise(ctx, destination, atTime, Math.max(0.1, duration * 0.8), { freq: 1500, q: 1.1, peak: 0.02 + intensity * 0.04, filterType: 'bandpass', pan: -0.12 });
          this.scheduleTone(ctx, destination, atTime + 0.02, Math.max(0.09, duration * 0.5), { from: 220, to: 110, peak: 0.008 + intensity * 0.018, type: 'triangle', pan: 0.1 });
          break;
        case 'crowd_murmur':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: 520, q: 0.65, peak: 0.02 + intensity * 0.032, filterType: 'bandpass', pan: -0.1 });
          this.scheduleNoise(ctx, destination, atTime + 0.03, duration * 0.95, { freq: 880, q: 0.8, peak: 0.014 + intensity * 0.024, filterType: 'bandpass', pan: 0.12 });
          this.scheduleTone(ctx, destination, atTime, duration * 0.85, { from: 190, to: 240, peak: 0.004 + intensity * 0.012, type: 'sine', pan: 0.04 });
          break;
        case 'laughter_burst':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.12, duration * 0.7), { from: 280, to: 420, peak: 0.012 + intensity * 0.022, type: 'triangle', pan: 0.2 });
          this.scheduleNoise(ctx, destination, atTime + 0.015, Math.max(0.11, duration * 0.75), { freq: 1300, q: 1.4, peak: 0.012 + intensity * 0.02, filterType: 'bandpass', pan: -0.16 });
          break;
        case 'skytrain_pass':
          this.scheduleTone(ctx, destination, atTime, duration, { from: 58, to: 46, peak: 0.045 + intensity * 0.07, type: 'sawtooth', pan: -0.25 });
          this.scheduleTone(ctx, destination, atTime + 0.05, duration * 0.9, { from: 140, to: 220, peak: 0.014 + intensity * 0.026, type: 'triangle', pan: 0.2 });
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: 2600, q: 0.75, peak: 0.012 + intensity * 0.02, filterType: 'highpass', pan: 0.1 });
          break;
        case 'bus_idle':
          this.scheduleTone(ctx, destination, atTime, duration, { from: 78, to: 74, peak: 0.028 + intensity * 0.045, type: 'sawtooth', pan: -0.06 });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration * 0.95, { from: 156, to: 148, peak: 0.01 + intensity * 0.02, type: 'triangle', pan: 0.08 });
          if (duration > 1) this.scheduleNoise(ctx, destination, atTime + duration * 0.68, 0.22, { freq: 2100, q: 1.3, peak: 0.01 + intensity * 0.018, filterType: 'highpass', pan: 0.18 });
          break;
        case 'car_horn_short':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.12, duration), { from: 410, to: 380, peak: 0.026 + intensity * 0.04, type: 'triangle', pan: 0.05 });
          this.scheduleTone(ctx, destination, atTime, Math.max(0.1, duration * 0.9), { from: 510, to: 480, peak: 0.018 + intensity * 0.03, type: 'sawtooth', pan: -0.04 });
          break;

        default:
          break;
      }
    }

    scheduleBed(ctx, destination, startAt, runtime) {
      const lowHum = {
        time: 0,
        duration: Math.max(1.6, runtime * 0.95),
        intensity: 0.18,
        engine: 'low_hum',
        params: { freq: 58 }
      };
      const airy = {
        time: 0.06,
        duration: Math.max(1.8, runtime * 0.85),
        intensity: 0.2,
        engine: 'filtered_noise_wash',
        params: { freq: 1800 }
      };
      this.renderEvent(ctx, destination, lowHum, startAt + lowHum.time);
      this.renderEvent(ctx, destination, airy, startAt + airy.time);
    }

    validateRecipe(pkg) {
      if (!pkg || typeof pkg !== 'object') return false;
      if (!Array.isArray(pkg.audio_events)) return false;
      return pkg.audio_events.every((event) => ENGINE_NAMES.includes(event.engine));
    }

    async preview(pkg) {
      await this.ensureContext();
      if (!this.validateRecipe(pkg)) throw new Error('Audio package is invalid or uses unsupported engines.');
      this.stop();

      const events = this.normalizeAudioEvents(pkg);
      const runtime = clamp(Number(pkg.runtime_seconds || 20), 10, 30);
      const preroll = this.defaultPrerollSeconds;
      const now = this.ctx.currentTime;
      const audioStartAt = now + preroll;

      this.masterNode = this.buildMasterChain(this.ctx, this.ctx.destination);
      this.scheduleBed(this.ctx, this.masterNode, audioStartAt, runtime);
      events.forEach((event) => this.renderEvent(this.ctx, this.masterNode, event, audioStartAt + event.time));

      const total = Math.max(runtime, events.reduce((max, event) => Math.max(max, event.time + event.duration), runtime));
      this.isPlaying = true;
      const timer = window.setTimeout(() => {
        this.isPlaying = false;
        this.clearActiveNodes();
      }, (total + preroll + 1.2) * 1000);
      this.activeTimers.push(timer);

      this.lastPreview = { startAt: audioStartAt, preroll, total, runtime };
      return this.lastPreview;
    }

    renderToOfflineBuffer(pkg, sampleRate = 48000) {
      if (!this.validateRecipe(pkg)) throw new Error('Cannot export: invalid audio events.');
      const events = this.normalizeAudioEvents(pkg);
      const runtime = clamp(Number(pkg.runtime_seconds || 20), 10, 30);
      const lengthSec = Math.max(runtime + 1, events.reduce((max, e) => Math.max(max, e.time + e.duration), runtime) + 1);
      const offline = new OfflineAudioContext(2, Math.ceil(lengthSec * sampleRate), sampleRate);
      const master = this.buildMasterChain(offline, offline.destination);
      this.scheduleBed(offline, master, 0, runtime);
      events.forEach((event) => this.renderEvent(offline, master, event, event.time));
      return offline.startRendering();
    }

    async exportWav(pkg) {
      const rendered = await this.renderToOfflineBuffer(pkg, 44100);
      return this.audioBufferToWav(rendered);
    }

    audioBufferToWav(buffer) {
      const channels = Math.min(2, buffer.numberOfChannels);
      const length = buffer.length;
      const bytes = 44 + length * channels * 2;
      const view = new DataView(new ArrayBuffer(bytes));
      const writeString = (o, s) => { for (let i = 0; i < s.length; i += 1) view.setUint8(o + i, s.charCodeAt(i)); };
      writeString(0, 'RIFF');
      view.setUint32(4, 36 + length * channels * 2, true);
      writeString(8, 'WAVEfmt ');
      view.setUint32(16, 16, true);
      view.setUint16(20, 1, true);
      view.setUint16(22, channels, true);
      view.setUint32(24, buffer.sampleRate, true);
      view.setUint32(28, buffer.sampleRate * channels * 2, true);
      view.setUint16(32, channels * 2, true);
      view.setUint16(34, 16, true);
      writeString(36, 'data');
      view.setUint32(40, length * channels * 2, true);

      const channelData = [];
      for (let ch = 0; ch < channels; ch += 1) channelData.push(buffer.getChannelData(ch));

      let o = 44;
      for (let i = 0; i < length; i += 1) {
        for (let ch = 0; ch < channels; ch += 1) {
          const s = Math.max(-1, Math.min(1, channelData[ch][i] || 0));
          view.setInt16(o, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
          o += 2;
        }
      }
      return new Blob([view], { type: 'audio/wav' });
    }
  }

  window.AsmrFoleyEngine = AsmrFoleyEngine;
})(window);
