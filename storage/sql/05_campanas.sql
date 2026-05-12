USE istsTicket;

-- Tabla de campañas
CREATE TABLE IF NOT EXISTS campanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    asunto VARCHAR(255) NOT NULL,
    contenido LONGTEXT NOT NULL,
    correo_origen VARCHAR(255) NOT NULL,
    tipo_destinatarios ENUM('todos', 'periodo', 'personalizado') NOT NULL DEFAULT 'todos',
    periodo_id INT NULL,
    estado ENUM('borrador', 'programada', 'enviando', 'completada', 'cancelada') NOT NULL DEFAULT 'borrador',
    fecha_envio DATETIME NULL,
    total_destinatarios INT DEFAULT 0,
    total_enviados INT DEFAULT 0,
    total_fallidos INT DEFAULT 0,
    usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_estado (estado),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha_creacion (created_at),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de destinatarios de campaña
CREATE TABLE IF NOT EXISTS campana_destinatarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campana_id INT NOT NULL,
    contacto_id INT NOT NULL,
    correo_destino VARCHAR(255) NOT NULL,
    nombre_destino VARCHAR(255),
    estado ENUM('pendiente', 'enviado', 'fallido', 'rebotado') NOT NULL DEFAULT 'pendiente',
    fecha_envio DATETIME NULL,
    error_mensaje TEXT NULL,
    intentos INT DEFAULT 0,
    INDEX idx_campana (campana_id),
    INDEX idx_contacto (contacto_id),
    INDEX idx_estado (estado),
    FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE,
    FOREIGN KEY (contacto_id) REFERENCES contactos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de cola de envíos (para evitar spam)
CREATE TABLE IF NOT EXISTS cola_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campana_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    estado ENUM('pendiente', 'procesando', 'completado', 'error') NOT NULL DEFAULT 'pendiente',
    intento INT DEFAULT 0,
    proximo_intento DATETIME NULL,
    error_log TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campana_estado (campana_id, estado),
    INDEX idx_proximo_intento (proximo_intento),
    FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES campana_destinatarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
