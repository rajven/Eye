#!/bin/bash
# Eye Installation Script for ALT Linux/Debian/Ubuntu
# Version: 2.0

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функции для вывода
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

# Проверка прав root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "Этот скрипт должен быть запущен с правами root"
        print_error "Используйте: sudo $0"
        exit 1
    fi
}

# Определение дистрибутива и менеджера пакетов
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
                print_info "Обнаружен ALT Linux $OS_VERSION"
                ;;
            debian)
                PACKAGE_MANAGER="apt"
                SERVICE_MANAGER="systemctl"
                OS_FAMILY="debian"
                print_info "Обнаружен Debian $OS_VERSION"
                ;;
            ubuntu)
                PACKAGE_MANAGER="apt"
                SERVICE_MANAGER="systemctl"
                OS_FAMILY="debian"
                print_info "Обнаружен Ubuntu $OS_VERSION"
                ;;
            *)
                print_error "Неподдерживаемый дистрибутив: $OS_ID"
                print_error "Поддерживаются: ALT Linux, Debian, Ubuntu"
                exit 1
                ;;
        esac
    else
        print_error "Не удалось определить дистрибутив"
        exit 1
    fi
}

# Установка зависимостей для ALT Linux
install_deps_altlinux() {
    print_step "Установка зависимостей для ALT Linux"
    
    # Обновление репозиториев
    apt-get update
    
    # Общие утилиты
    apt-get install -y git xxd wget fping hwdata
    
    # База данных
    apt-get install -y mariadb-server mariadb-client
    
    # Веб-сервер и PHP
    apt-get install -y apache2 \
        php8.2 php8.2-mysqlnd php8.2-intl php8.2-mbstring \
        pear-Mail php8.2-snmp php8.2-zip \
        php8.2-pgsql php8.2-mysqlnd php8.2-pdo_mysql php8.2-mysqlnd-mysqli
    
    # Perl модули
    apt-get install -y perl perl-Net-Patricia perl-NetAddr-IP \
        perl-Config-Tiny perl-Net-DNS perl-DateTime perl-Net-Ping \
        perl-Net-Netmask perl-Text-Iconv perl-Net-SNMP \
        perl-Net-Telnet perl-DBI perl-DBD-mysql perl-DBD-Pg \
        perl-Parallel-ForkManager perl-Proc-Daemon \
        perl-DateTime-Format-DateParse \
        perl-Net-OpenSSH perl-File-Tail perl-Crypt-Rijndael \
        perl-Crypt-CBC perl-CryptX perl-Crypt-DES \
        perl-File-Path-Tiny perl-Expect \
        perl-Proc-ProcessTable

    # Дополнительные сервисы
    apt-get install -y dnsmasq syslog-ng syslog-ng-journal
    
    # Установка pwgen если нет
    if ! command -v pwgen &> /dev/null; then
        apt-get install -y pwgen
    fi
    
    control fping public
    control ping public
}

# Установка зависимостей для Debian/Ubuntu
install_deps_debian() {
    print_step "Установка зависимостей для Debian/Ubuntu"
    
    # Обновление репозиториев
    apt-get update
    
    # Общие утилиты
    apt-get install -y git xxd bsdmainutils pwgen wget fping ieee-data
    
    # База данных
    apt-get install -y mariadb-server mariadb-client
    
    # Веб-сервер и PHP
    apt-get install -y apache2 libapache2-mod-fcgid \
        php php-mysql php-bcmath php-intl php-mbstring \
        php-date php-mail php-snmp php-zip php-fpm \
        php-db php-pgsql
    
    # Perl модули
    apt-get install -y perl libnet-patricia-perl libnetaddr-ip-perl \
        libconfig-tiny-perl libnet-dns-perl libdatetime-perl \
        libnet-netmask-perl libtext-iconv-perl libnet-snmp-perl \
        libnet-telnet-perl libdbi-perl libdbd-mysql-perl \
        libparallel-forkmanager-perl libproc-daemon-perl \
        libdatetime-format-dateparse-perl \
        libnet-openssh-perl libfile-tail-perl libcrypt-rijndael-perl \
        libcrypt-cbc-perl libcryptx-perl libdbd-pg-perl \
        libfile-path-tiny-perl libexpect-perl libcrypt-des-perl
    
    # Дополнительные сервисы
    apt-get install -y dnsmasq syslog-ng
}

# Обновление системы
update_system() {
    print_step "Обновление системы"
    $PACKAGE_MANAGER update -y
}

# Установка пакетов
install_packages() {
    print_step "Установка пакетов"
    
    case $OS_FAMILY in
        alt)
            install_deps_altlinux
            ;;
        debian)
            install_deps_debian
            ;;
    esac
}

