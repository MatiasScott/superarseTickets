<?php

return static function (Router $router): void {
	$router->get('/', 'DashboardController@index');

	// Admin Panel
	$router->get('/admin/dashboard', 'AdminController@dashboard');

	// Admin - Usuarios
	$router->get('/admin/usuarios', 'AdminController@usuariosIndex');
	$router->get('/admin/usuarios/create', 'AdminController@usuariosCreate');
	$router->post('/admin/usuarios', 'AdminController@usuariosStore');
	$router->get('/admin/usuarios/{id}/edit', 'AdminController@usuariosEdit');
	$router->post('/admin/usuarios/{id}', 'AdminController@usuariosUpdate');
	$router->post('/admin/usuarios/{id}/delete', 'AdminController@usuariosDelete');

	// Admin - Roles
	$router->get('/admin/roles', 'AdminController@rolesIndex');
	$router->get('/admin/roles/create', 'AdminController@rolesCreate');
	$router->post('/admin/roles', 'AdminController@rolesStore');
	$router->get('/admin/roles/{id}/edit', 'AdminController@rolesEdit');
	$router->post('/admin/roles/{id}', 'AdminController@rolesUpdate');
	$router->post('/admin/roles/{id}/delete', 'AdminController@rolesDelete');

	// Admin - Grupos
	$router->get('/admin/grupos', 'AdminController@gruposIndex');
	$router->get('/admin/grupos/create', 'AdminController@gruposCreate');
	$router->post('/admin/grupos', 'AdminController@gruposStore');
	$router->get('/admin/grupos/{id}/edit', 'AdminController@gruposEdit');
	$router->post('/admin/grupos/{id}', 'AdminController@gruposUpdate');

	// Admin - Catálogos
	$router->get('/admin/catalogo/{type}', 'AdminController@catalogIndex');
	$router->get('/admin/catalogo/{type}/create', 'AdminController@catalogCreate');
	$router->post('/admin/catalogo/{type}', 'AdminController@catalogStore');
	$router->get('/admin/catalogo/{type}/{id}/edit', 'AdminController@catalogEdit');
	$router->post('/admin/catalogo/{type}/{id}', 'AdminController@catalogUpdate');
	$router->post('/admin/catalogo/{type}/{id}/delete', 'AdminController@catalogDelete');

	// Admin - SLA Tickets
	$router->get('/admin/sla', 'AdminController@slaIndex');
	$router->post('/admin/sla/update', 'AdminController@slaUpdate');

	$router->get('/configuracion/general', 'ConfiguracionController@general');

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
	$router->get('/crm/getStudentTasks', 'CRMController@getStudentTasks');
	$router->post('/crm/addStudentTask', 'CRMController@addStudentTask');
	$router->post('/crm/updateStudentTaskParticipants', 'CRMController@updateStudentTaskParticipants');
	$router->post('/crm/updateStudentTaskResult', 'CRMController@updateStudentTaskResult');
	$router->post('/crm/completeStudentTask', 'CRMController@completeStudentTask');
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
	$router->get('/tickets/{id}/reply-attachment/{attachmentId}', 'TicketController@replyAttachment');
	$router->post('/tickets/{id}/reply', 'TicketController@replyTicket');
	$router->post('/tickets/{id}/note', 'TicketController@addNote');
	$router->post('/tickets/{id}/properties', 'TicketController@updateProperties');

	// Campañas de correo
	$router->get('/campanas', 'CampanaController@index');
	$router->get('/campanas/create', 'CampanaController@create');
	$router->post('/campanas', 'CampanaController@store');
	$router->get('/campanas/edit/{id}', 'CampanaController@edit');
	$router->post('/campanas/update/{id}', 'CampanaController@update');
	$router->post('/campanas/send/{id}', 'CampanaController@send');
	$router->post('/campanas/delete/{id}', 'CampanaController@delete');
	$router->get('/campanas/preview/{id}', 'CampanaController@preview');

	// Convenios
	$router->get('/convenios', 'ConvenioController@index');
	$router->get('/convenios/create', 'ConvenioController@create');
	$router->post('/convenios', 'ConvenioController@store');
	$router->get('/convenios/{id}', 'ConvenioController@show');
	$router->post('/convenios/{id}/datos', 'ConvenioController@updateDatos');
	$router->post('/convenios/{id}/notas', 'ConvenioController@storeNota');
	$router->post('/convenios/{id}/tareas', 'ConvenioController@storeTarea');
	$router->post('/convenios/{id}/tareas/{tareaId}/estado', 'ConvenioController@updateTareaEstado');
	$router->post('/convenios/{id}/tareas/{tareaId}/participantes', 'ConvenioController@updateTareaParticipantes');
	$router->post('/convenios/{id}/tareas/{tareaId}/resultado', 'ConvenioController@updateTareaResultado');

	$router->get('/bot', 'BotController@index');
	$router->get('/relaciones', 'RelacionesController@index');
	$router->get('/academico', 'ControlAcademicoController@index');
	$router->get('/auditoria/export/excel', 'AuditoriaController@exportExcel');
	$router->get('/auditoria/export/pdf', 'AuditoriaController@exportPdf');
	$router->get('/auditoria', 'AuditoriaController@index');

	// Mantenimiento y herramientas (solo super admin)
	$router->get('/admin/analyze-tables', 'AdminController@analyzeTables');
	$router->get('/admin/fix-permissions', 'AdminController@fixPermissions');
};
