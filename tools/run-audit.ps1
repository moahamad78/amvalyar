param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$TestArguments
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$AuditRoot = Split-Path -Parent $PSScriptRoot
$AuditDirectory = Join-Path $AuditRoot 'storage\framework\testing'
New-Item -ItemType Directory -Path $AuditDirectory -Force | Out-Null
$AuditDatabase = Join-Path $AuditDirectory ('audit-' + [guid]::NewGuid().ToString('N') + '.sqlite')
New-Item -ItemType File -Path $AuditDatabase | Out-Null
$AuditExitCode = 1
$AuditEnvironment = @{}
foreach ($AuditKey in @('APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'APP_CONFIG_CACHE')) {
    $AuditEnvironment[$AuditKey] = [Environment]::GetEnvironmentVariable($AuditKey, 'Process')
}
try {
    $env:APP_ENV = 'testing'
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = $AuditDatabase
    # A cached production configuration must never override the isolated DB.
    $env:APP_CONFIG_CACHE = Join-Path $AuditDirectory 'no-config-cache.php'
    & php artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Audit migrations failed.' }
    & php artisan db:seed --class='Tests\Support\AuditFixtureSeeder' --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Audit fixtures failed.' }
    & php artisan test @TestArguments
    $AuditExitCode = $LASTEXITCODE
}
finally {
    foreach ($AuditKey in $AuditEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($AuditKey, $AuditEnvironment[$AuditKey], 'Process')
    }
    if ($AuditExitCode -eq 0 -and (Test-Path -LiteralPath $AuditDatabase)) {
        Remove-Item -LiteralPath $AuditDatabase -Force
        Write-Host 'Disposable audit database removed after successful tests.'
    }
    else {
        Write-Host "Failed audit database retained for diagnostics: $AuditDatabase"
    }
}
exit $AuditExitCode
