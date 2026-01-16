#!/bin/bash
# Eye Installation Script for ALT Linux/Debian/Ubuntu with PostgreSQL support
# Version: 2.1

# set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Output functions
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}=== $1 ===${NC}"
}

# Check for root privileges
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        print_error "Use: sudo $0"
        exit 1
    fi
}

# Detect distribution and package manager
detect_distro() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS_ID=$ID
        OS_VERSION=$VERSION_ID
        OS_NAME=$NAME

        case $OS_ID in
            altlinux)
                PACKAGE_MANAGER="apt-get"
                SERVICE_MANAGER="systemctl"
                OS_FAMILY="alt"
                print_info "Detected ALT Linux $OS_VERSION"
                ;;
            debian)
                PACKAGE_MANAGER="apt"
                SERVICE_MANAGER="systemctl"
                OS_FAMILY="debian"
                print_info "Detected Debian $OS_VERSION"
                ;;
            ubuntu)
                PACKAGE_MANAGER="apt"
                SERVICE_MANAGER="systemctl"
                OS_FAMILY="debian"
                print_info "Detected Ubuntu $OS_VERSION"
                ;;
            *)
                print_error "Unsupported distribution: $OS_ID"
                print_error "Supported: ALT Linux, Debian, Ubuntu"
                exit 1
                ;;
        esac
    else
        print_error "Failed to detect distribution"
        exit 1
    fi
}

select_language() {
    print_step "Select Installation Language"

    echo "Available languages:"
    echo "1) English"
    echo "2) Russian (default)"
    echo ""

    while true; do
        read -p "Select language (1 or 2) [2]: " lang_choice
        
        # Если пустой ввод - по умолчанию английский
        if [[ -z "$lang_choice" ]]; then
            lang_choice="2"
        fi
        
        # Обработка ввода (приводим к нижнему регистру)
        lang_choice_lower=$(echo "$lang_choice" | tr '[:upper:]' '[:lower:]')
        
        case $lang_choice_lower in
            1|english|en|eng|анг|английский)
                EYE_LANG="english"
                EYE_LANG_SHORT="en"
                print_info "Selected English language"
                break
                ;;
            2|russian|ru|rus|ру|русский)
                EYE_LANG="russian"
                EYE_LANG_SHORT="ru"
                print_info "Selected Russian language (Русский)"
                break
                ;;
            *)
                print_error "Invalid choice: '$lang_choice'"
                print_warn "Available options: 1 (English), 2 (Russian)"
                print_warn "You can also type: english, en, russian, ru"
                ;;
        esac
    done
}

# Ask user for database type
select_database_type() {
    print_step "Select Database Type"

    echo "Available database types:"
    echo "1) MySQL/MariaDB (default)"
    echo "2) PostgreSQL"
    echo ""
    
    read -p "Select database type (1 or 2) [1]: " db_choice
    
    case $db_choice in
        2|postgres|postgresql|pgsql)
            DB_TYPE="postgresql"
            print_info "Selected PostgreSQL"
            ;;
        *)
            DB_TYPE="mysql"
            print_info "Selected MySQL/MariaDB"
            ;;
    esac
}

# Настройка параметров подключения к БД (общая для local и remote)
configure_database_connection() {
    echo ""
    if [[ "$DB_INSTALL" == "local" ]]; then
        echo "Local Database Configuration"
        echo "============================"
        DB_HOST="127.0.0.1"
        if [[ "$DB_TYPE" == "postgresql" ]]; then
            DB_PORT="5432"
        else
            DB_PORT="3306"
        fi
        echo "Database server: $DB_HOST:$DB_PORT (local)"
    else
        echo "Remote Database Configuration"
        echo "============================"
        read -p "Database server IP address: " DB_HOST
        read -p "Database port [$([ "$DB_TYPE" == "postgresql" ] && echo "5432" || echo "3306")]: " DB_PORT
        # Установка порта по умолчанию, если не введён
        if [[ -z "$DB_PORT" ]]; then
            if [[ "$DB_TYPE" == "postgresql" ]]; then
                DB_PORT="5432"
            else
                DB_PORT="3306"
            fi
        fi
    fi

    read -p "Database name [stat]: " DB_NAME
    read -p "Database username [stat]: " DB_USER
    echo ""

    # Установка значений по умолчанию
    : "${DB_NAME:=stat}"
    : "${DB_USER:=stat}"
}

# Function for installation type selection
select_installation_type() {
    echo "Select installation type:"
    echo "1. Web interface + network backend"
    echo "2. Web interface only"
    echo "3. Network backend only"
    echo ""

    read -p "Enter selection number [1]: " install_type

    case $install_type in
        1)
            INSTALL_TYPE="full"
            echo "Selected: Web interface + network backend"

            read -p "Install database locally? (y/n) [y]: " install_db

            if [[ -z "$install_db" || "$install_db" =~ ^[Yy]$ ]]; then
                DB_INSTALL="local"
                echo "Local database will be installed"
                select_database_type
            else
                DB_INSTALL="remote"
                echo "Remote database configuration"
                select_database_type
            fi
            configure_database_connection
            ;;

        2)
            INSTALL_TYPE="web"
            echo "Selected: Web interface only"
            DB_INSTALL="remote"
            select_database_type
            configure_database_connection
            ;;

        3)
            INSTALL_TYPE="backend"
            echo "Selected: Network backend only"
            DB_INSTALL="remote"
            select_database_type
            configure_database_connection
            ;;

        *)
            INSTALL_TYPE="full"
            echo "Default selected: Web interface + network backend"
            DB_INSTALL="local"
            echo "Local database will be installed"
            select_database_type
            configure_database_connection
            ;;
    esac

    # Защита от неопределённых переменных
    : "${DB_TYPE:=mysql}"
    : "${DB_INSTALL:=local}"
    : "${DB_HOST:=127.0.0.1}"
    : "${DB_NAME:=stat}"
    : "${DB_USER:=stat}"
}

# Install dependencies for ALT Linux
install_deps_altlinux() {
    print_step "Installing dependencies for ALT Linux"
    apt-get update

    # Общие утилиты (всегда нужны)
    apt-get install -y git wget rsync xxd hwdata pwgen

    # === Локальная база данных (если выбрана) ===
    if [[ "$DB_INSTALL" == "local" ]]; then
        if [[ "$DB_TYPE" == "postgresql" ]]; then
            apt-get install -y postgresql17 postgresql17-server postgresql17-contrib postgresql17-perl
        else
            apt-get install -y mariadb-server mariadb-client
        fi
    fi

    # === Веб-интерфейс (если нужен) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        apt-get install -y apache2 php8.2 php8.2-fpm-fcgi apache2-mod_fcgid \
            php8.2-intl php8.2-mbstring php8.2-snmp php8.2-zip pear-Mail

        if [[ "$DB_TYPE" == "postgresql" ]]; then
            apt-get install -y php8.2-pgsql php8.2-pdo_pgsql
        else
            apt-get install -y php8.2-mysqlnd php8.2-pdo_mysql php8.2-mysqlnd-mysqli
        fi
    fi

    # === Сетевой бэкенд (если нужен) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        apt-get install -y fping

        # Общие Perl-модули (независимо от СУБД)
        apt-get install -y perl \
            perl-Net-Patricia perl-NetAddr-IP perl-Config-Tiny \
            perl-Net-DNS perl-DateTime perl-Net-Ping \
            perl-Net-Netmask perl-Text-Iconv perl-Net-SNMP \
            perl-Net-Telnet perl-DBI \
            perl-Parallel-ForkManager perl-Proc-Daemon \
            perl-DateTime-Format-DateParse perl-DateTime-Format-Strptime \
            perl-Net-OpenSSH perl-File-Tail perl-Tie-File \
            perl-Crypt-Rijndael perl-Crypt-CBC perl-CryptX perl-Crypt-DES \
            perl-File-Path-Tiny perl-Expect perl-Proc-ProcessTable \
            perl-Text-CSV \
            perl-DBD-Pg perl-DBD-mysql
    fi

    # Дополнительные проверки (например, fping — нужны только бэкенду)
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        control fping public
    fi

    control ping public
}

