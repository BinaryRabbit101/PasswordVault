#!/usr/bin/env bash
# Run the Dusk browser suite (the estate recipe from HUB STANDARDS §3):
# swap in .env.dusk.local, fresh-migrate database/testing/dusk.sqlite, build
# assets, serve on 127.0.0.1:8449, run `php artisan dusk`, then put .env back
# and stop the server — whether or not the tests pass.
#
#   scripts/dusk.sh                      # whole suite
#   scripts/dusk.sh --filter=history     # extra args go to `php artisan dusk`
#   SKIP_BUILD=1 scripts/dusk.sh         # reuse public/build
set -uo pipefail
cd "$(dirname "$0")/.."

PORT=8449

if [[ ! -f .env.dusk.local ]]; then
    echo "Missing .env.dusk.local — copy .env.dusk.example and set APP_KEY (php artisan key:generate --show)." >&2
    exit 1
fi

if [[ -f .env.pretest-backup ]]; then
    echo ".env.pretest-backup already exists — a previous run didn't clean up. Restore it to .env by hand first." >&2
    exit 1
fi

SERVER_PID=""
cleanup() {
    [[ -n "$SERVER_PID" ]] && kill "$SERVER_PID" 2>/dev/null
    # `php artisan serve` forks a child PHP server on Windows; stop whatever holds the port.
    if command -v netstat >/dev/null 2>&1 && command -v taskkill >/dev/null 2>&1; then
        netstat -ano | awk -v p=":$PORT" '$2 ~ p"$" && /LISTENING/ {print $5}' | sort -u |
            while read -r pid; do taskkill //F //PID "$pid" >/dev/null 2>&1; done
    fi
    if [[ -f .env.pretest-backup ]]; then
        cp .env.pretest-backup .env && rm -f .env.pretest-backup
    fi
}
trap cleanup EXIT INT TERM

cp .env .env.pretest-backup
cp .env.dusk.local .env

mkdir -p database/testing
touch database/testing/dusk.sqlite
php artisan config:clear >/dev/null
php artisan migrate:fresh --force >/dev/null || exit 1

rm -f public/hot
if [[ -z "${SKIP_BUILD:-}" ]]; then
    npm run build >/dev/null || exit 1
fi

php artisan serve --host=127.0.0.1 --port="$PORT" >storage/logs/dusk-serve.log 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 40); do
    curl -s -o /dev/null "http://127.0.0.1:$PORT/login" && break
    sleep 0.25
done

PAO_DISABLE=1 php artisan dusk "$@"
