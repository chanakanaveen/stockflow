#!/bin/sh
set -eu
# Separate test credentials are restricted to the test database.
case "$MYSQL_TEST_PASSWORD" in
  *[!a-zA-Z0-9]*|'') echo "MYSQL_TEST_PASSWORD must be nonempty alphanumeric" >&2; exit 1 ;;
esac
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS stockflow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'stockflow_test'@'%' IDENTIFIED BY '$MYSQL_TEST_PASSWORD';
GRANT ALL PRIVILEGES ON stockflow_test.* TO 'stockflow_test'@'%';
SQL
