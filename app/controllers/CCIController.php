<?php

class CCIController extends Controller
{
	private function db(): PDO
	{
		return Database::getInstance()->connection();
	}

	private function connectSuperarseDatabase(): ?PDO
	{
		$host = trim((string) env('SUPERARSE_DB_HOST', ''));
		$port = trim((string) env('SUPERARSE_DB_PORT', '3306'));
		$database = trim((string) env('SUPERARSE_DB_DATABASE', ''));
		$username = trim((string) env('SUPERARSE_DB_USERNAME', ''));
		$password = (string) env('SUPERARSE_DB_PASSWORD', '');
		$charset = trim((string) env('SUPERARSE_DB_CHARSET', 'utf8mb4'));

		if ($host !== '' && $database !== '' && $username !== '') {
			$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
			return new PDO($dsn, $username, $password, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]);
		}

		// Fallback explícito solicitado: BD superarse1_conectados.
		$host = trim((string) env('DB_HOST', 'localhost'));
		$port = trim((string) env('DB_PORT', '3306'));
		$username = trim((string) env('DB_USERNAME', env('DB_USER', 'root')));
		$password = (string) env('DB_PASSWORD', '');
		$charset = trim((string) env('DB_CHARSET', 'utf8mb4'));
		$database = 'superarse1_conectados';

		if ($host === '' || $username === '') {
			return null;
		}

