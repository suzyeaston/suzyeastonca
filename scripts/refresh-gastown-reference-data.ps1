param(
    [string]$RouteAnchorsPath = "data/reference/route-anchors.json",
    [string]$CovDataDir = "data/cov",
    [string]$OutputDir = "data/reference/refresh",
    [switch]$SkipOverpass
)

<#
.SYNOPSIS
Refreshes lightweight open reference inputs for the working Gastown fallback build.

.DESCRIPTION
This helper keeps the simulator boot-safe: it always writes deterministic local
reference files first, then enriches them with local City of Vancouver open-data
exports when available.

Primary City datasets consumed when present:
 - public-streets
 - street-intersections
 - right-of-way-widths
 - street-lighting-poles
 - building-footprints-2015

Inputs:
 - data/reference/route-anchors.json
 - data/cov/public-streets.geojson (optional)
 - data/cov/street-intersections.geojson (optional)
 - data/cov/right-of-way-widths.geojson (optional)
 - data/cov/street-lighting-poles.geojson (optional)
 - data/cov/building-footprints.geojson (optional, exported from building-footprints-2015)

Outputs:
 - data/reference/refresh/gastown-route-reference.geojson
 - data/reference/refresh/gastown-street-context.geojson
 - data/reference/refresh/gastown-landmark-reference.geojson
 - data/reference/refresh/gastown-building-cues.geojson
 - data/reference/refresh/gastown-poi-reference.geojson
 - data/reference/refresh/gastown-overpass-buildings.json (optional raw Overpass snapshot)
