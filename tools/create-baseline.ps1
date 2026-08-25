Set-StrictMode -Version Latest
$ErrorActionPreference = "Continue"

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "CREATE CLEAN BASELINE" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

if (-not (Test-Path (Join-Path $Root ".git"))) {
    Write-Host "Git repository not found. Initializing..." -ForegroundColor Yellow
    git init

    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

Write-Host "`nRunning full test before baseline..." -ForegroundColor Yellow
& "$PSScriptRoot\full-test.ps1"

if ($LASTEXITCODE -ne 0) {
    Write-Host "Baseline aborted because tests failed." -ForegroundColor Red
    exit 1
}

Write-Host "`nGit status before staging:" -ForegroundColor Yellow
git status --short

Write-Host ""
Write-Host "Baseline tools are ready." -ForegroundColor Green
Write-Host "No commit was created automatically." -ForegroundColor Green
Write-Host "When ready, run:" -ForegroundColor Yellow
Write-Host 'git add .' -ForegroundColor Cyan
Write-Host 'git commit -m "baseline: stable asset management system"' -ForegroundColor Cyan