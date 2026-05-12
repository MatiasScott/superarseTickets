-- Permisos por modulo para roles
CREATE TABLE IF NOT EXISTS role_module_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_module (rol_id, module_key),
    INDEX idx_role_module_role (rol_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catalogo de modulos esperados por la aplicacion:
-- tickets, chat, crm, contactos, academico, campanas, bot, relaciones, auditoria, admin, configuracion
