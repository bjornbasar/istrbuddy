#!/bin/bash
set -e

# Seed the database if it doesn't exist yet
if [ ! -f db/istrbuddy.db ]; then
    echo "First run — seeding database..."
    php bin/karhu db:seed
    chown www-data:www-data db/istrbuddy.db
fi

exec "$@"
