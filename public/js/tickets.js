console.debug('Modulo tickets cargado');

document.addEventListener('DOMContentLoaded', () => {
	const multiFilterForm = document.querySelector('[data-ticket-multi-filters]');
	if (multiFilterForm) {
		const updateTicketFilterLabel = (filterName) => {
			const button = multiFilterForm.querySelector(`[data-ticket-filter-button="${filterName}"]`);
			if (!button) {
				return;
			}

			const checked = Array.from(multiFilterForm.querySelectorAll(`.ticket-filter-checkbox[data-filter-name="${filterName}"]:checked`));
			const emptyLabel = String(button.getAttribute('data-empty-label') || 'Sin filtro');
			if (checked.length === 0) {
				button.textContent = emptyLabel;
				return;
			}

			button.textContent = checked.length === 1
				? '1 seleccionado'
				: `${checked.length} seleccionados`;
		};

		const filterNames = ['estado_id', 'prioridad_id', 'grupo_id', 'asignado_id', 'tipo_id'];
		filterNames.forEach((filterName) => updateTicketFilterLabel(filterName));

		multiFilterForm.querySelectorAll('.ticket-filter-checkbox').forEach((checkbox) => {
			checkbox.addEventListener('change', () => {
				const filterName = checkbox.getAttribute('data-filter-name') || '';
				if (filterName !== '') {
					updateTicketFilterLabel(filterName);
				}
			});
		});
	}

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
				const url = new URL(endpoint, window.location.origin);
				url.searchParams.set('_ts', String(Date.now()));
				const response = await fetch(url.toString(), {
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
					cache: 'no-store'
				});
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

		refreshDashboard();
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
		const replyForm = document.getElementById('ticket-reply-form');
		const replyEditor = document.getElementById('reply-editor');
		const replyDropzone = document.getElementById('reply-dropzone');
		const replyFileInput = document.getElementById('reply-attachments');
		const replyFileList = document.getElementById('reply-attachments-list');
		const uploadProgress = document.getElementById('reply-upload-progress');
		const uploadProgressBar = document.getElementById('reply-upload-progress-bar');
		const uploadProgressText = document.getElementById('reply-upload-progress-text');
		let previewObjectUrls = [];

		const formatFileSize = (bytes) => {
			if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
			return `${Math.max(0.1, Math.round((bytes / 1024) * 10) / 10)} KB`;
		};

		const renderReplyFiles = () => {
			if (!replyFileInput || !replyFileList) return;
			previewObjectUrls.forEach((url) => {
				try {
					URL.revokeObjectURL(url);
				} catch (error) {
					// ignore
				}
			});
			previewObjectUrls = [];

			replyFileList.innerHTML = '';
			Array.from(replyFileInput.files || []).forEach((file, index) => {
				const chip = document.createElement('span');
				chip.className = 'compose-attachment-chip';

				const isImage = file.type.startsWith('image/');
				if (isImage) {
					chip.classList.add('is-image');
					const preview = document.createElement('img');
					preview.alt = file.name;
					const objectUrl = URL.createObjectURL(file);
					preview.src = objectUrl;
					previewObjectUrls.push(objectUrl);
					chip.appendChild(preview);
				} else {
					const icon = document.createElement('i');
					icon.className = 'bi bi-paperclip';
					chip.appendChild(icon);
				}

				const name = document.createElement('span');
				name.className = 'compose-attachment-name';
				name.textContent = file.name;
				chip.appendChild(name);

				const size = document.createElement('small');
				size.textContent = formatFileSize(file.size);
				chip.appendChild(size);

				const removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.innerHTML = '&times;';
				removeBtn.setAttribute('aria-label', 'Quitar adjunto');
				removeBtn.addEventListener('click', () => {
					const dt = new DataTransfer();
					Array.from(replyFileInput.files || []).forEach((currentFile, currentIndex) => {
						if (currentIndex !== index) dt.items.add(currentFile);
					});
					replyFileInput.files = dt.files;
					renderReplyFiles();
				});

				chip.appendChild(removeBtn);
				replyFileList.appendChild(chip);
			});
		};

		const addFilesToReplyInput = (fileList) => {
			if (!replyFileInput || !fileList) return;
			const MAX_FILES = 10;
			const MAX_TOTAL_BYTES = 20 * 1024 * 1024;
			const existingFiles = Array.from(replyFileInput.files || []);
			const incomingFiles = Array.from(fileList || []);
			const existingTotal = existingFiles.reduce((sum, file) => sum + (file.size || 0), 0);
			let runningTotal = existingTotal;

			const accepted = [];
			for (const file of incomingFiles) {
				if ((existingFiles.length + accepted.length) >= MAX_FILES) {
					showGlobalNotification(`Solo se permiten ${MAX_FILES} archivos por respuesta.`, 'warning');
					break;
				}
				if ((runningTotal + (file.size || 0)) > MAX_TOTAL_BYTES) {
					showGlobalNotification('El total de adjuntos no puede superar 20MB.', 'warning');
					continue;
				}

				runningTotal += (file.size || 0);
				accepted.push(file);
			}

			const dt = new DataTransfer();
			existingFiles.forEach((file) => dt.items.add(file));
			accepted.forEach((file) => dt.items.add(file));
			replyFileInput.files = dt.files;
			renderReplyFiles();
		};

		const insertImageAtCaret = (dataUrl) => {
			if (!replyEditor || !dataUrl) return;
			replyEditor.focus();
			const selection = window.getSelection();
			if (!selection || selection.rangeCount === 0) {
				replyEditor.insertAdjacentHTML('beforeend', `<p><img src="${dataUrl}" alt="Imagen adjunta"></p>`);
				return;
			}

			const range = selection.getRangeAt(0);
			const img = document.createElement('img');
			img.src = dataUrl;
			img.alt = 'Imagen adjunta';
			range.deleteContents();
			range.insertNode(img);
			range.setStartAfter(img);
			range.collapse(true);
			selection.removeAllRanges();
			selection.addRange(range);
		};

		const readImageFileAsDataUrl = (file) => new Promise((resolve, reject) => {
			const reader = new FileReader();
			reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
			reader.onerror = () => reject(new Error('No se pudo leer la imagen.'));
			reader.readAsDataURL(file);
		});

		const handleEditorFileDrop = async (files) => {
			const allFiles = Array.from(files || []);
			if (allFiles.length === 0) return;

			const imageFiles = allFiles.filter((file) => file.type.startsWith('image/'));
			const nonImageFiles = allFiles.filter((file) => !file.type.startsWith('image/'));

			for (const imageFile of imageFiles) {
				if (imageFile.size > 8 * 1024 * 1024) {
					showGlobalNotification(`La imagen ${imageFile.name} supera 8MB y no se inserto en el cuerpo.`, 'warning');
					continue;
				}
				try {
					const dataUrl = await readImageFileAsDataUrl(imageFile);
					if (dataUrl !== '') {
						insertImageAtCaret(dataUrl);
					}
				} catch (error) {
					showGlobalNotification(`No se pudo insertar ${imageFile.name}.`, 'danger');
				}
			}

			if (nonImageFiles.length > 0) {
				addFilesToReplyInput(nonImageFiles);
				showGlobalNotification('Los archivos no imagen se agregaron como adjuntos.', 'info');
			}
		};

		if (replyFileInput) {
			replyFileInput.addEventListener('change', () => {
				renderReplyFiles();
			});
		}

		if (replyDropzone && replyFileInput) {
			replyDropzone.addEventListener('click', () => replyFileInput.click());
			replyDropzone.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					replyFileInput.click();
				}
			});

			['dragenter', 'dragover'].forEach((evtName) => {
				replyDropzone.addEventListener(evtName, (event) => {
					event.preventDefault();
					event.stopPropagation();
					replyDropzone.classList.add('is-dragover');
				});
			});

			['dragleave', 'drop'].forEach((evtName) => {
				replyDropzone.addEventListener(evtName, (event) => {
					event.preventDefault();
					event.stopPropagation();
					replyDropzone.classList.remove('is-dragover');
				});
			});

			replyDropzone.addEventListener('drop', (event) => {
				const files = event.dataTransfer ? event.dataTransfer.files : null;
				if (!files || files.length === 0) return;
				addFilesToReplyInput(files);
			});
		}

		if (replyEditor) {
			replyEditor.addEventListener('dragover', (event) => {
				event.preventDefault();
			});

			replyEditor.addEventListener('drop', (event) => {
				event.preventDefault();
				const files = event.dataTransfer ? event.dataTransfer.files : null;
				if (!files || files.length === 0) return;
				handleEditorFileDrop(files);
			});

			replyEditor.addEventListener('paste', (event) => {
				const clipboard = event.clipboardData;
				if (!clipboard || !clipboard.items) return;
				const files = [];
				Array.from(clipboard.items).forEach((item) => {
					if (item.kind === 'file') {
						const file = item.getAsFile();
						if (file) files.push(file);
					}
				});
				if (files.length === 0) return;
				event.preventDefault();
				handleEditorFileDrop(files);
			});
		}

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
				const text = (editor.textContent || '').replace(/\u00a0|\u200b/g, ' ').trim();
				const hasImage = editor.querySelector('img') !== null;
				const emptyHtmlPatterns = ['<br>', '<div><br></div>', '<p><br></p>'];

				if ((!html || emptyHtmlPatterns.includes(html.toLowerCase())) && !hasImage) {
					event.preventDefault();
					showGlobalNotification('El contenido no puede estar vacio.', 'danger');
					return;
				}

				if (text === '' && !hasImage) {
					event.preventDefault();
					showGlobalNotification('El contenido no puede estar vacio.', 'danger');
					return;
				}

				hidden.value = html;
			});
		});

		if (replyForm) {
			replyForm.addEventListener('submit', (event) => {
				// Si otra validacion ya bloqueo el envio, no continuar con el XHR.
				if (event.defaultPrevented) {
					return;
				}

				if (!replyFileInput || !uploadProgress || !uploadProgressBar || !uploadProgressText) {
					return;
				}

				const replyBodyInput = document.getElementById('reply-body');
				if (replyEditor && replyBodyInput) {
					const html = (replyEditor.innerHTML || '').trim();
					const text = (replyEditor.textContent || '').replace(/\u00a0|\u200b/g, ' ').trim();
					const hasImage = replyEditor.querySelector('img') !== null;
					const emptyHtmlPatterns = ['<br>', '<div><br></div>', '<p><br></p>'];

					if (((!html || emptyHtmlPatterns.includes(html.toLowerCase())) && !hasImage) || (text === '' && !hasImage)) {
						event.preventDefault();
						showGlobalNotification('El contenido no puede estar vacio.', 'danger');
						return;
					}

					replyBodyInput.value = html;
				}

				const files = Array.from(replyFileInput.files || []);
				if (files.length === 0) {
					return;
				}

				event.preventDefault();
				const formData = new FormData(replyForm);
				const xhr = new XMLHttpRequest();
				xhr.open('POST', replyForm.action, true);

				uploadProgress.style.display = '';
				uploadProgressBar.style.width = '0%';
				uploadProgressText.textContent = 'Subiendo adjuntos... 0%';

				xhr.upload.addEventListener('progress', (e) => {
					if (!e.lengthComputable) return;
					const percent = Math.min(100, Math.round((e.loaded / e.total) * 100));
					uploadProgressBar.style.width = `${percent}%`;
					uploadProgressText.textContent = `Subiendo adjuntos... ${percent}%`;
				});

				xhr.addEventListener('load', () => {
					uploadProgressBar.style.width = '100%';
					uploadProgressText.textContent = 'Procesando respuesta...';
					window.location.reload();
				});

				xhr.addEventListener('error', () => {
					uploadProgressText.textContent = 'No se pudo subir los adjuntos.';
					showGlobalNotification('No se pudo subir los adjuntos.', 'danger');
				});

				xhr.send(formData);
			});
		}

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
		renderReplyFiles();
	}
});