# Install dependencies for Debian/Ubuntu
install_deps_debian() {
    print_step "Installing dependencies for Debian/Ubuntu"
    apt-get update

    # Общие утилиты (всегда нужны)
    apt-get install -y git wget rsync xxd hwdata pwgen bsdmainutils

    # === Локальная база данных (если выбрана) ===
    if [[ "$DB_INSTALL" == "local" ]]; then
        if [[ "$DB_TYPE" == "postgresql" ]]; then
            # Устанавливаем generic-пакеты PostgreSQL
            apt-get install -y postgresql postgresql-contrib postgresql-server-dev-all
        else
            apt-get install -y mariadb-server mariadb-client
        fi
    fi

    # === Веб-интерфейс (если нужен) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        apt-get install -y apache2 libapache2-mod-fcgid \
            php php-fpm \
            php-bcmath php-intl php-mbstring php-snmp php-zip php-mail \
            php-date php-db


        if [[ "$DB_TYPE" == "postgresql" ]]; then
            apt-get install -y php-pgsql
        else
            apt-get install -y php-mysql
        fi
    fi

    # === Сетевой бэкенд (если нужен) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        apt-get install -y fping

        # Perl и обязательные модули (имена корректны для Ubuntu 24.04)
        apt-get install -y perl \
            libnet-patricia-perl libnetaddr-ip-perl libconfig-tiny-perl \
            libnet-dns-perl libdatetime-perl libnet-netmask-perl \
            libtext-iconv-perl libnet-snmp-perl libnet-telnet-perl \
            libdbi-perl libparallel-forkmanager-perl libproc-daemon-perl \
            libdatetime-format-dateparse-perl libnetwork-ipv4addr-perl \
            libnet-openssh-perl libfile-tail-perl libdatetime-format-strptime-perl \
            libcrypt-rijndael-perl libcrypt-cbc-perl libcryptx-perl \
            libcrypt-des-perl libfile-path-tiny-perl libexpect-perl \
            libtext-csv-perl \
            libdbd-pg-perl libdbd-mysql-perl
    fi

    # === Дополнительно (если нужно) ===
    # Раскомментируйте, если требуется DNS-сервер
    # apt-get install -y bind9 bind9-utils bind9-host
}

# System update
update_system() {
    print_step "Updating apt cache"
    $PACKAGE_MANAGER update -y
}

upgrade_system() {
    print_step "Updating system"
    if [[ "$PACKAGE_MANAGER" == "apt-get" ]]; then
        apt-get dist-upgrade -y
    else
        $PACKAGE_MANAGER upgrade -y
    fi
}

# Install packages
install_packages() {
    print_step "Installing packages"

    case $OS_FAMILY in
        alt)
            install_deps_altlinux
            ;;
        debian)
            install_deps_debian
            ;;
    esac
}

# Create user and group
create_user_group() {
    print_step "Creating user and group"

    # Create group
    if ! getent group eye >/dev/null; then
        groupadd --system eye
        print_info "Group 'eye' created"
    else
        print_info "Group 'eye' already exists"
    fi

    # Create user
    if ! id -u eye >/dev/null 2>&1; then
        if [[ "$OS_FAMILY" == "alt" ]]; then
            # For ALT Linux
            useradd --system --shell /bin/bash --home-dir /opt/Eye \
                --gid eye --groups eye eye
        else
            # For Debian/Ubuntu
            adduser --system --disabled-password --disabled-login \
                --ingroup eye --home=/opt/Eye eye
        fi
        print_info "User 'eye' created"
    else
        print_info "User 'eye' already exists"
    fi

    # Create directory
    mkdir -p /opt/Eye
    chown eye:eye /opt/Eye
    chmod 770 /opt/Eye

    # Add nagios to eye group (if exists)
    if id -u nagios >/dev/null 2>&1; then
        usermod -a -G eye nagios
        print_info "User 'nagios' added to group 'eye'"
    fi
}

# Check and apply SNMP SHA512 patch
apply_snmp_patch() {
    print_info "Checking for SNMPv3 SHA512 support..."

    # File paths
    USM_PATCH_FILE="/opt/Eye/docs/patches/sha512.patch"
    if [[ "$OS_FAMILY" == "alt" ]]; then
        USM_PATCH_FILE="/opt/Eye/docs/patches/sha512.alt.patch"
    fi

    USM_PM_FILE=""

    # Search for USM.pm in system
    local usm_paths=(
        "/usr/share/perl5/Net/SNMP/Security/USM.pm"
        "/usr/lib/perl5/vendor_perl/Net/SNMP/Security/USM.pm"
        "/usr/local/share/perl5/Net/SNMP/Security/USM.pm"
    )

    for path in "${usm_paths[@]}"; do
        if [[ -f "$path" ]]; then
            USM_PM_FILE="$path"
            print_info "Found USM.pm: $USM_PM_FILE"
            break
        fi
    done

    if [[ -z "$USM_PM_FILE" ]]; then
        print_warn "USM.pm file not found in system"
        return 1
    fi

    # Check if patch already applied
    if grep -q "AUTH_PROTOCOL_HMACSHA512" "$USM_PM_FILE"; then
        print_info "SHA512 patch already applied"
        return 0
    fi

    # Create backup
    cp "$USM_PM_FILE" "${USM_PM_FILE}.backup"
    print_info "Backup created: ${USM_PM_FILE}.backup"

    # Try to apply patch file
    local patch_applied=false

    if [[ -f "$USM_PATCH_FILE" ]]; then
        print_info "Attempting to apply patch from $USM_PATCH_FILE"

        # Check if patch can be applied
        if patch --dry-run -l -p1 -i "$USM_PATCH_FILE" -r /tmp/patch.rej "$USM_PM_FILE" 2>/dev/null; then
            # Apply patch
            if patch -l -p1 -i "$USM_PATCH_FILE" "$USM_PM_FILE" 2>/dev/null; then
                print_info "Patch successfully applied!"
                patch_applied=true
            else
                print_warn "Failed to apply patch (dry-run passed but actual application failed)"
            fi
        else
            print_warn "Patch cannot be applied automatically (version mismatch)"

            # Check differences
            print_info "Checking patch differences..."
            if [[ -f "/opt/Eye/docs/patches/USM.pm" ]]; then
                diff -u "$USM_PM_FILE" "/opt/Eye/docs/patches/USM.pm" > /tmp/usm.diff 2>/dev/null || true

                if [[ -s /tmp/usm.diff ]]; then
                    print_warn "Differences found in USM.pm file"
                    echo "Differences:"
                    head -20 /tmp/usm.diff
                    echo "..."
                fi
            fi
        fi
    fi

    # If patch not applied, ask user
    if [[ "$patch_applied" == false ]]; then
        echo ""
        print_warn "Automatic patch application failed"
        print_warn "Modification of USM.pm file required for SNMPv3 with SHA512 support"
        echo ""

        read -p "Do you need SNMPv3 SHA512 support? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            # Try to replace the entire file
            if [[ -f "/opt/Eye/docs/patches/USM.pm" ]]; then
                print_info "Replacing USM.pm file entirely..."

                # Check version compatibility
                local original_ver=$(grep -i "version" "$USM_PM_FILE" | head -1)
                local patch_ver=$(grep -i "version" "/opt/Eye/docs/patches/USM.pm" | head -1)

                if [[ -n "$original_ver" && -n "$patch_ver" ]]; then
                    print_info "Original file version: $original_ver"
                    print_info "Patch version: $patch_ver"
                fi

                # Create additional backup
                cp "$USM_PM_FILE" "${USM_PM_FILE}.backup.$(date +%Y%m%d_%H%M%S)"

                # Replace file
                cp -f "/opt/Eye/docs/patches/USM.pm" "$USM_PM_FILE"

                # Check if replacement successful
                if grep -q "SHA-512" "$USM_PM_FILE"; then
                    print_info "USM.pm file successfully replaced, SHA512 support added"

                    # Save replacement info
                    echo "USM.pm file was replaced for SHA512 support" > "${USM_PM_FILE}.replaced"
                    echo "Original file saved as: ${USM_PM_FILE}.backup" >> "${USM_PM_FILE}.replaced"
                    echo "Replacement date: $(date)" >> "${USM_PM_FILE}.replaced"

                    return 0
                else
                    print_error "Failed to add SHA512 support after file replacement"
                    # Restore from backup
                    cp "${USM_PM_FILE}.backup" "$USM_PM_FILE"
                    return 1
                fi
            else
                print_error "Patched USM.pm file not found in /opt/Eye/docs/patches/"
                return 1
            fi
        else
            print_info "SNMPv3 SHA512 support disabled"
            return 0
        fi
    fi

    return 0
}

