<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

if (!isset($pdo)) {
    die("Database connection not established. Check your config.php file.");
}

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!function_exists('updateSetting')) {
    function updateSetting($pdo, $key, $value) {
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            return $stmt->execute([$key, $value, $value]);
        } catch (Exception $e) {
            error_log("Failed to update setting: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getSetting')) {
    function getSetting($pdo, $key) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            return $result ?: 'closed'; 
        } catch (Exception $e) {
            error_log("Failed to get setting: " . $e->getMessage());
            return 'closed';
        }
    }
}


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(isset($_POST['approve']) && isset($_POST['nomination_id'])) {
            $stmt = $pdo->prepare("UPDATE nominations SET status = 'approved', admin_remarks = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['remarks'] ?? '', $_POST['nomination_id']]);
            
            sendNominationEmail($pdo, $_POST['nomination_id'], 'approved', $_POST['remarks'] ?? '');
            
            $_SESSION['message'] = "Nomination approved successfully!";
            $_SESSION['message_type'] = "success";
            
        } elseif(isset($_POST['reject']) && isset($_POST['nomination_id'])) {
            $stmt = $pdo->prepare("UPDATE nominations SET status = 'rejected', admin_remarks = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['remarks'] ?? '', $_POST['nomination_id']]);
            
            sendNominationEmail($pdo, $_POST['nomination_id'], 'rejected', $_POST['remarks'] ?? '');
            
            $_SESSION['message'] = "Nomination rejected successfully!";
            $_SESSION['message_type'] = "warning";
            
        } elseif(isset($_POST['update_settings'])) {
            updateSetting($pdo, 'nomination_status', $_POST['nomination_status'] ?? 'closed');
            updateSetting($pdo, 'voting_status', $_POST['voting_status'] ?? 'closed');
            
            $_SESSION['message'] = "Settings updated successfully!";
            $_SESSION['message_type'] = "success";
        }
        
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        error_log("Dashboard error: " . $e->getMessage());
    }
}

function sendNominationEmail($pdo, $nomination_id, $status, $remarks) {
    try {
        $stmt = $pdo->prepare("SELECT nominee_name, nominee_email FROM nominations WHERE id = ?");
        $stmt->execute([$nomination_id]);
        $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nominee && !empty($nominee['nominee_email'])) {
            if (file_exists(__DIR__ . '/../config/email.php')) {
                require_once __DIR__ . '/../config/email.php';
                if (class_exists('EmailNotifier')) {
                    $emailNotifier = new EmailNotifier($pdo);
                    $emailNotifier->sendNominationStatus(
                        $nominee['nominee_email'],
                        $nominee['nominee_name'],
                        $status,
                        $remarks
                    );
                }
            }
        }
    } catch (Exception $e) {
        error_log("Failed to send nomination email: " . $e->getMessage());
    }
}

try {
    $stmt = $pdo->query("
        SELECT n.*, c.name as category_name 
        FROM nominations n 
        JOIN categories c ON n.category_id = c.id 
        WHERE n.status = 'pending'
        ORDER BY n.created_at DESC
    ");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pending = [];
    error_log("Failed to get pending nominations: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("
        SELECT n.*, c.name as category_name,
               (SELECT COUNT(*) FROM votes WHERE nomination_id = n.id) as vote_count
        FROM nominations n 
        JOIN categories c ON n.category_id = c.id 
        WHERE n.status = 'approved'
        ORDER BY c.name, vote_count DESC
    ");
    $approved = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $approved = [];
    error_log("Failed to get approved nominations: " . $e->getMessage());
}

$nominationStatus = getSetting($pdo, 'nomination_status');
$votingStatus = getSetting($pdo, 'voting_status');

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-person-badge"></i> Admin Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-file-earmark-text"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="backup.php">
                            <i class="bi bi-database"></i> Backup
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-gear"></i> System Settings</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nomination Status</label>
                                <select name="nomination_status" class="form-select">
                                    <option value="open" <?php echo $nominationStatus == 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="closed" <?php echo $nominationStatus == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Voting Status</label>
                                <select name="voting_status" class="form-select">
                                    <option value="open" <?php echo $votingStatus == 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="closed" <?php echo $votingStatus == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" name="update_settings" class="btn btn-primary w-100">
                                    <i class="bi bi-save"></i> Update Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0">
                            <i class="bi bi-clock-history"></i> 
                            Pending Nominations 
                            <span class="badge bg-dark"><?php echo count($pending); ?></span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if(count($pending) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="pendingTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Category</th>
                                            <th>Nominee</th>
                                            <th>Submitted By</th>
                                            <th>Description</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pending as $nom): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($nom['category_name']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($nom['nominee_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($nom['submitted_by'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars(substr($nom['nominee_description'] ?? '', 0, 100)); ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex flex-column gap-1">
                                                        <input type="hidden" name="nomination_id" value="<?php echo $nom['id']; ?>">
                                                        <input type="text" name="remarks" placeholder="Remarks" class="form-control form-control-sm" required>
                                                        <div class="d-flex gap-1">
                                                            <button type="submit" name="approve" class="btn btn-success btn-sm flex-fill">
                                                                <i class="bi bi-check-circle"></i> Approve
                                                            </button>
                                                            <button type="submit" name="reject" class="btn btn-danger btn-sm flex-fill">
                                                                <i class="bi bi-x-circle"></i> Reject
                                                            </button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No pending nominations to review.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-trophy"></i> 
                            Approved Nominations & Vote Counts
                            <span class="badge bg-light text-dark"><?php echo count($approved); ?></span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if(count($approved) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="approvedTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Category</th>
                                            <th>Nominee</th>
                                            <th>Description</th>
                                            <th>Votes</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($approved as $nom): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($nom['category_name']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($nom['nominee_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars(substr($nom['nominee_description'] ?? '', 0, 100)); ?></td>
                                                <td>
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?php echo $nom['vote_count']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($nom['vote_count'] > 0): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No votes</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> No approved nominations yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#approvedTable').DataTable({
                pageLength: 10,
                order: [[3, 'desc']], 
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries"
                }
            });
            
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
        
        document.querySelectorAll('button[name="reject"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to reject this nomination?')) {
                    e.preventDefault();
                }
            });
        });
        
        document.querySelectorAll('button[name="approve"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to approve this nomination?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>