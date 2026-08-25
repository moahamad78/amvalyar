Set-StrictMode -Version Latest
$ErrorActionPreference = "Continue"

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "PROJECT AUDIT" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

Write-Host "`n[1] Composer audit" -ForegroundColor Yellow
composer audit
$AuditExit = $LASTEXITCODE

Write-Host "`n[2] PHP lint - app/routes/tests" -ForegroundColor Yellow

$Failures = 0

foreach ($Base in @(
    "$Root\app",
    "$Root\routes",
    "$Root\tests"
)) {
    Get-ChildItem $Base -Recurse -File -Filter *.php |
        ForEach-Object {
            php -l $_.FullName | Out-Null

            if ($LASTEXITCODE -ne 0) {
                Write-Host "LINT FAIL: $($_.FullName)" -ForegroundColor Red
                $Failures++
            }
        }
}

Write-Host "`n[3] Git status" -ForegroundColor Yellow
git status --short

if ($AuditExit -ne 0 -or $Failures -gt 0) {
    Write-Host "`nPROJECT AUDIT: FAIL" -ForegroundColor Red
    exit 1
}

Write-Host "`nPROJECT AUDIT: PASS" -ForegroundColor Green
exit 0