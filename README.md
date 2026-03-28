# Eye

A personal project that has grown over the last 18 years. Sharing it here — maybe it will inspire someone to build something of their own, properly).

## 📋 Overview

Eye is a comprehensive network monitoring and management system that provides:

- Internet access management for IP addresses via MikroTik/Linux routers (configurable filtering, daily and monthly traffic limits).
- Access speed limiting (implemented on MikroTik; Linux functionality previously existed but was removed).
- Configuration generation for DHCP servers (dnsmasq, MikroTik).
- Configuration generation for DNS servers (BIND).
- SNMP polling of switches and routers with subsequent analysis and identification of IP address connection ports.
- Monitoring and management of network devices.
- Traffic analysis and bandwidth control.
- Collection and analysis of Syslog messages.
- Real-time statistics and reporting.

---

# Eye Monitoring System — Installation Guide

### System Requirements

#### Supported Distributions:

* ALT Linux 11.1+
* Debian 11+
* Ubuntu 20.04+

---

## 🚀 Quick Installation via Script

For automated installation/updates, use the installation script:

```bash
# Make the script executable
chmod +x install-eye.sh

# Run installation/update
sudo ./install-eye.sh
```

### Script Features

* Support for ALT Linux, Debian, and Ubuntu
* Support for two database systems: MySQL/MariaDB or PostgreSQL (experimental, not for production!)
* Multilingual interface: English or Russian
* Automatic dependency installation
* Configuration file setup
* Database initialization

---

## 🌐 Web Interface Access

* URL: `http://your-server-ip/`
* Admin panel: `http://your-server-ip/admin/`
* Login: `admin`
* Password: `admin`

---

## 🔐 Security Recommendations

* **CHANGE the admin password IMMEDIATELY**
* Generate a new API key
* Restrict access using a firewall
* Use HTTPS
* Perform regular updates and backups

---

## Important!

Do not modify system scripts! If you need to change something, create a copy of the script and work with that. Otherwise, your changes will be overwritten during updates.

---
