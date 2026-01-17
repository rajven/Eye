#!/bin/bash
# Eye Script validating current DB schema for ALT Linux/Debian/Ubuntu with PostgreSQL support
# Version: 1.0

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
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        DB_PORT="5432"
    else
        DB_PORT="3306"
    fi
}

# Configure MySQL
setup_mysql() {
    print_step "Configuring MySQL"

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

    # === Проверка: существует ли база данных? ===
    if mysql $MYSQL_OPT -sN -e "SHOW DATABASES;" | grep -q "^${DB_TEST}$"; then
        print_error "Database '$DB_TEST' already exists. The script has been stopped."
        exit 120
        fi

    print_info "Creating database..."

    # Import main SQL file
    mysql $MYSQL_OPT <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_TEST} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

    print_info "Importing database structure..."

    mysql $MYSQL_OPT ${DB_TEST} < ${SQL_CREATE_FILE}

    if [[ $? -ne 0 ]]; then
        print_error "Error importing create_db.sql"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi

    print_info "Database structure imported"

    # Create db user
    print_info "Creating user ${DB_USER}.."
    mysql $MYSQL_OPT <<EOF
GRANT ALL PRIVILEGES ON $DB_TEST.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

    if [[ $? -ne 0 ]]; then
        print_error "Error update user $DB_USER"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi

    # Clean up temporary file if created
    if [[ -f "$MYSQL_CNF_FILE" ]]; then
        rm -f "$MYSQL_CNF_FILE"
    fi

    return 0
}

# Configure PostgreSQL
setup_postgresql() {
    print_step "Configuring PostgreSQL"

    # Определяем локаль на основе языка
    if [[ "$EYE_LANG" == "russian" ]]; then
        LC_TYPE="ru_RU.UTF-8"
    else
        LC_TYPE="en_US.UTF-8"
    fi

    # === Проверка: существует ли БД? ===
    if sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw "^\s*${DB_TEST}\s*$"; then
        print_error "Database '$DB_TEST' already exists. The script has been stopped."
        exit 120
        fi

    print_info "Creating database '$DB_TEST' with locale '$LC_TYPE'..."

    sudo -u postgres createdb \
      --encoding=UTF8 \
      --lc-collate="$LC_TYPE" \
      --lc-ctype="$LC_TYPE" \
      --template=template0 \
      --owner="$DB_USER" \
      "$DB_TEST"

    if [[ $? -ne 0 ]]; then
        print_error "Failed to create database"
        return 1
    fi

    print_info "Database created successfully with owner '$DB_USER'"

    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_TEST TO $DB_USER;"

    # Теперь подключаемся как новый владелец для импорта
    print_info "Importing database structure as '$DB_USER'..."

    # Вариант 1: Используя sudo и переключение пользователя в psql
    sudo -u postgres psql -d "$DB_TEST" <<EOF
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
    sudo -u postgres psql -c "GRANT CONNECT ON DATABASE $DB_TEST TO postgres;"

    # Дать полные права пользователю postgres на все объекты
    sudo -u postgres psql -d "$DB_TEST" <<EOF
GRANT ALL ON SCHEMA public TO postgres;
ALTER DEFAULT PRIVILEGES FOR USER "$DB_USER" IN SCHEMA public GRANT ALL ON TABLES TO postgres;
ALTER DEFAULT PRIVILEGES FOR USER "$DB_USER" IN SCHEMA public GRANT ALL ON SEQUENCES TO postgres;
ALTER DEFAULT PRIVILEGES FOR USER "$DB_USER" IN SCHEMA public GRANT ALL ON FUNCTIONS TO postgres;
EOF

    print_info "Database setup completed successfully"

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
            SQL_CREATE_FILE="/opt/Eye/docs/databases/mysql/ru/create_db.sql"
        else
            SQL_CREATE_FILE="/opt/Eye/docs/databases/mysql/en/create_db.sql"
        fi
    elif [[ "$DB_TYPE" == "postgresql" ]]; then
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

    # Выполняем настройку в зависимости от СУБД
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        setup_postgresql
    else
        setup_mysql
    fi
}

# Install function
install_clear_db() {
    clear
    echo -e "${GREEN}================++++++++===========================${NC}"
    echo -e "${GREEN}   CheckDB Schema for Eye Monitoring System        ${NC}"
    echo -e "${GREEN}           for ALT Linux/Debian/Ubuntu             ${NC}"
    echo -e "${GREEN}===================================================${NC}"
    echo ""

    # Обязательные шаги (всегда)
    check_root
    detect_distro
    select_language

    # Настройка БД
    configure_database_connection
    setup_database

    #data migration
    /opt/Eye/docs/databases/checkDBschema.pl

    echo "The $DB_TEST database can be deleted"
}

# Function to display help
show_help() {
    echo "Usage: $0"
    echo "\tThe script checks the correctness of the working database structure."
    echo "\tTo do this, it creates a new empty database with a reference structure,"
    echo "\tand then compares the two databases. "
    echo "\tThe name of the test database is formed from the name of the working database with _test appended to it."
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

if [ -z "${DB_TYPE}" ]; then
    DB_TYPE='mysql'
    fi

# === Вывод результатов (для отладки или использования в других скриптах) ===
echo "EYE_LANG=$EYE_LANG"
echo "DB_NAME=$DB_NAME"
echo "DB_USER=$DB_USER"
echo "DB_HOST=$DB_HOST"
echo "DB_TYPE=$DB_TYPE"

DB_TEST="${DB_NAME}_test"

# Убедимся, что все необходимые параметры получены
if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASS" ]]; then
    print_error "Failed to extract database credentials from config files"
    exit 1
fi

install_clear_db

# Exit with success code
exit 0
