<section class="module-page">
	<div class="container-fluid py-4">
		<h1 class="h3 mb-4">Dashboard integral</h1>

		<div class="row g-3">
			<div class="col-md-3">
				<div class="card metric-card">
					<div class="card-body">
						<h2><?= e($metrics['interesados'] ?? 0) ?></h2>
						<p>Interesados</p>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card metric-card">
					<div class="card-body">
						<h2><?= e($metrics['estudiantes'] ?? 0) ?></h2>
						<p>Estudiantes</p>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card metric-card">
					<div class="card-body">
						<h2><?= e($metrics['tickets_abiertos'] ?? 0) ?></h2>
						<p>Tickets</p>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card metric-card">
					<div class="card-body">
						<h2><?= e($metrics['campanas_activas'] ?? 0) ?></h2>
						<p>Conversaciones activas</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
