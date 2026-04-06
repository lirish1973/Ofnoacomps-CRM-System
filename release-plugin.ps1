# release-plugin.ps1
# Usage:
#   .\release-plugin.ps1 -Plugin hoco-crm -Version 1.0.1
#   .\release-plugin.ps1 -Plugin smart-cart-recovery -Version 1.2.0

param(
    [Parameter(Mandatory)][string]$Plugin,
    [Parameter(Mandatory)][string]$Version
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# --- Config ---
$RepoRoot     = $PSScriptRoot
$PluginDir    = Join-Path $RepoRoot 'wordpress-plugin'
$ManifestFile = Join-Path $RepoRoot 'plugin-updates.json'
$TempDir      = Join-Path $RepoRoot "_release_tmp"
$RawBase      = 'https://github.com/lirish1973/Ofnoacomps-CRM-System/raw/main/wordpress-plugin'
$Today        = (Get-Date -Format 'yyyy-MM-dd')

# --- Plugin map ---
$PluginMap = @{
    'hoco-crm' = @{
        MainFile     = 'hoco-crm.php'
        VersionConst = 'HOCO_CRM_VERSION'
        ZipGlob      = 'hoco-crm.zip'
        NewZipName   = 'hoco-crm.zip'
        DownloadUrl  = "$RawBase/hoco-crm.zip"
    }
    'smart-cart-recovery' = @{
        MainFile     = 'smart-cart-recovery.php'
        VersionConst = 'SCR_VERSION'
        ZipGlob      = 'smart-cart-recovery-*.zip'
        NewZipName   = "smart-cart-recovery-v$Version.zip"
        DownloadUrl  = "$RawBase/smart-cart-recovery-v$Version.zip"
    }
}

# --- Validate inputs ---
if (-not $PluginMap.ContainsKey($Plugin)) {
    Write-Error "Unknown plugin: '$Plugin'. Options: $($PluginMap.Keys -join ', ')"
    exit 1
}
if ($Version -notmatch '^\d+\.\d+(\.\d+)?$') {
    Write-Error "Invalid version format: '$Version'. Example: 1.0.1 or 2.0.0"
    exit 1
}

$Cfg = $PluginMap[$Plugin]

# Find current zip
$OldZips = @(Get-ChildItem -Path $PluginDir -Filter $Cfg.ZipGlob -ErrorAction SilentlyContinue)
if ($OldZips.Count -eq 0) {
    Write-Error "No zip found for '$Plugin' in $PluginDir"
    exit 1
}
$OldZip = $OldZips | Sort-Object LastWriteTime -Descending | Select-Object -First 1

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Release: $Plugin  ->  v$Version" -ForegroundColor Cyan
Write-Host "  Current zip: $($OldZip.Name)" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan

# ---------------------------------------------------------------
# Step 1: Extract
# ---------------------------------------------------------------
Write-Host "[1/5] Extracting zip..." -ForegroundColor Yellow
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
Expand-Archive -Path $OldZip.FullName -DestinationPath $TempDir -Force

# Locate the plugin subfolder inside the extracted dir
$PluginSrcDir = Join-Path $TempDir $Plugin
if (-not (Test-Path $PluginSrcDir)) {
    $PluginSrcDir = (Get-ChildItem $TempDir -Directory | Select-Object -First 1).FullName
}
$MainFilePath = Join-Path $PluginSrcDir $Cfg.MainFile
if (-not (Test-Path $MainFilePath)) {
    Remove-Item $TempDir -Recurse -Force
    Write-Error "Main file not found: $MainFilePath"
    exit 1
}

# ---------------------------------------------------------------
# Step 2: Update version strings in the main PHP file
# ---------------------------------------------------------------
Write-Host "[2/5] Updating version in $($Cfg.MainFile)..." -ForegroundColor Yellow

$Lines = [System.IO.File]::ReadAllLines($MainFilePath, [System.Text.Encoding]::UTF8)
$OldVersion = '?'
$NewLines   = New-Object System.Collections.Generic.List[string]

foreach ($Line in $Lines) {

    # Detect current version from plugin header (first match wins)
    if ($OldVersion -eq '?' -and $Line -match '^\s*\*\s+Version:\s+([\d.]+)') {
        $OldVersion = $Matches[1]
    }

    # Replace header:   * Version:     1.0.0
    if ($Line -match '(^\s*\*\s+Version:\s+)[\d.]+') {
        $Line = $Line -replace '(Version:\s+)[\d.]+', "Version:     $Version"
    }

    # Replace constant: define( 'HOCO_CRM_VERSION', '1.0.0' );
    # or:               define('SCR_VERSION','1.1.0');
    $const = $Cfg.VersionConst
    if ($Line -match "define\s*\(\s*['""]$const['""]") {
        # Replace only the version value at the end: , '1.0.0' ) or ,"1.0.0")
        $Line = $Line -replace "(define\s*\(\s*['""]$const['""]\s*,\s*['""])[\d.]+(.*)", "`${1}$Version`${2}"
    }

    $NewLines.Add($Line)
}

[System.IO.File]::WriteAllLines($MainFilePath, $NewLines, [System.Text.Encoding]::UTF8)
Write-Host "   OK: $OldVersion -> $Version" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 3: Repack zip
# ---------------------------------------------------------------
Write-Host "[3/5] Packing new zip..." -ForegroundColor Yellow

$NewZipPath = Join-Path $PluginDir $Cfg.NewZipName

# Remove old zip(s) if the filename changes per version (smart-cart-recovery)
if ($Cfg.ZipGlob -ne $Cfg.NewZipName) {
    $OldZips | ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Host "   Removed old zip: $($_.Name)" -ForegroundColor Gray
    }
}

Compress-Archive -Path $PluginSrcDir -DestinationPath $NewZipPath -Force
Write-Host "   OK: $($Cfg.NewZipName)" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 4: Update plugin-updates.json
# ---------------------------------------------------------------
Write-Host "[4/5] Updating plugin-updates.json..." -ForegroundColor Yellow

$Manifest = Get-Content $ManifestFile -Raw | ConvertFrom-Json
if (-not ($Manifest.PSObject.Properties.Name -contains $Plugin)) {
    Remove-Item $TempDir -Recurse -Force
    Write-Error "Key '$Plugin' not found in plugin-updates.json"
    exit 1
}

$Manifest.$Plugin.version      = $Version
$Manifest.$Plugin.download_url = $Cfg.DownloadUrl
$Manifest.$Plugin.last_updated = $Today

$JsonOut = $Manifest | ConvertTo-Json -Depth 5
[System.IO.File]::WriteAllText($ManifestFile, $JsonOut, [System.Text.Encoding]::UTF8)
Write-Host "   OK: version=$Version" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 5: git add / commit / push
# ---------------------------------------------------------------
Write-Host "[5/5] Committing and pushing to GitHub..." -ForegroundColor Yellow

Push-Location $RepoRoot
try {
    $RelativeZip = "wordpress-plugin/$($Cfg.NewZipName)"
    git add $RelativeZip
    git add plugin-updates.json
    $CommitMsg = "release: $Plugin v$Version"
    git commit -m $CommitMsg
    git push origin main
    Write-Host "   OK: pushed" -ForegroundColor Green
} finally {
    Pop-Location
}

# ---------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------
Remove-Item $TempDir -Recurse -Force

Write-Host ""
Write-Host "=============================================" -ForegroundColor Green
Write-Host "  DONE: $Plugin v$Version released!" -ForegroundColor Green
Write-Host "  Sites will auto-update within 12 hours." -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Green
Write-Host ""
