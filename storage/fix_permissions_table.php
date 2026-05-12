<?php
// Script para recrear la tabla role_action_permissions con el esquema correcto

try {
    // Conexión directa a MySQL
    $db = new PDO(
        'mysql:host=localhost;port=3306;dbname=itsticket;charset=utf8mb4',
        'root',
        'Superarse.2025',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "✅ Conectado a la base de datos\n\n";
    
    // Primero, obtener el SQL del archivo
    $sqlFile = __DIR__ . '/sql/06_role_action_permissions.sql';
    $sql = file_get_contents($sqlFile);
    
    if (!$sql) {
        die("Error: No se pudo leer el archivo SQL en: " . $sqlFile . "\n");
    }
    
    // Ejecutar cada statement (separados por ;)
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Ejecutando: " . substr($statement, 0, 60) . "...\n";
            $db->exec($statement);
        }
    }
    
    echo "\n✅ ¡Tabla role_action_permissions actualizada correctamente!\n";
    echo "📝 Ahora intenta crear/editar un rol nuevamente\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
