<?php
require_once __DIR__ . '/../config.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$reportData = [];
$winners = [];
$zoneVotes = [];
$error = '';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM votes LIKE 'voter_location'");
    $hasLocation = $stmt->rowCount() > 0;
    
    if ($hasLocation) {
        $stmt = $pdo->query("
            SELECT 
                n.nominee_name,
                c.name as category_name,
                v.voter_location,
                COUNT(*) as vote_count
            FROM votes v
            JOIN nominations n ON v.nomination_id = n.id
            JOIN categories c ON n.category_id = c.id
            WHERE n.status = 'approved'
            GROUP BY n.id, v.voter_location
            ORDER BY c.name, vote_count DESC
        ");
        $zoneVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($zoneVotes as $vote) {
            $location = $vote['voter_location'] ?: 'Unknown';
            $reportData[$vote['category_name']][$vote['nominee_name']][$location] = $vote['vote_count'];
        }
    } else {
        $stmt = $pdo->query("
            SELECT 
                n.nominee_name,
                c.name as category_name,
                COUNT(*) as vote_count
            FROM votes v
            JOIN nominations n ON v.nomination_id = n.id
            JOIN categories c ON n.category_id = c.id
            WHERE n.status = 'approved'
            GROUP BY n.id
            ORDER BY c.name, vote_count DESC
        ");
        $simpleVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($simpleVotes as $vote) {
            $reportData[$vote['category_name']][$vote['nominee_name']]['Total'] = $vote['vote_count'];
        }
    }
    
    $stmt = $pdo->query("
        SELECT 
            n.nominee_name,
            n.nominee_description,
            c.name as category_name,
            COUNT(*) as total_votes
        FROM votes v
        JOIN nominations n ON v.nomination_id = n.id
        JOIN categories c ON n.category_id = c.id
        WHERE n.status = 'approved'
        GROUP BY n.id
        ORDER BY c.name, total_votes DESC
    ");
    
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($allResults as $row) {
        if(!isset($winners[$row['category_name']])) {
            $winners[$row['category_name']] = $row;
        }
    }
    
} catch (Exception $e) {
    $error = "Error generating report: " . $e->getMessage();
    error_log("Reports error: " . $e->getMessage());
}

$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $stats['total_votes'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM nominations WHERE status = 'approved'");
    $stats['total_nominees'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $stats['total_categories'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT voter_id) as total FROM votes");
    $stats['unique_voters'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Failed to get statistics: " . $e->getMessage());
}

$categoryVotes = [];
try {
    $stmt = $pdo->query("
        SELECT 
            c.name as category,
            COUNT(v.id) as votes
        FROM categories c
        LEFT JOIN nominations n ON n.category_id = c.id AND n.status = 'approved'
        LEFT JOIN votes v ON v.nomination_id = n.id
        GROUP BY c.id
        ORDER BY votes DESC
    ");
    $categoryVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to get category votes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .icon {
            font-size: 32px;
            opacity: 0.7;
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
        }
        .winner-card {
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            height: 100%;
            transition: transform 0.3s;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .winner-card:hover {
            transform: scale(1.03);
        }
        .winner-card .trophy {
            font-size: 48px;
            color: #ffc107;
        }
        .winner-card .crown {
            font-size: 32px;
            color: #ffd700;
        }
        .table-zone {
            font-size: 0.9rem;
        }
        .table-zone th {
            background-color: #f8f9fa;
        }
        .badge-votes {
            font-size: 1rem;
            padding: 8px 12px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-bar-chart-fill"></i> Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link" href="backup.php">
                    <i class="bi bi-database"></i> Backup
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">
            <i class="bi bi-file-earmark-text"></i> Voting Reports & Analytics
        </h2>

        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card bg-primary text-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="icon"><i class="bi bi-vote2"></i></div>
                            <div>Total Votes</div>
                            <div class="number"><?php echo number_format($stats['total_votes'] ?? 0); ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-up-circle" style="font-size: 40px;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success text-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="icon"><i class="bi bi-person-check"></i></div>
                            <div>Nominees</div>
                            <div class="number"><?php echo number_format($stats['total_nominees'] ?? 0); ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people" style="font-size: 40px;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-info text-white">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="icon"><i class="bi bi-tags"></i></div>
                            <div>Categories</div>
                            <div class="number"><?php echo number_format($stats['total_categories'] ?? 0); ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-grid" style="font-size: 40px;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning text-dark">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="icon"><i class="bi bi-person"></i></div>
                            <div>Voters</div>
                            <div class="number"><?php echo number_format($stats['unique_voters'] ?? 0); ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-badge" style="font-size: 40px;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!empty($winners)): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="bi bi-trophy"></i> Current Winners by Category</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach($winners as $category => $winner): ?>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="winner-card">
                                <div class="trophy">🏆</div>
                                <div class="crown">👑</div>
                                <h5 class="mt-2 text-truncate" title="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </h5>
                                <h4 class="text-success"><?php echo htmlspecialchars($winner['nominee_name']); ?></h4>
                                <p class="text-muted small"><?php echo htmlspecialchars(substr($winner['nominee_description'] ?? '', 0, 50)); ?></p>
                                <span class="badge bg-primary badge-votes">
                                    <i class="bi bi-star-fill"></i> <?php echo $winner['total_votes']; ?> votes
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No winners yet. Votes need to be cast first.
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0"><i class="bi bi-bar-chart"></i> Vote Distribution by Category</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="voteChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h4 class="mb-0"><i class="bi bi-pie-chart"></i> Category Breakdown</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!empty($reportData)): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-geo-alt"></i> Zone-wise Voting Analysis</h4>
            </div>
            <div class="card-body">
                <?php foreach($reportData as $category => $nominees): ?>
                    <h4 class="mt-4">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($category); ?></span>
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-zone">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nominee</th>
                                    <?php
                                    $allZones = [];
                                    foreach($nominees as $zones) {
                                        $allZones = array_unique(array_merge($allZones, array_keys($zones)));
                                    }
                                    sort($allZones);
                                    foreach($allZones as $zone):
                                    ?>
                                        <th class="text-center">
                                            <i class="bi bi-geo-alt"></i>
                                            <?php echo htmlspecialchars($zone); ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center"><i class="bi bi-trophy"></i> Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($nominees as $nominee => $zones): 
                                    $total = array_sum($zones);
                                    $maxVotes = max($zones);
                                    $isWinner = ($total == max(array_map('array_sum', $nominees)));
                                ?>
                                    <tr <?php echo $isWinner ? 'class="table-success"' : ''; ?>>
                                        <td>
                                            <strong><?php echo htmlspecialchars($nominee); ?></strong>
                                            <?php if($isWinner): ?>
                                                <span class="badge bg-success ms-2">🏆 Winner</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach($allZones as $zone): 
                                            $votes = isset($zones[$zone]) ? $zones[$zone] : 0;
                                            $percentage = $total > 0 ? round(($votes / $total) * 100) : 0;
                                        ?>
                                            <td class="text-center">
                                                <?php if($votes > 0): ?>
                                                    <span class="badge bg-info"><?php echo $votes; ?></span>
                                                    <small class="text-muted d-block"><?php echo $percentage; ?>%</small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-center">
                                            <strong class="text-primary"><?php echo $total; ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="bi bi-download"></i> Export Reports</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="?export=csv" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel"></i> Export CSV
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="?export=pdf" class="btn btn-danger w-100" onclick="window.print()">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="?print" class="btn btn-info w-100" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </a>
                    </div>
                    <div class="col-md-3">
                        <button onclick="location.reload()" class="btn btn-secondary w-100">
                            <i class="bi bi-arrow-repeat"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('voteChart').getContext('2d');
        const categories = <?php echo json_encode(array_column($categoryVotes, 'category')); ?>;
        const votes = <?php echo json_encode(array_column($categoryVotes, 'votes')); ?>;
        
        if (categories.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categories,
                    datasets: [{
                        label: 'Total Votes',
                        data: votes,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Votes by Category'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        const ctx2 = document.getElementById('pieChart').getContext('2d');
        const categories2 = <?php echo json_encode(array_column($categoryVotes, 'category')); ?>;
        const votes2 = <?php echo json_encode(array_column($categoryVotes, 'votes')); ?>;
        
        if (categories2.length > 0) {
            new Chart(ctx2, {
                type: 'pie',
                data: {
                    labels: categories2,
                    datasets: [{
                        data: votes2,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Category Distribution'
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>