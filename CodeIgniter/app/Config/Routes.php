<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('login', 'AuthController::login', ['as' => 'login.form']);
$routes->post('login', 'AuthController::authenticate', ['as' => 'login.submit']);
$routes->post('logout', 'AuthController::logout', ['as' => 'logout']);
$routes->get('/', 'Home::index', ['as' => 'entrada']);
$routes->get('inicio', 'Home::dashboard', ['as' => 'inicio']);
$routes->get('relatorios/eletricistas', 'RelatoriosController::electricians', ['as' => 'relatorios.eletricistas']);
$routes->get('relatorios/estoque', 'RelatoriosController::stock', ['as' => 'relatorios.estoque']);

foreach (['usuarios' => 'UsuariosController', 'clientes' => 'ClientesController', 'medidores' => 'MedidoresController', 'consumiveis' => 'ConsumiveisController'] as $resource => $controller) {
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
$routes->post('consumiveis/(:num)/entrada', 'ConsumiveisController::entry/$1', ['as' => 'consumiveis.entry']);
$routes->post('os/(:num)/consumiveis/reservar', 'OrdensServicoController::reserveConsumable/$1', ['as' => 'os.consumiveis.reserve']);
$routes->post('os/(:num)/consumiveis/(:num)/entregar', 'OrdensServicoController::deliverConsumable/$1/$2', ['as' => 'os.consumiveis.deliver']);
$routes->post('os/(:num)/consumiveis/(:num)/receber', 'OrdensServicoController::receiveConsumable/$1/$2', ['as' => 'os.consumiveis.receive']);
$routes->post('os/(:num)/checklists/(:num)/responder-inicio', 'OrdensServicoController::answerBeginning/$1/$2', ['as' => 'os.checklist.answer']);
$routes->post('os/(:num)/avaliacoes/(:num)/liberar-inicio', 'OrdensServicoController::releaseBeginning/$1/$2', ['as' => 'os.checklist.release']);

$routes->get('os', 'OrdensServicoController::index', ['as' => 'os.index']);
$routes->get('os/nova', 'OrdensServicoController::new', ['as' => 'os.new']);
$routes->post('os', 'OrdensServicoController::create', ['as' => 'os.create']);
$routes->get('os/(:num)', 'OrdensServicoController::show/$1', ['as' => 'os.show']);
$routes->get('os/(:num)/editar', 'OrdensServicoController::edit/$1', ['as' => 'os.edit']);
$routes->post('os/(:num)/atualizar', 'OrdensServicoController::update/$1', ['as' => 'os.update']);
$routes->post('os/(:num)/atribuir', 'OrdensServicoController::assign/$1', ['as' => 'os.assign']);
$routes->post('os/(:num)/cancelar', 'OrdensServicoController::cancel/$1', ['as' => 'os.cancel']);
$routes->get('checklists', 'ChecklistsController::index', ['as' => 'checklists.index']);
$routes->get('checklists/novo', 'ChecklistsController::new', ['as' => 'checklists.new']);
$routes->post('checklists', 'ChecklistsController::create', ['as' => 'checklists.create']);
$routes->get('checklists/(:num)', 'ChecklistsController::show/$1', ['as' => 'checklists.show']);
$routes->get('checklists/(:num)/editar', 'ChecklistsController::edit/$1', ['as' => 'checklists.edit']);
$routes->post('checklists/(:num)/atualizar', 'ChecklistsController::update/$1', ['as' => 'checklists.update']);
$routes->post('checklists/(:num)/itens', 'ChecklistsController::createItem/$1', ['as' => 'checklists.item.create']);
$routes->get('checklists/(:num)/itens/(:num)/editar', 'ChecklistsController::editItem/$1/$2', ['as' => 'checklists.item.edit']);
$routes->post('checklists/(:num)/itens/(:num)/atualizar', 'ChecklistsController::updateItem/$1/$2', ['as' => 'checklists.item.update']);
$routes->post('checklists/(:num)/itens/(:num)/excluir', 'ChecklistsController::deleteItem/$1/$2', ['as' => 'checklists.item.delete']);

$routes->post('os/(:num)/medidores/reservar', 'OrdensServicoController::reserveMeter/$1', ['as' => 'os.medidores.reserve']);
$routes->post('os/(:num)/medidores/(:num)/entregar', 'OrdensServicoController::deliverMeter/$1/$2', ['as' => 'os.medidores.deliver']);
$routes->post('os/(:num)/medidores/(:num)/receber', 'OrdensServicoController::receiveMeter/$1/$2', ['as' => 'os.medidores.receive']);
$routes->post('os/(:num)/medidores/(:num)/ocorrencia', 'OrdensServicoController::meterOccurrence/$1/$2', ['as' => 'os.medidores.occurrence']);
$routes->post('medidores/(:num)/ocorrencia', 'MedidoresController::occurrence/$1', ['as' => 'medidores.occurrence']);

$routes->post('os/(:num)/iniciar-atendimento', 'OrdensServicoController::startAttendance/$1', ['as' => 'os.attendance.start']);
$routes->post('os/(:num)/observacoes-atendimento', 'OrdensServicoController::noteAttendance/$1', ['as' => 'os.attendance.note']);

$routes->post('os/(:num)/consumiveis/(:num)/consumir', 'OrdensServicoController::consumeConsumable/$1/$2', ['as' => 'os.consumiveis.consume']);
$routes->post('os/(:num)/medidores/(:num)/aplicar', 'OrdensServicoController::applyMeter/$1/$2', ['as' => 'os.medidores.apply']);
$routes->post('os/(:num)/medidores/(:num)/retirar', 'OrdensServicoController::withdrawMeter/$1/$2', ['as' => 'os.medidores.withdraw']);

$routes->post('os/(:num)/checklists/(:num)/responder-fechamento', 'OrdensServicoController::answerClosing/$1/$2', ['as' => 'os.checklist.closing']);
$routes->post('os/(:num)/encerrar', 'OrdensServicoController::closeAttendance/$1', ['as' => 'os.attendance.close']);

$routes->post('os/(:num)/fotos', 'OrdensServicoController::uploadPhoto/$1', ['as'=>'os.photos.upload']);
$routes->get('os/(:num)/fotos/(:num)', 'OrdensServicoController::photo/$1/$2', ['as'=>'os.photos.show']);
$routes->post('os/(:num)/fotos/(:num)/remover', 'OrdensServicoController::removePhoto/$1/$2', ['as'=>'os.photos.remove']);
