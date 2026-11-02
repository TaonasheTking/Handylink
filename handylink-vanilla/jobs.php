<?php
session_start();
date_default_timezone_set('Africa/Harare');
if (empty($_SESSION['user_id'])) {
    header('Location: auth.php?mode=login');
    exit;
}
const DB_HOST = '127.0.0.1';
const DB_NAME = "handymen's";
const DB_USER = 'root';
const DB_PASS = '';
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function scheduleLabel(array $job): string {
    if (($job['schedule_type'] ?? 'flexible') === 'today') return 'Today' . (!empty($job['preferred_time']) ? ' · ' . date('g:i A', strtotime($job['preferred_time'])) : '');
    if (($job['schedule_type'] ?? '') === 'this_week') return 'This week' . (!empty($job['scheduled_date']) ? ' · ' . date('D, j M', strtotime($job['scheduled_date'])) : '');
    return 'Flexible';
}
$jobs = [];
$categories = [];
$error = '';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $categories = $pdo->query("SELECT DISTINCT sc.name FROM service_categories sc JOIN jobs j ON j.category_id = sc.id WHERE j.status = 'open' ORDER BY sc.name")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT j.id, j.title, j.description, j.location_address, j.estimated_price, j.budget_label, j.schedule_type, j.scheduled_date, j.preferred_time, j.created_at, sc.name AS category_name, u.full_name AS customer_name FROM jobs j JOIN service_categories sc ON sc.id = j.category_id JOIN users u ON u.id = j.customer_id WHERE j.status = 'open' AND j.booking_flow = 'job_post' ORDER BY j.created_at DESC");
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Could not load jobs. Check your database connection and make sure the HandyLink database update has been run.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find a job — HandyLink</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <?php include 'topBars.php'; ?>
    <main class="content">
      <div class="max-width">
        <div class="page-head-row">
          <div>
            <span class="page-eyebrow">The neighborhood board</span>
            <h1 class="page-title">Find your next<br>good deed.</h1>
            <p class="page-sub">Browse requests posted by people in your community. Only the broad area is shown publicly.</p>
          </div>
          <a href="post.php" class="btn btn-primary">Post a request <i data-icon="arrow-right" data-size="16"></i></a>
        </div>
        <?php if ($error !== ''): ?>
          <div class="form-card" role="alert" style="margin-bottom:16px;color:#a52c28;"><?= h($error) ?></div>
        <?php endif; ?>
        <div class="filter-bar">
          <label class="search-field"><i data-icon="search" data-size="17"></i><input id="jobSearchInput" placeholder="Search jobs, skills, or places" aria-label="Search jobs, skills, or places"></label>
          <button type="button" id="jobSearchClear" class="filter-clear-btn"><i data-icon="sliders-horizontal" data-size="16"></i> Clear search</button>
        </div>
        <div class="category-chip-row" id="jobCategoryChips">
          <button type="button" data-category-filter="All work" class="category-chip active">All work</button>
          <?php foreach ($categories as $category): ?>
            <button type="button" data-category-filter="<?= h($category) ?>" class="category-chip"><?= h($category) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="results-head">
          <div><h2 id="jobsCountHeading" class="section-title"><?= count($jobs) ?> open <?= count($jobs) === 1 ? 'job' : 'jobs' ?></h2><p id="jobsCountDetail" class="section-sub">Newest requests appear first.</p></div>
          <div class="results-sort"><i data-icon="filter" data-size="14"></i> Newest first</div>
        </div>
        <div class="job-grid" id="jobsGrid">
          <?php foreach ($jobs as $job):
            $category = (string)$job['category_name'];
            $searchText = strtolower($job['title'] . ' ' . $job['description'] . ' ' . $job['location_address'] . ' ' . $category);
            $poster = trim((string)$job['customer_name']) ?: 'HandyLink member';
            $initials = strtoupper(substr($poster, 0, 1));
          ?>
          <div class="job-card-wrap" data-category="<?= h($category) ?>" data-search="<?= h($searchText) ?>">
            <a href="job-detail.php?id=<?= (int)$job['id'] ?>" class="job-card">
              <div class="job-media paper-grid"><div class="overlay"></div><div class="job-tags"><span class="job-tag"><?= h(scheduleLabel($job)) ?></span><span class="job-tag"><?= h($category) ?></span></div><div class="job-location"><i data-icon="map-pin" data-size="12"></i><?= h($job['location_address']) ?></div></div>
              <div class="job-body"><h3 class="job-title"><?= h($job['title']) ?></h3><p class="job-desc"><?= h(mb_strimwidth((string)$job['description'], 0, 135, '…', 'UTF-8')) ?></p><div class="job-footer"><div class="job-poster"><span class="avatar"><?= h($initials) ?></span><span class="name"><?= h($poster) ?></span></div><span class="job-budget"><?= h($job['budget_label'] ?: ('$' . number_format((float)$job['estimated_price'], 2))) ?></span></div></div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="jobsEmptyState" class="empty-state <?= $jobs ? 'hidden' : '' ?>">
          <span class="empty-state-icon"><i data-icon="circle-help" data-size="22"></i></span>
          <h3><?= $jobs ? 'No jobs match that search' : 'No open jobs yet' ?></h3>
          <p><?= $jobs ? 'Try a broader phrase or another category.' : 'New requests will appear here when customers post them.' ?></p>
          <button type="button" id="jobsResetBtn" class="btn btn-card">Reset filters</button>
        </div>
        <div class="board-footer-note"><span class="category-chip active" style="cursor:default;">Open board</span><span>Exact addresses are not displayed on the public board.</span></div>
      </div>
    </main>
  </div>
</div>
<script src="js/icons.js"></script><script src="js/shell.js"></script><script src="js/jobs.js"></script>
</body>
</html>
