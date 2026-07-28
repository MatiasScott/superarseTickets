<?php
Auth::requireAuth();
?>

<section class="module-page">
	<div class="container-fluid">
		<div class="row mb-4">
			<div class="col-12">
				<h2><i class="bi bi-gear"></i> Gestionar Modalidades CRM</h2>
				<p class="text-muted">Configure las opciones de modalidad disponibles para los clientes potenciales</p>
			</div>
		</div>

		<div class="row">
			<div class="col-md-8">
				<div class="card">
					<div class="card-header">
						<h5 class="mb-0">Modalidades Disponibles</h5>
					</div>
					<div class="table-responsive">
						<table class="table table-hover mb-0">
							<thead class="table-light">
								<tr>
									<th>Nombre</th>
									<th>Descripción</th>
									<th>Orden</th>
									<th>Estado</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody id="modalidadesTableBody">
								<?php $modalidades = $modalidades ?? []; ?>
								<?php foreach ($modalidades as $modalidad): ?>
									<tr data-modalidad-id="<?= $modalidad['id'] ?>">
										<td><strong><?= e($modalidad['nombre']) ?></strong></td>
										<td><?= e($modalidad['descripcion'] ?? '-') ?></td>
										<td><span class="badge bg-info"><?= $modalidad['orden'] ?></span></td>
										<td>
											<?php if ($modalidad['activo']): ?>
												<span class="badge bg-success">Activo</span>
											<?php else: ?>
												<span class="badge bg-secondary">Inactivo</span>
											<?php endif; ?>
										</td>
										<td>
											<button class="btn btn-sm btn-outline-primary edit-modalidad" data-id="<?= $modalidad['id'] ?>">
												<i class="bi bi-pencil"></i> Editar
											</button>
											<button class="btn btn-sm btn-outline-danger delete-modalidad" data-id="<?= $modalidad['id'] ?>">
												<i class="bi bi-trash"></i> Eliminar
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-md-4">
				<div class="card">
					<div class="card-header">
						<h5 class="mb-0">Nueva Modalidad</h5>
					</div>
					<div class="card-body">
						<form id="modalidadForm">
							<input type="hidden" id="modalidadId" value="">
							
							<div class="mb-3">
								<label for="modalidadNombre" class="form-label">Nombre *</label>
								<input type="text" id="modalidadNombre" class="form-control" required maxlength="80" placeholder="Ej: Presencial">
							</div>

							<div class="mb-3">
								<label for="modalidadDescripcion" class="form-label">Descripción</label>
								<textarea id="modalidadDescripcion" class="form-control" rows="3" maxlength="255" placeholder="Descripción de la modalidad..."></textarea>
							</div>

							<div class="mb-3">
								<label for="modalidadOrden" class="form-label">Orden</label>
								<input type="number" id="modalidadOrden" class="form-control" value="0" min="0">
							</div>

							<div class="mb-3 form-check">
								<input type="checkbox" id="modalidadActivo" class="form-check-input" checked>
								<label class="form-check-label" for="modalidadActivo">Activo</label>
							</div>

							<div id="modalidadStatus"></div>

							<button type="submit" class="btn btn-primary w-100">
								<i class="bi bi-check-circle"></i> Guardar Modalidad
							</button>
							<button type="button" class="btn btn-secondary w-100 mt-2" id="resetModalidadBtn" style="display: none;">
								Limpiar Formulario
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<script>
const API_BASE = '<?= base_url() ?>';

