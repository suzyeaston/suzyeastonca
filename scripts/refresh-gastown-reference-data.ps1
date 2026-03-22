param(
    [string]$RouteAnchorsPath = "data/reference/route-anchors.json",
    [string]$OutputDir = "data/reference/refresh",
    [switch]$SkipOverpass
)

<#
.SYNOPSIS
Refreshes lightweight reference inputs for future Gastown world rebuilds.

.DESCRIPTION
This helper does not replace the deterministic fallback pipeline. It fetches
open reference data into local JSON/GeoJSON files so the project can evolve
toward a less fake build when civic datasets are available.

Inputs:
 - data/reference/route-anchors.json

Outputs:
 - data/reference/refresh/gastown-route-reference.geojson
 - data/reference/refresh/gastown-overpass-buildings.json (optional)

Typical use:
  pwsh ./scripts/refresh-gastown-reference-data.ps1
  pwsh ./scripts/refresh-gastown-reference-data.ps1 -SkipOverpass

After refreshing these files, review them and then feed the cleaned inputs into
scripts/generate-gastown-world.js or follow-up build tooling.
#>

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $RouteAnchorsPath)) {
    throw "Missing route anchors file: $RouteAnchorsPath"
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$anchors = Get-Content -Raw -Path $RouteAnchorsPath | ConvertFrom-Json

function New-FeatureCollection {
    param([array]$Features)
    return @{
        type = 'FeatureCollection'
        features = $Features
    }
}

$origin = @($anchors.origin.lon, $anchors.origin.lat)
$dest = @($anchors.dest.lon, $anchors.dest.lat)

$routeReference = New-FeatureCollection @(
    @{
        type = 'Feature'
        properties = @{
            id = 'gastown-origin'
            label = 'Waterfront origin anchor'
        }
        geometry = @{
            type = 'Point'
            coordinates = $origin
        }
    },
    @{
        type = 'Feature'
        properties = @{
            id = 'gastown-destination'
            label = 'Steam Clock destination anchor'
        }
        geometry = @{
            type = 'Point'
            coordinates = $dest
        }
    },
    @{
        type = 'Feature'
        properties = @{
            id = 'gastown-reference-line'
            label = 'Route anchor line for future map cleaning'
        }
        geometry = @{
            type = 'LineString'
            coordinates = @($origin, $dest)
        }
    }
)

$routeReferencePath = Join-Path $OutputDir 'gastown-route-reference.geojson'
$routeReference | ConvertTo-Json -Depth 8 | Set-Content -Path $routeReferencePath

if (-not $SkipOverpass) {
    $minLon = [Math]::Min($anchors.origin.lon, $anchors.dest.lon) - 0.003
    $minLat = [Math]::Min($anchors.origin.lat, $anchors.dest.lat) - 0.003
    $maxLon = [Math]::Max($anchors.origin.lon, $anchors.dest.lon) + 0.003
    $maxLat = [Math]::Max($anchors.origin.lat, $anchors.dest.lat) + 0.003
    $bbox = "$minLat,$minLon,$maxLat,$maxLon"
    $query = "[out:json][timeout:25];(way[""building""]($bbox);node(w););out body;"
    $encoded = [System.Uri]::EscapeDataString($query)
    $overpassUrl = "https://overpass-api.de/api/interpreter?data=$encoded"
    $overpassPath = Join-Path $OutputDir 'gastown-overpass-buildings.json'
    Invoke-WebRequest -Uri $overpassUrl -OutFile $overpassPath
}

Write-Host "Saved route reference to $routeReferencePath"
if (-not $SkipOverpass) {
    Write-Host "Saved optional Overpass building reference into $OutputDir"
}
