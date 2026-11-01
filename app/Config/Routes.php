<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'DashboardController::index');
$routes->match(['get', 'post'], 'login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');
$routes->get('dashboard', 'DashboardController::index');
$routes->match(['get', 'post'], 'projects', 'ProjectsController::index');
$routes->match(['get', 'post'], 'projects/(:num)/tree', 'ProjectsController::tree/$1');
$routes->match(['get', 'post'], 'test-cases', 'TestCasesController::index');
$routes->match(['get', 'post'], 'test-runs', 'TestRunsController::index');
$routes->get('my-tasks', 'WorkbenchController::index');
$routes->get('defects', 'DefectsController::index');
