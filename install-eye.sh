#!/bin/bash
# Eye Installation Script for ALT Linux/Debian/Ubuntu with PostgreSQL support
# Version: 2.1

set -e

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

select_language_with_auto() {
    print_step "Select Installation Language"
    
    # Проверка автоматического режима
    if [[ "$AUTO_MODE" == "true" ]]; then
        EYE_LANG="english"
        EYE_LANG_SHORT="en"
        print_info "Auto mode: English language selected by default"
        return 0
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

# Function for remote database configuration
configure_remote_database() {
    echo ""
    echo "Remote Database Configuration"
    echo "============================="
    
    select_database_type
    
    read -p "Database server IP address: " DB_HOST
#    read -p "Database port [default]: " DB_PORT
    read -p "Database name: " DB_NAME
    read -p "Database username: " DB_USER
    read -sp "Database password: " DB_PASS
    echo ""
    
    # Set defaults if empty
    [[ -z "$DB_PORT" ]] && DB_PORT="3306"
    [[ "$DB_TYPE" == "postgresql" ]] && [[ "$DB_PORT" == "3306" ]] && DB_PORT="5432"
    
    echo "Database configuration saved:"
    echo "  Type: $DB_TYPE"
    echo "  Host: $DB_HOST:$DB_PORT"
    echo "  Name: $DB_NAME"
    echo "  User: $DB_USER"
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
            
            # Ask about database
            read -p "Install database locally? (y/n) [y]: " install_db
            
            if [[ -z "$install_db" || "$install_db" =~ ^[Yy]$ ]]; then
                DB_INSTALL="local"
                echo "Local database will be installed"
                select_database_type
            else
                DB_INSTALL="remote"
                echo "Remote database configuration"
                configure_remote_database
            fi
            ;;
        2)
            INSTALL_TYPE="web"
            echo "Selected: Web interface only"
            DB_INSTALL="remote"
            configure_remote_database
            ;;
        3)
            INSTALL_TYPE="backend"
            echo "Selected: Network backend only"
            DB_INSTALL="remote"
            configure_remote_database
            ;;
        *)
            INSTALL_TYPE="full"
            echo "Default selected: Web interface + network backend"
            DB_INSTALL="local"
            echo "Local database will be installed"
            select_database_type
            ;;
    esac
}

# Install dependencies for ALT Linux
install_deps_altlinux() {
    print_step "Installing dependencies for ALT Linux"

    # Update repositories
    apt-get update

    # General utilities
    apt-get install -y git xxd wget fping hwdata rsync

    # Database installation based on selected type
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        apt-get install -y postgresql17 postgresql17-server postgresql17-contrib postgresql17-perl
    else
        apt-get install -y mariadb-server mariadb-client
    fi

    # Web server and PHP
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        apt-get install -y apache2 \
            php8.2 php8.2-pgsql php8.2-pdo_pgsql php8.2-intl php8.2-mbstring \
            pear-Mail php8.2-snmp php8.2-zip \
            php8.2-fpm-fcgi apache2-mod_fcgid
    else
        apt-get install -y apache2 \
            php8.2 php8.2-mysqlnd php8.2-intl php8.2-mbstring \
            pear-Mail php8.2-snmp php8.2-zip \
            php8.2-pgsql php8.2-mysqlnd php8.2-pdo_mysql php8.2-mysqlnd-mysqli \
            php8.2-fpm-fcgi apache2-mod_fcgid
    fi

    # Perl modules
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        apt-get install -y perl perl-Net-Patricia perl-NetAddr-IP \
            perl-Config-Tiny perl-Net-DNS perl-DateTime perl-Net-Ping \
            perl-Net-Netmask perl-Text-Iconv perl-Net-SNMP \
            perl-Net-Telnet perl-DBI perl-DBD-Pg \
            perl-Parallel-ForkManager perl-Proc-Daemon \
            perl-DateTime-Format-DateParse \
            perl-Net-OpenSSH perl-File-Tail perl-Crypt-Rijndael \
            perl-Crypt-CBC perl-CryptX perl-Crypt-DES \
            perl-File-Path-Tiny perl-Expect \
            perl-Proc-ProcessTable
    else
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
    fi

    # Additional services
    apt-get install -y dnsmasq syslog-ng syslog-ng-journal

    # Install pwgen if not present
    if ! command -v pwgen &> /dev/null; then
        apt-get install -y pwgen
    fi

    control fping public
    control ping public
}

