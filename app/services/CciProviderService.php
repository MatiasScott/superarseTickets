<?php

class CciProviderService
{
	private CciProvider $providerModel;
	private CciConfig $configModel;

	public function __construct()
	{
		$this->providerModel = new CciProvider();
		$this->configModel = new CciConfig();
	}

	public function listProviders(): array
	{
		return $this->providerModel->allProviders();
	}

	public function activeProviderCode(): string
	{
		return $this->configModel->getValue('general', 'default_provider', 'whatchimp');
	}

	public function resolveWhatchimpConfig(): array
	{
		$section = $this->configModel->getBySection('whatchimp');
		return [
			'api_key' => (string) (($section['api_key']['value'] ?? '') ?: ''),
			'base_url' => (string) (($section['base_url']['value'] ?? '') ?: ''),
			'numero_asociado' => (string) (($section['numero_asociado']['value'] ?? '') ?: ''),
			'alias' => (string) (($section['alias']['value'] ?? '') ?: ''),
			'webhook' => (string) (($section['webhook']['value'] ?? '') ?: ''),
			'estado' => (string) (($section['estado']['value'] ?? 'inactivo') ?: 'inactivo'),
		];
	}

	public function verifyWhatchimpConnection(): array
	{
		$cfg = $this->resolveWhatchimpConfig();
		$ok = $cfg['api_key'] !== '' && $cfg['base_url'] !== '';
		return [
			'ok' => $ok,
			'message' => $ok ? 'Configuración lista para conexión.' : 'Faltan API Key o URL Base.',
		];
	}
}
