param(
    [string]$Name = "snapshot"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Base = "D:\Projects\asset-management-system-backups"

$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$SafeName = ($Name -replace '[^a-zA-Z0-9\-_]', '-')
$Target = Join-Path $Base "$SafeName-$Stamp"

New-Item -ItemType Directory -Force -Path $Target | Out-Null

foreach ($Relative in @(
    "app",
    "resources",
    "routes",
    "tests",
    "database\migrations",
    "composer.json",
    "composer.lock",
    ".env.example",
    ".gitignore"
)) {
    $Source = Join-Path $Root $Relative

    if (-not (Test-Path $Source)) {
        continue
    }

    $Destination = Join-Path $Target $Relative
    $Parent = Split-Path -Parent $Destination

    if ($Parent) {
        New-Item -ItemType Directory -Force -Path $Parent | Out-Null
    }

    Copy-Item $Source $Destination -Recurse -Force
}

Write-Host ""
Write-Host "SNAPSHOT READY" -ForegroundColor Green
Write-Host $Target -ForegroundColor Cyan