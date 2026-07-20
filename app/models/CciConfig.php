<?php

class CciConfig extends Model
{
	protected string $table = 'cci_configuraciones';

	public function getBySection(string $section): array
	{
		$stmt = $this->db->prepare('SELECT config_key, config_value, is_secret FROM cci_configuraciones WHERE section_name = :section ORDER BY config_key ASC');
		$stmt->execute(['section' => $section]);
		$rows = $stmt->fetchAll() ?: [];

		$out = [];
		foreach ($rows as $row) {
			$key = (string) ($row['config_key'] ?? '');
			if ($key === '') {
				continue;
			}
			$out[$key] = [
				'value' => (string) ($row['config_value'] ?? ''),
				'is_secret' => ((int) ($row['is_secret'] ?? 0)) === 1,
			];
		}

		return $out;
	}

	public function getValue(string $section, string $key, string $default = ''): string
	{
		$stmt = $this->db->prepare('SELECT config_value FROM cci_configuraciones WHERE section_name = :section AND config_key = :config_key LIMIT 1');
		$stmt->execute([
			'section' => $section,
			'config_key' => $key,
		]);
		$value = $stmt->fetchColumn();
		return $value === false ? $default : (string) $value;
	}

	public function upsert(string $section, string $key, string $value, bool $isSecret = false, ?int $updatedBy = null): void
	{
		$stmt = $this->db->prepare('INSERT INTO cci_configuraciones (section_name, config_key, config_value, is_secret, updated_by, updated_at)
			VALUES (:section_name, :config_key, :config_value, :is_secret, :updated_by, NOW())
			ON DUPLICATE KEY UPDATE
				config_value = VALUES(config_value),
				is_secret = VALUES(is_secret),
				updated_by = VALUES(updated_by),
				updated_at = NOW()');
		$stmt->execute([
			'section_name' => $section,
			'config_key' => $key,
			'config_value' => $value,
			'is_secret' => $isSecret ? 1 : 0,
			'updated_by' => $updatedBy,
		]);
	}
}
