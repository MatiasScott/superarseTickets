-- La tabla `convenios` se consume desde la BD externa `superar1_conectados`.
-- En esta BD local solo se crean tablas de soporte (notas/tareas/catálogos).

CREATE TABLE IF NOT EXISTS convenio_notas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    convenio_id INT NOT NULL,
    usuario_id INT NOT NULL,
    nota TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipo_tarea_convenios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    orden INT DEFAULT 0,
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resultados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    orden INT DEFAULT 0,
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convenio_tareas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    convenio_id INT NOT NULL,
    tipo_tarea_id INT,
    resultado_id INT,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_vencimiento DATE,
    hora_vencimiento TIME,
    propietario_id INT NOT NULL,
    estado ENUM('pendiente','en_proceso','completada','cancelada') DEFAULT 'pendiente',
    completado TINYINT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_tarea_id) REFERENCES tipo_tarea_convenios(id) ON DELETE SET NULL,
    FOREIGN KEY (resultado_id) REFERENCES resultados(id) ON DELETE SET NULL,
    FOREIGN KEY (propietario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_convenio_id (convenio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convenio_tarea_relacionados (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tarea_id BIGINT NOT NULL,
    usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES convenio_tareas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_relacionado (tarea_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convenio_tarea_colaboradores (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tarea_id BIGINT NOT NULL,
    usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES convenio_tareas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_colaborador (tarea_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tipo_tarea_convenios (nombre, orden, estado)
SELECT 'Llamada', 1, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tipo_tarea_convenios WHERE nombre = 'Llamada');

INSERT INTO tipo_tarea_convenios (nombre, orden, estado)
SELECT 'Correo', 2, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tipo_tarea_convenios WHERE nombre = 'Correo');

INSERT INTO tipo_tarea_convenios (nombre, orden, estado)
SELECT 'Reunión', 3, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tipo_tarea_convenios WHERE nombre = 'Reunión');

INSERT INTO tipo_tarea_convenios (nombre, orden, estado)
SELECT 'Firma', 4, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tipo_tarea_convenios WHERE nombre = 'Firma');

INSERT INTO tipo_tarea_convenios (nombre, orden, estado)
SELECT 'Seguimiento', 5, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tipo_tarea_convenios WHERE nombre = 'Seguimiento');

INSERT INTO tipo_tarea_convenios (nombre, orden, estado)
SELECT 'Visita', 6, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tipo_tarea_convenios WHERE nombre = 'Visita');

INSERT INTO resultados (nombre, orden, estado)
SELECT 'Exitoso', 1, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM resultados WHERE nombre = 'Exitoso');

INSERT INTO resultados (nombre, orden, estado)
SELECT 'Pendiente', 2, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM resultados WHERE nombre = 'Pendiente');

INSERT INTO resultados (nombre, orden, estado)
SELECT 'Sin respuesta', 3, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM resultados WHERE nombre = 'Sin respuesta');

INSERT INTO resultados (nombre, orden, estado)
SELECT 'Reagendado', 4, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM resultados WHERE nombre = 'Reagendado');

INSERT INTO resultados (nombre, orden, estado)
SELECT 'Cancelado', 5, 'activo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM resultados WHERE nombre = 'Cancelado');