# Создание пользователя и группы
create_user_group() {
    print_step "Создание пользователя и группы"
    
    # Создание группы
    if ! getent group eye >/dev/null; then
        groupadd --system eye
        print_info "Создана группа eye"
    else
        print_info "Группа eye уже существует"
    fi
    
    # Создание пользователя
    if ! id -u eye >/dev/null 2>&1; then
        if [[ "$OS_FAMILY" == "alt" ]]; then
            # Для ALT Linux
            useradd --system --shell /bin/bash --home-dir /opt/Eye \
                --gid eye --groups eye eye
        else
            # Для Debian/Ubuntu
            adduser --system --disabled-password --disabled-login \
                --ingroup eye --home=/opt/Eye eye
        fi
        print_info "Создан пользователь eye"
    else
        print_info "Пользователь eye уже существует"
    fi
    
    # Создание директории
    mkdir -p /opt/Eye
    chown eye:eye /opt/Eye
    chmod 770 /opt/Eye
    
    # Добавление nagios в группу eye (если существует)
    if id -u nagios >/dev/null 2>&1; then
        usermod -a -G eye nagios
        print_info "Пользователь nagios добавлен в группу eye"
    fi
}

# Проверка и применение патча для SNMP SHA512
apply_snmp_patch() {
    print_info "Проверка поддержки SNMPv3 SHA512..."
    
    # Пути к файлам
    USM_PATCH_FILE="/opt/Eye/docs/patches/sha512.patch"
    if [[ "$OS_FAMILY" == "alt" ]]; then
        USM_PATCH_FILE="/opt/Eye/docs/patches/sha512.alt.patch"
	fi

    USM_PM_FILE=""
    
    # Поиск файла USM.pm в системе
    local usm_paths=(
        "/usr/share/perl5/Net/SNMP/Security/USM.pm"
        "/usr/lib/perl5/vendor_perl/Net/SNMP/Security/USM.pm"
        "/usr/local/share/perl5/Net/SNMP/Security/USM.pm"
    )
    
    for path in "${usm_paths[@]}"; do
        if [[ -f "$path" ]]; then
            USM_PM_FILE="$path"
            print_info "Найден USM.pm: $USM_PM_FILE"
            break
        fi
    done
    
    if [[ -z "$USM_PM_FILE" ]]; then
        print_warn "Файл USM.pm не найден в системе"
        return 1
    fi

    # Проверка, уже ли применен патч
    if grep -q "AUTH_PROTOCOL_HMACSHA512" "$USM_PM_FILE"; then
        print_info "Патч SHA512 уже применен"
        return 0
    fi
    
    # Создание резервной копии
    cp "$USM_PM_FILE" "${USM_PM_FILE}.backup"
    print_info "Создана резервная копия: ${USM_PM_FILE}.backup"
    
    # Попытка применить patch файл
    local patch_applied=false
    
    if [[ -f "$USM_PATCH_FILE" ]]; then
        print_info "Попытка применить патч из $USM_PATCH_FILE"
        
        # Проверка возможности применить патч
        if patch --dry-run -l -p1 -i "$USM_PATCH_FILE" -r /tmp/patch.rej "$USM_PM_FILE" 2>/dev/null; then
            # Применяем патч
            if patch -l -p1 -i "$USM_PATCH_FILE" "$USM_PM_FILE" 2>/dev/null; then
                print_info "Патч успешно применен!"
                patch_applied=true
            else
                print_warn "Не удалось применить патч (dry-run прошел, но реальное применение не удалось)"
            fi
        else
            print_warn "Патч не может быть применен автоматически (несоответствие версий)"
            
            # Проверка отличий
            print_info "Проверка отличий в патче..."
            if [[ -f "/opt/Eye/docs/patches/USM.pm" ]]; then
                diff -u "$USM_PM_FILE" "/opt/Eye/docs/patches/USM.pm" > /tmp/usm.diff 2>/dev/null || true
                
                if [[ -s /tmp/usm.diff ]]; then
                    print_warn "Обнаружены отличия в файле USM.pm"
                    echo "Отличия:"
                    head -20 /tmp/usm.diff
                    echo "..."
                fi
            fi
        fi
    fi
    
    # Если патч не применился, спрашиваем пользователя
    if [[ "$patch_applied" == false ]]; then
        echo ""
        print_warn "Автоматическое применение патча не удалось"
        print_warn "Для работы SNMPv3 с SHA512 требуется модифицировать файл USM.pm"
        echo ""
        
        read -p "Требуется ли поддержка SNMPv3 с SHA512? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            # Пробуем заменить файл целиком
            if [[ -f "/opt/Eye/docs/patches/USM.pm" ]]; then
                print_info "Замена файла USM.pm целиком..."
                
                # Проверка совместимости версий
                local original_ver=$(grep -i "version" "$USM_PM_FILE" | head -1)
                local patch_ver=$(grep -i "version" "/opt/Eye/docs/patches/USM.pm" | head -1)
                
                if [[ -n "$original_ver" && -n "$patch_ver" ]]; then
                    print_info "Версия оригинального файла: $original_ver"
                    print_info "Версия патча: $patch_ver"
                fi
                
                # Создаем дополнительную резервную копию
                cp "$USM_PM_FILE" "${USM_PM_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
                
                # Заменяем файл
                cp -f "/opt/Eye/docs/patches/USM.pm" "$USM_PM_FILE"
                
                # Проверяем успешность замены
                if grep -q "SHA-512" "$USM_PM_FILE"; then
                    print_info "Файл USM.pm успешно заменен, поддержка SHA512 добавлена"
                    
                    # Сохраняем информацию о замене
                    echo "Файл USM.pm был заменен для поддержки SHA512" > "${USM_PM_FILE}.replaced"
                    echo "Оригинальный файл сохранен как: ${USM_PM_FILE}.backup" >> "${USM_PM_FILE}.replaced"
                    echo "Дата замены: $(date)" >> "${USM_PM_FILE}.replaced"
                    
                    return 0
                else
                    print_error "Не удалось добавить поддержку SHA512 после замены файла"
                    # Восстанавливаем из резервной копии
                    cp "${USM_PM_FILE}.backup" "$USM_PM_FILE"
                    return 1
                fi
            else
                print_error "Файл USM.pm с патчем не найден в /opt/Eye/docs/patches/"
                return 1
            fi
        else
            print_info "Поддержка SNMPv3 SHA512 отключена"
            return 0
        fi
    fi
    
    return 0
}

