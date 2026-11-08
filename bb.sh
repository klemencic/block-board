#!/usr/bin/env sh
# Pomožni ukaz: zažene ukaz v PHP vsebniku (npr. ./bb.sh php artisan migrate)
exec docker compose run --rm --no-deps -T api "$@"
