<?php
/**
 * آماده‌سازی اولیهٔ آزمون‌یار — توسط start.sh فراخوانی می‌شود.
 *  ۱) ساخت دیتابیس در صورت نبود (اجرای اول)
 *  ۲) ساخت مدیر اولیه فقط زمانی که هیچ مدیری وجود نداشته باشد (پس از migrate)
 *
 * مقادیر اتصال از متغیرهای محیطی خوانده می‌شوند (DB_HOST، DB_PORT، DB_NAME، DB_USER، DB_PASS،
 * INITIAL_ADMIN_PASSWORD) که در start.sh تنظیم یا از .env خوانده می‌شوند.
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3307';
$name = getenv('DB_NAME') ?: 'ai_test_manager';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$adminPw = getenv('INITIAL_ADMIN_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$name}`");
} catch (PDOException $e) {
    fwrite(STDERR, 'DB_FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
echo 'DB_READY' . PHP_EOL;

try {
    $admins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=1 AND deleted_at IS NULL')->fetchColumn();
    if ($admins > 0) {
        echo 'ADMIN_EXISTS' . PHP_EOL;
        exit(0);
    }
    if ($adminPw === '') {
        fwrite(STDERR, 'ADMIN_PASSWORD_MISSING' . PHP_EOL);
        exit(0);
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 0) {
        $pdo->exec('INSERT INTO roles (code, name, created_at) VALUES '
            . "('TESTER','آزمونگر',UTC_TIMESTAMP()),"
            . "('DEVELOPER','برنامه‌نویس',UTC_TIMESTAMP()),"
            . "('MANAGER','مدیر پروژه',UTC_TIMESTAMP())");
    }
    $pdo->prepare('INSERT INTO users (username, display_name, password_hash, is_admin, is_active, must_change_password, created_at) '
        . 'VALUES ("admin","مدیر سیستم",?,1,1,0,UTC_TIMESTAMP())')
        ->execute([password_hash($adminPw, PASSWORD_ARGON2ID)]);
    echo 'ADMIN_CREATED' . PHP_EOL;
} catch (PDOException $e) {
    // جدول‌ها هنوز ساخته نشده‌اند؛ پس از اجرای migrate دوباره اجرا خواهد شد
    echo 'TABLES_PENDING' . PHP_EOL;
}
