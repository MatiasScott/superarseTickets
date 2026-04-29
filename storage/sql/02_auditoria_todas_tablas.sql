USE istsTicket;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(128) NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    record_id BIGINT NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    user_id BIGINT NULL,
    ip_address VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_table_date (table_name, created_at),
    INDEX idx_audit_user_date (user_id, created_at)
);

DROP PROCEDURE IF EXISTS sp_regenerar_triggers_auditoria;
DELIMITER $$

CREATE PROCEDURE sp_regenerar_triggers_auditoria(IN p_schema VARCHAR(128))
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table VARCHAR(128);
    DECLARE v_pk VARCHAR(128);
    DECLARE v_new_columns LONGTEXT;
    DECLARE v_old_columns LONGTEXT;
    DECLARE sql_text LONGTEXT;

    DECLARE cur CURSOR FOR
        SELECT t.table_name
        FROM information_schema.tables t
        WHERE t.table_schema = p_schema
            AND t.table_type = 'BASE TABLE'
            AND t.table_name <> 'audit_logs';

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_table;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        SELECT k.column_name
        INTO v_pk
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage k
            ON tc.constraint_name = k.constraint_name
            AND tc.table_schema = k.table_schema
            AND tc.table_name = k.table_name
        WHERE tc.table_schema = p_schema
            AND tc.table_name = v_table
            AND tc.constraint_type = 'PRIMARY KEY'
        ORDER BY k.ordinal_position
        LIMIT 1;

        IF v_pk IS NULL THEN
            SET v_pk = 'id';
        END IF;

        SELECT GROUP_CONCAT(CONCAT("'", c.column_name, "', NEW.`", c.column_name, "`")
            ORDER BY c.ordinal_position SEPARATOR ', ')
        INTO v_new_columns
        FROM information_schema.columns c
        WHERE c.table_schema = p_schema
            AND c.table_name = v_table;

        SELECT GROUP_CONCAT(CONCAT("'", c.column_name, "', OLD.`", c.column_name, "`")
            ORDER BY c.ordinal_position SEPARATOR ', ')
        INTO v_old_columns
        FROM information_schema.columns c
        WHERE c.table_schema = p_schema
            AND c.table_name = v_table;

        SET @sql_drop_ai = CONCAT('DROP TRIGGER IF EXISTS `', p_schema, '`.`aud_', v_table, '_ai`');
        PREPARE stmt FROM @sql_drop_ai;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @sql_drop_au = CONCAT('DROP TRIGGER IF EXISTS `', p_schema, '`.`aud_', v_table, '_au`');
        PREPARE stmt FROM @sql_drop_au;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @sql_drop_ad = CONCAT('DROP TRIGGER IF EXISTS `', p_schema, '`.`aud_', v_table, '_ad`');
        PREPARE stmt FROM @sql_drop_ad;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET sql_text = CONCAT(
            'CREATE TRIGGER `', p_schema, '`.`aud_', v_table, '_ai` AFTER INSERT ON `', p_schema, '`.`', v_table, '` FOR EACH ROW ',
            'INSERT INTO `', p_schema, '`.`audit_logs` (table_name, action, record_id, before_data, after_data, user_id, ip_address, created_at) VALUES (',
            QUOTE(v_table), ', ''INSERT'', NEW.`', v_pk, '`, NULL, JSON_OBJECT(', IFNULL(v_new_columns, "'_empty', NULL"), '), @audit_user_id, @audit_ip, NOW())'
        );
        PREPARE stmt FROM sql_text;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET sql_text = CONCAT(
            'CREATE TRIGGER `', p_schema, '`.`aud_', v_table, '_au` AFTER UPDATE ON `', p_schema, '`.`', v_table, '` FOR EACH ROW ',
            'INSERT INTO `', p_schema, '`.`audit_logs` (table_name, action, record_id, before_data, after_data, user_id, ip_address, created_at) VALUES (',
            QUOTE(v_table), ', ''UPDATE'', NEW.`', v_pk, '`, JSON_OBJECT(', IFNULL(v_old_columns, "'_empty', NULL"), '), JSON_OBJECT(', IFNULL(v_new_columns, "'_empty', NULL"), '), @audit_user_id, @audit_ip, NOW())'
        );
        PREPARE stmt FROM sql_text;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET sql_text = CONCAT(
            'CREATE TRIGGER `', p_schema, '`.`aud_', v_table, '_ad` AFTER DELETE ON `', p_schema, '`.`', v_table, '` FOR EACH ROW ',
            'INSERT INTO `', p_schema, '`.`audit_logs` (table_name, action, record_id, before_data, after_data, user_id, ip_address, created_at) VALUES (',
            QUOTE(v_table), ', ''DELETE'', OLD.`', v_pk, '`, JSON_OBJECT(', IFNULL(v_old_columns, "'_empty', NULL"), '), NULL, @audit_user_id, @audit_ip, NOW())'
        );
        PREPARE stmt FROM sql_text;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET v_pk = NULL;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

CALL sp_regenerar_triggers_auditoria(DATABASE());
