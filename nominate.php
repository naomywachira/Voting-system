<?php
require_once 'config/security_headers.php';
require_once 'config.php';
require_once 'config/csrf.php';
require_once 'config/rate_limit.php';
require_once 'config/security.php';

SecurityHeaders::apply();

$nominationStatus = getSetting($pdo, 'nomination_status');
if($nominationStatus != 'open') {
    header('Location: index.php?msg=Nomination period is currently closed');
    exit;
}

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';
$formData = [];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf();
    
    $limiter = new RateLimiter($pdo, 5, 1);
    $key = getUserIP() . '_nomination';
    
    if (!$limiter->attempt($key, 'nomination')) {
        $error = "Too many nomination attempts. Please wait a moment before trying again.";
    } else {
        $category_id = (int)$_POST['category_id'];
        $nominee_name = Security::sanitizeInput($_POST['nominee_name']);
        $nominee_email = Security::sanitizeInput($_POST['nominee_email']);
        $nominee_phone = Security::sanitizeInput($_POST['nominee_phone'] ?? '');
        $nominee_description = Security::sanitizeInput($_POST['nominee_description']);
        $submitted_by = Security::sanitizeInput($_POST['submitted_by']);
        $submitter_email = Security::sanitizeInput($_POST['submitter_email']);
        
        $formData = [
            'category_id' => $category_id,
            'nominee_name' => $nominee_name,
            'nominee_email' => $nominee_email,
            'nominee_phone' => $nominee_phone,
            'nominee_description' => $nominee_description,
            'submitted_by' => $submitted_by,
            'submitter_email' => $submitter_email
        ];
        
        $errors = [];
        
        if (empty($category_id)) {
            $errors[] = "Please select a category.";
        }
        
        if (empty($nominee_name) || strlen($nominee_name) < 2) {
            $errors[] = "Please enter a valid nominee name (minimum 2 characters).";
        }
        
        if (!empty($nominee_email) && !Security::validateEmail($nominee_email)) {
            $errors[] = "Please enter a valid email address for the nominee.";
        }
        
        if (empty($nominee_description) || strlen($nominee_description) < 10) {
            $errors[] = "Please provide a detailed reason for nomination (minimum 10 characters).";
        }
        
        if (empty($submitted_by) || strlen($submitted_by) < 2) {
            $errors[] = "Please enter your full name.";
        }
        
        if (empty($submitter_email) || !Security::validateEmail($submitter_email)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    SELECT id FROM nominations 
                    WHERE category_id = ? AND nominee_name = ? AND status != 'rejected'
                ");
                $stmt->execute([$category_id, $nominee_name]);
                
                if ($stmt->rowCount() > 0) {
                    $errors[] = "This nominee has already been submitted in this category.";
                }
            } catch (Exception $e) {
                error_log("Error checking duplicate nomination: " . $e->getMessage());
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO nominations (
                        category_id, 
                        nominee_name, 
                        nominee_email, 
                        nominee_phone,
                        nominee_description, 
                        submitted_by, 
                        submitter_email,
                        status,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                if($stmt->execute([
                    $category_id, 
                    $nominee_name, 
                    $nominee_email, 
                    $nominee_phone,
                    $nominee_description, 
                    $submitted_by, 
                    $submitter_email
                ])) {
                    $success = "✅ Nomination submitted successfully! It will be reviewed by the administrators.";
                    $formData = [];
                    
                    // Email disabled - PHPMailer not installed
                    // Uncomment this if you install PHPMailer via Composer
                    /*
                    if (file_exists(__DIR__ . '/config/email.php')) {
                        try {
                            require_once __DIR__ . '/config/email.php';
                            $emailNotifier = new EmailNotifier($pdo);
                            $emailNotifier->sendNominationConfirmation($submitter_email, $nominee_name, $category_id);
                        } catch (Exception $e) {
                            error_log("Failed to send confirmation email: " . $e->getMessage());
                        }
                    }
                    */
                } else {
                    $error = "Error submitting nomination. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Nomination error: " . $e->getMessage());
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// ================================================
// REMOVED: function getUserIP() 
// This function is already in config.php
// ================================================

function getCategoryName($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Nomination - Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <?php echo csrf_meta(); ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .form-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            border: none;
        }
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .form-header i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .form-body {
            padding: 30px;
            background: white;
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .form-label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-submit:hover {
            transform: scale(1.02);
            color: white;
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .info-box {
            background: #f8f9ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .character-count {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded-3 mb-4">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="index.php">
                            <i class="bi bi-vote2"></i> Voting System
                        </a>
                        <div class="navbar-nav ms-auto">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> Home
                            </a>
                            <a class="nav-link" href="vote.php">
                                <i class="bi bi-check-circle"></i> Vote
                            </a>
                            <a class="nav-link active" href="nominate.php">
                                <i class="bi bi-person-plus"></i> Nominate
                            </a>
                            <a class="nav-link" href="results.php">
                                <i class="bi bi-bar-chart"></i> Results
                            </a>
                        </div>
                    </div>
                </nav>

                <div class="card form-card">
                    <div class="form-header">
                        <i class="bi bi-person-plus"></i>
                        <h2 class="mb-0">Submit a Nomination</h2>
                        <p class="mb-0 opacity-75">Nominate someone deserving for an award</p>
                    </div>
                    <div class="form-body">
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

                        <div class="info-box">
                            <i class="bi bi-info-circle text-primary"></i>
                            <strong>Guidelines:</strong>
                            <ul class="mb-0 mt-1">
                                <li>Ensure the nominee meets the category requirements</li>
                                <li>Provide a clear reason for the nomination</li>
                                <li>Duplicate nominations may be rejected</li>
                                <li>All nominations are subject to admin review</li>
                            </ul>
                        </div>

                        <form method="POST" id="nominationForm">
                            <?php echo csrf_field(); ?>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    Category <span class="required">*</span>
                                </label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($formData['category_id']) && $formData['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            Nominee Name <span class="required">*</span>
                                        </label>
                                        <input type="text" name="nominee_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($formData['nominee_name'] ?? ''); ?>"
                                               placeholder="Full name of nominee" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nominee Email</label>
                                        <input type="email" name="nominee_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($formData['nominee_email'] ?? ''); ?>"
                                               placeholder="nominee@example.com">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nominee Phone</label>
                                <input type="tel" name="nominee_phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['nominee_phone'] ?? ''); ?>"
                                       placeholder="+254 712 345 678">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    Reason for Nomination <span class="required">*</span>
                                </label>
                                <textarea name="nominee_description" class="form-control" rows="4" 
                                          placeholder="Describe why this person deserves the nomination..." 
                                          onkeyup="countChars(this)" required><?php 
                                    echo htmlspecialchars($formData['nominee_description'] ?? ''); 
                                ?></textarea>
                                <div class="character-count">
                                    <span id="charCount">0</span> characters (minimum 10)
                                </div>
                            </div>
                            
                            <hr>
                            <h6 class="mb-3"><i class="bi bi-person"></i> Your Information</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            Your Name <span class="required">*</span>
                                        </label>
                                        <input type="text" name="submitted_by" class="form-control" 
                                               value="<?php echo htmlspecialchars($formData['submitted_by'] ?? ''); ?>"
                                               placeholder="Your full name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            Your Email <span class="required">*</span>
                                        </label>
                                        <input type="email" name="submitter_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($formData['submitter_email'] ?? ''); ?>"
                                               placeholder="you@example.com" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-submit" id="submitBtn">
                                    <i class="bi bi-send"></i> Submit Nomination
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center text-white mt-4">
                    <small>
                        <i class="bi bi-shield-check"></i> Secured with CSRF Protection &amp; Rate Limiting
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function countChars(textarea) {
            const count = textarea.value.length;
            document.getElementById('charCount').textContent = count;
            
            const charCountEl = document.getElementById('charCount');
            if (count < 10) {
                charCountEl.style.color = '#dc3545';
            } else {
                charCountEl.style.color = '#28a745';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('textarea[name="nominee_description"]');
            if (textarea) {
                countChars(textarea);
            }
        });

        document.getElementById('nominationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';
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