# Параметры API
$ApiUrl = "http://your-api-server.com/api/dhcp/reservations"
$ApiKey = "ваш_api_ключ"
$DhcpServer = "Ваш_DHCP_Сервер"

# Получаем текущие резервирования DHCP
$CurrentReservations = Get-DhcpServerv4Reservation -ComputerName $DhcpServer | 
                       Select-Object IPAddress, ClientId, Name, Description

# Получаем актуальные резервирования из API
$Headers = @{ "Authorization" = "Bearer $ApiKey" }
$ApiReservations = Invoke-RestMethod -Uri $ApiUrl -Method Get -Headers $Headers

# Конвертируем MAC-адреса в единый формат (убираем разделители)
$ApiReservations | ForEach-Object { 
    $_.MAC = $_.MAC -replace '[:-]', ''
}

# Удаляем резервирования, которых нет в API
foreach ($Reservation in $CurrentReservations) {
    $MacFromDhcp = $Reservation.ClientId -replace '[:-]', ''
    $MatchingApiEntry = $ApiReservations | Where-Object { $_.MAC -eq $MacFromDhcp }

    if (-not $MatchingApiEntry) {
        try {
            Remove-DhcpServerv4Reservation -ComputerName $DhcpServer `
                -IPAddress $Reservation.IPAddress `
                -ErrorAction Stop
            Write-Host "Удалено устаревшее резервирование: $($Reservation.IPAddress) ($($Reservation.ClientId))" -ForegroundColor Yellow
        } catch {
            Write-Host "Ошибка при удалении $($Reservation.IPAddress): $_" -ForegroundColor Red
        }
    }
}

# Добавляем/обновляем резервирования из API
foreach ($ApiReservation in $ApiReservations) {
    try {
        # Проверяем, существует ли запись
        $ExistingReservation = $CurrentReservations | Where-Object { 
            ($_.ClientId -replace '[:-]', '') -eq $ApiReservation.MAC
        }

        if ($ExistingReservation) {
            # Обновляем описание (если изменилось)
            Set-DhcpServerv4Reservation -ComputerName $DhcpServer `
                -IPAddress $ExistingReservation.IPAddress `
                -ClientId $ApiReservation.MAC `
                -Name $ApiReservation.Hostname `
                -Description $ApiReservation.Description `
                -ErrorAction Stop
            Write-Host "Обновлено резервирование: $($ExistingReservation.IPAddress)"
        } else {
            # Добавляем новое
            Add-DhcpServerv4Reservation -ComputerName $DhcpServer `
                -IPAddress $ApiReservation.IP `
                -ClientId $ApiReservation.MAC `
                -Name $ApiReservation.Hostname `
                -Description $ApiReservation.Description `
                -ErrorAction Stop
            Write-Host "Добавлено новое резервирование: $($ApiReservation.IP)"
        }
    } catch {
        Write-Host "Ошибка при обработке $($ApiReservation.IP): $_" -ForegroundColor Red
    }
}