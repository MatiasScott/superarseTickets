<?php
// Selector de país para celulares: datos compartidos con PhoneNumberPolicy + script (no diferido,
// para que esté disponible antes de los scripts del módulo).
if (!defined('ATLAS_PHONE_ASSETS_LOADED')):
	define('ATLAS_PHONE_ASSETS_LOADED', true);
	$phoneScriptPath = ROOT_PATH . '/public/js/phone-country.js';
	$phoneScriptVersion = is_file($phoneScriptPath) ? (string) filemtime($phoneScriptPath) : (string) time();
?>
<script>window.ATLAS_PHONE_COUNTRIES = <?= json_encode(PhoneNumberPolicy::countries(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('js/phone-country.js') . '?v=' . $phoneScriptVersion) ?>"></script>
<?php endif; ?>
