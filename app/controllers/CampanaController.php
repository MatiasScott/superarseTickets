<?php

class CampanaController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$campanas = [];

		try {
			$campanas = (new Campana())->all(100);
		} catch (Throwable $e) {
			$campanas = [];
		}

		$this->view('campanas/index', compact('campanas'), [
			'title' => 'Comunicaciones',
		]);
	}
}
