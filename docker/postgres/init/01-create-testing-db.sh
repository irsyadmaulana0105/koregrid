#!/bin/sh
# Dijalankan otomatis HANYA saat volume Postgres pertama kali dibuat.
# Membuat database terpisah untuk test suite (migrate:fresh) agar data development aman.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<SQL
CREATE DATABASE koregrid_testing OWNER "$POSTGRES_USER";
SQL
