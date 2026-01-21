<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Authentication routes
$routes->group('auth', ['namespace' => 'App\Controllers\Auth'], static function ($routes) {
    service('auth')->routes($routes);

    $routes->group('access-token', ['filter' => 'tokens'], static function ($routes) {
        $routes->get('list', 'AccessTokenController::index');
        $routes->post('generate', 'AccessTokenController::generate');
        $routes->delete('(:num)', 'AccessTokenController::delete/$1');
        $routes->post('revoke-token', 'AccessTokenController::revokeToken');
        $routes->post('revoke-all', 'AccessTokenController::revokeAll');
    });
});

// API routes
$routes->group('api', static function ($routes) {
    $routes->group('v1', ['namespace' => 'App\Controllers\API\v1', 'filter' => 'cors'], static function ($routes) {
        $routes->group('auth', ['filter' => 'auth-rates'], static function ($routes) {
            $routes->post('register', 'Auth::register');
            $routes->post('login', 'Auth::login');
        });

        $routes->group('auth', ['filter' => 'tokens'], static function ($routes) {
            $routes->post('logout', 'Auth::logout');
            $routes->get('me', 'Auth::me');
        });
    });
});
