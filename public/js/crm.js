// Exponer BASE_URL si no existe
if (typeof BASE_URL === 'undefined') {
	const pathParts = window.location.pathname.split('/').filter(p => p.length > 0);
	window.BASE_URL = pathParts.length > 0 ? '/' + pathParts[0] + '/' : '/';
}

document.addEventListener('DOMContentLoaded', () => {
	const root = document.querySelector('[data-crm-dashboard]');
	if (!root) {
		return;
	}

	const parseSeries = (raw) => {
		try {
			const parsed = JSON.parse(raw || '[]');
			return Array.isArray(parsed) ? parsed : [];
		} catch (error) {
			return [];
		}
	};

	const pipelineLabels = parseSeries(root.getAttribute('data-pipeline-labels'));
	const pipelineValues = parseSeries(root.getAttribute('data-pipeline-values')).map((v) => Number(v) || 0);
	const monthlyLabels = parseSeries(root.getAttribute('data-monthly-labels'));
	const monthlyValues = parseSeries(root.getAttribute('data-monthly-values')).map((v) => Number(v) || 0);

	const drawBarChart = (canvasId, labels, values, color) => {
		const canvas = document.getElementById(canvasId);
		if (!canvas || labels.length === 0 || values.length === 0) {
			return;
		}

		const dpr = window.devicePixelRatio || 1;
		const draw = () => {
			const rect = canvas.getBoundingClientRect();
			const width = Math.max(320, Math.floor(rect.width));
			const height = Math.max(220, Math.floor(rect.height || 220));
			canvas.width = Math.floor(width * dpr);
			canvas.height = Math.floor(height * dpr);

			const ctx = canvas.getContext('2d');
			if (!ctx) {
				return;
			}

			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			ctx.clearRect(0, 0, width, height);

			const pad = { top: 16, right: 14, bottom: 58, left: 36 };
			const chartW = width - pad.left - pad.right;
			const chartH = height - pad.top - pad.bottom;
			const maxValue = Math.max(5, ...values);

			ctx.strokeStyle = '#e4ecf6';
			for (let i = 0; i <= 4; i++) {
				const y = pad.top + (chartH * i) / 4;
				ctx.beginPath();
				ctx.moveTo(pad.left, y);
				ctx.lineTo(width - pad.right, y);
				ctx.stroke();
			}

			const step = chartW / values.length;
			const barW = Math.max(12, step * 0.55);

			values.forEach((value, i) => {
				const x = pad.left + (step * i) + (step - barW) / 2;
				const barH = Math.round((value / maxValue) * chartH);
				const y = pad.top + chartH - barH;

				ctx.fillStyle = color;
				ctx.fillRect(x, y, barW, barH);

				ctx.fillStyle = '#4d6680';
				ctx.font = '11px Manrope, sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText(String(value), x + barW / 2, y - 6);

				const lbl = String(labels[i] ?? '').slice(0, 14);
				ctx.save();
				ctx.translate(x + barW / 2, pad.top + chartH + 10);
				ctx.rotate(-Math.PI / 5);
				ctx.fillStyle = '#607891';
				ctx.fillText(lbl, 0, 0);
				ctx.restore();
			});
		};

		draw();
		window.addEventListener('resize', draw);
	};

	const drawLineChart = (canvasId, labels, values, lineColor, fillColor) => {
		const canvas = document.getElementById(canvasId);
		if (!canvas || labels.length === 0 || values.length === 0) {
			return;
		}

		const dpr = window.devicePixelRatio || 1;
		const draw = () => {
			const rect = canvas.getBoundingClientRect();
			const width = Math.max(320, Math.floor(rect.width));
			const height = Math.max(220, Math.floor(rect.height || 220));
			canvas.width = Math.floor(width * dpr);
			canvas.height = Math.floor(height * dpr);

			const ctx = canvas.getContext('2d');
			if (!ctx) {
				return;
			}

			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			ctx.clearRect(0, 0, width, height);

			const pad = { top: 16, right: 14, bottom: 34, left: 36 };
			const chartW = width - pad.left - pad.right;
			const chartH = height - pad.top - pad.bottom;
			const maxValue = Math.max(5, ...values);

			ctx.strokeStyle = '#e4ecf6';
			for (let i = 0; i <= 4; i++) {
				const y = pad.top + (chartH * i) / 4;
				ctx.beginPath();
				ctx.moveTo(pad.left, y);
				ctx.lineTo(width - pad.right, y);
				ctx.stroke();
			}

			const toX = (index) => pad.left + (chartW * index) / Math.max(1, values.length - 1);
			const toY = (value) => pad.top + chartH - ((value / maxValue) * chartH);

			const gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + chartH);
			gradient.addColorStop(0, fillColor);
			gradient.addColorStop(1, 'rgba(47, 128, 237, 0.03)');

			ctx.beginPath();
			values.forEach((value, i) => {
				const x = toX(i);
				const y = toY(value);
				if (i === 0) {
					ctx.moveTo(x, y);
				} else {
					ctx.lineTo(x, y);
				}
			});
			ctx.lineTo(toX(values.length - 1), pad.top + chartH);
			ctx.lineTo(toX(0), pad.top + chartH);
			ctx.closePath();
			ctx.fillStyle = gradient;
			ctx.fill();

			ctx.beginPath();
			values.forEach((value, i) => {
				const x = toX(i);
				const y = toY(value);
				if (i === 0) {
					ctx.moveTo(x, y);
				} else {
					ctx.lineTo(x, y);
				}
			});
			ctx.strokeStyle = lineColor;
			ctx.lineWidth = 2;
			ctx.stroke();

			ctx.fillStyle = lineColor;
			values.forEach((value, i) => {
				const x = toX(i);
				const y = toY(value);
				ctx.beginPath();
				ctx.arc(x, y, 2.5, 0, Math.PI * 2);
				ctx.fill();
			});

			ctx.fillStyle = '#607891';
			ctx.font = '11px Manrope, sans-serif';
			ctx.textAlign = 'center';
			labels.forEach((label, i) => {
				const x = toX(i);
				ctx.fillText(String(label), x, height - 8);
			});
		};

		draw();
		window.addEventListener('resize', draw);
	};

	drawBarChart('crmPipelineChart', pipelineLabels, pipelineValues, '#2f80ed');
	drawLineChart('crmMonthlyChart', monthlyLabels, monthlyValues, '#17a2b8', 'rgba(23, 162, 184, 0.25)');
});

