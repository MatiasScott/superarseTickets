# 📚 GUÍA DE MÓDULOS Y PERMISOS

## 🎫 **TICKETS**
### Descripción
Sistema de seguimiento de problemas y solicitudes. Los usuarios reportan problemas que se asignan a grupos y se siguen hasta resolverse.

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver listado de tickets
- **Crear** → Abrir nuevos tickets
- **Editar** → Modificar datos del ticket
- **Eliminar** → Borrar tickets
- **Exportar** → Descargar en Excel/CSV

### Casos de uso
- Soporte técnico → Ver, Listar, Crear, Editar
- Soporte avanzado → Ver, Listar, Crear, Editar, Eliminar
- Administrador → Todos

---

## 💬 **CHAT**
### Descripción
Gestión de correos y conversaciones con clientes. Sincroniza correos de cuentas de mail y permite responder desde el sistema.

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver listado de conversaciones
- **Responder** → Enviar respuestas a correos

### Casos de uso
- Agente de correos → Ver, Listar, Responder
- Supervisor → Ver, Listar

---

## 📊 **CRM**
### Descripción
Gestión de relaciones con clientes y pipeline de ventas. Convierte contactos en leads, les asigna etapas de progresión y realiza seguimiento.

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver listado de contactos en CRM
- **Crear** → Agregar nuevos leads
- **Editar** → Cambiar datos o etapa del lead
- **Eliminar** → Borrar leads
- **Exportar** → Descargar base de datos

### Casos de uso
- Asesor comercial → Ver, Listar, Crear, Editar
- Gerente de ventas → Todos (incluyendo Eliminar, Exportar)

---

## 👤 **CONTACTOS**
### Descripción
Base de datos centralizada de personas. Aquí viven todos: interesados, estudiantes, docentes, administrativos. **Este es el punto de inicio** del sistema.

**Tipos de contacto:**
- Interesados (potenciales clientes)
- Estudiantes (matriculados)
- Docentes
- Administrativos
- Externos

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver directorio de contactos
- **Crear** → Agregar nuevos contactos
- **Editar** → Modificar datos personales
- **Eliminar** → Borrar contactos
- **Exportar** → Descargar base de datos

### Casos de uso
- Personal administrativo → Ver, Listar, Crear, Editar
- Dirección → Todos

---

## 📧 **CAMPAÑAS**
### Descripción
Envío masivo de correos personalizados. Permite crear y enviar comunicaciones a todos los contactos, a un período específico, o a una selección personalizada.

**Características:**
- Editor HTML avanzado (negritas, cursivas, imágenes, enlaces)
- Seleccionar correo origen
- Filtrar por período académico
- Gestión de cola de envíos (no spam)

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver historial de campañas
- **Crear** → Diseñar nueva campaña
- **Editar** → Modificar campaña en borrador
- **Enviar** → Lanzar campaña
- **Eliminar** → Borrar campaña

### Casos de uso
- Comunicaciones → Ver, Listar, Crear, Editar, Enviar
- Coordinador académico → Todos
- Administrador → Todos

---

## 🎓 **ACADÉMICO**
### Descripción
Gestión de estudiantes, matrículas y carreras. Vincula contactos con su vida académica.

**Incluye:**
- Estudiantes (datos académicos)
- Carreras (programas disponibles)
- Matrículas (histórico de estudios)
- Períodos académicos

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver estudiantes y matrículas
- **Editar** → Actualizar estado académico
- **Exportar** → Descargar reportes

### Casos de uso
- Coordinador académico → Ver, Listar, Editar, Exportar
- Docente → Ver, Listar (solo lectura)

---

## 🤖 **BOT / IA**
### Descripción
**Chatbot y automación de respuestas inteligentes.**

Integración con canales de mensajería para responder automáticamente preguntas frecuentes:
- WhatsApp
- Web (chat en sitio)
- Facebook Messenger

Puede:
- Responder preguntas sobre matrículas
- Proporcionar información de programas
- Clasificar automáticamente mensajes como tickets
- Transferir a agente humano cuando sea necesario

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver conversaciones del bot
- **Configurar** → Ajustes del bot (solo admin)

