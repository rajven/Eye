#!/bin/bash

db_name=$1

echo -n "Enter password:"
read db_pass

C_TABLES=$(mysql -u root -p ${db_name} --password=${db_pass} -B -N -e "SHOW TABLES")

echo "Stage 1. CHange charset for tables"
echo "${C_TABLES}" | awk '{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; SET foreign_key_checks = 1; "}' >migration_utf8
echo "ALTER DATABASE ${db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;">>migration_utf8
mysql -u root -p ${db_name} --password=${db_pass} < migration_utf8
echo "Stage 1 - Done"
>migration_utf8
echo "Stage 2. Revert filed type to TEXT"
echo "${C_TABLES}" | while read table; do
mysql -u root -p ${db_name} --password=${db_pass} -e "show create table ${table}" | sed 's/\\n/\n/g' | egrep -i "[[:space:]]MEDIUMTEXT[[:space:]]" | awk '{ print $1 }' | while read c_field; do
    echo "ALTER TABLE $table MODIFY $c_field TEXT;" >>migration_utf8
    done
done
mysql -u root -p ${db_name} --password=${db_pass} < migration_utf8
echo "Stage2 - Done"

rm -f migration_utf8

exit