document.addEventListener('DOMContentLoaded', () => {
	const modalidadForm = document.getElementById('modalidadForm');
	const modalidadId = document.getElementById('modalidadId');
	const modalidadNombre = document.getElementById('modalidadNombre');
	const modalidadDescripcion = document.getElementById('modalidadDescripcion');
	const modalidadOrden = document.getElementById('modalidadOrden');
	const modalidadActivo = document.getElementById('modalidadActivo');
	const modalidadStatus = document.getElementById('modalidadStatus');
	const resetBtn = document.getElementById('resetModalidadBtn');
	const tableBody = document.getElementById('modalidadesTableBody');

	const showStatus = (message, type = 'success') => {
		modalidadStatus.innerHTML = `<div class="alert alert-${type} py-2 mb-0">${message}</div>`;
		setTimeout(() => {
			modalidadStatus.innerHTML = '';
		}, 3000);
	};

	const resetForm = () => {
		modalidadForm.reset();
		modalidadId.value = '';
		resetBtn.style.display = 'none';
		modalidadNombre.focus();
	};

	// Enviar formulario
	modalidadForm.addEventListener('submit', async (e) => {
		e.preventDefault();

		const id = parseInt(modalidadId.value) || 0;
		const nombre = modalidadNombre.value.trim();
		const descripcion = modalidadDescripcion.value.trim();
		const orden = parseInt(modalidadOrden.value) || 0;
		const activo = modalidadActivo.checked ? 1 : 0;

		if (!nombre) {
			showStatus('El nombre es requerido', 'warning');
			return;
		}

		try {
			const response = await fetch(API_BASE + '/crm/modalidades', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					id: id,
					nombre: nombre,
					descripcion: descripcion,
					orden: orden,
					activo: activo
				})
			});

			const text = await response.text();
			let data;
			try {
				data = JSON.parse(text);
			} catch (e) {
				console.error('Respuesta del servidor:', text);
				showStatus('Error: Respuesta inválida del servidor', 'danger');
				return;
			}

			if (data.success) {
				showStatus(data.message, 'success');
				if (!id) {
					// Recargar página para mostrar nueva modalidad
					setTimeout(() => location.reload(), 500);
				} else {
					resetForm();
					location.reload();
				}
			} else {
				showStatus(data.error || 'Error al guardar', 'danger');
			}
		} catch (error) {
			showStatus('Error: ' + error.message, 'danger');
		}
	});

	// Editar modalidad
	document.querySelectorAll('.edit-modalidad').forEach(btn => {
		btn.addEventListener('click', async (e) => {
			const id = btn.getAttribute('data-id');
			const row = btn.closest('tr');
			const nombre = row.querySelector('td:nth-child(1)').textContent.trim();
			const descripcion = row.querySelector('td:nth-child(2)').textContent.trim();
			const orden = row.querySelector('td:nth-child(3)').textContent.trim();
			const activo = row.querySelector('td:nth-child(4)').textContent.includes('Activo');

			modalidadId.value = id;
			modalidadNombre.value = nombre;
			modalidadDescripcion.value = descripcion !== '-' ? descripcion : '';
			modalidadOrden.value = orden;
			modalidadActivo.checked = activo;
			resetBtn.style.display = 'block';
			modalidadNombre.focus();

			// Scroll to form
			document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
		});
	});

	// Eliminar modalidad
	document.querySelectorAll('.delete-modalidad').forEach(btn => {
		btn.addEventListener('click', async (e) => {
			const id = btn.getAttribute('data-id');
			if (!confirm('¿Está seguro de que desea eliminar esta modalidad?')) {
				return;
			}

			try {
				const response = await fetch(API_BASE + '/crm/modalidades/delete', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ id: id })
				});

				const text = await response.text();
				let data;
				try {
					data = JSON.parse(text);
				} catch (e) {
					console.error('Respuesta del servidor:', text);
					showStatus('Error: Respuesta inválida del servidor', 'danger');
					return;
				}

				if (data.success) {
					showStatus('Modalidad eliminada', 'success');
					setTimeout(() => location.reload(), 500);
				} else {
					showStatus(data.error || 'Error al eliminar', 'danger');
				}
			} catch (error) {
				showStatus('Error: ' + error.message, 'danger');
			}
		});
	});

	// Reset button
	resetBtn.addEventListener('click', resetForm);
});
</script>
