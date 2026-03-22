param(
    [string]$RouteAnchorsPath = "data/reference/route-anchors.json",
    [string]$OutputDir = "data/reference/refresh",
    [switch]$SkipOverpass
)

<#
.SYNOPSIS
Refreshes lightweight open reference inputs for the working Gastown fallback build.

.DESCRIPTION
This helper keeps the simulator boot-safe: it always writes deterministic local
reference files first, then optionally enriches them with open web data.

Inputs:
 - data/reference/route-anchors.json

Outputs:
 - data/reference/refresh/gastown-route-reference.geojson
 - data/reference/refresh/gastown-street-context.geojson
 - data/reference/refresh/gastown-landmark-reference.geojson
 - data/reference/refresh/gastown-building-cues.geojson
 - data/reference/refresh/gastown-poi-reference.geojson
 - data/reference/refresh/gastown-overpass-buildings.json (optional raw Overpass snapshot)

How the generator uses these files:
 - route-reference: origin/destination + deterministic route skeleton
 - street-context: centerline context, curb/road framing hints, and road-vs-sidewalk staging
 - landmark-reference: stable named route beats such as Waterfront Station,
   Water/Cordova seam, Steam Clock, and Maple Tree Square edge
 - building-cues: simplified frontage cadence / massing cues for fallback staging
 - poi-reference: optional open POI landmarks around Water Street / Cordova

Typical use:
  pwsh ./scripts/refresh-gastown-reference-data.ps1
  pwsh ./scripts/refresh-gastown-reference-data.ps1 -SkipOverpass
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

function New-PointFeature {
    param(
        [string]$Id,
        [string]$Label,
        [double]$Lon,
        [double]$Lat,
        [hashtable]$Props = @{}
    )
    return @{
        type = 'Feature'
        properties = (@{
            id = $Id
            label = $Label
        } + $Props)
        geometry = @{
            type = 'Point'
            coordinates = @($Lon, $Lat)
        }
    }
}

function New-LineFeature {
    param(
        [string]$Id,
        [string]$Label,
        [array]$Coordinates,
        [hashtable]$Props = @{}
    )
    return @{
        type = 'Feature'
        properties = (@{
            id = $Id
            label = $Label
        } + $Props)
        geometry = @{
            type = 'LineString'
            coordinates = $Coordinates
        }
    }
}

$origin = @($anchors.origin.lon, $anchors.origin.lat)
$dest = @($anchors.dest.lon, $anchors.dest.lat)

$routeMid = @(
    @(-123.11115, 49.28558),
    @(-123.11032, 49.28512),
    @(-123.10962, 49.28482)
)

$routeReference = New-FeatureCollection @(
    (New-PointFeature -Id 'waterfront-station-threshold' -Label 'Waterfront Station threshold' -Lon $anchors.origin.lon -Lat $anchors.origin.lat -Props @{ kind = 'origin_anchor'; route_role = 'start' }),
    (New-PointFeature -Id 'steam-clock' -Label 'Gastown Steam Clock' -Lon $anchors.dest.lon -Lat $anchors.dest.lat -Props @{ kind = 'destination_anchor'; route_role = 'destination' }),
    (New-LineFeature -Id 'gastown-working-route' -Label 'Waterfront to Steam Clock working corridor' -Coordinates @($origin) + $routeMid + @($dest) -Props @{ kind = 'route_skeleton'; source = 'deterministic_open_reference' })
)

