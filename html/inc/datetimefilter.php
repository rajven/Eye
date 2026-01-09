<?php

if (!defined("CONFIG")) die("Not defined");

$date_shift = getParam('date_shift', $page_url, 'h');
$date_start = getParam('date_start', $page_url, '');
$date_stop = getParam('date_stop', $page_url, '');

// Инициализация переменных
$datetime_start = null;
$datetime_stop = null;
$time_start = null;
$time_stop = null;
$date1 = '';
$date2 = '';

$shift_array = [ 'h', '8h', 'd', 'm' ];

// если время не передано или установлен интервал сдвига - обновить время
if (empty($date_start) || empty($date_stop) || in_array($date_shift, $shift_array)) {
    // Устанавливаем конечную дату = текущее время
    $datetime_stop = new DateTime();
    $date2 = $datetime_stop->format('Y-m-d H:i');
    $time_stop = $datetime_stop->getTimestamp();
    
    // Устанавливаем начальную дату в зависимости от сдвига
    $datetime_start = clone $datetime_stop;
    
    switch ($date_shift) {
        case 'h':
            $datetime_start->modify('-1 hour');
            break;
        case '8h':
            $datetime_start->modify('-8 hours');
            break;
        case 'd':
            $datetime_start->modify('-1 day');
            break;
        case 'm':
            $datetime_start->modify('-1 month');
            break;
        case '-': 
        default:
            $datetime_start->modify('-1 day'); // значение по умолчанию
            break;
    }
    
    $time_start = $datetime_start->getTimestamp();
    $date1 = $datetime_start->format('Y-m-d H:i');
    
} else {
    // Если даты переданы, парсим их
    $datetime_start = GetDateTimeFromString($date_start);
    if ($datetime_start) {
        $date1 = $datetime_start->format('Y-m-d H:i');
        $time_start = $datetime_start->getTimestamp();
    }
    
    $datetime_stop = GetDateTimeFromString($date_stop);
    if ($datetime_stop) {
        $date2 = $datetime_stop->format('Y-m-d H:i');
        $time_stop = $datetime_stop->getTimestamp();
    }
}

// Защита от невалидных дат
if (!$datetime_start || !$datetime_stop) {
    // Устанавливаем значения по умолчанию при ошибке
    $datetime_stop = new DateTime();
    $datetime_start = clone $datetime_stop;
    $datetime_start->modify('-1 day');
    
    $date1 = $datetime_start->format('Y-m-d H:i');
    $date2 = $datetime_stop->format('Y-m-d H:i');
    $time_start = $datetime_start->getTimestamp();
    $time_stop = $datetime_stop->getTimestamp();
}

// Проверяем что начальная дата не позже конечной
if ($time_start > $time_stop) {
    // Меняем даты местами если нужно
    list($time_start, $time_stop) = [$time_stop, $time_start];
    list($date1, $date2) = [$date2, $date1];
    list($datetime_start, $datetime_stop) = [$datetime_stop, $datetime_start];
}

$days_shift = ceil(($time_stop - $time_start) / 86400);

// Сохраняем в сессии
$_SESSION[$page_url]['date_start'] = $date1;
$_SESSION[$page_url]['date_stop'] = $date2;
$_SESSION[$page_url]['date_shift'] = $date_shift;

?>

<script>
function SetDateShiftCustom() {
document.getElementById('date_shift').value='-';
}

function updateDates() {
    const dateShift = document.getElementById('date_shift').value;
    const dateStartInput = document.getElementById('date_start');
    const dateStopInput = document.getElementById('date_stop');
    
    // Если выбран произвольный период - не меняем даты
    if (dateShift === '-') {
        return;
    }
    
    // Получаем текущую дату и время
    const now = new Date();
    
    // Устанавливаем конечную дату = текущее время
    dateStopInput.value = formatDateTimeLocal(now);
    
    // Создаем копию для начальной даты
    const startDate = new Date(now);
    
    // Изменяем начальную дату в зависимости от выбора
    switch (dateShift) {
        case 'h': // Последний час
            startDate.setHours(startDate.getHours() - 1);
            break;
        case '8h': // Последние 8 часов
            startDate.setHours(startDate.getHours() - 8);
            break;
        case 'd': // Последние 24 часа
            startDate.setDate(startDate.getDate() - 1);
            break;
        case 'm': // Последний месяц
            startDate.setMonth(startDate.getMonth() - 1);
            break;
    }

    // Устанавливаем начальную дату
    dateStartInput.value = formatDateTimeLocal(startDate);
}

// Функция для форматирования даты в формат datetime-local
function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Автоматическое обновление при загрузке страницы, если нужно
/*
document.addEventListener('DOMContentLoaded', function() {
    const dateShift = document.getElementById(date_shift).value;
    if (dateShift !== 'custom') {
        updateDates();
    }
});
*/

// Опционально: автоматическая отправка формы при изменении
/*
function updateAndSubmit() {
    updateDates();
    document.getElementById('dateForm').submit();
}
*/
</script>
