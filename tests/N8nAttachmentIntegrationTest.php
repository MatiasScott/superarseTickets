<?php
declare(strict_types=1);
// CLI only. Real Database/Queue/Processor; simulated mailbox and configuration.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
function db_config(string $key, mixed $default = null): mixed {
    return ['host'=>'127.0.0.1','port'=>'3306','database'=>'iststicket_n8n_test',
        'username'=>getenv('ATLAS_TEST_USER'),'password'=>getenv('ATLAS_TEST_PASSWORD') ?: '',
        'charset'=>'utf8mb4'][$key] ?? $default;
}
function base_url(string $path = ''): string { return 'http://atlas-test.invalid/'.ltrim($path,'/'); }
class MailboxService { public function getAvailableAccounts(): array { return [['alias'=>'acc1','email'=>'fixture@example.invalid']]; } }
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "OK: $label\n"; }
function reject(callable $fn,string $reason,string $label): void {
    try { $fn(); } catch (InvalidArgumentException $e) { check(str_contains($e->getMessage(),$reason),$label); return; }
    throw new RuntimeException('No rechazo: '.$label);
}
function uid(string $id): string { return rtrim(strtr(base64_encode($id),'+/','-_'),'='); }
function payload(string $g,string $a,string $bytes): array { return ['account_alias'=>'acc1','graph_message_id'=>$g,'uid'=>uid($g),'graph_attachment_id'=>$a,'sha256'=>hash('sha256',$bytes),'content_base64'=>base64_encode($bytes)]; }
function headerFixture(string $id,string $name,string $mime,string $bytes,bool $inline=false): array { return ['id'=>$id,'name'=>$name,'mime'=>$mime,'size'=>strlen($bytes),'is_inline'=>$inline,'content_id'=>$inline?'fixture-image@invalid':'']; }
if (!getenv('ATLAS_TEST_USER') || getenv('ATLAS_TEST_PASSWORD') === false) { fwrite(STDERR,"Definir ATLAS_TEST_USER y ATLAS_TEST_PASSWORD.\n"); exit(1); }
$project=dirname(__DIR__);
define('ROOT_PATH',sys_get_temp_dir().'/atlas-n8n-test-'.bin2hex(random_bytes(8)));
try {
    require $project.'/app/core/Database.php';
    require $project.'/app/services/AttachmentQueueService.php';
    require_once $project.'/app/services/InlineCidResolver.php';
    require $project.'/app/services/AttachmentProcessorService.php';
    $db=Database::getInstance()->connection();
    check($db->query('SELECT DATABASE()')->fetchColumn()==='iststicket_n8n_test','Base aislada confirmada');
    foreach (['tickets','ticket_mensajes','cola_adjuntos','ticket_mensaje_adjuntos','mail_ticket_sync'] as $table) {
        check((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()===0,"Tabla vacia: $table");
    }
    $q=new AttachmentQueueService($db); $p=new AttachmentProcessorService();
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=',true);
    $doc="Documento ficticio de prueba\n";
    $db->beginTransaction();
    $db->exec("INSERT INTO tickets (codigo,asunto) VALUES ('N8N-TEST-A','Prueba A'),('N8N-TEST-B','Prueba B')");
    $t1=(int)$db->query("SELECT id FROM tickets WHERE codigo='N8N-TEST-A'")->fetchColumn();
    $t2=(int)$db->query("SELECT id FROM tickets WHERE codigo='N8N-TEST-B'")->fetchColumn();
    $stmt=$db->prepare('INSERT INTO ticket_mensajes (ticket_id,tipo,cuenta_alias,graph_message_id,mensaje) VALUES (?,\'original\',\'acc1\',?,?)');
    $stmt->execute([$t1,'fixture-A','<img src="cid:fixture-image@invalid">']); $m1=(int)$db->lastInsertId();
    $stmt->execute([$t2,'fixture-B','Mensaje B']); $m2=(int)$db->lastInsertId();
    $h=headerFixture('fixture-img','pixel.png','image/png',$png,true);
    $q->enqueueIncomingAttachments($t1,$m1,'acc1',uid('fixture-A'),[$h,headerFixture('fixture-doc','prueba.txt','text/plain',$doc)],'n8n');
    $q->enqueueIncomingAttachments($t2,$m2,'acc1',uid('fixture-B'),[headerFixture('fixture-php','php.txt','text/plain',$doc)],'php');
    $q->enqueueIncomingAttachments($t1,$m1,'acc1',uid('fixture-A'),[$h],'php');
    $db->commit();
    check((int)$db->query('SELECT COUNT(*) FROM cola_adjuntos')->fetchColumn()===3,'Encolado idempotente');
    check($db->query("SELECT processor FROM cola_adjuntos WHERE graph_attachment_id='fixture-img'")->fetchColumn()==='n8n','Duplicado no cambia propietario');
    reject(fn()=>$p->ingestFromN8n(payload('fixture-B','fixture-php',$doc)),'procesador tradicional','n8n rechaza trabajo PHP');
    $claimed=$q->claimPending(20);
    check(count($claimed)===1 && $claimed[0]['graph_attachment_id']==='fixture-php' && $claimed[0]['estado']==='procesando','PHP reclama exclusivamente su trabajo');
    reject(fn()=>$p->ingestFromN8n(payload('fixture-B','fixture-img',$png)),'no registrado','Rechazo de adjunto cruzado');
    $bad=payload('fixture-A','fixture-img',$png); $bad['sha256']=str_repeat('0',64);
    reject(fn()=>$p->ingestFromN8n($bad),'SHA-256','Rechazo SHA256 incorrecto');
    reject(fn()=>$p->ingestFromN8n(payload('fixture-A','fixture-img',$png.'x')),'Tamano','Rechazo tamano incorrecto');
    check((int)$db->query('SELECT COUNT(*) FROM ticket_mensaje_adjuntos')->fetchColumn()===0,'Rechazos sin archivos registrados');
    $img=$p->ingestFromN8n(payload('fixture-A','fixture-img',$png));
    $txt=$p->ingestFromN8n(payload('fixture-A','fixture-doc',$doc));
    foreach ([[$img,$png],[$txt,$doc]] as [$result,$bytes]) {
        check($result['ok']===true && $result['duplicate']===false && (int)$result['ticket_id']===$t1 && (int)$result['ticket_mensaje_id']===$m1,'Ingesta archivo '.(int)$result['attachment_id']);
        $s=$db->prepare('SELECT * FROM ticket_mensaje_adjuntos WHERE id=?'); $s->execute([$result['attachment_id']]); $r=$s->fetch();
        check(is_array($r) && str_starts_with($r['storage_path'],ROOT_PATH.'/storage/') && is_file($r['storage_path']) && hash_file('sha256',$r['storage_path'])===hash('sha256',$bytes) && $r['sha256']===hash('sha256',$bytes),'Archivo y SHA256 persistidos');
        if ($result===$img) check((int)$r['is_inline']===1 && $r['content_id']==='fixture-image@invalid','Content-ID conservado');
    }
    $html=$db->query('SELECT mensaje FROM ticket_mensajes WHERE id='.$m1)->fetchColumn();
    check(str_contains($html,'reply-attachment/'.$img['attachment_id'].'?mode=inline') && !str_contains($html,'cid:'),'CID resuelto en HTML');
    check($db->query('SELECT mensaje FROM ticket_mensajes WHERE id='.$m2)->fetchColumn()==='Mensaje B','Otro mensaje intacto');
    $repeat=$p->ingestFromN8n(payload('fixture-A','fixture-img',$png));
    check($repeat['duplicate']===true && $repeat['attachment_id']===$img['attachment_id'] && (int)$db->query('SELECT COUNT(*) FROM ticket_mensaje_adjuntos')->fetchColumn()===2,'Reintento sin duplicados');
    check((int)$db->query("SELECT COUNT(*) FROM cola_adjuntos WHERE processor='n8n' AND estado='procesado'")->fetchColumn()===2,'Cola n8n confirmada');
    echo "\nTODAS LAS PRUEBAS SUPERADAS\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR,'FALLO: '.$e->getMessage().PHP_EOL); exit(1);
} finally { echo 'Directorio de evidencia: '.ROOT_PATH.PHP_EOL; }
