param(
    [switch]$SkipEnvironmentChecks
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Invoke-Gate([string]$Label, [scriptblock]$Command) {
    Write-Host "`n== $Label ==" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "Release gate failed: $Label"
    }
}

Invoke-Gate 'Composer validation' { composer validate --no-check-publish }
Invoke-Gate 'Frontend production build' { npm run build }
$ChangedPhp = @(
    & git diff --name-only --diff-filter=ACMR -- '*.php'
    & git ls-files --others --exclude-standard -- '*.php'
) | Sort-Object -Unique

if ($ChangedPhp.Count -gt 0) {
    Invoke-Gate 'Changed PHP formatting' { vendor\bin\pint --test @ChangedPhp }
}
Invoke-Gate 'Isolated full test suite' { powershell -ExecutionPolicy Bypass -File tools\run-tests-isolated.ps1 }
Invoke-Gate 'Route cache compilation' { php artisan route:cache }
php artisan route:clear

if (-not $SkipEnvironmentChecks) {
    Invoke-Gate 'Production configuration' { php artisan app:production-readiness }
    Invoke-Gate 'Deployment prerequisites' { php artisan app:deployment-readiness }
}

Write-Host "`nRelease preflight passed." -ForegroundColor Green
