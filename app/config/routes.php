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
	$router->get('/heartbeat', 'DashboardController@heartbeat');
	$router->get('/api/notifications', 'DashboardController@notifications');
	$router->get('/api/health', 'InternalApiController@health');
	$router->get('/api/internal/sync-mails', 'InternalApiController@syncMails');
	$router->post('/api/internal/sync-mails', 'InternalApiController@syncMails');
	$router->get('/api/internal/process-campaigns', 'InternalApiController@processCampaigns');
	$router->post('/api/internal/process-campaigns', 'InternalApiController@processCampaigns');

	$router->get('/api/internal/crm-sync', 'InternalApiController@crmSync');
	$router->post('/api/internal/crm-sync', 'InternalApiController@crmSync');

	$router->get('/api/internal/process-attachments', 'InternalApiController@processAttachments');
	$router->post('/api/internal/process-attachments', 'InternalApiController@processAttachments');

	$router->post('/api/internal/generate-preview', 'InternalApiController@generatePreview');
	$router->get('/api/internal/dashboard-metrics', 'InternalApiController@dashboardMetrics');
	$router->post('/api/internal/dashboard-metrics', 'InternalApiController@dashboardMetrics');

	$router->get('/api/internal/run-worker', 'InternalApiController@runWorker');
	$router->post('/api/internal/run-worker', 'InternalApiController@runWorker');
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
	$router->get('/correo/quick-replies', 'CorreoController@quickReplies');
	$router->post('/correo/quick-replies', 'CorreoController@createQuickReply');
	$router->get('/correo/verify', 'CorreoController@verify');
	$router->post('/correo/sync-tickets', 'CorreoController@syncTickets');
	$router->post('/correo/sync-tickets/auto', 'CorreoController@syncTicketsAuto');
	$router->post('/correo/process-attachments/auto', 'CorreoController@processAttachmentsAuto');
	$router->get('/cron/correo/sync', 'CorreoController@cronSync');
	$router->get('/cron/correo/process-attachments', 'CorreoController@cronProcessAttachments');
	$router->get('/cron/crm/institutional-sync', 'CRMController@cronInstitutionalSync');
	$router->get('/correo/{uid}', 'CorreoController@show');
	$router->post('/correo/{uid}/reply', 'CorreoController@reply');

	// Centro de Comunicaciones Inteligente (CCI)
	$router->get('/cci', 'CCIController@dashboard');
	$router->get('/cci/dashboard', 'CCIController@dashboard');
	$router->get('/cci/conversaciones', 'CCIController@conversaciones');
	$router->post('/cci/conversaciones/{id}/reply', 'CCIController@sendConversationReply');
	$router->post('/cci/conversaciones/{id}/notas', 'CCIController@storeConversationNote');
	$router->post('/cci/conversaciones/{id}/etiqueta', 'CCIController@guardarEtiqueta');
	$router->post('/cci/conversaciones/{id}/convertir-potencial', 'CCIController@convertirClientePotencial');
	$router->post('/cci/conversaciones/cerrar-lote', 'CCIController@cerrarLote');
	$router->post('/cci/conversaciones/enviar-masivo', 'CCIController@enviarMasivo');
	$router->get('/cci/conversaciones/{id}/mensajes-anteriores', 'CCIController@obtenerMensajesAnteriores');
	$router->post('/cci/etiquetas/guardar', 'CCIController@crearEtiqueta');
	$router->post('/cci/etiquetas/toggle-estado/{id}', 'CCIController@toggleEstadoEtiqueta');
	// Rutas de subetiquetas (Req 1)
	$router->get('/cci/subetiquetas/{etiquetaId}', 'CCIController@obtenerSubetiquetas');
	$router->post('/cci/subetiquetas/guardar', 'CCIController@crearSubetiqueta');
	$router->post('/cci/subetiquetas/toggle-estado/{id}', 'CCIController@toggleEstadoSubetiqueta');
	$router->post('/cci/sync/whatsapp', 'CCIController@syncWhatsApp');
	$router->get('/cci/freshchat-diagnostico', 'CCIController@freshchatDiagnostico');
	$router->get('/api/cci/whatsapp/webhook', 'CCIController@whatsAppWebhook');
	$router->post('/api/cci/whatsapp/webhook', 'CCIController@whatsAppWebhook');
	$router->post('/cci/sync/whatchimp', 'CCIController@syncWhatchimp');
	$router->get('/api/cci/whatchimp/webhook', 'CCIController@whatchimpWebhook');
	$router->post('/api/cci/whatchimp/webhook', 'CCIController@whatchimpWebhook');
	$router->get('/cci/contactos', 'CCIController@contactos');
	$router->get('/cci/clientes-potenciales', 'CCIController@potenciales');
	$router->post('/cci/clientes-potenciales/{id}', 'CCIController@updatePotencial');
	$router->get('/cci/campanas', 'CCIController@campanas');
	$router->post('/cci/campanas', 'CCIController@storeCampana');
	$router->post('/cci/campanas/{id}/destinatarios', 'CCIController@addCampanaDestinatarios');
	$router->post('/cci/campanas/{id}/send', 'CCIController@sendCampana');
	$router->post('/cci/campanas/process-scheduled', 'CCIController@processScheduledCampanas');
	$router->get('/cci/reportes', 'CCIController@reportes');
	$router->get('/cci/plantillas', 'CCIController@plantillas');
	$router->post('/cci/plantillas', 'CCIController@storePlantilla');
	$router->post('/cci/plantillas/{id}', 'CCIController@updatePlantilla');
	$router->post('/cci/plantillas/{id}/delete', 'CCIController@deletePlantilla');
	$router->get('/cci/respuestas-rapidas', 'CCIController@respuestasRapidas');
	$router->post('/cci/respuestas-rapidas', 'CCIController@storeRespuestaRapida');
	$router->post('/cci/respuestas-rapidas/{id}', 'CCIController@updateRespuestaRapida');
	$router->post('/cci/respuestas-rapidas/{id}/delete', 'CCIController@deleteRespuestaRapida');
	$router->get('/cci/asignaciones', 'CCIController@asignaciones');
	$router->post('/cci/conversaciones/{id}/assign', 'CCIController@assignConversation');
	$router->post('/cci/conversaciones/{id}/estado', 'CCIController@updateConversationEstado');
	$router->post('/cci/asesores/{id}/usuario', 'CCIController@mapCciAdvisorUser');
	$router->get('/cci/sla', 'CCIController@sla');
	$router->get('/cci/automatizaciones', 'CCIController@automatizaciones');
	$router->post('/cci/automatizaciones/test', 'CCIController@testAutomatizacion');
	$router->get('/cci/configuracion', 'CCIController@configuracion');
	$router->post('/cci/configuracion', 'CCIController@saveConfiguracion');
	$router->get('/cci/auditoria', 'CCIController@auditoria');

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
	$router->get('/crm/interesados/students-filter', 'CRMController@interesadosStudentsFilter');
	$router->post('/crm/prospectos', 'CRMController@createProspect');
	$router->get('/crm/estudiantes', 'CRMController@estudiantes');
	$router->get('/crm/getStudentDetail', 'CRMController@getStudentDetail');
	$router->get('/crm/getStudentContactDetail', 'CRMController@getStudentContactDetail');
	$router->get('/crm/getProspectDetail', 'CRMController@getProspectDetail');
	$router->get('/crm/checkProspectPhone', 'CRMController@checkProspectPhone');
	$router->post('/crm/updateStudentContact', 'CRMController@updateStudentContact');
	$router->post('/crm/updateProspect', 'CRMController@updateProspect');
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
	$router->get('/crm/note-attachment/{id}', 'CRMController@noteAttachment');
	$router->get('/crm/searchProspectsByNote', 'CRMController@searchProspectsByNote');
	$router->post('/crm/softDeleteProspect', 'CRMController@softDeleteProspect');
	$router->post('/crm/restoreProspect', 'CRMController@restoreProspect');
	$router->post('/crm/bulkUpdateProspects', 'CRMController@bulkUpdateProspects');
	$router->get('/crm/getModalidades', 'CRMController@getModalidades');
	$router->get('/crm/getPipelineStates', 'CRMController@getPipelineStates');
	$router->get('/crm/getProspectAsesores', 'CRMController@getProspectAsesores');
	$router->get('/crm/modalidades', 'CRMController@listModalidades');
	$router->post('/crm/modalidades', 'CRMController@saveModalidad');
	$router->post('/crm/modalidades/delete', 'CRMController@deleteModalidad');

	$router->get('/tickets', 'TicketController@index');
	$router->get('/tickets/create', 'TicketController@create');
	$router->post('/tickets', 'TicketController@store');
	$router->get('/tickets/{id}', 'TicketController@show');
	$router->get('/tickets/{id}/attachment', 'TicketController@attachment');
	$router->get('/tickets/{id}/reply-attachment/{attachmentId}', 'TicketController@replyAttachment');
	$router->post('/tickets/{id}/reply', 'TicketController@replyTicket');
	$router->post('/tickets/{id}/forward', 'TicketController@forwardTicket');
	$router->post('/tickets/{id}/note', 'TicketController@addNote');
	$router->post('/tickets/{id}/note/{noteId}', 'TicketController@updateNote');
	$router->post('/tickets/{id}/note/{noteId}/delete', 'TicketController@deleteNote');
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
	$router->post('/convenios/{id}/notas/{notaId}', 'ConvenioController@updateNota');
	$router->get('/convenios/{id}/notas/{notaId}/attachment/{attachmentId}', 'ConvenioController@noteAttachment');
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
