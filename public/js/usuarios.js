document.addEventListener('DOMContentLoaded', () => {
	// Validación de formularios
	const forms = document.querySelectorAll('form');
	forms.forEach(form => {
		form.addEventListener('submit', (e) => {
			if (!form.checkValidity()) {
				e.preventDefault();
				e.stopPropagation();
			}
			form.classList.add('was-validated');
		});
	});

	// Auto-focus en primer input
	const firstInput = document.querySelector('input:not([type="hidden"])');
	if (firstInput && !firstInput.value) {
		firstInput.focus();
	}

	// Validación de teléfono
	const phoneInput = document.querySelector('input[type="tel"]');
	if (phoneInput) {
		phoneInput.addEventListener('change', () => {
			const phone = phoneInput.value;
			if (phone && !/^[+]?[0-9\s\-()]+$/.test(phone)) {
				phoneInput.classList.add('is-invalid');
			} else if (phone) {
				phoneInput.classList.remove('is-invalid');
				phoneInput.classList.add('is-valid');
			}
		});
	}

	// Validación de email
	const emailInputs = document.querySelectorAll('input[type="email"]');
	emailInputs.forEach(input => {
		input.addEventListener('change', () => {
			const email = input.value;
			if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
				input.classList.add('is-invalid');
			} else if (email) {
				input.classList.remove('is-invalid');
				input.classList.add('is-valid');
			}
		});
	});

	// Validación de contraseña coincidentes (en formulario de creación)
	const passwordInput = document.querySelector('#password');
	const confirmInput = document.querySelector('#confirm_password');

	if (passwordInput && confirmInput) {
		const validateMatch = () => {
			if (confirmInput.value && passwordInput.value !== confirmInput.value) {
				confirmInput.classList.add('is-invalid');
			} else if (confirmInput.value) {
				confirmInput.classList.remove('is-invalid');
				confirmInput.classList.add('is-valid');
			} else {
				confirmInput.classList.remove('is-invalid', 'is-valid');
			}
		};

		passwordInput.addEventListener('change', validateMatch);
		confirmInput.addEventListener('change', validateMatch);
	}

	// Confirmación de eliminación
	const deleteButtons = document.querySelectorAll('form[onsubmit*="confirm"]');
	deleteButtons.forEach(btn => {
		btn.addEventListener('submit', (e) => {
			const message = btn.getAttribute('onsubmit');
			if (!confirm('¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.')) {
				e.preventDefault();
			}
		});
	});

	// Animación de tabla
	const tableRows = document.querySelectorAll('table tbody tr');
	tableRows.forEach((row, index) => {
		row.style.animation = `fadeIn 0.3s ease forwards`;
		row.style.animationDelay = `${index * 0.05}s`;
	});
});

// Estilos de animación
const style = document.createElement('style');
style.textContent = `
	@keyframes fadeIn {
		from {
			opacity: 0;
			transform: translateY(-10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
`;
document.head.appendChild(style);