// Modales de Ver todo CRM
document.addEventListener('DOMContentLoaded', () => {
	const contactModal = document.getElementById('studentContactModal');
	const pipelineModal = document.getElementById('studentPipelineModal');
	const studentsTable = document.getElementById('crmStudentsTable');
	if (!contactModal && !pipelineModal && !studentsTable) {
		return;
	}

	const escapeHtml = (text) => {
		const div = document.createElement('div');
		div.textContent = String(text ?? '');
		return div.innerHTML;
	};

	const normalizeText = (value) => String(value || '').toLowerCase().trim();

	const parseCommaValues = (raw) => String(raw || '')
		.split(',')
		.map((item) => item.trim())
		.filter((item) => item !== '');

	const renderDynamicFields = (containerId, values, placeholder) => {
		const container = document.getElementById(containerId);
		if (!container) {
			return;
		}
		container.innerHTML = '';
		const list = values.length > 0 ? values : [''];

		list.forEach((value) => {
			const row = document.createElement('div');
			row.className = 'input-group mb-2 contact-extra-row';
			row.innerHTML = `
				<input type="text" class="form-control contact-extra-input" placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(value)}">
				<button type="button" class="btn btn-outline-danger contact-remove-extra">Quitar</button>
			`;
			container.appendChild(row);
		});

		container.querySelectorAll('.contact-remove-extra').forEach((btn) => {
			btn.addEventListener('click', () => {
				const rows = container.querySelectorAll('.contact-extra-row');
				if (rows.length <= 1) {
					const input = rows[0]?.querySelector('.contact-extra-input');
					if (input) {
						input.value = '';
					}
					return;
				}
				btn.closest('.contact-extra-row')?.remove();
			});
		});
	};

	const addDynamicField = (containerId, placeholder) => {
		const container = document.getElementById(containerId);
		if (!container) {
			return;
		}
		const row = document.createElement('div');
		row.className = 'input-group mb-2 contact-extra-row';
		row.innerHTML = `
			<input type="text" class="form-control contact-extra-input" placeholder="${escapeHtml(placeholder)}">
			<button type="button" class="btn btn-outline-danger contact-remove-extra">Quitar</button>
		`;
		container.appendChild(row);
		row.querySelector('.contact-remove-extra')?.addEventListener('click', () => {
			const rows = container.querySelectorAll('.contact-extra-row');
			if (rows.length <= 1) {
				const input = rows[0]?.querySelector('.contact-extra-input');
				if (input) {
					input.value = '';
				}
				return;
			}
			row.remove();
		});
	};

	const getDynamicValuesAsCsv = (containerId) => {
		const container = document.getElementById(containerId);
		if (!container) {
			return '';
		}
		const values = Array.from(container.querySelectorAll('.contact-extra-input'))
			.map((input) => String(input.value || '').trim())
			.filter((value) => value !== '');
		return values.join(', ');
	};

	const filterNameInput = document.getElementById('crmFilterName');
	const filterCareerInput = document.getElementById('crmFilterCareer');
	const filterClearBtn = document.getElementById('crmFilterClear');

	const applyTableFilters = () => {
		if (!studentsTable) {
			return;
		}
		const nameQuery = normalizeText(filterNameInput?.value || '');
		const careerQuery = normalizeText(filterCareerInput?.value || '');
		const rows = studentsTable.querySelectorAll('tbody tr[data-student-id]');
		rows.forEach((row) => {
			const rowName = normalizeText(row.getAttribute('data-student-name') || '');
			const rowCareer = normalizeText(row.getAttribute('data-student-career') || '');
			const matchName = nameQuery === '' || rowName.includes(nameQuery);
			const matchCareer = careerQuery === '' || rowCareer.includes(careerQuery);
			row.style.display = (matchName && matchCareer) ? '' : 'none';
		});
	};

	filterNameInput?.addEventListener('input', applyTableFilters);
	filterCareerInput?.addEventListener('input', applyTableFilters);
	filterClearBtn?.addEventListener('click', () => {
		if (filterNameInput) {
			filterNameInput.value = '';
		}
		if (filterCareerInput) {
			filterCareerInput.value = '';
		}
		applyTableFilters();
	});

	const contactLinks = document.querySelectorAll('.student-contact-link');
	contactLinks.forEach((link) => {
		link.addEventListener('click', (e) => {
			e.preventDefault();
			const studentId = link.getAttribute('data-student-id');
			if (!studentId) {
				return;
			}
			loadStudentContact(studentId);
		});
	});

	const pipelineLinks = document.querySelectorAll('.student-pipeline-action');
	pipelineLinks.forEach((link) => {
		link.addEventListener('click', (e) => {
			e.preventDefault();
			const studentId = link.getAttribute('data-student-id');
			if (!studentId) {
				return;
			}
			loadStudentPipeline(studentId);
		});
	});

	const loadStudentContact = async (studentId) => {
		try {
			const body = document.getElementById('studentContactBody');
			body.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

			const response = await fetch(`${BASE_URL}crm/getStudentContactDetail?id=${studentId}`);
			if (!response.ok) {
				throw new Error('Error al cargar contacto');
			}

			const data = await response.json();
			if (!data.success) {
				throw new Error(data.error || 'Error desconocido');
			}

			renderContactModal(data.student, studentId);
		} catch (error) {
			console.error('Error:', error);
			document.getElementById('studentContactBody').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
		}
	};

	const renderContactModal = (student, studentId) => {
		const body = document.getElementById('studentContactBody');
		const saveBtn = document.getElementById('saveStudentContactBtn');
		saveBtn.setAttribute('data-student-id', String(studentId));

		body.innerHTML = `
			<div id="contactSaveStatus" class="d-none"></div>
			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label">Codigo estudiante</label>
					<input type="text" class="form-control" value="${escapeHtml(student.codigo_estudiante || '')}" disabled>
				</div>
				<div class="col-md-6">
					<label class="form-label">Nombre completo</label>
					<input type="text" class="form-control" value="${escapeHtml(student.nombre_completo || '')}" disabled>
				</div>
				<div class="col-md-6">
					<label for="contactEmail" class="form-label">Correo principal</label>
					<input type="email" class="form-control" id="contactEmail" value="${escapeHtml(student.email || '')}">
				</div>
				<div class="col-md-3">
					<label for="contactPhone" class="form-label">Telefono</label>
					<input type="text" class="form-control" id="contactPhone" value="${escapeHtml(student.telefono || '')}">
				</div>
				<div class="col-md-3">
					<label for="contactCell" class="form-label">Celular</label>
					<input type="text" class="form-control" id="contactCell" value="${escapeHtml(student.celular || '')}">
				</div>
				<div class="col-12">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<label class="form-label mb-0">Correos adicionales</label>
						<button type="button" class="btn btn-sm btn-outline-primary" id="addExtraEmailBtn">+ Agregar</button>
					</div>
					<div id="extraEmailsList"></div>
				</div>
				<div class="col-12">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<label class="form-label mb-0">Numeros adicionales</label>
						<button type="button" class="btn btn-sm btn-outline-primary" id="addExtraPhoneBtn">+ Agregar</button>
					</div>
					<div id="extraPhonesList"></div>
				</div>
			</div>
		`;

		renderDynamicFields('extraEmailsList', parseCommaValues(student.extra_emails), 'correo@dominio.com');
		renderDynamicFields('extraPhonesList', parseCommaValues(student.extra_phones), '0999999999');

		document.getElementById('addExtraEmailBtn')?.addEventListener('click', () => {
			addDynamicField('extraEmailsList', 'correo@dominio.com');
		});

		document.getElementById('addExtraPhoneBtn')?.addEventListener('click', () => {
			addDynamicField('extraPhonesList', '0999999999');
		});
	};

	const loadStudentPipeline = async (studentId) => {
		try {
			const body = document.getElementById('studentPipelineBody');
			body.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

			const response = await fetch(`${BASE_URL}crm/getStudentDetail?id=${studentId}`);
			if (!response.ok) {
				throw new Error('Error al cargar pipeline');
			}

			const data = await response.json();
			if (!data.success) {
				throw new Error(data.error || 'Error desconocido');
			}

			renderPipelineModal(data.student, data.estados, studentId, data.pipeline_history || []);
		} catch (error) {
			console.error('Error:', error);
			document.getElementById('studentPipelineBody').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
		}
	};

	const renderPipelineModal = (student, estados, studentId, pipelineHistory) => {
		const body = document.getElementById('studentPipelineBody');
		const saveBtn = document.getElementById('saveStudentPipelineBtn');
		saveBtn.setAttribute('data-student-id', String(studentId));
		saveBtn.textContent = 'Guardar etapa';

		const currentStateId = Number(student.pipeline_estado_id || 0);
		const fullName = `${String(student.primer_nombre || '').trim()} ${String(student.segundo_nombre || '').trim()} ${String(student.primer_apellido || '').trim()} ${String(student.segundo_apellido || '').trim()}`.replace(/\s+/g, ' ').trim();
		const options = ['<option value="">Seleccionar estado...</option>'];
		const stageChips = [];
		estados.forEach((estado) => {
			const selected = Number(estado.id) === currentStateId ? ' selected' : '';
			options.push(`<option value="${escapeHtml(estado.id)}"${selected}>${escapeHtml(estado.nombre)}</option>`);
			const chipActive = Number(estado.id) === currentStateId ? ' is-active' : '';
			stageChips.push(`<button type="button" class="pipeline-stage-chip${chipActive}" data-stage-id="${escapeHtml(estado.id)}">${escapeHtml(estado.nombre)}</button>`);
		});

		body.innerHTML = `
			<div class="pipeline-edit-shell">
				<div id="pipelineSaveStatus" class="d-none"></div>
				<div class="pipeline-topbar">
					<div class="pipeline-avatar">${escapeHtml((fullName || 'E').charAt(0).toUpperCase())}</div>
					<div>
						<div class="pipeline-student-name">${escapeHtml(fullName || 'Estudiante')}</div>
						<div class="pipeline-student-meta">Codigo ${escapeHtml(student.codigo_estudiante || '-')} • ${escapeHtml(student.carrera || 'Sin carrera')}</div>
					</div>
				</div>

				<div class="pipeline-stage-wrap">
					<div class="pipeline-stage-title">Etapa del cliente potencial</div>
					<input type="hidden" id="pipelineStateId" value="${escapeHtml(currentStateId)}">
					<div class="pipeline-stage-list">${stageChips.join('')}</div>
				</div>

				<!-- Tabs Navigation -->
				<ul class="nav nav-tabs mt-4 mb-3" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active" id="detallesTabBtn" data-bs-toggle="tab" data-bs-target="#detallesPane" type="button" role="tab">Detalles de cliente potencial</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="actividadesTabBtn" data-bs-toggle="tab" data-bs-target="#actividadesPane" type="button" role="tab">Actividades</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="ticketsTabBtn" data-bs-toggle="tab" data-bs-target="#ticketsPane" type="button" role="tab">Tickets</button>
					</li>
				</ul>

				<!-- Tabs Content -->
				<div class="tab-content">
					<!-- Tab 1: Detalles -->
					<div class="tab-pane fade show active" id="detallesPane" role="tabpanel">
						<div class="row g-3">
							<div class="col-md-3">
								<div class="pipeline-detail-label">Nombre</div>
								<div class="pipeline-detail-value">${escapeHtml(String(student.primer_nombre || '-'))}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Apellido</div>
								<div class="pipeline-detail-value">${escapeHtml(String(student.primer_apellido || '-'))}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Correo electrónico</div>
								<div class="pipeline-detail-value">${escapeHtml(student.email || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Celular</div>
								<div class="pipeline-detail-value">${escapeHtml(student.celular || student.telefono || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Carrera</div>
								<div class="pipeline-detail-value">${escapeHtml(student.carrera || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Sede</div>
								<div class="pipeline-detail-value">${escapeHtml(student.sede || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Estado académico</div>
								<div class="pipeline-detail-value">${escapeHtml(student.estado || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Fecha matrícula</div>
								<div class="pipeline-detail-value">${escapeHtml(student.fecha_matricula || '-')}</div>
							</div>
						</div>
					</div>

					<!-- Tab 2: Actividades -->
					<div class="tab-pane fade" id="actividadesPane" role="tabpanel">
						<div id="actividadesList" style="max-height: 350px; overflow-y: auto;">
							<div class="text-center text-muted small py-3">
								<div class="spinner-border spinner-border-sm" role="status">
									<span class="visually-hidden">Cargando...</span>
								</div>
							</div>
						</div>
					</div>

					<!-- Tab 3: Tickets -->
					<div class="tab-pane fade" id="ticketsPane" role="tabpanel">
						<div id="ticketsList" style="max-height: 350px; overflow-y: auto;">
							<div class="text-center text-muted small py-3">
								<div class="spinner-border spinner-border-sm" role="status">
									<span class="visually-hidden">Cargando...</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="d-none">
					<label for="pipelineSelect" class="form-label">Grupo de pipeline</label>
					<select id="pipelineSelect" class="form-select">${options.join('')}</select>
				</div>
			</div>
		`;

		const pipelineSelect = document.getElementById('pipelineSelect');
		const pipelineStateId = document.getElementById('pipelineStateId');
		const chips = body.querySelectorAll('.pipeline-stage-chip');
		chips.forEach((chip) => {
			chip.addEventListener('click', () => {
				chips.forEach((item) => item.classList.remove('is-active'));
				chip.classList.add('is-active');
				const nextValue = chip.getAttribute('data-stage-id') || '';
				if (pipelineSelect) {
					pipelineSelect.value = nextValue;
				}
				if (pipelineStateId) {
					pipelineStateId.value = nextValue;
				}
			});
		});

		// Cargar datos en tabs
		loadStudentHistory(studentId);
		loadStudentTickets(studentId);
	};

	const loadStudentHistory = async (studentId) => {
		try {
			const response = await fetch(`${BASE_URL}crm/getCRMPipelineHistory?student_id=${studentId}`);
			if (!response.ok) throw new Error('Error al cargar historial');

			const data = await response.json();
			if (!data.success) throw new Error(data.error || 'Error desconocido');

			renderHistorial(data.historial || []);
		} catch (error) {
			console.error('Error:', error);
			const container = document.getElementById('actividadesList');
			if (container) {
				container.innerHTML = `<div class="alert alert-danger alert-sm">${escapeHtml(error.message)}</div>`;
			}
		}
	};

	const renderHistorial = (historial) => {
		const container = document.getElementById('actividadesList');
		if (!container) return;

		if (!Array.isArray(historial) || historial.length === 0) {
			container.innerHTML = '<p class="text-muted small text-center py-3">Sin cambios de etapa aún.</p>';
			return;
		}

		let html = '';
		historial.forEach((item) => {
			const date = new Date(item.created_at).toLocaleString('es-ES');
			html += `
				<div class="activity-item">
					<div class="activity-header">
						<strong class="activity-user">${escapeHtml(item.usuario || 'Usuario')}</strong>
						<small class="activity-date">${date}</small>
					</div>
					<div class="activity-action">${escapeHtml(item.note_text || 'Cambio de etapa')}</div>
				</div>
			`;
		});

		container.innerHTML = html;
	};

	const loadStudentTickets = async (studentId) => {
		try {
			const response = await fetch(`${BASE_URL}crm/getStudentTicketsByEmail?student_id=${studentId}`);
			let data = null;
			try {
				data = await response.json();
			} catch (parseError) {
				data = null;
			}

			if (!response.ok) {
				throw new Error((data && data.error) ? data.error : 'Error al cargar tickets');
			}

			if (!data.success) throw new Error(data.error || 'Error desconocido');

			renderTickets(data.tickets || []);
		} catch (error) {
			console.error('Error:', error);
			const container = document.getElementById('ticketsList');
			if (container) {
				container.innerHTML = `<div class="alert alert-danger alert-sm">${escapeHtml(error.message)}</div>`;
			}
		}
	};

	const renderTickets = (tickets) => {
		const container = document.getElementById('ticketsList');
		if (!container) return;

		if (!Array.isArray(tickets) || tickets.length === 0) {
			container.innerHTML = '<p class="text-muted small text-center py-3">No hay tickets enviados desde los correos registrados.</p>';
			return;
		}

		// Agrupar por fecha
		const grouped = {};
		tickets.forEach((ticket) => {
			const parsedDate = ticket.created_at ? new Date(ticket.created_at) : null;
			const date = parsedDate && !Number.isNaN(parsedDate.getTime())
				? parsedDate.toLocaleDateString('es-ES')
				: 'Sin fecha';
			if (!grouped[date]) {
				grouped[date] = [];
			}
			grouped[date].push(ticket);
		});

		let html = '';
		Object.entries(grouped).reverse().forEach(([date, items]) => {
			html += `<div class="ticket-date-group"><strong class="ticket-date">${date}</strong>`;
			items.forEach((ticket) => {
				const badgeClass = ticket.estado === 'cerrado' ? 'bg-success' : ticket.estado === 'resuelto' ? 'bg-info' : 'bg-warning';
				const ticketUrl = `${BASE_URL}tickets/${encodeURIComponent(String(ticket.id || ''))}`;
				const ticketSubject = escapeHtml(ticket.asunto || 'Sin asunto');
				html += `
					<div class="ticket-item">
						<div class="d-flex justify-content-between align-items-start">
							<div style="flex: 1;">
								<div class="ticket-subject">
									<a class="ticket-link" href="${ticketUrl}">${ticketSubject}</a>
								</div>
								<small class="ticket-desc text-muted">${escapeHtml((ticket.descripcion || '').substring(0, 80))}</small>
							</div>
							<span class="badge ${badgeClass}" style="white-space: nowrap; margin-left: 10px;">${escapeHtml(ticket.estado)}</span>
						</div>
					</div>
				`;
			});
			html += '</div>';
		});

		container.innerHTML = html;
	};

	const saveContactBtn = document.getElementById('saveStudentContactBtn');
	if (saveContactBtn) {
		saveContactBtn.addEventListener('click', async () => {
			const studentId = Number(saveContactBtn.getAttribute('data-student-id') || 0);
			const statusBox = document.getElementById('contactSaveStatus');
			if (studentId <= 0) {
				if (statusBox) {
					statusBox.className = 'alert alert-warning py-2 mb-3';
					statusBox.textContent = 'No se pudo identificar el estudiante.';
				}
				return;
			}

			if (statusBox) {
				statusBox.className = 'd-none';
				statusBox.textContent = '';
			}

			const payload = new URLSearchParams({
				student_id: String(studentId),
				email: String(document.getElementById('contactEmail')?.value || ''),
				telefono: String(document.getElementById('contactPhone')?.value || ''),
				celular: String(document.getElementById('contactCell')?.value || ''),
				extra_emails: getDynamicValuesAsCsv('extraEmailsList'),
				extra_phones: getDynamicValuesAsCsv('extraPhonesList'),
			});

			try {
				saveContactBtn.disabled = true;
				const response = await fetch(`${BASE_URL}crm/updateStudentContact`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: payload,
				});
				const data = await response.json();
				if (!response.ok || !data.success) {
					throw new Error(data.error || 'No se pudo guardar el contacto');
				}

				const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
				if (row) {
					const emailCol = row.querySelector('.email-col');
					if (emailCol) {
						emailCol.textContent = String(document.getElementById('contactEmail')?.value || '-');
					}
				}
				if (statusBox) {
					statusBox.className = 'alert alert-success py-2 mb-3';
					statusBox.textContent = 'Contacto actualizado correctamente.';
				}
			} catch (error) {
				console.error(error);
				if (statusBox) {
					statusBox.className = 'alert alert-danger py-2 mb-3';
					statusBox.textContent = error.message || 'Error al guardar contacto';
				}
			} finally {
				saveContactBtn.disabled = false;
			}
		});
	}

	const savePipelineBtn = document.getElementById('saveStudentPipelineBtn');
	if (savePipelineBtn) {
		savePipelineBtn.addEventListener('click', async () => {
			const studentId = Number(savePipelineBtn.getAttribute('data-student-id') || 0);
			const explicitState = Number(document.getElementById('pipelineStateId')?.value || 0);
			const fallbackState = Number(document.getElementById('pipelineSelect')?.value || 0);
			const estadoId = explicitState > 0 ? explicitState : fallbackState;
			const statusBox = document.getElementById('pipelineSaveStatus');

			if (statusBox) {
				statusBox.className = 'd-none';
				statusBox.textContent = '';
			}
			if (studentId <= 0 || estadoId <= 0) {
				if (statusBox) {
					statusBox.className = 'alert alert-warning py-2 mb-3';
					statusBox.textContent = 'Selecciona una etapa de pipeline antes de guardar.';
				}
				return;
			}

			const payload = new URLSearchParams({
				student_id: String(studentId),
				estado_id: String(estadoId),
			});

			try {
				savePipelineBtn.disabled = true;
				const response = await fetch(`${BASE_URL}crm/updateStudentState`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: payload,
				});
				const data = await response.json();
				if (!response.ok || !data.success) {
					throw new Error(data.error || 'No se pudo actualizar pipeline');
				}

				const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
				if (row) {
					const pipelineCell = row.querySelector('.pipeline-col');
					if (pipelineCell) {
						pipelineCell.innerHTML = `<span class="badge text-bg-light border">${escapeHtml(data.pipeline_nombre || 'Actualizado')}</span>`;
					}
				}

				await loadStudentPipeline(studentId);
				const refreshedStatusBox = document.getElementById('pipelineSaveStatus');
				if (refreshedStatusBox) {
					refreshedStatusBox.className = 'alert alert-success py-2 mb-3';
					refreshedStatusBox.textContent = 'Etapa actualizada correctamente.';
				}
			} catch (error) {
				console.error(error);
				if (statusBox) {
					statusBox.className = 'alert alert-danger py-2 mb-3';
					statusBox.textContent = error.message || 'Error al actualizar pipeline';
				}
			} finally {
				savePipelineBtn.disabled = false;
			}
		});
	}
});
