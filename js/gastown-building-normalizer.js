(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
    return;
  }

  root.GastownBuildingNormalizer = factory();
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function isFiniteNumber(value) {
    return typeof value === 'number' && Number.isFinite(value);
  }

  function sanitizePoints(points) {
    if (!Array.isArray(points)) return [];
    return points
      .filter((point) => point && isFiniteNumber(point.x) && isFiniteNumber(point.z))
      .map((point) => ({ x: point.x, z: point.z }));
  }

  function uniquePointCount(points) {
    return new Set(points.map((point) => point.x.toFixed(4) + '|' + point.z.toFixed(4))).size;
  }

  function ensureRenderableFootprint(localFootprint, width, depth) {
    const usable = sanitizePoints(localFootprint);
    if (usable.length >= 3 && uniquePointCount(usable) >= 3) {
      return usable;
    }

    const safeWidth = Math.max(3, isFiniteNumber(width) ? Math.abs(width) : 8);
    const safeDepth = Math.max(3, isFiniteNumber(depth) ? Math.abs(depth) : 8);
    return [
      { x: -safeWidth / 2, z: -safeDepth / 2 },
      { x: safeWidth / 2, z: -safeDepth / 2 },
      { x: safeWidth / 2, z: safeDepth / 2 },
      { x: -safeWidth / 2, z: safeDepth / 2 },
    ];
  }

  function getCentroid(points) {
    const usable = sanitizePoints(points);
    if (!usable.length) return { x: 0, z: 0 };

    const sum = usable.reduce((acc, point) => ({ x: acc.x + point.x, z: acc.z + point.z }), { x: 0, z: 0 });
    return { x: sum.x / usable.length, z: sum.z / usable.length };
  }

  function getBounds(points) {
    const usable = sanitizePoints(points);
    if (!usable.length) {
      return { minX: -4, maxX: 4, minZ: -4, maxZ: 4, width: 8, depth: 8 };
    }

    let minX = Infinity;
    let maxX = -Infinity;
    let minZ = Infinity;
    let maxZ = -Infinity;

    usable.forEach((point) => {
      minX = Math.min(minX, point.x);
      maxX = Math.max(maxX, point.x);
      minZ = Math.min(minZ, point.z);
      maxZ = Math.max(maxZ, point.z);
    });

    return {
      minX,
      maxX,
      minZ,
      maxZ,
      width: Math.max(3, maxX - minX),
      depth: Math.max(3, maxZ - minZ),
    };
  }

  function deriveYawFromFootprint(points, fallbackYaw) {
    if (isFiniteNumber(fallbackYaw)) {
      return fallbackYaw;
    }

    const usable = sanitizePoints(points);
    let longest = { dx: 0, dz: 0, len: 0 };
    for (let i = 0; i < usable.length; i += 1) {
      const current = usable[i];
      const next = usable[(i + 1) % usable.length];
      const dx = next.x - current.x;
      const dz = next.z - current.z;
      const len = Math.hypot(dx, dz);
      if (len > longest.len) {
        longest = { dx, dz, len };
      }
    }

    if (longest.len < 0.01) return 0;
    return Math.atan2(longest.dz, longest.dx);
  }

  function rotatePoint(point, angle) {
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    return {
      x: (point.x * cos) - (point.z * sin),
      z: (point.x * sin) + (point.z * cos),
    };
  }

  function hasTrustedLocalFootprint(source, localFootprint) {
    return localFootprint.length >= 3
      && uniquePointCount(localFootprint) >= 3
      && isFiniteNumber(source.x)
      && isFiniteNumber(source.z)
      && isFiniteNumber(source.yaw);
  }

  function normalizeBuildingForRender(building) {
    const source = building || {};
    const absoluteFootprint = sanitizePoints(source.footprint);
    const localFromSource = sanitizePoints(source.footprint_local);
    const sourceCentroid = getCentroid(absoluteFootprint);

    const trustedLocal = hasTrustedLocalFootprint(source, localFromSource);
    const x = isFiniteNumber(source.x) ? source.x : sourceCentroid.x;
    const z = isFiniteNumber(source.z) ? source.z : sourceCentroid.z;

    let yaw = deriveYawFromFootprint(absoluteFootprint.length >= 3 ? absoluteFootprint : localFromSource, source.yaw);
    let localFootprint = localFromSource;

    if (!trustedLocal) {
      const centered = absoluteFootprint.map((point) => ({
        x: point.x - x,
        z: point.z - z,
      }));
      localFootprint = centered.map((point) => rotatePoint(point, -yaw));
    }

    const safeLocalFootprint = ensureRenderableFootprint(localFootprint, source.width, source.depth);
    const localBounds = getBounds(safeLocalFootprint);

    const width = isFiniteNumber(source.width) ? Math.max(3, Math.abs(source.width)) : localBounds.width;
    const depth = isFiniteNumber(source.depth) ? Math.max(3, Math.abs(source.depth)) : localBounds.depth;
    yaw = trustedLocal ? deriveYawFromFootprint(safeLocalFootprint, source.yaw) : yaw;

    const absolute = absoluteFootprint.length >= 3
      ? absoluteFootprint
      : safeLocalFootprint.map((point) => ({ x: x + point.x, z: z + point.z }));

    return {
      ...source,
      x: isFiniteNumber(x) ? x : 0,
      z: isFiniteNumber(z) ? z : 0,
      width,
      depth,
      yaw: isFiniteNumber(yaw) ? yaw : 0,
      height: isFiniteNumber(source.height) ? Math.max(4, source.height) : 12,
      footprint: absolute,
      footprint_local: safeLocalFootprint,
      roofline_type: source.roofline_type || 'flat_cornice',
      window_bay_count: isFiniteNumber(source.window_bay_count) ? source.window_bay_count : 4,
      recessed_entry_count: isFiniteNumber(source.recessed_entry_count) ? source.recessed_entry_count : 1,
      storefront_rhythm: Object.assign({ base_band: 0.18, upper_rows: 3, bay_spacing: 2.8, entry_depth: 0.72, transom_band: 0.18 }, source.storefront_rhythm || {}),
      material_palette: Object.assign({ primary: 'brickDark', trim: 'stoneMuted', accent: 'stoneMuted', secondary: 'brickWarm' }, source.material_palette || {}),
      cornice_emphasis: isFiniteNumber(source.cornice_emphasis) ? source.cornice_emphasis : 0.24,
      mass_inset: isFiniteNumber(source.mass_inset) ? source.mass_inset : 0.96,
      hero_fidelity: source.hero_fidelity || 'standard',
      facade_variation_seed: isFiniteNumber(source.facade_variation_seed) ? source.facade_variation_seed : 0.5,
    };
  }

  return {
    normalizeBuildingForRender,
  };
});
