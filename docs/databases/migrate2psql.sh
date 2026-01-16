#!/bin/bash
# Eye Migration Script for ALT Linux/Debian/Ubuntu with PostgreSQL support
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

start_if_exists() {
    local service="$1"
    if systemctl cat "$service.service" >/dev/null 2>&1; then
        systemctl start "$service"
        return 0
    fi
    return 1
}

stop_if_exists() {
    local service="$1"
    if systemctl cat "$service.service" >/dev/null 2>&1; then
        systemctl stop "$service"
        return 0
    fi
    return 1
}
    
stop_eye() {
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "")
    if [ -n "${PHP_VERSION}" ]; then
        stop_if_exists php${PHP_VERSION}-fpm
        fi
    for svc in cron eye-statd dhcp-log stat-sync syslog-stat; do
        stop_if_exists ${svc}
        done
}

start_eye() {
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "")
    if [ -n "${PHP_VERSION}" ]; then
        start_if_exists php${PHP_VERSION}-fpm
        fi
    for svc in cron eye-statd dhcp-log stat-sync syslog-stat; do
        start_if_exists ${svc}
        done
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

    if [ -n "${EYE_LANG}" ]; then
	return
	fi

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

# Настройка параметров подключения к БД (общая для local и remote)
configure_database_connection() {
    echo ""
    echo "Local Database Configuration"
    echo "============================"
    DB_HOST="127.0.0.1"
    DB_PORT="5432"
    echo "Database server: $DB_HOST:$DB_PORT (local)"
}

# Install dependencies for ALT Linux
install_deps_altlinux() {
    print_step "Installing dependencies for ALT Linux"
    apt-get update
    # === Локальная база данных
    apt-get install -y postgresql17 postgresql17-server postgresql17-contrib postgresql17-perl
}

# Install dependencies for Debian/Ubuntu
install_deps_debian() {
    print_step "Installing dependencies for Debian/Ubuntu"
    apt-get update
    # === Локальная база данных
    apt-get install -y postgresql postgresql-contrib postgresql-server-dev-all
}

# System update
update_system() {
    print_step "Updating apt cache"
    $PACKAGE_MANAGER update -y
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
        exit 102
    fi

    print_info "Database structure imported successfully"

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

    print_info "User $DB_USER password: $DB_PASS"

    return 0
}

# Configure database based on selected type
setup_database() {
    print_step "Setting up local database"

    # Определяем пути к SQL-файлам в зависимости от типа БД и языка
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        if [[ "$EYE_LANG" == "russian" && -d "/opt/Eye/docs/databases/postgres/ru" ]]; then
            SQL_CREATE_FILE="/opt/Eye/docs/databases/postgres/ru/create_db.sql"
        else
            SQL_CREATE_FILE="/opt/Eye/docs/databases/postgres/en/create_db.sql"
        fi
    else
        print_error "Unsupported database type: $DB_TYPE"
        return 1
    fi

    # Проверка существования файлов
    if [[ ! -f "$SQL_CREATE_FILE" ]]; then
        print_error "SQL files not found for DB_TYPE=$DB_TYPE and EYE_LANG=$EYE_LANG"
        return 1
    fi
    print_info "Using SQL files for $EYE_LANG language"
    setup_postgresql
}

# Install function
eye_migrate2pgsql() {
    clear
    echo -e "${GREEN}================++++++++===========================${NC}"
    echo -e "${GREEN}   Migration Eye Monitoring System to PostgreSQL   ${NC}"
    echo -e "${GREEN}           for ALT Linux/Debian/Ubuntu             ${NC}"
    echo -e "${GREEN}===================================================${NC}"
    echo ""

    # Обязательные шаги (всегда)
    check_root
    detect_distro
    select_language

    # Обновление системы и установка пакетов (зависит от типа установки и ОС)
    update_system
    install_packages

    # Настройка БД
    configure_database_connection
    setup_database
    
    #data migration
    /opt/Eye/docs/databases/migrate2psql.pl --clear --batch
    
    if [ $? -eq 0 ]; then
	setup_configs
	fi
}

# Function to display help
show_help() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --help, -h     Show this help"
    echo ""
    echo "Supported distributions:"
    echo "  - ALT Linux 11.1+"
    echo "  - Debian 11+"
    echo "  - Ubuntu 20.04+"
    echo ""
}

# Function to check directory existence
check_directory() {
    [ -d "/opt/Eye" ]
    return $?
}

# Configure configuration files
setup_configs() {
    print_step "Configuring configuration files"

    # === Настройка веб-конфигурации (только если нужен веб) ===
    if [[ -f "/opt/Eye/html/cfg/config.php" ]]; then
            cp /opt/Eye/html/cfg/config.php /opt/Eye/html/cfg/config.migration.php
            PHP_DB_TYPE="pgsql"
            # Подстановка реальных значений
            sed -i "s/define(\"DB_TYPE\",\"[^\"]*\");/define(\"DB_TYPE\",\"$PHP_DB_TYPE\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_PORT\",\"[^\"]*\");/define(\"DB_PORT\",\"$DB_PORT\");/" /opt/Eye/html/cfg/config.php
            print_info "Web configuration file config.php created"
        else
            print_warn "Web config template not found, skipping PHP config"
        fi
        
    # === Настройка конфигурации бэкенда (только если нужен бэкенд) ===
    if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
            cp /opt/Eye/scripts/cfg/config /opt/Eye/scripts/cfg/config.migration
            # Подстановка значений
            sed -i "s/^DBTYPE=.*/DBTYPE=$DB_TYPE/" /opt/Eye/scripts/cfg/config
            sed -i "s/DBTYPE=mysql/DBTYPE=$DB_TYPE/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBPORT=.*/DBPORT=$DB_PORT/" /opt/Eye/scripts/cfg/config
            print_info "Backend configuration file scripts/cfg/config created"
        else
            print_warn "Backend config template not found, skipping scripts config"
        fi
}


