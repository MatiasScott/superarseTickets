// Exponer BASE_URL si no existe
if (typeof BASE_URL === 'undefined') {
	const pathParts = window.location.pathname.split('/').filter(p => p.length > 0);
	const moduleRoots = new Set([
		'dashboard', 'tickets', 'chat', 'correo', 'crm', 'contactos', 'academico',
		'campanas', 'convenios', 'bot', 'relaciones', 'auditoria', 'admin',
		'usuarios', 'catalogos', 'configuracion', 'login', 'logout'
	]);

	if (pathParts.length === 0) {
		window.BASE_URL = '/';
	} else if (moduleRoots.has(String(pathParts[0] || '').toLowerCase())) {
		// Cuando la app vive en la raiz (ej: /crm/interesados), la base debe ser '/'.
		window.BASE_URL = '/';
	} else {
		// Cuando la app vive en subcarpeta (ej: /istsTicket/crm/interesados), usar esa carpeta.
		window.BASE_URL = '/' + pathParts[0] + '/';
	}
}

document.addEventListener('DOMContentLoaded', () => {
	const studentsBaseTable = document.getElementById('crmStudentsBaseTable');
	if (studentsBaseTable) {
		const filterName = document.getElementById('crmStudentsFilterName');
		const filterCareer = document.getElementById('crmStudentsFilterCareer');
		const filterClear = document.getElementById('crmStudentsFilterClear');
		const counter = document.getElementById('crmStudentsCounter');
		const rows = Array.from(studentsBaseTable.querySelectorAll('tbody tr[data-student-name]'));

		const normalize = (value) => String(value || '').toLowerCase().trim();

		const applyStudentsFilters = () => {
			const qName = normalize(filterName?.value || '');
			const qCareer = normalize(filterCareer?.value || '');
			let visible = 0;

			rows.forEach((row) => {
				const rowName = normalize(row.getAttribute('data-student-name') || '');
				const rowCareer = normalize(row.getAttribute('data-student-career') || '');
				const match = (!qName || rowName.includes(qName)) && (!qCareer || rowCareer.includes(qCareer));
				row.style.display = match ? '' : 'none';
				if (match) visible += 1;
			});

			if (counter) {
				counter.textContent = `Mostrando ${visible} de ${rows.length} registros`;
			}
		};

		filterName?.addEventListener('input', applyStudentsFilters);
		filterCareer?.addEventListener('input', applyStudentsFilters);
		filterClear?.addEventListener('click', () => {
			if (filterName) filterName.value = '';
			if (filterCareer) filterCareer.value = '';
			applyStudentsFilters();
		});

		applyStudentsFilters();
	}

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
	const filterPeriodBtn = document.getElementById('crmFilterPeriodBtn');
	const filterCareerBtn = document.getElementById('crmFilterCareerBtn');
	const filterPipelineBtn = document.getElementById('crmFilterPipelineBtn');
	const filterLevelBtn = document.getElementById('crmFilterLevelBtn');
	const filterClearBtn = document.getElementById('crmFilterClear');
	const studentsCounter = document.getElementById('crmStudentsCounter');
	const prospectsTable = document.getElementById('crmProspectsTable');
	const prospectSearchInput = document.getElementById('crmProspectSearch');
	const prospectOriginBtn = document.getElementById('crmProspectOriginBtn');
	const prospectStageBtn = document.getElementById('crmProspectStageBtn');
	const prospectCareerBtn = document.getElementById('crmProspectCareerBtn');
	const prospectCreatedByBtn = document.getElementById('crmProspectCreatedByBtn');
	const prospectCreatedPreset = document.getElementById('crmProspectCreatedPreset');
	const prospectCustomWrap = document.getElementById('crmProspectCreatedCustomWrap');
	const prospectDateFromInput = document.getElementById('crmProspectDateFrom');
	const prospectDateToInput = document.getElementById('crmProspectDateTo');
	const prospectClearBtn = document.getElementById('crmProspectFilterClear');
	const prospectsCounter = document.getElementById('crmProspectsCounter');

	const getCheckedFilterValues = (group) => {
		return Array.from(document.querySelectorAll(`.crm-filter-checkbox[data-filter-group="${group}"]:checked`))
			.map((input) => String(input.value || '').trim())
			.filter((value) => value !== '');
	};

	const updateMultiFilterButtonLabel = (button, values, emptyLabel, singularLabel, pluralLabel) => {
		if (!button) {
			return;
		}
		if (!Array.isArray(values) || values.length === 0) {
			button.textContent = emptyLabel;
			return;
		}
		button.textContent = values.length === 1
			? `1 ${singularLabel}`
			: `${values.length} ${pluralLabel}`;
	};

	const refreshStudentsMultiFilterLabels = () => {
		updateMultiFilterButtonLabel(filterPeriodBtn, getCheckedFilterValues('period'), 'Todos los periodos', 'periodo', 'periodos');
		updateMultiFilterButtonLabel(filterCareerBtn, getCheckedFilterValues('career'), 'Todas las carreras', 'carrera', 'carreras');
		updateMultiFilterButtonLabel(filterPipelineBtn, getCheckedFilterValues('pipeline'), 'Todas las etapas', 'etapa', 'etapas');
		updateMultiFilterButtonLabel(filterLevelBtn, getCheckedFilterValues('level'), 'Todos los niveles', 'nivel', 'niveles');
	};

	const getCheckedProspectFilterValues = (group) => {
		return Array.from(document.querySelectorAll(`.crm-prospect-filter-checkbox[data-filter-group="${group}"]:checked`))
			.map((input) => normalizeText(input.value || ''))
			.filter((value) => value !== '');
	};

	const refreshProspectMultiFilterLabels = () => {
		updateMultiFilterButtonLabel(prospectOriginBtn, getCheckedProspectFilterValues('prospect-origin'), 'Todos los asesores', 'asesor', 'asesores');
		updateMultiFilterButtonLabel(prospectStageBtn, getCheckedProspectFilterValues('prospect-stage'), 'Todas las etapas', 'etapa', 'etapas');
		updateMultiFilterButtonLabel(prospectCareerBtn, getCheckedProspectFilterValues('prospect-career'), 'Todas las carreras', 'carrera', 'carreras');
		updateMultiFilterButtonLabel(prospectCreatedByBtn, getCheckedProspectFilterValues('prospect-created-by'), 'Todos', 'persona', 'personas');
	};

	const isVisibleRow = (row) => row && row.style.display !== 'none';
	let studentsRowsCache = [];
	let studentsFilterAbortController = null;
	let studentsFilterRequestId = 0;
	let studentsFilterRetryTimer = null;
	let studentsNetworkWarningShown = false;

	const updateStudentsCounter = (total, options = {}) => {
		if (!studentsCounter) {
			return;
		}
		const safeTotal = Number.isFinite(Number(total)) ? Number(total) : 0;
		const isLocal = Boolean(options && options.local === true);
		studentsCounter.textContent = isLocal
			? `${safeTotal} estudiantes (esta pagina)`
			: `${safeTotal} estudiantes`;
	};

	const renderStudentsRows = (rows) => {
		if (!studentsTable) {
			return;
		}

		const tbody = studentsTable.querySelector('tbody');
		if (!tbody) {
			return;
		}

		if (!Array.isArray(rows) || rows.length === 0) {
			tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No hay estudiantes para mostrar.</td></tr>';
			return;
		}

		const html = rows.map((item) => {
			const fullName = `${String(item.nombre || '').trim()} ${String(item.apellido || '').trim()}`.replace(/\s+/g, ' ').trim();
			return `
				<tr
					data-student-id="${escapeHtml(item.id || '')}"
					data-student-name="${escapeHtml(normalizeText(fullName))}"
					data-student-career="${escapeHtml(normalizeText(item.carrera || ''))}"
					data-student-pipeline="${escapeHtml(normalizeText(item.pipeline_nombre || ''))}"
				>
					<td>${escapeHtml(item.numero_identificacion || '-')}</td>
					<td>
						<a href="#" class="student-contact-link" data-student-id="${escapeHtml(item.id || '')}" data-bs-toggle="modal" data-bs-target="#studentContactModal">
							${escapeHtml(fullName || '-')}
						</a>
					</td>
					<td class="email-col">${escapeHtml(item.email || '-')}</td>
					<td class="career-col">${escapeHtml(item.carrera || '-')}</td>
					<td>${escapeHtml(item.periodo || '-')}</td>
					<td>${escapeHtml(item.nivel || '-')}</td>
					<td>${escapeHtml(item.estado || '-')}</td>
					<td class="pipeline-col"><span class="badge text-bg-light border">${escapeHtml(item.pipeline_nombre || 'Sin asignar')}</span></td>
					<td class="text-end">
						<button
							type="button"
							class="btn btn-sm btn-outline-primary student-pipeline-action"
							data-student-id="${escapeHtml(item.id || '')}"
							data-entity-type="student"
							data-bs-toggle="modal"
							data-bs-target="#studentPipelineModal"
							title="Ver / Editar CRM"
							aria-label="Ver / Editar CRM"
						>
							<i class="bi bi-pencil-square"></i>
						</button>
					</td>
				</tr>
			`;
		}).join('');

		tbody.innerHTML = html;
	};

	const updateProspectsCounter = () => {
		if (!prospectsCounter || !prospectsTable) {
			return;
		}
		const rows = Array.from(prospectsTable.querySelectorAll('tbody tr[data-prospect-origin]'));
		const total = rows.length;
		const visibles = rows.filter((row) => isVisibleRow(row)).length;
		prospectsCounter.textContent = `Mostrando ${visibles} de ${total} clientes potenciales`;
	};

	const reindexVisibleProspectsRows = () => {
		if (!prospectsTable) return;
		let visibleIndex = 0;
		const rows = Array.from(prospectsTable.querySelectorAll('tbody tr[data-prospect-origin]'));
		rows.forEach((row) => {
			if (!isVisibleRow(row)) {
				return;
			}
			visibleIndex += 1;
			const cell = row.querySelector('[data-prospect-row-num]');
			if (cell) {
				cell.textContent = String(visibleIndex);
			}
		});
	};

	const applyStudentsFiltersLocally = () => {
		if (!studentsTable) {
			updateStudentsCounter(0);
			return;
		}

		const periodos = getCheckedFilterValues('period').map((value) => normalizeText(value));
		const nombre = normalizeText(String(filterNameInput?.value || '').trim());
		const carreras = getCheckedFilterValues('career').map((value) => normalizeText(value));
		const etapas = getCheckedFilterValues('pipeline').map((value) => normalizeText(value));
		const niveles = getCheckedFilterValues('level').map((value) => normalizeText(value));

		const rows = Array.from(studentsTable.querySelectorAll('tbody tr[data-student-id]'));
		let visible = 0;
		rows.forEach((row) => {
			const rowName = normalizeText(row.getAttribute('data-student-name') || '');
			const rowCareer = normalizeText(row.getAttribute('data-student-career') || '');
			const rowPipeline = normalizeText(row.getAttribute('data-student-pipeline') || '');
			const cells = row.querySelectorAll('td');
			const rowPeriod = normalizeText(cells[4]?.textContent || '');
			const rowLevel = normalizeText(cells[5]?.textContent || '');

			const matchPeriod = periodos.length === 0 || periodos.includes(rowPeriod);
			const matchName = nombre === '' || rowName.includes(nombre);
			const matchCareer = carreras.length === 0 || carreras.includes(rowCareer);
			const matchPipeline = etapas.length === 0 || etapas.some((etapa) => rowPipeline.includes(etapa));
			const matchLevel = niveles.length === 0 || niveles.includes(rowLevel);

			const match = matchPeriod && matchName && matchCareer && matchPipeline && matchLevel;
			row.style.display = match ? '' : 'none';
			if (match) {
				visible += 1;
			}
		});

		updateStudentsCounter(visible, { local: true });
	};

	const applyTableFilters = async () => {
		if (!studentsTable) {
			updateStudentsCounter(0);
			return;
		}

		const params = new URLSearchParams();
		const periodos = getCheckedFilterValues('period');
		const nombre = String(filterNameInput?.value || '').trim();
		const carreras = getCheckedFilterValues('career');
		const etapas = getCheckedFilterValues('pipeline');
		const niveles = getCheckedFilterValues('level');

		if (periodos.length > 0) params.set('periodo', periodos.join(','));
		if (nombre !== '') params.set('nombre', nombre);
		if (carreras.length > 0) params.set('carrera', carreras.join(','));
		if (etapas.length > 0) params.set('etapa', etapas.join(','));
		if (niveles.length > 0) params.set('nivel', niveles.join(','));

		studentsFilterRequestId += 1;
		const requestId = studentsFilterRequestId;
		if (studentsFilterRetryTimer) {
			clearTimeout(studentsFilterRetryTimer);
			studentsFilterRetryTimer = null;
		}
		if (studentsFilterAbortController) {
			studentsFilterAbortController.abort();
		}
		studentsFilterAbortController = new AbortController();

		try {
			const response = await fetch(`${BASE_URL}crm/interesados/students-filter?${params.toString()}`, {
				signal: studentsFilterAbortController.signal,
				cache: 'no-store',
			});
			if (!response.ok) {
				throw new Error('No se pudo filtrar estudiantes');
			}

			const data = await response.json();
			if (requestId !== studentsFilterRequestId) {
				return;
			}
			if (!data.success) {
				throw new Error(data.error || 'Error al filtrar estudiantes');
			}

			studentsRowsCache = Array.isArray(data.students) ? data.students : [];
			renderStudentsRows(studentsRowsCache);
			updateStudentsCounter(Number(data.total || studentsRowsCache.length || 0));
			studentsNetworkWarningShown = false;
			bindStudentActions();
		} catch (error) {
			if (error && error.name === 'AbortError') {
				return;
			}
			console.error('Error filtrando estudiantes:', error);
			applyStudentsFiltersLocally();
			const isNetworkChanged = String(error?.message || '').toLowerCase().includes('failed to fetch');
			if (isNetworkChanged) {
				if (!studentsNetworkWarningShown) {
					window.showGlobalNotification?.('La red cambió durante la consulta. Se aplicó filtro local temporal en esta página.', 'warning');
					studentsNetworkWarningShown = true;
				}
				studentsFilterRetryTimer = setTimeout(() => {
					if (requestId === studentsFilterRequestId) {
						applyTableFilters();
					}
				}, 1500);
				return;
			}
			window.showGlobalNotification?.('No se pudo aplicar filtros de estudiantes. Se aplicó filtro local temporal.', 'warning');
		}
	};

	const applyProspectFilters = () => {
		if (!prospectsTable) {
			updateProspectsCounter();
			return;
		}

		const searchValue = normalizeText(String(prospectSearchInput?.value || '').trim());
		const searchDigits = String(prospectSearchInput?.value || '').replace(/\D+/g, '');
		const selectedOrigins = getCheckedProspectFilterValues('prospect-origin');
		const selectedStages = getCheckedProspectFilterValues('prospect-stage');
		const selectedCareers = getCheckedProspectFilterValues('prospect-career');
		const selectedCreatedBy = getCheckedProspectFilterValues('prospect-created-by');
		const createdPreset = String(prospectCreatedPreset?.value || '').trim();
		const fromDate = String(prospectDateFromInput?.value || '').trim();
		const toDate = String(prospectDateToInput?.value || '').trim();
		const rows = prospectsTable.querySelectorAll('tbody tr[data-prospect-origin]');
		const now = new Date();

		const getWeekRange = (referenceDate, previous = false) => {
			const date = new Date(referenceDate);
			const day = date.getDay();
			const deltaToMonday = day === 0 ? -6 : 1 - day;
			date.setHours(0, 0, 0, 0);
			date.setDate(date.getDate() + deltaToMonday + (previous ? -7 : 0));
			const start = new Date(date);
			const end = new Date(date);
			end.setDate(end.getDate() + 7);
			return { start, end };
		};

		let presetStart = null;
		let presetEnd = null;
		if (createdPreset === 'current_week') {
			const range = getWeekRange(now, false);
			presetStart = range.start;
			presetEnd = range.end;
		} else if (createdPreset === 'previous_week') {
			const range = getWeekRange(now, true);
			presetStart = range.start;
			presetEnd = range.end;
		} else if (createdPreset === 'last_30_days') {
			presetStart = new Date(now);
			presetStart.setHours(0, 0, 0, 0);
			presetStart.setDate(presetStart.getDate() - 30);
			presetEnd = null;
		}

		const customFrom = fromDate !== '' ? new Date(fromDate) : null;
		const customTo = toDate !== '' ? new Date(toDate) : null;

		if (prospectCustomWrap) {
			prospectCustomWrap.classList.toggle('d-none', createdPreset !== 'custom');
		}

		rows.forEach((row) => {
			const rowName = normalizeText(row.getAttribute('data-prospect-name') || '');
			const rowPhone = String(row.getAttribute('data-prospect-phone') || '').replace(/\D+/g, '');
			const rowOrigin = normalizeText(row.getAttribute('data-prospect-origin') || '');
			const rowStage = normalizeText(row.getAttribute('data-prospect-stage') || '');
			const rowCareer = normalizeText(row.getAttribute('data-prospect-career') || '');
			const rowCreatedByRaw = String(row.getAttribute('data-prospect-created-by') || '');
			const rowCreatedBy = rowCreatedByRaw === ''
				? []
				: rowCreatedByRaw.split('|').map((value) => normalizeText(value)).filter((value) => value !== '');
			const rowDatetimeRaw = String(row.getAttribute('data-prospect-datetime') || '').trim();
			const rowDatetime = rowDatetimeRaw !== '' ? new Date(rowDatetimeRaw.replace(' ', 'T')) : null;

			const matchSearch = searchValue === ''
				? true
				: rowName.includes(searchValue) || (searchDigits !== '' && rowPhone.includes(searchDigits));
			const matchOrigin = selectedOrigins.length === 0 || selectedOrigins.includes(rowOrigin);
			const matchStage = selectedStages.length === 0 || selectedStages.includes(rowStage);
			const matchCareer = selectedCareers.length === 0 || selectedCareers.includes(rowCareer);
			const matchCreatedBy = selectedCreatedBy.length === 0 || selectedCreatedBy.some((item) => rowCreatedBy.includes(item));

			let matchCreated = true;
			if (createdPreset === 'custom') {
				if (!rowDatetime || Number.isNaN(rowDatetime.getTime())) {
					matchCreated = false;
				} else {
					const afterFrom = !customFrom || rowDatetime >= customFrom;
					const beforeTo = !customTo || rowDatetime <= customTo;
					matchCreated = afterFrom && beforeTo;
				}
			} else if (presetStart instanceof Date) {
				if (!rowDatetime || Number.isNaN(rowDatetime.getTime())) {
					matchCreated = false;
				} else {
					const afterStart = rowDatetime >= presetStart;
					const beforeEnd = !(presetEnd instanceof Date) || rowDatetime < presetEnd;
					matchCreated = afterStart && beforeEnd;
				}
			}

			row.style.display = (matchSearch && matchOrigin && matchStage && matchCareer && matchCreatedBy && matchCreated) ? '' : 'none';
		});
		reindexVisibleProspectsRows();
		updateProspectsCounter();
	};

	filterNameInput?.addEventListener('input', applyTableFilters);
	Array.from(document.querySelectorAll('.crm-filter-checkbox')).forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			refreshStudentsMultiFilterLabels();
			applyTableFilters();
		});
	});
	Array.from(document.querySelectorAll('.crm-prospect-filter-checkbox')).forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			refreshProspectMultiFilterLabels();
			applyProspectFilters();
		});
	});
	prospectSearchInput?.addEventListener('input', applyProspectFilters);
	prospectCreatedPreset?.addEventListener('change', applyProspectFilters);
	prospectDateFromInput?.addEventListener('change', applyProspectFilters);
	prospectDateToInput?.addEventListener('change', applyProspectFilters);
	filterClearBtn?.addEventListener('click', () => {
		if (filterNameInput) {
			filterNameInput.value = '';
		}
		Array.from(document.querySelectorAll('.crm-filter-checkbox')).forEach((checkbox) => {
			checkbox.checked = false;
		});
		refreshStudentsMultiFilterLabels();

		applyTableFilters();
	});

	prospectClearBtn?.addEventListener('click', () => {
		if (prospectSearchInput) {
			prospectSearchInput.value = '';
		}
		if (prospectCreatedPreset) {
			prospectCreatedPreset.value = '';
		}
		Array.from(document.querySelectorAll('.crm-prospect-filter-checkbox')).forEach((checkbox) => {
			checkbox.checked = false;
		});
		if (prospectDateFromInput) {
			prospectDateFromInput.value = '';
		}
		if (prospectDateToInput) {
			prospectDateToInput.value = '';
		}
		refreshProspectMultiFilterLabels();
		applyProspectFilters();
	});

	applyTableFilters();
	applyProspectFilters();
	refreshStudentsMultiFilterLabels();
	refreshProspectMultiFilterLabels();

	function bindStudentActions() {
		const contactLinks = document.querySelectorAll('.student-contact-link');
		contactLinks.forEach((link) => {
			if (link.dataset.crmBound === '1') {
				return;
			}
			link.dataset.crmBound = '1';
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
			if (link.dataset.crmBound === '1') {
				return;
			}
			link.dataset.crmBound = '1';
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const entityId = link.getAttribute('data-student-id');
				const entityType = String(link.getAttribute('data-entity-type') || 'student').toLowerCase() === 'contact' ? 'contact' : 'student';
				if (!entityId) {
					return;
				}
				loadStudentPipeline(entityId, entityType);
			});
		});

		const prospectLinks = document.querySelectorAll('.prospect-edit-link');
		prospectLinks.forEach((link) => {
			if (link.dataset.crmBound === '1') {
				return;
			}
			link.dataset.crmBound = '1';
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const contactId = Number(link.getAttribute('data-contact-id') || 0);
				if (contactId <= 0) {
					return;
				}
				loadProspectDetail(contactId);
			});
		});
	}

	bindStudentActions();

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

	const loadProspectDetail = async (contactId) => {
		const body = document.getElementById('prospectEditBody');
		if (!body) {
			return;
		}

		body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

		try {
			const response = await fetch(`${BASE_URL}crm/getProspectDetail?id=${encodeURIComponent(String(contactId))}`);
			if (!response.ok) {
				throw new Error('No se pudo cargar el cliente potencial');
			}

			const data = await response.json();
			if (!data.success) {
				throw new Error(data.error || 'No se pudo cargar el cliente potencial');
			}

			renderProspectModal(data.prospect || {}, data.estados || []);
		} catch (error) {
			body.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message || 'Error al cargar el cliente potencial')}</div>`;
		}
	};

	const renderProspectModal = (prospect, estados) => {
		const body = document.getElementById('prospectEditBody');
		const saveBtn = document.getElementById('saveProspectBtn');
		if (!body || !saveBtn) {
			return;
		}

		saveBtn.setAttribute('data-contact-id', String(prospect.contacto_id || ''));
		const baseCareerSelect = document.getElementById('prospectCarrera');
		const currentCareer = normalizeText(prospect.carrera || '');
		const careerOptions = [];
		if (baseCareerSelect) {
			Array.from(baseCareerSelect.options || []).forEach((option) => {
				const optionValue = String(option.value || '').trim();
				if (optionValue === '') {
					return;
				}
				const selected = normalizeText(optionValue) === currentCareer ? ' selected' : '';
				careerOptions.push(`<option value="${escapeHtml(optionValue)}"${selected}>${escapeHtml(option.textContent || optionValue)}</option>`);
			});
		}
		if (careerOptions.length === 0 && String(prospect.carrera || '').trim() !== '') {
			const fallbackValue = String(prospect.carrera || '').trim();
			careerOptions.push(`<option value="${escapeHtml(fallbackValue)}" selected>${escapeHtml(fallbackValue)}</option>`);
		}

		const currentEstadoId = Number(prospect.estado_id || 0);
		const estadoOptions = ['<option value="">Sin etapa</option>'];
		(Array.isArray(estados) ? estados : []).forEach((estado) => {
			const estadoId = Number(estado.id || 0);
			const selected = estadoId > 0 && estadoId === currentEstadoId ? ' selected' : '';
			estadoOptions.push(`<option value="${escapeHtml(estadoId)}"${selected}>${escapeHtml(estado.nombre || '')}</option>`);
		});

		body.innerHTML = `
			<div id="prospectSaveStatus" class="d-none"></div>
			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label">Nombres</label>
					<input type="text" class="form-control" id="prospectEditNombres" value="${escapeHtml(prospect.nombre || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Apellidos</label>
					<input type="text" class="form-control" id="prospectEditApellidos" value="${escapeHtml(prospect.apellido || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Identificacion</label>
					<input type="text" class="form-control" id="prospectEditIdentificacion" value="${escapeHtml(prospect.cedula || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Correo personal</label>
					<input type="email" class="form-control" id="prospectEditCorreo" value="${escapeHtml(prospect.email || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Celular</label>
					<input type="text" class="form-control" id="prospectEditCelular" value="${escapeHtml(prospect.celular || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Propietario</label>
					<input type="text" class="form-control" id="prospectEditPropietario" value="${escapeHtml(prospect.propietario || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Carrera</label>
					<select class="form-select" id="prospectEditCarreraSelect">
						<option value="">Seleccione carrera</option>
						${careerOptions.join('')}
					</select>
				</div>
				<div class="col-md-6">
					<label class="form-label">Modalidad</label>
					<input type="text" class="form-control" id="prospectEditModalidad" value="${escapeHtml(prospect.modalidad || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Provincia</label>
					<input type="text" class="form-control" id="prospectEditProvincia" value="${escapeHtml(prospect.provincia || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Ciudad</label>
					<input type="text" class="form-control" id="prospectEditCiudad" value="${escapeHtml(prospect.ciudad || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Creado por</label>
					<input type="text" class="form-control" id="prospectEditCreadoPor" value="${escapeHtml(prospect.creado_por || '')}">
				</div>
				<div class="col-md-6">
					<label class="form-label">Etapa</label>
					<select class="form-select" id="prospectEditEstadoId">
						${estadoOptions.join('')}
					</select>
				</div>
			</div>
		`;
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

	const loadStudentPipeline = async (entityId, entityType = 'student') => {
		try {
			const body = document.getElementById('studentPipelineBody');
			body.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

			const response = await fetch(`${BASE_URL}crm/getStudentDetail?id=${encodeURIComponent(String(entityId || ''))}&entity_type=${encodeURIComponent(entityType)}`);
			if (!response.ok) {
				throw new Error('Error al cargar pipeline');
			}

			const data = await response.json();
			if (!data.success) {
				throw new Error(data.error || 'Error desconocido');
			}

			renderPipelineModal(data.student, data.estados, entityId, data.pipeline_history || [], entityType);
		} catch (error) {
			console.error('Error:', error);
			document.getElementById('studentPipelineBody').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
		}
	};

	const renderPipelineModal = (student, estados, entityId, pipelineHistory, entityType = 'student') => {
		const body = document.getElementById('studentPipelineBody');
		const saveBtn = document.getElementById('saveStudentPipelineBtn');
		saveBtn.setAttribute('data-student-id', String(entityId));
		saveBtn.setAttribute('data-entity-type', String(entityType));
		saveBtn.textContent = 'Guardar etapa';

		const currentStateIdsRaw = Array.isArray(student.pipeline_estado_ids)
			? student.pipeline_estado_ids
			: [];
		const currentStateIds = currentStateIdsRaw
			.map((value) => Number(value || 0))
			.filter((value) => value > 0);
		const fallbackStateId = Number(student.pipeline_estado_id || 0);
		if (currentStateIds.length === 0 && fallbackStateId > 0) {
			currentStateIds.push(fallbackStateId);
		}
		const selectedStateSet = new Set(currentStateIds);
		const isStudent = Number(student.is_student || 0) === 1;
		const statusBadge = isStudent
			? '<span class="badge text-bg-success">Estudiante Activo</span>'
			: '<span class="badge text-bg-warning">Cliente Potencial</span>';
		const statusMeta = isStudent ? 'Registro academico habilitado' : 'Aun sin registro academico';
		const fullName = `${String(student.primer_nombre || '').trim()} ${String(student.segundo_nombre || '').trim()} ${String(student.primer_apellido || '').trim()} ${String(student.segundo_apellido || '').trim()}`.replace(/\s+/g, ' ').trim();
		const options = ['<option value="">Seleccionar estado...</option>'];
		const stageChips = [];
		estados.forEach((estado) => {
			const estadoIdNum = Number(estado.id || 0);
			const selected = selectedStateSet.has(estadoIdNum) ? ' selected' : '';
			options.push(`<option value="${escapeHtml(estado.id)}"${selected}>${escapeHtml(estado.nombre)}</option>`);
			const chipActive = selectedStateSet.has(estadoIdNum) ? ' is-active' : '';
			stageChips.push(`<button type="button" class="pipeline-stage-chip${chipActive}" data-stage-id="${escapeHtml(estado.id)}">${escapeHtml(estado.nombre)}</button>`);
		});

		body.innerHTML = `
			<div class="pipeline-edit-shell">
				<div id="pipelineSaveStatus" class="d-none"></div>
				<div class="pipeline-topbar">
					<div class="pipeline-avatar">${escapeHtml((fullName || 'E').charAt(0).toUpperCase())}</div>
					<div>
						<div class="pipeline-student-name d-flex align-items-center gap-2">${escapeHtml(fullName || 'Persona CRM')} ${statusBadge}</div>
						<div class="pipeline-student-meta">${escapeHtml(statusMeta)} • Codigo ${escapeHtml(student.codigo_estudiante || '-')}</div>
					</div>
				</div>

				<div class="pipeline-stage-wrap">
					<div class="pipeline-stage-title">Etapa del cliente potencial</div>
					<input type="hidden" id="pipelineStateIds" value="${escapeHtml(currentStateIds.join(','))}">
					<div class="pipeline-stage-list">${stageChips.join('')}</div>
				</div>

				<!-- Tabs Navigation -->
				<ul class="nav nav-tabs mt-4 mb-3" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active" id="detallesTabBtn" data-bs-toggle="tab" data-bs-target="#detallesPane" type="button" role="tab">Detalles de cliente potencial</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="ticketsTabBtn" data-bs-toggle="tab" data-bs-target="#ticketsPane" type="button" role="tab">Tickets</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="notasTabBtn" data-bs-toggle="tab" data-bs-target="#notasPane" type="button" role="tab">Notas internas</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="historicoTabBtn" data-bs-toggle="tab" data-bs-target="#historicoPane" type="button" role="tab">Historico</button>
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
							${isStudent ? `
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
							` : `
							<div class="col-md-3">
								<div class="pipeline-detail-label">Identificación</div>
								<div class="pipeline-detail-value">${escapeHtml(student.cedula || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Carrera</div>
								<div class="pipeline-detail-value">${escapeHtml(student.carrera || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Modalidad</div>
								<div class="pipeline-detail-value">${escapeHtml(student.modalidad || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Propietario</div>
								<div class="pipeline-detail-value">${escapeHtml(student.origen || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Creado por</div>
								<div class="pipeline-detail-value">${escapeHtml(student.creado_por || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Provincia</div>
								<div class="pipeline-detail-value">${escapeHtml(student.provincia || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Ciudad</div>
								<div class="pipeline-detail-value">${escapeHtml(student.ciudad || '-')}</div>
							</div>
							<div class="col-md-3">
								<div class="pipeline-detail-label">Creado</div>
								<div class="pipeline-detail-value">${escapeHtml(student.prospect_created_at || student.fecha_matricula || '-')}</div>
							</div>
							<div class="col-md-12">
								<div class="alert alert-light border mb-0">No tiene relación académica activa; se muestran todos los datos CRM de creación y seguimiento.</div>
							</div>
							<div class="col-md-12">
								<button type="button" class="btn btn-outline-primary btn-sm" id="openProspectEditFromPipeline">
									<i class="bi bi-pencil-square"></i> Editar datos completos del cliente potencial
								</button>
							</div>
							`}
						</div>
					</div>

					<!-- Tab 2: Historico -->
					<div class="tab-pane fade" id="historicoPane" role="tabpanel">
						<div id="historicoList" style="max-height: 350px; overflow-y: auto;">
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

					<!-- Tab 4: Notas internas -->
					<div class="tab-pane fade" id="notasPane" role="tabpanel">
						<div class="mb-3">
							<label for="internalNoteText" class="form-label small fw-semibold">Nueva nota interna</label>
							<div class="input-group">
								<textarea id="internalNoteText" class="form-control" rows="2" placeholder="Escribe una nota interna del seguimiento CRM..."></textarea>
								<button type="button" class="btn btn-outline-primary" id="addInternalNoteBtn">Guardar nota</button>
							</div>
							<div class="mt-2">
								<input type="file" id="internalNoteAttachments" class="form-control form-control-sm" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar">
								<div id="internalNoteAttachmentsList" class="small text-muted mt-1"></div>
							</div>
							<div id="internalNoteStatus" class="small mt-2 text-muted"></div>
						</div>
						<div id="internalNotesList" style="max-height: 320px; overflow-y: auto;">
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
		const pipelineStateIds = document.getElementById('pipelineStateIds');
		const chips = body.querySelectorAll('.pipeline-stage-chip');
		const syncPipelineSelection = () => {
			const selectedIds = Array.from(chips)
				.filter((item) => item.classList.contains('is-active'))
				.map((item) => Number(item.getAttribute('data-stage-id') || 0))
				.filter((item) => item > 0);
			if (pipelineStateIds) {
				pipelineStateIds.value = selectedIds.join(',');
			}
			if (pipelineSelect) {
				if (selectedIds.length > 0) {
					pipelineSelect.value = String(selectedIds[0]);
				} else {
					pipelineSelect.value = '';
				}
			}
		};
		chips.forEach((chip) => {
			chip.addEventListener('click', () => {
				chip.classList.toggle('is-active');
				syncPipelineSelection();
			});
		});
		syncPipelineSelection();

		if (String(entityType).toLowerCase() === 'contact') {
			const editFromPipelineBtn = document.getElementById('openProspectEditFromPipeline');
			editFromPipelineBtn?.addEventListener('click', async () => {
				await loadProspectDetail(Number(entityId || 0));
				const pipelineModalEl = document.getElementById('studentPipelineModal');
				if (pipelineModalEl && window.bootstrap?.Modal) {
					const instance = window.bootstrap.Modal.getInstance(pipelineModalEl);
					instance?.hide();
				}
				const prospectModalEl = document.getElementById('prospectEditModal');
				if (prospectModalEl && window.bootstrap?.Modal) {
					const prospectModal = new window.bootstrap.Modal(prospectModalEl);
					prospectModal.show();
				}
			});
		}

		// Cargar datos en tabs
		loadStudentHistory(entityId, entityType);
		loadStudentTickets(entityId, entityType);
		loadStudentNotes(entityId, entityType);

		const addNoteBtn = document.getElementById('addInternalNoteBtn');
		const noteAttachmentsInput = document.getElementById('internalNoteAttachments');
		const noteAttachmentsList = document.getElementById('internalNoteAttachmentsList');

		const renderSelectedNoteFiles = () => {
			if (!noteAttachmentsInput || !noteAttachmentsList) return;
			const files = Array.from(noteAttachmentsInput.files || []);
			if (files.length === 0) {
				noteAttachmentsList.innerHTML = '';
				return;
			}
			noteAttachmentsList.innerHTML = files.map((file, index) => {
				const safeName = escapeHtml(file.name || `archivo-${index + 1}`);
				return `<button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1 crm-note-file-chip" data-file-index="${index}">${safeName} &times;</button>`;
			}).join('');
			noteAttachmentsList.querySelectorAll('.crm-note-file-chip').forEach((btn) => {
				btn.addEventListener('click', () => {
					if (!noteAttachmentsInput) return;
					const removeIndex = Number(btn.getAttribute('data-file-index') || -1);
					if (removeIndex < 0) return;
					const dt = new DataTransfer();
					Array.from(noteAttachmentsInput.files || []).forEach((file, currentIndex) => {
						if (currentIndex !== removeIndex) dt.items.add(file);
					});
					noteAttachmentsInput.files = dt.files;
					renderSelectedNoteFiles();
				});
			});
		};

		if (noteAttachmentsInput) {
			noteAttachmentsInput.addEventListener('change', renderSelectedNoteFiles);
		}
		if (addNoteBtn) {
			addNoteBtn.addEventListener('click', async () => {
				const noteInput = document.getElementById('internalNoteText');
				const statusBox = document.getElementById('internalNoteStatus');
				const noteText = String(noteInput?.value || '').trim();

				if (!noteText) {
					if (statusBox) {
						statusBox.className = 'small mt-2 text-danger';
						statusBox.textContent = 'Escribe una nota antes de guardar.';
					}
					return;
				}

				try {
					addNoteBtn.disabled = true;
					if (statusBox) {
						statusBox.className = 'small mt-2 text-muted';
						statusBox.textContent = 'Guardando nota...';
					}

					const payload = new FormData();
					payload.append('student_id', String(entityId));
					payload.append('note_text', noteText);
					Array.from(noteAttachmentsInput?.files || []).forEach((file) => {
						payload.append('attachments[]', file);
					});

					const response = await fetch(`${BASE_URL}crm/addStudentNote`, {
						method: 'POST',
						body: payload,
					});
					const data = await response.json();
					if (!response.ok || !data.success) {
						throw new Error(data.error || 'No se pudo guardar la nota.');
					}

					if (noteInput) {
						noteInput.value = '';
					}
					if (noteAttachmentsInput) {
						noteAttachmentsInput.value = '';
						renderSelectedNoteFiles();
					}
					if (statusBox) {
						statusBox.className = 'small mt-2 text-success';
						statusBox.textContent = 'Nota guardada correctamente.';
					}
					await loadStudentNotes(entityId, entityType);
					await loadStudentHistory(entityId, entityType);
				} catch (error) {
					if (statusBox) {
						statusBox.className = 'small mt-2 text-danger';
						statusBox.textContent = error.message || 'Error al guardar nota.';
					}
				} finally {
					addNoteBtn.disabled = false;
				}
			});
		}
	};

	const buildEntityQuery = (entityId, entityType = 'student') => {
		const id = Number(entityId || 0);
		if (String(entityType).toLowerCase() === 'contact') {
			return `contacto_id=${encodeURIComponent(String(id))}`;
		}
		return `student_id=${encodeURIComponent(String(id))}`;
	};

	const loadStudentHistory = async (entityId, entityType = 'student') => {
		try {
			const response = await fetch(`${BASE_URL}crm/getCRMPipelineHistory?${buildEntityQuery(entityId, entityType)}`);
			if (!response.ok) throw new Error('Error al cargar historial');

			const data = await response.json();
			if (!data.success) throw new Error(data.error || 'Error desconocido');

			renderHistorial(data.historial || []);
		} catch (error) {
			console.error('Error:', error);
			const container = document.getElementById('historicoList');
			if (container) {
				container.innerHTML = `<div class="alert alert-danger alert-sm">${escapeHtml(error.message)}</div>`;
			}
		}
	};

	const renderHistorial = (historial) => {
		const container = document.getElementById('historicoList');
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
						<strong class="activity-user">${escapeHtml(item.usuario || 'Sistema')}</strong>
						<small class="activity-date">${date}</small>
					</div>
					<div class="activity-action">${escapeHtml(item.note_text || 'Cambio de etapa')}</div>
				</div>
			`;
		});

		container.innerHTML = html;
	};

	const parseTaskIds = (raw) => String(raw || '')
		.split(',')
		.map((value) => Number(String(value || '').trim()))
		.filter((value) => Number.isFinite(value) && value > 0);

	const pluralTask = (value, singular, pluralForm) => (value === 1 ? singular : pluralForm);

	const formatTaskDuration = (totalMinutes) => {
		const minutesSafe = Math.max(0, totalMinutes);
		const days = Math.floor(minutesSafe / 1440);
		const hours = Math.floor((minutesSafe % 1440) / 60);
		const minutes = minutesSafe % 60;

		if (days > 0) {
			return `${days} ${pluralTask(days, 'dia', 'dias')} ${hours} ${pluralTask(hours, 'hora', 'horas')}`;
		}

		if (hours > 0) {
			return `${hours} ${pluralTask(hours, 'hora', 'horas')} ${minutes} min`;
		}

		return `${minutes} min`;
	};

	const parseTaskDeadline = (dateValue, timeValue) => {
		const dateText = String(dateValue || '').trim();
		if (!dateText) return null;

		const timeText = String(timeValue || '').trim() || '23:59';
		const composed = `${dateText}T${timeText.length === 5 ? `${timeText}:00` : timeText}`;
		const parsed = new Date(composed);
		return Number.isNaN(parsed.getTime()) ? null : parsed;
	};

	const renderCrmTaskCountdowns = (scope) => {
		const nodes = Array.from((scope || document).querySelectorAll('[data-crm-task-countdown]'));
		const now = new Date();

		nodes.forEach((node) => {
			const status = String(node.getAttribute('data-status') || '').trim().toLowerCase();
			const completed = String(node.getAttribute('data-completed') || '0') === '1';
			const deadline = parseTaskDeadline(node.getAttribute('data-date'), node.getAttribute('data-time'));

			node.className = 'crm-deadline-pill';
			if (status === 'completada' || completed) {
				node.classList.add('crm-deadline-complete');
				node.textContent = 'Completada';
				return;
			}

			if (!deadline) {
				node.classList.add('crm-deadline-none');
				node.textContent = 'Sin fecha limite';
				return;
			}

			const diffMs = deadline.getTime() - now.getTime();
			const absMinutes = Math.floor(Math.abs(diffMs) / 60000);

			if (diffMs < 0) {
				node.classList.add('crm-deadline-overdue');
				node.textContent = `Vencida hace ${formatTaskDuration(absMinutes)}`;
				return;
			}

			const hoursLeft = diffMs / 3600000;
			if (hoursLeft <= 24) {
				node.classList.add('crm-deadline-critical');
			} else if (hoursLeft <= 72) {
				node.classList.add('crm-deadline-warning');
			} else {
				node.classList.add('crm-deadline-ok');
			}

			node.textContent = `Faltan ${formatTaskDuration(absMinutes)}`;
		});
	};

	const buildOptions = (items, selectedValue, placeholder) => {
		const buffer = [`<option value="">${escapeHtml(placeholder)}</option>`];
		items.forEach((item) => {
			const value = String(item.id ?? '');
			const selected = String(selectedValue ?? '') === value ? ' selected' : '';
			buffer.push(`<option value="${escapeHtml(value)}"${selected}>${escapeHtml(item.nombre || '')}</option>`);
		});
		return buffer.join('');
	};

	const buildUserMultiOptions = (usuarios, selectedIds) => usuarios.map((user) => {
		const value = Number(user.id || 0);
		const selected = selectedIds.includes(value) ? ' selected' : '';
		return `<option value="${escapeHtml(value)}"${selected}>${escapeHtml(user.nombre || '')}</option>`;
	}).join('');

	const setTaskRowStatus = (row, status, message) => {
		const target = row.querySelector('[data-task-save-status]');
		if (!target) return;
		target.setAttribute('data-save-status', status);
		target.textContent = message;
	};

	const flashTaskRow = (row, className) => {
		if (!row) return;
		row.classList.remove('crm-task-row-saved', 'crm-task-row-error');
		row.classList.add(className);
		setTimeout(() => row.classList.remove(className), 1100);
	};

	const renderTaskPreview = (preview, options) => {
		if (!preview) return;
		const selected = options.filter((option) => option.selected);
		preview.innerHTML = '';
		if (!selected.length) {
			preview.classList.add('empty');
			preview.innerHTML = '<span class="crm-task-empty-text">Sin seleccionados</span>';
			return;
		}

		preview.classList.remove('empty');
		selected.forEach((option) => {
			const pill = document.createElement('span');
			pill.className = 'crm-task-pill';
			pill.innerHTML = '<span class="crm-task-check">✓</span>';
			pill.appendChild(document.createTextNode(option.textContent || ''));
			preview.appendChild(pill);
		});
	};

	const bindTaskSelectBlock = (scope, searchSelector, selectSelector, previewSelector) => {
		const search = scope.querySelector(searchSelector);
		const select = scope.querySelector(selectSelector);
		const preview = scope.querySelector(previewSelector);
		if (!select) return null;

		const options = Array.from(select.options || []);
		const applySearch = () => {
			const expected = normalizeText(search?.value || '');
			options.forEach((option) => {
				const actual = normalizeText(option.textContent || '');
				option.hidden = expected !== '' && !actual.includes(expected);
			});
		};

		if (search) {
			search.addEventListener('input', applySearch);
		}

		select.addEventListener('change', () => renderTaskPreview(preview, options));
		applySearch();
		renderTaskPreview(preview, options);
		return select;
	};

	const renderStudentTasks = (payload, entityId, entityType = 'student') => {
		const container = document.getElementById('tareasList');
		if (!container) return;

		const tasks = Array.isArray(payload.tasks) ? payload.tasks : [];
		const tipos = Array.isArray(payload.tipos_tarea) ? payload.tipos_tarea : [];
		const resultados = Array.isArray(payload.resultados) ? payload.resultados : [];
		const usuarios = Array.isArray(payload.usuarios) ? payload.usuarios : [];

		const taskRowsHtml = tasks.map((task) => {
			const taskId = Number(task.id || 0);
			const locked = Number(task.completado || 0) === 1 || String(task.estado || '').toLowerCase() === 'completada';
			const colIds = parseTaskIds(task.colaboradores_ids);

			return `
				<tr data-task-row data-task-id="${escapeHtml(taskId)}" data-locked="${locked ? '1' : '0'}">
					<td class="crm-task-col-title">
						<div class="fw-semibold">${escapeHtml(task.titulo || '-')}</div>
						<small class="text-muted">${escapeHtml(task.descripcion || '')}</small>
					</td>
					<td>${escapeHtml(task.tipo_tarea_nombre || '-')}</td>
					<td>${escapeHtml(task.propietario_nombre || '-')}</td>
					<td>
						<div class="crm-task-deadline-date">${escapeHtml(task.fecha_vencimiento || '-')} ${escapeHtml(task.hora_vencimiento || '')}</div>
						<span class="crm-deadline-pill" data-crm-task-countdown data-date="${escapeHtml(task.fecha_vencimiento || '')}" data-time="${escapeHtml(task.hora_vencimiento || '')}" data-status="${escapeHtml(task.estado || '')}" data-completed="${locked ? '1' : '0'}">Calculando...</span>
					</td>
					<td>
						<input type="text" class="form-control form-control-sm mb-1" data-task-col-search placeholder="Buscar por nombre..." ${locked ? 'disabled' : ''}>
						<select class="form-select form-select-sm" data-task-col-select multiple size="4" ${locked ? 'disabled' : ''}>${buildUserMultiOptions(usuarios, colIds)}</select>
						<div class="crm-task-preview empty" data-task-col-preview><span class="crm-task-empty-text">Sin seleccionados</span></div>
					</td>
					<td>
						<select class="form-select form-select-sm" data-task-result-select ${locked ? 'disabled' : ''}>${buildOptions(resultados, task.resultado_id || '', 'Sin resultado')}</select>
					</td>
					<td>
						<div class="crm-task-state-wrap">
							<span class="crm-task-status-pill ${locked ? 'is-complete' : 'is-pending'}">Estado actual: ${locked ? 'Completada' : 'Pendiente'}</span>
							<span class="crm-task-save-status" data-task-save-status="${locked ? 'saved' : 'idle'}">${locked ? 'Bloqueada por completado' : 'Auto-guardado activo'}</span>
							${locked
								? '<small class="text-muted">Tarea cerrada, sin edición.</small>'
								: '<label class="form-check d-flex align-items-center gap-2 mb-0"><input type="checkbox" class="form-check-input" data-task-complete-check><span class="form-check-label text-success fw-semibold"><i class="bi bi-check2-square"></i> Marcar completada</span></label>'}
						</div>
					</td>
				</tr>
			`;
		}).join('');

		container.innerHTML = `
			<div class="crm-tasks-shell">
				<form id="crmStudentTaskCreateForm" class="row g-2 mb-3">
					<input type="hidden" name="student_id" value="${escapeHtml(entityId)}">
					<div class="col-md-3">
						<label class="form-label mb-1">Tipo tarea</label>
						<div class="position-relative">
							<select name="tipo_tarea_id" class="form-select form-select-sm pe-5">${buildOptions(tipos, '', 'Seleccionar')}</select>
							<span class="position-absolute top-50 end-0 translate-middle-y pe-2 text-muted"><i class="bi bi-chevron-down"></i></span>
						</div>
					</div>
					<div class="col-md-5">
						<label class="form-label mb-1">Titulo</label>
						<input type="text" name="titulo" class="form-control form-control-sm" required>
					</div>
					<div class="col-md-4">
						<label class="form-label mb-1">Propietario</label>
						<select name="propietario_id" class="form-select form-select-sm" required>${buildOptions(usuarios, '', 'Seleccionar')}</select>
					</div>
					<div class="col-md-5">
						<label class="form-label mb-1">Descripcion</label>
						<textarea name="descripcion" rows="2" class="form-control form-control-sm"></textarea>
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1">Fecha venc.</label>
						<input type="date" name="fecha_vencimiento" class="form-control form-control-sm">
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1">Hora</label>
						<input type="time" name="hora_vencimiento" class="form-control form-control-sm">
					</div>
					<div class="col-md-3">
						<label class="form-label mb-1">Resultado</label>
						<select name="resultado_id" class="form-select form-select-sm">${buildOptions(resultados, '', 'Sin resultado')}</select>
					</div>
					<div class="col-md-3">
						<label class="form-label mb-1">Colaboradores</label>
						<input type="text" class="form-control form-control-sm mb-1" data-create-col-search placeholder="Buscar por nombre...">
						<select name="colaboradores[]" class="form-select form-select-sm" data-create-col-select multiple size="4">${buildUserMultiOptions(usuarios, [])}</select>
						<div class="crm-task-preview empty" data-create-col-preview><span class="crm-task-empty-text">Sin seleccionados</span></div>
					</div>
					<div class="col-md-2 d-grid align-self-end">
						<button type="submit" class="btn btn-primary btn-sm">Guardar tarea</button>
					</div>
				</form>

				<div class="table-responsive">
					<table class="table table-sm align-middle crm-task-table">
						<thead class="table-light">
							<tr>
								<th>Tarea</th>
								<th>Tipo</th>
								<th>Propietario</th>
								<th>Vence</th>
								<th>Colaboradores</th>
								<th>Resultado</th>
								<th>Estado</th>
							</tr>
						</thead>
						<tbody>
							${taskRowsHtml || '<tr><td colspan="7" class="text-center text-muted py-4">Sin tareas registradas.</td></tr>'}
						</tbody>
					</table>
				</div>
			</div>
		`;

		const createForm = document.getElementById('crmStudentTaskCreateForm');
		if (createForm) {
			bindTaskSelectBlock(createForm, '[data-create-col-search]', '[data-create-col-select]', '[data-create-col-preview]');

			createForm.addEventListener('submit', async (event) => {
				event.preventDefault();
				const payload = new URLSearchParams();
				if (String(entityType).toLowerCase() === 'contact') {
					payload.set('contacto_id', String(entityId));
				} else {
					payload.set('student_id', String(entityId));
				}
				payload.set('tipo_tarea_id', String(createForm.querySelector('[name="tipo_tarea_id"]')?.value || ''));
				payload.set('titulo', String(createForm.querySelector('[name="titulo"]')?.value || '').trim());
				payload.set('propietario_id', String(createForm.querySelector('[name="propietario_id"]')?.value || ''));
				payload.set('descripcion', String(createForm.querySelector('[name="descripcion"]')?.value || '').trim());
				payload.set('fecha_vencimiento', String(createForm.querySelector('[name="fecha_vencimiento"]')?.value || ''));
				payload.set('hora_vencimiento', String(createForm.querySelector('[name="hora_vencimiento"]')?.value || ''));
				payload.set('resultado_id', String(createForm.querySelector('[name="resultado_id"]')?.value || ''));
				Array.from(createForm.querySelector('[data-create-col-select]')?.selectedOptions || []).forEach((option) => payload.append('colaboradores[]', String(option.value || '')));

				try {
					const response = await fetch(`${BASE_URL}crm/addStudentTask`, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: payload,
					});
					const data = await response.json();
					if (!response.ok || !data.success) {
						throw new Error(data.error || 'No se pudo crear la tarea');
					}

					await loadStudentTasks(entityId, entityType);
					await loadStudentHistory(entityId, entityType);
				} catch (error) {
					alert(error.message || 'Error al guardar tarea');
				}
			});
		}

		const saveTimers = new Map();
		container.querySelectorAll('[data-task-row]').forEach((row) => {
			const locked = row.getAttribute('data-locked') === '1';
			const taskId = Number(row.getAttribute('data-task-id') || 0);
			bindTaskSelectBlock(row, '[data-task-col-search]', '[data-task-col-select]', '[data-task-col-preview]');

			if (locked || taskId <= 0) {
				return;
			}

			const scheduleParticipantsSave = () => {
				const timerKey = `task-${taskId}`;
				const activeTimer = saveTimers.get(timerKey);
				if (activeTimer) clearTimeout(activeTimer);
				setTaskRowStatus(row, 'pending', 'Cambios pendientes...');
				saveTimers.set(timerKey, setTimeout(async () => {
					const payload = new URLSearchParams();
					if (String(entityType).toLowerCase() === 'contact') {
						payload.set('contacto_id', String(entityId));
					} else {
						payload.set('student_id', String(entityId));
					}
					payload.set('task_id', String(taskId));
					Array.from(row.querySelector('[data-task-col-select]')?.selectedOptions || []).forEach((option) => payload.append('colaboradores[]', String(option.value || '')));

					try {
						setTaskRowStatus(row, 'saving', 'Guardando...');
						const response = await fetch(`${BASE_URL}crm/updateStudentTaskParticipants`, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: payload,
						});
						const data = await response.json();
						if (!response.ok || !data.success) throw new Error(data.error || 'No se pudo guardar.');
						setTaskRowStatus(row, 'saved', 'Auto-guardado listo');
						flashTaskRow(row, 'crm-task-row-saved');
						await loadStudentHistory(entityId, entityType);
					} catch (error) {
						setTaskRowStatus(row, 'error', 'Error al guardar');
						flashTaskRow(row, 'crm-task-row-error');
					}
				}, 450));
			};

			row.querySelector('[data-task-col-select]')?.addEventListener('change', scheduleParticipantsSave);

			row.querySelector('[data-task-result-select]')?.addEventListener('change', async (event) => {
				const payload = new URLSearchParams();
				if (String(entityType).toLowerCase() === 'contact') {
					payload.set('contacto_id', String(entityId));
				} else {
					payload.set('student_id', String(entityId));
				}
				payload.set('task_id', String(taskId));
				payload.set('resultado_id', String(event.target.value || ''));
				try {
					setTaskRowStatus(row, 'saving', 'Guardando resultado...');
					const response = await fetch(`${BASE_URL}crm/updateStudentTaskResult`, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: payload,
					});
					const data = await response.json();
					if (!response.ok || !data.success) throw new Error(data.error || 'No se pudo guardar resultado.');
					setTaskRowStatus(row, 'saved', 'Resultado guardado');
					flashTaskRow(row, 'crm-task-row-saved');
					await loadStudentHistory(entityId, entityType);
				} catch (error) {
					setTaskRowStatus(row, 'error', 'Error al guardar resultado');
					flashTaskRow(row, 'crm-task-row-error');
				}
			});

			row.querySelector('[data-task-complete-check]')?.addEventListener('change', async (event) => {
				if (!event.target.checked) {
					return;
				}
				const payload = new URLSearchParams();
				if (String(entityType).toLowerCase() === 'contact') {
					payload.set('contacto_id', String(entityId));
				} else {
					payload.set('student_id', String(entityId));
				}
				payload.set('task_id', String(taskId));
				try {
					setTaskRowStatus(row, 'saving', 'Cerrando tarea...');
					const response = await fetch(`${BASE_URL}crm/completeStudentTask`, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: payload,
					});
					const data = await response.json();
					if (!response.ok || !data.success) throw new Error(data.error || 'No se pudo completar la tarea');
					await loadStudentTasks(entityId, entityType);
					await loadStudentHistory(entityId, entityType);
				} catch (error) {
					event.target.checked = false;
					setTaskRowStatus(row, 'error', 'Error al completar');
					flashTaskRow(row, 'crm-task-row-error');
				}
			});
		});

		renderCrmTaskCountdowns(container);
	};

	const loadStudentTasks = async (entityId, entityType = 'student') => {
		try {
			const response = await fetch(`${BASE_URL}crm/getStudentTasks?${buildEntityQuery(entityId, entityType)}`);
			if (!response.ok) throw new Error('Error al cargar tareas');
			const data = await response.json();
			if (!data.success) throw new Error(data.error || 'Error desconocido');
			renderStudentTasks(data, entityId, entityType);
		} catch (error) {
			console.error('Error:', error);
			const container = document.getElementById('tareasList');
			if (container) {
				container.innerHTML = `<div class="alert alert-danger alert-sm">${escapeHtml(error.message)}</div>`;
			}
		}
	};

	const loadStudentTickets = async (entityId, entityType = 'student') => {
		try {
			const response = await fetch(`${BASE_URL}crm/getStudentTicketsByEmail?${buildEntityQuery(entityId, entityType)}`);
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

	const loadStudentNotes = async (entityId, entityType = 'student') => {
		try {
			const response = await fetch(`${BASE_URL}crm/getStudentNotes?student_id=${encodeURIComponent(String(entityId || 0))}`);
			let data = null;
			try {
				data = await response.json();
			} catch (parseError) {
				data = null;
			}

			if (!response.ok || !data || !data.success) {
				throw new Error((data && data.error) ? data.error : 'Error al cargar notas internas');
			}

			renderStudentNotes(data.notes || [], entityId, entityType);
		} catch (error) {
			const container = document.getElementById('internalNotesList');
			if (container) {
				container.innerHTML = `<div class="alert alert-danger alert-sm">${escapeHtml(error.message || 'Error al cargar notas internas')}</div>`;
			}
		}
	};

	const renderStudentNotes = (notes, entityId, entityType = 'student') => {
		const container = document.getElementById('internalNotesList');
		if (!container) return;
		const pipelineModalEl = document.getElementById('studentPipelineModal');
		const editModalEl = document.getElementById('crmNoteEditModal');
		const editNoteIdInput = document.getElementById('crmNoteEditId');
		const editTextarea = document.getElementById('crmNoteEditText');
		const editSubmitBtn = document.getElementById('crmNoteEditSubmit');
		const editAttachmentsList = document.getElementById('crmNoteEditAttachmentsList');
		const editNewAttachmentsInput = document.getElementById('crmNoteEditNewAttachments');
		const deleteModalEl = document.getElementById('crmNoteDeleteModal');
		const deleteNoteIdInput = document.getElementById('crmNoteDeleteId');
		const deletePreview = document.getElementById('crmNoteDeletePreview');
		const deleteSubmitBtn = document.getElementById('crmNoteDeleteSubmit');
		let restorePipelineAfterNoteModal = false;
		let removeAttachmentIds = new Set();

		const noteById = new Map();
		notes.forEach((note) => {
			const id = Number(note?.id || 0);
			if (id > 0) {
				noteById.set(id, note);
			}
		});

		const renderEditAttachments = (note) => {
			if (!editAttachmentsList) {
				return;
			}
			const attachments = Array.isArray(note?.attachments) ? note.attachments : [];
			if (!attachments.length) {
				editAttachmentsList.innerHTML = '<span class="text-muted">Sin adjuntos.</span>';
				return;
			}

			const html = attachments.map((att) => {
				const attId = Number(att?.id || 0);
				const attName = escapeHtml(att?.filename || 'Adjunto');
				const checked = removeAttachmentIds.has(attId) ? 'checked' : '';
				return `
					<div class="form-check mb-1">
						<input class="form-check-input crm-note-remove-att" type="checkbox" value="${attId}" id="crmNoteRemoveAtt${attId}" ${checked}>
						<label class="form-check-label" for="crmNoteRemoveAtt${attId}">
							Quitar ${attName}
							<a class="ms-1" href="${BASE_URL}crm/note-attachment/${attId}" target="_blank" rel="noopener noreferrer">(ver)</a>
						</label>
					</div>
				`;
			}).join('');

			editAttachmentsList.innerHTML = html;
			editAttachmentsList.querySelectorAll('.crm-note-remove-att').forEach((checkbox) => {
				checkbox.addEventListener('change', () => {
					const id = Number(checkbox.value || 0);
					if (id <= 0) return;
					if (checkbox.checked) {
						removeAttachmentIds.add(id);
					} else {
						removeAttachmentIds.delete(id);
					}
				});
			});
		};

		const markRestorePipeline = () => {
			restorePipelineAfterNoteModal = Boolean(pipelineModalEl && pipelineModalEl.classList.contains('show'));
		};

		const restorePipelineModal = () => {
			if (!restorePipelineAfterNoteModal || !pipelineModalEl || !window.bootstrap?.Modal) {
				restorePipelineAfterNoteModal = false;
				return;
			}
			const instance = window.bootstrap.Modal.getOrCreateInstance(pipelineModalEl);
			instance.show();
			restorePipelineAfterNoteModal = false;
		};

		if (editModalEl && !editModalEl.dataset.restoreBound) {
			editModalEl.addEventListener('hidden.bs.modal', () => {
				restorePipelineModal();
			});
			editModalEl.dataset.restoreBound = '1';
		}

		if (deleteModalEl && !deleteModalEl.dataset.restoreBound) {
			deleteModalEl.addEventListener('hidden.bs.modal', () => {
				restorePipelineModal();
			});
			deleteModalEl.dataset.restoreBound = '1';
		}

		if (!Array.isArray(notes) || notes.length === 0) {
			container.innerHTML = '<p class="text-muted small text-center py-3">No hay notas internas registradas.</p>';
			if (editNoteIdInput) editNoteIdInput.value = '';
			if (deleteNoteIdInput) deleteNoteIdInput.value = '';
			return;
		}

		let html = '<div class="list-group">';
		notes.forEach((note) => {
			const noteText = String(note.note_text || '').trim();
			const userName = String(note.user_name || 'Usuario');
			const rawDate = String(note.created_at || '');
			const parsedDate = rawDate ? new Date(rawDate) : null;
			const dateLabel = parsedDate && !Number.isNaN(parsedDate.getTime())
				? parsedDate.toLocaleString('es-ES')
				: '-';

			const noteId = Number(note.id || 0);
			const attachments = Array.isArray(note.attachments) ? note.attachments : [];
			const attachmentsHtml = attachments.length > 0
				? `<div class="mt-2">${attachments.map((att) => {
					const attId = Number(att.id || 0);
					const attName = escapeHtml(att.filename || 'Adjunto');
					return `<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="${BASE_URL}crm/note-attachment/${attId}"><i class="bi bi-paperclip"></i> ${attName}</a>`;
				}).join('')}</div>`
				: '';

			html += `
				<div class="list-group-item">
					<div class="d-flex justify-content-between align-items-start gap-2">
						<div>
							<div class="fw-semibold">${escapeHtml(userName)}</div>
							<div>${escapeHtml(noteText)}</div>
							${attachmentsHtml}
							<div class="mt-2">
								<button type="button" class="btn btn-sm btn-outline-primary crm-note-edit-btn" data-note-id="${noteId}" data-note-text="${escapeHtml(noteText)}" data-bs-toggle="modal" data-bs-target="#crmNoteEditModal">Editar</button>
								<button type="button" class="btn btn-sm btn-outline-danger crm-note-delete-btn" data-note-id="${noteId}" data-note-text="${escapeHtml(noteText)}" data-bs-toggle="modal" data-bs-target="#crmNoteDeleteModal">Eliminar</button>
							</div>
						</div>
						<small class="text-muted text-nowrap">${escapeHtml(dateLabel)}</small>
					</div>
				</div>
			`;
		});
		html += '</div>';

		container.innerHTML = html;
		container.querySelectorAll('.crm-note-edit-btn').forEach((button) => {
			button.addEventListener('click', () => {
				markRestorePipeline();
				const noteId = Number(button.getAttribute('data-note-id') || 0);
				const currentText = String(button.getAttribute('data-note-text') || '');
				if (noteId <= 0) return;
				removeAttachmentIds = new Set();
				if (editNoteIdInput) editNoteIdInput.value = String(noteId);
				if (editTextarea) editTextarea.value = currentText;
				if (editNewAttachmentsInput) {
					editNewAttachmentsInput.value = '';
				}
				renderEditAttachments(noteById.get(noteId) || null);
			});
		});

		container.querySelectorAll('.crm-note-delete-btn').forEach((button) => {
			button.addEventListener('click', () => {
				markRestorePipeline();
				const noteId = Number(button.getAttribute('data-note-id') || 0);
				if (noteId <= 0) return;
				if (deleteNoteIdInput) deleteNoteIdInput.value = String(noteId);
				if (deletePreview) {
					const previewText = String(button.getAttribute('data-note-text') || '').trim();
					deletePreview.textContent = previewText !== '' ? previewText : 'Nota sin contenido visible.';
				}
			});
		});

		if (editSubmitBtn) {
			editSubmitBtn.onclick = async () => {
				const noteId = Number(editNoteIdInput?.value || 0);
				const finalText = String(editTextarea?.value || '').trim();
				if (noteId <= 0) {
					window.showGlobalNotification('No se pudo identificar la nota.', 'warning');
					return;
				}
				if (finalText === '') {
					window.showGlobalNotification('La nota no puede quedar vacía.', 'warning');
					return;
				}
				try {
					editSubmitBtn.disabled = true;
					const payload = new FormData();
					payload.append('note_id', String(noteId));
					payload.append('note_text', finalText);
					if (removeAttachmentIds.size > 0) {
						payload.append('remove_attachment_ids', Array.from(removeAttachmentIds).join(','));
					}
					const newFiles = Array.from(editNewAttachmentsInput?.files || []);
					newFiles.forEach((file) => {
						payload.append('attachments[]', file);
					});
					const response = await fetch(`${BASE_URL}crm/updateStudentNote`, {
						method: 'POST',
						body: payload,
					});
					const data = await response.json();
					if (!response.ok || !data.success) {
						throw new Error(data.error || 'No se pudo editar la nota.');
					}
					if (editModalEl && window.bootstrap?.Modal) {
						window.bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
					}
					await loadStudentNotes(entityId, entityType);
					await loadStudentHistory(entityId, entityType);
					if (Array.isArray(data?.attachment_errors) && data.attachment_errors.length > 0) {
						window.showGlobalNotification(`Nota actualizada con advertencias: ${data.attachment_errors.join(' ')}`, 'warning');
					} else {
						window.showGlobalNotification('Nota actualizada.', 'success');
					}
				} catch (error) {
					window.showGlobalNotification(error.message || 'Error al actualizar nota.', 'danger');
				} finally {
					editSubmitBtn.disabled = false;
					removeAttachmentIds = new Set();
				}
			};
		}

		if (deleteSubmitBtn) {
			deleteSubmitBtn.onclick = async () => {
				const noteId = Number(deleteNoteIdInput?.value || 0);
				if (noteId <= 0) {
					window.showGlobalNotification('No se pudo identificar la nota.', 'warning');
					return;
				}
				try {
					deleteSubmitBtn.disabled = true;
					const payload = new URLSearchParams({ note_id: String(noteId) });
					const response = await fetch(`${BASE_URL}crm/deleteStudentNote`, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: payload,
					});
					const data = await response.json();
					if (!response.ok || !data.success) {
						throw new Error(data.error || 'No se pudo eliminar la nota.');
					}
					if (deleteModalEl && window.bootstrap?.Modal) {
						window.bootstrap.Modal.getOrCreateInstance(deleteModalEl).hide();
					}
					await loadStudentNotes(entityId, entityType);
					await loadStudentHistory(entityId, entityType);
					window.showGlobalNotification('Nota eliminada.', 'success');
				} catch (error) {
					window.showGlobalNotification(error.message || 'Error al eliminar nota.', 'danger');
				} finally {
					deleteSubmitBtn.disabled = false;
				}
			};
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
			const entityId = Number(savePipelineBtn.getAttribute('data-student-id') || 0);
			const entityType = String(savePipelineBtn.getAttribute('data-entity-type') || 'student').toLowerCase() === 'contact' ? 'contact' : 'student';
			const explicitStatesRaw = String(document.getElementById('pipelineStateIds')?.value || '');
			const explicitStates = explicitStatesRaw
				.split(',')
				.map((value) => Number((value || '').trim() || 0))
				.filter((value) => value > 0);
			const fallbackState = Number(document.getElementById('pipelineSelect')?.value || 0);
			if (explicitStates.length === 0 && fallbackState > 0) {
				explicitStates.push(fallbackState);
			}
			const estadoIds = Array.from(new Set(explicitStates));
			const statusBox = document.getElementById('pipelineSaveStatus');

			if (statusBox) {
				statusBox.className = 'd-none';
				statusBox.textContent = '';
			}
			if (entityId <= 0 || estadoIds.length === 0) {
				if (statusBox) {
					statusBox.className = 'alert alert-warning py-2 mb-3';
					statusBox.textContent = 'Selecciona al menos una etapa de pipeline antes de guardar.';
				}
				return;
			}

			const payload = new URLSearchParams({
				estado_id: String(estadoIds[0]),
				estado_ids: estadoIds.join(','),
			});
			if (entityType === 'contact') {
				payload.set('contacto_id', String(entityId));
			} else {
				payload.set('student_id', String(entityId));
			}

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

				const row = document.querySelector(`tr[data-student-id="${entityId}"]`);
				if (row) {
					const pipelineCell = row.querySelector('.pipeline-col');
					if (pipelineCell) {
						pipelineCell.innerHTML = `<span class="badge text-bg-light border">${escapeHtml(data.pipeline_nombre || 'Actualizado')}</span>`;
					}
				}

				await loadStudentPipeline(entityId, entityType);
				const refreshedStatusBox = document.getElementById('pipelineSaveStatus');
				if (refreshedStatusBox) {
					refreshedStatusBox.className = 'alert alert-success py-2 mb-3';
					refreshedStatusBox.textContent = 'Etapas actualizadas correctamente.';
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

	const saveProspectBtn = document.getElementById('saveProspectBtn');
	if (saveProspectBtn) {
		saveProspectBtn.addEventListener('click', async () => {
			const contactId = Number(saveProspectBtn.getAttribute('data-contact-id') || 0);
			const statusBox = document.getElementById('prospectSaveStatus');
			if (contactId <= 0) {
				if (statusBox) {
					statusBox.className = 'alert alert-warning py-2 mb-3';
					statusBox.textContent = 'No se pudo identificar el cliente potencial.';
				}
				return;
			}

			const payload = new URLSearchParams({
				contacto_id: String(contactId),
				nombres: String(document.getElementById('prospectEditNombres')?.value || '').trim(),
				apellidos: String(document.getElementById('prospectEditApellidos')?.value || '').trim(),
				identificacion: String(document.getElementById('prospectEditIdentificacion')?.value || '').trim(),
				correo_personal: String(document.getElementById('prospectEditCorreo')?.value || '').trim(),
				celular: String(document.getElementById('prospectEditCelular')?.value || '').trim(),
				propietario: String(document.getElementById('prospectEditPropietario')?.value || '').trim(),
				creado_por: String(document.getElementById('prospectEditCreadoPor')?.value || '').trim(),
				estado_id: String(document.getElementById('prospectEditEstadoId')?.value || '').trim(),
				carrera: String(document.getElementById('prospectEditCarreraSelect')?.value || '').trim(),
				modalidad: String(document.getElementById('prospectEditModalidad')?.value || '').trim(),
				provincia: String(document.getElementById('prospectEditProvincia')?.value || '').trim(),
				ciudad: String(document.getElementById('prospectEditCiudad')?.value || '').trim(),
			});

			try {
				saveProspectBtn.disabled = true;
				if (statusBox) {
					statusBox.className = 'alert alert-info py-2 mb-3';
					statusBox.textContent = 'Guardando cambios...';
				}

				const response = await fetch(`${BASE_URL}crm/updateProspect`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: payload,
				});

				const data = await response.json();
				if (!response.ok || !data.success) {
					throw new Error(data.error || 'No se pudo actualizar el cliente potencial');
				}

				const row = document.querySelector(`tr[data-prospect-contact-id="${contactId}"]`);
				if (row) {
					const newName = `${String(document.getElementById('prospectEditNombres')?.value || '').trim()} ${String(document.getElementById('prospectEditApellidos')?.value || '').trim()}`.replace(/\s+/g, ' ').trim();
					const newPhoneRaw = String(document.getElementById('prospectEditCelular')?.value || '').trim();
					let digitsPhone = newPhoneRaw.replace(/[^0-9]/g, '');
					if (digitsPhone.length === 10 && digitsPhone.startsWith('0')) {
						digitsPhone = `593${digitsPhone.slice(1)}`;
					}
					const contactLink = row.querySelector('.prospect-edit-link');
					if (contactLink) {
						contactLink.textContent = newName || '-';
					}

					const cells = row.querySelectorAll('td');
					if (cells.length >= 8) {
						const selectedCareerOption = document.getElementById('prospectEditCarreraSelect')?.selectedOptions?.[0];
						const selectedCareerText = String(selectedCareerOption?.textContent || document.getElementById('prospectEditCarreraSelect')?.value || '-').trim();
						cells[2].textContent = selectedCareerText || '-';
						const stageOption = document.getElementById('prospectEditEstadoId')?.selectedOptions?.[0];
						const stageText = String(stageOption?.textContent || 'Sin etapa').trim();
						cells[3].innerHTML = `<span class="badge text-bg-light border">${escapeHtml(stageText)}</span>`;
						if (digitsPhone !== '') {
							cells[4].innerHTML = `<a href="https://wa.me/${escapeHtml(digitsPhone)}" target="_blank" rel="noopener noreferrer">${escapeHtml(newPhoneRaw || digitsPhone)}</a>`;
						} else {
							cells[4].textContent = newPhoneRaw || '-';
						}
						cells[5].textContent = String(document.getElementById('prospectEditPropietario')?.value || '-') || '-';
						cells[6].textContent = String(document.getElementById('prospectEditCreadoPor')?.value || '-') || '-';
						row.setAttribute('data-prospect-name', normalizeText(newName));
						row.setAttribute('data-prospect-phone', newPhoneRaw.replace(/[^0-9]/g, ''));
						row.setAttribute('data-prospect-origin', normalizeText(document.getElementById('prospectEditPropietario')?.value || ''));
						row.setAttribute('data-prospect-stage', normalizeText(stageText));
						row.setAttribute('data-prospect-career', normalizeText(selectedCareerText));
						const createdByRaw = String(document.getElementById('prospectEditCreadoPor')?.value || '');
						const createdByNormalized = createdByRaw
							.split(',')
							.map((value) => normalizeText(value))
							.filter((value) => value !== '')
							.join('|');
						row.setAttribute('data-prospect-created-by', createdByNormalized);
					}
				}

				if (statusBox) {
					statusBox.className = 'alert alert-success py-2 mb-3';
					statusBox.textContent = 'Cliente potencial actualizado correctamente.';
				}

				applyProspectFilters();
			} catch (error) {
				if (statusBox) {
					statusBox.className = 'alert alert-danger py-2 mb-3';
					statusBox.textContent = error.message || 'Error al guardar cambios';
				}
			} finally {
				saveProspectBtn.disabled = false;
			}
		});
	}
});
