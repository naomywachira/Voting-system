<?php

require_once 'config/security_headers.php';
require_once 'config.php';

SecurityHeaders::apply();

$votingStatus = getSetting($pdo, 'voting_status');
$isVotingOpen = ($votingStatus === 'open');

try {
    $stmt = $pdo->query("
        SELECT 
            c.id as category_id,
            c.name as category_name,
            n.id as nominee_id,
            n.nominee_name,
            n.nominee_description,
            COUNT(v.id) as vote_count
        FROM categories c
        LEFT JOIN nominations n ON n.category_id = c.id AND n.status = 'approved'
        LEFT JOIN votes v ON v.nomination_id = n.id
        GROUP BY n.id, c.id
        ORDER BY c.name, vote_count DESC
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categoryResults = [];
    foreach ($results as $row) {
        if (!isset($categoryResults[$row['category_name']])) {
            $categoryResults[$row['category_name']] = [];
        }
        if ($row['nominee_id']) {
            $categoryResults[$row['category_name']][] = $row;
        }
    }
} catch (Exception $e) {
    $categoryResults = [];
    error_log("Failed to get results: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $totalVotes = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT voter_id) as total FROM votes");
    $totalVoters = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM nominations WHERE status = 'approved'");
    $totalNominees = $stmt->fetchColumn();
} catch (Exception $e) {
    $totalVotes = 0;
    $totalVoters = 0;
    $totalNominees = 0;
    error_log("Failed to get stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .results-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            border: none;
            margin-bottom: 30px;
        }
        .results-header {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .results-header i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .results-body {
            padding: 30px;
            background: white;
        }
        .category-result {
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        .category-result:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .category-title {
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #fff8e1;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }
        .result-item {
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.3s;
        }
        .result-item:hover {
            background: #f8f9ff;
        }
        .result-item .rank {
            font-weight: bold;
            color: #667eea;
            min-width: 30px;
        }
        .result-item .name {
            font-weight: 600;
        }
        .result-item .votes {
            font-size: 14px;
            color: #6c757d;
        }
        .result-item .vote-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        .result-item .vote-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 1s ease;
        }
        .result-item.winner {
            background: #f0fff4;
            border-color: #28a745;
            border-width: 2px;
        }
        .winner-badge {
            background: #ffd700;
            color: #333;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .stats-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats-box .number {
            font-size: 28px;
            font-weight: bold;
            color: #ffc107;
        }
        .stats-box .label {
            font-size: 13px;
            color: #6c757d;
        }
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255,255,255,0.8);
        }
        .navbar-dark .navbar-nav .nav-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded-3 mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-vote2"></i> Voting System
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        <?php if($isVotingOpen): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="vote.php">
                                <i class="bi bi-check-circle"></i> Vote
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="nominate.php">
                                <i class="bi bi-person-plus"></i> Nominate
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="results.php">
                                <i class="bi bi-bar-chart"></i> Results
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="results-card">
            <div class="results-header">
                <i class="bi bi-bar-chart"></i>
                <h2 class="mb-0">Voting Results</h2>
                <p class="mb-0 opacity-75">Current standings across all categories</p>
                <?php if($isVotingOpen): ?>
                    <span class="badge bg-success mt-2">
                        <i class="bi bi-check-circle"></i> Voting is OPEN
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger mt-2">
                        <i class="bi bi-x-circle"></i> Voting is CLOSED
                    </span>
                <?php endif; ?>
            </div>
            <div class="results-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-box">
                            <div class="number"><?php echo $totalVotes; ?></div>
                            <div class="label">Total Votes</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-box">
                            <div class="number"><?php echo $totalVoters; ?></div>
                            <div class="label">Total Voters</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-box">
                            <div class="number"><?php echo $totalNominees; ?></div>
                            <div class="label">Total Nominees</div>
                        </div>
                    </div>
                </div>

                <?php if(empty($categoryResults)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No results available yet. Votes will appear here once cast.
                    </div>
                <?php else: ?>
                    <?php foreach($categoryResults as $category => $nominees): ?>
                        <div class="category-result">
                            <div class="category-title">
                                <i class="bi bi-trophy text-warning"></i>
                                <?php echo htmlspecialchars($category); ?>
                                <span class="badge bg-secondary ms-2"><?php echo count($nominees); ?> nominees</span>
                            </div>
                            
                            <?php 
                            $maxVotes = !empty($nominees) ? max(array_column($nominees, 'vote_count')) : 0;
                            $rank = 1;
                            ?>
                            <?php foreach($nominees as $nominee): ?>
                                <?php 
                                $isWinner = ($nominee['vote_count'] == $maxVotes && $maxVotes > 0);
                                $percentage = $maxVotes > 0 ? round(($nominee['vote_count'] / $maxVotes) * 100) : 0;
                                ?>
                                <div class="result-item <?php echo $isWinner ? 'winner' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="rank">#<?php echo $rank; ?></span>
                                            <span class="name"><?php echo htmlspecialchars($nominee['nominee_name']); ?></span>
                                            <?php if($isWinner): ?>
                                                <span class="winner-badge ms-2">🏆 Winner</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="votes">
                                                <i class="bi bi-people"></i> <?php echo $nominee['vote_count']; ?> votes
                                            </span>
                                            <span class="text-muted ms-2"><?php echo $percentage; ?>%</span>
                                        </div>
                                    </div>
                                    <?php if($nominee['nominee_description']): ?>
                                        <div class="text-muted small mt-1">
                                            <?php echo htmlspecialchars(substr($nominee['nominee_description'], 0, 100)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="vote-bar">
                                        <div class="fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </div>
                                <?php $rank++; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-white mt-4">
            <small>
                <i class="bi bi-shield-check"></i> Results are updated in real-time
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.vote-bar .fill');
            bars.forEach(function(bar, index) {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(function() {
                    bar.style.width = width;
                }, 200 + (index * 50));
            });
        });
    </script>
</body>
</html>