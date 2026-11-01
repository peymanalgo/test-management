#!/usr/bin/env bash
# =====================================================================
#  آزمون‌یار — سامانه مدیریت تست نرم‌افزار
#  اسکریپت استارت کامل: MariaDB + دیتابیس + Migration + وب‌سرور PHP
#
#  استفاده:
#    ./start.sh            اجرای کامل سرویس‌ها (پیش‌فرض پورت 3838)
#    ./start.sh run 8090   اجرا با پورت دلخواه
#    ./start.sh stop       توقف وب‌سرور
#    ./start.sh restart    ری‌استارت وب‌سرور
# =====================================================================
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

# خواندن تنظیمات اتصال از .env (در صورت نبود، مقادیر پیش‌فرض)
env_val() { grep -E "^[[:space:]]*$1[[:space:]]*=" .env 2>/dev/null | head -1 | sed 's/^[^=]*=[[:space:]]*//' | tr -d "\"'" ; }
DB_HOST=$(env_val database.default.hostname); DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=$(env_val database.default.port);      DB_PORT=${DB_PORT:-3307}
DB_NAME=$(env_val database.default.database);  DB_NAME=${DB_NAME:-ai_test_manager}
DB_USER=$(env_val database.default.username);  DB_USER=${DB_USER:-root}
DB_PASS=$(env_val database.default.password)
INITIAL_PW=$(env_val INITIAL_ADMIN_PASSWORD)
DB_SERVICE=wampmariadb64
PORT=3838
SERVER_LOG="$ROOT/writable/logs/server.log"

CMD="${1:-run}"
[ "$CMD" = "run" ] || [ "$CMD" = "restart" ] && [ "${2:-}" != "" ] && PORT="$2"
[ "$CMD" = "stop" ] && [ "${2:-}" != "" ] && PORT="$2"

# ---------- رنگ و پیام ----------
info()  { printf '\033[1;34m[آزمون‌یار]\033[0m %s\n' "$*"; }
ok()    { printf '\033[1;32m  ✓\033[0m %s\n' "$*"; }
warn()  { printf '\033[1;33m  ⚠\033[0m %s\n' "$*"; }
fail()  { printf '\033[1;31m  ✗\033[0m %s\n' "$*"; }

# ---------- ۱) پیدا کردن PHP ----------
find_php() {
    local candidates=(
        "/c/wamp64/bin/php/php8.3.28/php.exe"
        "/c/wamp64/bin/php/php8.4.15/php.exe"
        "/c/wamp64/bin/php/php8.2.29/php.exe"
        "/c/laragon/bin/php/php8.3/php.exe"
        "/c/xampp/php/php.exe"
    )
    for p in "${candidates[@]}"; do [ -f "$p" ] && { echo "$p"; return 0; }; done
    for d in /c/wamp64/bin/php/*/ /c/laragon/bin/php/*/; do
        [ -f "${d}php.exe" ] && { echo "${d}php.exe"; return 0; }
    done
    command -v php.exe >/dev/null 2>&1 && { command -v php.exe; return 0; }
    command -v php >/dev/null 2>&1 && { command -v php; return 0; }
    return 1
}

port_listening() { netstat -an | grep -q "TCP.*[:.]$1[[:space:]].*LISTENING"; }

pids_on_port() {
    netstat -ano | grep "TCP.*[:.]$1[[:space:]].*LISTENING" | awk '{print $NF}' | sort -u
}

stop_server() {
    info "توقف وب‌سرور روی پورت $PORT ..."
    local pids; pids=$(pids_on_port "$PORT")
    if [ -z "$pids" ]; then ok "وب‌سرور در حال اجرا نبود."; return 0; fi
    for pid in $pids; do taskkill //F //PID "$pid" >/dev/null 2>&1; done
    sleep 1
    ok "وب‌سرور متوقف شد."
}

# =====================================================================
if [ "$CMD" = "stop" ]; then stop_server; exit 0; fi

info "شروع راه‌اندازی سامانه آزمون‌یار..."

# ---------- PHP ----------
PHP_BIN=$(find_php) || { fail "PHP پیدا نشد! Wamp/XAMPP/Laragon را نصب کنید."; exit 1; }
ok "PHP: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

MISSING_EXT=""
for ext in mysqli mbstring intl; do
    "$PHP_BIN" -m | grep -iq "^$ext$" || MISSING_EXT="$MISSING_EXT $ext"
done
if [ -n "$MISSING_EXT" ]; then warn "افزونه‌های غایب:$MISSING_EXT — ممکن است برنامه کار نکند."; else ok "افزونه‌های لازم فعال است (mysqli, mbstring, intl)."; fi

# ---------- MariaDB ----------
info "بررسی سرویس پایگاه‌داده (پورت $DB_PORT) ..."
if port_listening "$DB_PORT"; then
    ok "پایگاه‌داده در دسترس است."
