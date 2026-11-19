<?php

declare(strict_types=1);

/** Rebuild an HTML fragment from an explicit allowlist; never emit source markup. */
final class TicketHtmlSanitizer
{
    private const TAGS = ['p','br','b','strong','i','em','u','s','strike','a','ul','ol','li','img','blockquote','span','div','table','tbody','thead','tfoot','tr','td','th','hr','h1','h2','h3','h4','h5','h6','pre','code','sub','sup','caption'];
    private const DROP = ['script','style','iframe','object','embed','svg','math','template','noscript','textarea','title','head','xmp','plaintext','form','button','input','select','option','link','meta','base'];
    private const VOID = ['br','hr','img'];

    /** $images is null during writing; at display it is the exact message's authorized image URLs. */
    public static function clean(string $html, ?array $images = null): string
    {
        if ($html === '') return '';
        if (!class_exists('DOMDocument')) {
            // Do not silently discard CID or inline data when writing.
            if ($images === null) throw new RuntimeException('Atlas requiere ext-dom para procesar HTML.');
            // Fail closed at display.
            return nl2br(self::esc(strip_tags($html)));
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $old = libxml_use_internal_errors(true);
        try {
            $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$ok) return self::esc($html);
            $body = $doc->getElementsByTagName('body')->item(0);
            return $body ? trim(self::children($body, $images, 0)) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function children(DOMNode $node, ?array $images, int $depth): string
    {
        if ($depth > 100) return '';
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) { $out .= self::esc($child->nodeValue ?? ''); continue; }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) continue;
            if (!in_array($tag, self::TAGS, true)) { $out .= self::children($child, $images, $depth + 1); continue; }
            $attrs = '';
            foreach (['title','alt'] as $attr) {
                if ($child->hasAttribute($attr) && ($attr !== 'alt' || $tag === 'img')) $attrs .= ' ' . $attr . '="' . self::esc($child->getAttribute($attr)) . '"';
            }
            if ($tag === 'a') {
                $url = trim($child->getAttribute('href'));
                if (self::webUrl($url) || (preg_match('/^mailto:[^\s\x00-\x1f\x7f<>]+$/iD', $url) && !preg_match('/%0[ad]/i', $url))) {
                    $attrs .= ' href="' . self::esc($url) . '" target="_blank" rel="noopener noreferrer"';
                }
            }
            if ($tag === 'img') {
                $src = trim($child->getAttribute('src'));
                if ($images !== null) {
                    $src = self::authorizedImage($src, $images);
                } elseif (!self::webUrl($src) && !preg_match('/^cid:[^\x00-\x20\x7f<>"\']+$/iD', $src)
                    && !preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,[A-Za-z0-9+/]+={0,2}$~iD', $src)
                    && !preg_match('~^/(?!/)[^\x00-\x20\x7f\\\\]*$~D', $src)) {
                    $src = '';
                }
                if ($src === '') { $out .= self::esc($child->getAttribute('alt')); continue; }
                $attrs .= ' src="' . self::esc($src) . '"';
            }
            foreach (['width','height','colspan','rowspan'] as $attr) {
                if (!in_array($tag, ['img','td','th','table'], true)) continue;
                $v = $child->getAttribute($attr);
                if (preg_match('/^[1-9][0-9]{0,3}$/D', $v)) $attrs .= ' ' . $attr . '="' . $v . '"';
            }
            $out .= '<' . $tag . $attrs . '>';
            if (!in_array($tag, self::VOID, true)) $out .= self::children($child, $images, $depth + 1) . '</' . $tag . '>';
        }
        return $out;
    }

    private static function webUrl(string $url): bool
    {
        if (!preg_match('~^https?://[^\x00-\x20\x7f\\\\]+$~iD', $url)) return false;
        $parts = parse_url($url);
        return is_array($parts) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) && !isset($parts['user']) && !isset($parts['pass']);
    }

    private static function authorizedImage(string $src, array $images): string
    {
        // Only server-generated destinations belonging to the current message. Historical hostnames
        // may change; match the exact route and rebuild it with the current application base URL.
        if ($src === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $src)) return '';
        if (!self::webUrl($src) && !str_starts_with($src, '/')) return '';
        $parts = parse_url($src);
        if (!is_array($parts) || isset($parts['fragment']) || isset($parts['user'])) return '';
        foreach ($images as $url) {
            $allowed = parse_url($url);
            if (is_array($allowed) && ($parts['path'] ?? '') === ($allowed['path'] ?? '')
                && ($parts['query'] ?? '') === ($allowed['query'] ?? '')) return $url;
        }
        return '';
    }
}
