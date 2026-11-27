#!/bin/sh
# `composer dev`: what a local app runs beside Herd. The scheduler (schedule:work), Vite's hot reload, and a queue worker when QUEUE_CONNECTION
# is not sync. Without Herd it also serves the app (php artisan serve). Ctrl-C stops all of them.
set -u
cd "$(dirname "$0")/.."
trap 'trap - INT TERM; kill -TERM 0 2>/dev/null; exit 130' INT TERM
php artisan schedule:work &
npm run dev &
queue=$(php artisan tinker --execute 'echo config("queue.default");' 2>/dev/null)
if [ "$queue" != "sync" ]; then
    php artisan queue:listen --tries=3 --timeout=85 &
fi
command -v herd >/dev/null 2>&1 || php artisan serve &
wait
