-- SOLO LECTURA. Verificar primero el esquema real de produccion.
-- Asociaciones cuyo ticket difiere del ticket del mensaje.
SELECT a.id AS adjunto_id, a.ticket_id AS ticket_adjunto, a.ticket_mensaje_id,
       m.ticket_id AS ticket_mensaje, a.filename_original
FROM ticket_mensaje_adjuntos a
LEFT JOIN ticket_mensajes m ON m.id = a.ticket_mensaje_id
WHERE m.id IS NULL OR a.ticket_id <> m.ticket_id;

-- Inconsistencias de la cola antes de descargar archivos.
SELECT c.id AS cola_id, c.ticket_id AS ticket_cola, c.ticket_mensaje_id,
       m.ticket_id AS ticket_mensaje, c.account_alias, c.estado
FROM cola_adjuntos c
LEFT JOIN ticket_mensajes m ON m.id = c.ticket_mensaje_id
WHERE m.id IS NULL OR c.ticket_id <> m.ticket_id;

-- Resumen de estados; identificar errores y registros atascados.
SELECT estado, COUNT(*) AS total, MIN(created_at) AS primero,
       MAX(created_at) AS ultimo
FROM cola_adjuntos GROUP BY estado ORDER BY total DESC;
