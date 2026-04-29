	</div>

	<?php $scripts = $scripts ?? ['global.js']; ?>
	<footer class="page-footer">
		<div class="container-fluid py-2 small text-center text-muted">
			ISTS Ticket <?= date('Y') ?> - Plataforma institucional
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<?php foreach ($scripts as $script): ?>
		<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
	<?php endforeach; ?>
</body>
</html>