# Download and copy source code
install_source_code() {
    print_step "Installing Eye source code"

    # Создаём корневой каталог
    mkdir -p /opt/Eye
    chown eye:eye /opt/Eye
    chmod 755 /opt/Eye

    # === Устанавливаем документацию (всегда) ===
    if [ -d "docs" ]; then
        print_info "Copying documentation..."
        mkdir -p /opt/Eye/docs
        cp -R docs/* /opt/Eye/docs/ 2>/dev/null || true
        chown -R eye:eye /opt/Eye/docs
    fi

    # === Устанавливаем веб-интерфейс (если нужен) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        print_info "Copying web interface files..."
        mkdir -p /opt/Eye/html/cfg /opt/Eye/html/js
        if [ -d "html" ]; then
            cp -R html/* /opt/Eye/html/ 2>/dev/null || true
        fi
        download_additional_scripts
        chown -R eye:eye /opt/Eye/html
    fi

    # === Устанавливаем бэкенд (если нужен) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        print_info "Copying backend scripts..."
        mkdir -p /opt/Eye/scripts/cfg /opt/Eye/scripts/log
        if [ -d "scripts" ]; then
            cp -R scripts/* /opt/Eye/scripts/ 2>/dev/null || true
        fi
        chmod 750 /opt/Eye/scripts
        chmod 770 /opt/Eye/scripts/log
        chown -R eye:eye /opt/Eye/scripts

        if [[ -f "/opt/Eye/docs/systemd/stat-sync.service" ]]; then
            cp /opt/Eye/docs/systemd/stat-sync.service /etc/systemd/system/
            systemctl enable stat-sync.service
        fi

    fi

    # Применяем патч (только если установлен бэкенд, т.к. касается SNMP в Perl)
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        apply_snmp_patch
    fi
}

# Download additional scripts
download_additional_scripts() {
    print_step "Downloading additional scripts"

    # Create directories
    mkdir -p /opt/Eye/html/js/jq
    mkdir -p /opt/Eye/html/js/select2
    mkdir -p /opt/Eye/html/js/jstree

    # Download jQuery
    print_info "Downloading jQuery..."
    if ! wget -q https://code.jquery.com/jquery-3.7.0.min.js \
        -O /opt/Eye/html/js/jq/jquery.min.js; then
        print_warn "Failed to download jQuery, trying alternative source..."
        wget -q https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js \
            -O /opt/Eye/html/js/jq/jquery.min.js || \
            print_error "Failed to download jQuery"
    fi

    # Download Select2
    print_info "Downloading Select2..."
    if wget -q https://github.com/select2/select2/archive/4.0.12.tar.gz -O 4.0.12.tar.gz; then
        tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ \
            --strip-components=2 select2-4.0.12/dist 2>/dev/null || \
            tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ \
            --strip-components=1 select2-4.0.12/dist 2>/dev/null
        rm -f 4.0.12.tar.gz
    else
        print_warn "Failed to download Select2"
    fi

    # Download jsTree
    print_info "Downloading jsTree..."
    if wget -q https://github.com/vakata/jstree/archive/3.3.12.tar.gz -O jstree.tar.gz; then
        tar -xzf jstree.tar.gz -C /opt/Eye/html/js/
        rsync -a /opt/Eye/html/js/jstree-3.3.12/dist/ /opt/Eye/html/js/jstree/
        rm -rf /opt/Eye/html/js/jstree-3.3.12
        rm -f jstree.tar.gz
    else
        print_warn "Failed to download jsTree"
    fi

    # Set permissions
    chown -R eye:eye /opt/Eye/html/js
}

# Configure MySQL
setup_mysql() {
    print_step "Configuring MySQL"

    # Start and enable service
    $SERVICE_MANAGER enable mariadb 2>/dev/null || \
    $SERVICE_MANAGER enable mysql 2>/dev/null || true

    $SERVICE_MANAGER start mariadb 2>/dev/null || \
    $SERVICE_MANAGER start mysql 2>/dev/null || true

    # Check MySQL access
    if ! command -v mysql &> /dev/null; then
        print_error "MySQL client not installed"
        return 1
    fi

    MYSQL_OPT="-u root"

    # Check access without password
    if mysql -u root -e "SELECT 1;" 2>/dev/null; then
        print_info "MySQL accessible with empty password"
        echo ""
        print_warn "IMPORTANT: Need to set root password for MySQL!"
        print_warn "After installation run: mysql_secure_installation"
        echo ""
    else
        # Ask for password and create config file
        read -p "Enter MySQL root user password: " DB_ROOT_PASSWORD
        echo ""

        # Create temporary config file
        MYSQL_CNF_FILE="/tmp/mysql_root_eye.cnf"
        echo "[client]" > "$MYSQL_CNF_FILE"
        echo "user=root" >> "$MYSQL_CNF_FILE"
        echo "password=$DB_ROOT_PASSWORD" >> "$MYSQL_CNF_FILE"
        chmod 600 "$MYSQL_CNF_FILE"

        # Check connection
        if mysql --defaults-extra-file="$MYSQL_CNF_FILE" -e "SELECT 1;" &>/dev/null; then
            print_info "Successfully connected to MySQL"
            MYSQL_OPT="--defaults-extra-file=$MYSQL_CNF_FILE"
        else
            print_error "Incorrect MySQL root password"
            rm -f "$MYSQL_CNF_FILE"
            return 1
        fi
    fi

    read -p "Create database and user for Eye? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warn "Database creation skipped. Create manually:"
        print_warn "  mysql -u root -p ${DB_NAME}< ${SQL_CREATE_FILE}"
        print_warn "  mysql -u root -p ${DB_NAME} < ${SQL_DATA_FILE}"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 0
    fi

    # Generate password for db user
    DB_PASS=$(pwgen 16 1)

    print_info "Importing database structure..."

    # Import main SQL file
    mysql $MYSQL_OPT <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF
    mysql $MYSQL_OPT ${DB_NAME} < ${SQL_CREATE_FILE}

    if [[ $? -ne 0 ]]; then
        print_error "Error importing create_db.sql"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi

    print_info "Database structure imported"

    # Import data
    print_info "Importing initial data..."
    mysql $MYSQL_OPT ${DB_NAME} < ${SQL_DATA_FILE}

    if [[ $? -ne 0 ]]; then
        print_warn "Error importing data.sql (data may already exist)"
    else
        print_info "Initial data imported"
    fi

    # Create db user
    print_info "Creating user ${DB_USER}.."
    mysql $MYSQL_OPT <<EOF
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

    if [[ $? -ne 0 ]]; then
        print_error "Error creating user $DB_USER"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi

    print_info "User $DB_USER successfully created"

    # Save password information
    echo "MySQL $DB_USER user password: $DB_PASS" > /root/eye_mysql_password.txt
    chmod 600 /root/eye_mysql_password.txt

    print_info "User $DB_USER password: $DB_PASS"
    print_warn "Password saved in /root/eye_mysql_password.txt"

    # Clean up temporary file if created
    if [[ -f "$MYSQL_CNF_FILE" ]]; then
        rm -f "$MYSQL_CNF_FILE"
    fi

    return 0
}

# Configure PostgreSQL
setup_postgresql() {
    print_step "Configuring PostgreSQL"

    PGDATA="/var/lib/pgsql/data"

    # Для ALT Linux
    if [[ "$OS_FAMILY" == "alt" ]]; then
        echo "root ALL=(ALL:ALL) NOPASSWD: ALL" >/etc/sudoers.d/root
        PGDATA="/var/lib/pgsql/data"

        if [ -z "$(ls -A $PGDATA 2>/dev/null)" ]; then
            /etc/init.d/postgresql initdb

            # === ВАЖНО: настраиваем pg_hba.conf для безпарольного доступа ===
            local pg_hba_file="$PGDATA/pg_hba.conf"
            if [[ -f "$pg_hba_file" ]]; then
                # Делаем резервную копию
                cp "$pg_hba_file" "${pg_hba_file}.backup"

                # Вставляем правило для пользователя 'postgres' в начало файла
                # Это разрешит подключение без пароля через Unix-сокет
                sed -i '1i\
# Allow local postgres user without password\
local   all             postgres                                peer\
' "$pg_hba_file"

                print_info "Configured pg_hba.conf to allow peer authentication for 'postgres'"
            fi
        fi

        # Start and enable service
        $SERVICE_MANAGER enable postgresql
        $SERVICE_MANAGER restart postgresql
    else
        # Start and enable service
        $SERVICE_MANAGER enable postgresql
        $SERVICE_MANAGER start postgresql
    fi

    # Check PostgreSQL access
    if ! command -v psql &> /dev/null; then
        print_error "PostgreSQL client not installed"
        return 1
    fi

    # Спросить, создавать ли БД
    read -p "Create database and user for Eye? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warn "Database creation skipped. Create manually as postgres user:"
        print_warn "  sudo -u postgres createdb -O $DB_USER $DB_NAME"
        print_warn "  sudo -u postgres psql -d $DB_NAME -f $SQL_DATA_FILE"
        return 0
    fi

    # Генерация пароля для пользователя БД
    if command -v pwgen &> /dev/null; then
        DB_PASS=$(pwgen 16 1)
    else
        DB_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c16)
    fi

    # Определяем локаль на основе языка
    if [[ "$EYE_LANG" == "russian" ]]; then
        LC_TYPE="ru_RU.UTF-8"
    else
        LC_TYPE="en_US.UTF-8"
    fi

    print_info "Creating database '$DB_NAME' with locale '$LC_TYPE'..."

    # Set password for stat user
    print_info "Setting password for user $DB_USER ..."
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"

    sudo -u postgres createdb \
      --encoding=UTF8 \
      --lc-collate="$LC_TYPE" \
      --lc-ctype="$LC_TYPE" \
      --template=template0 \
      --owner="$DB_USER" \
      "$DB_NAME"

    if [[ $? -ne 0 ]]; then
        print_error "Failed to create database"
        return 1
    fi

    print_info "Database created successfully with owner '$DB_USER'"

    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"

    # Теперь подключаемся как новый владелец для импорта
    print_info "Importing database structure as '$DB_USER'..."

    # Вариант 1: Используя sudo и переключение пользователя в psql
    sudo -u postgres psql -d "$DB_NAME" <<EOF
SET ROLE "$DB_USER";
\i $SQL_CREATE_FILE
EOF

    if [[ $? -ne 0 ]]; then
        print_error "Error importing create_db.sql"
        return 1
    fi

    print_info "Database structure imported successfully"

    # Импортируем данные тоже как владелец
    if [[ -f "$SQL_DATA_FILE" ]]; then
        print_info "Importing database data as '$DB_USER'..."
        sudo -u postgres psql -d "$DB_NAME" <<EOF
SET ROLE "$DB_USER";
\i $SQL_DATA_FILE
EOF

        if [[ $? -ne 0 ]]; then
            print_warn "Warning: failed to import data (may already exist or non-critical)"
        else
            print_info "Database data imported successfully"
        fi
    fi

    # Дополнительные привилегии 
    print_info "Setting up additional privileges..."

    # Дать доступ пользователю postgres к БД
    sudo -u postgres psql -c "GRANT CONNECT ON DATABASE $DB_NAME TO postgres;"

    # Дать полные права пользователю postgres на все объекты
    sudo -u postgres psql -d "$DB_NAME" <<EOF
GRANT ALL ON SCHEMA public TO postgres;
ALTER DEFAULT PRIVILEGES FOR USER "$DB_USER" IN SCHEMA public GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR USER "$DB_USER" IN SCHEMA public GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR USER "$DB_USER" IN SCHEMA public GRANT ALL ON FUNCTIONS TO postgres;
EOF

    print_info "Database setup completed successfully"

    # Configure PostgreSQL for MD5 authentication
    if [[ "$OS_FAMILY" == "alt" ]]; then
        local pg_hba_file="/var/lib/pgsql/data/pg_hba.conf"
        if [[ -f "$pg_hba_file" ]]; then
            # Backup original
            cp "$pg_hba_file" "${pg_hba_file}.backup"
            # Add local md5 authentication if not present
            if ! grep -q "local.*$DB_NAME.*md5" "$pg_hba_file"; then
                echo "local   $DB_NAME            $DB_USER                                    scram-sha-256" >> "$pg_hba_file"
                print_info "Added MD5 authentication for $DB_USER user in pg_hba.conf"
                fi
            fi
        else
        local pg_hba_file="/etc/postgresql/$(ls /etc/postgresql/ | head -1)/main/pg_hba.conf"
        if [[ -f "$pg_hba_file" ]]; then
            # Backup original
            cp "$pg_hba_file" "${pg_hba_file}.backup"
            # Add local md5 authentication if not present
            if ! grep -q "local.*$DB_NAME.*md5" "$pg_hba_file"; then
                echo "local   $DB_NAME            $DB_USER                                    scram-sha-256" >> "$pg_hba_file"
                print_info "Added MD5 authentication for $DB_USER user in pg_hba.conf"
                fi
            fi
        fi

    # Restart PostgreSQL to apply changes
    $SERVICE_MANAGER restart postgresql

    # Save password information
    echo "PostgreSQL $DB_USER user password: $DB_PASS" > /root/eye_postgres_password.txt
    chmod 600 /root/eye_postgres_password.txt

    print_info "User $DB_USER password: $DB_PASS"
    print_warn "Password saved in /root/eye_postgres_password.txt"

    return 0
}

# Configure database based on selected type
setup_database() {
    # Пропускаем настройку, если БД — удалённая
    if [[ "$DB_INSTALL" != "local" ]]; then
        print_info "Database is configured remotely — skipping local setup"
        return 0
    fi

    print_step "Setting up local database"

    # Определяем пути к SQL-файлам в зависимости от типа БД и языка
    if [[ "$DB_TYPE" == "mysql" ]]; then
        if [[ "$EYE_LANG" == "russian" && -d "/opt/Eye/docs/databases/mysql/ru" ]]; then
            SQL_DATA_FILE="/opt/Eye/docs/databases/mysql/ru/data.sql"
            SQL_CREATE_FILE="/opt/Eye/docs/databases/mysql/ru/create_db.sql"
        else
            SQL_DATA_FILE="/opt/Eye/docs/databases/mysql/en/data.sql"
            SQL_CREATE_FILE="/opt/Eye/docs/databases/mysql/en/create_db.sql"
        fi
    elif [[ "$DB_TYPE" == "postgresql" ]]; then
        if [[ "$EYE_LANG" == "russian" && -d "/opt/Eye/docs/databases/postgres/ru" ]]; then
            SQL_DATA_FILE="/opt/Eye/docs/databases/postgres/ru/data.sql"
            SQL_CREATE_FILE="/opt/Eye/docs/databases/postgres/ru/create_db.sql"
        else
            SQL_DATA_FILE="/opt/Eye/docs/databases/postgres/en/data.sql"
            SQL_CREATE_FILE="/opt/Eye/docs/databases/postgres/en/create_db.sql"
        fi
    else
        print_error "Unsupported database type: $DB_TYPE"
        return 1
    fi

    # Проверка существования файлов
    if [[ ! -f "$SQL_CREATE_FILE" || ! -f "$SQL_DATA_FILE" ]]; then
        print_error "SQL files not found for DB_TYPE=$DB_TYPE and EYE_LANG=$EYE_LANG"
        return 1
    fi

    print_info "Using SQL files for $EYE_LANG language"

    # Выполняем настройку в зависимости от СУБД
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        setup_postgresql
    else
        setup_mysql
    fi
}

# Configure configuration files
setup_configs() {
    print_step "Configuring configuration files"

    # Генерация или запрос ключей шифрования
    print_info "Setting up encryption keys..."

    if [[ "$DB_INSTALL" == "local" ]]; then
        # Для локальной БД — генерируем автоматически
        if command -v pwgen &> /dev/null; then
            ENC_PASSWORD=$(pwgen 16 1)
        else
            ENC_PASSWORD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c16)
        fi
        ENC_VECTOR=$(tr -dc 0-9 </dev/urandom | head -c 16)
        print_info "Encryption keys generated automatically (local database)."
        print_info "Password: $ENC_PASSWORD"
        print_info "Vector: $ENC_VECTOR"
    else
        # Для удалённой БД — ОБЯЗАТЕЛЬНО запрашиваем у пользователя
        echo ""
        print_info "Remote database detected. You MUST provide the encryption keys"
        print_info "that are already in use by other Eye components connected to this database."
        echo ""
    
        while [[ -z "$ENC_PASSWORD" ]]; do
            read -p "Enter ENCRYPTION_KEY (16+ characters): " ENC_PASSWORD
            if [[ ${#ENC_PASSWORD} -lt 16 ]]; then
                print_warn "Key should be at least 16 characters long."
                ENC_PASSWORD=""
            fi
        done

        while [[ -z "$ENC_VECTOR" ]]; do
            read -p "Enter ENCRYPTION_IV (exactly 16 digits): " ENC_VECTOR
            if [[ ! "$ENC_VECTOR" =~ ^[0-9]{16}$ ]]; then
                print_warn "IV must consist of exactly 16 digits (0-9)."
                ENC_VECTOR=""
            fi
        done
    
        print_info "Encryption keys accepted for remote database."
    fi

    # === Настройка веб-конфигурации (только если нужен веб) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        if [[ -f "/opt/Eye/html/cfg/config.sample.php" ]]; then
            cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php

            # Определяем DB_TYPE для PHP (mysql или pgsql)
            PHP_DB_TYPE="$DB_TYPE"
            [[ "$DB_TYPE" == "postgresql" ]] && PHP_DB_TYPE="pgsql"

            # Подстановка реальных значений
            sed -i "s/define(\"DB_TYPE\",\"[^\"]*\");/define(\"DB_TYPE\",\"$PHP_DB_TYPE\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_HOST\",\"[^\"]*\");/define(\"DB_HOST\",\"$DB_HOST\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_PORT\",\"[^\"]*\");/define(\"DB_PORT\",\"$DB_PORT\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_NAME\",\"[^\"]*\");/define(\"DB_NAME\",\"$DB_NAME\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_USER\",\"[^\"]*\");/define(\"DB_USER\",\"$DB_USER\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_PASS\",\"[^\"]*\");/define(\"DB_PASS\",\"$DB_PASS\");/" /opt/Eye/html/cfg/config.php

            # Ключи шифрования
            sed -i "s/ENCRYPTION_KEY\",\"[^\"]*\"/ENCRYPTION_KEY\",\"$ENC_PASSWORD\"/" /opt/Eye/html/cfg/config.php
            sed -i "s/ENCRYPTION_IV\",\"[^\"]*\"/ENCRYPTION_IV\",\"$ENC_VECTOR\"/" /opt/Eye/html/cfg/config.php

            print_info "Web configuration file config.php created"
        else
            print_warn "Web config template not found, skipping PHP config"
        fi
    fi

    # === Настройка конфигурации бэкенда (только если нужен бэкенд) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
            cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

            # Подстановка значений
            sed -i "s/^DBTYPE=.*/DBTYPE=$DB_TYPE/" /opt/Eye/scripts/cfg/config
            sed -i "s/DBTYPE=db_type/DBTYPE=$DB_TYPE/" /opt/Eye/scripts/cfg/config

            sed -i "s/^DBHOST=.*/DBHOST=$DB_HOST/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBPORT=.*/DBPORT=$DB_PORT/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBNAME=.*/DBNAME=$DB_NAME/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBUSER=.*/DBUSER=$DB_USER/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBPASS=.*/DBPASS=$DB_PASS/" /opt/Eye/scripts/cfg/config

            # Ключи шифрования
            sed -i "s/^encryption_key=.*/encryption_key=$ENC_PASSWORD/" /opt/Eye/scripts/cfg/config
            sed -i "s/encryption_key=!!!CHANGE_ME!!!!/encryption_key=$ENC_PASSWORD/" /opt/Eye/scripts/cfg/config
            sed -i "s/^encryption_iv=.*/encryption_iv=$ENC_VECTOR/" /opt/Eye/scripts/cfg/config
            sed -i "s/encryption_iv=0123456789012345/encryption_iv=$ENC_VECTOR/" /opt/Eye/scripts/cfg/config

            print_info "Backend configuration file scripts/cfg/config created"
        else
            print_warn "Backend config template not found, skipping scripts config"
        fi
    fi

    # === Установка прав (только для существующих каталогов) ===
    if [[ -d "/opt/Eye/html/cfg" ]]; then
        chown -R eye:eye /opt/Eye/html/cfg
        chmod 750 /opt/Eye/html/cfg
        chmod 660 /opt/Eye/html/cfg/config.php 2>/dev/null || true
    fi

    if [[ -d "/opt/Eye/scripts/cfg" ]]; then
        chown -R eye:eye /opt/Eye/scripts/cfg
        chmod 750 /opt/Eye/scripts/cfg
        chmod 660 /opt/Eye/scripts/cfg/config 2>/dev/null || true
    fi

}

