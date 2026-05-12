-- Nueva tabla de permisos por acciones
USE istsTicket;

-- Tabla de acciones por módulo
DROP TABLE IF EXISTS role_action_permissions;

CREATE TABLE role_action_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    accion ENUM('ver', 'listar', 'crear', 'editar', 'eliminar', 'exportar', 'enviar', 'responder', 'configurar') NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_action (rol_id, module_key, accion),
    INDEX idx_role (rol_id),
    INDEX idx_module (module_key),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos base: Super Admin tiene TODOS los permisos
INSERT INTO role_action_permissions (rol_id, module_key, accion, allowed)
SELECT r.id, 'tickets', 'ver', 1 FROM roles r WHERE LOWER(r.nombre) LIKE '%super%' OR LOWER(r.nombre) LIKE '%admin%'
ON DUPLICATE KEY UPDATE allowed=1;

-- Módulos disponibles y sus acciones
-- tickets: Ver, Listar, Crear, Editar, Eliminar, Exportar
-- crm: Ver, Listar, Crear, Editar, Eliminar, Exportar
-- chat: Ver, Listar
-- contactos: Ver, Listar, Crear, Editar, Eliminar
-- academico: Ver, Listar, Editar
-- campanas: Ver, Listar, Crear, Editar, Eliminar, Exportar
-- bot: Ver, Listar (solo lectura del bot)
-- relaciones: Ver, Listar
-- auditoria: Ver, Listar, Exportar
-- admin: Todo
-- configuracion: Todo
