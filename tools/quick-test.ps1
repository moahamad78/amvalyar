param(
    [Parameter(Mandatory = $true)]
    [string]$Filter
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Continue"

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "QUICK TEST: $Filter" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

& "$PSScriptRoot\run-tests-isolated.ps1" "--filter=$Filter"
exit $LASTEXITCODE