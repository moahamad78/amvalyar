param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$TestArguments
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$DevDb = Join-Path $Root "database\database.sqlite"

if (-not (Test-Path $DevDb)) {
    throw "Development database not found: $DevDb"
}

$BeforeHash = (Get-FileHash $DevDb -Algorithm SHA256).Hash

# The audit runner creates a brand-new disposable SQLite database, migrates it,
# and loads deterministic compatibility fixtures. Cloning the developer DB made
# test outcomes depend on whichever demo data happened to exist locally.
& "$PSScriptRoot\run-audit.ps1" @TestArguments
$Exit = $LASTEXITCODE

$AfterHash = (Get-FileHash $DevDb -Algorithm SHA256).Hash

if ($BeforeHash -ne $AfterHash) {
    throw "SAFETY FAILURE: development database changed during test execution."
}

Write-Host ""
Write-Host "Development database hash unchanged." -ForegroundColor Green

exit $Exit