# Загрузка и копирование исходного кода
install_source_code() {
    print_step "Установка исходного кода Eye"
    
    # Создание структуры каталогов
    print_info "Создание структуры каталогов..."
    mkdir -p /opt/Eye/scripts/cfg
    mkdir -p /opt/Eye/scripts/log
    mkdir -p /opt/Eye/html/cfg
    mkdir -p /opt/Eye/html/js
    mkdir -p /opt/Eye/docs

    chmod -R 755 /opt/Eye/html
    chmod -R 770 /opt/Eye/scripts/log
    chmod 750 /opt/Eye/scripts

    # Копирование файлов
    print_info "Копирование файлов..."
    cp -R scripts/ /opt/Eye/
    cp -R html/ /opt/Eye/
    cp -R docs/ /opt/Eye/

    # Настройка прав
    chown -R eye:eye /opt/Eye

    # применение патча для SNMP SHA512
    apply_snmp_patch
}

# Загрузка дополнительных скриптов
download_additional_scripts() {
    print_step "Загрузка дополнительных скриптов"
    
    # Создание директорий
    mkdir -p /opt/Eye/html/js/jq
    mkdir -p /opt/Eye/html/js/select2
    mkdir -p /opt/Eye/html/js/jstree
    
    # Загрузка jQuery
    print_info "Загрузка jQuery..."
    if ! wget -q https://code.jquery.com/jquery-3.7.0.min.js \
        -O /opt/Eye/html/js/jq/jquery.min.js; then
        print_warn "Не удалось загрузить jQuery, попытка альтернативного источника..."
        wget -q https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js \
            -O /opt/Eye/html/js/jq/jquery.min.js || \
        print_error "Не удалось загрузить jQuery"
    fi
    
    # Загрузка Select2
    print_info "Загрузка Select2..."
    if wget -q https://github.com/select2/select2/archive/4.0.12.tar.gz -O 4.0.12.tar.gz; then
        tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ \
            --strip-components=2 select2-4.0.12/dist 2>/dev/null || \
        tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ \
            --strip-components=1 select2-4.0.12/dist 2>/dev/null
        rm -f 4.0.12.tar.gz
    else
        print_warn "Не удалось загрузить Select2"
    fi
    
    # Загрузка jsTree
    print_info "Загрузка jsTree..."
    if wget -q https://github.com/vakata/jstree/archive/3.3.12.tar.gz -O jstree.tar.gz; then
        tar -xzf jstree.tar.gz -C /opt/Eye/html/js/
        mv /opt/Eye/html/js/jstree-3.3.12/dist/* /opt/Eye/html/js/jstree
        rm -rf /opt/Eye/html/js/jstree-3.3.12
        rm -f jstree.tar.gz
    else
        print_warn "Не удалось загрузить jsTree"
    fi
    
    # Настройка прав
    chown -R eye:eye /opt/Eye/html/js
}

# Настройка MySQL
setup_mysql() {
    print_step "Настройка MySQL"
    
    # Запуск и включение службы
    $SERVICE_MANAGER enable mariadb 2>/dev/null || \
    $SERVICE_MANAGER enable mysql 2>/dev/null || true
    
    $SERVICE_MANAGER start mariadb 2>/dev/null || \
    $SERVICE_MANAGER start mysql 2>/dev/null || true
    
    # Проверка доступа к MySQL
    if ! command -v mysql &> /dev/null; then
        print_error "MySQL клиент не установлен"
        return 1
    fi
    
    MYSQL_OPT="-u root"
    
    # Проверяем доступ без пароля
    if mysql -u root -e "SELECT 1;" 2>/dev/null; then
        print_info "MySQL доступен с пустым паролем"
        echo ""
        print_warn "ВАЖНО: Нужно настроить пароль root для MySQL!"
        print_warn "После установки запустите: mysql_secure_installation"
        echo ""
    else
        # Запрашиваем пароль и создаем конфиг файл
        read -p "Введите пароль пользователя root MySQL: " DB_ROOT_PASSWORD
        echo ""
        
        # Создаем временный конфиг файл
        MYSQL_CNF_FILE="/tmp/mysql_root_eye.cnf"
        echo "[client]" > "$MYSQL_CNF_FILE"
        echo "user=root" >> "$MYSQL_CNF_FILE"
        echo "password=$DB_ROOT_PASSWORD" >> "$MYSQL_CNF_FILE"
        chmod 600 "$MYSQL_CNF_FILE"
        
        # Проверяем подключение
        if mysql --defaults-extra-file="$MYSQL_CNF_FILE" -e "SELECT 1;" &>/dev/null; then
            print_info "Успешное подключение к MySQL"
            MYSQL_OPT="--defaults-extra-file=$MYSQL_CNF_FILE"
        else
            print_error "Неверный пароль root MySQL"
            rm -f "$MYSQL_CNF_FILE"
            return 1
        fi
    fi
    
    read -p "Создать базу данных и пользователя для Eye? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warn "Создание БД пропущено. Создайте вручную:"
        print_warn "  mysql -u root -p < /opt/Eye/docs/mysql/create_db.sql"
        print_warn "  mysql -u root -p stat < /opt/Eye/docs/mysql/latest-mysql-ru.sql"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 0
    fi
    
    # Генерация пароля для пользователя stat
    DB_PASSWORD=$(pwgen 16 1)
    MYSQL_PASSWORD=$DB_PASSWORD
    
    print_info "Импорт структуры базы данных..."
    
    # Импорт основного SQL файла
    mysql $MYSQL_OPT < /opt/Eye/docs/mysql/create_db.sql
    
    if [[ $? -ne 0 ]]; then
        print_error "Ошибка при импорте create_db.sql"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi
    
    print_info "Структура базы данных импортирована"
    
    # Импорт данных
    print_info "Импорт начальных данных..."
    mysql $MYSQL_OPT stat < /opt/Eye/docs/mysql/latest-mysql-ru.sql
    
    if [[ $? -ne 0 ]]; then
        print_warn "Ошибка при импорте latest-mysql-ru.sql (возможно данные уже существуют)"
    else
        print_info "Начальные данные импортированы"
    fi
    
    # Создание пользователя stat
    print_info "Создание пользователя stat..."
    mysql $MYSQL_OPT <<EOF
CREATE USER IF NOT EXISTS 'stat'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON stat.* TO 'stat'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    if [[ $? -ne 0 ]]; then
        print_error "Ошибка при создании пользователя stat"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi
    
    print_info "Пользователь 'stat' успешно создан"
    
    # Сохранение информации о паролях
    echo "Пароль пользователя MySQL 'stat': $DB_PASSWORD" > /root/eye_mysql_password.txt
    chmod 600 /root/eye_mysql_password.txt
    
    print_info "Пароль пользователя 'stat': $DB_PASSWORD"
    print_warn "Пароль сохранен в /root/eye_mysql_password.txt"
    
    # Очистка временного файла если он был создан
    if [[ -f "$MYSQL_CNF_FILE" ]]; then
        rm -f "$MYSQL_CNF_FILE"
    fi
    
    return 0
}

# Настройка конфигурационных файлов
setup_configs() {
    print_step "Настройка конфигурационных файлов"
    
    # Копирование конфигурационных файлов
    if [[ -f "/opt/Eye/html/cfg/config.sample.php" ]]; then
        cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php
    fi
    
    if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
        cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config
    fi
    
    # Генерация ключей шифрования
    print_info "Генерация ключей шифрования..."
    if command -v pwgen &> /dev/null; then
        ENC_PASSWORD=$(pwgen 16 1)
    else
        ENC_PASSWORD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c16)
    fi
    
    ENC_VECTOR=$(tr -dc 0-9 </dev/urandom | head -c 16)
    
    # Настройка config.php
    if [[ -f "/opt/Eye/html/cfg/config.sample.php" ]]; then
        cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php
    
        # Обновляем пароль БД
        if [[ -n "$MYSQL_PASSWORD" ]]; then
            sed -i "s/define(\"DB_PASS\",\"[^\"]*\");/define(\"DB_PASS\",\"$MYSQL_PASSWORD\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_NAME\",\"[^\"]*\");/define(\"DB_NAME\",\"stat\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_USER\",\"[^\"]*\");/define(\"DB_USER\",\"stat\");/" /opt/Eye/html/cfg/config.php
        fi
    
        # Обновляем ключ шифрования
        sed -i "s/ENCRYPTION_KEY\",\"[^\"]*\"/ENCRYPTION_KEY\",\"$ENC_PASSWORD\"/" /opt/Eye/html/cfg/config.php
        sed -i "s/ENCRYPTION_KEY','[^']*'/ENCRYPTION_KEY','$ENC_PASSWORD'/" /opt/Eye/html/cfg/config.php
    
        # Обновляем вектор инициализации
        sed -i "s/ENCRYPTION_IV\",\"[^\"]*\"/ENCRYPTION_IV\",\"$ENC_VECTOR\"/" /opt/Eye/html/cfg/config.php
        sed -i "s/ENCRYPTION_IV','[^']*'/ENCRYPTION_IV','$ENC_VECTOR'/" /opt/Eye/html/cfg/config.php
    
        print_info "Конфигурационный файл config.php создан из шаблона"
    fi
    
    # Настройка config для скриптов
    if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
        cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config
    
        # Обновляем пароль БД
        if [[ -n "$MYSQL_PASSWORD" ]]; then
            sed -i "s/^DBPASS=.*/DBPASS=$MYSQL_PASSWORD/" /opt/Eye/scripts/cfg/config
            sed -i "s/DBPASS=mysql_password/DBPASS=$MYSQL_PASSWORD/" /opt/Eye/scripts/cfg/config
        fi
    
        # Обновляем имя пользователя БД
        sed -i "s/^DBUSER=.*/DBUSER=stat/" /opt/Eye/scripts/cfg/config
        sed -i "s/DBUSER=mysql_user/DBUSER=stat/" /opt/Eye/scripts/cfg/config
    
        # Обновляем имя БД
        sed -i "s/^DBNAME=.*/DBNAME=stat/" /opt/Eye/scripts/cfg/config
        sed -i "s/DBNAME=mysql_database/DBNAME=stat/" /opt/Eye/scripts/cfg/config
    
        # Обновляем ключ шифрования
        sed -i "s/^encryption_key=.*/encryption_key=$ENC_PASSWORD/" /opt/Eye/scripts/cfg/config
        sed -i "s/encryption_key=!!!CHANGE_ME!!!!/encryption_key=$ENC_PASSWORD/" /opt/Eye/scripts/cfg/config
    
        # Обновляем вектор инициализации
        sed -i "s/^encryption_iv=.*/encryption_iv=$ENC_VECTOR/" /opt/Eye/scripts/cfg/config
        sed -i "s/encryption_iv=0123456789012345/encryption_iv=$ENC_VECTOR/" /opt/Eye/scripts/cfg/config
    
        print_info "Конфигурационный файл scripts/cfg/config создан из шаблона"
    fi
    
    # Настройка прав
    chown -R eye:eye /opt/Eye/html/cfg /opt/Eye/scripts/cfg
    chmod 660 /opt/Eye/html/cfg/config.php /opt/Eye/scripts/cfg/config
    chmod 750 /opt/Eye/html/cfg /opt/Eye/scripts/cfg
    
    print_info "Ключи шифрования сгенерированы"
    print_info "Пароль: $ENC_PASSWORD"
    print_info "Вектор: $ENC_VECTOR"
}