# Install dependencies for Debian/Ubuntu
install_deps_debian() {
    print_step "Installing dependencies for Debian/Ubuntu"

    # Update repositories
    apt-get update

    # General utilities
    apt-get install -y git xxd bsdmainutils pwgen wget fping ieee-data rsync

    # Database installation based on selected type
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        apt-get install -y postgresql postgresql-client
    else
        apt-get install -y mariadb-server mariadb-client
    fi

    # Web server and PHP
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        apt-get install -y apache2 \
            php php-pgsql php-bcmath php-intl php-mbstring \
            php-date php-mail php-snmp php-zip \
            php-db php-fpm libapache2-mod-fcgid
    else
        apt-get install -y apache2 \
            php php-mysql php-bcmath php-intl php-mbstring \
            php-date php-mail php-snmp php-zip \
            php-db php-pgsql php-fpm libapache2-mod-fcgid
    fi

    # Perl modules
    if [[ "$DB_TYPE" == "postgresql" ]]; then
        apt-get install -y perl libnet-patricia-perl libnetaddr-ip-perl \
            libconfig-tiny-perl libnet-dns-perl libdatetime-perl \
            libnet-netmask-perl libtext-iconv-perl libnet-snmp-perl \
            libnet-telnet-perl libdbi-perl \
            libparallel-forkmanager-perl libproc-daemon-perl \
            libdatetime-format-dateparse-perl \
            libnet-openssh-perl libfile-tail-perl libcrypt-rijndael-perl \
            libcrypt-cbc-perl libcryptx-perl libdbd-pg-perl \
            libfile-path-tiny-perl libexpect-perl libcrypt-des-perl
    else
        apt-get install -y perl libnet-patricia-perl libnetaddr-ip-perl \
            libconfig-tiny-perl libnet-dns-perl libdatetime-perl \
            libnet-netmask-perl libtext-iconv-perl libnet-snmp-perl \
            libnet-telnet-perl libdbi-perl libdbd-mysql-perl \
            libparallel-forkmanager-perl libproc-daemon-perl \
            libdatetime-format-dateparse-perl \
            libnet-openssh-perl libfile-tail-perl libcrypt-rijndael-perl \
            libcrypt-cbc-perl libcryptx-perl libdbd-pg-perl \
            libfile-path-tiny-perl libexpect-perl libcrypt-des-perl
    fi

    # Additional services
    apt-get install -y dnsmasq syslog-ng
}

