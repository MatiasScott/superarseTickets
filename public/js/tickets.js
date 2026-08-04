console.debug('Modulo tickets cargado');

document.addEventListener('DOMContentLoaded', () => {
	const setupManualEmailChipInput = (inputEl, chipsEl, hiddenEl) => {
		if (!inputEl || !chipsEl || !hiddenEl) {
			return {
				add: () => false,
				clear: () => {},
			};
		}

		const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
		const normalizeEmail = (value) => String(value || '').trim().toLowerCase();
		const selected = new Map();

		const sync = () => {
			hiddenEl.value = Array.from(selected.keys()).join(', ');
			chipsEl.innerHTML = '';
			Array.from(selected.values()).forEach((entry) => {
				const chip = document.createElement('span');
				chip.className = 'ticket-email-chip';
				const text = document.createElement('span');
				text.textContent = entry;
				chip.appendChild(text);

				const removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.textContent = 'x';
				removeBtn.addEventListener('click', () => {
					selected.delete(entry);
					sync();
				});
				chip.appendChild(removeBtn);
				chipsEl.appendChild(chip);
			});
		};

		const add = (rawValue) => {
			const email = normalizeEmail(rawValue);
			if (!isValidEmail(email) || selected.has(email)) {
				return false;
			}
			selected.set(email, email);
			sync();
			return true;
		};

		inputEl.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ',' || event.key === ';' || event.key === 'Tab') {
				event.preventDefault();
				if (add(inputEl.value)) {
					inputEl.value = '';
				}
			}
			if (event.key === 'Backspace' && String(inputEl.value || '').trim() === '') {
				const keys = Array.from(selected.keys());
				const lastKey = keys.length > 0 ? keys[keys.length - 1] : '';
				if (lastKey !== '') {
					selected.delete(lastKey);
					sync();
				}
			}
		});

		inputEl.addEventListener('blur', () => {
			window.setTimeout(() => {
				const value = String(inputEl.value || '').trim();
				if (value !== '' && isValidEmail(value)) {
					if (add(value)) {
						inputEl.value = '';
					}
				}
			}, 100);
		});

		return {
			add,
			clear: () => {
				selected.clear();
				inputEl.value = '';
				sync();
			},
		};
	};

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
		const ccHiddenInput = document.getElementById('cc');
		const ccPickerInput = document.getElementById('cc_picker_input');
		const ccPickerResults = document.getElementById('cc_picker_results');
		const ccChips = document.getElementById('cc_chips');

		const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
		const normalizeEmail = (value) => String(value || '').trim().toLowerCase();
		const parseEmailList = (value) => {
			const items = String(value || '').split(/[;,]+/).map((item) => normalizeEmail(item)).filter((item) => item !== '');
			return Array.from(new Set(items));
		};

		const ccCatalog = new Map();
		const ccSelected = new Map();
		let activeSuggestionEmail = '';

		const buildCcCatalog = () => {
			if (!contactoSelect) {
				return;
			}

			Array.from(contactoSelect.options || []).forEach((opt) => {
				const rawName = String(opt.textContent || '').trim();
				if (rawName === '' || String(opt.value || '').trim() === '') {
					return;
				}
				const displayName = rawName.split(' - ')[0].trim();
				const primary = normalizeEmail(opt.getAttribute('data-email') || '');
				const extraList = String(opt.getAttribute('data-emails') || '')
					.split(',')
					.map((item) => normalizeEmail(item))
					.filter((item) => item !== '');
				const emailList = Array.from(new Set([primary, ...extraList].filter((item) => item !== '')));

				emailList.forEach((email) => {
					if (!isValidEmail(email)) {
						return;
					}
					if (!ccCatalog.has(email)) {
						ccCatalog.set(email, {
							email,
							name: displayName,
						});
					}
				});
			});
		};

		const updateCcHiddenValue = () => {
			if (!ccHiddenInput) {
				return;
			}
			ccHiddenInput.value = Array.from(ccSelected.keys()).join(', ');
		};

		const hideCcSuggestions = () => {
			if (!ccPickerResults) {
				return;
			}
			ccPickerResults.classList.add('d-none');
			ccPickerResults.innerHTML = '';
			activeSuggestionEmail = '';
		};

		const renderCcChips = () => {
			if (!ccChips) {
				return;
			}

			ccChips.innerHTML = '';
			Array.from(ccSelected.values()).forEach((entry) => {
				const chip = document.createElement('span');
				chip.className = 'badge text-bg-light border d-inline-flex align-items-center gap-2 px-2 py-2';

				const label = document.createElement('span');
				label.textContent = entry.name ? `${entry.name} <${entry.email}>` : entry.email;
				chip.appendChild(label);

				const removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.className = 'btn btn-sm btn-link text-danger p-0 m-0 lh-1';
				removeBtn.setAttribute('aria-label', 'Quitar copia');
				removeBtn.textContent = 'x';
				removeBtn.addEventListener('click', () => {
					ccSelected.delete(entry.email);
					renderCcChips();
					updateCcHiddenValue();
				});
				chip.appendChild(removeBtn);

				ccChips.appendChild(chip);
			});
		};

		const addCcEmail = (rawEmail) => {
			const email = normalizeEmail(rawEmail);
			if (!isValidEmail(email) || ccSelected.has(email)) {
				return false;
			}

			const catalogEntry = ccCatalog.get(email);
			ccSelected.set(email, {
				email,
				name: catalogEntry ? String(catalogEntry.name || '').trim() : '',
			});
			renderCcChips();
			updateCcHiddenValue();
			return true;
		};

		const renderCcSuggestions = () => {
			if (!ccPickerInput || !ccPickerResults) {
				return;
			}

			const query = String(ccPickerInput.value || '').trim().toLowerCase();
			if (query === '') {
				hideCcSuggestions();
				return;
			}

			const matches = Array.from(ccCatalog.values())
				.filter((entry) => !ccSelected.has(entry.email))
				.filter((entry) => entry.email.includes(query) || String(entry.name || '').toLowerCase().includes(query))
				.slice(0, 8);

			if (matches.length === 0 && !isValidEmail(query)) {
				hideCcSuggestions();
				return;
			}

			ccPickerResults.innerHTML = '';
			activeSuggestionEmail = '';

			matches.forEach((entry, index) => {
				const optionBtn = document.createElement('button');
				optionBtn.type = 'button';
				optionBtn.className = `list-group-item list-group-item-action${index === 0 ? ' active' : ''}`;
				optionBtn.setAttribute('data-cc-email', entry.email);
				optionBtn.textContent = entry.name ? `${entry.name} - ${entry.email}` : entry.email;
				optionBtn.addEventListener('click', () => {
					if (addCcEmail(entry.email) && ccPickerInput) {
						ccPickerInput.value = '';
						hideCcSuggestions();
						ccPickerInput.focus();
					}
				});
				ccPickerResults.appendChild(optionBtn);
				if (index === 0) {
					activeSuggestionEmail = entry.email;
				}
			});

			if (matches.length === 0 && isValidEmail(query)) {
				const freeOption = document.createElement('button');
				freeOption.type = 'button';
				freeOption.className = 'list-group-item list-group-item-action active';
				freeOption.setAttribute('data-cc-email', query);
				freeOption.textContent = `Agregar: ${query}`;
				freeOption.addEventListener('click', () => {
					if (addCcEmail(query) && ccPickerInput) {
						ccPickerInput.value = '';
						hideCcSuggestions();
						ccPickerInput.focus();
					}
				});
				ccPickerResults.appendChild(freeOption);
				activeSuggestionEmail = query;
			}

			ccPickerResults.classList.remove('d-none');
		};

		const commitCcInput = () => {
			if (!ccPickerInput) {
				return;
			}

			const value = normalizeEmail(ccPickerInput.value || '');
			if (value === '') {
				hideCcSuggestions();
				return;
			}

			const candidate = activeSuggestionEmail !== '' ? activeSuggestionEmail : value;
			if (addCcEmail(candidate)) {
				ccPickerInput.value = '';
			}
			hideCcSuggestions();
		};

		buildCcCatalog();
		if (ccHiddenInput) {
			parseEmailList(ccHiddenInput.value || '').forEach((email) => {
				if (isValidEmail(email)) {
					addCcEmail(email);
				}
			});
			updateCcHiddenValue();
		}

		if (ccPickerInput) {
			ccPickerInput.addEventListener('input', () => {
				renderCcSuggestions();
			});

			ccPickerInput.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === 'Tab' || event.key === ',' || event.key === ';') {
					event.preventDefault();
					commitCcInput();
					return;
				}

				if (event.key === 'Backspace' && String(ccPickerInput.value || '').trim() === '') {
					const keys = Array.from(ccSelected.keys());
					const lastKey = keys.length > 0 ? keys[keys.length - 1] : '';
					if (lastKey !== '') {
						ccSelected.delete(lastKey);
						renderCcChips();
						updateCcHiddenValue();
					}
				}
			});

			ccPickerInput.addEventListener('blur', () => {
				window.setTimeout(() => {
					hideCcSuggestions();
				}, 120);
			});
		}

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
			const optionContainsEmail = (option, emailValue) => {
				const primary = (option?.getAttribute('data-email') || '').trim().toLowerCase();
				if (primary === emailValue) {
					return true;
				}

				const rawEmails = (option?.getAttribute('data-emails') || '').trim().toLowerCase();
				if (rawEmails === '') {
					return false;
				}

				const emails = rawEmails.split(',').map((item) => item.trim()).filter((item) => item !== '');
				return emails.includes(emailValue);
			};

			const syncContactFromEmail = (enforce = false) => {
				const value = (buscarCorreo.value || '').trim().toLowerCase();
				if (value === '') {
					contactoSelect.setCustomValidity('');
					return;
				}

				const datalist = document.getElementById('contactosCorreos');
				const datalistOptions = datalist ? Array.from(datalist.querySelectorAll('option')) : [];
				const datalistMatch = datalistOptions.find((opt) => String(opt.value || '').trim().toLowerCase() === value);
				if (datalistMatch) {
					const mappedId = String(datalistMatch.getAttribute('data-contacto-id') || '').trim();
					if (mappedId !== '') {
						contactoSelect.value = mappedId;
						contactoSelect.setCustomValidity('');
						return;
					}
				}

				const options = Array.from(contactoSelect.options);
				const match = options.find((opt) => optionContainsEmail(opt, value));

				if (match) {
					contactoSelect.value = match.value;
					contactoSelect.setCustomValidity('');
					return;
				}

				contactoSelect.value = '';
				if (enforce) {
					if (isValidEmail(value)) {
						// Permitido: contacto nuevo se crea en backend al enviar.
						contactoSelect.setCustomValidity('');
					} else {
						contactoSelect.setCustomValidity('Ingresa un correo válido para crear un nuevo contacto.');
					}
				} else {
					contactoSelect.setCustomValidity('');
				}
			};

			buscarCorreo.addEventListener('input', () => syncContactFromEmail(false));
			buscarCorreo.addEventListener('change', () => syncContactFromEmail(true));
			buscarCorreo.addEventListener('blur', () => syncContactFromEmail(true));
			contactoSelect.addEventListener('change', () => contactoSelect.setCustomValidity(''));

			// Algunos navegadores autocompletan campos despues de renderizar.
			window.setTimeout(() => syncContactFromEmail(true), 0);
			window.setTimeout(() => syncContactFromEmail(true), 250);
		}

		composeForm.addEventListener('submit', (event) => {
			const html = (editor?.innerHTML || '').trim();
			if (hiddenInput) {
				hiddenInput.value = html;
			}

			if (buscarCorreo && contactoSelect) {
				const typedEmail = (buscarCorreo.value || '').trim().toLowerCase();
				if (typedEmail !== '') {
					const selectedOption = contactoSelect.options[contactoSelect.selectedIndex] || null;
					if (selectedOption && optionContainsEmail(selectedOption, typedEmail)) {
						contactoSelect.setCustomValidity('');
					} else if (!selectedOption || String(selectedOption.value || '').trim() === '') {
						if (!isValidEmail(typedEmail)) {
							event.preventDefault();
							contactoSelect.setCustomValidity('Ingresa un correo válido para crear contacto nuevo.');
							showGlobalNotification('El correo ingresado no es válido.', 'warning');
							contactoSelect.reportValidity();
							return;
						}
						contactoSelect.setCustomValidity('');
					} else {
						event.preventDefault();
						contactoSelect.setCustomValidity('El contacto seleccionado no corresponde al correo buscado.');
						showGlobalNotification('El correo buscado no está asociado al contacto seleccionado.', 'warning');
						contactoSelect.reportValidity();
						return;
					}
				}
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
		const composeForward = document.getElementById('compose-forward');
		const composeNote = document.getElementById('compose-note');
		const tabReply = document.getElementById('tab-reply');
		const tabForward = document.getElementById('tab-forward');
		const tabNote = document.getElementById('tab-note');
		const replyForm = document.getElementById('ticket-reply-form');
		const forwardForm = document.getElementById('ticket-forward-form');
		const forwardRecipientsInput = document.getElementById('forwardRecipientsInput');
		const forwardRecipientsValue = document.getElementById('forwardRecipientsValue');
		const forwardRecipientsChips = document.getElementById('forwardRecipientsChips');
		const forwardRecipientsClear = document.getElementById('forwardRecipientsClear');
		const replyEditor = document.getElementById('reply-editor');
		const replyDropzone = document.getElementById('reply-dropzone');
		const replyFileInput = document.getElementById('reply-attachments');
		const replyFileList = document.getElementById('reply-attachments-list');
		const uploadProgress = document.getElementById('reply-upload-progress');
		const uploadProgressBar = document.getElementById('reply-upload-progress-bar');
		const uploadProgressText = document.getElementById('reply-upload-progress-text');
		let previewObjectUrls = [];
		const forwardRecipientsPicker = setupManualEmailChipInput(forwardRecipientsInput, forwardRecipientsChips, forwardRecipientsValue);
		forwardRecipientsClear?.addEventListener('click', () => forwardRecipientsPicker.clear());

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

		const bootNoteEditing = () => {
			const buttons = Array.from(document.querySelectorAll('[data-ticket-note-edit-btn="true"]'));
			if (!buttons.length) return;
			const modalEl = document.getElementById('ticketNoteEditModal');
			const form = document.getElementById('ticketNoteEditForm');
			const textarea = document.getElementById('ticketNoteEditText');
			if (!modalEl || !form || !textarea) return;

			buttons.forEach((btn) => {
				btn.addEventListener('click', () => {
					const noteId = Number(btn.getAttribute('data-ticket-note-id') || 0);
					if (!noteId) return;
					let currentText = '';
					const messageCard = btn.closest('.message-card');
					const messageBody = messageCard ? messageCard.querySelector('.message-body') : null;
					if (messageBody) {
						currentText = String(messageBody.innerText || messageBody.textContent || '').trim();
					}

					const ticketPath = (window.location.pathname || '').replace(/\/$/, '');
					form.action = `${ticketPath}/note/${noteId}`;
					textarea.value = currentText;
					if (!currentText) {
						const fallback = String(btn.getAttribute('data-ticket-note-preview') || '').trim();
						textarea.value = fallback;
					}
				});
			});
		};

		const bootNoteDeleteModal = () => {
			const buttons = Array.from(document.querySelectorAll('[data-ticket-note-delete-btn="true"]'));
			if (!buttons.length) return;
			const modalEl = document.getElementById('ticketNoteDeleteModal');
			const form = document.getElementById('ticketNoteDeleteForm');
			const preview = document.getElementById('ticketNoteDeletePreview');
			if (!modalEl || !form || !preview) return;

			buttons.forEach((btn) => {
				btn.addEventListener('click', () => {
					const noteId = Number(btn.getAttribute('data-ticket-note-id') || 0);
					if (!noteId) return;
					const ticketPath = (window.location.pathname || '').replace(/\/$/, '');
					form.action = `${ticketPath}/note/${noteId}/delete`;
					const rawPreview = String(btn.getAttribute('data-ticket-note-preview') || '').trim();
					preview.textContent = rawPreview !== '' ? rawPreview : 'Nota sin contenido visible.';
				});
			});
		};

		const bootNoteAttachments = () => {
			const dropzone = document.getElementById('note-dropzone');
			const input = document.getElementById('note-attachments');
			const list = document.getElementById('note-attachments-list');
			if (!dropzone || !input || !list) return;

			const render = () => {
				const files = Array.from(input.files || []);
				if (!files.length) {
					list.innerHTML = '';
					return;
				}
				list.innerHTML = files.map((file, idx) => {
					const safeName = String(file.name || `archivo-${idx + 1}`).replace(/[<>"]/g, '');
					return `<button type="button" class="compose-attachment-chip" data-remove-note-file="${idx}">${safeName} <span aria-hidden="true">&times;</span></button>`;
				}).join('');

				Array.from(list.querySelectorAll('[data-remove-note-file]')).forEach((chip) => {
					chip.addEventListener('click', () => {
						const removeIdx = Number(chip.getAttribute('data-remove-note-file') || -1);
						if (removeIdx < 0) return;
						const dt = new DataTransfer();
						Array.from(input.files || []).forEach((file, i) => {
							if (i !== removeIdx) dt.items.add(file);
						});
						input.files = dt.files;
						render();
					});
				});
			};

			const openPicker = () => input.click();
			dropzone.addEventListener('click', openPicker);
			dropzone.addEventListener('keydown', (e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					openPicker();
				}
			});

			['dragenter', 'dragover'].forEach((eventName) => {
				dropzone.addEventListener(eventName, (e) => {
					e.preventDefault();
					dropzone.classList.add('is-dragover');
				});
			});
			['dragleave', 'drop'].forEach((eventName) => {
				dropzone.addEventListener(eventName, (e) => {
					e.preventDefault();
					if (eventName !== 'drop') {
						dropzone.classList.remove('is-dragover');
					}
				});
			});

			dropzone.addEventListener('drop', (e) => {
				dropzone.classList.remove('is-dragover');
				const dropped = e.dataTransfer?.files;
				if (!dropped || !dropped.length) return;
				const dt = new DataTransfer();
				Array.from(input.files || []).forEach((f) => dt.items.add(f));
				Array.from(dropped).forEach((f) => dt.items.add(f));
				input.files = dt.files;
				render();
			});

			input.addEventListener('change', render);
			render();
		};

		const bootAgentFilterByGroup = () => {
			const groupSelect = document.querySelector('select[name="grupo_id"]');
			const agentSelect = document.querySelector('select[data-ticket-agente-select="true"]');
			if (!groupSelect || !agentSelect) return;

			const options = Array.from(agentSelect.querySelectorAll('option'));
			const applyFilter = () => {
				const groupId = Number(groupSelect.value || 0);
				options.forEach((opt, idx) => {
					if (idx === 0) {
						opt.hidden = false;
						opt.disabled = false;
						return;
					}
					const raw = String(opt.getAttribute('data-grupo-ids') || '');
					const ids = raw.split(',').map((n) => Number(String(n).trim())).filter((n) => n > 0);
					const visible = groupId <= 0 ? true : ids.includes(groupId);
					opt.hidden = !visible;
					opt.disabled = !visible;
					if (!visible && opt.selected) {
						agentSelect.value = '';
					}
				});
			};

			groupSelect.addEventListener('change', applyFilter);
			applyFilter();
		};

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

		bootNoteEditing();
		bootNoteDeleteModal();
		bootNoteAttachments();
		bootAgentFilterByGroup();

		const setComposeMode = (mode) => {
			const isReply = mode === 'reply';
			const isForward = mode === 'forward';
			if (composeReply) composeReply.style.display = isReply ? '' : 'none';
			if (composeForward) composeForward.style.display = isForward ? '' : 'none';
			if (composeNote) composeNote.style.display = (!isReply && !isForward) ? '' : 'none';
			if (tabReply) tabReply.classList.toggle('active', isReply);
			if (tabForward) tabForward.classList.toggle('active', isForward);
			if (tabNote) tabNote.classList.toggle('active', (!isReply && !isForward));
		};

		ticketShow.querySelectorAll('[data-compose-mode]').forEach((button) => {
			button.addEventListener('click', () => {
				setComposeMode(button.getAttribute('data-compose-mode') || 'reply');
			});
		});

		if (forwardForm) {
			forwardForm.addEventListener('submit', (event) => {
				if (forwardRecipientsInput && forwardRecipientsValue && String(forwardRecipientsInput.value || '').trim() !== '') {
					forwardRecipientsPicker.add(forwardRecipientsInput.value);
					forwardRecipientsInput.value = '';
				}

				if (!forwardRecipientsValue || String(forwardRecipientsValue.value || '').trim() === '') {
					event.preventDefault();
					showGlobalNotification('Debes agregar al menos un destinatario para reenviar.', 'warning');
					forwardRecipientsInput?.focus();
				}
			});
		}

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

		const quickRepliesModal = document.getElementById('quickRepliesTicketModal');
		const quickRepliesList = document.getElementById('quick-replies-ticket-list');
		const quickRepliesForm = document.getElementById('quick-replies-ticket-create');
		const quickRepliesStatus = document.getElementById('quick-replies-ticket-status');

		const escapeText = (value) => String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');

		const insertQuickReplyIntoEditor = (text) => {
			if (!replyEditor) return;
			const clean = String(text || '').trim();
			if (clean === '') return;
			const html = `<p>${escapeText(clean).replace(/\n/g, '<br>')}</p>`;
			replyEditor.insertAdjacentHTML('beforeend', html);
			replyEditor.focus();
		};

		const bindQuickReplyInsertButtons = () => {
			if (!quickRepliesList) return;
			quickRepliesList.querySelectorAll('.quick-reply-ticket-item').forEach((btn) => {
				btn.addEventListener('click', () => {
					const desc = String(btn.getAttribute('data-description') || '');
					insertQuickReplyIntoEditor(desc);
					if (window.bootstrap && quickRepliesModal) {
						const modal = window.bootstrap.Modal.getInstance(quickRepliesModal);
						if (modal) modal.hide();
					}
				});
			});
		};

		const loadQuickReplies = async () => {
			if (!quickRepliesList) return;
			quickRepliesList.innerHTML = '<div class="text-muted small">Cargando...</div>';
			try {
				const response = await fetch(`${BASE_URL}correo/quick-replies`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				const data = await response.json();
				if (!response.ok || !data.ok) {
					throw new Error(data.error || 'No se pudo cargar respuestas rápidas.');
				}
				const items = Array.isArray(data.items) ? data.items : [];
				if (items.length === 0) {
					quickRepliesList.innerHTML = '<div class="text-muted small">Aún no hay respuestas rápidas.</div>';
					return;
				}
				quickRepliesList.innerHTML = items.map((item) => {
					const title = escapeText(item.title || 'Sin título');
					const description = escapeText(item.description || '');
					return `<button type="button" class="list-group-item list-group-item-action quick-reply-ticket-item" data-description="${description}"><div class="fw-semibold">${title}</div><small class="text-muted">${description}</small></button>`;
				}).join('');
				bindQuickReplyInsertButtons();
			} catch (error) {
				quickRepliesList.innerHTML = `<div class="text-danger small">${escapeText(error.message || 'Error al cargar.')}</div>`;
			}
		};

		if (quickRepliesModal) {
			quickRepliesModal.addEventListener('show.bs.modal', () => {
				if (quickRepliesStatus) {
					quickRepliesStatus.className = 'small mb-2 text-muted';
					quickRepliesStatus.textContent = 'Selecciona una respuesta o crea una nueva.';
				}
				loadQuickReplies();
			});
		}

		if (quickRepliesForm) {
			quickRepliesForm.addEventListener('submit', async (event) => {
				event.preventDefault();
				const formData = new FormData(quickRepliesForm);
				try {
					if (quickRepliesStatus) {
						quickRepliesStatus.className = 'small mb-2 text-muted';
						quickRepliesStatus.textContent = 'Guardando respuesta rápida...';
					}
					const response = await fetch(`${BASE_URL}correo/quick-replies`, {
						method: 'POST',
						body: formData,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const data = await response.json();
					if (!response.ok || !data.ok) {
						throw new Error(data.error || 'No se pudo guardar la respuesta rápida.');
					}
					quickRepliesForm.reset();
					if (quickRepliesStatus) {
						quickRepliesStatus.className = 'small mb-2 text-success';
						quickRepliesStatus.textContent = data.message || 'Guardado correctamente.';
					}
					await loadQuickReplies();
				} catch (error) {
					if (quickRepliesStatus) {
						quickRepliesStatus.className = 'small mb-2 text-danger';
						quickRepliesStatus.textContent = error.message || 'Error al guardar.';
					}
				}
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
