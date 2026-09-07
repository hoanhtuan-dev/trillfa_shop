#!/usr/bin/env bash
# =============================================================================
# Deploy Trillfa Shop — đẩy code từ git lên Hostinger (production)
# -----------------------------------------------------------------------------
# SSH (dùng lại lần sau):  ssh -p 65002 u310846799@145.79.25.57
# App trên server:         ~/domains/trillfa.shop   (branch main)
# Repo:                    https://github.com/hoanhtuan-dev/trillfa_shop.git
#
# Cách dùng (chạy trên máy local, sau khi đã commit + push):
#   bash scripts/deploy.sh
# =============================================================================
set -euo pipefail

SSH_TARGET="u310846799@145.79.25.57"
SSH_PORT="65002"
APP_DIR="domains/trillfa.shop"

echo "==> Deploy lên ${SSH_TARGET} (${APP_DIR})"

ssh -p "${SSH_PORT}" -o ConnectTimeout=20 "${SSH_TARGET}" "bash -s" <<'REMOTE'
set -euo pipefail
cd "$HOME/domains/trillfa.shop"

echo "-- git fetch + fast-forward pull --"
git fetch origin main
git pull --ff-only origin main

echo "-- cài vendor (bỏ scripts — shared hosting tắt proc_open) --"
composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts \
  || echo "  (composer bỏ qua — vendor đã build sẵn, chỉ deploy frontend)"

echo "-- migrate (an toàn, không đổi schema nếu không có migration mới) --"
php artisan migrate --force

echo "-- xóa cache cấu hình/route/view --"
php artisan optimize:clear

echo "-- hoàn tất. Kiểm tra: https://trillfa.shop/studio --"
REMOTE

echo "==> Done."
