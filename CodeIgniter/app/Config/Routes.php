<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('login', 'AuthController::login', ['as' => 'login.form']);
$routes->post('login', 'AuthController::authenticate', ['as' => 'login.submit']);
$routes->post('logout', 'AuthController::logout', ['as' => 'logout']);
$routes->get('/', 'Home::index', ['as' => 'entrada']);
$routes->get('inicio', 'Home::dashboard', ['as' => 'inicio']);

foreach (['usuarios' => 'UsuariosController', 'clientes' => 'ClientesController', 'medidores' => 'MedidoresController'] as $resource => $controller) {
    $routes->get($resource, $controller . '::index', ['as' => $resource . '.index']);
    $routes->get($resource . '/novo', $controller . '::new', ['as' => $resource . '.new']);
    $routes->post($resource, $controller . '::create', ['as' => $resource . '.create']);
    $routes->get($resource . '/(:num)', $controller . '::show/$1', ['as' => $resource . '.show']);
    $routes->get($resource . '/(:num)/editar', $controller . '::edit/$1', ['as' => $resource . '.edit']);
    $routes->post($resource . '/(:num)/atualizar', $controller . '::update/$1', ['as' => $resource . '.update']);
    $routes->post($resource . '/(:num)/excluir', $controller . '::delete/$1', ['as' => $resource . '.delete']);
}

$routes->post('medidores/(:num)/enviar', 'MedidoresController::send/$1', ['as' => 'medidores.send']);
$routes->post('medidores/(:num)/devolver', 'MedidoresController::returnToDepot/$1', ['as' => 'medidores.return']);