# System update
update_system() {
    print_step "Updating system"
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

    # Create directory structure
    print_info "Creating directory structure..."
    mkdir -p /opt/Eye/scripts/cfg
    mkdir -p /opt/Eye/scripts/log
    mkdir -p /opt/Eye/html/cfg
    mkdir -p /opt/Eye/html/js
    mkdir -p /opt/Eye/docs

    chmod -R 755 /opt/Eye/html
    chmod -R 770 /opt/Eye/scripts/log
    chmod 750 /opt/Eye/scripts

    # Copy files
    print_info "Copying files..."
    cp -R scripts/ /opt/Eye/
    cp -R html/ /opt/Eye/
    cp -R docs/ /opt/Eye/

    # Set permissions
    chown -R eye:eye /opt/Eye

    # Apply SNMP SHA512 patch
    apply_snmp_patch
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
        print_warn "  mysql -u root -p < ${SQL_CREATE_FILE}"
        print_warn "  mysql -u root -p stat < ${SQL_DATA_FILE}"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 0
    fi

    # Generate password for stat user
    DB_PASSWORD=$(pwgen 16 1)
    MYSQL_PASSWORD=$DB_PASSWORD

    print_info "Importing database structure..."

    # Import main SQL file
    mysql $MYSQL_OPT < ${SQL_CREATE_FILE}

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
    mysql $MYSQL_OPT stat < ${SQL_DATA_FILE}

    if [[ $? -ne 0 ]]; then
        print_warn "Error importing data.sql (data may already exist)"
    else
        print_info "Initial data imported"
    fi

    # Create stat user
    print_info "Creating user 'stat'..."
    mysql $MYSQL_OPT <<EOF
CREATE USER IF NOT EXISTS 'stat'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON stat.* TO 'stat'@'localhost';
FLUSH PRIVILEGES;
EOF

    if [[ $? -ne 0 ]]; then
        print_error "Error creating user 'stat'"
        if [[ -f "$MYSQL_CNF_FILE" ]]; then
            rm -f "$MYSQL_CNF_FILE"
        fi
        return 1
    fi

    print_info "User 'stat' successfully created"

    # Save password information
    echo "MySQL 'stat' user password: $DB_PASSWORD" > /root/eye_mysql_password.txt
    chmod 600 /root/eye_mysql_password.txt

    print_info "User 'stat' password: $DB_PASSWORD"
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
    if [[ "$OS_FAMILY" == "alt" ]]; then
        echo "root ALL=(ALL:ALL) NOPASSWD: ALL" >/etc/sudoers.d/root
        PGDATA="/var/lib/pgsql/data"
        if [ -z "$(ls -A $PGDATA 2>/dev/null)" ]; then
            /etc/init.d/postgresql initdb
            fi
        fi

    # Start and enable service
    $SERVICE_MANAGER enable postgresql
    $SERVICE_MANAGER start postgresql

    # Check PostgreSQL access
    if ! command -v psql &> /dev/null; then
        print_error "PostgreSQL client not installed"
        return 1
    fi

    # Switch to postgres user to execute commands
    read -p "Create database and user for Eye? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warn "Database creation skipped. Create manually as postgres user:"
        print_warn "  sudo -u postgres psql -f ${SQL_CREATE_FILE}"
        print_warn "  sudo -u postgres psql -d stat -f ${SQL_DATA_FILE}"
        return 0
    fi

    # Generate password for stat user
    DB_PASSWORD=$(pwgen 16 1)
    POSTGRES_PASSWORD=$DB_PASSWORD

    print_info "Importing database structure..."

    # Import main SQL file as postgres user
    if [[ "$OS_FAMILY" == "alt" ]]; then
        psql -U postgres -f ${SQL_CREATE_FILE}
        else
        sudo -u postgres psql -f ${SQL_CREATE_FILE}
        fi

    if [[ $? -ne 0 ]]; then
        print_error "Error importing create_db.sql"
        return 1
    fi

    print_info "Database structure imported"

    # Set password for stat user
    print_info "Setting password for user 'stat'..."
    if [[ "$OS_FAMILY" == "alt" ]]; then
        psql -U postgres -c "CREATE USER stat WITH PASSWORD '$DB_PASSWORD';"
        psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE stat TO stat;"
        else
        sudo -u postgres psql -c "CREATE USER stat WITH PASSWORD '$DB_PASSWORD';"
        sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE stat TO stat;"
        fi

    # Import data
    print_info "Importing initial data..."
    if [[ "$OS_FAMILY" == "alt" ]]; then
        psql -U postgres -d stat -f ${SQL_DATA_FILE}
        else
        sudo -u postgres psql -d stat -f ${SQL_DATA_FILE}
        fi

    if [[ $? -ne 0 ]]; then
        print_warn "Error importing data.sql (data may already exist)"
    else
        print_info "Initial data imported"
    fi

    # Grant privileges on all tables to stat user
    print_info "Granting privileges on all tables to user 'stat'..."
    if [[ "$OS_FAMILY" == "alt" ]]; then
        psql -U postgres -d stat <<EOF
GRANT ALL ON ALL TABLES IN SCHEMA public TO stat;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO stat;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO stat;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO stat;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO stat;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO stat;
EOF
        else
        sudo -u postgres psql -d stat <<EOF
GRANT ALL ON ALL TABLES IN SCHEMA public TO stat;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO stat;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO stat;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO stat;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO stat;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO stat;
EOF
    fi

    # Configure PostgreSQL for MD5 authentication
    if [[ "$OS_FAMILY" == "alt" ]]; then
        local pg_hba_file="/var/lib/pgsql/data/pg_hba.conf"
        if [[ -f "$pg_hba_file" ]]; then
            # Backup original
            cp "$pg_hba_file" "${pg_hba_file}.backup"
            # Add local md5 authentication if not present
            if ! grep -q "local.*stat.*md5" "$pg_hba_file"; then
                echo "local   stat            stat                                    scram-sha-256" >> "$pg_hba_file"
                print_info "Added MD5 authentication for stat user in pg_hba.conf"
                fi
            fi
        else
        local pg_hba_file="/etc/postgresql/$(ls /etc/postgresql/ | head -1)/main/pg_hba.conf"
        if [[ -f "$pg_hba_file" ]]; then
            # Backup original
            cp "$pg_hba_file" "${pg_hba_file}.backup"
            # Add local md5 authentication if not present
            if ! grep -q "local.*stat.*md5" "$pg_hba_file"; then
                echo "local   stat            stat                                    scram-sha-256" >> "$pg_hba_file"
                print_info "Added MD5 authentication for stat user in pg_hba.conf"
                fi
            fi
        fi
    # Restart PostgreSQL to apply changes
    $SERVICE_MANAGER restart postgresql

    # Save password information
    echo "PostgreSQL 'stat' user password: $DB_PASSWORD" > /root/eye_postgres_password.txt
    chmod 600 /root/eye_postgres_password.txt

    print_info "User 'stat' password: $DB_PASSWORD"
    print_warn "Password saved in /root/eye_postgres_password.txt"

    return 0
}

