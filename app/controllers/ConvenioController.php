<?php

class ConvenioController extends Controller
{
    private function normalizeText(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }

        if (function_exists('mb_convert_encoding')) {
            $clean = mb_convert_encoding($clean, 'UTF-8', 'UTF-8');
        }

        return $clean;
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

        $byId = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid > 0) {
                $byId[$uid] = trim((string) ($row['nombre'] ?? ''));
            }
        }

        $labels = [];
        foreach ($orderedIds as $uid) {
            $labels[] = $byId[$uid] ?? ('Usuario #' . $uid);
        }

        return implode(', ', $labels);
    }

    private function registerHistorial(PDO $db, int $convenioId, ?int $tareaId, string $accion, string $detalle = ''): void
    {
        try {
            $stmt = $db->prepare("INSERT INTO convenio_historial (convenio_id, tarea_id, usuario_id, accion, detalle) VALUES (:convenio_id, :tarea_id, :usuario_id, :accion, :detalle)");
            $stmt->execute([
                'convenio_id' => $convenioId,
                'tarea_id' => $tareaId,
                'usuario_id' => Auth::id() ? (int) Auth::id() : null,
                'accion' => substr($this->normalizeText($accion), 0, 80),
                'detalle' => $this->normalizeText($detalle),
            ]);
        } catch (Throwable $e) {
            // El historial no debe bloquear la operación principal.
        }
    }

    private function listHistorial(PDO $db, int $convenioId): array
    {
        $sql = "SELECT h.*, u.nombre AS usuario_nombre
            FROM convenio_historial h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.convenio_id = :convenio_id
            ORDER BY h.created_at DESC, h.id DESC
            LIMIT 250";

        $stmt = $db->prepare($sql);
        $stmt->execute(['convenio_id' => $convenioId]);
        return $stmt->fetchAll() ?: [];
    }

    private function findTareaMeta(PDO $db, int $convenioId, int $taskId): ?array
    {
        $stmt = $db->prepare("SELECT id, estado, completado FROM convenio_tareas WHERE id = :id AND convenio_id = :convenio_id LIMIT 1");
        $stmt->execute([
            'id' => $taskId,
            'convenio_id' => $convenioId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function connectConveniosDatabase(): ?PDO
    {
        $host = trim((string) env('CONVENIOS_DB_HOST', (string) env('SUPERARSE_DB_HOST', '')));
        $port = trim((string) env('CONVENIOS_DB_PORT', (string) env('SUPERARSE_DB_PORT', '3306')));
        $database = trim((string) env('CONVENIOS_DB_DATABASE', (string) env('SUPERARSE_DB_DATABASE', '')));
        $username = trim((string) env('CONVENIOS_DB_USERNAME', (string) env('SUPERARSE_DB_USERNAME', '')));
        $password = (string) env('CONVENIOS_DB_PASSWORD', (string) env('SUPERARSE_DB_PASSWORD', ''));
        $charset = trim((string) env('CONVENIOS_DB_CHARSET', (string) env('SUPERARSE_DB_CHARSET', 'utf8mb4')));

        if ($host === '' || $database === '' || $username === '') {
            return null;
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function ensureAccess(): void
    {
        Auth::requireAuth();
        if (!Auth::canAccessModule('convenios')) {
            set_flash('error', 'No tienes permisos para acceder al módulo de convenios.');
            redirect('dashboard');
        }
    }

    private function ensureConveniosTables(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS convenio_notas (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            convenio_id INT NOT NULL,
            usuario_id INT NOT NULL,
            nota TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS convenio_nota_adjuntos (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            convenio_id INT NOT NULL,
            nota_id BIGINT NOT NULL,
            filename_original VARCHAR(255) NOT NULL,
            filename_storage VARCHAR(255) NOT NULL,
            mime VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
            size_bytes BIGINT NOT NULL DEFAULT 0,
            storage_path VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_convenio_nota_adjuntos_nota (nota_id),
            INDEX idx_convenio_nota_adjuntos_convenio (convenio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

        $db->exec("CREATE TABLE IF NOT EXISTS convenio_tareas (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            convenio_id INT NOT NULL,
            tipo_tarea_id INT NULL,
            resultado_id INT NULL,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT NULL,
            fecha_vencimiento DATE NULL,
            hora_vencimiento TIME NULL,
            propietario_id INT NOT NULL,
            estado ENUM('pendiente','en_proceso','completada','cancelada') DEFAULT 'pendiente',
            completado TINYINT DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tipo_tarea_id) REFERENCES tipo_tarea_convenios(id) ON DELETE SET NULL,
            FOREIGN KEY (resultado_id) REFERENCES resultados(id) ON DELETE SET NULL,
            FOREIGN KEY (propietario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
            FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_convenio_id (convenio_id),
            INDEX idx_convenio_tarea_estado (estado),
            INDEX idx_convenio_tarea_vencimiento (fecha_vencimiento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS convenio_tarea_relacionados (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            tarea_id BIGINT NOT NULL,
            usuario_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tarea_id) REFERENCES convenio_tareas(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_relacionado (tarea_id, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS convenio_tarea_colaboradores (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            tarea_id BIGINT NOT NULL,
            usuario_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tarea_id) REFERENCES convenio_tareas(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_colaborador (tarea_id, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS convenio_historial (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            convenio_id INT NOT NULL,
            tarea_id BIGINT NULL,
            usuario_id INT NULL,
            accion VARCHAR(80) NOT NULL,
            detalle TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_convenio_historial_convenio (convenio_id),
            INDEX idx_convenio_historial_tarea (tarea_id),
            INDEX idx_convenio_historial_fecha (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
    }

    public function index(): void
    {
        $this->ensureAccess();

        $localDb = Database::getInstance()->connection();
        $this->ensureConveniosTables($localDb);
        $remote = $this->connectConveniosDatabase();
        if ($remote === null) {
            set_flash('error', 'No se pudo conectar a la BD de convenios (superar1_conectados). Configura CONVENIOS_DB_* o SUPERARSE_DB_* en .env.');
            $this->view('convenios/index', ['convenios' => []], ['title' => 'Convenios']);
            return;
        }

        $model = new Convenio();
        $model->setConnection($remote);
        $convenios = $model->listWithStats();

        $notaCounts = [];
        $tareaCounts = [];
        foreach ($localDb->query("SELECT convenio_id, COUNT(*) total FROM convenio_notas GROUP BY convenio_id")->fetchAll() ?: [] as $row) {
            $notaCounts[(int) ($row['convenio_id'] ?? 0)] = (int) ($row['total'] ?? 0);
        }
        foreach ($localDb->query("SELECT convenio_id, COUNT(*) total FROM convenio_tareas GROUP BY convenio_id")->fetchAll() ?: [] as $row) {
            $tareaCounts[(int) ($row['convenio_id'] ?? 0)] = (int) ($row['total'] ?? 0);
        }

        foreach ($convenios as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $row['total_notas'] = $notaCounts[$id] ?? 0;
            $row['total_tareas'] = $tareaCounts[$id] ?? 0;
        }
        unset($row);

        $this->view('convenios/index', compact('convenios'), [
            'title' => 'Convenios',
        ]);
    }

    public function create(): void
    {
        $this->ensureAccess();

        set_flash('error', 'Los datos del convenio son de solo lectura. Solo se permite registrar notas y tareas.');
        redirect('convenios');
    }

    public function store(): void
    {
        $this->ensureAccess();

        set_flash('error', 'No se permite crear convenios desde este módulo. Solo se permite registrar notas y tareas.');
        redirect('convenios');
    }

    public function show(string $id): void
    {
        $this->ensureAccess();

        $convenioId = (int) $id;
        $localDb = Database::getInstance()->connection();
        $this->ensureConveniosTables($localDb);
        $remote = $this->connectConveniosDatabase();
        if ($remote === null) {
            set_flash('error', 'No se pudo conectar a la BD de convenios.');
            redirect('convenios');
        }

        $convenioModel = new Convenio();
        $convenioModel->setConnection($remote);
        $notaModel = new ConvenioNota();
        $tareaModel = new ConvenioTarea();

        $convenio = $convenioModel->findById($convenioId);
        if (!$convenio) {
            set_flash('error', 'Convenio no encontrado.');
            redirect('convenios');
        }

        $notas = $notaModel->listByConvenio($convenioId);
        $noteIds = [];
        foreach ($notas as $noteRow) {
            $nid = (int) ($noteRow['id'] ?? 0);
            if ($nid > 0) {
                $noteIds[$nid] = $nid;
            }
        }
        $attachmentsByNote = [];
        if (!empty($noteIds)) {
            $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
            $sqlAttachments = "SELECT id, nota_id, filename_original, mime, size_bytes, created_at
                FROM convenio_nota_adjuntos
                WHERE nota_id IN ($placeholders)
                ORDER BY id ASC";
            $stmtAttachments = $localDb->prepare($sqlAttachments);
            $stmtAttachments->execute(array_values($noteIds));
            foreach ($stmtAttachments->fetchAll() ?: [] as $row) {
                $nid = (int) ($row['nota_id'] ?? 0);
                if ($nid <= 0) {
                    continue;
                }
                $attachmentsByNote[$nid][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'filename_original' => (string) ($row['filename_original'] ?? 'Adjunto'),
                    'mime' => (string) ($row['mime'] ?? 'application/octet-stream'),
                    'size_bytes' => (int) ($row['size_bytes'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        }
        foreach ($notas as &$noteRow) {
            $nid = (int) ($noteRow['id'] ?? 0);
            $noteRow['attachments'] = $attachmentsByNote[$nid] ?? [];
        }
        unset($noteRow);
        $tareas = $tareaModel->listByConvenio($convenioId);
        $historial = $this->listHistorial($localDb, $convenioId);
        $tiposTarea = $tareaModel->activeTiposTarea();
        $resultados = $tareaModel->activeResultados();
        $usuarios = $tareaModel->activeUsuarios();

        $this->view('convenios/show', compact('convenio', 'notas', 'tareas', 'historial', 'tiposTarea', 'resultados', 'usuarios'), [
            'title' => 'Convenio #' . $convenioId,
        ]);
    }

    public function updateDatos(string $id): void
    {
        $this->ensureAccess();
        $convenioId = (int) $id;
        set_flash('error', 'Los datos del convenio son de solo lectura. Solo se permite registrar notas y tareas.');
        redirect('convenios/' . $convenioId);
    }

    public function storeNota(string $id): void
    {
        $this->ensureAccess();
        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('convenios/' . (int) $id);
        }

        $convenioId = (int) $id;
        $nota = trim((string) ($_POST['nota'] ?? ''));
        if ($nota === '') {
            set_flash('error', 'La nota no puede estar vacía.');
            redirect('convenios/' . $convenioId);
        }

        try {
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);
            $this->ensureUserNotificationsTable($localDb);

            $model = new ConvenioNota();
            $notaId = $model->createNota($convenioId, (int) (Auth::id() ?? 0), $nota);
            $uploadErrors = $this->storeConvenioNoteAttachments($localDb, $convenioId, $notaId, $_FILES['attachments'] ?? null);

            $mentionIds = $this->extractMentionUserIds($nota, $localDb, (int) (Auth::id() ?? 0));
            if (!empty($mentionIds)) {
                $this->createMentionNotifications(
                    $localDb,
                    $mentionIds,
                    'Mención en nota de convenio',
                    'Te mencionaron en una nota de convenio.',
                    base_url('convenios/' . $convenioId)
                );
            }

            $this->registerHistorial($localDb, $convenioId, null, 'nota_creada', 'Se registró una nota interna.');

            if (!empty($uploadErrors)) {
                set_flash('warning', 'Nota guardada con advertencias en adjuntos: ' . implode(' ', $uploadErrors));
            } else {
                set_flash('success', 'Nota registrada correctamente.');
            }
        } catch (Throwable $e) {
            set_flash('error', 'Error al registrar nota: ' . $e->getMessage());
        }

        redirect('convenios/' . $convenioId);
    }

    public function updateNota(string $id, string $notaId): void
    {
        $this->ensureAccess();
        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('convenios/' . (int) $id);
        }

        $convenioId = (int) $id;
        $noteId = (int) $notaId;
        $texto = trim((string) ($_POST['nota'] ?? ''));
        $removeIds = array_values(array_unique(array_map('intval', (array) ($_POST['remove_attachment_ids'] ?? []))));

        if ($convenioId <= 0 || $noteId <= 0 || $texto === '') {
            set_flash('error', 'Datos inválidos para actualizar la nota.');
            redirect('convenios/' . $convenioId);
        }

        try {
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);
            $this->ensureUserNotificationsTable($localDb);

            $model = new ConvenioNota();
            $updated = $model->updateNota($convenioId, $noteId, $texto);
            if (!$updated) {
                set_flash('error', 'No se encontró la nota a editar.');
                redirect('convenios/' . $convenioId);
            }

            $this->removeConvenioAttachments($localDb, $convenioId, $noteId, $removeIds);
            $uploadErrors = $this->storeConvenioNoteAttachments($localDb, $convenioId, $noteId, $_FILES['attachments'] ?? null);

            $mentionIds = $this->extractMentionUserIds($texto, $localDb, (int) (Auth::id() ?? 0));
            if (!empty($mentionIds)) {
                $this->createMentionNotifications(
                    $localDb,
                    $mentionIds,
                    'Mención en nota de convenio',
                    'Te mencionaron en una nota editada de convenio.',
                    base_url('convenios/' . $convenioId)
                );
            }

            $this->registerHistorial($localDb, $convenioId, null, 'nota_editada', 'Se editó una nota interna.');
            if (!empty($uploadErrors)) {
                set_flash('warning', 'Nota editada con advertencias en adjuntos: ' . implode(' ', $uploadErrors));
            } else {
                set_flash('success', 'Nota actualizada correctamente.');
            }
        } catch (Throwable $e) {
            set_flash('error', 'Error al actualizar la nota: ' . $e->getMessage());
        }

        redirect('convenios/' . $convenioId);
    }

    public function noteAttachment(string $id, string $notaId, string $attachmentId): void
    {
        $this->ensureAccess();
        $convenioId = (int) $id;
        $noteId = (int) $notaId;
        $attId = (int) $attachmentId;
        if ($convenioId <= 0 || $noteId <= 0 || $attId <= 0) {
            http_response_code(400);
            echo 'Adjunto inválido.';
            return;
        }

        try {
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);
            $stmt = $localDb->prepare('SELECT filename_original, mime, size_bytes, storage_path
                FROM convenio_nota_adjuntos
                WHERE id = :id AND convenio_id = :convenio_id AND nota_id = :nota_id
                LIMIT 1');
            $stmt->execute([
                'id' => $attId,
                'convenio_id' => $convenioId,
                'nota_id' => $noteId,
            ]);
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

            $basePath = realpath(ROOT_PATH . '/uploads/convenios-notas');
            $realFile = realpath($fullPath);
            if (!$this->pathStartsWith($realFile, $basePath)) {
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

    private function storeConvenioNoteAttachments(PDO $db, int $convenioId, int $noteId, $rawFiles): array
    {
        $errors = [];
        if ($convenioId <= 0 || $noteId <= 0 || !is_array($rawFiles) || !isset($rawFiles['name'])) {
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
        $uploadDir = ROOT_PATH . '/uploads/convenios-notas/' . $convenioId . '/' . $noteId;
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

            $stmt = $db->prepare('INSERT INTO convenio_nota_adjuntos (convenio_id, nota_id, filename_original, filename_storage, mime, size_bytes, storage_path, created_at)
                VALUES (:convenio_id, :nota_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, NOW())');
            $stmt->execute([
                'convenio_id' => $convenioId,
                'nota_id' => $noteId,
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

    private function removeConvenioAttachments(PDO $db, int $convenioId, int $noteId, array $removeIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $removeIds), static fn($value) => $value > 0));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, storage_path FROM convenio_nota_adjuntos WHERE convenio_id = ? AND nota_id = ? AND id IN ($placeholders)";
        $params = array_merge([$convenioId, $noteId], $ids);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        if (empty($rows)) {
            return;
        }

        $deleteIds = [];
        foreach ($rows as $row) {
            $deleteIds[] = (int) ($row['id'] ?? 0);
            $path = (string) ($row['storage_path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }

        $deleteIds = array_values(array_filter($deleteIds, static fn($value) => $value > 0));
        if (!empty($deleteIds)) {
            $deletePlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $deleteStmt = $db->prepare("DELETE FROM convenio_nota_adjuntos WHERE id IN ($deletePlaceholders)");
            $deleteStmt->execute($deleteIds);
        }
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

    public function storeTarea(string $id): void
    {
        $this->ensureAccess();
        if (!verify_csrf($_POST['_token'] ?? null)) {
            set_flash('error', 'Token CSRF inválido.');
            redirect('convenios/' . (int) $id);
        }

        $convenioId = (int) $id;
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $propietarioId = (int) ($_POST['propietario_id'] ?? 0);

        if ($titulo === '' || $propietarioId <= 0) {
            set_flash('error', 'Título y propietario son obligatorios para la tarea.');
            redirect('convenios/' . $convenioId);
        }

        $relacionados = array_values(array_unique(array_map('intval', (array) ($_POST['relacionados'] ?? []))));
        $colaboradores = array_values(array_unique(array_map('intval', (array) ($_POST['colaboradores'] ?? []))));

        try {
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);

            $model = new ConvenioTarea();
            $taskId = $model->createTarea([
                'convenio_id' => $convenioId,
                'tipo_tarea_id' => ($_POST['tipo_tarea_id'] ?? '') !== '' ? (int) $_POST['tipo_tarea_id'] : null,
                'resultado_id' => ($_POST['resultado_id'] ?? '') !== '' ? (int) $_POST['resultado_id'] : null,
                'titulo' => $titulo,
                'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
                'fecha_vencimiento' => trim((string) ($_POST['fecha_vencimiento'] ?? '')),
                'hora_vencimiento' => trim((string) ($_POST['hora_vencimiento'] ?? '')),
                'propietario_id' => $propietarioId,
                'estado' => 'pendiente',
                'completado' => 0,
                'created_by' => (int) (Auth::id() ?? 0),
            ], $relacionados, $colaboradores);

            $detalle = 'Tarea creada: ' . $titulo;
            $this->registerHistorial($localDb, $convenioId, $taskId, 'tarea_creada', $detalle);

            set_flash('success', 'Tarea registrada correctamente.');
        } catch (Throwable $e) {
            set_flash('error', 'Error al registrar tarea: ' . $e->getMessage());
        }

        redirect('convenios/' . $convenioId);
    }

    public function updateTareaEstado(string $id, string $tareaId): void
    {
        $this->ensureAccess();
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (!verify_csrf($_POST['_token'] ?? null)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Token CSRF inválido.']);
                return;
            }
            set_flash('error', 'Token CSRF inválido.');
            redirect('convenios/' . (int) $id);
        }

        $convenioId = (int) $id;
        $taskId = (int) $tareaId;
        $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));
        $estadosValidos = ['pendiente', 'completada'];
        if (!in_array($estado, $estadosValidos, true)) {
            $estado = 'pendiente';
        }

        $completado = $estado === 'completada' ? 1 : 0;

        try {
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);

            $taskMeta = $this->findTareaMeta($localDb, $convenioId, $taskId);
            if (!$taskMeta) {
                if ($isAjax) {
                    http_response_code(404);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'message' => 'La tarea no existe o no pertenece al convenio.']);
                    return;
                }

                set_flash('error', 'La tarea no existe o no pertenece al convenio.');
                redirect('convenios/' . $convenioId);
            }

            $alreadyCompleted = ((string) ($taskMeta['estado'] ?? '') === 'completada') || ((int) ($taskMeta['completado'] ?? 0) === 1);
            if ($alreadyCompleted) {
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'ok' => true,
                        'message' => 'La tarea ya está completada y no se puede modificar.',
                        'estado' => 'completada',
                        'completado' => 1,
                    ]);
                    return;
                }

                set_flash('error', 'La tarea ya está completada y no se puede modificar.');
                redirect('convenios/' . $convenioId);
            }

            $model = new ConvenioTarea();
            $model->updateEstado($taskId, $estado, $completado);
            $this->registerHistorial($localDb, $convenioId, $taskId, 'estado_actualizado', 'Estado: ' . $estado . ' | Completado: ' . $completado);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true,
                    'message' => 'Estado actualizado.',
                    'estado' => $estado,
                    'completado' => $completado,
                ]);
                return;
            }

            set_flash('success', 'Estado de tarea actualizado.');
        } catch (Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Error al actualizar tarea: ' . $e->getMessage()]);
                return;
            }
            set_flash('error', 'Error al actualizar tarea: ' . $e->getMessage());
        }

        redirect('convenios/' . $convenioId);
    }

    public function updateTareaParticipantes(string $id, string $tareaId): void
    {
        $this->ensureAccess();
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (!verify_csrf($_POST['_token'] ?? null)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Token CSRF inválido.']);
                return;
            }
            set_flash('error', 'Token CSRF inválido.');
            redirect('convenios/' . (int) $id);
        }

        $convenioId = (int) $id;
        $taskId = (int) $tareaId;

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
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);

            $taskMeta = $this->findTareaMeta($localDb, $convenioId, $taskId);
            if (!$taskMeta) {
                if ($isAjax) {
                    http_response_code(404);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'message' => 'La tarea no existe o no pertenece al convenio.']);
                    return;
                }

                set_flash('error', 'La tarea no existe o no pertenece al convenio.');
                redirect('convenios/' . $convenioId);
            }

            $alreadyCompleted = ((string) ($taskMeta['estado'] ?? '') === 'completada') || ((int) ($taskMeta['completado'] ?? 0) === 1);
            if ($alreadyCompleted) {
                if ($isAjax) {
                    http_response_code(409);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'message' => 'La tarea está completada y quedó bloqueada.']);
                    return;
                }

                set_flash('error', 'La tarea está completada y quedó bloqueada.');
                redirect('convenios/' . $convenioId);
            }

            $model = new ConvenioTarea();
            $updated = $model->updateParticipantes($taskId, $convenioId, array_values($relacionados), array_values($colaboradores));

            if (!$updated) {
                if ($isAjax) {
                    http_response_code(404);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'message' => 'La tarea no existe o no pertenece al convenio.']);
                    return;
                }
                set_flash('error', 'La tarea no existe o no pertenece al convenio.');
                redirect('convenios/' . $convenioId);
            }

            $detalle = 'Relacionados: ' . $this->formatUsuariosList($localDb, array_values($relacionados));
            $detalle .= ' | Colaboradores: ' . $this->formatUsuariosList($localDb, array_values($colaboradores));
            $this->registerHistorial($localDb, $convenioId, $taskId, 'participantes_actualizados', $detalle);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => 'Participantes actualizados.']);
                return;
            }

            set_flash('success', 'Relacionados y colaboradores actualizados.');
        } catch (Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Error al actualizar participantes: ' . $e->getMessage()]);
                return;
            }
            set_flash('error', 'Error al actualizar participantes: ' . $e->getMessage());
        }

        redirect('convenios/' . $convenioId);
    }

    public function updateTareaResultado(string $id, string $tareaId): void
    {
        $this->ensureAccess();
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (!verify_csrf($_POST['_token'] ?? null)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Token CSRF inválido.']);
                return;
            }
            set_flash('error', 'Token CSRF inválido.');
            redirect('convenios/' . (int) $id);
        }

        $convenioId = (int) $id;
        $taskId = (int) $tareaId;
        $resultadoId = ($_POST['resultado_id'] ?? '') !== '' ? (int) $_POST['resultado_id'] : null;

        try {
            $localDb = Database::getInstance()->connection();
            $this->ensureConveniosTables($localDb);

            $taskMeta = $this->findTareaMeta($localDb, $convenioId, $taskId);
            if (!$taskMeta) {
                if ($isAjax) {
                    http_response_code(404);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'message' => 'La tarea no existe o no pertenece al convenio.']);
                    return;
                }

                set_flash('error', 'La tarea no existe o no pertenece al convenio.');
                redirect('convenios/' . $convenioId);
            }

            $alreadyCompleted = ((string) ($taskMeta['estado'] ?? '') === 'completada') || ((int) ($taskMeta['completado'] ?? 0) === 1);
            if ($alreadyCompleted) {
                if ($isAjax) {
                    http_response_code(409);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'message' => 'La tarea está completada y quedó bloqueada.']);
                    return;
                }

                set_flash('error', 'La tarea está completada y quedó bloqueada.');
                redirect('convenios/' . $convenioId);
            }

            $model = new ConvenioTarea();
            $model->updateResultado($taskId, $resultadoId);

            $resultadoNombre = '-';
            if ($resultadoId !== null && $resultadoId > 0) {
                $stmt = $localDb->prepare("SELECT nombre FROM resultados WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $resultadoId]);
                $resultadoNombre = trim((string) ($stmt->fetchColumn() ?: '-'));
            }

            $this->registerHistorial($localDb, $convenioId, $taskId, 'resultado_actualizado', 'Resultado: ' . $resultadoNombre);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => 'Resultado actualizado.']);
                return;
            }

            set_flash('success', 'Resultado actualizado.');
        } catch (Throwable $e) {
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Error al actualizar resultado: ' . $e->getMessage()]);
                return;
            }
            set_flash('error', 'Error al actualizar resultado: ' . $e->getMessage());
        }

        redirect('convenios/' . $convenioId);
    }
}
