<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Authentication routes
$routes->group('auth', static function ($routes) {
    service('auth')->routes($routes);
});

// API routes
$routes->group('api', static function ($routes) {
    $routes->group('v1', ['namespace' => 'App\Controllers\API\v1', 'filter' => 'cors'], static function ($routes) {
        $routes->group('auth', ['filter' => 'auth-rates'], static function ($routes) {
            $routes->post('login', 'Auth::login');
        });
    });
});
