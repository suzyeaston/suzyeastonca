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
      this.activeOscillators = [];
      this.isPlaying = false;
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
      this.activeOscillators.forEach((osc) => { try { osc.stop(); } catch (e) {} });
      this.activeOscillators = [];
      this.nodes.forEach((node) => { try { node.disconnect(); } catch (e) {} });
      this.nodes = [];
    }

    stop() {
      this.clearActiveNodes();
      this.isPlaying = false;
    }

    buildMasterChain(ctx, destination) {
      const input = ctx.createGain();
      input.gain.value = 0.84;
      const comp = ctx.createDynamicsCompressor();
      comp.threshold.value = -22;
      comp.ratio.value = 4;
      const lowpass = ctx.createBiquadFilter();
      lowpass.type = 'lowpass';
      lowpass.frequency.value = 12000;
      const convolver = this.makeTinyReverb(ctx);
      const wet = ctx.createGain();
      wet.gain.value = 0.16;
      const dry = ctx.createGain();
      dry.gain.value = 0.84;
      const bit = this.makeBitCrusherNode(ctx, { bits: 8, normfreq: 0.8 });

      input.connect(comp);
      comp.connect(lowpass);
      lowpass.connect(dry);
      lowpass.connect(convolver);
      convolver.connect(wet);
      dry.connect(bit);
      wet.connect(bit);
      bit.connect(destination);
      this.nodes.push(input, comp, lowpass, convolver, wet, dry, bit);
      return input;
    }

    makeBitCrusherNode(ctx, options) {
      const bits = options.bits || 8;
      const normfreq = options.normfreq || 1;
      const node = ctx.createScriptProcessor(4096, 1, 1);
      let phaser = 0;
      let last = 0;
      const step = Math.pow(0.5, bits);
      node.onaudioprocess = function (e) {
        const input = e.inputBuffer.getChannelData(0);
        const output = e.outputBuffer.getChannelData(0);
        for (let i = 0; i < input.length; i += 1) {
          phaser += normfreq;
          if (phaser >= 1.0) {
            phaser -= 1.0;
            last = step * Math.floor(input[i] / step + 0.5);
          }
          output[i] = last;
        }
      };
      return node;
    }

    makeTinyReverb(ctx) {
      const c = ctx.createConvolver();
      const length = Math.floor(ctx.sampleRate * 0.6);
      const i = ctx.createBuffer(2, length, ctx.sampleRate);
      for (let ch = 0; ch < 2; ch += 1) {
        const d = i.getChannelData(ch);
        for (let n = 0; n < length; n += 1) d[n] = (Math.random() * 2 - 1) * Math.pow(1 - n / length, 2.2);
      }
      c.buffer = i;
      return c;
    }

    scheduleTone(ctx, target, start, duration, opts) {
      const osc = ctx.createOscillator();
      osc.type = opts.type || 'sine';
      osc.frequency.setValueAtTime(Math.max(20, opts.from || 220), start);
      if (opts.to) osc.frequency.exponentialRampToValueAtTime(Math.max(20, opts.to), start + duration);
      const gain = ctx.createGain();
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(Math.max(0.001, opts.peak || 0.2), start + Math.min(0.03, duration * 0.2));
      gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
      osc.connect(gain);
      gain.connect(target);
      osc.start(start);
      osc.stop(start + duration + 0.03);
      this.activeOscillators.push(osc);
      this.nodes.push(osc, gain);
    }

    scheduleNoise(ctx, target, start, duration, opts) {
      const buffer = ctx.createBuffer(1, Math.max(1, Math.floor(ctx.sampleRate * duration)), ctx.sampleRate);
      const data = buffer.getChannelData(0);
      for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;
      const source = ctx.createBufferSource();
      source.buffer = buffer;
      const filter = ctx.createBiquadFilter();
      filter.type = opts.filterType || 'bandpass';
      filter.frequency.value = opts.freq || 1400;
      filter.Q.value = opts.q || 1;
      const gain = ctx.createGain();
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(Math.max(0.001, opts.peak || 0.12), start + Math.min(0.05, duration * 0.35));
      gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
      source.connect(filter);
      filter.connect(gain);
      gain.connect(target);
      source.start(start);
      source.stop(start + duration + 0.02);
      this.nodes.push(source, filter, gain);
    }

    renderEvent(ctx, destination, event, atTime) {
      const duration = clamp(Number(event.duration || 0.2), 0.03, 8);
      const intensity = clamp(Number(event.intensity || 0.5), 0, 1);
      const p = event.params || {};

      switch (event.engine) {
        case 'glissando_rise':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 160, to: p.to || 1280, peak: 0.12 + intensity * 0.2, type: 'sawtooth' });
          break;
        case 'synth_bloom':
          this.scheduleTone(ctx, destination, atTime, duration * 0.9, { from: p.from || 280, to: p.to || 520, peak: 0.08 + intensity * 0.15, type: 'triangle' });
          this.scheduleTone(ctx, destination, atTime + 0.02, duration, { from: (p.from || 280) * 1.5, to: (p.to || 520) * 1.5, peak: 0.05 + intensity * 0.1, type: 'sine' });
          break;
        case 'sub_swell':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.freq || 48, to: (p.freq || 48) * 1.08, peak: 0.13 + intensity * 0.2, type: 'sine' });
          break;
        case 'filtered_noise_wash':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 2800, q: 0.7, peak: 0.08 + intensity * 0.12, filterType: 'lowpass' });
          break;
        case 'ceramic_tick':
          this.scheduleTone(ctx, destination, atTime, duration * 0.25, { from: p.pitch || 980, to: 360, peak: 0.06 + intensity * 0.12, type: 'triangle' });
          break;
        case 'paper_crackle':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 2100, q: 1.4, peak: 0.05 + intensity * 0.1, filterType: 'highpass' });
          break;
        case 'steam_hiss':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 5200, q: 0.5, peak: 0.04 + intensity * 0.1, filterType: 'lowpass' });
          break;
        case 'tape_stop_drop':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 280, to: p.to || 35, peak: 0.1 + intensity * 0.13, type: 'square' });
          break;
        case 'bit_pulse':
          this.scheduleTone(ctx, destination, atTime, duration * 0.2, { from: p.freq || 420, to: p.freq || 420, peak: 0.08 + intensity * 0.1, type: 'square' });
          break;
        case 'breath_pulse':
          this.scheduleNoise(ctx, destination, atTime, duration, { freq: p.freq || 780, q: 0.9, peak: 0.05 + intensity * 0.1, filterType: 'bandpass' });
          break;
        case 'low_hum':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.freq || 72, to: p.freq || 72, peak: 0.06 + intensity * 0.16, type: 'sine' });
          break;
        case 'digital_shimmer':
          this.scheduleTone(ctx, destination, atTime, duration * 0.7, { from: p.from || 1200, to: p.to || 2400, peak: 0.04 + intensity * 0.08, type: 'triangle' });
          break;
        default:
          break;
      }
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
      const now = this.ctx.currentTime + 0.05;
      this.masterNode = this.buildMasterChain(this.ctx, this.ctx.destination);
      pkg.audio_events.forEach((event) => {
        const timeOffset = Math.max(0, Number(event.time || 0));
        this.renderEvent(this.ctx, this.masterNode, event, now + timeOffset);
      });
      const total = pkg.audio_events.reduce((max, event) => Math.max(max, Number(event.time || 0) + Number(event.duration || 0.2)), 0);
      this.isPlaying = true;
      setTimeout(() => { this.isPlaying = false; this.clearActiveNodes(); }, (total + 1.1) * 1000);
      return { startAt: now, total };
    }

    async exportWav(pkg) {
      if (!this.validateRecipe(pkg)) throw new Error('Cannot export: invalid audio events.');
      const sr = 44100;
      const lengthSec = Math.max(2, pkg.audio_events.reduce((max, e) => Math.max(max, Number(e.time || 0) + Number(e.duration || 0.2)), 0) + 1);
      const offline = new OfflineAudioContext(1, Math.ceil(lengthSec * sr), sr);
      const master = this.buildMasterChain(offline, offline.destination);
      pkg.audio_events.forEach((event) => this.renderEvent(offline, master, event, Math.max(0, Number(event.time || 0))));
      const rendered = await offline.startRendering();
      return this.audioBufferToWav(rendered);
    }

    audioBufferToWav(buffer) {
      const channelData = buffer.getChannelData(0);
      const bytes = 44 + channelData.length * 2;
      const view = new DataView(new ArrayBuffer(bytes));
      const writeString = (o, s) => { for (let i = 0; i < s.length; i += 1) view.setUint8(o + i, s.charCodeAt(i)); };
      writeString(0, 'RIFF');
      view.setUint32(4, 36 + channelData.length * 2, true);
      writeString(8, 'WAVEfmt ');
      view.setUint32(16, 16, true);
      view.setUint16(20, 1, true);
      view.setUint16(22, 1, true);
      view.setUint32(24, buffer.sampleRate, true);
      view.setUint32(28, buffer.sampleRate * 2, true);
      view.setUint16(32, 2, true);
      view.setUint16(34, 16, true);
      writeString(36, 'data');
      view.setUint32(40, channelData.length * 2, true);
      let o = 44;
      for (let i = 0; i < channelData.length; i += 1) {
        const s = Math.max(-1, Math.min(1, channelData[i]));
        view.setInt16(o, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
        o += 2;
      }
      return new Blob([view], { type: 'audio/wav' });
    }
  }

  window.AsmrFoleyEngine = AsmrFoleyEngine;
})(window);