# Функция применения языковых настроек к конфигурации
apply_language_settings() {
    print_info "Applying language settings: $EYE_LANG"

    # Применяем языковые настройки только если установлен веб-интерфейс
    if [[ "$INSTALL_TYPE" != "web" && "$INSTALL_TYPE" != "full" ]]; then
        print_info "Web interface not installed — skipping language configuration"
        return 0
    fi

    # Проверяем, существует ли каталог конфигурации веба
    if [[ ! -d "/opt/Eye/html/cfg" ]]; then
        print_warn "Web config directory not found — skipping language setup"
        return 0
    fi

    CONFIG_PHP="/opt/Eye/html/cfg/config.php"
    if [[ ! -f "$CONFIG_PHP" ]]; then
        print_warn "Web config file not found — skipping language setup"
        return 0
    fi

    if [[ "$EYE_LANG" == "russian" ]]; then
        # Установка русского языка
        sed -i "s/define(\"HTML_LANG\",\"[^\"]*\"\");/define(\"HTML_LANG\",\"russian\");/g" "$CONFIG_PHP"
        sed -i "s/setlocale(LC_ALL, '[^']*');/setlocale(LC_ALL, 'ru_RU.UTF-8');/g" "$CONFIG_PHP"
        print_info "Web interface language set to Russian"
    else
        # Установка английского языка (по умолчанию)
        sed -i "s/define(\"HTML_LANG\",\"[^\"]*\"\");/define(\"HTML_LANG\",\"english\");/g" "$CONFIG_PHP"
        sed -i "s/setlocale(LC_ALL, '[^']*');/setlocale(LC_ALL, 'en_US.UTF-8');/g" "$CONFIG_PHP"
        print_info "Web interface language set to English"
    fi
}

