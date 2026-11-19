<?php
require __DIR__.'/../app/services/InlineCidResolver.php';
function same($expected, $actual, $label) {
    if ($expected !== $actual) { throw new RuntimeException($label.' expected '.var_export($expected,true).' got '.var_export($actual,true)); }
    echo "OK: $label\n";
}
$map = ['imagen01'=>'/a', 'imagen010'=>'/b'];
same('<img src="/a"><img src="/b"><img src="cid:imagen011">', InlineCidResolver::replace('<img src="cid:imagen01"><img src="cid:imagen010"><img src="cid:imagen011">',$map),'CID completos');
same('<IMG SRC="/a"><img src="/a"><img src="/a">', InlineCidResolver::replace('<IMG SRC=\'CID:IMAGEN01\'><img src=cid:imagen01><img src="cid:%69magen01">',$map),'Comillas, mayusculas, URL encoding');
$html = '<p>cid:imagen01</p><a href="cid:imagen01">x</a><img data-src="cid:imagen01" title="cid:imagen01"><!-- <img src="cid:imagen01"> -->';
same($html, InlineCidResolver::replace($html,$map),'Texto y atributos ajenos intactos');
same('<img title="a > b" src="/a">', InlineCidResolver::replace('<img title="a > b" src="cid:imagen01">',$map),'Atributo con signo mayor');
same('<img src="/b">', InlineCidResolver::replace('<img src="cid:imagen010">',['imagen010'=>'/b','imagen01'=>'/a']),'Orden independiente');
same('<img src="/a"><img src="/b">', InlineCidResolver::replace(InlineCidResolver::replace('<img src="cid:imagen01"><img src="cid:imagen010">',['imagen01'=>'/a']),['imagen010'=>'/b']),'Entregas sucesivas');
same('<img src="/a">',InlineCidResolver::replace('<img src="/a">',['imagen01'=>'/b']),'URL previa intacta');
$amb=[]; InlineCidResolver::add($amb,'same','/a'); InlineCidResolver::add($amb,'SAME','/b');
same('<img src="cid:same">',InlineCidResolver::replace('<img src="cid:same">',$amb),'Mapa ambiguo no elige imagen');
same('<img src="/missing">',InlineCidResolver::replace('<img src="cid:same">',$amb,'/missing'),'CID pendiente oculto');
same('<img src="/a?x=1&amp;y=2">',InlineCidResolver::replace('<img src="cid:same">',['same'=>'/a?x=1&y=2']),'Escape URL');
