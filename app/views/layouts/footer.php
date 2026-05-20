	</div>

	<?php $scripts = $scripts ?? ['global.js']; ?>
	<footer class="page-footer">
		<div class="container-fluid py-2 small text-center">
			<span style="color:var(--ink-soft)">
				<img src="<?= e(asset('img/atlas_ticket.jpeg')) ?>" alt="Atlas" style="width:16px;height:16px;border-radius:4px;object-fit:cover;vertical-align:middle;margin-right:5px;opacity:.7">
				Atlas Ticket &copy; <?= date('Y') ?> — Plataforma institucional
			</span>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<?php foreach ($scripts as $script): ?>
		<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
	<?php endforeach; ?>
</body>
</html>
