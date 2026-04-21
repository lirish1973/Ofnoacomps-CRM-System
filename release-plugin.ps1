# release-plugin.ps1
# Packages a WordPress plugin directly from the source directory,
# updates version strings, updates plugin-updates.json, commits and pushes.
#
# Usage:
#   .\release-plugin.ps1 -Plugin ofnoacomps-crm -Version 1.4.2
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

# UTF-8 without BOM - used for all file writes to avoid WordPress "unexpected output" errors
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

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

# Source directory - always the live source, not an old zip
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
# Step 1: Copy source to temp dir
# ---------------------------------------------------------------
Write-Host "[1/6] Copying source to temp dir..." -ForegroundColor Yellow
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir | Out-Null

$TempPluginDir = Join-Path $TempDir $Plugin
Copy-Item -Path $PluginSrcDir -Destination $TempPluginDir -Recurse -Force

# Remove dev/debug files
$devFiles = @('*.log', 'Thumbs.db', '.DS_Store')
foreach ($pattern in $devFiles) {
    Get-ChildItem -Path $TempPluginDir -Filter $pattern -Recurse -ErrorAction SilentlyContinue |
        Remove-Item -Force
}
Write-Host "   OK: copied $((Get-ChildItem $TempPluginDir -Recurse -File).Count) files" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 2: Update version in TEMP copy (for ZIP)
# ---------------------------------------------------------------
Write-Host "[2/6] Updating version in $($Cfg.MainFile) (temp)..." -ForegroundColor Yellow

$TempMainFile = Join-Path $TempPluginDir $Cfg.MainFile
$Lines        = [System.IO.File]::ReadAllLines($TempMainFile, $Utf8NoBom)
$OldVersion   = '?'
$NewLines     = New-Object System.Collections.Generic.List[string]

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

[System.IO.File]::WriteAllLines($TempMainFile, $NewLines, $Utf8NoBom)
Write-Host "   OK: $OldVersion -> $Version" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 3: Sync version in SOURCE file
# ---------------------------------------------------------------
Write-Host "[3/6] Syncing version in source file..." -ForegroundColor Yellow

$SrcLines  = [System.IO.File]::ReadAllLines($MainFilePath, $Utf8NoBom)
$SyncLines = New-Object System.Collections.Generic.List[string]

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

[System.IO.File]::WriteAllLines($MainFilePath, $SyncLines, $Utf8NoBom)
Write-Host "   OK: source file synced" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 4: Build ZIP from temp dir
# ---------------------------------------------------------------
Write-Host "[4/6] Packing ZIP..." -ForegroundColor Yellow

$NewZipPath = Join-Path $PluginDir $Cfg.NewZipName

# Remove old ZIPs for versioned plugins
if ($Plugin -eq 'smart-cart-recovery') {
    Get-ChildItem -Path $PluginDir -Filter 'smart-cart-recovery-*.zip' -ErrorAction SilentlyContinue |
        ForEach-Object { Remove-Item $_.FullName -Force; Write-Host "   Removed: $($_.Name)" -ForegroundColor Gray }
}

Remove-Item $NewZipPath -Force -ErrorAction SilentlyContinue

$7zExe = @("C:\Program Files\7-Zip\7z.exe","C:\Program Files (x86)\7-Zip\7z.exe") |
         Where-Object { Test-Path $_ } | Select-Object -First 1

