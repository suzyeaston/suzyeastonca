(function (window) {
  'use strict';

  const ENGINE_NAMES = [
    'glissando_rise', 'synth_bloom', 'sub_swell', 'filtered_noise_wash', 'ceramic_tick',
    'paper_crackle', 'steam_hiss', 'tape_stop_drop', 'bit_pulse', 'breath_pulse',
    'low_hum', 'digital_shimmer'
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
      return pkg.audio_events
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
