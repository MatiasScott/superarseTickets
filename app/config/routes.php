<?php

return static function (Router $router): void {
	$router->get('/', 'DashboardController@index');

	$router->get('/login', 'AuthController@showLogin');
	$router->post('/login', 'AuthController@login');
	$router->post('/logout', 'AuthController@logout');

	$router->get('/dashboard', 'DashboardController@index');

	$router->get('/contactos', 'ContactoController@index');

	$router->get('/crm/interesados', 'CRMController@interesados');
	$router->get('/crm/estudiantes', 'CRMController@estudiantes');

	$router->get('/tickets', 'TicketController@index');
	$router->get('/tickets/create', 'TicketController@create');
	$router->post('/tickets', 'TicketController@store');
	$router->get('/tickets/{id}', 'TicketController@show');

	$router->get('/campanas', 'CampanaController@index');
	$router->get('/bot', 'BotController@index');
	$router->get('/relaciones', 'RelacionesController@index');
	$router->get('/academico', 'ControlAcademicoController@index');
	$router->get('/auditoria', 'AuditoriaController@index');
};
