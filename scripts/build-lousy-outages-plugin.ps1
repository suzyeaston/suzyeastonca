$ErrorActionPreference = "Stop"

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Resolve-Path (Join-Path $scriptRoot "..")
$src = Join-Path $repoRoot "plugins\lousy-outages"
$dist = Join-Path $repoRoot "dist"
$zipPath = Join-Path $dist "lousy-outages.zip"

if (-not (Test-Path $src)) {
    throw "Plugin source not found: $src"
}

if (-not (Test-Path $dist)) {
    New-Item -ItemType Directory -Path $dist | Out-Null
}

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$srcRoot = (Resolve-Path $src).Path
$excludedDirs = @(".git", "tests", "node_modules")
$excludedFileNames = @(".DS_Store", "wp-config.php")
$excludedExtensions = @(".bak", ".backup", ".tmp")

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    Get-ChildItem -Path $srcRoot -File -Recurse -Force | ForEach-Object {
        $fullPath = $_.FullName
        $relative = $fullPath.Substring($srcRoot.Length)

        while ($relative.StartsWith("\") -or $relative.StartsWith("/")) {
            $relative = $relative.Substring(1)
        }

        $segments = $relative -split "[\\/]+"

        foreach ($segment in $segments) {
            if ($excludedDirs -contains $segment) {
                return
            }
        }

        if ($excludedFileNames -contains $_.Name) {
            return
        }

        if ($_.Name -like ".env" -or $_.Name -like ".env.*") {
            return
        }

        if ($excludedExtensions -contains $_.Extension.ToLowerInvariant()) {
            return
        }

        if ($_.FullName -match "\\secrets\\|/secrets/") {
            return
        }

        $entryName = "lousy-outages/" + ($relative -replace "\\", "/")

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip,
            $fullPath,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $zip.Dispose()
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)

try {
    $entries = $zip.Entries | ForEach-Object { $_.FullName }

    $badBackslashes = $entries | Where-Object { $_.Contains("\") }
    if ($badBackslashes) {
        Write-Host "BAD ZIP: entries contain backslashes:" -ForegroundColor Red
        $badBackslashes
        throw "ZIP validation failed: backslash paths found."
    }

    $badRoot = $entries | Where-Object { -not $_.StartsWith("lousy-outages/") }
    if ($badRoot) {
        Write-Host "BAD ZIP: entries outside lousy-outages/ root:" -ForegroundColor Red
        $badRoot
        throw "ZIP validation failed: bad root folder."
    }

    $required = @(
        "lousy-outages/lousy-outages.php",
        "lousy-outages/includes/ExternalSignals.php",
        "lousy-outages/includes/UserReports.php",
        "lousy-outages/public/shortcode.php"
    )

    $missing = $required | Where-Object { $entries -notcontains $_ }
    if ($missing) {
        Write-Host "BAD ZIP: missing required files:" -ForegroundColor Red
        $missing
        throw "ZIP validation failed: required files missing."
    }
}
finally {
    $zip.Dispose()
}

Write-Host "Built: $zipPath" -ForegroundColor Green
Write-Host "ZIP validation passed: forward-slash paths, correct root, required files present." -ForegroundColor Green
