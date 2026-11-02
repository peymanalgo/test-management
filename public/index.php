<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.2'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// به‌صورت خودکار در چیدمان‌های رایج پیدا می‌شود: کنار public ، داخل همان پوشه، یا ~/azmoon-yar
$_pathsCandidates = [
    FCPATH . '../app/Config/Paths.php',        // استاندارد: app کنارِ public
    FCPATH . 'app/Config/Paths.php',           // کل پروژه داخل Document Root
    FCPATH . '../azmoon-yar/app/Config/Paths.php', // پروژه در ~/azmoon-yar
];
$_pathsPath = null;
foreach ($_pathsCandidates as $_c) {
    if (is_file($_c)) { $_pathsPath = $_c; break; }
}
if ($_pathsPath === null && is_file(FCPATH . '../../app/Config/Paths.php')) {
    $_pathsPath = FCPATH . '../../app/Config/Paths.php';
}
if ($_pathsPath === null) {
    http_response_code(500);
    exit('app/Config/Paths.php not found. Looked in: ' . implode(' | ', $_pathsCandidates));
}
require $_pathsPath;
unset($_pathsCandidates, $_c, $_pathsPath);

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
