<?php
/**
 * Script de mantenimiento para corregir la tabla role_action_permissions
 * Acceso: http://localhost/istsTicket/public/fix-permissions.php
 */

// Cargar la configuración
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Helpers.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Reparar Tabla de Permisos</title>";
echo "<style>body { font-family: Arial; margin: 20px; background: #f5f5f5; } .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); } .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0; border-radius: 3px; } .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 3px; } .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 3px; } code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; } pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; } </style></head><body><div class='container'>";
echo "<h1>🔧 Reparar Tabla de Permisos</h1>";

try {
    $db = Database::getInstance()->connection();
    
    echo "<p class='info'>✅ Conectado a base de datos correctamente</p>";
    
    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/../storage/sql/06_role_action_permissions.sql';
    if (!file_exists($sqlFile)) {
        die("<p class='error'>❌ Archivo no encontrado: " . $sqlFile . "</p></div></body></html>");
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        die("<p class='error'>❌ Error al leer el archivo SQL</p></div></body></html>");
    }
    
    echo "<p class='info'>📄 Archivo SQL leído: <code>" . basename($sqlFile) . "</code></p>";
    
    // Ejecutar statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "<h3>Ejecutando sentencias SQL:</h3><pre>";
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "• " . substr($statement, 0, 80) . "...\n";
            $db->exec($statement);
        }
    }
    echo "</pre>";
    
    echo "<p class='success'>";
    echo "<strong>✅ ¡Tabla actualizada correctamente!</strong><br>";
    echo "La tabla <code>role_action_permissions</code> ha sido recreada con el esquema correcto.<br>";
    echo "Ahora puedes crear/editar roles sin problemas.";
    echo "</p>";
    
    echo "<p><a href='" . base_url('admin/roles') . "' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;'>← Volver a Roles</a></p>";
    
} catch (Throwable $e) {
    echo "<p class='error'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</p>";
    echo "<p><a href='javascript:history.back()' style='background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;'>← Volver</a></p>";
}

echo "</div></body></html>";
?>
