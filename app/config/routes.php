<?php

return static function (Router $router): void {
	$router->get('/', 'DashboardController@index');

	$router->get('/login', 'AuthController@showLogin');
	$router->post('/login', 'AuthController@login');
	$router->post('/logout', 'AuthController@logout');
	$router->get('/change-password', 'AuthController@showChangePassword');
	$router->post('/change-password', 'AuthController@changePassword');

	$router->get('/dashboard', 'DashboardController@index');
	$router->get('/tickets/dashboard', 'TicketController@dashboard');
	$router->get('/tickets/dashboard/data', 'TicketController@dashboardData');
	$router->get('/tickets/dashboard/grupos', 'TicketController@dashboardGroupDetails');
	$router->get('/configuracion', 'ConfiguracionController@index');
	$router->post('/configuracion/mail', 'ConfiguracionController@saveMail');
	$router->post('/configuracion/whatsapp', 'ConfiguracionController@saveWhatsApp');

	$router->get('/chat/dashboard', 'CorreoController@dashboard');
	$router->get('/correo', 'CorreoController@index');
	$router->get('/correo/compose', 'CorreoController@compose');
	$router->post('/correo/send', 'CorreoController@send');
	$router->get('/correo/verify', 'CorreoController@verify');
	$router->post('/correo/sync-tickets', 'CorreoController@syncTickets');
	$router->post('/correo/sync-tickets/auto', 'CorreoController@syncTicketsAuto');
	$router->get('/correo/{uid}', 'CorreoController@show');
	$router->post('/correo/{uid}/reply', 'CorreoController@reply');

	$router->get('/catalogos', 'CatalogoController@index');
	$router->get('/catalogos/{module}/create', 'CatalogoController@create');
	$router->get('/catalogos/{module}/{id}/edit', 'CatalogoController@edit');
	$router->post('/catalogos/{module}/{id}', 'CatalogoController@update');
	$router->post('/catalogos/{module}/{id}/delete', 'CatalogoController@delete');
	$router->get('/catalogos/{module}', 'CatalogoController@list');
	$router->post('/catalogos/{module}', 'CatalogoController@store');

	$router->get('/usuarios', 'UsuarioController@index');
	$router->get('/usuarios/create', 'UsuarioController@create');
	$router->post('/usuarios', 'UsuarioController@store');
	$router->get('/usuarios/{id}', 'UsuarioController@show');
	$router->get('/usuarios/{id}/edit', 'UsuarioController@edit');
	$router->post('/usuarios/{id}', 'UsuarioController@update');
	$router->post('/usuarios/{id}/delete', 'UsuarioController@delete');

	$router->get('/contactos', 'ContactoController@index');

	$router->get('/crm/dashboard', 'CRMController@dashboard');
	$router->get('/crm/interesados', 'CRMController@interesados');
	$router->get('/crm/estudiantes', 'CRMController@estudiantes');
	$router->get('/crm/getStudentDetail', 'CRMController@getStudentDetail');
	$router->get('/crm/getStudentContactDetail', 'CRMController@getStudentContactDetail');
	$router->post('/crm/updateStudentContact', 'CRMController@updateStudentContact');
	$router->post('/crm/updateStudentState', 'CRMController@updateStudentState');
	$router->get('/crm/getCRMPipelineHistory', 'CRMController@getCRMPipelineHistory');
	$router->get('/crm/getStudentTicketsByEmail', 'CRMController@getStudentTicketsByEmail');
	$router->get('/crm/getStudentNotes', 'CRMController@getStudentNotes');
	$router->post('/crm/addStudentNote', 'CRMController@addStudentNote');
	$router->post('/crm/updateStudentNote', 'CRMController@updateStudentNote');
	$router->post('/crm/deleteStudentNote', 'CRMController@deleteStudentNote');

	$router->get('/tickets', 'TicketController@index');
	$router->get('/tickets/create', 'TicketController@create');
	$router->post('/tickets', 'TicketController@store');
	$router->get('/tickets/{id}', 'TicketController@show');
	$router->get('/tickets/{id}/attachment', 'TicketController@attachment');
	$router->post('/tickets/{id}/reply', 'TicketController@replyTicket');
	$router->post('/tickets/{id}/note', 'TicketController@addNote');
	$router->post('/tickets/{id}/properties', 'TicketController@updateProperties');

	$router->get('/campanas', 'CampanaController@index');
	$router->get('/bot', 'BotController@index');
	$router->get('/relaciones', 'RelacionesController@index');
	$router->get('/academico', 'ControlAcademicoController@index');
	$router->get('/auditoria', 'AuditoriaController@index');
};
