<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'DashboardController::index');
$routes->match(['get', 'post'], 'login', 'AuthController::login');
$routes->post('logout', 'AuthController::logout');
$routes->get('dashboard', 'DashboardController::index');
