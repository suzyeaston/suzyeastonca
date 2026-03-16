(function (window) {
  'use strict';

  async function fetchWorld(url) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
      throw new Error('Could not load Gastown world data.');
    }
    const data = await response.json();
    validateWorldData(data);
    return normalizeWorldData(data);
  }

  async function loadGastownWorldData(url, fallbackUrl) {
    if (!url) {
      throw new Error('Missing world data URL.');
    }

    try {
      return await fetchWorld(url);
    } catch (primaryError) {
      if (!fallbackUrl || fallbackUrl === url) {
        throw primaryError;
      }
      const fallback = await fetchWorld(fallbackUrl);
      fallback.meta = fallback.meta || {};
      fallback.meta.runtimeFallbackActive = true;
      return fallback;
    }
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

  function normalizeWorldData(data) {
    const normalized = data || {};

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

    const defaultMoodPreset = {
      ambientBed: 'eerie_drones',
      audioDensity: 0.65,
      voiceFreq: 0.2,
      lightIntensity: 0.78,
    };

    const defaultWeatherPreset = {
      rainIntensity: 0,
      fogDensity: 0.012,
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
    normalized.moodPresets.eerie = mergePreset(defaultMoodPreset, normalized.moodPresets.eerie);
    normalized.moodPresets.calm = mergePreset(defaultMoodPreset, Object.assign({ ambientBed: 'quiet_night', audioDensity: 0.45, voiceFreq: 0.1, lightIntensity: 0.86 }, normalized.moodPresets.calm));
    normalized.moodPresets.lively = mergePreset(defaultMoodPreset, Object.assign({ ambientBed: 'nightlife_hum', audioDensity: 0.84, voiceFreq: 0.35, lightIntensity: 0.96 }, normalized.moodPresets.lively));

    normalized.weatherPresets = normalized.weatherPresets && typeof normalized.weatherPresets === 'object' ? normalized.weatherPresets : {};
    normalized.weatherPresets.clear = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0, fogDensity: 0.008 }, normalized.weatherPresets.clear));
    normalized.weatherPresets.rain = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0.85, fogDensity: 0.016 }, normalized.weatherPresets.rain));
    normalized.weatherPresets.fog = mergePreset(defaultWeatherPreset, Object.assign({ rainIntensity: 0.08, fogDensity: 0.024 }, normalized.weatherPresets.fog));

    normalized.timeOfDayPresets = normalized.timeOfDayPresets && typeof normalized.timeOfDayPresets === 'object' ? normalized.timeOfDayPresets : {};
    normalized.timeOfDayPresets.morning = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#8aa8bf', ambientColor: '#cfdeee', ambientIntensity: 1.02, keyColor: '#fff2d1', keyIntensity: 0.84, fillColor: '#8ba2ba', fillIntensity: 0.32, buildingContrast: 0.95, buildingEdgeColor: '#2d3948', roadColor: '#4f5862', sidewalkColor: '#9e968e', laneColor: '#d4d8dc', landmarkGlow: 0.22, rainVisibility: 0.74, pathBrightness: 0.34, fogBoost: -0.002 }, normalized.timeOfDayPresets.morning));
    normalized.timeOfDayPresets.dusk = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#3f4b67', ambientColor: '#9eb0c6', ambientIntensity: 0.84, keyColor: '#f4c18f', keyIntensity: 0.65, fillColor: '#6e7a97', fillIntensity: 0.28, buildingContrast: 1.08, buildingEdgeColor: '#1f2c3c', roadColor: '#343a45', sidewalkColor: '#8f8780', laneColor: '#c4c7cc', landmarkGlow: 0.38, rainVisibility: 0.92, pathBrightness: 0.24, fogBoost: 0.003 }, normalized.timeOfDayPresets.dusk));
    normalized.timeOfDayPresets.night = mergePreset(defaultTimeOfDayPreset, Object.assign({ sky: '#101822', ambientColor: '#7e93ab', ambientIntensity: 0.72, keyColor: '#9eb9d4', keyIntensity: 0.6, fillColor: '#526782', fillIntensity: 0.22, buildingContrast: 1.2, buildingEdgeColor: '#1a2230', roadColor: '#2b3138', sidewalkColor: '#827a74', laneColor: '#adb3ba', landmarkGlow: 0.44, rainVisibility: 1, pathBrightness: 0.2, fogBoost: 0.006 }, normalized.timeOfDayPresets.night));

    return normalized;
  }

  window.GastownWorldLoader = {
    load: loadGastownWorldData,
    normalizeWorldData: normalizeWorldData,
  };
})(window);
