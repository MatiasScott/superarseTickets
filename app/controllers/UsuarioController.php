<?php

class UsuarioController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$usuarios = [];

		try {
			$usuarios = (new Usuario())->all(100);
		} catch (Throwable $e) {
			$usuarios = [];
		}

		$this->view('usuarios/index', compact('usuarios'), [
			'title' => 'Gestión de Cuentas',
		]);
	}

	public function create(): void
	{
		Auth::requireAuth();

		$roles = [];
		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->query("SELECT id, nombre FROM roles ORDER BY nombre");
			$roles = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$roles = [];
		}

		$this->view('usuarios/create', compact('roles'), [
			'title' => 'Crear Cuenta',
		]);
	}

	public function store(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('usuarios/create');
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$email = trim((string) ($_POST['email'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$confirm_password = (string) ($_POST['confirm_password'] ?? '');
		$rol_id = (int) ($_POST['rol_id'] ?? 0);
		$telefono = trim((string) ($_POST['telefono'] ?? ''));
		$estado = $_POST['estado'] ?? 'activo';

		if (empty($nombre) || empty($email) || empty($password)) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('usuarios/create');
		}

		if ($password !== $confirm_password) {
			set_flash('error', 'Las contraseñas no coinciden.');
			redirect('usuarios/create');
		}

		$validation = validate_password_strength($password);
		if (!$validation['valid']) {
			set_flash('error', implode(' ', $validation['errors']));
			redirect('usuarios/create');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			set_flash('error', 'El correo electrónico no es válido.');
			redirect('usuarios/create');
		}

		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
		$stmt->execute(['email' => $email]);
		if ($stmt->fetch() !== false) {
			set_flash('error', 'El correo ya está registrado.');
			redirect('usuarios/create');
		}

		$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

		try {
			$usuario = new Usuario();
			$usuario_id = $usuario->create([
				'nombre' => $nombre,
				'email' => $email,
				'password' => $hashed_password,
				'rol_id' => $rol_id > 0 ? $rol_id : null,
				'telefono' => !empty($telefono) ? $telefono : null,
				'estado' => $estado,
			]);

			if ($usuario_id) {
				set_flash('success', 'Cuenta creada exitosamente.');
				redirect('usuarios');
			}

			set_flash('error', 'Error al crear la cuenta.');
			redirect('usuarios/create');
		} catch (Throwable $e) {
			set_flash('error', 'Error al crear la cuenta: ' . $e->getMessage());
			redirect('usuarios/create');
		}
	}

	public function show(int $id): void
	{
		Auth::requireAuth();

		try {
			$usuario = (new Usuario())->find($id);
			if (!$usuario) {
				set_flash('error', 'Usuario no encontrado.');
				redirect('usuarios');
			}

			$this->view('usuarios/show', compact('usuario'), [
				'title' => 'Detalles de Usuario: ' . e($usuario['nombre']),
			]);
		} catch (Throwable $e) {
			set_flash('error', 'Error al cargar el usuario.');
			redirect('usuarios');
		}
	}

	public function edit(int $id): void
	{
		Auth::requireAuth();

		$usuario = null;
		$roles = [];

		try {
			$usuario = (new Usuario())->find($id);
			if (!$usuario) {
				set_flash('error', 'Usuario no encontrado.');
				redirect('usuarios');
			}

			$db = Database::getInstance()->connection();
			$stmt = $db->query("SELECT id, nombre FROM roles ORDER BY nombre");
			$roles = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			set_flash('error', 'Error al cargar el usuario.');
			redirect('usuarios');
		}

		$this->view('usuarios/edit', compact('usuario', 'roles'), [
			'title' => 'Editar Usuario: ' . e($usuario['nombre']),
		]);
	}

	public function update(int $id): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('usuarios/' . $id . '/edit');
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$email = trim((string) ($_POST['email'] ?? ''));
		$rol_id = (int) ($_POST['rol_id'] ?? 0);
		$telefono = trim((string) ($_POST['telefono'] ?? ''));
		$estado = $_POST['estado'] ?? 'activo';

		if (empty($nombre) || empty($email)) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('usuarios/' . $id . '/edit');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			set_flash('error', 'El correo electrónico no es válido.');
			redirect('usuarios/' . $id . '/edit');
		}

		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1");
		$stmt->execute(['email' => $email, 'id' => $id]);
		if ($stmt->fetch() !== false) {
			set_flash('error', 'El correo ya está registrado por otro usuario.');
			redirect('usuarios/' . $id . '/edit');
		}

		try {
			$usuario = new Usuario();
			$result = $usuario->update($id, [
				'nombre' => $nombre,
				'email' => $email,
				'rol_id' => $rol_id > 0 ? $rol_id : null,
				'telefono' => !empty($telefono) ? $telefono : null,
				'estado' => $estado,
			]);

			if ($result) {
				set_flash('success', 'Usuario actualizado correctamente.');
				redirect('usuarios');
			}

			set_flash('error', 'Error al actualizar el usuario.');
			redirect('usuarios/' . $id . '/edit');
		} catch (Throwable $e) {
			set_flash('error', 'Error al actualizar: ' . $e->getMessage());
			redirect('usuarios/' . $id . '/edit');
		}
	}

	public function delete(int $id): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('usuarios');
		}

		if ($id === 1) {
			set_flash('error', 'No puedes eliminar el usuario administrador.');
			redirect('usuarios');
		}

		try {
			$usuario = new Usuario();
			if ($usuario->delete($id)) {
				set_flash('success', 'Usuario eliminado correctamente.');
				redirect('usuarios');
			}

			set_flash('error', 'Error al eliminar el usuario.');
			redirect('usuarios');
		} catch (Throwable $e) {
			set_flash('error', 'Error al eliminar: ' . $e->getMessage());
			redirect('usuarios');
		}
	}
}
