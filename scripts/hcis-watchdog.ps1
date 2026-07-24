$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
$logDirectory = Join-Path $projectRoot 'storage\logs'
$watchdogLog = Join-Path $logDirectory 'hcis-watchdog.log'
$nginxRoot = Join-Path $projectRoot 'server\nginx-1.31.2'
$nginx = Join-Path $nginxRoot 'nginx.exe'
$nginxConfig = Join-Path $projectRoot 'server\nginx.conf'
$phpCgi = (Get-Command php-cgi.exe -ErrorAction Stop).Source
$phpWorkerPorts = @(9011, 9012, 9013)
$cloudflared = Join-Path $projectRoot 'server\cloudflared.exe'
$cloudflaredConfig = 'C:\Users\USER\.cloudflared\hcis.yml'
$healthUrl = 'http://127.0.0.1:8010/health'
$publicHealthUrl = 'https://hcis.ensys.id/health'
$desiredTunnelConnectors = 2
$healthFailureCount = 0
$publicHealthFailureCount = 0

New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

$createdNew = $false
$mutex = New-Object System.Threading.Mutex($true, 'Local\HCIS-One-Watchdog', [ref] $createdNew)
if (-not $createdNew) {
    exit 0
}

function Write-WatchdogLog([string] $Message) {
    Add-Content -LiteralPath $watchdogLog -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
}

function Test-Port([int] $Port) {
    return $null -ne (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1)
}

function Test-HcisHealth {
    try {
        $response = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 8
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Test-PublicHealth {
    try {
        $response = Invoke-WebRequest -Uri $publicHealthUrl -UseBasicParsing -TimeoutSec 12
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Ensure-PhpWorkers {
    $env:PHP_FCGI_MAX_REQUESTS = '1000'

    foreach ($port in $phpWorkerPorts) {
        if (Test-Port $port) {
            continue
        }

        Start-Process -FilePath $phpCgi `
            -ArgumentList @(
                '-d', 'expose_php=0',
                '-d', 'zend_extension=opcache',
                '-d', 'opcache.enable=1',
                '-d', 'opcache.memory_consumption=128',
                '-d', 'opcache.interned_strings_buffer=16',
                '-d', 'opcache.max_accelerated_files=20000',
                '-d', 'opcache.validate_timestamps=1',
                '-d', 'opcache.revalidate_freq=2',
                '-d', 'opcache.file_cache=C:/Project/hcis/storage/framework/opcache',
                '-d', 'realpath_cache_size=16M',
                '-d', 'realpath_cache_ttl=600',
                '-b', "127.0.0.1:$port"
            ) `
            -WorkingDirectory $projectRoot `
            -WindowStyle Hidden `
            -RedirectStandardOutput (Join-Path $logDirectory "php-cgi-$port.out.log") `
            -RedirectStandardError (Join-Path $logDirectory "php-cgi-$port.err.log")
        Write-WatchdogLog "PHP worker dijalankan pada port $port."
    }
}

function Start-Nginx {
    Start-Process -FilePath $nginx `
        -ArgumentList @('-p', $nginxRoot, '-c', $nginxConfig) `
        -WorkingDirectory $nginxRoot `
        -WindowStyle Hidden
    Write-WatchdogLog 'Nginx HCIS dijalankan pada port 8010.'
}

function Ensure-Nginx {
    if (-not (Test-Port 8010)) {
        Start-Nginx
    }
}

function Restart-HcisStack {
    & $nginx -p $nginxRoot -c $nginxConfig -s quit 2>$null
    Start-Sleep -Seconds 3

    foreach ($port in $phpWorkerPorts) {
        $listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($listener) {
            Stop-Process -Id $listener.OwningProcess -Force -ErrorAction SilentlyContinue
        }
    }

    Start-Sleep -Seconds 2
    Ensure-PhpWorkers
    Start-Sleep -Seconds 2
    Start-Nginx
    Write-WatchdogLog 'Stack HCIS dipulihkan setelah tiga pemeriksaan gagal.'
}

function Ensure-HcisStack {
    Ensure-PhpWorkers
    Ensure-Nginx

    if (Test-HcisHealth) {
        $script:healthFailureCount = 0
        return
    }

    $script:healthFailureCount++
    Write-WatchdogLog "Health check gagal ($script:healthFailureCount/3)."

    if ($script:healthFailureCount -ge 3) {
        $script:healthFailureCount = 0
        Restart-HcisStack
    }
}

function Get-HcisTunnelProcess {
    return Get-CimInstance Win32_Process -Filter "Name = 'cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like '*hcis.yml*' }
}

function Start-HcisTunnel([int] $Instance = 1) {
    Start-Process -FilePath $cloudflared `
        -ArgumentList @('tunnel', '--config', $cloudflaredConfig, 'run') `
        -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDirectory "cloudflared-$Instance.out.log") `
        -RedirectStandardError (Join-Path $logDirectory "cloudflared-$Instance.err.log")
    Write-WatchdogLog "Konektor tunnel HCIS #$Instance dijalankan."
}

function Restart-HcisTunnel {
    $tunnels = @(Get-HcisTunnelProcess)
    foreach ($tunnel in $tunnels) {
        Stop-Process -Id $tunnel.ProcessId -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
    for ($instance = 1; $instance -le $desiredTunnelConnectors; $instance++) {
        Start-HcisTunnel $instance
        Start-Sleep -Seconds 1
    }
}

function Ensure-HcisTunnel {
    $tunnels = @(Get-HcisTunnelProcess)

    if ($tunnels.Count -lt $desiredTunnelConnectors -and (Test-Path -LiteralPath $cloudflared) -and (Test-Path -LiteralPath $cloudflaredConfig)) {
        for ($instance = $tunnels.Count + 1; $instance -le $desiredTunnelConnectors; $instance++) {
            Start-HcisTunnel $instance
            Start-Sleep -Seconds 1
        }
        $script:publicHealthFailureCount = 0
        return
    }

    if (Test-PublicHealth) {
        $script:publicHealthFailureCount = 0
        return
    }

    $script:publicHealthFailureCount++
    Write-WatchdogLog "Health check publik gagal ($script:publicHealthFailureCount/2)."

    if ($script:publicHealthFailureCount -ge 2) {
        $script:publicHealthFailureCount = 0
        Restart-HcisTunnel
        Write-WatchdogLog 'Tunnel HCIS dipulihkan karena akses publik gagal dua kali.'
    }
}

Write-WatchdogLog 'Watchdog production HCIS aktif.'

try {
    while ($true) {
        Ensure-HcisStack
        Ensure-HcisTunnel
        Start-Sleep -Seconds 20
    }
} finally {
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}
