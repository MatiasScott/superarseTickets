<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">Redactar Correo</h2>
		<a class="btn btn-outline-secondary" href="<?= e(base_url('correo')) ?>">Volver a Bandeja</a>
	</div>

	<?php if ($msg = get_flash('success')): ?>
		<div class="alert alert-success"><?= e($msg) ?></div>
	<?php endif; ?>
	<?php if ($msg = get_flash('error')): ?>
		<div class="alert alert-danger"><?= e($msg) ?></div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= e(base_url('correo/send')) ?>">
				<?= csrf_field() ?>
				<div class="row g-3">
					<div class="col-md-4">
						<label class="form-label">Cuenta de salida</label>
						<select class="form-select" name="account_alias">
							<option value="">Default</option>
							<?php foreach (($accounts ?? []) as $acc): ?>
								<option value="<?= e($acc['alias']) ?>" <?= (($defaultAlias ?? '') === $acc['alias']) ? 'selected' : '' ?>>
									<?= e($acc['name'] . ' (' . $acc['email'] . ')') ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-8">
						<label class="form-label">Para</label>
						<input class="form-control" type="email" name="to" value="<?= e($prefillTo ?? '') ?>" required>
					</div>
					<div class="col-12">
						<label class="form-label">Asunto</label>
						<input class="form-control" type="text" name="subject" value="<?= e($prefillSubject ?? '') ?>" required>
					</div>
					<div class="col-12">
						<label class="form-label">Mensaje</label>
						<textarea class="form-control" name="body" rows="10" required></textarea>
					</div>
				</div>
				<button class="btn btn-primary mt-3" type="submit">Enviar Correo</button>
			</form>
		</div>
	</div>
</div>
