# TDS School Management Software - starts the local server and opens the browser.
# All school data lives in %APPDATA%\TDS School Data, so updating or reinstalling never touches it.
param(
    [switch]$NoBrowser,
    [string]$DataDir = (Join-Path $env:APPDATA 'TDS School Data')
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Join-Path $root 'php\php.exe'
$app = Join-Path $root 'app'
$phpArgs = @('-c', (Join-Path $root 'php\php.ini'), '-d', ('extension_dir=' + (Join-Path $root 'php\ext')),
             '-d', ('curl.cainfo=' + (Join-Path $root 'php\cacert.pem')), '-d', ('openssl.cafile=' + (Join-Path $root 'php\cacert.pem')))
$log = Join-Path $DataDir 'storage\logs\launcher.log'

function Write-Log($msg) { Add-Content -Path $log -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $msg) }
function Show-Error($msg) {
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show($msg + "`n`nDetails: $log", 'TDS School Management Software', 'OK', 'Error') | Out-Null
}
function Test-OurServer($port) {
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:$port/up" -UseBasicParsing -TimeoutSec 2
        return ($r.StatusCode -eq 200)
    } catch { return $false }
}
function Test-PortFree($port) {
    $c = New-Object System.Net.Sockets.TcpClient
    try { $c.Connect('127.0.0.1', $port); $c.Close(); return $false } catch { return $true }
}
function Invoke-Artisan([string[]]$artisanArgs) {
    $out = & $php @phpArgs (Join-Path $app 'artisan') @artisanArgs 2>&1
    Write-Log ("artisan " + ($artisanArgs -join ' ') + ": " + ($out -join ' | '))
    if ($LASTEXITCODE -ne 0) { throw "artisan $($artisanArgs -join ' ') failed" }
}

try {
    # 1. Data folders
    foreach ($d in @('storage\app\private', 'storage\app\public', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $DataDir $d) | Out-Null
    }

    # 2. Port: reuse a running server, else first free port
    $portFile = Join-Path $DataDir 'port.txt'
    $ports = @(8765..8775)
    if (Test-Path $portFile) { $saved = [int](Get-Content $portFile -Raw); $ports = @($saved) + ($ports | Where-Object { $_ -ne $saved }) }
    foreach ($p in $ports) {
        if (Test-OurServer $p) {
            if (-not $NoBrowser) { Start-Process "http://127.0.0.1:$p/" }
            exit 0
        }
    }
    $port = $ports | Where-Object { Test-PortFree $_ } | Select-Object -First 1
    if (-not $port) { throw 'No free port between 8765 and 8775.' }
    Set-Content -Path $portFile -Value $port

    # 3. Per-computer secret key
    $keyFile = Join-Path $DataDir 'app.key'
    if (-not (Test-Path $keyFile)) {
        $bytes = New-Object byte[] 32
        [System.Security.Cryptography.RNGCryptoServiceProvider]::new().GetBytes($bytes)
        Set-Content -Path $keyFile -Value ('base64:' + [Convert]::ToBase64String($bytes)) -NoNewline
    }

    # 4. First-run date in the registry (trial is not reset by deleting the data folder)
    $reg = 'HKCU:\Software\TDS School Management'
    if (-not (Test-Path $reg)) { New-Item -Path $reg -Force | Out-Null }
    $firstRun = (Get-ItemProperty -Path $reg -Name FirstRun -ErrorAction SilentlyContinue).FirstRun
    if (-not $firstRun) { $firstRun = Get-Date -Format 'yyyy-MM-dd'; Set-ItemProperty -Path $reg -Name FirstRun -Value $firstRun }

    $env:TDS_DATA_DIR = $DataDir
    $env:APP_KEY = (Get-Content $keyFile -Raw).Trim()
    $env:APP_URL = "http://127.0.0.1:$port"
    $env:TDS_FIRST_RUN = $firstRun

    # 5. Database: create / upgrade (safe to run every time)
    $db = Join-Path $DataDir 'database.sqlite'
    if (-not (Test-Path $db)) { New-Item -ItemType File -Path $db | Out-Null }
    Invoke-Artisan @('migrate', '--force')
    $seeded = Join-Path $DataDir 'seeded.flag'
    if (-not (Test-Path $seeded)) { Invoke-Artisan @('db:seed', '--force'); Set-Content -Path $seeded -Value (Get-Date -Format 's') }

    # 6. Start the web server (hidden) and open the browser
    $serverArgs = $phpArgs + @('-S', "127.0.0.1:$port", '-t', (Join-Path $app 'public'), (Join-Path $app 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'))
    Start-Process -FilePath $php -ArgumentList ($serverArgs | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }) `
        -WorkingDirectory (Join-Path $app 'public') -WindowStyle Hidden `
        -RedirectStandardError (Join-Path $DataDir 'storage\logs\server.log') -RedirectStandardOutput (Join-Path $DataDir 'storage\logs\server-out.log')

    for ($i = 0; $i -lt 60; $i++) {
        if (Test-OurServer $port) {
            Write-Log "server started on port $port"
            if (-not $NoBrowser) { Start-Process "http://127.0.0.1:$port/" }
            exit 0
        }
        Start-Sleep -Milliseconds 500
    }
    throw "Server did not start on port $port."
} catch {
    try { Write-Log ("ERROR: " + $_.Exception.Message) } catch {}
    if (-not $NoBrowser) { Show-Error ("TDS School Management Software could not start.`n" + $_.Exception.Message) }
    exit 1
}
