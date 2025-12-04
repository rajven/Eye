#!/bin/bash

set -o pipefail

# Целевой файл
TARGET_FILE="/opt/Eye/scripts/utils/mac-oids/manuf.csv"
BACKUP_FILE="/opt/Eye/scripts/utils/mac-oids/manuf.csv.backup"

# Perl скрипт для преобразования формата oui.txt в CSV
convert_oui_to_csv() {
    local input_file="$1"
    local output_file="$2"
    
    perl -e '
use strict;
use warnings;

my $in_block = 0;
my $hex_code = "";
my $company_name = "";
my @address_lines = ();
my $block_count = 0;

open(my $out, ">", $ARGV[1]) or die "Cannot open output file: $!";

open(my $in, "<", $ARGV[0]) or die "Cannot open input file: $ARGV[0]: $!";
while (my $line = <$in>) {
    chomp $line;
    
    # Пропускаем заголовки
    next if $line =~ /^OUI\/MA-L/;
    next if $line =~ /^company_id/;
    next if $line =~ /^Address/;
    
    # Находим строку с hex-кодом (формат: XX-XX-XX   (hex)                Company Name)
    if ($line =~ /^([0-9A-F]{2}-[0-9A-F]{2}-[0-9A-F]{2})\s+\(hex\)\s+(.+)$/) {
        save_block() if $in_block && $hex_code && $company_name;
        
        $in_block = 1;
        $hex_code = $1;
        $company_name = $2;
        @address_lines = ();
        next;
    }
    
    # Находим base16 строку (формат: XXXXXX     (base 16)            Company Name)
    if ($line =~ /^([0-9A-F]{6})\s+\(base 16\)\s+(.+)$/) {
        if (!$hex_code) {
            my $hex_part = $1;
            $hex_code = substr($hex_part, 0, 2) . "-" . substr($hex_part, 2, 2) . "-" . substr($hex_part, 4, 2);
            $company_name = $2;
            $in_block = 1;
            @address_lines = ();
        }
        next;
    }
    
    # Если мы внутри блока и строка не пустая и не начинается с заглавной буквы или цифры (новый блок)
    if ($in_block && $line =~ /\S/ && $line !~ /^[A-Z]/ && $line !~ /^[0-9]/) {
        push @address_lines, $line;
        next;
    }
    
    # Конец блока (пустая строка или начало нового блока)
    if ($line =~ /^\s*$/ || $line =~ /^[A-Z]/ || $line =~ /^[0-9]/) {
        save_block() if $in_block && $hex_code && $company_name;
        $in_block = 0;
        $hex_code = "";
        $company_name = "";
        @address_lines = ();
        next;
    }
}

save_block() if $in_block && $hex_code && $company_name;

close $in;
close $out;

print STDERR "Processed blocks: $block_count\n";

sub save_block {
    $block_count++;
    
    # Форматируем MAC адрес
    $hex_code =~ s/-/:/g;
    
    # Формируем EXT INFO из адресных строк
    my $ext_info = "";
    if (@address_lines) {
        $ext_info = join(", ", @address_lines);
    }
    
    # Очищаем поля
    $company_name =~ s/\s+/ /g;
    $company_name =~ s/^\s+|\s+$//g;
    $company_name =~ s/"/'\''/g;
    
    $ext_info =~ s/\s+/ /g;
    $ext_info =~ s/^\s+|\s+$//g;
    $ext_info =~ s/"/'\''/g;
    
    # Выводим в формате: MAC Prefix; Org; EXT INFO
    print $out "$hex_code;$company_name;$ext_info\n";
    
    # Сбрасываем переменные
    $hex_code = "";
    $company_name = "";
    @address_lines = ();
    $in_block = 0;
}
    ' "$input_file" "$output_file"
    
    return $?
}

