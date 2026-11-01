# استقرار آزمون‌یار روی هاست cPanel

این راهنما سه روش برای انتقال کامل سامانه (کد + دیتابیس) به هاست سی‌پنل ارائه می‌کند.
مخزن گیت **شامل `vendor/` کامل** است؛ بنابراین روی هاست **به Composer، Node و SSH نیاز نیست**.

> پیش‌نیازهای هاست: PHP 8.2+ با افزونه‌های `mysqli, mbstring, intl, gd, zip, dom, xml, fileinfo` + MySQL/MariaDB.

---

## روش ۱ (پیشنهادی): ترمینال cPanel + اسکریپت استقرار

اگر در cPanel شما بخش **Terminal** فعال است:

1. در cPanel یک دیتابیس بسازید (**MySQL® Databases**): دیتابیس + کاربر + رمز، و کاربر را با دسترسی FULL به دیتابیس اضافه کنید. نام‌ها را یادداشت کنید (معمولاً `cpaneluser_xxx`).
2. **Terminal** را باز کنید و بزنید:

```bash
cd ~
git clone https://github.com/peymanalgo/test-management.git azmoon-yar
bash azmoon-yar/deploy/deploy.sh https://github.com/peymanalgo/test-management.git main
```

اسکریپت: کد کامل را می‌آورد (با vendor)، `public` را به `public_html` منتقل و مسیر `index.php` را اصلاح می‌کند و مجوزهای `writable` را تنظیم می‌کند.
3. در مرورگر باز کنید: `https://دامنه-شما/install.php` — نصب‌کنندهٔ وب باز می‌شود.
4. در نصب‌کننده: اطلاعات دیتابیس مرحلهٔ ۱ + **رمز مدیر** (حداقل ۱۲ نویسه) + نشانی پایه را وارد و «شروع نصب» بزنید. جدول‌ها از `install/schema.sql` ساخته و کاربر `admin` ایجاد می‌شود و `.env` تولید و نصب‌کننده قفل می‌شود.
5. برای امنیت `install.php` و پوشهٔ `public_html/install` را حذف کنید.
6. ورود: `https://دامنه-شما/` با `admin` و رمز انتخابی شما.

برای به‌روزرسانی‌های بعدی فقط کافی است در Terminal بزنید:
```bash
bash ~/azmoon-yar/deploy/deploy.sh https://github.com/peymanalgo/test-management.git main
```
(داده‌ها و `.env` دست‌نخورده می‌مانند.)

---

## روش ۲: Git Version Control خود cPanel (بدون ترمینال)

1. cPanel → **Git Version Control** → Create → Repository URL همان آدرس گیت‌هاب → مسیر `azmoon-yar` (داخل home).
2. بعد از Clone، از **File Manager**:
   - محتوای `azmoon-yar/public` را به `public_html` کپی کنید (+ پوشهٔ `install`).
   - در `public_html/index.php` خط `require FCPATH . '../app/Config/Paths.php';` را به مسیر مطلق پروژه تغییر دهید، مثلاً:
     `require FCPATH . '/home/CPANELUSER/azmoon-yar/app/Config/Paths.php';`
3. همان مراحل ۳ تا ۵ روش ۱ (باز کردن `/install.php`).

---

## روش ۳: آپلود ZIP (بدون گیت و بدون ترمینال)

1. روی سیستم خودتان: **Code → Download ZIP** از گیت‌هاب (یا از مخزن محلی یک ZIP بسازید) — باید شامل `vendor/` باشد.
2. cPanel → **File Manager**:
   - ZIP را در `/home/CPANELUSER/` آپلود و **Extract** کنید تا پوشهٔ `azmoon-yar` ساخته شود.
   - محتوای `azmoon-yar/public` + پوشهٔ `install` را به `public_html` منتقل کنید.
   - خط `paths` در `public_html/index.php` را مثل روش ۲ اصلاح کنید.
3. cPanel → **phpMyAdmin** → دیتابیس خود را انتخاب → Import → فایل `azmoon-yar/install/schema.sql` (ساختار + نقش‌ها).
   سپس در تب SQL این را اجرا کنید تا مدیر ساخته شود (رمز را عوض کنید):

```sql
INSERT INTO users (username, display_name, password_hash, is_admin, is_active, must_change_password, created_at)
VALUES ('admin', 'مدیر سیستم', 'HASH', 1, 1, 0, UTC_TIMESTAMP());
```

   برای ساخت `HASH` می‌توانید از نصب‌کنندهٔ `/install.php` استفاده کنید (روش راحت‌تر) یا هش Argon2id بسازید.
4. فایل `.env` را در ریشهٔ `azmoon-yar` بسازید (الگو در `env` موجود است) و مقادیر production را بگذارید.

---

## ساختار پیشنهادی روی هاست

```
/home/CPANELUSER/
├── azmoon-yar/          ← کل پروژه از گیت (app, vendor, writable, .env)
└── public_html/         ← فقط public + install.php (بعد از نصب حذف شود)
```

## چک‌لیست پس از استقرار

- [ ] ورود `admin` کار می‌کند و رمز قوی است
- [ ] `CI_ENVIRONMENT = production` در `.env`
- [ ] SSL فعال (cPanel → SSL/TLS Status → AutoSSL) و بازدید http به https ریدایرکت شود
- [ ] `install.php` و `public_html/install` حذف شده‌اند
- [ ] یک پروژه + درخت + مسئولان + چند سناریو ساخته و چرخهٔ تست یک‌بار کامل اجرا شد
- [ ] از **Backup** سی‌پنل برای دیتابیس و پوشهٔ `azmoon-yar` (شامل پیوست‌ها و `.env`) زمان‌بندی کنید

## رفع اشکال رایج

| مشکل | راه‌حل |
|---|---|
| صفحهٔ سفید/خطای 500 | `writable/logs` را ببینید؛ معمولاً افزونهٔ PHP کم است یا `.env` ساخته نشده |
| صفحهٔ 404 به‌جز صفحهٔ اول | مطمئن شوید `public_html/.htaccess` موجود است (در `public/` پروژه هست) |
| خطای اتصال DB | پیشوندهای `cpaneluser_` را در نام دیتابیس/کاربر چک کنید |
| تاریخ‌ها اشتباه | زمان سرور مهم نیست؛ تاریخ‌ها UTC ذخیره و شمسی/میلادی نمایش داده می‌شوند |
