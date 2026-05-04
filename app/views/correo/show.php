<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">Detalle de Correo</h2>
		<a class="btn btn-outline-secondary" href="<?= e(base_url('correo?account=' . urlencode($accountAlias ?? ''))) ?>">Volver</a>
	</div>

	<?php if ($msg = get_flash('success')): ?>
		<div class="alert alert-success"><?= e($msg) ?></div>
	<?php endif; ?>
	<?php if ($msg = get_flash('error')): ?>
		<div class="alert alert-danger"><?= e($msg) ?></div>
	<?php endif; ?>

	<div class="card mb-3">
		<div class="card-body">
			<div><strong>Cuenta:</strong> <?= e(($account['name'] ?? '') . ' <' . ($account['email'] ?? '') . '>') ?></div>
			<div><strong>De:</strong> <?= e($message['from'] ?? '') ?></div>
			<div><strong>Para:</strong> <?= e($message['to'] ?? '') ?></div>
			<div><strong>Fecha:</strong> <?= e($message['date'] ?? '') ?></div>
			<div><strong>Asunto:</strong> <?= e($message['subject'] ?? '') ?></div>
		</div>
	</div>

	<div class="card mb-3">
		<div class="card-header">Mensaje</div>
		<div class="card-body">
			<div class="border rounded p-3 bg-light" style="white-space:pre-wrap;"><?= e($message['body_text'] ?? '') ?></div>
		</div>
	</div>

	<div class="card">
		<div class="card-header">Responder</div>
		<div class="card-body">
			<form method="POST" action="<?= e(base_url('correo/' . rawurlencode((string) ($message['uid'] ?? '')) . '/reply')) ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="account_alias" value="<?= e($accountAlias ?? '') ?>">
				<div class="mb-3">
					<label class="form-label">Respuesta</label>
					<textarea class="form-control" name="body" rows="6" required></textarea>
				</div>
				<button class="btn btn-primary" type="submit">Enviar Respuesta</button>
			</form>
		</div>
	</div>
</div>
