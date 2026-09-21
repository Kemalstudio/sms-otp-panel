<#
    Запускает шлюз на Windows: панель, воркер очереди и планировщик.

    Процессы отвязываются от запустившей их консоли (Start-Process), поэтому
    переживают закрытие терминала. Это не замена службам: после перезагрузки
    их нужно запустить снова, а следить за падениями некому. Для постоянной
    работы оформляйте их службами через NSSM — но для отладки и небольшой
    нагрузки этого достаточно.

        powershell -ExecutionPolicy Bypass -File deploy\windows\start-gateway.ps1
#>

param(
    [string]$BindHost = '0.0.0.0',
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$pidFile = Join-Path $root 'storage\app\gateway.pids'
$logDir = Join-Path $root 'storage\logs'

New-Item -ItemType Directory -Force -Path $logDir | Out-Null

# Повторный запуск поверх работающего шлюза даст два воркера на одной очереди
# и занятый порт, поэтому сначала останавливаем прежние процессы.
if (Test-Path $pidFile) {
    Write-Host 'Останавливаю прежние процессы...'
    & (Join-Path $PSScriptRoot 'stop-gateway.ps1')
}

$jobs = @(
    @{ Name = 'panel';     Args = @('artisan', 'serve', "--host=$BindHost", "--port=$Port") },
    @{ Name = 'queue';     Args = @('artisan', 'queue:work', 'redis', '--tries=3', '--backoff=10', '--sleep=3') },
    @{ Name = 'scheduler'; Args = @('artisan', 'schedule:work') }
)

$started = @()

foreach ($job in $jobs) {
    $out = Join-Path $logDir "$($job.Name).out.log"
    $err = Join-Path $logDir "$($job.Name).err.log"

    $process = Start-Process -FilePath 'php' `
        -ArgumentList $job.Args `
        -WorkingDirectory $root `
        -WindowStyle Hidden `
        -RedirectStandardOutput $out `
        -RedirectStandardError $err `
        -PassThru

    $started += [PSCustomObject]@{ Name = $job.Name; Id = $process.Id }
    Write-Host ("  {0,-10} PID {1}" -f $job.Name, $process.Id)
}

$started | ConvertTo-Json -Compress | Set-Content -Path $pidFile -Encoding utf8

Start-Sleep -Seconds 5

try {
    $probe = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/up" -UseBasicParsing -TimeoutSec 10
    Write-Host ("Панель отвечает: HTTP {0}" -f $probe.StatusCode) -ForegroundColor Green
} catch {
    Write-Host 'Панель не ответила — смотрите storage\logs\panel.err.log' -ForegroundColor Red
}
