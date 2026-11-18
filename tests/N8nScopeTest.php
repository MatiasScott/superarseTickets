<?php
// Prueba aislada: no carga .env, BD, Graph ni Redis.
class Controller {}
class MailboxService {
    public function getAvailableAccounts(): array { return [['alias'=>'acc1','email'=>'test@example.invalid']]; }
}
function env($key,$default='') { return $GLOBALS['fakeEnv'][$key] ?? $default; }
require __DIR__.'/../app/controllers/CorreoController.php';
$c = new CorreoController();
$m = new ReflectionMethod($c,'runTicketSync');
$p = new ReflectionMethod($c,'n8nManagedAliases');
function check($condition,$label) { if (!$condition) { throw new RuntimeException($label); } echo "OK: $label\n"; }
$GLOBALS['fakeEnv']=['N8N_MANAGED_MAIL_ALIASES'=>' acc1, ACC1, acc2 '];
check($p->invoke($c)===[], 'Deshabilitado por defecto');
$GLOBALS['fakeEnv']['N8N_INGEST_ENABLED']='true';
check($p->invoke($c)===['acc1','acc2'], 'Normalizacion de aliases');
$r=$m->invoke($c,'acc1');
check($r['aliases']===[] && $r['sync_errors']===[] && $r['n8n_excluded_aliases']===['acc1'], 'Exclusion individual sin acceder a BD');
$r=$m->invoke($c,null);
check($r['aliases']===[] && $r['sync_errors']===[], 'Exclusion de todos los buzones configurados');
try { $c->ingestN8nMessage(['account_alias'=>'acc1','graph_message_id'=>'abc','uid'=>'YWJj','mailbox'=>'wrong@example.invalid']); throw new RuntimeException('Debia rechazar mailbox'); } catch (InvalidArgumentException $e) { check(str_contains($e->getMessage(),'Mailbox'), 'Rechazo de mailbox incorrecto'); }
$GLOBALS['fakeEnv']['N8N_MANAGED_MAIL_ALIASES']='';
try { $c->ingestN8nMessage(['account_alias'=>'acc1','graph_message_id'=>'abc','uid'=>'YWJj','mailbox'=>'test@example.invalid']); throw new RuntimeException('Debia rechazar alias'); } catch (InvalidArgumentException $e) { check(str_contains($e->getMessage(),'asignado'), 'Rechazo de alias no asignado'); }
