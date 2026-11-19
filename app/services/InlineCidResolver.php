<?php

declare(strict_types=1);

/** Resolucion de src CID; no es un sanitizador HTML. */
final class InlineCidResolver
{
    public static function key(string $cid): string
    {
        return strtolower(trim(trim($cid), '<>'));
    }

    /** Rechazar mapas ambiguos en lugar de elegir una imagen por orden. */
    public static function add(array &$map, string $cid, string $url): void
    {
        $key = self::key($cid);
        if ($key === '') { return; }
        if (array_key_exists($key, $map) && $map[$key] !== $url) {
            $map[$key] = null;
        } else {
            $map[$key] = $url;
        }
    }

    public static function replace(string $html, array $map, ?string $missing = null): string
    {
        $normalized = [];
        foreach ($map as $cid => $url) {
            if (!is_string($url) || $url === '') {
                $normalized[self::key((string) $cid)] = null;
                continue;
            }
            self::add($normalized, (string) $cid, $url);
        }
        // Consumir etiquetas completas y comentarios: no modificar texto, href,
        // data-src ni atributos que contienen una etiqueta como texto.
        $tags = '~<!--.*?(?:-->|\z)|<(script|style|textarea|title)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?(?:</\1\s*>|\z)|<[a-z][a-z0-9:-]*(?=\s|/?>)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~is';
        return preg_replace_callback($tags, static function (array $tag) use ($normalized, $missing): string {
            if (!preg_match('~^<img(?=\s|/?>)~i', $tag[0])) { return $tag[0]; }
            $attrs = '~\G(\s+)([^\s=<>/"\']+)(?:(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\'=`<]+)))?~';
            $offset = 4;
            $out = substr($tag[0], 0, $offset);
            $seenSrc = false;
            while (preg_match($attrs, $tag[0], $a, PREG_UNMATCHED_AS_NULL, $offset)) {
                $offset += strlen($a[0]);
                $out .= $a[0];
                if (strcasecmp($a[2], 'src') !== 0 || $seenSrc) { continue; }
                $seenSrc = true;
                $value = $a[4] ?? $a[5] ?? $a[6] ?? '';
                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (strncasecmp(trim($value), 'cid:', 4) !== 0) { continue; }
                $key = self::key(rawurldecode(substr(trim($value), 4)));
                $url = $normalized[$key] ?? $missing;
                if ($url === null) { continue; }
                $out = substr($out, 0, -strlen($a[0])) . $a[1] . $a[2] . '="'
                    . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            return $out . substr($tag[0], $offset);
        }, $html) ?? $html;
    }
}