		try {
			$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
			return new PDO($dsn, $username, $password, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]);
		} catch (Throwable $e) {
			return null;
		}
	}

	private function fetchCciCareerOptions(PDO $localDb): array
	{
		// 1) Intentar igual que CRM: users.programa desde BD Superarse.
		try {
			$remote = $this->connectSuperarseDatabase();
			if ($remote instanceof PDO) {
				$rows = $remote->query("SELECT DISTINCT TRIM(COALESCE(programa, '')) AS programa
					FROM users
					WHERE programa IS NOT NULL AND TRIM(programa) <> ''
					ORDER BY programa ASC")->fetchAll() ?: [];
				$carreras = [];
				foreach ($rows as $row) {
					$nombre = trim((string) ($row['programa'] ?? ''));
					if ($nombre !== '') {
						$carreras[] = ['id' => 0, 'nombre' => $nombre];
					}
				}
				if (!empty($carreras)) {
					return $carreras;
				}
			}
		} catch (Throwable $e) {
			// continuar con fallback local
		}

		// 2) Fallback local (tabla carreras)
		$carreras = $localDb->query("SELECT id, nombre
			FROM carreras
			WHERE TRIM(COALESCE(nombre, '')) <> ''
			  AND (
				LOWER(TRIM(COALESCE(estado, ''))) = 'activo'
				OR estado = '1'
				OR estado = 1
				OR estado IS NULL
			  )
			ORDER BY nombre ASC
			LIMIT 200")->fetchAll() ?: [];
		if (!empty($carreras)) {
			return $carreras;
		}

		return $localDb->query("SELECT id, nombre
			FROM carreras
			WHERE TRIM(COALESCE(nombre, '')) <> ''
			ORDER BY nombre ASC
			LIMIT 200")->fetchAll() ?: [];
	}

	private function ensureCciTables(PDO $db): void
	{
		$db->exec("CREATE TABLE IF NOT EXISTS cci_proveedores (
			id INT AUTO_INCREMENT PRIMARY KEY,
			codigo VARCHAR(50) NOT NULL,
			nombre VARCHAR(120) NOT NULL,
			descripcion VARCHAR(255) NULL,
			estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
			orden INT NOT NULL DEFAULT 0,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_cci_proveedor_codigo (codigo)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_configuraciones (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			section_name VARCHAR(60) NOT NULL,
			config_key VARCHAR(80) NOT NULL,
			config_value TEXT NULL,
			is_secret TINYINT(1) NOT NULL DEFAULT 0,
			updated_by INT NULL,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_cci_config (section_name, config_key),
			INDEX idx_cci_config_section (section_name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_conversacion_notas (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			conversacion_id INT NOT NULL,
			nota TEXT NOT NULL,
			usuario_id INT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_cci_notas_conversacion (conversacion_id),
			INDEX idx_cci_notas_usuario (usuario_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_mensaje_refs (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			provider_code VARCHAR(50) NOT NULL,
			external_message_id VARCHAR(191) NOT NULL,
			conversacion_id INT NULL,
			mensaje_id BIGINT NULL,
			direction ENUM('in','out') NOT NULL DEFAULT 'in',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_cci_msg_ref (provider_code, external_message_id),
			INDEX idx_cci_msg_ref_conv (conversacion_id),
			INDEX idx_cci_msg_ref_msg (mensaje_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_webhook_logs (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			provider_code VARCHAR(50) NOT NULL,
			event_type VARCHAR(80) NULL,
			payload LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'ok',
			error_message VARCHAR(255) NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_cci_webhook_provider (provider_code),
			INDEX idx_cci_webhook_created (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_sync_state (
			provider_code VARCHAR(50) PRIMARY KEY,
			last_cursor VARCHAR(255) NULL,
			last_sync_at DATETIME NULL,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_conversacion_refs (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			provider_code VARCHAR(50) NOT NULL,
			external_conversation_id VARCHAR(191) NOT NULL,
			conversacion_id INT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_cci_conv_ref (provider_code, external_conversation_id),
			INDEX idx_cci_conv_ref_conversacion (conversacion_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_asesor_usuario_map (
			crm_asesor_id INT PRIMARY KEY,
			usuario_id INT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_cci_asesor_usuario_usuario (usuario_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS user_notifications (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			user_id INT NOT NULL,
			title VARCHAR(180) NOT NULL,
			message TEXT NOT NULL,
			url VARCHAR(500) NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'info',
			is_read TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_user_notifications_user_read (user_id, is_read),
			INDEX idx_user_notifications_created (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		// El ENUM original solo admite activo/inactivo: ampliarlo para poder cerrar conversaciones.
		try {
			$estadoCol = $db->query("SHOW COLUMNS FROM bot_conversaciones LIKE 'estado'")->fetch();
			if ($estadoCol && !str_contains(strtolower((string) ($estadoCol['Type'] ?? '')), "'cerrado'")) {
				$db->exec("ALTER TABLE bot_conversaciones MODIFY estado ENUM('activo','inactivo','cerrado') NULL DEFAULT 'activo'");
			}
		} catch (Throwable $e) {
			// Sin permisos de ALTER se mantiene el comportamiento actual.
		}

		// En algunos entornos bot_mensajes no tiene columna tipo/fecha: sin ella, imágenes/audio/video se muestran como texto.
		try {
			$mensajesColumns = array_column($db->query('SHOW COLUMNS FROM bot_mensajes')->fetchAll(), 'Field');
			if (!in_array('tipo', $mensajesColumns, true)) {
				$db->exec("ALTER TABLE bot_mensajes ADD COLUMN tipo ENUM('texto','archivo') NOT NULL DEFAULT 'texto' AFTER mensaje");
			}
			if (!in_array('fecha', $mensajesColumns, true)) {
				$db->exec("ALTER TABLE bot_mensajes ADD COLUMN fecha DATETIME NULL AFTER tipo");
			}
			if (!in_array('updated_at', $mensajesColumns, true)) {
				$db->exec("ALTER TABLE bot_mensajes ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
			}
		} catch (Throwable $e) {
			// Sin permisos de ALTER se mantiene el comportamiento actual.
		}

		$db->exec("CREATE TABLE IF NOT EXISTS cci_etiquetas (
			id INT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(100) NOT NULL,
			estado TINYINT DEFAULT 1,
			fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Tabla de subetiquetas (Req 1)
		$db->exec("CREATE TABLE IF NOT EXISTS cci_subetiquetas (
			id INT AUTO_INCREMENT PRIMARY KEY,
			etiqueta_id INT NOT NULL,
			nombre VARCHAR(100) NOT NULL,
			estado TINYINT DEFAULT 1,
			fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
			FOREIGN KEY (etiqueta_id) REFERENCES cci_etiquetas(id) ON DELETE CASCADE,
			INDEX idx_cci_subetiqueta_etiqueta (etiqueta_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		try {
			$conversacionesColumns = array_column($db->query('SHOW COLUMNS FROM bot_conversaciones')->fetchAll(), 'Field');
			if (!in_array('etiqueta_id', $conversacionesColumns, true)) {
				$db->exec('ALTER TABLE bot_conversaciones ADD COLUMN etiqueta_id INT NULL DEFAULT NULL');
			}
			if (!in_array('subetiqueta_id', $conversacionesColumns, true)) {
				$db->exec('ALTER TABLE bot_conversaciones ADD COLUMN subetiqueta_id INT NULL DEFAULT NULL');
			}
		} catch (Throwable $e) {
			// Sin permisos de ALTER se mantiene el comportamiento actual.
		}


		$db->exec("CREATE TABLE IF NOT EXISTS cci_sync_diagnostics (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			provider_code VARCHAR(50) NOT NULL,
			window_start DATETIME NULL,
			window_end DATETIME NULL,
			source_rows INT NOT NULL DEFAULT 0,
			conversation_rows INT NOT NULL DEFAULT 0,
			imported_rows INT NOT NULL DEFAULT 0,
			skipped_rows INT NOT NULL DEFAULT 0,
			delimiter_name VARCHAR(20) NULL,
			headers_json TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_cci_sync_diag_provider_created (provider_code, created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_plantillas (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(150) NOT NULL,
			canal VARCHAR(40) NOT NULL DEFAULT 'whatsapp',
			categoria VARCHAR(80) NULL,
			contenido TEXT NOT NULL,
			variables_json TEXT NULL,
			estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
			created_by INT NULL,
			updated_by INT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_cci_plantillas_canal (canal),
			INDEX idx_cci_plantillas_estado (estado),
			INDEX idx_cci_plantillas_categoria (categoria)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_respuestas_rapidas (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			categoria VARCHAR(80) NULL,
			atajo VARCHAR(50) NULL,
			titulo VARCHAR(160) NOT NULL,
			contenido TEXT NOT NULL,
			favorito TINYINT(1) NOT NULL DEFAULT 0,
			uso_count INT NOT NULL DEFAULT 0,
			estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
			created_by INT NULL,
			updated_by INT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_cci_rr_categoria (categoria),
			INDEX idx_cci_rr_atajo (atajo),
			INDEX idx_cci_rr_estado (estado),
			INDEX idx_cci_rr_favorito (favorito)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_campanas (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(160) NOT NULL,
			descripcion VARCHAR(255) NULL,
			canal VARCHAR(40) NOT NULL DEFAULT 'whatsapp',
			provider_code VARCHAR(50) NOT NULL DEFAULT 'whatchimp',
			plantilla_id BIGINT NULL,
			mensaje_base TEXT NULL,
			estado ENUM('borrador','programada','enviando','completada','cancelada') NOT NULL DEFAULT 'borrador',
			fecha_programada DATETIME NULL,
			created_by INT NULL,
			updated_by INT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_cci_campanas_estado (estado),
			INDEX idx_cci_campanas_provider (provider_code),
			INDEX idx_cci_campanas_fecha_programada (fecha_programada)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_campana_destinatarios (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			campana_id BIGINT NOT NULL,
			contacto_id INT NULL,
			nombre VARCHAR(160) NULL,
			telefono VARCHAR(40) NOT NULL,
			estado_envio ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
			intentos INT NOT NULL DEFAULT 0,
			ultimo_error VARCHAR(255) NULL,
			external_message_id VARCHAR(191) NULL,
			enviado_at DATETIME NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_cci_campana_destinatario (campana_id, telefono),
			INDEX idx_cci_camp_dest_campana (campana_id),
			INDEX idx_cci_camp_dest_estado (estado_envio)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS cci_automation_logs (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			event_name VARCHAR(80) NOT NULL,
			endpoint_url VARCHAR(255) NULL,
			dispatch_status VARCHAR(20) NOT NULL DEFAULT 'sent',
			request_payload LONGTEXT NULL,
			response_payload LONGTEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_cci_auto_event (event_name),
			INDEX idx_cci_auto_status (dispatch_status),
			INDEX idx_cci_auto_created (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$providers = [
			['whatchimp', 'Whatchimp', 'Proveedor actual para WhatsApp', 1],
			['whatsapp_cloud', 'WhatsApp Business Cloud', 'Meta WhatsApp Cloud API', 2],
			['messenger', 'Facebook Messenger', 'Mensajería Facebook', 3],
			['instagram_direct', 'Instagram Direct', 'Mensajería Instagram', 4],
			['telegram', 'Telegram', 'Mensajería Telegram', 5],
			['web_chat', 'Web Chat', 'Canal de chat web', 6],
		];

		$stmt = $db->prepare('INSERT INTO cci_proveedores (codigo, nombre, descripcion, estado, orden) VALUES (:codigo, :nombre, :descripcion, "activo", :orden)
			ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion), orden = VALUES(orden), estado = VALUES(estado), updated_at = NOW()');
		foreach ($providers as $provider) {
			$stmt->execute([
				'codigo' => $provider[0],
				'nombre' => $provider[1],
				'descripcion' => $provider[2],
				'orden' => $provider[3],
			]);
		}

		$configDefaults = [
			['general', 'default_provider', 'whatchimp', 0],
			['general', 'lead_interaction_threshold', '5', 0],
			['campanas', 'auto_enabled', 'inactivo', 0],
			['campanas', 'auto_limit_campaigns', '5', 0],
			['campanas', 'auto_batch_size', '100', 0],
			['campanas', 'auto_retry_max', '3', 0],
			['reportes', 'default_days', '30', 0],
			['reportes', 'objetivo_entrega_pct', '95', 0],
			['reportes', 'objetivo_error_pct', '5', 0],
			['sla', 'max_sin_responder_minutos', '15', 0],
			['sla', 'max_espera_minutos', '30', 0],
			['sla', 'max_interacciones', '8', 0],
			['sla', 'recordatorio_minutos', '10', 0],
			['whatchimp', 'estado', 'inactivo', 0],
			['whatchimp', 'api_key', '', 1],
			['whatchimp', 'base_url', '', 0],
			['whatchimp', 'numero_asociado', '', 0],
			['whatchimp', 'alias', '', 0],
			['whatchimp', 'webhook', '', 0],
			['whatchimp', 'send_endpoint', '/api/v1/whatsapp/send', 0],
			['whatchimp', 'sync_endpoint', '/api/v1/whatsapp/get/conversation', 0],
			['whatchimp', 'verify_token', '', 1],
			['n8n', 'estado', 'inactivo', 0],
			['n8n', 'url', '', 0],
			['n8n', 'webhook', '', 0],
			['n8n', 'auth_token', '', 1],
			['n8n', 'timeout_ms', '12000', 0],
			['n8n', 'event_filter', '', 0],
			['ia', 'estado', 'inactivo', 0],
			['ia', 'proveedor', 'openai', 0],
			['ia', 'modelo', 'gpt-4.1-mini', 0],
			['ia', 'temperatura', '0.3', 0],
			['ia', 'limite_tokens', '1200', 0],
			['ia', 'prompt_base', '', 0],
			['ia', 'base_conocimiento', '', 0],
		];

		$configStmt = $db->prepare('INSERT IGNORE INTO cci_configuraciones (section_name, config_key, config_value, is_secret, updated_by, updated_at)
			VALUES (:section_name, :config_key, :config_value, :is_secret, NULL, NOW())');
		foreach ($configDefaults as $default) {
			$configStmt->execute([
				'section_name' => $default[0],
				'config_key' => $default[1],
				'config_value' => $default[2],
				'is_secret' => $default[3],
			]);
		}
	}

	private function ensureProspectByInteraction(PDO $db): void
	{
		$threshold = (int) ((new CciConfig())->getValue('general', 'lead_interaction_threshold', '5'));
		$threshold = max(1, $threshold);

		$sql = "SELECT bc.contacto_id, COUNT(*) AS total_interacciones
			FROM bot_conversaciones bc
			WHERE bc.contacto_id IS NOT NULL
			GROUP BY bc.contacto_id
			HAVING COUNT(*) >= :threshold";
		$stmt = $db->prepare($sql);
		$stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll() ?: [];

		if (empty($rows)) {
			return;
		}

		$estadoInicial = $this->resolveInitialProspectStateId($db);
		$upsert = $db->prepare('INSERT INTO interesados (contacto_id, estado_id, origen, creado_por, convertido, estado, created_at, updated_at)
			VALUES (:contacto_id, :estado_id, "CCI", "sistema", 0, "activo", NOW(), NOW())
			ON DUPLICATE KEY UPDATE
				estado_id = COALESCE(interesados.estado_id, VALUES(estado_id)),
				estado = "activo",
				updated_at = NOW()');

		foreach ($rows as $row) {
			$contactId = (int) ($row['contacto_id'] ?? 0);
			if ($contactId <= 0) {
				continue;
			}
			$upsert->execute([
				'contacto_id' => $contactId,
				'estado_id' => $estadoInicial,
			]);
		}
	}

	private function resolveInitialProspectStateId(PDO $db): ?int
	{
		$stmt = $db->query("SELECT id FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC LIMIT 1");
		$id = (int) ($stmt ? ($stmt->fetchColumn() ?: 0) : 0);
		return $id > 0 ? $id : null;
	}

	private function fetchConfigSection(PDO $db, string $section): array
	{
		$stmt = $db->prepare('SELECT config_key, config_value, is_secret FROM cci_configuraciones WHERE section_name = :section ORDER BY config_key ASC');
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

	private function fireAutomationEvent(string $eventName, array $payload = [], array $meta = []): void
	{
		try {
			(new CciAutomationService())->dispatch($eventName, $payload, $meta);
		} catch (Throwable $e) {
			// No bloquear procesos funcionales por automatización.
		}
	}

	private function getTableColumnsSafe(PDO $db, string $table): array
	{
		try {
			$stmt = $db->query('SHOW COLUMNS FROM ' . $table);
			$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
			$columns = [];
			foreach ($rows as $row) {
				$field = (string) ($row['Field'] ?? '');
				if ($field !== '') {
					$columns[] = $field;
				}
			}
			return $columns;
		} catch (Throwable $e) {
			return [];
		}
	}

	private function normalizePhone(string $value): string
	{
		$digits = preg_replace('/[^0-9+]/', '', trim($value)) ?: '';
		if ($digits === '') {
			return '';
		}
		if (str_starts_with($digits, '00')) {
			$digits = '+' . substr($digits, 2);
		}
		if (!str_starts_with($digits, '+')) {
			$digits = '+' . ltrim($digits, '+');
		}
		return $digits;
	}

	private function ensureContactByPhone(PDO $db, string $phone, string $name = ''): ?int
	{
		$phone = $this->normalizePhone($phone);
		if ($phone === '') {
			return null;
		}

		try {
			$stmt = $db->prepare('SELECT contacto_id FROM telefonos_contacto WHERE telefono = :telefono ORDER BY id ASC LIMIT 1');
			$stmt->execute(['telefono' => $phone]);
			$contactId = (int) ($stmt->fetchColumn() ?: 0);
			if ($contactId > 0) {
				return $contactId;
			}
		} catch (Throwable $e) {
			// continúa con fallback.
		}

		$first = 'Contacto';
		$last = 'CCI';
		$name = trim($name);
		if ($name !== '') {
			$parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			if (!empty($parts)) {
				$first = mb_substr((string) ($parts[0] ?? 'Contacto'), 0, 150);
				$last = mb_substr((string) implode(' ', array_slice($parts, 1)), 0, 150);
				if ($last === '') {
					$last = 'CCI';
				}
			}
		}

		// En algunos entornos `contactos.tipo` es ENUM y no incluye "interesado".
		$insertContact = $db->prepare('INSERT INTO contactos (nombre, apellido, tipo, estado, created_at, updated_at)
			VALUES (:nombre, :apellido, "externo", "activo", NOW(), NOW())');
		$insertContact->execute([
			'nombre' => $first,
			'apellido' => $last,
		]);
		$contactId = (int) $db->lastInsertId();
		if ($contactId <= 0) {
			return null;
		}

		try {
			$insertPhone = $db->prepare('INSERT IGNORE INTO telefonos_contacto (contacto_id, telefono, tipo, estado, created_at, updated_at)
				VALUES (:contacto_id, :telefono, "principal", "activo", NOW(), NOW())');
			$insertPhone->execute([
				'contacto_id' => $contactId,
				'telefono' => $phone,
			]);
		} catch (Throwable $e) {
			// sin bloqueo.
		}

		try {
			$insertChannel = $db->prepare('INSERT IGNORE INTO crm_person_channels (contacto_id, channel_type, channel_value, source, created_at, updated_at)
				VALUES (:contacto_id, "phone", :channel_value, "whatchimp", NOW(), NOW())');
			$insertChannel->execute([
				'contacto_id' => $contactId,
				'channel_value' => $phone,
			]);
		} catch (Throwable $e) {
			// tabla puede no existir en algunos entornos.
		}

		return $contactId;
	}

	private function ensureConversation(PDO $db, ?int $contactId, string $canal = 'whatsapp'): ?int
	{
		if ($contactId === null || $contactId <= 0) {
			return null;
		}

		$stmt = $db->prepare('SELECT id
			FROM bot_conversaciones
			WHERE contacto_id = :contacto_id AND canal = :canal
			ORDER BY id DESC
			LIMIT 1');
		$stmt->execute([
			'contacto_id' => $contactId,
			'canal' => $canal,
		]);
		$conversationId = (int) ($stmt->fetchColumn() ?: 0);
		if ($conversationId > 0) {
			return $conversationId;
		}

		$columns = $this->getTableColumnsSafe($db, 'bot_conversaciones');
		$allowed = [
			'contacto_id' => $contactId,
			'canal' => $canal,
			'estado' => 'activo',
			'fecha_inicio' => date('Y-m-d H:i:s'),
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		];

		$data = [];
		foreach ($allowed as $k => $v) {
			if (in_array($k, $columns, true)) {
				$data[$k] = $v;
			}
		}

		if (empty($data)) {
			return null;
		}

		$columnList = implode(', ', array_keys($data));
		$placeholders = implode(', ', array_map(static fn($k) => ':' . $k, array_keys($data)));
		$insertSql = "INSERT INTO bot_conversaciones ({$columnList}) VALUES ({$placeholders})";
		$insert = $db->prepare($insertSql);
		$insert->execute($data);

		return (int) $db->lastInsertId();
	}

	private function messageRefExists(PDO $db, string $providerCode, string $externalId): bool
	{
		if ($externalId === '') {
			return false;
		}
		$stmt = $db->prepare('SELECT id FROM cci_mensaje_refs WHERE provider_code = :provider_code AND external_message_id = :external_id LIMIT 1');
		$stmt->execute([
			'provider_code' => $providerCode,
			'external_id' => $externalId,
		]);
		return (bool) $stmt->fetchColumn();
	}

	private function saveMessageRef(PDO $db, string $providerCode, string $externalId, ?int $conversationId, ?int $messageId, string $direction): void
	{
		if ($externalId === '') {
			return;
		}

		$stmt = $db->prepare('INSERT INTO cci_mensaje_refs (provider_code, external_message_id, conversacion_id, mensaje_id, direction, created_at)
			VALUES (:provider_code, :external_id, :conversation_id, :message_id, :direction, NOW())
			ON DUPLICATE KEY UPDATE
				conversacion_id = VALUES(conversacion_id),
				mensaje_id = VALUES(mensaje_id),
				direction = VALUES(direction)');
		$stmt->execute([
			'provider_code' => $providerCode,
			'external_id' => $externalId,
			'conversation_id' => $conversationId,
			'message_id' => $messageId,
			'direction' => $direction,
		]);
	}

	private function insertBotMessage(PDO $db, int $conversationId, string $text, bool $isOut, string $dateTime = '', string $tipo = 'texto'): int
	{
		if ($conversationId <= 0 || trim($text) === '') {
			return 0;
		}

		// Normalizar tipo al ENUM válido de la BD: 'texto' | 'archivo'
		$fileTypes = ['image', 'video', 'audio', 'document', 'sticker', 'voice', 'archivo'];
		$tipoNorm = in_array(strtolower($tipo), $fileTypes, true) ? 'archivo' : 'texto';
		$normalizedDateTime = date('Y-m-d H:i:s');
		if (trim($dateTime) !== '') {
			$timestamp = strtotime($dateTime);
			if ($timestamp !== false) {
				$normalizedDateTime = date('Y-m-d H:i:s', $timestamp);
			}
		}

		$columns = $this->getTableColumnsSafe($db, 'bot_mensajes');
		$allowed = [
			'conversacion_id' => $conversationId,
			'mensaje' => mb_substr($text, 0, 10000),
			'es_bot' => $isOut ? 1 : 0,
			'tipo' => $tipoNorm,
			'fecha' => $normalizedDateTime,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		];

		$data = [];
		foreach ($allowed as $k => $v) {
			if (in_array($k, $columns, true)) {
				$data[$k] = $v;
			}
		}

		$columnList = implode(', ', array_keys($data));
		$placeholders = implode(', ', array_map(static fn($k) => ':' . $k, array_keys($data)));
		$sql = "INSERT INTO bot_mensajes ({$columnList}) VALUES ({$placeholders})";
		$stmt = $db->prepare($sql);
		$stmt->execute($data);
		return (int) $db->lastInsertId();
	}

	private function normalizeWhatchimpMessages(array $payload): array
	{
		$candidates = [];
		if (isset($payload['messages']) && is_array($payload['messages'])) {
			$candidates = $payload['messages'];
		} elseif (isset($payload['contact_list']) && is_array($payload['contact_list'])) {
			$candidates = $payload['contact_list'];
		} elseif (isset($payload['result']) && is_array($payload['result'])) {
			$candidates = $payload['result'];
		} elseif (isset($payload['data']['messages']) && is_array($payload['data']['messages'])) {
			$candidates = $payload['data']['messages'];
		} elseif (isset($payload['data']) && is_array($payload['data']) && isset($payload['data'][0])) {
			$candidates = $payload['data'];
		} elseif (isset($payload[0]) && is_array($payload[0])) {
			$candidates = $payload;
		} else {
			$candidates = [$payload];
		}

		$out = [];
		foreach ($candidates as $msg) {
			if (!is_array($msg)) {
				continue;
			}

			$body = (string) (
				$msg['message']
				?? $msg['text']
				?? $msg['messageText']
				?? $msg['lastMessage']
				?? $msg['last_message']
				?? $msg['lastMessageText']
				?? $msg['data']['text']
				?? $msg['body']
				?? $msg['content']
				?? ''
			);
			$body = trim($body);

			// Extraer URL de media si existe (para imágenes/audio/video entrantes de WATI)
			$mediaUrl = trim((string) (
				$msg['mediaUrl']
				?? $msg['media_url']
				?? $msg['fileUrl']
				?? $msg['data']['mediaUrl']
				?? $msg['data']['fileUrl']
				?? $msg['data']['downloadUrl']
				?? ''
			));

			$msgTypeRaw = strtolower(trim((string) ($msg['message_type'] ?? ($msg['type'] ?? 'texto'))));
			$isMediaType = in_array($msgTypeRaw, ['image', 'video', 'audio', 'document', 'sticker', 'voice'], true);

			// Intentar extraer filename desde data (ej. WATI document messages)
			$msgData = $msg['data'] ?? [];
			if (!is_array($msgData)) {
				$msgData = [];
			}
			$dataFilename = trim((string) ($msgData['filename'] ?? ($msgData['name'] ?? ($msgData['fileName'] ?? ''))));
			if ($mediaUrl === '') {
				$mediaUrl = trim((string) ($msgData['mediaUrl'] ?? ($msgData['fileUrl'] ?? ($msgData['downloadUrl'] ?? ''))));
			}

			// Si es tipo media y hay URL o filename disponible, priorizar sobre el body (que puede ser texto de sistema)
			$caption = '';
			if ($isMediaType) {
				if ($dataFilename !== '') {
					// filename real del documento (ej. "invoice.pdf") tiene máxima prioridad
					$caption = $body; // guardar texto original como caption
					$body = $dataFilename;
				} elseif ($mediaUrl !== '') {
					// Si el texto es un placeholder de sistema, no guardar como caption
					$systemPlaceholders = ['se adjuntó un archivo', 'a file was attached', 'file attached'];
					if ($body !== '' && !in_array(strtolower(trim($body)), $systemPlaceholders, true)) {
						$caption = $body; // texto real del cliente → guardar como caption
					}
					$body = $mediaUrl;
				}
			} elseif ($body === '' && $mediaUrl !== '') {
				$body = $mediaUrl;
			}

			$directionRaw = strtolower(trim((string) ($msg['direction'] ?? ($msg['type_direction'] ?? (!empty($msg['owner']) ? 'out' : 'in')))));
			$direction = in_array($directionRaw, ['out', 'sent', 'outbound', 'bot'], true) ? 'out' : 'in';

			$phone = (string) (
				$msg['from']
				?? $msg['whatsappNumber']
				?? $msg['waId']
				?? $msg['customerNumber']
				?? $msg['from_number']
				?? $msg['phone']
				?? $msg['wa_id']
				?? ''
			);
			if ($direction === 'out') {
				$alt = (string) ($msg['to'] ?? ($msg['to_number'] ?? ''));
				if ($alt !== '') {
					$phone = $alt;
				}
			}

			$phone = $this->normalizePhone($phone);
			if ($phone === '') {
				continue;
			}

			if ($body === '') {
				if ($isMediaType) {
					// Tipo de media conocido pero sin URL ni texto: saltar silenciosamente
					continue;
				}
				$body = 'Contacto sincronizado desde WATI';
			}

			$timestamp = (string) (
				$msg['timestamp']
				?? ($msg['date']
					?? ($msg['created_at']
						?? ($msg['lastUpdated']
							?? ($msg['updatedAt']
								?? ($msg['last_message_time'] ?? '')))))
			);
			$dateTime = '';
			if ($timestamp !== '') {
				if (ctype_digit($timestamp)) {
					$dateTime = date('Y-m-d H:i:s', (int) $timestamp);
				} else {
					$ts = strtotime($timestamp);
					if ($ts !== false) {
						$dateTime = date('Y-m-d H:i:s', $ts);
					}
				}
			}

			$externalId = (string) (
				$msg['id']
				?? $msg['whatsappMessageId']
				?? $msg['localMessageId']
				?? $msg['message_id']
				?? $msg['wamid']
				?? ''
			);

			$out[] = [
				'external_id' => trim($externalId),
				'direction' => $direction,
				'phone' => $phone,
				'text' => $body,
				'caption' => $caption,
				'datetime' => $dateTime,
				'contact_name' => trim((string) ($msg['contact_name'] ?? ($msg['name'] ?? ($msg['displayName'] ?? ($msg['fullName'] ?? ''))))),
				'tipo' => trim((string) ($msg['message_type'] ?? ($msg['type'] ?? 'texto'))),
			];
		}

		return $out;
	}

	private function processWhatchimpMessages(PDO $db, array $normalizedMessages, string $source): array
	{
		$created = 0;
		$skipped = 0;

		foreach ($normalizedMessages as $event) {
			$externalId = trim((string) ($event['external_id'] ?? ''));
			if ($externalId !== '' && $this->messageRefExists($db, 'whatchimp', $externalId)) {
				$skipped++;
				continue;
			}

			$phone = trim((string) ($event['phone'] ?? ''));
			$text = trim((string) ($event['text'] ?? ''));
			if ($phone === '' || $text === '') {
				$skipped++;
				continue;
			}

			$contactId = $this->ensureContactByPhone($db, $phone, (string) ($event['contact_name'] ?? ''));
			$conversationId = $this->ensureConversation($db, $contactId, 'whatsapp');
			if ($conversationId === null || $conversationId <= 0) {
				$skipped++;
				continue;
			}

			$isOut = ((string) ($event['direction'] ?? 'in')) === 'out';
			$messageId = $this->insertBotMessage(
				$db,
				$conversationId,
				$text,
				$isOut,
				(string) ($event['datetime'] ?? ''),
				(string) ($event['tipo'] ?? 'texto')
			);

			if ($messageId > 0 && $externalId !== '') {
				$this->saveMessageRef($db, 'whatchimp', $externalId, $conversationId, $messageId, $isOut ? 'out' : 'in');
			}

			// Si el archivo venía con texto (caption), insertar como mensaje de texto adicional
			$caption = trim((string) ($event['caption'] ?? ''));
			if ($messageId > 0 && $caption !== '') {
				$this->insertBotMessage(
					$db,
					$conversationId,
					$caption,
					$isOut,
					(string) ($event['datetime'] ?? ''),
					'texto'
				);
			}

			$created++;

			$this->fireAutomationEvent('message_' . ($isOut ? 'sent' : 'received'), [
				'source' => $source,
				'provider' => 'whatchimp',
				'external_id' => $externalId,
				'conversation_id' => $conversationId,
				'contact_id' => $contactId,
				'phone' => $phone,
				'text' => $text,
				'direction' => $isOut ? 'out' : 'in',
				'message_id' => $messageId,
			]);
		}

		AuditLogger::log('CREATE', 'cci_whatchimp_ingest', null, null, [
			'source' => $source,
			'created' => $created,
			'skipped' => $skipped,
		]);

		return [
			'created' => $created,
			'skipped' => $skipped,
		];
	}

	private function resolveConversationPhone(PDO $db, int $conversationId): string
	{
		if ($conversationId <= 0) {
			return '';
		}

		$sql = "SELECT tc.telefono
			FROM bot_conversaciones bc
			LEFT JOIN (
				SELECT x.contacto_id, x.telefono
				FROM telefonos_contacto x
				INNER JOIN (
					SELECT contacto_id, MIN(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) y ON y.first_id = x.id
			) tc ON tc.contacto_id = bc.contacto_id
			WHERE bc.id = :id
			LIMIT 1";

		$stmt = $db->prepare($sql);
		$stmt->execute(['id' => $conversationId]);
		$phone = (string) ($stmt->fetchColumn() ?: '');
		return $this->normalizePhone($phone);
	}

	private function parseCampaignRecipients(string $raw): array
	{
		$rows = preg_split('/\r\n|\r|\n/', $raw) ?: [];
		$out = [];

		foreach ($rows as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			$name = '';
			$phoneRaw = $line;

			if (str_contains($line, ',')) {
				$parts = explode(',', $line, 2);
				$name = trim((string) ($parts[0] ?? ''));
				$phoneRaw = trim((string) ($parts[1] ?? ''));
			}

			$phone = $this->normalizePhone($phoneRaw);
			if ($phone === '') {
				continue;
			}

			$out[] = [
				'nombre' => $name,
				'telefono' => $phone,
			];
		}

		$dedup = [];
		foreach ($out as $row) {
			$dedup[$row['telefono']] = $row;
		}

		return array_values($dedup);
	}

	private function resolveCampanaTemplateText(PDO $db, array $campana): string
	{
		$templateText = trim((string) ($campana['mensaje_base'] ?? ''));
		$plantillaId = (int) ($campana['plantilla_id'] ?? 0);
		if ($plantillaId > 0) {
			$stmt = $db->prepare('SELECT contenido FROM cci_plantillas WHERE id = :id AND estado = "activo" LIMIT 1');
			$stmt->execute(['id' => $plantillaId]);
			$fromTemplate = trim((string) ($stmt->fetchColumn() ?: ''));
			if ($fromTemplate !== '') {
				$templateText = $fromTemplate;
			}
		}
		return $templateText;
	}

	private function applyCampaignVariables(string $template, array $recipient): string
	{
		$nombre = trim((string) ($recipient['nombre'] ?? ''));
		$telefono = trim((string) ($recipient['telefono'] ?? ''));
		$replaced = strtr($template, [
			'{{nombre}}' => $nombre !== '' ? $nombre : 'Cliente',
			'{{telefono}}' => $telefono,
		]);
		return trim($replaced);
	}

	/**
	 * Normaliza archivos del formulario de respuesta:
	 * - attachments[] (multiple)
	 * - audio_record (single)
	 */
	private function validateFreshchatSendResponse(array $response, bool $expectMedia): array
	{
		$data = is_array($response['data'] ?? null) ? $response['data'] : [];
		$failureStatus = strtoupper(trim((string) ($data['status'] ?? ($data['message']['status'] ?? ''))));
		$failureReason = trim((string) ($data['error_message'] ?? ($data['message']['error_message'] ?? ($data['message'] ?? ''))));
		if ($failureStatus === 'FAILED' || $failureReason !== '') {
			return [
				'ok' => false,
				'reason' => 'Freshchat reportó fallo de entrega: ' . ($failureReason !== '' ? $failureReason : $failureStatus),
				'data_keys' => array_keys($data),
			];
		}
		$messageId = '';

		$idCandidates = [
			(string) ($data['id'] ?? ''),
			(string) ($data['message_id'] ?? ''),
			(string) ($data['messageId'] ?? ''),
			(string) (($data['message']['id'] ?? '')),
			(string) (($data['message']['message_id'] ?? '')),
		];
		foreach ($idCandidates as $candidate) {
			$candidate = trim($candidate);
			if ($candidate !== '') {
				$messageId = $candidate;
				break;
			}
		}

		if ($messageId === '') {
			return [
				'ok' => false,
				'reason' => 'Freshchat respondió sin id de mensaje.',
				'data_keys' => array_keys($data),
			];
		}

		if (!$expectMedia) {
			return ['ok' => true, 'message_id' => $messageId];
		}

		$parts = [];
		if (isset($data['message_parts']) && is_array($data['message_parts'])) {
			$parts = $data['message_parts'];
		} elseif (isset($data['message']['message_parts']) && is_array($data['message']['message_parts'])) {
			$parts = $data['message']['message_parts'];
		}

		if (empty($parts)) {
			return [
				'ok' => false,
				'reason' => 'Freshchat respondió sin message_parts de medio.',
				'message_id' => $messageId,
				'data_keys' => array_keys($data),
			];
		}

		foreach ($parts as $part) {
			if (!is_array($part)) {
				continue;
			}
			if (
				isset($part['image'])
				|| isset($part['video'])
				|| isset($part['file'])
				|| isset($part['audio'])
				|| isset($part['document'])
			) {
				return ['ok' => true, 'message_id' => $messageId];
			}
		}

		return [
			'ok' => false,
			'reason' => 'Freshchat respondió sin partes de media válidas.',
			'message_id' => $messageId,
		];
	}

	private function collectReplyUploads(array $attachmentsInput, array $audioInput): array
	{
		$uploads = [];

		if (!empty($attachmentsInput['name']) && is_array($attachmentsInput['name'])) {
			$count = count($attachmentsInput['name']);
			for ($i = 0; $i < $count; $i++) {
				$uploads[] = [
					'name' => (string) ($attachmentsInput['name'][$i] ?? ''),
					'error' => (int) ($attachmentsInput['error'][$i] ?? UPLOAD_ERR_NO_FILE),
					'tmp_name' => (string) ($attachmentsInput['tmp_name'][$i] ?? ''),
					'size' => (int) ($attachmentsInput['size'][$i] ?? 0),
				];
			}
		}

		if (!empty($audioInput) && is_array($audioInput)) {
			$uploads[] = [
				'name' => (string) ($audioInput['name'] ?? ''),
				'error' => (int) ($audioInput['error'] ?? UPLOAD_ERR_NO_FILE),
				'tmp_name' => (string) ($audioInput['tmp_name'] ?? ''),
				'size' => (int) ($audioInput['size'] ?? 0),
			];
		}

		return $uploads;
	}

	private function isUploadedEntryPresent(array $upload): bool
	{
		$error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
		$name = trim((string) ($upload['name'] ?? ''));
		$tmpPath = trim((string) ($upload['tmp_name'] ?? ''));

		if ($error === UPLOAD_ERR_OK && $tmpPath !== '' && is_file($tmpPath)) {
			return true;
		}

		return $name !== '' && $error !== UPLOAD_ERR_NO_FILE;
	}

	private function buildUploadFallbackName(string $tmpPath, string $prefix = 'archivo'): string
	{
		$prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($prefix)) ?: 'archivo';
		$ext = 'bin';
		$mime = function_exists('mime_content_type') ? strtolower((string) (mime_content_type($tmpPath) ?: '')) : '';

		if (str_starts_with($mime, 'audio/')) {
			$audioExt = substr($mime, 6);
			$audioExt = preg_replace('/[^a-z0-9]/', '', $audioExt);
			$ext = $audioExt !== '' ? $audioExt : 'ogg';
		} elseif (str_starts_with($mime, 'image/')) {
			$imageExt = substr($mime, 6);
			$imageExt = preg_replace('/[^a-z0-9]/', '', $imageExt);
			$ext = $imageExt !== '' ? $imageExt : 'jpg';
		} elseif (str_starts_with($mime, 'video/')) {
			$videoExt = substr($mime, 6);
			$videoExt = preg_replace('/[^a-z0-9]/', '', $videoExt);
			$ext = $videoExt !== '' ? $videoExt : 'mp4';
		} elseif ($mime === 'application/pdf') {
			$ext = 'pdf';
		}

		return $prefix . '_' . date('Ymd_His') . '.' . $ext;
	}

	private function detectExtensionFromMime(string $mime): string
	{
		$mime = strtolower(trim($mime));
		if ($mime === '') {
			return '';
		}

		$map = [
			'application/pdf' => 'pdf',
			'application/msword' => 'doc',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/vnd.ms-excel' => 'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/zip' => 'zip',
			'application/vnd.rar' => 'rar',
			'text/plain' => 'txt',
			'text/csv' => 'csv',
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'video/mp4' => 'mp4',
			'audio/mpeg' => 'mp3',
			'audio/mp3' => 'mp3',
			'audio/ogg' => 'ogg',
			'audio/wav' => 'wav',
			'audio/x-wav' => 'wav',
			'audio/webm' => 'webm',
			'audio/mp4' => 'm4a',
		];
		return $map[$mime] ?? '';
	}

	private function isGenericAttachmentName(string $name): bool
	{
		$name = strtolower(trim($name));
		if ($name === '') {
			return false;
		}
		return preg_match('/^attachmente?(?:\.[a-z0-9]{1,10})?$/i', $name) === 1;
	}

	private function buildFreshchatPreferredFileName(string $uploadName, string $remoteName, string $contentType = ''): string
	{
		$first = trim($uploadName);
		$second = trim($remoteName);
		$candidate = $first !== '' ? $first : $second;
		if ($this->isGenericAttachmentName($candidate) && $second !== '' && !$this->isGenericAttachmentName($second)) {
			$candidate = $second;
		}
		if ($candidate === '' || $this->isGenericAttachmentName($candidate)) {
			$candidate = 'documento_' . date('Ymd_His');
		}

		$candidate = basename($candidate);
		$translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate);
		if (is_string($translit) && trim($translit) !== '') {
			$candidate = $translit;
		}
		$candidate = preg_replace('/[^A-Za-z0-9._ -]/', '_', $candidate) ?? $candidate;
		$candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
		$candidate = trim($candidate, " ._-");

		$base = pathinfo($candidate, PATHINFO_FILENAME);
		$ext = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));
		if ($base === '') {
			$base = 'documento_' . date('Ymd_His');
		}
		if ($ext === '') {
			$ext = strtolower((string) pathinfo($uploadName, PATHINFO_EXTENSION));
		}
		if ($ext === '') {
			$ext = strtolower((string) pathinfo($remoteName, PATHINFO_EXTENSION));
		}
		if ($ext === '') {
			$ext = $this->detectExtensionFromMime($contentType);
		}
		if ($ext === '') {
			$ext = 'bin';
		}
		$ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';

		$base = mb_substr($base, 0, 90);
		return $base . '.' . $ext;
	}

	private function parseIniSizeToBytes(string $value): int
	{
		$value = trim($value);
		if ($value === '') {
			return 0;
		}
		$number = (float) $value;
		$unit = strtolower(substr($value, -1));
		switch ($unit) {
			case 'g':
				$number *= 1024;
				// no break
			case 'm':
				$number *= 1024;
				// no break
			case 'k':
				$number *= 1024;
		}
		return (int) round($number);
	}

	private function formatBytesLabel(int $bytes): string
	{
		if ($bytes <= 0) {
			return '0 B';
		}
		$units = ['B', 'KB', 'MB', 'GB'];
		$idx = 0;
		$size = (float) $bytes;
		while ($size >= 1024 && $idx < count($units) - 1) {
			$size /= 1024;
			$idx++;
		}
		return number_format($size, $idx === 0 ? 0 : 1) . ' ' . $units[$idx];
	}

	private function extractBase64AudioUpload(array $post): ?array
	{
		$raw = trim((string) ($post['audio_record_b64'] ?? ''));
		if ($raw === '') {
			return null;
		}

		$mime = 'audio/webm';
		$payload = $raw;
		if (preg_match('#^data:([^;]+);base64,(.+)$#', $raw, $matches)) {
			$mime = strtolower(trim((string) ($matches[1] ?? 'audio/webm')));
			$payload = (string) ($matches[2] ?? '');
		}

		$binary = base64_decode($payload, true);
		if ($binary === false || $binary === '') {
			return null;
		}

		$tmpPath = tempnam(sys_get_temp_dir(), 'cci-audio-');
		if ($tmpPath === false || file_put_contents($tmpPath, $binary) === false) {
			return null;
		}

		$extMap = [
			'audio/mpeg' => 'mp3',
			'audio/mp3' => 'mp3',
			'audio/wav' => 'wav',
			'audio/x-wav' => 'wav',
			'audio/ogg' => 'ogg',
			'audio/webm' => 'webm',
			'audio/mp4' => 'm4a',
			'audio/aac' => 'aac',
		];
		$ext = $extMap[$mime] ?? 'webm';

		$size = filesize($tmpPath);
		if ($size === false || $size <= 0) {
			@unlink($tmpPath);
			return null;
		}

		return [
			'name' => 'audio_note_' . date('Ymd_His') . '.' . $ext,
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $tmpPath,
			'size' => (int) $size,
			'_generated_tmp' => true,
			'_mime' => $mime,
		];
	}

	public function sendConversationReply(int $id): void
	{
		Auth::requireAuth();
		$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
		if (
			strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
			&& $contentLength > 0
			&& empty($_POST)
			&& empty($_FILES)
		) {
			$postMax = $this->parseIniSizeToBytes((string) ini_get('post_max_size'));
			$uploadMax = $this->parseIniSizeToBytes((string) ini_get('upload_max_filesize'));
			$limit = $postMax > 0 && $uploadMax > 0 ? min($postMax, $uploadMax) : max($postMax, $uploadMax);
			$limitText = $limit > 0 ? $this->formatBytesLabel($limit) : ((string) ini_get('post_max_size'));
			set_flash('error', 'El archivo es demasiado grande para el servidor. Límite actual: ' . $limitText . '.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$text = trim((string) ($_POST['reply_text'] ?? ''));
		$attachments = is_array($_FILES['attachments'] ?? null) ? $_FILES['attachments'] : [];
		$audioRecord = is_array($_FILES['audio_record'] ?? null) ? $_FILES['audio_record'] : [];
		$uploads = $this->collectReplyUploads($attachments, $audioRecord);
		$cleanupGeneratedTmp = [];
		$base64AudioUpload = $this->extractBase64AudioUpload($_POST);
		if ($base64AudioUpload !== null) {
			$uploads[] = $base64AudioUpload;
			if (!empty($base64AudioUpload['_generated_tmp']) && !empty($base64AudioUpload['tmp_name'])) {
				$cleanupGeneratedTmp[] = (string) $base64AudioUpload['tmp_name'];
			}
		}

		$hasAnyUpload = false;
		foreach ($uploads as $upload) {
			if ($this->isUploadedEntryPresent($upload)) {
				$hasAnyUpload = true;
				break;
			}
		}

		if ($text === '' && !$hasAnyUpload) {
			set_flash('error', 'Escribe un mensaje o adjunta archivos.');
			foreach ($cleanupGeneratedTmp as $tmpFileToCleanup) {
				if ($tmpFileToCleanup !== '' && is_file($tmpFileToCleanup)) {
					@unlink($tmpFileToCleanup);
				}
			}
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$conversationStmt = $db->prepare('SELECT canal FROM bot_conversaciones WHERE id = :id LIMIT 1');
		$conversationStmt->execute(['id' => $id]);
		if ((string) ($conversationStmt->fetchColumn() ?: '') === 'freshchat') {
			$hasAttachments = $hasAnyUpload;
			if ($text === '' && !$hasAttachments) {
				set_flash('error', 'Escribe un mensaje o adjunta un archivo para responder por Freshchat.');
				redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
			}

			$referenceStmt = $db->prepare('SELECT external_conversation_id
				FROM cci_conversacion_refs
				WHERE provider_code = "freshchat" AND conversacion_id = :id
				LIMIT 1');
			$referenceStmt->execute(['id' => $id]);
			$externalConversationId = trim((string) ($referenceStmt->fetchColumn() ?: ''));
			if ($externalConversationId === '') {
				set_flash('error', 'La conversación no tiene un identificador externo de Freshchat.');
				redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
			}

			$freshchat = new FreshchatService();
			$conversation = $freshchat->getConversation($externalConversationId);
			if (!($conversation['ok'] ?? false)) {
				set_flash('error', 'No se pudo consultar la conversación en Freshchat: ' . (string) ($conversation['error'] ?? 'error desconocido'));
				redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
			}

			$agentId = trim((string) ($conversation['data']['assigned_agent_id'] ?? ''));
			if ($agentId === '') {
				$currentUserEmail = trim((string) (Auth::user()['email'] ?? ''));
				$matchedAgent = $freshchat->findAgentByEmail($currentUserEmail);
				$agentId = trim((string) ($matchedAgent['id'] ?? ''));
			}
			if ($agentId === '') {
				$freshchatConfig = $freshchat->getConfig();
				$configuredAgent = $freshchat->findAgentByEmail((string) ($freshchatConfig['agent_email'] ?? ''));
				$agentId = trim((string) ($configuredAgent['id'] ?? ''));
				$configuredAgentId = trim((string) ($freshchatConfig['agent_id'] ?? ''));
				if (preg_match('/^[a-f0-9-]{36}$/i', $configuredAgentId)) {
					$agentId = $agentId !== '' ? $agentId : $configuredAgentId;
				}
			}
			if ($agentId === '') {
				set_flash('error', 'No se encontró un agente Freshchat válido. Asigna la conversación allí o configura FRESHCHAT_AGENT_EMAIL con el correo de un agente activo.');
				redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
			}

			$messages = $freshchat->getConversationMessages($externalConversationId);
			$userId = '';
			foreach ($messages['data']['messages'] ?? [] as $freshchatMessage) {
				if (!is_array($freshchatMessage) || strtolower((string) ($freshchatMessage['actor_type'] ?? '')) !== 'user') {
					continue;
				}
				$userId = trim((string) ($freshchatMessage['user_id'] ?? ($freshchatMessage['actor_id'] ?? '')));
				if ($userId !== '') {
					break;
				}
			}
			if ($userId === '') {
				set_flash('error', 'No se pudo identificar al contacto de esta conversación en Freshchat.');
				redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
			}

			$freshchatErrors = [];
			$freshchatSentCount = 0;
			$freshchatExpectedCount = $text !== '' ? 1 : 0;
			$freshchatAttachmentExpectedCount = 0;
			$freshchatAttachmentSentCount = 0;

			if ($text !== '') {
				$send = $freshchat->sendConversationMessage($externalConversationId, $agentId, $userId, mb_substr($text, 0, 10000));
				if ($send['ok'] ?? false) {
					$validation = $this->validateFreshchatSendResponse($send, false);
					if (!($validation['ok'] ?? false)) {
						$freshchatErrors[] = 'texto: ' . (string) ($validation['reason'] ?? 'respuesta no confirmada por Freshchat');
						error_log('CCI Freshchat texto sin confirmacion: ' . json_encode([
							'conversation_id' => $id,
							'external_conversation_id' => $externalConversationId,
							'response_data' => $send['data'] ?? null,
						], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
					} else {
						$messageId = $this->insertBotMessage($db, $id, $text, true, (string) ($send['data']['created_time'] ?? date('c')), 'texto');
						$externalMessageId = trim((string) ($validation['message_id'] ?? ''));
						if ($messageId > 0 && $externalMessageId !== '') {
							$this->saveMessageRef($db, 'freshchat', $externalMessageId, $id, $messageId, 'out');
						}
						AuditLogger::log('CREATE', 'bot_mensajes', $messageId > 0 ? $messageId : null, null, [
							'conversacion_id' => $id,
							'channel' => 'freshchat',
							'external_message_id' => $externalMessageId,
						]);
						$freshchatSentCount++;
					}
				} else {
					$freshchatErrors[] = (string) ($send['error'] ?? 'error desconocido al enviar el texto');
				}
			}

			if ($hasAttachments) {
				foreach ($uploads as $uploadFile) {
					$fileName = trim((string) ($uploadFile['name'] ?? ''));
					$error = (int) ($uploadFile['error'] ?? 1);
					$tmpPath = (string) ($uploadFile['tmp_name'] ?? '');
					if ($error === UPLOAD_ERR_NO_FILE) {
						continue;
					}
					if ($fileName === '' && $error === UPLOAD_ERR_OK && $tmpPath !== '' && is_file($tmpPath)) {
						$fileName = $this->buildUploadFallbackName($tmpPath, 'audio');
					}
					$freshchatExpectedCount++;
					$freshchatAttachmentExpectedCount++;
					if ($error !== UPLOAD_ERR_OK || empty($tmpPath) || !is_file($tmpPath)) {
						$label = $fileName !== '' ? $fileName : 'archivo sin nombre';
						$freshchatErrors[] = e($label) . ': no se pudo procesar el archivo subido.';
						continue;
					}

					$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
					$mediaKind = $this->getMediaType($ext); // image | video | audio | document
					$localDetectedMime = function_exists('mime_content_type') ? (string) (mime_content_type($tmpPath) ?: '') : '';
					$uploadFileName = $this->buildFreshchatPreferredFileName($fileName, $fileName, $localDetectedMime);
					$uploadKind = $mediaKind === 'image' ? 'image' : 'file';
					$upload = $uploadKind === 'image'
						? $freshchat->uploadImage($tmpPath, $uploadFileName)
						: $freshchat->uploadFile($tmpPath, $uploadFileName);
					if (!($upload['ok'] ?? false)) {
						$freshchatErrors[] = e($fileName) . ': ' . (string) ($upload['error'] ?? 'no se pudo subir a Freshchat.');
						continue;
					}

					$uploadData = is_array($upload['data'] ?? null) ? $upload['data'] : [];
					$fileSecurityStatus = strtoupper(trim((string) ($uploadData['file_security_status'] ?? '')));
					if ($fileSecurityStatus === 'MALWARE_FILE' || $fileSecurityStatus === 'AV_FAILURE') {
						$freshchatErrors[] = e($fileName) . ': Freshchat bloqueó el archivo por seguridad (' . $fileSecurityStatus . ').';
						continue;
					}
					$sendWhilePending = false;
					if ($fileSecurityStatus === 'AV_PENDING') {
						$sendWhilePending = true;
					}
					if ($fileSecurityStatus !== '' && !in_array($fileSecurityStatus, ['SAFE_FILE', 'AV_PENDING', 'TOO_LARGE_FOR_MALWARE_CHECK'], true)) {
						$freshchatErrors[] = e($fileName) . ': Freshchat devolvió estado de seguridad no soportado (' . $fileSecurityStatus . ').';
						continue;
					}
					$fileUrl = $this->resolveFreshchatUploadedUrl($uploadData);
					$fileHash = $this->resolveFreshchatUploadedHash($uploadData);
					$fileRemoteName = $this->resolveFreshchatUploadedName($uploadData, $uploadFileName);
					$fileContentType = trim((string) ($uploadData['file_content_type'] ?? $uploadData['content_type'] ?? ''));
					$payloadFileName = $this->buildFreshchatPreferredFileName($uploadFileName, $fileRemoteName, $fileContentType);
					$fileExtensionType = strtolower(trim((string) ($uploadData['file_extension_type'] ?? pathinfo($payloadFileName, PATHINFO_EXTENSION) ?? pathinfo($fileName, PATHINFO_EXTENSION))));
					if ($fileUrl === '' && $fileHash === '') {
						$knownKeys = implode(', ', array_keys($uploadData));
						$freshchatErrors[] = e($fileName) . ': Freshchat no devolvió URL/hash del archivo subido. Claves detectadas: ' . ($knownKeys !== '' ? $knownKeys : 'ninguna');
						continue;
					}

					$attempts = [];
					$filePartsByHash = [];
					if ($fileHash !== '') {
						$canonicalPart = ['fileHash' => $fileHash, 'fileSource' => 'FRESHCHAT'];
						if ($payloadFileName !== '') {
							$canonicalPart['name'] = $payloadFileName;
							$canonicalPart['fileName'] = $payloadFileName;
							$canonicalPart['filename'] = $payloadFileName;
						}
						if ($fileContentType !== '') {
							$canonicalPart['contentType'] = $fileContentType;
						}
						if ($fileExtensionType !== '') {
							$canonicalPart['file_extension'] = $fileExtensionType;
							$canonicalPart['fileExtension'] = $fileExtensionType;
						}
						$filePartsByHash[] = $canonicalPart;
						$filePartsByHash[] = ['file_hash' => $fileHash, 'file_source' => 'FRESHCHAT', 'name' => $payloadFileName];
					}
					$filePartsByUrl = [];
					if ($fileUrl !== '') {
						$filePartsByUrl[] = ['url' => $fileUrl, 'name' => $payloadFileName, 'fileSource' => 'FRESHCHAT'];
						$filePartsByUrl[] = ['url' => $fileUrl, 'name' => $payloadFileName];
					}
					if ($mediaKind === 'image') {
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'image', 'part' => ['url' => (string) ($part['url'] ?? '')]];
						}
					} elseif ($mediaKind === 'video') {
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'video', 'part' => ['url' => (string) ($part['url'] ?? '')]];
						}
						foreach ($filePartsByHash as $part) {
							$attempts[] = ['type' => 'file', 'part' => $part];
						}
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'file', 'part' => $part];
						}
					} elseif ($mediaKind === 'audio') {
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'audio', 'part' => ['url' => (string) ($part['url'] ?? '')]];
						}
						foreach ($filePartsByHash as $part) {
							$attempts[] = ['type' => 'file', 'part' => $part];
						}
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'file', 'part' => $part];
						}
					} else {
						foreach ($filePartsByHash as $part) {
							$attempts[] = ['type' => 'document', 'part' => $part];
						}
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'document', 'part' => $part];
						}
						foreach ($filePartsByHash as $part) {
							$attempts[] = ['type' => 'file', 'part' => $part];
						}
						foreach ($filePartsByUrl as $part) {
							$attempts[] = ['type' => 'file', 'part' => $part];
						}
					}
					$attempts = array_values(array_filter($attempts, static fn($a) => !empty($a['type']) && !empty($a['part'])));

					if ($fileSecurityStatus === 'SAFE_FILE' || $fileSecurityStatus === 'TOO_LARGE_FOR_MALWARE_CHECK' || $sendWhilePending) {
						error_log('CCI Freshchat archivo listo para envio: ' . json_encode([
							'conversation_id' => $id,
							'external_conversation_id' => $externalConversationId,
							'file_name' => $fileName,
							'file_hash' => $fileHash,
							'file_security_status' => $fileSecurityStatus,
						], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
					}

					$send = null;
					$confirmedExternalMessageId = '';
					$attemptErrors = [];
					error_log('CCI Freshchat envio adjunto payload: ' . json_encode([
						'conversation_id' => $id,
						'external_conversation_id' => $externalConversationId,
						'upload_name' => $fileName,
						'upload_file_name' => $uploadFileName,
						'remote_name' => $fileRemoteName,
						'payload_file_name' => $payloadFileName,
						'file_hash' => $fileHash,
						'file_url' => $fileUrl,
						'security_status' => $fileSecurityStatus,
						'content_type' => $fileContentType,
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
					$maxRounds = $sendWhilePending ? 2 : 1;
					for ($round = 1; $round <= $maxRounds; $round++) {
						foreach ($attempts as $attempt) {
							$sendTry = $freshchat->sendConversationMedia(
								$externalConversationId,
								$agentId,
								$userId,
								(string) ($attempt['type'] ?? 'file'),
								(array) ($attempt['part'] ?? [])
							);
							if ($sendTry['ok'] ?? false) {
								$validation = $this->validateFreshchatSendResponse($sendTry, true);
								if ($validation['ok'] ?? false) {
									$send = $sendTry;
									$confirmedExternalMessageId = trim((string) ($validation['message_id'] ?? ''));
									break 2;
								}
								$attemptErrors[] = 'ronda ' . $round . ' ' . (string) ($attempt['type'] ?? 'file') . ': respuesta no confirmada (' . (string) ($validation['reason'] ?? 'sin detalle') . ')';
								error_log('CCI Freshchat media sin confirmacion: ' . json_encode([
									'conversation_id' => $id,
									'external_conversation_id' => $externalConversationId,
									'file_name' => $fileName,
									'attempt_round' => $round,
									'attempt_type' => (string) ($attempt['type'] ?? 'file'),
									'attempt_part' => (array) ($attempt['part'] ?? []),
									'response_data' => $sendTry['data'] ?? null,
								], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
								continue;
							}
							$attemptErrors[] = 'ronda ' . $round . ' ' . (string) ($attempt['type'] ?? 'file') . ': ' . (string) ($sendTry['error'] ?? 'rechazado');
						}
						if ($round < $maxRounds) {
							usleep(400000);
						}
					}
					if ($send === null) {
						if ($sendWhilePending) {
							$attemptErrors[] = 'archivo aún en escaneo AV_PENDING';
						}
						$freshchatErrors[] = e($fileName) . ': no se pudo enviar adjunto (' . implode(' | ', $attemptErrors) . ')';
						continue;
					}

					// Guardar copia local para mostrarla en el historial sin depender de la URL de Freshchat.
					$saved = $this->saveConversationAttachment($tmpPath, $fileName, (int) $id, Auth::id());
					$localName = $saved['name'] ?? $fileName;
					$messageId = $this->insertBotMessage($db, $id, $localName, true, (string) ($send['data']['created_time'] ?? date('c')), 'archivo');
					$externalMessageId = $confirmedExternalMessageId !== ''
						? $confirmedExternalMessageId
						: trim((string) ($send['data']['id'] ?? ''));
					if ($messageId > 0 && $externalMessageId !== '') {
						$this->saveMessageRef($db, 'freshchat', $externalMessageId, $id, $messageId, 'out');
					}
					AuditLogger::log('CREATE', 'bot_mensajes', $messageId > 0 ? $messageId : null, null, [
						'conversacion_id' => $id,
						'channel' => 'freshchat',
						'archivo' => $localName,
					]);
					$freshchatSentCount++;
					$freshchatAttachmentSentCount++;
				}
			}

			if ($freshchatExpectedCount > 0 && $freshchatSentCount === 0) {
				if (empty($freshchatErrors)) {
					$freshchatErrors[] = 'Freshchat no confirmó el envío del mensaje.';
				}
				$firstError = $freshchatErrors[0];
				if (stripos($firstError, 'WhatsApp 24 hours window has crossed') !== false) {
					$firstError = 'La ventana de respuesta de WhatsApp está cerrada. Solo puedes enviar texto libre durante las 24 horas posteriores al último mensaje del contacto. Para reabrir el chat debes enviar una plantilla aprobada desde Freshchat o esperar que el contacto escriba nuevamente.';
				}
				set_flash('error', $firstError);
			} elseif ($freshchatExpectedCount > $freshchatSentCount) {
				$prefix = 'Se envió parcialmente por Freshchat.';
				if ($freshchatAttachmentExpectedCount > 0 && $freshchatAttachmentSentCount === 0) {
					$prefix = 'Freshchat no confirmó entrega de adjuntos/audio al cliente.';
				}
				set_flash('warning', $prefix . (!empty($freshchatErrors) ? ' Errores: ' . implode('; ', $freshchatErrors) : ''));
			} elseif (!empty($freshchatErrors)) {
				set_flash('warning', 'Se envió parcialmente. Errores: ' . implode('; ', $freshchatErrors));
			} else {
				set_flash('success', 'Mensaje enviado por Freshchat.');
			}
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}
		$phone = $this->resolveConversationPhone($db, (int) $id);
		if ($phone === '') {
			set_flash('error', 'No se encontró un número para la conversación seleccionada.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		// Validar y procesar archivos
		$attachmentPaths = [];
		$uploadErrorMessages = [];
		$maxFileSize = 100 * 1024 * 1024; // 100MB
		if (!empty($uploads)) {
			foreach ($uploads as $uploadFile) {
				$error = (int) ($uploadFile['error'] ?? 1);
				$tmpPath = (string) ($uploadFile['tmp_name'] ?? '');
				$fileName = trim((string) ($uploadFile['name'] ?? ''));
				$fileSize = (int) ($uploadFile['size'] ?? 0);

				if ($error === UPLOAD_ERR_NO_FILE) {
					continue; // sin archivo, ignorar
				}

				if ($fileName === '' && $error === UPLOAD_ERR_OK && $tmpPath !== '' && is_file($tmpPath)) {
					$fileName = $this->buildUploadFallbackName($tmpPath, 'audio');
				}

				if ($error !== UPLOAD_ERR_OK) {
					$phpUploadErrors = [
						UPLOAD_ERR_INI_SIZE   => 'excede upload_max_filesize del servidor',
						UPLOAD_ERR_FORM_SIZE  => 'excede MAX_FILE_SIZE del formulario',
						UPLOAD_ERR_PARTIAL    => 'se subió parcialmente',
						UPLOAD_ERR_NO_TMP_DIR => 'falta carpeta temporal',
						UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir en disco',
						UPLOAD_ERR_EXTENSION  => 'bloqueado por extensión PHP',
					];
					$errMsg = $phpUploadErrors[$error] ?? "error $error";
					$label = $fileName !== '' ? $fileName : 'archivo sin nombre';
					$uploadErrorMessages[] = e($label) . ": $errMsg";
					error_log("CCI upload error $error for $fileName");
					continue;
				}

				if ($fileSize > $maxFileSize) {
					$label = $fileName !== '' ? $fileName : 'archivo sin nombre';
					$uploadErrorMessages[] = e($label) . ': excede 100MB';
					continue;
				}

				if (empty($tmpPath) || !is_file($tmpPath)) {
					$label = $fileName !== '' ? $fileName : 'archivo sin nombre';
					$uploadErrorMessages[] = e($label) . ': archivo temporal no encontrado';
					error_log("Temporary file not found: $tmpPath");
					continue;
				}

				$saved = $this->saveConversationAttachment($tmpPath, $fileName, (int) $id, Auth::id());
				if ($saved) {
					$attachmentPaths[] = [
						'path' => $saved['path'],
						'name' => $saved['name'],
						'size' => $fileSize,
					];
				} else {
					$label = $fileName !== '' ? $fileName : 'archivo sin nombre';
					$uploadErrorMessages[] = e($label) . ': no se pudo guardar en servidor';
				}
			}
		}

		// Si hubo errores de upload antes de procesar, reportarlos de inmediato
		if (!empty($uploadErrorMessages) && empty($attachmentPaths) && $text === '') {
			foreach ($cleanupGeneratedTmp as $tmpFileToCleanup) {
				if ($tmpFileToCleanup !== '' && is_file($tmpFileToCleanup)) {
					@unlink($tmpFileToCleanup);
				}
			}
			set_flash('error', 'Error al subir archivo(s): ' . implode('; ', $uploadErrorMessages));
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$service = new WhatchimpService();
		$send = [];

		// Enviar texto si existe
		if ($text !== '') {
			$send = $service->sendTextMessage($phone, $text, [
				'conversation_id' => (int) $id,
				'user_id' => Auth::id(),
			]);
			if ($send['ok'] ?? false) {
				$messageId = $this->insertBotMessage($db, (int) $id, $text, true, date('Y-m-d H:i:s'), 'texto');
				$externalId = trim((string) ($send['message_id'] ?? ''));
				if ($externalId !== '' && $messageId > 0) {
					$this->saveMessageRef($db, 'whatchimp', $externalId, (int) $id, $messageId, 'out');
				}
				AuditLogger::log('CREATE', 'bot_mensajes', $messageId > 0 ? $messageId : null, null, [
					'conversacion_id' => (int) $id,
					'es_bot' => 1,
					'channel' => 'whatchimp',
				]);
			}
		}

		// Enviar archivos si existen
		$fileErrors = [];
		if (!empty($attachmentPaths)) {
			foreach ($attachmentPaths as $file) {
				$path = (string) ($file['path'] ?? '');
				$name = (string) ($file['name'] ?? '');
				$displayName = (string) preg_replace('/^\d{14}_[a-f0-9]{8}_/', '', $name) ?: $name;

				$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				$mediaType = $this->getMediaType($ext);

				// Guardar en DB siempre (así aparece en el chat aunque WhatsApp falle)
				$msgId = $this->insertBotMessage($db, (int) $id, $name, true, date('Y-m-d H:i:s'), 'archivo');

				// Intentar enviar a WhatsApp
				$sendMedia = $service->sendMediaMessage($phone, $path, $mediaType, [
					'conversation_id' => (int) $id,
					'user_id' => Auth::id(),
					'file_name' => $name,
				]);

				if ($sendMedia['ok'] ?? false) {
					if ($msgId > 0) {
						$extId = trim((string) ($sendMedia['message_id'] ?? ''));
						if ($extId !== '') {
							$this->saveMessageRef($db, 'whatchimp', $extId, (int) $id, $msgId, 'out');
						}
					}
					AuditLogger::log('CREATE', 'bot_mensajes', $msgId > 0 ? $msgId : null, null, [
						'archivo' => $name,
						'size' => $file['size'],
						'path' => $path,
					]);
				} else {
					// El archivo quedó guardado localmente, pero no se envió a WhatsApp
					$watiError = (string) ($sendMedia['error'] ?? ('HTTP ' . ($sendMedia['http_code'] ?? '?')));
					$fileErrors[] = $displayName . ': ' . $watiError;
					error_log("CCI sendMedia failed for $name: $watiError");
				}
			}
		}

		// Mensaje de feedback
		$allErrors = array_merge($uploadErrorMessages, $fileErrors);
		if (!($send['ok'] ?? false) && $text !== '') {
			set_flash('error', 'Error al enviar mensaje: ' . (string) ($send['error'] ?? 'desconocido'));
		} elseif (!empty($allErrors)) {
			$feedbackMsg = '';
			$sentOk = count($attachmentPaths) - count($fileErrors);
			if ($text !== '' && ($send['ok'] ?? false)) {
				$feedbackMsg .= 'Mensaje enviado. ';
			}
			if ($sentOk > 0) {
				$feedbackMsg .= "$sentOk archivo(s) enviado(s) a WhatsApp. ";
			}
			$feedbackMsg .= 'Error: ' . implode('; ', $allErrors);
			set_flash('warning', rtrim($feedbackMsg));
		} else {
			$feedbackMsg = 'Mensaje enviado';
			if (count($attachmentPaths) > 0) {
				$feedbackMsg .= ' (' . count($attachmentPaths) . ' archivo(s))';
			}
			set_flash('success', $feedbackMsg);
		}

		redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
	}

	private function resolveFreshchatUploadedUrl(array $data): string
	{
		$candidates = [
			(string) ($data['url'] ?? ''),
			(string) ($data['file_url'] ?? ''),
			(string) ($data['fileUrl'] ?? ''),
			(string) ($data['location'] ?? ''),
			(string) ($data['download_url'] ?? ''),
			(string) ($data['downloadUrl'] ?? ''),
		];

		if (isset($data['file']) && is_array($data['file'])) {
			$candidates[] = (string) ($data['file']['url'] ?? '');
			$candidates[] = (string) ($data['file']['file_url'] ?? '');
			$candidates[] = (string) ($data['file']['fileUrl'] ?? '');
		}
		if (isset($data['data']) && is_array($data['data'])) {
			$candidates[] = (string) ($data['data']['url'] ?? '');
			$candidates[] = (string) ($data['data']['file_url'] ?? '');
			$candidates[] = (string) ($data['data']['fileUrl'] ?? '');
		}

		foreach ($candidates as $candidate) {
			$candidate = trim($candidate);
			if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_URL)) {
				return $candidate;
			}
		}

		// Fallback: buscar cualquier valor URL en profundidad (1 nivel)
		foreach ($data as $value) {
			if (is_string($value)) {
				$raw = trim($value);
				if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_URL)) {
					return $raw;
				}
			}
			if (is_array($value)) {
				foreach ($value as $nested) {
					if (!is_string($nested)) {
						continue;
					}
					$rawNested = trim($nested);
					if ($rawNested !== '' && filter_var($rawNested, FILTER_VALIDATE_URL)) {
						return $rawNested;
					}
				}
			}
		}

		return '';
	}

	private function resolveFreshchatUploadedName(array $data, string $fallback): string
	{
		$candidates = [
			(string) ($data['file_name'] ?? ''),
			(string) ($data['filename'] ?? ''),
			(string) ($data['name'] ?? ''),
			(string) ($data['fileName'] ?? ''),
			(isset($data['file']) && is_array($data['file'])) ? (string) ($data['file']['name'] ?? '') : '',
			(isset($data['data']) && is_array($data['data'])) ? (string) ($data['data']['name'] ?? '') : '',
		];

		foreach ($candidates as $candidate) {
			$candidate = trim($candidate);
			if ($candidate !== '') {
				if (preg_match('/^attachment(?:\.[a-z0-9]{1,10})?$/i', $candidate) === 1) {
					continue;
				}
				return $candidate;
			}
		}

		return $fallback;
	}

	private function resolveFreshchatUploadedHash(array $data): string
	{
		$candidates = [
			(string) ($data['file_hash'] ?? ''),
			(string) ($data['fileHash'] ?? ''),
			(string) ($data['hash'] ?? ''),
			(isset($data['file']) && is_array($data['file'])) ? (string) ($data['file']['file_hash'] ?? ($data['file']['hash'] ?? '')) : '',
			(isset($data['data']) && is_array($data['data'])) ? (string) ($data['data']['file_hash'] ?? ($data['data']['hash'] ?? '')) : '',
		];

		foreach ($candidates as $candidate) {
			$candidate = trim($candidate);
			if ($candidate !== '') {
				return $candidate;
			}
		}

		return '';
	}

	private function getMediaType(string $ext): string
	{
		$ext = strtolower($ext);
		$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
		$videoExts = ['mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv'];
		$audioExts = ['mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg', 'webm', 'opus'];

		if (in_array($ext, $imageExts)) return 'image';
		if (in_array($ext, $videoExts)) return 'video';
		if (in_array($ext, $audioExts)) return 'audio';
		return 'document';
	}


	private function saveConversationAttachment(string $tmpPath, string $filename, int $convId, int $userId): ?array
	{
		if (!is_file($tmpPath)) return null;
		$uploadDir = STORAGE_PATH . '/uploads/cci-attachments';
		if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
		$originalBaseName = basename($filename);
		$sanitized = preg_replace('/[\\\/:*?"<>|\x00-\x1F\x7F]/u', '_', $originalBaseName);
		$sanitized = trim((string) $sanitized);
		if ($sanitized === '' || $sanitized === '.' || $sanitized === '..') {
			$sanitized = 'archivo_adjunto';
		}
		$unique = date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8) . '_' . $sanitized;
		$destPath = $uploadDir . '/' . $unique;
		if (!move_uploaded_file($tmpPath, $destPath)) return null;
		chmod($destPath, 0644);

		// Copiar también al directorio público para que sea accesible desde web
		$publicDir = PUBLIC_PATH . '/cci-attachments';
		if (!is_dir($publicDir)) @mkdir($publicDir, 0755, true);
		@copy($destPath, $publicDir . '/' . $unique);
		@chmod($publicDir . '/' . $unique, 0644);

		return [
			'path' => $destPath,
			'name' => $unique,
		];
	}
	public function whatsAppWebhook(): void
	{
		$this->whatchimpWebhook();
	}

	public function whatchimpWebhook(): void
	{
		header('Content-Type: application/json; charset=UTF-8');
		$db = $this->db();
		$this->ensureCciTables($db);

		$service = new WhatchimpService();
		if (!$service->verifyTokenFromRequest()) {
			http_response_code(403);
			echo json_encode(['ok' => false, 'error' => 'Token de verificación inválido.'], JSON_UNESCAPED_UNICODE);
			return;
		}

		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
			echo json_encode([
				'ok' => true,
				'message' => 'Webhook WhatsApp activo.',
				'challenge' => (string) ($_GET['challenge'] ?? ''),
			], JSON_UNESCAPED_UNICODE);
			return;
		}

		$raw = (string) file_get_contents('php://input');
		$payload = json_decode($raw, true);
		if (!is_array($payload)) {
			$payload = [];
		}

		try {
			$logStmt = $db->prepare('INSERT INTO cci_webhook_logs (provider_code, event_type, payload, status, created_at)
				VALUES ("whatchimp", :event_type, :payload, "ok", NOW())');
			$logStmt->execute([
				'event_type' => (string) ($payload['event'] ?? 'message'),
				'payload' => $raw,
			]);
		} catch (Throwable $e) {
			// No bloquear por logging.
		}

		$messages = $this->normalizeWhatchimpMessages($payload);
		$result = $this->processWhatchimpMessages($db, $messages, 'webhook');

		echo json_encode([
			'ok' => true,
			'processed' => $result['created'],
			'skipped' => $result['skipped'],
		], JSON_UNESCAPED_UNICODE);
	}

	public function syncWhatsApp(): void
	{
		$this->syncWhatchimp();
	}

	private function extractFreshchatMessage(string $messageParts): array
	{
		$parts = json_decode($messageParts, true);
		if (!is_array($parts)) {
			$text = trim($messageParts);
			return ['text' => $text, 'tipo' => preg_match('#^https?://#i', $text) ? 'archivo' : 'texto', 'media_name' => ''];
		}

		$texts = [];
		$mediaUrls = [];
		$mediaNames = [];
		foreach ($parts as $part) {
			if (!is_array($part)) {
				continue;
			}
			$text = trim((string) ($part['text']['content'] ?? ''));
			if ($text !== '') {
				$texts[] = $text;
			}
			foreach (['image', 'video', 'file', 'audio', 'document'] as $mediaType) {
				$name = trim((string) ($part[$mediaType]['name'] ?? ''));
				if ($name !== '' && !$this->isGenericAttachmentName($name)) {
					$mediaNames[] = $name;
				}
				$url = trim((string) ($part[$mediaType]['url'] ?? ''));
				if ($url !== '') {
					$mediaUrls[] = $url;
					continue;
				}
				if ($name !== '') {
					$mediaUrls[] = $name;
				}
			}
		}
		if ($mediaUrls !== []) {
			return [
				'text' => implode("\n", $mediaUrls),
				'tipo' => 'archivo',
				'media_name' => $mediaNames[0] ?? '',
			];
		}
		return ['text' => implode("\n", $texts), 'tipo' => 'texto', 'media_name' => ''];
	}

	private function syncFreshchatConversationLive(PDO $db, int $localConversationId, string $externalConversationId): array
	{
		if ($localConversationId <= 0 || trim($externalConversationId) === '') {
			return ['ok' => false, 'created' => 0, 'skipped' => 0];
		}

		$service = new FreshchatService();
		$rowsById = [];
		$anchorStmt = $db->prepare('SELECT MAX(COALESCE(fecha, created_at)) FROM bot_mensajes WHERE conversacion_id = :id');
		$anchorStmt->execute(['id' => $localConversationId]);
		$lastLocalTs = strtotime((string) ($anchorStmt->fetchColumn() ?: '')) ?: 0;
		$fromTimeUtc = $lastLocalTs > 0 ? gmdate('Y-m-d\TH:i:s\Z', max(0, $lastLocalTs - 900)) : '';

		$passes = [];
		if ($fromTimeUtc !== '') {
			$passes[] = ['from_time' => $fromTimeUtc, 'max_pages' => 8];
		}
		$passes[] = ['from_time' => '', 'max_pages' => 6];

		foreach ($passes as $pass) {
			$fromTime = (string) ($pass['from_time'] ?? '');
			$maxPages = (int) ($pass['max_pages'] ?? 6);
			$passRows = [];
			for ($page = 1; $page <= $maxPages; $page++) {
				$response = $service->getConversationMessages($externalConversationId, $page, 50, $fromTime);
				if (!($response['ok'] ?? false)) {
					if ($fromTime !== '') {
						error_log('CCI Freshchat live sync reintento sin from_time: ' . json_encode([
							'local_conversation_id' => $localConversationId,
							'external_conversation_id' => $externalConversationId,
							'page' => $page,
							'from_time' => $fromTime,
							'error' => (string) ($response['error'] ?? 'error desconocido'),
						], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
						break;
					}
					error_log('CCI Freshchat live sync fallo: ' . json_encode([
						'local_conversation_id' => $localConversationId,
						'external_conversation_id' => $externalConversationId,
						'page' => $page,
						'error' => (string) ($response['error'] ?? 'error desconocido'),
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
					return ['ok' => false, 'created' => 0, 'skipped' => 0, 'error' => (string) ($response['error'] ?? 'error desconocido')];
				}

				$pageRows = is_array($response['data']['messages'] ?? null) ? $response['data']['messages'] : [];
				if ($pageRows === []) {
					break;
				}
				foreach ($pageRows as $row) {
					if (!is_array($row)) {
						continue;
					}
					$passRows[] = $row;
					$rid = trim((string) ($row['id'] ?? ''));
					$key = $rid !== '' ? $rid : ('p' . $page . '_' . count($rowsById));
					$rowsById[$key] = $row;
				}

				if (count($pageRows) < 50) {
					break;
				}
			}

			if ($fromTime !== '' && !empty($passRows) && $lastLocalTs > 0) {
				$maxFetchedTs = 0;
				foreach ($passRows as $passRow) {
					$ts = strtotime((string) ($passRow['created_time'] ?? ($passRow['created_at'] ?? ''))) ?: 0;
					if ($ts > $maxFetchedTs) {
						$maxFetchedTs = $ts;
					}
				}
				if ($maxFetchedTs > 0 && $maxFetchedTs + 60 < $lastLocalTs) {
					error_log('CCI Freshchat live sync detecto lote antiguo y aplicara fallback: ' . json_encode([
						'local_conversation_id' => $localConversationId,
						'external_conversation_id' => $externalConversationId,
						'from_time' => $fromTime,
						'last_local_ts' => $lastLocalTs,
						'max_fetched_ts' => $maxFetchedTs,
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
					$rowsById = [];
					continue;
				}
			}

			if (!empty($rowsById)) {
				break;
			}
		}

		$rows = array_values($rowsById);

		usort($rows, static function (array $a, array $b): int {
			$ta = strtotime((string) ($a['created_time'] ?? ($a['created_at'] ?? ''))) ?: 0;
			$tb = strtotime((string) ($b['created_time'] ?? ($b['created_at'] ?? ''))) ?: 0;
			if ($ta === $tb) {
				return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
			}
			return $ta <=> $tb;
		});
		$created = 0;
		$skipped = 0;
		error_log('CCI Freshchat live sync lote: ' . json_encode([
			'local_conversation_id' => $localConversationId,
			'external_conversation_id' => $externalConversationId,
			'from_time' => $fromTimeUtc,
			'fetched_rows' => count($rows),
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		foreach ($rows as $row) {
			if (!is_array($row)) {
				$skipped++;
				continue;
			}
			$externalMessageId = trim((string) ($row['id'] ?? ''));
			if ($externalMessageId === '' || $this->messageRefExists($db, 'freshchat', $externalMessageId)) {
				$skipped++;
				continue;
			}

			$messagePartsJson = json_encode($row['message_parts'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$message = $this->extractFreshchatMessage((string) $messagePartsJson);
			$text = trim((string) ($message['text'] ?? ''));
			if ($text === '') {
				$skipped++;
				continue;
			}

			$tipo = (string) ($message['tipo'] ?? 'texto');
			if ($tipo === 'archivo') {
				$maybeUrl = $this->extractFreshchatMediaUrl($text);
				if ($maybeUrl !== '') {
					$preferredMediaName = trim((string) ($message['media_name'] ?? ''));
					$text = $this->cacheFreshchatMedia($maybeUrl, $externalMessageId, $preferredMediaName);
				}
			}

			$actorType = strtoupper(trim((string) ($row['actor_type'] ?? '')));
			$isOut = $actorType !== 'USER';
			$createdAt = (string) ($row['created_time'] ?? ($row['created_at'] ?? date('c')));
			$localMessageId = $this->insertBotMessage($db, $localConversationId, $text, $isOut, $createdAt, $tipo);
			if ($localMessageId > 0) {
				$this->saveMessageRef($db, 'freshchat', $externalMessageId, $localConversationId, $localMessageId, $isOut ? 'out' : 'in');
				$created++;
			} else {
				$skipped++;
			}
		}

		return ['ok' => true, 'created' => $created, 'skipped' => $skipped];
	}

	private function normalizeImportedFreshchatMedia(PDO $db): int
	{
		// En algunos entornos bot_mensajes no tiene columna tipo/updated_at: evitar romper el sync.
		$columns = $this->getTableColumnsSafe($db, 'bot_mensajes');
		if (!in_array('tipo', $columns, true)) {
			return 0;
		}
		$setUpdatedAt = in_array('updated_at', $columns, true) ? ', bm.updated_at = NOW()' : '';
		$stmt = $db->prepare('UPDATE bot_mensajes bm
			INNER JOIN bot_conversaciones bc ON bc.id = bm.conversacion_id
			SET bm.tipo = "archivo"' . $setUpdatedAt . '
			WHERE bc.canal = "freshchat" AND bm.tipo = "texto" AND bm.mensaje REGEXP "^https?://"');
		$stmt->execute();
		return $stmt->rowCount();
	}

	private function cacheFreshchatMedia(string $url, string $externalMessageId, string $preferredName = ''): string
	{
		if (!filter_var($url, FILTER_VALIDATE_URL) || !function_exists('curl_init')) {
			return $url;
		}

		$headers = [];
		$curl = curl_init($url);
		if ($curl === false) {
			return $url;
		}
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 45,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
				$separator = strpos($header, ':');
				if ($separator !== false) {
					$headers[strtolower(trim(substr($header, 0, $separator)))] = trim(substr($header, $separator + 1));
				}
				return strlen($header);
			},
		]);
		$body = curl_exec($curl);
		$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);
		if (!is_string($body) || $httpCode < 200 || $httpCode >= 300 || strlen($body) > 25 * 1024 * 1024) {
			return $url;
		}

		$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$contentType = strtolower((string) ($headers['content-type'] ?? ''));
		if ($extension === '') {
			$extension = match (true) {
				str_contains($contentType, 'jpeg') => 'jpg',
				str_contains($contentType, 'png') => 'png',
				str_contains($contentType, 'gif') => 'gif',
				str_contains($contentType, 'webp') => 'webp',
				str_contains($contentType, 'pdf') => 'pdf',
				str_contains($contentType, 'mp4') => 'mp4',
				str_contains($contentType, 'mpeg') => 'mp3',
				default => 'bin',
			};
		}

		$hashToken = substr(hash('sha256', $externalMessageId . '|' . $url), 0, 8);
		$safePreferred = $this->buildFreshchatPreferredFileName($preferredName, $preferredName, $contentType);
		if ($safePreferred === '' || $this->isGenericAttachmentName($safePreferred)) {
			$safePreferred = 'freshchat_media.' . $extension;
		}
		$filename = date('YmdHis') . '_' . $hashToken . '_' . $safePreferred;
		$storageDir = STORAGE_PATH . '/uploads/cci-attachments';
		$publicDir = PUBLIC_PATH . '/cci-attachments';
		if ((!is_dir($storageDir) && !@mkdir($storageDir, 0755, true)) || (!is_dir($publicDir) && !@mkdir($publicDir, 0755, true))) {
			return $url;
		}
		if (file_put_contents($storageDir . '/' . $filename, $body) === false || file_put_contents($publicDir . '/' . $filename, $body) === false) {
			return $url;
		}
		@chmod($storageDir . '/' . $filename, 0644);
		@chmod($publicDir . '/' . $filename, 0644);
		return $filename;
	}

	private function extractFreshchatMediaUrl(string $value): string
	{
		if (preg_match('#https?://[^\s"<>]+?\.(?:jpe?g|png|gif|webp|bmp|mp4|mov|mp3|wav|m4a|pdf|docx?|xlsx?|zip)(?:\?[^\s"<>]*)?#i', $value, $match)) {
			return (string) $match[0];
		}
		if (preg_match('#https?://[^\s"<>]+#i', $value, $match)) {
			return (string) $match[0];
		}
		return '';
	}

	private function normalizeFreshchatMediaUrls(PDO $db): int
	{
		if (!in_array('tipo', $this->getTableColumnsSafe($db, 'bot_mensajes'), true)) {
			return 0;
		}
		$stmt = $db->query('SELECT bm.id, bm.mensaje
			FROM bot_mensajes bm
			INNER JOIN bot_conversaciones bc ON bc.id = bm.conversacion_id
			WHERE bc.canal = "freshchat" AND bm.tipo = "archivo" AND bm.mensaje LIKE "http%"');
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		$update = $db->prepare('UPDATE bot_mensajes SET mensaje = :mensaje, updated_at = NOW() WHERE id = :id');
		$updated = 0;
		foreach ($rows as $row) {
			$current = (string) ($row['mensaje'] ?? '');
			$url = $this->extractFreshchatMediaUrl($current);
			if ($url !== '' && $url !== $current) {
				$update->execute(['mensaje' => $url, 'id' => (int) $row['id']]);
				$updated++;
			}
		}
		return $updated;
	}

	/**
	 * Corrige mensajes ya cacheados localmente (ej. freshchat_<hash>.ogg) que quedaron marcados como texto.
	 */
	private function normalizeCachedFreshchatFilenames(PDO $db): int
	{
		$columns = $this->getTableColumnsSafe($db, 'bot_mensajes');
		if (!in_array('tipo', $columns, true)) {
			return 0;
		}
		$setUpdatedAt = in_array('updated_at', $columns, true) ? ', bm.updated_at = NOW()' : '';
		$mediaExtPattern = '\\.(jpe?g|png|gif|webp|bmp|mp4|avi|mov|mkv|flv|wmv|mp3|wav|aac|m4a|flac|ogg|opus|pdf|docx?|xlsx?|zip)$';
		$stmt = $db->prepare('UPDATE bot_mensajes bm
			INNER JOIN bot_conversaciones bc ON bc.id = bm.conversacion_id
			SET bm.tipo = "archivo"' . $setUpdatedAt . '
			WHERE bc.canal = "freshchat" AND bm.tipo = "texto"
			AND bm.mensaje NOT LIKE "% %"
			AND bm.mensaje REGEXP :pattern');
		$stmt->execute(['pattern' => $mediaExtPattern]);
		return $stmt->rowCount();
	}

	private function ensureFreshchatConversation(PDO $db, int $contactId, string $externalConversationId): ?int
	{
		$lookup = $db->prepare('SELECT conversacion_id FROM cci_conversacion_refs WHERE provider_code = "freshchat" AND external_conversation_id = :external_id LIMIT 1');
		$lookup->execute(['external_id' => $externalConversationId]);
		$conversationId = (int) ($lookup->fetchColumn() ?: 0);
		if ($conversationId > 0) {
			return $conversationId;
		}

		$columns = $this->getTableColumnsSafe($db, 'bot_conversaciones');
		$allowed = [
			'contacto_id' => $contactId,
			'canal' => 'freshchat',
			'estado' => 'activo',
			'fecha_inicio' => date('Y-m-d H:i:s'),
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		];
		$data = array_filter($allowed, static fn($key) => in_array($key, $columns, true), ARRAY_FILTER_USE_KEY);
		if ($data === []) {
			return null;
		}
		$insert = $db->prepare('INSERT INTO bot_conversaciones (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_map(static fn($key) => ':' . $key, array_keys($data))) . ')');
		$insert->execute($data);
		$conversationId = (int) $db->lastInsertId();
		if ($conversationId <= 0) {
			return null;
		}

		$save = $db->prepare('INSERT INTO cci_conversacion_refs (provider_code, external_conversation_id, conversacion_id, created_at, updated_at)
			VALUES ("freshchat", :external_id, :conversacion_id, NOW(), NOW())
			ON DUPLICATE KEY UPDATE updated_at = NOW()');
		$save->execute(['external_id' => $externalConversationId, 'conversacion_id' => $conversationId]);

		// Otra ejecución pudo registrar la misma conversación: conservar la referencia ganadora.
		$lookup->execute(['external_id' => $externalConversationId]);
		$storedId = (int) ($lookup->fetchColumn() ?: 0);
		return $storedId > 0 ? $storedId : $conversationId;
	}

	private function freshchatRowValue(array $row, array $keys): string
	{
		foreach ($keys as $key) {
			$value = trim((string) ($row[$key] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}
		return '';
	}

	private function importFreshchatTranscript(PDO $db, string $csv): array
	{
		$firstLine = strtok($csv, "\r\n");
		$delimiterCandidates = [',', ';', "\t"];
		$delimiter = ',';
		$delimiterCount = 0;
		foreach ($delimiterCandidates as $candidate) {
			$count = substr_count((string) $firstLine, $candidate);
			if ($count > $delimiterCount) {
				$delimiter = $candidate;
				$delimiterCount = $count;
			}
		}

		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $csv);
		rewind($stream);
		$headers = fgetcsv($stream, 0, $delimiter);
		if (!is_array($headers)) {
			return ['created' => 0, 'skipped' => 0, 'error' => 'El reporte Freshchat no contiene encabezados CSV.'];
		}
		$headers = array_map(static function ($header): string {
			$normalized = strtolower(trim((string) $header));
			$normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
			return trim((string) preg_replace('/[^a-z0-9]+/', '_', $normalized), '_');
		}, $headers);
		$rowsByConversation = [];
		$sourceRows = 0;
		while (($values = fgetcsv($stream, 0, $delimiter)) !== false) {
			if (count($values) !== count($headers)) {
				continue;
			}
			$sourceRows++;
			$row = array_combine($headers, $values);
			$conversationId = $this->freshchatRowValue($row, ['conversation_id', 'conversationid', 'conv_id', 'convid']);
			if ($conversationId !== '') {
				$rowsByConversation[$conversationId][] = $row;
			}
		}
		fclose($stream);

		$created = 0;
		$skipped = 0;
		foreach ($rowsByConversation as $externalConversationId => $rows) {
			$phone = '';
			$name = '';
			foreach ($rows as $row) {
				$phone = $this->normalizePhone($this->freshchatRowValue($row, ['user_phone', 'userphone', 'customer_phone', 'customerphone', 'actor_phone', 'actorphone']));
				if ($phone !== '') {
					$name = trim($this->freshchatRowValue($row, ['actor_first_name', 'actorfirstname', 'user_first_name', 'userfirstname']) . ' ' . $this->freshchatRowValue($row, ['actor_last_name', 'actorlastname', 'user_last_name', 'userlastname']));
					break;
				}
			}
			if ($phone === '') {
				$skipped += count($rows);
				continue;
			}
			$contactId = $this->ensureContactByPhone($db, $phone, $name);
			$localConversationId = $this->ensureFreshchatConversation($db, (int) $contactId, $externalConversationId);
			if ($localConversationId === null) {
				$skipped += count($rows);
				continue;
			}
			foreach ($rows as $row) {
				$messageId = $this->freshchatRowValue($row, ['message_id', 'messageid', 'id']);
				$message = $this->extractFreshchatMessage($this->freshchatRowValue($row, ['message_parts', 'messageparts', 'message_part', 'messagepart']));
				$text = trim((string) ($message['text'] ?? ''));
				if ($messageId === '' || $text === '') {
					$skipped++;
					continue;
				}
				if ($this->messageRefExists($db, 'freshchat', $messageId)) {
					$skipped++;
					continue;
				}
				$isOut = strtoupper($this->freshchatRowValue($row, ['actor_type', 'actortype'])) !== 'USER';
				$tipo = (string) ($message['tipo'] ?? 'texto');
				if ($tipo === 'archivo') {
					$text = $this->extractFreshchatMediaUrl($text) ?: $text;
					$text = $this->cacheFreshchatMedia($text, $messageId);
				}
				$localMessageId = $this->insertBotMessage($db, $localConversationId, $text, $isOut, $this->freshchatRowValue($row, ['created_time', 'createdtime', 'created_at', 'createdat', 'timestamp']), $tipo);
				if ($localMessageId > 0) {
					$this->saveMessageRef($db, 'freshchat', $messageId, $localConversationId, $localMessageId, $isOut ? 'out' : 'in');
					$created++;
				}
			}
		}

		return [
			'created' => $created,
			'skipped' => $skipped,
			'source_rows' => $sourceRows,
			'conversation_rows' => array_sum(array_map('count', $rowsByConversation)),
			'headers' => $headers,
			'delimiter_name' => $delimiter === "\t" ? 'tabulador' : ($delimiter === ';' ? 'punto y coma' : 'coma'),
		];
	}

	private function saveFreshchatDiagnostic(PDO $db, array $result, array $pending): void
	{
		$stmt = $db->prepare('INSERT INTO cci_sync_diagnostics (provider_code, window_start, window_end, source_rows, conversation_rows, imported_rows, skipped_rows, delimiter_name, headers_json, created_at)
			VALUES ("freshchat", :window_start, :window_end, :source_rows, :conversation_rows, :imported_rows, :skipped_rows, :delimiter_name, :headers_json, NOW())');
		$stmt->execute([
			'window_start' => !empty($pending['window_start']) ? date('Y-m-d H:i:s', strtotime((string) $pending['window_start'])) : null,
			'window_end' => !empty($pending['window_end']) ? date('Y-m-d H:i:s', strtotime((string) $pending['window_end'])) : null,
			'source_rows' => (int) ($result['source_rows'] ?? 0),
			'conversation_rows' => (int) ($result['conversation_rows'] ?? 0),
			'imported_rows' => (int) ($result['created'] ?? 0),
			'skipped_rows' => (int) ($result['skipped'] ?? 0),
			'delimiter_name' => (string) ($result['delimiter_name'] ?? ''),
			'headers_json' => json_encode(array_values($result['headers'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);
	}

	public function freshchatDiagnostico(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);
		$stmt = $db->query('SELECT * FROM cci_sync_diagnostics WHERE provider_code = "freshchat" ORDER BY id DESC LIMIT 10');
		$diagnostics = $stmt ? ($stmt->fetchAll() ?: []) : [];
		$this->view('cci/freshchat-diagnostico', compact('diagnostics'), [
			'title' => 'Diagnóstico Freshchat',
			'styles' => ['cci.css'],
		]);
	}

	/**
	 * Ejecuta un paso del ciclo Freshchat: importa el reporte pendiente o solicita la siguiente ventana diaria.
	 */
	private function processFreshchatSyncStep(PDO $db): array
	{
		$service = new FreshchatService();
		$config = $service->getConfig();
		if (trim((string) ($config['api_token'] ?? '')) === '') {
			return ['ok' => true, 'status' => 'disabled', 'message' => 'Freshchat no tiene token configurado.'];
		}

		$stateStmt = $db->prepare('SELECT last_cursor, updated_at FROM cci_sync_state WHERE provider_code = "freshchat" LIMIT 1');
		$stateStmt->execute();
		$state = $stateStmt->fetch() ?: [];
		$pending = json_decode((string) ($state['last_cursor'] ?? ''), true);
		$stateUpdatedAtRaw = trim((string) ($state['updated_at'] ?? ''));
		$stateUpdatedAtTs = $stateUpdatedAtRaw !== '' ? strtotime($stateUpdatedAtRaw) : false;
		$pendingAgeSeconds = $stateUpdatedAtTs !== false ? (time() - $stateUpdatedAtTs) : 0;
		$checkpointStmt = $db->query('SELECT MAX(window_end) FROM cci_sync_diagnostics WHERE provider_code = "freshchat"');
		$latestCheckpoint = trim((string) ($checkpointStmt ? ($checkpointStmt->fetchColumn() ?: '') : ''));
		$pendingWindowEnd = is_array($pending) ? trim((string) ($pending['window_end'] ?? '')) : '';
		if (
			is_array($pending)
			&& !empty($pending['report_id'])
			&& $latestCheckpoint !== ''
			&& $pendingWindowEnd !== ''
			&& strtotime($pendingWindowEnd) <= strtotime($latestCheckpoint)
		) {
			$pending = ['next_start' => $latestCheckpoint];
			$db->prepare('UPDATE cci_sync_state SET last_cursor = :cursor, updated_at = NOW() WHERE provider_code = "freshchat"')
				->execute(['cursor' => json_encode($pending, JSON_UNESCAPED_SLASHES)]);
		}

		// Si un report_id queda pendiente demasiado tiempo, liberar cursor para no bloquear toda la sincronización.
		if (is_array($pending) && !empty($pending['report_id']) && $pendingAgeSeconds > 1200) {
			$fallbackStart = $pendingWindowEnd !== '' ? $pendingWindowEnd : $latestCheckpoint;
			$pending = ['next_start' => $fallbackStart !== '' ? $fallbackStart : date(DateTimeInterface::ATOM)];
			$db->prepare('UPDATE cci_sync_state SET last_cursor = :cursor, updated_at = NOW() WHERE provider_code = "freshchat"')
				->execute(['cursor' => json_encode($pending, JSON_UNESCAPED_SLASHES)]);
		}

		if (is_array($pending) && !empty($pending['report_id'])) {
			$report = $service->getReport((string) $pending['report_id']);
			if (!$report['ok']) {
				$reportError = (string) ($report['error'] ?? 'error desconocido');
				$looksStale = preg_match('/\b(404|not\s*found|expired|gone)\b/i', $reportError) === 1;
				if ($looksStale && $pendingAgeSeconds > 1200) {
					$fallbackStart = $pendingWindowEnd !== '' ? $pendingWindowEnd : $latestCheckpoint;
					$resetState = ['next_start' => $fallbackStart !== '' ? $fallbackStart : date(DateTimeInterface::ATOM)];
					$db->prepare('UPDATE cci_sync_state SET last_cursor = :cursor, updated_at = NOW() WHERE provider_code = "freshchat"')
						->execute(['cursor' => json_encode($resetState, JSON_UNESCAPED_SLASHES)]);
					$pending = $resetState;
				} else {
					return ['ok' => true, 'status' => 'pending', 'message' => 'La exportación Freshchat aún no está lista.'];
				}
			}
			$link = (string) ($report['data']['links'][0]['link']['href'] ?? '');
			if ($link === '' && ($report['ok'] ?? false)) {
				if ($pendingAgeSeconds > 1200) {
					$fallbackStart = $pendingWindowEnd !== '' ? $pendingWindowEnd : $latestCheckpoint;
					$resetState = ['next_start' => $fallbackStart !== '' ? $fallbackStart : date(DateTimeInterface::ATOM)];
					$db->prepare('UPDATE cci_sync_state SET last_cursor = :cursor, updated_at = NOW() WHERE provider_code = "freshchat"')
						->execute(['cursor' => json_encode($resetState, JSON_UNESCAPED_SLASHES)]);
				} else {
					return ['ok' => true, 'status' => 'pending', 'message' => 'La exportación Freshchat sigue en proceso.'];
				}
			}
			if ($link !== '') {
				$download = $service->downloadCsv($link);
				if (!$download['ok']) {
					return ['ok' => false, 'status' => 'error', 'message' => (string) $download['error']];
				}

				$result = $this->importFreshchatTranscript($db, (string) $download['csv']);
				$result['normalized_media'] = $this->normalizeImportedFreshchatMedia($db);
				$result['normalized_media_urls'] = $this->normalizeFreshchatMediaUrls($db);
				$result['normalized_cached_filenames'] = $this->normalizeCachedFreshchatFilenames($db);
				$this->saveFreshchatDiagnostic($db, $result, $pending);

				$windowEnd = trim((string) ($pending['window_end'] ?? ''));
				$overallEnd = trim((string) ($pending['overall_end'] ?? ''));
				$nextCursor = '';
				if ($windowEnd !== '') {
					$nextState = ['next_start' => $windowEnd];
					if ($overallEnd !== '' && strtotime($windowEnd) < strtotime($overallEnd)) {
						$nextState['overall_end'] = $overallEnd;
					}
					$nextCursor = json_encode($nextState, JSON_UNESCAPED_SLASHES);
				}
				$saveState = $db->prepare('INSERT INTO cci_sync_state (provider_code, last_cursor, last_sync_at, updated_at) VALUES ("freshchat", :cursor, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_cursor = VALUES(last_cursor), last_sync_at = NOW(), updated_at = NOW()');
				$saveState->execute(['cursor' => $nextCursor]);

				return [
					'ok' => true,
					'status' => 'imported',
					'created' => (int) ($result['created'] ?? 0),
					'skipped' => (int) ($result['skipped'] ?? 0),
					'source_rows' => (int) ($result['source_rows'] ?? 0),
					'message' => 'Freshchat sincronizado. Filas del reporte: ' . (int) ($result['source_rows'] ?? 0)
						. ', importados: ' . (int) ($result['created'] ?? 0)
						. ', omitidos: ' . (int) ($result['skipped'] ?? 0) . '.',
				];
			}
		}

		$overallEnd = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$startRaw = is_array($pending) ? trim((string) ($pending['next_start'] ?? '')) : '';
		if ($startRaw === '') {
			$startRaw = $latestCheckpoint;
		}
		if ($startRaw === '') {
			$startRaw = trim((string) ($config['sync_start'] ?? ''));
		}
		try {
			$start = $startRaw !== ''
				? new DateTimeImmutable($startRaw, new DateTimeZone('UTC'))
				: $overallEnd->sub(new DateInterval('P30D'));
		} catch (Throwable $e) {
			return ['ok' => false, 'status' => 'error', 'message' => 'FRESHCHAT_SYNC_START no tiene una fecha UTC válida.'];
		}

		// Si el backlog es demasiado grande, priorizar últimos 2 días para reflejar mensajes recientes antes.
		$recentFloor = $overallEnd->sub(new DateInterval('P2D'));
		if ($start < $recentFloor) {
			$start = $recentFloor;
		}
		if ($start >= $overallEnd) {
			return ['ok' => true, 'status' => 'up_to_date', 'message' => 'El historial Freshchat ya está actualizado.'];
		}

		$windowEnd = min($start->add(new DateInterval('P1D')), $overallEnd);
		$report = $service->requestChatTranscript($start->format('Y-m-d\TH:i:s.000\Z'), $windowEnd->format('Y-m-d\TH:i:s.000\Z'));
		if (!$report['ok']) {
			$reportError = (string) ($report['error'] ?? 'error desconocido');
			if (strpos($reportError, 'HTTP 429') !== false || stripos($reportError, 'req/min') !== false) {
				return ['ok' => true, 'status' => 'throttled', 'message' => 'Freshchat limitó temporalmente la solicitud de transcript. Reintentará automáticamente.'];
			}
			return ['ok' => false, 'status' => 'error', 'message' => (string) $report['error']];
		}
		$reportId = trim((string) ($report['data']['id'] ?? ''));
		if ($reportId === '') {
			return ['ok' => false, 'status' => 'error', 'message' => 'Freshchat no devolvió un identificador para la exportación.'];
		}

		$cursor = json_encode([
			'report_id' => $reportId,
			'window_start' => $start->format(DateTimeInterface::ATOM),
			'window_end' => $windowEnd->format(DateTimeInterface::ATOM),
			'overall_end' => $overallEnd->format(DateTimeInterface::ATOM),
		], JSON_UNESCAPED_SLASHES);
		$save = $db->prepare('INSERT INTO cci_sync_state (provider_code, last_cursor, updated_at) VALUES ("freshchat", :cursor, NOW()) ON DUPLICATE KEY UPDATE last_cursor = VALUES(last_cursor), updated_at = NOW()');
		$save->execute(['cursor' => $cursor]);

		return [
			'ok' => true,
			'status' => 'requested',
			'message' => 'Exportación Freshchat solicitada para ' . $start->format('Y-m-d') . '.',
		];
	}

	/**
	 * Reclama el turno de ejecución de forma atómica para que dos procesos no sincronicen a la vez.
	 */
	private function claimFreshchatAutoSync(PDO $db): bool
	{
		$intervalSeconds = max(60, (int) env('FRESHCHAT_SYNC_INTERVAL_SECONDS', 300));
		$db->prepare('INSERT IGNORE INTO cci_sync_state (provider_code, last_sync_at, updated_at) VALUES ("freshchat_auto", NULL, NOW())')
			->execute();

		$claim = $db->prepare('UPDATE cci_sync_state
			SET last_sync_at = NOW(), updated_at = NOW()
			WHERE provider_code = "freshchat_auto"
			  AND (last_sync_at IS NULL OR last_sync_at <= DATE_SUB(NOW(), INTERVAL :seconds SECOND))');
		$claim->bindValue(':seconds', $intervalSeconds, PDO::PARAM_INT);
		$claim->execute();

		return $claim->rowCount() > 0;
	}

	/**
	 * Sincroniza Freshchat sin contexto HTTP (sin auth/csrf/redirect). Para uso desde AutoSyncScheduler.
	 */
	public function runFreshchatSyncBackground(): array
	{
		try {
			$db = $this->db();
			$this->ensureCciTables($db);

			if (!$this->claimFreshchatAutoSync($db)) {
				return ['ok' => true, 'status' => 'throttled'];
			}

			return $this->processFreshchatSyncStep($db);
		} catch (Throwable $e) {
			return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
		}
	}

	/**
	 * Sincroniza mensajes de WhatsApp sin requerir contexto HTTP (sin auth/csrf/redirect).
	 * Para uso desde AutoSyncScheduler.
	 */
	public function runWhatchimpSyncBackground(int $limit = 100): array
	{
		try {
			$db = $this->db();
			$this->ensureCciTables($db);

			$stateStmt = $db->prepare('SELECT last_cursor, last_sync_at FROM cci_sync_state WHERE provider_code = "whatchimp" LIMIT 1');
			$stateStmt->execute();
			$state = $stateStmt->fetch() ?: [];

			$cursor = trim((string) ($state['last_cursor'] ?? ''));
			$since  = $cursor === '' ? trim((string) ($state['last_sync_at'] ?? '')) : '';

			$service = new WhatchimpService();
			$pull    = $service->fetchMessages($since, $cursor, $limit);
			if (!($pull['ok'] ?? false)) {
				return ['ok' => false, 'error' => (string) ($pull['error'] ?? 'error desconocido'), 'created' => 0, 'skipped' => 0];
			}

			$normalized = $this->normalizeWhatchimpMessages([
				'messages' => is_array($pull['messages'] ?? null) ? $pull['messages'] : [],
			]);
			$result = $this->processWhatchimpMessages($db, $normalized, 'sync');

			$upsert = $db->prepare('INSERT INTO cci_sync_state (provider_code, last_cursor, last_sync_at, updated_at)
				VALUES ("whatchimp", :cursor, NOW(), NOW())
				ON DUPLICATE KEY UPDATE last_cursor = VALUES(last_cursor), last_sync_at = VALUES(last_sync_at), updated_at = NOW()');
			$upsert->execute(['cursor' => (string) ($pull['next_cursor'] ?? '')]);

			return ['ok' => true, 'created' => (int) ($result['created'] ?? 0), 'skipped' => (int) ($result['skipped'] ?? 0)];
		} catch (Throwable $e) {
			return ['ok' => false, 'error' => $e->getMessage(), 'created' => 0, 'skipped' => 0];
		}
	}

	public function syncWhatchimp(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones');
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$stateStmt = $db->prepare('SELECT last_cursor, last_sync_at FROM cci_sync_state WHERE provider_code = "whatchimp" LIMIT 1');
		$stateStmt->execute();
		$state = $stateStmt->fetch() ?: [];

		$cursor = trim((string) ($state['last_cursor'] ?? ''));
		$since = '';
		if ($cursor === '') {
			$since = trim((string) ($state['last_sync_at'] ?? ''));
		}

		$service = new WhatchimpService();
		$pull = $service->fetchMessages($since, $cursor, 100);
		if (!$pull['ok']) {
			set_flash('error', 'No se pudo sincronizar el proveedor de WhatsApp: ' . (string) ($pull['error'] ?? 'error desconocido'));
			redirect('cci/conversaciones');
		}

		$normalized = $this->normalizeWhatchimpMessages([
			'messages' => is_array($pull['messages'] ?? null) ? $pull['messages'] : [],
		]);
		$result = $this->processWhatchimpMessages($db, $normalized, 'sync');

		$upsertState = $db->prepare('INSERT INTO cci_sync_state (provider_code, last_cursor, last_sync_at, updated_at)
			VALUES ("whatchimp", :last_cursor, NOW(), NOW())
			ON DUPLICATE KEY UPDATE last_cursor = VALUES(last_cursor), last_sync_at = VALUES(last_sync_at), updated_at = NOW()');
		$upsertState->execute([
			'last_cursor' => (string) ($pull['next_cursor'] ?? ''),
		]);

		set_flash('success', 'Sincronización WhatsApp completada. Procesados: ' . (int) $result['created'] . ', omitidos: ' . (int) $result['skipped'] . '.');
		$this->fireAutomationEvent('provider_sync_completed', [
			'provider' => 'whatsapp',
			'processed' => (int) $result['created'],
			'skipped' => (int) $result['skipped'],
			'next_cursor' => (string) ($pull['next_cursor'] ?? ''),
		]);
		redirect('cci/conversaciones');
	}

	public function dashboard(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);
		$this->ensureProspectByInteraction($db);

		$kpis = [
			'total_conversaciones' => 0,
			'conversaciones_activas' => 0,
			'conversaciones_pendientes' => 0,
			'clientes_potenciales' => 0,
			'clientes_matriculados' => 0,
			'tiempo_promedio_respuesta_min' => 0,
		];

		$porAsesor = [];
		$porPlataforma = [];
		$seriesDiarias = [];
		$seriesMensuales = [];
		$porEstado = [];

		try {
			$kpis['total_conversaciones'] = (int) $db->query('SELECT COUNT(*) FROM bot_conversaciones')->fetchColumn();
			$kpis['conversaciones_activas'] = (int) $db->query("SELECT COUNT(*) FROM bot_conversaciones WHERE estado = 'activo'")->fetchColumn();
			$kpis['conversaciones_pendientes'] = (int) $db->query("SELECT COUNT(*) FROM bot_conversaciones WHERE estado IN ('pendiente','nuevo')")->fetchColumn();
			$kpis['clientes_potenciales'] = (int) $db->query("SELECT COUNT(*) FROM interesados WHERE estado = 'activo' AND COALESCE(convertido, 0) = 0")->fetchColumn();
			$kpis['clientes_matriculados'] = (int) $db->query("SELECT COUNT(*) FROM interesados WHERE COALESCE(convertido, 0) = 1")->fetchColumn();

			$avgStmt = $db->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, a.first_in, b.first_out)) AS avg_min
				FROM (
					SELECT conversacion_id, MIN(COALESCE(fecha, created_at)) AS first_in
					FROM bot_mensajes
					WHERE es_bot = 0
					GROUP BY conversacion_id
				) a
				INNER JOIN (
					SELECT conversacion_id, MIN(COALESCE(fecha, created_at)) AS first_out
					FROM bot_mensajes
					WHERE es_bot = 1
					GROUP BY conversacion_id
				) b ON b.conversacion_id = a.conversacion_id AND b.first_out >= a.first_in");
			$kpis['tiempo_promedio_respuesta_min'] = (int) round((float) ($avgStmt ? ($avgStmt->fetchColumn() ?: 0) : 0));

			$porAsesor = $db->query("SELECT COALESCE(u.nombre, 'Sin asignar') AS asesor, COUNT(*) AS total
				FROM bot_conversaciones bc
				LEFT JOIN usuarios u ON u.id = bc.asignado_a
				GROUP BY COALESCE(u.nombre, 'Sin asignar')
				ORDER BY total DESC LIMIT 10")->fetchAll() ?: [];

			$porPlataforma = $db->query("SELECT COALESCE(canal, 'sin_canal') AS plataforma, COUNT(*) AS total
				FROM bot_conversaciones
				GROUP BY COALESCE(canal, 'sin_canal')
				ORDER BY total DESC")->fetchAll() ?: [];

			$seriesDiarias = $db->query("SELECT DATE(COALESCE(fecha_inicio, created_at)) AS etiqueta, COUNT(*) AS total
				FROM bot_conversaciones
				WHERE COALESCE(fecha_inicio, created_at) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
				GROUP BY DATE(COALESCE(fecha_inicio, created_at))
				ORDER BY etiqueta ASC")->fetchAll() ?: [];

			$seriesMensuales = $db->query("SELECT DATE_FORMAT(COALESCE(fecha_inicio, created_at), '%Y-%m') AS etiqueta, COUNT(*) AS total
				FROM bot_conversaciones
				WHERE COALESCE(fecha_inicio, created_at) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
				GROUP BY DATE_FORMAT(COALESCE(fecha_inicio, created_at), '%Y-%m')
				ORDER BY etiqueta ASC")->fetchAll() ?: [];

			$porEstado = $db->query("SELECT COALESCE(estado, 'sin_estado') AS estado, COUNT(*) AS total
				FROM bot_conversaciones
				GROUP BY COALESCE(estado, 'sin_estado')
				ORDER BY total DESC")->fetchAll() ?: [];
		} catch (Throwable $e) {
			// Mantener dashboard funcional aun si falta alguna tabla/columna auxiliar.
		}

		$this->view('cci/dashboard', compact('kpis', 'porAsesor', 'porPlataforma', 'seriesDiarias', 'seriesMensuales', 'porEstado'), [
			'title' => 'Centro de Comunicaciones - Dashboard',
		]);
	}

	public function reportes(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$config = new CciConfig();
		$defaultDays = max(7, min(120, (int) $config->getValue('reportes', 'default_days', '30')));
		$goalDelivery = max(1.0, min(100.0, (float) $config->getValue('reportes', 'objetivo_entrega_pct', '95')));
		$goalError = max(0.0, min(100.0, (float) $config->getValue('reportes', 'objetivo_error_pct', '5')));

		$today = new DateTimeImmutable('today');
		$defaultFrom = $today->sub(new DateInterval('P' . max(1, $defaultDays - 1) . 'D'))->format('Y-m-d');
		$defaultTo = $today->format('Y-m-d');

		$from = trim((string) ($_GET['desde'] ?? $defaultFrom));
		$to = trim((string) ($_GET['hasta'] ?? $defaultTo));
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
			$from = $defaultFrom;
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			$to = $defaultTo;
		}
		if ($from > $to) {
			[$from, $to] = [$to, $from];
		}

		$provider = trim((string) ($_GET['provider'] ?? ''));
		$campanaId = max(0, (int) ($_GET['campana_id'] ?? 0));

		$filters = [
			'desde' => $from,
			'hasta' => $to,
			'provider' => $provider,
			'campana_id' => $campanaId,
			'goal_delivery' => $goalDelivery,
			'goal_error' => $goalError,
		];

		$campanas = [];
		$providers = [];
		$kpis = [
			'total_destinatarios' => 0,
			'enviados' => 0,
			'errores' => 0,
			'pendientes' => 0,
			'intentos_totales' => 0,
			'promedio_intentos' => 0.0,
			'tasa_entrega' => 0.0,
			'tasa_error' => 0.0,
			'campanas_total' => 0,
			'campanas_programadas' => 0,
		];
		$seriesDiarias = [];
		$topCampanas = [];
		$erroresTop = [];
		$porProveedor = [];
		$automations = [
			'total' => 0,
			'ok' => 0,
			'failed' => 0,
			'pending' => 0,
		];

		try {
			$campanas = $db->query('SELECT id, nombre FROM cci_campanas ORDER BY id DESC LIMIT 300')->fetchAll() ?: [];
			$providers = $db->query('SELECT codigo, nombre FROM cci_proveedores WHERE estado = "activo" ORDER BY orden ASC, nombre ASC')->fetchAll() ?: [];

			$where = ['COALESCE(cd.enviado_at, cd.updated_at, cd.created_at) >= :from_dt', 'COALESCE(cd.enviado_at, cd.updated_at, cd.created_at) < DATE_ADD(:to_dt, INTERVAL 1 DAY)'];
			$params = [
				'from_dt' => $from . ' 00:00:00',
				'to_dt' => $to . ' 00:00:00',
			];
			if ($provider !== '') {
				$where[] = 'c.provider_code = :provider';
				$params['provider'] = $provider;
			}
			if ($campanaId > 0) {
				$where[] = 'c.id = :campana_id';
				$params['campana_id'] = $campanaId;
			}
			$whereSql = 'WHERE ' . implode(' AND ', $where);

			$kpiStmt = $db->prepare("SELECT
				COUNT(*) AS total_destinatarios,
				SUM(CASE WHEN cd.estado_envio = 'enviado' THEN 1 ELSE 0 END) AS enviados,
				SUM(CASE WHEN cd.estado_envio = 'error' THEN 1 ELSE 0 END) AS errores,
				SUM(CASE WHEN cd.estado_envio = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
				COALESCE(SUM(cd.intentos), 0) AS intentos_totales,
				COALESCE(AVG(cd.intentos), 0) AS promedio_intentos,
				COUNT(DISTINCT c.id) AS campanas_total
			FROM cci_campana_destinatarios cd
			INNER JOIN cci_campanas c ON c.id = cd.campana_id
			{$whereSql}");
			$kpiStmt->execute($params);
			$kpiRow = $kpiStmt->fetch() ?: [];

			$kpis['total_destinatarios'] = (int) ($kpiRow['total_destinatarios'] ?? 0);
			$kpis['enviados'] = (int) ($kpiRow['enviados'] ?? 0);
			$kpis['errores'] = (int) ($kpiRow['errores'] ?? 0);
			$kpis['pendientes'] = (int) ($kpiRow['pendientes'] ?? 0);
			$kpis['intentos_totales'] = (int) ($kpiRow['intentos_totales'] ?? 0);
			$kpis['promedio_intentos'] = round((float) ($kpiRow['promedio_intentos'] ?? 0), 2);
			$kpis['campanas_total'] = (int) ($kpiRow['campanas_total'] ?? 0);

			if ($kpis['total_destinatarios'] > 0) {
				$kpis['tasa_entrega'] = round(($kpis['enviados'] * 100) / $kpis['total_destinatarios'], 2);
				$kpis['tasa_error'] = round(($kpis['errores'] * 100) / $kpis['total_destinatarios'], 2);
			}

			$programadasStmt = $db->prepare('SELECT COUNT(*)
				FROM cci_campanas
				WHERE estado = "programada"
				AND fecha_programada IS NOT NULL
				AND fecha_programada <= DATE_ADD(:to_dt, INTERVAL 1 DAY)');
			$programadasStmt->execute(['to_dt' => $to . ' 00:00:00']);
			$kpis['campanas_programadas'] = (int) ($programadasStmt->fetchColumn() ?: 0);

			$dailyStmt = $db->prepare("SELECT DATE(COALESCE(cd.enviado_at, cd.updated_at, cd.created_at)) AS fecha,
				SUM(CASE WHEN cd.estado_envio = 'enviado' THEN 1 ELSE 0 END) AS enviados,
				SUM(CASE WHEN cd.estado_envio = 'error' THEN 1 ELSE 0 END) AS errores
			FROM cci_campana_destinatarios cd
			INNER JOIN cci_campanas c ON c.id = cd.campana_id
			{$whereSql}
			GROUP BY DATE(COALESCE(cd.enviado_at, cd.updated_at, cd.created_at))
			ORDER BY fecha ASC");
			$dailyStmt->execute($params);
			$seriesDiarias = $dailyStmt->fetchAll() ?: [];

			$topStmt = $db->prepare("SELECT c.id, c.nombre, c.provider_code,
				COUNT(*) AS total,
				SUM(CASE WHEN cd.estado_envio = 'enviado' THEN 1 ELSE 0 END) AS enviados,
				SUM(CASE WHEN cd.estado_envio = 'error' THEN 1 ELSE 0 END) AS errores,
				ROUND((SUM(CASE WHEN cd.estado_envio = 'enviado' THEN 1 ELSE 0 END) * 100) / NULLIF(COUNT(*), 0), 2) AS tasa_entrega
			FROM cci_campana_destinatarios cd
			INNER JOIN cci_campanas c ON c.id = cd.campana_id
			{$whereSql}
			GROUP BY c.id, c.nombre, c.provider_code
			ORDER BY tasa_entrega DESC, total DESC
			LIMIT 12");
			$topStmt->execute($params);
			$topCampanas = $topStmt->fetchAll() ?: [];

			$errorStmt = $db->prepare("SELECT COALESCE(NULLIF(TRIM(cd.ultimo_error), ''), 'Sin detalle') AS error_label, COUNT(*) AS total
			FROM cci_campana_destinatarios cd
			INNER JOIN cci_campanas c ON c.id = cd.campana_id
			{$whereSql} AND cd.estado_envio = 'error'
			GROUP BY COALESCE(NULLIF(TRIM(cd.ultimo_error), ''), 'Sin detalle')
			ORDER BY total DESC
			LIMIT 10");
			$errorStmt->execute($params);
			$erroresTop = $errorStmt->fetchAll() ?: [];

			$providerStmt = $db->prepare("SELECT c.provider_code,
				COUNT(*) AS total,
				SUM(CASE WHEN cd.estado_envio = 'enviado' THEN 1 ELSE 0 END) AS enviados,
				SUM(CASE WHEN cd.estado_envio = 'error' THEN 1 ELSE 0 END) AS errores
			FROM cci_campana_destinatarios cd
			INNER JOIN cci_campanas c ON c.id = cd.campana_id
			{$whereSql}
			GROUP BY c.provider_code
			ORDER BY total DESC");
			$providerStmt->execute($params);
			$porProveedor = $providerStmt->fetchAll() ?: [];

			$autoStmt = $db->prepare('SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN dispatch_status = "sent" THEN 1 ELSE 0 END) AS ok,
				SUM(CASE WHEN dispatch_status = "failed" THEN 1 ELSE 0 END) AS failed,
				SUM(CASE WHEN dispatch_status = "pending" THEN 1 ELSE 0 END) AS pending
			FROM cci_automation_logs
			WHERE created_at >= :from_dt
			AND created_at < DATE_ADD(:to_dt, INTERVAL 1 DAY)');
			$autoStmt->execute([
				'from_dt' => $from . ' 00:00:00',
				'to_dt' => $to . ' 00:00:00',
			]);
			$autoRow = $autoStmt->fetch() ?: [];
			$automations['total'] = (int) ($autoRow['total'] ?? 0);
			$automations['ok'] = (int) ($autoRow['ok'] ?? 0);
			$automations['failed'] = (int) ($autoRow['failed'] ?? 0);
			$automations['pending'] = (int) ($autoRow['pending'] ?? 0);
		} catch (Throwable $e) {
			// Mantener pantalla operativa incluso si alguna consulta falla.
		}

		$this->view('cci/reportes', compact('filters', 'kpis', 'campanas', 'providers', 'seriesDiarias', 'topCampanas', 'erroresTop', 'porProveedor', 'automations'), [
			'title' => 'Centro de Comunicaciones - Reportes',
		]);
	}

	public function updateConversationEstado(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones?selected_id=' . max(1, $id));
		}

		$estado = strtolower(trim((string) ($_POST['estado'] ?? '')));
		if (!in_array($estado, ['activo', 'cerrado'], true)) {
			set_flash('error', 'Estado de conversación no válido.');
			redirect('cci/conversaciones?selected_id=' . max(1, $id));
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$before = $db->prepare('SELECT estado FROM bot_conversaciones WHERE id = :id AND canal = "freshchat" LIMIT 1');
		$before->execute(['id' => $id]);
		$estadoActual = $before->fetchColumn();
		if ($estadoActual === false) {
			set_flash('error', 'Conversación Freshchat no encontrada.');
			redirect('cci/conversaciones');
		}

		$db->prepare('UPDATE bot_conversaciones SET estado = :estado, updated_at = NOW() WHERE id = :id')
			->execute(['estado' => $estado, 'id' => $id]);
		AuditLogger::log('UPDATE', 'cci_conversacion_estado', $id, ['estado' => $estadoActual], ['estado' => $estado]);

		set_flash('success', $estado === 'cerrado' ? 'Conversación cerrada.' : 'Conversación reabierta.');
		redirect('cci/conversaciones?selected_id=' . max(1, $id));
	}

	public function storeConversationNote(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$nota = trim((string) ($_POST['nota'] ?? ''));
		if ($nota === '') {
			set_flash('error', 'La nota privada es obligatoria.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$noteId = (new CciConversationNote())->create([
			'conversacion_id' => (int) $id,
			'nota' => mb_substr($nota, 0, 5000),
			'usuario_id' => Auth::id(),
			'created_at' => date('Y-m-d H:i:s'),
		]);
		AuditLogger::log('CREATE', 'cci_conversacion_notas', $noteId, null, [
			'conversacion_id' => (int) $id,
			'nota' => mb_substr($nota, 0, 280),
		]);
		set_flash('success', 'Nota privada guardada.');
		redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
	}

	public function contactos(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$contactos = [];
		try {
			$sql = "SELECT c.id, c.nombre, c.apellido, c.email, c.estado, c.created_at, c.updated_at,
				COALESCE(tc.telefono, '') AS numero,
				COALESCE(i.ciudad, '') AS ciudad,
				COALESCE(i.provincia, '') AS provincia,
				COALESCE(i.origen, '') AS origen,
				COALESCE(i.convertido, 0) AS convertido,
				COALESCE(i.created_at, c.created_at) AS fecha_creacion
			FROM contactos c
			LEFT JOIN interesados i ON i.contacto_id = c.id
			LEFT JOIN (
				SELECT x.contacto_id, x.telefono
				FROM telefonos_contacto x
				INNER JOIN (
					SELECT contacto_id, MIN(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) y ON y.first_id = x.id
			) tc ON tc.contacto_id = c.id
			ORDER BY c.id DESC
			LIMIT 500";
			$contactos = $db->query($sql)->fetchAll() ?: [];
		} catch (Throwable $e) {
			$contactos = [];
		}

		$this->view('cci/contactos', compact('contactos'), [
			'title' => 'Centro de Comunicaciones - Contactos',
		]);
	}

	public function potenciales(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);
		$this->ensureProspectByInteraction($db);

		$items = [];
		$usuarios = [];
		$estados = [];
		try {
			$items = $db->query("SELECT i.id, i.contacto_id, i.origen, i.estado_id, i.created_at, i.updated_at,
				COALESCE(i.carrera, '') AS carrera,
				COALESCE(i.modalidad, '') AS modalidad,
				COALESCE(i.provincia, '') AS provincia,
				COALESCE(i.ciudad, '') AS ciudad,
				COALESCE(i.convertido, 0) AS convertido,
				COALESCE(c.nombre, '') AS nombre,
				COALESCE(c.apellido, '') AS apellido,
				COALESCE(c.email, '') AS email,
				COALESCE(pe.nombre, 'Sin etapa') AS estado_nombre,
				COALESCE(tc.telefono, '') AS telefono
			FROM interesados i
			INNER JOIN contactos c ON c.id = i.contacto_id
			LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
			LEFT JOIN (
				SELECT x.contacto_id, x.telefono
				FROM telefonos_contacto x
				INNER JOIN (
					SELECT contacto_id, MIN(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) y ON y.first_id = x.id
			) tc ON tc.contacto_id = c.id
			WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0
			ORDER BY i.updated_at DESC, i.id DESC")->fetchAll() ?: [];

			$usuarios = $db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre ASC")->fetchAll() ?: [];
			$estados = $db->query("SELECT id, nombre FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC")->fetchAll() ?: [];
		} catch (Throwable $e) {
			$items = [];
			$usuarios = [];
			$estados = [];
		}

		$this->view('cci/potenciales', compact('items', 'usuarios', 'estados'), [
			'title' => 'Centro de Comunicaciones - Clientes Potenciales',
		]);
	}

	public function updatePotencial(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/clientes-potenciales');
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$stmt = $db->prepare('SELECT * FROM interesados WHERE id = :id LIMIT 1');
		$stmt->execute(['id' => $id]);
		$before = $stmt->fetch();
		if (!$before) {
			set_flash('error', 'Cliente potencial no encontrado.');
			redirect('cci/clientes-potenciales');
		}

		$estadoId = (int) ($_POST['estado_id'] ?? 0);
		$carrera = trim((string) ($_POST['carrera'] ?? ''));
		$modalidad = trim((string) ($_POST['modalidad'] ?? ''));
		$provincia = trim((string) ($_POST['provincia'] ?? ''));
		$ciudad = trim((string) ($_POST['ciudad'] ?? ''));

		$up = $db->prepare('UPDATE interesados
			SET estado_id = :estado_id,
				carrera = :carrera,
				modalidad = :modalidad,
				provincia = :provincia,
				ciudad = :ciudad,
				updated_at = NOW()
			WHERE id = :id
			LIMIT 1');
		$up->execute([
			'estado_id' => $estadoId > 0 ? $estadoId : null,
			'carrera' => $carrera !== '' ? mb_substr($carrera, 0, 180) : null,
			'modalidad' => $modalidad !== '' ? mb_substr($modalidad, 0, 80) : null,
			'provincia' => $provincia !== '' ? mb_substr($provincia, 0, 120) : null,
			'ciudad' => $ciudad !== '' ? mb_substr($ciudad, 0, 120) : null,
			'id' => $id,
		]);

		$stmt->execute(['id' => $id]);
		$after = $stmt->fetch();
		AuditLogger::log('UPDATE', 'interesados', $id, $before, $after);
		$this->fireAutomationEvent('lead_updated', [
			'lead_id' => $id,
			'contact_id' => (int) ($after['contacto_id'] ?? 0),
			'estado_id' => (int) ($after['estado_id'] ?? 0),
			'updated_by' => Auth::id(),
		]);

		set_flash('success', 'Cliente potencial actualizado correctamente.');
		redirect('cci/clientes-potenciales');
	}

	public function campanas(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$selectedId = (int) ($_GET['selected_id'] ?? 0);
		$campanas = [];
		$plantillas = [];
		$destinatarios = [];

		try {
			$campanas = $db->query("SELECT c.id, c.nombre, c.canal, c.provider_code, c.estado, c.fecha_programada, c.updated_at,
				COUNT(d.id) AS total,
				SUM(CASE WHEN d.estado_envio = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
				SUM(CASE WHEN d.estado_envio = 'enviado' THEN 1 ELSE 0 END) AS enviados,
				SUM(CASE WHEN d.estado_envio = 'error' THEN 1 ELSE 0 END) AS errores
			FROM cci_campanas c
			LEFT JOIN cci_campana_destinatarios d ON d.campana_id = c.id
			GROUP BY c.id, c.nombre, c.canal, c.provider_code, c.estado, c.fecha_programada, c.updated_at
			ORDER BY c.id DESC
			LIMIT 200")->fetchAll() ?: [];

			$plantillas = $db->query("SELECT id, nombre, canal FROM cci_plantillas WHERE estado = 'activo' ORDER BY nombre ASC")->fetchAll() ?: [];

			if ($selectedId <= 0 && !empty($campanas)) {
				$selectedId = (int) ($campanas[0]['id'] ?? 0);
			}

			if ($selectedId > 0) {
				$destStmt = $db->prepare("SELECT id, nombre, telefono, estado_envio, intentos, ultimo_error, external_message_id, enviado_at, updated_at
					FROM cci_campana_destinatarios
					WHERE campana_id = :id
					ORDER BY id DESC
					LIMIT 500");
				$destStmt->execute(['id' => $selectedId]);
				$destinatarios = $destStmt->fetchAll() ?: [];
			}
		} catch (Throwable $e) {
			$campanas = [];
			$plantillas = [];
			$destinatarios = [];
		}

		$this->view('cci/campanas', compact('campanas', 'plantillas', 'selectedId', 'destinatarios'), [
			'title' => 'Centro de Comunicaciones - Campañas',
		]);
	}

	public function storeCampana(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/campanas');
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
		$canal = trim((string) ($_POST['canal'] ?? 'whatsapp'));
		$provider = trim((string) ($_POST['provider_code'] ?? 'whatchimp'));
		$plantillaId = (int) ($_POST['plantilla_id'] ?? 0);
		$mensajeBase = trim((string) ($_POST['mensaje_base'] ?? ''));
		$fechaProgramada = trim((string) ($_POST['fecha_programada'] ?? ''));

		if ($nombre === '') {
			set_flash('error', 'El nombre de campaña es obligatorio.');
			redirect('cci/campanas');
		}
		if ($plantillaId <= 0 && $mensajeBase === '') {
			set_flash('error', 'Define una plantilla o un mensaje base para la campaña.');
			redirect('cci/campanas');
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$fechaSql = null;
		if ($fechaProgramada !== '') {
			$ts = strtotime($fechaProgramada);
			if ($ts !== false) {
				$fechaSql = date('Y-m-d H:i:s', $ts);
			}
		}

		$stmt = $db->prepare('INSERT INTO cci_campanas (nombre, descripcion, canal, provider_code, plantilla_id, mensaje_base, estado, fecha_programada, created_by, updated_by, created_at, updated_at)
			VALUES (:nombre, :descripcion, :canal, :provider_code, :plantilla_id, :mensaje_base, :estado, :fecha_programada, :created_by, :updated_by, NOW(), NOW())');
		$stmt->execute([
			'nombre' => mb_substr($nombre, 0, 160),
			'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 255) : null,
			'canal' => mb_substr($canal !== '' ? $canal : 'whatsapp', 0, 40),
			'provider_code' => mb_substr($provider !== '' ? $provider : 'whatchimp', 0, 50),
			'plantilla_id' => $plantillaId > 0 ? $plantillaId : null,
			'mensaje_base' => $mensajeBase !== '' ? mb_substr($mensajeBase, 0, 20000) : null,
			'estado' => $fechaSql !== null ? 'programada' : 'borrador',
			'fecha_programada' => $fechaSql,
			'created_by' => Auth::id(),
			'updated_by' => Auth::id(),
		]);

		$id = (int) $db->lastInsertId();
		AuditLogger::log('CREATE', 'cci_campanas', $id, null, [
			'nombre' => $nombre,
			'provider_code' => $provider,
			'canal' => $canal,
		]);
		$this->fireAutomationEvent('campaign_created', [
			'campaign_id' => $id,
			'name' => $nombre,
			'provider' => $provider,
			'channel' => $canal,
			'scheduled_at' => $fechaSql,
			'created_by' => Auth::id(),
		]);

		set_flash('success', 'Campaña creada correctamente.');
		redirect('cci/campanas?selected_id=' . $id);
	}

	public function addCampanaDestinatarios(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		$raw = trim((string) ($_POST['destinatarios'] ?? ''));
		if ($raw === '') {
			set_flash('error', 'Debes ingresar destinatarios para cargar el lote.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		$rows = $this->parseCampaignRecipients($raw);
		if (empty($rows)) {
			set_flash('error', 'No se detectaron teléfonos válidos en el lote.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$campStmt = $db->prepare('SELECT * FROM cci_campanas WHERE id = :id LIMIT 1');
		$campStmt->execute(['id' => $id]);
		$campana = $campStmt->fetch();
		if (!$campana) {
			set_flash('error', 'Campaña no encontrada.');
			redirect('cci/campanas');
		}
		if (trim((string) ($campana['provider_code'] ?? 'whatchimp')) !== 'whatchimp') {
			set_flash('error', 'Proveedor de campaña no soportado todavía. Solo Whatchimp está habilitado en esta fase.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		$up = $db->prepare('INSERT INTO cci_campana_destinatarios (campana_id, contacto_id, nombre, telefono, estado_envio, intentos, created_at, updated_at)
			VALUES (:campana_id, :contacto_id, :nombre, :telefono, "pendiente", 0, NOW(), NOW())
			ON DUPLICATE KEY UPDATE
				contacto_id = VALUES(contacto_id),
				nombre = VALUES(nombre),
				estado_envio = IF(cci_campana_destinatarios.estado_envio = "enviado", "enviado", "pendiente"),
				updated_at = NOW()');

		$count = 0;
		foreach ($rows as $row) {
			$contactId = $this->ensureContactByPhone($db, (string) $row['telefono'], (string) $row['nombre']);
			$up->execute([
				'campana_id' => $id,
				'contacto_id' => $contactId,
				'nombre' => trim((string) $row['nombre']) !== '' ? mb_substr((string) $row['nombre'], 0, 160) : null,
				'telefono' => (string) $row['telefono'],
			]);
			$count++;
		}

		$db->prepare('UPDATE cci_campanas SET updated_by = :updated_by, updated_at = NOW() WHERE id = :id LIMIT 1')
			->execute(['updated_by' => Auth::id(), 'id' => $id]);

		AuditLogger::log('CREATE', 'cci_campana_destinatarios', null, null, [
			'campana_id' => $id,
			'cargados' => $count,
		]);
		$this->fireAutomationEvent('campaign_recipients_loaded', [
			'campaign_id' => $id,
			'loaded' => $count,
			'provider' => (string) ($campana['provider_code'] ?? 'whatchimp'),
			'updated_by' => Auth::id(),
		]);

		set_flash('success', 'Lote cargado correctamente. Destinatarios procesados: ' . $count . '.');
		redirect('cci/campanas?selected_id=' . max(1, (int) $id));
	}

	private function campaignRetryDelayMinutes(int $attempts): int
	{
		$attempts = max(1, $attempts);
		$delay = (int) (5 * (2 ** max(0, $attempts - 1))); // 5,10,20,40...
		return min(720, $delay);
	}

	private function pickRetryRows(PDO $db, int $campaignId, int $remaining, int $retryMax): array
	{
		if ($remaining <= 0) {
			return [];
		}

		$stmt = $db->prepare('SELECT *
			FROM cci_campana_destinatarios
			WHERE campana_id = :campana_id
				AND estado_envio = "error"
				AND intentos < :retry_max
			ORDER BY updated_at ASC, id ASC
			LIMIT :lim');
		$stmt->bindValue(':campana_id', $campaignId, PDO::PARAM_INT);
		$stmt->bindValue(':retry_max', $retryMax, PDO::PARAM_INT);
		$stmt->bindValue(':lim', $remaining, PDO::PARAM_INT);
		$stmt->execute();
		$candidates = $stmt->fetchAll() ?: [];

		$out = [];
		$nowTs = time();
		foreach ($candidates as $row) {
			$updatedAt = strtotime((string) ($row['updated_at'] ?? '')) ?: 0;
			$attempts = (int) ($row['intentos'] ?? 0);
			$nextTs = $updatedAt + ($this->campaignRetryDelayMinutes(max(1, $attempts)) * 60);
			if ($updatedAt <= 0 || $nowTs >= $nextTs) {
				$out[] = $row;
			}
		}

		return $out;
	}

	private function processCampaignBatch(PDO $db, array $campana, int $batch, int $retryMax, bool $fromScheduler = false, ?int $actorUserId = null): array
	{
		$campaignId = (int) ($campana['id'] ?? 0);
		if ($campaignId <= 0) {
			return ['sent' => 0, 'errors' => 0, 'remaining' => 0, 'state' => 'borrador'];
		}

		$destStmt = $db->prepare('SELECT * FROM cci_campana_destinatarios WHERE campana_id = :campana_id AND estado_envio = "pendiente" ORDER BY id ASC LIMIT :batch');
		$destStmt->bindValue(':campana_id', $campaignId, PDO::PARAM_INT);
		$destStmt->bindValue(':batch', $batch, PDO::PARAM_INT);
		$destStmt->execute();
		$rows = $destStmt->fetchAll() ?: [];
		if (count($rows) < $batch) {
			$retryRows = $this->pickRetryRows($db, $campaignId, $batch - count($rows), $retryMax);
			$rows = array_merge($rows, $retryRows);
		}

		if (empty($rows)) {
			return ['sent' => 0, 'errors' => 0, 'remaining' => 0, 'state' => (string) ($campana['estado'] ?? 'borrador')];
		}

		$templateText = $this->resolveCampanaTemplateText($db, $campana);
		if ($templateText === '') {
			return ['sent' => 0, 'errors' => count($rows), 'remaining' => count($rows), 'state' => (string) ($campana['estado'] ?? 'borrador')];
		}

		$userId = $actorUserId ?? Auth::id();
		$service = new WhatchimpService();
		$sent = 0;
		$errors = 0;

		$db->prepare('UPDATE cci_campanas SET estado = "enviando", updated_by = :updated_by, updated_at = NOW() WHERE id = :id LIMIT 1')
			->execute(['updated_by' => $userId, 'id' => $campaignId]);

		$okStmt = $db->prepare('UPDATE cci_campana_destinatarios
			SET estado_envio = "enviado",
				intentos = intentos + 1,
				external_message_id = :external_message_id,
				ultimo_error = NULL,
				enviado_at = NOW(),
				updated_at = NOW()
			WHERE id = :id
			LIMIT 1');

		$errStmt = $db->prepare('UPDATE cci_campana_destinatarios
			SET estado_envio = "error",
				intentos = intentos + 1,
				ultimo_error = :ultimo_error,
				updated_at = NOW()
			WHERE id = :id
			LIMIT 1');

		foreach ($rows as $row) {
			$destId = (int) ($row['id'] ?? 0);
			$phone = (string) ($row['telefono'] ?? '');
			$text = $this->applyCampaignVariables($templateText, $row);

			if ($phone === '' || $text === '') {
				$errStmt->execute([
					'ultimo_error' => 'Datos incompletos para envío.',
					'id' => $destId,
				]);
				$errors++;
				continue;
			}

			$resp = $service->sendTextMessage($phone, $text, [
				'campaign_id' => $campaignId,
				'recipient_id' => $destId,
				'user_id' => $userId,
				'scheduler' => $fromScheduler ? 1 : 0,
			]);

			if (!($resp['ok'] ?? false)) {
				$errStmt->execute([
					'ultimo_error' => mb_substr((string) ($resp['error'] ?? 'Error desconocido'), 0, 255),
					'id' => $destId,
				]);
				$errors++;
				continue;
			}

			$externalId = trim((string) ($resp['message_id'] ?? ''));
			$okStmt->execute([
				'external_message_id' => $externalId !== '' ? $externalId : null,
				'id' => $destId,
			]);

			$contactId = $this->ensureContactByPhone($db, $phone, (string) ($row['nombre'] ?? ''));
			$conversationId = $this->ensureConversation($db, $contactId, 'whatsapp');
			if ($conversationId !== null && $conversationId > 0) {
				$msgId = $this->insertBotMessage($db, $conversationId, $text, true, date('Y-m-d H:i:s'), 'texto');
				if ($externalId !== '' && $msgId > 0) {
					$this->saveMessageRef($db, 'whatchimp', $externalId, $conversationId, $msgId, 'out');
				}
			}

			$sent++;
		}

		$remainingStmt = $db->prepare('SELECT COUNT(*)
			FROM cci_campana_destinatarios
			WHERE campana_id = :campana_id
				AND (
					estado_envio = "pendiente"
					OR (estado_envio = "error" AND intentos < :retry_max)
				)');
		$remainingStmt->bindValue(':campana_id', $campaignId, PDO::PARAM_INT);
		$remainingStmt->bindValue(':retry_max', $retryMax, PDO::PARAM_INT);
		$remainingStmt->execute();
		$remaining = (int) ($remainingStmt->fetchColumn() ?: 0);

		$newState = $remaining > 0 ? 'enviando' : 'completada';
		$db->prepare('UPDATE cci_campanas SET estado = :estado, updated_by = :updated_by, updated_at = NOW() WHERE id = :id LIMIT 1')
			->execute(['estado' => $newState, 'updated_by' => $userId, 'id' => $campaignId]);

		AuditLogger::log('UPDATE', 'cci_campanas', $campaignId, null, [
			'enviados' => $sent,
			'errores' => $errors,
			'pendientes_restantes' => $remaining,
			'batch' => $batch,
			'retry_max' => $retryMax,
			'scheduler' => $fromScheduler ? 1 : 0,
		]);

		$this->fireAutomationEvent('campaign_batch_processed', [
			'campaign_id' => $campaignId,
			'provider' => (string) ($campana['provider_code'] ?? 'whatchimp'),
			'batch' => $batch,
			'retry_max' => $retryMax,
			'sent' => $sent,
			'errors' => $errors,
			'remaining' => $remaining,
			'state' => $newState,
			'updated_by' => $userId,
			'scheduler' => $fromScheduler,
		]);

		return [
			'sent' => $sent,
			'errors' => $errors,
			'remaining' => $remaining,
			'state' => $newState,
		];
	}

	public function sendCampana(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$campStmt = $db->prepare('SELECT * FROM cci_campanas WHERE id = :id LIMIT 1');
		$campStmt->execute(['id' => $id]);
		$campana = $campStmt->fetch();
		if (!$campana) {
			set_flash('error', 'Campaña no encontrada.');
			redirect('cci/campanas');
		}
		if (trim((string) ($campana['provider_code'] ?? 'whatchimp')) !== 'whatchimp') {
			set_flash('error', 'Proveedor de campaña no soportado todavía. Solo Whatchimp está habilitado en esta fase.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		$batch = max(1, min(500, (int) ($_POST['batch_size'] ?? 100)));
		$retryMax = max(1, min(10, (int) ($_POST['retry_max'] ?? 3)));
		$result = $this->processCampaignBatch($db, is_array($campana) ? $campana : [], $batch, $retryMax, false, Auth::id());

		if ((int) ($result['sent'] ?? 0) === 0 && (int) ($result['errors'] ?? 0) === 0) {
			set_flash('error', 'No hay destinatarios elegibles para envío/reintento en esta campaña.');
			redirect('cci/campanas?selected_id=' . max(1, (int) $id));
		}

		set_flash('success', 'Ejecución de campaña finalizada. Enviados: ' . (int) ($result['sent'] ?? 0) . ', errores: ' . (int) ($result['errors'] ?? 0) . ', pendientes: ' . (int) ($result['remaining'] ?? 0) . '.');
		redirect('cci/campanas?selected_id=' . max(1, (int) $id));
	}

	public function runScheduledCampaignsInternal(int $limitCampaigns = 5, int $batchSize = 100, int $retryMax = 3): array
	{
		$db = $this->db();
		$this->ensureCciTables($db);

		$limitCampaigns = max(1, min(50, $limitCampaigns));
		$batchSize = max(1, min(500, $batchSize));
		$retryMax = max(1, min(10, $retryMax));

		$stmt = $db->prepare("SELECT *
			FROM cci_campanas
			WHERE provider_code = 'whatchimp'
				AND estado IN ('programada', 'enviando')
				AND (fecha_programada IS NULL OR fecha_programada <= NOW())
			ORDER BY COALESCE(fecha_programada, created_at) ASC, id ASC
			LIMIT :lim");
		$stmt->bindValue(':lim', $limitCampaigns, PDO::PARAM_INT);
		$stmt->execute();
		$campaigns = $stmt->fetchAll() ?: [];

		$summary = [
			'processed_campaigns' => 0,
			'sent' => 0,
			'errors' => 0,
			'remaining' => 0,
			'items' => [],
		];

		foreach ($campaigns as $campana) {
			$result = $this->processCampaignBatch($db, is_array($campana) ? $campana : [], $batchSize, $retryMax, true, null);
			$summary['processed_campaigns']++;
			$summary['sent'] += (int) ($result['sent'] ?? 0);
			$summary['errors'] += (int) ($result['errors'] ?? 0);
			$summary['remaining'] += (int) ($result['remaining'] ?? 0);
			$summary['items'][] = [
				'campaign_id' => (int) ($campana['id'] ?? 0),
				'name' => (string) ($campana['nombre'] ?? ''),
				'state' => (string) ($result['state'] ?? ''),
				'sent' => (int) ($result['sent'] ?? 0),
				'errors' => (int) ($result['errors'] ?? 0),
				'remaining' => (int) ($result['remaining'] ?? 0),
			];
		}

		return $summary;
	}

	public function processScheduledCampanas(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/campanas');
		}

		$limit = max(1, min(50, (int) ($_POST['limit_campaigns'] ?? 5)));
		$batch = max(1, min(500, (int) ($_POST['batch_size'] ?? 100)));
		$retryMax = max(1, min(10, (int) ($_POST['retry_max'] ?? 3)));

		$result = $this->runScheduledCampaignsInternal($limit, $batch, $retryMax);
		set_flash('success', 'Procesamiento programado ejecutado. Campañas: ' . (int) ($result['processed_campaigns'] ?? 0) . ', enviados: ' . (int) ($result['sent'] ?? 0) . ', errores: ' . (int) ($result['errors'] ?? 0) . '.');
		redirect('cci/campanas');
	}

	public function plantillas(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$q = trim((string) ($_GET['q'] ?? ''));
		$items = [];
		try {
			$sql = "SELECT id, nombre, canal, categoria, contenido, variables_json, estado, created_at, updated_at
				FROM cci_plantillas
				WHERE estado = 'activo'";
			$params = [];
			if ($q !== '') {
				$sql .= " AND (nombre LIKE :q OR categoria LIKE :q OR contenido LIKE :q)";
				$params['q'] = '%' . $q . '%';
			}
			$sql .= " ORDER BY updated_at DESC, id DESC LIMIT 300";
			$stmt = $db->prepare($sql);
			$stmt->execute($params);
			$items = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$items = [];
		}

		$this->view('cci/plantillas', compact('items', 'q'), [
			'title' => 'Centro de Comunicaciones - Plantillas',
		]);
	}

	public function storePlantilla(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/plantillas');
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$canal = trim((string) ($_POST['canal'] ?? 'whatsapp'));
		$categoria = trim((string) ($_POST['categoria'] ?? ''));
		$contenido = trim((string) ($_POST['contenido'] ?? ''));
		$variablesRaw = trim((string) ($_POST['variables'] ?? ''));

		if ($nombre === '' || $contenido === '') {
			set_flash('error', 'Nombre y contenido son obligatorios para la plantilla.');
			redirect('cci/plantillas');
		}

		$variables = [];
		if ($variablesRaw !== '') {
			$parts = preg_split('/\s*,\s*/', $variablesRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			foreach ($parts as $p) {
				$key = trim($p);
				if ($key !== '') {
					$variables[] = $key;
				}
			}
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$stmt = $db->prepare('INSERT INTO cci_plantillas (nombre, canal, categoria, contenido, variables_json, estado, created_by, updated_by, created_at, updated_at)
			VALUES (:nombre, :canal, :categoria, :contenido, :variables_json, "activo", :created_by, :updated_by, NOW(), NOW())');
		$stmt->execute([
			'nombre' => mb_substr($nombre, 0, 150),
			'canal' => mb_substr($canal !== '' ? $canal : 'whatsapp', 0, 40),
			'categoria' => $categoria !== '' ? mb_substr($categoria, 0, 80) : null,
			'contenido' => mb_substr($contenido, 0, 20000),
			'variables_json' => !empty($variables) ? json_encode(array_values(array_unique($variables)), JSON_UNESCAPED_UNICODE) : null,
			'created_by' => Auth::id(),
			'updated_by' => Auth::id(),
		]);

		$id = (int) $db->lastInsertId();
		AuditLogger::log('CREATE', 'cci_plantillas', $id, null, [
			'nombre' => $nombre,
			'canal' => $canal,
			'categoria' => $categoria,
		]);

		set_flash('success', 'Plantilla creada correctamente.');
		redirect('cci/plantillas');
	}

	public function updatePlantilla(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/plantillas');
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$beforeStmt = $db->prepare('SELECT * FROM cci_plantillas WHERE id = :id LIMIT 1');
		$beforeStmt->execute(['id' => $id]);
		$before = $beforeStmt->fetch();
		if (!$before) {
			set_flash('error', 'Plantilla no encontrada.');
			redirect('cci/plantillas');
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$canal = trim((string) ($_POST['canal'] ?? 'whatsapp'));
		$categoria = trim((string) ($_POST['categoria'] ?? ''));
		$contenido = trim((string) ($_POST['contenido'] ?? ''));
		$variablesRaw = trim((string) ($_POST['variables'] ?? ''));

		if ($nombre === '' || $contenido === '') {
			set_flash('error', 'Nombre y contenido son obligatorios para la plantilla.');
			redirect('cci/plantillas');
		}

		$variables = [];
		if ($variablesRaw !== '') {
			$parts = preg_split('/\s*,\s*/', $variablesRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			foreach ($parts as $p) {
				$key = trim($p);
				if ($key !== '') {
					$variables[] = $key;
				}
			}
		}

		$up = $db->prepare('UPDATE cci_plantillas
			SET nombre = :nombre,
				canal = :canal,
				categoria = :categoria,
				contenido = :contenido,
				variables_json = :variables_json,
				updated_by = :updated_by,
				updated_at = NOW()
			WHERE id = :id
			LIMIT 1');
		$up->execute([
			'nombre' => mb_substr($nombre, 0, 150),
			'canal' => mb_substr($canal !== '' ? $canal : 'whatsapp', 0, 40),
			'categoria' => $categoria !== '' ? mb_substr($categoria, 0, 80) : null,
			'contenido' => mb_substr($contenido, 0, 20000),
			'variables_json' => !empty($variables) ? json_encode(array_values(array_unique($variables)), JSON_UNESCAPED_UNICODE) : null,
			'updated_by' => Auth::id(),
			'id' => $id,
		]);

		$beforeStmt->execute(['id' => $id]);
		$after = $beforeStmt->fetch();
		AuditLogger::log('UPDATE', 'cci_plantillas', $id, $before, $after);

		set_flash('success', 'Plantilla actualizada correctamente.');
		redirect('cci/plantillas');
	}

	public function deletePlantilla(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/plantillas');
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$beforeStmt = $db->prepare('SELECT * FROM cci_plantillas WHERE id = :id LIMIT 1');
		$beforeStmt->execute(['id' => $id]);
		$before = $beforeStmt->fetch();
		if (!$before) {
			set_flash('error', 'Plantilla no encontrada.');
			redirect('cci/plantillas');
		}

		$db->prepare('UPDATE cci_plantillas SET estado = "inactivo", updated_by = :updated_by, updated_at = NOW() WHERE id = :id LIMIT 1')
			->execute(['updated_by' => Auth::id(), 'id' => $id]);

		$beforeStmt->execute(['id' => $id]);
		$after = $beforeStmt->fetch();
		AuditLogger::log('DELETE', 'cci_plantillas', $id, $before, $after);

		set_flash('success', 'Plantilla archivada correctamente.');
		redirect('cci/plantillas');
	}

	public function respuestasRapidas(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$q = trim((string) ($_GET['q'] ?? ''));
		$items = [];
		$categorias = [];
		try {
			$sql = "SELECT id, categoria, atajo, titulo, contenido, favorito, uso_count, estado, created_at, updated_at
				FROM cci_respuestas_rapidas
				WHERE estado = 'activo'";
			$params = [];
			if ($q !== '') {
				$sql .= " AND (titulo LIKE :q OR categoria LIKE :q OR atajo LIKE :q OR contenido LIKE :q)";
				$params['q'] = '%' . $q . '%';
			}
			$sql .= " ORDER BY favorito DESC, uso_count DESC, updated_at DESC, id DESC LIMIT 500";
			$stmt = $db->prepare($sql);
			$stmt->execute($params);
			$items = $stmt->fetchAll() ?: [];

			$catStmt = $db->query("SELECT DISTINCT categoria FROM cci_respuestas_rapidas WHERE estado = 'activo' AND categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC");
			$categorias = $catStmt ? ($catStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
		} catch (Throwable $e) {
			$items = [];
			$categorias = [];
		}

		$this->view('cci/respuestas_rapidas', compact('items', 'categorias', 'q'), [
			'title' => 'Centro de Comunicaciones - Respuestas rápidas',
		]);
	}

	public function storeRespuestaRapida(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/respuestas-rapidas');
		}

		$titulo = trim((string) ($_POST['titulo'] ?? ''));
		$contenido = trim((string) ($_POST['contenido'] ?? ''));
		$categoria = trim((string) ($_POST['categoria'] ?? ''));
		$atajo = trim((string) ($_POST['atajo'] ?? ''));
		$favorito = ((int) ($_POST['favorito'] ?? 0)) === 1 ? 1 : 0;

		if ($titulo === '' || $contenido === '') {
			set_flash('error', 'Título y contenido son obligatorios para la respuesta rápida.');
			redirect('cci/respuestas-rapidas');
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$stmt = $db->prepare('INSERT INTO cci_respuestas_rapidas (categoria, atajo, titulo, contenido, favorito, uso_count, estado, created_by, updated_by, created_at, updated_at)
			VALUES (:categoria, :atajo, :titulo, :contenido, :favorito, 0, "activo", :created_by, :updated_by, NOW(), NOW())');
		$stmt->execute([
			'categoria' => $categoria !== '' ? mb_substr($categoria, 0, 80) : null,
			'atajo' => $atajo !== '' ? mb_substr($atajo, 0, 50) : null,
			'titulo' => mb_substr($titulo, 0, 160),
			'contenido' => mb_substr($contenido, 0, 20000),
			'favorito' => $favorito,
			'created_by' => Auth::id(),
			'updated_by' => Auth::id(),
		]);

		$id = (int) $db->lastInsertId();
		AuditLogger::log('CREATE', 'cci_respuestas_rapidas', $id, null, [
			'titulo' => $titulo,
			'categoria' => $categoria,
			'atajo' => $atajo,
		]);

		set_flash('success', 'Respuesta rápida creada correctamente.');
		redirect('cci/respuestas-rapidas');
	}

	public function updateRespuestaRapida(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/respuestas-rapidas');
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$beforeStmt = $db->prepare('SELECT * FROM cci_respuestas_rapidas WHERE id = :id LIMIT 1');
		$beforeStmt->execute(['id' => $id]);
		$before = $beforeStmt->fetch();
		if (!$before) {
			set_flash('error', 'Respuesta rápida no encontrada.');
			redirect('cci/respuestas-rapidas');
		}

		$titulo = trim((string) ($_POST['titulo'] ?? ''));
		$contenido = trim((string) ($_POST['contenido'] ?? ''));
		$categoria = trim((string) ($_POST['categoria'] ?? ''));
		$atajo = trim((string) ($_POST['atajo'] ?? ''));
		$favorito = ((int) ($_POST['favorito'] ?? 0)) === 1 ? 1 : 0;

		if ($titulo === '' || $contenido === '') {
			set_flash('error', 'Título y contenido son obligatorios para la respuesta rápida.');
			redirect('cci/respuestas-rapidas');
		}

		$up = $db->prepare('UPDATE cci_respuestas_rapidas
			SET categoria = :categoria,
				atajo = :atajo,
				titulo = :titulo,
				contenido = :contenido,
				favorito = :favorito,
				updated_by = :updated_by,
				updated_at = NOW()
			WHERE id = :id
			LIMIT 1');
		$up->execute([
			'categoria' => $categoria !== '' ? mb_substr($categoria, 0, 80) : null,
			'atajo' => $atajo !== '' ? mb_substr($atajo, 0, 50) : null,
			'titulo' => mb_substr($titulo, 0, 160),
			'contenido' => mb_substr($contenido, 0, 20000),
			'favorito' => $favorito,
			'updated_by' => Auth::id(),
			'id' => $id,
		]);

		$beforeStmt->execute(['id' => $id]);
		$after = $beforeStmt->fetch();
		AuditLogger::log('UPDATE', 'cci_respuestas_rapidas', $id, $before, $after);

		set_flash('success', 'Respuesta rápida actualizada correctamente.');
		redirect('cci/respuestas-rapidas');
	}

	public function deleteRespuestaRapida(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/respuestas-rapidas');
		}

		$db = $this->db();
		$this->ensureCciTables($db);
		$beforeStmt = $db->prepare('SELECT * FROM cci_respuestas_rapidas WHERE id = :id LIMIT 1');
		$beforeStmt->execute(['id' => $id]);
		$before = $beforeStmt->fetch();
		if (!$before) {
			set_flash('error', 'Respuesta rápida no encontrada.');
			redirect('cci/respuestas-rapidas');
		}

		$db->prepare('UPDATE cci_respuestas_rapidas SET estado = "inactivo", updated_by = :updated_by, updated_at = NOW() WHERE id = :id LIMIT 1')
			->execute(['updated_by' => Auth::id(), 'id' => $id]);

		$beforeStmt->execute(['id' => $id]);
		$after = $beforeStmt->fetch();
		AuditLogger::log('DELETE', 'cci_respuestas_rapidas', $id, $before, $after);

		set_flash('success', 'Respuesta rápida archivada correctamente.');
		redirect('cci/respuestas-rapidas');
	}

	private function fetchCciAdvisorOptions(PDO $db): array
	{
		try {
			$rows = $db->query('SELECT a.id, a.nombre, m.usuario_id AS mapped_usuario_id, u.nombre AS mapped_usuario_nombre
				FROM crm_prospect_asesores a
				LEFT JOIN cci_asesor_usuario_map m ON m.crm_asesor_id = a.id
				LEFT JOIN usuarios u ON u.id = m.usuario_id AND u.estado = "activo"
				WHERE a.estado = "activo"
				ORDER BY a.nombre ASC')->fetchAll() ?: [];
			$users = $db->query('SELECT id, nombre, email FROM usuarios WHERE estado = "activo" ORDER BY nombre ASC')->fetchAll() ?: [];
			$options = [];
			foreach ($rows as $row) {
				$userId = (int) ($row['mapped_usuario_id'] ?? 0);
				$userName = trim((string) ($row['mapped_usuario_nombre'] ?? ''));
				if ($userId <= 0 || $userName === '') {
					$advisorName = $this->normalizeAdvisorIdentity((string) ($row['nombre'] ?? ''));
					foreach ($users as $user) {
						$userNameKey = $this->normalizeAdvisorIdentity((string) ($user['nombre'] ?? ''));
						$emailKey = $this->normalizeAdvisorIdentity((string) strtok((string) ($user['email'] ?? ''), '@'));
						if ($advisorName !== '' && ($advisorName === $userNameKey || $advisorName === $emailKey)) {
							$userId = (int) ($user['id'] ?? 0);
							$userName = (string) ($user['nombre'] ?? '');
							break;
						}
					}
				}
				if ($userId > 0 && $userName !== '') {
					$row['usuario_id'] = $userId;
					$row['usuario_nombre'] = $userName;
					$options[] = $row;
				}
			}
			return $options;
		} catch (Throwable $e) {
			return [];
		}
	}

	private function normalizeAdvisorIdentity(string $value): string
	{
		$value = trim(mb_strtolower($value));
		$value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
		return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
	}

	public function mapCciAdvisorUser(int $advisorId): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/asignaciones');
		}
		$db = $this->db();
		$this->ensureCciTables($db);
		$userId = (int) ($_POST['usuario_id'] ?? 0);
		$advisorStmt = $db->prepare('SELECT id FROM crm_prospect_asesores WHERE id = :id AND estado = "activo" LIMIT 1');
		$advisorStmt->execute(['id' => $advisorId]);
		$userStmt = $db->prepare('SELECT id FROM usuarios WHERE id = :id AND estado = "activo" LIMIT 1');
		$userStmt->execute(['id' => $userId]);
		if (!$advisorStmt->fetchColumn() || !$userStmt->fetchColumn()) {
			set_flash('error', 'Selecciona un asesor CRM y un usuario activo válidos.');
			redirect('cci/asignaciones');
		}
		$db->prepare('INSERT INTO cci_asesor_usuario_map (crm_asesor_id, usuario_id, created_at, updated_at)
			VALUES (:advisor_id, :user_id, NOW(), NOW())
			ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), updated_at = NOW()')
			->execute(['advisor_id' => $advisorId, 'user_id' => $userId]);
		set_flash('success', 'Cuenta del asesor CRM vinculada correctamente.');
		redirect('cci/asignaciones');
	}

	public function assignConversation(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones?selected_id=' . $id);
		}
		$db = $this->db();
		$this->ensureCciTables($db);
		$advisorId = (int) ($_POST['crm_asesor_id'] ?? 0);
		$target = $db->prepare('SELECT a.nombre AS asesor_nombre, m.usuario_id, u.nombre AS usuario_nombre
			FROM crm_prospect_asesores a
			INNER JOIN cci_asesor_usuario_map m ON m.crm_asesor_id = a.id
			INNER JOIN usuarios u ON u.id = m.usuario_id AND u.estado = "activo"
			WHERE a.id = :advisor_id AND a.estado = "activo" LIMIT 1');
		$target->execute(['advisor_id' => $advisorId]);
		$advisor = $target->fetch() ?: null;
		if ($advisor === null) {
			$options = $this->fetchCciAdvisorOptions($db);
			foreach ($options as $option) {
				if ((int) ($option['id'] ?? 0) === $advisorId) {
					$advisor = [
						'asesor_nombre' => (string) ($option['nombre'] ?? ''),
						'usuario_id' => (int) ($option['usuario_id'] ?? 0),
						'usuario_nombre' => (string) ($option['usuario_nombre'] ?? ''),
					];
					break;
				}
			}
		}
		if ($advisor === null) {
			set_flash('error', 'El asesor CRM seleccionado aún no está vinculado a un usuario activo.');
			redirect('cci/asignaciones');
		}
		$conversation = $db->prepare('SELECT id, asignado_a FROM bot_conversaciones WHERE id = :id AND canal = "freshchat" LIMIT 1');
		$conversation->execute(['id' => $id]);
		$row = $conversation->fetch() ?: null;
		if ($row === null) {
			set_flash('error', 'Conversación Freshchat no encontrada.');
			redirect('cci/conversaciones');
		}
		$userId = (int) $advisor['usuario_id'];
		$db->prepare('UPDATE bot_conversaciones SET asignado_a = :user_id, updated_at = NOW() WHERE id = :id')
			->execute(['user_id' => $userId, 'id' => $id]);
		$db->prepare('INSERT INTO user_notifications (user_id, title, message, url, type, is_read, created_at)
			VALUES (:user_id, :title, :message, :url, "cci_assignment", 0, NOW())')->execute([
			'user_id' => $userId,
			'title' => 'Chat Freshchat asignado',
			'message' => 'Tienes una conversación Freshchat asignada como ' . (string) $advisor['asesor_nombre'] . '.',
			'url' => base_url('cci/conversaciones?selected_id=' . $id),
		]);
		AuditLogger::log('UPDATE', 'cci_conversacion_asignacion', $id, ['asignado_a' => $row['asignado_a']], ['asignado_a' => $userId, 'crm_asesor_id' => $advisorId]);
		set_flash('success', 'Conversación asignada a ' . (string) $advisor['asesor_nombre'] . '. Se notificó a ' . (string) $advisor['usuario_nombre'] . '.');
		redirect('cci/conversaciones?selected_id=' . $id);
	}

	public function asignaciones(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$items = [];
		$advisors = $this->fetchCciAdvisorOptions($db);
		$allCrmAdvisors = [];
		$users = [];
		try {
			$allCrmAdvisors = $db->query('SELECT id, nombre FROM crm_prospect_asesores WHERE estado = "activo" ORDER BY nombre ASC')->fetchAll() ?: [];
			$users = $db->query('SELECT id, nombre, email FROM usuarios WHERE estado = "activo" ORDER BY nombre ASC')->fetchAll() ?: [];
			$items = $db->query("SELECT bc.id,
				COALESCE(c.nombre, '') AS nombre,
				COALESCE(c.apellido, '') AS apellido,
				COALESCE(tc.telefono, '') AS telefono,
				COALESCE(i.carrera, '') AS carrera,
				COALESCE(i.modalidad, '') AS modalidad,
				COALESCE(bc.estado, 'pendiente') AS estado,
				COALESCE(bc.fecha_inicio, bc.created_at) AS fecha,
				COALESCE(u.nombre, 'Sin asignar') AS asesor_actual
			FROM bot_conversaciones bc
			LEFT JOIN contactos c ON c.id = bc.contacto_id
			LEFT JOIN interesados i ON i.contacto_id = bc.contacto_id AND i.estado = 'activo'
			LEFT JOIN (
				SELECT x.contacto_id, x.telefono
				FROM telefonos_contacto x
				INNER JOIN (
					SELECT contacto_id, MIN(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) y ON y.first_id = x.id
			) tc ON tc.contacto_id = bc.contacto_id
			LEFT JOIN usuarios u ON u.id = bc.asignado_a
			WHERE bc.canal = 'freshchat'
			ORDER BY COALESCE(bc.fecha_inicio, bc.created_at) DESC
			LIMIT 200")->fetchAll() ?: [];
		} catch (Throwable $e) {
			$items = [];
		}

		$this->view('cci/asignaciones', compact('items', 'advisors', 'allCrmAdvisors', 'users'), [
			'title' => 'Centro de Comunicaciones - Asignaciones',
		]);
	}

	public function sla(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);
		$sla = $this->fetchConfigSection($db, 'sla');
		$this->view('cci/sla', compact('sla'), [
			'title' => 'Centro de Comunicaciones - SLA',
		]);
	}

	public function automatizaciones(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);
		$config = new CciConfig();
		$n8n = [
			'estado' => $config->getValue('n8n', 'estado', 'inactivo'),
			'url' => $config->getValue('n8n', 'url', ''),
			'webhook' => $config->getValue('n8n', 'webhook', ''),
			'event_filter' => $config->getValue('n8n', 'event_filter', ''),
			'timeout_ms' => $config->getValue('n8n', 'timeout_ms', '12000'),
		];

		$logs = [];
		try {
			$logs = (new CciAutomationService())->latestLogs(120);
		} catch (Throwable $e) {
			$logs = [];
		}

		$catalogoEventos = [
			'message_received',
			'message_sent',
			'message_sent_manual',
			'provider_sync_completed',
			'lead_updated',
			'campaign_created',
			'campaign_recipients_loaded',
			'campaign_batch_processed',
		];

		$this->view('cci/automatizaciones', compact('n8n', 'logs', 'catalogoEventos'), [
			'title' => 'Centro de Comunicaciones - Automatizaciones',
		]);
	}

	public function testAutomatizacion(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/automatizaciones');
		}

		$eventName = trim((string) ($_POST['event_name'] ?? ''));
		if ($eventName === '') {
			$eventName = 'manual_test';
		}

		$payload = [
			'initiated_by' => Auth::id(),
			'timestamp' => date('c'),
			'note' => trim((string) ($_POST['note'] ?? 'Prueba manual desde módulo CCI')),
		];

		$result = (new CciAutomationService())->dispatch($eventName, $payload, [
			'origin' => 'cci_automatizaciones_ui',
		]);

		if (!($result['ok'] ?? false)) {
			set_flash('error', 'Prueba de automatización no enviada: ' . (string) ($result['message'] ?? 'sin detalle'));
			redirect('cci/automatizaciones');
		}

		set_flash('success', 'Prueba de automatización enviada correctamente a n8n.');
		redirect('cci/automatizaciones');
	}

	public function configuracion(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$tabs = [
			'general' => $this->fetchConfigSection($db, 'general'),
			'campanas' => $this->fetchConfigSection($db, 'campanas'),
			'whatchimp' => $this->fetchConfigSection($db, 'whatchimp'),
			'n8n' => $this->fetchConfigSection($db, 'n8n'),
			'ia' => $this->fetchConfigSection($db, 'ia'),
			'sla' => $this->fetchConfigSection($db, 'sla'),
		];

		$providers = (new CciProvider())->allProviders();
		$whatchimpHealth = (new CciProviderService())->verifyWhatchimpConnection();

		$this->view('cci/configuracion', compact('tabs', 'providers', 'whatchimpHealth'), [
			'title' => 'Centro de Comunicaciones - Configuración',
		]);
	}

	public function saveConfiguracion(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('cci/configuracion');
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$config = new CciConfig();
		$userId = Auth::id();

		$map = [
			'general' => ['default_provider', 'lead_interaction_threshold'],
			'campanas' => ['auto_enabled', 'auto_limit_campaigns', 'auto_batch_size', 'auto_retry_max'],
			'whatchimp' => ['estado', 'api_key', 'base_url', 'numero_asociado', 'alias', 'webhook', 'send_endpoint', 'sync_endpoint', 'verify_token'],
			'n8n' => ['estado', 'url', 'webhook', 'auth_token', 'timeout_ms', 'event_filter'],
			'ia' => ['estado', 'proveedor', 'modelo', 'temperatura', 'limite_tokens', 'prompt_base', 'base_conocimiento'],
			'sla' => ['max_sin_responder_minutos', 'max_espera_minutos', 'max_interacciones', 'recordatorio_minutos'],
		];

		$before = [];
		$after = [];

		foreach ($map as $section => $keys) {
			foreach ($keys as $key) {
				$fieldName = $section . '__' . $key;
				if (!array_key_exists($fieldName, $_POST)) {
					continue;
				}
				$value = trim((string) ($_POST[$fieldName] ?? ''));
				$current = $config->getValue($section, $key, '');
				$before[$section . '.' . $key] = $current;
				$after[$section . '.' . $key] = $value;
				$config->upsert($section, $key, $value, (($section === 'whatchimp' && $key === 'api_key') || ($section === 'n8n' && $key === 'auth_token')), $userId);
			}
		}

		AuditLogger::log('UPDATE', 'cci_configuraciones', null, $before, $after);
		set_flash('success', 'Configuración CCI guardada correctamente.');
		redirect('cci/configuracion');
	}

	public function auditoria(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$logs = [];
		try {
			$stmt = $db->query("SELECT a.id, a.tabla, a.registro_id, a.accion, a.created_at, COALESCE(u.nombre, 'Sistema') AS usuario
				FROM auditoria a
				LEFT JOIN usuarios u ON u.id = a.usuario_id
				WHERE a.tabla LIKE 'cci_%' OR a.tabla IN ('interesados', 'bot_conversaciones', 'bot_mensajes')
				ORDER BY a.id DESC
				LIMIT 300");
			$logs = $stmt ? ($stmt->fetchAll() ?: []) : [];
		} catch (Throwable $e) {
			$logs = [];
		}

		$this->view('cci/auditoria', compact('logs'), [
			'title' => 'Centro de Comunicaciones - Auditoría',
		]);
	}

	public function guardarEtiqueta(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$etiquetaId = (int) ($_POST['etiqueta_id'] ?? 0);
		$db = $this->db();
		$this->ensureCciTables($db);

		if ($id > 0 && $etiquetaId > 0) {
			$stmt = $db->prepare("UPDATE bot_conversaciones SET etiqueta_id = :etiqueta_id, estado = 'cerrado', updated_at = NOW() WHERE id = :id");
			$stmt->execute([
				'etiqueta_id' => $etiquetaId,
				'id'          => $id,
			]);
			AuditLogger::log('UPDATE', 'bot_conversaciones', $id, null, [
				'etiqueta_id' => $etiquetaId,
				'estado' => 'cerrado',
			]);
			set_flash('success', 'Etiqueta asignada y conversación cerrada.');
		}

		redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
	}

	public function crearEtiqueta(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones');
		}

		$nombre = trim((string) ($_POST['nombre_etiqueta'] ?? ''));
		$selectedId = (int) ($_POST['selected_id'] ?? 0);
		$db = $this->db();
		$this->ensureCciTables($db);

		if ($nombre !== '') {
			$stmt = $db->prepare('INSERT INTO cci_etiquetas (nombre, estado, fecha_creacion) VALUES (:nombre, 1, NOW())');
			$stmt->execute(['nombre' => mb_substr($nombre, 0, 100)]);
			AuditLogger::log('CREATE', 'cci_etiquetas', (int) $db->lastInsertId(), null, ['nombre' => $nombre]);
			set_flash('success', 'Etiqueta creada correctamente.');
		} else {
			set_flash('error', 'El nombre de la etiqueta es obligatorio.');
		}

		redirect('cci/conversaciones' . ($selectedId > 0 ? '?selected_id=' . $selectedId : ''));
	}

	public function toggleEstadoEtiqueta(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones');
		}

		$selectedId = (int) ($_POST['selected_id'] ?? 0);
		$db = $this->db();
		$this->ensureCciTables($db);

		if ($id > 0) {
			$db->prepare('UPDATE cci_etiquetas SET estado = IF(estado = 1, 0, 1) WHERE id = :id')->execute(['id' => $id]);
			AuditLogger::log('UPDATE', 'cci_etiquetas', $id, null, ['toggle' => true]);
		}

		redirect('cci/conversaciones' . ($selectedId > 0 ? '?selected_id=' . $selectedId : ''));
	}

	/**
	 * Helper: Crear mensaje de sistema en timeline (Req 11)
	 * Inserta un mensaje de tipo 'sistema' con icono y color
	 */
	private function crearMensajeSistema(PDO $db, int $conversacionId, string $texto, string $tipo = 'info'): void
	{
		if ($conversacionId <= 0 || empty($texto)) {
			return;
		}

		try {
			$stmt = $db->prepare('
				INSERT INTO bot_mensajes (conversacion_id, usuario_id, usuario_nombre, contenido, tipo, es_bot, fecha, created_at, sistema_tipo)
				VALUES (:conversacion_id, 0, "Sistema", :contenido, "sistema", 1, NOW(), NOW(), :sistema_tipo)
			');
			$stmt->execute([
				'conversacion_id' => $conversacionId,
				'contenido' => mb_substr($texto, 0, 1000),
				'sistema_tipo' => $tipo,
			]);
		} catch (Throwable $e) {
			// Silenciosamente ignorar si no se puede crear el mensaje del sistema
		}
	}

	/**
	 * Enviar mensaje masivo a múltiples conversaciones (Req 3)
	 * POST /cci/conversaciones/enviar-masivo
	 */
	public function enviarMasivo(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$inputJson = file_get_contents('php://input');
		$data = json_decode($inputJson, true) ?: [];
		$ids = (array) ($data['ids'] ?? []);
		$mensaje = trim((string) ($data['mensaje'] ?? ''));
		$agregarNota = (bool) ($data['agregar_nota'] ?? false);
		$token = (string) ($data['_token'] ?? '');

		if (!verify_csrf($token)) {
			echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
			exit;
		}

		if (empty($ids) || empty($mensaje)) {
			echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
			exit;
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		// Filtrar IDs válidos
		$validIds = array_filter($ids, static fn($id) => is_numeric($id) && ((int)$id) > 0);
		if (empty($validIds)) {
			echo json_encode(['ok' => false, 'error' => 'No hay IDs válidas']);
			exit;
		}

		try {
			$sent = 0;
			$usuario = Auth::user()['nombre'] ?? 'CCI';

			// Enviar mensaje a cada conversación
			foreach ($validIds as $convId) {
				$convId = (int) $convId;

				// Crear mensaje en bot_mensajes
				$stmt = $db->prepare('
					INSERT INTO bot_mensajes (conversacion_id, usuario_id, usuario_nombre, contenido, tipo, es_bot, fecha, created_at)
					VALUES (:conversacion_id, 0, :usuario_nombre, :contenido, "texto", 1, NOW(), NOW())
				');
				$stmt->execute([
					'conversacion_id' => $convId,
					'usuario_nombre' => $usuario,
					'contenido' => mb_substr($mensaje, 0, 1000),
				]);

				// Si desea, también crear como nota interna
				if ($agregarNota) {
					$this->crearMensajeSistema($db, $convId, 'Mensaje masivo: ' . mb_substr($mensaje, 0, 100), 'info');
				}

				$sent++;
			}

			AuditLogger::log('CREATE', 'bot_mensajes', 0, null, [
				'bulk_send' => true,
				'count' => $sent,
				'ids' => implode(',', $validIds),
				'mensaje_preview' => mb_substr($mensaje, 0, 50),
			]);

			echo json_encode([
				'ok' => true,
				'sent' => $sent,
				'message' => 'Mensaje enviado a ' . $sent . ' conversación(es)',
			]);
		} catch (Throwable $e) {
			echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	// Métodos para subetiquetas (Req 1)
	public function obtenerSubetiquetas(int $etiquetaId): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);

		$stmt = $db->prepare('SELECT id, nombre, estado FROM cci_subetiquetas WHERE etiqueta_id = :etiqueta_id AND estado = 1 ORDER BY nombre ASC');
		$stmt->execute(['etiqueta_id' => $etiquetaId]);
		$subetiquetas = $stmt->fetchAll() ?: [];

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['ok' => true, 'subetiquetas' => $subetiquetas], JSON_UNESCAPED_UNICODE);
		exit;
	}

	public function crearSubetiqueta(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$etiquetaId = (int) ($_POST['etiqueta_id'] ?? 0);
		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$selectedId = (int) ($_POST['selected_id'] ?? 0);
		$db = $this->db();
		$this->ensureCciTables($db);

		if ($etiquetaId <= 0 || $nombre === '') {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['ok' => false, 'error' => 'Datos incompletos.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$stmt = $db->prepare('INSERT INTO cci_subetiquetas (etiqueta_id, nombre, estado, fecha_creacion) VALUES (:etiqueta_id, :nombre, 1, NOW())');
		$stmt->execute(['etiqueta_id' => $etiquetaId, 'nombre' => mb_substr($nombre, 0, 100)]);
		AuditLogger::log('CREATE', 'cci_subetiquetas', (int) $db->lastInsertId(), null, ['etiqueta_id' => $etiquetaId, 'nombre' => $nombre]);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['ok' => true, 'id' => (int) $db->lastInsertId(), 'nombre' => $nombre], JSON_UNESCAPED_UNICODE);
		exit;
	}

	public function toggleEstadoSubetiqueta(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones');
		}

		$selectedId = (int) ($_POST['selected_id'] ?? 0);
		$db = $this->db();
		$this->ensureCciTables($db);

		if ($id > 0) {
			$db->prepare('UPDATE cci_subetiquetas SET estado = IF(estado = 1, 0, 1) WHERE id = :id')->execute(['id' => $id]);
			AuditLogger::log('UPDATE', 'cci_subetiquetas', $id, null, ['toggle' => true]);
		}

		redirect('cci/conversaciones' . ($selectedId > 0 ? '?selected_id=' . $selectedId : ''));
	}

	public function convertirClientePotencial(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		$stmt = $db->prepare('SELECT contacto_id FROM bot_conversaciones WHERE id = :id LIMIT 1');
		$stmt->execute(['id' => $id]);
		$contactId = (int) ($stmt->fetchColumn() ?: 0);
		if ($contactId <= 0) {
			set_flash('error', 'La conversación no tiene un contacto asociado.');
			redirect('cci/conversaciones?selected_id=' . max(1, (int) $id));
		}

		$existing = $db->prepare('SELECT id FROM interesados WHERE contacto_id = :contacto_id LIMIT 1');
		$existing->execute(['contacto_id' => $contactId]);
		if ((int) ($existing->fetchColumn() ?: 0) > 0) {
			set_flash('success', 'Este contacto ya es un cliente potencial.');
			redirect('crm/interesados?tab=prospects&open_contact_id=' . $contactId);
		}

		$estadoInicial = $this->resolveInitialProspectStateId($db);
		$creadoPor = trim((string) (Auth::user()['nombre'] ?? 'CCI'));
		$insert = $db->prepare('INSERT INTO interesados (contacto_id, estado_id, origen, creado_por, convertido, estado, created_at, updated_at)
			VALUES (:contacto_id, :estado_id, "CCI", :creado_por, 0, "activo", NOW(), NOW())');
		$insert->execute([
			'contacto_id' => $contactId,
			'estado_id' => $estadoInicial,
			'creado_por' => $creadoPor !== '' ? mb_substr($creadoPor, 0, 255) : 'CCI',
		]);
		AuditLogger::log('CREATE', 'interesados', (int) $db->lastInsertId(), null, [
			'contacto_id' => $contactId,
			'origen' => 'CCI',
		]);

		set_flash('success', 'Contacto convertido en cliente potencial.');
		redirect('crm/interesados?tab=prospects&open_contact_id=' . $contactId);
	}

	public function cerrarLote(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');
		
		$inputJson = file_get_contents('php://input');
		$data = json_decode($inputJson, true) ?: [];
		$ids = (array) ($data['ids'] ?? []);
		$token = (string) ($data['_token'] ?? '');

		if (!verify_csrf($token)) {
			echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
			exit;
		}

		if (empty($ids)) {
			echo json_encode(['ok' => false, 'error' => 'Sin IDs seleccionadas']);
			exit;
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		// Filtrar IDs válidos (solo números)
		$validIds = array_filter($ids, static fn($id) => is_numeric($id) && ((int)$id) > 0);
		if (empty($validIds)) {
			echo json_encode(['ok' => false, 'error' => 'No hay IDs válidas']);
			exit;
		}

		$placeholders = implode(',', array_fill(0, count($validIds), '?'));
		$stmt = $db->prepare("UPDATE bot_conversaciones SET estado = 'cerrado', updated_at = NOW() WHERE id IN ($placeholders) AND canal = 'freshchat'");
		try {
			$stmt->execute(array_values($validIds));
			$closed = $stmt->rowCount();
			
			// Crear mensaje de sistema para cada conversación cerrada (Req 11)
			foreach ($validIds as $convId) {
				$this->crearMensajeSistema($db, (int) $convId, 'Conversación cerrada por ' . (Auth::user()['nombre'] ?? 'usuario'), 'success');
			}
			
			AuditLogger::log('UPDATE', 'bot_conversaciones', 0, null, [
				'bulk_close' => true,
				'count' => $closed,
				'ids' => implode(',', $validIds),
			]);
			echo json_encode(['ok' => true, 'closed' => $closed]);
		} catch (Throwable $e) {
			echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function conversaciones(): void
	{
		Auth::requireAuth();
		$db = $this->db();
		$this->ensureCciTables($db);
		$this->ensureProspectByInteraction($db);
		try {
			$this->normalizeCachedFreshchatFilenames($db);
		} catch (Throwable $e) {
			// No bloquear la carga de la vista si la reparación de medios falla.
		}

		$selectedId = (int) ($_GET['selected_id'] ?? 0);
		$estadoFilter = strtolower(trim((string) ($_GET['estado'] ?? 'activo')));
		if (!in_array($estadoFilter, ['activo', 'cerrado', 'todos'], true)) {
			$estadoFilter = 'activo';
		}
		$asesorFilter = (int) ($_GET['asesor'] ?? 0);
		$etiquetaFilter = (int) ($_GET['etiqueta'] ?? 0);

		// Filtros de fecha (Req 8)
		$fechaInicio = trim((string) ($_GET['fecha_inicio'] ?? ''));
		$fechaFin = trim((string) ($_GET['fecha_fin'] ?? ''));
		$fechaInicio = $fechaInicio !== '' && strtotime($fechaInicio) !== false ? $fechaInicio : '';
		$fechaFin = $fechaFin !== '' && strtotime($fechaFin) !== false ? $fechaFin : '';

		$total = 0;
		$items = [];
		$thread = [];
		$notes = [];
		$selected = null;
		$freshchatReplyWindowOpen = true;
		$freshchatLastInboundAt = '';
		$hasMoreMessages = false;
		$messagesHidden = 0;
		$advisors = $this->fetchCciAdvisorOptions($db);
		$etiquetasActivas = $db->query("SELECT * FROM cci_etiquetas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll() ?: [];
		$todasLasEtiquetas = $db->query("SELECT * FROM cci_etiquetas ORDER BY nombre ASC")->fetchAll() ?: [];

		// Para modal de cliente potencial (Req 5)
		$carreras = $this->fetchCciCareerOptions($db);
		$asesoresCrm = $db->query("SELECT id, nombre FROM crm_prospect_asesores WHERE estado = 'activo' ORDER BY nombre ASC LIMIT 50")->fetchAll() ?: [];

		try {
			$where = ["bc.canal = 'freshchat'"];
			$params = [];
			if ($estadoFilter !== 'todos') {
				$where[] = 'bc.estado = :estado';
				$params['estado'] = $estadoFilter;
			}
			if ($asesorFilter > 0) {
				$where[] = 'bc.asignado_a = :asesor';
				$params['asesor'] = $asesorFilter;
			}
			if ($etiquetaFilter > 0) {
				$where[] = 'bc.etiqueta_id = :etiqueta';
				$params['etiqueta'] = $etiquetaFilter;
			}
			// Filtro de rango de fechas (Req 8)
			if ($fechaInicio !== '') {
				$where[] = 'COALESCE(bm.fecha, bm.created_at, bc.created_at) >= :fecha_inicio';
				$params['fecha_inicio'] = $fechaInicio . ' 00:00:00';
			}
			if ($fechaFin !== '') {
				$where[] = 'COALESCE(bm.fecha, bm.created_at, bc.created_at) <= :fecha_fin';
				$params['fecha_fin'] = $fechaFin . ' 23:59:59';
			}
			$whereSql = implode(' AND ', $where);

			$countStmt = $db->prepare('SELECT COUNT(*) FROM bot_conversaciones bc WHERE ' . $whereSql);
			$countStmt->execute($params);
			$total = (int) $countStmt->fetchColumn();

			$sql = "SELECT bc.id, bc.contacto_id, bc.canal, bc.estado, bc.asignado_a, bc.etiqueta_id,
				COALESCE(et.nombre, '') AS etiqueta_nombre,
				COALESCE(bc.fecha_inicio, bc.created_at) AS fecha_inicio,
				COALESCE(c.nombre, '') AS nombre,
				COALESCE(c.apellido, '') AS apellido,
				COALESCE(tc.telefono, '') AS telefono,
				COALESCE(u.nombre, 'Sin asignar') AS asesor,
				MAX(COALESCE(bm.fecha, bm.created_at)) AS ultimo_mensaje_fecha,
				SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(bm.mensaje, '') ORDER BY COALESCE(bm.fecha, bm.created_at) DESC SEPARATOR '||'), '||', 1) AS ultimo_mensaje
			FROM bot_conversaciones bc
			LEFT JOIN contactos c ON c.id = bc.contacto_id
			LEFT JOIN usuarios u ON u.id = bc.asignado_a
			LEFT JOIN cci_etiquetas et ON et.id = bc.etiqueta_id
			LEFT JOIN bot_mensajes bm ON bm.conversacion_id = bc.id
			LEFT JOIN (
				SELECT x.contacto_id, x.telefono
				FROM telefonos_contacto x
				INNER JOIN (
					SELECT contacto_id, MIN(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) y ON y.first_id = x.id
			) tc ON tc.contacto_id = bc.contacto_id
			WHERE {$whereSql}
			GROUP BY bc.id, bc.contacto_id, bc.canal, bc.estado, bc.asignado_a, bc.etiqueta_id, et.nombre, bc.fecha_inicio, bc.created_at, c.nombre, c.apellido, tc.telefono, u.nombre
			ORDER BY COALESCE(MAX(COALESCE(bm.fecha, bm.created_at)), bc.fecha_inicio, bc.created_at) DESC";
			$stmt = $db->prepare($sql);
			$stmt->execute($params);
			$items = $stmt->fetchAll() ?: [];

			// Formatear fechas a DD-mmm (Req 9)
			$monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
			foreach ($items as &$item) {
				if (!empty($item['ultimo_mensaje_fecha'])) {
					$timestamp = strtotime((string) $item['ultimo_mensaje_fecha']);
					if ($timestamp !== false) {
						$day = date('d', $timestamp);
						$month = date('n', $timestamp) - 1;
						$item['ultimo_mensaje_fecha'] = (int)$day . '-' . ($monthNames[$month] ?? 'mes');
					}
				}
			}
			unset($item);

			if ($selectedId <= 0 && !empty($items)) {
				$selectedId = (int) ($items[0]['id'] ?? 0);
			}

			if ($selectedId > 0) {
				$selStmt = $db->prepare('SELECT bc.*, COALESCE(u.nombre, "Sin asignar") AS asesor
					FROM bot_conversaciones bc
					LEFT JOIN usuarios u ON u.id = bc.asignado_a
					WHERE bc.id = :id AND bc.canal = "freshchat" LIMIT 1');
				$selStmt->execute(['id' => $selectedId]);
				$selected = $selStmt->fetch() ?: null;

				if (is_array($selected) && (string) ($selected['canal'] ?? '') === 'freshchat') {
					$refStmt = $db->prepare('SELECT external_conversation_id FROM cci_conversacion_refs WHERE provider_code = "freshchat" AND conversacion_id = :id LIMIT 1');
					$refStmt->execute(['id' => $selectedId]);
					$externalConversationId = trim((string) ($refStmt->fetchColumn() ?: ''));
					if ($externalConversationId !== '') {
						$this->syncFreshchatConversationLive($db, $selectedId, $externalConversationId);
					}
				}

				// En algunos entornos bot_mensajes no tiene columna tipo/fecha: seleccionar solo lo disponible.
				$msgColumns = $this->getTableColumnsSafe($db, 'bot_mensajes');
				$selectParts = ['id', 'mensaje', 'es_bot', 'created_at'];
				$selectParts[] = in_array('tipo', $msgColumns, true) ? 'tipo' : "'texto' AS tipo";
				$selectParts[] = in_array('fecha', $msgColumns, true) ? 'fecha' : 'created_at AS fecha';
				
				// Paginación de mensajes (Req 12)
				$pageSize = 50;
				$dateExpr = 'COALESCE(' . (in_array('fecha', $msgColumns, true) ? 'fecha' : 'created_at') . ', created_at)';
				$threadStmt = $db->prepare('SELECT ' . implode(', ', $selectParts) . '
					FROM bot_mensajes
					WHERE conversacion_id = :id
					ORDER BY ' . $dateExpr . ' DESC, id DESC
					LIMIT ' . (int)$pageSize);
				$threadStmt->execute(['id' => $selectedId]);
				$thread = $threadStmt->fetchAll() ?: [];
				// Revertir para pintar en orden cronológico (antiguo -> nuevo) en la vista.
				$thread = array_reverse($thread);
				
				// Verificar si hay más mensajes (para mostrar botón "Cargar más")
				$countStmt = $db->prepare('SELECT COUNT(*) FROM bot_mensajes WHERE conversacion_id = :id');
				$countStmt->execute(['id' => $selectedId]);
				$totalMessages = (int) $countStmt->fetchColumn();
				$hasMoreMessages = $totalMessages > $pageSize;
				$messagesHidden = max(0, $totalMessages - $pageSize);
				if ((string) ($selected['canal'] ?? '') === 'freshchat') {
					$freshchatReplyWindowOpen = false;
					for ($index = count($thread) - 1; $index >= 0; $index--) {
						$message = $thread[$index];
						if ((int) ($message['es_bot'] ?? 0) !== 0) {
							continue;
						}
						$freshchatLastInboundAt = (string) ($message['fecha'] ?? ($message['created_at'] ?? ''));
						$lastInboundTimestamp = strtotime($freshchatLastInboundAt);
						$freshchatReplyWindowOpen = $lastInboundTimestamp !== false && $lastInboundTimestamp >= time() - 86400;
						break;
					}
				}

				$notesHistory = (new CciConversationNote())->byConversation($selectedId);

				// Fusionar mensajes y notas en timeline cronológico (Req 15)
				$timeline = [];
				foreach ($thread as $msg) {
					$timeline[] = array_merge($msg, [
						'_type' => 'mensaje',
						'_sortDate' => strtotime((string) ($msg['fecha'] ?? ($msg['created_at'] ?? 'now'))),
					]);
				}
				foreach ($notesHistory as $note) {
					$timeline[] = array_merge($note, [
						'_type' => 'nota',
						'_sortDate' => strtotime((string) ($note['created_at'] ?? 'now')),
					]);
				}
				usort($timeline, static fn($a, $b) => ((int) ($a['_sortDate'] ?? 0)) <=> ((int) ($b['_sortDate'] ?? 0)));
				$thread = $timeline;
				$notes = $notesHistory;
			}
		} catch (Throwable $e) {
			error_log('CCI conversaciones() error: ' . $e->getMessage());
			$items = [];
		}

		// Endpoint JSON para auto-refresh de mensajes desde el frontend
		if (isset($_GET['json_thread']) && $selectedId > 0) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'ok'       => true,
				'messages' => array_values(array_map(static function (array $m): array {
					return [
						'id'     => (int) ($m['id'] ?? 0),
						'texto'  => (string) ($m['mensaje'] ?? ''),
						'tipo'   => (string) ($m['tipo'] ?? 'texto'),
						'es_bot' => (int) ($m['es_bot'] ?? 0),
						'fecha'  => (string) ($m['fecha'] ?? ($m['created_at'] ?? '')),
					];
				}, $thread)),
			], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$this->view('cci/conversaciones', compact(
			'items',
			'total',
			'selected',
			'selectedId',
			'thread',
			'notes',
			'advisors',
			'estadoFilter',
			'asesorFilter',
			'etiquetaFilter',
			'fechaInicio',
			'fechaFin',
			'etiquetasActivas',
			'todasLasEtiquetas',
			'carreras',
			'asesoresCrm',
			'hasMoreMessages',
			'messagesHidden',
			'freshchatReplyWindowOpen',
			'freshchatLastInboundAt'
		), [
			'title' => 'Centro de Comunicaciones - Conversaciones',
			'styles' => ['cci.css'],
		]);
	}

	/**
	 * Obtener mensajes anteriores de una conversación (Req 12 - Paginación)
	 * GET /cci/conversaciones/{id}/mensajes-anteriores?offset=50
	 */
	public function obtenerMensajesAnteriores(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$id = (int) ($_GET['id'] ?? 0);
		$offset = (int) ($_GET['offset'] ?? 50);
		
		if ($id <= 0 || $offset < 0) {
			echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
			exit;
		}

		$db = $this->db();
		$this->ensureCciTables($db);

		// Fetch mensajes anteriores: saltar los más recientes y traer el bloque previo.
		$stmt = $db->prepare('
			SELECT id,
			       conversacion_id,
			       COALESCE(usuario_id, 0) AS usuario_id,
			       COALESCE(usuario_nombre, "") AS usuario_nombre,
			       COALESCE(mensaje, "") AS contenido,
			       COALESCE(tipo, "texto") AS tipo,
			       COALESCE(es_bot, 0) AS es_bot,
			       COALESCE(fecha, created_at) AS fecha,
			       created_at
			FROM bot_mensajes
			WHERE conversacion_id = :id
			ORDER BY COALESCE(fecha, created_at) DESC, id DESC
			LIMIT 50
			OFFSET :offset
		');
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		
		try {
			$stmt->execute();
			$messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
			$messages = array_reverse($messages);
			echo json_encode([
				'ok' => true,
				'mensajes' => array_map(static function ($m) {
					return [
						'id'             => (int) $m['id'],
						'conversacion_id' => (int) $m['conversacion_id'],
						'usuario_id'     => (int) ($m['usuario_id'] ?? 0),
						'usuario_nombre' => (string) ($m['usuario_nombre'] ?? 'Cliente'),
						'contenido'      => (string) ($m['contenido'] ?? ''),
						'tipo'           => (string) ($m['tipo'] ?? 'texto'),
						'es_bot'         => (int) ($m['es_bot'] ?? 0),
						'fecha'          => (string) ($m['fecha'] ?? ($m['created_at'] ?? '')),
						'_type'          => 'mensaje',
					];
				}, $messages),
			], JSON_UNESCAPED_UNICODE);
		} catch (Throwable $e) {
			echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

}
