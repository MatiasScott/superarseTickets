<?php

class AuthController extends Controller
{
	public function showLogin(): void
	{
		if (Auth::check()) {
			redirect('dashboard');
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

		$username = trim((string) ($_POST['username'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');

		if ($username === '' || $password === '') {
			set_flash('error', 'Debes ingresar usuario y clave.');
			redirect('login');
		}

		if (!Auth::attempt($username, $password)) {
			set_flash('error', 'Credenciales invalidas.');
			redirect('login');
		}

		redirect('dashboard');
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
}
