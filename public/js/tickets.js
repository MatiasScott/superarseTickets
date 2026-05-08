console.debug('Modulo tickets cargado');

document.addEventListener('DOMContentLoaded', () => {
	const scopes = document.querySelectorAll('[data-auth-fallback-scope="true"]');

	scopes.forEach((scope) => {
		const hasTechnicalAuthError = /user authentication failed/i.test(scope.textContent || '');
		if (!hasTechnicalAuthError) {
			return;
		}

		scope.innerHTML = `
			<div class="empty-panel" role="status" aria-live="polite">
				<div class="empty-icon" aria-hidden="true">⚠</div>
				<div>No se pudo autenticar la integración para este panel.</div>
				<div>Revisa las credenciales en Configuración y vuelve a intentar.</div>
			</div>
		`;
	});
});
