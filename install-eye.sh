#!/bin/bash
# Eye Installation Script for ALT Linux/Debian/Ubuntu with PostgreSQL support
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

# ---------------------------------------------------------------------------
# Small helpers: private temp dir, backups, random secrets, safe substitution
# ---------------------------------------------------------------------------

# All temporary files live in a private directory owned by root (0700).
# Never use fixed file names under /tmp: they can be pre-created as symlinks
# by a local user (the Eye web interface runs as an unprivileged service user).
WORKDIR=""

make_workdir() {
    [[ -n "$WORKDIR" ]] && return 0
    if ! WORKDIR=$(mktemp -d "${TMPDIR:-/tmp}/eye-install.XXXXXX"); then
        print_error "Cannot create temporary working directory"
        exit 1
    fi
    chmod 700 "$WORKDIR"
    return 0
}

cleanup_workdir() {
    if [[ -n "$WORKDIR" && -d "$WORKDIR" ]]; then
        rm -rf "$WORKDIR"
    fi
}

# Timestamped backup of a file we are about to modify. An existing backup is
# never overwritten, so repeated runs still keep the pristine original file.
backup_file() {
    local f="$1"
    [[ -e "$f" ]] || return 1

    local backup stamp i=1
    stamp=$(date +%Y-%m-%d_%H-%M-%S)
    backup="${f}.eye-backup.${stamp}"
    while [[ -e "$backup" ]]; do
        backup="${f}.eye-backup.${stamp}.${i}"
        i=$((i + 1))
    done

    if cp -a "$f" "$backup"; then
        LAST_BACKUP="$backup"
        print_info "Backup: $backup"
        return 0
    fi

    print_error "Failed to create backup of $f"
    return 1
}