#>

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $RouteAnchorsPath)) {
    throw "Missing route anchors file: $RouteAnchorsPath"
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$anchors = Get-Content -Raw -Path $RouteAnchorsPath | ConvertFrom-Json

function New-FeatureCollection {
    param([array]$Features)
    @{ type = 'FeatureCollection'; features = $Features }
}

function New-PointFeature {
    param([string]$Id,[string]$Label,[double]$Lon,[double]$Lat,[hashtable]$Props = @{})
    @{ type = 'Feature'; properties = (@{ id = $Id; label = $Label } + $Props); geometry = @{ type = 'Point'; coordinates = @($Lon, $Lat) } }
}

function New-LineFeature {
    param([string]$Id,[string]$Label,[array]$Coordinates,[hashtable]$Props = @{})
    @{ type = 'Feature'; properties = (@{ id = $Id; label = $Label } + $Props); geometry = @{ type = 'LineString'; coordinates = $Coordinates } }
}

function New-PolygonFeature {
    param([string]$Id,[string]$Label,[array]$Coordinates,[hashtable]$Props = @{})
    @{ type = 'Feature'; properties = (@{ id = $Id; label = $Label } + $Props); geometry = @{ type = 'Polygon'; coordinates = @($Coordinates) } }
}

function Get-OptionalFeatureCollection {
    param([string]$Path)
    if (-not (Test-Path $Path)) { return $null }
    $data = Get-Content -Raw -Path $Path | ConvertFrom-Json
    if ($null -eq $data -or $data.type -ne 'FeatureCollection') { return $null }
    return $data
}

function Get-Coords($feature) {
    if ($null -eq $feature -or $null -eq $feature.geometry) { return $null }
    return $feature.geometry.coordinates
}

function Get-FirstLine($collection) {
    if ($null -eq $collection) { return $null }
    foreach ($feature in $collection.features) {
        if ($feature.geometry.type -eq 'LineString') { return $feature.geometry.coordinates }
    }
    return $null
}

function Get-FeatureById($collection, $id) {
    if ($null -eq $collection) { return $null }
    foreach ($feature in $collection.features) {
        if ($feature.properties.id -eq $id -or $feature.id -eq $id) { return $feature }
    }
    return $null
}

function Get-PropertyValue($props, [string[]]$Names) {
    foreach ($name in $Names) {
        if ($null -ne $props.PSObject.Properties[$name] -and $null -ne $props.$name -and "$($props.$name)" -ne '') {
            return $props.$name
        }
    }
    return $null
}

function Find-Feature($collection, [scriptblock]$Predicate) {
    if ($null -eq $collection) { return $null }
    foreach ($feature in $collection.features) {
        if (& $Predicate $feature) { return $feature }
    }
    return $null
}

$publicStreets = Get-OptionalFeatureCollection (Join-Path $CovDataDir 'public-streets.geojson')
$streetIntersections = Get-OptionalFeatureCollection (Join-Path $CovDataDir 'street-intersections.geojson')
$rightOfWayWidths = Get-OptionalFeatureCollection (Join-Path $CovDataDir 'right-of-way-widths.geojson')
$lightingPoles = Get-OptionalFeatureCollection (Join-Path $CovDataDir 'street-lighting-poles.geojson')
$buildingFootprints = Get-OptionalFeatureCollection (Join-Path $CovDataDir 'building-footprints.geojson')

$origin = @($anchors.origin.lon, $anchors.origin.lat)
$dest = @($anchors.dest.lon, $anchors.dest.lat)

$routeMid = @(
    @(-123.11145, 49.28586),
    @(-123.11096, 49.28557),
    @(-123.11018, 49.28518),
    @(-123.10954, 49.28484),
    @(-123.10916, 49.28460)
)

$defaultWaterCenterline = @(
    @(-123.11164, 49.28589),
    @(-123.11121, 49.28562),
    @(-123.11064, 49.28531),
    @(-123.11003, 49.28502),
    @(-123.10947, 49.28473),
    @(-123.10912, 49.28451)
)
$defaultWaterEast = @(
    @(-123.10925, 49.28459),
    @(-123.10898, 49.28447),
    @(-123.10874, 49.28433)
)
$defaultCambie = @(
    @(-123.10922, 49.28486),
    @(-123.10910, 49.28460),
    @(-123.10900, 49.28430)
)

$streetFeature = Find-Feature $publicStreets { param($feature)
    $name = (Get-PropertyValue $feature.properties @('street_name','full_name','name','STREET','ST_NAME'))
    return ($feature.geometry.type -eq 'LineString' -and "$name" -match 'Water')
}
$waterCenterlineCoords = if ($streetFeature) { Get-Coords $streetFeature } else { $defaultWaterCenterline }
$waterEastCoords = $defaultWaterEast

$cambieStreetFeature = Find-Feature $publicStreets { param($feature)
    $name = (Get-PropertyValue $feature.properties @('street_name','full_name','name','STREET','ST_NAME'))
    return ($feature.geometry.type -eq 'LineString' -and "$name" -match 'Cambie')
}
$cambieCoords = if ($cambieStreetFeature) { Get-Coords $cambieStreetFeature } else { $defaultCambie }

$intersectionFeature = Find-Feature $streetIntersections { param($feature)
    $props = $feature.properties
    $streetA = (Get-PropertyValue $props @('street_1','street_a','from_street','streetname1','streetName1','name_1'))
    $streetB = (Get-PropertyValue $props @('street_2','street_b','to_street','streetname2','streetName2','name_2'))
    return ("$streetA $streetB" -match 'Water' -and "$streetA $streetB" -match 'Cambie')
}
if ($null -eq $intersectionFeature) {
    $intersectionFeature = New-PointFeature -Id 'water-cambie-intersection' -Label 'Water/Cambie intersection' -Lon -123.10912 -Lat 49.28460 -Props @{ kind = 'street_intersection'; source_dataset = 'deterministic_fallback' }
}

$rowFeature = Find-Feature $rightOfWayWidths { param($feature)
    $name = (Get-PropertyValue $feature.properties @('street_name','full_name','name','STREET','ST_NAME'))
    return ("$name" -match 'Water')
}
$rowProps = if ($rowFeature) { $rowFeature.properties } else { @{} }
$rightOfWayWidth = [double](Get-PropertyValue $rowProps @('right_of_way_width','row_width','width_m','width'))
if (-not $rightOfWayWidth) { $rightOfWayWidth = 18.4 }
$carriagewayWidth = [double](Get-PropertyValue $rowProps @('carriageway_width','roadway_width','street_width'))
if (-not $carriagewayWidth) { $carriagewayWidth = 10.2 }
$sidewalkWidth = [double](Get-PropertyValue $rowProps @('sidewalk_width'))
if (-not $sidewalkWidth) { $sidewalkWidth = [Math]::Round(($rightOfWayWidth - $carriagewayWidth) / 2, 2) }

$lampFeatures = @()
if ($lightingPoles) {
    $i = 0
    foreach ($feature in $lightingPoles.features) {
        if ($feature.geometry.type -ne 'Point') { continue }
        $coords = Get-Coords $feature
        $lampFeatures += New-PointFeature -Id ("heritage-lamp-$i") -Label 'Heritage lamp cue' -Lon $coords[0] -Lat $coords[1] -Props @{ kind = 'heritage_lamp'; source_dataset = 'street-lighting-poles' }
        $i++
        if ($i -ge 10) { break }
    }
}

$buildingCueFeatures = @()
if ($buildingFootprints) {
    $i = 0
    foreach ($feature in $buildingFootprints.features) {
        if ($feature.geometry.type -ne 'Polygon') { continue }
        $ring = $feature.geometry.coordinates[0]
        if ($null -eq $ring -or $ring.Count -lt 4) { continue }
        $buildingCueFeatures += New-PolygonFeature -Id ("footprint-cue-$i") -Label 'Frontage massing cue' -Coordinates $ring -Props @{ kind = 'frontage_massing'; source_dataset = 'building-footprints-2015'; tone = 'stoneMuted'; height_hint = 15 }
        $i++
        if ($i -ge 16) { break }
    }
}
if ($buildingCueFeatures.Count -eq 0) {
    $buildingCueFeatures = @(
        (New-PolygonFeature -Id 'water-street-south-frontage' -Label 'Water Street south frontage massing cue' -Coordinates @(
            @(-123.11131, 49.28550), @(-123.11074, 49.28522), @(-123.10985, 49.28479), @(-123.10933, 49.28453), @(-123.10925, 49.28441), @(-123.10988, 49.28471), @(-123.11090, 49.28520), @(-123.11143, 49.28546), @(-123.11131, 49.28550)
        ) -Props @{ kind = 'frontage_massing'; side = 'south'; source_dataset = 'deterministic_fallback'; tone = 'brickWarm'; height_hint = 15 }),
        (New-PolygonFeature -Id 'water-street-north-frontage' -Label 'Water Street north frontage massing cue' -Coordinates @(
            @(-123.11105, 49.28582), @(-123.11051, 49.28555), @(-123.10960, 49.28512), @(-123.10914, 49.28487), @(-123.10901, 49.28498), @(-123.10952, 49.28524), @(-123.11045, 49.28568), @(-123.11095, 49.28591), @(-123.11105, 49.28582)
        ) -Props @{ kind = 'frontage_massing'; side = 'north'; source_dataset = 'deterministic_fallback'; tone = 'stoneMuted'; height_hint = 16 })
    )
}

$routeReference = New-FeatureCollection @(
    (New-PointFeature -Id 'waterfront-station-threshold' -Label 'Waterfront Station threshold' -Lon $anchors.origin.lon -Lat $anchors.origin.lat -Props @{ kind = 'origin_anchor'; route_role = 'start' }),
    (New-PointFeature -Id 'steam-clock' -Label 'Gastown Steam Clock' -Lon $anchors.dest.lon -Lat $anchors.dest.lat -Props @{ kind = 'destination_anchor'; route_role = 'destination' }),
    (New-LineFeature -Id 'gastown-working-route' -Label 'Waterfront to Steam Clock working corridor' -Coordinates (@($origin) + $routeMid + @($dest)) -Props @{ kind = 'route_skeleton'; source = 'deterministic_open_reference' })
)

$streetContext = New-FeatureCollection @(
    (New-LineFeature -Id 'water-street-context-centerline' -Label 'Water Street west context centerline' -Coordinates $waterCenterlineCoords -Props @{ corridor = 'water-street'; priority = 'primary'; use = 'main_corridor'; source_dataset = $(if($streetFeature){'public-streets'}else{'deterministic_fallback'}) }),
    (New-LineFeature -Id 'water-street-east-context-centerline' -Label 'Water Street east context centerline' -Coordinates $waterEastCoords -Props @{ corridor = 'water-street'; priority = 'primary'; use = 'east_leg'; source_dataset = $(if($streetFeature){'public-streets'}else{'deterministic_fallback'}) }),
    (New-LineFeature -Id 'cambie-street-centerline' -Label 'Cambie Street context centerline' -Coordinates $cambieCoords -Props @{ corridor = 'cambie-street'; priority = 'primary'; use = 'cross_corridor'; source_dataset = $(if($cambieStreetFeature){'public-streets'}else{'deterministic_fallback'}) }),
    (New-PointFeature -Id 'water-cambie-intersection' -Label 'Water/Cambie intersection' -Lon (Get-Coords $intersectionFeature)[0] -Lat (Get-Coords $intersectionFeature)[1] -Props @{ kind = 'street_intersection'; source_dataset = $(if($streetIntersections){'street-intersections'}else{'deterministic_fallback'}) }),
    (New-PointFeature -Id 'water-cambie-row-width' -Label 'Water/Cambie right-of-way width cue' -Lon (Get-Coords $intersectionFeature)[0] -Lat (Get-Coords $intersectionFeature)[1] -Props @{ kind = 'row_width'; source_dataset = $(if($rightOfWayWidths){'right-of-way-widths'}else{'deterministic_fallback'}); right_of_way_width = $rightOfWayWidth; carriageway_width = $carriagewayWidth; sidewalk_width = $sidewalkWidth }),
    (New-LineFeature -Id 'steam-clock-plaza-edge' -Label 'Steam Clock plaza edge cue' -Coordinates @(
        @(-123.10936, 49.28473),
        @(-123.10918, 49.28464),
        @(-123.10900, 49.28455)
    ) -Props @{ corridor = 'steam-clock'; kind = 'plaza_edge'; priority = 'primary' })
)

$landmarkReference = New-FeatureCollection @(
    (New-PointFeature -Id 'waterfront-station-threshold' -Label 'Waterfront Station threshold' -Lon $anchors.origin.lon -Lat $anchors.origin.lat -Props @{ kind = 'district_gate'; sequence = 1 }),
    (New-PointFeature -Id 'water-cordova-seam' -Label 'Water/Cordova seam' -Lon -123.11103 -Lat 49.28551 -Props @{ kind = 'street_pivot'; sequence = 2 }),
    (New-PointFeature -Id 'water-street-mid-block' -Label 'Water Street mid block' -Lon -123.10989 -Lat 49.28492 -Props @{ kind = 'heritage_frontage'; sequence = 3 }),
    (New-PointFeature -Id 'steam-clock' -Label 'Gastown Steam Clock' -Lon $anchors.dest.lon -Lat $anchors.dest.lat -Props @{ kind = 'clock'; sequence = 4 }),
    (New-PointFeature -Id 'maple-tree-square-edge' -Label 'Maple Tree Square edge' -Lon -123.10876 -Lat 49.28429 -Props @{ kind = 'plaza_edge'; sequence = 5 })
)

$buildingCues = New-FeatureCollection $buildingCueFeatures
$poiFeatures = @(
    (New-PointFeature -Id 'steam-clock-poi' -Label 'Gastown Steam Clock POI' -Lon $anchors.dest.lon -Lat $anchors.dest.lat -Props @{ category = 'landmark'; source = 'manual_open_reference_seed' }),
    (New-PointFeature -Id 'waterfront-station-poi' -Label 'Waterfront Station POI' -Lon $anchors.origin.lon -Lat $anchors.origin.lat -Props @{ category = 'transit'; source = 'manual_open_reference_seed' }),
    (New-PointFeature -Id 'maple-tree-square-poi' -Label 'Maple Tree Square POI' -Lon -123.10876 -Lat 49.28429 -Props @{ category = 'plaza'; source = 'manual_open_reference_seed' })
)
if ($lampFeatures.Count -gt 0) { $poiFeatures += $lampFeatures }
$poiReference = New-FeatureCollection $poiFeatures

$routeReferencePath = Join-Path $OutputDir 'gastown-route-reference.geojson'
$streetContextPath = Join-Path $OutputDir 'gastown-street-context.geojson'
$landmarkReferencePath = Join-Path $OutputDir 'gastown-landmark-reference.geojson'
$buildingCuePath = Join-Path $OutputDir 'gastown-building-cues.geojson'
$poiReferencePath = Join-Path $OutputDir 'gastown-poi-reference.geojson'

$routeReference | ConvertTo-Json -Depth 14 | Set-Content -Path $routeReferencePath
$streetContext | ConvertTo-Json -Depth 14 | Set-Content -Path $streetContextPath
$landmarkReference | ConvertTo-Json -Depth 14 | Set-Content -Path $landmarkReferencePath
$buildingCues | ConvertTo-Json -Depth 14 | Set-Content -Path $buildingCuePath
$poiReference | ConvertTo-Json -Depth 14 | Set-Content -Path $poiReferencePath

if (-not $SkipOverpass) {
    $minLon = [Math]::Min($anchors.origin.lon, $anchors.dest.lon) - 0.003
    $minLat = [Math]::Min($anchors.origin.lat, $anchors.dest.lat) - 0.003
    $maxLon = [Math]::Max($anchors.origin.lon, $anchors.dest.lon) + 0.003
    $maxLat = [Math]::Max($anchors.origin.lat, $anchors.dest.lat) + 0.003
    $bbox = "$minLat,$minLon,$maxLat,$maxLon"
    $query = '[out:json][timeout:25];(way["building"](' + $bbox + ');node(w);nwr["tourism"](' + $bbox + ');nwr["historic"](' + $bbox + ');nwr["amenity"](' + $bbox + '););out body;'
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
