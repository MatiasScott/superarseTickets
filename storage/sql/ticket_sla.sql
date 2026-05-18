-- Tabla para SLA de tickets por prioridad
CREATE TABLE IF NOT EXISTS ticket_sla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prioridad VARCHAR(20) NOT NULL UNIQUE, -- 'alta', 'media', 'baja'
    primera_respuesta_horas INT NOT NULL,
    resolucion_horas INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar valores iniciales
INSERT INTO ticket_sla (prioridad, primera_respuesta_horas, resolucion_horas) VALUES
('alta', 8, 24),
('media', 24, 48),
('baja', 48, 96)
ON DUPLICATE KEY UPDATE
    primera_respuesta_horas=VALUES(primera_respuesta_horas),
    resolucion_horas=VALUES(resolucion_horas);
