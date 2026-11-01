#!/bin/sh
set -eu
export APP_ENV=testing
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export DB_DATABASE=stockflow_test
export DB_USERNAME=stockflow_test
export DB_PASSWORD="${TEST_DB_PASSWORD:?Missing test database password}"
exec php artisan test "$@"
