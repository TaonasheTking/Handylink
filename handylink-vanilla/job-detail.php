<?php
session_start();
date_default_timezone_set('Africa/Harare');
if (empty($_SESSION['user_id'])) { header('Location: auth.php?mode=login'); exit; }
const DB_HOST = '127.0.0.1';
const DB_NAME = "handymen's";
const DB_USER = 'root';
const DB_PASS = '';
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$jobId || $jobId < 1) { http_response_code(404); exit('Job not found.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    header('Location: job-negotiation.php?job_id=' . $jobId);
    exit;
}
$error = '';
$notice = '';
$job = null;
$pdo = null;
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $error = 'Your session expired. Refresh the page and try again.';
        } else {
            $message = trim($_POST['application_message'] ?? '');
            if (mb_strlen($message) > 1000) {
                $error = 'Your note must be 1,000 characters or fewer.';
            } else {
                $pdo->beginTransaction();
                $check = $pdo->prepare("SELECT j.id, j.customer_id, j.status, j.booking_flow FROM jobs j WHERE j.id = ? LIMIT 1 FOR UPDATE");
                $check->execute([$jobId]);
                $target = $check->fetch();
                $account = $pdo->prepare("SELECT user_type, is_active FROM users WHERE id = ? LIMIT 1");
                $account->execute([(int)$_SESSION['user_id']]);
                $currentAccount = $account->fetch();
                if (!$target || $target['status'] !== 'open' || $target['booking_flow'] !== 'job_post') {
                    $error = 'This job is no longer open for applications.';
                } elseif ((int)$target['customer_id'] === (int)$_SESSION['user_id']) {
                    $error = 'You cannot apply to your own job.';
                } elseif (!$currentAccount || (int)$currentAccount['is_active'] !== 1 || !in_array($currentAccount['user_type'], ['provider', 'both'], true)) {
                    $error = 'This account is set up to find help. Change it to “Find and offer help” or “Offer help” in your profile to express interest.';
                } else {
                    // Some older signups did not create a helper profile. Create it on demand
                    // so a provider-capable account can express interest from any browser.
                    $profileStmt = $pdo->prepare("SELECT id FROM provider_profiles WHERE user_id = ? ORDER BY id LIMIT 1");
                    $profileStmt->execute([(int)$_SESSION['user_id']]);
                    $providerProfileId = (int)($profileStmt->fetchColumn() ?: 0);
                    if (!$providerProfileId) {
                        $createProfile = $pdo->prepare("INSERT INTO provider_profiles (user_id, bio, business_name, is_business) VALUES (?, NULL, NULL, 0)");
                        $createProfile->execute([(int)$_SESSION['user_id']]);
                        $providerProfileId = (int)$pdo->lastInsertId();
                    }
                    $wallet = $pdo->prepare('INSERT IGNORE INTO wallets (provider_id, credit_balance) VALUES (?, 0)');
                    $wallet->execute([$providerProfileId]);

                    $existing = $pdo->prepare("SELECT id FROM job_applications WHERE job_id = ? AND provider_id = ? LIMIT 1");
                    $existing->execute([$jobId, $providerProfileId]);
                    if ($existing->fetchColumn()) {
                        $notice = 'You have already expressed interest in this job.';
                    } else {
                        $insert = $pdo->prepare("INSERT INTO job_applications (job_id, provider_id, message, status) VALUES (?, ?, ?, 'pending')");
                        $insert->execute([$jobId, $providerProfileId, $message !== '' ? $message : null]);
                        $notice = 'Your interest has been sent to the customer. No credits have been deducted.';
                    }
                }
                if ($pdo->inTransaction()) {
                    if ($error === '') $pdo->commit(); else $pdo->rollBack();
                }
            }
        }
    }
    $stmt = $pdo->prepare("SELECT j.id, j.customer_id, j.category_id, j.booking_flow, j.assigned_provider_id, j.title, j.description, j.location_address, j.estimated_price, j.budget_label, j.schedule_type, j.scheduled_date, j.preferred_time, j.status, j.created_at, sc.name AS category_name, u.full_name AS customer_name FROM jobs j JOIN service_categories sc ON sc.id = j.category_id JOIN users u ON u.id = j.customer_id WHERE j.id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Could not load this job. Check your database connection and run the database update SQL.';
}
if (!$job) { http_response_code(404); exit('Job not found.'); }
$currentUserId = (int)$_SESSION['user_id'];
$ownJob = (int)$job['customer_id'] === $currentUserId;
$providerStmt = $pdo->prepare('SELECT id FROM provider_profiles WHERE user_id = ? LIMIT 1');
$providerStmt->execute([$currentUserId]);
$currentProviderProfileId = (int)($providerStmt->fetchColumn() ?: 0);
$userTypeStmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
$userTypeStmt->execute([$currentUserId]);
$currentUserType = $userTypeStmt->fetchColumn() ?: '';
$isAssignedHelper = $currentProviderProfileId > 0 && (int)$job['assigned_provider_id'] === $currentProviderProfileId;
$canSeePrivateAddress = $ownJob || ($job['status'] === 'confirmed' && $isAssignedHelper);
$job['private_address'] = null;
if ($canSeePrivateAddress) {
    $privateAddressStmt = $pdo->prepare('SELECT private_address FROM jobs WHERE id = ? LIMIT 1');
    $privateAddressStmt->execute([$jobId]);
    $job['private_address'] = $privateAddressStmt->fetchColumn() ?: null;
}
$canApply = $job['status'] === 'open' && $job['booking_flow'] === 'job_post' && !$ownJob && in_array($currentUserType, ['provider', 'both'], true);
$schedule = 'Flexible';
if ($job['schedule_type'] === 'today') $schedule = 'Today' . (!empty($job['preferred_time']) ? ' · ' . date('g:i A', strtotime($job['preferred_time'])) : '');
elseif ($job['schedule_type'] === 'this_week') $schedule = 'This week' . (!empty($job['scheduled_date']) ? ' · ' . date('D, j M', strtotime($job['scheduled_date'])) : '') . (!empty($job['preferred_time']) ? ' · ' . date('g:i A', strtotime($job['preferred_time'])) : '');
$posterName = trim((string)$job['customer_name']) ?: 'HandyLink member';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= h($job['title']) ?> — HandyLink</title><link rel="stylesheet" href="css/style.css"></head>
<body>
<div class="app-shell">
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <?php include 'topBars.php'; ?>
    <main class="content"><div class="max-width-narrow">
      <a href="jobs.php" class="back-link"><i data-icon="arrow-left" data-size="16"></i> Back to jobs</a>
      <?php if ($error !== ''): ?><div class="form-card" role="alert" style="margin-bottom:16px;color:#a52c28;"><?= h($error) ?></div><?php endif; ?>
      <?php if ($notice !== ''): ?><div class="form-card" role="status" style="margin-bottom:16px;"><?= h($notice) ?></div><?php endif; ?>
      <div class="detail-layout"><div>
        <div class="detail-media paper-grid"><div class="overlay"></div><div class="tags"><span class="job-tag"><?= h($schedule) ?></span><span class="job-tag"><?= h($job['category_name']) ?></span></div><div class="title-wrap"><h1><?= h($job['title']) ?></h1><p class="loc"><i data-icon="map-pin" data-size="14"></i><?= h($job['location_address']) ?></p></div></div>
        <div class="detail-card"><h2>About this job</h2><p class="body"><?= nl2br(h($job['description'])) ?></p><div class="detail-pills"><span class="specialty-pill"><?= h($job['category_name']) ?></span><span class="specialty-pill"><?= h($schedule) ?></span><span class="specialty-pill"><?= h(ucwords(str_replace('_', ' ', $job['status']))) ?></span></div></div>
        <div class="poster-row"><span class="avatar"><?= h(strtoupper(substr($posterName, 0, 1))) ?></span><div><p class="name"><?= h($posterName) ?></p><p class="meta">Posted <?= h(date('j M Y', strtotime($job['created_at']))) ?></p></div></div>
        <?php if ($canSeePrivateAddress && !empty($job['private_address'])): ?><div class="detail-card"><h2>Private address</h2><p class="body"><?= h($job['private_address']) ?></p><p class="meta">Visible only to the customer and assigned helper after confirmation.</p></div><?php elseif (!empty($job['private_address'])): ?><div class="detail-card"><h2><i data-icon="lock-keyhole" data-size="15"></i> Exact address protected</h2><p class="body">The exact address is hidden until this job is confirmed with its assigned helper.</p></div><?php endif; ?>
      </div>
      <aside class="aside-col"><div class="unlock-card"><p class="label">Customer's budget</p><p class="credits" style="font-size:28px;"><?= h($job['budget_label'] ?: ('$' . number_format((float)$job['estimated_price'], 2))) ?></p><p class="budget-note">Public area: <?= h($job['location_address']) ?></p>
        <?php if ($canApply): ?><a href="job-negotiation.php?job_id=<?= (int)$job['id'] ?>" class="accept-btn">View job and send an offer</a><p class="privacy-note"><i data-icon="lock-keyhole" data-size="13"></i>Offers and negotiations are private. No credits are charged in this phase.</p>
        <?php elseif ($ownJob): ?><p class="body">This is your request.</p><a class="btn btn-primary" href="job-negotiation.php?job_id=<?= (int)$job['id'] ?>">View helper offers</a>
        <?php elseif ($job['status'] === 'pending_accept' && $isAssignedHelper): ?><p class="body">This request was sent directly to you. Confirm the details with the customer before the exact address is shared.</p>
        <?php elseif ($job['status'] !== 'open'): ?><p class="body">This job is <?= h(str_replace('_', ' ', $job['status'])) ?> and is not accepting new applications.</p>
        <?php elseif (!$ownJob && !in_array($currentUserType, ['provider', 'both'], true)): ?><p class="body">Your account is currently set to “Find help.” <a href="profile.php">Change your HandyLink role</a> to “Find and offer help” or “Offer help” to express interest.</p>
        <?php else: ?><p class="body">Create a helper profile before expressing interest in jobs.</p><?php endif; ?>
      </div><div class="covered-card"><div class="head"><i data-icon="shield-check" data-size="15"></i><strong>Location privacy</strong></div><p>Only the broad area is shown publicly. The exact address is revealed only to the customer and assigned helper after confirmation.</p></div></aside></div>
    </div></main>
  </div>
</div>
<script src="js/icons.js"></script><script src="js/shell.js"></script>
</body></html>
