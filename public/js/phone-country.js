/**
 * Selector de país para celulares (bandera + código) y normalización internacional (E.164).
 * Mantiene el input original como campo oculto con el valor final (+<código><número>),
 * por lo que los formularios y listeners existentes siguen funcionando igual.
 */
(function () {
	'use strict';

	if (window.AtlasPhone) {
		return;
	}

	var DEFAULT_ISO = 'EC';
	var PREFERRED = ['EC', 'CO', 'PE', 'VE', 'US', 'ES', 'AR', 'CL', 'MX', 'BO', 'CU', 'IT'];
	var SHARED_DIAL_DEFAULTS = { '1': 'US', '7': 'RU', '39': 'IT' };
	var INVALID_MESSAGE = 'El celular debe incluir el código de país y un número válido (Ej: +593987654321).';
	var FLAG_CSS = 'https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css';

	var countries = Array.isArray(window.ATLAS_PHONE_COUNTRIES) && window.ATLAS_PHONE_COUNTRIES.length
		? window.ATLAS_PHONE_COUNTRIES
		: [{ iso: 'EC', name: 'Ecuador', dial: '593' }];

	var byIso = {};
	var dialCodes = {};
	countries.forEach(function (c) {
		byIso[c.iso] = c;
		dialCodes[String(c.dial)] = true;
	});

	var stripAccents = function (text) {
		return String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
	};

	var matchDialCode = function (digits) {
		for (var len = 3; len >= 1; len--) {
			var candidate = digits.slice(0, len);
			if (candidate.length === len && dialCodes[candidate]) {
				return candidate;
			}
		}
		return null;
	};

	var normalizeLegacyEcuador = function (digits) {
		if (/^5939\d{8}$/.test(digits)) return '+' + digits;
		if (/^09\d{8}$/.test(digits)) return '+593' + digits.slice(1);
		if (/^9\d{8}$/.test(digits)) return '+593' + digits;
		return '';
	};

	var normalizeInternationalDigits = function (digits) {
		var dial = matchDialCode(digits);
		if (!dial) return '';
		var national = digits.slice(dial.length);

		if (dial === '593') {
			if (national.charAt(0) === '0') national = national.slice(1);
			return /^9\d{8}$/.test(national) ? '+593' + national : '';
		}

		if (dial !== '39' && national.charAt(0) === '0') {
			national = national.slice(1);
		}
		if (national.length < 6 || national.length > 14 || dial.length + national.length > 15) return '';
		if (dial !== '39' && national.charAt(0) === '0') return '';

		return '+' + dial + national;
	};

	// Misma regla que PhoneNumberPolicy::normalize (PHP).
	var normalize = function (value) {
		var raw = String(value || '').trim();
		if (raw === '') return '';
		var digits = raw.replace(/\D+/g, '');
		if (digits === '') return '';

		var hasPlus = raw.charAt(0) === '+';
		if (!hasPlus && digits.indexOf('00') !== 0) {
			return normalizeLegacyEcuador(digits);
		}
		if (!hasPlus) {
			digits = digits.slice(2);
		}
		return normalizeInternationalDigits(digits);
	};

	var resolveIso = function (dial, national) {
		var fallback = SHARED_DIAL_DEFAULTS[dial] || null;
		for (var i = 0; i < countries.length; i++) {
			var c = countries[i];
			if (String(c.dial) !== dial) continue;
			if (Array.isArray(c.areas) && c.areas.length) {
				for (var j = 0; j < c.areas.length; j++) {
					if (national.indexOf(String(c.areas[j])) === 0) return c.iso;
				}
				continue;
			}
			if (!fallback) return c.iso;
		}
		return fallback || DEFAULT_ISO;
	};

	var split = function (value) {
		var normalized = normalize(value);
		var raw = String(value || '').trim();
		var source = normalized || (raw.charAt(0) === '+' ? raw : '');
		var digits = source.replace(/\D+/g, '');
		if (digits === '') {
			// Número local sin código: se asume Ecuador.
			return { iso: DEFAULT_ISO, dial: '593', national: raw.replace(/\D+/g, '') };
		}
		var dial = matchDialCode(digits);
		if (!dial) {
			return { iso: DEFAULT_ISO, dial: '593', national: digits };
		}
		var national = digits.slice(dial.length);
		return { iso: resolveIso(dial, national), dial: dial, national: national };
	};

	var flagLoaded = false;
	var ensureFlagCss = function () {
		if (flagLoaded || document.querySelector('link[data-atlas-flags]')) {
			flagLoaded = true;
			return;
		}
		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = FLAG_CSS;
		link.setAttribute('data-atlas-flags', '1');
		document.head.appendChild(link);
		flagLoaded = true;
	};

	var ensureStyles = function () {
		if (document.getElementById('atlas-phone-styles')) return;
		var style = document.createElement('style');
		style.id = 'atlas-phone-styles';
		style.textContent = [
			'.atlas-phone{position:relative;flex-wrap:nowrap}',
			'.atlas-phone .atlas-phone-btn{display:flex;align-items:center;gap:.35rem;white-space:nowrap;border-color:var(--bs-border-color,#dee2e6);background:var(--bs-tertiary-bg,#f8f9fa);color:inherit}',
			'.atlas-phone .fi{width:1.25em;line-height:1em;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.08)}',
			'.atlas-phone-menu{position:absolute;top:100%;left:0;z-index:1080;width:min(320px,90vw);margin-top:2px;background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);border-radius:.5rem;box-shadow:0 .5rem 1rem rgba(0,0,0,.15);padding:.5rem;display:none}',
			'.atlas-phone-menu.show{display:block}',
			'.atlas-phone-list{max-height:240px;overflow-y:auto;margin:.5rem 0 0;padding:0;list-style:none}',
			'.atlas-phone-list li{display:flex;align-items:center;gap:.5rem;padding:.35rem .5rem;border-radius:.35rem;cursor:pointer;font-size:.9rem}',
			'.atlas-phone-list li:hover,.atlas-phone-list li.active{background:var(--bs-primary-bg-subtle,#e7f1ff)}',
			'.atlas-phone-list li .atlas-phone-dial{margin-left:auto;color:var(--bs-secondary-color,#6c757d)}',
			'.atlas-phone-list li.atlas-phone-sep{height:1px;padding:0;margin:.25rem 0;background:var(--bs-border-color,#dee2e6);cursor:default}'
		].join('');
		document.head.appendChild(style);
	};

	var flagHtml = function (iso) {
		return '<span class="fi fi-' + String(iso || '').toLowerCase() + '"></span>';
	};

	var escapeHtml = function (text) {
		var div = document.createElement('div');
		div.textContent = String(text == null ? '' : text);
		return div.innerHTML;
	};

	var fire = function (el, type) {
		el.dispatchEvent(new Event(type, { bubbles: type !== 'blur' }));
	};

	var enhance = function (input, options) {
		if (!input || input.dataset.atlasPhone === '1') {
			return input ? input._atlasPhone || null : null;
		}
		options = options || {};
		ensureStyles();
		ensureFlagCss();

		input.dataset.atlasPhone = '1';
		var originalId = input.id || ('atlasPhone' + Math.random().toString(36).slice(2));
		var placeholder = options.placeholder || 'Número celular';

		var group = document.createElement('div');
		group.className = 'input-group atlas-phone';
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'btn atlas-phone-btn';
		button.setAttribute('aria-haspopup', 'listbox');
		button.setAttribute('aria-expanded', 'false');
		button.title = 'Seleccionar país';

		var visible = document.createElement('input');
		visible.type = 'tel';
		visible.className = input.className || 'form-control';
		visible.classList.add('form-control');
		visible.id = originalId + 'Numero';
		visible.placeholder = placeholder;
		visible.inputMode = 'tel';
		visible.autocomplete = 'tel-national';
		visible.maxLength = 20;
		if (input.disabled) visible.disabled = true;
		if (input.readOnly) visible.readOnly = true;

		var menu = document.createElement('div');
		menu.className = 'atlas-phone-menu';
		menu.innerHTML = '<input type="search" class="form-control form-control-sm" placeholder="Buscar país o código..." autocomplete="off"><ul class="atlas-phone-list" role="listbox"></ul>';
		var search = menu.querySelector('input');
		var list = menu.querySelector('ul');

		input.parentNode.insertBefore(group, input);
		group.appendChild(button);
		group.appendChild(visible);
		group.appendChild(menu);
		group.appendChild(input);
		input.type = 'hidden';
		input.removeAttribute('pattern');

		var label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
		if (label) label.setAttribute('for', visible.id);

		var state = { iso: DEFAULT_ISO, userPicked: false };

		var renderButton = function () {
			var c = byIso[state.iso] || byIso[DEFAULT_ISO] || countries[0];
			button.innerHTML = flagHtml(c.iso) + '<span>+' + escapeHtml(c.dial) + '</span><i class="bi bi-chevron-down small"></i>';
			button.setAttribute('aria-label', 'País: ' + c.name + ' (+' + c.dial + ')');
		};

		var currentDial = function () {
			return String((byIso[state.iso] || byIso[DEFAULT_ISO]).dial);
		};

		var composeValue = function () {
			var national = visible.value.replace(/\D+/g, '');
			if (national === '') return '';
			var full = '+' + currentDial() + national;
			return normalize(full) || full;
		};

		var updateValidity = function (strict) {
			var hasValue = visible.value.replace(/\D+/g, '') !== '';
			var valid = !hasValue || normalize(input.value) !== '';
			visible.classList.toggle('is-invalid', strict && !valid);
			visible.setCustomValidity(valid ? '' : INVALID_MESSAGE);
			return valid;
		};

		var pushValue = function (eventTypes) {
			var value = composeValue();
			if (input.value === value) {
				return;
			}
			input.value = value;
			(eventTypes || ['input']).forEach(function (t) { fire(input, t); });
		};

		var setFromValue = function (value) {
			var raw = String(value || '').trim();
			if (raw === '') {
				visible.value = '';
				input.value = '';
				renderButton();
				updateValidity(false);
				return;
			}
			var parts = split(raw);
			state.iso = parts.iso;
			state.userPicked = false;
			visible.value = parts.national;
			input.value = normalize(raw) || raw;
			renderButton();
			updateValidity(false);
		};

		var renderList = function () {
			var q = stripAccents(search.value).replace(/^\+/, '').trim();
			var html = [];
			var matches = function (c) {
				if (q === '') return true;
				return stripAccents(c.name).indexOf(q) !== -1 || c.iso.toLowerCase() === q || String(c.dial).indexOf(q) === 0;
			};
			var item = function (c) {
				return '<li role="option" data-iso="' + c.iso + '"' + (c.iso === state.iso ? ' class="active" aria-selected="true"' : '') + '>'
					+ flagHtml(c.iso) + '<span>' + escapeHtml(c.name) + '</span><span class="atlas-phone-dial">+' + escapeHtml(c.dial) + '</span></li>';
			};
			if (q === '') {
				PREFERRED.forEach(function (iso) { if (byIso[iso]) html.push(item(byIso[iso])); });
				html.push('<li class="atlas-phone-sep" aria-hidden="true"></li>');
			}
			countries.forEach(function (c) { if (matches(c)) html.push(item(c)); });
			list.innerHTML = html.length ? html.join('') : '<li class="text-muted">Sin resultados</li>';
		};

		var closeMenu = function () {
			menu.classList.remove('show');
			button.setAttribute('aria-expanded', 'false');
		};

		var openMenu = function () {
			search.value = '';
			renderList();
			menu.classList.add('show');
			button.setAttribute('aria-expanded', 'true');
			search.focus();
		};

		button.addEventListener('click', function (e) {
			e.preventDefault();
			if (menu.classList.contains('show')) closeMenu(); else openMenu();
		});

		search.addEventListener('input', renderList);
		search.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				e.stopPropagation();
				closeMenu();
				button.focus();
			} else if (e.key === 'Enter') {
				e.preventDefault();
				var first = list.querySelector('li[data-iso]');
				if (first) first.click();
			}
		});

		list.addEventListener('click', function (e) {
			var li = e.target.closest('li[data-iso]');
			if (!li) return;
			state.iso = li.getAttribute('data-iso');
			state.userPicked = true;
			renderButton();
			closeMenu();
			pushValue(['input', 'change']);
			updateValidity(visible.value !== '');
			visible.focus();
		});

		document.addEventListener('click', function (e) {
			if (!group.contains(e.target)) closeMenu();
		});

		visible.addEventListener('input', function () {
			var cleaned = visible.value.replace(/[^\d\s()+\-.]/g, '');
			if (cleaned !== visible.value) visible.value = cleaned;
			var raw = visible.value.trim();
			// Si pegan el número completo con código (+57..., 0057...), se detecta el país.
			if (raw.charAt(0) === '+' || /^00[1-9]/.test(raw.replace(/\s+/g, ''))) {
				var parts = split(raw.charAt(0) === '+' ? raw : '+' + raw.replace(/\D+/g, '').slice(2));
				if (parts.national !== '' || raw.length > 4) {
					state.iso = parts.iso;
					state.userPicked = false;
					visible.value = parts.national;
					renderButton();
				}
			}
			// Código compartido (+1): ajustar bandera según código de área si el usuario no eligió país.
			var dial = currentDial();
			var nationalDigits = visible.value.replace(/\D+/g, '');
			if (!state.userPicked && SHARED_DIAL_DEFAULTS[dial] && nationalDigits.length >= 3) {
				var resolved = resolveIso(dial, nationalDigits);
				if (resolved !== state.iso) {
					state.iso = resolved;
					renderButton();
				}
			}
			pushValue(['input']);
			if (visible.classList.contains('is-invalid')) updateValidity(true);
		});

		visible.addEventListener('change', function () {
			pushValue(['input']);
			fire(input, 'change');
		});

		visible.addEventListener('blur', function () {
			updateValidity(true);
			fire(input, 'blur');
		});

		var form = input.form;
		if (form) {
			form.addEventListener('reset', function () {
				window.setTimeout(function () {
					state.iso = DEFAULT_ISO;
					setFromValue('');
				}, 0);
			});
		}

		setFromValue(input.value);

		var api = {
			setValue: setFromValue,
			getValue: function () { return input.value; },
			isValid: function () { return updateValidity(true); },
			visibleInput: visible
		};
		input._atlasPhone = api;
		return api;
	};

	var setValue = function (input, value) {
		if (!input) return;
		if (input._atlasPhone) {
			input._atlasPhone.setValue(value);
		} else {
			input.value = value;
		}
	};

	window.AtlasPhone = {
		countries: countries,
		normalize: normalize,
		split: split,
		enhance: enhance,
		setValue: setValue,
		invalidMessage: INVALID_MESSAGE
	};
})();
