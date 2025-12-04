#!/usr/bin/perl 

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
no warnings 'utf8';
use Encode qw(encode decode);
use open ':std', ':encoding(utf8)';
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::net_utils;
use strict;
use warnings;

binmode(STDOUT, ':encoding(utf8)');
binmode(STDERR, ':encoding(utf8)');

my $clean_run = $ARGV[0] || '';
my $batch_size = 1000;

# Очищаем таблицу если нужно
if ($clean_run eq 'clean') {
    do_sql($dbh, "TRUNCATE TABLE mac_vendors");
    print "Таблица очищена\n";
}

# Получаем существующие OUI из базы
my %existing_oui;
if ($clean_run ne 'clean') {
    print "Получаем существующие OUI из базы...\n";
    my $sth = $dbh->prepare("SELECT oui FROM mac_vendors");
    $sth->execute;
    while (my ($oui) = $sth->fetchrow_array) {
        $existing_oui{$oui} = 1;
    }
    $sth->finish;
    print "Найдено существующих записей: " . scalar(keys %existing_oui) . "\n";
}

my $filename = "/opt/Eye/scripts/utils/mac-oids/manuf.csv";
open(my $fh, '<:encoding(utf8)', $filename) 
    or die "Не удалось открыть файл $filename: $!";

print "Обработка файла...\n";

my @batch_data;
my $total_processed = 0;
my $total_inserted = 0;
my $line_num = 0;

while (my $line = <$fh>) {
    $line_num++;
    chomp $line;
    
    # Пропускаем пустые строки
    next if $line =~ /^\s*$/;
    
    # Пропускаем заголовок
    next if $line_num == 1 && $line =~ /^MAC Prefix/;
    
    # Разбиваем на поля
    my @fields = split(/;/, $line, 3);
    
    my $oui = $fields[0] || '';
    my $company = $fields[1] || '';
    my $address = $fields[2] || '';
    
    # Убираем кавычки если есть
    $oui =~ s/^\"|\"$//g;
    $company =~ s/^\"|\"$//g;
    $address =~ s/^\"|\"$//g;
    
    # Очищаем данные
    $oui = trim($oui);
    $company = trim($company);
    $address = trim($address);
    
    # Пропускаем если нет OUI
    next unless $oui && $oui =~ /\S/;
    
    # Убираем маску подсети
    $oui =~ s{/[0-9]+}{};
    
    # Нормализуем MAC
    $oui = mac_splitted($oui);
    
    # Проверяем минимальную длину
    next unless length($oui) >= 8;
    
    # Пропускаем если уже есть в базе
    next if $existing_oui{$oui};
    
    # Добавляем в пакет
    push @batch_data, [$oui, $company, $address];
    $total_processed++;
    
    # Вставляем пакет при достижении размера
    if (@batch_data >= $batch_size) {
        $total_inserted += insert_batch_simple(\@batch_data);
        @batch_data = ();
    }
    
    # Прогресс
    print "Обработано строк: $line_num\r" if ($line_num % 1000 == 0);
}

close $fh;

# Вставляем остатки
if (@batch_data) {
    $total_inserted += insert_batch_simple(\@batch_data);
}

print "\n\nИтоги:\n";
print "Обработано строк: $total_processed\n";
print "Добавлено записей: $total_inserted\n";
print "Done!\n";

exit;

# Простая пакетная вставка
sub insert_batch_simple {
    my ($batch_ref) = @_;
    my @data = @$batch_ref;
    
    return 0 unless @data;
    
    my $sth = $dbh->prepare("
        INSERT INTO mac_vendors (oui, companyName, companyAddress) 
        VALUES (?, ?, ?)
    ");
    
    $dbh->begin_work;
    my $inserted = 0;
    
    foreach my $row (@data) {
        my ($oui, $company, $address) = @$row;
        
        eval {
            $sth->execute($oui, $company, $address);
            $inserted++;
        };
        
        if ($@) {
            warn "Ошибка при вставке $oui: $@\n";
        }
    }
    
    $dbh->commit;
    $sth->finish;
    
    return $inserted;
}