# Perl скрипт для обработки IEEE CSV файлов
convert_ieee_csv() {
    local input_file="$1"
    local output_file="$2"
    
    perl -e '
use strict;
use warnings;
use Text::ParseWords;

open(my $out, ">>", $ARGV[1]) or die "Cannot open output file: $!";

open(my $in, "<", $ARGV[0]) or do {
    warn "Cannot open input file: $ARGV[0]: $!";
    exit 0;
};

my $line_count = 0;
while (my $line = <$in>) {
    $line_count++;
    next if $line_count == 1; # Пропускаем заголовок
    
    chomp $line;
    
    # Разбираем CSV с учетом кавычек
    my @fields = parse_line(",", 0, $line);
    
    next unless @fields >= 4;
    
    my ($registry, $assignment, $org_name, $org_address) = 
        (defined $fields[0] ? $fields[0] : "",
         defined $fields[1] ? $fields[1] : "",
         defined $fields[2] ? $fields[2] : "",
         defined $fields[3] ? $fields[3] : "");
    
    next if $assignment eq "" || $org_name eq "";
    
    # Форматируем MAC адрес
    $assignment =~ s/-/:/g;
    
    # Очищаем поля
    $org_name =~ s/^\s+|\s+$//g;
    $org_name =~ s/\s+/ /g;
    $org_name =~ s/"/'\''/g;
    
    $org_address =~ s/^\s+|\s+$//g if defined $org_address;
    $org_address = "" unless defined $org_address;
    $org_address =~ s/"/'\''/g;
    
    # Выводим
    print $out "$assignment;$org_name;$org_address\n";
}

close $in;
close $out;

print STDERR "Processed IEEE records: " . ($line_count - 1) . "\n";
    ' "$input_file" "$output_file"
    
    return $?
}

# Функция для загрузки с Wireshark (тоже нужно адаптировать формат)
download_from_wireshark() {
    echo "Пробуем скачать данные с Wireshark..."
    TEMP_FILE=$(mktemp)

    if wget -q -O - https://www.wireshark.org/download/automated/data/manuf 2>/dev/null | \
        perl -e '
use strict;
use warnings;

while (my $line = <STDIN>) {
    chomp $line;
    
    # Пропускаем комментарии и пустые строки
    next if $line =~ /^#/;
    next if $line =~ /^\s*$/;
    
    # Формат Wireshark: MAC\tVendor\tComment
    my ($mac, $vendor, $comment) = split(/\t/, $line);
    
    # Очищаем MAC адрес (убираем 00:00:00/... если есть)
    $mac =~ s/\/.*$//;
    $mac =~ s/00:00:00//;
    $mac =~ s/^\s+|\s+$//g;
    
    next if $mac eq "";
    
    # Очищаем vendor
    $vendor = "" unless defined $vendor;
    $vendor =~ s/^\s+|\s+$//g;
    $vendor =~ s/"/'\''/g;
    
    # Очищаем comment
    $comment = "" unless defined $comment;
    $comment =~ s/^\s+|\s+$//g;
    $comment =~ s/"/'\''/g;
    
    # Выводим
    print "$mac;$vendor;$comment\n";
}
        ' > "$TEMP_FILE"
    then
        if [[ -s "$TEMP_FILE" ]]; then
            echo "Данные успешно загружены с Wireshark"
            cat "$TEMP_FILE" > "$TARGET_FILE"
            rm -f "$TEMP_FILE"
            return 0
        else
            echo "Скачанный файл пуст"
            rm -f "$TEMP_FILE"
        fi
    else
        echo "Ошибка загрузки с Wireshark"
        rm -f "$TEMP_FILE"
    fi
    
    return 1
}

