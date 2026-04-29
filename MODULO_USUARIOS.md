# Módulo de Gestión de Usuarios

## Descripción
El módulo de Gestión de Usuarios permite crear, actualizar, visualizar y eliminar cuentas de usuario en el sistema ISTS Ticket.

## Funcionalidades

### 1. Listar Usuarios
- **Ruta**: GET `/usuarios`
- **Vista**: `app/views/usuarios/index.php`
- Muestra tabla con todos los usuarios registrados
- Filtrado por rol, estado, etc.
- Acciones rápidas: Ver, Editar, Eliminar

### 2. Crear Usuario
- **Ruta**: GET `/usuarios/create` (formulario)
- **Ruta**: POST `/usuarios` (guardar)
- **Vista**: `app/views/usuarios/create.php`
- Validaciones:
  - Nombre requerido
  - Email único y válido
  - Contraseña fuerte (8+ caracteres, mayús, minús, números, especiales)
  - Teléfono opcional (validación internacional)
  - Rol asignable

### 3. Editar Usuario
- **Ruta**: GET `/usuarios/{id}/edit` (formulario)
- **Ruta**: POST `/usuarios/{id}` (actualizar)
- **Vista**: `app/views/usuarios/edit.php`
- Puede modificar: nombre, email, rol, teléfono, estado
- NO permite cambiar contraseña (ver endpoint separado)

### 4. Ver Detalles
- **Ruta**: GET `/usuarios/{id}`
- **Vista**: `app/views/usuarios/show.php`
- Muestra información completa del usuario
- Fechas de creación y última actualización
- Avatar generado con inicial del nombre

### 5. Eliminar Usuario
- **Ruta**: POST `/usuarios/{id}/delete`
- Requiere confirmación
- No permite eliminar usuario con ID=1 (super admin)

## Controlador

**Archivo**: `app/controllers/UsuarioController.php`

Métodos:
- `index()` - Listar usuarios
- `create()` - Mostrar formulario de creación
- `store()` - Procesar creación
- `show($id)` - Ver detalles
- `edit($id)` - Mostrar formulario de edición
- `update($id)` - Procesar actualización
- `delete($id)` - Eliminar usuario

## Validaciones

- Contraseñas con `validate_password_strength()`
- Emails únicos por usuario
- Teléfono en formato internacional (+34 600 000 000)
- CSRF en todos los formularios
- Autenticación requerida en todos los endpoints

## Seguridad

- ✓ Prepared statements en todas las queries
- ✓ CSRF tokens en formularios
- ✓ Password hashing con bcrypt (cost: 10)
- ✓ Validación de propriedad de recursos
- ✓ Protección contra eliminación de super admin

## Integración con Servicios

### Envío de Correos (MailService)
Cuando se crea un usuario, se puede enviar correo de bienvenida:
```php
$mail = new MailService();
$mail->sendTemplate($usuario['email'], 'welcome', [
    'nombre' => $usuario['nombre'],
    'email' => $usuario['email']
], 'Bienvenido a ISTS');
```

### Mensajes SMS (PhoneService)
Para enviar SMS a usuarios:
```php
$phone = new PhoneService();
$phone->sendSMS('+34600000000', 'Mensaje de prueba');
```

### Automatizaciones (BotService)
Se pueden activar automatizaciones al crear usuarios:
```php
$bot = new BotService();
$bot->handleAutomation('welcome_email', [
    'email' => $usuario['email'],
    'nombre' => $usuario['nombre']
]);
```

---

# Configuración de Correos (Mail)

## Archivo de Configuración
- **Ruta**: `app/config/mail.php`
- **Variables de Entorno**: `.env`

## Controladores Soportados

### 1. SMTP
Usar servidor SMTP externo:
```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=tu_usuario
MAIL_PASSWORD=tu_contraseña
MAIL_ENCRYPTION=tls
MAIL_FROM_EMAIL=noreply@ists.local
```

### 2. Sendmail
Usar sendmail local:
```env
MAIL_DRIVER=sendmail
SENDMAIL_PATH=/usr/sbin/sendmail -bs
```

### 3. Mailgun (futuro)
Usar servicio de Mailgun

### 4. AWS SES (futuro)
Usar servicio de AWS

## Servicio MailService

**Archivo**: `app/services/MailService.php`

### Métodos
```php
// Enviar correo simple
$mail = new MailService();
$mail->send($to, $subject, $body, $cc = [], $bcc = []);

// Enviar desde plantilla
$mail->sendTemplate($to, 'template_name', $data, $subject);

// Validar email
MailService::isValidEmail('usuario@ejemplo.com');
```

### Plantillas
- **Ruta**: `app/views/emails/`
- Plantillas disponibles:
  - `welcome.php` - Bienvenida de usuario
  - `password_reset.php` - Reinicio de contraseña
  - `ticket_created.php` - Ticket creado
  - `ticket_updated.php` - Ticket actualizado

---

# Configuración de Teléfono (Phone)

## Archivo de Configuración
- **Ruta**: `app/config/phone.php`
- **Variables de Entorno**: `.env`

## Proveedores SMS Soportados

### 1. Twilio
```env
SMS_PROVIDER=twilio
TWILIO_ENABLED=true
TWILIO_ACCOUNT_SID=tu_account_sid
TWILIO_AUTH_TOKEN=tu_auth_token
TWILIO_PHONE_NUMBER=+1234567890
```

### 2. Nexmo/Vonage
```env
NEXMO_ENABLED=true
NEXMO_API_KEY=tu_api_key
NEXMO_API_SECRET=tu_api_secret
NEXMO_FROM_NUMBER=ISTS
```

