<?php
session_start();
require_once 'config/security_headers.php';
require_once 'config.php';

SecurityHeaders::apply();


$nominationStatus = getSetting($pdo, 'nomination_status');
$votingStatus = getSetting($pdo, 'voting_status');

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $totalVotes = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM nominations WHERE status = 'approved'");
    $totalNominees = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM nominations WHERE status = 'pending'");
    $pendingNominations = $stmt->fetchColumn();
} catch (Exception $e) {
    $totalVotes = 0;
    $totalNominees = 0;
    $pendingNominations = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting System - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .card { 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
        }
        .btn-primary { 
            background: linear-gradient(45deg, #667eea, #764ba2); 
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-success {
            padding: 12px 30px;
            font-weight: 600;
        }
        .stat-box {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-box .label {
            font-size: 14px;
            opacity: 0.9;
        }
        .footer {
            background: #2d3748;
            color: #a0aec0;
            padding: 30px 0;
            margin-top: 50px;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-vote2"></i> Voting System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="index.php"><i class="bi bi-house"></i> Home</a>
                <a class="nav-link" href="vote.php"><i class="bi bi-check-circle"></i> Vote Now</a>
                <a class="nav-link" href="nominate.php"><i class="bi bi-person-plus"></i> Nominate</a>
                <a class="nav-link" href="results.php"><i class="bi bi-bar-chart"></i> Results</a>
                <a class="nav-link" href="admin/login.php"><i class="bi bi-shield-lock"></i> Admin</a>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-3">Welcome to Voting System</h1>
                    <p class="lead mb-4">Cast your vote for the best candidates across multiple categories.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <?php if($nominationStatus == 'open'): ?>
                            <a href="nominate.php" class="btn btn-light btn-lg">Submit Nomination</a>
                        <?php endif; ?>
                        <?php if($votingStatus == 'open'): ?>
                            <a href="vote.php" class="btn btn-success btn-lg">Cast Vote</a>
                        <?php endif; ?>
                        <a href="results.php" class="btn btn-outline-light btn-lg">View Results</a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="number"><?php echo $totalVotes; ?></div>
                                <div class="label">Total Votes</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="number"><?php echo $totalNominees; ?></div>
                                <div class="label">Nominees</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="number"><?php echo count($categories); ?></div>
                                <div class="label">Categories</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="number"><?php echo $pendingNominations; ?></div>
                                <div class="label">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> System Status</h5>
                            <div>
                                <span class="badge bg-<?php echo $nominationStatus == 'open' ? 'success' : 'danger'; ?> status-badge">
                                    Nominations: <?php echo ucfirst($nominationStatus); ?>
                                </span>
                                <span class="badge bg-<?php echo $votingStatus == 'open' ? 'success' : 'danger'; ?> status-badge ms-2">
                                    Voting: <?php echo ucfirst($votingStatus); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="mb-3"><i class="bi bi-rocket"></i> Quick Actions</h5>
                        <div class="d-flex justify-content-center flex-wrap gap-3">
                            <?php if($nominationStatus == 'open'): ?>
                                <a href="nominate.php" class="btn btn-primary">Submit Nomination</a>
                            <?php endif; ?>
                            <?php if($votingStatus == 'open'): ?>
                                <a href="vote.php" class="btn btn-success">Cast Your Vote</a>
                            <?php endif; ?>
                            <a href="results.php" class="btn btn-info text-white">View Results</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Voting System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>