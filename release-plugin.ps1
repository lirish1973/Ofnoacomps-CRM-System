# release-plugin.ps1
# Packages a WordPress plugin directly from the source directory,
# updates version strings, updates plugin-updates.json, commits and pushes.
#
# Usage:
#   .\release-plugin.ps1 -Plugin ofnoacomps-crm -Version 1.3.1
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
    'ofnoacomps-crm' = @{
        MainFile     = 'ofnoacomps-crm.php'
        VersionConst = 'OFNOACOMPS_CRM_VERSION'
        NewZipName   = 'ofnoacomps-crm.zip'
        DownloadUrl  = "$RawBase/ofnoacomps-crm.zip"
    }
    'smart-cart-recovery' = @{
        MainFile     = 'smart-cart-recovery.php'
        VersionConst = 'SCR_VERSION'
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

# Source directory — always the live source, not an old zip
$PluginSrcDir = Join-Path $PluginDir $Plugin
if (-not (Test-Path $PluginSrcDir)) {
    Write-Error "Plugin source directory not found: $PluginSrcDir"
    exit 1
}
$MainFilePath = Join-Path $PluginSrcDir $Cfg.MainFile
if (-not (Test-Path $MainFilePath)) {
    Write-Error "Main plugin file not found: $MainFilePath"
    exit 1
}

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Release: $Plugin  ->  v$Version"           -ForegroundColor Cyan
Write-Host "  Source:  $PluginSrcDir"                     -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan

# ---------------------------------------------------------------
# Step 1: Copy source to temp dir so we don't modify working files
# ---------------------------------------------------------------
Write-Host "[1/5] Copying source to temp dir..." -ForegroundColor Yellow
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir | Out-Null

$TempPluginDir = Join-Path $TempDir $Plugin
Copy-Item -Path $PluginSrcDir -Destination $TempPluginDir -Recurse -Force

# Remove any dev/debug files that should not ship
$devFiles = @('*.log', 'Thumbs.db', '.DS_Store')
foreach ($pattern in $devFiles) {
    Get-ChildItem -Path $TempPluginDir -Filter $pattern -Recurse -ErrorAction SilentlyContinue |
        Remove-Item -Force
}

Write-Host "   OK: copied $((Get-ChildItem $TempPluginDir -Recurse -File).Count) files" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 2: Update version strings in the copied main PHP file
# ---------------------------------------------------------------
Write-Host "[2/5] Updating version in $($Cfg.MainFile)..." -ForegroundColor Yellow

$TempMainFile = Join-Path $TempPluginDir $Cfg.MainFile
$Lines     = [System.IO.File]::ReadAllLines($TempMainFile, [System.Text.Encoding]::UTF8)
$OldVersion = '?'
$NewLines   = New-Object System.Collections.Generic.List[string]

foreach ($Line in $Lines) {
    if ($OldVersion -eq '?' -and $Line -match '^\s*\*\s+Version:\s+([\d.]+)') {
        $OldVersion = $Matches[1]
    }
    if ($Line -match '(^\s*\*\s+Version:\s+)[\d.]+') {
        $Line = $Line -replace '(Version:\s+)[\d.]+', "Version:     $Version"
    }
    $const = $Cfg.VersionConst
    if ($Line -match "define\s*\(\s*['""]$const['""]") {
        $Line = $Line -replace "(define\s*\(\s*['""]$const['""]\s*,\s*['""])[\d.]+(.*)", "`${1}$Version`${2}"
    }
    $NewLines.Add($Line)
}

[System.IO.File]::WriteAllLines($TempMainFile, $NewLines, [System.Text.Encoding]::UTF8)
Write-Host "   OK: $OldVersion -> $Version" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 3: Also update version in SOURCE main file (keep in sync)
# ---------------------------------------------------------------
Write-Host "[3/5] Syncing version in source file..." -ForegroundColor Yellow

$SrcLines   = [System.IO.File]::ReadAllLines($MainFilePath, [System.Text.Encoding]::UTF8)
$SyncLines  = New-Object System.Collections.Generic.List[string]

foreach ($Line in $SrcLines) {
    if ($Line -match '(^\s*\*\s+Version:\s+)[\d.]+') {
        $Line = $Line -replace '(Version:\s+)[\d.]+', "Version:     $Version"
    }
    $const = $Cfg.VersionConst
    if ($Line -match "define\s*\(\s*['""]$const['""]") {
        $Line = $Line -replace "(define\s*\(\s*['""]$const['""]\s*,\s*['""])[\d.]+(.*)", "`${1}$Version`${2}"
    }
    $SyncLines.Add($Line)
}

[System.IO.File]::WriteAllLines($MainFilePath, $SyncLines, [System.Text.Encoding]::UTF8)
Write-Host "   OK: source file synced" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 4: Pack zip from temp dir
# ---------------------------------------------------------------
Write-Host "[4/5] Packing zip..." -ForegroundColor Yellow

$NewZipPath = Join-Path $PluginDir $Cfg.NewZipName

# Remove old zip(s) for plugins that version their zip filename
if ($Plugin -eq 'smart-cart-recovery') {
    Get-ChildItem -Path $PluginDir -Filter 'smart-cart-recovery-*.zip' -ErrorAction SilentlyContinue |
        ForEach-Object {
            Remove-Item $_.FullName -Force
            Write-Host "   Removed old zip: $($_.Name)" -ForegroundColor Gray
        }
}

# Use 7-Zip for proper forward-slash ZIP entries (Linux compatible)
$7zExe = @("C:\Program Files\7-Zip\7z.exe","C:\Program Files (x86)\7-Zip\7z.exe") |
         Where-Object { Test-Path $_ } | Select-Object -First 1

Remove-Item $NewZipPath -Force -ErrorAction SilentlyContinue
if ($7zExe) {
    # 7-Zip creates proper forward-slash ZIP entries (Linux compatible)
    Start-Process -FilePath $7zExe `
        -ArgumentList "a", "-tzip", "`"$NewZipPath`"", "`"$Plugin\`"" `
        -WorkingDirectory $TempDir `
        -Wait -NoNewWindow
} else {
    # Fallback: .NET ZipArchive with forward slashes
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipStream = [System.IO.File]::Open($NewZipPath, [System.IO.FileMode]::Create)
    $archive   = [System.IO.Compression.ZipArchive]::new($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
    Get-ChildItem $TempPluginDir -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($TempDir.Length + 1).Replace('\','/')
        $entry = $archive.CreateEntry($rel)
        $dst   = $entry.Open()
        $src   = [System.IO.File]::OpenRead($_.FullName)
        $src.CopyTo($dst); $src.Dispose(); $dst.Dispose()
    }
    $archive.Dispose(); $zipStream.Dispose()
    Write-Warning "7-Zip not found — used .NET fallback (forward slashes preserved)."
}
$ZipSize = [math]::Round((Get-Item $NewZipPath).Length / 1KB, 1)
Write-Host "   OK: $($Cfg.NewZipName) ($ZipSize KB)" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 5: Update plugin-updates.json
# ---------------------------------------------------------------
Write-Host "[5/5] Updating plugin-updates.json..." -ForegroundColor Yellow

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
Write-Host "   OK: version=$Version, last_updated=$Today" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 6: git add / commit / push
# ---------------------------------------------------------------
Write-Host "[6/6] Committing and pushing to GitHub..." -ForegroundColor Yellow

Push-Location $RepoRoot
try {
    $RelativeZip    = "wordpress-plugin/$($Cfg.NewZipName)"
    $RelativeMain   = "wordpress-plugin/$Plugin/$($Cfg.MainFile)"
    git add $RelativeZip
    git add plugin-updates.json
    git add $RelativeMain
    $CommitMsg = "release: $Plugin v$Version"
    git commit -m $CommitMsg
    git push origin main
    Write-Host "   OK: pushed to GitHub" -ForegroundColor Green
} finally {
    Pop-Location
}

# ---------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------
Remove-Item $TempDir -Recurse -Force

Write-Host ""
Write-Host "=============================================" -ForegroundColor Green
Write-Host "  DONE: $Plugin v$Version released!"          -ForegroundColor Green
Write-Host "  Sites will auto-update within 12 hours."   -ForegroundColor Green
Write-Host "  Zip size: $ZipSize KB"                      -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Green
Write-Host ""
