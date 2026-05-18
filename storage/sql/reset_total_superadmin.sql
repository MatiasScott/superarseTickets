-- Reset total de datos + superadministrador + primer inicio con cambio de contraseña
-- Base objetivo: istsTicket (ajusta con USE si corresponde)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

START TRANSACTION;

-- 1) Vaciar todas las tablas de la BD actual (mantiene estructura)
DELIMITER $$
DROP PROCEDURE IF EXISTS truncate_all_tables$$
CREATE PROCEDURE truncate_all_tables()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_table VARCHAR(255);

  DECLARE cur CURSOR FOR
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_type = 'BASE TABLE';

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  SET FOREIGN_KEY_CHECKS = 0;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_table;
    IF done = 1 THEN
      LEAVE read_loop;
    END IF;

    SET @sql = CONCAT('TRUNCATE TABLE `', v_table, '`');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;

  SET FOREIGN_KEY_CHECKS = 1;
END$$
DELIMITER ;

CALL truncate_all_tables();
DROP PROCEDURE IF EXISTS truncate_all_tables;

-- 2) Asegurar columna de primer inicio (si no existe)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'usuarios'
    AND column_name = 'must_change_password'
);

SET @alter_sql := IF(
  @col_exists = 0,
  'ALTER TABLE usuarios ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password',
  'SELECT 1'
);
PREPARE stmt2 FROM @alter_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 3) Crear rol superadministrador
INSERT INTO roles (nombre, descripcion, estado, created_at, updated_at)
VALUES ('superadministrador', 'Acceso total al sistema', 'activo', NOW(), NOW());

SET @rol_superadmin_id := LAST_INSERT_ID();

-- 4) Crear usuario superadministrador temporal
-- Clave temporal en texto plano: Temp1234*
-- Hash bcrypt generado: $2y$10$TH3r9i7I2vtJZPl9PqQyE.eg5i3N12KB5kXx7qhRRG2LnwEYiqdbq
INSERT INTO usuarios (
  nombre,
  email,
  password,
  rol_id,
  estado,
  must_change_password,
  created_at,
  updated_at
) VALUES (
  'Super Administrador',
  'superadmin@local.test',
  '$2y$10$TH3r9i7I2vtJZPl9PqQyE.eg5i3N12KB5kXx7qhRRG2LnwEYiqdbq',
  @rol_superadmin_id,
  'activo',
  1,
  NOW(),
  NOW()
);

COMMIT;

-- Verificación rápida
SELECT id, nombre, email, rol_id, estado, must_change_password
FROM usuarios
WHERE email = 'superadmin@local.test'
LIMIT 1;
