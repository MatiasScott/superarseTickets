<?php
$titulo = (string) ($titulo ?? 'Módulo');
$descripcion = (string) ($descripcion ?? 'En construcción.');
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="card cci-card">
			<div class="card-body text-center py-5">
				<h1 class="h3 mb-2"><?= e($titulo) ?></h1>
				<p class="text-muted mb-0"><?= e($descripcion) ?></p>
			</div>
		</div>
	</div>
</section>
