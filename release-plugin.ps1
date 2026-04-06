# =============================================================================
# release-plugin.ps1
# =============================================================================
# שחרור גרסה אוטומטי לפלאגינים של Ofnoacomps-CRM-System
#
# שימוש:
#   .\release-plugin.ps1 -Plugin hoco-crm -Version 1.0.1
#   .\release-plugin.ps1 -Plugin smart-cart-recovery -Version 1.2.0
#
# מה הסקריפט עושה אוטומטית:
#   1. מחלץ את ה-zip הנוכחי
#   2. מעדכן את הגרסה בקובץ הראשי (header + constant)
#   3. אורז מחדש ל-zip חדש
#   4. מעדכן plugin-updates.json
#   5. Commit + push לגיט
# =============================================================================

param(
    [Parameter(Mandatory)][string]$Plugin,
    [Parameter(Mandatory)][string]$Version
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ── הגדרות ───────────────────────────────────────────────────────────────────
$RepoRoot    = $PSScriptRoot
$PluginDir   = Join-Path $RepoRoot 'wordpress-plugin'
$ManifestFile= Join-Path $RepoRoot 'plugin-updates.json'
$TempDir     = Join-Path $RepoRoot "_release_tmp_$Plugin"
$RawBase     = 'https://github.com/lirish1973/Ofnoacomps-CRM-System/raw/main/wordpress-plugin'
$Today       = (Get-Date -Format 'yyyy-MM-dd')

# ── מפת פלאגינים ─────────────────────────────────────────────────────────────
$PluginMap = @{
    'hoco-crm' = @{
        MainFile    = 'hoco-crm.php'
        VersionConst= 'HOCO_CRM_VERSION'
        OldZipGlob  = 'hoco-crm.zip'
        NewZipName  = 'hoco-crm.zip'          # תמיד אותו שם
        DownloadUrl = "$RawBase/hoco-crm.zip"
    }
    'smart-cart-recovery' = @{
        MainFile    = 'smart-cart-recovery.php'
        VersionConst= 'SCR_VERSION'
        OldZipGlob  = 'smart-cart-recovery-*.zip'
        NewZipName  = "smart-cart-recovery-v$Version.zip"
        DownloadUrl = "$RawBase/smart-cart-recovery-v$Version.zip"
    }
}

# ── אימות ────────────────────────────────────────────────────────────────────
if (-not $PluginMap.ContainsKey($Plugin)) {
    Write-Error "פלאגין לא מוכר: '$Plugin'. אפשרויות: $($PluginMap.Keys -join ', ')"
    exit 1
}

if ($Version -notmatch '^\d+\.\d+(\.\d+)?$') {
    Write-Error "פורמט גרסה לא תקין: '$Version'. דוגמה: 1.0.1 או 2.0.0"
    exit 1
}

$Cfg = $PluginMap[$Plugin]

# מצא את ה-zip הנוכחי
$OldZips = Get-ChildItem -Path $PluginDir -Filter $Cfg.OldZipGlob
if ($OldZips.Count -eq 0) {
    Write-Error "לא נמצא zip עבור '$Plugin' ב-$PluginDir"
    exit 1
}
$OldZip = $OldZips | Sort-Object LastWriteTime -Descending | Select-Object -First 1

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "  Release: $Plugin  →  v$Version" -ForegroundColor Cyan
Write-Host "  Zip נוכחי: $($OldZip.Name)" -ForegroundColor Cyan
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan

# ── שלב 1: חילוץ ─────────────────────────────────────────────────────────────
Write-Host "[1/5] מחלץ zip..." -ForegroundColor Yellow
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
Expand-Archive -Path $OldZip.FullName -DestinationPath $TempDir -Force

$PluginSrcDir = Join-Path $TempDir $Plugin
if (-not (Test-Path $PluginSrcDir)) {
    # אולי יש תיקייה בשם שונה — קח את הראשונה
    $PluginSrcDir = (Get-ChildItem $TempDir -Directory | Select-Object -First 1).FullName
}

$MainFilePath = Join-Path $PluginSrcDir $Cfg.MainFile
if (-not (Test-Path $MainFilePath)) {
    Write-Error "קובץ ראשי לא נמצא: $MainFilePath"
    Remove-Item $TempDir -Recurse -Force
    exit 1
}

# ── שלב 2: עדכון גרסה בקוד ──────────────────────────────────────────────────
Write-Host "[2/5] מעדכן גרסה ב-$($Cfg.MainFile)..." -ForegroundColor Yellow
$Content = Get-Content $MainFilePath -Raw -Encoding UTF8

# גלה גרסה נוכחית (מהקונסטנט)
if ($Content -match "define\(\s*'$($Cfg.VersionConst)'\s*,\s*'([^']+)'\s*\)") {
    $OldVersion = $Matches[1]
} elseif ($Content -match "define\(\s*`"$($Cfg.VersionConst)`"\s*,\s*`"([^`"]+)`"\s*\)") {
    $OldVersion = $Matches[1]
} else {
    $OldVersion = '?'
}

Write-Host "   גרסה נוכחית: $OldVersion  →  $Version" -ForegroundColor Gray

# עדכן header: "Version:     x.x.x"
$Content = $Content -replace '(\*\s*Version:\s+)[\d.]+', "`${1}$Version"

