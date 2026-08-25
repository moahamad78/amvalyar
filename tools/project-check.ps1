Set-StrictMode -Version Latest
$ErrorActionPreference = "Continue"

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "PROJECT CHECK" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

Write-Host "`n[1] Git status" -ForegroundColor Yellow
git status --short

Write-Host "`n[2] PHP" -ForegroundColor Yellow
php -v | Select-Object -First 2

Write-Host "`n[3] Composer validate" -ForegroundColor Yellow
composer validate --no-check-publish
$ComposerExit = $LASTEXITCODE

Write-Host "`n[4] Laravel about" -ForegroundColor Yellow
php artisan about

Write-Host "`n[5] Route smoke" -ForegroundColor Yellow
php artisan route:list --name=asset-plates

if ($ComposerExit -ne 0) {
    Write-Host "`nPROJECT CHECK: WARNING" -ForegroundColor Yellow
    exit 2
}

Write-Host "`nPROJECT CHECK: PASS" -ForegroundColor Green
exit 0