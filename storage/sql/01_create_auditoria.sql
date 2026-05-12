-- Script para crear tabla auditoria
-- Ejecutar en phpMyAdmin o MySQL client

USE istsTicket;

CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(128) NOT NULL,
    registro_id INT NULL,
    accion ENUM('CREATE', 'READ', 'UPDATE', 'DELETE') NOT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    usuario_id INT NULL,
    ip VARCHAR(64) NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabla_fecha (tabla, fecha),
    INDEX idx_usuario_fecha (usuario_id, fecha),
    INDEX idx_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