# Configure Apache and PHP
setup_apache_php() {
    print_step "Configuring Apache and PHP"

    # Determine PHP version
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "8.2")
    echo "Версия PHP: $PHP_VERSION"

    # Configure PHP for all distributions
    if [[ "$OS_FAMILY" == "alt" ]]; then
        # ALT Linux
        PHP_INI="/etc/php/$PHP_VERSION/apache2/php.ini"
        APACHE_CONF_DIR="/etc/httpd2/conf"
        APACHE_SITES_DIR="$APACHE_CONF_DIR/sites-available"
        DEFAULT_CONF="$APACHE_SITES_DIR/000-default.conf"
        APACHE_USER="apache2"
    else
        # Debian/Ubuntu
        PHP_INI="/etc/php/$PHP_VERSION/apache2/php.ini"
        APACHE_CONF_DIR="/etc/apache2"
        APACHE_SITES_DIR="$APACHE_CONF_DIR/sites-available"
        DEFAULT_CONF="$APACHE_SITES_DIR/000-default.conf"
        APACHE_USER="www-data"
    fi

    # Configure Apache
    if [[ -f "/opt/Eye/docs/apache/000-default.conf" ]]; then
        print_info "Using prepared Apache template for ALT Linux"

        # Create directory if it doesn't exist
        mkdir -p "$APACHE_SITES_DIR"

        # Copy prepared config
        cp "/opt/Eye/docs/apache/000-default.conf" "$DEFAULT_CONF"

        # Enable site
        if [[ -f "$APACHE_CONF_DIR/sites-enabled/000-default.conf" ]]; then
            rm -f "$APACHE_CONF_DIR/sites-enabled/000-default.conf"
            ln -sf "$DEFAULT_CONF" "$APACHE_CONF_DIR/sites-enabled/000-default.conf"
        fi
    fi

    # Configure sudoers
    if [[ -f "/opt/Eye/docs/sudoers.d/www-data" ]]; then
        # Use prepared template, substituting correct user
        sed "s/www-data/eye/g" /opt/Eye/docs/sudoers.d/www-data > /etc/sudoers.d/eye
        chmod 440 /etc/sudoers.d/eye
        print_info "Sudoers file created from template"
    fi

    # Restart Apache
    if [[ "$OS_FAMILY" == "alt" ]]; then
        # ALT Linux uses httpd2
        APACHE_SERVICE="httpd2"
    else
        APACHE_SERVICE="apache2"
    fi

    #    usermod -a -G eye $APACHE_USER

    if [[ "$OS_FAMILY" == "debian" ]]; then
        a2dismod php${PHP_VERSION} 2>/dev/null
        a2dismod mpm_prefork 2>/dev/null

        a2enmod mpm_event 2>/dev/null
        a2enconf php${PHP_VERSION}-fpm  2>/dev/null
    fi

    mkdir -p /var/log/php-fpm/

    a2enmod setenvif
    a2enmod proxy
    a2enmod proxy_fcgi

    print_info "Apache configured, sudoers user: $APACHE_USER"
    print_info "Apache service: $APACHE_SERVICE"

    # Configure php-fpm
    print_info "Configure  php-fpm${PHP_VERSION}"
    if [[ -f "/opt/Eye/docs/php-fpm/eye.conf" ]]; then
        print_info "Using prepared php-fpm template"
        if [[ "$OS_FAMILY" == "alt" ]]; then
            cp "/opt/Eye/docs/php-fpm/eye.conf" /etc/fpm${PHP_VERSION}/php-fpm.d/
        else
            cp "/opt/Eye/docs/php-fpm/eye.conf" /etc/php/${PHP_VERSION}/fpm/pool.available/
            ln -sf "/etc/php/${PHP_VERSION}/fpm/pool.available/eye.conf" "/etc/php/${PHP_VERSION}/fpm/pool.d/eye.conf"
        fi
    fi

    $SERVICE_MANAGER enable "$APACHE_SERVICE"
    $SERVICE_MANAGER restart "$APACHE_SERVICE"

    $SERVICE_MANAGER enable php${PHP_VERSION}-fpm.service
    $SERVICE_MANAGER restart php${PHP_VERSION}-fpm.service

    # Check configuration
    if [[ "$OS_FAMILY" == "alt" ]]; then
        httpd2 -t 2>/dev/null && print_info "Apache (httpd2) configuration is valid" || print_warn "Check Apache configuration"
    else
        apache2ctl -t 2>/dev/null && print_info "Apache configuration is valid" || print_warn "Check Apache configuration"
    fi
}

