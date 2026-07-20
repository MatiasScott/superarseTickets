<?php
$sla = $sla ?? [];
$get = static function (array $section, string $key, string $default = ''): string {
	if (!isset($section[$key]) || !is_array($section[$key])) {
		return $default;
	}
	return (string) (($section[$key]['value'] ?? $default));
};
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<h1 class="h3 mb-1"><i class="bi bi-stopwatch"></i> SLA CCI</h1>
		<p class="text-muted mb-3">Parámetros configurables de tiempos y límites de seguimiento.</p>

		<div class="row g-3">
			<div class="col-12 col-md-6 col-xl-3">
				<div class="cci-kpi-card"><span>Tiempo máximo sin responder</span><strong><?= e($get($sla, 'max_sin_responder_minutos', '15')) ?> min</strong></div>
			</div>
			<div class="col-12 col-md-6 col-xl-3">
				<div class="cci-kpi-card accent-warning"><span>Tiempo máximo de espera</span><strong><?= e($get($sla, 'max_espera_minutos', '30')) ?> min</strong></div>
			</div>
			<div class="col-12 col-md-6 col-xl-3">
				<div class="cci-kpi-card accent-info"><span>Máximo interacciones</span><strong><?= e($get($sla, 'max_interacciones', '8')) ?></strong></div>
			</div>
			<div class="col-12 col-md-6 col-xl-3">
				<div class="cci-kpi-card accent-success"><span>Tiempo recordatorio</span><strong><?= e($get($sla, 'recordatorio_minutos', '10')) ?> min</strong></div>
			</div>
		</div>
	</div>
</section>
