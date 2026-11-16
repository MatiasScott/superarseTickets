-- Ejecutar primero en base de datos local de pruebas.
CREATE TABLE IF NOT EXISTS crm_prospect_merges (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 primary_contact_id INT NOT NULL,
 secondary_contact_id INT NOT NULL,
 choices_json JSON NOT NULL,
 merged_by INT NOT NULL,
 merged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_secondary_merge (secondary_contact_id),
 KEY idx_primary_merge (primary_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