else
    warn "پایگاه‌داه بالا نیست؛ تلاش برای اجرای سرویس $DB_SERVICE ..."
    net start "$DB_SERVICE" >/dev/null 2>&1 || warn "اجرای سرویس ممکن نشد (نیاز به مجوز Administrator). Wamp را دستی اجرا کنید."
    for i in $(seq 1 30); do
        port_listening "$DB_PORT" && break
        sleep 1
    done
    port_listening "$DB_PORT" && ok "پایگاه‌داده بالا آمد." || { fail "پایگاه‌داده روی پورت $DB_PORT در دسترس نیست! سرویس $DB_SERVICE را دستی اجرا کنید."; exit 1; }
fi

# ---------- دیتابیس + Migration + مدیر اولیه ----------
export DB_HOST DB_PORT DB_NAME DB_USER DB_PASS

ENSURE_OUT=$(INITIAL_ADMIN_PASSWORD="$INITIAL_PW" "$PHP_BIN" "$ROOT/bin/ensure_ready.php" 2>&1) || { fail "ساخت/اتصال دیتابیس ناموفق بود:"; echo "$ENSURE_OUT" | tail -3; exit 1; }
echo "$ENSURE_OUT" | grep -q "DB_READY" && ok "دیتابیس «$DB_NAME» آماده است." || { fail "آماده‌سازی دیتابیس ناموفق:"; echo "$ENSURE_OUT"; exit 1; }

info "بررسی Migration ها ..."
PENDING=$("$PHP_BIN" spark migrate:status 2>/dev/null | grep -cE '^\|[^|]+\|[^|]+\|[^|]+\|[^|]+\|[[:space:]]+\|')
if [ "${PENDING:-0}" -gt 0 ]; then
    MIG_OUT=$("$PHP_BIN" spark migrate 2>&1) || { fail "Migration ناموفق:"; echo "$MIG_OUT" | tail -5; exit 1; }
    ok "Migration های جدید اعمال شدند."
else
    ok "اسکیمای دیتابیس به‌روز است."
fi

ENSURE2=$(INITIAL_ADMIN_PASSWORD="$INITIAL_PW" "$PHP_BIN" "$ROOT/bin/ensure_ready.php" 2>&1)
case "$ENSURE2" in
    *ADMIN_CREATED*)   ok "مدیر اولیه با نام کاربری admin ساخته شد." ;;
    *ADMIN_EXISTS*)    ok "حساب مدیر موجود است (admin)." ;;
    *ADMIN_PASSWORD_MISSING*) warn "مدیری وجود ندارد و INITIAL_ADMIN_PASSWORD در .env تنظیم نشده است." ;;
    *) warn "بررسی مدیر اولیه: $ENSURE2" ;;
esac

# ---------- وب‌سرور ----------
[ "$CMD" = "restart" ] && stop_server
stop_server >/dev/null 2>&1

info "راه‌اندازی وب‌سرور روی پورت $PORT ..."
mkdir -p writable/logs
( nohup "$PHP_BIN" spark serve --host 0.0.0.0 --port "$PORT" >"$SERVER_LOG" 2>&1 & ) 2>/dev/null

UP=0
for i in $(seq 1 30); do
    sleep 1
    if port_listening "$PORT"; then
        CODE=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/login" 2>/dev/null || echo 000)
        [ "$CODE" = "200" ] && { UP=1; break; }
    fi
done
if [ "$UP" != "1" ]; then
    fail "وب‌سرور بالا نیامد! ده خط آخر لاگ:"
    tail -10 "$SERVER_LOG" 2>/dev/null
    exit 1
fi
ok "وب‌سرور فعال است. لاگ: writable/logs/server.log"

LAN_IP=$(powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object {\$_.IPAddress -notlike '169.254*' -and \$_.IPAddress -ne '127.0.0.1'} | Select-Object -First 1).IPAddress" 2>/dev/null | tr -d '\r')

echo
printf '\033[1;32m══════════════════════════════════════════════════════\033[0m\n'
printf '\033[1m  آزمون‌یار آماده است 🎉\033[0m\n'
echo "  --------------------------------------------------"
echo "  ورود:            http://localhost:$PORT/login"
[ -n "${LAN_IP:-}" ] && echo "  از شبکه محلی:    http://$LAN_IP:$PORT/login"
echo "  نام کاربری:      admin"
echo "  رمز عبور:        همان مقدار INITIAL_ADMIN_PASSWORD در فایل .env"
echo "  --------------------------------------------------"
echo "  توقف:            ./start.sh stop"
echo "  ری‌استارت:        ./start.sh restart"
printf '\033[1;32m══════════════════════════════════════════════════════\033[0m\n'

cmd //c start "" "http://localhost:$PORT/login" >/dev/null 2>&1 || true
info "مرورگر باز شد. اگر باز نشد، آدرس بالا را دستی وارد کنید."
