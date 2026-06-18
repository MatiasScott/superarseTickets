<?php

class CRMController extends Controller
{
	public function dashboard(): void
	{
		Auth::requireAuth();

		$admisionesRows = [];
		$matriculasRows = [];
		$docenciaRows = [];

		$kpiMatriculas3233 = [
			'label' => 'KPI etapas 3.2 + 3.3 por nivel',
			'levels' => $this->dashboardEmptyLevelBucket(),
			'total' => 0,
		];
		$kpiMatriculas45 = [
			'label' => 'KPI etapas 4 + 5 por nivel',
			'levels' => $this->dashboardEmptyLevelBucket(),
			'total' => 0,
		];

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$stageMetaByEstadoId = [];
			$stageKeyByEstadoId = [];
			$activeEstados = $db->query("SELECT id, nombre, categoria, orden FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC")->fetchAll() ?: [];
			foreach ($activeEstados as $estado) {
				$estadoId = (int) ($estado['id'] ?? 0);
				if ($estadoId <= 0) {
					continue;
				}

				$area = $this->dashboardResolveCrmAreaFromState($estado);
				if ($area === null) {
					continue;
				}

				$label = trim((string) ($estado['nombre'] ?? ''));
				if ($label === '') {
					$label = 'Etapa #' . $estadoId;
				}

				$stageMetaByEstadoId[$estadoId] = [
					'area' => $area,
					'label' => $label,
				];

				if ($area === 'admisiones') {
					$admisionesRows[$estadoId] = ['label' => $label, 'count' => 0];
				} elseif ($area === 'matriculas') {
					$matriculasRows[$estadoId] = ['label' => $label, 'levels' => $this->dashboardEmptyLevelBucket(), 'total' => 0];
				} elseif ($area === 'docencia') {
					$docenciaRows[$estadoId] = ['label' => $label, 'levels' => $this->dashboardEmptyLevelBucket(), 'total' => 0];
				}

				$stageKeyByEstadoId[$estadoId] = $this->dashboardResolveStageKeyFromState($estado);
			}

			$pipelineRows = $db->query("SELECT p.student_id, p.estado_id, COALESCE(pe.nombre, '') AS estado_nombre
				FROM crm_student_pipeline p
				LEFT JOIN pipeline_estados pe ON pe.id = p.estado_id")->fetchAll() ?: [];

			$userLevels = [];
			$remote = $this->connectSuperarseDatabase();
			if ($remote instanceof PDO && $this->resolveSuperarseStudentTable($remote) === 'users') {
				$userRows = $remote->query('SELECT id, nivel FROM users')->fetchAll() ?: [];
				foreach ($userRows as $userRow) {
					$studentId = (int) ($userRow['id'] ?? 0);
					if ($studentId <= 0) {
						continue;
					}
					$nivel = $this->dashboardExtractNivel((string) ($userRow['nivel'] ?? ''));
					if ($nivel !== null) {
						$userLevels[$studentId] = $nivel;
					}
				}
			}

			foreach ($pipelineRows as $row) {
				$estadoId = (int) ($row['estado_id'] ?? 0);
				$stageName = trim((string) ($row['estado_nombre'] ?? ''));
				$stageMeta = $stageMetaByEstadoId[$estadoId] ?? null;

				if ($stageMeta === null) {
					$fallbackArea = $this->dashboardResolveCrmAreaFromState([
						'nombre' => $stageName,
						'categoria' => '',
					]);
					if ($fallbackArea === null) {
						continue;
					}

					$fallbackLabel = $stageName !== '' ? $stageName : 'Sin etapa';
					$fallbackKey = 'fallback:' . md5($fallbackArea . '|' . $fallbackLabel);
					$stageMeta = [
						'area' => $fallbackArea,
						'label' => $fallbackLabel,
					];

					if ($fallbackArea === 'admisiones' && !isset($admisionesRows[$fallbackKey])) {
						$admisionesRows[$fallbackKey] = ['label' => $fallbackLabel, 'count' => 0];
					} elseif ($fallbackArea === 'matriculas' && !isset($matriculasRows[$fallbackKey])) {
						$matriculasRows[$fallbackKey] = ['label' => $fallbackLabel, 'levels' => $this->dashboardEmptyLevelBucket(), 'total' => 0];
					} elseif ($fallbackArea === 'docencia' && !isset($docenciaRows[$fallbackKey])) {
						$docenciaRows[$fallbackKey] = ['label' => $fallbackLabel, 'levels' => $this->dashboardEmptyLevelBucket(), 'total' => 0];
					}

					$stageMetaByEstadoId[$fallbackKey] = $stageMeta;
					if (!isset($stageKeyByEstadoId[$fallbackKey])) {
						$stageKeyByEstadoId[$fallbackKey] = $this->dashboardResolveStageKey($fallbackLabel);
					}
					$estadoId = $fallbackKey;
				}

				if ($stageMeta['area'] === 'admisiones' && isset($admisionesRows[$estadoId])) {
					$admisionesRows[$estadoId]['count']++;
					continue;
				}

				$studentId = (int) ($row['student_id'] ?? 0);
				$nivel = $userLevels[$studentId] ?? null;

				if ($stageMeta['area'] === 'matriculas' && isset($matriculasRows[$estadoId])) {
					$this->dashboardIncrementByLevel($matriculasRows[$estadoId], $nivel);

					$stageKey = $stageKeyByEstadoId[$estadoId] ?? $this->dashboardResolveStageKey($stageName);
					if ($stageKey === 'mat_32' || $stageKey === 'mat_33') {
						$this->dashboardIncrementByLevel($kpiMatriculas3233, $nivel);
					}

					if ($stageKey === 'mat_4' || $stageKey === 'mat_5') {
						$this->dashboardIncrementByLevel($kpiMatriculas45, $nivel);
					}
					continue;
				}

				if ($stageMeta['area'] === 'docencia' && isset($docenciaRows[$estadoId])) {
					$this->dashboardIncrementByLevel($docenciaRows[$estadoId], $nivel);
				}
			}
		} catch (Throwable $e) {
			// Si falla alguna fuente de datos, renderiza en cero para no romper la vista.
		}

		$this->view('crm/dashboard', [
			'admisionesRows' => array_values($admisionesRows),
			'matriculasRows' => array_values($matriculasRows),
			'docenciaRows' => array_values($docenciaRows),
			'kpiMatriculas3233' => $kpiMatriculas3233,
			'kpiMatriculas45' => $kpiMatriculas45,
		], [
			'title' => 'CRM - Dashboard',
		]);
	}

	private function dashboardEmptyLevelBucket(): array
	{
		return [
			'1' => 0,
			'2' => 0,
			'3' => 0,
			'4' => 0,
		];
	}

	private function dashboardExtractNivel(string $rawNivel): ?string
	{
		$rawNivel = trim($rawNivel);
		if ($rawNivel === '') {
			return null;
		}

		if (preg_match('/([1-4])/', $rawNivel, $matches) !== 1) {
			return null;
		}

		return (string) ($matches[1] ?? '');
	}

	private function dashboardNormalizeLabel(string $value): string
	{
		$value = mb_strtolower(trim($value), 'UTF-8');
		$value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
		$value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
		return trim(preg_replace('/\s+/', ' ', $value) ?: '');
	}

	private function dashboardResolveCrmAreaFromState(array $state): ?string
	{
		$normalizedCategory = $this->dashboardNormalizeLabel((string) ($state['categoria'] ?? ''));
		$normalizedName = $this->dashboardNormalizeLabel((string) ($state['nombre'] ?? ''));

		$areaAliases = [
			'admisiones' => ['admisiones', 'admision', 'adm'],
			'matriculas' => ['matriculas', 'matricula', 'mat'],
			'docencia' => ['docencia', 'docente', 'doc'],
		];

		foreach ($areaAliases as $area => $aliases) {
			foreach ($aliases as $alias) {
				if ($normalizedCategory === $alias || strpos($normalizedCategory, $alias . ' ') === 0 || strpos($normalizedCategory, ' ' . $alias . ' ') !== false || substr($normalizedCategory, -strlen(' ' . $alias)) === ' ' . $alias) {
					return $area;
				}
			}
		}

		$stageKey = $this->dashboardResolveStageKey((string) ($state['nombre'] ?? ''));
		if ($stageKey !== null) {
			if (strpos($stageKey, 'adm_') === 0) {
				return 'admisiones';
			}
			if (strpos($stageKey, 'mat_') === 0) {
				return 'matriculas';
			}
			if (strpos($stageKey, 'doc_') === 0) {
				return 'docencia';
			}
		}

		if (strpos($normalizedName, 'riesgo') !== false || strpos($normalizedName, 'egresado') !== false || strpos($normalizedName, 'graduado') !== false || strpos($normalizedName, 'retiro') !== false) {
			return 'docencia';
		}

		return null;
	}

	private function dashboardResolveStageKey(string $stageName): ?string
	{
		$code = $this->dashboardExtractStageCode($stageName);
		$byCode = [
			'1' => 'adm_1',
			'2' => 'adm_2',
			'3.1' => 'mat_31',
			'3.2' => 'mat_32',
			'3.3' => 'mat_33',
			'4' => 'mat_4',
			'5' => 'mat_5',
		];
		if ($code !== null && isset($byCode[$code])) {
			return $byCode[$code];
		}

		$normalized = $this->dashboardNormalizeLabel($stageName);
		if ($normalized === '') {
			return null;
		}

		$aliases = [
			'adm_1' => ['etapa interesados'],
			'adm_2' => ['etapa seguimiento'],
			'adm_no_legaliza' => ['siguiente periodo no legaliza matricula'],
			'adm_descalificado' => ['descalificado'],
			'mat_31' => ['etapa nuevos siguiente paso', 'etapa nuevos siguiente pao'],
			'mat_32' => ['inscrito reingreso y homologacion siguiente paso', 'inscrito reingreso y homologacion siguiente pao'],
			'mat_33' => ['pendiente prematricula antiguos siguiente paso', 'pendiente prematricula antiguos siguiente pao'],
			'mat_4' => ['prematricula nuevos y antiguos siguiente paso', 'prematricula nuevos y antiguos siguiente pao'],
			'mat_5' => ['etapa matriculado pao actual', 'etapa matriculado paso actual'],
			'doc_riesgo_financiero' => ['riesgo financiero'],
			'doc_riesgo_academico' => ['riesgo academico'],
			'doc_riesgo_af' => ['riesgo a f', 'riesgo af'],
			'doc_retiro_anulacion' => ['retiro y anulacion de matricula'],
			'doc_egresado' => ['egresado'],
			'doc_graduado' => ['graduado'],
		];

		foreach ($aliases as $key => $checks) {
			foreach ($checks as $check) {
				if (strpos($normalized, $check) !== false) {
					return $key;
				}
			}
		}

		return null;
	}

