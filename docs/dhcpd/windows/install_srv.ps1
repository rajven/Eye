# Установка службы через nssm (https://nssm.cc)
nssm install "DHCP_Events_Monitor" "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe" "-ExecutionPolicy Bypass -File C:\Scripts\dhcp_monitor.ps1"
nssm start DHCP_Events_Monitor