# Generate a random secret. Only alphanumeric characters are used on purpose:
# these values end up in SQL literals, in a PHP config and in a key=value file.
gen_secret() {
    local len="${1:-16}"
    local val=""
    if command -v pwgen >/dev/null 2>&1; then
        val=$(pwgen -s "$len" 1)
    fi
    if [[ ${#val} -ne $len ]]; then
        val=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$len")
    fi
    printf '%s' "$val"
}

# Generate a random numeric string (the AES IV has to be exactly 16 digits).
gen_digits() {
    local len="${1:-16}"
    tr -dc '0-9' </dev/urandom | head -c "$len"
}

# Escape a value for use inside a single quoted SQL string literal.
# MySQL/MariaDB additionally treats backslash as an escape character unless
# NO_BACKSLASH_ESCAPES is set, so double it too.
sql_lit() {
    local v="${1//\'/\'\'}"
    printf "'%s'" "$v"
}

sql_lit_mysql() {
    local v="${1//\\/\\\\}"
    v="${v//\'/\'\'}"
    printf "'%s'" "$v"
}

# DB names/user names are SQL identifiers: keep them boring or the DDL below
# (and the pg_hba/grep matchers) becomes unreliable.
is_valid_db_ident() {
    [[ "$1" =~ ^[A-Za-z_][A-Za-z0-9_-]{0,62}$ ]]
}

# Read a mandatory identifier with validation.
read_db_ident() {
    local prompt="$1" var_name="$2" default_value="$3" value
    while true; do
        read -r -p "$prompt [$default_value]: " value
        : "${value:=$default_value}"
        if is_valid_db_ident "$value"; then
            printf -v "$var_name" '%s' "$value"
            return 0
        fi
        print_error "Invalid value: use letters, digits, '_' or '-' (max 63 chars, not starting with a digit)"
    done
}

# Write define("<name>","<value>") into a PHP config file.
# The new value is passed to perl via %ENV: neither sed nor the shell ever
# parses it, so '/', '&', '"', '$' and ';' in a password are harmless.
# Returns 1 (and changes nothing) if the define is not present in the file.
cfg_set_php() {
    local file="$1" name="$2" value="$3"
    CFG_FILE="$file" CFG_NAME="$name" CFG_VALUE="$value" perl -e '
        require File::Copy;
        local $/;
        open(my $in, "<", $ENV{CFG_FILE}) or die "cannot read $ENV{CFG_FILE}: $!\n";
        my $text = <$in>;
        close($in);
        my ($name, $value) = @ENV{qw(CFG_NAME CFG_VALUE)};
        $value =~ s/[\r\n]+//g;
        my $escaped = $value;
        # PHP reads the value out of a double quoted string, so escape the
        # backslash, the double quote and the dollar sign (interpolation).
        $escaped =~ s/([\\\"\x24])/\\$1/g;
        # Текущее значение может содержать уже экранированные \" и \\, поэтому
        # читаем его как PHP-строку в двойных кавычках: (?:[^"\\]|\\.)*
        my $hit = ($text =~ s{^([ \t]*define[ \t]*\([ \t]*")\Q$name\E("[ \t]*,[ \t]*")((?:[^"\\]|\\.)*)(")}
                             {$1 . $name . $2 . $escaped . $4}gem);
        exit(1) unless $hit;
        my $tmp = "$ENV{CFG_FILE}.tmp.$$";
        open(my $out, ">", $tmp) or die "cannot write $tmp: $!\n";
        print {$out} $text;
        close($out) or die "write failed: $!\n";
        File::Copy::move($tmp, $ENV{CFG_FILE}) or die "move failed: $!\n";
    ' || return 1
    return 0
}

# Write "<key>=<value>" into a key=value config file (Perl Config::Tiny format).
cfg_set_key() {
    local file="$1" key="$2" value="$3"
    CFG_FILE="$file" CFG_KEY="$key" CFG_VALUE="$value" perl -e '
        use Fcntl qw(:flock);
        local $/;
        open(my $in, "<", $ENV{CFG_FILE}) or die "cannot read $ENV{CFG_FILE}: $!\n";
        flock($in, LOCK_EX);
        my $text = <$in>;
        close($in);
        $text = "" unless defined $text;
        my ($key, $value) = @ENV{qw(CFG_KEY CFG_VALUE)};
        $value =~ s/[\r\n]+//g;
        my $hit = ($text =~ s{^([ \t]*\Q$key\E[ \t]*=)[^\n]*}{$1 . $value}gem);
        exit(1) unless $hit;
        open(my $out, ">", $ENV{CFG_FILE}) or die "cannot write $ENV{CFG_FILE}: $!\n";
        flock($out, LOCK_EX);
        print {$out} $text;
        close($out) or die "close failed: $!\n";
    ' || return 1
    return 0
}


# Check for root privileges
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        print_error "Use: sudo $0"
        exit 1
    fi
}

service_exists() {
    systemctl cat "$1.service" >/dev/null 2>&1
}

service_enabled() {
    systemctl is-enabled --quiet "$1.service"
}

safe_start_service() {
    local svc="$1"
    if service_exists "$svc"; then
        if systemctl start "$svc"; then
            print_info "Service ${svc} has been successfully started"
        else
            print_error "Failed to start ${svc}"
        fi
    fi
}

safe_stop_service() {
    local svc="$1"
    if service_exists "$svc"; then
        if systemctl stop "$svc"; then
            print_info "Service ${svc} has been successfully stopped"
        else
            print_error "Failed to stop ${svc}"
        fi
    fi
}

stop_eye() {
    print_step "Stopping services"
    local PHP_VERSION
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "")
    if [ -n "${PHP_VERSION}" ]; then
        safe_stop_service "php${PHP_VERSION}-fpm"
    fi

    # EYE_SERVICES_STOPPED включает аварийное восстановление в on_script_exit.
    for svc in cron eye-statd dhcp-log stat-sync syslog-stat; do
        safe_stop_service "$svc"
    done

    EYE_SERVICES_STOPPED=1
}

start_eye() {
    print_step "Starting services"
    local PHP_VERSION
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "")
    if [ -n "${PHP_VERSION}" ]; then
        safe_start_service "php${PHP_VERSION}-fpm"
    fi

    for svc in cron eye-statd dhcp-log stat-sync syslog-stat; do
        safe_start_service "$svc"
    done

    EYE_SERVICES_STOPPED=0
}

# Safety net: whatever killed the script (exit 1xx inside the DB setup, an
# interrupt, a failed step), the services stopped by stop_eye() come back up.
on_script_exit() {
    local rc=$?

    if [[ -n "${SERVICE_MANAGER:-}" && "${EYE_SERVICES_STOPPED:-0}" == "1" ]]; then
        print_warn "The script exited (code $rc) with Eye services stopped - starting them again"
        start_eye
    fi

    cleanup_workdir
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
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        DEFAULT_PORT=5432
    else
        DEFAULT_PORT=3306
    fi

    if [[ "$DB_INSTALL" == "local" ]]; then
        echo "Local Database Configuration"
        echo "============================"
        DB_HOST="127.0.0.1"
        DB_PORT="$DEFAULT_PORT"
        echo "Database server: $DB_HOST:$DB_PORT (local)"
    else
        echo "Remote Database Configuration"
        echo "============================"
        read -r -p "Database server IP address: " DB_HOST
        read -r -p "Database port [$DEFAULT_PORT]: " DB_PORT
        : "${DB_PORT:=$DEFAULT_PORT}"
    fi

    read_db_ident "Database name" DB_NAME eye
    read_db_ident "Database username" DB_USER eye
    read -r -e -p "Database user password [$DEF_PASS]: " DB_PASS || true
    : "${DB_PASS:=$DEF_PASS}"
    # A password must not contain newline/CR: both config files are line based
    DB_PASS=${DB_PASS//[$'\r\n']/}

    echo ""
    print_warn "The database password is not printed out - see the password file in /root after setup."
}

# Function for installation type selection
select_installation_type() {
    echo "Select installation type:"
    echo "1. Web interface + network backend"
    echo "2. Web interface only"
    echo "3. Network backend only"
    echo ""

    read -p "Enter selection number [1]: " install_type

    # alnum only: base64 passwords can contain '/' and '+', which used to
    # break the config substitution below ("/" collided with the sed delimiter)
    make_workdir
    DEF_PASS=$(gen_secret 16)

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
    : "${DB_NAME:=eye}"
    : "${DB_USER:=eye}"
    : "${DB_PASS:=$DEF_PASS}"
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
        apt-get install -y fping iptables ipset

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
            perl-Text-CSV perl-Log-Log4perl \
            perl-DBD-Pg perl-DBD-mysql
    fi

    # Дополнительные проверки (например, fping — нужны только бэкенду)
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        control fping public
    fi

    control ping public_caps
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
        apt-get install -y fping ipset netfilter-persistent

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
            libtext-csv-perl liblog-log4perl-perl \
            libdbd-pg-perl libdbd-mysql-perl
    fi

    # === Дополнительно (если нужно) ===
    # apt-get install -y bind9 bind9-utils bind9-host
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

    # Timestamped backup (never overwrites an existing one)
    backup_file "$USM_PM_FILE" || return 1

    # Try to apply patch file
    local patch_applied=false

    if [[ -f "$USM_PATCH_FILE" ]]; then
        print_info "Attempting to apply patch from $USM_PATCH_FILE"

        # Check if patch can be applied
        make_workdir
        if patch --dry-run -l -p1 -i "$USM_PATCH_FILE" -r "$WORKDIR/patch.rej" "$USM_PM_FILE" 2>/dev/null; then
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
                make_workdir
                diff -u "$USM_PM_FILE" "/opt/Eye/docs/patches/USM.pm" > "$WORKDIR/usm.diff" 2>/dev/null || true

                if [[ -s "$WORKDIR/usm.diff" ]]; then
                    print_warn "Differences found in USM.pm file"
                    echo "Differences:"
                    head -20 "$WORKDIR/usm.diff"
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

                # Create additional backup before full file replacement
                backup_file "$USM_PM_FILE"

                # Replace file
                cp -f "/opt/Eye/docs/patches/USM.pm" "$USM_PM_FILE"

                # Check if replacement successful
                if grep -q "SHA-512" "$USM_PM_FILE"; then
                    print_info "USM.pm file successfully replaced, SHA512 support added"

                    # Save replacement info
                    echo "USM.pm file was replaced for SHA512 support" > "${USM_PM_FILE}.replaced"
                    echo "Original file saved as: ${LAST_BACKUP:-$USM_PM_FILE.backup}" >> "${USM_PM_FILE}.replaced"
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

upgrade_source_code() {
    print_step "Upgrading Eye source code"

    mkdir -p /opt/Eye
    chown eye:eye /opt/Eye
    chmod 755 /opt/Eye

    # === Обновление документации ===
    if [ -d "docs" ]; then
        print_info "Updating documentation..."
        mkdir -p /opt/Eye/docs
        rsync -ar docs/ /opt/Eye/docs/
        chown -R eye:eye /opt/Eye/docs
    fi

    # === Обновление web-интерфейса ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        print_info "Updating web interface..."
        mkdir -p /opt/Eye/html/cfg /opt/Eye/html/js
        if [ -d "html" ]; then
            rsync -a --exclude cfg/ html/ /opt/Eye/html/
        fi
        download_additional_scripts
        chown -R eye:eye /opt/Eye/html
    fi

    # === Обновление backend ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        print_info "Updating backend scripts..."
        mkdir -p /opt/Eye/scripts/{cfg,log}
        rsync -a --existing --exclude='*/' scripts/ /opt/Eye/scripts/
        rsync -a scripts/eyelib/   /opt/Eye/scripts/eyelib/
        # Обновляем только если каталог установлен
        [[ -d /opt/Eye/scripts/updates ]] && rsync -a scripts/updates/ /opt/Eye/scripts/updates/
        [[ -d /opt/Eye/scripts/utils   ]] && rsync -a scripts/utils/   /opt/Eye/scripts/utils/

        chmod 750 /opt/Eye/scripts
        chmod 770 /opt/Eye/scripts/log
        chown -R eye:eye /opt/Eye/scripts

        install_ipset_utils

        declare -a SERVICES=(
            dhcp-log
            dhcp-log-truncate
            eye-statd
            stat-sync
            syslog-stat
        )

        SYSTEMD_CHANGED=0
        declare -a RESTART_SERVICES=()
        for svc in "${SERVICES[@]}"; do
            if ! service_exists "$svc"; then
                continue
            fi
            SRC="/opt/Eye/docs/systemd/$svc.service"
            [[ -f "$SRC" ]] || { print_info "SKIP $svc: source file missing"; continue; }
            DST="/etc/systemd/system/$svc.service"
            if [[ ! -f "$DST" ]] || ! cmp -s "$SRC" "$DST"; then
                print_info "Updating $svc"
                [[ -f "$DST" ]] && backup_file "$DST"
                cp -f "$SRC" "$DST"
                chmod 644 "$DST"
                SYSTEMD_CHANGED=1
                if service_enabled "$svc"; then
                    RESTART_SERVICES+=("$svc")
                    fi
                print_info "COPIED $SRC -> $DST"
            else
                print_info "SKIP $svc: file identical"
            fi
        done

        if [[ $SYSTEMD_CHANGED -eq 1 ]]; then
            systemctl daemon-reload
            for svc in "${RESTART_SERVICES[@]}"; do
                systemctl stop "$svc"
                systemctl start "$svc"
            done
        fi
    fi

    # === Патч SNMP ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        apply_snmp_patch
    fi
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
            [[ -f "/etc/systemd/system/stat-sync.service" ]] && backup_file "/etc/systemd/system/stat-sync.service"
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
        read -s -r -p "Enter MySQL root user password: " DB_ROOT_PASSWORD
        echo ""

        # Temporary config file inside the private (0700) work directory
        if ! MYSQL_CNF_FILE=$(mktemp "$WORKDIR/mysql_root.XXXXXX"); then
            print_error "Cannot create temporary MySQL config"
            return 1
        fi
        echo "[client]" > "$MYSQL_CNF_FILE"
        echo "user=root" >> "$MYSQL_CNF_FILE"
        echo "password=$DB_ROOT_PASSWORD" >> "$MYSQL_CNF_FILE"
        chmod 600 "$MYSQL_CNF_FILE"
        unset DB_ROOT_PASSWORD

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

    # === Проверка: существует ли база данных? ===
    if mysql $MYSQL_OPT -sN -e "SHOW DATABASES;" | grep -q "^${DB_NAME}$"; then
        print_error "Database '$DB_NAME' already exists. The script has been stopped."
        exit 120
        fi

    print_info "Create database..."

    # Import main SQL file
    mysql $MYSQL_OPT <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF
    if [[ $? -ne 0 ]]; then
        print_error "Error creating database ${DB_NAME}"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        exit 121
    fi

    print_info "Importing database structure..."

    mysql $MYSQL_OPT ${DB_NAME} < ${SQL_CREATE_FILE}

    if [[ $? -ne 0 ]]; then
        print_error "Error importing create_db.sql"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        exit 122
    fi

    print_info "Database structure imported"

    # Import data
    print_info "Importing initial data..."
    mysql $MYSQL_OPT ${DB_NAME} < ${SQL_DATA_FILE}

    if [[ $? -ne 0 ]]; then
        print_error "Error importing data.sql !!!"
        exit 123
    else
        print_info "Initial data imported"
    fi

    # Create db user
    print_info "Creating user ${DB_USER}.."
    # The web interface connects over TCP (PDO dsn host=...), so the account
    # has to be granted for both 'localhost' (socket) and '127.0.0.1' (TCP):
    # with skip_name_resolve=ON a 'localhost'-only account cannot log in.
    DB_PASS_SQL=$(sql_lit_mysql "$DB_PASS")
    mysql $MYSQL_OPT <<EOF
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY $DB_PASS_SQL;
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY $DB_PASS_SQL;
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
    unset DB_PASS_SQL

    if [[ $? -ne 0 ]]; then
        print_error "Error creating user $DB_USER"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        exit 124
    fi

    print_info "User $DB_USER successfully created"

    # Save password information (0600, readable only by root)
    DB_PASS_FILE="/root/eye_mysql_password.txt"
    if ! [[ -e "$DB_PASS_FILE" ]]; then
        ( umask 077; : > "$DB_PASS_FILE" )
    fi
    chmod 600 "$DB_PASS_FILE"
    cat > "$DB_PASS_FILE" <<EOF
Eye database account
  user:     $DB_USER
  host:     $DB_HOST:$DB_PORT
  database: $DB_NAME
  password: $DB_PASS
EOF
    print_warn "Password for '$DB_USER' saved in $DB_PASS_FILE (mode 0600), it is not printed to the screen"

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
        # ALT: root needs an explicit NOPASSWD rule to be able to run
        # "sudo -u postgres psql" without a password prompt, the whole
        # PostgreSQL setup below relies on it.
        make_workdir
        printf '%s\n' "root ALL=(ALL:ALL) NOPASSWD: ALL" > "$WORKDIR/sudoers.root"
        backup_file /etc/sudoers.d/root 2>/dev/null || true
        install -m 0440 -o root -g root "$WORKDIR/sudoers.root" /etc/sudoers.d/root

        PGDATA="/var/lib/pgsql/data"

        if [ -z "$(ls -A $PGDATA 2>/dev/null)" ]; then
            /etc/init.d/postgresql initdb

            # === ВАЖНО: настраиваем pg_hba.conf для безпарольного доступа ===
            local pg_hba_file="$PGDATA/pg_hba.conf"
            if [[ -f "$pg_hba_file" ]]; then
                # Резервная копия с меткой времени
                backup_file "$pg_hba_file"

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
        exit 110
    fi

    # === Проверка: существует ли БД? ===
    if sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw "^\s*${DB_NAME}\s*$"; then
        print_error "Database '$DB_NAME' already exists. The script has been stopped."
        exit 120
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

    # Определяем локаль на основе языка
    if [[ "$EYE_LANG" == "russian" ]]; then
        LC_TYPE="ru_RU.UTF-8"
    else
        LC_TYPE="en_US.UTF-8"
    fi

    print_info "Creating database '$DB_NAME' with locale '$LC_TYPE'..."

    # Set password for the Eye role. The statement is fed via stdin so the
    # password never shows up in the process list of a postgres backend.
    print_info "Setting password for user $DB_USER ..."
    sudo -u postgres psql -v ON_ERROR_STOP=1 <<EOSQL
CREATE USER $DB_USER WITH PASSWORD $(sql_lit "$DB_PASS");
EOSQL

    sudo -u postgres createdb \
      --encoding=UTF8 \
      --lc-collate="$LC_TYPE" \
      --lc-ctype="$LC_TYPE" \
      --template=template0 \
      --owner="$DB_USER" \
      "$DB_NAME"

    if [[ $? -ne 0 ]]; then
        print_error "Failed to create database"
        exit 121
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
        exit 122
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
            print_error "Warning: failed to import data (may already exist or non-critical)"
            exit 123
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
            # Timestamped backup of the original
            backup_file "$pg_hba_file"
            # Add local md5 authentication if not present
            if ! grep -q "local.*$DB_NAME.*md5" "$pg_hba_file"; then
                echo "local   $DB_NAME            $DB_USER                                    scram-sha-256" >> "$pg_hba_file"
                print_info "Added scram-sha-256 authentication for $DB_USER user in pg_hba.conf"
                fi
            fi
        else
        local pg_hba_file="/etc/postgresql/$(ls /etc/postgresql/ | head -1)/main/pg_hba.conf"
        if [[ -f "$pg_hba_file" ]]; then
            # Timestamped backup of the original
            backup_file "$pg_hba_file"
            # Add local md5 authentication if not present
            if ! grep -q "local.*$DB_NAME.*md5" "$pg_hba_file"; then
                echo "local   $DB_NAME            $DB_USER                                    scram-sha-256" >> "$pg_hba_file"
                print_info "Added scram-sha-256 authentication for $DB_USER user in pg_hba.conf"
                fi
            fi
        fi

    # Restart PostgreSQL to apply changes
    $SERVICE_MANAGER restart postgresql

    # Save password information (0600, readable only by root)
    DB_PASS_FILE="/root/eye_postgres_password.txt"
    if ! [[ -e "$DB_PASS_FILE" ]]; then
        ( umask 077; : > "$DB_PASS_FILE" )
    fi
    chmod 600 "$DB_PASS_FILE"
    cat > "$DB_PASS_FILE" <<EOF
Eye database account
  user:     $DB_USER
  host:     $DB_HOST:$DB_PORT
  database: $DB_NAME
  password: $DB_PASS
EOF
    print_warn "Password for '$DB_USER' saved in $DB_PASS_FILE (mode 0600), it is not printed to the screen"

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
        exit 130
    fi

    # Проверка существования файлов
    if [[ ! -f "$SQL_CREATE_FILE" || ! -f "$SQL_DATA_FILE" ]]; then
        print_error "SQL files not found for DB_TYPE=$DB_TYPE and EYE_LANG=$EYE_LANG"
        exit 131
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

# Apply a value to a config file, loudly reporting a template that does not
# contain the expected key. strict=1 (default) aborts, strict=0 only warns -
# DB_PORT is legitimately absent from the shipped templates.
cfg_apply_php() {
    local file="$1" name="$2" value="$3" strict="${4:-1}"
    if cfg_set_php "$file" "$name" "$value"; then
        return 0
    fi
    if [[ "$strict" == "1" ]]; then
        print_error "Cannot set $name in $file: define() not found in the template"
        return 1
    fi
    print_warn "Optional key $name is not present in $file - skipped"
    return 0
}

cfg_apply_key() {
    local file="$1" name="$2" value="$3" strict="${4:-1}"
    if cfg_set_key "$file" "$name" "$value"; then
        return 0
    fi
    if [[ "$strict" == "1" ]]; then
        print_error "Cannot set $name in $file: key not found in the template"
        return 1
    fi
    print_warn "Optional key $name is not present in $file - skipped"
    return 0
}

# ---------------------------------------------------------------------------
# Из поставки удалены старая тема (white.css) и её библиотеки: Remodal заменён
# нативным <dialog>, Select2 — обычными <select> с поиском (js/eye.js).
#   1) добираем с машины то, чего в исходниках уже нет;
#   2) переключаем HTML_STYLE в РАБОЧЕМ config.php: при апдейте конфиг не
#      перезаписывается, и "white" указал бы на несуществующий файл — админка
#      поднялась бы совсем без стилей.
# Путь в переменной EYE_HTML — чтобы функцию можно было проверить без root.
# ---------------------------------------------------------------------------
retire_legacy_theme() {
    local EYE_HTML="${EYE_HTML:-/opt/Eye/html}"
    local cfg="$EYE_HTML/cfg/config.php"
    local removed=0 f cur

    print_step "Removing retired appearance files"

    local legacy=(
        "$EYE_HTML/css/white.css"
        "$EYE_HTML/css/remodal-custom.css"
        "$EYE_HTML/login.css"
        "$EYE_HTML/js/remodal"
        "$EYE_HTML/js/remodal.min.js"
        "$EYE_HTML/js/remodal-auth.js"
        "$EYE_HTML/js/remodal-user.js"
        "$EYE_HTML/js/remodal-devices.js"
        "$EYE_HTML/js/select-auto.js"
        "$EYE_HTML/js/select2"
    )
    for f in "${legacy[@]}"; do
        if [[ -e "$f" ]]; then
            if rm -rf "$f"; then
                print_info "Removed: $f"
                removed=$((removed + 1))
            else
                print_error "Cannot remove $f"
            fi
        fi
    done
    if [[ $removed -eq 0 ]]; then
        print_info "Nothing to remove (already clean)"
    fi
    # jQuery не трогаем: его использует jsTree на admin/devices/index-tree.php

    if [[ ! -f "$cfg" ]]; then
        print_warn "Нет $cfg — тема возьмётся из шаблона config.sample.php"
        return 0
    fi
    if ! grep -qE 'define[[:space:]]*\([[:space:]]*"HTML_STYLE"' "$cfg"; then
        print_warn "В $cfg нет HTML_STYLE — добавьте: define(\"HTML_STYLE\",\"eye\");"
        return 0
    fi

    cur=$(grep -oP 'define\s*\(\s*"HTML_STYLE"\s*,\s*"\K[^"]+' "$cfg" | head -1)
    case "$cur" in
        white|"")
            backup_file "$cfg" || print_warn "Не удалось сделать резервную копию $cfg"
            if cfg_set_php "$cfg" HTML_STYLE eye; then
                print_info "HTML_STYLE: \"$cur\" → \"eye\" (white.css из поставки удалён)"
            else
                print_error "Не удалось менять HTML_STYLE в $cfg — поправьте вручную: define(\"HTML_STYLE\",\"eye\");"
                return 1
            fi
            ;;
        eye|eye-dark)
            print_info "HTML_STYLE уже \"$cur\""
            ;;
        *)
            print_warn "HTML_STYLE=\"$cur\" — своя тема, не трогаем (учтите: white.css из поставки удалён)"
            ;;
    esac
    return 0
}

setup_configs() {
    print_step "Configuring configuration files"

    # Генерация или запрос ключей шифрования
    print_info "Setting up encryption keys..."

    if [[ "$DB_INSTALL" == "local" ]]; then
        # Для локальной БД — генерируем автоматически.
        # Ключ и IV нигде не печатаются: значение попадает в config.php (0660),
        # в скриптовый config и в /root/eye_*_password.txt.
        ENC_PASSWORD=$(gen_secret 16)
        ENC_VECTOR=$(gen_digits 16)
        print_info "Encryption keys generated automatically (local database)."
        print_info "Key: 16 chars in /opt/Eye/html/cfg/config.php, IV: 16 digits (generated from /dev/urandom)"
    else
        # Для удалённой БД — ОБЯЗАТЕЛЬНО запрашиваем у пользователя
        echo ""
        print_info "Remote database detected. You MUST provide the encryption keys"
        print_info "that are already in use by other Eye components connected to this database."
        echo ""
    
        while [[ -z "$ENC_PASSWORD" ]]; do
            read -r -p "Enter ENCRYPTION_KEY (16+ characters): " ENC_PASSWORD
            if [[ ${#ENC_PASSWORD} -lt 16 ]]; then
                print_warn "Key should be at least 16 characters long."
                ENC_PASSWORD=""
            fi
        done

        while [[ -z "$ENC_VECTOR" ]]; do
            read -r -p "Enter ENCRYPTION_IV (exactly 16 digits): " ENC_VECTOR
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
            # Повторный запуск установщика на рабочей машине не должен
            # незаметно терять уже отредактированный конфиг.
            if [[ -f /opt/Eye/html/cfg/config.php ]]; then
                print_warn "Existing web config.php found - saving a copy before regenerating it"
                backup_file /opt/Eye/html/cfg/config.php
            fi
            cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php

            # Определяем DB_TYPE для PHP (mysql или pgsql)
            PHP_DB_TYPE="$DB_TYPE"
            [[ "$DB_TYPE" == "postgresql" ]] && PHP_DB_TYPE="pgsql"

            # Подстановка реальных значений (perl + %ENV, без sed:
            # параметры не должны интерпретироваться ни shell, ни sed)
            cfg_fail=0
            WEB_CFG=/opt/Eye/html/cfg/config.php
            cfg_apply_php "$WEB_CFG" DB_TYPE       "$PHP_DB_TYPE" || cfg_fail=1
            cfg_apply_php "$WEB_CFG" DB_HOST       "$DB_HOST"     || cfg_fail=1
            cfg_apply_php "$WEB_CFG" DB_PORT       "$DB_PORT"  0  || cfg_fail=1
            cfg_apply_php "$WEB_CFG" DB_NAME       "$DB_NAME"     || cfg_fail=1
            cfg_apply_php "$WEB_CFG" DB_USER       "$DB_USER"     || cfg_fail=1
            cfg_apply_php "$WEB_CFG" DB_PASS       "$DB_PASS"     || cfg_fail=1
            # Ключи шифрования
            cfg_apply_php "$WEB_CFG" ENCRYPTION_KEY "$ENC_PASSWORD" || cfg_fail=1
            cfg_apply_php "$WEB_CFG" ENCRYPTION_IV  "$ENC_VECTOR"   || cfg_fail=1

            if [[ $cfg_fail -ne 0 ]]; then
                print_error "Web configuration is incomplete - see the errors above"
                return 1
            fi

            print_info "Web configuration file config.php created"
        else
            print_warn "Web config template not found, skipping PHP config"
        fi
    fi

    # === Настройка конфигурации бэкенда (только если нужен бэкенд) ===
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
            if [[ -f /opt/Eye/scripts/cfg/config ]]; then
                print_warn "Existing backend config found - saving a copy before regenerating it"
                backup_file /opt/Eye/scripts/cfg/config
            fi
            cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

            # Подстановка значений (perl + %ENV, см. комментарий выше)
            cfg_fail=0
            BE_CFG=/opt/Eye/scripts/cfg/config
            cfg_apply_key "$BE_CFG" DBTYPE         "$DB_TYPE"      || cfg_fail=1
            cfg_apply_key "$BE_CFG" DBHOST         "$DB_HOST"      || cfg_fail=1
            cfg_apply_key "$BE_CFG" DBPORT         "$DB_PORT"   0  || cfg_fail=1
            cfg_apply_key "$BE_CFG" DBNAME         "$DB_NAME"      || cfg_fail=1
            cfg_apply_key "$BE_CFG" DBUSER         "$DB_USER"      || cfg_fail=1
            cfg_apply_key "$BE_CFG" DBPASS         "$DB_PASS"      || cfg_fail=1
            # Ключи шифрования
            cfg_apply_key "$BE_CFG" encryption_key "$ENC_PASSWORD" || cfg_fail=1
            cfg_apply_key "$BE_CFG" encryption_iv  "$ENC_VECTOR"   || cfg_fail=1

            if [[ $cfg_fail -ne 0 ]]; then
                print_error "Backend configuration is incomplete - see the errors above"
                return 1
            fi

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
        cfg_apply_php "$CONFIG_PHP" HTML_LANG "russian" \
            || print_warn "HTML_LANG not updated - check $CONFIG_PHP manually"
        sed -i "s/setlocale(LC_ALL, '[^']*');/setlocale(LC_ALL, 'ru_RU.UTF-8');/g" "$CONFIG_PHP"
        print_info "Web interface language set to Russian"
    else
        # Установка английского языка (по умолчанию)
        cfg_apply_php "$CONFIG_PHP" HTML_LANG "english" \
            || print_warn "HTML_LANG not updated - check $CONFIG_PHP manually"
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

        # Copy prepared config (previous version is kept, timestamped)
        [[ -f "$DEFAULT_CONF" ]] && backup_file "$DEFAULT_CONF"
        cp "/opt/Eye/docs/apache/000-default.conf" "$DEFAULT_CONF"

        # Enable site
        if [[ -f "$APACHE_CONF_DIR/sites-enabled/000-default.conf" ]]; then
            rm -f "$APACHE_CONF_DIR/sites-enabled/000-default.conf"
            ln -sf "$DEFAULT_CONF" "$APACHE_CONF_DIR/sites-enabled/000-default.conf"
        fi
    fi

    # Configure sudoers
    if [[ -f "/opt/Eye/docs/sudoers.d/www-data" ]]; then
        make_workdir
        # Use prepared template, substituting correct user
        if sed "s/www-data/eye/g" /opt/Eye/docs/sudoers.d/www-data > "$WORKDIR/sudoers.eye" \
            && visudo -cf "$WORKDIR/sudoers.eye" >/dev/null 2>&1; then
            backup_file /etc/sudoers.d/eye 2>/dev/null || true
            install -m 0440 -o root -g root "$WORKDIR/sudoers.eye" /etc/sudoers.d/eye
            print_info "Sudoers file created from template (validated with visudo)"
        else
            print_error "visudo rejected the generated sudoers.d/eye - it was NOT installed"
            print_warn "Create /etc/sudoers.d/eye manually from docs/sudoers.d/www-data if needed"
        fi
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
_install_cron() {
    local src="$1"
    local name="$2"

    # 2 = нечего устанавливать (источник отсутствует)
    [[ ! -f "$src" ]] && return 2

    [[ -f "/etc/cron.d/$name" ]] && backup_file "/etc/cron.d/$name"
    if cp -f "$src" "/etc/cron.d/$name"; then
        chown root:root "/etc/cron.d/$name"
        chmod 644 "/etc/cron.d/$name"
        print_info "Cron job added: /etc/cron.d/$name"
        return 0
    else
        print_error "Failed to add cron job: /etc/cron.d/$name"
        return 1
    fi
}

_install_logrotate() {
    local src="$1"
    local name="$2"

    # 2 = нечего устанавливать (источник отсутствует)
    [[ ! -f "$src" ]] && return 2

    [[ -f "/etc/logrotate.d/$name" ]] && backup_file "/etc/logrotate.d/$name"
    if cp -f "$src" "/etc/logrotate.d/$name"; then
        chown root:root "/etc/logrotate.d/$name"
        chmod 644 "/etc/logrotate.d/$name"
        print_info "Logrotate config added: /etc/logrotate.d/$name"
        return 0
    else
        print_error "Failed to add logrotate config: /etc/logrotate.d/$name"
        return 1
    fi
}

setup_cron_logrotate() {
    print_step "Configuring cron and logrotate"

    local _dir
    for _dir in /etc/cron.d /etc/logrotate.d; do
        if [[ ! -d "$_dir" ]]; then
            mkdir -p "$_dir"
            chown root:root "$_dir"
            chmod 755 "$_dir"
        fi
    done

    local cron_added=0

    # Cron by user
    _install_cron "/opt/Eye/docs/cron/eye" "eye" && cron_added=1

    # Cron by root
    _install_cron "/opt/Eye/docs/cron/eye-root" "eye-root" && cron_added=1

    # Logrotate - dnsmasq
    if [[ -f /etc/dnsmasq.conf ]]; then
        _install_logrotate "/opt/Eye/docs/logrotate/eye-dnsmasq" "eye-dnsmasq"
    fi

    # Logrotate - syslog-ng
    if [[ -f /etc/syslog-ng/conf.d/eye.conf ]]; then
        _install_logrotate "/opt/Eye/docs/logrotate/eye-syslog-ng" "eye-syslog-ng"
    fi

    # Logrotate - eye scripts
    if [[ -d /opt/Eye/scripts ]]; then
        _install_logrotate "/opt/Eye/docs/logrotate/eye-scripts" "eye-scripts"
    fi

    print_info "Cron and logrotate configuration completed"

    if (( cron_added )); then
        print_warn "Edit /etc/cron.d/eye to enable required scripts"
    fi

    return 0
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

    # Backup configuration with a timestamp: an already configured production
    # dnsmasq is replaced below, and a plain ".backup" would be overwritten by
    # the second run of the installer (i.e. by the already broken config).
    if [[ -f "/etc/dnsmasq.conf" ]]; then
        if [[ -s "/etc/dnsmasq.conf" ]] && [[ $(grep -vcE '^\s*(#.*)?$' /etc/dnsmasq.conf) -gt 3 ]]; then
            print_warn "/etc/dnsmasq.conf already contains configuration - it will be replaced by the Eye template"
        fi
        backup_file /etc/dnsmasq.conf || print_error "Cannot backup /etc/dnsmasq.conf"
    fi

    # Copy configuration from Eye
    if [[ -f "/opt/Eye/docs/addons/dnsmasq.conf" ]]; then
        cat /opt/Eye/docs/addons/dnsmasq.conf > /etc/dnsmasq.conf
    fi

    # Copy systemd services
    if [[ -f "/opt/Eye/docs/systemd/dhcp-log.service" ]]; then
        [[ -f "/etc/systemd/system/dhcp-log.service" ]] && backup_file "/etc/systemd/system/dhcp-log.service"
        cp /opt/Eye/docs/systemd/dhcp-log.service /etc/systemd/system/
        mkdir -p /etc/systemd/system/dnsmasq.service.d
        cp -f /opt/Eye/docs/systemd/dnsmasq.service.d/override.conf /etc/systemd/system/dnsmasq.service.d
    fi

    if [[ -f "/opt/Eye/docs/systemd/dhcp-log-truncate.service" ]]; then
        [[ -f "/etc/systemd/system/dhcp-log-truncate.service" ]] && backup_file "/etc/systemd/system/dhcp-log-truncate.service"
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

    # Timestamped backup of the main config (used for rollback below)
    SYSLOGNG_BACKUP=""
    if [[ -f "/etc/syslog-ng/syslog-ng.conf" ]]; then
        if backup_file /etc/syslog-ng/syslog-ng.conf; then
            SYSLOGNG_BACKUP="$LAST_BACKUP"
        fi
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

            if printf '\n%s\n' "$syslogng_options" >> /etc/syslog-ng/syslog-ng.conf; then
                print_info "Added options block to syslog-ng.conf"
            else
                print_error "Cannot append options block to /etc/syslog-ng/syslog-ng.conf"
            fi
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
        [[ -f "/etc/systemd/system/syslog-stat.service" ]] && backup_file "/etc/systemd/system/syslog-stat.service"
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

    # Проверяем синтаксис ДО перезапуска: с битым конфигом syslog-ng не
    # поднимется, и сбор логов со всего сетевого оборудования остановится.
    SYSLOGNG_VALID=1
    if command -v syslog-ng >/dev/null 2>&1; then
        if ! syslog-ng --syntax-only >/dev/null 2>&1; then
            print_error "syslog-ng configuration is INVALID after the changes"
            if [[ -n "$SYSLOGNG_BACKUP" && -f "$SYSLOGNG_BACKUP" ]]; then
                cp -a "$SYSLOGNG_BACKUP" /etc/syslog-ng/syslog-ng.conf
                print_warn "Previous /etc/syslog-ng/syslog-ng.conf restored from $SYSLOGNG_BACKUP"
            fi
            SYSLOGNG_VALID=0
        fi
    else
        print_warn "syslog-ng binary not found, cannot verify configuration syntax"
    fi

    if (( SYSLOGNG_VALID )); then
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
    else
        print_warn "syslog-ng was NOT restarted - apply /etc/syslog-ng/conf.d/eye.conf manually"
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

    # Final check (skipped when we already rolled the configuration back)
    if (( SYSLOGNG_VALID )) && command -v syslog-ng &> /dev/null; then
        if syslog-ng --syntax-only; then
            print_info "syslog-ng configuration is valid"
        else
            print_error "Error in syslog-ng configuration"
            print_warn "Check files: /etc/syslog-ng/syslog-ng.conf and /etc/syslog-ng/conf.d/eye.conf"
        fi
    fi

    print_info "syslog-ng configuration completed"
    print_info "To receive logs from devices, configure them to send to IP: $(hostname -f 2>/dev/null || hostname -I 2>/dev/null | awk '{print $1}')"
}

# Install the ipset restore helper and hook it into netfilter-persistent
# (iptables.service on ALT). Previously the override directory was tested with
# [[ -f ]], so the systemd drop-in was never installed and ipset lists were
# not restored after a reboot.
install_ipset_utils() {
    local src="/opt/Eye/docs/ipset/ipset-utils.sh"
    local dropin_src="/opt/Eye/docs/systemd/netfilter-persistent.service.d/override.conf"
    local unit_dir=""

    if [[ ! -f "$src" ]]; then
        print_warn "ipset helper $src not found - skipping"
        return 1
    fi

    # remove old SysV init script (replaced by the systemd override)
    if [[ -e /etc/init.d/ipset ]]; then
        rm -f /etc/init.d/ipset
    fi

    mkdir -p /usr/local/bin
    if ! install -m 0755 -o root -g root "$src" /usr/local/bin/; then
        print_error "Cannot install $src into /usr/local/bin"
        return 1
    fi

    if [[ "$OS_FAMILY" == "alt" ]]; then
        unit_dir="iptables.service.d"
    else
        unit_dir="netfilter-persistent.service.d"
    fi

    if [[ ! -f "$dropin_src" ]]; then
        print_warn "$dropin_src not found - ipset will NOT be restored at boot"
        return 1
    fi

    backup_file "/etc/systemd/system/$unit_dir/override.conf" 2>/dev/null || true
    mkdir -p "/etc/systemd/system/$unit_dir"
    if ! install -m 0644 -o root -g root "$dropin_src" "/etc/systemd/system/$unit_dir/override.conf"; then
        print_error "Cannot install systemd override into $unit_dir"
        return 1
    fi

    $SERVICE_MANAGER daemon-reload
    base_unit="${unit_dir%.service.d}"
    if service_exists "$base_unit"; then
        print_info "ipset: helper + override installed for $unit_dir ($base_unit)"
    else
        print_warn "ipset helper installed, but unit $base_unit.service is missing -"
        print_warn "ipset lists will only be restored once it is enabled."
    fi
    return 0
}

# Configure additional services
setup_additional_services() {
    print_step "Configuring additional services"

    # stat-sync service
    if [[ -f "/opt/Eye/docs/systemd/stat-sync.service" ]]; then
        [[ -f "/etc/systemd/system/stat-sync.service" ]] && backup_file "/etc/systemd/system/stat-sync.service"
        cp /opt/Eye/docs/systemd/stat-sync.service /etc/systemd/system/
        $SERVICE_MANAGER enable stat-sync.service
        print_info "stat-sync service enabled"
    fi

    # eye-statd service (NetFlow)
    if [[ -f "/opt/Eye/docs/systemd/eye-statd.service" ]]; then
        [[ -f "/etc/systemd/system/eye-statd.service" ]] && backup_file "/etc/systemd/system/eye-statd.service"
        cp /opt/Eye/docs/systemd/eye-statd.service /etc/systemd/system/
        $SERVICE_MANAGER enable eye-statd.service
        print_info "eye-statd service (NetFlow) enabled"
    fi

    # add ipset
    install_ipset_utils

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
            echo "   PostgreSQL user '$DB_USER' password saved in:"
            echo "   /root/eye_postgres_password.txt (mode 0600)"
            echo ""
        fi
    else
        echo "   mysql_secure_installation"
        if [[ -f "/root/eye_mysql_password.txt" ]]; then
            echo ""
            echo "   MySQL user '$DB_USER' password saved in:"
            echo "   /root/eye_mysql_password.txt (mode 0600)"
            echo ""
        fi
    fi
    
    echo ""
    echo "2. Check and edit configuration files:"
    echo "   /opt/Eye/html/cfg/config.php"
    echo "   /opt/Eye/scripts/cfg/config"
    echo ""
    
    echo "3. Configure cron jobs:"
    echo "   nano /etc/cron.d/eye"
    echo "   Uncomment required scripts"
    echo ""
    echo "4. Configure if necessary:"
    echo "   - DHCP: /etc/dnsmasq.conf"
    echo "   - NetFlow: configure on network devices"
    echo ""
    echo "5. WEB INTERFACE ACCESS:"
    # hostname -f is empty on a host without FQDN -> fall back to the IP
    SRV_NAME=$(hostname -f 2>/dev/null) || SRV_NAME=$(hostname -I 2>/dev/null | awk '{print $1}')
    : "${SRV_NAME:=<server-address>}"
    echo "   URL:      http://$SRV_NAME/"
    echo "   Admin:    http://$SRV_NAME/admin/"
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
    DB_USER=""
    DB_NAME="eye"   # совпадает с значением по умолчанию в приглашении
    DB_HOST="127.0.0.1"
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

    # Старая тема и её файлы больше не нужны
    retire_legacy_theme

    # Настройка конфигов — всегда (но внутри учитывает INSTALL_TYPE)
    if ! setup_configs; then
        print_error "Installation aborted: configuration files are incomplete"
        exit 140
    fi

    # Язык — только если установлен веб
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        apply_language_settings
    fi

    # Веб-сервер — только если нужен веб
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "web" ]]; then
        setup_apache_php
    fi

    # Доп. сервисы (dnsmasq, syslog-ng и т.п.) — только для бэкенда
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        setup_additional_services
    fi

    # Cron и logrotate — только если есть бэкенд (там — фоновые задачи и логи)
    if [[ "$INSTALL_TYPE" == "full" || "$INSTALL_TYPE" == "backend" ]]; then
        setup_cron_logrotate
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
        --exclude="netflow" \
        --exclude="scripts/log" \
        --exclude="scripts/log/*" \
        --exclude="html/log" \
        --exclude="html/log/*" \
        -C / "opt/Eye" 2> >(tee "$WORKDIR/backup.log" >&2)

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
    make_workdir

    stop_eye

    backup_current_installation || {
        echo "CRITICAL: Backup failed. Aborting upgrade."
        start_eye
        exit 1
    }

    update_system
    install_packages
    upgrade_source_code

    # config.php при апдейте не перезаписывается — тему переключаем на месте
    retire_legacy_theme

    upgrade_source_code

    if [ -f /opt/Eye/scripts/updates/upgrade.pl ]; then
        perl /opt/Eye/scripts/updates/upgrade.pl
        import_mac_database
        fi

    start_eye

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

# Temporary files and the restore-on-failure net are active from this point on
make_workdir
trap 'on_script_exit' EXIT
trap 'print_warn "Interrupted by user"; exit 130' INT TERM

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