# Configure cron and logrotate
setup_cron_logrotate() {
    print_step "Configuring cron and logrotate"

    # Cron
    if [[ -f "/opt/Eye/docs/cron/stat" ]]; then
        cp /opt/Eye/docs/cron/stat /etc/cron.d/eye
        chmod 644 /etc/cron.d/eye
        print_info "Cron job added: /etc/cron.d/eye"
    fi

    # Logrotate
    if [ -f /etc/dnsmasq.conf ] && [ -f "/opt/Eye/docs/logrotate/dnsmasq" ]; then
	cp /opt/Eye/docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq-eye
    fi

    if [ -e /opt/Eye/scripts ] && [ -f "/opt/Eye/docs/logrotate/scripts" ]; then
	cp /opt/Eye/docs/logrotate/scripts /etc/logrotate.d/eye-scripts
    fi

    print_info "Cron and logrotate configuration completed"
    print_warn "Edit /etc/cron.d/eye to enable required scripts"
}

# Configure DHCP server (dnsmasq)
setup_dhcp_server() {
    print_step "Configuring DHCP server"

    read -p "Configure DHCP server (dnsmasq)? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return 0
    fi

    if [[ "$OS_FAMILY" == "debian" ]]; then
	apt install dnsmasq -y
	else
	apt-get install dnsmasq -y
	fi

    # Backup configuration
    if [[ -f "/etc/dnsmasq.conf" ]]; then
        cp /etc/dnsmasq.conf /etc/dnsmasq.conf.backup
    fi

    # Copy configuration from Eye
    if [[ -f "/opt/Eye/docs/addons/dnsmasq.conf" ]]; then
        cat /opt/Eye/docs/addons/dnsmasq.conf > /etc/dnsmasq.conf
    fi

    # Copy systemd services
    if [[ -f "/opt/Eye/docs/systemd/dhcp-log.service" ]]; then
        cp /opt/Eye/docs/systemd/dhcp-log.service /etc/systemd/system/
        mkdir -p /etc/systemd/system/dnsmasq.service.d
        cp -f /opt/Eye/docs/systemd/dnsmasq.service.d/override.conf /etc/systemd/system/dnsmasq.service.d
    fi

    if [[ -f "/opt/Eye/docs/systemd/dhcp-log-truncate.service" ]]; then
        cp /opt/Eye/docs/systemd/dhcp-log-truncate.service /etc/systemd/system/
    fi

    # Enable services
    $SERVICE_MANAGER enable dnsmasq
#    $SERVICE_MANAGER start dnsmasq

    print_info "DHCP server configured"
    print_warn "Edit /etc/dnsmasq.conf for your network"
}

# Configure syslog-ng
setup_syslog() {
    print_step "Configuring syslog-ng"

    read -p "Configure remote log collection (syslog-ng)? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return 0
    fi

    if [[ "$OS_FAMILY" == "debian" ]]; then
	apt install syslog-ng -y
	else
	apt-get install syslog-ng syslog-ng-journal -y
	fi

    # Create backup of main config
    if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
        cp /etc/syslog-ng/syslog-ng.conf /etc/syslog-ng/syslog-ng.conf.backup
        print_info "Backup created: /etc/syslog-ng/syslog-ng.conf.backup"
    fi

    # Copy additional config for Eye
    if [[ -f "/opt/Eye/docs/syslog-ng/eye.conf" ]]; then
        mkdir -p /etc/syslog-ng/conf.d
        cp /opt/Eye/docs/syslog-ng/eye.conf /etc/syslog-ng/conf.d/eye.conf

        # Check if conf.d inclusion already exists in main config
        if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
            if ! grep -q "@include.*conf\.d" /etc/syslog-ng/syslog-ng.conf && \
                ! grep -q "include.*conf\.d" /etc/syslog-ng/syslog-ng.conf; then
                # Add conf.d directory inclusion to end of file
                echo "" >> /etc/syslog-ng/syslog-ng.conf
                echo "# Include Eye monitoring configuration" >> /etc/syslog-ng/syslog-ng.conf
                echo "@include \"/etc/syslog-ng/conf.d/*.conf\"" >> /etc/syslog-ng/syslog-ng.conf
                print_info "Added conf.d directory inclusion to syslog-ng.conf"
            fi
        fi
        print_info "Configuration file eye.conf copied to /etc/syslog-ng/conf.d/"
    else
        print_warn "eye.conf configuration file not found in /opt/Eye/docs/syslog-ng/"
    fi

    # options block
    syslogng_options='options {
chain_hostnames(off);
flush_lines(0);
use_dns(no);
use_fqdn(no);
dns_cache(no);
owner("root");
group("adm");
perm(0640);
stats_freq(0);
time_reopen(10);
log_fifo_size(1000);
create_dirs(yes);
keep_hostname(no);
};'

    # Check for options in main config
    if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
        if ! grep -q "^options\s*{" /etc/syslog-ng/syslog-ng.conf; then
            # Add options block if it doesn't exist

            if grep -q "^@version:" /etc/syslog-ng/syslog-ng.conf; then
                # Insert after @version: line
                sed -i "/^@version:/a\\$syslogng_options" /etc/syslog-ng/syslog-ng.conf
            else
                # Insert at beginning of file
                sed -i "1i\\$syslogng_options" /etc/syslog-ng/syslog-ng.conf
            fi
            print_info "Added options block to syslog-ng.conf"
        else
            # Check for required parameters in existing options block
            local missing_params=()

            if ! grep -q "time_reopen\s*(.*)" /etc/syslog-ng/syslog-ng.conf; then
                missing_params+=("time_reopen(10)")
            fi

            if ! grep -q "log_fifo_size\s*(.*)" /etc/syslog-ng/syslog-ng.conf; then
                missing_params+=("log_fifo_size(1000)")
            fi

            if ! grep -q "chain_hostnames\s*(.*)" /etc/syslog-ng/syslog-ng.conf; then
                missing_params+=("chain_hostnames(off)")
            fi

            if ! grep -q "create_dirs\s*(.*)" /etc/syslog-ng/syslog-ng.conf; then
                missing_params+=("create_dirs(yes)")
            fi

            if ! grep -q "keep_hostname\s*(.*)" /etc/syslog-ng/syslog-ng.conf; then
                missing_params+=("keep_hostname(no)")
            fi

            # Add missing parameters
            if [[ ${#missing_params[@]} -gt 0 ]]; then
                # Find options block and add parameters to end of block
                sed -i '/^options\s*{/,/^}/ {
/^}/ i\    '"$(IFS='; '; echo "${missing_params[*]}")"';
}' /etc/syslog-ng/syslog-ng.conf
                print_info "Added parameters to options block: ${missing_params[*]}"
            fi
        fi
    fi

    # Copy systemd service for Eye log processing
    if [[ -f "/opt/Eye/docs/systemd/syslog-stat.service" ]]; then
        cp /opt/Eye/docs/systemd/syslog-stat.service /etc/systemd/system/
        chmod 644 /etc/systemd/system/syslog-stat.service
        print_info "syslog-stat service copied"
    fi

    # Create log directory if it doesn't exist
    mkdir -p /opt/Eye/scripts/log
    chown eye:eye /opt/Eye/scripts/log
    chmod 770 /opt/Eye/scripts/log

    # Enable and start services
    $SERVICE_MANAGER daemon-reload

    if $SERVICE_MANAGER enable syslog-ng; then
        print_info "syslog-ng service enabled for autostart"
    else
        print_warn "Failed to enable syslog-ng for autostart"
    fi

    if $SERVICE_MANAGER restart syslog-ng; then
        print_info "syslog-ng service restarted"
    else
        print_warn "Failed to restart syslog-ng"
    fi

    if [[ -f "/etc/systemd/system/syslog-stat.service" ]]; then
        if $SERVICE_MANAGER enable syslog-stat; then
            print_info "syslog-stat service enabled for autostart"
        else
            print_warn "Failed to enable syslog-stat for autostart"
        fi

        if $SERVICE_MANAGER start syslog-stat; then
            print_info "syslog-stat service started"
        else
            print_warn "Failed to start syslog-stat"
        fi
    fi

    # Check syslog-ng configuration
    if command -v syslog-ng &> /dev/null; then
        if syslog-ng --syntax-only; then
            print_info "syslog-ng configuration is valid"
        else
            print_error "Error in syslog-ng configuration"
            print_warn "Check files: /etc/syslog-ng/syslog-ng.conf and /etc/syslog-ng/conf.d/eye.conf"
        fi
    fi

    print_info "syslog-ng configuration completed"
    print_info "To receive logs from devices, configure them to send to IP: $(hostname -f)"
}

