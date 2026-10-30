<?php

require_once 'config/security_headers.php';
require_once 'config.php';
require_once 'config/csrf.php';
require_once 'config/rate_limit.php';
require_once 'config/security.php';

SecurityHeaders::apply();

$votingStatus = getSetting($pdo, 'voting_status');
if($votingStatus != 'open') {
    header('Location: index.php?msg=Voting is currently closed');
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM nominations n WHERE n.category_id = c.id AND n.status = 'approved') as nominee_count
        FROM categories c
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    error_log("Failed to get categories: " . $e->getMessage());
}

$nomineesByCategory = [];
try {
    foreach ($categories as $category) {
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   (SELECT COUNT(*) FROM votes WHERE nomination_id = n.id) as vote_count
            FROM nominations n 
            WHERE n.category_id = ? AND n.status = 'approved'
            ORDER BY n.nominee_name
        ");
        $stmt->execute([$category['id']]);
        $nomineesByCategory[$category['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Failed to get nominees: " . $e->getMessage());
}

$voterId = getUserIP() . '_' . session_id();
$votedCategories = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT n.category_id 
        FROM votes v 
        JOIN nominations n ON v.nomination_id = n.id 
        WHERE v.voter_id = ?
    ");
    $stmt->execute([$voterId]);
    $votedCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Failed to get voted categories: " . $e->getMessage());
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $limiter = new RateLimiter($pdo, 10, 1);
    $key = getUserIP() . '_vote';
    
    if (!$limiter->attempt($key, 'vote')) {
        $error = "You have voted too many times. Please wait a moment before trying again.";
    } else {
        $ip = getUserIP();
        $location = getLocationFromIP($ip);
        
        try {
            $pdo->beginTransaction();
            
            $votesCast = 0;
            $errors = [];
            
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'vote_') === 0 && !empty($value)) {
                    $categoryId = str_replace('vote_', '', $key);
                    $nominationId = (int)$value;
                    
                    $stmt = $pdo->prepare("
                        SELECT v.id FROM votes v
                        JOIN nominations n ON v.nomination_id = n.id
                        WHERE v.voter_id = ? AND n.category_id = ?
                    ");
                    $voterId = getUserIP() . '_' . session_id();
                    $stmt->execute([$voterId, $categoryId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $errors[] = "You have already voted in category ID: $categoryId";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT id FROM nominations 
                        WHERE id = ? AND category_id = ? AND status = 'approved'
                    ");
                    $stmt->execute([$nominationId, $categoryId]);
                    
                    if ($stmt->rowCount() == 0) {
                        $errors[] = "Invalid nomination selected for category ID: $categoryId";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (nomination_id, voter_id, voter_ip, voter_location) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$nominationId, $voterId, $ip, $location]);
                    $votesCast++;
                }
            }
            
            if ($votesCast > 0) {
                $pdo->commit();
                $success = "✅ Your votes have been recorded successfully! Thank you for voting.";
                
            } else {
                $pdo->rollBack();
                if (empty($errors)) {
                    $error = "No votes were cast. Please select at least one nominee.";
                } else {
                    $error = implode("<br>", $errors);
                }
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error recording your vote: " . $e->getMessage();
            error_log("Voting error: " . $e->getMessage());
        }
    }
}

// ================================================
// REMOVED: function getUserIP() - already in config.php
// REMOVED: function getLocationFromIP() - already in config.php
// ================================================

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $totalVotes = $stmt->fetchColumn();
} catch (Exception $e) {
    $totalVotes = 0;
    error_log("Failed to get total votes: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT COUNT(DISTINCT voter_id) as total FROM votes");
    $totalVoters = $stmt->fetchColumn();
} catch (Exception $e) {
    $totalVoters = 0;
    error_log("Failed to get voter count: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Now - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <?php echo csrf_meta(); ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .vote-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            border: none;
            margin-bottom: 30px;
        }
        .vote-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .vote-header i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .vote-body {
            padding: 30px;
            background: white;
        }
        .category-section {
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        .category-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .category-title {
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f8f9ff;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        .category-title .badge {
            font-size: 12px;
            padding: 5px 10px;
        }
        .nominee-option {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
            cursor: pointer;
            background: white;
        }
        .nominee-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateX(5px);
        }
        .nominee-option.selected {
            border-color: #28a745;
            background: #f0fff4;
        }
        .nominee-option .nominee-name {
            font-weight: 600;
            color: #333;
        }
        .nominee-option .vote-count {
            font-size: 12px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 2px 10px;
            border-radius: 12px;
        }
        .nominee-option .description {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
        }
        .nominee-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }
        .nominee-option input[type="radio"]:disabled {
            cursor: not-allowed;
        }
        .btn-submit {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 18px;
            transition: transform 0.3s;
        }
        .btn-submit:hover {
            transform: scale(1.02);
            color: white;
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .stats-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-box .number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        .stats-box .label {
            font-size: 13px;
            color: #6c757d;
        }
        .voted-badge {
            background: #28a745;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .already-voted {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .already-voted .nominee-option {
            border-color: #28a745;
            background: #f0fff4;
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
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house"></i> Home
                    </a>
                    <a class="nav-link active" href="vote.php">
                        <i class="bi bi-check-circle"></i> Vote
                    </a>
                    <a class="nav-link" href="nominate.php">
                        <i class="bi bi-person-plus"></i> Nominate
                    </a>
                    <a class="nav-link" href="results.php">
                        <i class="bi bi-bar-chart"></i> Results
                    </a>
                </div>
            </div>
        </nav>

        <div class="vote-card">
            <div class="vote-header">
                <i class="bi bi-check-circle"></i>
                <h2 class="mb-0">Cast Your Vote</h2>
                <p class="mb-0 opacity-75">Vote for the best candidates in each category</p>
            </div>
            <div class="vote-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-box">
                            <div class="number"><?php echo count($categories); ?></div>
                            <div class="label">Categories</div>
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
                            <div class="number"><?php echo $totalVotes; ?></div>
                            <div class="label">Total Votes Cast</div>
                        </div>
                    </div>
                </div>

                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($votingStatus == 'open'): ?>
                    <form method="POST" id="voteForm" onsubmit="return validateForm()">
                        <?php echo csrf_field(); ?>
                        
                        <?php foreach($categories as $category): 
                            $nominees = $nomineesByCategory[$category['id']] ?? [];
                            $hasVoted = in_array($category['id'], $votedCategories);
                            $hasNominees = count($nominees) > 0;
                        ?>
                            <div class="category-section <?php echo $hasVoted ? 'already-voted' : ''; ?>">
                                <div class="category-title d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-trophy text-primary"></i>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </span>
                                    <?php if($hasVoted): ?>
                                        <span class="voted-badge">
                                            <i class="bi bi-check-circle"></i> Already Voted
                                        </span>
                                    <?php endif; ?>
                                    <?php if(!$hasNominees): ?>
                                        <span class="badge bg-warning text-dark">No Nominees</span>
                                    <?php endif; ?>
                                </div>

                                <?php if($hasNominees): ?>
                                    <?php foreach($nominees as $nominee): ?>
                                        <div class="nominee-option" onclick="selectNominee('<?php echo $category['id']; ?>', '<?php echo $nominee['id']; ?>')">
                                            <div class="d-flex align-items-start">
                                                <input type="radio" 
                                                       name="vote_<?php echo $category['id']; ?>" 
                                                       value="<?php echo $nominee['id']; ?>" 
                                                       id="nominee_<?php echo $nominee['id']; ?>"
                                                       <?php echo $hasVoted ? 'disabled' : ''; ?>
                                                       required>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <span class="nominee-name"><?php echo htmlspecialchars($nominee['nominee_name']); ?></span>
                                                            <?php if($nominee['nominee_email']): ?>
                                                                <small class="text-muted ms-2">
                                                                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($nominee['nominee_email']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="vote-count">
                                                            <i class="bi bi-people"></i> <?php echo $nominee['vote_count']; ?> votes
                                                        </span>
                                                    </div>
                                                    <?php if($nominee['nominee_description']): ?>
                                                        <div class="description">
                                                            <?php echo htmlspecialchars(substr($nominee['nominee_description'], 0, 150)); ?>
                                                            <?php if(strlen($nominee['nominee_description']) > 150): ?>
                                                                <span class="text-muted">...</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> No approved nominees in this category yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-success btn-submit" id="submitBtn">
                                <i class="bi bi-check-circle"></i> Submit Your Votes
                            </button>
                            <p class="text-muted mt-2">
                                <small><i class="bi bi-info-circle"></i> You can only vote once per category</small>
                            </p>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <h4><i class="bi bi-clock"></i> Voting is Currently Closed</h4>
                        <p>Voting will be available when the administrator opens it.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-white mt-4">
            <small>
                <i class="bi bi-shield-check"></i> Secured with CSRF Protection &amp; Rate Limiting
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectNominee(categoryId, nomineeId) {
            const radio = document.getElementById('nominee_' + nomineeId);
            if (radio && !radio.disabled) {
                radio.checked = true;
                
                const categorySection = radio.closest('.category-section');
                if (categorySection) {
                    const options = categorySection.querySelectorAll('.nominee-option');
                    options.forEach(function(opt) {
                        opt.classList.remove('selected');
                    });
                    
                    const selectedOption = radio.closest('.nominee-option');
                    if (selectedOption) {
                        selectedOption.classList.add('selected');
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
            checkedRadios.forEach(function(radio) {
                const option = radio.closest('.nominee-option');
                if (option) {
                    option.classList.add('selected');
                }
            });
        });

        function validateForm() {
            const categories = document.querySelectorAll('.category-section:not(.already-voted)');
            let allVoted = true;
            let errors = [];
            
            categories.forEach(function(category) {
                const radios = category.querySelectorAll('input[type="radio"]');
                const isChecked = Array.from(radios).some(radio => radio.checked);
                
                if (radios.length > 0 && !isChecked) {
                    allVoted = false;
                    const categoryTitle = category.querySelector('.category-title span').textContent.trim();
                    errors.push('Please select a nominee in "' + categoryTitle + '"');
                }
            });
            
            if (!allVoted) {
                alert('⚠️ Please vote in all categories:\n\n' + errors.join('\n'));
                return false;
            }
            
            const totalVotes = document.querySelectorAll('input[type="radio"]:checked').length;
            if (totalVotes === 0) {
                alert('Please select at least one nominee to vote for.');
                return false;
            }
            
            return confirm('✅ Are you sure you want to submit your votes?\n\nYou have selected ' + totalVotes + ' nominee(s).\n\nYou cannot change your votes after submission.');
        }

        document.getElementById('voteForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting Your Votes...';
        });

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>