# Око

Обычный быдло-кодинг, разросшийся за последние 13 лет. Выкладываю сюда - может кого-то сподвигнет сделать что-то своё нормально).

Предназначен для контроля доступа юзеров в интернет на оборудовании микротик или linux-сервере.
Возможности:
- Управляет выходом в интернет для ip-адресов. Настраивается фильтрация, объём трафика в сутки, месяц.
- Ограничивать скорость (только на микротике, функционал на линухе был, но давно вырезан)
- генерит конфиги для dhcp-серверов (dnsmasq, mikrotik)
- генерит конфиг для named
- опрашивает свичи и роутеры по snmp после чего анализирует и находит порты подключения ip-адресов
- ну и ещё по мелочи...