# Configure additional services
setup_additional_services() {
    print_step "Configuring additional services"

    # stat-sync service
    if [[ -f "/opt/Eye/docs/systemd/stat-sync.service" ]]; then
        cp /opt/Eye/docs/systemd/stat-sync.service /etc/systemd/system/
        $SERVICE_MANAGER enable stat-sync.service
        print_info "stat-sync service enabled"
    fi

    # eye-statd service (NetFlow)
    if [[ -f "/opt/Eye/docs/systemd/eye-statd.service" ]]; then
        cp /opt/Eye/docs/systemd/eye-statd.service /etc/systemd/system/
        $SERVICE_MANAGER enable eye-statd.service
        print_info "eye-statd service (NetFlow) enabled"
    fi

    # Configure DHCP
    setup_dhcp_server

    # Configure syslog
    setup_syslog
}

# Import MAC address database
import_mac_database() {
    print_step "Importing MAC address database"

    if [[ -f "/opt/Eye/scripts/utils/mac-oids/download-macs.sh" ]]; then
        cd /opt/Eye/scripts/utils/mac-oids/

        # Download MAC database
        print_info "Downloading MAC address database..."
        bash download-macs.sh

        # Update vendors
        if [[ -f "update-mac-vendors.pl" ]]; then
            print_info "Updating vendor information..."
            perl update-mac-vendors.pl
        fi

        cd - >/dev/null
    else
        print_warn "MAC address import scripts not found"
    fi
}

# Final instructions
show_final_instructions() {
    echo ""
    echo -e "${GREEN}===========================================${NC}"
    echo -e "${GREEN}   INSTALLATION COMPLETED SUCCESSFULLY!   ${NC}"
    echo -e "${GREEN}===========================================${NC}"
    echo ""
    echo "SYSTEM INFORMATION:"
    echo "  Distribution:     $OS_NAME"
    echo "  Version:          $OS_VERSION"
    echo "  Database:         $DB_TYPE"
    echo "  Language:         $EYE_LANG"
    echo "  User:             eye"
    echo "  Directory:        /opt/Eye"
    echo ""
    echo ""
    echo "TO COMPLETE SETUP, EXECUTE:"
    echo ""
    echo "1. Configure database security:"
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        print_info "   PostgreSQL: Edit pg_hba.conf if needed"
        if [[ -f "/root/eye_postgres_password.txt" ]]; then
            echo ""
            echo "3. PostgreSQL 'stat' user password saved in:"
            echo "   /root/eye_postgres_password.txt"
            echo ""
        fi
    else
        echo "   mysql_secure_installation"
        if [[ -f "/root/eye_mysql_password.txt" ]]; then
            echo ""
            echo "3. MySQL 'stat' user password saved in:"
            echo "   /root/eye_mysql_password.txt"
            echo ""
        fi
    fi
    
    echo ""
    echo "2. Check and edit configuration files:"
    echo "   /opt/Eye/html/cfg/config.php"
    echo "   /opt/Eye/scripts/cfg/config"
    echo ""
    
    echo "4. Configure cron jobs:"
    echo "   nano /etc/cron.d/eye"
    echo "   Uncomment required scripts"
    echo ""
    echo "5. Configure if necessary:"
    echo "   - DHCP: /etc/dnsmasq.conf"
    echo "   - NetFlow: configure on network devices"
    echo ""
    echo "6. WEB INTERFACE ACCESS:"
    echo "   URL:      http://$(hostname -f)/"
    echo "   Admin:    http://$(hostname -f)/admin/"
    echo "   Login:    admin"
    echo "   Password: admin"
    echo ""
    echo -e "${RED}IMPORTANT:${NC}"
    echo "   - CHANGE admin password and API key!"
    echo "   - Configure users and networks in web interface"
    echo ""
    echo -e "${GREEN}===========================================${NC}"
    echo ""
}

# Final instructions
show_final_upgrade() {
    echo ""
    echo -e "${GREEN}===========================================${NC}"
    echo -e "${GREEN}      UPGRADE COMPLETED SUCCESSFULLY!      ${NC}"
    echo -e "${GREEN}===========================================${NC}"
    echo ""
}

# Install function
eye_install() {
    clear
    echo -e "${GREEN}===========================================${NC}"
    echo -e "${GREEN}   Installing Eye Monitoring System       ${NC}"
    echo -e "${GREEN}   for ALT Linux/Debian/Ubuntu            ${NC}"
    echo -e "${GREEN}===========================================${NC}"
    echo ""

    # Инициализация глобальных переменных
    DB_PASS=""
    DB_TYPE="mysql"
    EYE_LANG="russian"
    EYE_LANG_SHORT="ru"
    SQL_DATA_FILE=""
    SQL_CREATE_FILE=""
    INSTALL_TYPE="full"
    DB_INSTALL="local"

    # Обязательные шаги (всегда)
    check_root
    detect_distro
    select_language

    # Выбор типа установки (устанавливает INSTALL_TYPE, DB_INSTALL, DB_TYPE и параметры БД)
    select_installation_type

    # Обновление системы и установка пакетов (зависит от типа установки и ОС)
    update_system
    install_packages          # ← внутри уже учитывает INSTALL_TYPE и DB_INSTALL

    # Пользователь нужен всегда (для /opt/Eye)
    create_user_group

    # Установка исходного кода (учитывает INSTALL_TYPE)
    install_source_code

    # Настройка БД — ТОЛЬКО если локальная
    if [[ "$DB_INSTALL" == "local" ]]; then
        setup_database
    fi

    # Настройка конфигов — всегда (но внутри учитывает INSTALL_TYPE)
    setup_configs

    # Язык — только если установлен веб
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        apply_language_settings
    fi

    # Веб-сервер — только если нужен веб
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        setup_apache_php
    fi

    # Cron и logrotate — только если есть бэкенд (там — фоновые задачи и логи)
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        setup_cron_logrotate
    fi

    # Доп. сервисы (dnsmasq, syslog-ng и т.п.) — только для бэкенда
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        setup_additional_services
    fi

    # Импорт MAC-базы — только если есть бэкенд (он её использует)
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        import_mac_database
    fi

    show_final_instructions
}

