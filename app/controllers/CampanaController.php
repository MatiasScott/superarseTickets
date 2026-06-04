<?php

class CampanaController extends Controller
{
    private function ensureCampanasTables(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS campanas (
			id INT AUTO_INCREMENT PRIMARY KEY,
			titulo VARCHAR(255) NOT NULL,
			asunto VARCHAR(255) NOT NULL,
			contenido LONGTEXT NOT NULL,
			correo_origen VARCHAR(255) NOT NULL,
			tipo_destinatarios ENUM('todos', 'periodo', 'personalizado') NOT NULL DEFAULT 'todos',
			periodo_id INT NULL,
			estado ENUM('borrador', 'programada', 'enviando', 'completada', 'cancelada') NOT NULL DEFAULT 'borrador',
			fecha_envio DATETIME NULL,
			total_destinatarios INT DEFAULT 0,
			total_enviados INT DEFAULT 0,
			total_fallidos INT DEFAULT 0,
			usuario_id INT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at TIMESTAMP NULL,
			INDEX idx_estado (estado),
			INDEX idx_usuario (usuario_id),
			INDEX idx_fecha_creacion (created_at),
			FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS campana_destinatarios (
			id INT AUTO_INCREMENT PRIMARY KEY,
			campana_id INT NOT NULL,
			contacto_id INT NOT NULL,
			correo_destino VARCHAR(255) NOT NULL,
			nombre_destino VARCHAR(255),
			estado ENUM('pendiente', 'enviado', 'fallido', 'rebotado') NOT NULL DEFAULT 'pendiente',
			fecha_envio DATETIME NULL,
			error_mensaje TEXT NULL,
			intentos INT DEFAULT 0,
			INDEX idx_campana (campana_id),
			INDEX idx_contacto (contacto_id),
			INDEX idx_estado (estado),
			FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE,
			FOREIGN KEY (contacto_id) REFERENCES contactos(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS cola_envios (
			id INT AUTO_INCREMENT PRIMARY KEY,
			campana_id INT NOT NULL,
			destinatario_id INT NOT NULL,
			estado ENUM('pendiente', 'procesando', 'completado', 'error') NOT NULL DEFAULT 'pendiente',
			intento INT DEFAULT 0,
			proximo_intento DATETIME NULL,
			error_log TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_campana_estado (campana_id, estado),
			INDEX idx_proximo_intento (proximo_intento),
			FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE,
			FOREIGN KEY (destinatario_id) REFERENCES campana_destinatarios(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function index(): void
    {
        Auth::requireAuth();

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $sql = "SELECT c.*, u.nombre AS usuario_nombre
			FROM campanas c
			LEFT JOIN usuarios u ON c.usuario_id = u.id
			WHERE c.deleted_at IS NULL
			ORDER BY c.created_at DESC";
        $campanas = $db->query($sql)->fetchAll() ?: [];

        $this->view('campanas/index', compact('campanas'), ['title' => 'Campañas de Correo']);
    }

    public function create(): void
    {
        Auth::requireAuth();

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $cuentas = $this->getCampanaMailAccounts();
        $periodos = $this->getCampanaPeriodOptions();
        $pipelineEstados = $this->getCampanaPipelineStates($db);
        $carreras = $this->getCampanaCarreras($db);
        $niveles = $this->getCampanaNiveles();
        $resumen = array_merge($this->getCampanaCreateSummary($db), $this->getCampanaExternalStats());
        $resumen['cuentas'] = count($cuentas);

        $this->view('campanas/create', compact('cuentas', 'periodos', 'pipelineEstados', 'carreras', 'niveles', 'resumen'), ['title' => 'Nueva Campaña']);
    }

    public function store(): void
    {
        Auth::requireAuth();

        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('campanas/create');
        }

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $asunto = trim((string) ($_POST['asunto'] ?? ''));
        $contenido = (string) ($_POST['contenido'] ?? '');
        $correo_origen = trim((string) ($_POST['correo_origen'] ?? ''));
        $tipo_destinatarios = (string) ($_POST['tipo_destinatarios'] ?? 'todos');
        $periodoIdRaw = trim((string) ($_POST['periodo_id'] ?? ''));
        $periodo_id = $periodoIdRaw !== '' ? (int) $periodoIdRaw : null;
        $entityScope = (string) ($_POST['entity_scope'] ?? 'todos');
        $pipelineEstadoRaw = trim((string) ($_POST['pipeline_estado_id'] ?? ''));
        $pipelineEstadoId = $pipelineEstadoRaw !== '' ? (int) $pipelineEstadoRaw : null;
        $carreraPrograma = trim((string) ($_POST['carrera_id'] ?? ''));
        $nivelAcademico = trim((string) ($_POST['nivel'] ?? ''));

        if (!in_array($entityScope, ['todos', 'potenciales', 'estudiantes'], true)) {
            $entityScope = 'todos';
        }

        if ($titulo === '' || $asunto === '' || $contenido === '' || $correo_origen === '') {
            set_flash('error', 'Todos los campos son obligatorios.');
            redirect('campanas/create');
        }

        if ($tipo_destinatarios === 'periodo' && $periodo_id === null) {
            set_flash('error', 'Selecciona un período para ese tipo de segmentación.');
            redirect('campanas/create');
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO campanas (titulo, asunto, contenido, correo_origen, tipo_destinatarios, periodo_id, usuario_id, estado)
				VALUES (:titulo, :asunto, :contenido, :correo_origen, :tipo_destinatarios, :periodo_id, :usuario_id, 'borrador')");
            $stmt->execute([
                'titulo' => $titulo,
                'asunto' => $asunto,
                'contenido' => $contenido,
                'correo_origen' => $correo_origen,
                'tipo_destinatarios' => $tipo_destinatarios,
                'periodo_id' => $periodo_id,
                'usuario_id' => Auth::id(),
            ]);

            $campanaId = (int) $db->lastInsertId();
            $this->agregarDestinatarios($db, $campanaId, [
                'tipo_destinatarios' => $tipo_destinatarios,
                'periodo_id' => $periodo_id,
                'entity_scope' => $entityScope,
                'pipeline_estado_id' => $pipelineEstadoId,
                'carrera_programa' => $carreraPrograma,
                'nivel' => $nivelAcademico,
            ]);

            AuditLogger::log('CREATE', 'campanas', $campanaId, null, [
                'titulo' => $titulo,
                'asunto' => $asunto,
                'tipo_destinatarios' => $tipo_destinatarios,
                'entity_scope' => $entityScope,
                'pipeline_estado_id' => $pipelineEstadoId,
                'carrera_programa' => $carreraPrograma,
                'nivel' => $nivelAcademico,
                'estado' => 'borrador',
            ]);

            $db->commit();
            set_flash('success', 'Campaña creada correctamente.');
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            set_flash('error', 'Error al crear campaña: ' . $e->getMessage());
        }

        redirect('campanas');
    }

    public function edit(int $id): void
    {
        Auth::requireAuth();

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $stmt = $db->prepare("SELECT * FROM campanas WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $campana = $stmt->fetch();

        if (!$campana) {
            set_flash('error', 'Campaña no encontrada.');
            redirect('campanas');
        }

        $cuentas = $this->getCampanaMailAccounts();
        $periodos = $this->getCampanaPeriodOptions();
        $this->view('campanas/edit', compact('campana', 'cuentas', 'periodos'), ['title' => 'Editar Campaña']);
    }

    public function update(int $id): void
    {
        Auth::requireAuth();

        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('campanas');
        }

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $stmt = $db->prepare("SELECT * FROM campanas WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $campana = $stmt->fetch();

        if (!$campana) {
            set_flash('error', 'Campaña no encontrada.');
            redirect('campanas');
        }

        if (($campana['estado'] ?? '') !== 'borrador') {
            set_flash('error', 'Solo se pueden editar campañas en borrador.');
            redirect('campanas/edit/' . $id);
        }

        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $asunto = trim((string) ($_POST['asunto'] ?? ''));
        $contenido = (string) ($_POST['contenido'] ?? '');
        $correo_origen = trim((string) ($_POST['correo_origen'] ?? ''));

        if ($titulo === '' || $asunto === '' || $contenido === '' || $correo_origen === '') {
            set_flash('error', 'Todos los campos son obligatorios.');
            redirect('campanas/edit/' . $id);
        }

        try {
            $before = [
                'titulo' => $campana['titulo'] ?? '',
                'asunto' => $campana['asunto'] ?? '',
                'correo_origen' => $campana['correo_origen'] ?? '',
            ];

            $stmt = $db->prepare("UPDATE campanas
				SET titulo = :titulo, asunto = :asunto, contenido = :contenido, correo_origen = :correo_origen
				WHERE id = :id");
            $stmt->execute([
                'titulo' => $titulo,
                'asunto' => $asunto,
                'contenido' => $contenido,
                'correo_origen' => $correo_origen,
                'id' => $id,
            ]);

            AuditLogger::log('UPDATE', 'campanas', $id, $before, [
                'titulo' => $titulo,
                'asunto' => $asunto,
                'correo_origen' => $correo_origen,
            ]);

            set_flash('success', 'Campaña actualizada correctamente.');
        } catch (Throwable $e) {
            set_flash('error', 'Error al actualizar campaña: ' . $e->getMessage());
        }

        redirect('campanas/edit/' . $id);
    }

    public function send(int $id): void
    {
        Auth::requireAuth();

        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('campanas');
        }

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $stmt = $db->prepare("SELECT * FROM campanas WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $campana = $stmt->fetch();

        if (!$campana) {
            set_flash('error', 'Campaña no encontrada.');
            redirect('campanas');
        }

        if (($campana['estado'] ?? '') !== 'borrador') {
            set_flash('error', 'La campaña ya fue enviada.');
            redirect('campanas');
        }

        try {
            $db->beginTransaction();

            $update = $db->prepare("UPDATE campanas SET estado = 'enviando', fecha_envio = NOW() WHERE id = :id");
            $update->execute(['id' => $id]);

            $destinatarios = $db->prepare("SELECT id FROM campana_destinatarios WHERE campana_id = :id AND estado = 'pendiente'");
            $destinatarios->execute(['id' => $id]);
            $rows = $destinatarios->fetchAll() ?: [];

            $insertQueue = $db->prepare("INSERT INTO cola_envios (campana_id, destinatario_id, estado) VALUES (:campana_id, :destinatario_id, 'pendiente')");
            foreach ($rows as $row) {
                $insertQueue->execute([
                    'campana_id' => $id,
                    'destinatario_id' => (int) ($row['id'] ?? 0),
                ]);
            }

            AuditLogger::log('UPDATE', 'campanas', $id, ['estado' => 'borrador'], ['estado' => 'enviando']);
            $db->commit();
            set_flash('success', 'Campaña enviándose. Se procesará en segundo plano.');
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            set_flash('error', 'Error al enviar campaña: ' . $e->getMessage());
        }

        redirect('campanas');
    }

    public function delete(int $id): void
    {
        Auth::requireAuth();

        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('campanas');
        }

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $stmt = $db->prepare("SELECT * FROM campanas WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $campana = $stmt->fetch();

        if (!$campana) {
            set_flash('error', 'Campaña no encontrada.');
            redirect('campanas');
        }

        if (($campana['estado'] ?? '') !== 'borrador') {
            set_flash('error', 'Solo se pueden eliminar campañas en borrador.');
            redirect('campanas');
        }

        try {
            $stmt = $db->prepare("UPDATE campanas SET deleted_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
            AuditLogger::log('DELETE', 'campanas', $id, ['titulo' => $campana['titulo'] ?? ''], null);
            set_flash('success', 'Campaña eliminada correctamente.');
        } catch (Throwable $e) {
            set_flash('error', 'Error al eliminar campaña: ' . $e->getMessage());
        }

        redirect('campanas');
    }

    public function preview(int $id): void
    {
        Auth::requireAuth();

        $db = Database::getInstance()->connection();
        $this->ensureCampanasTables($db);

        $stmt = $db->prepare("SELECT * FROM campanas WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $campana = $stmt->fetch();

        if (!$campana) {
            set_flash('error', 'Campaña no encontrada.');
            redirect('campanas');
        }

        header('Content-Type: text/html; charset=utf-8');
        echo (string) ($campana['contenido'] ?? '');
        exit;
    }

    private function getCampanaMailAccounts(): array
    {
        try {
            $mailService = new MailService();
            $accounts = $mailService->getAvailableAccounts();
            $result = [];

            foreach ($accounts as $account) {
                $email = trim((string) ($account['email'] ?? ''));
                if ($email === '') {
                    continue;
                }

                $result[] = [
                    'correo_cuenta' => $email,
                    'alias' => (string) ($account['alias'] ?? ''),
                    'nombre' => (string) ($account['name'] ?? $email),
                ];
            }

            return $result;
        } catch (Throwable $e) {
            $fromEmail = trim((string) env('MAIL_FROM_EMAIL', ''));
            if ($fromEmail !== '') {
                return [[
                    'correo_cuenta' => $fromEmail,
                    'alias' => 'default',
                    'nombre' => trim((string) env('MAIL_FROM_NAME', 'ISTS Ticket System')),
                ]];
            }

            return [];
        }
    }

    private function getCampanaPeriodOptions(): array
    {
        $options = [];

        try {
            $remote = $this->connectSuperarseDatabase();
            if ($remote !== null && $this->resolveSuperarseStudentTable($remote) === 'users') {
                $rows = $remote->query("SELECT DISTINCT TRIM(COALESCE(periodo, '')) AS periodo
					FROM users
					WHERE periodo IS NOT NULL AND TRIM(periodo) <> ''
					ORDER BY periodo DESC")->fetchAll() ?: [];

                foreach ($rows as $row) {
                    $periodo = trim((string) ($row['periodo'] ?? ''));
                    if ($periodo !== '') {
                        $options[] = [
                            'id' => count($options) + 1,
                            'nombre' => $periodo,
                            'clave' => $periodo,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            $options = [];
        }

        if (!empty($options)) {
            return $options;
        }

        try {
            $db = Database::getInstance()->connection();
            $rows = $db->query("SELECT DISTINCT DATE_FORMAT(e.created_at, '%Y-%m') AS periodo
				FROM estudiantes e
				WHERE e.created_at IS NOT NULL
				ORDER BY periodo DESC")->fetchAll() ?: [];

            foreach ($rows as $row) {
                $periodo = trim((string) ($row['periodo'] ?? ''));
                if ($periodo !== '') {
                    $options[] = [
                        'id' => count($options) + 1,
                        'nombre' => $periodo,
                        'clave' => $periodo,
                    ];
                }
            }
        } catch (Throwable $e) {
            // fallback vacío
        }

        return $options;
    }

    private function getCampanaPipelineStates(PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT id, nombre, orden, categoria FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC");
            return $stmt ? ($stmt->fetchAll() ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function getCampanaCarreras(PDO $db): array
    {
        $options = [];

        try {
            $remote = $this->connectSuperarseDatabase();
            if ($remote !== null && $this->resolveSuperarseStudentTable($remote) === 'users') {
                $rows = $remote->query("SELECT DISTINCT TRIM(COALESCE(programa, '')) AS programa
                    FROM users
                    WHERE TRIM(COALESCE(programa, '')) <> ''
                    AND TRIM(COALESCE(programa, '')) NOT IN (
                        'AUTO EVALUCION',
                        'EJEMPLO',
                        'SEGUIMIENTO DOCENTE'
                    )
                    ORDER BY programa ASC")->fetchAll() ?: [];

                foreach ($rows as $row) {
                    $programa = trim((string) ($row['programa'] ?? ''));
                    if ($programa === '') {
                        continue;
                    }
                    $options[] = [
                        'id' => $programa,
                        'nombre' => $programa,
                    ];
                }
            }
        } catch (Throwable $e) {
            $options = [];
        }

        if (!empty($options)) {
            return $options;
        }

        try {
            $stmt = $db->query("SELECT id, nombre FROM carreras WHERE estado = 'activo' ORDER BY nombre ASC");
            $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
            foreach ($rows as $row) {
                $nombre = trim((string) ($row['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $options[] = [
                    'id' => $nombre,
                    'nombre' => $nombre,
                ];
            }
            return $options;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function getCampanaNiveles(): array
    {
        $options = [];

        try {
            $remote = $this->connectSuperarseDatabase();
            if ($remote === null || $this->resolveSuperarseStudentTable($remote) !== 'users') {
                return [];
            }

            $rows = $remote->query("SELECT DISTINCT TRIM(COALESCE(nivel, '')) AS nivel
				FROM users
				WHERE TRIM(COALESCE(nivel, '')) <> ''
				ORDER BY nivel ASC")->fetchAll() ?: [];

            foreach ($rows as $row) {
                $nivel = trim((string) ($row['nivel'] ?? ''));
                if ($nivel === '') {
                    continue;
                }
                $options[] = [
                    'id' => $nivel,
                    'nombre' => $nivel,
                ];
            }
        } catch (Throwable $e) {
            return [];
        }

        return $options;
    }

    private function getCampanaCreateSummary(PDO $db): array
    {
        try {
            return [
                'contactos_email' => (int) $db->query("SELECT COUNT(*) FROM contactos WHERE estado = 'activo' AND email IS NOT NULL AND email <> ''")->fetchColumn(),
                'interesados' => (int) $db->query("SELECT COUNT(*) FROM interesados WHERE estado = 'activo'")->fetchColumn(),
                'estudiantes' => (int) $db->query("SELECT COUNT(*) FROM estudiantes WHERE estado = 'activo'")->fetchColumn(),
                'matriculas' => (int) $db->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'activo'")->fetchColumn(),
                'correos' => (int) $db->query("SELECT COUNT(*) FROM correos_contacto WHERE estado = 'activo'")->fetchColumn(),
                'telefonos' => (int) $db->query("SELECT COUNT(*) FROM telefonos_contacto WHERE estado = 'activo'")->fetchColumn(),
            ];
        } catch (Throwable $e) {
            return [
                'contactos_email' => 0,
                'interesados' => 0,
                'estudiantes' => 0,
                'matriculas' => 0,
                'correos' => 0,
                'telefonos' => 0,
            ];
        }
    }

    private function getCampanaExternalStats(): array
    {
        try {
            $remote = $this->connectSuperarseDatabase();
            if ($remote === null || $this->resolveSuperarseStudentTable($remote) !== 'users') {
                return [
                    'external_total_users' => 0,
                    'external_users_con_usuario' => 0,
                    'external_users_con_correo_electronico' => 0,
                    'external_users_con_correo_personal' => 0,
                ];
            }

            return [
                'external_total_users' => (int) $remote->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'external_users_con_usuario' => (int) $remote->query("SELECT COUNT(*) FROM users WHERE TRIM(COALESCE(usuario, '')) <> ''")->fetchColumn(),
                'external_users_con_correo_electronico' => (int) $remote->query("SELECT COUNT(*) FROM users WHERE TRIM(COALESCE(correo_electronico, '')) <> ''")->fetchColumn(),
                'external_users_con_correo_personal' => (int) $remote->query("SELECT COUNT(*) FROM users WHERE TRIM(COALESCE(correo_personal, '')) <> ''")->fetchColumn(),
            ];
        } catch (Throwable $e) {
            return [
                'external_total_users' => 0,
                'external_users_con_usuario' => 0,
                'external_users_con_correo_electronico' => 0,
                'external_users_con_correo_personal' => 0,
            ];
        }
    }

    private function connectSuperarseDatabase(): ?PDO
    {
        $host = trim((string) env('SUPERARSE_DB_HOST', ''));
        $port = trim((string) env('SUPERARSE_DB_PORT', '3306'));
        $database = trim((string) env('SUPERARSE_DB_DATABASE', ''));
        $username = trim((string) env('SUPERARSE_DB_USERNAME', ''));
        $password = (string) env('SUPERARSE_DB_PASSWORD', '');
        $charset = trim((string) env('SUPERARSE_DB_CHARSET', 'utf8mb4'));

        if ($host === '' || $database === '' || $username === '') {
            return null;
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function resolveSuperarseStudentTable(PDO $remote): ?string
    {
        try {
            $stmt = $remote->query("SHOW TABLES LIKE 'users'");
            if (($stmt->fetchColumn() ?? '') !== '') {
                return 'users';
            }
        } catch (Throwable $e) {
            // Ignorar.
        }

        try {
            $stmt = $remote->query("SHOW TABLES LIKE 'estudiantes'");
            if (($stmt->fetchColumn() ?? '') !== '') {
                return 'estudiantes';
            }
        } catch (Throwable $e) {
            // Ignorar.
        }

        return null;
    }

    private function findContactByIdentity(PDO $db, string $identity): ?array
    {
        $identity = $this->normalizeIdentityValue($identity);
        if ($identity === '') {
            return null;
        }

        $stmt = $db->prepare("SELECT id, nombre, apellido, cedula, email FROM contactos
			WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(cedula, ''))), '.', ''), '-', ''), ' ', '') = :identity
			LIMIT 1");
        $stmt->execute(['identity' => $identity]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function normalizeIdentityValue(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', $value) ?: '';
    }

    private function normalizeEmailValue(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return $value;
    }

    private function resolvePeriodKeyById(?int $periodoId): string
    {
        if ($periodoId === null || $periodoId <= 0) {
            return '';
        }

        $periodos = $this->getCampanaPeriodOptions();
        foreach ($periodos as $periodo) {
            if ((int) ($periodo['id'] ?? 0) === $periodoId) {
                return (string) ($periodo['clave'] ?? '');
            }
        }

        return '';
    }

    private function mapContactEmails(PDO $db, array $contactIds): array
    {
        $map = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $contactIds), static function ($id): bool {
            return $id > 0;
        })));

        if (empty($ids)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT contacto_id, correo FROM correos_contacto WHERE estado = 'activo' AND contacto_id IN ($placeholders) ORDER BY id ASC");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as $row) {
            $contactId = (int) ($row['contacto_id'] ?? 0);
            if ($contactId <= 0) {
                continue;
            }

            $email = $this->normalizeEmailValue((string) ($row['correo'] ?? ''));
            if ($email === '') {
                continue;
            }

            if (!isset($map[$contactId])) {
                $map[$contactId] = [];
            }
            $map[$contactId][$email] = $email;
        }

        return $map;
    }

    private function mapExternalUsersByIdentity(array $identities): array
    {
        $result = [];
        $clean = array_values(array_unique(array_filter(array_map(function ($value): string {
            return $this->normalizeIdentityValue((string) $value);
        }, $identities))));

        if (empty($clean)) {
            return $result;
        }

        try {
            $remote = $this->connectSuperarseDatabase();
            if ($remote === null || $this->resolveSuperarseStudentTable($remote) !== 'users') {
                return $result;
            }

            $placeholders = implode(',', array_fill(0, count($clean), '?'));
            $sql = "SELECT numero_identificacion, usuario, correo_electronico, correo_personal, programa, nivel
				FROM users
				WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(numero_identificacion, ''))), '.', ''), '-', ''), ' ', '') IN ($placeholders)";
            $stmt = $remote->prepare($sql);
            $stmt->execute($clean);
            $rows = $stmt->fetchAll() ?: [];

            foreach ($rows as $row) {
                $identity = $this->normalizeIdentityValue((string) ($row['numero_identificacion'] ?? ''));
                if ($identity === '') {
                    continue;
                }

                $result[$identity] = [
                    'usuario' => $this->normalizeEmailValue((string) ($row['usuario'] ?? '')),
                    'correo_electronico' => $this->normalizeEmailValue((string) ($row['correo_electronico'] ?? '')),
                    'correo_personal' => $this->normalizeEmailValue((string) ($row['correo_personal'] ?? '')),
                    'programa' => trim((string) ($row['programa'] ?? '')),
                    'nivel' => trim((string) ($row['nivel'] ?? '')),
                ];
            }
        } catch (Throwable $e) {
            return [];
        }

        return $result;
    }

    private function agregarDestinatarios(PDO $db, int $campanaId, array $filters): void
    {
        $tipo = (string) ($filters['tipo_destinatarios'] ?? 'todos');
        $periodoId = isset($filters['periodo_id']) ? (int) $filters['periodo_id'] : 0;
        $entityScope = (string) ($filters['entity_scope'] ?? 'todos');
        $pipelineEstadoId = isset($filters['pipeline_estado_id']) ? (int) $filters['pipeline_estado_id'] : 0;
        $carreraPrograma = trim((string) ($filters['carrera_programa'] ?? ''));
        $nivelAcademico = trim((string) ($filters['nivel'] ?? ''));

        $destinatarios = [];
        $seen = [];
        $periodContactAllow = [];

        if ($tipo === 'periodo' && $periodoId > 0) {
            $periodoClave = $this->resolvePeriodKeyById($periodoId);
            if ($periodoClave !== '') {
                try {
                    $remote = $this->connectSuperarseDatabase();
                    if ($remote !== null && $this->resolveSuperarseStudentTable($remote) === 'users') {
                        $stmt = $remote->prepare("SELECT numero_identificacion
							FROM users
							WHERE TRIM(COALESCE(periodo, '')) = :periodo
							ORDER BY id ASC");
                        $stmt->execute(['periodo' => $periodoClave]);
                        $rows = $stmt->fetchAll() ?: [];

                        foreach ($rows as $row) {
                            $identity = $this->normalizeIdentityValue((string) ($row['numero_identificacion'] ?? ''));
                            if ($identity !== '') {
                                $periodContactAllow[$identity] = true;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $periodContactAllow = [];
                }
            }
        }

        $params = [];
        $where = ["c.estado = 'activo'"];

        if ($entityScope === 'potenciales') {
            $where[] = 'i.id IS NOT NULL';
            $where[] = 'COALESCE(i.convertido, 0) = 0';
        }
        if ($entityScope === 'estudiantes') {
            $where[] = 'e.id IS NOT NULL';
        }
        if ($pipelineEstadoId > 0) {
            $where[] = 'i.estado_id = :pipeline_estado_id';
            $params[':pipeline_estado_id'] = $pipelineEstadoId;
        }
        $sql = "SELECT DISTINCT
				c.id,
				c.nombre,
				c.apellido,
				c.cedula,
				c.email,
				i.id AS interesado_id,
				i.estado_id AS interesado_estado_id,
				e.id AS estudiante_id
			FROM contactos c
			LEFT JOIN interesados i ON i.contacto_id = c.id AND i.estado = 'activo'
			LEFT JOIN estudiantes e ON e.contacto_id = c.id AND e.estado = 'activo'
			WHERE " . implode(' AND ', $where) . "
			ORDER BY c.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contactRows = $stmt->fetchAll() ?: [];

        $contactIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $contactRows);
        $emailMap = $this->mapContactEmails($db, $contactIds);

        $identities = array_map(function (array $row): string {
            return $this->normalizeIdentityValue((string) ($row['cedula'] ?? ''));
        }, $contactRows);
        $externalByIdentity = $this->mapExternalUsersByIdentity($identities);

        foreach ($contactRows as $row) {
            $contactId = (int) ($row['id'] ?? 0);
            if ($contactId <= 0) {
                continue;
            }

            $identity = $this->normalizeIdentityValue((string) ($row['cedula'] ?? ''));
            if (!empty($periodContactAllow) && ($identity === '' || !isset($periodContactAllow[$identity]))) {
                continue;
            }

            $isStudent = (int) ($row['estudiante_id'] ?? 0) > 0;
            $external = $identity !== '' ? ($externalByIdentity[$identity] ?? []) : [];

            if ($carreraPrograma !== '') {
                $programa = trim((string) ($external['programa'] ?? ''));
                if ($programa === '' || mb_strtolower($programa) !== mb_strtolower($carreraPrograma)) {
                    continue;
                }
            }

            if ($nivelAcademico !== '') {
                $nivel = trim((string) ($external['nivel'] ?? ''));
                if ($nivel === '' || mb_strtolower($nivel) !== mb_strtolower($nivelAcademico)) {
                    continue;
                }
            }

            $emails = [];
            if ($isStudent) {
                $to = $this->normalizeEmailValue((string) ($external['usuario'] ?? ''));
                if ($to === '') {
                    $to = $this->normalizeEmailValue((string) ($external['correo_electronico'] ?? ''));
                }
                if ($to !== '') {
                    $emails[$to] = $to;
                }
            }

            $contactEmail = $this->normalizeEmailValue((string) ($row['email'] ?? ''));
            if ($contactEmail !== '') {
                $emails[$contactEmail] = $contactEmail;
            }

            foreach (($emailMap[$contactId] ?? []) as $mail) {
                $emails[$mail] = $mail;
            }

            $personalExternal = $this->normalizeEmailValue((string) ($external['correo_personal'] ?? ''));
            if ($personalExternal !== '') {
                $emails[$personalExternal] = $personalExternal;
            }

            $fullName = trim((string) ($row['nombre'] ?? '') . ' ' . (string) ($row['apellido'] ?? ''));
            foreach ($emails as $mail) {
                $key = $contactId . '|' . strtolower($mail);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $destinatarios[] = [
                    'contacto_id' => $contactId,
                    'correo' => $mail,
                    'nombre' => $fullName,
                ];
            }
        }

        $insert = $db->prepare("INSERT INTO campana_destinatarios (campana_id, contacto_id, correo_destino, nombre_destino)
			VALUES (:campana_id, :contacto_id, :correo_destino, :nombre_destino)");
        $total = 0;
        foreach ($destinatarios as $destinatario) {
            $insert->execute([
                'campana_id' => $campanaId,
                'contacto_id' => (int) $destinatario['contacto_id'],
                'correo_destino' => (string) $destinatario['correo'],
                'nombre_destino' => (string) $destinatario['nombre'],
            ]);
            $total++;
        }

        $update = $db->prepare("UPDATE campanas SET total_destinatarios = :total WHERE id = :campana_id");
        $update->execute(['total' => $total, 'campana_id' => $campanaId]);
    }
}