	private function dashboardResolveStageKeyFromState(array $state): ?string
	{
		$stageName = (string) ($state['nombre'] ?? '');
		$stageCategory = $this->dashboardNormalizeLabel((string) ($state['categoria'] ?? ''));

		$resolved = $this->dashboardResolveStageKey($stageName);
		if ($resolved !== null) {
			return $resolved;
		}

		if ($stageCategory !== '') {
			$resolvedByCategory = $this->dashboardResolveStageKey($stageCategory);
			if ($resolvedByCategory !== null) {
				return $resolvedByCategory;
			}
		}

		return null;
	}

	private function dashboardExtractStageCode(string $stageName): ?string
	{
		$raw = mb_strtolower(trim($stageName), 'UTF-8');
		$raw = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw) ?: $raw;

		if (preg_match('/\b(3\.[123])\b/', $raw, $matches) === 1) {
			return (string) ($matches[1] ?? '');
		}

		if (preg_match('/\b([1245])\b/', $raw, $matches) === 1) {
			return (string) ($matches[1] ?? '');
		}

		return null;
	}

	private function dashboardIncrementByLevel(array &$bucket, ?string $level): void
	{
		$bucket['total']++;

		if ($level === null || !isset($bucket['levels'][$level])) {
			return;
		}

		$bucket['levels'][$level]++;
	}

	public function interesados(): void
	{
		Auth::requireAuth();
		$this->ensureCrmSupportTables();
		$periodoFiltro = $this->sanitizePeriodoKey((string) ($_GET['periodo'] ?? ''));

		$studentsPerPage = 25;
		$studentPage = max(1, (int) ($_GET['student_page'] ?? 1));
		$studentOffset = ($studentPage - 1) * $studentsPerPage;
		$studentsData = $this->fetchSuperarseStudents($studentsPerPage, $periodoFiltro, $studentOffset);
		$estudiantesSuperarse = is_array($studentsData['rows'] ?? null) ? $studentsData['rows'] : [];
		$periodos = is_array($studentsData['periodos'] ?? null) ? $studentsData['periodos'] : [];
		$totalStudents = max(0, (int) ($studentsData['total'] ?? count($estudiantesSuperarse)));
		$studentPages = max(1, (int) ceil($totalStudents / $studentsPerPage));
		$studentPage = min($studentPage, $studentPages);
		if ($studentPage > 0 && $studentOffset !== (($studentPage - 1) * $studentsPerPage)) {
			$studentOffset = ($studentPage - 1) * $studentsPerPage;
			$studentsData = $this->fetchSuperarseStudents($studentsPerPage, $periodoFiltro, $studentOffset);
			$estudiantesSuperarse = is_array($studentsData['rows'] ?? null) ? $studentsData['rows'] : [];
			$periodos = is_array($studentsData['periodos'] ?? null) ? $studentsData['periodos'] : $periodos;
		}

		$prospectPerPage = 25;
		$prospectPage = max(1, (int) ($_GET['prospect_page'] ?? 1));
		$totalProspects = $this->countLocalProspects();
		$prospectPages = max(1, (int) ceil($totalProspects / $prospectPerPage));
		$prospectPage = min($prospectPage, $prospectPages);
		$prospectosLocales = $this->fetchLocalProspects($prospectPerPage, ($prospectPage - 1) * $prospectPerPage);

		$this->view('crm/interesados', [
			'estudiantesSuperarse' => $estudiantesSuperarse,
			'studentPage'      => $studentPage,
			'studentPages'     => $studentPages,
			'studentsPerPage'  => $studentsPerPage,
			'totalStudents'    => $totalStudents,
			'prospectosLocales' => $prospectosLocales,
			'periodos' => $periodos,
			'periodoSeleccionado' => $periodoFiltro,
			'sourceLabel' => (string) ($studentsData['source'] ?? 'No disponible'),
			'sourceError' => (string) ($studentsData['error'] ?? ''),
			'prospectPage'   => $prospectPage,
			'prospectPages'  => $prospectPages,
			'totalProspects' => $totalProspects,
		], [
			'title' => 'CRM - Ver todo CRM',
		]);
	}

	public function createProspect(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('crm/interesados');
		}

		$nombres = trim((string) ($_POST['nombres'] ?? ''));
		$apellidos = trim((string) ($_POST['apellidos'] ?? ''));
		$identificacion = $this->normalizeIdentityValue((string) ($_POST['identificacion'] ?? ''));
		$celular = $this->normalizePhoneValue((string) ($_POST['celular'] ?? ''));
		$correoPersonal = $this->normalizeEmailValue((string) ($_POST['correo_personal'] ?? ''));
		$origen = trim((string) ($_POST['origen'] ?? 'crm_manual'));

		if ($nombres === '' || $apellidos === '' || $identificacion === '') {
			set_flash('error', 'Nombres, apellidos e identificacion son obligatorios.');
			redirect('crm/interesados');
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$db->beginTransaction();

			$contactId = $this->findPrimaryContactIdForMerge($db, $identificacion);
			$isNew = false;
			if ($contactId === null) {
				if ($correoPersonal !== '') {
					$byEmail = $this->resolveContactByEmailFallback($db, $correoPersonal);
					if ($byEmail !== null) {
						$contactId = $byEmail;
					}
				}
			}

			if ($contactId === null) {
				$emailToStore = $correoPersonal;
				if ($emailToStore !== '' && !$this->canUseEmailAsPrimaryContact($db, $emailToStore, null)) {
					$emailToStore = '';
				}

				$insertContact = $db->prepare('INSERT INTO contactos (nombre, apellido, cedula, email, tipo, estado, created_at, updated_at)
					VALUES (:nombre, :apellido, :cedula, :email, "externo", "activo", NOW(), NOW())');
				$insertContact->execute([
					'nombre' => mb_substr($nombres, 0, 150),
					'apellido' => mb_substr($apellidos, 0, 150),
					'cedula' => mb_substr($identificacion, 0, 20),
					'email' => $emailToStore !== '' ? $emailToStore : null,
				]);
				$contactId = (int) $db->lastInsertId();
				$isNew = true;
			} else {
				$updateContact = $db->prepare('UPDATE contactos
					SET nombre = :nombre,
						apellido = :apellido,
						cedula = :cedula,
						estado = "activo",
						updated_at = NOW()
					WHERE id = :id
					LIMIT 1');
				$updateContact->execute([
					'nombre' => mb_substr($nombres, 0, 150),
					'apellido' => mb_substr($apellidos, 0, 150),
					'cedula' => mb_substr($identificacion, 0, 20),
					'id' => $contactId,
				]);
			}

			if ($correoPersonal !== '') {
				$this->upsertContactEmail($db, $contactId, $correoPersonal, 'personal');
				$this->addContactChannel($db, $contactId, 'email', $correoPersonal, 'crm_manual');
			}

			if ($celular !== '') {
				$this->upsertContactPhone($db, $contactId, $celular, 'principal');
				$this->addContactChannel($db, $contactId, 'phone', $celular, 'crm_manual');
			}

			$estadoInicialId = $this->resolveInitialProspectStateId($db);
			$this->upsertInteresado($db, $contactId, $estadoInicialId, $origen !== '' ? $origen : 'crm_manual');

			$db->commit();

			// Registrar evento histórico inicial en el timeline CRM
			try {
				$origenLabel = $origen !== '' ? $origen : 'crm_manual';
				$etapaLabel  = 'Sin etapa';
				if ($estadoInicialId !== null) {
					$etapaStmt = $db->prepare('SELECT nombre FROM pipeline_estados WHERE id = :id LIMIT 1');
					$etapaStmt->execute([':id' => $estadoInicialId]);
					$etapaLabel = (string) ($etapaStmt->fetchColumn() ?: 'Sin etapa');
				}
				$noteText = sprintf(
					'Creó el cliente potencial. Etapa inicial: %s. Origen: %s.',
					$etapaLabel,
					$origenLabel
				);
				$this->crmHistoryNote($contactId, 'prospect_created', $noteText);
			} catch (Throwable $ignore) {
				// No interrumpir el flujo por el historial
			}

			set_flash('success', $isNew
				? 'Cliente potencial creado correctamente.'
				: 'Cliente potencial actualizado y vinculado correctamente.');
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			set_flash('error', 'No se pudo crear el cliente potencial: ' . $e->getMessage());
		}

		redirect('crm/interesados');
	}

	public function estudiantes(): void
	{
		Auth::requireAuth();
		$estudiantes = [];

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT e.id, e.numero_identificacion, e.estado,
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

	private function fetchSuperarseStudents(int $limit = 500, string $periodoFiltro = '', int $offset = 0): array
	{
		$limit = max(1, min(2000, $limit));
		$offset = max(0, $offset);
		$periodoFiltro = $this->sanitizePeriodoKey($periodoFiltro);
		try {
			$this->ensureCrmSupportTables();
			$remote = $this->connectSuperarseDatabase();
			if ($remote === null) {
				$fallbackRows = $this->fetchLocalFallbackStudents($limit, $periodoFiltro, $offset);
				$fallbackRows = $this->attachPipelineData($fallbackRows);
				$fallbackTotal = $this->countLocalStudents($periodoFiltro);
				$periodos = $this->fetchLocalPeriodOptions();
				return [
					'rows' => $fallbackRows,
					'total' => $fallbackTotal > 0 ? $fallbackTotal : count($fallbackRows),
					'periodos' => $periodos,
					'source' => 'Local (fallback)',
					'error' => 'No se pudo conectar a la BD Superarse. Revisa SUPERARSE_DB_* en .env.',
				];
			}

			$sourceTable = $this->resolveSuperarseStudentTable($remote);
			if ($sourceTable === null) {
				throw new RuntimeException('No existe tabla users ni estudiantes en la BD Superarse.');
			}

			$periodos = $this->fetchRemotePeriodOptions($remote, $sourceTable);
			$params = [];

			if ($sourceTable === 'users') {
				$whereSql = '';
				if ($periodoFiltro !== '') {
					$whereSql = "WHERE TRIM(COALESCE(u.periodo, '')) = :periodo";
					$params[':periodo'] = $periodoFiltro;
				}

				$sql = "SELECT
						u.id,
						u.numero_identificacion AS numero_identificacion,
						TRIM(CONCAT_WS(' ', u.primer_nombre, u.segundo_nombre)) AS nombre,
						TRIM(CONCAT_WS(' ', u.primer_apellido, u.segundo_apellido)) AS apellido,
						u.correo_electronico AS email,
						u.programa AS carrera,
						u.nivel,
						u.periodo,
						u.estado,
						u.fecha_matricula,
						TRIM(COALESCE(u.periodo, '')) AS periodo_clave
					FROM users u
					$whereSql
					ORDER BY u.id DESC
					LIMIT :limit OFFSET :offset";
			} else {
				$whereSql = '';
				if ($periodoFiltro !== '') {
					$whereSql = "WHERE DATE_FORMAT(e.created_at, '%Y-%m') = :periodo";
					$params[':periodo'] = $periodoFiltro;
				}

				$sql = "SELECT e.id, e.codigo_estudiante, e.estado,
						'' AS nivel,
						'' AS periodo,
							c.nombre, c.apellido, c.email,
							ca.nombre AS carrera,
							e.created_at AS fecha_matricula,
							DATE_FORMAT(e.created_at, '%Y-%m') AS periodo_clave
						FROM estudiantes e
						LEFT JOIN contactos c ON c.id = e.contacto_id
						LEFT JOIN matriculas m ON m.estudiante_id = e.id
						LEFT JOIN carreras ca ON ca.id = m.carrera_id
						$whereSql
						ORDER BY e.id DESC
						LIMIT :limit OFFSET :offset";
			}

			$stmt = $remote->prepare($sql);
			if ($periodoFiltro !== '') {
				$stmt->bindValue(':periodo', $periodoFiltro, PDO::PARAM_STR);
			}
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll() ?: [];
			$rows = $this->attachPipelineData($rows);

			$total = 0;
			try {
				if ($sourceTable === 'users') {
					$countSql = 'SELECT COUNT(*) FROM users u';
					if ($periodoFiltro !== '') {
						$countSql .= " WHERE TRIM(COALESCE(u.periodo, '')) = :periodo";
					}
				} else {
					$countSql = 'SELECT COUNT(*) FROM estudiantes e';
					if ($periodoFiltro !== '') {
						$countSql .= " WHERE DATE_FORMAT(e.created_at, '%Y-%m') = :periodo";
					}
				}

				$countStmt = $remote->prepare($countSql);
				if ($periodoFiltro !== '') {
					$countStmt->bindValue(':periodo', $periodoFiltro, PDO::PARAM_STR);
				}
				$countStmt->execute();
				$total = (int) $countStmt->fetchColumn();
			} catch (Throwable $e) {
				$total = count($rows);
			}

			return [
				'rows' => $rows,
				'total' => $total,
				'periodos' => $periodos,
				'source' => 'Superarse (' . $sourceTable . ')',
				'error' => '',
			];
		} catch (Throwable $e) {
			$fallbackRows = $this->fetchLocalFallbackStudents($limit, $periodoFiltro, $offset);
			$fallbackRows = $this->attachPipelineData($fallbackRows);
			$fallbackTotal = $this->countLocalStudents($periodoFiltro);
			$periodos = $this->fetchLocalPeriodOptions();
			return [
				'rows' => $fallbackRows,
				'total' => $fallbackTotal > 0 ? $fallbackTotal : count($fallbackRows),
				'periodos' => $periodos,
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

	private function fetchLocalFallbackStudents(int $limit, string $periodoFiltro = '', int $offset = 0): array
	{
		try {
			$db = Database::getInstance()->connection();
			$periodoFiltro = $this->sanitizePeriodoKey($periodoFiltro);
			$offset = max(0, $offset);
			$whereSql = '';
			if ($periodoFiltro !== '') {
				$whereSql = "WHERE DATE_FORMAT(e.created_at, '%Y-%m') = :periodo";
			}

			$sql = "SELECT e.id, e.codigo_estudiante, e.estado,
						'' AS nivel,
						'' AS periodo,
						c.nombre, c.apellido, c.email,
						ca.nombre AS carrera,
						e.created_at AS fecha_matricula,
						DATE_FORMAT(e.created_at, '%Y-%m') AS periodo_clave
					FROM estudiantes e
					LEFT JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					$whereSql
					ORDER BY e.id DESC
					LIMIT :limit OFFSET :offset";
			$stmt = $db->prepare($sql);
			if ($periodoFiltro !== '') {
				$stmt->bindValue(':periodo', $periodoFiltro, PDO::PARAM_STR);
			}
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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

	private function countLocalStudents(string $periodoFiltro = ''): int
	{
		try {
			$db = Database::getInstance()->connection();
			$periodoFiltro = $this->sanitizePeriodoKey($periodoFiltro);
			if ($periodoFiltro === '') {
				return (int) $db->query('SELECT COUNT(*) FROM estudiantes')->fetchColumn();
			}

			$stmt = $db->prepare("SELECT COUNT(*) FROM estudiantes e WHERE DATE_FORMAT(e.created_at, '%Y-%m') = :periodo");
			$stmt->execute([':periodo' => $periodoFiltro]);
			return (int) $stmt->fetchColumn();
		} catch (Throwable $e) {
			return 0;
		}
	}

	private function sanitizePeriodoKey(string $periodo): string
	{
		$periodo = trim($periodo);
		if ($periodo === '') {
			return '';
		}

		if (mb_strlen($periodo) > 40) {
			$periodo = mb_substr($periodo, 0, 40);
		}

		return preg_match('/^[\p{L}\p{N}\s\-_.\/]+$/u', $periodo) === 1 ? $periodo : '';
	}

	private function fetchRemotePeriodOptions(PDO $remote, string $sourceTable): array
	{
		try {
			if ($sourceTable === 'users') {
				$sql = "SELECT DISTINCT TRIM(COALESCE(u.periodo, '')) AS periodo
					FROM users u
					WHERE u.periodo IS NOT NULL AND TRIM(u.periodo) <> ''
					ORDER BY periodo DESC";
			} else {
				$sql = "SELECT DISTINCT DATE_FORMAT(e.created_at, '%Y-%m') AS periodo
					FROM estudiantes e
					WHERE e.created_at IS NOT NULL
					ORDER BY periodo DESC";
			}

			$rows = $remote->query($sql)->fetchAll() ?: [];
			$periodos = [];
			foreach ($rows as $row) {
				$periodo = $this->sanitizePeriodoKey((string) ($row['periodo'] ?? ''));
				if ($periodo !== '') {
					$periodos[] = $periodo;
				}
			}

			return array_values(array_unique($periodos));
		} catch (Throwable $e) {
			return [];
		}
	}

	private function fetchLocalPeriodOptions(): array
	{
		try {
			$db = Database::getInstance()->connection();
			$rows = $db->query("SELECT DISTINCT DATE_FORMAT(e.created_at, '%Y-%m') AS periodo
				FROM estudiantes e
				WHERE e.created_at IS NOT NULL
				ORDER BY periodo DESC")->fetchAll() ?: [];

			$periodos = [];
			foreach ($rows as $row) {
				$periodo = $this->sanitizePeriodoKey((string) ($row['periodo'] ?? ''));
				if ($periodo !== '') {
					$periodos[] = $periodo;
				}
			}

			return array_values(array_unique($periodos));
		} catch (Throwable $e) {
			return [];
		}
	}

	private function currentUserId(): int
	{
		return (int) ($_SESSION['user_id'] ?? 0);
	}

	private function crmHistoryNote(int $studentId, string $sourceType, string $noteText): void
	{
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
			VALUES (:student_id, :source_type, :note_text, :user_id, NOW())");
		$stmt->execute([
			':student_id' => $studentId,
			':source_type' => $sourceType,
			':note_text' => $noteText,
			':user_id' => $this->currentUserId(),
		]);
	}

	private function formatUsuariosList(PDO $db, array $ids): string
	{
		$cleanIds = [];
		foreach ($ids as $id) {
			$value = (int) $id;
			if ($value > 0) {
				$cleanIds[$value] = $value;
			}
		}

		if (empty($cleanIds)) {
			return '-';
		}

		$orderedIds = array_values($cleanIds);
		$placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
		$stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE id IN ($placeholders)");
		$stmt->execute($orderedIds);
		$rows = $stmt->fetchAll() ?: [];

		$map = [];
		foreach ($rows as $row) {
			$uid = (int) ($row['id'] ?? 0);
			if ($uid > 0) {
				$map[$uid] = (string) ($row['nombre'] ?? ('Usuario #' . $uid));
			}
		}

		$labels = [];
		foreach ($orderedIds as $uid) {
			$labels[] = $map[$uid] ?? ('Usuario #' . $uid);
		}

		return implode(', ', $labels);
	}

	private function activeTaskTypes(PDO $db): array
	{
		$stmt = $db->query("SELECT id, nombre FROM tipo_tarea_convenios WHERE estado = 'activo' ORDER BY orden ASC, nombre ASC");
		return $stmt ? ($stmt->fetchAll() ?: []) : [];
	}

	private function activeResultados(PDO $db): array
	{
		$stmt = $db->query("SELECT id, nombre FROM resultados WHERE estado = 'activo' ORDER BY orden ASC, nombre ASC");
		return $stmt ? ($stmt->fetchAll() ?: []) : [];
	}

	private function activeUsuarios(PDO $db): array
	{
		$stmt = $db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre ASC");
		return $stmt ? ($stmt->fetchAll() ?: []) : [];
	}

	private function findStudentTaskMeta(PDO $db, int $studentId, int $taskId): ?array
	{
		$stmt = $db->prepare("SELECT id, estado, completado FROM crm_student_tasks WHERE id = :id AND student_id = :student_id LIMIT 1");
		$stmt->execute([
			':id' => $taskId,
			':student_id' => $studentId,
		]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	private function listStudentTasks(PDO $db, int $studentId): array
	{
		$sql = "SELECT t.*, tt.nombre AS tipo_tarea_nombre, r.nombre AS resultado_nombre,
				up.nombre AS propietario_nombre, uc.nombre AS created_by_nombre,
				GROUP_CONCAT(DISTINCT ur.nombre ORDER BY ur.nombre SEPARATOR ', ') AS relacionados,
				GROUP_CONCAT(DISTINCT uc2.nombre ORDER BY uc2.nombre SEPARATOR ', ') AS colaboradores,
				GROUP_CONCAT(DISTINCT tr.usuario_id ORDER BY tr.usuario_id SEPARATOR ',') AS relacionados_ids,
				GROUP_CONCAT(DISTINCT tc.usuario_id ORDER BY tc.usuario_id SEPARATOR ',') AS colaboradores_ids
			FROM crm_student_tasks t
			LEFT JOIN tipo_tarea_convenios tt ON tt.id = t.tipo_tarea_id
			LEFT JOIN resultados r ON r.id = t.resultado_id
			LEFT JOIN usuarios up ON up.id = t.propietario_id
			LEFT JOIN usuarios uc ON uc.id = t.created_by
			LEFT JOIN crm_student_task_relacionados tr ON tr.task_id = t.id
			LEFT JOIN usuarios ur ON ur.id = tr.usuario_id
			LEFT JOIN crm_student_task_colaboradores tc ON tc.task_id = t.id
			LEFT JOIN usuarios uc2 ON uc2.id = tc.usuario_id
			WHERE t.student_id = :student_id
			GROUP BY t.id
			ORDER BY t.completado ASC, t.fecha_vencimiento ASC, t.hora_vencimiento ASC, t.id DESC";

		$stmt = $db->prepare($sql);
		$stmt->execute([':student_id' => $studentId]);
		return $stmt->fetchAll() ?: [];
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

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_notes (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				student_id INT NOT NULL,
				source_type VARCHAR(40) NOT NULL DEFAULT 'note',
				note_text TEXT NOT NULL,
				created_by INT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP NULL DEFAULT NULL,
				INDEX idx_student_source (student_id, source_type),
				INDEX idx_created_at (created_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS tipo_tarea_convenios (
				id INT AUTO_INCREMENT PRIMARY KEY,
				nombre VARCHAR(150) NOT NULL,
				orden INT DEFAULT 0,
				estado ENUM('activo','inactivo') DEFAULT 'activo',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

			$db->exec("CREATE TABLE IF NOT EXISTS resultados (
				id INT AUTO_INCREMENT PRIMARY KEY,
				nombre VARCHAR(150) NOT NULL,
				orden INT DEFAULT 0,
				estado ENUM('activo','inactivo') DEFAULT 'activo',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_tasks (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				student_id INT NOT NULL,
				tipo_tarea_id INT NULL,
				resultado_id INT NULL,
				titulo VARCHAR(255) NOT NULL,
				descripcion TEXT NULL,
				fecha_vencimiento DATE NULL,
				hora_vencimiento TIME NULL,
				propietario_id INT NOT NULL,
				estado ENUM('pendiente','completada') DEFAULT 'pendiente',
				completado TINYINT(1) DEFAULT 0,
				created_by INT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				INDEX idx_student_id (student_id),
				INDEX idx_estado (estado),
				INDEX idx_vencimiento (fecha_vencimiento),
				INDEX idx_propietario (propietario_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_task_relacionados (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				task_id BIGINT NOT NULL,
				usuario_id INT NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uniq_task_relacionado (task_id, usuario_id),
				INDEX idx_task_id (task_id),
				INDEX idx_usuario_id (usuario_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_task_colaboradores (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				task_id BIGINT NOT NULL,
				usuario_id INT NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uniq_task_colaborador (task_id, usuario_id),
				INDEX idx_task_id (task_id),
				INDEX idx_usuario_id (usuario_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS correos_contacto (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				contacto_id INT NOT NULL,
				correo VARCHAR(255) NOT NULL,
				tipo VARCHAR(30) NOT NULL DEFAULT 'personal',
				estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY uniq_contacto_correo (contacto_id, correo),
				INDEX idx_correo (correo),
				INDEX idx_contacto (contacto_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS telefonos_contacto (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				contacto_id INT NOT NULL,
				telefono VARCHAR(40) NOT NULL,
				tipo VARCHAR(30) NOT NULL DEFAULT 'principal',
				estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY uniq_contacto_telefono (contacto_id, telefono),
				INDEX idx_telefono (telefono),
				INDEX idx_contacto (contacto_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_person_channels (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				contacto_id INT NOT NULL,
				channel_type ENUM('email','phone') NOT NULL,
				channel_value VARCHAR(255) NOT NULL,
				source VARCHAR(50) NOT NULL DEFAULT 'superarse',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY uniq_contact_channel (contacto_id, channel_type, channel_value),
				INDEX idx_channel_type_value (channel_type, channel_value),
				INDEX idx_contacto_id (contacto_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_academic_history (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				contacto_id INT NOT NULL,
				source_user_id INT NOT NULL,
				codigo_estudiante VARCHAR(80) NULL,
				carrera VARCHAR(180) NULL,
				matricula VARCHAR(120) NULL,
				nivel VARCHAR(80) NULL,
				estado_academico VARCHAR(80) NULL,
				periodo VARCHAR(80) NULL,
				payload JSON NULL,
				last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uniq_contact_user (contacto_id, source_user_id),
				INDEX idx_source_user_id (source_user_id),
				INDEX idx_last_seen_at (last_seen_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS crm_superarse_sync_state (
				id TINYINT NOT NULL PRIMARY KEY,
				last_user_id INT NOT NULL DEFAULT 0,
				last_run_at DATETIME NULL,
				last_status VARCHAR(20) NOT NULL DEFAULT 'idle',
				last_summary JSON NULL,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$countTipos = (int) $db->query("SELECT COUNT(*) FROM tipo_tarea_convenios")->fetchColumn();
			if ($countTipos === 0) {
				$db->exec("INSERT INTO tipo_tarea_convenios (nombre, orden) VALUES
					('Llamada', 1), ('Correo', 2), ('Reunión', 3), ('Firma', 4), ('Seguimiento', 5), ('Visita', 6)");
			}

			$countResultados = (int) $db->query("SELECT COUNT(*) FROM resultados")->fetchColumn();
			if ($countResultados === 0) {
				$db->exec("INSERT INTO resultados (nombre, orden) VALUES
					('Exitoso', 1), ('Pendiente', 2), ('Sin respuesta', 3), ('Reagendado', 4), ('Cancelado', 5)");
			}

			$db->exec("INSERT IGNORE INTO crm_superarse_sync_state (id, last_user_id, last_status, updated_at)
				VALUES (1, 0, 'idle', NOW())");
		} catch (Throwable $e) {
			// Evitar romper el flujo principal por auto-creacion auxiliar.
		}
	}

	private function fetchLocalProspects(int $perPage = 25, int $offset = 0): array
	{
		$perPage = max(10, min(200, $perPage));
		$offset  = max(0, $offset);
		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT
				i.id,
				i.contacto_id,
				i.origen,
				i.convertido,
				i.created_at,
				COALESCE(pe.nombre, 'Sin etapa') AS etapa,
				c.nombre,
				c.apellido,
				c.cedula,
				c.email,
				COALESCE(tc.telefono, '') AS celular
			FROM interesados i
			INNER JOIN contactos c ON c.id = i.contacto_id
			LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
			LEFT JOIN (
				SELECT t1.contacto_id, t1.telefono
				FROM telefonos_contacto t1
				INNER JOIN (
					SELECT contacto_id, MIN(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) tx ON tx.first_id = t1.id
			) tc ON tc.contacto_id = i.contacto_id
			WHERE i.estado = 'activo'
			ORDER BY i.id DESC
			LIMIT :perPage OFFSET :offset";

			$stmt = $db->prepare($sql);
			$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			return [];
		}
	}

	private function countLocalProspects(): int
	{
		try {
			$db = Database::getInstance()->connection();
			return (int) $db->query("SELECT COUNT(*) FROM interesados WHERE estado = 'activo'")->fetchColumn();
		} catch (Throwable $e) {
			return 0;
		}
	}

	private function resolveInitialProspectStateId(PDO $db): ?int
	{
		$preferred = [
			'1. etapa interesados',
			'etapa interesados',
			'interesado',
			'interesados',
		];

		$stmt = $db->query("SELECT id, nombre FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC");
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		if (empty($rows)) {
			return null;
		}

		foreach ($rows as $row) {
			$name = $this->dashboardNormalizeLabel((string) ($row['nombre'] ?? ''));
			foreach ($preferred as $target) {
				if ($name === $target || str_contains($name, $target)) {
					$id = (int) ($row['id'] ?? 0);
					if ($id > 0) {
						return $id;
					}
				}
			}
		}

		$fallbackId = (int) ($rows[0]['id'] ?? 0);
		return $fallbackId > 0 ? $fallbackId : null;
	}

	private function upsertInteresado(PDO $db, int $contactId, ?int $estadoId, string $origen): void
	{
		if ($contactId <= 0) {
			return;
		}

		$stmt = $db->prepare('SELECT id FROM interesados WHERE contacto_id = :contacto_id LIMIT 1');
		$stmt->execute(['contacto_id' => $contactId]);
		$existingId = (int) ($stmt->fetchColumn() ?: 0);

		if ($existingId > 0) {
			$update = $db->prepare('UPDATE interesados
				SET estado_id = :estado_id,
					origen = :origen,
					convertido = 0,
					estado = "activo",
					updated_at = NOW()
				WHERE id = :id
				LIMIT 1');
			$update->execute([
				'estado_id' => $estadoId,
				'origen' => mb_substr($origen, 0, 100),
				'id' => $existingId,
			]);
			return;
		}

		$insert = $db->prepare('INSERT INTO interesados (contacto_id, estado_id, origen, convertido, estado, created_at, updated_at)
			VALUES (:contacto_id, :estado_id, :origen, 0, "activo", NOW(), NOW())');
		$insert->execute([
			'contacto_id' => $contactId,
			'estado_id' => $estadoId,
			'origen' => mb_substr($origen, 0, 100),
		]);
	}

	private function markProspectAsConverted(PDO $db, int $contactId): void
	{
		if ($contactId <= 0) {
			return;
		}

		$stmt = $db->prepare('UPDATE interesados SET convertido = 1, updated_at = NOW() WHERE contacto_id = :contacto_id');
		$stmt->execute(['contacto_id' => $contactId]);
	}

	private function upsertContactEmail(PDO $db, int $contactId, string $email, string $type = 'personal'): void
	{
		if ($contactId <= 0 || $email === '') {
			return;
		}

		$stmt = $db->prepare('INSERT INTO correos_contacto (contacto_id, correo, tipo, estado, created_at, updated_at)
			VALUES (:contacto_id, :correo, :tipo, "activo", NOW(), NOW())
			ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), estado = "activo", updated_at = NOW()');
		$stmt->execute([
			'contacto_id' => $contactId,
			'correo' => mb_substr($email, 0, 255),
			'tipo' => mb_substr($type, 0, 30),
		]);
	}

	private function upsertContactPhone(PDO $db, int $contactId, string $phone, string $type = 'principal'): void
	{
		if ($contactId <= 0 || $phone === '') {
			return;
		}

		$stmt = $db->prepare('INSERT INTO telefonos_contacto (contacto_id, telefono, tipo, estado, created_at, updated_at)
			VALUES (:contacto_id, :telefono, :tipo, "activo", NOW(), NOW())
			ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), estado = "activo", updated_at = NOW()');
		$stmt->execute([
			'contacto_id' => $contactId,
			'telefono' => mb_substr($phone, 0, 40),
			'tipo' => mb_substr($type, 0, 30),
		]);
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
			$student = null;
			if ($remote instanceof PDO) {
				$sourceTable = $this->resolveSuperarseStudentTable($remote);
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
				} elseif ($sourceTable === 'estudiantes') {
					$sql = "SELECT
							e.id,
							e.codigo_estudiante,
							TRIM(CONCAT_WS(' ', c.nombre, c.apellido)) AS nombre_completo,
							c.email,
							COALESCE(tp.telefono, '') AS telefono,
							COALESCE(tp.telefono, '') AS celular
						FROM estudiantes e
						LEFT JOIN contactos c ON c.id = e.contacto_id
						LEFT JOIN (
							SELECT t1.contacto_id, t1.telefono
							FROM telefonos_contacto t1
							INNER JOIN (
								SELECT contacto_id, MIN(id) AS first_id
								FROM telefonos_contacto
								WHERE estado = 'activo'
								GROUP BY contacto_id
							) tx ON tx.first_id = t1.id
						) tp ON tp.contacto_id = e.contacto_id
						WHERE e.id = :id
						LIMIT 1";
				} else {
					$sql = null;
				}

				if ($sql !== null) {
					$stmt = $remote->prepare($sql);
					$stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
					$stmt->execute();
					$student = $stmt->fetch() ?: null;
				}
			}

			if (!$student) {
				$db = Database::getInstance()->connection();
				$sql = "SELECT
						e.id,
						e.codigo_estudiante,
						TRIM(CONCAT_WS(' ', c.nombre, c.apellido)) AS nombre_completo,
						c.email,
						COALESCE(tp.telefono, '') AS telefono,
						COALESCE(tp.telefono, '') AS celular
					FROM estudiantes e
					LEFT JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN (
						SELECT t1.contacto_id, t1.telefono
						FROM telefonos_contacto t1
						INNER JOIN (
							SELECT contacto_id, MIN(id) AS first_id
							FROM telefonos_contacto
							WHERE estado = 'activo'
							GROUP BY contacto_id
						) tx ON tx.first_id = t1.id
					) tp ON tp.contacto_id = e.contacto_id
					WHERE e.id = :id
					LIMIT 1";
				$stmt = $db->prepare($sql);
				$stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
				$stmt->execute();
				$student = $stmt->fetch() ?: null;
			}

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
			$updated = false;
			if ($remote instanceof PDO) {
				$sourceTable = $this->resolveSuperarseStudentTable($remote);
				if ($sourceTable === 'users') {
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
					$updated = true;
				} elseif ($sourceTable === 'estudiantes') {
					$contactoIdStmt = $remote->prepare('SELECT contacto_id FROM estudiantes WHERE id = :id LIMIT 1');
					$contactoIdStmt->execute([':id' => $studentId]);
					$contactoId = (int) ($contactoIdStmt->fetchColumn() ?: 0);
					if ($contactoId > 0) {
						$updateContact = $remote->prepare('UPDATE contactos SET email = :email, updated_at = NOW() WHERE id = :id LIMIT 1');
						$updateContact->execute([
							':email' => $email !== '' ? $email : null,
							':id' => $contactoId,
						]);
						$updated = true;
					}
				}
			}

			if (!$updated) {
				$db = Database::getInstance()->connection();
				$contactStmt = $db->prepare('SELECT contacto_id FROM estudiantes WHERE id = :id LIMIT 1');
				$contactStmt->execute([':id' => $studentId]);
				$contactoId = (int) ($contactStmt->fetchColumn() ?: 0);
				if ($contactoId <= 0) {
					throw new RuntimeException('Estudiante no encontrado');
				}

				$updateContact = $db->prepare('UPDATE contactos
					SET email = :email,
						updated_at = NOW()
					WHERE id = :id
					LIMIT 1');
				$updateContact->execute([
					':email' => $email !== '' ? $email : null,
					':id' => $contactoId,
				]);
			}

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

		$entityId = max(0, (int) ($_GET['id'] ?? 0));
		$entityType = strtolower(trim((string) ($_GET['entity_type'] ?? 'student')));
		if ($entityType !== 'contact') {
			$entityType = 'student';
		}

		if ($entityId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$student = null;
			$pipelineKey = $entityId;

			if ($entityType === 'contact') {
				$stmtLocal = $db->prepare("SELECT
					c.id AS id,
					'' AS codigo_estudiante,
					c.nombre AS primer_nombre,
					'' AS segundo_nombre,
					c.apellido AS primer_apellido,
					'' AS segundo_apellido,
					c.email AS email,
					COALESCE(tp.telefono, '') AS telefono,
					COALESCE(tp.telefono, '') AS celular,
					'' AS carrera,
					'' AS nivel,
					'' AS sede,
					COALESCE(c.estado, 'activo') AS estado,
					c.created_at AS fecha_matricula,
					COALESCE(i.origen, 'crm_manual') AS origen,
					COALESCE(i.convertido, 0) AS convertido,
					i.id AS interesado_id,
					e.id AS estudiante_local_id
				FROM contactos c
				LEFT JOIN interesados i ON i.contacto_id = c.id
				LEFT JOIN estudiantes e ON e.contacto_id = c.id
				LEFT JOIN (
					SELECT t1.contacto_id, t1.telefono
					FROM telefonos_contacto t1
					INNER JOIN (
						SELECT contacto_id, MIN(id) AS first_id
						FROM telefonos_contacto
						WHERE estado = 'activo'
						GROUP BY contacto_id
					) tx ON tx.first_id = t1.id
				) tp ON tp.contacto_id = c.id
				WHERE c.id = :id
				LIMIT 1");
				$stmtLocal->bindValue(':id', $entityId, PDO::PARAM_INT);
				$stmtLocal->execute();
				$student = $stmtLocal->fetch();
				if (!$student) {
					throw new RuntimeException('Contacto no encontrado');
				}

				$student['is_student'] = !empty($student['estudiante_local_id']) ? 1 : 0;
				$student['entity_type'] = 'contact';
			} else {
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
							u.nivel,
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
				$stmt->bindValue(':id', $entityId, PDO::PARAM_INT);
				$stmt->execute();
				$student = $stmt->fetch();

				if (!$student) {
					throw new RuntimeException('Estudiante no encontrado');
				}

				$student['is_student'] = 1;
				$student['entity_type'] = 'student';
			}

			// Obtener estados del pipeline
			$currentPipelineStmt = $db->prepare('SELECT estado_id FROM crm_student_pipeline WHERE student_id = :student_id LIMIT 1');
			$currentPipelineStmt->execute([':student_id' => $pipelineKey]);
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
				AND csn.source_type IN ('prospect_created', 'estado_change', 'task_create', 'task_participants', 'task_result', 'task_complete')
				ORDER BY csn.created_at DESC
				LIMIT 30");
			$historyStmt->execute([':student_id' => $pipelineKey]);
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

		$studentId = max(0, (int) ($_POST['student_id'] ?? $_POST['contacto_id'] ?? 0));
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

	public function getStudentTasks(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_GET['student_id'] ?? $_GET['contacto_id'] ?? 0));
		if ($studentId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();

			echo json_encode([
				'success' => true,
				'tasks' => $this->listStudentTasks($db, $studentId),
				'tipos_tarea' => $this->activeTaskTypes($db),
				'resultados' => $this->activeResultados($db),
				'usuarios' => $this->activeUsuarios($db),
			]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function addStudentTask(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? $_POST['contacto_id'] ?? 0));
		$titulo = trim((string) ($_POST['titulo'] ?? ''));
		$propietarioId = max(0, (int) ($_POST['propietario_id'] ?? 0));

		if ($studentId <= 0 || $titulo === '' || $propietarioId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Título y propietario son obligatorios.']);
			exit;
		}

		$relacionados = array_values(array_unique(array_map('intval', (array) ($_POST['relacionados'] ?? []))));
		$colaboradores = array_values(array_unique(array_map('intval', (array) ($_POST['colaboradores'] ?? []))));

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$db->beginTransaction();

			$stmt = $db->prepare("INSERT INTO crm_student_tasks (student_id, tipo_tarea_id, resultado_id, titulo, descripcion, fecha_vencimiento, hora_vencimiento, propietario_id, estado, completado, created_by)
				VALUES (:student_id, :tipo_tarea_id, :resultado_id, :titulo, :descripcion, :fecha_vencimiento, :hora_vencimiento, :propietario_id, 'pendiente', 0, :created_by)");
			$stmt->execute([
				':student_id' => $studentId,
				':tipo_tarea_id' => ($_POST['tipo_tarea_id'] ?? '') !== '' ? (int) $_POST['tipo_tarea_id'] : null,
				':resultado_id' => ($_POST['resultado_id'] ?? '') !== '' ? (int) $_POST['resultado_id'] : null,
				':titulo' => $titulo,
				':descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
				':fecha_vencimiento' => trim((string) ($_POST['fecha_vencimiento'] ?? '')),
				':hora_vencimiento' => trim((string) ($_POST['hora_vencimiento'] ?? '')),
				':propietario_id' => $propietarioId,
				':created_by' => $this->currentUserId(),
			]);

			$taskId = (int) $db->lastInsertId();

			if (!empty($relacionados)) {
				$relStmt = $db->prepare("INSERT INTO crm_student_task_relacionados (task_id, usuario_id) VALUES (:task_id, :usuario_id)");
				foreach ($relacionados as $usuarioId) {
					if ($usuarioId > 0) {
						$relStmt->execute([':task_id' => $taskId, ':usuario_id' => $usuarioId]);
					}
				}
			}

			if (!empty($colaboradores)) {
				$colStmt = $db->prepare("INSERT INTO crm_student_task_colaboradores (task_id, usuario_id) VALUES (:task_id, :usuario_id)");
				foreach ($colaboradores as $usuarioId) {
					if ($usuarioId > 0) {
						$colStmt->execute([':task_id' => $taskId, ':usuario_id' => $usuarioId]);
					}
				}
			}

			$this->crmHistoryNote($studentId, 'task_create', 'Tarea creada: ' . $titulo);
			$db->commit();

			echo json_encode(['success' => true, 'message' => 'Tarea creada correctamente.', 'task_id' => $taskId]);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function updateStudentTaskParticipants(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? $_POST['contacto_id'] ?? 0));
		$taskId = max(0, (int) ($_POST['task_id'] ?? 0));
		if ($studentId <= 0 || $taskId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
			exit;
		}

		$relacionadosRaw = (array) ($_POST['relacionados'] ?? []);
		$colaboradoresRaw = (array) ($_POST['colaboradores'] ?? []);
		$relacionados = [];
		foreach ($relacionadosRaw as $item) {
			$value = (int) $item;
			if ($value > 0) {
				$relacionados[$value] = $value;
			}
		}
		$colaboradores = [];
		foreach ($colaboradoresRaw as $item) {
			$value = (int) $item;
			if ($value > 0) {
				$colaboradores[$value] = $value;
			}
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$meta = $this->findStudentTaskMeta($db, $studentId, $taskId);
			if (!$meta) {
				http_response_code(404);
				echo json_encode(['success' => false, 'error' => 'Tarea no encontrada.']);
				exit;
			}
			if ((string) ($meta['estado'] ?? '') === 'completada' || (int) ($meta['completado'] ?? 0) === 1) {
				http_response_code(409);
				echo json_encode(['success' => false, 'error' => 'La tarea está completada y quedó bloqueada.']);
				exit;
			}

			$db->beginTransaction();
			$db->prepare("DELETE FROM crm_student_task_relacionados WHERE task_id = :task_id")->execute([':task_id' => $taskId]);
			$db->prepare("DELETE FROM crm_student_task_colaboradores WHERE task_id = :task_id")->execute([':task_id' => $taskId]);

			if (!empty($relacionados)) {
				$relStmt = $db->prepare("INSERT INTO crm_student_task_relacionados (task_id, usuario_id) VALUES (:task_id, :usuario_id)");
				foreach (array_values($relacionados) as $usuarioId) {
					$relStmt->execute([':task_id' => $taskId, ':usuario_id' => $usuarioId]);
				}
			}

			if (!empty($colaboradores)) {
				$colStmt = $db->prepare("INSERT INTO crm_student_task_colaboradores (task_id, usuario_id) VALUES (:task_id, :usuario_id)");
				foreach (array_values($colaboradores) as $usuarioId) {
					$colStmt->execute([':task_id' => $taskId, ':usuario_id' => $usuarioId]);
				}
			}

			$detalle = 'Relacionados: ' . $this->formatUsuariosList($db, array_values($relacionados));
			$detalle .= ' | Colaboradores: ' . $this->formatUsuariosList($db, array_values($colaboradores));
			$this->crmHistoryNote($studentId, 'task_participants', $detalle);
			$db->commit();

			echo json_encode(['success' => true, 'message' => 'Participantes actualizados.']);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function updateStudentTaskResult(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? $_POST['contacto_id'] ?? 0));
		$taskId = max(0, (int) ($_POST['task_id'] ?? 0));
		if ($studentId <= 0 || $taskId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
			exit;
		}

		$resultadoId = ($_POST['resultado_id'] ?? '') !== '' ? (int) $_POST['resultado_id'] : null;

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$meta = $this->findStudentTaskMeta($db, $studentId, $taskId);
			if (!$meta) {
				http_response_code(404);
				echo json_encode(['success' => false, 'error' => 'Tarea no encontrada.']);
				exit;
			}
			if ((string) ($meta['estado'] ?? '') === 'completada' || (int) ($meta['completado'] ?? 0) === 1) {
				http_response_code(409);
				echo json_encode(['success' => false, 'error' => 'La tarea está completada y quedó bloqueada.']);
				exit;
			}

			$stmt = $db->prepare("UPDATE crm_student_tasks SET resultado_id = :resultado_id WHERE id = :id");
			$stmt->execute([':resultado_id' => $resultadoId, ':id' => $taskId]);

			$resultadoNombre = '-';
			if ($resultadoId !== null && $resultadoId > 0) {
				$resStmt = $db->prepare("SELECT nombre FROM resultados WHERE id = :id LIMIT 1");
				$resStmt->execute([':id' => $resultadoId]);
				$resultadoNombre = (string) ($resStmt->fetchColumn() ?: '-');
			}

			$this->crmHistoryNote($studentId, 'task_result', 'Resultado de tarea: ' . $resultadoNombre);
			echo json_encode(['success' => true, 'message' => 'Resultado actualizado.']);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function completeStudentTask(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$studentId = max(0, (int) ($_POST['student_id'] ?? $_POST['contacto_id'] ?? 0));
		$taskId = max(0, (int) ($_POST['task_id'] ?? 0));
		if ($studentId <= 0 || $taskId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$meta = $this->findStudentTaskMeta($db, $studentId, $taskId);
			if (!$meta) {
				http_response_code(404);
				echo json_encode(['success' => false, 'error' => 'Tarea no encontrada.']);
				exit;
			}
			if ((string) ($meta['estado'] ?? '') === 'completada' || (int) ($meta['completado'] ?? 0) === 1) {
				echo json_encode(['success' => true, 'message' => 'La tarea ya estaba completada.']);
				exit;
			}

			$db->prepare("UPDATE crm_student_tasks SET estado = 'completada', completado = 1 WHERE id = :id")->execute([':id' => $taskId]);
			$titleStmt = $db->prepare("SELECT titulo FROM crm_student_tasks WHERE id = :id LIMIT 1");
			$titleStmt->execute([':id' => $taskId]);
			$titulo = (string) ($titleStmt->fetchColumn() ?: 'Tarea');

			$this->crmHistoryNote($studentId, 'task_complete', 'Tarea completada: ' . $titulo);
			echo json_encode(['success' => true, 'message' => 'Tarea completada.']);
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

		$studentId = max(0, (int) ($_GET['student_id'] ?? $_GET['contacto_id'] ?? 0));
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
					WHERE csn.student_id = :student_id AND csn.source_type IN ('estado_change', 'task_create', 'task_participants', 'task_result', 'task_complete')
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

		$studentId = max(0, (int) ($_GET['student_id'] ?? $_GET['contacto_id'] ?? 0));
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

			if ($studentEmail === '') {
				try {
					$contactStmt = $db->prepare('SELECT email FROM contactos WHERE id = ? LIMIT 1');
					$contactStmt->execute([$studentId]);
					$studentEmail = (string) ($contactStmt->fetchColumn() ?? '');
				} catch (Throwable $e) {
					$studentEmail = '';
				}
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

			try {
				$extraContactEmails = $db->prepare('SELECT correo FROM correos_contacto WHERE contacto_id = ? AND estado = "activo"');
				$extraContactEmails->execute([$studentId]);
				$extraRows = $extraContactEmails->fetchAll() ?: [];
				foreach ($extraRows as $extraRow) {
					$mail = trim((string) ($extraRow['correo'] ?? ''));
					if ($mail !== '') {
						$emails[] = $mail;
					}
				}
			} catch (Throwable $e) {
				// sin correos extra
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

	public function cronInstitutionalSync(): void
	{
		header('Content-Type: application/json; charset=utf-8');

		if (!$this->isAuthorizedInstitutionalSyncRequest()) {
			http_response_code(403);
			echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
			exit;
		}

		$limit = max(20, min(500, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 150)));

		try {
			$result = $this->runInstitutionalSyncBatch($limit);
			echo json_encode([
				'ok' => true,
				'service' => 'crm-institutional-sync',
				'batch_limit' => $limit,
				'result' => $result,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
		}

		exit;
	}

	private function isAuthorizedInstitutionalSyncRequest(): bool
	{
		$providedToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? $_REQUEST['token'] ?? ''));
		$expectedToken = trim((string) env('CRM_SYNC_INTERNAL_TOKEN', ''));
		if ($expectedToken === '') {
			$expectedToken = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
		}

		return $expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken);
	}

	private function runInstitutionalSyncBatch(int $limit): array
	{
		$this->ensureCrmSupportTables();
		$db = Database::getInstance()->connection();

		$remote = $this->connectSuperarseDatabase();
		if ($remote === null) {
			throw new RuntimeException('No se pudo conectar a la BD de SuperarseConectados.');
		}

		if ($this->resolveSuperarseStudentTable($remote) !== 'users') {
			throw new RuntimeException('La integracion institucional requiere tabla users en SuperarseConectados.');
		}

		$stateStmt = $db->query('SELECT last_user_id FROM crm_superarse_sync_state WHERE id = 1 LIMIT 1');
		$state = $stateStmt ? ($stateStmt->fetch() ?: ['last_user_id' => 0]) : ['last_user_id' => 0];
		$cursor = max(0, (int) ($state['last_user_id'] ?? 0));

		$rows = $this->fetchSuperarseUsersBatch($remote, $cursor, $limit);
		$summary = [
			'cursor_inicial' => $cursor,
			'cursor_final' => $cursor,
			'processed' => 0,
			'matched_contacts' => 0,
			'created_students' => 0,
			'updated_students' => 0,
			'created_matriculas' => 0,
			'merged_duplicates' => 0,
			'skipped_no_identity' => 0,
			'skipped_not_found_in_crm' => 0,
			'errors' => 0,
			'error_samples' => [],
			'has_more' => false,
		];

		$maxUserId = $cursor;
		foreach ($rows as $row) {
			$userId = (int) ($row['id'] ?? 0);
			if ($userId <= 0) {
				continue;
			}

			$maxUserId = max($maxUserId, $userId);
			$summary['processed']++;

			try {
				$identity = $this->resolveIdentityDocument($row);
				$personalEmail = $this->normalizeEmailValue($this->resolveFirstValue($row, [
					'correo_personal',
					'email_personal',
					'personal_email',
					'correo_alterno',
					'email_alterno',
				]));
				$institutionalEmail = $this->normalizeEmailValue($this->resolveFirstValue($row, [
					'correo_institucional',
					'email_institucional',
					'institutional_email',
					'correo_electronico',
					'email',
				]));

				if ($identity === '') {
					$summary['skipped_no_identity']++;
					continue;
				}

				$contactId = $this->findPrimaryContactIdForMerge($db, $identity);
				if ($contactId === null) {
					$summary['skipped_not_found_in_crm']++;
					continue;
				}

				$this->updateContactFromInstitutionalData($db, $contactId, $row, $identity, $personalEmail, $institutionalEmail);
				$summary['matched_contacts']++;

				$summary['merged_duplicates'] += $this->mergeSecondaryContacts($db, $contactId, $identity);

				if ($personalEmail !== '') {
					$this->addContactChannel($db, $contactId, 'email', $personalEmail, 'superarse_personal');
					$this->upsertContactEmail($db, $contactId, $personalEmail, 'personal');
				}
				if ($institutionalEmail !== '') {
					$this->addContactChannel($db, $contactId, 'email', $institutionalEmail, 'superarse_institucional');
					$this->upsertContactEmail($db, $contactId, $institutionalEmail, 'institucional');
				}

				foreach ([
					$this->resolveFirstValue($row, ['celular', 'telefono_movil', 'movil']),
					$this->resolveFirstValue($row, ['telefono', 'telefono_convencional', 'phone']),
				] as $phone) {
					$normalizedPhone = $this->normalizePhoneValue($phone);
					if ($normalizedPhone !== '') {
						$this->addContactChannel($db, $contactId, 'phone', $normalizedPhone, 'superarse');
						$this->upsertContactPhone($db, $contactId, $normalizedPhone, 'secundario');
					}
				}

				$codigoEstudiante = $this->resolveFirstValue($row, ['codigo_estudiante', 'codigo_matricula', 'matricula', 'codigo']);
				$estadoAcademico = $this->resolveFirstValue($row, ['estado_academico', 'estado']);
				$estudianteResult = $this->ensureStudentFromContact($db, $contactId, $codigoEstudiante, $estadoAcademico);
				$this->markProspectAsConverted($db, $contactId);
				if (!empty($estudianteResult['created'])) {
					$summary['created_students']++;
				} else {
					$summary['updated_students']++;
				}

				$carreraNombre = $this->resolveFirstValue($row, ['carrera', 'programa']);
				$carreraId = $this->ensureCareer($db, $carreraNombre);
				if ($carreraId !== null && (int) ($estudianteResult['student_id'] ?? 0) > 0) {
					$createdMatricula = $this->ensureMatricula(
						$db,
						(int) $estudianteResult['student_id'],
						$carreraId,
						$this->normalizeDateValue($this->resolveFirstValue($row, ['fecha_matricula', 'created_at'])),
						$this->resolveFirstValue($row, ['estado_academico', 'estado'])
					);
					if ($createdMatricula) {
						$summary['created_matriculas']++;
					}
				}

				$this->upsertAcademicHistory($db, $contactId, $row);
			} catch (Throwable $rowError) {
				$summary['errors']++;
				if (count($summary['error_samples']) < 5) {
					$summary['error_samples'][] = 'user_id=' . $userId . ': ' . $rowError->getMessage();
				}
			}
		}

		$summary['cursor_final'] = $maxUserId;
		$summary['has_more'] = count($rows) >= $limit;

		$updateState = $db->prepare('UPDATE crm_superarse_sync_state SET last_user_id = :last_user_id, last_run_at = NOW(), last_status = :status, last_summary = :summary, updated_at = NOW() WHERE id = 1');
		$updateState->execute([
			'last_user_id' => $maxUserId,
			'status' => $summary['errors'] > 0 ? 'warning' : 'ok',
			'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);

		return $summary;
	}

	private function fetchSuperarseUsersBatch(PDO $remote, int $cursor, int $limit): array
	{
		$columnRows = $remote->query('SHOW COLUMNS FROM users')->fetchAll() ?: [];
		$available = [];
		foreach ($columnRows as $columnRow) {
			$name = trim((string) ($columnRow['Field'] ?? ''));
			if ($name !== '') {
				$available[$name] = true;
			}
		}

		if (!isset($available['id'])) {
			throw new RuntimeException('La tabla users no tiene la columna id.');
		}

		$candidates = [
			'id',
			'primer_nombre',
			'segundo_nombre',
			'primer_apellido',
			'segundo_apellido',
			'nombre',
			'nombres',
			'apellido',
			'apellidos',
			'cedula',
			'identificacion',
			'numero_identificacion',
			'documento',
			'pasaporte',
			'correo_electronico',
			'email',
			'correo_institucional',
			'email_institucional',
			'correo_personal',
			'email_personal',
			'telefono',
			'celular',
			'movil',
			'programa',
			'carrera',
			'nivel',
			'estado',
			'estado_academico',
			'codigo_matricula',
			'codigo_estudiante',
			'matricula',
			'periodo',
			'fecha_matricula',
			'created_at',
		];

		$selectColumns = [];
		foreach ($candidates as $candidate) {
			if (isset($available[$candidate])) {
				$selectColumns[] = '`' . $candidate . '`';
			}
		}

		if (!in_array('`id`', $selectColumns, true)) {
			$selectColumns[] = '`id`';
		}

		$sql = 'SELECT ' . implode(', ', array_unique($selectColumns)) . ' FROM users WHERE id > :cursor ORDER BY id ASC LIMIT :limit';
		$stmt = $remote->prepare($sql);
		$stmt->bindValue(':cursor', max(0, $cursor), PDO::PARAM_INT);
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll() ?: [];
	}

	private function resolveFirstValue(array $row, array $keys): string
	{
		foreach ($keys as $key) {
			$value = trim((string) ($row[$key] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	private function resolveIdentityDocument(array $row): string
	{
		$raw = $this->resolveFirstValue($row, [
			'identificacion',
			'cedula',
			'numero_identificacion',
			'documento',
			'pasaporte',
		]);

		return $this->normalizeIdentityValue($raw);
	}

	private function normalizeIdentityValue(string $value): string
	{
		$value = strtoupper(trim($value));
		if ($value === '') {
			return '';
		}

		$value = preg_replace('/[^A-Z0-9]/', '', $value) ?: '';
		return $value;
	}

	private function normalizeEmailValue(string $value): string
	{
		$value = strtolower(trim($value));
		if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
			return '';
		}

		return $value;
	}

	private function normalizePhoneValue(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		$value = preg_replace('/[^0-9+]/', '', $value) ?: '';
		if ($value === '' || strlen($value) < 7) {
			return '';
		}

		return $value;
	}

	private function findPrimaryContactIdForMerge(PDO $db, string $identity): ?int
	{
		if ($identity === '') {
			return null;
		}

		$stmt = $db->prepare("SELECT id
			FROM contactos
			WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(cedula, ''))), '.', ''), '-', ''), ' ', '') = :identity
			ORDER BY id ASC
			LIMIT 1");
		$stmt->execute(['identity' => $identity]);
		$id = (int) ($stmt->fetchColumn() ?: 0);
		return $id > 0 ? $id : null;
	}

	private function resolveContactByEmailFallback(PDO $db, string $email): ?int
	{
		if ($email === '') {
			return null;
		}

		$stmt = $db->prepare('SELECT id FROM contactos WHERE LOWER(TRIM(COALESCE(email, ""))) = :email LIMIT 1');
		$stmt->execute(['email' => $email]);
		$id = (int) ($stmt->fetchColumn() ?: 0);
		return $id > 0 ? $id : null;
	}

	private function createContactFromInstitutionalData(PDO $db, array $row, string $identity, string $personalEmail, string $institutionalEmail): int
	{
		$firstName = $this->resolveFirstValue($row, ['primer_nombre', 'nombres', 'nombre']);
		$secondName = $this->resolveFirstValue($row, ['segundo_nombre']);
		$nombre = trim($firstName . ' ' . $secondName);
		if ($nombre === '') {
			$nombre = 'SinNombre';
		}

		$lastName = $this->resolveFirstValue($row, ['primer_apellido', 'apellidos', 'apellido']);
		$secondLastName = $this->resolveFirstValue($row, ['segundo_apellido']);
		$apellido = trim($lastName . ' ' . $secondLastName);
		if ($apellido === '') {
			$apellido = 'SinApellido';
		}

		$primaryEmail = $personalEmail !== '' ? $personalEmail : $institutionalEmail;
		if ($primaryEmail !== '' && !$this->canUseEmailAsPrimaryContact($db, $primaryEmail, null)) {
			$primaryEmail = '';
		}

		$stmt = $db->prepare('INSERT INTO contactos (nombre, apellido, cedula, email, tipo, estado, created_at, updated_at)
			VALUES (:nombre, :apellido, :cedula, :email, :tipo, :estado, NOW(), NOW())');
		$stmt->execute([
			'nombre' => mb_substr($nombre, 0, 150),
			'apellido' => mb_substr($apellido, 0, 150),
			'cedula' => $identity !== '' ? mb_substr($identity, 0, 20) : null,
			'email' => $primaryEmail !== '' ? $primaryEmail : null,
			'tipo' => 'estudiante',
			'estado' => 'activo',
		]);

		return (int) $db->lastInsertId();
	}

	private function updateContactFromInstitutionalData(PDO $db, int $contactId, array $row, string $identity, string $personalEmail, string $institutionalEmail): void
	{
		$current = $this->getContactRow($db, $contactId);
		if ($current === null) {
			return;
		}

		$firstName = $this->resolveFirstValue($row, ['primer_nombre', 'nombres', 'nombre']);
		$secondName = $this->resolveFirstValue($row, ['segundo_nombre']);
		$newNombre = trim($firstName . ' ' . $secondName);

		$lastName = $this->resolveFirstValue($row, ['primer_apellido', 'apellidos', 'apellido']);
		$secondLastName = $this->resolveFirstValue($row, ['segundo_apellido']);
		$newApellido = trim($lastName . ' ' . $secondLastName);

		$targetEmail = $personalEmail !== '' ? $personalEmail : '';
		$currentEmail = strtolower(trim((string) ($current['email'] ?? '')));
		if ($targetEmail === '' && $currentEmail === '') {
			$targetEmail = $institutionalEmail;
		}

		if ($targetEmail !== '' && !$this->canUseEmailAsPrimaryContact($db, $targetEmail, $contactId)) {
			$targetEmail = $currentEmail;
		}

		$payload = [
			'id' => $contactId,
			'nombre' => $newNombre !== '' ? mb_substr($newNombre, 0, 150) : (string) ($current['nombre'] ?? ''),
			'apellido' => $newApellido !== '' ? mb_substr($newApellido, 0, 150) : (string) ($current['apellido'] ?? ''),
			'cedula' => $identity !== '' ? mb_substr($identity, 0, 20) : ((string) ($current['cedula'] ?? '') !== '' ? (string) ($current['cedula'] ?? '') : null),
			'email' => $targetEmail !== '' ? $targetEmail : ((string) ($current['email'] ?? '') !== '' ? (string) ($current['email'] ?? '') : null),
		];

		$stmt = $db->prepare('UPDATE contactos
			SET nombre = :nombre,
				apellido = :apellido,
				cedula = :cedula,
				email = :email,
				tipo = "estudiante",
				estado = "activo",
				updated_at = NOW()
			WHERE id = :id
			LIMIT 1');
		$stmt->execute($payload);
	}

	private function getContactRow(PDO $db, int $contactId): ?array
	{
		$stmt = $db->prepare('SELECT id, nombre, apellido, cedula, email FROM contactos WHERE id = :id LIMIT 1');
		$stmt->execute(['id' => $contactId]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	private function canUseEmailAsPrimaryContact(PDO $db, string $email, ?int $excludeContactId): bool
	{
		if ($email === '') {
			return false;
		}

		$sql = 'SELECT id FROM contactos WHERE LOWER(TRIM(COALESCE(email, ""))) = :email';
		$params = ['email' => strtolower(trim($email))];
		if ($excludeContactId !== null && $excludeContactId > 0) {
			$sql .= ' AND id <> :exclude_id';
			$params['exclude_id'] = $excludeContactId;
		}
		$sql .= ' LIMIT 1';

		$stmt = $db->prepare($sql);
		$stmt->execute($params);

		return !$stmt->fetchColumn();
	}

	private function addContactChannel(PDO $db, int $contactId, string $channelType, string $channelValue, string $source): void
	{
		$channelValue = trim($channelValue);
		if ($contactId <= 0 || $channelValue === '') {
			return;
		}

		$stmt = $db->prepare('INSERT INTO crm_person_channels (contacto_id, channel_type, channel_value, source, created_at, updated_at)
			VALUES (:contacto_id, :channel_type, :channel_value, :source, NOW(), NOW())
			ON DUPLICATE KEY UPDATE source = VALUES(source), updated_at = NOW()');
		$stmt->execute([
			'contacto_id' => $contactId,
			'channel_type' => $channelType,
			'channel_value' => mb_substr($channelValue, 0, 255),
			'source' => mb_substr($source, 0, 50),
		]);
	}

	private function ensureStudentFromContact(PDO $db, int $contactId, string $codigoEstudiante, string $estadoAcademico): array
	{
		$stmt = $db->prepare('SELECT id, codigo_estudiante FROM estudiantes WHERE contacto_id = :contacto_id LIMIT 1');
		$stmt->execute(['contacto_id' => $contactId]);
		$row = $stmt->fetch();

		$estado = strtolower(trim($estadoAcademico));
		$estado = $estado === 'inactivo' ? 'inactivo' : 'activo';

		if ($row) {
			$studentId = (int) ($row['id'] ?? 0);
			$currentCode = trim((string) ($row['codigo_estudiante'] ?? ''));
			$newCode = trim($codigoEstudiante);
			if ($newCode === '') {
				$newCode = $currentCode;
			}

			$update = $db->prepare('UPDATE estudiantes SET codigo_estudiante = :codigo, estado = :estado, updated_at = NOW() WHERE id = :id LIMIT 1');
			$update->execute([
				'codigo' => $newCode !== '' ? mb_substr($newCode, 0, 50) : null,
				'estado' => $estado,
				'id' => $studentId,
			]);

			return ['student_id' => $studentId, 'created' => false];
		}

		$insert = $db->prepare('INSERT INTO estudiantes (contacto_id, codigo_estudiante, estado, created_at, updated_at)
			VALUES (:contacto_id, :codigo_estudiante, :estado, NOW(), NOW())');
		$insert->execute([
			'contacto_id' => $contactId,
			'codigo_estudiante' => trim($codigoEstudiante) !== '' ? mb_substr(trim($codigoEstudiante), 0, 50) : null,
			'estado' => $estado,
		]);

		return ['student_id' => (int) $db->lastInsertId(), 'created' => true];
	}

	private function ensureCareer(PDO $db, string $careerName): ?int
	{
		$careerName = trim($careerName);
		if ($careerName === '') {
			return null;
		}

		$stmt = $db->prepare('SELECT id FROM carreras WHERE LOWER(TRIM(COALESCE(nombre, ""))) = :nombre LIMIT 1');
		$stmt->execute(['nombre' => strtolower($careerName)]);
		$id = (int) ($stmt->fetchColumn() ?: 0);
		if ($id > 0) {
			return $id;
		}

		$insert = $db->prepare('INSERT INTO carreras (nombre, estado, created_at, updated_at) VALUES (:nombre, "activo", NOW(), NOW())');
		$insert->execute(['nombre' => mb_substr($careerName, 0, 150)]);
		return (int) $db->lastInsertId();
	}

	private function ensureMatricula(PDO $db, int $studentId, int $careerId, ?string $fecha, string $estadoMatricula): bool
	{
		if ($studentId <= 0 || $careerId <= 0) {
			return false;
		}

		$estadoMatricula = trim($estadoMatricula);
		if ($estadoMatricula === '') {
			$estadoMatricula = 'activo';
		}

		$stmt = $db->prepare('SELECT id FROM matriculas
			WHERE estudiante_id = :estudiante_id
			  AND carrera_id = :carrera_id
			  AND COALESCE(estado_matricula, "") = :estado_matricula
			  AND ((fecha IS NULL AND :fecha IS NULL) OR fecha = :fecha)
			LIMIT 1');
		$stmt->execute([
			'estudiante_id' => $studentId,
			'carrera_id' => $careerId,
			'estado_matricula' => mb_substr($estadoMatricula, 0, 50),
			'fecha' => $fecha,
		]);

		if ($stmt->fetchColumn()) {
			return false;
		}

		$insert = $db->prepare('INSERT INTO matriculas (estudiante_id, carrera_id, fecha, estado_matricula, estado, created_at, updated_at)
			VALUES (:estudiante_id, :carrera_id, :fecha, :estado_matricula, "activo", NOW(), NOW())');
		$insert->execute([
			'estudiante_id' => $studentId,
			'carrera_id' => $careerId,
			'fecha' => $fecha,
			'estado_matricula' => mb_substr($estadoMatricula, 0, 50),
		]);

		return true;
	}

	private function normalizeDateValue(string $raw): ?string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return null;
		}

		$timestamp = strtotime($raw);
		if ($timestamp === false) {
			return null;
		}

		return date('Y-m-d', $timestamp);
	}

	private function upsertAcademicHistory(PDO $db, int $contactId, array $row): void
	{
		$userId = (int) ($row['id'] ?? 0);
		if ($contactId <= 0 || $userId <= 0) {
			return;
		}

		$stmt = $db->prepare('INSERT INTO crm_student_academic_history
			(contacto_id, source_user_id, codigo_estudiante, carrera, matricula, nivel, estado_academico, periodo, payload, last_seen_at)
			VALUES (:contacto_id, :source_user_id, :codigo_estudiante, :carrera, :matricula, :nivel, :estado_academico, :periodo, :payload, NOW())
			ON DUPLICATE KEY UPDATE
			codigo_estudiante = VALUES(codigo_estudiante),
			carrera = VALUES(carrera),
			matricula = VALUES(matricula),
			nivel = VALUES(nivel),
			estado_academico = VALUES(estado_academico),
			periodo = VALUES(periodo),
			payload = VALUES(payload),
			last_seen_at = NOW()');

		$stmt->execute([
			'contacto_id' => $contactId,
			'source_user_id' => $userId,
			'codigo_estudiante' => mb_substr($this->resolveFirstValue($row, ['codigo_estudiante', 'codigo_matricula', 'matricula', 'codigo']), 0, 80),
			'carrera' => mb_substr($this->resolveFirstValue($row, ['carrera', 'programa']), 0, 180),
			'matricula' => mb_substr($this->resolveFirstValue($row, ['matricula', 'codigo_matricula']), 0, 120),
			'nivel' => mb_substr($this->resolveFirstValue($row, ['nivel']), 0, 80),
			'estado_academico' => mb_substr($this->resolveFirstValue($row, ['estado_academico', 'estado']), 0, 80),
			'periodo' => mb_substr($this->resolveFirstValue($row, ['periodo']), 0, 80),
			'payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);
	}

	private function mergeSecondaryContacts(PDO $db, int $primaryContactId, string $identity): int
	{
		if ($primaryContactId <= 0 || $identity === '') {
			return 0;
		}

		$stmt = $db->prepare("SELECT id
			FROM contactos
			WHERE id <> :primary_id
			  AND REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(cedula, ''))), '.', ''), '-', ''), ' ', '') = :identity
			ORDER BY id ASC");
		$stmt->execute([
			'primary_id' => $primaryContactId,
			'identity' => $identity,
		]);

		$rows = $stmt->fetchAll() ?: [];
		$merged = 0;
		foreach ($rows as $row) {
			$secondaryId = (int) ($row['id'] ?? 0);
			if ($secondaryId <= 0) {
				continue;
			}

			if ($this->mergeOneSecondaryContact($db, $primaryContactId, $secondaryId)) {
				$merged++;
			}
		}

		return $merged;
	}

	private function mergeOneSecondaryContact(PDO $db, int $primaryContactId, int $secondaryContactId): bool
	{
		if ($primaryContactId <= 0 || $secondaryContactId <= 0 || $primaryContactId === $secondaryContactId) {
			return false;
		}

		try {
			$db->beginTransaction();

			$db->prepare('UPDATE tickets SET contacto_id = :primary_id WHERE contacto_id = :secondary_id')
				->execute(['primary_id' => $primaryContactId, 'secondary_id' => $secondaryContactId]);
			$db->prepare('UPDATE interesados SET contacto_id = :primary_id WHERE contacto_id = :secondary_id')
				->execute(['primary_id' => $primaryContactId, 'secondary_id' => $secondaryContactId]);
			$db->prepare('UPDATE campana_destinatarios SET contacto_id = :primary_id WHERE contacto_id = :secondary_id')
				->execute(['primary_id' => $primaryContactId, 'secondary_id' => $secondaryContactId]);
			$db->prepare('UPDATE bot_conversaciones SET contacto_id = :primary_id WHERE contacto_id = :secondary_id')
				->execute(['primary_id' => $primaryContactId, 'secondary_id' => $secondaryContactId]);

			$db->prepare('INSERT IGNORE INTO crm_person_channels (contacto_id, channel_type, channel_value, source, created_at, updated_at)
				SELECT :primary_id, channel_type, channel_value, source, NOW(), NOW()
				FROM crm_person_channels
				WHERE contacto_id = :secondary_id')
				->execute(['primary_id' => $primaryContactId, 'secondary_id' => $secondaryContactId]);
			$db->prepare('DELETE FROM crm_person_channels WHERE contacto_id = :secondary_id')
				->execute(['secondary_id' => $secondaryContactId]);

			$primaryStudentStmt = $db->prepare('SELECT id FROM estudiantes WHERE contacto_id = :contacto_id LIMIT 1');
			$primaryStudentStmt->execute(['contacto_id' => $primaryContactId]);
			$primaryStudentId = (int) ($primaryStudentStmt->fetchColumn() ?: 0);

			$secondaryStudentStmt = $db->prepare('SELECT id FROM estudiantes WHERE contacto_id = :contacto_id LIMIT 1');
			$secondaryStudentStmt->execute(['contacto_id' => $secondaryContactId]);
			$secondaryStudentId = (int) ($secondaryStudentStmt->fetchColumn() ?: 0);

			if ($secondaryStudentId > 0 && $primaryStudentId <= 0) {
				$db->prepare('UPDATE estudiantes SET contacto_id = :primary_id, updated_at = NOW() WHERE id = :student_id LIMIT 1')
					->execute(['primary_id' => $primaryContactId, 'student_id' => $secondaryStudentId]);
			} elseif ($secondaryStudentId > 0 && $primaryStudentId > 0) {
				$db->prepare('UPDATE matriculas SET estudiante_id = :primary_student WHERE estudiante_id = :secondary_student')
					->execute(['primary_student' => $primaryStudentId, 'secondary_student' => $secondaryStudentId]);
				$db->prepare('DELETE FROM estudiantes WHERE id = :student_id LIMIT 1')
					->execute(['student_id' => $secondaryStudentId]);
			}

			$db->prepare('DELETE FROM contactos WHERE id = :secondary_id LIMIT 1')
				->execute(['secondary_id' => $secondaryContactId]);

			$db->commit();
			return true;
		} catch (Throwable $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			return false;
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
}
