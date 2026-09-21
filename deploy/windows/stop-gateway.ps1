<#
    Останавливает процессы, запущенные start-gateway.ps1.

        powershell -ExecutionPolicy Bypass -File deploy\windows\stop-gateway.ps1
#>

$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$pidFile = Join-Path $root 'storage\app\gateway.pids'

if (-not (Test-Path $pidFile)) {
    Write-Host 'Файла с PID нет — похоже, шлюз не запускался этим скриптом.'
    return
}

$entries = Get-Content $pidFile -Raw | ConvertFrom-Json

foreach ($entry in @($entries)) {
    $process = Get-Process -Id $entry.Id -ErrorAction SilentlyContinue

    # Проверка имени: за время работы PID мог быть переиспользован системой
    # под чужой процесс, и убивать его нельзя.
    if ($process -and $process.ProcessName -eq 'php') {
        Stop-Process -Id $entry.Id -Force
        Write-Host ("  остановлен {0,-10} PID {1}" -f $entry.Name, $entry.Id)
    } else {
        Write-Host ("  {0,-10} PID {1} уже не работает" -f $entry.Name, $entry.Id)
    }
}

Remove-Item $pidFile -Force
