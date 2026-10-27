# Arquitectura de servicios Atlas

## Ubicacion de Docker Compose

El archivo docker-compose para servicios externos se incluye en:

- ops/docker/docker-compose.yml

En produccion debe vivir fuera del codigo PHP, por ejemplo:

- C:\\DockerServicios
- /opt/docker

## Servicios desplegados

- Redis: 6379
- n8n: 5678
- Uptime Kuma: 3001
- Portainer: 9000, 9443

## API interna Atlas

Endpoints protegidos por header:

- X-Internal-Token: {INTERNAL_API_TOKEN}

Endpoints:

- POST /api/internal/sync-mails
- POST /api/internal/process-campaigns
- POST /api/internal/crm-sync
- POST /api/internal/process-attachments
- POST /api/internal/generate-preview
- POST /api/internal/dashboard-metrics
- POST /api/internal/run-worker

Health:

- GET /api/health

## Variables requeridas en .env de Atlas

- INTERNAL_API_TOKEN
- APP_WEB_AUTOSYNC_ENABLED=false
- REDIS_HOST
- REDIS_PORT
- REDIS_PASSWORD
- REDIS_DB
- REDIS_TIMEOUT
- REDIS_PREFIX
- QUEUE_WORKER_MAX_JOBS

## Buzon de Comunicacion y Marketing

La cuenta `solucionesnexo@superarse.edu.ec` (`MAIL_ACCOUNT_16`) se asigna
directamente al grupo activo **Comunicacion y Marketing**, independientemente
del asunto. CorreoController tambien reconoce solicitudes de marketing,
diseno, artes, fotografia, videos y comunicacion institucional por contenido
cuando no existe un mapeo directo del buzon a otro grupo.

MailTicketPolicy fija el inicio de importacion al **27 de octubre de 2026,
00:00 America/Guayaquil** (`2026-10-27T05:00:00Z`). No es una fecha movil:
los correos de ese dia y posteriores siguen siendo elegibles en futuras
sincronizaciones. Para este buzon se omiten el bootstrap y la reclasificacion
historicos; Graph, IMAP y la recuperacion por fechas respetan el limite.
Las demas cuentas conservan su comportamiento. No se eliminan tickets previos.

## Colas Redis

- mail_queue
- attachment_queue
- campaign_queue
- crm_sync_queue
- preview_queue
- metrics_queue

## Jobs en base de datos

Tablas nuevas:

- jobs
- jobs_detalles

Cada invocacion de /api/internal/* crea un job y lo encola en Redis.
El worker procesa la cola y actualiza el estado a completado o error.

## Worker recomendado

Puedes ejecutarlo con cron cada minuto:

php public/cron/worker.php token={INTERNAL_API_TOKEN} max=30

Si prefieres HTTP desde n8n:

POST /api/internal/run-worker
Header X-Internal-Token: {INTERNAL_API_TOKEN}
Body: max=30

## Monitoreo recomendado en Uptime Kuma

- Aplicacion: https://dominio.com
- API health: https://dominio.com/api/health
- n8n: http://localhost:5678
- Redis TCP: 6379
- MySQL TCP: 3306

## Flujo sugerido n8n

1. Trigger cada 5 minutos
2. Llamar POST /api/internal/sync-mails
3. Llamar POST /api/internal/process-attachments
4. Llamar POST /api/internal/crm-sync
5. Llamar POST /api/internal/process-campaigns
6. Llamar POST /api/internal/dashboard-metrics
7. Llamar POST /api/internal/run-worker (max configurable)

Con este flujo, Atlas no ejecuta procesos pesados dentro de la carga de paginas.
