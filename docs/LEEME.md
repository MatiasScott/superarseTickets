# Axxis: parche inicial de integridad de adjuntos

Este paquete NO es una correccion completa del incidente ni una migracion automatica. Se basa en los ZIP recibidos el 10-11-2026.

## Cambios
- AttachmentQueueService: reserva transaccional con SELECT FOR UPDATE (InnoDB); evita que workers concurrentes reclamen las mismas filas.
- AttachmentProcessorService: valida que ticket_mensajes.id pertenezca al ticket antes de descargar/guardar un archivo.
- GraphMailService: solicita contentId al listar adjuntos y devuelve is_inline/content_id en la descarga.

## Aplicacion segura
1. Respaldar archivos originales, base de datos y almacenamiento.
2. Ejecutar docs/auditoria_adjuntos.sql con permisos de SOLO LECTURA; investigar los resultados.
3. En un ambiente de pruebas, reemplazar los tres archivos en app/services.
4. Ejecutar php -l sobre los tres archivos y probar correos con adjuntos normales, CID inline, dos cuentas y dos workers concurrentes.
5. Desplegar con ventana controlada y observar cola_adjuntos.

## Advertencias
- No ejecutar workers simultaneos durante el despliegue.
- No se corrigen automaticamente asociaciones historicas ni archivos ya almacenados.
- No se han ejecutado pruebas de integracion contra la base de datos ni Microsoft Graph reales.
- El bloqueo FOR UPDATE puede generar espera cuando hay muchos workers; evaluar SKIP LOCKED solo si la version MariaDB/MySQL lo soporta.
- n8n debe orquestar los endpoints existentes y no crear una segunda ruta independiente de descarga sin diseno adicional.
