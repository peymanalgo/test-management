#!/usr/bin/env bash
# =====================================================================
#  آزمون‌یار — استقرار روی هاست cPanel (از طریق Terminal داخل cPanel)
#
#  این اسکریپت روی «خود هاست» اجرا می‌شود و:
#   ۱) پروژه را از گیت‌هاب clone می‌کند (شامل vendor — بدون نیاز به Composer)
#   ۲) فایل‌های عمومی را به public_html منتقل و مسیرها را اصلاح می‌کند
#   ۳) مجوزهای writable را می‌-set کند
#   ۴) شما را به نصب‌کنندهٔ وب (/install.php) هدایت می‌کند که دیتابیس می‌سازد
#
#  اجرا (در Terminal سی‌پنل):
#    bash deploy/deploy.sh https://github.com/USERNAME/REPO.git
#
#  پیش‌نیازها: cPanel Terminal فعال + دیتابیس MySQL از قبل ساخدهشده
# =====================================================================
set -euo pipefail

REPO_URL="${1:?استفاده: bash deploy/deploy.sh <آدرس-گیت> [شاخه]}"
BRANCH="${2:-main}"
APP_DIR="$HOME/azmoon-yar"
PUBLIC_HTML="$HOME/public_html"
STAMP=$(date +%Y%m%d%H%M%S)

info(){ printf '\033[1;34m[deploy]\033[0m %s\n' "$*"; }
ok(){ printf '\033[1;32m  ✓\033[0m %s\n' "$*"; }
die(){ printf '\033[1;31m  ✗ %s\033[0m\n' "$*"; exit 1; }

# PHP هاست
PHP_BIN=php
command -v php >/dev/null 2>&1 || { ls /opt/cpanel/ea-php*/root/usr/bin/php 2>/dev/null | tail -1 | xargs -I{} true; for p in /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php; do [ -x "$p" ] && PHP_BIN="$p" && break; done; }
info "PHP: $($PHP_BIN -v | head -1)"

# ۱) دریافت پروژه (کامل با vendor — بدون Composer)
if [ -d "$APP_DIR/.git" ]; then
  info "به‌روزرسانی پروژهٔ موجود در $APP_DIR"
  git -C "$APP_DIR" fetch --all --force
  git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
  info "دریافت پروژه از $REPO_URL (شاخهٔ $BRANCH)"
  git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR" || die "clone ناموفق — آدرس گیت و دسترسی عمومی/توکن را بررسی کنید"
fi
ok "کد کامل (با vendor) آماده شد"

# ۲) پشتیبان و انتشار فایل‌های عمومی
mkdir -p "$PUBLIC_HTML"
[ -f "$PUBLIC_HTML/index.php" ] && { cp -a "$PUBLIC_HTML" "$HOME/public_html.bak.$STAMP"; info "پشتیبان: public_html.bak.$STAMP"; }
cp -a "$APP_DIR/public/." "$PUBLIC_HTML/"
cp -a "$APP_DIR/install" "$PUBLIC_HTML/install" 2>/dev/null || true
ok "فایل‌های عمومی در public_html قرار گرفت"

# ۳) اصلاح مسیر bootstrap در index.php (چون public_html جدا از پوشهٔ پروژه است)
index="$PUBLIC_HTML/index.php"
python_pos_fix() { :; }
sed -i "s#require FCPATH . '../app/Config/Paths.php';#require FCPATH . '$APP_DIR/app/Config/Paths.php';#" "$index"
grep -q "$APP_DIR/app/Config/Paths.php" "$index" && ok "مسیر index.php اصلاح شد" || die "اصلاح index.php ناموفق"

# ۴) دارایی‌ها و مجوزها
cp -a "$APP_DIR/public/assets" "$PUBLIC_HTML/assets" 2>/dev/null || true
mkdir -p "$APP_DIR/writable/"{cache,logs,session,uploads}
chmod -R 755 "$APP_DIR/writable"
ok "مجوزهای writable تنظیم شد"

# ۵) اگر .env از قبل هست، احتمالاً نصب شده — وگرنه نصب‌کننده را باز کن
BASE_URL="https://${CPANEL_DOMAIN:-$(hostname 2>/dev/null || echo localhost)}/"
if [ ! -f "$APP_DIR/.env" ]; then
  # .env اولیه با دیتابیس پیش‌فرض cPanel (نام‌گذاری رایج) — install.php مقادیر نهایی را می‌نویسد
  info "پروندهٔ .env وجود ندارد؛ نصب‌کنندهٔ وب را باز کنید"
fi

echo
printf '\033[1;32m══════════════════════════════════════════════\033[0m\n'
printf '\033[1m  آمادهٔ نصب نهایی 🎉\033[0m\n'
echo "  ۱) در مرورگر باز کنید:  ${BASE_URL}install.php"
echo "  ۲) اطلاعات دیتابیس cPanel را وارد کنید (از MySQL® Databases)"
echo "  ۳) پس از نصب موفق، install.php و public_html/install را حذف کنید"
echo "  ورود نهایی: ${BASE_URL}  (admin + رمز انتخابی شما)"
printf '\033[1;32m══════════════════════════════════════════════\033[0m\n'
