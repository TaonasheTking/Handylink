<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: auth.php?mode=login');
  exit;
}
include "connection.php";
date_default_timezone_set('Africa/Harare');
// Count jobs visible on the open job board.
$stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'open' AND booking_flow = 'job_post'");
$openJobsCount = (int) $stmt->fetchColumn();

// Load public job cards from the database; exact addresses are never selected here.
$recentJobsStmt = $pdo->query("SELECT j.id, j.title, j.description, j.location_address, j.estimated_price, j.budget_label, j.schedule_type, j.scheduled_date, j.preferred_time, sc.name AS category_name, u.full_name AS customer_name
  FROM jobs j
  JOIN service_categories sc ON sc.id = j.category_id
  JOIN users u ON u.id = j.customer_id
  WHERE j.status = 'open' AND j.booking_flow = 'job_post'
  ORDER BY j.created_at DESC
  LIMIT 6");
$recentJobs = $recentJobsStmt->fetchAll();

// Count active users who can provide services.
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE user_type IN ('provider', 'both')
      AND is_active = 1
");

$helpersCount = (int) $stmt->fetchColumn();

// Get the logged-in user's credit balance.
$userId = (int) $_SESSION['user_id'];
$creditsBalance = 0.00;

$stmt = $pdo->prepare("
    SELECT w.credit_balance
    FROM wallets w
    JOIN provider_profiles pp ON pp.id = w.provider_id
    WHERE pp.user_id = ?
");

$stmt->execute([$userId]);
$wallet = $stmt->fetch();

if ($wallet) {
  $creditsBalance = (float) $wallet['credit_balance'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Overview — HandyLink</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <div class="app-shell">

    <?php
    include "sidebar.php";
    ?>
    <div class="main">

      <?php include "topBars.php"; ?>

      <main class="content">
        <div class="max-width">

          <section class="hero">
            <span class="hero-ring-1"></span>
            <span class="hero-ring-2"></span>
            <div class="hero-inner">
              <div class="hero-eyebrow">
                <span class="ico"><i data-icon="sparkles" data-size="14"></i></span>
                <span>Your neighborhood, in motion</span>
              </div>
              <h1>A little help goes<br>a long way, <span class="accent">today.</span></h1>
              <p class="hero-sub">
                Welcome,
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Neighbor', ENT_QUOTES, 'UTF-8') ?>!
                There are people nearby who can use what you know — and a few things you do not have to do alone.
              </p>
              <div class="hero-actions">
                <a href="jobs.php" class="btn btn-primary">Find something to help with <i data-icon="arrow-right" data-size="16"></i></a>
                <a href="post.php" class="btn btn-outline">Post a request <i data-icon="plus" data-size="16"></i></a>
              </div>
            </div>
          </section>

          <section class="stats-grid">
            <div class="stat-card">
              <div class="stat-card-head"><span class="stat-icon tone-a"><i data-icon="briefcase-business" data-size="19"></i></span><span class="stat-live">Live</span></div>
              <p class="stat-value"><?= $openJobsCount ?></p>
              <p class="stat-label">Open jobs available</p>
              <p class="stat-detail">Waiting for a capable neighbor</p>
            </div>
            <div class="stat-card">
              <div class="stat-card-head"><span class="stat-icon tone-b"><i data-icon="user-round" data-size="19"></i></span><span class="stat-live">Live</span></div>
              <p class="stat-value"><?= $helpersCount ?></p>
              <p class="stat-label">Registered helpers</p>
              <p class="stat-detail">People ready to lend a hand</p>
            </div>
            <div class="stat-card">
              <div class="stat-card-head"><span class="stat-icon tone-c"><i data-icon="coins" data-size="19"></i></span><span class="stat-live">Live</span></div>
              <p class="stat-value"><?= number_format($creditsBalance, 2) ?></p>
              <p class="stat-label">Credits to spend</p>
              <p class="stat-detail">Enough for your next unlock</p>
            </div>
          </section>

          <div class="content-grid">
            <section>
              <div class="section-head">
                <div>
                  <span class="section-eyebrow">Fresh on the block</span>
                  <h2 class="section-title">Jobs you might like</h2>
                  <p class="section-sub">A few ways to show up this week.</p>
                </div>
                <a href="jobs.php" class="section-link">See all jobs <i data-icon="arrow-right" data-size="15"></i></a>
              </div>

              <div class="job-grid">
                <?php foreach ($recentJobs as $job):
                  $schedule = 'Flexible';
                  if ($job['schedule_type'] === 'today') {
                    $schedule = 'Today' . (!empty($job['preferred_time']) ? ' · ' . date('g:i A', strtotime($job['preferred_time'])) : '');
                  } elseif ($job['schedule_type'] === 'this_week') {
                    $schedule = 'This week' . (!empty($job['scheduled_date']) ? ' · ' . date('D, j M', strtotime($job['scheduled_date'])) : '') . (!empty($job['preferred_time']) ? ' · ' . date('g:i A', strtotime($job['preferred_time'])) : '');
                  }
                  $poster = trim((string)$job['customer_name']) ?: 'HandyLink member';
                  $budget = $job['budget_label'] ?: ('$' . number_format((float)$job['estimated_price'], 2));
                ?>
                  <a href="job-detail.php?id=<?= (int)$job['id'] ?>" class="job-card">
                    <div class="job-media paper-grid">
                      <div class="overlay"></div>
                      <div class="job-tags"><span class="job-tag"><?= htmlspecialchars($schedule, ENT_QUOTES, 'UTF-8') ?></span><span class="job-tag"><?= htmlspecialchars($job['category_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
                      <div class="job-location"><i data-icon="map-pin" data-size="12"></i><?= htmlspecialchars($job['location_address'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="job-body">
                      <h3 class="job-title"><?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                      <p class="job-desc"><?= htmlspecialchars(mb_strimwidth((string)$job['description'], 0, 135, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></p>
                      <div class="job-footer">
                        <div class="job-poster"><span class="avatar"><?= htmlspecialchars(strtoupper(substr($poster, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span><span class="name"><?= htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') ?></span></div>
                        <span class="job-budget"><?= htmlspecialchars($budget, ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
                <?php if (!$recentJobs): ?><p class="section-sub">No open jobs yet. New neighborhood requests will appear here.</p><?php endif; ?>
              </div>

              <a href="jobs.php" class="btn btn-card jobs-mobile-browse">Browse all jobs <i data-icon="arrow-right" data-size="15"></i></a>
            </section>

            <aside>
              <div class="section-head" style="margin-bottom:20px;">
                <div>
                  <span class="section-eyebrow">Your rhythm</span>
                  <h2 class="section-title">Recent activity</h2>
                </div>
              </div>

              <div class="activity-card">
                <div class="activity-list">
                  <div class="activity-item">
                    <span class="rail"></span>
                    <span class="activity-icon tone-a"><i data-icon="wallet-cards" data-size="14"></i></span>
                    <div>
                      <p class="activity-title">Credits added</p>
                      <p class="activity-desc">You purchased the 10-credit bundle</p>
                      <p class="activity-time">Yesterday</p>
                    </div>
                  </div>
                  <div class="activity-item">
                    <span class="rail"></span>
                    <span class="activity-icon tone-b"><i data-icon="hand-helping" data-size="14"></i></span>
                    <div>
                      <p class="activity-title">Job accepted</p>
                      <p class="activity-desc">You accepted Sara's grocery pickup</p>
                      <p class="activity-time">2 days ago</p>
                    </div>
                  </div>
                  <div class="activity-item">
                    <span class="activity-icon tone-c"><i data-icon="briefcase-business" data-size="14"></i></span>
                    <div>
                      <p class="activity-title">New review</p>
                      <p class="activity-desc">Faisal left you a 5-star review</p>
                      <p class="activity-time">3 days ago</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="promise-card">
                <div class="promise-head"><i data-icon="wallet-cards" data-size="18"></i><span class="label">The HandyLink promise</span></div>
                <p class="promise-title">Credits move. Trust stays.</p>
                <p class="promise-body">Every completed job keeps your corner of town a little more connected.</p>
              </div>
            </aside>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script src="js/icons.js"></script>
  <script src="js/shell.js"></script>
</body>

</html>
