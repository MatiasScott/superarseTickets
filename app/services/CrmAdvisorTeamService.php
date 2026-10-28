<?php

/**
 * Asignación dinámica de asesores CRM a equipos (mañana / noche / individual).
 * Determina el valor "creado_por" de los clientes potenciales a partir del asesor.
 */
class CrmAdvisorTeamService
{
	public const TEAM_MANANA = 'manana';
	public const TEAM_NOCHE = 'noche';
	public const TEAM_PROPIO = 'propio';

	public const CREATOR_MANANA = 'EQUIPO MAÑANA';
	public const CREATOR_NOCHE = 'EQUIPO NOCHE';

	private const LEGACY_TEAMS = [
		'Lizbeth Ochoa' => self::TEAM_MANANA,
		'Jennifer Betancourt' => self::TEAM_MANANA,
		'Melany Artieda' => self::TEAM_MANANA,
		'Melany Vásquez' => self::TEAM_NOCHE,
		'Luis Granja' => self::TEAM_PROPIO,
		'Mayra Segarra' => self::TEAM_PROPIO,
		'Noemi Toro' => self::TEAM_PROPIO,
	];

	private static bool $schemaReady = false;

	public static function teamOptions(): array
	{
		return [
			self::TEAM_MANANA => 'Equipo mañana',
			self::TEAM_NOCHE => 'Equipo noche',
			self::TEAM_PROPIO => 'Individual (creado por = asesor)',
			'' => 'Sin asignación',
		];
	}

	public static function normalizeTeam(?string $team): ?string
	{
		$team = strtolower(trim((string) $team));
		return in_array($team, [self::TEAM_MANANA, self::TEAM_NOCHE, self::TEAM_PROPIO], true) ? $team : null;
	}

	public static function creatorForTeam(?string $team, string $asesor): string
	{
		switch (self::normalizeTeam($team)) {
			case self::TEAM_MANANA:
				return self::CREATOR_MANANA;
			case self::TEAM_NOCHE:
				return self::CREATOR_NOCHE;
			case self::TEAM_PROPIO:
				return trim($asesor);
			default:
				return '';
		}
	}

	public static function legacyCreatorFor(string $asesor): string
	{
		$asesor = trim($asesor);
		return self::creatorForTeam(self::LEGACY_TEAMS[$asesor] ?? null, $asesor);
	}

	public static function ensureSchema(PDO $db): void
	{
		if (self::$schemaReady) {
			return;
		}

		$exists = $db->query("SHOW COLUMNS FROM crm_prospect_asesores LIKE 'equipo'")->fetch();
		if (!$exists) {
			$db->exec("ALTER TABLE crm_prospect_asesores ADD COLUMN equipo VARCHAR(20) NULL DEFAULT NULL AFTER nombre");
			// Migración inicial: conservar las reglas que antes estaban fijas en el código.
			$stmt = $db->prepare('UPDATE crm_prospect_asesores SET equipo = :equipo WHERE nombre = :nombre AND equipo IS NULL');
			foreach (self::LEGACY_TEAMS as $nombre => $equipo) {
				$stmt->execute(['equipo' => $equipo, 'nombre' => $nombre]);
			}
		}

		self::$schemaReady = true;
	}

	public static function creatorFor(PDO $db, string $asesor): string
	{
		$asesor = trim($asesor);
		if ($asesor === '') {
			return '';
		}

		try {
			self::ensureSchema($db);
			$stmt = $db->prepare('SELECT equipo FROM crm_prospect_asesores WHERE nombre = :nombre ORDER BY (estado = \'activo\') DESC, id ASC LIMIT 1');
			$stmt->execute(['nombre' => $asesor]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				return self::legacyCreatorFor($asesor);
			}

			return self::creatorForTeam($row['equipo'] ?? null, $asesor);
		} catch (Throwable $e) {
			return self::legacyCreatorFor($asesor);
		}
	}

	/** @return array<string,string> nombre asesor => creado_por */
	public static function map(PDO $db): array
	{
		try {
			self::ensureSchema($db);
			$rows = $db->query('SELECT nombre, equipo FROM crm_prospect_asesores ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
		} catch (Throwable $e) {
			$map = [];
			foreach (array_keys(self::LEGACY_TEAMS) as $nombre) {
				$map[$nombre] = self::legacyCreatorFor($nombre);
			}
			return $map;
		}

		$map = [];
		foreach ($rows as $row) {
			$nombre = trim((string) ($row['nombre'] ?? ''));
			if ($nombre !== '' && !array_key_exists($nombre, $map)) {
				$map[$nombre] = self::creatorForTeam($row['equipo'] ?? null, $nombre);
			}
		}

		return $map;
	}
}
