$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
$out = Join-Path $dist 'lousy-outages.zip'
$src = Join-Path $root 'plugins/lousy-outages'

if (-not (Test-Path $src)) {
    throw "Source plugin directory not found: $src"
}

New-Item -ItemType Directory -Force -Path $dist | Out-Null
if (Test-Path $out) { Remove-Item $out -Force }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$excludeDirNames = @('.git', 'tests', 'node_modules', 'secrets')
$requiredEntries = @(
    'lousy-outages/lousy-outages.php',
    'lousy-outages/includes/ExternalSignals.php',
    'lousy-outages/public/shortcode.php'
)

$files = Get-ChildItem -Path $src -File -Recurse -Force | Where-Object {
    $rel = $_.FullName.Substring($src.Length).TrimStart('\\','/')
    if ([string]::IsNullOrWhiteSpace($rel)) { return $false }

    $segments = $rel -split '[\\/]'
    foreach ($segment in $segments) {
        if ($excludeDirNames -contains $segment) { return $false }
    }

    $name = $_.Name
    if ($name -eq 'wp-config.php') { return $false }
    if ($name -eq '.env') { return $false }
    if ($name -like '.env.*') { return $false }
    if ($name -like '*.bak') { return $false }
    if ($name -like '*.backup') { return $false }
    if ($name -like '*.tmp') { return $false }
    if ($name -eq '.DS_Store') { return $false }

    return $true
}

$zip = [System.IO.Compression.ZipFile]::Open($out, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $files) {
        $rel = $file.FullName.Substring($src.Length).TrimStart('\\','/')
        $normalized = $rel -replace '\\','/'
        $entryName = "lousy-outages/$normalized"
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
}
finally {
    $zip.Dispose()
}

$zipCheck = [System.IO.Compression.ZipFile]::OpenRead($out)
try {
    $entries = @($zipCheck.Entries | ForEach-Object { $_.FullName })

    if ($entries.Count -eq 0) {
        throw 'ZIP is empty.'
    }

    if ($entries | Where-Object { $_ -like '*\\*' }) {
        throw 'ZIP contains backslash path separators.'
    }

    if ($entries | Where-Object { $_ -notlike 'lousy-outages/*' }) {
        throw 'ZIP contains entries outside lousy-outages/ root.'
    }

    foreach ($required in $requiredEntries) {
        if (-not ($entries -contains $required)) {
            throw "ZIP missing required entry: $required"
        }
    }
}
finally {
    $zipCheck.Dispose()
}

Write-Output "Built and validated: $out"