if ($7zExe) {
    Start-Process -FilePath $7zExe `
        -ArgumentList "a", "-tzip", "`"$NewZipPath`"", "`"$Plugin\`"" `
        -WorkingDirectory $TempDir `
        -Wait -NoNewWindow
} else {
    # .NET fallback with forward slashes
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipStream = [System.IO.File]::Open($NewZipPath, [System.IO.FileMode]::Create)
    $archive   = [System.IO.Compression.ZipArchive]::new($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
    Get-ChildItem $TempPluginDir -Recurse -File | ForEach-Object {
        $rel   = $_.FullName.Substring($TempDir.Length + 1).Replace('\','/')
        $entry = $archive.CreateEntry($rel)
        $dst   = $entry.Open()
        $src   = [System.IO.File]::OpenRead($_.FullName)
        $src.CopyTo($dst); $src.Dispose(); $dst.Dispose()
    }
    $archive.Dispose(); $zipStream.Dispose()
    Write-Warning "7-Zip not found - used .NET fallback (forward slashes preserved)."
}

$ZipSizeKB = [math]::Round((Get-Item $NewZipPath).Length / 1KB, 1)
Write-Host "   OK: $($Cfg.NewZipName) ($ZipSizeKB KB)" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 5: Update plugin-updates.json (Python - preserves UTF-8/Hebrew)
# ---------------------------------------------------------------
Write-Host "[5/6] Updating plugin-updates.json..." -ForegroundColor Yellow

$PyScript = @"
import json, sys
manifest_path = sys.argv[1]
plugin_key    = sys.argv[2]
new_version   = sys.argv[3]
download_url  = sys.argv[4]
today         = sys.argv[5]

with open(manifest_path, 'r', encoding='utf-8') as f:
    m = json.load(f)

if plugin_key not in m:
    print(f'ERROR: key {plugin_key} not found in manifest', file=sys.stderr)
    sys.exit(1)

m[plugin_key]['version']      = new_version
m[plugin_key]['download_url'] = download_url
m[plugin_key]['last_updated'] = today

with open(manifest_path, 'w', encoding='utf-8') as f:
    json.dump(m, f, ensure_ascii=False, indent=2)

print(f'OK: version={new_version}, last_updated={today}')
"@

$PyScriptPath = Join-Path $RepoRoot "_release_update_manifest.py"
[System.IO.File]::WriteAllText($PyScriptPath, $PyScript, $Utf8NoBom)

$PyExe = @('python', 'python3', 'py') | ForEach-Object {
    $p = Get-Command $_ -ErrorAction SilentlyContinue
    if ($p) { $p.Source }
} | Select-Object -First 1

if ($PyExe) {
    & $PyExe $PyScriptPath $ManifestFile $Plugin $Version $Cfg.DownloadUrl $Today
    if ($LASTEXITCODE -ne 0) { throw "Python manifest update failed" }
} else {
    # Fallback: PowerShell ConvertTo-Json (may escape Hebrew as \uXXXX — still valid JSON)
    Write-Warning "Python not found - using PowerShell fallback (Hebrew stored as \u escapes)"
    $Manifest = Get-Content $ManifestFile -Raw | ConvertFrom-Json
    $Manifest.$Plugin.version      = $Version
    $Manifest.$Plugin.download_url = $Cfg.DownloadUrl
    $Manifest.$Plugin.last_updated = $Today
    $JsonOut = $Manifest | ConvertTo-Json -Depth 5
    [System.IO.File]::WriteAllText($ManifestFile, $JsonOut, $Utf8NoBom)
}

Remove-Item $PyScriptPath -Force -ErrorAction SilentlyContinue
Write-Host "   OK: version=$Version, last_updated=$Today" -ForegroundColor Green

# ---------------------------------------------------------------
# Step 6: git add / commit / auto-stash / pull-rebase / push / pop
# ---------------------------------------------------------------
Write-Host "[6/6] Committing and pushing to GitHub..." -ForegroundColor Yellow

$GitExe = @(
    "C:\Program Files\Git\bin\git.exe",
    "C:\Program Files (x86)\Git\bin\git.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $GitExe) {
    $found = Get-Command git -ErrorAction SilentlyContinue
    if ($found) { $GitExe = $found.Source }
}

if (-not $GitExe) {
    Write-Warning "git not found - skipping commit/push. Push manually."
} else {
    Push-Location $RepoRoot
    $Stashed = $false
    try {
        $RelativeZip  = "wordpress-plugin/$($Cfg.NewZipName)"
        $RelativeMain = "wordpress-plugin/$Plugin/$($Cfg.MainFile)"

        & $GitExe add $RelativeZip
        & $GitExe add plugin-updates.json
        & $GitExe add $RelativeMain
        & $GitExe add "wordpress-plugin/$Plugin/"

        & $GitExe commit -m "release: $Plugin v$Version"

        # --- Auto-stash any unstaged/untracked changes before rebase ---
        $DirtyLines = & $GitExe status --porcelain 2>&1
        if ($DirtyLines) {
            Write-Host "   Auto-stashing local changes before rebase..." -ForegroundColor Gray
            & $GitExe stash push -u -m "pre-release-rebase-$Plugin-$Version"
            $Stashed = $true
            Write-Host "   Stash saved." -ForegroundColor Gray
        }

        # Pull rebase to stay in sync with GitHub Actions or parallel pushes
        & $GitExe pull --rebase origin main

        & $GitExe push origin main
        Write-Host "   OK: pushed to GitHub" -ForegroundColor Green

    } finally {
        # Always restore stashed changes — even if push failed
        if ($Stashed) {
            Write-Host "   Restoring stashed changes..." -ForegroundColor Gray
            & $GitExe stash pop
            Write-Host "   Local changes restored." -ForegroundColor Gray
        }
        Pop-Location
    }
}

# ---------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------
Remove-Item $TempDir -Recurse -Force -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "=============================================" -ForegroundColor Green
Write-Host "  DONE: $Plugin v$Version released!"          -ForegroundColor Green
Write-Host "  Sites will auto-update within 1 hour."     -ForegroundColor Green
Write-Host "  Zip size: $ZipSizeKB KB"                   -ForegroundColor Green
Write-Host "=============================================" -ForegroundColor Green
Write-Host ""
