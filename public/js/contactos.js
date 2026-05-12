console.debug('Modulo contactos cargado');

document.addEventListener('DOMContentLoaded', () => {
	const table = document.querySelector('[data-contactos-table]');
	if (!table) return;

	const filters = Array.from(document.querySelectorAll('[data-filter]'));
	const rows = Array.from(table.querySelectorAll('tbody tr'));
	const counter = document.querySelector('[data-contactos-counter]');

	const applyFilters = () => {
		let visible = 0;
		rows.forEach((row) => {
			let matches = true;
			filters.forEach((input) => {
				const key = input.getAttribute('data-filter') || '';
				const expected = (input.value || '').toLowerCase().trim();
				const cell = row.querySelector(`[data-column="${key}"]`);
				const actual = (cell?.textContent || '').toLowerCase().trim();
				if (expected && !actual.includes(expected)) {
					matches = false;
				}
			});

			row.style.display = matches ? '' : 'none';
			if (matches) visible += 1;
		});

		if (counter) {
			counter.textContent = `Mostrando ${visible} de ${rows.length} registros`;
		}
	};

	filters.forEach((input) => {
		input.addEventListener('input', applyFilters);
	});

	applyFilters();
});
