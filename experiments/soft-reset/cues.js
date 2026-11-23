/*
 * SOFT RESET — 60-second AI Film Club scene score.
 *
 * A deterministic timeline for the Gastown simulator's Station East harbour
 * world. This module does not mutate the live scene, generate audio, or
 * impersonate an AI model. A film renderer can sample it at arbitrary times.
 *
 * Coordinate music to visual states by passing measured or manually mapped
 * 0..1 audio levels into sample(t, { bass, mids, highs }).
 *
 * Browser: window.GastownFilmCues.sample(t, energy)
 * Node:    require('./cues.js').sample(t, energy)
 */
(function (root, factory) {
  'use strict';
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.GastownFilmCues = api;
})(typeof window !== 'undefined' ? window : null, function () {
  'use strict';
  const DURATION = 60;
  const CUES = Object.freeze([
    Object.freeze({ id: 'the-world', from: 0, to: 10, title: 'The world', audio: 'Vancouver field sound + one human motif' }),
    Object.freeze({ id: 'accelerating', from: 10, to: 24, title: 'The machine wakes', audio: 'Magenta pulse, motors, pitch ascent' }),
    Object.freeze({ id: 'war', from: 24, to: 38, title: 'The war machine', audio: 'Full AI/human orchestra, accelerating percussion' }),
    Object.freeze({ id: 'interruption', from: 38, to: 48, title: 'A human frequency', audio: 'Abrupt silence then Suzy voice / single sustained note' }),
    Object.freeze({ id: 'bloom', from: 48, to: 60, title: 'The harbour learns to sing', audio: 'Magenta harmony rejoins the human voice' })
  ]);
  const clamp01 = v => Math.max(0, Math.min(1, Number.isFinite(v) ? v : 0));
  const clampTime = t => Math.max(0, Math.min(DURATION, Number.isFinite(t) ? t : 0));
  function smooth(from, to, t) {
    if (to <= from) return t >= to ? 1 : 0;
    const x = clamp01((t - from) / (to - from));
    return x * x * (3 - 2 * x);
  }
  function cueAt(seconds) {
    const t = clampTime(seconds);
    return CUES.find(c => t >= c.from && t < c.to) || CUES[CUES.length - 1];
  }
  function sample(seconds, energy) {
    const t = clampTime(seconds);
    const a = energy && typeof energy === 'object' ? energy : {};
    const bass = clamp01(a.bass), mids = clamp01(a.mids), highs = clamp01(a.highs);
    const destruction = clamp01(smooth(10, 32, t) * (1 - smooth(38, 58, t)));
    const war = clamp01(smooth(22, 31, t) * (1 - smooth(37, 41, t)));
    const bloom = smooth(40, 60, t);
    const note = smooth(38, 43, t);
    return Object.freeze({
      seconds: t,
      cue: cueAt(t).id,
      progress: t / DURATION,
      // Normalized, descriptive values. A Three.js adapter owns the visuals.
      distortion: clamp01(destruction * (0.85 + 0.15 * highs)),
      mechanicalGrowth: clamp01(smooth(12, 33, t) * (1 - smooth(41, 60, t))),
      warningGlow: clamp01(war * (0.6 + 0.4 * bass)),
      fracturedSky: clamp01(destruction * (0.85 + 0.15 * mids)),
      // 'love' is a generative change, not a pink-screen color swap.
      bioluminescence: clamp01(bloom * (0.8 + 0.2 * highs)),
      collectiveMotion: clamp01(bloom * (0.7 + 0.3 * mids)),
      humanSignal: note,
      peace: clamp01(bloom * (1 - 0.2 * bass)),
      visualPulse: clamp01((bass * 0.65 + mids * 0.25 + highs * 0.1) * (0.25 + destruction * 0.35 + bloom * 0.4)),
      cameraPreset: 'station-east-fence-lookout',
      isSilentTurn: t >= 38 && t < 41
    });
  }
  return Object.freeze({ DURATION, CUES, sample, cueAt, smooth });
});
