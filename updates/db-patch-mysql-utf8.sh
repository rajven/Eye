#!/bin/bash

echo "Run in mysql console:"

dbname=$1

mysql -u root -p ${dbname} -B -N -e "SHOW TABLES" | awk '{print "SET foreign_key_checks = 0; ALTER TABLE", $1, "CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; SET foreign_key_checks = 1; "}' >migration_utf8
echo "ALTER DATABASE ${dbname} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;">>migration_utf8

mysql -u root -p ${dbname} < migration_utf8
rm -f migration_utf8

exit
