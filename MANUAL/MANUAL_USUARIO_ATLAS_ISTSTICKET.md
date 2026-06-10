# MANUAL DE USUARIO
# Atlas - Plataforma ISTSTicket

## 1. INTRODUCCION

El manual de Atlas, plataforma del proyecto ISTSTicket, es una herramienta esencial diseñada para optimizar la organización y la gestión operativa de la información institucional. La plataforma centraliza procesos de atención, seguimiento, comunicación y control, facilitando el trabajo diario de las áreas administrativas y de soporte.

Este sistema ha sido desarrollado con el objetivo de mejorar la eficiencia operativa, facilitar el acceso a la información y asegurar la integridad y seguridad de los datos.

## 2. OBJETIVO

El propósito de este manual es proporcionar una guía detallada sobre el uso y las funcionalidades de Atlas en el proyecto ISTSTicket.

Este documento está dirigido al Coordinador de la Unidad de Soporte Técnico y TICs, encargado de administrar Atlas y de brindar soporte técnico para su correcto funcionamiento.

## 3. AMBITO DE CAPACIDAD

Atlas (ISTSTicket), correctamente implementado, ofrece los siguientes beneficios:

- Accesibilidad mejorada: permite a los usuarios acceder a la información de manera rápida y eficiente.
- Organización eficaz: facilita la clasificación y el manejo coherente de la información en módulos especializados.
- Trazabilidad: mantiene historial de cambios y auditoría para seguimiento y control.
- Automatización operativa: integra procesos de sincronización, respuestas y gestión por prioridades.

## 4. DESARROLLO

### 4.0 Alcance funcional de Atlas (ISTSTicket)

El sistema Atlas en ISTSTicket está orientado principalmente a la operación de:

1. Tickets: registro, seguimiento, respuestas, asignaciones y control de SLA.
2. CRM: gestión de interesados y estudiantes, pipeline, tareas y notas de seguimiento.
3. Convenios: seguimiento operativo mediante notas, tareas e historial.

Adicionalmente, integra módulos de Correo/WhatsApp, Campañas, Administración, Auditoría y configuración de integraciones.

### 4.1 Acceso al sistema (Autenticación)

#### Vista: Iniciar sesión
Para qué sirve:
- Permite ingresar al sistema con credenciales de usuario.

Paso a paso:
1. Ingrese a la pantalla de inicio de sesión.
2. Escriba su correo o nombre de usuario.
3. Escriba su contraseña.
4. Presione Iniciar sesión.
5. El sistema lo redirige al panel principal según sus permisos.

Observaciones:
- Si las credenciales son inválidas, el sistema mostrará un mensaje de error.
- El acceso está protegido por token de seguridad (CSRF).

#### Vista: Cambiar contraseña
Para qué sirve:
- Permite actualizar la contraseña del usuario autenticado.

Paso a paso:
1. Ingrese a Cambiar contraseña.
2. Escriba la contraseña actual.
3. Escriba la nueva contraseña.
4. Confirme la nueva contraseña.
5. Presione Guardar.

Validaciones:
- Todos los campos son obligatorios.
- La nueva contraseña debe cumplir reglas de seguridad.

---

### 4.2 Dashboard principal

#### Vista: Dashboard
Para qué sirve:
- Muestra indicadores globales de operación (tickets, SLA, asignaciones, conversaciones y ranking).

Cómo funciona:
1. Al ingresar, el sistema carga métricas en tiempo real desde base de datos.
2. Se visualizan KPI de tickets sin resolver, vencidos, en espera y no asignados.
3. Se muestran paneles de desempeño y tiempos promedio de atención.

Acciones del usuario:
- Consultar indicadores.
- Usar el dashboard como punto de control para decisiones operativas.

---

### 4.3 Módulo Tickets

#### Vista: Dashboard de Tickets
Para qué sirve:
- Presenta estadísticas específicas del módulo de tickets y detalle por grupos.

Paso a paso:
1. Ingrese a Tickets Dashboard.
2. Revise métricas globales.
3. Acceda al detalle por grupo para identificar cargas y tiempos.

#### Vista: Listado de Tickets
Para qué sirve:
- Consultar y filtrar tickets registrados.

Paso a paso:
1. Ingrese a Tickets.
2. Use filtros: estado, prioridad, tipo, grupo, asignado y búsqueda.
3. Ordene resultados por columna.
4. Navegue por paginación.
5. Abra el ticket requerido para ver detalle.

#### Vista: Crear Ticket
Para qué sirve:
- Registrar un nuevo ticket manualmente para un contacto.

Paso a paso:
1. Ingrese a Crear ticket.
2. Seleccione el contacto.
3. Ingrese asunto y descripción.
4. Seleccione cuenta de correo de salida (opcional).
5. Presione Guardar.

Cómo funciona:
- El sistema asigna estado/prioridad/grupo por defecto.
- Genera código de ticket automático.
- Intenta enviar notificación por correo al contacto.

#### Vista: Detalle de Ticket
Para qué sirve:
- Gestionar el ciclo de vida del ticket.

