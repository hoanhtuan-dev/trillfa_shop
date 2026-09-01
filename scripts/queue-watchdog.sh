#!/bin/bash
# Queue worker watchdog: keeps php artisan queue:work alive (shared hosting may kill it).
cd /home/u310846799/domains/trillfa.shop || exit 1
while true; do
  if ! pgrep -f "artisan queue:work" >/dev/null; then
    nohup php artisan queue:work --timeout=600 --tries=3 --sleep=2 >> storage/logs/queue-worker.log 2>&1 < /dev/null &
    echo "$(date '+%F %T') worker restarted" >> storage/logs/queue-watchdog.log
  fi
  sleep 45
done
