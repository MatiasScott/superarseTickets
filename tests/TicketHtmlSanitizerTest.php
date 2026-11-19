<?php
require_once __DIR__ . '/../app/services/TicketHtmlSanitizer.php';
function check(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "OK: $label\n";
}
if (!class_exists('DOMDocument')) {
    check(!str_contains(TicketHtmlSanitizer::clean('<img src=x onerror=alert(1)>', []), '<img'), 'Fallback seguro sin DOM');
    try { TicketHtmlSanitizer::clean('<p>correo</p>'); } catch (RuntimeException $e) { echo "OK: Escritura bloqueada sin DOM\n"; }
    fwrite(STDERR, "Falta ext-dom; pruebas HTML completas pendientes.\n"); exit(2);
}
$base='http://127.0.0.1:8095/tickets/5/reply-attachment/';
$urls=[$base.'6?mode=inline',$base.'7?mode=inline',$base.'8?mode=inline'];
$h=TicketHtmlSanitizer::clean('<p onclick="alert(1)">Información <b>válida</b></p><script>alert(1)</script><svg onload="alert(1)"></svg><a href="jav&#x61;script:alert(1)">X</a><img src="x" onerror="alert(1)"><iframe src="https://x.test"></iframe><table><tr><td>dato</td></tr></table>', []);
check(!preg_match('/onclick|onerror|<script|<svg|<iframe|javascript:/i',$h), 'XSS y contenido activo eliminados');
check(str_contains($h,'Información') && str_contains($h,'<b>válida</b>') && str_contains($h,'<td>dato</td>'), 'Texto UTF-8 y formato');
$h=TicketHtmlSanitizer::clean('<img src="cid:atlas-image-004@invalid0"><img src="data:image/png;base64,AAAA"><a href="https://example.invalid">link</a>');
check(str_contains($h,'cid:atlas-image-004@invalid0') && str_contains($h,'data:image/png;base64,AAAA'), 'CID y datos inline conservados en escritura');
$h=TicketHtmlSanitizer::clean(implode('',array_map(fn($u)=>'<img src="'.$u.'" onerror="x">',$urls)).'<img src="'.$base.'99?mode=inline"><img src="https://tracker.invalid/pixel"><img src="cid:missing"><img src="data:image/svg+xml;base64,AAAA">', $urls);
check(substr_count($h,'<img ')===3 && !str_contains($h,'onerror') && !str_contains($h,'99?mode') && !str_contains($h,'tracker'), 'Tres imágenes propias, sin cruzadas ni rastreo');
foreach($urls as $u) check(str_contains($h,$u),'URL propia conservada: '.$u);
check(TicketHtmlSanitizer::clean('<img src="https://old.invalid/tickets/5/reply-attachment/6?mode=inline">',$urls)==='<img src="'.$urls[0].'">','Normalización de host histórico');
check(!str_contains(TicketHtmlSanitizer::clean('<a href="java&#10;script:alert(1)">a</a><img srcset="x" src="javascript:alert(1)">',[]),'href='),'Protocolos y srcset bloqueados');