Acciones principales:
1. Ver trazabilidad del ticket y mensajes.
2. Responder ticket.
3. Agregar nota interna.
4. Actualizar propiedades (estado, prioridad, asignación u otros campos configurados).

Edición:
- La edición operativa se realiza actualizando propiedades y agregando respuestas/notas, no mediante formulario clásico de editar ticket completo.

---

### 4.4 Módulo Correo y WhatsApp

#### Vista: Chat Dashboard
Para qué sirve:
- Ver resumen de conversaciones, cuentas, no leídos y estado de conectores.

Paso a paso:
1. Ingrese a Chat Dashboard.
2. Seleccione cuenta/canal.
3. Revise indicadores de mensajes y sincronización.

#### Vista: Bandeja (WhatsApp)
Para qué sirve:
- Administrar conversaciones de WhatsApp registradas en el sistema.

Paso a paso:
1. Ingrese a Bandeja.
2. Seleccione conversación.
3. Revise historial de mensajes.
4. Use paginación para navegar conversaciones.

#### Vista: Detalle de Correo
Para qué sirve:
- Consultar un mensaje específico y responderlo.

Paso a paso:
1. Abra el mensaje desde bandeja.
2. Revise asunto, remitente y cuerpo.
3. Ingrese respuesta.
4. Envíe la respuesta.

#### Vista: Redactar Correo
Para qué sirve:
- Enviar un correo manual desde una cuenta configurada.

Paso a paso:
1. Ingrese a Redactar.
2. Seleccione cuenta de envío.
3. Ingrese destinatario, asunto y mensaje.
4. Presione Enviar.

Validaciones:
- El destinatario debe tener formato de correo válido.
- Asunto y mensaje son obligatorios.

#### Funciones operativas del módulo
1. Verificar conexión IMAP de cuenta.
2. Sincronizar correos para convertirlos en tickets.
3. Ejecutar sincronización automática (interna/cron) según configuración.
4. Procesar adjuntos automáticos.

---

### 4.5 Módulo Configuración de Integraciones

#### Vista: Configuración
Para qué sirve:
- Administrar integración de correo (SMTP/Graph), cuentas Office 365 y canal WhatsApp.

Subsección: Configuración de correo
Paso a paso:
1. Ingrese a Configuración.
2. Complete datos generales de correo.
3. Configure una o más cuentas de envío/recepción.
4. Defina estrategia de cuenta y cuenta por defecto.
5. Si usa Graph, complete tenant, client id y client secret.
6. Presione Guardar correo.

Subsección: Configuración de WhatsApp
Paso a paso:
1. Active o desactive canal WhatsApp.
2. Ingrese API key, webhook y números.
3. Defina estrategia de número.
4. Presione Guardar WhatsApp.

Cómo funciona:
- La configuración se guarda en variables de entorno y controla el comportamiento de sincronización y mensajería.

---

### 4.6 Módulo CRM

#### Vista: CRM Dashboard
Para qué sirve:
- Visualizar distribución de estudiantes/interesados por etapas del pipeline.

Paso a paso:
1. Ingrese a CRM Dashboard.
2. Revise conteos por etapa y por nivel.
3. Use la información para seguimiento comercial/académico.

#### Vista: Interesados
Para qué sirve:
- Consultar interesados y crear potenciales manuales.

Crear potencial (paso a paso):
1. Ingrese a CRM Interesados.
2. Complete nombres, apellidos e identificación.
3. Agregue celular y correo (si aplica).
4. Presione Crear.

Cómo funciona:
- El sistema crea o vincula contacto existente.
- Registra origen y etapa inicial del pipeline.

#### Vista: Estudiantes
Para qué sirve:
- Consultar estudiantes y gestionar su seguimiento CRM.

Acciones de edición disponibles:
1. Editar datos de contacto (correo, teléfono, celular y extras).
2. Cambiar estado del pipeline.
3. Gestionar tareas:
- Crear tarea.
- Editar participantes (relacionados y colaboradores).
- Actualizar resultado de tarea.
- Completar tarea.
4. Gestionar notas:
- Crear nota.
- Editar nota.
- Eliminar nota.

Flujo sugerido de trabajo:
1. Buscar estudiante/contacto.
2. Abrir detalle.
3. Actualizar estado del pipeline.
4. Crear tareas de seguimiento.
5. Registrar notas de avance.

---

### 4.7 Módulo Campañas

#### Vista: Listado de Campañas
Para qué sirve:
- Ver campañas de correo creadas y su estado (borrador, enviando, completada, etc.).

#### Vista: Crear Campaña
Para qué sirve:
- Diseñar una campaña y segmentar destinatarios.

Paso a paso:
1. Ingrese a Nueva Campaña.
2. Complete título, asunto, contenido y correo origen.
3. Defina tipo de destinatarios (todos, período o personalizado).
4. Configure filtros de segmentación.
5. Guarde la campaña.

#### Vista: Editar Campaña
Cómo funciona:
- Solo se pueden editar campañas en estado borrador.

Paso a paso:
1. Abra la campaña.
2. Modifique campos requeridos.
3. Guarde cambios.