### 3. AWS SNS
```env
SNS_ENABLED=true
AWS_ACCESS_KEY_ID=tu_key
AWS_SECRET_ACCESS_KEY=tu_secret
AWS_REGION=us-east-1
```

## Servicio PhoneService

**Archivo**: `app/services/PhoneService.php`

### Métodos
```php
$phone = new PhoneService();

// Enviar SMS
$phone->sendSMS('+34600000000', 'Mensaje');

// Validar número
$phone->isValidPhone('600000000', 'ES');

// Normalizar a E.164
$phone->normalizePhone('600000000', 'ES'); // +34600000000

// Formatear para mostrar
$phone->formatPhone('+34600000000', 'international'); // +34 (600) 000-000
```

### Formatos Soportados
- E.164: `+34600000000`
- Internacional: `+34 (600) 000-000`

### Países Soportados
- ES (España) - +34
- US (USA) - +1
- MX (México) - +52
- AR (Argentina) - +54
- CO (Colombia) - +57
- CL (Chile) - +56
- PE (Perú) - +51

---

# Configuración de Bots y Automatización

## Archivo de Configuración
- **Ruta**: `app/config/bots.php`
- **Variables de Entorno**: `.env`

## Bots Disponibles

### 1. WhatsApp Bot
```env
BOT_WHATSAPP_ENABLED=true
BOT_WHATSAPP_PHONE=+34600000000
BOT_WHATSAPP_API_KEY=tu_api_key
BOT_WHATSAPP_WEBHOOK=https://example.com/webhooks/whatsapp
```

### 2. Telegram Bot
```env
BOT_TELEGRAM_ENABLED=true
BOT_TELEGRAM_TOKEN=tu_bot_token
BOT_TELEGRAM_WEBHOOK=https://example.com/webhooks/telegram
```

### 3. Facebook Messenger
```env
BOT_MESSENGER_ENABLED=true
BOT_MESSENGER_TOKEN=tu_page_token
BOT_MESSENGER_VERIFY_TOKEN=tu_verify_token
BOT_MESSENGER_WEBHOOK=https://example.com/webhooks/messenger
```

### 4. Email Bot
```env
BOT_EMAIL_ENABLED=true
```

### 5. SMS Bot
```env
BOT_SMS_ENABLED=true
```

## Servicio BotService

**Archivo**: `app/services/BotService.php`

### Métodos
```php
$bot = new BotService();

// Enviar mensaje de bienvenida
$bot->sendWelcomeMessage('email', 'usuario@ejemplo.com', [
    'nombre' => 'Juan'
]);

// Procesar automatización por trigger
$bot->handleAutomation('welcome_email', $data);

// Verificar disponibilidad según horario
if ($bot->isAvailable()) {
    // Responder al usuario
}

// Obtener plantilla
$template = $bot->getTemplate('greeting');
```

## Automatizaciones Configurables

### welcome_email
Se ejecuta al crear un usuario
```php
$bot->handleAutomation('welcome_email', [
    'email' => $usuario['email'],
    'nombre' => $usuario['nombre']
]);
```

### password_reset
Se ejecuta al solicitar reset de contraseña
```php
$bot->handleAutomation('password_reset', [
    'email' => $usuario['email'],
    'reset_token' => $token
]);
```

### ticket_created
Se ejecuta cuando se crea un ticket
```php
$bot->handleAutomation('ticket_created', [
    'ticket_id' => $ticket['id'],
    'customer_email' => $customer['email'],
    'assignee_email' => $assignee['email'],
    'notify_customer' => true,
    'notify_assignee' => true
]);
```

### ticket_updated
Se ejecuta cuando se actualiza un ticket
```php
$bot->handleAutomation('ticket_updated', [
    'ticket_id' => $ticket['id'],
    'watchers' => $watchers,
    'notify_watchers' => true
]);
```

## Horarios de Disponibilidad

```php
// Config
'availability' => [
    'enabled' => true,
    'timezone' => 'Europe/Madrid',
    'business_hours' => [
        'monday' => ['start' => '09:00', 'end' => '18:00'],
        // ...
    ]
]

// Uso
if ($bot->isAvailable()) {
    // Responder inmediatamente
} else {
    $msg = $bot->getOutOfHoursMessage();
}
```

## Límites y Throttling

```php
'rate_limiting' => [
    'max_messages_per_minute' => 60,
    'max_messages_per_hour' => 1000,
    'block_duration_minutes' => 15,
]
```

## Logging

```php
'logging' => [
    'enabled' => true,
    'log_level' => 'INFO',
    'store_conversations' => true,
    'retention_days' => 90,
]
```

---

## Ejemplo de Uso Completo

```php
// Crear nuevo usuario
$usuario = new Usuario();
$usuario_id = $usuario->create([
    'nombre' => 'Juan Pérez',
    'email' => 'juan@ejemplo.com',
    'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
    'rol_id' => 2,
    'telefono' => '+34600000000'
]);

// Enviar correo de bienvenida
$mail = new MailService();
$mail->sendTemplate($usuario['email'], 'welcome', [
    'nombre' => $usuario['nombre']
], 'Bienvenido a ISTS');

// Enviar SMS
$phone = new PhoneService();
$phone->sendSMS('+34600000000', 'Tu cuenta ha sido creada. Bienvenido a ISTS');

// Ejecutar automatización
$bot = new BotService();
$bot->handleAutomation('welcome_email', $usuario);
```