# Функция для проверки и использования альтернативных источников
use_alternative_sources() {
    echo "Пробуем альтернативные источники..."

    local alternative_sources=(
        "/usr/share/hwdata/oui.txt"
        "/usr/share/ieee-data/oui.txt"
        "/var/lib/ieee-data/oui.txt"
    )

    for source in "${alternative_sources[@]}"; do
        if [[ -f "$source" ]]; then
            echo "Найден файл: $source"
            echo "Конвертируем формат..."

            TEMP_FILE=$(mktemp)

            if convert_oui_to_csv "$source" "$TEMP_FILE"; then
                if [[ -s "$TEMP_FILE" ]]; then
                    echo "Конвертация успешна, копируем в $TARGET_FILE"
                    mv -f "$TEMP_FILE" "$TARGET_FILE"

                    # Добавляем заголовок
                    echo "MAC Prefix;Org;EXT INFO" | cat - "$TARGET_FILE" > "${TARGET_FILE}.tmp"
                    mv -f "${TARGET_FILE}.tmp" "$TARGET_FILE"

                    echo "Использован файл: $source"
                    return 0
                else
                    echo "Конвертированный файл пуст"
                    rm -f "$TEMP_FILE"
                fi
            else
                echo "Ошибка конвертации файла: $source"
                rm -f "$TEMP_FILE"
            fi
        fi
    done

    echo "Пробуем скачать данные с IEEE..."
    IEEE_SOURCES=(
        "http://standards-oui.ieee.org/cid/cid.csv"
        "http://standards-oui.ieee.org/iab/iab.csv"
        "http://standards-oui.ieee.org/oui/oui.csv"
        "http://standards-oui.ieee.org/oui28/mam.csv"
        "http://standards-oui.ieee.org/oui36/oui36.csv"
    )

    TEMP_COMBINED=$(mktemp)

    for ieee_url in "${IEEE_SOURCES[@]}"; do
        echo "Скачиваем: $ieee_url"
        TEMP_FILE=$(mktemp)
        if wget -q -O "$TEMP_FILE" "$ieee_url" 2>/dev/null; then
            if convert_ieee_csv "$TEMP_FILE" "$TEMP_COMBINED"; then
                echo "  OK"
            else
                echo "  Ошибка обработки"
            fi
        else
            echo "  Ошибка загрузки"
        fi
        rm -f "$TEMP_FILE"
    done

    if [[ -s "$TEMP_COMBINED" ]]; then
        echo "Объединяем и сортируем данные IEEE..."
        sort -u "$TEMP_COMBINED" > "$TARGET_FILE"
        rm -f "$TEMP_COMBINED"
        
        # Добавляем заголовок
        echo "MAC Prefix;Org;EXT INFO" | cat - "$TARGET_FILE" > "${TARGET_FILE}.tmp"
        mv -f "${TARGET_FILE}.tmp" "$TARGET_FILE"
        
        echo "Данные IEEE успешно загружены"
        return 0
    fi

    rm -f "$TEMP_COMBINED"
    echo "Все альтернативные источники недоступны"
    return 1
}

# Основная логика

if [[ -f "$TARGET_FILE" ]]; then
    cp -f "$TARGET_FILE" "$BACKUP_FILE"
    echo "Создана резервная копия: $BACKUP_FILE"
fi

# 1. пробуем Wireshark
if download_from_wireshark; then
    echo "Данные успешно загружены с Wireshark"
    [ -e "$BACKUP_FILE" ] && rm -f "$BACKUP_FILE"
    echo "Готово"
    exit 0
fi

# 2. пробуем альтернативные источники
if use_alternative_sources; then
    echo "Данные успешно получены из альтернативных источников"
    [ -e "$BACKUP_FILE" ] && rm -f "$BACKUP_FILE"
    echo "Готово"
    exit 0
fi

# 3. Если ничего не помогло
echo "Не удалось получить данные о MAC-адресах"

if [[ -f "$BACKUP_FILE" ]]; then
    echo "Восстанавливаем из резервной копии"
    cp -f "$BACKUP_FILE" "$TARGET_FILE"
    echo "Используем существующий файл"
fi

[ -e "$BACKUP_FILE" ] && rm -f "$BACKUP_FILE"

echo "Готово"
exit 1
