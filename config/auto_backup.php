<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'voting_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

require_once __DIR__ . '/backup_class.php';

$backup = new DatabaseBackup($pdo);
$message = '';
$error = '';

if (isset($_POST['create_backup'])) {
    $result = $backup->createBackup();
    if ($result['success']) {
        $message = "Backup created successfully: " . $result['file'];
    } else {
        $error = "Backup failed: " . $result['error'];
    }
}

if (isset($_POST['restore_backup']) && isset($_POST['filename'])) {
    if (isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {
        $result = $backup->restoreBackup($_POST['filename']);
        if ($result['success']) {
            $message = "Backup restored successfully!";
        } else {
            $error = "Restore failed: " . $result['error'];
        }
    }
}

if (isset($_POST['delete_backup']) && isset($_POST['filename'])) {
    $result = $backup->deleteBackup($_POST['filename']);
    if ($result['success']) {
        $message = "Backup deleted successfully!";
    } else {
        $error = "Delete failed: " . $result['error'];
    }
}

if (isset($_GET['download']) && isset($_GET['file'])) {
    $backup->downloadBackup($_GET['file']);
    exit;
}

$stats = $backup->getStats();
$backups = $backup->listBackups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .backup-size { font-family: monospace; }
        .action-buttons .btn { margin: 2px; }
        .stats-card {
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            text-align: center;
            margin-bottom: 15px;
        }
        .stats-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
        }
        .stats-card .label {
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-database"></i> Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="reports.php">Reports</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-database"></i> Database Backup Manager</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <div class="number"><?php echo $stats['total_backups']; ?></div>
                                    <div class="label">Total Backups</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <div class="number"><?php echo round($stats['total_size'] / 1024 / 1024, 2); ?> MB</div>
                                    <div class="label">Total Size</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <div class="number">
                                        <?php 
                                        if ($stats['newest']) {
                                            echo date('Y-m-d', $stats['newest']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <div class="label">Latest Backup</div>
                                </div>
                            </div>
                        </div>

                        <?php if($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4 p-3 bg-light rounded">
                            <h4><i class="bi bi-plus-circle"></i> Create New Backup</h4>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="create_backup" class="btn btn-success" 
                                        onclick="this.disabled=true; this.innerHTML='<i class=\'bi bi-hourglass-split\'></i> Creating...'; this.form.submit();">
                                    <i class="bi bi-database"></i> Create New Backup
                                </button>
                            </form>
                            <small class="text-muted ms-3">
                                <i class="bi bi-info-circle"></i> 
                                Creates a compressed backup of your entire database. Auto-backups run daily at 2:00 AM.
                            </small>
                            <a href="../logs/backup.log" target="_blank" class="btn btn-outline-secondary btn-sm ms-3">
                                <i class="bi bi-file-text"></i> View Logs
                            </a>
                        </div>
                        
                        <h4><i class="bi bi-list"></i> Available Backups</h4>
                        <?php if(count($backups) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Date Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($backups as $index => $backup_file): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($backup_file['filename']); ?></td>
                                            <td class="backup-size"><?php echo round($backup_file['size'] / 1024, 2); ?> KB</td>
                                            <td><?php echo date('Y-m-d H:i:s', $backup_file['modified']); ?></td>
                                            <td class="action-buttons">
                                                <form method="POST" style="display: inline-block;" 
                                                      onsubmit="return confirmRestore('<?php echo htmlspecialchars($backup_file['filename']); ?>')">
                                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup_file['filename']); ?>">
                                                    <input type="hidden" name="confirm" id="confirm_<?php echo $index; ?>" value="no">
                                                    <button type="submit" name="restore_backup" class="btn btn-warning btn-sm" 
                                                            onclick="setConfirm(<?php echo $index; ?>, '<?php echo htmlspecialchars($backup_file['filename']); ?>')">
                                                        <i class="bi bi-arrow-repeat"></i> Restore
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline-block;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this backup? This action cannot be undone.')">
                                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup_file['filename']); ?>">
                                                    <button type="submit" name="delete_backup" class="btn btn-danger btn-sm">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                                
                                                <a href="?download=1&file=<?php echo urlencode($backup_file['filename']); ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <strong><i class="bi bi-lightbulb"></i> Tips:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Backups are stored in the <code>backups/</code> folder</li>
                                    <li>Files are compressed (gzip format) to save space</li>
                                    <li>Auto-backups run daily at 2:00 AM and keep 30 days of history</li>
                                    <li>Restoring will overwrite your current database</li>
                                    <li>Download backups to keep offline copies for extra security</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>No backups found!</strong> Click "Create New Backup" to create your first database backup.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmRestore(filename) {
        return confirm('WARNING: Restoring will overwrite your current database!\n\nAre you absolutely sure you want to restore from: ' + filename + '?\n\nThis action cannot be undone!');
    }
    
    function setConfirm(index, filename) {
        if (confirmRestore(filename)) {
            document.getElementById('confirm_' + index).value = 'yes';
            return true;
        }
        return false;
    }
    </script>
</body>
</html>