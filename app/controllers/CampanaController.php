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

		$sql = "SELECT c.*, u.nombre as usuario_nombre 
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

		// Obtener correos configurados
		$cuentas = $db->query("SELECT DISTINCT correo_cuenta FROM mail_accounts WHERE estado = 'activo' ORDER BY correo_cuenta")->fetchAll() ?: [];
		
		// Obtener períodos (si existen en sistema académico)
		$periodos = [];

		$this->view('campanas/create', compact('cuentas', 'periodos'), ['title' => 'Nueva Campaña']);
	}

	public function store(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('campanas');
		}

		$db = Database::getInstance()->connection();
		$this->ensureCampanasTables($db);

		$titulo = trim($_POST['titulo'] ?? '');
		$asunto = trim($_POST['asunto'] ?? '');
		$contenido = $_POST['contenido'] ?? '';
		$correo_origen = trim($_POST['correo_origen'] ?? '');
		$tipo_destinatarios = $_POST['tipo_destinatarios'] ?? 'todos';
		$periodo_id = (int)($_POST['periodo_id'] ?? 0) ?: null;

		if (empty($titulo) || empty($asunto) || empty($contenido) || empty($correo_origen)) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('campanas/create');
		}

		try {
			$db->beginTransaction();

			$stmt = $db->prepare("
				INSERT INTO campanas (titulo, asunto, contenido, correo_origen, tipo_destinatarios, periodo_id, usuario_id, estado)
				VALUES (:titulo, :asunto, :contenido, :correo_origen, :tipo_destinatarios, :periodo_id, :usuario_id, 'borrador')
			");

			$stmt->execute([
				'titulo' => $titulo,
				'asunto' => $asunto,
				'contenido' => $contenido,
				'correo_origen' => $correo_origen,
				'tipo_destinatarios' => $tipo_destinatarios,
				'periodo_id' => $periodo_id,
				'usuario_id' => Auth::id(),
			]);

			$campana_id = (int) $db->lastInsertId();

			// Agregar destinatarios según tipo
			$this->agregarDestinatarios($db, $campana_id, $tipo_destinatarios, $periodo_id);

			AuditLogger::log('CREATE', 'campanas', $campana_id, null, [
				'titulo' => $titulo,
				'asunto' => $asunto,
				'tipo_destinatarios' => $tipo_destinatarios,
				'estado' => 'borrador'
			]);

			$db->commit();
			set_flash('success', 'Campaña creada correctamente.');
		} catch (Throwable $e) {
			$db->rollBack();
			set_flash('error', 'Error al crear campaña: ' . $e->getMessage());
		}

		redirect('campanas');
	}

	private function agregarDestinatarios(PDO $db, int $campana_id, string $tipo, ?int $periodo_id): void
	{
		if ($tipo === 'todos') {
			// Obtener todos los contactos con correo
			$stmt = $db->query("SELECT id, nombre, email FROM contactos WHERE email IS NOT NULL AND email != '' AND estado = 'activo' ORDER BY id");
		} elseif ($tipo === 'periodo' && $periodo_id) {
			// Obtener estudiantes del período
			$stmt = $db->prepare("
				SELECT c.id, c.nombre, c.email 
				FROM contactos c
				INNER JOIN estudiantes e ON c.id = e.contacto_id
				INNER JOIN matriculas m ON e.id = m.estudiante_id
				WHERE m.periodo_id = :periodo_id AND c.email IS NOT NULL AND c.email != '' AND c.estado = 'activo'
			");
			$stmt->execute(['periodo_id' => $periodo_id]);
		} else {
			return;
		}

		$contactos = $stmt->fetchAll() ?: [];
		$insert = $db->prepare("
			INSERT INTO campana_destinatarios (campana_id, contacto_id, correo_destino, nombre_destino)
			VALUES (:campana_id, :contacto_id, :correo_destino, :nombre_destino)
		");

		$total = 0;
		foreach ($contactos as $contacto) {
			$insert->execute([
				'campana_id' => $campana_id,
				'contacto_id' => $contacto['id'],
				'correo_destino' => $contacto['email'],
				'nombre_destino' => $contacto['nombre'],
			]);
			$total++;
		}

		// Actualizar total de destinatarios
		$update = $db->prepare("UPDATE campanas SET total_destinatarios = :total WHERE id = :campana_id");
		$update->execute(['total' => $total, 'campana_id' => $campana_id]);
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

		// Solo el propietario puede editar (o admin con acceso a módulo)

		$cuentas = $db->query("SELECT DISTINCT correo_cuenta FROM mail_accounts WHERE estado = 'activo' ORDER BY correo_cuenta")->fetchAll() ?: [];
		$periodos = [];

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

		// Solo borrador se puede editar
		if ($campana['estado'] !== 'borrador') {
			set_flash('error', 'Solo se pueden editar campañas en borrador.');
			redirect('campanas');
		}

		$titulo = trim($_POST['titulo'] ?? '');
		$asunto = trim($_POST['asunto'] ?? '');
		$contenido = $_POST['contenido'] ?? '';
		$correo_origen = trim($_POST['correo_origen'] ?? '');

		if (empty($titulo) || empty($asunto) || empty($contenido) || empty($correo_origen)) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('campanas/edit/' . $id);
		}

		try {
			$before = [
				'titulo' => $campana['titulo'],
				'asunto' => $campana['asunto'],
				'correo_origen' => $campana['correo_origen']
			];

			$stmt = $db->prepare("
				UPDATE campanas 
				SET titulo = :titulo, asunto = :asunto, contenido = :contenido, correo_origen = :correo_origen
				WHERE id = :id
			");

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
				'correo_origen' => $correo_origen
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

		if ($campana['estado'] !== 'borrador') {
			set_flash('error', 'La campaña ya fue enviada.');
			redirect('campanas');
		}

		try {
			$db->beginTransaction();

			// Cambiar estado a enviando
			$update = $db->prepare("UPDATE campanas SET estado = 'enviando', fecha_envio = NOW() WHERE id = :id");
			$update->execute(['id' => $id]);

			// Crear registros en cola_envios
			$destinatarios = $db->query("SELECT id FROM campana_destinatarios WHERE campana_id = $id AND estado = 'pendiente'")->fetchAll() ?: [];

			$insert_cola = $db->prepare("INSERT INTO cola_envios (campana_id, destinatario_id, estado) VALUES (:campana_id, :destinatario_id, 'pendiente')");

			foreach ($destinatarios as $dest) {
				$insert_cola->execute([
					'campana_id' => $id,
					'destinatario_id' => $dest['id'],
				]);
			}

			AuditLogger::log('UPDATE', 'campanas', $id, ['estado' => 'borrador'], ['estado' => 'enviando']);

			$db->commit();
			set_flash('success', 'Campaña enviándose. Se procesará en segundo plano.');
		} catch (Throwable $e) {
			$db->rollBack();
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

		// Solo en borrador se puede eliminar
		if ($campana['estado'] !== 'borrador') {
			set_flash('error', 'Solo se pueden eliminar campañas en borrador.');
			redirect('campanas');
		}

		try {
			$stmt = $db->prepare("UPDATE campanas SET deleted_at = NOW() WHERE id = :id");
			$stmt->execute(['id' => $id]);

			AuditLogger::log('DELETE', 'campanas', $id, ['titulo' => $campana['titulo']], null);

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
		echo $campana['contenido'];
		exit;
	}
}
