$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
$out = Join-Path $dist 'lousy-outages.zip'
$src = Join-Path $root 'plugins/lousy-outages'
New-Item -ItemType Directory -Force -Path $dist | Out-Null
if (Test-Path $out) { Remove-Item $out -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
$tmp = Join-Path $dist 'lousy-outages'
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
Copy-Item $src $tmp -Recurse
Get-ChildItem $tmp -Recurse -Force | Where-Object {
  $_.FullName -match '\\.git\\|\\tests\\|\\node_modules\\|\\secrets\\|wp-config.php|\\.env|\\.bak$|~$|backup'
} | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
[System.IO.Compression.ZipFile]::CreateFromDirectory($tmp, $out)
Remove-Item $tmp -Recurse -Force
Write-Output "Built: $out"