# Настройка Apache и PHP
setup_apache_php() {
    print_step "Настройка Apache и PHP"
    
    # Определение версии PHP
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "8.1")
    
    # Настройка PHP для всех дистрибутивов
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
    
    # Настраиваем Apache
    if [[ -f "/opt/Eye/docs/apache/000-default.conf" ]]; then
        print_info "Используем готовый шаблон сайта"

        # Создаём директории
        mkdir -p "$APACHE_SITES_DIR"

        # Копируем дефалтный конфиг
        cp "/opt/Eye/docs/apache/000-default.conf" "$DEFAULT_CONF"

        # Включаем сайт
        if [[ -f "$APACHE_CONF_DIR/sites-enabled/000-default.conf" ]]; then
            rm -f "$APACHE_CONF_DIR/sites-enabled/000-default.conf"
            ln -sf "$DEFAULT_CONF" "$APACHE_CONF_DIR/sites-enabled/000-default.conf"
        fi
    fi

    # Настраиваем sudoers
    if [[ -f "/opt/Eye/docs/sudoers.d/www-data" ]]; then
        # Используем подготовленный шаблон для корректного юзера
        sed "s/www-data/eye/g" /opt/Eye/docs/sudoers.d/www-data > /etc/sudoers.d/eye
        chmod 440 /etc/sudoers.d/eye
        print_info "Sudoers создан из шаблона"
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

    print_info "Apache настроен, sudoers user: $APACHE_USER"
    print_info "Apache service: $APACHE_SERVICE"

    print_info "Настраиваем php-fpm${PHP_VERSION}"

    # Configure php-fpm
    if [[ -f "/opt/Eye/docs/php-fpm/eye.conf" ]]; then
        print_info "Используем подготовленный php-fpm шаблон"
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
        httpd2 -t 2>/dev/null && print_info "Конфигурация Apache (httpd2) корректна" || print_warn "Проверьте конфигурацию Apache "
        else
        apache2ctl -t 2>/dev/null && print_info "Конфигурация Apache (httpd2) корректна" || print_warn "Проверьте конфигурацию Apache "
    fi
}


