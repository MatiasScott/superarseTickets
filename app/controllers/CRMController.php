<?php

class CRMController extends Controller
{
	public function dashboard(): void
	{
		Auth::requireAuth();
		$periodoFiltro = $this->sanitizePeriodoKey((string) ($_GET['periodo'] ?? ''));
		$periodosDashboard = [];

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
		$countedEntitiesByArea = [
			'admisiones' => [],
			'matriculas' => [],
			'docencia' => [],
		];

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$allowedStudentIds = null;
			$stageMetaByEstadoId = [];
			$stageKeyByEstadoId = [];
			$excludedEstadoIds = [];
			$activeEstados = $db->query("SELECT id, nombre, categoria, orden FROM pipeline_estados WHERE estado = 'activo' ORDER BY orden ASC, id ASC")->fetchAll() ?: [];
			foreach ($activeEstados as $estado) {
				$estadoId = (int) ($estado['id'] ?? 0);
				if ($estadoId <= 0) {
					continue;
				}

				$area = $this->dashboardResolveCrmAreaFromState($estado);
				if ($area === null) {
					$excludedEstadoIds[$estadoId] = true;
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

			$pipelineRows = $db->query("SELECT pm.student_id, pm.estado_id, COALESCE(pe.nombre, '') AS estado_nombre, 'student' AS source_type
				FROM crm_student_pipeline_multi pm
				LEFT JOIN pipeline_estados pe ON pe.id = pm.estado_id
				ORDER BY pm.student_id ASC, pe.orden ASC, pe.id ASC")->fetchAll() ?: [];

			$legacyPipelineRows = $db->query("SELECT p.student_id, p.estado_id, COALESCE(pe.nombre, '') AS estado_nombre, 'student' AS source_type
				FROM crm_student_pipeline p
				LEFT JOIN pipeline_estados pe ON pe.id = p.estado_id
				WHERE NOT EXISTS (
					SELECT 1 FROM crm_student_pipeline_multi pm WHERE pm.student_id = p.student_id
				)")->fetchAll() ?: [];
			if (!empty($legacyPipelineRows)) {
				$pipelineRows = array_merge($pipelineRows, $legacyPipelineRows);
			}

			$prospectPipelineRows = $db->query("SELECT i.contacto_id AS student_id, i.estado_id, COALESCE(pe.nombre, '') AS estado_nombre, 'prospect' AS source_type
				FROM interesados i
				LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
				WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0")->fetchAll() ?: [];
			if (!empty($prospectPipelineRows)) {
				$pipelineRows = array_merge($pipelineRows, $prospectPipelineRows);
			}

			$userLevels = [];
			$remote = $this->connectSuperarseDatabase();
			if ($remote instanceof PDO && $this->resolveSuperarseStudentTable($remote) === 'users') {
				$periodosDashboard = $this->fetchRemotePeriodOptions($remote, 'users');
				$userRows = $remote->query('SELECT id, nivel, TRIM(COALESCE(periodo, "")) AS periodo FROM users')->fetchAll() ?: [];
				$allowedStudentIds = [];
				foreach ($userRows as $userRow) {
					$studentId = (int) ($userRow['id'] ?? 0);
					if ($studentId <= 0) {
						continue;
					}
					$currentPeriodo = $this->sanitizePeriodoKey((string) ($userRow['periodo'] ?? ''));
					if ($periodoFiltro !== '' && $currentPeriodo !== $periodoFiltro) {
						continue;
					}
					$allowedStudentIds[$studentId] = true;
					$nivel = $this->dashboardExtractNivel((string) ($userRow['nivel'] ?? ''));
					if ($nivel !== null) {
						$userLevels[$studentId] = $nivel;
					}
				}
			} else {
				$periodosDashboard = $this->fetchLocalPeriodOptions();
				if ($periodoFiltro !== '') {
					$allowedStudentIds = [];
					$stmtIds = $db->prepare("SELECT id FROM estudiantes WHERE DATE_FORMAT(created_at, '%Y-%m') = :periodo");
					$stmtIds->execute([':periodo' => $periodoFiltro]);
					foreach (($stmtIds->fetchAll() ?: []) as $rowId) {
						$sid = (int) ($rowId['id'] ?? 0);
						if ($sid > 0) {
							$allowedStudentIds[$sid] = true;
						}
					}
				}
			}

			if (empty($periodosDashboard)) {
				$periodosDashboard = $this->fetchLocalPeriodOptions();
			}

			foreach ($pipelineRows as $row) {
				$sourceType = (string) ($row['source_type'] ?? 'student');
				$studentId = (int) ($row['student_id'] ?? 0);
				if ($periodoFiltro !== '' && $sourceType === 'student' && is_array($allowedStudentIds) && !isset($allowedStudentIds[$studentId])) {
					continue;
				}

				$estadoId = (int) ($row['estado_id'] ?? 0);
				$stageName = trim((string) ($row['estado_nombre'] ?? ''));
				$stageMeta = $stageMetaByEstadoId[$estadoId] ?? null;

				if ($stageMeta === null) {
					if ($estadoId > 0 && isset($excludedEstadoIds[$estadoId])) {
						continue;
					}

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

				$areaName = (string) ($stageMeta['area'] ?? '');
				$entityKey = $sourceType . ':' . $studentId;
				if ($areaName !== '' && isset($countedEntitiesByArea[$areaName][$entityKey])) {
					continue;
				}
				if ($areaName !== '' && isset($countedEntitiesByArea[$areaName])) {
					$countedEntitiesByArea[$areaName][$entityKey] = true;
				}

				if ($stageMeta['area'] === 'admisiones' && isset($admisionesRows[$estadoId])) {
					$admisionesRows[$estadoId]['count']++;
					continue;
				}

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
			'periodosDashboard' => $periodosDashboard,
			'periodoDashboardSeleccionado' => $periodoFiltro,
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

		$excludedCategories = ['sin crm', 'sin_crm', 'ninguno', 'ninguna', 'no crm', 'no pertenece a ningun crm'];
		foreach ($excludedCategories as $excludedCategory) {
			if ($normalizedCategory === $excludedCategory) {
				return null;
			}
		}

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
		try {
			if ($this->shouldRunProspectReconcile()) {
				$this->reconcileProspectsWithSuperarseUsers();
				$this->markProspectReconcileRun();
			}
		} catch (Throwable $e) {
			// Evitar romper la vista por reconciliacion.
		}
		$periodoFiltro = $this->sanitizePeriodoKey((string) ($_GET['periodo'] ?? ''));
		$prospectSort = $this->normalizeProspectSort((string) ($_GET['prospect_sort'] ?? 'desc'));

		$studentsPerPage = 15;
		$studentPage = max(1, (int) ($_GET['student_page'] ?? 1));
		$studentOffset = ($studentPage - 1) * $studentsPerPage;
		$studentsData = $this->fetchSuperarseStudents($studentsPerPage, $periodoFiltro, $studentOffset);
		$estudiantesSuperarse = is_array($studentsData['rows'] ?? null) ? $studentsData['rows'] : [];
		$programas = is_array($studentsData['programas'] ?? null) ? $studentsData['programas'] : [];
		$nivelesEstudiantes = is_array($studentsData['niveles'] ?? null) ? $studentsData['niveles'] : [];
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

		$totalProspects = $this->countLocalProspects();
		$prospectPerPage = 50;
		$prospectPage = max(1, (int) ($_GET['prospect_page'] ?? 1));
		$prospectPages = max(1, (int) ceil($totalProspects / $prospectPerPage));
		$prospectPage = min($prospectPage, $prospectPages);
		$prospectosLocales = $this->fetchLocalProspects($prospectPerPage, ($prospectPage - 1) * $prospectPerPage, $prospectSort);

		if (empty($prospectosLocales) && $totalProspects > 0 && $prospectPage > 1) {
			$prospectPage = 1;
			$prospectosLocales = $this->fetchLocalProspects($prospectPerPage, 0, $prospectSort);
		}

		$db = Database::getInstance()->connection();
		try {
			$pipelineEstados = $this->fetchVisiblePipelineStates($db, 'id, nombre');
			$pipelineEstadosEstudiantes = $this->fetchAllPipelineStates($db, 'id, nombre');
			$prospectAdvisorOptions = $this->fetchProspectSelectorOptions($db, 'crm_prospect_asesores');
			$prospectCreatorOptions = $this->fetchProspectSelectorOptions($db, 'crm_prospect_creadores');
		} catch (Throwable $e) {
			$pipelineEstados = [];
			$pipelineEstadosEstudiantes = [];
			$prospectAdvisorOptions = [];
			$prospectCreatorOptions = [];
		}

		try {
			$prospectFilterOptions = $this->fetchDistinctProspectFilterOptions();
		} catch (Throwable $e) {
			$prospectFilterOptions = ['origins' => [], 'stages' => [], 'careers' => [], 'createdBy' => []];
		}
		if (empty($prospectAdvisorOptions)) {
			$prospectAdvisorOptions = $prospectFilterOptions['origins'];
		}

		$this->view('crm/interesados', [
			'estudiantesSuperarse' => $estudiantesSuperarse,
			'studentPage'      => $studentPage,
			'studentPages'     => $studentPages,
			'studentsPerPage'  => $studentsPerPage,
			'totalStudents'    => $totalStudents,
			'prospectosLocales' => $prospectosLocales,
			'programas' => $programas,
			'nivelesEstudiantes' => $nivelesEstudiantes,
			'periodos' => $periodos,
			'periodoSeleccionado' => $periodoFiltro,
			'pipelineEstados' => $pipelineEstados,
			'pipelineEstadosEstudiantes' => $pipelineEstadosEstudiantes,
			'prospectAdvisorOptions' => $prospectAdvisorOptions,
			'prospectCreatorOptions' => $prospectCreatorOptions,
			'prospectFilterOptions' => $prospectFilterOptions,
			'sourceLabel' => (string) ($studentsData['source'] ?? 'No disponible'),
			'sourceError' => (string) ($studentsData['error'] ?? ''),
			'prospectPage'   => $prospectPage,
			'prospectPages'  => $prospectPages,
			'totalProspects' => $totalProspects,
			'prospectSort' => $prospectSort,
		], [
			'title' => 'CRM - Ver todo CRM',
		]);
	}

	public function createProspect(): void
	{
		Auth::requireAuth();

		// Si llega por AJAX (modal del CCI), respondemos JSON y no redirigimos,
		// para que el frontend muestre errores reales en lugar de un falso éxito.
		$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

		$ajaxFail = static function (string $message) use ($isAjax): void {
			if ($isAjax) {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['ok' => false, 'error' => $message]);
				exit;
			}
			set_flash('error', $message);
			redirect('crm/interesados?tab=prospects');
		};

		if (!verify_csrf($_POST['_token'] ?? null)) {
			$ajaxFail('Token CSRF invalido. Recarga la pagina e intenta de nuevo.');
		}

		$nombres = trim((string) ($_POST['nombres'] ?? ''));
		$apellidos = trim((string) ($_POST['apellidos'] ?? ''));
		$identificacion = $this->normalizeIdentityValue((string) ($_POST['identificacion'] ?? ''));
		$celularRaw = trim((string) ($_POST['celular'] ?? ''));
		$celular = $this->normalizeProspectPhoneValue($celularRaw);
		$correoPersonal = $this->normalizeEmailValue((string) ($_POST['correo_personal'] ?? ''));
		$origen = trim((string) ($_POST['origen'] ?? 'crm_manual'));
		$carrera = trim((string) ($_POST['carrera'] ?? ''));
		// Mapeo automático de Creado por según Asesor
		$creadoPor = $this->mapAsesorToCreador($origen);
		$modalidad = trim((string) ($_POST['modalidad'] ?? ''));
		$provincia = trim((string) ($_POST['provincia'] ?? ''));
		$ciudad = trim((string) ($_POST['ciudad'] ?? ''));

		if ($nombres === '') {
			$ajaxFail('El nombre es obligatorio.');
		}

		if ($celularRaw !== '' && $celular === '') {
			$ajaxFail('El celular debe tener el formato +593987654321.');
		}

		$identificacionToStore = $identificacion !== '' ? mb_substr($identificacion, 0, 20) : null;

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

			if ($celular !== '') {
				$phoneOwnerContactId = $this->findActiveContactIdByPhone($db, $celular);
				if ($phoneOwnerContactId !== null) {
					if ($contactId === null) {
						// El teléfono ya pertenece a un contacto existente (ej. creado desde CCI): se reutiliza en vez de bloquear.
						$contactId = $phoneOwnerContactId;
					} elseif ($phoneOwnerContactId !== (int) $contactId) {
						$conflictSuffix = $this->buildConflictContactSuffix($db, $phoneOwnerContactId);
						throw new RuntimeException('El número de celular ya existe en otro cliente potencial' . $conflictSuffix . '.');
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
					'cedula' => $identificacionToStore,
					'email' => $emailToStore !== '' ? $emailToStore : null,
				]);
				$contactId = (int) $db->lastInsertId();
				$isNew = true;
			} else {
				$updateContact = $db->prepare('UPDATE contactos
					SET nombre = :nombre,
						apellido = :apellido,
						cedula = COALESCE(:cedula, cedula),
						estado = "activo",
						updated_at = NOW()
					WHERE id = :id
					LIMIT 1');
				$updateContact->execute([
					'nombre' => mb_substr($nombres, 0, 150),
					'apellido' => mb_substr($apellidos, 0, 150),
					'cedula' => $identificacionToStore,
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
			$this->upsertInteresado(
				$db,
				$contactId,
				$estadoInicialId,
				$origen !== '' ? $origen : 'crm_manual',
				$creadoPor,
				$carrera,
				$modalidad,
				$provincia,
				$ciudad
			);

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

			if ($isAjax) {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode([
					'ok' => true,
					'message' => $isNew
						? 'Cliente potencial creado correctamente.'
						: 'Cliente potencial actualizado y vinculado correctamente.',
					'contacto_id' => (int) $contactId,
				]);
				exit;
			}

			set_flash('success', $isNew
				? 'Cliente potencial creado correctamente.'
				: 'Cliente potencial actualizado y vinculado correctamente.');
			redirect('crm/interesados?tab=prospects&open_contact_id=' . (int) $contactId);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			if ($isAjax) {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['ok' => false, 'error' => 'No se pudo crear el cliente potencial: ' . $e->getMessage()]);
				exit;
			}
			set_flash('error', 'No se pudo crear el cliente potencial: ' . $e->getMessage());
		}

		redirect('crm/interesados?tab=prospects');
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
					LEFT JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					ORDER BY e.id DESC
					LIMIT 10000";
			$stmt = $db->query($sql);
			$estudiantes = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$estudiantes = [];
		}

		$this->view('crm/estudiantes', compact('estudiantes'), [
			'title' => 'CRM - Estudiantes',
		]);
	}

	public function estudiantesFilter(): void
	{
		Auth::requireAuth();

		$nombre = strtolower(trim((string) ($_GET['nombre'] ?? '')));
		$carrera = strtolower(trim((string) ($_GET['carrera'] ?? '')));

		$db = Database::getInstance()->connection();

		$where = [];
		$params = [];

		if ($nombre !== '') {
			$where[] = "LOWER(CONCAT_WS(' ', COALESCE(c.nombre, ''), COALESCE(c.apellido, ''))) LIKE :nombre";
			$params['nombre'] = '%' . $nombre . '%';
		}

		if ($carrera !== '') {
			$where[] = "LOWER(COALESCE(ca.nombre, '')) LIKE :carrera";
			$params['carrera'] = '%' . $carrera . '%';
		}

		$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

		$sql = "SELECT e.id, e.numero_identificacion, e.estado,
				   c.nombre, c.apellido,
				   ca.nombre AS carrera
				FROM estudiantes e
				LEFT JOIN contactos c ON c.id = e.contacto_id
				LEFT JOIN matriculas m ON m.estudiante_id = e.id
				LEFT JOIN carreras ca ON ca.id = m.carrera_id
				$whereClause
				ORDER BY e.id DESC
				LIMIT 10000";

		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$estudiantes = $stmt->fetchAll() ?: [];

		$countSql = "SELECT COUNT(*) AS total FROM estudiantes e
					LEFT JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					$whereClause";

		$countStmt = $db->prepare($countSql);
		$countStmt->execute($params);
		$countRow = $countStmt->fetch();
		$total = (int) ($countRow['total'] ?? 0);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'success' => true,
			'estudiantes' => $estudiantes,
			'total' => $total,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	public function interesadosStudentsFilter(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$readMultiFilter = static function (string $name): array {
			$raw = $_GET[$name] ?? null;
			if (is_array($raw)) {
				return array_values($raw);
			}
			if (is_string($raw) && trim($raw) !== '') {
				return preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			}
			return [];
		};

		$periodos = array_values(array_filter(array_unique(array_map(function ($value): string {
			return $this->sanitizePeriodoKey((string) $value);
		}, $readMultiFilter('periodo'))), static function ($value): bool {
			return $value !== '';
		}));
		$normalizeFilter = static function ($value): string {
			$normalized = mb_strtolower(trim((string) $value), 'UTF-8');
			$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
			if (is_string($ascii) && $ascii !== '') {
				$normalized = $ascii;
			}
			$normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?: '';
			return trim((string) preg_replace('/\s+/', ' ', $normalized));
		};

		$periodosNormalized = array_values(array_filter(array_map($normalizeFilter, $periodos), static function ($value): bool {
			return $value !== '';
		}));
		$periodo = count($periodos) === 1 ? (string) ($periodos[0] ?? '') : '';
		$nombre = $normalizeFilter((string) ($_GET['nombre'] ?? ''));
		$carreras = array_values(array_filter(array_unique(array_map(static function ($value): string {
			$normalized = mb_strtolower(trim((string) $value), 'UTF-8');
			$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
			if (is_string($ascii) && $ascii !== '') {
				$normalized = $ascii;
			}
			$normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?: '';
			return trim((string) preg_replace('/\s+/', ' ', $normalized));
		}, $readMultiFilter('carrera'))), static function ($value): bool {
			return $value !== '';
		}));
		$etapas = array_values(array_filter(array_unique(array_map(static function ($value): string {
			$normalized = mb_strtolower(trim((string) $value), 'UTF-8');
			$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
			if (is_string($ascii) && $ascii !== '') {
				$normalized = $ascii;
			}
			$normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?: '';
			return trim((string) preg_replace('/\s+/', ' ', $normalized));
		}, $readMultiFilter('etapa'))), static function ($value): bool {
			return $value !== '';
		}));
		$niveles = array_values(array_filter(array_unique(array_map(static function ($value): string {
			$normalized = mb_strtolower(trim((string) $value), 'UTF-8');
			$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
			if (is_string($ascii) && $ascii !== '') {
				$normalized = $ascii;
			}
			$normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?: '';
			return trim((string) preg_replace('/\s+/', ' ', $normalized));
		}, $readMultiFilter('nivel'))), static function ($value): bool {
			return $value !== '';
		}));

		try {
			$studentsData = $this->fetchSuperarseStudents(50000, $periodo, 0);
			$rows = is_array($studentsData['rows'] ?? null) ? $studentsData['rows'] : [];

			$normalize = $normalizeFilter;

			$filtered = [];
			foreach ($rows as $row) {
				$fullName = $normalize((string) ($row['nombre'] ?? '') . ' ' . (string) ($row['apellido'] ?? ''));
				$rowEmail = $normalize((string) ($row['email'] ?? ''));
				$rowPeriod = $normalize($row['periodo_clave'] ?? ($row['periodo'] ?? ''));
				$rowCareer = $normalize($row['carrera'] ?? '');
				$rowStage = $normalize($row['pipeline_nombre'] ?? '');
				$rowStageListRaw = is_array($row['pipeline_nombres'] ?? null) ? $row['pipeline_nombres'] : [];
				$rowStages = array_values(array_filter(array_map($normalize, $rowStageListRaw), static fn($value) => $value !== ''));
				if (empty($rowStages) && $rowStage !== '') {
					$rowStages[] = $rowStage;
				}
				$rowLevel = $normalize($row['nivel'] ?? '');

				if (!empty($periodosNormalized) && !in_array($rowPeriod, $periodosNormalized, true)) {
					continue;
				}
				if ($nombre !== '' && strpos($fullName, $nombre) === false && strpos($rowEmail, $nombre) === false) {
					continue;
				}
				if (!empty($carreras) && !in_array($rowCareer, $carreras, true)) {
					continue;
				}
				if (!empty($etapas) && empty(array_intersect($rowStages, $etapas))) {
					continue;
				}
				if (!empty($niveles) && !in_array($rowLevel, $niveles, true)) {
					continue;
				}

				$filtered[] = $row;
			}

			echo json_encode([
				'success' => true,
				'students' => $filtered,
				'total' => count($filtered),
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		exit;
	}

	private function fetchSuperarseStudents(int $limit = 500, string $periodoFiltro = '', int $offset = 0): array
	{
		$limit = max(1, min(50000, $limit));
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
				$programas = $this->fetchLocalCareerOptions();
				$niveles = $this->fetchLocalLevelOptions();
				return [
					'rows' => $fallbackRows,
					'total' => $fallbackTotal > 0 ? $fallbackTotal : count($fallbackRows),
					'periodos' => $periodos,
					'programas' => $programas,
					'niveles' => $niveles,
					'source' => 'Local (fallback)',
					'error' => 'No se pudo conectar a la BD Superarse. Revisa SUPERARSE_DB_* en .env.',
				];
			}

			$sourceTable = $this->resolveSuperarseStudentTable($remote);
			if ($sourceTable === null) {
				throw new RuntimeException('No existe tabla users ni estudiantes en la BD Superarse.');
			}

			$periodos = $this->fetchRemotePeriodOptions($remote, $sourceTable);
			$programas = $sourceTable === 'users' ? $this->fetchRemoteProgramOptions($remote) : $this->fetchLocalCareerOptions();
			$niveles = $sourceTable === 'users' ? $this->fetchRemoteLevelOptions($remote) : $this->fetchLocalLevelOptions();
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
				'programas' => $programas,
				'niveles' => $niveles,
				'source' => 'Superarse (' . $sourceTable . ')',
				'error' => '',
			];
		} catch (Throwable $e) {
			$fallbackRows = $this->fetchLocalFallbackStudents($limit, $periodoFiltro, $offset);
			$fallbackRows = $this->attachPipelineData($fallbackRows);
			$fallbackTotal = $this->countLocalStudents($periodoFiltro);
			$periodos = $this->fetchLocalPeriodOptions();
			$programas = $this->fetchLocalCareerOptions();
			$niveles = $this->fetchLocalLevelOptions();
			return [
				'rows' => $fallbackRows,
				'total' => $fallbackTotal > 0 ? $fallbackTotal : count($fallbackRows),
				'periodos' => $periodos,
				'programas' => $programas,
				'niveles' => $niveles,
				'source' => 'Local (fallback)',
				'error' => 'No se pudo leer estudiantes de Superarse: ' . $e->getMessage(),
			];
		}
	}

	private function fetchRemoteProgramOptions(PDO $remote): array
	{
		try {
			$rows = $remote->query("SELECT DISTINCT TRIM(COALESCE(programa, '')) AS programa
				FROM users
				WHERE programa IS NOT NULL AND TRIM(programa) <> ''
				ORDER BY programa ASC")->fetchAll() ?: [];
			$programas = [];
			foreach ($rows as $row) {
				$programa = trim((string) ($row['programa'] ?? ''));
				if ($programa !== '') {
					$programas[] = $programa;
				}
			}
			return array_values(array_unique($programas));
		} catch (Throwable $e) {
			return [];
		}
	}

	private function fetchRemoteLevelOptions(PDO $remote): array
	{
		try {
			$rows = $remote->query("SELECT DISTINCT TRIM(COALESCE(nivel, '')) AS nivel
				FROM users
				WHERE nivel IS NOT NULL AND TRIM(nivel) <> ''
				ORDER BY nivel ASC")->fetchAll() ?: [];
			$niveles = [];
			foreach ($rows as $row) {
				$nivel = trim((string) ($row['nivel'] ?? ''));
				if ($nivel !== '') {
					$niveles[] = $nivel;
				}
			}
			return array_values(array_unique($niveles));
		} catch (Throwable $e) {
			return [];
		}
	}

	private function fetchLocalCareerOptions(): array
	{
		try {
			$db = Database::getInstance()->connection();
			$rows = $db->query("SELECT nombre FROM carreras WHERE estado = 'activo' ORDER BY nombre ASC")->fetchAll() ?: [];
			$programas = [];
			foreach ($rows as $row) {
				$nombre = trim((string) ($row['nombre'] ?? ''));
				if ($nombre !== '') {
					$programas[] = $nombre;
				}
			}
			return array_values(array_unique($programas));
		} catch (Throwable $e) {
			return [];
		}
	}

	private function fetchLocalLevelOptions(): array
	{
		try {
			$db = Database::getInstance()->connection();
			$rows = $db->query("SELECT DISTINCT TRIM(COALESCE(nivel, '')) AS nivel
				FROM estudiantes
				WHERE nivel IS NOT NULL AND TRIM(nivel) <> ''
				ORDER BY nivel ASC")->fetchAll() ?: [];
			$niveles = [];
			foreach ($rows as $row) {
				$nivel = trim((string) ($row['nivel'] ?? ''));
				if ($nivel !== '') {
					$niveles[] = $nivel;
				}
			}
			return array_values(array_unique($niveles));
		} catch (Throwable $e) {
			return [];
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
		return (int) (Auth::id() ?? ($_SESSION['user_id'] ?? 0));
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

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_pipeline_multi (
				student_id INT NOT NULL,
				estado_id INT NOT NULL,
				updated_by INT NULL,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (student_id, estado_id),
				INDEX idx_pipeline_multi_estado (estado_id),
				INDEX idx_pipeline_multi_student (student_id)
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

			$db->exec("CREATE TABLE IF NOT EXISTS crm_student_note_adjuntos (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				note_id BIGINT NOT NULL,
				student_id INT NOT NULL,
				filename_original VARCHAR(255) NOT NULL,
				filename_storage VARCHAR(255) NOT NULL,
				mime VARCHAR(120) NOT NULL,
				size_bytes INT NOT NULL DEFAULT 0,
				storage_path VARCHAR(600) NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_crm_note_adjuntos_note (note_id),
				INDEX idx_crm_note_adjuntos_student (student_id)
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

			$interesadosColumns = $this->getTableColumnsSafe($db, 'interesados');
			if (!in_array('carrera', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN carrera VARCHAR(180) NULL AFTER origen');
			}
			if (!in_array('creado_por', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN creado_por VARCHAR(255) NULL AFTER origen');
			}
			if (!in_array('asesor', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN asesor VARCHAR(255) NULL AFTER origen');
			}
			if (!in_array('modalidad', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN modalidad VARCHAR(80) NULL AFTER carrera');
			}
			if (!in_array('provincia', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN provincia VARCHAR(120) NULL AFTER modalidad');
			}
			if (!in_array('ciudad', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN ciudad VARCHAR(120) NULL AFTER provincia');
			}
			if (!in_array('deleted_at', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at');
			}
			if (!in_array('razon_descalificacion', $interesadosColumns, true)) {
				$db->exec('ALTER TABLE interesados ADD COLUMN razon_descalificacion INT NULL DEFAULT NULL');
			}

			$contactosColumns = $this->getTableColumnsSafe($db, 'contactos');
			if (!in_array('deleted_at', $contactosColumns, true)) {
				$db->exec('ALTER TABLE contactos ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL');
			}

			$this->ensureProspectSelectorCatalogTables($db);
		} catch (Throwable $e) {
			// Evitar romper el flujo principal por auto-creacion auxiliar.
		}
	}

	private function ensureProspectSelectorCatalogTables(PDO $db): void
	{
		$db->exec("CREATE TABLE IF NOT EXISTS crm_prospect_asesores (
			id INT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(120) NOT NULL,
			estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
			created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_crm_prospect_asesor_nombre (nombre)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS crm_prospect_creadores (
			id INT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(120) NOT NULL,
			estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
			created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_crm_prospect_creador_nombre (nombre)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$db->exec("CREATE TABLE IF NOT EXISTS crm_modalidades (
			id INT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(80) NOT NULL,
			descripcion VARCHAR(255) NULL,
			activo TINYINT(1) NOT NULL DEFAULT 1,
			orden INT NOT NULL DEFAULT 0,
			created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_crm_modalidade_nombre (nombre)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		// Insertar modalidades por defecto si no existen
		$countModalidades = (int) $db->query("SELECT COUNT(*) FROM crm_modalidades")->fetchColumn();
		if ($countModalidades === 0) {
			$db->exec("INSERT INTO crm_modalidades (nombre, descripcion, activo, orden) VALUES
				('Presencial Híbrida', 'Clases en sitio físico', 1, 1),
				('Virtual Híbrida', 'Clases en línea', 1, 2),
				('En Línea', 'Clases completamente en línea', 1, 3)");
		}

		$db->exec("CREATE TABLE IF NOT EXISTS crm_descalificacion_razones (
			id INT AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(150) NOT NULL,
			orden INT NOT NULL DEFAULT 0,
			activo TINYINT(1) NOT NULL DEFAULT 1,
			created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$db->exec("CREATE TABLE IF NOT EXISTS soft_delete_audit (
				id INT AUTO_INCREMENT PRIMARY KEY,
				entity_type VARCHAR(50) NOT NULL,
				entity_id INT NOT NULL,
				deleted_by INT NULL,
				deleted_at DATETIME NOT NULL,
				restored_by INT NULL,
				restored_at DATETIME NULL,
				reason VARCHAR(255) NULL,
				created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				INDEX idx_soft_delete_audit_entity (entity_type, entity_id),
				INDEX idx_soft_delete_audit_deleted_at (deleted_at),
				INDEX idx_soft_delete_audit_restored_at (restored_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$countRazones = (int) $db->query("SELECT COUNT(*) FROM crm_descalificacion_razones")->fetchColumn();
		if ($countRazones === 0) {
			$db->exec("INSERT INTO crm_descalificacion_razones (nombre, orden) VALUES
				('Sin recursos económicos', 1),
				('No cumple requisitos académicos', 2),
				('Falta de tiempo', 3),
				('Eligió otra institución', 4),
				('No está interesado', 5),
				('No se puede contactar', 6),
				('Número equivocado', 7),
				('Otro', 8)");
		}
	}

	private function fetchProspectSelectorOptions(PDO $db, string $table): array
	{
		$stmt = $db->query("SELECT nombre FROM {$table} WHERE estado = 'activo' ORDER BY nombre ASC, id ASC");
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		$options = [];
		foreach ($rows as $row) {
			$value = trim((string) ($row['nombre'] ?? ''));
			if ($value !== '') {
				$options[] = $value;
			}
		}

		return $options;
	}

	private function fetchVisiblePipelineStates(PDO $db, string $columns = 'id, nombre'): array
	{
		// FASE 3: Solo mostrar estas 5 etapas en el CRM (en orden específico)
		$allowedStages = ['1 Etapa interesado', '2 Etapa seguimiento', 'Siguiente periodo (no legaliza matricula)', 'Descalificado', 'Inscritos'];
		$placeholders = implode(',', array_fill(0, count($allowedStages), '?'));
		
		$sql = "SELECT {$columns}
			FROM pipeline_estados
			WHERE estado = 'activo'
			  AND nombre IN ($placeholders)
			ORDER BY 
				CASE nombre
					WHEN '1 Etapa interesado' THEN 1
					WHEN '2 Etapa seguimiento' THEN 2
					WHEN 'Siguiente periodo (no legaliza matricula)' THEN 3
					WHEN 'Descalificado' THEN 4
					WHEN 'Inscritos' THEN 5
					ELSE 6
				END ASC";
		
		$stmt = $db->prepare($sql);
		$stmt->execute($allowedStages);
		return $stmt ? ($stmt->fetchAll() ?: []) : [];
	}

	private function fetchAllPipelineStates(PDO $db, string $columns = 'id, nombre'): array
	{
		// Para estudiantes: obtener TODAS las etapas sin filtrar
		$sql = "SELECT {$columns}
			FROM pipeline_estados
			WHERE estado = 'activo'
			ORDER BY orden ASC, nombre ASC";
		
		$stmt = $db->prepare($sql);
		$stmt->execute();
		return $stmt ? ($stmt->fetchAll() ?: []) : [];
	}

	public function interesadosProspectsFilter(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$accentMap = [
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
			'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
			'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
			'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
			'ç' => 'c',
		];
		// Normalizar igual que en SQL: minusculas, sin acentos y sin enie.
		$normalizeFilter = static function ($value) use ($accentMap): string {
			$normalized = mb_strtolower(trim((string) $value), 'UTF-8');
			$normalized = strtr($normalized, $accentMap);
			$normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?: '';
			return trim((string) preg_replace('/\s+/', ' ', $normalized));
		};
		$readMulti = static function (string $name) use ($normalizeFilter): array {
			$raw = $_GET[$name] ?? null;
			$items = is_array($raw) ? $raw : (is_string($raw) && trim($raw) !== '' ? preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] : []);
			return array_values(array_filter(array_unique(array_map($normalizeFilter, $items)), static fn($v) => $v !== ''));
		};

		$search = trim((string) ($_GET['q'] ?? ''));
		$searchDigits = preg_replace('/\D+/', '', $search) ?: '';
		$origenes = $readMulti('origen');
		$etapas = $readMulti('etapa');
		$carreras = $readMulti('carrera');
		$creadoPor = $readMulti('creado_por');
		$preset = trim((string) ($_GET['created_preset'] ?? ''));
		$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
		$dateTo = trim((string) ($_GET['date_to'] ?? ''));
		$sort = $this->normalizeProspectSort((string) ($_GET['sort'] ?? 'desc'));
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = max(10, min(200, (int) ($_GET['per_page'] ?? 50)));

		try {
			$db = Database::getInstance()->connection();

			$where = ["i.estado = 'activo'", 'COALESCE(i.convertido, 0) = 0', 'i.deleted_at IS NULL'];
			$params = [];

			if ($search !== '') {
				$like = '%' . mb_strtolower($search, 'UTF-8') . '%';
				$cond = "(LOWER(CONCAT_WS(' ', COALESCE(c.nombre, ''), COALESCE(c.apellido, ''))) LIKE :search_name
					OR LOWER(COALESCE(c.email, '')) LIKE :search_email";
				$params[':search_name'] = $like;
				$params[':search_email'] = $like;
				if ($searchDigits !== '') {
					// El telefono puede venir con +593: buscar por sufijo de digitos.
					$cond .= " OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(tc.telefono, ''), '+', ''), '-', ''), ' ', ''), ')', ''), '(', '') LIKE :search_phone";
					$params[':search_phone'] = '%' . $searchDigits;
				}
				$cond .= ')';
				$where[] = $cond;
			}

			// Los valores llegan normalizados desde el cliente (minusculas, sin acentos,
			// sin enie): normalizar igual la columna en SQL para que coincidan.
			$sqlAccentExpr = static function (string $expression): string {
				return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER($expression), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ü', 'u'), 'ñ', 'n')";
			};
			$valueAccentMap = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'];

			$addMulti = function (string $expressionBase, array $values, string $prefix) use (&$where, &$params, $sqlAccentExpr, $valueAccentMap): void {
				if (empty($values)) {
					return;
				}
				$expr = $sqlAccentExpr($expressionBase);
				$placeholders = [];
				foreach (array_values($values) as $idx => $value) {
					$key = $prefix . '_' . $idx;
					$placeholders[] = ':' . ltrim($key, ':');
					$params[$key] = strtr($value, $valueAccentMap);
				}
				$where[] = "$expr IN (" . implode(',', $placeholders) . ")";
			};

			$addMulti('i.origen', $origenes, ':origen');
			$addMulti('COALESCE(pe.nombre, \'\')', $etapas, ':etapa');
			$addMulti('i.carrera', $carreras, ':carrera');

			// creado_por puede contener varios nombres separados por coma:
			// se busca por subcadena dentro del campo normalizado.
			if (!empty($creadoPor)) {
				$cpExpr = $sqlAccentExpr('i.creado_por');
				$orParts = [];
				foreach (array_values($creadoPor) as $idx => $value) {
					$key = ':cp_' . $idx;
					$orParts[] = "$cpExpr LIKE " . $key;
					$params[$key] = '%' . strtr($value, $valueAccentMap) . '%';
				}
				$where[] = '(' . implode(' OR ', $orParts) . ')';
			}

			// Filtros de fecha de creacion
			$now = new DateTimeImmutable('now');
			$presetStart = null;
			$presetEnd = null;
			if ($preset === 'today') {
				$presetStart = $now->setTime(0, 0, 0);
				$presetEnd = $presetStart->modify('+1 day');
			} elseif ($preset === 'current_week') {
				$dow = (int) $now->format('N');
				$presetStart = $now->setTime(0, 0, 0)->modify('-' . ($dow - 1) . ' days');
				$presetEnd = $presetStart->modify('+7 days');
			} elseif ($preset === 'previous_week') {
				$dow = (int) $now->format('N');
				$presetStart = $now->setTime(0, 0, 0)->modify('-' . ($dow - 1 + 7) . ' days');
				$presetEnd = $presetStart->modify('+7 days');
			} elseif ($preset === 'last_30_days') {
				$presetStart = $now->setTime(0, 0, 0)->modify('-30 days');
			} elseif ($preset === 'custom' || $dateFrom !== '' || $dateTo !== '') {
				if ($dateFrom !== '') {
					$dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dateFrom);
					$presetStart = $dt ?: null;
				}
				if ($dateTo !== '') {
					$dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dateTo);
					$presetEnd = $dt ?: null;
				}
			}

			if ($presetStart instanceof DateTimeInterface) {
				$where[] = 'i.created_at >= :date_from';
				$params[':date_from'] = $presetStart->format('Y-m-d H:i:s');
			}
			if ($presetEnd instanceof DateTimeInterface) {
				$where[] = ($preset === 'custom' ? 'i.created_at <= :date_to' : 'i.created_at < :date_to');
				$params[':date_to'] = $presetEnd->format('Y-m-d H:i:s');
			}

			$whereSql = 'WHERE ' . implode(' AND ', $where);
			$orderBy = $sort === 'asc'
				? "ORDER BY COALESCE(c.nombre, 'Sin nombre') ASC, COALESCE(c.apellido, '') ASC, i.id ASC"
				: "ORDER BY COALESCE(c.nombre, 'Sin nombre') DESC, COALESCE(c.apellido, '') DESC, i.id DESC";
			$offset = ($page - 1) * $perPage;

			$baseJoins = '
				FROM interesados i
				LEFT JOIN contactos c ON c.id = i.contacto_id
				LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
					AND LOWER(REPLACE(TRIM(COALESCE(pe.categoria, \'\')), \' \', \'_\')) <> \'sin_crm\'
				LEFT JOIN (
					SELECT t1.contacto_id, t1.telefono
					FROM telefonos_contacto t1
					INNER JOIN (
						SELECT contacto_id, MAX(id) AS first_id
						FROM telefonos_contacto
						WHERE estado = \'activo\'
						GROUP BY contacto_id
					) tx ON tx.first_id = t1.id
				) tc ON tc.contacto_id = i.contacto_id';

			$sql = "SELECT
				i.id,
				i.contacto_id,
				i.origen,
				i.convertido,
				i.carrera,
				i.creado_por,
				i.modalidad,
				i.provincia,
				i.ciudad,
				i.estado_id,
				i.created_at,
				COALESCE(pe.nombre, 'Sin etapa') AS etapa,
				COALESCE(c.nombre, 'Sin nombre') AS nombre,
				COALESCE(c.apellido, '') AS apellido,
				COALESCE(c.cedula, '') AS cedula,
				COALESCE(c.email, '') AS email,
				CASE WHEN COALESCE(i.convertido, 0) = 1 THEN 'Estudiante' ELSE 'Cliente potencial' END AS estado_cliente,
				COALESCE(tc.telefono, '') AS celular
			{$baseJoins}
			{$whereSql}
			{$orderBy}
			LIMIT {$perPage} OFFSET {$offset}";

			$stmt = $db->prepare($sql);
			foreach ($params as $key => $value) {
				$stmt->bindValue($key, $value, PDO::PARAM_STR);
			}
			$stmt->execute();
			$rows = $stmt->fetchAll() ?: [];

			$countStmt = $db->prepare("SELECT COUNT(*) {$baseJoins} {$whereSql}");
			foreach ($params as $key => $value) {
				$countStmt->bindValue($key, $value, PDO::PARAM_STR);
			}
			$countStmt->execute();
			$total = (int) $countStmt->fetchColumn();
			$pages = max(1, (int) ceil($total / $perPage));

			// Renderizar las filas con la misma plantilla que usa la vista inicial.
			$html = '';
			try {
				$partialFile = dirname(__DIR__) . '/views/crm/partials/prospectos_rows.php';
				if (is_file($partialFile)) {
					ob_start();
					$prospectosLocales = $rows;
					include $partialFile;
					$html = ob_get_clean();
				}
			} catch (Throwable $partialError) {
				$html = '';
			}

			echo json_encode([
				'success' => true,
				'html' => $html,
				'prospectos' => $rows,
				'total' => $total,
				'page' => min($page, $pages),
				'pages' => $pages,
				'per_page' => $perPage,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		exit;
	}

	private function fetchDistinctProspectFilterOptions(): array
	{
		$options = [
			'origins' => [],
			'stages' => [],
			'careers' => [],
			'createdBy' => [],
		];

		try {
			$db = Database::getInstance()->connection();

			$rows = $db->query("SELECT DISTINCT i.origen AS value
				FROM interesados i
				WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0 AND i.deleted_at IS NULL
				  AND COALESCE(TRIM(i.origen), '') <> ''
				ORDER BY value ASC")->fetchAll() ?: [];
			foreach ($rows as $row) {
				$value = trim((string) ($row['value'] ?? ''));
				if ($value !== '') {
					$options['origins'][] = $value;
				}
			}

			$rows = $db->query("SELECT DISTINCT COALESCE(pe.nombre, 'Sin etapa') AS value
				FROM interesados i
				LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
					AND LOWER(REPLACE(TRIM(COALESCE(pe.categoria, '')), ' ', '_')) <> 'sin_crm'
				WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0 AND i.deleted_at IS NULL
				ORDER BY value ASC")->fetchAll() ?: [];
			foreach ($rows as $row) {
				$value = trim((string) ($row['value'] ?? ''));
				if ($value !== '') {
					$options['stages'][] = $value;
				}
			}

			$rows = $db->query("SELECT DISTINCT i.carrera AS value
				FROM interesados i
				WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0 AND i.deleted_at IS NULL
				  AND COALESCE(TRIM(i.carrera), '') <> ''
				ORDER BY value ASC")->fetchAll() ?: [];
			foreach ($rows as $row) {
				$value = trim((string) ($row['value'] ?? ''));
				if ($value !== '') {
					$options['careers'][] = $value;
				}
			}

			$rows = $db->query("SELECT i.creado_por
				FROM interesados i
				WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0 AND i.deleted_at IS NULL
				  AND COALESCE(TRIM(i.creado_por), '') <> ''")->fetchAll() ?: [];
			$creators = [];
			foreach ($rows as $row) {
				$parts = preg_split('/\s*,\s*/', trim((string) ($row['creado_por'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
				foreach ($parts as $part) {
					$part = trim($part);
					if ($part !== '') {
						$creators[mb_strtolower($part, 'UTF-8')] = $part;
					}
				}
			}
			$options['createdBy'] = array_values($creators);
			natsort($options['createdBy']);
		} catch (Throwable $e) {
			return $options;
		}

		return $options;
	}

	private function fetchLocalProspects(int $perPage = 25, int $offset = 0, string $sortDirection = 'desc'): array
	{
		$perPage = max(10, min(50000, $perPage));
		$offset  = max(0, $offset);
		$sortDirection = $this->normalizeProspectSort($sortDirection);
		try {
			$db = Database::getInstance()->connection();
			$orderBy = $sortDirection === 'asc'
				? "ORDER BY COALESCE(c.nombre, 'Sin nombre') ASC, COALESCE(c.apellido, '') ASC, i.id ASC"
				: "ORDER BY COALESCE(c.nombre, 'Sin nombre') DESC, COALESCE(c.apellido, '') DESC, i.id DESC";
			$sql = "SELECT
				i.id,
				i.contacto_id,
				i.origen,
				i.convertido,
				i.carrera,
				i.creado_por,
				i.modalidad,
				i.provincia,
				i.ciudad,
				i.estado_id,
				i.created_at,
				COALESCE(pe.nombre, 'Sin etapa') AS etapa,
				COALESCE(c.nombre, 'Sin nombre') AS nombre,
				COALESCE(c.apellido, '') AS apellido,
				COALESCE(c.cedula, '') AS cedula,
				COALESCE(c.email, '') AS email,
				CASE WHEN COALESCE(i.convertido, 0) = 1 THEN 'Estudiante' ELSE 'Cliente potencial' END AS estado_cliente,
				COALESCE(tc.telefono, '') AS celular
			FROM interesados i
			LEFT JOIN contactos c ON c.id = i.contacto_id
			LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
				AND LOWER(REPLACE(TRIM(COALESCE(pe.categoria, '')), ' ', '_')) <> 'sin_crm'
			LEFT JOIN (
				SELECT t1.contacto_id, t1.telefono
				FROM telefonos_contacto t1
				INNER JOIN (
					SELECT contacto_id, MAX(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) tx ON tx.first_id = t1.id
			) tc ON tc.contacto_id = i.contacto_id
			WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0 AND i.deleted_at IS NULL
			{$orderBy}
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
			return (int) $db->query("SELECT COUNT(*) FROM interesados WHERE estado = 'activo' AND COALESCE(convertido, 0) = 0 AND deleted_at IS NULL")->fetchColumn();
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

		$stmt = $db->query("SELECT id, nombre FROM pipeline_estados WHERE estado = 'activo' AND LOWER(REPLACE(TRIM(COALESCE(categoria, '')), ' ', '_')) <> 'sin_crm' ORDER BY orden ASC, id ASC");
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

	private function upsertInteresado(PDO $db, int $contactId, ?int $estadoId, string $origen, string $creadoPor = '', string $carrera = '', string $modalidad = '', string $provincia = '', string $ciudad = ''): void
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
					creado_por = CASE
						WHEN COALESCE(TRIM(creado_por), "") = "" THEN :creado_por
						ELSE creado_por
					END,
					carrera = :carrera,
					modalidad = :modalidad,
					provincia = :provincia,
					ciudad = :ciudad,
					convertido = 0,
					estado = "activo",
					updated_at = NOW()
				WHERE id = :id
				LIMIT 1');
			$update->execute([
				'estado_id' => $estadoId,
				'origen' => mb_substr($origen, 0, 100),
				'creado_por' => $creadoPor !== '' ? mb_substr($creadoPor, 0, 255) : null,
				'carrera' => $carrera !== '' ? mb_substr($carrera, 0, 180) : null,
				'modalidad' => $modalidad !== '' ? mb_substr($modalidad, 0, 80) : null,
				'provincia' => $provincia !== '' ? mb_substr($provincia, 0, 120) : null,
				'ciudad' => $ciudad !== '' ? mb_substr($ciudad, 0, 120) : null,
				'id' => $existingId,
			]);
			return;
		}

		$insert = $db->prepare('INSERT INTO interesados (contacto_id, estado_id, origen, creado_por, carrera, modalidad, provincia, ciudad, convertido, estado, created_at, updated_at)
			VALUES (:contacto_id, :estado_id, :origen, :creado_por, :carrera, :modalidad, :provincia, :ciudad, 0, "activo", NOW(), NOW())');
		$insert->execute([
			'contacto_id' => $contactId,
			'estado_id' => $estadoId,
			'origen' => mb_substr($origen, 0, 100),
			'creado_por' => $creadoPor !== '' ? mb_substr($creadoPor, 0, 255) : null,
			'carrera' => $carrera !== '' ? mb_substr($carrera, 0, 180) : null,
			'modalidad' => $modalidad !== '' ? mb_substr($modalidad, 0, 80) : null,
			'provincia' => $provincia !== '' ? mb_substr($provincia, 0, 120) : null,
			'ciudad' => $ciudad !== '' ? mb_substr($ciudad, 0, 120) : null,
		]);
	}

	private function markProspectAsConverted(PDO $db, int $contactId): void
	{
		if ($contactId <= 0) {
			return;
		}

		$stmt = $db->prepare('UPDATE interesados SET convertido = 1, estado = "inactivo", updated_at = NOW() WHERE contacto_id = :contacto_id');
		$stmt->execute(['contacto_id' => $contactId]);
	}

	private function migrateProspectCrmDataToStudent(PDO $db, int $contactId, int $studentKey): void
	{
		if ($contactId <= 0 || $studentKey <= 0 || $contactId === $studentKey) {
			return;
		}

		$sourcePipeline = $db->prepare('SELECT estado_id FROM crm_student_pipeline WHERE student_id = :id LIMIT 1');
		$sourcePipeline->execute([':id' => $contactId]);
		$sourceEstadoId = (int) ($sourcePipeline->fetchColumn() ?: 0);

		$targetPipeline = $db->prepare('SELECT estado_id FROM crm_student_pipeline WHERE student_id = :id LIMIT 1');
		$targetPipeline->execute([':id' => $studentKey]);
		$targetEstadoId = (int) ($targetPipeline->fetchColumn() ?: 0);

		if ($sourceEstadoId > 0 && $targetEstadoId <= 0) {
			$upsert = $db->prepare('INSERT INTO crm_student_pipeline (student_id, estado_id, updated_by, updated_at)
				VALUES (:student_id, :estado_id, :updated_by, NOW())
				ON DUPLICATE KEY UPDATE estado_id = VALUES(estado_id), updated_by = VALUES(updated_by), updated_at = NOW()');
			$upsert->execute([
				':student_id' => $studentKey,
				':estado_id' => $sourceEstadoId,
				':updated_by' => $this->currentUserId() ?: null,
			]);
		}

		$db->prepare('UPDATE crm_student_notes SET student_id = :target_id WHERE student_id = :source_id')
			->execute([':target_id' => $studentKey, ':source_id' => $contactId]);
		$db->prepare('UPDATE crm_student_tasks SET student_id = :target_id WHERE student_id = :source_id')
			->execute([':target_id' => $studentKey, ':source_id' => $contactId]);

		$sourceExtrasStmt = $db->prepare('SELECT extra_emails, extra_phones FROM crm_student_contact_extras WHERE student_id = :id LIMIT 1');
		$sourceExtrasStmt->execute([':id' => $contactId]);
		$sourceExtras = $sourceExtrasStmt->fetch() ?: null;
		if ($sourceExtras) {
			$targetExtrasStmt = $db->prepare('SELECT extra_emails, extra_phones FROM crm_student_contact_extras WHERE student_id = :id LIMIT 1');
			$targetExtrasStmt->execute([':id' => $studentKey]);
			$targetExtras = $targetExtrasStmt->fetch() ?: ['extra_emails' => '', 'extra_phones' => ''];

			$mergeCsv = static function (string $a, string $b): string {
				$items = preg_split('/\s*,\s*/', trim($a . ',' . $b), -1, PREG_SPLIT_NO_EMPTY) ?: [];
				$items = array_values(array_unique(array_map('trim', $items)));
				return implode(', ', array_filter($items, static fn($x) => $x !== ''));
			};

			$mergedEmails = $mergeCsv((string) ($targetExtras['extra_emails'] ?? ''), (string) ($sourceExtras['extra_emails'] ?? ''));
			$mergedPhones = $mergeCsv((string) ($targetExtras['extra_phones'] ?? ''), (string) ($sourceExtras['extra_phones'] ?? ''));

			$db->prepare('INSERT INTO crm_student_contact_extras (student_id, extra_emails, extra_phones, updated_by, updated_at)
				VALUES (:student_id, :extra_emails, :extra_phones, :updated_by, NOW())
				ON DUPLICATE KEY UPDATE extra_emails = VALUES(extra_emails), extra_phones = VALUES(extra_phones), updated_by = VALUES(updated_by), updated_at = NOW()')
				->execute([
					':student_id' => $studentKey,
					':extra_emails' => $mergedEmails,
					':extra_phones' => $mergedPhones,
					':updated_by' => $this->currentUserId() ?: null,
				]);

			$db->prepare('DELETE FROM crm_student_contact_extras WHERE student_id = :id')->execute([':id' => $contactId]);
		}

		$db->prepare('DELETE FROM crm_student_pipeline WHERE student_id = :id')->execute([':id' => $contactId]);
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

		if (mb_strtolower(trim($type), 'UTF-8') === 'principal') {
			$deactivate = $db->prepare('UPDATE telefonos_contacto
				SET estado = "inactivo",
					updated_at = NOW()
				WHERE contacto_id = :contacto_id
				  AND estado = "activo"
				  AND tipo = "principal"
				  AND telefono <> :telefono');
			$deactivate->execute([
				'contacto_id' => $contactId,
				'telefono' => mb_substr($phone, 0, 40),
			]);
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

	private function findActiveContactIdByPhone(PDO $db, string $phone): ?int
	{
		$phone = trim($phone);
		if ($phone === '') {
			return null;
		}

		$stmt = $db->prepare('SELECT contacto_id
			FROM telefonos_contacto
			WHERE telefono = :telefono AND estado = "activo"
			ORDER BY id ASC
			LIMIT 1');
		$stmt->execute([':telefono' => $phone]);
		$contactId = (int) ($stmt->fetchColumn() ?: 0);
		return $contactId > 0 ? $contactId : null;
	}

	private function attachPipelineData(array $rows): array
	{
		if (empty($rows)) {
			return [];
		}

		try {
			$db = Database::getInstance()->connection();
			$activeEstados = $this->fetchAllPipelineStates($db, 'id, nombre');
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
						AND LOWER(REPLACE(TRIM(COALESCE(pe.categoria, '')), ' ', '_')) <> 'sin_crm'
					WHERE p.student_id IN ($placeholders)";
			$stmt = $db->prepare($sql);
			$stmt->execute($ids);
			$pipelineRows = $stmt->fetchAll() ?: [];

			$pipelineMap = [];
			foreach ($pipelineRows as $pipelineRow) {
				$pipelineMap[(int) ($pipelineRow['student_id'] ?? 0)] = [
					'estado_id' => (int) ($pipelineRow['estado_id'] ?? 0),
					'pipeline_nombre' => (string) ($pipelineRow['pipeline_nombre'] ?? 'Sin asignar'),
					'pipeline_nombres' => [],
				];
			}

			$sqlMulti = "SELECT pm.student_id, pm.estado_id, COALESCE(pe.nombre, '') AS pipeline_nombre
				FROM crm_student_pipeline_multi pm
				LEFT JOIN pipeline_estados pe ON pe.id = pm.estado_id
					AND LOWER(REPLACE(TRIM(COALESCE(pe.categoria, '')), ' ', '_')) <> 'sin_crm'
				WHERE pm.student_id IN ($placeholders)
				ORDER BY pm.student_id ASC, pe.orden ASC, pe.id ASC";
			$stmtMulti = $db->prepare($sqlMulti);
			$stmtMulti->execute($ids);
			$multiRows = $stmtMulti->fetchAll() ?: [];
			foreach ($multiRows as $multiRow) {
				$studentId = (int) ($multiRow['student_id'] ?? 0);
				$estadoId = (int) ($multiRow['estado_id'] ?? 0);
				$estadoNombre = trim((string) ($multiRow['pipeline_nombre'] ?? ''));
				if ($studentId <= 0) {
					continue;
				}
				if (!isset($pipelineMap[$studentId])) {
					$pipelineMap[$studentId] = [
						'estado_id' => $estadoId,
						'pipeline_nombre' => $estadoNombre !== '' ? $estadoNombre : 'Sin asignar',
						'pipeline_nombres' => [],
					];
				}
				if ($estadoNombre !== '') {
					$pipelineMap[$studentId]['pipeline_nombres'][] = $estadoNombre;
				}
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
							'pipeline_nombres' => [(string) ($stateNames[$randomEstadoId] ?? 'Sin asignar')],
						];
					}
				}
			}

			foreach ($rows as &$row) {
				$studentId = (int) ($row['id'] ?? 0);
				$allNames = array_values(array_unique(array_filter(array_map('trim', (array) ($pipelineMap[$studentId]['pipeline_nombres'] ?? [])))));
				if (empty($allNames)) {
					$fallbackName = (string) ($pipelineMap[$studentId]['pipeline_nombre'] ?? 'Sin asignar');
					if ($fallbackName !== '') {
						$allNames[] = $fallbackName;
					}
				}
				$row['pipeline_estado_id'] = (int) ($pipelineMap[$studentId]['estado_id'] ?? 0);
				$row['pipeline_nombre'] = !empty($allNames) ? implode(', ', $allNames) : 'Sin asignar';
				$row['pipeline_nombres'] = $allNames;
			}
			unset($row);
		} catch (Throwable $e) {
			foreach ($rows as &$row) {
				$row['pipeline_estado_id'] = 0;
				$row['pipeline_nombre'] = 'Sin asignar';
				$row['pipeline_nombres'] = [];
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
			$resolvedContactoId = 0;
			$resolvedSourceTable = null;
			if ($remote instanceof PDO) {
				$sourceTable = $this->resolveSuperarseStudentTable($remote);
				$resolvedSourceTable = $sourceTable;
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
							e.contacto_id,
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
								SELECT contacto_id, MAX(id) AS first_id
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

					if ($student && $sourceTable === 'estudiantes') {
						$resolvedContactoId = (int) ($student['contacto_id'] ?? 0);
					}
				}
			}

			if (!$student) {
				$db = Database::getInstance()->connection();
				$sql = "SELECT
						e.id,
						e.contacto_id,
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
							SELECT contacto_id, MAX(id) AS first_id
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
				if ($student) {
					$resolvedContactoId = (int) ($student['contacto_id'] ?? 0);
				}
			}

			if (!$student) {
				throw new RuntimeException('Estudiante no encontrado');
			}

			$db = Database::getInstance()->connection();
			if ($resolvedContactoId <= 0 && $resolvedSourceTable === 'users') {
				$historyStmt = $db->prepare('SELECT contacto_id
					FROM crm_student_academic_history
					WHERE source_user_id = :source_user_id
					ORDER BY last_seen_at DESC, id DESC
					LIMIT 1');
				$historyStmt->execute([':source_user_id' => $studentId]);
				$resolvedContactoId = (int) ($historyStmt->fetchColumn() ?: 0);
			}

			$extrasStmt = $db->prepare('SELECT extra_emails, extra_phones FROM crm_student_contact_extras WHERE student_id = :student_id LIMIT 1');
			$extrasStmt->execute([':student_id' => $studentId]);
			$extras = $extrasStmt->fetch() ?: ['extra_emails' => '', 'extra_phones' => ''];

			$extraEmailsCsv = (string) ($extras['extra_emails'] ?? '');
			if ($resolvedContactoId > 0) {
				try {
					$extraEmailRowsStmt = $db->prepare('SELECT correo FROM correos_contacto
						WHERE contacto_id = :contacto_id
						  AND estado = "activo"
						  AND tipo = "extra"
						ORDER BY id ASC');
					$extraEmailRowsStmt->execute([':contacto_id' => $resolvedContactoId]);
					$extraEmailRows = $extraEmailRowsStmt->fetchAll() ?: [];
					$extraEmailValues = [];
					foreach ($extraEmailRows as $extraEmailRow) {
						$normalized = $this->normalizeEmailValue((string) ($extraEmailRow['correo'] ?? ''));
						if ($normalized !== '') {
							$extraEmailValues[$normalized] = $normalized;
						}
					}
					if (!empty($extraEmailValues)) {
						$extraEmailsCsv = implode(', ', array_values($extraEmailValues));
					}
				} catch (Throwable $ignore) {
					// Mantener fallback de extras por student_id.
				}
			}

			echo json_encode([
				'success' => true,
				'student' => [
					'id' => (int) ($student['id'] ?? 0),
					'contacto_id' => $resolvedContactoId > 0 ? $resolvedContactoId : null,
					'codigo_estudiante' => (string) ($student['codigo_estudiante'] ?? ''),
					'nombre_completo' => (string) ($student['nombre_completo'] ?? ''),
					'email' => (string) ($student['email'] ?? ''),
					'telefono' => (string) ($student['telefono'] ?? ''),
					'celular' => (string) ($student['celular'] ?? ''),
					'extra_emails' => $extraEmailsCsv,
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
		$contactoIdFromPayload = max(0, (int) ($_POST['contacto_id'] ?? 0));
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
			$db = Database::getInstance()->connection();
			$beforeEmail = '';
			$resolvedContactoId = $contactoIdFromPayload;
			$contactStmtBefore = $db->prepare('SELECT c.email
				FROM estudiantes e
				LEFT JOIN contactos c ON c.id = e.contacto_id
				WHERE e.id = :id
				LIMIT 1');
			$contactStmtBefore->execute([':id' => $studentId]);
			$beforeEmail = (string) ($contactStmtBefore->fetchColumn() ?: '');

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
					$historyStmt = $db->prepare('SELECT contacto_id
						FROM crm_student_academic_history
						WHERE source_user_id = :source_user_id
						ORDER BY last_seen_at DESC, id DESC
						LIMIT 1');
					$historyStmt->execute([':source_user_id' => $studentId]);
					$historyContactoId = (int) ($historyStmt->fetchColumn() ?: 0);
					if ($historyContactoId > 0) {
						$resolvedContactoId = $historyContactoId;
					}
					if ($resolvedContactoId <= 0) {
						$resolvedContactoId = $this->resolveOrCreateLocalContactFromRemoteUser($db, $remote, $studentId, $email);
					}
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
						$resolvedContactoId = $contactoId;
						$updated = true;
					}
				}
			}

			if (!$updated) {
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
				$resolvedContactoId = $contactoId;
			}

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
				':updated_by' => $this->currentUserId(),
			]);

			if ($resolvedContactoId <= 0) {
				$fallbackContactStmt = $db->prepare('SELECT contacto_id FROM estudiantes WHERE id = :id LIMIT 1');
				$fallbackContactStmt->execute([':id' => $studentId]);
				$resolvedContactoId = (int) ($fallbackContactStmt->fetchColumn() ?: 0);
			}

			if ($resolvedContactoId > 0) {
				$emailPrincipal = $this->normalizeEmailValue($email);
				if ($emailPrincipal !== '') {
					$this->upsertContactEmail($db, $resolvedContactoId, $emailPrincipal, 'personal');
				}

				$extraEmailList = $this->parseCsvEmailValues($extraEmails);
				if (empty($extraEmailList)) {
					$deactivateExtra = $db->prepare('UPDATE correos_contacto
						SET estado = "inactivo", updated_at = NOW()
						WHERE contacto_id = :contacto_id
						  AND tipo = "extra"
						  AND estado = "activo"');
					$deactivateExtra->execute([':contacto_id' => $resolvedContactoId]);
				} else {
					$emailPlaceholders = implode(',', array_fill(0, count($extraEmailList), '?'));
					$deactivateExtra = $db->prepare("UPDATE correos_contacto
						SET estado = 'inactivo', updated_at = NOW()
						WHERE contacto_id = ?
						  AND tipo = 'extra'
						  AND estado = 'activo'
						  AND LOWER(TRIM(correo)) NOT IN ({$emailPlaceholders})");
					$deactivateParams = array_merge([$resolvedContactoId], $extraEmailList);
					$deactivateExtra->execute($deactivateParams);

					foreach ($extraEmailList as $extraEmail) {
						$this->upsertContactEmail($db, $resolvedContactoId, $extraEmail, 'extra');
					}
				}
			}

			$historyNote = sprintf(
				'Actualizó contacto. Email: %s -> %s. Teléfono: %s. Celular: %s. Correos extra: %s. Teléfonos extra: %s.',
				$beforeEmail !== '' ? $beforeEmail : '-',
				$email !== '' ? $email : '-',
				$telefono !== '' ? $telefono : '-',
				$celular !== '' ? $celular : '-',
				$extraEmails !== '' ? $extraEmails : '-',
				$extraPhones !== '' ? $extraPhones : '-'
			);
			$this->crmHistoryNote($studentId, 'contact_update', $historyNote);

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

	private function resolveOrCreateLocalContactFromRemoteUser(PDO $db, PDO $remote, int $sourceUserId, string $preferredEmail = ''): int
	{
		if ($sourceUserId <= 0) {
			return 0;
		}

		$stmt = $remote->prepare('SELECT
			u.id,
			u.numero_identificacion,
			u.codigo_matricula,
			u.primer_nombre,
			u.segundo_nombre,
			u.primer_apellido,
			u.segundo_apellido,
			u.correo_electronico,
			u.programa,
			u.nivel,
			u.periodo,
			u.estado
		FROM users u
		WHERE u.id = :id
		LIMIT 1');
		$stmt->execute([':id' => $sourceUserId]);
		$row = $stmt->fetch();
		if (!$row) {
			return 0;
		}

		$identity = $this->normalizeIdentityValue((string) ($row['numero_identificacion'] ?? ''));
		$contactId = $this->findPrimaryContactIdForMerge($db, $identity);

		$emailCandidates = [];
		$preferredEmailNormalized = $this->normalizeEmailValue($preferredEmail);
		if ($preferredEmailNormalized !== '') {
			$emailCandidates[] = $preferredEmailNormalized;
		}
		$remoteEmailNormalized = $this->normalizeEmailValue((string) ($row['correo_electronico'] ?? ''));
		if ($remoteEmailNormalized !== '') {
			$emailCandidates[] = $remoteEmailNormalized;
		}

		if ($contactId === null) {
			foreach ($emailCandidates as $candidateEmail) {
				$byEmail = $this->resolveContactByEmailFallback($db, $candidateEmail);
				if ($byEmail !== null) {
					$contactId = $byEmail;
					break;
				}
			}
		}

		$firstName = trim((string) ($row['primer_nombre'] ?? '') . ' ' . (string) ($row['segundo_nombre'] ?? ''));
		$lastName = trim((string) ($row['primer_apellido'] ?? '') . ' ' . (string) ($row['segundo_apellido'] ?? ''));
		if ($firstName === '') {
			$firstName = 'Sin nombre';
		}

		if ($contactId === null) {
			$emailToStore = '';
			foreach ($emailCandidates as $candidateEmail) {
				if ($this->canUseEmailAsPrimaryContact($db, $candidateEmail, null)) {
					$emailToStore = $candidateEmail;
					break;
				}
			}

			$insert = $db->prepare('INSERT INTO contactos (nombre, apellido, cedula, email, tipo, estado, created_at, updated_at)
				VALUES (:nombre, :apellido, :cedula, :email, "estudiante", "activo", NOW(), NOW())');
			$insert->execute([
				':nombre' => mb_substr($firstName, 0, 150),
				':apellido' => mb_substr($lastName, 0, 150),
				':cedula' => $identity !== '' ? mb_substr($identity, 0, 20) : null,
				':email' => $emailToStore !== '' ? $emailToStore : null,
			]);
			$contactId = (int) $db->lastInsertId();
		} else {
			$update = $db->prepare('UPDATE contactos
				SET nombre = :nombre,
					apellido = :apellido,
					cedula = COALESCE(:cedula, cedula),
					estado = "activo",
					updated_at = NOW()
				WHERE id = :id
				LIMIT 1');
			$update->execute([
				':nombre' => mb_substr($firstName, 0, 150),
				':apellido' => mb_substr($lastName, 0, 150),
				':cedula' => $identity !== '' ? mb_substr($identity, 0, 20) : null,
				':id' => $contactId,
			]);
		}

		if ($contactId <= 0) {
			return 0;
		}

		$estadoAcademico = strtolower(trim((string) ($row['estado'] ?? 'activo')));
		$codigo = trim((string) ($row['codigo_matricula'] ?? ''));
		$this->ensureStudentFromContact($db, $contactId, $codigo, $estadoAcademico);
		$this->upsertAcademicHistory($db, $contactId, $row);

		foreach ($emailCandidates as $candidateEmail) {
			$this->upsertContactEmail($db, $contactId, $candidateEmail, 'personal');
		}

		return $contactId;
	}

	private function parseCsvEmailValues(string $value): array
	{
		$items = preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$result = [];
		foreach ($items as $item) {
			$email = $this->normalizeEmailValue((string) $item);
			if ($email === '') {
				continue;
			}
			$result[$email] = $email;
		}

		return array_values($result);
	}

	public function getProspectDetail(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$contactoId = max(0, (int) ($_GET['id'] ?? $_GET['contact_id'] ?? 0));
		if ($contactoId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();

			$sql = "SELECT
				c.id AS contacto_id,
				c.nombre,
				c.apellido,
				c.cedula,
				c.email,
				COALESCE(tp.telefono, '') AS celular,
				COALESCE(i.origen, '') AS propietario,
				COALESCE(i.creado_por, '') AS creado_por,
				COALESCE(i.asesor, '') AS asesor,
				COALESCE(i.carrera, '') AS carrera,
				COALESCE(i.modalidad, '') AS modalidad,
				COALESCE(i.provincia, '') AS provincia,
				COALESCE(i.ciudad, '') AS ciudad,
				COALESCE(i.estado_id, 0) AS estado_id,
				COALESCE(pe.nombre, 'Sin etapa') AS etapa
			FROM contactos c
			INNER JOIN interesados i ON i.contacto_id = c.id
			LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
			LEFT JOIN (
				SELECT t1.contacto_id, t1.telefono
				FROM telefonos_contacto t1
				INNER JOIN (
					SELECT contacto_id, MAX(id) AS first_id
					FROM telefonos_contacto
					WHERE estado = 'activo'
					GROUP BY contacto_id
				) tx ON tx.first_id = t1.id
			) tp ON tp.contacto_id = c.id
			WHERE c.id = :id AND c.deleted_at IS NULL
			LIMIT 1";

			$stmt = $db->prepare($sql);
			$stmt->execute([':id' => $contactoId]);
			$prospect = $stmt->fetch();
			if (!$prospect) {
				throw new RuntimeException('Cliente potencial no encontrado');
			}

			$pipelineEstados = $this->fetchVisiblePipelineStates($db, 'id, nombre');
			$prospectAdvisorOptions = $this->fetchProspectSelectorOptions($db, 'crm_prospect_asesores');
			$prospectCreatorOptions = $this->fetchProspectSelectorOptions($db, 'crm_prospect_creadores');

			echo json_encode([
				'success' => true,
				'prospect' => $prospect,
				'estados' => $pipelineEstados,
				'asesores' => $prospectAdvisorOptions,
				'creadores' => $prospectCreatorOptions,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}

		exit;
	}

	public function updateProspect(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$contactoId = max(0, (int) ($_POST['contacto_id'] ?? 0));
		$estadoRaw = trim((string) ($_POST['estado_id'] ?? ''));
		$hasEstadoId = $estadoRaw !== '';
		$estadoId = max(0, (int) $estadoRaw);
		$nombres = trim((string) ($_POST['nombres'] ?? ''));
		$apellidos = trim((string) ($_POST['apellidos'] ?? ''));
		$identificacion = $this->normalizeIdentityValue((string) ($_POST['identificacion'] ?? ''));
		$correoPersonal = $this->normalizeEmailValue((string) ($_POST['correo_personal'] ?? ''));
		$celularRaw = trim((string) ($_POST['celular'] ?? ''));
		$celular = $this->normalizeProspectPhoneValue($celularRaw);
		$propietario = trim((string) ($_POST['propietario'] ?? ''));
		// Mapeo automático de Creado por según Asesor (propietario)
		$creadoPor = $this->mapAsesorToCreador($propietario);
		$carrera = trim((string) ($_POST['carrera'] ?? ''));
		$modalidad = trim((string) ($_POST['modalidad'] ?? ''));
		$provincia = trim((string) ($_POST['provincia'] ?? ''));
		$ciudad = trim((string) ($_POST['ciudad'] ?? ''));

		if ($contactoId <= 0 || $nombres === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
			exit;
		}

		if ($celularRaw !== '' && $celular === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'El celular debe tener el formato +593987654321.']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$db->beginTransaction();

			if ($celular !== '') {
				$phoneOwnerContactId = $this->findActiveContactIdByPhone($db, $celular);
				if ($phoneOwnerContactId !== null && $phoneOwnerContactId !== $contactoId) {
					$conflictSuffix = $this->buildConflictContactSuffix($db, $phoneOwnerContactId);
					throw new RuntimeException('El número de celular ya existe en otro cliente potencial' . $conflictSuffix . '.');
				}
			}

			$contactStmt = $db->prepare('SELECT id FROM contactos WHERE id = :id LIMIT 1');
			$contactStmt->execute([':id' => $contactoId]);
			if (!$contactStmt->fetch()) {
				throw new RuntimeException('Cliente potencial no encontrado');
			}

			$conflictContactId = null;
			if ($correoPersonal !== '' && !$this->canUseEmailAsPrimaryContact($db, $correoPersonal, $contactoId, $conflictContactId)) {
				$conflictSuffix = $this->buildConflictContactSuffix($db, $conflictContactId);
				$connectionLabel = $this->getDbConnectionLabel($db);
				$connectionSuffix = $connectionLabel !== '' ? (' [DB: ' . $connectionLabel . ']') : '';
				throw new RuntimeException('El correo ya está registrado en otro cliente potencial' . $conflictSuffix . $connectionSuffix . '.');
			}

			$contactUpdate = $db->prepare('UPDATE contactos
				SET nombre = :nombre,
					apellido = :apellido,
					cedula = :cedula,
					email = :email,
					updated_at = NOW()
				WHERE id = :id
				LIMIT 1');
			$contactUpdate->execute([
				':nombre' => mb_substr($nombres, 0, 150),
				':apellido' => mb_substr($apellidos, 0, 150),
				':cedula' => $identificacion !== '' ? mb_substr($identificacion, 0, 20) : null,
				':email' => $correoPersonal !== '' ? $correoPersonal : null,
				':id' => $contactoId,
			]);

			if ($celular !== '') {
				$this->upsertContactPhone($db, $contactoId, $celular, 'principal');
			}

			if ($correoPersonal !== '') {
				$this->upsertContactEmail($db, $contactoId, $correoPersonal, 'personal');
			}

			if ($hasEstadoId) {
				$interesadoUpdate = $db->prepare('UPDATE interesados
					SET estado_id = :estado_id,
						origen = :origen,
						creado_por = :creado_por,
						carrera = :carrera,
						modalidad = :modalidad,
						provincia = :provincia,
						ciudad = :ciudad,
						updated_at = NOW()
					WHERE contacto_id = :contacto_id
					LIMIT 1');
				$interesadoUpdate->execute([
					':estado_id' => $estadoId > 0 ? $estadoId : null,
					':origen' => $propietario !== '' ? mb_substr($propietario, 0, 100) : 'crm_manual',
					':creado_por' => $creadoPor !== '' ? mb_substr($creadoPor, 0, 255) : null,
					':carrera' => $carrera !== '' ? mb_substr($carrera, 0, 180) : null,
					':modalidad' => $modalidad !== '' ? mb_substr($modalidad, 0, 80) : null,
					':provincia' => $provincia !== '' ? mb_substr($provincia, 0, 120) : null,
					':ciudad' => $ciudad !== '' ? mb_substr($ciudad, 0, 120) : null,
					':contacto_id' => $contactoId,
				]);
			} else {
				$interesadoUpdate = $db->prepare('UPDATE interesados
					SET origen = :origen,
						creado_por = :creado_por,
						carrera = :carrera,
						modalidad = :modalidad,
						provincia = :provincia,
						ciudad = :ciudad,
						updated_at = NOW()
					WHERE contacto_id = :contacto_id
					LIMIT 1');
				$interesadoUpdate->execute([
					':origen' => $propietario !== '' ? mb_substr($propietario, 0, 100) : 'crm_manual',
					':creado_por' => $creadoPor !== '' ? mb_substr($creadoPor, 0, 255) : null,
					':carrera' => $carrera !== '' ? mb_substr($carrera, 0, 180) : null,
					':modalidad' => $modalidad !== '' ? mb_substr($modalidad, 0, 80) : null,
					':provincia' => $provincia !== '' ? mb_substr($provincia, 0, 120) : null,
					':ciudad' => $ciudad !== '' ? mb_substr($ciudad, 0, 120) : null,
					':contacto_id' => $contactoId,
				]);
			}

			if ($hasEstadoId && $estadoId > 0) {
				$pipelineSql = "INSERT INTO crm_student_pipeline (student_id, estado_id, updated_by, updated_at)
					VALUES (:student_id, :estado_id, :updated_by, NOW())
					ON DUPLICATE KEY UPDATE
					estado_id = VALUES(estado_id),
					updated_by = VALUES(updated_by),
					updated_at = NOW()";
				$pipelineStmt = $db->prepare($pipelineSql);
				$pipelineStmt->execute([
					':student_id' => $contactoId,
					':estado_id' => $estadoId,
					':updated_by' => $this->currentUserId(),
				]);
			}

			$this->crmHistoryNote(
				$contactoId,
				'prospect_update',
				sprintf(
					'Actualizó cliente potencial. Nombre: %s %s. Identificación: %s. Email: %s. Celular: %s. Propietario: %s. Creado por: %s. Carrera: %s. Modalidad: %s. Provincia: %s. Ciudad: %s.',
					$nombres,
					$apellidos,
					$identificacion !== '' ? $identificacion : '-',
					$correoPersonal !== '' ? $correoPersonal : '-',
					$celular !== '' ? $celular : '-',
					$propietario !== '' ? $propietario : '-',
					$creadoPor !== '' ? $creadoPor : '-',
					$carrera !== '' ? $carrera : '-',
					$modalidad !== '' ? $modalidad : '-',
					$provincia !== '' ? $provincia : '-',
					$ciudad !== '' ? $ciudad : '-'
				)
			);

			$db->commit();

			echo json_encode([
				'success' => true,
				'message' => 'Cliente potencial actualizado correctamente.',
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}

		exit;
	}

	public function checkProspectPhone(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$celularRaw = trim((string) ($_GET['celular'] ?? ''));
		$celular = $this->normalizeProspectPhoneValue($celularRaw);

		if ($celularRaw !== '' && $celular === '') {
			http_response_code(400);
			echo json_encode([
				'success' => false,
				'error' => 'El celular debe tener el formato +593987654321.',
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}

		if ($celular === '') {
			echo json_encode([
				'success' => true,
				'exists' => false,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$ownerId = $this->findActiveContactIdByPhone($db, $celular);
			$ownerSuffix = $this->buildConflictContactSuffix($db, $ownerId);

			echo json_encode([
				'success' => true,
				'exists' => $ownerId !== null,
				'message' => $ownerId !== null ? ('El número de celular ya está registrado' . $ownerSuffix . '.') : '',
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'error' => 'No se pudo validar el celular.',
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
					COALESCE(c.cedula, '') AS cedula,
					c.email AS email,
					COALESCE(tp.telefono, '') AS telefono,
					COALESCE(tp.telefono, '') AS celular,
					'' AS carrera,
					'' AS nivel,
					'' AS sede,
					COALESCE(c.estado, 'activo') AS estado,
					c.created_at AS fecha_matricula,
					i.created_at AS prospect_created_at,
					COALESCE(i.origen, 'crm_manual') AS origen,
					COALESCE(i.carrera, '') AS carrera,
					COALESCE(i.modalidad, '') AS modalidad,
					COALESCE(i.provincia, '') AS provincia,
					COALESCE(i.ciudad, '') AS ciudad,
					COALESCE(i.creado_por, '') AS creado_por,
					COALESCE(i.estado_id, 0) AS interesado_estado_id,
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
						SELECT contacto_id, MAX(id) AS first_id
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
				$sourceTable = $remote ? $this->resolveSuperarseStudentTable($remote) : null;

				if ($remote !== null && $sourceTable !== null) {
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
					$student = $stmt->fetch() ?: null;
				} else {
					$student = null;
				}

				// Fallback a base de datos local si Superarse no está disponible
				if (!$student) {
					$stmtLocal = $db->prepare("SELECT
							e.id,
							COALESCE(e.codigo_estudiante, '') AS codigo_estudiante,
							COALESCE(c.nombre, 'Sin nombre') AS primer_nombre,
							'' AS segundo_nombre,
							COALESCE(c.apellido, '') AS primer_apellido,
							'' AS segundo_apellido,
							COALESCE(c.email, '') AS email,
							COALESCE(tp.telefono, '') AS telefono,
							COALESCE(tp.telefono, '') AS celular,
							COALESCE(ca.nombre, '') AS carrera,
							'' AS nivel,
							'' AS sede,
							e.estado,
							e.created_at AS fecha_matricula
						FROM estudiantes e
						LEFT JOIN contactos c ON c.id = e.contacto_id
						LEFT JOIN matriculas m ON m.estudiante_id = e.id
						LEFT JOIN carreras ca ON ca.id = m.carrera_id
						LEFT JOIN (
							SELECT t1.contacto_id, t1.telefono
							FROM telefonos_contacto t1
							INNER JOIN (
								SELECT contacto_id, MAX(id) AS first_id
								FROM telefonos_contacto
								WHERE estado = 'activo'
								GROUP BY contacto_id
							) tx ON tx.first_id = t1.id
						) tp ON tp.contacto_id = e.contacto_id
						WHERE e.id = :id
						LIMIT 1");
					$stmtLocal->bindValue(':id', $entityId, PDO::PARAM_INT);
					$stmtLocal->execute();
					$student = $stmtLocal->fetch() ?: null;
				}

				if (!$student) {
					throw new RuntimeException('Estudiante no encontrado');
				}

				$student['is_student'] = 1;
				$student['entity_type'] = 'student';
			}

			// Obtener estados del pipeline
			$currentPipelineMultiStmt = $db->prepare('SELECT estado_id FROM crm_student_pipeline_multi WHERE student_id = :student_id ORDER BY estado_id ASC');
			$currentPipelineMultiStmt->execute([':student_id' => $pipelineKey]);
			$currentPipelineMultiRows = $currentPipelineMultiStmt->fetchAll() ?: [];
			$pipelineEstadoIds = [];
			foreach ($currentPipelineMultiRows as $multiRow) {
				$multiEstadoId = (int) ($multiRow['estado_id'] ?? 0);
				if ($multiEstadoId > 0) {
					$pipelineEstadoIds[] = $multiEstadoId;
				}
			}

			$currentPipelineStmt = $db->prepare('SELECT estado_id FROM crm_student_pipeline WHERE student_id = :student_id LIMIT 1');
			$currentPipelineStmt->execute([':student_id' => $pipelineKey]);
			$currentPipeline = $currentPipelineStmt->fetch();
			$pipelineEstadoId = (int) ($currentPipeline['estado_id'] ?? 0);
			if (!empty($pipelineEstadoIds)) {
				$pipelineEstadoId = (int) $pipelineEstadoIds[0];
			}
			if ($pipelineEstadoId <= 0 && $entityType === 'contact') {
				$pipelineEstadoId = (int) ($student['interesado_estado_id'] ?? 0);
				if ($pipelineEstadoId > 0) {
					$pipelineEstadoIds = [$pipelineEstadoId];
				}
			}
			$student['pipeline_estado_id'] = $pipelineEstadoId;
			$student['pipeline_estado_ids'] = array_values(array_unique(array_map('intval', $pipelineEstadoIds)));
			
			// Para contactos (prospectos): solo 5 etapas. Para estudiantes: todas las etapas
			if ($entityType === 'contact') {
				$pipelineEstados = $this->fetchVisiblePipelineStates($db, 'id, nombre');
			} else {
				$pipelineEstados = $this->fetchAllPipelineStates($db, 'id, nombre');
			}

			// Obtener razones de descalificación
			try {
				$razonesStmt = $db->query('SELECT id, nombre FROM crm_descalificacion_razones ORDER BY nombre');
				$razonesList = $razonesStmt ? ($razonesStmt->fetchAll() ?: []) : [];
			} catch (Throwable $e) {
				$razonesList = [];
			}

			// Obtener razón descalificación del prospecto si existe
			$razonDescalificacionId = null;
			if ($entityType === 'contact') {
				try {
					$razonStmt = $db->prepare('SELECT razon_descalificacion FROM interesados WHERE contacto_id = :id LIMIT 1');
					$razonStmt->execute([':id' => $entityId]);
					$razonResult = $razonStmt->fetch();
					if ($razonResult) {
						$razonDescalificacionId = (int) ($razonResult['razon_descalificacion'] ?? 0);
					}
				} catch (Throwable $e) {
					// Columna puede no existir en produccion
				}
			}
			$student['razon_descalificacion_id'] = $razonDescalificacionId;

			$historyStmt = $db->prepare("SELECT
					csn.id,
					csn.note_text,
					csn.created_at,
					COALESCE(u.nombre, 'Sistema') AS user_name
				FROM crm_student_notes csn
				LEFT JOIN usuarios u ON u.id = csn.created_by
				WHERE csn.student_id = :student_id
				AND csn.source_type IN ('prospect_created', 'estado_change', 'task_create', 'task_participants', 'task_result', 'task_complete', 'contact_update', 'prospect_update', 'note_edit', 'note_delete')
				ORDER BY csn.created_at DESC
				LIMIT 30");
			$historyStmt->execute([':student_id' => $pipelineKey]);
			$pipelineHistory = $historyStmt->fetchAll() ?: [];

			echo json_encode([
				'success' => true,
				'student' => $student,
				'estados' => $pipelineEstados,
				'razones_descalificacion' => $razonesList,
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
		$estadoIdsRaw = $_POST['estado_ids'] ?? null;
		$estadoIds = [];
		if (is_array($estadoIdsRaw)) {
			foreach ($estadoIdsRaw as $rawId) {
				$id = (int) $rawId;
				if ($id > 0) {
					$estadoIds[$id] = $id;
				}
			}
		} elseif ($estadoIdsRaw !== null) {
			$tokens = preg_split('/[;,]+/', (string) $estadoIdsRaw) ?: [];
			foreach ($tokens as $token) {
				$id = (int) trim((string) $token);
				if ($id > 0) {
					$estadoIds[$id] = $id;
				}
			}
		}

		$estadoIdFallback = max(0, (int) ($_POST['estado_id'] ?? 0));
		if ($estadoIdFallback > 0) {
			$estadoIds[$estadoIdFallback] = $estadoIdFallback;
		}
		$estadoIds = array_values($estadoIds);

		if ($studentId <= 0 || empty($estadoIds)) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();

			$previousStmt = $db->prepare("SELECT COALESCE(pe.nombre, 'Sin asignar') AS nombre
					FROM crm_student_pipeline_multi pm
					LEFT JOIN pipeline_estados pe ON pe.id = pm.estado_id
					WHERE pm.student_id = :student_id
					ORDER BY pe.orden ASC, pe.id ASC");
			$previousStmt->execute([':student_id' => $studentId]);
			$previousRows = $previousStmt->fetchAll() ?: [];
			$previousNames = [];
			foreach ($previousRows as $prevRow) {
				$name = trim((string) ($prevRow['nombre'] ?? ''));
				if ($name !== '') {
					$previousNames[] = $name;
				}
			}
			if (empty($previousNames)) {
				$legacyPrevStmt = $db->prepare("SELECT COALESCE(pe.nombre, 'Sin asignar') AS nombre
					FROM crm_student_pipeline p
					LEFT JOIN pipeline_estados pe ON pe.id = p.estado_id
					WHERE p.student_id = :student_id
					LIMIT 1");
				$legacyPrevStmt->execute([':student_id' => $studentId]);
				$legacyPrev = $legacyPrevStmt->fetch();
				if ($legacyPrev) {
					$legacyName = trim((string) ($legacyPrev['nombre'] ?? ''));
					if ($legacyName !== '') {
						$previousNames[] = $legacyName;
					}
				}
			}

			$validatedIds = [];
			$validatedNames = [];
			$estadoStmt = $db->prepare("SELECT id, nombre FROM pipeline_estados
				WHERE id = :id
				  AND estado = 'activo'
				  AND LOWER(REPLACE(TRIM(COALESCE(categoria, '')), ' ', '_')) <> 'sin_crm'
				LIMIT 1");
			foreach ($estadoIds as $estadoId) {
				$estadoStmt->execute([':id' => (int) $estadoId]);
				$estado = $estadoStmt->fetch();
				if (!$estado) {
					throw new RuntimeException('Estado de pipeline inválido: ' . (int) $estadoId);
				}
				$validatedIds[] = (int) ($estado['id'] ?? 0);
				$validatedNames[] = (string) ($estado['nombre'] ?? ('ID ' . (int) $estadoId));
			}

			$validatedIds = array_values(array_unique(array_filter($validatedIds, static fn($id) => (int) $id > 0)));
			if (empty($validatedIds)) {
				throw new RuntimeException('Debes seleccionar al menos una etapa válida.');
			}

			$db->beginTransaction();

			$deleteMultiStmt = $db->prepare('DELETE FROM crm_student_pipeline_multi WHERE student_id = :student_id');
			$deleteMultiStmt->execute([':student_id' => $studentId]);

			$insertMultiStmt = $db->prepare('INSERT INTO crm_student_pipeline_multi (student_id, estado_id, updated_by, updated_at)
				VALUES (:student_id, :estado_id, :updated_by, NOW())');
			foreach ($validatedIds as $estadoId) {
				$insertMultiStmt->execute([
					':student_id' => $studentId,
					':estado_id' => $estadoId,
					':updated_by' => $this->currentUserId(),
				]);
			}

			$primaryEstadoId = (int) $validatedIds[0];
			$pipelineSql = "INSERT INTO crm_student_pipeline (student_id, estado_id, updated_by, updated_at)
						VALUES (:student_id, :estado_id, :updated_by, NOW())
						ON DUPLICATE KEY UPDATE
						estado_id = VALUES(estado_id),
						updated_by = VALUES(updated_by),
						updated_at = NOW()";
			$pipelineStmt = $db->prepare($pipelineSql);
			$pipelineStmt->execute([
				':student_id' => $studentId,
				':estado_id' => $primaryEstadoId,
				':updated_by' => $this->currentUserId(),
			]);

			$interesadoStateStmt = $db->prepare('UPDATE interesados SET estado_id = :estado_id, updated_at = NOW() WHERE contacto_id = :contacto_id LIMIT 1');
			$interesadoStateStmt->execute([
				':estado_id' => $primaryEstadoId,
				':contacto_id' => $studentId,
			]);

			// Guardar razón de descalificación si aplica
			$razonDescalificacion = (int) ($_POST['razon_descalificacion'] ?? 0);
			if ($razonDescalificacion > 0) {
				$updateRazonStmt = $db->prepare('UPDATE interesados SET razon_descalificacion = :razon WHERE contacto_id = :contacto_id LIMIT 1');
				$updateRazonStmt->execute([
					':razon' => $razonDescalificacion,
					':contacto_id' => $studentId,
				]);
			}

			$noteSql = "INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
					VALUES (:student_id, 'estado_change', :note_text, :user_id, NOW())";
			$noteStmt = $db->prepare($noteSql);
			$previousLabel = !empty($previousNames) ? implode(', ', array_values(array_unique($previousNames))) : 'Sin asignar';
			$currentLabel = implode(', ', $validatedNames);
			$noteStmt->execute([
				':student_id' => $studentId,
				':note_text' => 'Cambio de pipeline: ' . $previousLabel . ' -> ' . $currentLabel,
				':user_id' => $this->currentUserId(),
			]);

			$db->commit();

			echo json_encode([
				'success' => true,
				'message' => 'Etapas actualizadas correctamente',
				'pipeline_nombre' => implode(', ', $validatedNames),
				'pipeline_estado_ids' => $validatedIds,
			]);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
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
			$this->ensureCrmSupportTables();
			$sql = "SELECT csn.id, csn.note_text, csn.created_at, csn.created_by, u.nombre AS user_name
					FROM crm_student_notes csn
					LEFT JOIN usuarios u ON u.id = csn.created_by
					WHERE csn.student_id = :student_id AND csn.source_type = 'note'
					ORDER BY csn.created_at DESC";
			$stmt = $db->prepare($sql);
			$stmt->execute([':student_id' => $studentId]);
			$notes = $stmt->fetchAll() ?: [];

			$attachmentsMap = [];
			if (!empty($notes)) {
				$noteIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $notes)));
				if (!empty($noteIds)) {
					$placeholders = implode(',', array_fill(0, count($noteIds), '?'));
					$stmtAdj = $db->prepare("SELECT id, note_id, filename_original, mime, size_bytes
						FROM crm_student_note_adjuntos
						WHERE note_id IN ($placeholders)
						ORDER BY id ASC");
					$stmtAdj->execute($noteIds);
					foreach (($stmtAdj->fetchAll() ?: []) as $adjRow) {
						$noteId = (int) ($adjRow['note_id'] ?? 0);
						if ($noteId <= 0) {
							continue;
						}
						if (!isset($attachmentsMap[$noteId])) {
							$attachmentsMap[$noteId] = [];
						}
						$attachmentsMap[$noteId][] = [
							'id' => (int) ($adjRow['id'] ?? 0),
							'filename' => (string) ($adjRow['filename_original'] ?? 'Adjunto'),
							'mime' => (string) ($adjRow['mime'] ?? 'application/octet-stream'),
							'size' => (int) ($adjRow['size_bytes'] ?? 0),
						];
					}
				}
			}

			foreach ($notes as $idx => $note) {
				$noteId = (int) ($note['id'] ?? 0);
				$notes[$idx]['attachments'] = $attachmentsMap[$noteId] ?? [];
			}

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
			$this->ensureCrmSupportTables();
			$this->ensureUserNotificationsTable($db);
			$currentUserId = $this->currentUserId();
			$sql = "INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
					VALUES (:student_id, 'note', :note_text, :user_id, NOW())";
			$stmt = $db->prepare($sql);
			$stmt->execute([
				':student_id' => $studentId,
				':note_text' => $noteText,
				':user_id' => $currentUserId,
			]);

			$noteId = (int) $db->lastInsertId();
			$uploadErrors = $this->storeCrmNoteAttachments($db, $studentId, $noteId, $_FILES['attachments'] ?? null);

			$mentionIds = $this->extractMentionUserIds($noteText, $db, $currentUserId);
			if (!empty($mentionIds)) {
				$this->createMentionNotifications(
					$db,
					$mentionIds,
					'Mención en nota CRM',
					'Te mencionaron en una nota del CRM.',
					base_url('crm/interesados')
				);
			}

			$userName = 'Usuario';
			try {
				$userStmt = $db->prepare('SELECT nombre FROM usuarios WHERE id = :id LIMIT 1');
				$userStmt->execute([':id' => $currentUserId]);
				$userResult = $userStmt->fetch();
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
				'attachment_errors' => $uploadErrors,
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
		$removeAttachmentIdsRaw = $_POST['remove_attachment_ids'] ?? '';
		$removeAttachmentIds = [];
		if (is_array($removeAttachmentIdsRaw)) {
			foreach ($removeAttachmentIdsRaw as $rawId) {
				$id = (int) $rawId;
				if ($id > 0) {
					$removeAttachmentIds[$id] = $id;
				}
			}
		} else {
			$parts = preg_split('/\s*,\s*/', (string) $removeAttachmentIdsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			foreach ($parts as $rawId) {
				$id = (int) $rawId;
				if ($id > 0) {
					$removeAttachmentIds[$id] = $id;
				}
			}
		}
		$removeAttachmentIds = array_values($removeAttachmentIds);

		if ($noteId <= 0 || $noteText === '') {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
			exit;
		}

		try {
			$db = Database::getInstance()->connection();
			$this->ensureCrmSupportTables();
			$this->ensureUserNotificationsTable($db);
			$db->beginTransaction();

			$stmt = $db->prepare("SELECT id, created_by, student_id FROM crm_student_notes WHERE id = :id AND source_type = 'note'");
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

			if (!empty($removeAttachmentIds)) {
				$removePlaceholders = implode(',', array_fill(0, count($removeAttachmentIds), '?'));
				$selectSql = "SELECT id, storage_path FROM crm_student_note_adjuntos WHERE note_id = ? AND id IN ($removePlaceholders)";
				$selectStmt = $db->prepare($selectSql);
				$selectStmt->execute(array_merge([$noteId], $removeAttachmentIds));
				$rowsToDelete = $selectStmt->fetchAll() ?: [];

				foreach ($rowsToDelete as $rowToDelete) {
					$filePath = (string) ($rowToDelete['storage_path'] ?? '');
					if ($filePath !== '' && is_file($filePath)) {
						@unlink($filePath);
					}
				}

				$deleteSql = "DELETE FROM crm_student_note_adjuntos WHERE note_id = ? AND id IN ($removePlaceholders)";
				$deleteStmt = $db->prepare($deleteSql);
				$deleteStmt->execute(array_merge([$noteId], $removeAttachmentIds));
			}

			$uploadErrors = [];
			$studentIdForNote = (int) ($note['student_id'] ?? 0);
			if ($studentIdForNote > 0) {
				$uploadErrors = $this->storeCrmNoteAttachments($db, $studentIdForNote, $noteId, $_FILES['attachments'] ?? null);
			}

			$mentionIds = $this->extractMentionUserIds($noteText, $db, $this->currentUserId());
			if (!empty($mentionIds)) {
				$this->createMentionNotifications(
					$db,
					$mentionIds,
					'Mención en nota CRM',
					'Te mencionaron en una nota editada del CRM.',
					base_url('crm/interesados')
				);
			}

			if ($studentIdForNote > 0) {
				$historyStmt = $db->prepare("INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
					VALUES (:student_id, 'note_edit', :note_text, :user_id, NOW())");
				$historyStmt->execute([
					':student_id' => $studentIdForNote,
					':note_text' => 'Editó nota interna #' . $noteId,
					':user_id' => $this->currentUserId(),
				]);
			}

			$db->commit();

			echo json_encode([
				'success' => true,
				'note_text' => $noteText,
				'updated_at' => date('Y-m-d H:i:s'),
				'attachment_errors' => $uploadErrors,
			]);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
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
			$this->ensureCrmSupportTables();
			$db->beginTransaction();

			$stmt = $db->prepare("SELECT id, student_id FROM crm_student_notes WHERE id = :id AND source_type = 'note' LIMIT 1");
			$stmt->execute([':id' => $noteId]);
			$note = $stmt->fetch();
			if (!$note) {
				throw new RuntimeException('Nota no encontrada');
			}

			$studentId = (int) ($note['student_id'] ?? 0);

			$stmtAdj = $db->prepare('SELECT storage_path FROM crm_student_note_adjuntos WHERE note_id = :note_id');
			$stmtAdj->execute([':note_id' => $noteId]);
			foreach (($stmtAdj->fetchAll() ?: []) as $adjRow) {
				$path = (string) ($adjRow['storage_path'] ?? '');
				if ($path !== '' && is_file($path)) {
					@unlink($path);
				}
			}

			$db->prepare('DELETE FROM crm_student_note_adjuntos WHERE note_id = :note_id')->execute([':note_id' => $noteId]);
			$db->prepare("DELETE FROM crm_student_notes WHERE id = :id AND source_type = 'note' LIMIT 1")->execute([':id' => $noteId]);

			if ($studentId > 0) {
				$historyStmt = $db->prepare("INSERT INTO crm_student_notes (student_id, source_type, note_text, created_by, created_at)
					VALUES (:student_id, 'note_delete', :note_text, :user_id, NOW())");
				$historyStmt->execute([
					':student_id' => $studentId,
					':note_text' => 'Eliminó nota interna #' . $noteId,
					':user_id' => $this->currentUserId(),
				]);
			}

			$db->commit();
			echo json_encode(['success' => true, 'message' => 'Nota eliminada correctamente.']);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function noteAttachment(string $id): void
	{
		Auth::requireAuth();
		$attachmentId = (int) $id;
		if ($attachmentId <= 0) {
			http_response_code(400);
			echo 'Adjunto invalido.';
			return;
		}

		try {
			$db = Database::getInstance()->connection();
			$this->ensureCrmSupportTables();
			$stmt = $db->prepare('SELECT filename_original, mime, size_bytes, storage_path FROM crm_student_note_adjuntos WHERE id = :id LIMIT 1');
			$stmt->execute(['id' => $attachmentId]);
			$row = $stmt->fetch() ?: null;
			if (!is_array($row)) {
				http_response_code(404);
				echo 'Adjunto no encontrado.';
				return;
			}

			$fullPath = (string) ($row['storage_path'] ?? '');
			if ($fullPath === '' || !is_file($fullPath)) {
				http_response_code(404);
				echo 'Archivo adjunto no disponible.';
				return;
			}

			$basePath = realpath(ROOT_PATH . '/uploads/crm-notes');
			$realFile = realpath($fullPath);
			$insideAllowed = $this->pathStartsWith($realFile, $basePath);
			if (!$insideAllowed) {
				http_response_code(403);
				echo 'Acceso denegado al adjunto.';
				return;
			}

			$filename = (string) ($row['filename_original'] ?? 'adjunto.bin');
			$mime = (string) ($row['mime'] ?? 'application/octet-stream');
			$size = (int) ($row['size_bytes'] ?? filesize($realFile));

			header('Content-Type: ' . $mime);
			header('Content-Length: ' . $size);
			header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
			readfile($realFile);
		} catch (Throwable $e) {
			http_response_code(500);
			echo 'No se pudo servir el adjunto.';
		}
	}

	private function storeCrmNoteAttachments(PDO $db, int $studentId, int $noteId, $rawFiles): array
	{
		$errors = [];
		if ($studentId <= 0 || $noteId <= 0 || !is_array($rawFiles) || !isset($rawFiles['name'])) {
			return $errors;
		}

		$files = [];
		if (!is_array($rawFiles['name'])) {
			$files[] = $rawFiles;
		} else {
			$count = count($rawFiles['name']);
			for ($i = 0; $i < $count; $i++) {
				$files[] = [
					'name' => $rawFiles['name'][$i] ?? '',
					'type' => $rawFiles['type'][$i] ?? '',
					'tmp_name' => $rawFiles['tmp_name'][$i] ?? '',
					'error' => $rawFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
					'size' => $rawFiles['size'][$i] ?? 0,
				];
			}
		}

		$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar'];
		$maxBytes = 15 * 1024 * 1024;
		$uploadDir = ROOT_PATH . '/uploads/crm-notes/' . $studentId . '/' . $noteId;
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
			return ['No se pudo crear el directorio de adjuntos.'];
		}

		$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
		foreach ($files as $file) {
			$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
			if ($errorCode === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if ($errorCode !== UPLOAD_ERR_OK) {
				$errors[] = 'Archivo no válido (código ' . $errorCode . ').';
				continue;
			}

			$tmpName = (string) ($file['tmp_name'] ?? '');
			$origName = trim((string) ($file['name'] ?? 'adjunto'));
			$size = (int) ($file['size'] ?? 0);
			if ($tmpName === '' || !is_uploaded_file($tmpName)) {
				$errors[] = 'No se recibió correctamente el archivo ' . $origName . '.';
				continue;
			}
			if ($size <= 0 || $size > $maxBytes) {
				$errors[] = 'El archivo ' . $origName . ' supera 15MB o está vacío.';
				continue;
			}

			$ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
			if (!in_array($ext, $allowedExtensions, true)) {
				$errors[] = 'Tipo de archivo no permitido: ' . $origName . '.';
				continue;
			}

			$mime = 'application/octet-stream';
			if ($finfo !== null) {
				$detected = finfo_file($finfo, $tmpName);
				if (is_string($detected) && $detected !== '') {
					$mime = $detected;
				}
			}

			$storageName = bin2hex(random_bytes(16)) . ($ext !== '' ? ('.' . $ext) : '');
			$targetPath = $uploadDir . '/' . $storageName;
			if (!move_uploaded_file($tmpName, $targetPath)) {
				$errors[] = 'No se pudo almacenar el archivo ' . $origName . '.';
				continue;
			}

			$stmt = $db->prepare('INSERT INTO crm_student_note_adjuntos (note_id, student_id, filename_original, filename_storage, mime, size_bytes, storage_path, created_at)
				VALUES (:note_id, :student_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, NOW())');
			$stmt->execute([
				'note_id' => $noteId,
				'student_id' => $studentId,
				'filename_original' => substr($origName, 0, 255),
				'filename_storage' => $storageName,
				'mime' => substr($mime, 0, 120),
				'size_bytes' => $size,
				'storage_path' => $targetPath,
			]);
		}

		if ($finfo !== null) {
			finfo_close($finfo);
		}

		return $errors;
	}

	private function ensureUserNotificationsTable(PDO $db): void
	{
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
	}

	private function extractMentionUserIds(string $text, PDO $db, int $excludeUserId = 0): array
	{
		if (!preg_match_all('/@([a-zA-Z0-9._-]{2,80})/u', $text, $matches)) {
			return [];
		}

		$tokens = array_values(array_unique(array_map(static function ($value): string {
			return strtolower(trim((string) $value));
		}, (array) ($matches[1] ?? []))));
		$tokens = array_values(array_filter($tokens, static fn($token) => $token !== ''));
		if (empty($tokens)) {
			return [];
		}

		$rows = $db->query("SELECT id, nombre, email FROM usuarios WHERE estado = 'activo'")->fetchAll() ?: [];
		$ids = [];
		foreach ($rows as $row) {
			$userId = (int) ($row['id'] ?? 0);
			if ($userId <= 0 || ($excludeUserId > 0 && $userId === $excludeUserId)) {
				continue;
			}
			$nameKey = strtolower(trim((string) ($row['nombre'] ?? '')));
			$email = strtolower(trim((string) ($row['email'] ?? '')));
			$emailLocal = $email !== '' ? strtolower(trim((string) strtok($email, '@'))) : '';
			if (in_array($nameKey, $tokens, true) || ($emailLocal !== '' && in_array($emailLocal, $tokens, true))) {
				$ids[$userId] = $userId;
			}
		}

		return array_values($ids);
	}

	private function createMentionNotifications(PDO $db, array $userIds, string $title, string $message, string $url): void
	{
		if (empty($userIds)) {
			return;
		}

		$stmt = $db->prepare('INSERT INTO user_notifications (user_id, title, message, url, type, is_read, created_at)
			VALUES (:user_id, :title, :message, :url, :type, 0, NOW())');
		foreach ($userIds as $userId) {
			$uid = (int) $userId;
			if ($uid <= 0) {
				continue;
			}
			$stmt->execute([
				'user_id' => $uid,
				'title' => mb_substr($title, 0, 180),
				'message' => $message,
				'url' => mb_substr($url, 0, 500),
				'type' => 'mention',
			]);
		}
	}

	private function pathStartsWith($fullPath, $basePath): bool
	{
		if (!is_string($fullPath) || !is_string($basePath) || $fullPath === '' || $basePath === '') {
			return false;
		}
		$normalizedFull = strtolower(str_replace('\\', '/', $fullPath));
		$normalizedBase = rtrim(strtolower(str_replace('\\', '/', $basePath)), '/');
		return str_starts_with($normalizedFull, $normalizedBase . '/') || $normalizedFull === $normalizedBase;
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
			$sql = "SELECT csn.created_at, COALESCE(u.nombre, 'Sistema') AS usuario, csn.note_text
					FROM crm_student_notes csn
					LEFT JOIN usuarios u ON u.id = csn.created_by
					WHERE csn.student_id = :student_id AND csn.source_type IN ('prospect_created', 'estado_change', 'task_create', 'task_participants', 'task_result', 'task_complete', 'contact_update', 'prospect_update', 'note_edit', 'note_delete')
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

	private const PROSPECT_RECONCILE_LOCK_FILE = STORAGE_PATH . '/logs/.crm_prospect_reconcile_last_run';
	private const PROSPECT_RECONCILE_INTERVAL_SECONDS = 300;

	private function shouldRunProspectReconcile(): bool
	{
		$lastRun = 0;
		if (is_file(self::PROSPECT_RECONCILE_LOCK_FILE)) {
			$lastRun = (int) @file_get_contents(self::PROSPECT_RECONCILE_LOCK_FILE);
		}

		return (time() - $lastRun) >= self::PROSPECT_RECONCILE_INTERVAL_SECONDS;
	}

	private function markProspectReconcileRun(): void
	{
		$logDir = dirname(self::PROSPECT_RECONCILE_LOCK_FILE);
		if (!is_dir($logDir)) {
			@mkdir($logDir, 0755, true);
		}
		@file_put_contents(self::PROSPECT_RECONCILE_LOCK_FILE, (string) time(), LOCK_EX);
	}

	private function reconcileProspectsWithSuperarseUsers(): void
	{
		$db = Database::getInstance()->connection();
		$remote = $this->connectSuperarseDatabase();
		if (!$remote instanceof PDO || $this->resolveSuperarseStudentTable($remote) !== 'users') {
			return;
		}

		$rows = $db->query("SELECT i.contacto_id, c.cedula
			FROM interesados i
			INNER JOIN contactos c ON c.id = i.contacto_id
			WHERE i.estado = 'activo' AND COALESCE(i.convertido, 0) = 0")->fetchAll() ?: [];
		if (empty($rows)) {
			return;
		}

		$identityToContact = [];
		foreach ($rows as $row) {
			$contactId = (int) ($row['contacto_id'] ?? 0);
			$identity = $this->normalizeIdentityValue((string) ($row['cedula'] ?? ''));
			if ($contactId > 0 && $identity !== '') {
				$identityToContact[$identity] = $contactId;
			}
		}
		if (empty($identityToContact)) {
			return;
		}

		$columns = $remote->query('SHOW COLUMNS FROM users')->fetchAll() ?: [];
		$availableCols = [];
		foreach ($columns as $column) {
			$name = trim((string) ($column['Field'] ?? ''));
			if ($name !== '') {
				$availableCols[$name] = true;
			}
		}

		$identityColumn = null;
		foreach (['numero_identificacion', 'cedula', 'identificacion', 'documento', 'pasaporte'] as $candidate) {
			if (isset($availableCols[$candidate])) {
				$identityColumn = $candidate;
				break;
			}
		}
		if ($identityColumn === null) {
			return;
		}

		$careerSelect = "'' AS career_name";
		foreach (['carrera', 'programa'] as $candidateCareer) {
			if (isset($availableCols[$candidateCareer])) {
				$careerSelect = "$candidateCareer AS career_name";
				break;
			}
		}

		$identities = array_keys($identityToContact);
		foreach (array_chunk($identities, 200) as $chunk) {
			$placeholders = implode(',', array_fill(0, count($chunk), '?'));
			$sql = "SELECT id, $careerSelect, REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE($identityColumn, ''))), '.', ''), '-', ''), ' ', '') AS identity_key
				FROM users
				WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE($identityColumn, ''))), '.', ''), '-', ''), ' ', '') IN ($placeholders)";
			$stmt = $remote->prepare($sql);
			$stmt->execute($chunk);
			$matched = $stmt->fetchAll() ?: [];

			foreach ($matched as $user) {
				$userId = (int) ($user['id'] ?? 0);
				$identity = $this->normalizeIdentityValue((string) ($user['identity_key'] ?? ''));
				$contactId = (int) ($identityToContact[$identity] ?? 0);
				if ($userId <= 0 || $contactId <= 0) {
					continue;
				}

				$careerName = trim((string) ($user['career_name'] ?? ''));
				if (!$this->hasActiveMatriculaForContact($db, $contactId, $careerName)) {
					continue;
				}

				$this->ensureStudentFromContact($db, $contactId, (string) $userId, 'activo');
				$this->migrateProspectCrmDataToStudent($db, $contactId, $userId);
				$this->markProspectAsConverted($db, $contactId);
			}
		}
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
				$this->migrateProspectCrmDataToStudent($db, $contactId, $userId);
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

	private function normalizeProspectSort(string $value): string
	{
		$value = strtolower(trim($value));
		return in_array($value, ['asc', 'desc'], true) ? $value : 'desc';
	}

	private function normalizeProspectPhoneValue(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		$digits = preg_replace('/\D+/', '', $value) ?: '';
		if ($digits === '') {
			return '';
		}

		if (preg_match('/^5939\d{8}$/', $digits) === 1) {
			return '+' . $digits;
		}

		if (preg_match('/^09\d{8}$/', $digits) === 1) {
			return '+593' . substr($digits, 1);
		}

		if (preg_match('/^9\d{8}$/', $digits) === 1) {
			return '+593' . $digits;
		}

		return '';
	}

	private function hasActiveMatriculaForContact(PDO $db, int $contactId, string $careerName = ''): bool
	{
		if ($contactId <= 0 || trim($careerName) === '') {
			return false;
		}

		$sql = 'SELECT m.id
			FROM estudiantes e
			INNER JOIN matriculas m ON m.estudiante_id = e.id
			LEFT JOIN carreras c ON c.id = m.carrera_id
			WHERE e.contacto_id = :contacto_id
			  AND e.estado = "activo"
			  AND m.estado = "activo"
			  AND COALESCE(m.estado_matricula, "") = "activo"';
		$params = ['contacto_id' => $contactId];

		$careerName = trim($careerName);
		if ($careerName !== '') {
			$sql .= ' AND LOWER(TRIM(COALESCE(c.nombre, ""))) = :career_name';
			$params['career_name'] = mb_strtolower($careerName);
		}

		$sql .= ' LIMIT 1';
		$stmt = $db->prepare($sql);
		$stmt->execute($params);

		return (bool) $stmt->fetchColumn();
	}

	private function mapAsesorToCreador(string $asesor): string
	{
		$asesor = trim($asesor);
		if ($asesor === '') {
			return '';
		}

		// Regla A: Si asesor IN (Lizbeth Ochoa, Jennifer Betancourt, Melany Artieda) -> creado_por = "EQUIPO MAÑANA"
		$equipoManana = ['Lizbeth Ochoa', 'Jennifer Betancourt', 'Melany Artieda'];
		if (in_array($asesor, $equipoManana, true)) {
			return 'EQUIPO MAÑANA';
		}

		// Regla B: Si asesor = "Melany Vásquez" -> creado_por = "EQUIPO NOCHE"
		if ($asesor === 'Melany Vásquez') {
			return 'EQUIPO NOCHE';
		}

		// Regla C: Si asesor IN (Luis Granja, Mayra Segarra, Noemi Toro) -> creado_por = mismo asesor
		$equipoPropio = ['Luis Granja', 'Mayra Segarra', 'Noemi Toro'];
		if (in_array($asesor, $equipoPropio, true)) {
			return $asesor;
		}

		// Por defecto, retornar vacío
		return '';
	}

	private function normalizeCreatedByValue(string $value): string
	{
		$items = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$unique = [];
		foreach ($items as $item) {
			$clean = trim((string) $item);
			if ($clean === '') {
				continue;
			}
			$key = mb_strtolower($clean, 'UTF-8');
			if (isset($unique[$key])) {
				continue;
			}
			$unique[$key] = mb_substr($clean, 0, 60);
		}

		return implode(', ', array_values($unique));
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

	private function canUseEmailAsPrimaryContact(PDO $db, string $email, ?int $excludeContactId, ?int &$conflictContactId = null): bool
	{
		$conflictContactId = null;

		if ($email === '') {
			return false;
		}

		$sql = 'SELECT id FROM contactos WHERE deleted_at IS NULL AND LOWER(TRIM(COALESCE(email, ""))) = :email';
		$params = ['email' => strtolower(trim($email))];
		if ($excludeContactId !== null && $excludeContactId > 0) {
			$sql .= ' AND id <> :exclude_id';
			$params['exclude_id'] = $excludeContactId;
		}
		$sql .= ' LIMIT 1';

		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$foundId = $stmt->fetchColumn();
		if ($foundId !== false) {
			$conflictContactId = (int) $foundId;
		}

		return $foundId === false;
	}

	private function buildConflictContactSuffix(PDO $db, ?int $contactId): string
	{
		if ($contactId === null || $contactId <= 0) {
			return '';
		}

		$name = $this->getContactDisplayName($db, (int) $contactId);
		if ($name !== '') {
			return ' (ID conflicto: ' . (int) $contactId . ', contacto: ' . $name . ')';
		}

		return ' (ID conflicto: ' . (int) $contactId . ')';
	}

	private function getContactDisplayName(PDO $db, int $contactId): string
	{
		if ($contactId <= 0) {
			return '';
		}

		$stmt = $db->prepare('SELECT nombre, apellido FROM contactos WHERE id = :id LIMIT 1');
		$stmt->execute([':id' => $contactId]);
		$row = $stmt->fetch();
		if (!$row) {
			return '';
		}

		$fullName = trim((string) ($row['nombre'] ?? '') . ' ' . (string) ($row['apellido'] ?? ''));
		return $fullName;
	}

	private function getDbConnectionLabel(PDO $db): string
	{
		try {
			$stmt = $db->query('SELECT DATABASE() AS db_name, @@hostname AS host_name, @@port AS port_number');
			$row = $stmt ? $stmt->fetch() : null;
			if (!$row) {
				return '';
			}

			$dbName = trim((string) ($row['db_name'] ?? ''));
			$hostName = trim((string) ($row['host_name'] ?? ''));
			$portNumber = trim((string) ($row['port_number'] ?? ''));

			if ($dbName === '' && $hostName === '' && $portNumber === '') {
				return '';
			}

			$label = $dbName;
			if ($hostName !== '') {
				$label .= ($label !== '' ? '@' : '') . $hostName;
			}
			if ($portNumber !== '') {
				$label .= ':' . $portNumber;
			}

			return $label;
		} catch (Throwable $e) {
			return '';
		}
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

	/**
	 * Buscar prospectos por nota interna
	 * Devuelve lista de prospect contact IDs que tienen notas con la palabra clave
	 */
	public function searchProspectsByNote(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$keyword = trim((string) ($_GET['keyword'] ?? ''));

		if ($keyword === '') {
			echo json_encode([
				'success' => true,
				'contact_ids' => [],
				'total' => 0,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();

			// Buscar notas que contengan la palabra clave
			$searchTerm = '%' . addcslashes($keyword, '%_') . '%';
			$stmt = $db->prepare("
				SELECT DISTINCT i.contacto_id
				FROM crm_student_notes cn
				INNER JOIN interesados i ON i.contacto_id = cn.student_id
				WHERE cn.note_text LIKE :keyword
				  AND i.estado = 'activo'
				  AND COALESCE(i.convertido, 0) = 0
				  AND i.deleted_at IS NULL
				ORDER BY cn.created_at DESC
				LIMIT 5000
			");
			$stmt->execute([':keyword' => $searchTerm]);
			$results = $stmt->fetchAll() ?: [];

			$contactIds = [];
			foreach ($results as $row) {
				$contactId = (int) ($row['contacto_id'] ?? 0);
				if ($contactId > 0) {
					$contactIds[] = $contactId;
				}
			}

			echo json_encode([
				'success' => true,
				'contact_ids' => array_values(array_unique($contactIds)),
				'total' => count(array_unique($contactIds)),
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage(),
				'contact_ids' => [],
				'total' => 0,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		exit;
	}

	/**
	 * Soft-delete un cliente potencial (prospect)
	 * Marcacomo deleted_at = NOW() sin eliminar datos
	 */
	public function softDeleteProspect(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$contactoId = max(0, (int) ($_POST['contacto_id'] ?? 0));
		$razon = trim((string) ($_POST['razon'] ?? ''));

		if ($contactoId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$db->beginTransaction();

			// Soft-delete en contactos
			$stmt = $db->prepare('UPDATE contactos SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id LIMIT 1');
			$stmt->execute([':id' => $contactoId]);

			// Soft-delete en interesados
			$stmt = $db->prepare('UPDATE interesados SET deleted_at = NOW(), updated_at = NOW() WHERE contacto_id = :contacto_id');
			$stmt->execute([':contacto_id' => $contactoId]);

			// Registrar en tabla de auditoría soft_delete_audit
			$auditStmt = $db->prepare('INSERT INTO soft_delete_audit (entity_type, entity_id, deleted_by, deleted_at, reason)
				VALUES (:entity_type, :entity_id, :deleted_by, NOW(), :reason)');
			$auditStmt->execute([
				':entity_type' => 'contacto',
				':entity_id' => $contactoId,
				':deleted_by' => $this->currentUserId() ?: null,
				':reason' => $razon !== '' ? mb_substr($razon, 0, 255) : null,
			]);

			// Registrar nota histórica
			try {
				$noteText = 'Eliminó cliente potencial';
				if ($razon !== '') {
					$noteText .= '. Razón: ' . $razon;
				}
				$this->crmHistoryNote($contactoId, 'prospect_deleted', $noteText);
			} catch (Throwable $ignore) {
				// No interrumpir el flujo
			}

			$db->commit();

			echo json_encode([
				'success' => true,
				'message' => 'Cliente potencial eliminado correctamente',
			]);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}

		exit;
	}

	/**
	 * Restaurar un cliente potencial previamente eliminado
	 */
	public function restoreProspect(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		$contactoId = max(0, (int) ($_POST['contacto_id'] ?? 0));

		if ($contactoId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'ID inválido']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$db->beginTransaction();

			// Restaurar en contactos
			$stmt = $db->prepare('UPDATE contactos SET deleted_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1');
			$stmt->execute([':id' => $contactoId]);

			// Restaurar en interesados
			$stmt = $db->prepare('UPDATE interesados SET deleted_at = NULL, updated_at = NOW() WHERE contacto_id = :contacto_id');
			$stmt->execute([':contacto_id' => $contactoId]);

			// Actualizar tabla de auditoría
			$auditStmt = $db->prepare('UPDATE soft_delete_audit SET restored_at = NOW(), restored_by = :restored_by
				WHERE entity_type = :entity_type AND entity_id = :entity_id AND restored_at IS NULL
				LIMIT 1');
			$auditStmt->execute([
				':entity_type' => 'contacto',
				':entity_id' => $contactoId,
				':restored_by' => $this->currentUserId() ?: null,
			]);

			// Registrar nota histórica
			try {
				$this->crmHistoryNote($contactoId, 'prospect_restored', 'Restauró cliente potencial eliminado');
			} catch (Throwable $ignore) {
				// No interrumpir el flujo
			}

			$db->commit();

			echo json_encode([
				'success' => true,
				'message' => 'Cliente potencial restaurado correctamente',
			]);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}

		exit;
	}

	/**
	 * FASE 3.3: Actualización masiva de etapas para múltiples prospectos
	 * POST /crm/bulkUpdateProspects
	 */
	public function bulkUpdateProspects(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		// Leer JSON del body
		$input = json_decode(file_get_contents('php://input'), true) ?? [];
		$contactIds = $input['contact_ids'] ?? [];
		$newStage = trim((string) ($input['new_stage'] ?? ''));
		$note = trim((string) ($input['note'] ?? ''));

		// Validaciones
		if (!is_array($contactIds) || count($contactIds) === 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'IDs de contactos inválidos']);
			exit;
		}

		if (empty($newStage)) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Etapa no especificada']);
			exit;
		}

		// Convertir contactIds a enteros
		$contactIds = array_map(function($id) { return max(0, (int)$id); }, $contactIds);
		$contactIds = array_filter($contactIds);

		if (count($contactIds) === 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'IDs de contactos inválidos']);
			exit;
		}

		try {
			$this->ensureCrmSupportTables();
			$db = Database::getInstance()->connection();
			$db->beginTransaction();

			$placeholders = implode(',', array_fill(0, count($contactIds), '?'));

			// Obtener ID de etapa
			$stageStmt = $db->prepare('SELECT id FROM etapas WHERE nombre = ? LIMIT 1');
			$stageStmt->execute([$newStage]);
			$stageRow = $stageStmt->fetch(PDO::FETCH_ASSOC);

			if (!$stageRow) {
				// Si no existe, crear la etapa
				$createStageStmt = $db->prepare('INSERT INTO etapas (nombre, descripcion, created_at) VALUES (?, ?, NOW())');
				$createStageStmt->execute([$newStage, 'Etapa creada automáticamente']);
				$stageId = (int)$db->lastInsertId();
			} else {
				$stageId = (int)$stageRow['id'];
			}

			// Si se proporciona new_asesor, actualizar también asesor
			$newAsesor = trim($requestData['new_asesor'] ?? '');
			if (!empty($newAsesor)) {
				$updateAsesorStmt = $db->prepare("UPDATE interesados SET asesor = ? WHERE contacto_id IN ($placeholders) AND deleted_at IS NULL");
				$params = array_merge([$newAsesor], $contactIds);
				$updateAsesorStmt->execute($params);
				$asesorDisplay = $newAsesor;
			} else {
				// Obtener asesor del primer prospecto para mostrar en respuesta
				$firstContactId = reset($contactIds);
				$asesorStmt = $db->prepare('SELECT DISTINCT i.asesor FROM interesados i WHERE i.contacto_id = ? AND i.asesor IS NOT NULL LIMIT 1');
				$asesorStmt->execute([$firstContactId]);
				$asesorRow = $asesorStmt->fetch(PDO::FETCH_ASSOC);
				$asesorDisplay = $asesorRow['asesor'] ?? 'N/A';
			}

			// Actualizar etapas para cada prospect
			$updateStmt = $db->prepare("UPDATE interesados SET estado_id = ?, updated_at = NOW() WHERE contacto_id IN ($placeholders) AND deleted_at IS NULL");
			$params = [$stageId, ...$contactIds];
			$updateStmt->execute($params);

			$updatedCount = $updateStmt->rowCount();

			// Si hay nota, agregarla a cada prospect
			if (!empty($note)) {
				foreach ($contactIds as $contactId) {
					try {
						// Agregar nota interna
						$noteStmt = $db->prepare('INSERT INTO crm_student_notes (student_id, contact_id, note_text, user_id, created_at) VALUES (?, ?, ?, ?, NOW())');
						$noteStmt->execute([$contactId, $contactId, $note, $this->currentUserId() ?: null]);
					} catch (Throwable $ignore) {
						// Continuar si falla inserción de nota
					}
				}
			}

			$db->commit();

			echo json_encode([
				'success' => true,
				'message' => "Etapa '{$newStage}' actualizada para $updatedCount cliente(s)",
				'updated_count' => $updatedCount,
				'stage' => $newStage,
				'asesor' => $asesorDisplay,
			]);
		} catch (Throwable $e) {
			if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
				$db->rollBack();
			}
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}

		exit;
	}

	public function getModalidades(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json');
		try {
			$db = Database::getInstance()->connection();
			$this->ensureCrmSupportTables();
			$stmt = $db->prepare("SELECT id, nombre FROM crm_modalidades WHERE activo = 1 ORDER BY orden ASC, nombre ASC");
			$stmt->execute();
			$modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
			echo json_encode(['success' => true, 'data' => $modalidades]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function getPipelineStates(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json');
		try {
			$db = Database::getInstance()->connection();
			$estados = $this->fetchVisiblePipelineStates($db, 'id, nombre');
			echo json_encode(['success' => true, 'data' => $estados]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function getProspectAsesores(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json');
		try {
			$db = Database::getInstance()->connection();
			$asesores = $this->fetchProspectSelectorOptions($db, 'crm_prospect_asesores');
			echo json_encode(['success' => true, 'data' => $asesores]);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function listModalidades(): void
	{
		Auth::requireAuth();
		try {
			$db = Database::getInstance()->connection();
			$this->ensureCrmSupportTables();
			$stmt = $db->prepare("SELECT id, nombre, descripcion, activo, orden, created_at FROM crm_modalidades ORDER BY orden ASC, nombre ASC");
			$stmt->execute();
			$modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
			$this->view('crm/modalidades', ['modalidades' => $modalidades], ['title' => 'Gestionar Modalidades']);
		} catch (Throwable $e) {
			redirect('/?error=' . urlencode($e->getMessage()));
		}
	}

	public function saveModalidad(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json');
		try {
			$id = (int) ($_POST['id'] ?? 0);
			$nombre = trim((string) ($_POST['nombre'] ?? ''));
			$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
			$activo = (int) ($_POST['activo'] ?? 1);
			$orden = (int) ($_POST['orden'] ?? 0);

			if (empty($nombre)) {
				throw new Exception('El nombre es requerido');
			}

			$db = Database::getInstance()->connection();
			$this->ensureCrmSupportTables();

			if ($id > 0) {
				// Actualizar
				$stmt = $db->prepare("UPDATE crm_modalidades SET nombre = ?, descripcion = ?, activo = ?, orden = ? WHERE id = ?");
				$stmt->execute([$nombre, $descripcion, $activo, $orden, $id]);
				echo json_encode(['success' => true, 'message' => 'Modalidad actualizada']);
			} else {
				// Crear
				$stmt = $db->prepare("INSERT INTO crm_modalidades (nombre, descripcion, activo, orden) VALUES (?, ?, ?, ?)");
				$stmt->execute([$nombre, $descripcion, $activo, $orden]);
				$newId = $db->lastInsertId();
				echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Modalidad creada']);
			}
		} catch (Throwable $e) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

	public function deleteModalidad(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json');
		try {
			$id = (int) ($_POST['id'] ?? 0);
			if ($id <= 0) {
				throw new Exception('ID inválido');
			}

			$db = Database::getInstance()->connection();
			$this->ensureCrmSupportTables();
			$stmt = $db->prepare("DELETE FROM crm_modalidades WHERE id = ?");
			$stmt->execute([$id]);
			echo json_encode(['success' => true, 'message' => 'Modalidad eliminada']);
		} catch (Throwable $e) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}

}
