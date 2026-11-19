# Atlas — corrección CID, desarrollo local
Base: c2c86bb. Extraer únicamente en el worktree aislado atlas-n8n-http-test tras respaldar los archivos modificados. No ejecutar en producción. No contiene migraciones ni configuraciones.

Pruebas: php tests/InlineCidResolverTest.php (10 casos), php tests/N8nScopeTest.php (6 casos), php -l en los seis PHP. Resultados correctos en PHP 8.4. No se ejecutó la integración SQL ni la aplicación completa aquí (sin PDO MySQL / entorno Atlas). No repetir N8nAttachmentIntegrationTest sobre una BD con fixtures existentes.

Cambios: resolución de atributo src de img por CID completo; no modifica texto ni otros atributos; visualización local sin escritura de HTML ni consulta remota para adjuntos; retirar fallback obsoleto; limitar actualización SQL por mensaje/ticket con bloqueo; no elegir CID ambiguo en cabeceras de un mismo mensaje; ajustar comparación de rutas según plataforma. No repara HTML histórico ya incorrecto. No es un sanitizador HTML: XSS pendiente de una corrección separada, con pruebas de compatibilidad. Pendiente comprobar HTML malformado, concurrencia, visualización y flujo Graph/n8n. No publicar hasta completar pruebas.