### Casos de uso
- Todos los usuarios → Ver, Listar (monitoreo)
- Administrador → Todos (incluyendo configuración)

---

## 🔗 **RELACIONES**
### Descripción
Seguimiento de interacciones y comunicaciones con clientes. Historial de quién habló con quién, cuándo, y sobre qué.

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver historial de interacciones

### Casos de uso
- Equipo comercial → Ver, Listar
- Gerencia → Ver, Listar

---

## 🔍 **AUDITORÍA**
### Descripción
Registro de **todos los cambios en el sistema**. Quién cambió qué, cuándo y cómo.

**Registra:**
- Creación de registros
- Modificaciones
- Eliminaciones
- Usuario responsable
- Fecha y hora exacta
- IP de origen

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver logs de cambios
- **Exportar** → Descargar auditoría en Excel

### Casos de uso
- Administrador → Todos
- Gerencia → Ver, Listar, Exportar
- **Solo lectura** (no se puede editar auditoría)

---

## ⚙️ **ADMINISTRACIÓN**
### Descripción
Panel central para gestionar el sistema.

**Incluye:**
- Usuarios (crear, editar, eliminar empleados)
- Roles (definir qué puede hacer cada perfil)
- Grupos (organizar equipos de trabajo)
- Catálogos (estados, prioridades, tipos)
- Permisos (qué módulos ve cada rol)

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Listar** → Ver usuarios, roles, grupos
- **Crear** → Agregar nuevos elementos
- **Editar** → Modificar configuración
- **Eliminar** → Borrar elementos
- **Configurar** → Acceso especial

### Casos de uso
- **Solo Administrador y Super Admin**
- Muy restrictivo por seguridad

---

## ⚡ **CONFIGURACIÓN**
### Descripción
Ajustes generales del sistema: integraciones, correo, WhatsApp y otras opciones.

**Permite configurar:**
- Cuentas de correo (SMTP, OAuth)
- Integración WhatsApp Business
- Sincronización automática
- Parámetros del bot
- Horarios de respuesta

### Acciones disponibles
- **Ver** → Acceso al módulo
- **Editar** → Modificar configuración

### Casos de uso
- **Solo Administrador y Super Admin**
- Muy restrictivo por seguridad

---

## 📋 MATRIZ DE PERMISOS RECOMENDADOS

### 🔵 SOPORTE TÉCNICO
- ✅ Tickets: Ver, Listar, Crear, Editar
- ✅ Chat: Ver, Listar, Responder
- ✅ Contactos: Ver, Listar
- ❌ CRM, Académico, Campañas, Admin, Config

### 🟢 COORDINADOR ACADÉMICO
- ✅ Tickets: Ver, Listar
- ✅ Contactos: Ver, Listar, Crear, Editar
- ✅ Académico: Ver, Listar, Editar, Exportar
- ✅ Campañas: Ver, Listar, Crear, Editar, Enviar
- ✅ CRM: Ver, Listar, Editar
- ✅ Auditoría: Ver, Listar
- ❌ Admin, Config (solo Super Admin)

### 🟣 GERENTE / DIRECTOR
- ✅ Todo excepto Admin y Config (solo lectura)
- ✅ Auditoría: Ver, Listar, Exportar

### 🔴 SUPER ADMIN
- ✅ **Todo acceso completo** a todos los módulos y acciones

---

## 🎯 FLUJO TÍPICO EN EL SISTEMA

```
1. CONTACTO → Entra en base de datos (módulo Contactos)
                    ↓
2. INTERÉS → Se clasifica en CRM (seguimiento)
                    ↓
3. DECISIÓN → Matrícula como Estudiante (módulo Académico)
                    ↓
4. APOYO → Si hay dudas → Ticket (módulo Tickets)
                    ↓
5. COMUNICACIÓN → Campañas masivas (recordatorios, anuncios)
                    ↓
6. AUDITORÍA → Cada cambio queda registrado
```

---

## ✅ PRÓXIMOS PASOS

1. **Crear Roles** en Admin → Roles
2. **Asignar Acciones** a cada rol
3. **Crear Usuarios** en Admin → Usuarios
4. **Asignar Rol** a cada usuario
5. **Verificar** que cada usuario ve solo sus módulos permitidos
