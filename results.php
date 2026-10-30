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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Voting System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
                <?php if($isVotingOpen): ?>
                    <a class="nav-link" href="vote.php">Vote</a>
                <?php endif; ?>
                <a class="nav-link" href="nominate.php">Nominate</a>
                <a class="nav-link active" href="results.php">Results</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-bar-chart"></i> Voting Results</h2>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $totalVotes; ?></h3>
                        <p>Total Votes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $totalVoters; ?></h3>
                        <p>Total Voters</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $totalNominees; ?></h3>
                        <p>Total Nominees</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if(empty($categoryResults)): ?>
            <div class="alert alert-info">No results available yet.</div>
        <?php else: ?>
            <?php foreach($categoryResults as $category => $nominees): ?>
                <div class="card mb-3">
                    <div class="card-header bg-warning">
                        <h4><?php echo htmlspecialchars($category); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $maxVotes = !empty($nominees) ? max(array_column($nominees, 'vote_count')) : 0;
                        $rank = 1;
                        ?>
                        <?php foreach($nominees as $nominee): 
                            $isWinner = ($nominee['vote_count'] == $maxVotes && $maxVotes > 0);
                            $percentage = $maxVotes > 0 ? round(($nominee['vote_count'] / $maxVotes) * 100) : 0;
                        ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2 <?php echo $isWinner ? 'bg-success bg-opacity-10' : ''; ?>">
                                <div>
                                    <span class="fw-bold">#<?php echo $rank; ?></span>
                                    <strong><?php echo htmlspecialchars($nominee['nominee_name']); ?></strong>
                                    <?php if($isWinner): ?>
                                        <span class="badge bg-warning text-dark">🏆 Winner</span>
                                    <?php endif; ?>
                                    <?php if($nominee['nominee_description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($nominee['nominee_description'], 0, 100)); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="badge bg-primary rounded-pill"><?php echo $nominee['vote_count']; ?> votes</span>
                                    <span class="text-muted ms-2"><?php echo $percentage; ?>%</span>
                                </div>
                            </div>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
