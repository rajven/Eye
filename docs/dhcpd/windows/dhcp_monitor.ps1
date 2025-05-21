# Параметры API
$ApiHost = "STAT_IP_OR_HOSTNAME"  # Замените на IP/хост API-сервера
$ApiLogin = "LOGIN"               # Логин для API
$ApiKey = "API_CUSTOMER_KEY"      # API-ключ клиента

# Функция отправки данных на API
function Send-DhcpEventToApi {
    param (
        [string]$Mac,
        [string]$Ip,
        [string]$Action,
        [string]$Hostname
    )

    # Формируем URL запроса
    $ApiUrl = "http://$ApiHost/api.php?login=$ApiLogin&api_key=$ApiKey&send=dhcp&mac=$Mac&ip=$Ip&action=$Action&hostname=$Hostname"

    try {
        # Отправляем GET-запрос
        $Response = Invoke-RestMethod -Uri $ApiUrl -Method Get -ErrorAction Stop
        
        # Логируем успешную отправку
        Write-Host "[$(Get-Date)] DHCP Event Sent: MAC=$Mac, IP=$Ip, Action=$Action, Hostname=$Hostname"
        Write-Host "API Response: $($Response | ConvertTo-Json -Compress)"
    }
    catch {
        Write-Host "[ERROR] Failed to send DHCP event: $_" -ForegroundColor Red
    }
}

# Основной цикл: мониторим события DHCP
while ($true) {
    # Получаем последние события DHCP (ID 10 = "Аренда выдана", ID 11 = "Аренда освобождена")
    $Events = Get-WinEvent -LogName "Microsoft-Windows-DHCP-Server/Operational" -MaxEvents 10 -ErrorAction SilentlyContinue |
              Where-Object { $_.Id -eq 10 -or $_.Id -eq 11 }

    foreach ($Event in $Events) {
        # Парсим параметры события
        $Ip = $Event.Properties[0].Value
        $Mac = $Event.Properties[1].Value
        $Hostname = $Event.Properties[2].Value
        $Action = if ($Event.Id -eq 10) { "add" } else { "del" }

        # Отправляем данные на API
        Send-DhcpEventToApi -Mac $Mac -Ip $Ip -Action $Action -Hostname $Hostname
    }

    # Пауза перед следующей проверкой (5 секунд)
    Start-Sleep -Seconds 5
}