# Configure database based on selected type
setup_database() {

    # Выбор правильных SQL файлов для импорта данных
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
    fi
    
    print_info "Using SQL files for $EYE_LANG language"

    if [[ "$DB_TYPE" == "postgresql" ]]; then
        setup_postgresql
    else
        setup_mysql
    fi
}

# Configure configuration files
setup_configs() {
    print_step "Configuring configuration files"

    # Copy configuration files
    if [[ -f "/opt/Eye/html/cfg/config.sample.php" ]]; then
        cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php
    fi

    if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
        cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config
    fi

    # Generate encryption keys
    print_info "Generating encryption keys..."
    if command -v pwgen &> /dev/null; then
        ENC_PASSWORD=$(pwgen 16 1)
    else
        ENC_PASSWORD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c16)
    fi

    ENC_VECTOR=$(tr -dc 0-9 </dev/urandom | head -c 16)

    # Configure config.php
    if [[ -f "/opt/Eye/html/cfg/config.sample.php" ]]; then
        cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php

        # Update database configuration based on type
        if [[ "$DB_TYPE" == "postgresql" ]]; then
            # PostgreSQL configuration
            if [[ -n "$POSTGRES_PASSWORD" ]]; then
                sed -i "s/define(\"DB_PASS\",\"[^\"]*\");/define(\"DB_PASS\",\"$POSTGRES_PASSWORD\");/" /opt/Eye/html/cfg/config.php
            fi
            sed -i "s/define(\"DB_TYPE\",\"[^\"]*\");/define(\"DB_TYPE\",\"postgresql\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_HOST\",\"[^\"]*\");/define(\"DB_HOST\",\"localhost\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_PORT\",\"[^\"]*\");/define(\"DB_PORT\",\"5432\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_NAME\",\"[^\"]*\");/define(\"DB_NAME\",\"stat\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_USER\",\"[^\"]*\");/define(\"DB_USER\",\"stat\");/" /opt/Eye/html/cfg/config.php
        else
            # MySQL configuration
            if [[ -n "$MYSQL_PASSWORD" ]]; then
                sed -i "s/define(\"DB_PASS\",\"[^\"]*\");/define(\"DB_PASS\",\"$MYSQL_PASSWORD\");/" /opt/Eye/html/cfg/config.php
            fi
            sed -i "s/define(\"DB_TYPE\",\"[^\"]*\");/define(\"DB_TYPE\",\"mysql\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_HOST\",\"[^\"]*\");/define(\"DB_HOST\",\"localhost\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_PORT\",\"[^\"]*\");/define(\"DB_PORT\",\"3306\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_NAME\",\"[^\"]*\");/define(\"DB_NAME\",\"stat\");/" /opt/Eye/html/cfg/config.php
            sed -i "s/define(\"DB_USER\",\"[^\"]*\");/define(\"DB_USER\",\"stat\");/" /opt/Eye/html/cfg/config.php
        fi

        # Update encryption key
        sed -i "s/ENCRYPTION_KEY\",\"[^\"]*\"/ENCRYPTION_KEY\",\"$ENC_PASSWORD\"/" /opt/Eye/html/cfg/config.php
        sed -i "s/ENCRYPTION_KEY','[^']*'/ENCRYPTION_KEY','$ENC_PASSWORD'/" /opt/Eye/html/cfg/config.php

        # Update initialization vector
        sed -i "s/ENCRYPTION_IV\",\"[^\"]*\"/ENCRYPTION_IV\",\"$ENC_VECTOR\"/" /opt/Eye/html/cfg/config.php
        sed -i "s/ENCRYPTION_IV','[^']*'/ENCRYPTION_IV','$ENC_VECTOR'/" /opt/Eye/html/cfg/config.php

        print_info "Configuration file config.php created from template"
    fi

    # Configure config for scripts
    if [[ -f "/opt/Eye/scripts/cfg/config.sample" ]]; then
        cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

        # Update database configuration based on type
        if [[ "$DB_TYPE" == "postgresql" ]]; then
            # PostgreSQL configuration
            sed -i "s/^DBTYPE=.*/DBTYPE=postgresql/" /opt/Eye/scripts/cfg/config
            sed -i "s/DBTYPE=db_type/DBTYPE=postgresql/" /opt/Eye/scripts/cfg/config
            
            # Update database connection settings
            sed -i "s/^DBHOST=.*/DBHOST=localhost/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBPORT=.*/DBPORT=5432/" /opt/Eye/scripts/cfg/config
            
            if [[ -n "$POSTGRES_PASSWORD" ]]; then
                sed -i "s/^DBPASS=.*/DBPASS=$POSTGRES_PASSWORD/" /opt/Eye/scripts/cfg/config
                sed -i "s/DBPASS=db_password/DBPASS=$POSTGRES_PASSWORD/" /opt/Eye/scripts/cfg/config
            fi
        else
            # MySQL configuration
            sed -i "s/^DBTYPE=.*/DBTYPE=mysql/" /opt/Eye/scripts/cfg/config
            sed -i "s/DBTYPE=db_type/DBTYPE=mysql/" /opt/Eye/scripts/cfg/config
            
            # Update database connection settings
            sed -i "s/^DBHOST=.*/DBHOST=localhost/" /opt/Eye/scripts/cfg/config
            sed -i "s/^DBPORT=.*/DBPORT=3306/" /opt/Eye/scripts/cfg/config
            
            if [[ -n "$MYSQL_PASSWORD" ]]; then
                sed -i "s/^DBPASS=.*/DBPASS=$MYSQL_PASSWORD/" /opt/Eye/scripts/cfg/config
                sed -i "s/DBPASS=db_password/DBPASS=$MYSQL_PASSWORD/" /opt/Eye/scripts/cfg/config
            fi
        fi

        # Common settings
        sed -i "s/^DBNAME=.*/DBNAME=stat/" /opt/Eye/scripts/cfg/config
        sed -i "s/DBNAME=db_database/DBNAME=stat/" /opt/Eye/scripts/cfg/config
        sed -i "s/^DBUSER=.*/DBUSER=stat/" /opt/Eye/scripts/cfg/config
        sed -i "s/DBUSER=db_user/DBUSER=stat/" /opt/Eye/scripts/cfg/config

        # Update encryption key
        sed -i "s/^encryption_key=.*/encryption_key=$ENC_PASSWORD/" /opt/Eye/scripts/cfg/config
        sed -i "s/encryption_key=!!!CHANGE_ME!!!!/encryption_key=$ENC_PASSWORD/" /opt/Eye/scripts/cfg/config

        # Update initialization vector
        sed -i "s/^encryption_iv=.*/encryption_iv=$ENC_VECTOR/" /opt/Eye/scripts/cfg/config
        sed -i "s/encryption_iv=0123456789012345/encryption_iv=$ENC_VECTOR/" /opt/Eye/scripts/cfg/config

        print_info "Configuration file scripts/cfg/config created from template"
    fi

    # Set permissions
    chown -R eye:eye /opt/Eye/html/cfg /opt/Eye/scripts/cfg
    chmod 660 /opt/Eye/html/cfg/config.php /opt/Eye/scripts/cfg/config
    chmod 750 /opt/Eye/html/cfg /opt/Eye/scripts/cfg

    print_info "Encryption keys generated"
    print_info "Password: $ENC_PASSWORD"
    print_info "Vector: $ENC_VECTOR"
}

