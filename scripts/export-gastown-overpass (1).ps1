param(
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path,
    [string]$RouteAnchorsPath = "",
    [string]$Endpoint = "https://overpass-api.de/api/interpreter",
    [string]$UserAgent = "SuzyGastownSim/0.1 (local build pipeline)",
    [int]$TimeoutSeconds = 90,
    [double]$PaddingLat = 0.0018,
    [double]$PaddingLon = 0.0024
)

$ErrorActionPreference = 'Stop'

if (-not $RouteAnchorsPath) {
    $RouteAnchorsPath = Join-Path $RepoRoot 'data/reference/route-anchors.json'
}

if (-not (Test-Path $RouteAnchorsPath)) {
    throw "Missing route anchors file: $RouteAnchorsPath"
}

$anchors = Get-Content -Raw -Path $RouteAnchorsPath | ConvertFrom-Json
$origin = $anchors.origin
$dest = $anchors.dest

$minLat = [Math]::Min([double]$origin.lat, [double]$dest.lat) - $PaddingLat
$maxLat = [Math]::Max([double]$origin.lat, [double]$dest.lat) + $PaddingLat
$minLon = [Math]::Min([double]$origin.lon, [double]$dest.lon) - $PaddingLon
$maxLon = [Math]::Max([double]$origin.lon, [double]$dest.lon) + $PaddingLon

$osmDir = Join-Path $RepoRoot 'data/osm'
New-Item -ItemType Directory -Force -Path $osmDir | Out-Null
$rawPath = Join-Path $osmDir 'gastown-overpass.json'
$poiPath = Join-Path $osmDir 'gastown-pois.geojson'
$manifestPath = Join-Path $osmDir '_manifest.json'

$bbox = "$minLat,$minLon,$maxLat,$maxLon"
$query = @"
[out:json][timeout:$TimeoutSeconds];
(
  nwr[building]($bbox);
  nwr[shop]($bbox);
  nwr[amenity]($bbox);
  nwr[tourism]($bbox);
  nwr[historic]($bbox);
  nwr[railway]($bbox);
  nwr[public_transport]($bbox);
  nwr[highway=pedestrian]($bbox);
  nwr[name~"Steam Clock|Waterfront|Gastown|Maple Tree", i]($bbox);
);
out center geom tags;
"@

$headers = @{
    'User-Agent' = $UserAgent
    'Accept' = 'application/json'
}

Write-Host "Requesting Overpass data for bbox: $bbox" -ForegroundColor Cyan
$response = Invoke-RestMethod -Method Post -Uri $Endpoint -Headers $headers -Body @{ data = $query } -TimeoutSec $TimeoutSeconds

if ($null -eq $response -or $null -eq $response.elements) {
    throw "Overpass did not return an elements array."
}

$response | ConvertTo-Json -Depth 100 | Set-Content -Path $rawPath -Encoding UTF8

$features = @()
foreach ($element in $response.elements) {
    if ($null -eq $element.tags) { continue }

    $lon = $null
    $lat = $null
    if ($element.center) {
        $lon = $element.center.lon
        $lat = $element.center.lat
    }
    elseif ($element.lon -and $element.lat) {
        $lon = $element.lon
        $lat = $element.lat
    }
    elseif ($element.geometry -and $element.geometry.Count -gt 0) {
        $lon = $element.geometry[0].lon
        $lat = $element.geometry[0].lat
    }

    if ($null -eq $lon -or $null -eq $lat) { continue }

    $name = if ($element.tags.name) { $element.tags.name } else { "$($element.type)-$($element.id)" }
    $kind = if ($element.tags.tourism) { "tourism" }
        elseif ($element.tags.historic) { "historic" }
        elseif ($element.tags.shop) { "shop" }
        elseif ($element.tags.amenity) { "amenity" }
        elseif ($element.tags.railway) { "railway" }
        elseif ($element.tags.public_transport) { "public_transport" }
        elseif ($element.tags.building) { "building" }
        else { "osm" }

    $properties = [ordered]@{
        id = "$($element.type)-$($element.id)"
        label = $name
        kind = $kind
        osm_type = $element.type
        osm_id = $element.id
        tags = $element.tags
    }

    $features += [ordered]@{
        type = 'Feature'
        properties = $properties
        geometry = [ordered]@{
            type = 'Point'
            coordinates = @([double]$lon, [double]$lat)
        }
    }
}

$geojson = [ordered]@{
    type = 'FeatureCollection'
    name = 'gastown-pois'
    features = $features
}

$geojson | ConvertTo-Json -Depth 100 | Set-Content -Path $poiPath -Encoding UTF8

$manifest = [ordered]@{
    timestamp = (Get-Date).ToString('o')
    endpoint = $Endpoint
    bbox = [ordered]@{
        minLat = $minLat
        minLon = $minLon
        maxLat = $maxLat
        maxLon = $maxLon
    }
    files = [ordered]@{
        raw = 'data/osm/gastown-overpass.json'
        pois = 'data/osm/gastown-pois.geojson'
    }
    counts = [ordered]@{
        elements = @($response.elements).Count
        poiFeatures = @($features).Count
    }
}

$manifest | ConvertTo-Json -Depth 20 | Set-Content -Path $manifestPath -Encoding UTF8

Write-Host "Saved raw Overpass response -> data/osm/gastown-overpass.json" -ForegroundColor Green
Write-Host "Saved derived POI GeoJSON  -> data/osm/gastown-pois.geojson" -ForegroundColor Green