# Инициализация глобальных переменных
DB_NAME=""
DB_USER=""
DB_HOST=""
DB_PASS=""
DB_TYPE="mysql"
EYE_LANG="russian"
SQL_CREATE_FILE=""

PHP_CONFIG="/opt/Eye/html/cfg/config.php"
PERL_CONFIG="/opt/Eye/scripts/cfg/config"

# Проверяем наличие хотя бы одного конфига Eye
if [[ -f "${PHP_CONFIG}" ]] || [[ -f "${PERL_CONFIG}" ]]; then
        echo "✓ Eye configuration detected"
    else
	echo "Eye installation not found! Bye."
	exit 101
    fi

if [[ -f "${PHP_CONFIG}" ]]; then
            # Извлекаем DB_HOST из PHP-конфига
            DB_HOST=$(grep -oP 'define\s*\(\s*"DB_HOST"\s*,\s*"\K[^"]+' ${PHP_CONFIG} 2>/dev/null)
    fi
if [[ -z "$DB_HOST" && -f "${PERL_CONFIG}" ]]; then
            # Извлекаем из Perl-конфига
            DB_HOST=$(grep -oP '^DBHOST=\K.*' ${PERL_CONFIG} 2>/dev/null)
    fi

if [[ "$DB_HOST" == "127.0.0.1" || "$DB_HOST" == "localhost" || "$DB_HOST" == "::1" ]]; then
            DB_INSTALL="local"
        else
            echo "Remote database detected. Abort installation!"
            exit 100
        fi

# === Восстанавливаем DB_TYPE ===
if [[ -f "${PHP_CONFIG}" ]]; then
            DB_TYPE=$(grep -oP 'define\s*\(\s*"DB_TYPE"\s*,\s*"\K[^"]+' ${PHP_CONFIG} 2>/dev/null)
            # В PHP может быть 'pgsql' вместо 'postgresql'
            if [[ "$DB_TYPE" == "pgsql" ]]; then
                DB_TYPE="postgresql"
            elif [[ "$DB_TYPE" == "mysql" ]]; then
                DB_TYPE="mysql"
            fi
        elif [[ -f "${PERL_CONFIG}" ]]; then
            DB_TYPE=$(grep -oP '^DBTYPE=\K.*' ${PERL_CONFIG} 2>/dev/null)
        fi

if [[ "$DB_TYPE" == "postgresql" ]]; then
    echo "Already using PostgreSQL! Nothing to do."
    exit 0
fi

DB_TYPE="postgresql"

if [[ -f "$PHP_CONFIG" ]]; then
    # Извлекаем язык
    if HTML_LANG=$(grep -oP 'define\s*\(\s*"HTML_LANG"\s*,\s*"\K[^"]+' "$PHP_CONFIG" 2>/dev/null); then
        case "$HTML_LANG" in
            russian|ru) EYE_LANG="russian" ;;
            english|en) EYE_LANG="english" ;;
        esac
    fi

    # Извлекаем БД параметры
    DB_NAME=$(grep -oP 'define\s*\(\s*"DB_NAME"\s*,\s*"\K[^"]+' "$PHP_CONFIG" 2>/dev/null)
    DB_USER=$(grep -oP 'define\s*\(\s*"DB_USER"\s*,\s*"\K[^"]+' "$PHP_CONFIG" 2>/dev/null)
    DB_HOST=$(grep -oP 'define\s*\(\s*"DB_HOST"\s*,\s*"\K[^"]+' "$PHP_CONFIG" 2>/dev/null)
    DB_PASS=$(grep -oP 'define\s*\(\s*"DB_PASS"\s*,\s*"\K[^"]+' "$PHP_CONFIG" 2>/dev/null)
fi

# читаем из Perl-конфига ===

if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASS" ]] && [[ -f "$PERL_CONFIG" ]]; then
    while IFS='=' read -r key value; do
        # Пропускаем комментарии и пустые строки
        [[ $key =~ ^#.*$ || -z $key ]] && continue
        case "$key" in
            DBNAME)  DB_NAME="$value" ;;
            DBUSER)  DB_USER="$value" ;;
            DBSERVER) DB_HOST="$value" ;;
            DBPASS) DB_PASS="$value" ;;
        esac
    done < "$PERL_CONFIG"
    fi

# === Вывод результатов (для отладки или использования в других скриптах) ===
echo "EYE_LANG=$EYE_LANG"
echo "DB_NAME=$DB_NAME"
echo "DB_USER=$DB_USER"
echo "DB_HOST=$DB_HOST"

# Убедимся, что все необходимые параметры получены
if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASS" ]]; then
    print_error "Failed to extract database credentials from config files"
    exit 1
fi

stop_eye

eye_migrate2pgsql

start_eye

echo "Maybe need install:"
echo "\t AltLinux: apt-get install php8.2-pgsql php8.2-pdo_pgsql"
echo "\t Debian/Ubuntu: apt install php-pgsql"

# Exit with success code
exit 0