$streetContext = New-FeatureCollection @(
    (New-LineFeature -Id 'water-street-context-centerline' -Label 'Water Street context centerline' -Coordinates @(
        @(-123.11158, 49.28574),
        @(-123.11118, 49.28554),
        @(-123.11066, 49.28527),
        @(-123.11006, 49.28501),
        @(-123.10954, 49.28477),
        @(-123.10908, 49.28456)
    ) -Props @{ corridor = 'water-street'; priority = 'primary' }),
    (New-LineFeature -Id 'cordova-transition-context' -Label 'Cordova transition seam' -Coordinates @(
        @(-123.11177, 49.28586),
        @(-123.11132, 49.28563),
        @(-123.11094, 49.28543)
    ) -Props @{ corridor = 'water-cordova-seam'; priority = 'secondary' })
)

    (New-LineFeature -Id 'water-street-south-curb' -Label 'Water Street south curb cue' -Coordinates @(
        @(-123.11135, 49.28550),
        @(-123.11080, 49.28525),
        @(-123.10992, 49.28486),
        @(-123.10941, 49.28464)
    ) -Props @{ corridor = 'water-street'; kind = 'curb_edge'; priority = 'supporting' }),
    (New-LineFeature -Id 'water-street-north-curb' -Label 'Water Street north curb cue' -Coordinates @(
        @(-123.11110, 49.28575),
        @(-123.11057, 49.28551),
        @(-123.10967, 49.28512),
        @(-123.10919, 49.28491)
    ) -Props @{ corridor = 'water-street'; kind = 'curb_edge'; priority = 'supporting' }),
    (New-LineFeature -Id 'steam-clock-plaza-edge' -Label 'Steam Clock plaza edge cue' -Coordinates @(
        @(-123.10933, 49.28470),
        @(-123.10912, 49.28459),
        @(-123.10894, 49.28447)
    ) -Props @{ corridor = 'steam-clock'; kind = 'plaza_edge'; priority = 'primary' })
)

$landmarkReference = New-FeatureCollection @(
    (New-PointFeature -Id 'waterfront-station-threshold' -Label 'Waterfront Station threshold' -Lon $anchors.origin.lon -Lat $anchors.origin.lat -Props @{ kind = 'district_gate'; sequence = 1 }),
    (New-PointFeature -Id 'water-cordova-seam' -Label 'Water/Cordova seam' -Lon -123.11110 -Lat 49.28548 -Props @{ kind = 'street_pivot'; sequence = 2 }),
    (New-PointFeature -Id 'water-street-mid-block' -Label 'Water Street mid block' -Lon -123.10992 -Lat 49.28495 -Props @{ kind = 'heritage_frontage'; sequence = 3 }),
    (New-PointFeature -Id 'steam-clock' -Label 'Gastown Steam Clock' -Lon $anchors.dest.lon -Lat $anchors.dest.lat -Props @{ kind = 'clock'; sequence = 4 }),
    (New-PointFeature -Id 'maple-tree-square-edge' -Label 'Maple Tree Square edge' -Lon -123.10866 -Lat 49.28426 -Props @{ kind = 'plaza_edge'; sequence = 5 })
)

$buildingCues = New-FeatureCollection @(
    @{
        type = 'Feature'
        properties = @{
            id = 'water-street-south-frontage'
            label = 'Water Street south frontage massing cue'
            kind = 'frontage_band'
            side = 'south'
            rhythm = 'tight_heritage'
        }
        geometry = @{
            type = 'Polygon'
            coordinates = @(@(
                @(-123.11129, 49.28546),
                @(-123.11075, 49.28520),
                @(-123.10985, 49.28480),
                @(-123.10938, 49.28461),
                @(-123.10930, 49.28448),
                @(-123.10990, 49.28473),
                @(-123.11092, 49.28518),
                @(-123.11142, 49.28542),
                @(-123.11129, 49.28546)
            ))
        }
    },
    @{
        type = 'Feature'
        properties = @{
            id = 'water-street-north-frontage'
            label = 'Water Street north frontage massing cue'
            kind = 'frontage_band'
            side = 'north'
            rhythm = 'split_corner_then_longer_row'
        }
        geometry = @{
            type = 'Polygon'
            coordinates = @(@(
                @(-123.11104, 49.28578),
                @(-123.11053, 49.28553),
                @(-123.10960, 49.28511),
                @(-123.10913, 49.28490),
                @(-123.10901, 49.28499),
                @(-123.10952, 49.28522),
                @(-123.11045, 49.28563),
                @(-123.11094, 49.28587),
                @(-123.11104, 49.28578)
            ))
        }
    },
    @{
        type = 'Feature'
        properties = @{
            id = 'steam-clock-frontage-cadence'
            label = 'Steam Clock frontage cadence cue'
            kind = 'frontage_band'
            side = 'clock_plaza'
            rhythm = 'plaza_pause_then_tight_row'
        }
        geometry = @{
            type = 'Polygon'
            coordinates = @(@(
                @(-123.10942, 49.28473),
                @(-123.10917, 49.28461),
                @(-123.10896, 49.28447),
                @(-123.10889, 49.28455),
                @(-123.10910, 49.28468),
                @(-123.10935, 49.28479),
                @(-123.10942, 49.28473)
            ))
        }
    }
)

