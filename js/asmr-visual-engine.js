(function (window) {
  'use strict';

  const VISUAL_REGISTRY = [
    { id: 'scanline_field', label: 'Scanline Field', category: 'support', priority: 'support', description: 'CRT line drift overlay.', expected_shape: 'horizontal scan lines', renderer: 'drawScanlineField', intensity: [0.08, 0.4], openingHero: false, supportOnly: true },
    { id: 'pixel_grid_pulse', label: 'Pixel Grid Pulse', category: 'support', priority: 'support', description: 'Retro pixel grid pulse.', expected_shape: 'small grid cells', renderer: 'drawPixelGridPulse', intensity: [0.1, 0.35], openingHero: false, supportOnly: true },
    { id: 'wireframe_horizon', label: 'Wireframe Horizon', category: 'scene', priority: 'support', description: 'Perspective horizon lines.', expected_shape: 'vanishing horizon fan', renderer: 'drawWireframeHorizon', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'radial_bloom', label: 'Radial Bloom', category: 'atmosphere', priority: 'support', description: 'Soft center bloom.', expected_shape: 'radial glow', renderer: 'drawRadialBloom', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'particle_trail', label: 'Particle Trail', category: 'motion', priority: 'support', description: 'Moving particle points.', expected_shape: 'drifting dots', renderer: 'drawParticleTrail', intensity: [0.2, 0.6], openingHero: false, supportOnly: true },
    { id: 'glitch_flash', label: 'Glitch Flash', category: 'support', priority: 'support', description: 'Brief digital glitch streaks.', expected_shape: 'horizontal flashes', renderer: 'drawGlitchFlash', intensity: [0.05, 0.25], openingHero: false, supportOnly: true },
    { id: 'waveform_ring', label: 'Waveform Ring', category: 'motion', priority: 'support', description: 'Oscillating ring pulse.', expected_shape: 'wobble ring', renderer: 'drawWaveformRing', intensity: [0.15, 0.5], openingHero: false, supportOnly: true },
    { id: 'macro_texture_drift', label: 'Macro Texture Drift', category: 'texture', priority: 'support', description: 'Linear texture drift.', expected_shape: 'short bars', renderer: 'drawMacroTextureDrift', intensity: [0.12, 0.35], openingHero: false, supportOnly: true },
    { id: 'signal_bars', label: 'Signal Bars', category: 'support', priority: 'support', description: 'Diagnostic signal meter bars.', expected_shape: 'small stacked bars', renderer: 'drawSignalBars', intensity: [0.05, 0.2], openingHero: false, supportOnly: true },
    { id: 'text_reveal', label: 'Text Reveal', category: 'support', priority: 'support', description: 'Short terminal text reveal.', expected_shape: 'single text block', renderer: 'drawTextReveal', intensity: [0.15, 0.5], openingHero: false, supportOnly: true },
    { id: 'volumetric_fog', label: 'Volumetric Fog', category: 'atmosphere', priority: 'support', description: 'Layered fog drift.', expected_shape: 'horizontal haze bands', renderer: 'drawVolumetricFog', intensity: [0.2, 0.6], openingHero: false, supportOnly: true },
    { id: 'glass_refraction', label: 'Glass Refraction', category: 'texture', priority: 'support', description: 'Refraction contour lines.', expected_shape: 'curved refractive lines', renderer: 'drawGlassRefraction', intensity: [0.15, 0.5], openingHero: false, supportOnly: true },
    { id: 'halo_glyphs', label: 'Halo Glyphs', category: 'atmosphere', priority: 'support', description: 'Arc glyph halos.', expected_shape: 'partial circular arcs', renderer: 'drawHaloGlyphs', intensity: [0.15, 0.5], openingHero: false, supportOnly: true },
    { id: 'cathedral_beam', label: 'Cathedral Beam', category: 'atmosphere', priority: 'support', description: 'Focused light beam.', expected_shape: 'vertical cone beam', renderer: 'drawCathedralBeam', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'monolith_silhouette', label: 'Monolith Silhouette', category: 'scene', priority: 'hero', description: 'Large central monolith.', expected_shape: 'single tall slab', renderer: 'drawMonolithSilhouette', intensity: [0.25, 0.75], openingHero: true, supportOnly: false },
    { id: 'starfield_drift', label: 'Starfield Drift', category: 'atmosphere', priority: 'support', description: 'Sparse moving stars.', expected_shape: 'small drifting stars', renderer: 'drawStarfieldDrift', intensity: [0.08, 0.3], openingHero: false, supportOnly: true },
    { id: 'orbiting_shards', label: 'Orbiting Shards', category: 'motion', priority: 'support', description: 'Orbiting shard fragments.', expected_shape: 'angular orbit points', renderer: 'drawOrbitingShards', intensity: [0.15, 0.5], openingHero: false, supportOnly: true },
    { id: 'pulse_orb', label: 'Pulse Orb', category: 'motion', priority: 'support', description: 'Core pulse orb.', expected_shape: 'pulsing circle', renderer: 'drawPulseOrb', intensity: [0.2, 0.58], openingHero: false, supportOnly: true },
    { id: 'energy_column', label: 'Energy Column', category: 'motion', priority: 'support', description: 'Energy column pulse.', expected_shape: 'soft vertical beam', renderer: 'drawEnergyColumn', intensity: [0.2, 0.55], openingHero: false, supportOnly: true },
    { id: 'refraction_ripple', label: 'Refraction Ripple', category: 'texture', priority: 'support', description: 'Ripple distortion rings.', expected_shape: 'ripple circles', renderer: 'drawRefractionRipple', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'chromatic_veil', label: 'Chromatic Veil', category: 'support', priority: 'support', description: 'Chromatic split veil.', expected_shape: 'subtle RGB haze', renderer: 'drawChromaticVeil', intensity: [0.06, 0.2], openingHero: false, supportOnly: true },
    { id: 'terminal_runes', label: 'Terminal Runes', category: 'texture', priority: 'support', description: 'Arcade rune marks.', expected_shape: 'tiny glyph strokes', renderer: 'drawTerminalRunes', intensity: [0.1, 0.35], openingHero: false, supportOnly: true },
    { id: 'snow_drift', label: 'Snow Drift', category: 'atmosphere', priority: 'support', description: 'Snow particles drifting.', expected_shape: 'falling dots', renderer: 'drawSnowDrift', intensity: [0.25, 0.7], openingHero: false, supportOnly: true },
    { id: 'amber_halo', label: 'Amber Halo', category: 'atmosphere', priority: 'support', description: 'Warm streetlight halos.', expected_shape: 'amber glow circles', renderer: 'drawAmberHalo', intensity: [0.15, 0.4], openingHero: false, supportOnly: true },
    { id: 'wet_reflection_shimmer', label: 'Wet Reflection Shimmer', category: 'texture', priority: 'support', description: 'Wet streak reflections.', expected_shape: 'vertical reflection strips', renderer: 'drawWetReflectionShimmer', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'brick_shadow_drift', label: 'Brick Shadow Drift', category: 'texture', priority: 'support', description: 'Brick shadow texture.', expected_shape: 'brick-like side blocks', renderer: 'drawBrickShadowDrift', intensity: [0.12, 0.38], openingHero: false, supportOnly: true },
    { id: 'steam_plume_column', label: 'Steam Plume Column', category: 'atmosphere', priority: 'support', description: 'Steam column plume.', expected_shape: 'soft column mist', renderer: 'drawSteamPlumeColumn', intensity: [0.18, 0.5], openingHero: false, supportOnly: true },
    { id: 'clock_face_reveal', label: 'Clock Face Reveal', category: 'landmark', priority: 'hero', description: 'Clock face reveal motif.', expected_shape: 'round clock face', renderer: 'drawClockFaceReveal', intensity: [0.25, 0.7], openingHero: true, supportOnly: false },
    { id: 'harbor_mist', label: 'Harbor Mist', category: 'atmosphere', priority: 'support', description: 'Harbor fog sheet.', expected_shape: 'layered mist rows', renderer: 'drawHarborMist', intensity: [0.2, 0.6], openingHero: false, supportOnly: true },
    { id: 'neon_wet_reflections', label: 'Neon Wet Reflections', category: 'texture', priority: 'support', description: 'Neon wet pavement texture.', expected_shape: 'glowing vertical puddles', renderer: 'drawNeonWetReflections', intensity: [0.2, 0.55], openingHero: false, supportOnly: true },
    { id: 'winter_particulate_depth', label: 'Winter Particulate Depth', category: 'atmosphere', priority: 'support', description: 'Depth snow particles.', expected_shape: 'layered snow points', renderer: 'drawWinterParticulateDepth', intensity: [0.2, 0.62], openingHero: false, supportOnly: true },
    { id: 'gastown_clock_silhouette', label: 'Gastown Clock', category: 'landmark', priority: 'hero', description: 'Steam clock silhouette.', expected_shape: 'tall steam clock with roof cap, round face, glass body, side pipes, and pedestal base', renderer: 'drawGastownClockSilhouette', intensity: [0.35, 0.8], openingHero: true, supportOnly: false },
    { id: 'cobblestone_perspective', label: 'Cobblestone Perspective', category: 'texture', priority: 'support', description: 'Cobblestone lane texture.', expected_shape: 'ground grid stones', renderer: 'drawCobblestonePerspective', intensity: [0.18, 0.45], openingHero: false, supportOnly: true },
    { id: 'brick_wall_parallax', label: 'Brick Wall Parallax', category: 'texture', priority: 'support', description: 'Brick facade sides.', expected_shape: 'left/right brick blocks', renderer: 'drawBrickWallParallax', intensity: [0.18, 0.45], openingHero: false, supportOnly: true },
    { id: 'streetlamp_halo_row', label: 'Streetlamp Halo Row', category: 'atmosphere', priority: 'support', description: 'Streetlamp halo row.', expected_shape: 'four halo circles', renderer: 'drawStreetlampHaloRow', intensity: [0.18, 0.48], openingHero: false, supportOnly: true },
    { id: 'granville_neon_marquee', label: 'Granville Neon Marquee', category: 'scene', priority: 'support', description: 'Neon marquee slabs.', expected_shape: 'vertical neon panels', renderer: 'drawGranvilleNeonMarquee', intensity: [0.18, 0.48], openingHero: false, supportOnly: true },
    { id: 'neon_sign_flicker', label: 'Neon Sign Flicker', category: 'scene', priority: 'support', description: 'Neon strip flicker.', expected_shape: 'horizontal neon bars', renderer: 'drawNeonSignFlicker', intensity: [0.18, 0.5], openingHero: false, supportOnly: true },
    { id: 'traffic_light_glow', label: 'Traffic Light Glow', category: 'scene', priority: 'support', description: 'Traffic signal glow.', expected_shape: 'three light halos', renderer: 'drawTrafficLightGlow', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'skytrain_track', label: 'SkyTrain Track', category: 'motion', priority: 'support', description: 'Elevated track lines.', expected_shape: 'dual horizontal track', renderer: 'drawSkytrainTrack', intensity: [0.2, 0.55], openingHero: false, supportOnly: true },
    { id: 'skytrain_pass_visual', label: 'SkyTrain Pass', category: 'motion', priority: 'hero', description: 'SkyTrain car pass-through.', expected_shape: 'moving train block', renderer: 'drawSkytrainPassVisual', intensity: [0.25, 0.7], openingHero: true, supportOnly: false },
    { id: 'bus_pass_visual', label: 'Bus Pass', category: 'motion', priority: 'support', description: 'Bus pass-through.', expected_shape: 'moving bus block', renderer: 'drawBusPassVisual', intensity: [0.2, 0.6], openingHero: false, supportOnly: true },
    { id: 'northshore_mountain_ridge', label: 'North Shore Ridge', category: 'scene', priority: 'support', description: 'North Shore mountain ridge.', expected_shape: 'mountain silhouette ridge', renderer: 'drawNorthshoreMountainRidge', intensity: [0.22, 0.62], openingHero: false, supportOnly: true },
    { id: 'mountain_mist_layers', label: 'Mountain Mist Layers', category: 'atmosphere', priority: 'support', description: 'Mist bands for mountain scene.', expected_shape: 'layered mist bands', renderer: 'drawMountainMistLayers', intensity: [0.18, 0.5], openingHero: false, supportOnly: true },
    { id: 'rain_streaks', label: 'Rain Streaks', category: 'atmosphere', priority: 'support', description: 'Diagonal rain lines.', expected_shape: 'falling streaks', renderer: 'drawRainStreaks', intensity: [0.2, 0.6], openingHero: false, supportOnly: true },
    { id: 'puddle_reflections', label: 'Puddle Reflections', category: 'texture', priority: 'support', description: 'Puddle neon reflections.', expected_shape: 'vertical reflection pools', renderer: 'drawPuddleReflections', intensity: [0.18, 0.5], openingHero: false, supportOnly: true },
    { id: 'science_world_dome', label: 'Science World Dome', category: 'landmark', priority: 'hero', description: 'Geodesic dome silhouette with lattice.', expected_shape: 'dome arc + triangle lattice', renderer: 'drawScienceWorldDome', intensity: [0.35, 0.85], openingHero: true, supportOnly: false },
    { id: 'chinatown_gate', label: 'Chinatown Gate', category: 'landmark', priority: 'hero', description: 'Chinatown gate profile.', expected_shape: 'two pillars + layered roof', renderer: 'drawChinatownGate', intensity: [0.3, 0.8], openingHero: true, supportOnly: false },
    { id: 'english_bay_inukshuk', label: 'English Bay Inukshuk', category: 'landmark', priority: 'hero', description: 'Stacked stone Inukshuk reveal.', expected_shape: 'base + torso + arm stone + head', renderer: 'drawEnglishBayInukshuk', intensity: [0.35, 0.88], openingHero: true, supportOnly: false },
    { id: 'maritime_museum_sailroof', label: 'Maritime Museum Sail Roof', category: 'landmark', priority: 'hero', description: 'Sail roof gesture.', expected_shape: 'arched sail roof line', renderer: 'drawMaritimeMuseumSailroof', intensity: [0.28, 0.8], openingHero: true, supportOnly: false },
    { id: 'lions_gate_bridge', label: 'Lions Gate Bridge', category: 'landmark', priority: 'hero', description: 'Suspension bridge silhouette with two towers, deck, top cable arc, and suspenders.', expected_shape: 'two towers + deck + hanging cable lines', renderer: 'drawLionsGateBridge', intensity: [0.35, 0.9], openingHero: true, supportOnly: false },
    { id: 'bc_place_dome', label: 'BC Place Dome', category: 'landmark', priority: 'hero', description: 'Stadium dome spoked silhouette.', expected_shape: 'low dome with radial spokes', renderer: 'drawBCPlaceDome', intensity: [0.3, 0.8], openingHero: true, supportOnly: false },
    { id: 'port_cranes', label: 'Port Cranes', category: 'landmark', priority: 'hero', description: 'Container crane row.', expected_shape: 'angular crane arms', renderer: 'drawPortCranes', intensity: [0.28, 0.75], openingHero: true, supportOnly: false },
    { id: 'planetarium_dome', label: 'Planetarium Dome', category: 'landmark', priority: 'hero', description: 'Smooth observatory dome.', expected_shape: 'smooth dome + low base', renderer: 'drawPlanetariumDome', intensity: [0.3, 0.82], openingHero: true, supportOnly: false },
    { id: 'starfield_projection', label: 'Starfield Projection', category: 'landmark', priority: 'hero', description: 'Planetarium star projection.', expected_shape: 'dome projection arcs + stars', renderer: 'drawStarfieldProjection', intensity: [0.25, 0.75], openingHero: true, supportOnly: false },
    { id: 'constellation_lines', label: 'Constellation Lines', category: 'atmosphere', priority: 'support', description: 'Constellation line overlay.', expected_shape: 'tiny connected stars', renderer: 'drawConstellationLines', intensity: [0.12, 0.35], openingHero: false, supportOnly: true },
    { id: 'canada_place_sails', label: 'Canada Place Sails', category: 'landmark', priority: 'hero', description: 'Canada Place sail peaks.', expected_shape: 'triangular sail peaks', renderer: 'drawCanadaPlaceSails', intensity: [0.28, 0.78], openingHero: true, supportOnly: false },
    { id: 'gastown_scene', label: 'Gastown Scene', category: 'scene', priority: 'hero', description: 'Gastown scene background stack.', expected_shape: 'brick + cobblestone + clock support', renderer: 'drawGastownScene', intensity: [0.28, 0.76], openingHero: true, supportOnly: false },
    { id: 'granville_scene', label: 'Granville Scene', category: 'scene', priority: 'hero', description: 'Granville neon street scene.', expected_shape: 'neon facades + reflections', renderer: 'drawGranvilleScene', intensity: [0.28, 0.76], openingHero: true, supportOnly: false },
    { id: 'north_shore_scene', label: 'North Shore Scene', category: 'scene', priority: 'hero', description: 'North Shore ridge and mist scene.', expected_shape: 'ridge horizon + mist', renderer: 'drawNorthShoreScene', intensity: [0.3, 0.8], openingHero: true, supportOnly: false },
    { id: 'waterfront_scene', label: 'Waterfront Scene', category: 'scene', priority: 'hero', description: 'Waterfront horizon and harbor support.', expected_shape: 'waterline + distant structures', renderer: 'drawWaterfrontScene', intensity: [0.28, 0.8], openingHero: true, supportOnly: false },
    { id: 'clear_cold_shimmer', label: 'Clear Cold Shimmer', category: 'atmosphere', priority: 'support', description: 'Cold air shimmer.', expected_shape: 'soft cool haze', renderer: 'drawClearColdShimmer', intensity: [0.15, 0.45], openingHero: false, supportOnly: true },
    { id: 'ocean_surface_shimmer', label: 'Ocean Surface Shimmer', category: 'texture', priority: 'support', description: 'Ocean highlight shimmer.', expected_shape: 'horizontal sparkle strips', renderer: 'drawOceanSurfaceShimmer', intensity: [0.18, 0.52], openingHero: false, supportOnly: true },
    { id: 'seabus_silhouette', label: 'SeaBus Silhouette', category: 'landmark', priority: 'hero', description: 'SeaBus vessel silhouette.', expected_shape: 'low ferry hull', renderer: 'drawSeabusSilhouette', intensity: [0.28, 0.76], openingHero: true, supportOnly: false },
    { id: 'gull_silhouettes', label: 'Gull Silhouettes', category: 'motion', priority: 'support', description: 'Subtle gull V silhouettes.', expected_shape: '2-4 small V arcs', renderer: 'drawGullSilhouettes', intensity: [0.06, 0.2], openingHero: false, supportOnly: true }
  ];

  const VISUAL_TYPES = VISUAL_REGISTRY.map((item) => item.id);
  const VISUAL_REGISTRY_MAP = VISUAL_REGISTRY.reduce((acc, item) => { acc[item.id] = item; return acc; }, {});
  const VISUAL_CATEGORY = VISUAL_REGISTRY.reduce((acc, item) => {
    if (!acc[item.category]) acc[item.category] = [];
    acc[item.category].push(item.id);
    return acc;
  }, {});
  const SUPPORT_OVERLAY_TYPES = ['scanline_field', 'chromatic_veil', 'signal_bars', 'pixel_grid_pulse', 'glitch_flash', 'starfield_drift'];

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function hasAnyTag(tags, needles) {
    return needles.some((needle) => tags.includes(needle));
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
      this.lowResTarget = null;
      this.debugOptions = { enabled: false, showProvenance: false };
      this.debugFrameState = null;
      this.previewTimeout = null;
      this.previewToken = 0;
      this.playbackOptions = { loopPlayback: false };
      this.lastPreviewReport = null;
    }


    getVisualRegistry() {
      return VISUAL_REGISTRY.slice();
    }

    setDebugOptions(options) {
      this.debugOptions = Object.assign({}, this.debugOptions, options || {});
    }

    buildSingleVisualTimeline(visualType, runtime, options = {}) {
      const clampedRuntime = clamp(Number(runtime || 3.2), 1.2, 6);
      const intensity = clamp(Number(options.intensity || 0.85), 0.1, 1);
      return {
        runtime_seconds: clampedRuntime,
        visual_events: [{
          time: 0,
          duration: clampedRuntime,
          visual_type: visualType,
          intensity,
          params: {
            debug_preview: true,
            minimal_context: true,
            ...(options.params && typeof options.params === 'object' ? options.params : {})
          },
          sync_role: 'debug_single_preview'
        }],
        sync_points: [],
        end_card: { use_end_card: false },
        title: 'Visual Debug Preview',
        style_tags: Array.isArray(options.style_tags) ? options.style_tags : ['debug_preview']
      };
    }

    previewVisualMotifById(visualType, options = {}) {
      if (!VISUAL_REGISTRY_MAP[visualType]) {
        this.lastPreviewReport = {
          motifId: visualType,
          rendererMissing: true,
          pipelineOk: false,
          warning: 'Renderer missing'
        };
        if (this.debugOptions && this.debugOptions.enabled && window.console && typeof window.console.warn === 'function') {
          window.console.warn('[ASMR] No visual renderer found for motif id:', visualType);
        }
        return false;
      }
      if (this.previewTimeout) {
        window.clearTimeout(this.previewTimeout);
        this.previewTimeout = null;
      }

      const previewToken = this.previewToken + 1;
      this.previewToken = previewToken;
      const runtimeSeconds = clamp(Number(options.runtimeSeconds || 1.8), 1.2, 6);
      const shouldAutoStop = options.autoStop !== false;
      const holdLastFrame = options.holdLastFrame !== false;
      const loopPreview = !!options.loopPreview;
      const preserveFrameOnStop = options.preserveFrameOnStop !== false;

      this.loadTimeline(this.buildSingleVisualTimeline(visualType, runtimeSeconds, options));
      const timelineEvents = (this.timeline && Array.isArray(this.timeline.visualEvents)) ? this.timeline.visualEvents : [];
      const hasMotifEvent = timelineEvents.some((event) => event && event.visual_type === visualType);
      this.resize();
      let pipelineOk = true;
      try {
        const previewT = Math.min(Math.max(0.15, runtimeSeconds * 0.11), 0.2);
        const activeAtPreviewT = timelineEvents.some((event) => {
          const start = Number(event.time || 0);
          const duration = Math.max(0.01, Number(event.duration || 0));
          return event.visual_type === visualType && previewT >= start && previewT <= (start + duration);
        });
        this.render(activeAtPreviewT ? previewT : 0);
      } catch (err) {
        pipelineOk = false;
      }
      this.play(0, { loopPlayback: loopPreview });
      if (shouldAutoStop) {
        this.previewTimeout = window.setTimeout(() => {
          if (previewToken !== this.previewToken) return;
          this.stop({
            clearFrame: !(holdLastFrame && preserveFrameOnStop),
            resetTime: false
          });
        }, Math.round(runtimeSeconds * 1000));
      }
      this.lastPreviewReport = {
        motifId: visualType,
        rendererMissing: false,
        pipelineOk,
        warning: !hasMotifEvent
          ? 'Preview timeline missing motif event'
          : (pipelineOk ? '' : 'Rendered, but no visible motif detected'),
        activePreviewMotif: hasMotifEvent ? visualType : '',
        timelineEventCount: timelineEvents.length,
        mode: loopPreview ? 'loop' : (holdLastFrame ? 'hold' : 'oneshot')
      };
      if (!hasMotifEvent && this.debugOptions && this.debugOptions.enabled && window.console && typeof window.console.warn === 'function') {
        window.console.warn('[ASMR] Preview timeline missing motif event for:', visualType, timelineEvents);
      }
      return true;
    }

    previewVisualType(visualType, runtimeSeconds) {
      return this.previewVisualMotifById(visualType, { runtimeSeconds: runtimeSeconds || 3.2 });
    }

    cancelPreview(options = {}) {
      this.previewToken += 1;
      if (this.previewTimeout) {
        window.clearTimeout(this.previewTimeout);
        this.previewTimeout = null;
      }
      this.stop({
        clearFrame: options.clearFrame === true,
        resetTime: options.resetTime !== false
      });
    }

    getLastPreviewReport() {
      return this.lastPreviewReport ? { ...this.lastPreviewReport } : null;
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



    enrichVisualEvents(events, runtime, syncPoints) {
      const out = Array.isArray(events) ? events.slice() : [];
      const debugPreviewMode = out.some((event) => event && event.params && event.params.debug_preview === true);
      const hasValidEvents = out.some((event) => event && VISUAL_TYPES.includes(event.visual_type));
      if ((!out.length || Number(out[0].time || 99) > 0.12) && (!debugPreviewMode || !hasValidEvents)) {
        out.unshift({ time: 0, duration: Math.min(debugPreviewMode ? 2.4 : 6, runtime * 0.42), visual_type: 'volumetric_fog', intensity: debugPreviewMode ? 0.14 : 0.46, params: {}, sync_role: 'opening_atmosphere' });
      }

      const hasEarly = out.some((e) => Number(e.time || 99) <= 0.8 && Number(e.intensity || 0) >= 0.3);
      const hasEarlyHero = out.some((e) => Number(e.time || 99) <= 0.9 && ['science_world_dome', 'chinatown_gate', 'english_bay_inukshuk', 'maritime_museum_sailroof', 'lions_gate_bridge', 'bc_place_dome', 'port_cranes', 'planetarium_dome', 'starfield_projection', 'canada_place_sails', 'gastown_clock_silhouette', 'seabus_silhouette', 'waterfront_scene', 'gastown_scene', 'granville_scene', 'north_shore_scene'].includes(e.visual_type));
      if (!debugPreviewMode && !hasEarly && !hasEarlyHero) out.push({ time: 0.52, duration: 1.3, visual_type: 'pulse_orb', intensity: 0.58, params: {}, sync_role: 'early_focal' });

      if (debugPreviewMode) {
        return out.sort((a, b) => a.time - b.time);
      }

      out.sort((a, b) => a.time - b.time);
      const maxGap = Math.max(2.4, runtime * 0.2);
      const withBridges = [];
      let prev = 0;
      let bridgeAdded = false;
      out.forEach((e) => {
        if (!bridgeAdded && e.time - prev > maxGap) {
          withBridges.push({ time: prev + maxGap * 0.5, duration: 1.4, visual_type: 'volumetric_fog', intensity: 0.22, params: {}, sync_role: 'bridge_motion' });
          bridgeAdded = true;
        }
        withBridges.push(e);
        prev = e.time;
      });

      return withBridges.sort((a, b) => a.time - b.time);
    }

    normalizeVisualTimeline(pkg) {
      const runtime = clamp(Number(pkg.runtime_seconds || 20), 10, 30);
      const events = Array.isArray(pkg.visual_events) ? pkg.visual_events : [];
      let visualEvents = events
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

      visualEvents = this.enrichVisualEvents(visualEvents, runtime, syncPoints);

      return {
        runtime,
        visualEvents,
        syncPoints,
        endCard: pkg.end_card || {},
        title: String(pkg.title || 'ASMR LAB'),
        renderProfile: this.resolveRenderProfile(pkg)
      };
    }

    resolveRenderProfile(pkg) {
      const tags = (Array.isArray(pkg.style_tags) ? pkg.style_tags : []).map((t) => String(t || '').toLowerCase());
      const pixelMode = hasAnyTag(tags, ['pixel_art', '8bit', 'lowres_mode']);
      const vancouverCue = hasAnyTag(tags, ['gastown', 'granville', 'north_shore', 'vancouver', 'snow', 'rain', 'fog', 'amber', 'neon', 'brick', 'cobblestone', 'mountains']);
      const theme = {
        bgTop: '#02050c',
        bgMid: '#070d1e',
        bgBottom: '#050911',
        fog: [74, 112, 255],
        particles: [120, 220, 255],
        core: [176, 228, 255],
        amber: [255, 196, 118],
        neonA: [255, 96, 186],
        neonB: [120, 255, 220]
      };

      if (hasAnyTag(tags, ['fog', 'snow', 'rain', 'vancouver'])) {
        theme.bgTop = '#071020';
        theme.bgMid = '#10243a';
        theme.bgBottom = '#0a1a29';
        theme.fog = [122, 170, 215];
        theme.particles = [186, 220, 255];
      }
      if (hasAnyTag(tags, ['gastown', 'amber', 'brick', 'cobblestone'])) {
        theme.bgMid = '#211a21';
        theme.bgBottom = '#160f18';
        theme.amber = [255, 188, 110];
      }
      if (hasAnyTag(tags, ['neon', 'arcade', 'chiptune', 'granville'])) {
        theme.neonA = [255, 92, 205];
        theme.neonB = [108, 250, 225];
      }

      return {
        pixelMode,
        vancouverCue,
        tags,
        lowResScale: pixelMode ? 4 : 1,
        paletteSteps: pixelMode ? 24 : 0,
        orderedDither: pixelMode && hasAnyTag(tags, ['dither']),
        scanlines: true,
        glow: true,
        theme
      };
    }

    loadTimeline(pkg) {
      this.timeline = this.normalizeVisualTimeline(pkg || {});
    }

    play(clockOffset, options = {}) {
      if (!this.ctx || !this.timeline) return;
      this.stop();
      this.resize();
      this.clockOffset = Number(clockOffset || 0);
      this.startPerf = performance.now() - (this.clockOffset * 1000);
      this.playbackOptions = {
        loopPlayback: !!options.loopPlayback
      };
      this.running = true;
      this.loop();
    }

    stop(options = {}) {
      this.running = false;
      if (this.rafId) {
        cancelAnimationFrame(this.rafId);
        this.rafId = null;
      }
      this.playbackOptions = { loopPlayback: false };
      if (options.resetTime !== false) this.currentTime = 0;
      if (options.clearFrame !== false) this.clearFrame();
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
      const linger = this.timeline.endCard && this.timeline.endCard.use_end_card ? 1.25 : 1.75;
      if (this.currentTime >= this.timeline.runtime + linger) {
        if (this.playbackOptions && this.playbackOptions.loopPlayback) {
          this.startPerf = performance.now();
          this.currentTime = 0;
          this.rafId = requestAnimationFrame(this.loop.bind(this));
          return;
        }
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

    getLowResTarget(width, height, profile) {
      const scale = Math.max(2, Number(profile.lowResScale || 4));
      const lowW = Math.max(160, Math.floor(width / scale));
      const lowH = Math.max(90, Math.floor(height / scale));
      if (this.lowResTarget && this.lowResTarget.canvas.width === lowW && this.lowResTarget.canvas.height === lowH) {
        return this.lowResTarget;
      }
      this.lowResTarget = this.createRenderTarget(lowW, lowH);
      return this.lowResTarget;
    }

    applyPixelPost(ctx, width, height, profile) {
      const steps = Math.max(2, Number(profile.paletteSteps || 0));
      if (steps <= 1) return;
      const image = ctx.getImageData(0, 0, width, height);
      const data = image.data;
      const bayer = [
        [0, 8, 2, 10],
        [12, 4, 14, 6],
        [3, 11, 1, 9],
        [15, 7, 13, 5]
      ];
      const ditherOn = !!profile.orderedDither;
      for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
          const i = (y * width + x) * 4;
          const threshold = ditherOn ? ((bayer[y % 4][x % 4] / 16) - 0.5) * (255 / steps) : 0;
          for (let c = 0; c < 3; c += 1) {
            const v = clamp(data[i + c] + threshold, 0, 255);
            data[i + c] = Math.round((v / 255) * (steps - 1)) * (255 / (steps - 1));
          }
        }
      }
      ctx.putImageData(image, 0, 0);
    }

    parseWeirdnessLevel() {
      const tags = (this.timeline && this.timeline.renderProfile && this.timeline.renderProfile.tags) || [];
      const found = tags.find((tag) => /^weirdness_\d+$/.test(tag));
      if (!found) return 6;
      const level = Number(found.split('_')[1] || 6);
      return clamp(level, 1, 10);
    }

    drawSceneLayers(ctx, width, height, t, normalized, overlays) {
      const landmarkTypes = VISUAL_CATEGORY.landmark || [];
      const sceneTypes = VISUAL_CATEGORY.scene || [];
      const atmosphereTypes = VISUAL_CATEGORY.atmosphere || [];
      const motionTypes = VISUAL_CATEGORY.motion || [];
      const supportOverlays = SUPPORT_OVERLAY_TYPES;
      const weirdness = this.parseWeirdnessLevel();
      const weirdNorm = (weirdness - 1) / 9;
      let distortion = 0.04 + (0.18 - 0.04) * weirdNorm;
      const activeEvents = [];

      this.timeline.visualEvents.forEach((event) => {
        const progress = this.eventProgress(event, t);
        if (progress <= -0.03 || progress >= 1.2) return;
        activeEvents.push({ event, progress });
      });

      const hasLandmark = activeEvents.some((item) => landmarkTypes.includes(item.event.visual_type));
      const hasScene = activeEvents.some((item) => sceneTypes.includes(item.event.visual_type));
      const hasHeroVisual = hasLandmark || hasScene;
      if (hasLandmark) distortion *= 0.6;
      if (activeEvents.some((item) => ['planetarium_dome', 'starfield_projection'].includes(item.event.visual_type))) distortion *= 0.5;

      const supportCandidates = activeEvents
        .filter((item) => supportOverlays.includes(item.event.visual_type))
        .sort((a, b) => Number(b.event.intensity || 0) - Number(a.event.intensity || 0));
      const activeSupportType = supportCandidates.length ? supportCandidates[0].event.visual_type : null;

      this.drawBaseChamber(ctx, width, height, t, normalized);

      const drawByType = (predicate, dampenSupport) => {
        activeEvents.filter(predicate).forEach((item) => {
          let intensity = Math.max(0.05, Math.min(1, Number(item.event.intensity || 0.5)));
          if (dampenSupport && hasHeroVisual) intensity *= 0.18;
          this.drawEvent(ctx, item.event, item.progress, intensity, width, height, normalized);
        });
      };

      drawByType((item) => atmosphereTypes.includes(item.event.visual_type), false);
      drawByType((item) => landmarkTypes.includes(item.event.visual_type) || sceneTypes.includes(item.event.visual_type), false);
      drawByType((item) => motionTypes.includes(item.event.visual_type), hasHeroVisual);
      drawByType((item) => !atmosphereTypes.includes(item.event.visual_type)
          && !landmarkTypes.includes(item.event.visual_type)
          && !sceneTypes.includes(item.event.visual_type)
          && !motionTypes.includes(item.event.visual_type)
          && !supportOverlays.includes(item.event.visual_type), false);

      activeEvents.filter((item) => supportOverlays.includes(item.event.visual_type)).forEach((item) => {
        if (item.event.visual_type !== activeSupportType) return;
        let intensity = Math.max(0.05, Math.min(1, Number(item.event.intensity || 0.5)));
        if (hasHeroVisual) intensity *= 0.1;
        this.drawEvent(ctx, item.event, item.progress, intensity, width, height, normalized);
      });

      this.debugFrameState = {
        activeVisualTypes: activeEvents.map((item) => item.event.visual_type),
        heroVisualTypes: activeEvents
          .filter((item) => (VISUAL_REGISTRY_MAP[item.event.visual_type] && VISUAL_REGISTRY_MAP[item.event.visual_type].priority === 'hero'))
          .map((item) => item.event.visual_type),
        supportVisualTypes: activeEvents
          .filter((item) => (VISUAL_REGISTRY_MAP[item.event.visual_type] && VISUAL_REGISTRY_MAP[item.event.visual_type].priority !== 'hero'))
          .map((item) => item.event.visual_type),
        beatLabel: this.getBeatLabelAtTime(t)
      };

      if (distortion > 0) {
        ctx.save();
        const distortionAlpha = hasHeroVisual ? Math.min(0.012, distortion * 0.06) : Math.min(0.04, distortion * 0.16);
        ctx.globalAlpha = distortionAlpha;
        const jitter = Math.sin(t * 13) * distortion * 18;
        ctx.fillStyle = 'rgba(140,170,255,0.22)';
        ctx.fillRect(jitter, 0, width, height);
        ctx.restore();
      }

      if (overlays) {
        this.drawScanlines(ctx, width, height, normalized);
        this.drawGlow(ctx, width, height, normalized);
      }
    }

    renderToContext(ctx, width, height, t) {
      if (!ctx || !this.timeline) return;
      // retro CRT styling should not destroy landmark readability.
      const runtime = this.timeline.runtime;
      const normalized = clamp(t / Math.max(1, runtime), 0, 1.2);
      const profile = this.timeline.renderProfile || this.resolveRenderProfile({});

      if (profile.pixelMode) {
        const lowRes = this.getLowResTarget(width, height, profile);
        this.drawSceneLayers(lowRes.ctx, lowRes.canvas.width, lowRes.canvas.height, t, normalized, false);
        this.applyPixelPost(lowRes.ctx, lowRes.canvas.width, lowRes.canvas.height, profile);

        ctx.save();
        ctx.imageSmoothingEnabled = false;
        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(lowRes.canvas, 0, 0, width, height);
        ctx.restore();

        if (profile.scanlines) this.drawScanlines(ctx, width, height, normalized);
        if (profile.glow) this.drawGlow(ctx, width, height, normalized);
      } else {
        this.drawSceneLayers(ctx, width, height, t, normalized, true);
      }

      this.drawDebugProvenance(ctx, width, height);

      if (this.timeline.endCard && this.timeline.endCard.use_end_card && t > runtime - 1.6) {
        const p = Math.max(0, Math.min(1, (t - (runtime - 1.6)) / 1.4));
        this.drawEndCard(ctx, this.timeline.endCard, p, width, height);
      }
    }


    getBeatLabelAtTime(t) {
      const runtime = this.timeline ? Number(this.timeline.runtime || 20) : 20;
      const beats = [
        { label: 'Opening', t0: 0, t1: runtime * 0.25 },
        { label: 'Arrival', t0: runtime * 0.25, t1: runtime * 0.5 },
        { label: 'Lift', t0: runtime * 0.5, t1: runtime * 0.75 },
        { label: 'Resolve', t0: runtime * 0.75, t1: runtime + 0.001 }
      ];
      const current = beats.find((beat) => t >= beat.t0 && t < beat.t1);
      return current ? current.label : 'Opening';
    }

    drawDebugProvenance(ctx, w, h) {
      if (!this.debugOptions || !this.debugOptions.enabled || !this.debugOptions.showProvenance || !this.debugFrameState) return;
      const lines = [
        `Beat: ${this.debugFrameState.beatLabel}`,
        `Hero: ${(this.debugFrameState.heroVisualTypes || []).join(', ') || '—'}`,
        `Support: ${(this.debugFrameState.supportVisualTypes || []).join(', ') || '—'}`,
        `Active: ${(this.debugFrameState.activeVisualTypes || []).join(', ') || '—'}`
      ];
      ctx.save();
      ctx.fillStyle = 'rgba(3,10,24,0.78)';
      ctx.fillRect(8, 8, w * 0.5, 16 + lines.length * 13);
      ctx.fillStyle = '#b8dcff';
      ctx.font = '11px monospace';
      lines.forEach((line, idx) => {
        ctx.fillText(line, 14, 22 + idx * 13);
      });
      ctx.restore();
    }

    drawBaseChamber(ctx, w, h, t, normalized) {
      const theme = (this.timeline && this.timeline.renderProfile && this.timeline.renderProfile.theme) || {};
      const debugMinimal = this.timeline && Array.isArray(this.timeline.visualEvents)
        && this.timeline.visualEvents.some((event) => event && event.params && (event.params.debug_preview === true || event.params.minimal_context === true));
      const horizon = h * 0.6;
      const pulse = 0.5 + 0.5 * Math.sin(t * 1.9);

      const bg = ctx.createLinearGradient(0, 0, 0, h);
      bg.addColorStop(0, theme.bgTop || '#02050c');
      bg.addColorStop(0.58, theme.bgMid || '#070d1e');
      bg.addColorStop(1, theme.bgBottom || '#050911');
      ctx.fillStyle = bg;
      ctx.fillRect(0, 0, w, h);

      const radial = ctx.createRadialGradient(w * 0.52, h * 0.52, 20, w * 0.52, h * 0.54, Math.max(w, h) * 0.72);
      const fog = theme.fog || [74, 112, 255];
      radial.addColorStop(0, `rgba(${fog[0]},${fog[1]},${fog[2]},${debugMinimal ? 0.06 : (0.1 + pulse * 0.05)})`);
      radial.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = radial;
      ctx.fillRect(0, 0, w, h);

      ctx.strokeStyle = 'rgba(110,150,255,0.12)';
      for (let i = 0; i < (debugMinimal ? 4 : 14); i += 1) {
        const p = i / 13;
        const y = horizon + Math.pow(p, 1.8) * (h - horizon);
        ctx.beginPath();
        ctx.moveTo(0, y + Math.sin(t * 0.8 + i) * 0.35);
        ctx.lineTo(w, y);
        ctx.stroke();
      }

      const particles = theme.particles || [120, 220, 255];
      ctx.fillStyle = `rgba(${particles[0]},${particles[1]},${particles[2]},${debugMinimal ? 0.03 : (0.06 + normalized * 0.1)})`;
      for (let i = 0; i < (debugMinimal ? 10 : 46); i += 1) {
        const x = (i * 53 + t * (8 + (i % 5))) % w;
        const y = (i * 31 + Math.sin(t + i) * 10) % (h * 0.9);
        ctx.fillRect(x, y, 1.6, 1.6);
      }

      const coreX = w * (0.5 + Math.sin(t * 0.13) * 0.02);
      const coreY = h * (0.5 + Math.cos(t * 0.11) * 0.02);

      const core = ctx.createRadialGradient(coreX, coreY, 2, coreX, coreY, h * 0.28);
      const coreColor = theme.core || [176, 228, 255];
      core.addColorStop(0, `rgba(${coreColor[0]},${coreColor[1]},${coreColor[2]},${debugMinimal ? 0.16 : (0.24 + pulse * 0.18)})`);
      core.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = core;
      ctx.fillRect(0, 0, w, h);
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
        case 'volumetric_fog': {
          ctx.fillStyle = `rgba(120,170,255,${0.04 + intensity * 0.08})`;
          for (let i = 0; i < 22; i += 1) {
            const x = (i * 63 + normalized * 180 + Math.sin(i + normalized * 7) * 40) % w;
            const y = (i * 29 + normalized * 90) % h;
            ctx.fillRect(x, y, 140, 12);
          }
          break;
        }
        case 'glass_refraction': {
          ctx.strokeStyle = `rgba(182,236,255,${0.14 + intensity * 0.22})`;
          ctx.lineWidth = 1.2 + intensity * 1.6;
          for (let i = 0; i < 8; i += 1) {
            const yy = h * (0.15 + i * 0.1) + Math.sin(normalized * 8 + i) * 10;
            ctx.beginPath();
            ctx.moveTo(w * 0.12, yy);
            ctx.bezierCurveTo(w * 0.3, yy - 20, w * 0.7, yy + 20, w * 0.88, yy);
            ctx.stroke();
          }
          break;
        }
        case 'halo_glyphs': {
          ctx.strokeStyle = `rgba(158,255,238,${0.18 + intensity * 0.26})`;
          for (let i = 0; i < 6; i += 1) {
            const r = 38 + i * 22 + Math.sin(normalized * 9 + i) * 6;
            ctx.beginPath();
            ctx.arc(w * 0.5, h * 0.5, r, normalized + i, normalized + i + Math.PI * 0.9);
            ctx.stroke();
          }
          break;
        }
        case 'cathedral_beam': {
          const beamW = 40 + intensity * 110;
          const gx = w * 0.5 + Math.sin(normalized * 2.6) * (w * 0.08);
          const g = ctx.createLinearGradient(gx, h * 0.05, gx, h * 0.92);
          g.addColorStop(0, `rgba(180,230,255,${0.24 + intensity * 0.24})`);
          g.addColorStop(1, 'rgba(0,0,0,0)');
          ctx.fillStyle = g;
          ctx.fillRect(gx - beamW * 0.5, h * 0.05, beamW, h * 0.9);
          break;
        }
        case 'monolith_silhouette': {
          ctx.fillStyle = `rgba(6,10,18,${0.6 + intensity * 0.3})`;
          const mw = w * 0.16;
          const mh = h * 0.52;
          ctx.fillRect(w * 0.5 - mw * 0.5, h * 0.5 - mh * 0.42, mw, mh);
          break;
        }
        case 'starfield_drift': {
          ctx.fillStyle = `rgba(186,232,255,${0.2 + intensity * 0.2})`;
          for (let i = 0; i < 46; i += 1) {
            const x = (i * 47 + normalized * 140 + Math.sin(i * 2.2) * 18) % w;
            const y = (i * 31 + normalized * 40) % h;
            ctx.fillRect(x, y, 1.4, 1.4);
          }
          break;
        }
        case 'orbiting_shards': {
          for (let i = 0; i < 7; i += 1) {
            const a = normalized * 4 + i * (Math.PI * 2 / 7);
            const r = 90 + i * 18 + Math.sin(normalized * 8 + i) * 10;
            const x = w * 0.5 + Math.cos(a) * r;
            const y = h * 0.5 + Math.sin(a) * (r * 0.6);
            ctx.fillStyle = `rgba(146,220,255,${0.14 + intensity * 0.22})`;
            ctx.fillRect(x, y, 8, 2);
          }
          break;
        }
        case 'pulse_orb': {
          const r = 28 + Math.sin(p * Math.PI) * (130 + intensity * 120);
          const g = ctx.createRadialGradient(w * 0.5, h * 0.5, 1, w * 0.5, h * 0.5, r);
          g.addColorStop(0, `rgba(214,248,255,${0.28 + intensity * 0.3})`);
          g.addColorStop(1, 'rgba(0,0,0,0)');
          ctx.fillStyle = g;
          ctx.beginPath();
          ctx.arc(w * 0.5, h * 0.5, r, 0, Math.PI * 2);
          ctx.fill();
          break;
        }
        case 'energy_column': {
          const cx = w * (0.5 + Math.sin(normalized * 1.6) * 0.08);
          ctx.fillStyle = `rgba(128,255,220,${0.07 + intensity * 0.16})`;
          ctx.fillRect(cx - 22, h * 0.14, 44, h * 0.72);
          ctx.strokeStyle = `rgba(178,255,236,${0.18 + intensity * 0.24})`;
          ctx.strokeRect(cx - 14, h * 0.2, 28, h * 0.58);
          break;
        }
        case 'refraction_ripple': {
          ctx.strokeStyle = `rgba(162,224,255,${0.12 + intensity * 0.2})`;
          for (let i = 0; i < 5; i += 1) {
            ctx.beginPath();
            const rr = 26 + i * 36 + p * 120;
            ctx.ellipse(w * 0.5, h * 0.5, rr, rr * 0.6, 0, 0, Math.PI * 2);
            ctx.stroke();
          }
          break;
        }
        case 'chromatic_veil': {
          const veil = ctx.createLinearGradient(0, 0, w, h);
          veil.addColorStop(0, `rgba(120,140,255,${0.08 + intensity * 0.08})`);
          veil.addColorStop(0.5, `rgba(120,255,220,${0.04 + intensity * 0.08})`);
          veil.addColorStop(1, `rgba(255,120,180,${0.04 + intensity * 0.07})`);
          ctx.fillStyle = veil;
          ctx.fillRect(0, 0, w, h);
          break;
        }
        case 'terminal_runes': {
          const rows = 8;
          ctx.fillStyle = `rgba(168,255,220,${0.2 + intensity * 0.3})`;
          ctx.font = '16px monospace';
          for (let i = 0; i < rows; i += 1) {
            const yy = h * 0.2 + i * 26;
            const txt = ['∆','⟟','⌁','⟡','⋄','⟢'][i % 6] + ' SIGNAL-' + (i + 1);
            ctx.fillText(txt, w * 0.12 + Math.sin(normalized * 6 + i) * 12, yy);
          }
          break;
        }
        case 'snow_drift': {
          ctx.fillStyle = `rgba(218,236,255,${0.12 + intensity * 0.2})`;
          for (let i = 0; i < 70; i += 1) {
            const x = (i * 41 + normalized * 50 + Math.sin(i + normalized * 5) * 10) % w;
            const y = (i * 26 + normalized * 120 + i * 0.5) % h;
            ctx.fillRect(x, y, 1.8, 1.8);
          }
          break;
        }
        case 'amber_halo': {
          for (let i = 0; i < 3; i += 1) {
            const lx = w * (0.2 + i * 0.3);
            const ly = h * (0.26 + (i % 2) * 0.08);
            const r = 44 + intensity * 80;
            const halo = ctx.createRadialGradient(lx, ly, 1, lx, ly, r);
            halo.addColorStop(0, `rgba(255,196,118,${0.22 + intensity * 0.22})`);
            halo.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = halo;
            ctx.fillRect(lx - r, ly - r, r * 2, r * 2);
          }
          break;
        }
        case 'wet_reflection_shimmer': {
          ctx.fillStyle = `rgba(120,170,255,${0.06 + intensity * 0.12})`;
          for (let i = 0; i < 18; i += 1) {
            const yy = h * 0.62 + i * 9 + Math.sin(normalized * 10 + i) * 2;
            ctx.fillRect(0, yy, w, 2);
          }
          break;
        }
        case 'brick_shadow_drift': {
          ctx.fillStyle = `rgba(78,52,44,${0.1 + intensity * 0.12})`;
          const brickW = 40;
          const brickH = 16;
          for (let y = 0; y < h * 0.6; y += brickH) {
            for (let x = ((y / brickH) % 2) * (brickW / 2); x < w; x += brickW) {
              ctx.fillRect(x + Math.sin(normalized * 3 + y * 0.01) * 2, y, brickW - 2, brickH - 2);
            }
          }
          break;
        }
        case 'steam_plume_column': {
          const cx = w * 0.5;
          ctx.fillStyle = `rgba(206,224,240,${0.08 + intensity * 0.16})`;
          for (let i = 0; i < 18; i += 1) {
            const ww = 22 + i * 10;
            const yy = h * 0.75 - i * 18 - (normalized * 22 % 18);
            ctx.fillRect(cx - ww * 0.5 + Math.sin(i + normalized * 4) * 8, yy, ww, 12);
          }
          break;
        }
        case 'clock_face_reveal': {
          const r = 70 + intensity * 90;
          ctx.strokeStyle = `rgba(255,236,190,${0.18 + intensity * 0.26})`;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.arc(w * 0.5, h * 0.45, r, 0, Math.PI * 2);
          ctx.stroke();
          for (let i = 0; i < 12; i += 1) {
            const a = (i / 12) * Math.PI * 2 + normalized * 0.2;
            const x1 = w * 0.5 + Math.cos(a) * (r - 10);
            const y1 = h * 0.45 + Math.sin(a) * (r - 10);
            const x2 = w * 0.5 + Math.cos(a) * r;
            const y2 = h * 0.45 + Math.sin(a) * r;
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
          }
          break;
        }
        case 'harbor_mist': {
          ctx.fillStyle = `rgba(142,176,188,${0.06 + intensity * 0.12})`;
          for (let i = 0; i < 16; i += 1) {
            const y = h * 0.52 + i * 12 + Math.sin(normalized * 4 + i) * 4;
            ctx.fillRect(-8, y, w + 16, 10);
          }
          break;
        }
        case 'neon_wet_reflections': {
          const theme = (this.timeline && this.timeline.renderProfile && this.timeline.renderProfile.theme) || {};
          const neonA = theme.neonA || [255, 96, 186];
          const neonB = theme.neonB || [120, 255, 220];
          const colors = [`rgba(90,190,255,`, `rgba(${neonA[0]},${neonA[1]},${neonA[2]},`, `rgba(${neonB[0]},${neonB[1]},${neonB[2]},`];
          for (let i = 0; i < 12; i += 1) {
            const x = (i * 83 + normalized * 60) % w;
            const alpha = 0.08 + intensity * 0.12;
            ctx.fillStyle = `${colors[i % colors.length]}${alpha})`;
            ctx.fillRect(x, h * 0.64 + Math.sin(i + normalized * 8) * 9, 22, h * 0.24);
          }
          break;
        }

        case 'gastown_clock_silhouette': {
          const cx = w * 0.52;
          const baseY = h * 0.73;
          const faceY = h * 0.35;
          const faceR = Math.max(18, Math.min(w, h) * 0.07);
          const columnTop = faceY + faceR * 0.82;
          const columnBottom = baseY - h * 0.01;
          const columnW = Math.max(24, w * 0.05);
          const bodyH = columnBottom - columnTop;
          const baseW = columnW * 2.35;
          const baseH = h * 0.12;
          const shadowAlpha = 0.24 + intensity * 0.4;

          ctx.fillStyle = `rgba(30,22,20,${shadowAlpha})`;
          ctx.fillRect(cx - baseW * 0.56, baseY - baseH * 0.08, baseW * 1.12, baseH * 0.45);
          ctx.fillRect(cx - baseW * 0.45, baseY + baseH * 0.24, baseW * 0.9, baseH * 0.7);

          ctx.fillRect(cx - columnW * 0.54, columnTop, columnW * 1.08, bodyH);
          ctx.fillStyle = `rgba(72,88,96,${0.08 + intensity * 0.14})`;
          ctx.fillRect(cx - columnW * 0.34, columnTop + h * 0.015, columnW * 0.68, bodyH * 0.8);

          ctx.fillStyle = `rgba(34,24,22,${0.26 + intensity * 0.42})`;
          ctx.fillRect(cx - columnW * 0.18, columnTop + bodyH * 0.08, columnW * 0.07, bodyH * 0.68);
          ctx.fillRect(cx + columnW * 0.11, columnTop + bodyH * 0.08, columnW * 0.07, bodyH * 0.68);

          const pipeY = faceY + faceR * 0.95;
          const pipeW = columnW * 0.42;
          const pipeH = h * 0.07;
          ctx.fillRect(cx - columnW * 0.96, pipeY, pipeW, pipeH);
          ctx.fillRect(cx + columnW * 0.54, pipeY, pipeW, pipeH);
          ctx.fillRect(cx - columnW * 1.05, pipeY - pipeH * 0.5, pipeW * 0.2, pipeH * 0.5);
          ctx.fillRect(cx + columnW * 0.85, pipeY - pipeH * 0.5, pipeW * 0.2, pipeH * 0.5);

          const faceGlow = ctx.createRadialGradient(cx, faceY, faceR * 0.3, cx, faceY, faceR * 2.1);
          faceGlow.addColorStop(0, `rgba(255,214,154,${0.2 + intensity * 0.18})`);
          faceGlow.addColorStop(1, 'rgba(0,0,0,0)');

          ctx.fillStyle = faceGlow;
          ctx.fillRect(cx - faceR * 2.2, faceY - faceR * 2.2, faceR * 4.4, faceR * 4.4);

          ctx.fillStyle = `rgba(255,212,152,${0.16 + intensity * 0.2})`;
          ctx.beginPath();
          ctx.arc(cx, faceY, faceR * 0.86, 0, Math.PI * 2);
          ctx.fill();

          ctx.strokeStyle = `rgba(255,232,188,${0.24 + intensity * 0.26})`;
          ctx.lineWidth = Math.max(1.2, faceR * 0.08);
          ctx.beginPath();
          ctx.arc(cx, faceY, faceR, 0, Math.PI * 2);
          ctx.stroke();

          for (let i = 0; i < 12; i += 1) {
            const angle = (i / 12) * Math.PI * 2 - Math.PI * 0.5;
            const major = (i % 3 === 0);
            const innerR = faceR * (major ? 0.62 : 0.74);
            const outerR = faceR * 0.93;
            ctx.lineWidth = major ? Math.max(1.5, faceR * 0.1) : Math.max(1, faceR * 0.05);
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(angle) * innerR, faceY + Math.sin(angle) * innerR);
            ctx.lineTo(cx + Math.cos(angle) * outerR, faceY + Math.sin(angle) * outerR);
            ctx.stroke();
          }

          ctx.strokeStyle = `rgba(255,238,205,${0.36 + intensity * 0.22})`;
          ctx.lineCap = 'round';
          ctx.lineWidth = Math.max(1.2, faceR * 0.09);
          ctx.beginPath();
          ctx.moveTo(cx, faceY);
          ctx.lineTo(cx + faceR * 0.02, faceY - faceR * 0.52);
          ctx.stroke();
          ctx.lineWidth = Math.max(1.6, faceR * 0.11);
          ctx.beginPath();
          ctx.moveTo(cx, faceY);
          ctx.lineTo(cx + faceR * 0.45, faceY + faceR * 0.14);
          ctx.stroke();
          ctx.lineCap = 'butt';

          const capY = faceY - faceR * 1.33;
          ctx.fillStyle = `rgba(34,24,22,${0.3 + intensity * 0.44})`;
          ctx.fillRect(cx - columnW * 0.65, capY + faceR * 0.22, columnW * 1.3, faceR * 0.35);
          ctx.beginPath();
          ctx.moveTo(cx - columnW * 0.86, capY + faceR * 0.24);
          ctx.lineTo(cx, capY - faceR * 0.28);
          ctx.lineTo(cx + columnW * 0.86, capY + faceR * 0.24);
          ctx.closePath();
          ctx.fill();
          ctx.fillRect(cx - columnW * 0.07, capY - faceR * 0.44, columnW * 0.14, faceR * 0.2);

          const steam = ctx.createLinearGradient(cx, capY - faceR * 1.1, cx, capY + faceR * 0.2);
          steam.addColorStop(0, 'rgba(198,220,236,0)');
          steam.addColorStop(1, `rgba(198,220,236,${0.1 + intensity * 0.1})`);
          ctx.fillStyle = steam;
          for (let i = 0; i < 4; i += 1) {
            const drift = Math.sin(normalized * 4 + i * 0.8) * (faceR * 0.2);
            ctx.fillRect(cx - faceR * 0.5 + drift, capY - faceR * 1.1 - i * 8, faceR * 0.36, faceR * 0.58);
          }
          break;
        }
        case 'cobblestone_perspective': {
          ctx.strokeStyle = `rgba(148,170,188,${0.12 + intensity * 0.2})`;
          for (let row = 0; row < 10; row += 1) {
            const y = h * 0.62 + row * (h * 0.038);
            const count = 6 + row;
            for (let i = 0; i < count; i += 1) {
              const ww = w / count;
              const x = i * ww + ((row % 2) * ww * 0.5);
              ctx.strokeRect(x, y, ww - 4, h * 0.03);
            }
          }
          break;
        }
        case 'brick_wall_parallax': {
          ctx.fillStyle = `rgba(88,52,44,${0.14 + intensity * 0.2})`;
          for (let y = 0; y < h * 0.6; y += 18) {
            for (let x = 0; x < w * 0.22; x += 36) ctx.fillRect(x + Math.sin(normalized * 4 + y * 0.02) * 2, y, 34, 16);
            for (let x = w * 0.78; x < w; x += 36) ctx.fillRect(x - Math.sin(normalized * 4 + y * 0.02) * 2, y, 34, 16);
          }
          break;
        }
        case 'streetlamp_halo_row': {
          for (let i = 0; i < 4; i += 1) {
            const lx = w * (0.14 + i * 0.22);
            const ly = h * 0.26;
            const glow = ctx.createRadialGradient(lx, ly, 2, lx, ly, 90);
            glow.addColorStop(0, `rgba(255,196,128,${0.24 + intensity * 0.2})`);
            glow.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = glow;
            ctx.fillRect(lx - 90, ly - 90, 180, 180);
          }
          break;
        }
        case 'granville_neon_marquee': {
          const neon = (this.timeline.renderProfile.theme.neonA || [255, 96, 186]);
          ctx.fillStyle = `rgba(${neon[0]},${neon[1]},${neon[2]},${0.22 + intensity * 0.26})`;
          ctx.fillRect(w * 0.6, h * 0.1, w * 0.08, h * 0.6);
          ctx.fillRect(w * 0.24, h * 0.2, w * 0.05, h * 0.42);
          break;
        }
        case 'neon_sign_flicker': {
          const flick = (Math.sin(normalized * 45) > 0.2) ? 1 : 0.35;
          ctx.fillStyle = `rgba(110,255,230,${(0.16 + intensity * 0.2) * flick})`;
          for (let i = 0; i < 6; i += 1) ctx.fillRect(w * 0.18 + i * 60, h * 0.22 + (i % 2) * 24, 44, 8);
          break;
        }
        case 'traffic_light_glow': {
          const x = w * 0.82;
          const ys = [h * 0.24, h * 0.3, h * 0.36];
          const cols = ['255,60,60', '255,220,90', '90,255,130'];
          ys.forEach((y, i) => { const g = ctx.createRadialGradient(x, y, 2, x, y, 30); g.addColorStop(0, `rgba(${cols[i]},${0.2 + intensity * 0.2})`); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.fillRect(x - 32, y - 32, 64, 64); });
          break;
        }
        case 'skytrain_track': {
          ctx.strokeStyle = `rgba(160,190,220,${0.16 + intensity * 0.2})`;
          ctx.lineWidth = 3;
          ctx.beginPath(); ctx.moveTo(0, h * 0.44); ctx.lineTo(w, h * 0.44); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(0, h * 0.47); ctx.lineTo(w, h * 0.47); ctx.stroke();
          break;
        }
        case 'skytrain_pass_visual': {
          const x = ((normalized * 1.4) % 1.2) * w - w * 0.2;
          ctx.fillStyle = `rgba(190,215,236,${0.2 + intensity * 0.24})`;
          ctx.fillRect(x, h * 0.38, w * 0.22, h * 0.08);
          for (let i = 0; i < 7; i += 1) ctx.clearRect(x + 8 + i * 28, h * 0.4, 18, h * 0.03);
          break;
        }
        case 'bus_pass_visual': {
          const x = ((normalized * 1.1) % 1.25) * w - w * 0.25;
          ctx.fillStyle = `rgba(228,186,110,${0.22 + intensity * 0.25})`;
          ctx.fillRect(x, h * 0.44, w * 0.26, h * 0.09);
          ctx.fillStyle = 'rgba(20,26,36,0.55)';
          for (let i = 0; i < 6; i += 1) ctx.fillRect(x + 10 + i * 30, h * 0.46, 18, h * 0.028);
          break;
        }
        case 'science_world_dome': {
          const cx = w * 0.5;
          const cy = h * 0.74;
          const r = Math.min(w, h) * 0.25;
          ctx.strokeStyle = `rgba(188,220,240,${0.34 + intensity * 0.36})`;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.arc(cx, cy, r, Math.PI, 0);
          ctx.stroke();
          for (let i = 1; i <= 5; i += 1) {
            const ratio = i / 6;
            ctx.beginPath();
            ctx.arc(cx, cy, r * ratio, Math.PI, 0);
            ctx.stroke();
          }
          for (let ring = 1; ring <= 4; ring += 1) {
            const ry = r * (ring / 5);
            const rx = Math.sqrt(Math.max(0.01, (r * r) - (ry * ry)));
            const y = cy - ry;
            ctx.beginPath();
            ctx.moveTo(cx - rx, y);
            ctx.lineTo(cx + rx, y);
            ctx.stroke();
          }
          break;
        }
        case 'chinatown_gate': {
          const baseY = h * 0.68;
          ctx.fillStyle = `rgba(170,82,88,${0.28 + intensity * 0.24})`;
          ctx.fillRect(w * 0.3, baseY - h * 0.16, w * 0.04, h * 0.16);
          ctx.fillRect(w * 0.66, baseY - h * 0.16, w * 0.04, h * 0.16);
          ctx.fillRect(w * 0.41, baseY - h * 0.18, w * 0.18, h * 0.18);
          ctx.fillRect(w * 0.25, baseY - h * 0.22, w * 0.16, h * 0.06);
          ctx.fillRect(w * 0.59, baseY - h * 0.22, w * 0.16, h * 0.06);
          ctx.fillStyle = `rgba(240,182,132,${0.2 + intensity * 0.24})`;
          for (let i = 0; i < 5; i += 1) ctx.beginPath(), ctx.arc(w * (0.34 + i * 0.08), baseY - h * 0.24, 2, 0, Math.PI * 2), ctx.fill();
          break;
        }
        case 'english_bay_inukshuk': {
          const alpha = 0.45 + intensity * 0.42;
          const x = w * 0.5;
          const y = h * 0.68;
          const scale = Math.min(w, h);
          ctx.fillStyle = `rgba(198,210,224,${alpha})`;
          ctx.strokeStyle = `rgba(230,240,250,${Math.min(0.95, alpha + 0.2)})`;
          ctx.lineWidth = Math.max(2, scale * 0.006);
          const stones = [
            [x - scale * 0.09, y, scale * 0.18, scale * 0.05],
            [x - scale * 0.038, y - scale * 0.16, scale * 0.076, scale * 0.16],
            [x - scale * 0.13, y - scale * 0.12, scale * 0.26, scale * 0.045],
            [x - scale * 0.03, y - scale * 0.22, scale * 0.06, scale * 0.045]
          ];
          stones.forEach((stone) => {
            ctx.fillRect(stone[0], stone[1], stone[2], stone[3]);
            ctx.strokeRect(stone[0], stone[1], stone[2], stone[3]);
          });
          break;
        }
        case 'maritime_museum_sailroof': {
          ctx.strokeStyle = `rgba(186,214,226,${0.28 + intensity * 0.3})`;
          ctx.lineWidth = 3;
          ctx.beginPath();
          ctx.moveTo(w * 0.24, h * 0.68);
          ctx.quadraticCurveTo(w * 0.54, h * 0.28, w * 0.8, h * 0.66);
          ctx.stroke();
          ctx.beginPath();
          ctx.moveTo(w * 0.54, h * 0.68);
          ctx.lineTo(w * 0.54, h * 0.36);
          ctx.stroke();
          break;
        }

        case 'clear_cold_shimmer': {
          ctx.fillStyle = `rgba(196,226,255,${0.06 + intensity * 0.12})`;
          for (let i = 0; i < 70; i += 1) {
            const x = (i * 47 + normalized * 90) % w;
            const y = (i * 23 + normalized * 60) % (h * 0.8);
            const s = 1 + ((i % 3) * 0.8);
            ctx.fillRect(x, y, s, s);
          }
          break;
        }
        case 'gastown_scene': {
          this.drawEvent(ctx, { visual_type: 'brick_wall_parallax', params: {}, intensity: intensity * 0.8 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'cobblestone_perspective', params: {}, intensity: intensity * 0.8 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'streetlamp_halo_row', params: {}, intensity: intensity * 0.85 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'gastown_clock_silhouette', params: {}, intensity: intensity * 0.9 }, p, intensity, w, h, normalized);
          break;
        }
        case 'granville_scene': {
          this.drawEvent(ctx, { visual_type: 'granville_neon_marquee', params: {}, intensity: intensity * 0.9 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'neon_sign_flicker', params: {}, intensity: intensity * 0.85 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'traffic_light_glow', params: {}, intensity: intensity * 0.7 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'puddle_reflections', params: {}, intensity: intensity * 0.72 }, p, intensity, w, h, normalized);
          break;
        }
        case 'north_shore_scene': {
          this.drawEvent(ctx, { visual_type: 'northshore_mountain_ridge', params: {}, intensity: intensity * 0.9 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'mountain_mist_layers', params: {}, intensity: intensity * 0.9 }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'harbor_mist', params: {}, intensity: intensity * 0.75 }, p, intensity, w, h, normalized);
          break;
        }



        case 'waterfront_scene': {
          const horizon = h * 0.58;
          ctx.strokeStyle = `rgba(146,190,220,${0.2 + intensity * 0.2})`;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.moveTo(0, horizon);
          ctx.lineTo(w, horizon);
          ctx.stroke();
          // Audit note: removed dominant port crane bars from generic waterfront scene helper to avoid mystery landmark-like columns.
          this.drawEvent(ctx, { visual_type: 'gull_silhouettes', params: {}, intensity: Math.min(0.2, intensity * 0.25) }, p, intensity, w, h, normalized);
          this.drawEvent(ctx, { visual_type: 'ocean_surface_shimmer', params: {}, intensity: intensity * 0.78 }, p, intensity, w, h, normalized);
          const glow = ctx.createLinearGradient(0, horizon - 40, 0, horizon + 80);
          glow.addColorStop(0, `rgba(255,140,210,${0.06 + intensity * 0.08})`);
          glow.addColorStop(1, 'rgba(0,0,0,0)');
          ctx.fillStyle = glow;
          ctx.fillRect(0, horizon - 40, w, 120);
          break;
        }
        case 'ocean_surface_shimmer': {
          ctx.fillStyle = `rgba(106,170,220,${0.05 + intensity * 0.08})`;
          for (let i = 0; i < 20; i += 1) {
            const yy = h * 0.58 + i * 7 + Math.sin(normalized * 9 + i) * 2;
            const ww = w * (0.44 + (i % 6) * 0.08);
            const xx = (Math.sin(normalized * 3 + i * 0.6) * 0.24 + 0.5) * (w - ww);
            ctx.fillRect(xx, yy, ww, 2);
          }
          break;
        }
        case 'seabus_silhouette': {
          const progress = (normalized % 1);
          const x = -w * 0.2 + progress * (w * 1.4);
          const y = h * 0.57;
          ctx.fillStyle = `rgba(26,36,48,${0.32 + intensity * 0.28})`;
          ctx.fillRect(x, y, w * 0.16, h * 0.03);
          ctx.fillRect(x + w * 0.03, y - h * 0.02, w * 0.06, h * 0.02);
          ctx.strokeStyle = `rgba(146,190,220,${0.14 + intensity * 0.16})`;
          ctx.lineWidth = 1;
          ctx.beginPath();
          ctx.moveTo(x - 10, y + h * 0.035);
          ctx.lineTo(x - 34, y + h * 0.045);
          ctx.moveTo(x - 2, y + h * 0.035);
          ctx.lineTo(x - 24, y + h * 0.052);
          ctx.stroke();
          break;
        }


        case 'planetarium_dome': {
          const cx = w * 0.5;
          const cy = h * 0.75;
          const r = Math.min(w, h) * 0.24;
          ctx.fillStyle = `rgba(18,28,48,${0.34 + intensity * 0.24})`;
          ctx.beginPath();
          ctx.arc(cx, cy, r, Math.PI, 0);
          ctx.lineTo(cx + r, cy + h * 0.03);
          ctx.lineTo(cx - r, cy + h * 0.03);
          ctx.closePath();
          ctx.fill();
          ctx.strokeStyle = `rgba(164,202,238,${0.28 + intensity * 0.25})`;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.arc(cx, cy, r, Math.PI, 0);
          ctx.stroke();
          ctx.beginPath();
          ctx.moveTo(cx - r * 0.95, cy + h * 0.03);
          ctx.lineTo(cx + r * 0.95, cy + h * 0.03);
          ctx.stroke();
          break;
        }
        case 'starfield_projection': {
          const cx = w * 0.5;
          const cy = h * 0.72;
          const r = Math.min(w, h) * 0.27;
          ctx.strokeStyle = `rgba(110,180,255,${0.08 + intensity * 0.16})`;
          for (let i = 0; i < 6; i += 1) {
            ctx.beginPath();
            ctx.arc(cx, cy, r * (0.35 + i * 0.1), Math.PI + 0.14, -0.14);
            ctx.stroke();
          }
          ctx.fillStyle = `rgba(188,226,255,${0.22 + intensity * 0.24})`;
          for (let i = 0; i < 42; i += 1) {
            const px = cx + Math.sin(i * 2.1 + normalized * 2.8) * (r * (0.18 + (i % 6) * 0.12));
            const py = cy - Math.abs(Math.cos(i * 1.7 + normalized * 2.1)) * (r * (0.2 + (i % 5) * 0.13));
            ctx.fillRect(px, py, 1.5, 1.5);
          }
          break;
        }
        case 'constellation_lines': {
          ctx.strokeStyle = `rgba(170,214,255,${0.08 + intensity * 0.13})`;
          ctx.lineWidth = 1;
          const pts = [[0.32,0.24],[0.38,0.2],[0.45,0.25],[0.52,0.19],[0.58,0.24],[0.64,0.2]];
          ctx.beginPath();
          pts.forEach((pt, idx) => {
            const x = w * pt[0]; const y = h * pt[1];
            if (idx === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            ctx.moveTo(x, y); ctx.arc(x, y, 1.3, 0, Math.PI * 2);
          });
          ctx.stroke();
          break;
        }
        case 'canada_place_sails': {
          const y = h * 0.58;
          ctx.strokeStyle = `rgba(196,220,238,${0.2 + intensity * 0.2})`;
          ctx.lineWidth = 2;
          for (let i = 0; i < 5; i += 1) {
            const x = w * (0.34 + i * 0.07);
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x + w * 0.04, y - h * 0.09);
            ctx.lineTo(x + w * 0.08, y);
            ctx.closePath();
            ctx.stroke();
          }
          break;
        }

        case 'lions_gate_bridge': {
          const deckY = h * 0.6;
          const leftTowerX = w * 0.34;
          const rightTowerX = w * 0.66;
          const towerTop = h * 0.28;
          const towerBottom = deckY;
          ctx.strokeStyle = `rgba(198,224,240,${0.36 + intensity * 0.34})`;
          ctx.lineWidth = 2.2;

          ctx.beginPath();
          ctx.moveTo(w * 0.14, deckY);
          ctx.lineTo(w * 0.86, deckY);
          ctx.stroke();

          ctx.beginPath();
          ctx.moveTo(leftTowerX, towerBottom);
          ctx.lineTo(leftTowerX, towerTop);
          ctx.moveTo(rightTowerX, towerBottom);
          ctx.lineTo(rightTowerX, towerTop);
          ctx.stroke();

          ctx.beginPath();
          ctx.moveTo(leftTowerX - w * 0.02, towerTop + h * 0.01);
          ctx.lineTo(leftTowerX + w * 0.02, towerTop + h * 0.01);
          ctx.moveTo(rightTowerX - w * 0.02, towerTop + h * 0.01);
          ctx.lineTo(rightTowerX + w * 0.02, towerTop + h * 0.01);
          ctx.stroke();

          const cableLift = 0.08 + intensity * 0.035;
          ctx.beginPath();
          ctx.moveTo(w * 0.16, deckY);
          ctx.bezierCurveTo(w * 0.3, deckY - h * cableLift, w * 0.7, deckY - h * cableLift, w * 0.84, deckY);
          ctx.stroke();

          const suspenders = 14;
          for (let i = 0; i <= suspenders; i += 1) {
            const x = w * (0.18 + (i / suspenders) * 0.64);
            const curveP = (x - w * 0.16) / (w * 0.68);
            const topY = deckY - h * (Math.sin(Math.PI * curveP) * cableLift);
            ctx.beginPath();
            ctx.moveTo(x, topY);
            ctx.lineTo(x, deckY);
            ctx.stroke();
          }
          break;
        }
        case 'bc_place_dome': {
          const cx = w * 0.52;
          const cy = h * 0.72;
          const rx = w * 0.24;
          const ry = h * 0.14;
          ctx.strokeStyle = `rgba(182,216,234,${0.28 + intensity * 0.3})`;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.ellipse(cx, cy, rx, ry, 0, Math.PI, 0, true);
          ctx.stroke();
          for (let i = -4; i <= 4; i += 1) {
            const x = cx + i * (rx / 4.5);
            ctx.beginPath(); ctx.moveTo(x, cy); ctx.lineTo(cx, cy - ry * 1.3); ctx.stroke();
          }
          break;
        }
        case 'port_cranes': {
          ctx.strokeStyle = `rgba(164,188,208,${0.22 + intensity * 0.26})`;
          ctx.lineWidth = 3;
          for (let i = 0; i < 4; i += 1) {
            const x = w * (0.16 + i * 0.16);
            const base = h * 0.68;
            ctx.beginPath(); ctx.moveTo(x, base); ctx.lineTo(x, h * 0.5); ctx.lineTo(x + w * 0.1, h * 0.46); ctx.stroke();
          }
          break;
        }

        case 'northshore_mountain_ridge': {
          ctx.fillStyle = `rgba(40,62,86,${0.24 + intensity * 0.28})`;
          ctx.beginPath();
          ctx.moveTo(0, h * 0.62);
          for (let i = 0; i <= 8; i += 1) ctx.lineTo((i / 8) * w, h * (0.44 + (Math.sin(i * 0.9) * 0.08)));
          ctx.lineTo(w, h); ctx.lineTo(0, h); ctx.closePath(); ctx.fill();
          break;
        }
        case 'mountain_mist_layers': {
          ctx.fillStyle = `rgba(156,186,206,${0.08 + intensity * 0.14})`;
          for (let i = 0; i < 12; i += 1) {
            const y = h * 0.45 + i * 14 + Math.sin(normalized * 3 + i) * 6;
            ctx.fillRect(-6, y, w + 12, 10);
          }
          break;
        }
        case 'rain_streaks': {
          ctx.strokeStyle = `rgba(170,210,255,${0.14 + intensity * 0.2})`;
          for (let i = 0; i < 120; i += 1) {
            const x = (i * 23 + normalized * 220) % w;
            const y = (i * 17 + normalized * 320) % h;
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - 4, y + 14); ctx.stroke();
          }
          break;
        }
        case 'puddle_reflections': {
          const neon = this.timeline.renderProfile.theme.neonB || [120, 255, 220];
          ctx.fillStyle = `rgba(${neon[0]},${neon[1]},${neon[2]},${0.08 + intensity * 0.14})`;
          for (let i = 0; i < 14; i += 1) ctx.fillRect((i * 74 + normalized * 30) % w, h * 0.67 + (i % 4) * 16, 28, h * 0.2);
          break;
        }

        case 'winter_particulate_depth': {
          ctx.fillStyle = `rgba(200,222,255,${0.08 + intensity * 0.16})`;
          for (let i = 0; i < 90; i += 1) {
            const depth = 0.4 + (i % 5) * 0.15;
            const x = (i * 29 + normalized * 80 * depth) % w;
            const y = (i * 17 + normalized * 110 * (1.2 - depth)) % h;
            const size = 1 + depth;
            ctx.fillRect(x, y, size, size);
          }
          break;
        }

        case 'gull_silhouettes': {
          ctx.strokeStyle = `rgba(214,232,244,${0.1 + intensity * 0.12})`;
          ctx.lineWidth = 1.6;
          const gulls = 2 + Math.round(Math.min(0.2, intensity) * 10);
          for (let i = 0; i < gulls; i += 1) {
            const gx = w * (0.2 + i * 0.2) + Math.sin(normalized * 2 + i) * 18;
            const gy = h * (0.18 + (i % 2) * 0.06) + Math.cos(normalized * 1.5 + i) * 6;
            ctx.beginPath();
            ctx.moveTo(gx - 8, gy);
            ctx.quadraticCurveTo(gx - 2, gy - 6, gx + 4, gy);
            ctx.quadraticCurveTo(gx + 10, gy - 6, gx + 16, gy);
            ctx.stroke();
          }
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
      const style = String(endCard.reveal_style || '').toLowerCase();
      const lines = String(endCard.text || 'END SIGNAL').split(/\n/);
      const lineHeight = Math.max(20, h * 0.045);
      const originX = w * 0.08;
      const originY = h * 0.36;
      const charsToShow = Math.floor(lines.join('\n').length * progress);
      let shown = 0;

      ctx.globalAlpha = progress;
      ctx.fillStyle = '#dcf7ff';
      ctx.shadowColor = 'rgba(120,236,255,0.85)';
      ctx.shadowBlur = 10;
      ctx.textBaseline = 'top';

      if (style === 'event_card') {
        const cardW = w * 0.76;
        const cardH = h * 0.56;
        const cardX = w * 0.12;
        const cardY = h * 0.2;
        ctx.fillStyle = 'rgba(6,15,28,0.86)';
        ctx.fillRect(cardX, cardY, cardW, cardH);
        ctx.strokeStyle = 'rgba(125,220,255,0.75)';
        ctx.lineWidth = 2;
        ctx.strokeRect(cardX, cardY, cardW, cardH);

        const filtered = lines.filter((l) => l.trim().length || l === '');
        const header = filtered[0] || 'AI FILM CLUB';
        const meta = filtered[1] || '';
        const title = filtered[3] || filtered[2] || 'END SIGNAL';
        const body = filtered.slice(4);
        let y = cardY + 24;
        ctx.fillStyle = '#89e6ff';
        ctx.font = 'bold 26px monospace';
        ctx.fillText(header, cardX + 24, y);
        y += lineHeight * 1.1;
        ctx.fillStyle = '#b8d7ef';
        ctx.font = '16px monospace';
        if (meta) ctx.fillText(meta, cardX + 24, y);
        y += lineHeight * 1.05;
        ctx.fillStyle = '#f7fcff';
        ctx.font = 'bold 30px monospace';
        ctx.fillText(title, cardX + 24, y);
        y += lineHeight * 1.3;
        ctx.fillStyle = '#d4ebff';
        ctx.font = '18px monospace';
        body.forEach((line) => {
          if (!line.trim()) {
            y += lineHeight * 0.45;
            return;
          }
          ctx.fillText(line, cardX + 24, y);
          y += lineHeight * 0.95;
        });
      } else {
        ctx.font = 'bold 32px monospace';
        lines.forEach((line, i) => {
          let out = line;
          if (style === 'typewriter') {
            const remain = Math.max(0, charsToShow - shown);
            out = line.slice(0, remain);
            shown += line.length + 1;
          }
          if (style === 'glitch_wipe') {
            const wipe = clamp((progress - (i * 0.08)) * 1.8, 0, 1);
            ctx.save();
            ctx.beginPath();
            ctx.rect(originX, originY + i * lineHeight, w * 0.86 * wipe, lineHeight + 4);
            ctx.clip();
            ctx.fillText(out, originX, originY + i * lineHeight);
            ctx.restore();
          } else {
            ctx.fillText(out, originX, originY + i * lineHeight);
          }
        });
      }
      ctx.globalAlpha = 1;
      ctx.shadowBlur = 0;
    }
  }

  window.AsmrVisualEngine = AsmrVisualEngine;
  window.ASMR_VISUAL_REGISTRY = VISUAL_REGISTRY;
})(window);