# Настройка cron и logrotate
setup_cron_logrotate() {
    print_step "Настройка cron и logrotate"
    
    # Cron
    if [[ -f "/opt/Eye/docs/cron/stat" ]]; then
        cp /opt/Eye/docs/cron/stat /etc/cron.d/eye
        chmod 644 /etc/cron.d/eye
        print_info "Cron задача добавлена: /etc/cron.d/eye"
    fi
    
    # Logrotate
    if [[ -f "/opt/Eye/docs/logrotate/dnsmasq" ]]; then
        cp /opt/Eye/docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq-eye
    fi
    
    if [[ -f "/opt/Eye/docs/logrotate/scripts" ]]; then
        cp /opt/Eye/docs/logrotate/scripts /etc/logrotate.d/eye-scripts
    fi
    
    print_info "Настройка cron и logrotate завершена"
    print_warn "Отредактируйте /etc/cron.d/eye для включения нужных скриптов"
}

# Настройка DHCP сервера (dnsmasq)
setup_dhcp_server() {
    print_step "Настройка DHCP сервера"
    
    read -p "Настроить DHCP сервер (dnsmasq)? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return 0
    fi
    
    # Резервная копия конфигурации
    if [[ -f "/etc/dnsmasq.conf" ]]; then
        cp /etc/dnsmasq.conf /etc/dnsmasq.conf.backup
    fi
    
    # Копирование конфигурации из Eye
    if [[ -f "/opt/Eye/docs/addons/dnsmasq.conf" ]]; then
        cat /opt/Eye/docs/addons/dnsmasq.conf > /etc/dnsmasq.conf
    fi
    
    # Копирование systemd сервисов
    if [[ -f "/opt/Eye/docs/systemd/dhcp-log.service" ]]; then
        cp /opt/Eye/docs/systemd/dhcp-log.service /etc/systemd/system/
    fi
    
    if [[ -f "/opt/Eye/docs/systemd/dhcp-log-truncate.service" ]]; then
        cp /opt/Eye/docs/systemd/dhcp-log-truncate.service /etc/systemd/system/
    fi
    
    # Включение сервисов
    $SERVICE_MANAGER enable dnsmasq
    $SERVICE_MANAGER start dnsmasq
    
    print_info "DHCP сервер настроен"
    print_warn "Отредактируйте /etc/dnsmasq.conf под вашу сеть"
}

