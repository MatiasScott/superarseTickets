USE istsTicket;

-- Credencial inicial sugerida (cambiar tras primer ingreso): SuperAdmin@2026
-- Hash bcrypt generado con password_hash() de PHP.

INSERT INTO roles (nombre, descripcion, estado)
VALUES ('super_administrador', 'Acceso total al sistema', 'activo')
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    estado = VALUES(estado);

INSERT INTO usuarios (nombre, email, password, rol_id, estado)
SELECT
    'Super Administrador',
    'superadmin@ists.local',
    '$2y$10$G9.ShTH0n7pqEC6IFVRadOD4eojzUFxW6cjLAr0Tk9xocNb5FQVDy',
    r.id,
    'activo'
FROM roles r
WHERE r.nombre = 'super_administrador'
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    password = VALUES(password),
    rol_id = VALUES(rol_id),
    estado = VALUES(estado);
