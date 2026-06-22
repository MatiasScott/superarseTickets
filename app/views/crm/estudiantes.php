<section class="module-page crm-page">
	<?php $estudiantes = $estudiantes ?? []; ?>
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 crm-header">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-mortarboard"></i> CRM - Estudiantes</h1>
				<p class="text-muted mb-0">Listado académico con filtros rápidos.</p>
			</div>
		</div>

		<div class="card border-0 shadow-sm mb-3">
			<div class="card-body py-3">
			<div class="row g-2 align-items-end">
				<div class="col-md-5">
					<label for="filterEstudiantesNombre" class="form-label mb-1 fw-semibold"><i class="bi bi-search"></i> Buscar por nombre</label>
					<input type="text" id="filterEstudiantesNombre" class="form-control" placeholder="Ej: Maria Lopez">
				</div>
				<div class="col-md-4">
					<label for="filterEstudiantesCarrera" class="form-label mb-1 fw-semibold"><i class="bi bi-book"></i> Filtrar por carrera</label>
					<input type="text" id="filterEstudiantesCarrera" class="form-control" placeholder="Ej: Administración">
				</div>
				<div class="col-md-3 d-grid">
					<button type="button" id="btnLimpiarEstudiantes" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Limpiar filtros</button>
			</div>
		</div>

		<div class="table-responsive" data-mobile-cards>
			<div id="estudiantesContainer"></div>
		</div>
		<div class="module-counter text-muted small mt-2 text-end"><span id="contadorEstudiantes">Mostrando 0 de 0 registros</span></div>

		<script>
			const filterNombre = document.getElementById('filterEstudiantesNombre');
			const filterCarrera = document.getElementById('filterEstudiantesCarrera');
			const btnLimpiar = document.getElementById('btnLimpiarEstudiantes');
			const container = document.getElementById('estudiantesContainer');
			const contadorSpan = document.getElementById('contadorEstudiantes');

			const cargarEstudiantes = async () => {
				const nombre = filterNombre.value.trim();
				const carrera = filterCarrera.value.trim();

				const params = new URLSearchParams();
				if (nombre) params.append('nombre', nombre);
				if (carrera) params.append('carrera', carrera);

				try {
					const response = await fetch(`<?= base_url('crm/estudiantesFilter') ?>?${params.toString()}`);
					if (!response.ok) throw new Error('Error en la solicitud');

					const data = await response.json();
					if (!data.success) throw new Error('Respuesta inválida');

					const estudiantes = data.estudiantes || [];
					const total = data.total || 0;

					contadorSpan.textContent = `Mostrando ${estudiantes.length} de ${total} registros`;

					if (estudiantes.length === 0) {
						container.innerHTML = `
							<table class="table table-hover align-middle">
								<thead><tr><th colspan="5" class="text-center text-muted py-4">No hay estudiantes para mostrar.</th></tr></thead>
							</table>
						`;
						return;
					}

					let html = `
						<table class="table table-hover align-middle">
							<thead>
								<tr>
									<th>ID</th>
									<th>Código</th>
									<th>Nombre</th>
									<th>Carrera</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody>
					`;

					estudiantes.forEach((item) => {
						const nombre = `${item.nombre || ''} ${item.apellido || ''}`.trim();
						const codigo = item.numero_identificacion || '-';
						const carrera = item.carrera || '-';
						const estado = item.estado || '-';

						html += `
							<tr>
								<td>${escapeHtml(item.id)}</td>
								<td>${escapeHtml(codigo)}</td>
								<td>${escapeHtml(nombre)}</td>
								<td>${escapeHtml(carrera)}</td>
								<td><span class="badge text-bg-light border">${escapeHtml(estado)}</span></td>
							</tr>
						`;
					});

					html += `
							</tbody>
						</table>
					`;

					container.innerHTML = html;
				} catch (error) {
					console.error('Error al cargar estudiantes:', error);
					container.innerHTML = `<div class="alert alert-danger">Error al cargar estudiantes. Intenta nuevamente.</div>`;
				}
			};

			const escapeHtml = (text) => {
				const map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return (text || '').toString().replace(/[&<>"']/g, (m) => map[m]);
			};

			// Event listeners
			filterNombre.addEventListener('change', cargarEstudiantes);
			filterNombre.addEventListener('keyup', cargarEstudiantes);
			filterCarrera.addEventListener('change', cargarEstudiantes);
			filterCarrera.addEventListener('keyup', cargarEstudiantes);

			btnLimpiar.addEventListener('click', () => {
				filterNombre.value = '';
				filterCarrera.value = '';
				cargarEstudiantes();
			});

			// Cargar estudiantes al iniciar
			cargarEstudiantes();
		</script>
	</div>
</section>
