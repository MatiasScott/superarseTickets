console.debug('Modulo tickets cargado');

document.addEventListener('DOMContentLoaded', () => {
	const dashboardRoot = document.querySelector('[data-ticket-dashboard-live="true"]');
	if (dashboardRoot) {
		const endpoint = dashboardRoot.getAttribute('data-ticket-dashboard-url') || '';
		const escapeHtml = (value) => String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
		const statNodes = {
			sin_resolver: dashboardRoot.querySelector('[data-ticket-stat="sin_resolver"]'),
			vencidos: dashboardRoot.querySelector('[data-ticket-stat="vencidos"]'),
			vencen_hoy: dashboardRoot.querySelector('[data-ticket-stat="vencen_hoy"]')
		};
		const groupList = document.querySelector('[data-ticket-group-list]');
		const updatedAt = dashboardRoot.querySelector('[data-ticket-updated-at]');

		const renderGroups = (groups) => {
			if (!groupList) return;
			if (!Array.isArray(groups) || groups.length === 0) {
				groupList.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay datos para mostrar.</td></tr>';
				return;
			}

			groupList.innerHTML = groups
				.map((row) => {
					const url = String(row.url ?? '');
					const total = String(row.total ?? 0);
					const grupo = escapeHtml(row.grupo ?? 'Sin asignar');
					const abiertos = String(row.abiertos ?? 0);
					const vencidos = String(row.vencidos ?? 0);
					const porVencer = String(row.por_vencer ?? 0);
					return `<tr><td>${grupo}</td><td class="text-end">${abiertos}</td><td class="text-end text-danger">${vencidos}</td><td class="text-end text-warning">${porVencer}</td><td class="text-end"><a href="${url}" class="fw-semibold">${total}</a></td></tr>`;
				})
				.join('');
		};

		const refreshDashboard = async () => {
			if (!endpoint) return;
			try {
				const response = await fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				if (!response.ok) return;
				const payload = await response.json();
				if (!payload || payload.ok !== true || !payload.data) return;

				const data = payload.data;
				const stats = data.stats || {};
				if (statNodes.sin_resolver) statNodes.sin_resolver.textContent = String(stats.sin_resolver ?? 0);
				if (statNodes.vencidos) statNodes.vencidos.textContent = String(stats.vencidos ?? 0);
				if (statNodes.vencen_hoy) statNodes.vencen_hoy.textContent = String(stats.vencen_hoy ?? 0);
				renderGroups(data.groupKpis || []);
				if (updatedAt) updatedAt.textContent = String(data.actualizado || '');
			} catch (error) {
				console.debug('No se pudo actualizar dashboard de tickets en tiempo real.', error);
			}
		};

		window.setInterval(refreshDashboard, 15000);
	}

	const composeForm = document.getElementById('ticketComposeForm');
	if (composeForm) {
		const editor = document.getElementById('ticket-editor');
		const hiddenInput = document.getElementById('descripcion_html');
		const contactoSelect = document.getElementById('contacto_id');
		const buscarCorreo = document.getElementById('buscar_correo');
		const imageFileInput = document.getElementById('ticket-editor-image-file');

		document.querySelectorAll('[data-editor-cmd]').forEach((button) => {
			button.addEventListener('click', () => {
				const cmd = button.getAttribute('data-editor-cmd');
				if (!cmd) return;
				document.execCommand(cmd, false);
				editor?.focus();
			});
		});

		document.querySelectorAll('[data-editor-action]').forEach((button) => {
			button.addEventListener('click', () => {
				const action = button.getAttribute('data-editor-action');
				if (action === 'link') {
					const url = window.prompt('Ingresa la URL del enlace:');
					if (url) document.execCommand('createLink', false, url);
				}
				if (action === 'image') {
					const url = window.prompt('Ingresa la URL de la imagen:');
					if (url) document.execCommand('insertImage', false, url);
				}
				if (action === 'image-file') {
					imageFileInput?.click();
				}
				editor?.focus();
			});
		});

		if (imageFileInput) {
			imageFileInput.addEventListener('change', () => {
				const file = imageFileInput.files && imageFileInput.files[0] ? imageFileInput.files[0] : null;
				if (!file) return;
				if (!file.type.startsWith('image/')) {
					showGlobalNotification('Selecciona un archivo de imagen válido.', 'danger');
					imageFileInput.value = '';
					return;
				}

				const reader = new FileReader();
				reader.onload = () => {
					if (typeof reader.result === 'string') {
						document.execCommand('insertImage', false, reader.result);
					}
					imageFileInput.value = '';
				};
				reader.readAsDataURL(file);
			});
		}

		if (buscarCorreo && contactoSelect) {
			buscarCorreo.addEventListener('change', () => {
				const value = (buscarCorreo.value || '').trim().toLowerCase();
				if (value === '') return;
				const options = Array.from(contactoSelect.options);
				const match = options.find((opt) => (opt.getAttribute('data-email') || '').trim().toLowerCase() === value);
				if (match) {
					contactoSelect.value = match.value;
				}
			});
		}

		composeForm.addEventListener('submit', (event) => {
			const html = (editor?.innerHTML || '').trim();
			if (hiddenInput) {
				hiddenInput.value = html;
			}
			const plain = (editor?.textContent || '').trim();
			if (plain === '') {
				event.preventDefault();
				showGlobalNotification('Debes ingresar una descripción del ticket.', 'danger');
			}
		});
	}

	const ticketShow = document.querySelector('[data-ticket-show="true"]');
	if (ticketShow) {
		const composeReply = document.getElementById('compose-reply');
		const composeNote = document.getElementById('compose-note');
		const tabReply = document.getElementById('tab-reply');
		const tabNote = document.getElementById('tab-note');

		const setComposeMode = (mode) => {
			const isReply = mode === 'reply';
			if (composeReply) composeReply.style.display = isReply ? '' : 'none';
			if (composeNote) composeNote.style.display = isReply ? 'none' : '';
			if (tabReply) tabReply.classList.toggle('active', isReply);
			if (tabNote) tabNote.classList.toggle('active', !isReply);
		};

		ticketShow.querySelectorAll('[data-compose-mode]').forEach((button) => {
			button.addEventListener('click', () => {
				setComposeMode(button.getAttribute('data-compose-mode') || 'reply');
			});
		});

		ticketShow.querySelectorAll('[data-editor-cmd]').forEach((button) => {
			button.addEventListener('click', () => {
				const editorId = button.getAttribute('data-editor-target') || '';
				const cmd = button.getAttribute('data-editor-cmd') || '';
				const editor = document.getElementById(editorId);
				if (!editor || !cmd) return;
				editor.focus();
				document.execCommand(cmd, false, null);
			});
		});

		ticketShow.querySelectorAll('[data-editor-link="true"]').forEach((button) => {
			button.addEventListener('click', () => {
				const editorId = button.getAttribute('data-editor-target') || '';
				const editor = document.getElementById(editorId);
				if (!editor) return;
				const url = window.prompt('URL del enlace:');
				if (!url) return;
				editor.focus();
				document.execCommand('createLink', false, url);
			});
		});

		ticketShow.querySelectorAll('[data-editor-clear="true"]').forEach((button) => {
			button.addEventListener('click', () => {
				const editorId = button.getAttribute('data-editor-target') || '';
				const editor = document.getElementById(editorId);
				if (editor) editor.innerHTML = '';
			});
		});

		ticketShow.querySelectorAll('[data-editor-form]').forEach((form) => {
			form.addEventListener('submit', (event) => {
				const map = (form.getAttribute('data-editor-form') || '').split(':');
				const editor = document.getElementById(map[0] || '');
				const hidden = document.getElementById(map[1] || '');
				if (!editor || !hidden) return;

				const html = (editor.innerHTML || '').trim();
				if (!html || html === '<br>') {
					event.preventDefault();
					showGlobalNotification('El contenido no puede estar vacio.', 'danger');
					return;
				}

				hidden.value = html;
			});
		});

		ticketShow.querySelectorAll('[data-toggle-panel]').forEach((button) => {
			button.addEventListener('click', () => {
				const key = button.getAttribute('data-toggle-panel') || '';
				const body = document.getElementById(`panel-${key}-body`);
				if (!body) return;
				const collapsed = body.classList.toggle('panel-body-collapsed');
				button.textContent = collapsed ? 'Expandir' : 'Contraer';
			});
		});

		setComposeMode('reply');
	}
});
