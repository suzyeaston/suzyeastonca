# Gastown data pipeline quickstart

## What these scripts do

They pull free/open map context into the theme repo, then rebuild `assets/world/gastown-water-street.json`.

### Main commands

#### 1) Pull civic + OSM data and rebuild the simulator

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-vancouver-scene-cache.ps1
```

#### 2) Pull only civic data + rebuild

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\build-vancouver-scene-cache.ps1 -SkipOverpass
```

#### 3) Import your own GeoJSON and rebuild

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\import-gastown-geojson.ps1 `
  -InputPath C:\maps\building-footprints.geojson `
  -Kind buildings `
  -RebuildWorld
```

## Output files

- `data/cov/*.geojson` = City of Vancouver open-data slices
- `data/osm/gastown-overpass.json` = raw OSM/Overpass pull
- `data/osm/gastown-pois.geojson` = simplified POI points
- `data/reference/refresh/*.geojson` = helper reference files
- `assets/world/gastown-water-street.json` = runtime world used by the simulator

## Why this matters

Your current sim looks generic because the world file is still fallback-heavy. The win is not "better shaders first". The win is:

1. better building footprints
2. better street alignment
3. better landmark placement
4. more prop density near Steam Clock / Maple Tree Square / Waterfront edge
5. recognisable block-by-block facade variation

Data first, then art pass. That's the rock move.
