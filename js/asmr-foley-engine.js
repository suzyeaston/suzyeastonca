(function (window) {
  'use strict';

  const ENGINE_NAMES = [
    'plastic_tap', 'paper_crinkle', 'soft_tear', 'adhesive_peel', 'rain_hiss',
    'breath_pulse', 'ceramic_tick', 'ui_bloop', 'low_hum', 'fiber_brush'
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
      this.currentRecipe = null;
      this.isPlaying = false;
    }

    async ensureContext() {
      if (!window.AudioContext && !window.webkitAudioContext) {
        throw new Error('Web Audio is unavailable in this browser.');
      }
      if (!this.ctx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        this.ctx = new Ctx();
      }
      if (this.ctx.state === 'suspended') {
        await this.ctx.resume();
      }
      return this.ctx;
    }

    setRecipe(recipe) {
      this.currentRecipe = recipe || null;
    }

    clearActiveNodes() {
      this.activeOscillators.forEach((osc) => {
        try { osc.stop(); } catch (e) { }
      });
      this.activeOscillators = [];
      this.nodes.forEach((node) => {
        try { node.disconnect(); } catch (e) { }
      });
      this.nodes = [];
    }

    stop() {
      this.clearActiveNodes();
      this.isPlaying = false;
    }

    buildMasterChain(ctx, destination, master = {}) {
      const input = ctx.createGain();
      input.gain.value = 0.95;

      const lowpass = ctx.createBiquadFilter();
      lowpass.type = 'lowpass';
      lowpass.frequency.value = clamp(Number(master.lowpass_hz || 12000), 600, 18000);

      const drive = ctx.createWaveShaper();
      drive.curve = this.makeSaturationCurve(25);
      drive.oversample = '2x';

      const wet = ctx.createGain();
      wet.gain.value = clamp(Number(master.reverb_wet || 0.1), 0, 0.6);
      const dry = ctx.createGain();
      dry.gain.value = 1 - wet.gain.value;

      const convolver = this.makeTinyReverb(ctx);
      const bitNode = this.makeBitCrusherNode(ctx, {
        bits: clamp(Number(master.bitcrusher_bits || 8), 3, 16),
        normfreq: 1 / clamp(Number(master.downsample_factor || 1), 1, 12)
      });

      input.connect(lowpass);
      lowpass.connect(drive);
      drive.connect(dry);
      drive.connect(convolver);
      convolver.connect(wet);
      dry.connect(bitNode);
      wet.connect(bitNode);
      bitNode.connect(destination);

      this.nodes.push(input, lowpass, drive, dry, wet, convolver, bitNode);
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
      const convolver = ctx.createConvolver();
      const length = ctx.sampleRate * 0.4;
      const impulse = ctx.createBuffer(2, length, ctx.sampleRate);
      for (let ch = 0; ch < 2; ch += 1) {
        const data = impulse.getChannelData(ch);
        for (let i = 0; i < length; i += 1) {
          data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / length, 2);
        }
      }
      convolver.buffer = impulse;
      return convolver;
    }

    makeSaturationCurve(amount) {
      const k = typeof amount === 'number' ? amount : 50;
      const n = 44100;
      const curve = new Float32Array(n);
      const deg = Math.PI / 180;
      for (let i = 0; i < n; i += 1) {
        const x = (i * 2) / n - 1;
        curve[i] = ((3 + k) * x * 20 * deg) / (Math.PI + k * Math.abs(x));
      }
      return curve;
    }

    scheduleNoiseBurst(ctx, target, start, duration, opts = {}) {
      const buffer = ctx.createBuffer(1, Math.floor(ctx.sampleRate * duration), ctx.sampleRate);
      const data = buffer.getChannelData(0);
      for (let i = 0; i < data.length; i += 1) {
        data[i] = (Math.random() * 2 - 1) * (opts.color || 1);
      }
      const source = ctx.createBufferSource();
      source.buffer = buffer;

      const filter = ctx.createBiquadFilter();
      filter.type = opts.filterType || 'bandpass';
      filter.frequency.value = opts.freq || 1400;
      filter.Q.value = opts.q || 1;

      const gain = ctx.createGain();
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(opts.peak || 0.15, start + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

      source.connect(filter);
      filter.connect(gain);
      gain.connect(target);

      source.start(start);
      source.stop(start + duration + 0.02);

      this.nodes.push(source, filter, gain);
    }

    scheduleTone(ctx, target, start, duration, opts = {}) {
      const osc = ctx.createOscillator();
      osc.type = opts.type || 'triangle';
      osc.frequency.setValueAtTime(opts.from || 220, start);
      if (opts.to) {
        osc.frequency.exponentialRampToValueAtTime(Math.max(20, opts.to), start + duration);
      }
      const gain = ctx.createGain();
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(opts.peak || 0.2, start + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
      osc.connect(gain);
      gain.connect(target);
      osc.start(start);
      osc.stop(start + duration + 0.03);
      this.activeOscillators.push(osc);
      this.nodes.push(osc, gain);
    }

    renderEvent(ctx, destination, event, atTime) {
      const duration = clamp(Number(event.duration || 0.2), 0.03, 4);
      const p = event.params || {};

      switch (event.engine) {
        case 'plastic_tap':
          this.scheduleTone(ctx, destination, atTime, duration * 0.4, { from: p.pitch || 620, to: 280, peak: 0.18, type: 'square' });
          this.scheduleNoiseBurst(ctx, destination, atTime, duration * 0.25, { freq: 1800, q: 6, peak: 0.07 });
          break;
        case 'paper_crinkle':
          this.scheduleNoiseBurst(ctx, destination, atTime, duration, { freq: 2500, q: 0.8, peak: 0.12, filterType: 'highpass' });
          break;
        case 'soft_tear':
          this.scheduleNoiseBurst(ctx, destination, atTime, duration, { freq: 1200, q: 1.5, peak: 0.14 });
          this.scheduleTone(ctx, destination, atTime, duration * 0.5, { from: 180, to: 120, peak: 0.05 });
          break;
        case 'adhesive_peel':
          this.scheduleNoiseBurst(ctx, destination, atTime, duration, { freq: 900, q: 2, peak: 0.1 });
          this.scheduleTone(ctx, destination, atTime, duration, { from: 320, to: 90, peak: 0.08, type: 'sawtooth' });
          break;
        case 'rain_hiss':
          this.scheduleNoiseBurst(ctx, destination, atTime, duration, { freq: 6000, q: 0.7, peak: 0.09, filterType: 'lowpass' });
          break;
        case 'breath_pulse':
          this.scheduleNoiseBurst(ctx, destination, atTime, duration, { freq: 700, q: 0.9, peak: 0.1 });
          break;
        case 'ceramic_tick':
          this.scheduleTone(ctx, destination, atTime, duration * 0.25, { from: p.pitch || 900, to: 300, peak: 0.12, type: 'triangle' });
          break;
        case 'ui_bloop':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.from || 320, to: p.to || 560, peak: 0.16, type: 'square' });
          break;
        case 'low_hum':
          this.scheduleTone(ctx, destination, atTime, duration, { from: p.freq || 70, to: p.freq || 70, peak: 0.15, type: 'sine' });
          break;
        case 'fiber_brush':
          this.scheduleNoiseBurst(ctx, destination, atTime, duration, { freq: 1600, q: 1.1, peak: 0.08 });
          break;
        default:
          break;
      }
    }

    validateRecipe(recipe) {
      if (!recipe || typeof recipe !== 'object') return false;
      if (!Array.isArray(recipe.events)) return false;
      return recipe.events.every((event) => ENGINE_NAMES.includes(event.engine));
    }

    async preview(recipe) {
      await this.ensureContext();
      if (!this.validateRecipe(recipe)) {
        throw new Error('Sound recipe is invalid or uses unsupported engines.');
      }
      this.stop();
      const now = this.ctx.currentTime + 0.05;
      this.masterNode = this.buildMasterChain(this.ctx, this.ctx.destination, recipe.master || {});
      recipe.events.forEach((event) => {
        const timeOffset = Math.max(0, Number(event.time || 0));
        this.renderEvent(this.ctx, this.masterNode, event, now + timeOffset);
      });
      const total = recipe.events.reduce((max, event) => {
        const end = Number(event.time || 0) + Number(event.duration || 0.2);
        return Math.max(max, end);
      }, 0);
      this.isPlaying = true;
      setTimeout(() => { this.isPlaying = false; this.clearActiveNodes(); }, (total + 1.5) * 1000);
    }

    async exportWav(recipe) {
      if (!this.validateRecipe(recipe)) {
        throw new Error('Cannot export: invalid recipe.');
      }
      const lengthSec = Math.max(2, recipe.events.reduce((max, event) => Math.max(max, Number(event.time || 0) + Number(event.duration || 0.2)), 0) + 1);
      const sr = 44100;
      const offline = new OfflineAudioContext(1, Math.ceil(lengthSec * sr), sr);
      const master = this.buildMasterChain(offline, offline.destination, recipe.master || {});
      recipe.events.forEach((event) => {
        this.renderEvent(offline, master, event, Math.max(0, Number(event.time || 0)));
      });
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
      let offset = 44;
      for (let i = 0; i < channelData.length; i += 1) {
        const s = Math.max(-1, Math.min(1, channelData[i]));
        view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
        offset += 2;
      }
      return new Blob([view], { type: 'audio/wav' });
    }
  }

  window.AsmrFoleyEngine = AsmrFoleyEngine;
})(window);
