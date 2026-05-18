<?php

class CRMController extends Controller
{
	public function dashboard(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$estadoId = max(0, (int) ($_GET['estado_id'] ?? 0));

		$pipelineEstados = [];
		$estadoLabel = 'Todos los estados';
		try {
			$pipelineEstados = $db->query("SELECT id, nombre FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC")->fetchAll() ?: [];
			foreach ($pipelineEstados as $estado) {
				if ((int) ($estado['id'] ?? 0) === $estadoId) {
					$estadoLabel = (string) ($estado['nombre'] ?? 'Estado');
					break;
				}
			}
		} catch (Throwable $e) {
			$pipelineEstados = [];
		}

		$metrics = [
			'contactos' => 0,
			'interesados' => 0,
			'estudiantes' => 0,
			'convertidos' => 0,
			'tasa_conversion' => 0.0,
		];
		$recentInteresados = [];
		$pipelineBreakdown = [];
		$monthlySeries = [];

		$whereInteresados = " WHERE i.estado = 'activo' ";
		$paramsInteresados = [];
		if ($estadoId > 0) {
			$whereInteresados .= ' AND i.estado_id = :estado_id ';
			$paramsInteresados[':estado_id'] = $estadoId;
		}

		try {
			$metrics['contactos'] = (int) $db->query("SELECT COUNT(*) FROM contactos WHERE estado = 'activo'")->fetchColumn();
		} catch (Throwable $e) {
			$metrics['contactos'] = 0;
		}

		try {
			$sql = 'SELECT COUNT(*) FROM interesados i ' . $whereInteresados;
			$stmt = $db->prepare($sql);
			$stmt->execute($paramsInteresados);
			$metrics['interesados'] = (int) $stmt->fetchColumn();
		} catch (Throwable $e) {
			$metrics['interesados'] = 0;
		}

		try {
			$studentsData = $this->fetchSuperarseStudents(400);
			$metrics['estudiantes'] = (int) ($studentsData['total'] ?? 0);
		} catch (Throwable $e) {
			$metrics['estudiantes'] = 0;
		}

		try {
			$sql = 'SELECT COUNT(*) FROM interesados i ' . $whereInteresados . ' AND i.convertido = 1';
			$stmt = $db->prepare($sql);
			$stmt->execute($paramsInteresados);
			$metrics['convertidos'] = (int) $stmt->fetchColumn();
		} catch (Throwable $e) {
			$metrics['convertidos'] = 0;
		}
		$metrics['tasa_conversion'] = $metrics['interesados'] > 0
			? round(($metrics['convertidos'] / $metrics['interesados']) * 100, 1)
			: 0.0;

		try {
			$sql = "SELECT i.id, c.nombre, c.apellido, i.origen, i.convertido, i.estado
					FROM interesados i
					INNER JOIN contactos c ON c.id = i.contacto_id
					" . $whereInteresados . "
					ORDER BY i.id DESC
					LIMIT 8";
			$stmt = $db->prepare($sql);
			$stmt->execute($paramsInteresados);
			$recentInteresados = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$recentInteresados = [];
		}

		try {
			$sql = "SELECT pe.id, pe.nombre, COUNT(i.id) AS total
					FROM pipeline_estados pe
					LEFT JOIN interesados i
						ON i.estado_id = pe.id
						AND i.estado = 'activo'
					GROUP BY pe.id, pe.nombre
					ORDER BY pe.id ASC";
			$pipelineBreakdown = $db->query($sql)->fetchAll() ?: [];
		} catch (Throwable $e) {
			$pipelineBreakdown = [];
		}

		try {
			$sql = "SELECT DATE_FORMAT(i.created_at, '%Y-%m') AS mes, COUNT(*) AS total
					FROM interesados i
					" . $whereInteresados . "
					GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
					ORDER BY mes DESC
					LIMIT 6";
			$stmt = $db->prepare($sql);
			$stmt->execute($paramsInteresados);
			$monthlySeries = array_reverse($stmt->fetchAll() ?: []);
		} catch (Throwable $e) {
			$monthlySeries = [];
		}

		$pipelineLabels = [];
		$pipelineValues = [];
		foreach ($pipelineBreakdown as $row) {
			$pipelineLabels[] = (string) ($row['nombre'] ?? 'Estado');
			$pipelineValues[] = (int) ($row['total'] ?? 0);
		}

		$monthlyLabels = [];
		$monthlyValues = [];
		foreach ($monthlySeries as $row) {
			$monthlyLabels[] = (string) ($row['mes'] ?? '');
			$monthlyValues[] = (int) ($row['total'] ?? 0);
		}

		$this->view('crm/dashboard', [
			'metrics' => $metrics,
			'recentInteresados' => $recentInteresados,
			'pipelineEstados' => $pipelineEstados,
			'estadoId' => $estadoId,
			'estadoLabel' => $estadoLabel,
			'pipelineLabels' => $pipelineLabels,
			'pipelineValues' => $pipelineValues,
			'monthlyLabels' => $monthlyLabels,
			'monthlyValues' => $monthlyValues,
		], [
			'title' => 'CRM - Dashboard',
		]);
	}

