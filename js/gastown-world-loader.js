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

  function validateWorldData(data) {
    if (!data || !Array.isArray(data.nodes) || !Array.isArray(data.buildingPlanes)) {
      throw new Error('Gastown world data is malformed.');
    }
  }

  window.GastownWorldLoader = {
    load: loadGastownWorldData,
  };
})(window);
