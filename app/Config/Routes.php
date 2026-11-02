<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ---------------- عمومی ----------------
$routes->get('/', 'DashboardController::index');
$routes->match(["GET", "POST"], 'login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');
$routes->get('logout', 'AuthController::logout');
$routes->get('lang/(:alpha)', 'LangController::switch/$1');
// فایل‌های استاتیک از طریق PHP — دور زدن مسدودسازی 403 سطح وب‌سرور هاست‌های اشتراکی
$routes->get('assets/(:alpha)/(:any)', 'AssetController::serve/$1/$2');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {

    // ---------------- ادمین: داشبورد ----------------
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('dashboard/recent', 'DashboardController::recent'); // AJAX آخرین اجراها

    // ---------------- ادمین: پروژه‌ها و درخت ----------------
    $routes->match(["GET", "POST"], 'projects', 'ProjectsController::index');
    $routes->post('projects/(:num)/update', 'ProjectsController::update/$1');           // ویرایش پروژه
    $routes->get('projects/(:num)/tree', 'ProjectsController::tree/$1');
    $routes->post('projects/(:num)/nodes/save', 'ProjectsController::saveNode/$1');      // ایجاد/ویرایش گره
    $routes->post('nodes/(:num)/delete', 'ProjectsController::deleteNode/$1');
    $routes->post('nodes/(:num)/assign', 'ProjectsController::assignNode/$1');          // سه مسئول بخش
    $routes->get('nodes/(:num)/cases', 'ProjectsController::nodeCases/$1');              // گرید سناریوهای بخش (AJAX)
    $routes->post('nodes/(:num)/cases/save', 'ProjectsController::saveCase/$1');        // CRUD سناریو
    $routes->post('cases/(:num)/archive', 'ProjectsController::archiveCase/$1');
    $routes->post('nodes/(:num)/import', 'ProjectsController::importPreview/$1');       // مرحله ۱ اکسل
    $routes->post('nodes/(:num)/import/commit', 'ProjectsController::importCommit/$1'); // مرحله ۲ اکسل
    $routes->get('import/template', 'ProjectsController::template');                    // قالب XLSX

    // ---------------- ادمین: سناریوها (گرید سراسری) ----------------
    $routes->get('test-cases', 'TestCasesController::index');
    $routes->get('test-cases/data', 'TestCasesController::data');                       // گرید (AJAX)
    $routes->get('test-cases/(:num)/detail', 'TestCasesController::detail/$1');         // پاپ‌آپ فقط‌خواندنی
    $routes->get('history/(:num)', 'TestCasesController::timeline/$1');                 // درخت تاریخچهٔ سناریو

    // ---------------- ادمین: کاربران ----------------
    $routes->get('admin/users', 'AdminUsersController::index');
    $routes->get('admin/users/data', 'AdminUsersController::data');
    $routes->post('admin/users/save', 'AdminUsersController::save');
    $routes->post('admin/users/(:num)/toggle', 'AdminUsersController::toggle/$1');

    // ---------------- ادمین: آپدیت ----------------
    $routes->get('admin/update', 'UpdateController::index');
    $routes->get('admin/update/check', 'UpdateController::check');
    $routes->match(['GET', 'POST'], 'admin/update/apply', 'UpdateController::apply');

    // ---------------- کارتابل (غیرادمین) ----------------
    $routes->get('workbench', 'WorkbenchController::index');
    $routes->get('workbench/(:num)/detail', 'WorkbenchController::detail/$1');          // جزئیات برای مودال
    $routes->post('workbench/(:num)/start', 'WorkbenchController::start/$1');
    $routes->post('workbench/(:num)/execute', 'WorkbenchController::execute/$1');
    $routes->post('workbench/(:num)/unblock', 'WorkbenchController::unblock/$1');
    $routes->post('workbench/(:num)/approve', 'WorkbenchController::approve/$1');
    $routes->post('workbench/(:num)/reject', 'WorkbenchController::reject/$1');
    $routes->post('workbench/(:num)/reopen', 'WorkbenchController::reopen/$1');
    $routes->post('workbench/(:num)/cancel', 'WorkbenchController::cancel/$1');
    $routes->post('defects/(:num)/fix-start', 'WorkbenchController::startFix/$1');
    $routes->post('defects/(:num)/fix-done', 'WorkbenchController::declareFixed/$1');

    // ---------------- مشترک: اعلان‌ها ----------------
    $routes->get('notifications', 'NotificationsController::index');
    $routes->get('notifications/latest', 'NotificationsController::latest');
    $routes->post('notifications/read/(:num)', 'NotificationsController::read/$1');
    $routes->post('notifications/read-all', 'NotificationsController::readAll');
});
