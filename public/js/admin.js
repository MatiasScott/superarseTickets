/**
 * ADMIN - Scripts generales
 * Filtros, confirmaciones, validaciones
 */

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
	initAdminFilters();
	initDeleteConfirmations();
	initFormValidation();
	initTableSorting();
});

/**
 * Filtro dinámico en tablas
 */
function initAdminFilters() {
	const filterInputs = document.querySelectorAll('[data-filter]');
	const filterTable = document.querySelector('[data-filter-table]');

	if (!filterTable || filterInputs.length === 0) return;

	filterInputs.forEach(input => {
		input.addEventListener('input', function () {
			filterTableRows(filterTable);
		});
	});
}

function filterTableRows(table) {
	const filterInputs = document.querySelectorAll('[data-filter]');
	const tbody = table.querySelector('tbody');
	const rows = tbody.querySelectorAll('tr');

	rows.forEach(row => {
		let matches = true;

		filterInputs.forEach(input => {
			const column = input.dataset.filter;
			const searchValue = input.value.toLowerCase();
			const cellText = row.querySelector(`td[data-column="${column}"]`)?.textContent.toLowerCase() || '';

			if (searchValue && !cellText.includes(searchValue)) {
				matches = false;
			}
		});

		row.style.display = matches ? '' : 'none';
	});

	updateRowCount(table);
}

function updateRowCount(table) {
	const tbody = table.querySelector('tbody');
	const visibleRows = tbody.querySelectorAll('tr:not([style*="display: none"])').length;
	const totalRows = tbody.querySelectorAll('tr').length;

	const counter = table.querySelector('[data-row-counter]');
	if (counter) {
		counter.textContent = `Mostrando ${visibleRows} de ${totalRows} registros`;
	}
}

/**
 * Confirmación de eliminación
 */
function initDeleteConfirmations() {
	const deleteForms = document.querySelectorAll('[data-confirm-delete]');

	deleteForms.forEach(form => {
		form.addEventListener('submit', function (e) {
			const message = this.dataset.confirmDelete || '¿Estás seguro de que deseas desactivar este registro?';
			if (!confirm(message)) {
				e.preventDefault();
				return false;
			}
		});
	});
}

/**
 * Validación de formularios
 */
function initFormValidation() {
	const forms = document.querySelectorAll('[data-validate]');

	forms.forEach(form => {
		form.addEventListener('submit', function (e) {
			if (!validateForm(this)) {
				e.preventDefault();
			}
		});
	});
}

function validateForm(form) {
	let isValid = true;

	// Validar campos requeridos
	const requiredFields = form.querySelectorAll('[required]');
	requiredFields.forEach(field => {
		if (!field.value.trim()) {
			markFieldError(field, 'Este campo es requerido');
			isValid = false;
		} else {
			clearFieldError(field);
		}
	});

	// Validar emails
	const emailFields = form.querySelectorAll('[type="email"]');
	emailFields.forEach(field => {
		if (field.value && !isValidEmail(field.value)) {
			markFieldError(field, 'Email inválido');
			isValid = false;
		}
	});

	return isValid;
}

function isValidEmail(email) {
	const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	return re.test(email);
}

function markFieldError(field, message) {
	field.classList.add('is-invalid');
	const feedback = field.parentElement.querySelector('.invalid-feedback') || createFeedbackElement(message);
	if (!feedback.parentElement) {
		field.parentElement.appendChild(feedback);
	}
}

function clearFieldError(field) {
	field.classList.remove('is-invalid');
	const feedback = field.parentElement.querySelector('.invalid-feedback');
	if (feedback) {
		feedback.remove();
	}
}

function createFeedbackElement(message) {
	const div = document.createElement('div');
	div.className = 'invalid-feedback';
	div.textContent = message;
	return div;
}

/**
 * Ordenamiento de tablas
 */
function initTableSorting() {
	const tables = document.querySelectorAll('[data-sortable]');

	tables.forEach(table => {
		const headers = table.querySelectorAll('th[data-sort]');

		headers.forEach(header => {
			header.style.cursor = 'pointer';
			header.addEventListener('click', function () {
				sortTable(table, this.dataset.sort);
			});
		});
	});
}

function sortTable(table, column) {
	const tbody = table.querySelector('tbody');
	const rows = Array.from(tbody.querySelectorAll('tr'));
	const isAsc = table.dataset.sortOrder === 'asc';

	rows.sort((a, b) => {
		const aVal = a.querySelector(`td[data-column="${column}"]`)?.textContent.trim() || '';
		const bVal = b.querySelector(`td[data-column="${column}"]`)?.textContent.trim() || '';

		// Intentar convertir a números
		const aNum = parseFloat(aVal);
		const bNum = parseFloat(bVal);

		if (!isNaN(aNum) && !isNaN(bNum)) {
			return isAsc ? aNum - bNum : bNum - aNum;
		}

		return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
	});

	tbody.innerHTML = '';
	rows.forEach(row => tbody.appendChild(row));

	// Actualizar indicador de orden
	table.dataset.sortOrder = isAsc ? 'desc' : 'asc';
}

/**
 * Utilidades
 */

// Seleccionar/deseleccionar todo
function toggleAllCheckboxes(masterCheckbox, className) {
	const checkboxes = document.querySelectorAll('.' + className);
	checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
}

// Acción en lote
function performBulkAction(action, selectedIds) {
	if (selectedIds.length === 0) {
		alert('Selecciona al menos un registro');
		return false;
	}
	return confirm(`¿Ejecutar "${action}" en ${selectedIds.length} registro(s)?`);
}

// Mostrar/ocultar elemento
function toggleElement(selector) {
	const element = document.querySelector(selector);
	if (element) {
		element.style.display = element.style.display === 'none' ? '' : 'none';
	}
}

// Copiar al portapapeles
function copyToClipboard(text) {
	navigator.clipboard.writeText(text).then(() => {
		alert('Copiado al portapapeles');
	}).catch(() => {
		alert('Error al copiar');
	});
}