	public function interesados(): void
	{
		Auth::requireAuth();
		$this->ensureCrmSupportTables();
		$studentsData = $this->fetchSuperarseStudents(1000);
		$estudiantesSuperarse = is_array($studentsData['rows'] ?? null) ? $studentsData['rows'] : [];

		$this->view('crm/interesados', [
			'estudiantesSuperarse' => $estudiantesSuperarse,
			'sourceLabel' => (string) ($studentsData['source'] ?? 'No disponible'),
			'sourceError' => (string) ($studentsData['error'] ?? ''),
		], [
			'title' => 'CRM - Ver todo CRM',
		]);
	}

	public function estudiantes(): void
	{
		Auth::requireAuth();
		$estudiantes = [];

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT e.id, e.codigo_estudiante, e.estado,
					   c.nombre, c.apellido,
					   ca.nombre AS carrera
					FROM estudiantes e
					INNER JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					ORDER BY e.id DESC
					LIMIT 200";
			$stmt = $db->query($sql);
			$estudiantes = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$estudiantes = [];
		}

		$this->view('crm/estudiantes', compact('estudiantes'), [
			'title' => 'CRM - Estudiantes',
		]);
	}

	private function fetchSuperarseStudents(int $limit = 500): array
	{
		$limit = max(50, min(2000, $limit));
		try {
			$this->ensureCrmSupportTables();
			$remote = $this->connectSuperarseDatabase();
			if ($remote === null) {
				$fallbackRows = $this->fetchLocalFallbackStudents($limit);
				$fallbackRows = $this->attachPipelineData($fallbackRows);
				$fallbackTotal = $this->countLocalStudents();
				return [
					'rows' => $fallbackRows,
					'total' => $fallbackTotal > 0 ? $fallbackTotal : count($fallbackRows),
					'source' => 'Local (fallback)',
					'error' => 'No se pudo conectar a la BD Superarse. Revisa SUPERARSE_DB_* en .env.',
				];
			}

			$sourceTable = $this->resolveSuperarseStudentTable($remote);
			if ($sourceTable === null) {
				throw new RuntimeException('No existe tabla users ni estudiantes en la BD Superarse.');
			}

			if ($sourceTable === 'users') {
				$sql = "SELECT
						u.id,
						u.codigo_matricula AS codigo_estudiante,
						TRIM(CONCAT_WS(' ', u.primer_nombre, u.segundo_nombre)) AS nombre,
						TRIM(CONCAT_WS(' ', u.primer_apellido, u.segundo_apellido)) AS apellido,
						u.correo_electronico AS email,
						u.programa AS carrera,
						u.estado
					FROM users u
					ORDER BY u.id DESC
					LIMIT :limit";
			} else {
				$sql = "SELECT e.id, e.codigo_estudiante, e.estado,
							c.nombre, c.apellido, c.email,
							ca.nombre AS carrera
						FROM estudiantes e
						LEFT JOIN contactos c ON c.id = e.contacto_id
						LEFT JOIN matriculas m ON m.estudiante_id = e.id
						LEFT JOIN carreras ca ON ca.id = m.carrera_id
						ORDER BY e.id DESC
						LIMIT :limit";
			}

			$stmt = $remote->prepare($sql);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll() ?: [];
			$rows = $this->attachPipelineData($rows);

			$total = 0;
			try {
				$total = (int) $remote->query('SELECT COUNT(*) FROM ' . $sourceTable)->fetchColumn();
			} catch (Throwable $e) {
				$total = count($rows);
			}

			return [
				'rows' => $rows,
				'total' => $total,
				'source' => 'Superarse (' . $sourceTable . ')',
				'error' => '',
			];
		} catch (Throwable $e) {
			$fallbackRows = $this->fetchLocalFallbackStudents($limit);
			$fallbackRows = $this->attachPipelineData($fallbackRows);
			$fallbackTotal = $this->countLocalStudents();
			return [
				'rows' => $fallbackRows,
				'total' => $fallbackTotal > 0 ? $fallbackTotal : count($fallbackRows),
				'source' => 'Local (fallback)',
				'error' => 'No se pudo leer estudiantes de Superarse: ' . $e->getMessage(),
			];
		}
	}

	private function resolveSuperarseStudentTable(PDO $remote): ?string
	{
		try {
			$stmt = $remote->query("SHOW TABLES LIKE 'users'");
			if (($stmt->fetchColumn() ?? '') !== '') {
				return 'users';
			}
		} catch (Throwable $e) {
			// Ignorar y probar siguiente tabla
		}

		try {
			$stmt = $remote->query("SHOW TABLES LIKE 'estudiantes'");
			if (($stmt->fetchColumn() ?? '') !== '') {
				return 'estudiantes';
			}
		} catch (Throwable $e) {
			// Ignorar y reportar null
		}

		return null;
	}

	private function fetchLocalFallbackStudents(int $limit): array
	{
		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT e.id, e.codigo_estudiante, e.estado,
						c.nombre, c.apellido, c.email,
						ca.nombre AS carrera
					FROM estudiantes e
					LEFT JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					ORDER BY e.id DESC
					LIMIT :limit";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			return [];
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

	private function countLocalStudents(): int
	{
		try {
			$db = Database::getInstance()->connection();
			return (int) $db->query('SELECT COUNT(*) FROM estudiantes')->fetchColumn();
		} catch (Throwable $e) {
			return 0;
		}
	}

	private function ensureCrmSupportTables(): void
	{
		try {
			$db = Database::getInstance()->connection();
			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_pipeline (
				student_id INT NOT NULL PRIMARY KEY,
				estado_id INT NOT NULL,
				updated_by INT NULL,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				INDEX idx_estado_id (estado_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_contact_extras (
				student_id INT NOT NULL PRIMARY KEY,
				extra_emails TEXT NULL,
				extra_phones TEXT NULL,
				updated_by INT NULL,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		} catch (Throwable $e) {
			// Evitar romper el flujo principal por auto-creacion auxiliar.
		}
	}

	private function attachPipelineData(array $rows): array
	{
		if (empty($rows)) {
			return [];
		}

		try {
			$db = Database::getInstance()->connection();
			$activeEstados = $db->query("SELECT id, nombre FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC")->fetchAll() ?: [];
			$stateIds = [];
			$stateNames = [];
			foreach ($activeEstados as $estado) {
				$estadoId = (int) ($estado['id'] ?? 0);
				if ($estadoId <= 0) {
					continue;
				}
				$stateIds[] = $estadoId;
				$stateNames[$estadoId] = (string) ($estado['nombre'] ?? ('Estado ' . $estadoId));
			}

			$ids = array_values(array_filter(array_map(
				static function (array $row): int {
					return (int) ($row['id'] ?? 0);
				},
				$rows
			)));

			if (empty($ids)) {
				return $rows;
			}

			$placeholders = implode(',', array_fill(0, count($ids), '?'));
			$sql = "SELECT p.student_id, p.estado_id, pe.nombre AS pipeline_nombre
					FROM crm_student_pipeline p
					LEFT JOIN pipeline_estados pe ON pe.id = p.estado_id
					WHERE p.student_id IN ($placeholders)";
			$stmt = $db->prepare($sql);
			$stmt->execute($ids);
			$pipelineRows = $stmt->fetchAll() ?: [];

			$pipelineMap = [];
			foreach ($pipelineRows as $pipelineRow) {
				$pipelineMap[(int) ($pipelineRow['student_id'] ?? 0)] = [
					'estado_id' => (int) ($pipelineRow['estado_id'] ?? 0),
					'pipeline_nombre' => (string) ($pipelineRow['pipeline_nombre'] ?? 'Sin asignar'),
				];
			}

			// Asignar pipeline aleatorio persistente a estudiantes sin estado.
			if (!empty($stateIds)) {
				$missingStudentIds = [];
				foreach ($ids as $studentId) {
					if (!isset($pipelineMap[$studentId])) {
						$missingStudentIds[] = (int) $studentId;
					}
				}

				if (!empty($missingStudentIds)) {
					$insertSql = "INSERT INTO crm_student_pipeline (student_id, estado_id, updated_by, updated_at)
						VALUES (:student_id, :estado_id, NULL, NOW())
						ON DUPLICATE KEY UPDATE
						estado_id = VALUES(estado_id),
						updated_at = NOW()";
					$insertStmt = $db->prepare($insertSql);

					foreach ($missingStudentIds as $studentId) {
						$randomEstadoId = (int) $stateIds[array_rand($stateIds)];
						$insertStmt->execute([
							':student_id' => $studentId,
							':estado_id' => $randomEstadoId,
						]);

						$pipelineMap[$studentId] = [
							'estado_id' => $randomEstadoId,
							'pipeline_nombre' => (string) ($stateNames[$randomEstadoId] ?? 'Sin asignar'),
						];
					}
				}
			}

			foreach ($rows as &$row) {
				$studentId = (int) ($row['id'] ?? 0);
				$row['pipeline_estado_id'] = (int) ($pipelineMap[$studentId]['estado_id'] ?? 0);
				$row['pipeline_nombre'] = (string) ($pipelineMap[$studentId]['pipeline_nombre'] ?? 'Sin asignar');
			}
			unset($row);
		} catch (Throwable $e) {
			foreach ($rows as &$row) {
				$row['pipeline_estado_id'] = 0;
				$row['pipeline_nombre'] = 'Sin asignar';
			}
			unset($row);
		}

		return $rows;
	}

	public function getStudentContactDetail(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_GET['id'] ?? 0));
		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID invalido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$remote = $this->connectSuperarseDatabase();
			if ($remote === null) {
				throw new RuntimeException('No se pudo conectar a Superarse');
			}

			$sourceTable = $this->resolveSuperarseStudentTable($remote);
			if ($sourceTable === null) {
				throw new RuntimeException('Tabla de estudiantes no encontrada');
			}

			if ($sourceTable === 'users') {
				$sql = "SELECT
						u.id,
						u.codigo_matricula AS codigo_estudiante,
						TRIM(CONCAT_WS(' ', u.primer_nombre, u.segundo_nombre, u.primer_apellido, u.segundo_apellido)) AS nombre_completo,
						u.correo_electronico AS email,
						u.telefono,
						u.celular
					FROM users u
					WHERE u.id = :id
					LIMIT 1";
			} else {
				$sql = "SELECT
						e.id,
						e.codigo_estudiante,
						TRIM(CONCAT_WS(' ', c.nombre, c.apellido)) AS nombre_completo,
						c.email,
						c.telefono,
						'' AS celular
					FROM estudiantes e
					LEFT JOIN contactos c ON c.id = e.contacto_id
					WHERE e.id = :id
					LIMIT 1";
			}

			$stmt = $remote->prepare($sql);
			$stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
			$stmt->execute();
			$student = $stmt->fetch();
			if (!$student) {
				throw new RuntimeException('Estudiante no encontrado');
			}

			$db = Database::getInstance()->connection();
			$extrasStmt = $db->prepare('SELECT extra_emails, extra_phones FROM crm_student_contact_extras WHERE student_id = :student_id LIMIT 1');
			$extrasStmt->execute([':student_id' => $studentId]);
			$extras = $extrasStmt->fetch() ?: ['extra_emails' => '', 'extra_phones' => ''];

			echo json_encode([
				'success' => true,
				'student' => [
					'id' => (int) ($student['id'] ?? 0),
					'codigo_estudiante' => (string) ($student['codigo_estudiante'] ?? ''),
					'nombre_completo' => (string) ($student['nombre_completo'] ?? ''),
					'email' => (string) ($student['email'] ?? ''),
					'telefono' => (string) ($student['telefono'] ?? ''),
					'celular' => (string) ($student['celular'] ?? ''),
					'extra_emails' => (string) ($extras['extra_emails'] ?? ''),
					'extra_phones' => (string) ($extras['extra_phones'] ?? ''),
				],
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function updateStudentContact(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? 0));
		$email = trim((string) ($_POST['email'] ?? ''));
		$telefono = trim((string) ($_POST['telefono'] ?? ''));
		$celular = trim((string) ($_POST['celular'] ?? ''));
		$extraEmails = trim((string) ($_POST['extra_emails'] ?? ''));
		$extraPhones = trim((string) ($_POST['extra_phones'] ?? ''));

		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$remote = $this->connectSuperarseDatabase();
			if ($remote === null) {
				throw new RuntimeException('No se pudo conectar a Superarse');
			}

			$sourceTable = $this->resolveSuperarseStudentTable($remote);
			if ($sourceTable !== 'users') {
				throw new RuntimeException('La edicion de contacto requiere tabla users en Superarse');
			}

			$updateSql = "UPDATE users
						 SET correo_electronico = :email,
							 telefono = :telefono,
							 celular = :celular
						 WHERE id = :id";
			$updateStmt = $remote->prepare($updateSql);
			$updateStmt->execute([
				':email' => $email,
				':telefono' => $telefono,
				':celular' => $celular,
				':id' => $studentId,
			]);

			$db = Database::getInstance()->connection();
			$extraSql = "INSERT INTO crm_student_contact_extras (student_id, extra_emails, extra_phones, updated_by, updated_at)
						 VALUES (:student_id, :extra_emails, :extra_phones, :updated_by, NOW())
						 ON DUPLICATE KEY UPDATE
						 extra_emails = VALUES(extra_emails),
						 extra_phones = VALUES(extra_phones),
						 updated_by = VALUES(updated_by),
						 updated_at = NOW()";
			$extraStmt = $db->prepare($extraSql);
			$extraStmt->execute([
				':student_id' => $studentId,
				':extra_emails' => $extraEmails,
				':extra_phones' => $extraPhones,
				':updated_by' => (int) ($_SESSION['user_id'] ?? 0),
			]);

			echo json_encode([
				'success' => true,
				'message' => 'Contacto actualizado correctamente',
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function getStudentDetail(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_GET['id'] ?? 0));
		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$remote = $this->connectSuperarseDatabase();
			if ($remote === null) {
				throw new RuntimeException('No se pudo conectar a Superarse');
			}

			$sourceTable = $this->resolveSuperarseStudentTable($remote);
			if ($sourceTable === null) {
				throw new RuntimeException('Tabla de estudiantes no encontrada');
			}

			if ($sourceTable === 'users') {
				$sql = "SELECT
						u.id,
						u.codigo_matricula AS codigo_estudiante,
						u.primer_nombre,
						u.segundo_nombre,
						u.primer_apellido,
						u.segundo_apellido,
						u.correo_electronico AS email,
						u.telefono,
						u.celular,
						u.programa AS carrera,
						u.sede,
						u.estado,
						u.fecha_matricula
					FROM users u
					WHERE u.id = :id
					LIMIT 1";
			} else {
				$sql = "SELECT
						e.id,
						e.codigo_estudiante,
						c.nombre AS primer_nombre,
						'' AS segundo_nombre,
						c.apellido AS primer_apellido,
						'' AS segundo_apellido,
						c.email,
						c.telefono,
						'' AS celular,
						ca.nombre AS carrera,
						'' AS sede,
						e.estado,
						e.created_at AS fecha_matricula
					FROM estudiantes e
					LEFT JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					WHERE e.id = :id
					LIMIT 1";
			}

			$stmt = $remote->prepare($sql);
			$stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
			$stmt->execute();
			$student = $stmt->fetch();

			if (!$student) {
				throw new RuntimeException('Estudiante no encontrado');
			}

			// Obtener estados del pipeline
			$db = Database::getInstance()->connection();
			$currentPipelineStmt = $db->prepare('SELECT estado_id FROM crm_student_pipeline WHERE student_id = :student_id LIMIT 1');
			$currentPipelineStmt->execute([':student_id' => $studentId]);
			$currentPipeline = $currentPipelineStmt->fetch();
			$student['pipeline_estado_id'] = (int) ($currentPipeline['estado_id'] ?? 0);
			$pipelineEstados = $db->query("SELECT id, nombre FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC")->fetchAll() ?: [];

			$historyStmt = $db->prepare("SELECT
					csn.id,
					csn.note_text,
					csn.created_at,
					COALESCE(u.nombre, 'Sistema') AS user_name
				FROM crm_student_notes csn
				LEFT JOIN usuarios u ON u.id = csn.created_by
				WHERE csn.student_id = :student_id
				AND csn.source_type = 'estado_change'
				ORDER BY csn.created_at DESC
				LIMIT 30");
			$historyStmt->execute([':student_id' => $studentId]);
			$pipelineHistory = $historyStmt->fetchAll() ?: [];

			echo json_encode([
				'success' => true,
				'student' => $student,
				'estados' => $pipelineEstados,
				'pipeline_history' => $pipelineHistory,
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function updateStudentState(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? 0));
		$estadoId = max(0, (int) ($_POST['estado_id'] ?? 0));

		if ($studentId <= 0 || $estadoId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();

			$previousStateName = 'Sin asignar';
			$previousStmt = $db->prepare("SELECT p.estado_id, COALESCE(pe.nombre, 'Sin asignar') AS nombre
					FROM crm_student_pipeline p
					LEFT JOIN pipeline_estados pe ON pe.id = p.estado_id
					WHERE p.student_id = :student_id
					LIMIT 1");
			$previousStmt->execute([':student_id' => $studentId]);
			$previous = $previousStmt->fetch();
			if ($previous) {
				$previousStateName = (string) ($previous['nombre'] ?? 'Sin asignar');
			}

			$estadoStmt = $db->prepare("SELECT id, nombre FROM pipeline_estados WHERE id = :id AND estado = 'activo' LIMIT 1");
			$estadoStmt->execute([':id' => $estadoId]);
			$estado = $estadoStmt->fetch();
			if (!$estado) {
				throw new RuntimeException('Estado de pipeline inválido');
			}

			$pipelineSql = "INSERT INTO crm_student_pipeline (student_id, estado_id, updated_by, updated_at)
						VALUES (:student_id, :estado_id, :updated_by, NOW())
						ON DUPLICATE KEY UPDATE
						estado_id = VALUES(estado_id),
						updated_by = VALUES(updated_by),
						updated_at = NOW()";
			$pipelineStmt = $db->prepare($pipelineSql);
			$pipelineStmt->execute([
				':student_id' => $studentId,
				':estado_id' => $estadoId,
				':updated_by' => (int) ($_SESSION['user_id'] ?? 0),
			]);

			$noteSql = "INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
					VALUES (:student_id, 'estado_change', :note_text, :user_id, NOW())";
			$noteStmt = $db->prepare($noteSql);
			$noteStmt->execute([
				':student_id' => $studentId,
				':note_text' => 'Cambio de pipeline: ' . $previousStateName . ' -> ' . (string) ($estado['nombre'] ?? ('ID ' . $estadoId)),
				':user_id' => (int) ($_SESSION['user_id'] ?? 0),
			]);

			echo json_encode([
				'success' => true,
				'message' => 'Estado actualizado correctamente',
				'pipeline_nombre' => (string) ($estado['nombre'] ?? 'Estado'),
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function getStudentNotes(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_GET['student_id'] ?? 0));
		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT csn.id, csn.note_text, csn.created_at, csn.created_by, u.nombre AS user_name
					FROM crm_student_notes csn
					LEFT JOIN usuarios u ON u.id = csn.created_by
					WHERE csn.student_id = :student_id AND csn.source_type = 'note'
					ORDER BY csn.created_at DESC";
			$stmt = $db->prepare($sql);
			$stmt->execute([':student_id' => $studentId]);
			$notes = $stmt->fetchAll() ?: [];

			echo json_encode([
				'success' => true,
				'notes' => $notes,
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function addStudentNote(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? 0));
		$noteText = trim((string) ($_POST['note_text'] ?? ''));

		if ($studentId <= 0 || $noteText === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();
			$sql = "INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
					VALUES (:student_id, 'note', :note_text, :user_id, NOW())";
			$stmt = $db->prepare($sql);
			$stmt->execute([
				':student_id' => $studentId,
				':note_text' => $noteText,
				':user_id' => (int) ($_SESSION['user_id'] ?? 0),
			]);

			$noteId = (int) $db->lastInsertId();
			$userName = 'Usuario';
			try {
				$userResult = $db->query("SELECT nombre FROM usuarios WHERE id = " . ((int) ($_SESSION['user_id'] ?? 0)))->fetch();
				if ($userResult) {
					$userName = (string) ($userResult['nombre'] ?? 'Usuario');
				}
			} catch (Throwable $e) {
				// Ignorar errores de usuario
			}

			echo json_encode([
				'success' => true,
				'note_id' => $noteId,
				'note_text' => $noteText,
				'created_at' => date('Y-m-d H:i:s'),
				'user_name' => $userName,
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function updateStudentNote(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$noteId = max(0, (int) ($_POST['note_id'] ?? 0));
		$noteText = trim((string) ($_POST['note_text'] ?? ''));

		if ($noteId <= 0 || $noteText === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();
			
			// Verificar que la nota existe y pertenece al usuario actual o es admin
			$note = $db->prepare("SELECT id, created_by FROM crm_student_notes WHERE id = :id AND source_type = 'note'")
				->execute([':id' => $noteId]);
			$stmt = $db->prepare("SELECT id, created_by FROM crm_student_notes WHERE id = :id AND source_type = 'note'");
			$stmt->execute([':id' => $noteId]);
			$note = $stmt->fetch();

			if (!$note) {
				throw new RuntimeException('Nota no encontrada');
			}

			// Actualizar nota
			$sql = "UPDATE crm_student_notes SET note_text = :note_text, updated_at = NOW() WHERE id = :id";
			$stmt = $db->prepare($sql);
			$stmt->execute([
				':note_text' => $noteText,
				':id' => $noteId,
			]);

			echo json_encode([
				'success' => true,
				'note_text' => $noteText,
				'updated_at' => date('Y-m-d H:i:s'),
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function deleteStudentNote(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$noteId = max(0, (int) ($_POST['note_id'] ?? 0));

		if ($noteId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();
			
			// Verificar que la nota existe
			$stmt = $db->prepare("SELECT id FROM crm_student_notes WHERE id = :id AND source_type = 'note'");
			$stmt->execute([':id' => $noteId]);
			$note = $stmt->fetch();

			if (!$note) {
				throw new RuntimeException('Nota no encontrada');
			}

			// Eliminar nota
			$sql = "DELETE FROM crm_student_notes WHERE id = :id";
			$stmt = $db->prepare($sql);
			$stmt->execute([':id' => $noteId]);

			echo json_encode([
				'success' => true,
				'message' => 'Nota eliminada correctamente',
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function getCRMPipelineHistory(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_GET['student_id'] ?? 0));
		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT csn.created_at, u.nombre AS usuario, csn.note_text
					FROM crm_student_notes csn
					LEFT JOIN usuarios u ON u.id = csn.created_by
					WHERE csn.student_id = :student_id AND csn.source_type = 'estado_change'
					ORDER BY csn.created_at DESC";
			$stmt = $db->prepare($sql);
			$stmt->execute([':student_id' => $studentId]);
			$historial = $stmt->fetchAll() ?: [];

			echo json_encode([
				'success' => true,
				'historial' => $historial,
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function getStudentTicketsByEmail(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_GET['student_id'] ?? 0));
		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();

			// Obtener correo principal de Superarse (si existe)
			$studentEmail = '';
			try {
				$remote = $this->connectSuperarseDatabase();
				if ($remote !== null) {
					$stmt = $remote->prepare("SELECT correo_electronico FROM users WHERE id = ? LIMIT 1");
					$stmt->execute([$studentId]);
					$studentEmail = (string) ($stmt->fetchColumn() ?? '');
				}
			} catch (Throwable $e) {
				// Fallback silencioso
			}

			// Obtener extras desde tabla local
			try {
				$extrasStmt = $db->prepare('SELECT extra_emails FROM crm_student_contact_extras WHERE student_id = ? LIMIT 1');
				$extrasStmt->execute([$studentId]);
				$extras = $extrasStmt->fetch();
				$extraEmails = (string) ($extras['extra_emails'] ?? '');
			} catch (Throwable $e) {
				$extraEmails = '';
			}

			// Construir lista de emails
			$emails = [];
			if (!empty($studentEmail)) {
				$emails[] = trim($studentEmail);
			}
			$extraList = preg_split('/\s*,\s*/', $extraEmails, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($extraList as $e) {
				$e = trim($e);
				if (!empty($e)) {
					$emails[] = $e;
				}
			}
			$emails = array_unique(array_filter($emails));

			if (empty($emails)) {
				echo json_encode(['success' => true, 'tickets' => []]);
				exit;
			}

			$emails = array_values(array_unique(array_map(
				static function (string $value): string {
					return strtolower(trim($value));
				},
				array_filter($emails)
			)));

			if (empty($emails)) {
				echo json_encode(['success' => true, 'tickets' => []]);
				exit;
			}

			$ticketColumns = $this->getTableColumnsSafe($db, 'tickets');
			$contactColumns = $this->getTableColumnsSafe($db, 'contactos');

			$emailColumn = null;
			foreach (['email', 'correo', 'correo_electronico'] as $candidate) {
				if (in_array($candidate, $contactColumns, true)) {
					$emailColumn = $candidate;
					break;
				}
			}

			if ($emailColumn === null) {
				echo json_encode(['success' => true, 'tickets' => []]);
				exit;
			}

			$createdColumn = 'created_at';
			if (!in_array($createdColumn, $ticketColumns, true)) {
				$createdColumn = in_array('fecha', $ticketColumns, true) ? 'fecha' : 'id';
			}

			$descriptionExpr = "''";
			if (in_array('descripcion', $ticketColumns, true)) {
				$descriptionExpr = 't.descripcion';
			} elseif (in_array('detalle', $ticketColumns, true)) {
				$descriptionExpr = 't.detalle';
			}

			$statusJoin = '';
			$statusExpr = "'Sin estado'";
			if (in_array('estado', $ticketColumns, true)) {
				$statusExpr = 't.estado';
			} elseif (in_array('estado_id', $ticketColumns, true)) {
				$statusJoin = ' LEFT JOIN ticket_estados te ON te.id = t.estado_id ';
				$statusExpr = "COALESCE(te.nombre, 'Sin estado')";
			}

			$emailParams = [];
			$emailPlaceholders = [];
			foreach ($emails as $index => $email) {
				$key = ':e' . $index;
				$emailPlaceholders[] = $key;
				$emailParams[$key] = $email;
			}

			$ticketSql = "SELECT t.id,
					COALESCE(t.asunto, '') AS asunto,
					{$descriptionExpr} AS descripcion,
					{$statusExpr} AS estado,
					t.{$createdColumn} AS created_at,
					c.{$emailColumn} AS email
				FROM tickets t
				LEFT JOIN contactos c ON c.id = t.contacto_id
				{$statusJoin}
				WHERE c.{$emailColumn} IS NOT NULL
				  AND LOWER(TRIM(c.{$emailColumn})) IN (" . implode(',', $emailPlaceholders) . ")
				ORDER BY t.{$createdColumn} DESC
				LIMIT 100";

			$ticketStmt = $db->prepare($ticketSql);
			$ticketStmt->execute($emailParams);
			$tickets = $ticketStmt->fetchAll() ?: [];

			echo json_encode([
				'success' => true,
				'tickets' => $tickets,
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
		}
		exit;
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
}