backup_current_installation() {
    print_step "Creating full backup of current Eye installation"

    local EYE_ROOT="/opt/Eye"
    local BACKUP_DIR="/opt"
    local TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
    local BACKUP_FILE="$BACKUP_DIR/eye_backup_${TIMESTAMP}.tar.gz"

    # Проверка: существует ли инсталляция
    if [[ ! -d "$EYE_ROOT" ]]; then
        print_warn "Directory $EYE_ROOT not found — skipping backup"
        return 0
    fi

    # Проверка свободного места (~300 МБ на всякий случай)
    local FREE_SPACE_KB=$(df "$BACKUP_DIR" | awk 'NR==2 {print $4}')
    local MIN_FREE_KB=307200  # ~300 MB
    if [[ $FREE_SPACE_KB -lt $MIN_FREE_KB ]]; then
        print_error "Not enough free space in $BACKUP_DIR for full backup (need ~300 MB)"
        return 1
    fi

    print_info "Creating full backup of $EYE_ROOT (excluding logs and docs)"
    print_info "Backup file: $BACKUP_FILE"

    # Архивируем ВЕСЬ /opt/Eye, но исключаем:
    #   - docs/ — не меняется, идёт с дистрибутивом
    #   - scripts/log/ — логи (большие, не конфигурация)
    #   - html/log/ — если есть
    tar -czf "$BACKUP_FILE" \
        --exclude="docs" \
        --exclude="scripts/log" \
        --exclude="scripts/log/*" \
        --exclude="html/log" \
        --exclude="html/log/*" \
        -C / "opt/Eye" 2>/dev/null

    if [[ $? -eq 0 && -f "$BACKUP_FILE" ]]; then
        print_info "✅ Backup completed successfully"
        chmod 600 "$BACKUP_FILE"
        chown root:root "$BACKUP_FILE"
    else
        print_error "❌ Failed to create backup archive"
        return 1
    fi
}

# Upgrade function
eye_upgrade() {
    clear
    echo -e "${GREEN}===========================================${NC}"
    echo -e "${GREEN}       Update Eye Monitoring System        ${NC}"
    echo -e "${GREEN}===========================================${NC}"
    echo ""

    check_root
    detect_distro

    backup_current_installation || {
        echo "CRITICAL: Backup failed. Aborting upgrade."
        exit 1
    }

    update_system
    install_packages
    install_source_code
    import_mac_database
    /opt/Eye/scripts/updates/upgrade.pl
    show_final_upgrade
}

# Function to display help
show_help() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --help, -h     Show this help"
    echo "  --upgrade, -u  Automatic upgrade"
    echo "  --install, -i  Interactive install"
    echo ""
    echo "Supported distributions:"
    echo "  - ALT Linux 11.1+"
    echo "  - Debian 11+"
    echo "  - Ubuntu 20.04+"
    echo ""
}

# Function to check user existence
check_user() {
    id "eye" &>/dev/null
    return $?
}

# Function to check directory existence
check_directory() {
    [ -d "/opt/Eye" ]
    return $?
}

# Function to check if Eye config files exist
check_eye_configs() {
    # Веб-конфиг
    if [[ -f "/opt/Eye/html/cfg/config.php" ]]; then
        return 0
    fi
    # Бэкенд-конфиг
    if [[ -f "/opt/Eye/scripts/cfg/config" ]]; then
        return 0
    fi
    return 1
}

# Handle command line arguments
case "$1" in
    --help|-h)
        show_help
        exit 0
        ;;
    --upgrade|-u)
        mode="upgrade"
        echo "Mode set to: upgrade"
        ;;
    --install|-i)
        mode="install"
        echo "Mode set to: install"
        ;;
    *)
    # Auto-detect mode
    echo "Auto-detecting installation status..."

    if check_user; then
        user_exists=true
        echo "✓ User 'eye' exists"
    else
        user_exists=false
        echo "✗ User 'eye' does not exist"
    fi

    if check_directory; then
        dir_exists=true
        echo "✓ Directory /opt/Eye exists"
    else
        dir_exists=false
        echo "✗ Directory /opt/Eye does not exist"
    fi

    # Проверяем наличие хотя бы одного конфига Eye
    eye_config_found=false
    if [[ -f "/opt/Eye/html/cfg/config.php" ]] || [[ -f "/opt/Eye/scripts/cfg/config" ]]; then
        eye_config_found=true
        echo "✓ Eye configuration detected"
    fi

    if $user_exists && $dir_exists && $eye_config_found; then
        mode="upgrade"
        echo "Existing Eye installation detected. Switching to upgrade mode."

        # === Восстанавливаем INSTALL_TYPE ===
        if [[ -f "/opt/Eye/html/cfg/config.php" ]] && [[ -f "/opt/Eye/scripts/cfg/config" ]]; then
            INSTALL_TYPE="full"
        elif [[ -f "/opt/Eye/html/cfg/config.php" ]]; then
            INSTALL_TYPE="web"
        elif [[ -f "/opt/Eye/scripts/cfg/config" ]]; then
            INSTALL_TYPE="backend"
        else
            INSTALL_TYPE="full"  # fallback
        fi

        # === Восстанавливаем DB_INSTALL (local/remote) ===
        DB_HOST=""
        if [[ -f "/opt/Eye/html/cfg/config.php" ]]; then
            # Извлекаем DB_HOST из PHP-конфига
            DB_HOST=$(grep -oP 'define\s*\(\s*"DB_HOST"\s*,\s*"\K[^"]+' /opt/Eye/html/cfg/config.php 2>/dev/null)
        fi
        if [[ -z "$DB_HOST" && -f "/opt/Eye/scripts/cfg/config" ]]; then
            # Извлекаем из Perl-конфига
            DB_HOST=$(grep -oP '^DBHOST=\K.*' /opt/Eye/scripts/cfg/config 2>/dev/null)
        fi
    
        if [[ "$DB_HOST" == "127.0.0.1" || "$DB_HOST" == "localhost" || "$DB_HOST" == "::1" ]]; then
            DB_INSTALL="local"
        else
            DB_INSTALL="remote"
        fi

        # === Восстанавливаем DB_TYPE ===
        if [[ -f "/opt/Eye/html/cfg/config.php" ]]; then
            DB_TYPE=$(grep -oP 'define\s*\(\s*"DB_TYPE"\s*,\s*"\K[^"]+' /opt/Eye/html/cfg/config.php 2>/dev/null)
            # В PHP может быть 'pgsql' вместо 'postgresql'
            if [[ "$DB_TYPE" == "pgsql" ]]; then
                DB_TYPE="postgresql"
            elif [[ "$DB_TYPE" == "mysql" ]]; then
                DB_TYPE="mysql"
            fi
        elif [[ -f "/opt/Eye/scripts/cfg/config" ]]; then
            DB_TYPE=$(grep -oP '^DBTYPE=\K.*' /opt/Eye/scripts/cfg/config 2>/dev/null)
        fi

        # Защита от неопределённых значений
        : "${INSTALL_TYPE:=full}"
        : "${DB_INSTALL:=remote}"
        : "${DB_TYPE:=mysql}"

        echo "  → INSTALL_TYPE = $INSTALL_TYPE"
        echo "  → DB_INSTALL   = $DB_INSTALL"
        echo "  → DB_TYPE      = $DB_TYPE"

    else
        mode="install"
        echo "No existing Eye installation found. Switching to install mode."
    fi
    ;;
esac

echo ""
echo "Selected mode: $mode"

# Main execution based on mode
case "$mode" in
    "upgrade")
        echo "Starting upgrade process..."
        # Start upgrade
        eye_upgrade
        ;;
    "install")
        echo "Starting installation process..."
        # Start installation
        eye_install
        ;;
    *)
        echo "Error: Unknown mode '$mode'"
        exit 1
        ;;
esac

# Exit with success code
exit 0
