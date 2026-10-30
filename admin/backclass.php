<?php
class DatabaseBackup {
    private $pdo;
    private $backup_dir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->backup_dir = __DIR__ . '/../backups/';
        
        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }
    
    public function createBackup() {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $this->backup_dir . $filename;
        
        try {
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                return ['success' => false, 'error' => 'No tables found'];
            }
            
            $sql = "-- Backup Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                $create = $this->pdo->query("SHOW CREATE TABLE $table")->fetch();
                $sql .= $create[1] . ";\n\n";
                
                $data = $this->pdo->query("SELECT * FROM $table")->fetchAll();
                foreach ($data as $row) {
                    $values = array_map(function($v) {
                        return $v === null ? 'NULL' : "'" . addslashes($v) . "'";
                    }, $row);
                    $sql .= "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($filepath, $sql);
            
            $gzpath = $filepath . '.gz';
            $gz = gzopen($gzpath, 'w9');
            gzwrite($gz, $sql);
            gzclose($gz);
            unlink($filepath);
            
            return ['success' => true, 'file' => $filename . '.gz'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function listBackups() {
        $backups = [];
        $files = glob($this->backup_dir . '*.gz');
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
        usort($backups, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        return $backups;
    }
    
    public function restoreBackup($filename) {
        return ['success' => false, 'error' => 'Restore not implemented'];
    }
    
    public function deleteBackup($filename) {
        $filepath = $this->backup_dir . $filename;
        if (file_exists($filepath) && unlink($filepath)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Cannot delete file'];
    }
    
    public function downloadBackup($filename) {
        $filepath = $this->backup_dir . $filename;
        if (!file_exists($filepath)) {
            return;
        }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    public function getStats() {
        $files = glob($this->backup_dir . '*.gz');
        return [
            'total_backups' => count($files),
            'total_size' => array_sum(array_map('filesize', $files)),
            'newest' => count($files) > 0 ? max(array_map('filemtime', $files)) : null
        ];
    }
}
?>