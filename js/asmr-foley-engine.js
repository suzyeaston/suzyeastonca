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
    'crowd_murmur', 'laughter_burst', 'skytrain_pass', 'bus_idle', 'car_horn_short', 'seabus_horn', 'ocean_waves', 'gulls_distant', 'crosswalk_chirp', 'compass_tap', 'bike_bell', 'skateboard_roll', 'siren_distant',
    'gastown_clock_whistle', 'church_bells', 'harbour_noon_horn', 'nine_oclock_gun', 'planetarium_hum', 'planetarium_chime'
  ];

  const BED_ENGINES = new Set([
    'ocean_waves', 'rain_close', 'rain_roof', 'harbor_fog_bed', 'city_electrical_hum',
    'planetarium_hum', 'crowd_murmur', 'filtered_noise_wash', 'low_hum'
  ]);

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
          const isBed = BED_ENGINES.has(event.engine);
          const duration = isBed
            ? clamp(Number(event.duration || 3), 3, runtime + 0.5)
            : clamp(Number(event.duration || 0.2), 0.12, 8);
          const intensity = clamp(Number(event.intensity || 0.5), 0, 1);
          const params = (event.params && typeof event.params === 'object' && !Array.isArray(event.params))
            ? { ...event.params }
            : {};
          return {
            time,
            duration,
            intensity,
            engine: event.engine,
            params,
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
      input.gain.value = 0.58;

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
      limiter.threshold.value = -9;
      limiter.knee.value = 2;
      limiter.ratio.value = 14;
      limiter.attack.value = 0.002;
      limiter.release.value = 0.1;

      const stereo = ctx.createStereoPanner ? ctx.createStereoPanner() : null;
      if (stereo) stereo.pan.value = 0;

      const convolver = this.makeTinyReverb(ctx);
      const wet = ctx.createGain();
      wet.gain.value = 0.17;
      const dry = ctx.createGain();
      dry.gain.value = 0.83;

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
      return this.shapeEnvelopeEx(gainParam, start, duration, peak, {});
    }

    shapeEnvelopeEx(gainParam, start, duration, peak, env = {}) {
      const attack = Math.max(0.01, Math.min(duration * 0.8, Number(env.attack || Math.min(0.18, Math.max(0.025, duration * 0.24)))));
      const hold = Math.max(0, Math.min(duration - attack, Number(env.hold ?? Math.max(0.01, duration * 0.2))));
      const release = Math.max(0.03, Number(env.release || Math.max(0.08, duration - attack - hold)));
      const tail = Math.max(0, Number(env.tail || 0));
      const sustain = Math.max(0.0009, peak * 0.72);
      const peakVal = Math.max(0.0015, peak);
      const attackAt = start + attack;
      const holdAt = Math.min(start + duration, attackAt + hold);
      const releaseAt = holdAt + release;
      const finalAt = releaseAt + tail;
      const useLinear = env.curve === 'linear';

      gainParam.setValueAtTime(0.0001, start);
      if (useLinear) {
        gainParam.linearRampToValueAtTime(peakVal, attackAt);
        gainParam.linearRampToValueAtTime(sustain, holdAt);
        gainParam.linearRampToValueAtTime(0.0001, finalAt);
      } else {
        gainParam.exponentialRampToValueAtTime(peakVal, attackAt);
        gainParam.exponentialRampToValueAtTime(Math.max(0.0008, sustain), holdAt);
        gainParam.exponentialRampToValueAtTime(0.0001, finalAt);
      }
      return { tail: release + tail, endAt: finalAt };
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
      const envResult = this.shapeEnvelopeEx(gain.gain, start, duration, opts.peak || 0.1, opts.env || {});

      osc.connect(gain);
      if (pan) {
        gain.connect(pan);
        pan.connect(target);
      } else {
        gain.connect(target);
      }

      osc.start(start);
      osc.stop(start + duration + envResult.tail + 0.05);
      this.activeSources.push(osc);
      this.nodes.push(osc, gain);
      if (pan) this.nodes.push(pan);
    }

    scheduleNoise(ctx, target, start, duration, opts) {
      const env = opts.env || {};
      const envRelease = Math.max(0.03, Number(env.release || Math.max(0.08, duration * 0.5)));
      const envTail = Math.max(0, Number(env.tail || 0));
      const sourceDuration = duration + envRelease + envTail + 0.08;
      const buffer = ctx.createBuffer(1, Math.max(1, Math.floor(ctx.sampleRate * sourceDuration)), ctx.sampleRate);
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
      const envResult = this.shapeEnvelopeEx(gain.gain, start, duration, opts.peak || 0.08, opts.env || {});

      source.connect(filter);
      filter.connect(gain);
      if (pan) {
        gain.connect(pan);
        pan.connect(target);
      } else {
        gain.connect(target);
      }

      source.start(start);
      source.stop(start + duration + envResult.tail + 0.03);
      this.activeSources.push(source);
      this.nodes.push(source, filter, gain);
      if (pan) this.nodes.push(pan);
    }

    renderEvent(ctx, destination, event, atTime) {
      const isBed = BED_ENGINES.has(event.engine);
      const duration = isBed ? Math.max(0.12, Number(event.duration || 3)) : clamp(Number(event.duration || 0.2), 0.12, 8);
      const intensity = clamp(Number(event.intensity || 0.5), 0, 1);
      const p = (event.params && typeof event.params === 'object' && !Array.isArray(event.params)) ? event.params : {};

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
          this.scheduleTone(ctx, destination, atTime, duration, {
            from: 58, to: 46, peak: 0.032 + intensity * 0.05, type: 'sawtooth', pan: -0.22,
            env: { attack: 0.28, hold: duration * 0.34, release: 1.8, tail: 0.5 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.05, duration * 0.9, {
            from: 140, to: 220, peak: 0.011 + intensity * 0.02, type: 'triangle', pan: 0.18,
            env: { attack: 0.22, hold: duration * 0.3, release: 1.5, tail: 0.4 }
          });
          this.scheduleNoise(ctx, destination, atTime, duration, {
            freq: 2400, q: 0.62, peak: 0.008 + intensity * 0.014, filterType: 'highpass', pan: 0.08,
            env: { attack: 0.2, hold: duration * 0.35, release: 1.4, tail: 0.3 }
          });
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


        case 'seabus_horn': {
          const f0 = Number(p.f0 || 86);
          const attack = clamp(Number(p.attack || 0.24), 0.12, 0.6);
          const release = clamp(Number(p.release || 1.8), 1, 3.2);
          const wobble = clamp(Number(p.wobble || 0.06), 0, 0.2);
          const drift = clamp(Number(p.drift || 0.012), 0, 0.04);
          const bright = clamp(Number(p.brightness || 0.28), 0, 1);
          this.scheduleTone(ctx, destination, atTime, duration, {
            from: f0 * (1 + drift), to: f0 * (0.985 - drift), peak: 0.012 + intensity * 0.018, type: 'sine', pan: -0.05,
            env: { attack, hold: duration * 0.34, release, tail: 0.4 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration * 0.95, {
            from: f0 * 2.02 * (1 + drift * 0.5), to: f0 * 2.0, peak: (0.005 + intensity * 0.01) * (0.5 + bright * 0.9), type: 'triangle', pan: 0.06,
            env: { attack: attack * 0.9, hold: duration * 0.3, release: release * 0.9, tail: 0.35 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.04, duration * 0.9, {
            from: f0 * 3.01, to: f0 * (3.01 - wobble * 0.8), peak: (0.002 + intensity * 0.005) * (0.4 + bright), type: 'sine', pan: 0.03,
            env: { attack: attack * 0.8, hold: duration * 0.2, release: release * 0.8, tail: 0.3 }
          });
          this.scheduleNoise(ctx, destination, atTime + 0.02, duration * 0.82, {
            freq: 360, q: 0.74, peak: 0.002 + intensity * 0.005, filterType: 'bandpass', pan: 0.01,
            env: { attack: attack * 0.9, hold: duration * 0.26, release: release * 0.75, tail: 0.25 }
          });
          break;
        }
        case 'ocean_waves': {
          const swellRate = clamp(Number(p.swell_rate || 0.35), 0.18, 0.75);
          const foam = clamp(Number(p.foam ?? 0.65), 0, 1);
          const fadeOut = clamp(Number(p.fade_out || 2.8), 2.2, 3.2);
          const calmTailStart = atTime + Math.max(0.2, duration - fadeOut);
          const swells = Math.max(2, Math.round(duration * swellRate));

          this.scheduleNoise(ctx, destination, atTime, duration, {
            freq: 240, q: 0.52, peak: 0.009 + intensity * 0.017, filterType: 'bandpass', pan: -0.16,
            env: { attack: 0.45, hold: duration * 0.45, release: 2.2, tail: 0.4 }
          });
          this.scheduleNoise(ctx, destination, atTime + 0.03, duration, {
            freq: 520, q: 0.66, peak: 0.005 + intensity * 0.012, filterType: 'lowpass', pan: 0.14,
            env: { attack: 0.38, hold: duration * 0.4, release: 2, tail: 0.35 }
          });

          for (let i = 0; i < swells; i += 1) {
            const dt = (i / Math.max(1, swells - 1)) * Math.max(0.2, duration - 1.2);
            const swellAt = atTime + dt;
            const tailFactor = swellAt >= calmTailStart ? Math.max(0.25, (atTime + duration - swellAt) / Math.max(0.4, fadeOut)) : 1;
            this.scheduleTone(ctx, destination, swellAt, 1.35, {
              from: 62, to: 53, peak: (0.003 + intensity * 0.008) * tailFactor, type: 'sine', pan: (i % 2 ? 0.1 : -0.08),
              env: { attack: 0.3, hold: 0.42, release: 1.5, tail: 0.3 }
            });
            if (foam > 0.05 && (swellAt + 0.25) < (atTime + duration - 0.8)) {
              this.scheduleNoise(ctx, destination, swellAt + 0.25, 0.34, {
                freq: 1900, q: 1.05, peak: (0.0015 + intensity * 0.0045) * foam * tailFactor, filterType: 'highpass', pan: (i % 2 ? -0.12 : 0.12),
                env: { attack: 0.05, hold: 0.08, release: 0.55, tail: 0.15 }
              });
            }
          }
          break;
        }
        case 'gulls_distant': {
          const chirps = Math.max(2, Math.min(6, Math.round(duration * 1.4)));
          for (let i = 0; i < chirps; i += 1) {
            const dt = (i / Math.max(1, chirps - 1)) * Math.max(0.1, duration * 0.9);
            this.scheduleTone(ctx, destination, atTime + dt, 0.12, { from: 980 + (i % 3) * 120, to: 760, peak: 0.006 + intensity * 0.012, type: 'triangle', pan: (i % 2 ? 0.22 : -0.18) });
          }
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: 1900, q: 0.7, peak: 0.004 + intensity * 0.01, filterType: 'bandpass' });
          break;
        }
        case 'crosswalk_chirp': {
          const pulses = Math.max(3, Math.min(10, Math.round(duration / 0.12)));
          for (let i = 0; i < pulses; i += 1) {
            this.scheduleTone(ctx, destination, atTime + i * 0.12, 0.08, { from: 1320, to: 1180, peak: 0.006 + intensity * 0.014, type: 'square', pan: 0.04 });
          }
          break;
        }
        case 'compass_tap':
          this.scheduleNoise(ctx, destination, atTime, Math.max(0.06, duration * 0.7), { freq: 2200, q: 2.2, peak: 0.008 + intensity * 0.014, filterType: 'bandpass' });
          this.scheduleTone(ctx, destination, atTime + 0.01, Math.max(0.07, duration * 0.6), { from: 1620, to: 1240, peak: 0.006 + intensity * 0.012, type: 'triangle', pan: 0.12 });
          break;
        case 'bike_bell':
          this.scheduleTone(ctx, destination, atTime, Math.max(0.12, duration * 0.62), { from: 980, to: 760, peak: 0.008 + intensity * 0.015, type: 'sine', pan: -0.06 });
          this.scheduleTone(ctx, destination, atTime + 0.05, Math.max(0.14, duration * 0.65), { from: 1320, to: 990, peak: 0.006 + intensity * 0.013, type: 'triangle', pan: 0.07 });
          break;
        case 'skateboard_roll':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: 260, q: 0.7, peak: 0.012 + intensity * 0.02, filterType: 'bandpass', pan: -0.08 });
          this.scheduleTone(ctx, destination, atTime, duration, { from: 92, to: 80, peak: 0.008 + intensity * 0.014, type: 'sine', pan: 0.08 });
          for (let i = 0; i < 6; i += 1) {
            this.scheduleNoise(ctx, destination, atTime + i * (duration / 6), 0.035, { freq: 1800, q: 1.8, peak: 0.003 + intensity * 0.008, filterType: 'highpass', pan: ((i % 2) ? -0.12 : 0.12) });
          }
          break;
        case 'siren_distant':
          this.scheduleTone(ctx, destination, atTime, duration, { from: 420, to: 620, peak: 0.006 + intensity * 0.012, type: 'triangle', pan: -0.18 });
          this.scheduleTone(ctx, destination, atTime + 0.15, duration * 0.9, { from: 560, to: 380, peak: 0.005 + intensity * 0.011, type: 'sine', pan: 0.18 });
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: 1200, q: 0.9, peak: 0.002 + intensity * 0.006, filterType: 'bandpass' });
          break;

        case 'gastown_clock_whistle': {
          const f0 = clamp(Number(p.f0 || 540), 520, 560);
          const interval = clamp(Number(p.interval || 1.06), 1.02, 1.14);
          const attack = clamp(Number(p.attack || 0.18), 0.1, 0.5);
          const release = clamp(Number(p.release || 1.8), 1, 3.2);
          const breathy = clamp(Number(p.breathy ?? 0.7), 0, 1);
          const vibrato = clamp(Number(p.vibrato ?? 0.55), 0, 1);
          const bend = 1 + (0.028 + vibrato * 0.02);
          this.scheduleNoise(ctx, destination, atTime, duration, {
            freq: 1700, q: 0.8, peak: (0.004 + intensity * 0.01) * (0.35 + breathy), filterType: 'bandpass', pan: 0.03,
            env: { attack: attack * 0.7, hold: duration * 0.28, release: release * 0.9, tail: 0.25 }
          });
          this.scheduleNoise(ctx, destination, atTime + 0.02, duration * 0.95, {
            freq: 980, q: 0.6, peak: (0.003 + intensity * 0.007) * breathy, filterType: 'lowpass', pan: -0.02,
            env: { attack: attack * 0.9, hold: duration * 0.3, release: release * 0.8, tail: 0.2 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration * 0.9, {
            from: f0 * bend, to: f0 * (1 + vibrato * 0.006), peak: 0.011 + intensity * 0.018, type: 'triangle', pan: -0.06,
            env: { attack, hold: duration * 0.33, release, tail: 0.35 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.05, duration * 0.88, {
            from: (f0 * interval) * (1 + vibrato * 0.018), to: f0 * interval, peak: 0.008 + intensity * 0.013, type: 'sine', pan: 0.07,
            env: { attack: attack * 1.1, hold: duration * 0.3, release: release * 0.95, tail: 0.35 }
          });
          break;
        }
        case 'church_bells': {
          const root = Number(p.root || 392);
          const decay = clamp(Number(p.decay || 4.5), 2.5, 7.5);
          const brightness = clamp(Number(p.brightness || 0.35), 0, 1);
          const drift = clamp(Number(p.drift || 0.01), 0, 0.04);
          const partials = [
            { r: 1.0, amp: 1.0, pan: -0.12, d: 1.0, type: 'sine' },
            { r: 2.7, amp: 0.62, pan: 0.14, d: 0.82, type: 'triangle' },
            { r: 5.8, amp: 0.4, pan: -0.18, d: 0.68, type: 'sine' },
            { r: 8.9, amp: 0.24, pan: 0.2, d: 0.54, type: 'triangle' }
          ];
          partials.forEach((part, i) => {
            this.scheduleTone(ctx, destination, atTime + i * 0.01, Math.max(duration, decay * part.d), {
              from: root * part.r * (1 + drift),
              to: root * part.r * (0.62 - drift * 0.5),
              peak: (0.004 + intensity * 0.012) * part.amp * (0.65 + brightness * 0.7),
              type: part.type,
              pan: part.pan,
              env: { attack: 0.22 + i * 0.03, hold: 0.5, release: decay * part.d, tail: 0.65 }
            });
          });
          this.scheduleNoise(ctx, destination, atTime + 0.03, Math.max(duration, decay * 0.9), {
            freq: 1800 + brightness * 1000, q: 0.6, peak: 0.0015 + intensity * 0.004, filterType: 'lowpass', pan: 0,
            env: { attack: 0.14, hold: 0.4, release: decay * 0.75, tail: 0.4 }
          });
          break;
        }
        case 'harbour_noon_horn': {
          const f0 = Number(p.f0 || 74);
          const attack = clamp(Number(p.attack || 0.35), 0.15, 0.7);
          const release = clamp(Number(p.release || 2.2), 1.2, 4.8);
          const wobble = clamp(Number(p.wobble || 0.07), 0, 0.2);
          const drift = clamp(Number(p.drift || 0.015), 0, 0.04);
          const brightness = clamp(Number(p.brightness || 0.35), 0, 1);
          this.scheduleTone(ctx, destination, atTime, duration, {
            from: f0 * (1 + drift), to: f0 * (0.97 - drift * 0.4), peak: 0.012 + intensity * 0.02, type: 'sine', pan: -0.02,
            env: { attack, hold: duration * 0.38, release, tail: 0.6 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.015, duration * 0.96, {
            from: f0 * 2.01 * (1 + wobble * 0.03), to: f0 * 2.0, peak: (0.006 + intensity * 0.012) * (0.55 + brightness), type: 'triangle', pan: -0.08,
            env: { attack: attack * 0.85, hold: duration * 0.3, release: release * 0.92, tail: 0.5 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.03, duration * 0.9, {
            from: f0 * 3.02, to: f0 * (2.95 - wobble * 0.2), peak: (0.003 + intensity * 0.007) * (0.35 + brightness), type: 'sine', pan: 0.06,
            env: { attack: attack * 0.8, hold: duration * 0.24, release: release * 0.8, tail: 0.45 }
          });
          this.scheduleNoise(ctx, destination, atTime + 0.04, duration * 0.86, {
            freq: 320, q: 0.68, peak: (0.002 + intensity * 0.005) * (0.45 + brightness), filterType: 'bandpass', pan: 0.03,
            env: { attack: attack * 0.9, hold: duration * 0.25, release: release * 0.72, tail: 0.3 }
          });
          break;
        }
        case 'nine_oclock_gun': {
          this.scheduleNoise(ctx, destination, atTime, Math.max(0.2, duration * 0.4), { freq: 220, q: 0.65, peak: 0.018 + intensity * 0.03, filterType: 'lowpass', pan: -0.03 });
          this.scheduleTone(ctx, destination, atTime, Math.max(0.28, duration * 0.55), { from: 54, to: 40, peak: 0.02 + intensity * 0.032, type: 'sine', pan: 0.02 });
          this.scheduleNoise(ctx, destination, atTime + 0.06, Math.max(0.25, duration * 0.45), { freq: 880, q: 0.8, peak: 0.005 + intensity * 0.01, filterType: 'bandpass', pan: 0.06 });
          break;
        }
        case 'planetarium_hum': {
          this.scheduleTone(ctx, destination, atTime, duration, {
            from: 88, to: 94, peak: 0.007 + intensity * 0.013, type: 'sine', pan: -0.02,
            env: { attack: 0.35, hold: duration * 0.44, release: 2, tail: 0.5 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.04, duration * 0.96, {
            from: 126, to: 121, peak: 0.003 + intensity * 0.007, type: 'triangle', pan: 0.04,
            env: { attack: 0.3, hold: duration * 0.35, release: 1.8, tail: 0.35 }
          });
          this.scheduleNoise(ctx, destination, atTime, duration, {
            freq: 740, q: 0.52, peak: 0.003 + intensity * 0.009, filterType: 'lowpass', pan: 0.02,
            env: { attack: 0.4, hold: duration * 0.42, release: 2.1, tail: 0.4 }
          });
          break;
        }
        case 'planetarium_chime': {
          this.scheduleTone(ctx, destination, atTime, Math.max(0.16, duration * 0.75), {
            from: 932, to: 690, peak: 0.004 + intensity * 0.008, type: 'triangle', pan: 0.08,
            env: { attack: 0.08, hold: 0.18, release: 1.4, tail: 0.2 }
          });
          this.scheduleTone(ctx, destination, atTime + 0.04, Math.max(0.14, duration * 0.68), {
            from: 1188, to: 860, peak: 0.003 + intensity * 0.006, type: 'sine', pan: -0.07,
            env: { attack: 0.07, hold: 0.16, release: 1.2, tail: 0.2 }
          });
          this.scheduleNoise(ctx, destination, atTime + 0.03, Math.max(0.15, duration * 0.6), {
            freq: 2600, q: 1.1, peak: 0.001 + intensity * 0.0025, filterType: 'bandpass', pan: 0,
            env: { attack: 0.05, hold: 0.08, release: 0.9, tail: 0.15 }
          });
          break;
        }

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


    estimateEventTail(event) {
      const e = event && event.engine;
      if (e === 'harbour_noon_horn') return 2.8;
      if (e === 'seabus_horn') return 2.2;
      if (e === 'church_bells') return 4.8;
      if (e === 'gastown_clock_whistle') return 2.1;
      if (e === 'planetarium_hum' || BED_ENGINES.has(e)) return 2.4;
      return 0.8;
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
      if (window && window.console && typeof window.console.log === 'function') {
        window.console.log('[ASMR] normalized audio events', events.map((event) => ({ time: Number(event.time.toFixed(3)), duration: Number(event.duration.toFixed(3)), engine: event.engine })));
      }
      const runtime = clamp(Number(pkg.runtime_seconds || 20), 10, 30);
      const preroll = this.defaultPrerollSeconds;
      const now = this.ctx.currentTime;
      const audioStartAt = now + preroll;

      this.masterNode = this.buildMasterChain(this.ctx, this.ctx.destination);
      this.scheduleBed(this.ctx, this.masterNode, audioStartAt, runtime);
      events.forEach((event) => this.renderEvent(this.ctx, this.masterNode, event, audioStartAt + event.time));

      const total = Math.max(runtime, events.reduce((max, event) => Math.max(max, event.time + event.duration + this.estimateEventTail(event)), runtime));
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
      const lengthSec = Math.max(runtime + 2, events.reduce((max, e) => Math.max(max, e.time + e.duration + this.estimateEventTail(e)), runtime) + 1.5);
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
