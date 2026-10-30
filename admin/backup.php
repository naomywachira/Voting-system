<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=voting_system', 'root', '');
        $backup_dir = __DIR__ . '/../backups/';
        
        if (!file_exists($backup_dir)) mkdir($backup_dir, 0777, true);
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n";
        
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE $table")->fetch();
            $sql .= $create[1] . ";\n\n";
            
            $data = $pdo->query("SELECT * FROM $table")->fetchAll();
            foreach ($data as $row) {
                $values = array_map(function($v) { return $v === null ? 'NULL' : "'" . addslashes($v) . "'"; }, $row);
                $sql .= "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        file_put_contents($file, $sql);
        
        $gz = gzopen($file . '.gz', 'w9');
        gzwrite($gz, $sql);
        gzclose($gz);
        unlink($file);
        
        echo "✅ Backup created successfully!";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Minimal Backup</title></head>
<body>
    <h2>Minimal Backup Tester</h2>
    <form method="POST">
        <button type="submit" name="create">Create Backup</button>
    </form>
    <p><small>Check <?php echo __DIR__ . '/../backups/'; ?> for backup files</small></p>
</body>
</html>