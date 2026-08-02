# One-time setup: GitHub Actions deploy secrets (run on YOUR Windows PC in PowerShell).
# Prerequisites: winget install GitHub.cli   then   gh auth login   (login as suzyeaston)
#
# Usage: cd to your suzyeastonca repo, then:
#   powershell -ExecutionPolicy Bypass -File scripts\setup-github-deploy-secrets-interactive.ps1

$ErrorActionPreference = "Stop"

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    Write-Host "Install GitHub CLI first: winget install GitHub.cli"
    Write-Host "Then run: gh auth login"
    exit 1
}

$auth = gh auth status 2>&1
if ($auth -match "not logged in") {
    Write-Host "Run: gh auth login"
    exit 1
}

Write-Host "Enter SFTP credentials from your hosting panel (same as cPanel SFTP & SSH Access)."
$host = Read-Host "LOUSY_SSH_HOST"
$port = Read-Host "LOUSY_SSH_PORT (e.g. 27)"
$user = Read-Host "LOUSY_SSH_USER"
$pass = Read-Host "LOUSY_SSH_PASSWORD" -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($pass)
$passPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)

$repo = gh repo view --json nameWithOwner -q .nameWithOwner
Write-Host "Setting secrets on $repo ..."

gh secret set LOUSY_SSH_HOST --body $host
gh secret set LOUSY_SSH_PORT --body $port
gh secret set LOUSY_SSH_USER --body $user
gh secret set LOUSY_SSH_PASSWORD --body $passPlain

Write-Host ""
Write-Host "Done. Open GitHub -> Actions -> Deploy to Production -> Run workflow (main, both)."
Write-Host "Or Re-run any failed deploy job."
