#!/bin/sh

set -eu

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS `${MYSQL_TEST_DATABASE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOSQL

if [ -n "${MYSQL_USER:-}" ] && [ "${MYSQL_USER}" != "root" ]; then
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
        GRANT ALL PRIVILEGES ON `${MYSQL_TEST_DATABASE}`.* TO '${MYSQL_USER}'@'%';
        FLUSH PRIVILEGES;
EOSQL
fi