# Функция применения языковых настроек к конфигурации
apply_language_settings() {
    print_info "Applying language settings: $EYE_LANG"
    
    # Настройка config.php
    if [[ -f "/opt/Eye/html/cfg/config.php" ]]; then
        if [[ "$EYE_LANG" == "russian" ]]; then
            # Установка русского языка
            sed -i "s/define(\"HTML_LANG\",\"english\");/define(\"HTML_LANG\",\"russian\");/g" /opt/Eye/html/cfg/config.php
            sed -i "s/setlocale(LC_ALL, 'en_US\.UTF-8');/setlocale(LC_ALL, 'ru_RU.UTF8');/g" /opt/Eye/html/cfg/config.php
            print_info "Web interface language set to Russian"
        else
            # Установка английского языка (по умолчанию)
            sed -i "s/define(\"HTML_LANG\",\"russian\");/define(\"HTML_LANG\",\"english\");/g" /opt/Eye/html/cfg/config.php
            sed -i "s/setlocale(LC_ALL, 'ru_RU\.UTF8');/setlocale(LC_ALL, 'en_US.UTF-8');/g" /opt/Eye/html/cfg/config.php
            print_info "Web interface language set to English"
        fi
    fi

}

# Configure Apache and PHP
setup_apache_php() {
    print_step "Configuring Apache and PHP"

    # Determine PHP version
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | grep -oP '\d+\.\d+' || echo "8.1")

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
    if [[ -f "/opt/Eye/docs/logrotate/dnsmasq" ]]; then
        cp /opt/Eye/docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq-eye
    fi

    if [[ -f "/opt/Eye/docs/logrotate/scripts" ]]; then
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
    fi

    if [[ -f "/opt/Eye/docs/systemd/dhcp-log-truncate.service" ]]; then
        cp /opt/Eye/docs/systemd/dhcp-log-truncate.service /etc/systemd/system/
    fi

    # Enable services
    $SERVICE_MANAGER enable dnsmasq
    $SERVICE_MANAGER start dnsmasq

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
    echo "  Language:         $EYE_LANG"  # <-- Добавлено
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


    # Глобальные переменные
    MYSQL_PASSWORD=""
    POSTGRES_PASSWORD=""
    DB_TYPE="mysql"
    EYE_LANG="english"
    EYE_LANG_SHORT="en"
    SQL_DATA_FILE=
    SQL_CREATE_FILE=
    INSTALL_TYPE="full"
    DB_INSTALL="local"

    # Execute installation steps
    check_root
    detect_distro
    select_language_with_auto
    select_database_type
    update_system
    install_packages
    create_user_group
    install_source_code
    download_additional_scripts
    setup_database
    setup_configs
    apply_language_settings
    setup_apache_php
    setup_cron_logrotate
    setup_additional_services
    import_mac_database

    show_final_instructions
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
    update_system
    install_packages
    install_source_code
    import_mac_database
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
        # autodetect mode
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

        if $user_exists && $dir_exists; then
            mode="upgrade"
            echo "Existing installation detected. Switching to upgrade mode."
        else
            mode="install"
            echo "No existing installation found. Switching to install mode."
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
