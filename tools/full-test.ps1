Set-StrictMode -Version Latest
$ErrorActionPreference = "Continue"

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "FULL TEST" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

php artisan view:cache
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

& "$PSScriptRoot\run-tests-isolated.ps1"
exit $LASTEXITCODE