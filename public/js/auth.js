document.addEventListener('DOMContentLoaded', () => {
	// Focus en campo de credencial si existe (página de login)
	const credentialInput = document.querySelector('#credential');
	if (credentialInput) {
		credentialInput.focus();
	}

	// Validación de fortaleza de contraseña
	const newPasswordInput = document.querySelector('#new_password');
	const confirmPasswordInput = document.querySelector('#confirm_password');
	const passwordStrengthDiv = document.querySelector('#password_strength');
	const passwordMatchSpan = document.querySelector('#password_match');

	if (newPasswordInput && passwordStrengthDiv) {
		newPasswordInput.addEventListener('input', () => {
			const password = newPasswordInput.value;
			const strength = calculatePasswordStrength(password);

			// Mostrar indicador de fortaleza
			if (password.length > 0) {
				passwordStrengthDiv.className = 'password-strength ' + strength.level;
				passwordStrengthDiv.innerHTML = '<small class="d-block mt-1">' + strength.message + '</small>';
			} else {
				passwordStrengthDiv.innerHTML = '';
			}

			// Validar coincidencia de contraseñas
			if (confirmPasswordInput) {
				validatePasswordMatch();
			}
		});
	}

	if (confirmPasswordInput && newPasswordInput) {
		confirmPasswordInput.addEventListener('input', validatePasswordMatch);
	}

	function calculatePasswordStrength(password) {
		let strength = 0;
		const checks = {
			length: password.length >= 8,
			uppercase: /[A-Z]/.test(password),
			lowercase: /[a-z]/.test(password),
			numbers: /[0-9]/.test(password),
			special: /[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/.test(password),
		};

		Object.values(checks).forEach(check => {
			if (check) strength++;
		});

		let level, message;
		if (strength === 0) {
			level = 'weak';
			message = 'Ingresa una contraseña';
		} else if (strength <= 2) {
			level = 'weak';
			message = 'Contraseña débil';
		} else if (strength <= 3) {
			level = 'fair';
			message = 'Contraseña regular';
		} else if (strength <= 4) {
			level = 'good';
			message = 'Contraseña buena';
		} else {
			level = 'strong';
			message = 'Contraseña fuerte';
		}

		return { level, message, checks };
	}

	function validatePasswordMatch() {
		const newPassword = newPasswordInput.value;
		const confirmPassword = confirmPasswordInput.value;

		if (confirmPassword.length === 0) {
			passwordMatchSpan.textContent = '';
			confirmPasswordInput.classList.remove('is-invalid', 'is-valid');
		} else if (newPassword === confirmPassword) {
			passwordMatchSpan.textContent = 'Las contraseñas coinciden ✓';
			passwordMatchSpan.className = 'text-success d-block mt-1';
			confirmPasswordInput.classList.add('is-valid');
			confirmPasswordInput.classList.remove('is-invalid');
		} else {
			passwordMatchSpan.textContent = 'Las contraseñas no coinciden';
			passwordMatchSpan.className = 'text-danger d-block mt-1';
			confirmPasswordInput.classList.add('is-invalid');
			confirmPasswordInput.classList.remove('is-valid');
		}
	}
});
