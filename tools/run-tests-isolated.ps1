param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$TestArguments
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$DevDb = Join-Path $Root "database\database.sqlite"
$TestDb = Join-Path $Root "database\testing.sqlite"

if (-not (Test-Path $DevDb)) {
    throw "Development database not found: $DevDb"
}

# Every PHPUnit process starts from a disposable clone of the development
# fixture database. PHPUnit never points at database.sqlite directly.
Copy-Item $DevDb $TestDb -Force

$BeforeHash = (Get-FileHash $DevDb -Algorithm SHA256).Hash

$PreviousConnection = $env:DB_CONNECTION
$PreviousDatabase = $env:DB_DATABASE
$PreviousEnvironment = $env:APP_ENV

try {
    $env:APP_ENV = "testing"
    $env:DB_CONNECTION = "sqlite"
    $env:DB_DATABASE = $TestDb

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host "ISOLATED TEST DATABASE" -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host "Development DB: $DevDb"
    Write-Host "Disposable DB : $TestDb"
    Write-Host ""

    # Keep the disposable clone aligned with migrations added on the current
    # branch without ever touching the developer's working database.
    & php artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to migrate the isolated test database."
    }

    & php artisan test @TestArguments
    $Exit = $LASTEXITCODE
}
finally {
    if ($null -eq $PreviousConnection) {
        Remove-Item Env:DB_CONNECTION -ErrorAction SilentlyContinue
    }
    else {
        $env:DB_CONNECTION = $PreviousConnection
    }

    if ($null -eq $PreviousDatabase) {
        Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue
    }
    else {
        $env:DB_DATABASE = $PreviousDatabase
    }

    if ($null -eq $PreviousEnvironment) {
        Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue
    }
    else {
        $env:APP_ENV = $PreviousEnvironment
    }
}

$AfterHash = (Get-FileHash $DevDb -Algorithm SHA256).Hash

if ($BeforeHash -ne $AfterHash) {
    throw "SAFETY FAILURE: development database changed during test execution."
}

Write-Host ""
Write-Host "Development database hash unchanged." -ForegroundColor Green

exit $Exit
