(function (window) {
  'use strict';

  async function loadGastownWorldData(url) {
    if (!url) {
      throw new Error('Missing world data URL.');
    }

    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
      throw new Error('Could not load Gastown world data.');
    }

    const data = await response.json();
    validateWorldData(data);
    return data;
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
      if (building.footprint) {
        assertPolygon(building.footprint, 'buildings[' + index + '].footprint');
      }
      if (building.window_bay_count && typeof building.window_bay_count !== 'number') {
        throw new Error('Gastown world data is malformed: buildings[' + index + '].window_bay_count must be numeric.');
      }
    });
  }

  window.GastownWorldLoader = {
    load: loadGastownWorldData,
  };
})(window);
