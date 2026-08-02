# Push LOUSY_SSH_* from .env.deploy.local to GitHub Actions repository secrets.
# Run in PowerShell from the repo root after: gh auth login
param()

$ErrorActionPreference = "Stop"
$Root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
if (Test-Path (Join-Path $PSScriptRoot "..")) {
    $Root = Resolve-Path (Join-Path $PSScriptRoot "..")
}
$EnvFile = Join-Path $Root ".env.deploy.local"

if (-not (Test-Path $EnvFile)) {
    Write-Error "Missing $EnvFile — copy scripts/env.deploy.example to .env.deploy.local and fill in credentials."
}

Get-Content $EnvFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
        $parts = $line.Split("=", 2)
        $name = $parts[0].Trim()
        $value = $parts[1].Trim()
        Set-Item -Path "env:$name" -Value $value
    }
}

$required = @("LOUSY_SSH_HOST", "LOUSY_SSH_PORT", "LOUSY_SSH_USER", "LOUSY_SSH_PASSWORD")
foreach ($key in $required) {
    if (-not $env:$key) {
        Write-Error "Missing $key in $EnvFile"
    }
}

$repo = gh repo view --json nameWithOwner -q .nameWithOwner
Write-Host "Setting GitHub Actions secrets for $repo..."
gh secret set LOUSY_SSH_HOST --body $env:LOUSY_SSH_HOST
gh secret set LOUSY_SSH_PORT --body $env:LOUSY_SSH_PORT
gh secret set LOUSY_SSH_USER --body $env:LOUSY_SSH_USER
gh secret set LOUSY_SSH_PASSWORD --body $env:LOUSY_SSH_PASSWORD
Write-Host "Done. Re-run Deploy to Production in GitHub Actions."