Otras acciones:
1. Enviar campaña.
2. Eliminar campaña.
3. Previsualizar campaña.

---

### 4.8 Módulo Convenios

#### Vista: Listado de Convenios
Para qué sirve:
- Consultar convenios y sus estadísticas de notas/tareas.

#### Vista: Detalle de Convenio
Para qué sirve:
- Gestionar seguimiento operativo del convenio.

Cómo funciona:
1. Los datos base del convenio son de solo lectura.
2. Se permite crear notas y tareas asociadas.
3. Se mantiene historial de acciones.

Crear nota (paso a paso):
1. Abrir convenio.
2. Ir a sección Notas.
3. Escribir contenido.
4. Guardar.

Crear/editar tareas (paso a paso):
1. Abrir convenio.
2. Ir a sección Tareas.
3. Definir título, responsable, fechas y participantes.
4. Guardar tarea.
5. Actualizar estado, participantes y resultado según avance.

Importante:
- No se permite crear ni editar los datos maestros del convenio desde este módulo.

---

### 4.9 Módulo Contactos

#### Vista: Contactos
Para qué sirve:
- Consultar listado de contactos.

Cómo funciona:
1. Muestra datos generales (nombre, identificación, tipo, estado, fecha).
2. En esta vista actual no hay formulario de creación/edición directa.

---

### 4.10 Módulo Relaciones

#### Vista: Relaciones Usuario-Grupo
Para qué sirve:
- Consultar relaciones entre usuarios y grupos de atención.

Cómo funciona:
1. Muestra usuario, grupo, estado y fecha de relación.
2. En esta vista actual no hay creación/edición directa.

---

### 4.11 Módulo Académico

#### Vista: Control Académico
Para qué sirve:
- Consultar matrículas con datos de estudiante y carrera.

Cómo funciona:
1. Lista registros académicos recientes.
2. En esta vista actual no hay creación/edición directa.

---

### 4.12 Módulo Bot

#### Vista: Bot de Atención
Para qué sirve:
- Mostrar indicadores de operación del bot.

Indicadores principales:
1. Preguntas resueltas por bot.
2. Conversaciones derivadas a asesor.
3. Tickets generados.

---

### 4.13 Módulo Auditoría

#### Vista: Auditoría del sistema
Para qué sirve:
- Consultar bitácora de acciones realizadas en el sistema.

Paso a paso:
1. Ingrese a Auditoría.
2. Filtre por tabla, acción, usuario y rango de fechas.
3. Revise resultados.

Exportar reportes:
1. Exportar a Excel.
2. Exportar a PDF.

---

### 4.14 Módulo Administración

#### Vista: Panel de Administración
Para qué sirve:
- Centraliza la gestión administrativa del sistema.

Submódulos principales:

1. Gestión de usuarios (Admin)
- Crear usuario.
- Editar usuario.
- Desactivar usuario.

2. Gestión de roles
- Crear rol.
- Editar rol.
- Asignar permisos por acción.
- Desactivar rol.

3. Gestión de grupos
- Crear grupo de tickets.
- Editar grupo.

4. Catálogos operativos
- Estados de ticket/chat.
- Prioridades de ticket.
- Tipos de ticket.
- Estados del pipeline CRM.

5. SLA por prioridad
- Configurar horas de primera respuesta y resolución.

6. Herramientas de mantenimiento (solo super admin)
- Analizar tablas usadas/no usadas.
- Actualizar estructura de permisos.

Nota:
- El módulo Catalogos genérico está deshabilitado y redirige al panel de administración para usar catálogos activos.

---

### 4.15 Módulo Usuarios (Gestión de Cuentas)

#### Vista: Gestión de Cuentas
Para qué sirve:
- Gestionar cuentas de usuario desde módulo operativo.

Funciones:
1. Listar cuentas.
2. Crear cuenta.
3. Ver detalle.
4. Editar cuenta.
5. Eliminar cuenta (según reglas).

Crear cuenta (paso a paso):
1. Ingrese a Usuarios > Crear.
2. Complete nombre, correo, contraseña y confirmación.
3. Seleccione rol y estado.
4. Asigne grupos.
5. Guarde.

Editar cuenta (paso a paso):
1. Abra la cuenta en Editar.
2. Modifique nombre, correo, rol, estado o grupos.
3. Guarde cambios.

Validaciones:
- Correo único y formato válido.
- Contraseña segura al crear.

---

## 5. RECOMENDACIONES DE USO

1. Mantener actualizados roles y permisos según estructura organizacional.
2. Configurar correctamente cuentas de correo antes de operar tickets/campañas.
3. Revisar periódicamente auditoría para control de cambios.
4. Definir SLA por prioridad para medir cumplimiento de atención.
5. Registrar notas y tareas en CRM y Convenios para asegurar trazabilidad.

## 6. SOPORTE

Para incidencias técnicas de Atlas (ISTSTicket):
1. Validar primero configuración de integraciones y permisos.
2. Revisar bitácora de auditoría y registros operativos.
3. Escalar al Coordinador de Soporte Técnico y TICs con evidencia del caso.