# Настройка syslog-ng
setup_syslog() {
    print_step "Настройка syslog-ng"
    
    read -p "Настроить удаленный сбор логов (syslog-ng)? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return 0
    fi
    
    # Создаем резервную копию основного конфига
    if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
        cp /etc/syslog-ng/syslog-ng.conf /etc/syslog-ng/syslog-ng.conf.backup
        print_info "Создана резервная копия /etc/syslog-ng/syslog-ng.conf"
    fi
    
    # Копируем дополнительный конфиг для Eye
    if [[ -f "/opt/Eye/docs/syslog-ng/eye.conf" ]]; then
        mkdir -p /etc/syslog-ng/conf.d
        cp /opt/Eye/docs/syslog-ng/eye.conf /etc/syslog-ng/conf.d/eye.conf
        
        # Проверяем, есть ли уже включение conf.d в основном конфиге
        if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
            if ! grep -q "@include.*conf\.d" /etc/syslog-ng/syslog-ng.conf && \
               ! grep -q "include.*conf\.d" /etc/syslog-ng/syslog-ng.conf; then
                # Добавляем включение conf.d директории в конец файла
                echo "" >> /etc/syslog-ng/syslog-ng.conf
                echo "# Include Eye monitoring configuration" >> /etc/syslog-ng/syslog-ng.conf
                echo "@include \"/etc/syslog-ng/conf.d/*.conf\"" >> /etc/syslog-ng/syslog-ng.conf
                print_info "Добавлено включение conf.d директории в syslog-ng.conf"
            fi
        fi
        print_info "Конфигурационный файл eye.conf скопирован в /etc/syslog-ng/conf.d/"
    else
        print_warn "Файл конфигурации eye.conf не найден в /opt/Eye/docs/syslog-ng/"
    fi
    
# блок options
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

    # Проверяем наличие options в основном конфиге
    if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
        if ! grep -q "^options\s*{" /etc/syslog-ng/syslog-ng.conf; then
            # Добавляем блок options если его нет

            if grep -q "^@version:" /etc/syslog-ng/syslog-ng.conf; then
                # Вставляем после строки @version:
                sed -i "/^@version:/a\\$syslogng_options" /etc/syslog-ng/syslog-ng.conf
            else
                # Вставляем в начало файла
                sed -i "1i\\$syslogng_options" /etc/syslog-ng/syslog-ng.conf
            fi
            print_info "Добавлен блок options в syslog-ng.conf"
        else
            # Проверяем наличие необходимых параметров в существующем блоке options
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
            
            # Добавляем недостающие параметры
            if [[ ${#missing_params[@]} -gt 0 ]]; then
                # Находим блок options и добавляем параметры в конец блока
                sed -i '/^options\s*{/,/^}/ {
                    /^}/ i\    '"$(IFS='; '; echo "${missing_params[*]}")"';
                }' /etc/syslog-ng/syslog-ng.conf
                print_info "Добавлены параметры в блок options: ${missing_params[*]}"
            fi
        fi
    fi
    
    # Копирование systemd сервиса для обработки логов Eye
    if [[ -f "/opt/Eye/docs/systemd/syslog-stat.service" ]]; then
        cp /opt/Eye/docs/systemd/syslog-stat.service /etc/systemd/system/
        chmod 644 /etc/systemd/system/syslog-stat.service
        print_info "Сервис syslog-stat скопирован"
    fi
    
    # Создаем директорию для логов если её нет
    mkdir -p /opt/Eye/scripts/log
    chown eye:eye /opt/Eye/scripts/log
    chmod 770 /opt/Eye/scripts/log
    
    # Включение и запуск сервисов
    $SERVICE_MANAGER daemon-reload
    
    if $SERVICE_MANAGER enable syslog-ng; then
        print_info "Сервис syslog-ng включен в автозагрузку"
    else
        print_warn "Не удалось включить syslog-ng в автозагрузку"
    fi
    
    if $SERVICE_MANAGER restart syslog-ng; then
        print_info "Сервис syslog-ng перезапущен"
    else
        print_warn "Не удалось перезапустить syslog-ng"
    fi
    
    if [[ -f "/etc/systemd/system/syslog-stat.service" ]]; then
        if $SERVICE_MANAGER enable syslog-stat; then
            print_info "Сервис syslog-stat включен в автозагрузку"
        else
            print_warn "Не удалось включить syslog-stat в автозагрузку"
        fi
        
        if $SERVICE_MANAGER start syslog-stat; then
            print_info "Сервис syslog-stat запущен"
        else
            print_warn "Не удалось запустить syslog-stat"
        fi
    fi
    
    # Проверка конфигурации syslog-ng
    if command -v syslog-ng &> /dev/null; then
        if syslog-ng --syntax-only; then
            print_info "Конфигурация syslog-ng корректна"
        else
            print_error "Ошибка в конфигурации syslog-ng"
            print_warn "Проверьте файлы: /etc/syslog-ng/syslog-ng.conf и /etc/syslog-ng/conf.d/eye.conf"
        fi
    fi
    
    print_info "Настройка syslog-ng завершена"
    print_info "Для приема логов с устройств настройте их на отправку на IP: $(hostname -f)"
}

# Настройка дополнительных сервисов
setup_additional_services() {
    print_step "Настройка дополнительных сервисов"
    
    # Сервис stat-sync
    if [[ -f "/opt/Eye/docs/systemd/stat-sync.service" ]]; then
        cp /opt/Eye/docs/systemd/stat-sync.service /etc/systemd/system/
        $SERVICE_MANAGER enable stat-sync.service
        print_info "Сервис stat-sync включен"
    fi
    
    # Сервис eye-statd (NetFlow)
    if [[ -f "/opt/Eye/docs/systemd/eye-statd.service" ]]; then
        cp /opt/Eye/docs/systemd/eye-statd.service /etc/systemd/system/
        $SERVICE_MANAGER enable eye-statd.service
        print_info "Сервис eye-statd (NetFlow) включен"
    fi
    
    # Настройка DHCP
    setup_dhcp_server
    
    # Настройка syslog
    setup_syslog
}

# Импорт базы MAC-адресов
import_mac_database() {
    print_step "Импорт базы MAC-адресов"
    
    if [[ -f "/opt/Eye/scripts/utils/mac-oids/download-macs.sh" ]]; then
        cd /opt/Eye/scripts/utils/mac-oids/

        # Загрузка базы MAC
        print_info "Загрузка базы MAC-адресов..."
        bash download-macs.sh
        
        # Обновление вендоров
        if [[ -f "update-mac-vendors.pl" ]]; then
            print_info "Обновление информации о вендорах..."
            perl update-mac-vendors.pl
        fi
        
        cd - >/dev/null
    else
        print_warn "Скрипты для импорта MAC-адресов не найдены"
    fi
}

# Финальные инструкции
show_final_instructions() {
    echo ""
    echo -e "${GREEN}===========================================${NC}"
    echo -e "${GREEN}   УСТАНОВКА ЗАВЕРШЕНА УСПЕШНО!          ${NC}"
    echo -e "${GREEN}===========================================${NC}"
    echo ""
    echo "СИСТЕМНАЯ ИНФОРМАЦИЯ:"
    echo "  Дистрибутив:      $OS_NAME"
    echo "  Версия:           $OS_VERSION"
    echo "  Пользователь:     eye"
    echo "  Директория:       /opt/Eye"
    echo ""
    echo "ДЛЯ ЗАВЕРШЕНИЯ НАСТРОЙКИ ВЫПОЛНИТЕ:"
    echo ""
    echo "1. Настройте безопасность MySQL:"
    echo "   mysql_secure_installation"
    echo ""
    echo "2. Проверьте и отредактируйте конфигурационные файлы:"
    echo "   /opt/Eye/html/cfg/config.php"
    echo "   /opt/Eye/scripts/cfg/config"
    echo ""
    if [[ -f "/root/eye_mysql_password.txt" ]]; then
        echo "3. Пароль MySQL пользователя 'stat' сохранен в:"
        echo "   /root/eye_mysql_password.txt"
        echo ""
    fi
    echo "4. Настройте cron задачи:"
    echo "   nano /etc/cron.d/eye"
    echo "   Раскомментируйте нужные скрипты"
    echo ""
    echo "5. Настройте при необходимости:"
    echo "   - DHCP: /etc/dnsmasq.conf"
    echo "   - NetFlow: настройте на сетевых устройствах"
    echo ""
    echo "6. ДОСТУП К ВЕБ-ИНТЕРФЕЙСУ:"
    echo "   URL:      http://$(hostname -f)/"
    echo "   Админка:  http://$(hostname -f)/admin/"
    echo "   Логин:    admin"
    echo "   Пароль:   admin"
    echo ""
    echo -e "${RED}ВАЖНО:${NC}"
    echo "   - СМЕНИТЕ пароль администратора и API ключ!"
    echo "   - Настройте пользователей и сети в веб-интерфейсе"
    echo ""
    echo -e "${GREEN}===========================================${NC}"
    echo ""
}

# Главная функция
main() {
    clear
    echo -e "${GREEN}===========================================${NC}"
    echo -e "${GREEN}   Установка Eye Monitoring System        ${NC}"
    echo -e "${GREEN}   для ALT Linux/Debian/Ubuntu            ${NC}"
    echo -e "${GREEN}===========================================${NC}"
    echo ""
    
    # Глобальные переменные
    MYSQL_PASSWORD=""
    
    # Выполнение шагов установки
    check_root
    detect_distro
    update_system
    install_packages
    create_user_group
    install_source_code
    download_additional_scripts
    setup_mysql
    setup_configs
    setup_apache_php
    setup_cron_logrotate
    setup_additional_services
    import_mac_database
    
    show_final_instructions
}

# Обработка аргументов командной строки
case "$1" in
    --help|-h)
        echo "Использование: $0 [опции]"
        echo ""
        echo "Опции:"
        echo "  --help, -h     Показать эту справку"
        echo "  --auto         Автоматическая установка (минимальное взаимодействие)"
        echo ""
        echo "Поддерживаемые дистрибутивы:"
        echo "  - ALT Linux 11.1+"
        echo "  - Debian 11+"
        echo "  - Ubuntu 20.04+"
        echo ""
        exit 0
        ;;
    --auto)
        # Режим с минимальным взаимодействием
        print_warn "Автоматический режим. Все подтверждения будут приняты как 'yes'"
        export DEBIAN_FRONTEND=noninteractive
        ;;
    *)
        # Интерактивный режим по умолчанию
        ;;
esac

# Запуск установки
main "$@"

# Выход с кодом успеха
exit 0