$poiReference = New-FeatureCollection @(
    (New-PointFeature -Id 'steam-clock-poi' -Label 'Gastown Steam Clock POI' -Lon $anchors.dest.lon -Lat $anchors.dest.lat -Props @{ category = 'landmark'; source = 'manual_open_reference_seed' }),
    (New-PointFeature -Id 'waterfront-station-poi' -Label 'Waterfront Station POI' -Lon $anchors.origin.lon -Lat $anchors.origin.lat -Props @{ category = 'transit'; source = 'manual_open_reference_seed' }),
    (New-PointFeature -Id 'maple-tree-square-poi' -Label 'Maple Tree Square POI' -Lon -123.10866 -Lat 49.28426 -Props @{ category = 'plaza'; source = 'manual_open_reference_seed' })
)

$routeReferencePath = Join-Path $OutputDir 'gastown-route-reference.geojson'
$streetContextPath = Join-Path $OutputDir 'gastown-street-context.geojson'
$landmarkReferencePath = Join-Path $OutputDir 'gastown-landmark-reference.geojson'
$buildingCuePath = Join-Path $OutputDir 'gastown-building-cues.geojson'
$poiReferencePath = Join-Path $OutputDir 'gastown-poi-reference.geojson'

$routeReference | ConvertTo-Json -Depth 10 | Set-Content -Path $routeReferencePath
$streetContext | ConvertTo-Json -Depth 10 | Set-Content -Path $streetContextPath
$landmarkReference | ConvertTo-Json -Depth 10 | Set-Content -Path $landmarkReferencePath
$buildingCues | ConvertTo-Json -Depth 10 | Set-Content -Path $buildingCuePath
$poiReference | ConvertTo-Json -Depth 10 | Set-Content -Path $poiReferencePath

if (-not $SkipOverpass) {
    $minLon = [Math]::Min($anchors.origin.lon, $anchors.dest.lon) - 0.003
    $minLat = [Math]::Min($anchors.origin.lat, $anchors.dest.lat) - 0.003
    $maxLon = [Math]::Max($anchors.origin.lon, $anchors.dest.lon) + 0.003
    $maxLat = [Math]::Max($anchors.origin.lat, $anchors.dest.lat) + 0.003
    $bbox = "$minLat,$minLon,$maxLat,$maxLon"
    $query = "[out:json][timeout:25];(way[""building""]($bbox);node(w);nwr[""tourism""]($bbox);nwr[""historic""]($bbox);nwr[""amenity""]($bbox););out body;"
    $encoded = [System.Uri]::EscapeDataString($query)
    $overpassUrl = "https://overpass-api.de/api/interpreter?data=$encoded"
    $overpassPath = Join-Path $OutputDir 'gastown-overpass-buildings.json'
    Invoke-WebRequest -Uri $overpassUrl -OutFile $overpassPath
}

Write-Host "Saved route reference to $routeReferencePath"
Write-Host "Saved street context to $streetContextPath"
Write-Host "Saved landmark reference to $landmarkReferencePath"
Write-Host "Saved building cues to $buildingCuePath"
Write-Host "Saved POI reference to $poiReferencePath"
if (-not $SkipOverpass) {
    Write-Host "Saved optional Overpass buildings + POI snapshot to $OutputDir"
}
