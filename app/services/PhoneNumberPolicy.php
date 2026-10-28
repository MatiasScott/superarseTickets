<?php

/**
 * Normalización/validación de celulares internacionales a formato E.164 (+<código><número>).
 * La lista de países es compartida con el selector JS (public/js/phone-country.js).
 */
class PhoneNumberPolicy
{
	public const DEFAULT_ISO = 'EC';
	public const INVALID_MESSAGE = 'El celular debe incluir el código de país y un número válido (Ej: +593987654321).';

	// Código compartido por varios países: país usado por defecto al interpretar el número.
	private const SHARED_DIAL_DEFAULTS = ['1' => 'US', '7' => 'RU', '39' => 'IT'];

	private static ?array $countries = null;

	public static function countries(): array
	{
		if (self::$countries === null) {
			$file = dirname(__DIR__) . '/config/phone_countries.json';
			$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
			self::$countries = is_array($data) ? $data : [['iso' => 'EC', 'name' => 'Ecuador', 'dial' => '593']];
		}

		return self::$countries;
	}

	/** @return array<string,true> */
	private static function dialCodes(): array
	{
		static $codes = null;
		if ($codes === null) {
			$codes = [];
			foreach (self::countries() as $country) {
				$codes[(string) $country['dial']] = true;
			}
		}

		return $codes;
	}

	/**
	 * Devuelve el número en formato +<código><número> o '' si no es válido.
	 * - Con "+" o "00": se acepta cualquier código de país conocido.
	 * - Sin código: solo se aceptan los formatos locales de Ecuador (09XXXXXXXX, 9XXXXXXXX, 5939XXXXXXXX).
	 */
	public static function normalize(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		$digits = preg_replace('/\D+/', '', $value) ?: '';
		if ($digits === '') {
			return '';
		}

		$international = str_starts_with($value, '+') || str_starts_with($digits, '00');
		if (!$international) {
			return self::normalizeLegacyEcuador($digits);
		}

		if (str_starts_with($digits, '00') && !str_starts_with($value, '+')) {
			$digits = substr($digits, 2);
		}

		return self::normalizeInternationalDigits($digits);
	}

	public static function isValid(string $value): bool
	{
		return self::normalize($value) !== '';
	}

	/** @return array{iso:string,dial:string,national:string}|null */
	public static function split(string $e164): ?array
	{
		$digits = preg_replace('/\D+/', '', $e164) ?: '';
		$dial = self::matchDialCode($digits);
		if ($dial === null) {
			return null;
		}

		$national = substr($digits, strlen($dial));
		return ['iso' => self::resolveIso($dial, $national), 'dial' => $dial, 'national' => $national];
	}

	private static function normalizeLegacyEcuador(string $digits): string
	{
		if (preg_match('/^5939\d{8}$/', $digits) === 1) {
			return '+' . $digits;
		}
		if (preg_match('/^09\d{8}$/', $digits) === 1) {
			return '+593' . substr($digits, 1);
		}
		if (preg_match('/^9\d{8}$/', $digits) === 1) {
			return '+593' . $digits;
		}

		return '';
	}

	private static function normalizeInternationalDigits(string $digits): string
	{
		$dial = self::matchDialCode($digits);
		if ($dial === null) {
			return '';
		}

		$national = substr($digits, strlen($dial));

		if ($dial === '593') {
			if (str_starts_with($national, '0')) {
				$national = substr($national, 1);
			}
			return preg_match('/^9\d{8}$/', $national) === 1 ? '+593' . $national : '';
		}

		// Prefijo troncal nacional (0) que algunos usuarios escriben tras el código; Italia lo conserva.
		if ($dial !== '39' && str_starts_with($national, '0')) {
			$national = substr($national, 1);
		}

		$length = strlen($national);
		if ($length < 6 || $length > 14 || strlen($dial) + $length > 15 || str_starts_with($national, '0') && $dial !== '39') {
			return '';
		}

		return '+' . $dial . $national;
	}

	private static function matchDialCode(string $digits): ?string
	{
		$codes = self::dialCodes();
		for ($len = 3; $len >= 1; $len--) {
			$candidate = substr($digits, 0, $len);
			if (strlen($candidate) === $len && isset($codes[$candidate])) {
				return $candidate;
			}
		}

		return null;
	}

	private static function resolveIso(string $dial, string $national): string
	{
		$fallback = self::SHARED_DIAL_DEFAULTS[$dial] ?? null;
		foreach (self::countries() as $country) {
			if ((string) $country['dial'] !== $dial) {
				continue;
			}
			if (!empty($country['areas'])) {
				foreach ($country['areas'] as $area) {
					if (str_starts_with($national, (string) $area)) {
						return (string) $country['iso'];
					}
				}
				continue;
			}
			if ($fallback === null) {
				return (string) $country['iso'];
			}
		}

		return $fallback ?? self::DEFAULT_ISO;
	}
}
