<?php

class AuthController extends Controller
{
	public function showLogin(): void
	{
		if (Auth::check()) {
			redirect(Auth::homePath());
		}

		$this->view('auth/login', [], [
			'title' => 'Iniciar sesion',
			'showSidebar' => false,
		]);
	}

	public function login(): void
	{
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('login');
		}

		$credential = trim((string) ($_POST['credential'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');

		if ($credential === '' || $password === '') {
			set_flash('error', 'Debes ingresar correo o nombre y clave.');
			redirect('login');
		}

		if (!Auth::attempt($credential, $password)) {
			set_flash('error', 'Credenciales invalidas.');
			redirect('login');
		}

		// Debug: confirmar que sesión se seteó
		if (!Auth::check() || Auth::id() === null) {
			set_flash('error', 'Error al establecer sesión. Intenta de nuevo.');
			redirect('login');
		}

		redirect(Auth::homePath());
	}

	public function logout(): void
	{
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('dashboard');
		}

		Auth::logout();
		set_flash('success', 'Sesion cerrada correctamente.');
		redirect('login');
	}

	public function showChangePassword(): void
	{
		Auth::requireAuth();

		$this->view('auth/change-password', [], [
			'title' => 'Cambiar Contraseña',
			'showSidebar' => true,
		]);
	}

	public function changePassword(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('change-password');
		}

		$current_password = (string) ($_POST['current_password'] ?? '');
		$new_password = (string) ($_POST['new_password'] ?? '');
		$confirm_password = (string) ($_POST['confirm_password'] ?? '');

		if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('change-password');
		}

		if ($new_password !== $confirm_password) {
			set_flash('error', 'Las contraseñas nuevas no coinciden.');
			redirect('change-password');
		}

		$validation = validate_password_strength($new_password);
		if (!$validation['valid']) {
			set_flash('error', implode(' ', $validation['errors']));
			redirect('change-password');
		}

		if (!Auth::verifyCurrentPassword($current_password)) {
			set_flash('error', 'La contraseña actual es incorrecta.');
			redirect('change-password');
		}

		if (Auth::updatePassword(Auth::id(), $new_password)) {
			set_flash('success', 'Contraseña actualizada correctamente.');
			redirect('dashboard');
		}

		set_flash('error', 'Error al actualizar la contraseña.');
		redirect('change-password');
	}
}
