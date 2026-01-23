<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Authentication routes
$routes->group('auth', ['namespace' => 'App\Controllers\Auth'], static function ($routes) {
    service('auth')->routes($routes);
});

// API routes
$routes->group('api', static function ($routes) {
    $routes->group('v1', ['namespace' => 'App\Controllers\API\v1', 'filter' => 'cors'], static function ($routes) {
        $routes->group('auth', ['filter' => 'auth-rates'], static function ($routes) {
            $routes->post('register', 'AuthController::register');
            $routes->post('login', 'AuthController::login');
            $routes->post('magic-link', 'AuthController::magicLink');
            $routes->post('magic-link/verify', 'AuthController::verifyMagicLink');
        });

        $routes->group('auth', ['filter' => 'tokens'], static function ($routes) {
            $routes->post('logout', 'AuthController::logout');
            $routes->get('me', 'AuthController::me');
        });

        $routes->group('access-token', ['filter' => 'tokens'], static function ($routes) {
            $routes->get('list', 'AccessTokenController::index');
            $routes->post('generate', 'AccessTokenController::generate');
            $routes->delete('(:num)', 'AccessTokenController::delete/$1');
            $routes->post('revoke-token', 'AccessTokenController::revokeToken');
            $routes->post('revoke-all', 'AccessTokenController::revokeAll');
        });
    });

    $routes->group('docs', ['namespace' => 'App\Controllers\API'], static function ($routes) {
        $routes->get('/', 'Swagger::index');
        $routes->get('json', 'Swagger::json');
    });
});