# עדכן constant (בגרשיים יחידות)
$Content = $Content -replace "(define\(\s*'$($Cfg.VersionConst)'\s*,\s*')\d[\d.]*(')", "`${1}$Version`${2}"

# עדכן constant (בגרשיים כפולות)
$Content = $Content -replace "(define\(\s*`"$($Cfg.VersionConst)`"\s*,\s*`")\d[\d.]*(`")", "`${1}$Version`${2}"

Set-Content -Path $MainFilePath -Value $Content -Encoding UTF8 -NoNewline

Write-Host "   ✓ גרסה עודכנה" -ForegroundColor Green

# ── שלב 3: ארוז מחדש ────────────────────────────────────────────────────────
Write-Host "[3/5] אורז zip חדש..." -ForegroundColor Yellow
$NewZipPath = Join-Path $PluginDir $Cfg.NewZipName

# אם שם הזיפ משתנה לפי גרסה (smart-cart-recovery) — מחק ישן
if ($Cfg.OldZipGlob -ne $Cfg.NewZipName) {
    $OldZips | ForEach-Object { Remove-Item $_.FullName -Force }
    Write-Host "   הוסר zip ישן: $($OldZips.Name -join ', ')" -ForegroundColor Gray
}

Compress-Archive -Path $PluginSrcDir -DestinationPath $NewZipPath -Force
Write-Host "   ✓ $($Cfg.NewZipName)" -ForegroundColor Green

# ── שלב 4: עדכון plugin-updates.json ────────────────────────────────────────
Write-Host "[4/5] מעדכן plugin-updates.json..." -ForegroundColor Yellow
$Manifest = Get-Content $ManifestFile -Raw | ConvertFrom-Json

if (-not $Manifest.PSObject.Properties.Name.Contains($Plugin)) {
    Write-Error "מפתח '$Plugin' חסר ב-plugin-updates.json"
    Remove-Item $TempDir -Recurse -Force
    exit 1
}

$Manifest.$Plugin.version      = $Version
$Manifest.$Plugin.download_url = $Cfg.DownloadUrl
$Manifest.$Plugin.last_updated = $Today

# שמור עם עיצוב נקי
$Manifest | ConvertTo-Json -Depth 5 | Set-Content $ManifestFile -Encoding UTF8
Write-Host "   ✓ version=$Version, download_url עודכן" -ForegroundColor Green

# ── שלב 5: Commit + Push ────────────────────────────────────────────────────
Write-Host "[5/5] Commit + Push לגיט..." -ForegroundColor Yellow
$CommitMsg = "release: $Plugin v$Version"

Push-Location $RepoRoot
try {
    git add "wordpress-plugin/$($Cfg.NewZipName)"
    # אם שם הזיפ השתנה, הוסף את הישן ל-index (יוסר)
    if ($Cfg.OldZipGlob -ne $Cfg.NewZipName) {
        git add "wordpress-plugin/"
    }
    git add plugin-updates.json
    git commit -m $CommitMsg
    git push origin main
    Write-Host "   ✓ Push הצליח" -ForegroundColor Green
} finally {
    Pop-Location
}

# ── ניקוי ────────────────────────────────────────────────────────────────────
Remove-Item $TempDir -Recurse -Force

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Green
Write-Host "  ✅ Release הושלם: $Plugin v$Version" -ForegroundColor Green
Write-Host "  האתרים יקבלו עדכון אוטומטי תוך עד 12 שעות." -ForegroundColor Green
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Green
Write-Host ""
