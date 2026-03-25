param(
    [Parameter(Mandatory = $true)]
    [string]$InputPath,
    [ValidateSet('buildings','streets','lighting','trees','heritage','custom')]
    [string]$Kind = 'custom',
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path,
    [string]$CustomOutputRelativePath = 'data/imports/custom.geojson',
    [switch]$RebuildWorld
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $InputPath)) {
    throw "Input file not found: $InputPath"
}

$json = Get-Content -Raw -Path $InputPath | ConvertFrom-Json
if ($null -eq $json.type -or $json.type -ne 'FeatureCollection') {
    throw 'Input must be a GeoJSON FeatureCollection.'
}

$relativeDestination = switch ($Kind) {
    'buildings' { 'data/cov/building-footprints.geojson' }
    'streets'   { 'data/cov/public-streets.geojson' }
    'lighting'  { 'data/cov/street-lighting-poles.geojson' }
    'trees'     { 'data/cov/public-trees.geojson' }
    'heritage'  { 'data/cov/heritage-sites.geojson' }
    default     { $CustomOutputRelativePath }
}

$destination = Join-Path $RepoRoot $relativeDestination
$destinationDir = Split-Path -Parent $destination
New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
Copy-Item -Path $InputPath -Destination $destination -Force

$featureCount = if ($json.features) { @($json.features).Count } else { 0 }
Write-Host "Imported $featureCount features -> $relativeDestination" -ForegroundColor Green

if ($RebuildWorld) {
    Push-Location $RepoRoot
    try {
        & node (Join-Path 'scripts' 'build-gastown-world.js')
        if ($LASTEXITCODE -ne 0) {
            throw 'World rebuild failed after import.'
        }
    }
    finally {
        Pop-Location
    }
}
