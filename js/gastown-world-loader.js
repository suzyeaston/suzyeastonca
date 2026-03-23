(function (window) {
  'use strict';

  async function fetchWorld(url) {
    let response;
    try {
      response = await fetch(url, { credentials: 'same-origin' });
    } catch (error) {
      throw new Error('Could not load Gastown world data from ' + url + '.');
    }
    if (!response.ok) {
      throw new Error('Could not load Gastown world data from ' + url + ' (HTTP ' + response.status + ').');
    }
    const data = await response.json();
    validateWorldData(data);
    return normalizeWorldData(data);
  }

  async function loadGastownWorldData(url) {
    if (!url) {
      throw new Error('Missing Gastown world data URL.');
    }

    return fetchWorld(url);
  }


  function isFiniteNumber(value) {
    return typeof value === 'number' && Number.isFinite(value);
  }

  function assertFiniteNumberIfPresent(value, label) {
    if (value === undefined || value === null) return;
    if (!isFiniteNumber(value)) {
      throw new Error('Gastown world data is malformed: ' + label + ' must be a finite number when present.');
    }
  }

  function assertPolygon(points, label) {
    if (!Array.isArray(points) || points.length < 3) {
      throw new Error('Gastown world data is malformed: ' + label + ' requires at least 3 points.');
    }
  }

  function validatePointLike(point, label) {
    if (!point || !isFiniteNumber(point.x) || !isFiniteNumber(point.z)) {
      throw new Error('Gastown world data is malformed: ' + label + ' must include finite x/z values.');
    }
  }

  function validateProp(prop, index) {
    if (!prop || typeof prop !== 'object') {
      throw new Error('Gastown world data is malformed: props[' + index + '] must be an object.');
    }
    if (typeof prop.id !== 'string' || !prop.id) {
      throw new Error('Gastown world data is malformed: props[' + index + '].id is required.');
    }
    if (typeof prop.kind !== 'string' || !prop.kind) {
      throw new Error('Gastown world data is malformed: props[' + index + '].kind is required.');
    }
    ['x', 'z', 'yaw', 'scale'].forEach((field) => {
      assertFiniteNumberIfPresent(prop[field], 'props[' + index + '].' + field);
    });
    assertFiniteNumberIfPresent(prop.y, 'props[' + index + '].y');
  }

  function validateNpc(npc, index) {
    if (!npc || typeof npc !== 'object') {
      throw new Error('Gastown world data is malformed: npcs[' + index + '] must be an object.');
    }
    if (typeof npc.id !== 'string' || !npc.id) {
      throw new Error('Gastown world data is malformed: npcs[' + index + '].id is required.');
    }
    if (typeof npc.role !== 'string' || !npc.role) {
      throw new Error('Gastown world data is malformed: npcs[' + index + '].role is required.');
    }
    assertFiniteNumberIfPresent(npc.interactRadius, 'npcs[' + index + '].interactRadius');
    assertFiniteNumberIfPresent(npc.silhouetteScale, 'npcs[' + index + '].silhouetteScale');
    if (npc.idleSpot) {
      validatePointLike(npc.idleSpot, 'npcs[' + index + '].idleSpot');
    }
    if (npc.patrol && !Array.isArray(npc.patrol)) {
      throw new Error('Gastown world data is malformed: npcs[' + index + '].patrol must be an array when present.');
    }
    (npc.patrol || []).forEach((point, patrolIndex) => validatePointLike(point, 'npcs[' + index + '].patrol[' + patrolIndex + ']'));
  }

  function validateWorldData(data) {
    const hasRoute = data && data.route && Array.isArray(data.route.centerline);
    const hasNodes = data && Array.isArray(data.nodes);
    const hasBuildings = data && Array.isArray(data.buildings);
    const hasStreetZones = data && data.zones && Array.isArray(data.zones.street);
    const hasSidewalkZones = data && data.zones && Array.isArray(data.zones.sidewalk);

    if (!hasRoute || !hasNodes || !hasBuildings || !hasStreetZones || !hasSidewalkZones) {
      throw new Error('Gastown world data is malformed.');
    }

    assertPolygon(data.route.walkBounds, 'route.walkBounds');
    data.zones.street.forEach((zone, index) => assertPolygon(zone.polygon, 'zones.street[' + index + ']'));
    data.zones.sidewalk.forEach((zone, index) => assertPolygon(zone.polygon, 'zones.sidewalk[' + index + ']'));

    if (data.hero_landmarks && !Array.isArray(data.hero_landmarks)) {
      throw new Error('Gastown world data is malformed: hero_landmarks must be an array.');
    }

    if (data.facade_profiles && typeof data.facade_profiles !== 'object') {
      throw new Error('Gastown world data is malformed: facade_profiles must be an object.');
    }

    if (data.props && !Array.isArray(data.props)) {
      throw new Error('Gastown world data is malformed: props must be an array.');
    }
    if (data.npcs && !Array.isArray(data.npcs)) {
      throw new Error('Gastown world data is malformed: npcs must be an array.');
    }

    if (data.navigator) {
      if (data.navigator.focusCorridor && !Array.isArray(data.navigator.focusCorridor)) {
        throw new Error('Gastown world data is malformed: navigator.focusCorridor must be an array.');
      }
    }

    if (data.streetscape) {
      if (data.streetscape.surfaceBands && !Array.isArray(data.streetscape.surfaceBands)) {
        throw new Error('Gastown world data is malformed: streetscape.surfaceBands must be an array.');
      }
      if (data.streetscape.lamps && !Array.isArray(data.streetscape.lamps)) {
        throw new Error('Gastown world data is malformed: streetscape.lamps must be an array.');
      }
      if (data.streetscape.trees && !Array.isArray(data.streetscape.trees)) {
        throw new Error('Gastown world data is malformed: streetscape.trees must be an array.');
      }
      if (data.streetscape.bollards && !Array.isArray(data.streetscape.bollards)) {
        throw new Error('Gastown world data is malformed: streetscape.bollards must be an array.');
      }
    }

    (data.props || []).forEach(validateProp);
    (data.npcs || []).forEach(validateNpc);

    data.buildings.forEach((building, index) => {
      const hasAbsoluteFootprint = Array.isArray(building.footprint);
      const hasLocalFootprint = Array.isArray(building.footprint_local);
      if (hasAbsoluteFootprint) {
        assertPolygon(building.footprint, 'buildings[' + index + '].footprint');
      }
      if (hasLocalFootprint) {
        assertPolygon(building.footprint_local, 'buildings[' + index + '].footprint_local');
      }
      if (!hasAbsoluteFootprint && !hasLocalFootprint) {
        throw new Error('Gastown world data is malformed: buildings[' + index + '] requires footprint or footprint_local.');
      }

      if (building.window_bay_count !== undefined && typeof building.window_bay_count !== 'number') {
        throw new Error('Gastown world data is malformed: buildings[' + index + '].window_bay_count must be numeric.');
      }

      ['x', 'z', 'width', 'depth', 'yaw', 'height', 'cornice_emphasis', 'mass_inset', 'recessed_entry_count'].forEach((field) => {
        assertFiniteNumberIfPresent(building[field], 'buildings[' + index + '].' + field);
      });

      if (building.storefront_rhythm && typeof building.storefront_rhythm !== 'object') {
        throw new Error('Gastown world data is malformed: buildings[' + index + '].storefront_rhythm must be an object when present.');
      }
      if (building.storefront_rhythm) {
        assertFiniteNumberIfPresent(building.storefront_rhythm.base_band, 'buildings[' + index + '].storefront_rhythm.base_band');
        assertFiniteNumberIfPresent(building.storefront_rhythm.upper_rows, 'buildings[' + index + '].storefront_rhythm.upper_rows');
      }
    });
  }

  function mergePreset(base, override) {
    return Object.assign({}, base, override || {});
  }

  function normalizePoint(point, fallbackX, fallbackZ) {
    return {
      x: isFiniteNumber(point && point.x) ? point.x : fallbackX,
      z: isFiniteNumber(point && point.z) ? point.z : fallbackZ,
    };
  }

  function normalizeWorldData(data) {
    const normalized = data || {};
    normalized.meta = normalized.meta && typeof normalized.meta === 'object' ? normalized.meta : {};
    normalized.meta.buildClassification = typeof normalized.meta.buildClassification === 'string'
      ? normalized.meta.buildClassification
      : (normalized.meta.isRealCivicBuild === false ? 'approximate-fallback' : 'offline-civic-build');
    normalized.meta.provenanceSummary = typeof normalized.meta.provenanceSummary === 'string'
      ? normalized.meta.provenanceSummary
      : (normalized.meta.isRealCivicBuild === false
        ? 'Approximate fallback corridor retained because civic/open-data inputs were unavailable.'
        : 'Offline civic-data build normalized for runtime use.');
    normalized.meta.openDataInputs = normalized.meta.openDataInputs && typeof normalized.meta.openDataInputs === 'object'
      ? normalized.meta.openDataInputs
      : {};

    normalized.landmarks = Array.isArray(normalized.landmarks) ? normalized.landmarks : [];
    normalized.audioZones = Array.isArray(normalized.audioZones) ? normalized.audioZones : [];
    normalized.hero_landmarks = Array.isArray(normalized.hero_landmarks) ? normalized.hero_landmarks : [];
    normalized.facade_profiles = normalized.facade_profiles && typeof normalized.facade_profiles === 'object' ? normalized.facade_profiles : {};

    normalized.navigator = normalized.navigator && typeof normalized.navigator === 'object' ? normalized.navigator : {};
    normalized.navigator.focusCorridor = Array.isArray(normalized.navigator.focusCorridor) ? normalized.navigator.focusCorridor : [];

    normalized.streetscape = normalized.streetscape && typeof normalized.streetscape === 'object' ? normalized.streetscape : {};
    normalized.streetscape.lamps = Array.isArray(normalized.streetscape.lamps) ? normalized.streetscape.lamps : [];
    normalized.streetscape.trees = Array.isArray(normalized.streetscape.trees) ? normalized.streetscape.trees : [];
    normalized.streetscape.bollards = Array.isArray(normalized.streetscape.bollards) ? normalized.streetscape.bollards : [];
    normalized.streetscape.surfaceBands = Array.isArray(normalized.streetscape.surfaceBands) ? normalized.streetscape.surfaceBands : [];

    normalized.props = Array.isArray(normalized.props) ? normalized.props : [];
    normalized.props = normalized.props.map((prop, index) => ({
      id: typeof prop.id === 'string' && prop.id ? prop.id : 'prop-' + index,
      kind: typeof prop.kind === 'string' && prop.kind ? prop.kind : 'cardboard_box',
      x: isFiniteNumber(prop.x) ? prop.x : 0,
      y: isFiniteNumber(prop.y) ? prop.y : 0,
      z: isFiniteNumber(prop.z) ? prop.z : 0,
      yaw: isFiniteNumber(prop.yaw) ? prop.yaw : 0,
      scale: isFiniteNumber(prop.scale) ? prop.scale : 1,
    }));

    normalized.npcs = Array.isArray(normalized.npcs) ? normalized.npcs : [];
    normalized.npcs = normalized.npcs.map((npc, index) => {
      const fallbackPoint = normalizePoint((npc && npc.idleSpot) || (npc && npc.patrol && npc.patrol[0]) || normalized.route.centerline[0] || { x: 0, z: 0 }, 0, 0);
      const patrol = Array.isArray(npc && npc.patrol) ? npc.patrol.map((point) => normalizePoint(point, fallbackPoint.x, fallbackPoint.z)) : [];
      return {
        id: typeof npc.id === 'string' && npc.id ? npc.id : 'npc-' + index,
        role: typeof npc.role === 'string' && npc.role ? npc.role : 'pedestrian',
        behavior: typeof npc.behavior === 'string' ? npc.behavior : '',
        pose: typeof npc.pose === 'string' ? npc.pose : '',
        heldProp: typeof npc.heldProp === 'string' ? npc.heldProp : '',
        companionGroup: typeof npc.companionGroup === 'string' ? npc.companionGroup : '',
        voiceCue: typeof npc.voiceCue === 'string' ? npc.voiceCue : '',
        silhouetteScale: isFiniteNumber(npc.silhouetteScale) ? npc.silhouetteScale : 1,
        interactRadius: isFiniteNumber(npc.interactRadius) ? npc.interactRadius : 2.4,
        dialogId: typeof npc.dialogId === 'string' ? npc.dialogId : '',
        idleSpot: normalizePoint(npc.idleSpot || fallbackPoint, fallbackPoint.x, fallbackPoint.z),
        patrol: patrol,
      };
    });

    const defaultMoodPreset = {
      ambientBed: 'quiet_night',
      audioDensity: 0.5,
      voiceFreq: 0.12,
      lightIntensity: 0.86,
    };

    const defaultWeatherPreset = {
      rainIntensity: 0,
      fogDensity: 0.01,
      cloudCoverage: 0.32,
      cloudDarkness: 0.28,
      cloudAltitude: 62,
      rainSpeed: 1,
      rainOpacity: 0.7,
      lightningFrequency: 0,
      lightningIntensity: 0,
      thunderEnabled: false,
    };

    const defaultTimeOfDayPreset = {
      sky: '#101822',
      ambientColor: '#8aa0b8',
      ambientIntensity: 0.8,
      keyColor: '#9eb9d4',
      keyIntensity: 0.68,
      fillColor: '#5b6f87',
      fillIntensity: 0.25,
      buildingContrast: 1,
      buildingEdgeColor: '#223142',
      roadColor: '#2b3138',
      sidewalkColor: '#8f8780',
      laneColor: '#aab1b8',
      landmarkGlow: 0.3,
      rainVisibility: 1,
      pathBrightness: 0.22,
      fogBoost: 0,
    };

    normalized.moodPresets = normalized.moodPresets && typeof normalized.moodPresets === 'object' ? normalized.moodPresets : {};
    normalized.moodPresets.calm = mergePreset(defaultMoodPreset, Object.assign({ ambientBed: 'quiet_night', audioDensity: 0.42, voiceFreq: 0.08, lightIntensity: 0.9 }, normalized.moodPresets.calm));
    normalized.moodPresets.commuter = mergePreset(defaultMoodPreset, Object.assign({ ambientBed: 'commuter_mix', audioDensity: 0.62, voiceFreq: 0.22, lightIntensity: 0.88 }, normalized.moodPresets.commuter));
    normalized.moodPresets.lively = mergePreset(defaultMoodPreset, Object.assign({ ambientBed: 'nightlife_hum', audioDensity: 0.8, voiceFreq: 0.34, lightIntensity: 0.96 }, normalized.moodPresets.lively));
    normalized.moodPresets.eerie = mergePreset(defaultMoodPreset, Object.assign({ ambientBed: 'eerie_drones', audioDensity: 0.6, voiceFreq: 0.16, lightIntensity: 0.74 }, normalized.moodPresets.eerie));

    normalized.weatherPresets = normalized.weatherPresets && typeof normalized.weatherPresets === 'object' ? normalized.weatherPresets : {};
    normalized.weatherPresets.clear = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0, fogDensity: 0.0065, cloudCoverage: 0.38, cloudDarkness: 0.22, cloudAltitude: 68, rainOpacity: 0.16, rainSpeed: 0.65 }, normalized.weatherPresets.clear));
    normalized.weatherPresets.drizzle = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0.34, fogDensity: 0.0085, cloudCoverage: 0.58, cloudDarkness: 0.34, cloudAltitude: 64, rainOpacity: 0.4, rainSpeed: 0.9 }, normalized.weatherPresets.drizzle));
    normalized.weatherPresets.rain = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0.78, fogDensity: 0.013, cloudCoverage: 0.78, cloudDarkness: 0.48, cloudAltitude: 60, rainOpacity: 0.66, rainSpeed: 1.18 }, normalized.weatherPresets.rain));
    normalized.weatherPresets.fog = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0.08, fogDensity: 0.019, cloudCoverage: 0.7, cloudDarkness: 0.4, cloudAltitude: 58, rainOpacity: 0.28, rainSpeed: 0.72 }, normalized.weatherPresets.fog));
    normalized.weatherPresets.thunderstorm = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 1, fogDensity: 0.018, cloudCoverage: 0.94, cloudDarkness: 0.72, cloudAltitude: 54, rainOpacity: 0.86, rainSpeed: 1.5, lightningFrequency: 0.18, lightningIntensity: 1.3, thunderEnabled: true }, normalized.weatherPresets.thunderstorm));

    normalized.timeOfDayPresets = normalized.timeOfDayPresets && typeof normalized.timeOfDayPresets === 'object' ? normalized.timeOfDayPresets : {};
    normalized.timeOfDayPresets.morning = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#8ea4b5', ambientColor: '#c2ced8', ambientIntensity: 0.94, keyColor: '#d8d8cc', keyIntensity: 0.7, fillColor: '#708398', fillIntensity: 0.3, buildingContrast: 1.02, buildingEdgeColor: '#314150', roadColor: '#3c464f', sidewalkColor: '#9b9185', laneColor: '#b6bfc7', landmarkGlow: 0.18, rainVisibility: 0.88, pathBrightness: 0.16, fogBoost: -0.0022 }, normalized.timeOfDayPresets.morning));
    normalized.timeOfDayPresets.afternoon = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#9fb3c1', ambientColor: '#d5d7cf', ambientIntensity: 0.91, keyColor: '#ead0ab', keyIntensity: 0.76, fillColor: '#7f8d98', fillIntensity: 0.26, buildingContrast: 1.08, buildingEdgeColor: '#3d4b59', roadColor: '#353b42', sidewalkColor: '#978b7c', laneColor: '#c5cbc8', landmarkGlow: 0.2, rainVisibility: 0.9, pathBrightness: 0.18, fogBoost: -0.0014 }, normalized.timeOfDayPresets.afternoon));
    normalized.timeOfDayPresets.dusk = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#2d3447', ambientColor: '#76879c', ambientIntensity: 0.56, keyColor: '#d5a06e', keyIntensity: 0.56, fillColor: '#40546b', fillIntensity: 0.18, buildingContrast: 1.14, buildingEdgeColor: '#162230', roadColor: '#242a31', sidewalkColor: '#7f756b', laneColor: '#9da6ae', landmarkGlow: 0.4, rainVisibility: 0.88, pathBrightness: 0.12, fogBoost: 0.001 }, normalized.timeOfDayPresets.dusk));
    normalized.timeOfDayPresets.night = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#0d131d', ambientColor: '#5e7389', ambientIntensity: 0.46, keyColor: '#8aa8c5', keyIntensity: 0.48, fillColor: '#31475c', fillIntensity: 0.16, buildingContrast: 1.22, buildingEdgeColor: '#131b27', roadColor: '#1d232a', sidewalkColor: '#746b63', laneColor: '#8d99a3', landmarkGlow: 0.46, rainVisibility: 1, pathBrightness: 0.1, fogBoost: 0.004 }, normalized.timeOfDayPresets.night));

    return normalized;
  }

  window.GastownWorldLoader = {
    load: loadGastownWorldData,
    normalizeWorldData: normalizeWorldData,
  };
})(window);
