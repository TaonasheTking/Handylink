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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = null;
$error = '';
$success = isset($_GET['posted']) && $_GET['posted'] === '1';
$categories = [];
$providers = [];

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $categories = $pdo->query('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name')->fetchAll();
    $providers = $pdo->query("SELECT pp.id AS profile_id, u.full_name, pp.business_name FROM provider_profiles pp JOIN users u ON u.id = pp.user_id WHERE u.is_active = 1 AND u.user_type IN ('provider','both') ORDER BY COALESCE(pp.business_name, u.full_name)")->fetchAll();
} catch (PDOException $e) {
    $error = 'Could not load HandyLink data. Check your database connection and run the supplied database update SQL.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
    $locationAddress = trim($_POST['location_address'] ?? '');
    $privateAddress = trim($_POST['private_address'] ?? '');
    $scheduleType = $_POST['schedule_type'] ?? 'flexible';
    $scheduledDate = trim($_POST['scheduled_date'] ?? '');
    $preferredTime = trim($_POST['preferred_time'] ?? '');
    $budgetLabel = $_POST['budget_label'] ?? '';
    $confirmationWindow = ($_POST['confirmation_window_hours'] ?? '') === 'none' ? null : filter_var($_POST['confirmation_window_hours'] ?? '', FILTER_VALIDATE_INT);
    $mode = ($_POST['booking_flow'] ?? 'job_post') === 'direct_hire' ? 'direct_hire' : 'job_post';
    $providerProfileId = filter_var($_POST['direct_provider_id'] ?? '', FILTER_VALIDATE_INT);

    $budgetRanges = [
        '$5 – $10' => [5.00, 10.00],
        '$10 – $25' => [10.00, 25.00],
        '$25 – $50' => [25.00, 50.00],
        '$50+' => [50.00, null]
    ];
    $allowedBudgets = array_keys($budgetRanges);
    $allowedSchedules = ['flexible', 'today', 'this_week'];
    $allowedConfirmationWindows = [1, 3, 6, 12, 24, 48];

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($title === '' || mb_strlen($title) > 150) {
        $error = 'Enter a title of up to 150 characters.';
    } elseif ($description === '') {
        $error = 'Describe the work you need done.';
    } elseif (!$categoryId || !in_array($categoryId, array_map('intval', array_column($categories, 'id')), true)) {
        $error = 'Choose a valid service category.';
    } elseif ($locationAddress === '' || mb_strlen($locationAddress) > 255) {
        $error = 'Enter the broad area where the job will take place (up to 255 characters).';
    } elseif (mb_strlen($privateAddress) > 255) {
        $error = 'The private address must be 255 characters or fewer.';
    } elseif (!in_array($scheduleType, $allowedSchedules, true)) {
        $error = 'Choose a valid schedule.';
    } elseif (!in_array($budgetLabel, $allowedBudgets, true)) {
        $error = 'Choose a budget range.';
    } elseif ($confirmationWindow !== null && !in_array($confirmationWindow, $allowedConfirmationWindows, true)) {
        $error = 'Choose a valid confirmation deadline.';
    } elseif ($scheduleType === 'today' && $preferredTime === '') {
        $error = 'Choose a preferred time for a job scheduled today.';
    } elseif ($scheduleType === 'this_week' && $scheduledDate === '') {
        $error = 'Choose a date for a job scheduled this week.';
    } else {
        $today = new DateTimeImmutable('today');
        $dateValue = null;
        $timeValue = null;
        if ($scheduleType === 'today') {
            $dateValue = $today->format('Y-m-d');
            $timeValue = $preferredTime;
        } elseif ($scheduleType === 'this_week') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $scheduledDate);
            $maxDate = $today->modify('+6 days');
            if (!$date || $date->format('Y-m-d') !== $scheduledDate || $date < $today || $date > $maxDate) {
                $error = 'Choose a date from today through the next 6 days.';
            } else {
                $dateValue = $date->format('Y-m-d');
                $timeValue = $preferredTime !== '' ? $preferredTime : null;
            }
        }

        if ($error === '' && $preferredTime !== '' && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $preferredTime) !== 1) {
            $error = 'Choose a valid preferred time.';
        }
        if ($error === '' && $mode === 'direct_hire') {
            $providerCheck = $pdo->prepare("SELECT pp.id FROM provider_profiles pp JOIN users u ON u.id = pp.user_id WHERE pp.id = ? AND u.is_active = 1 AND u.user_type IN ('provider','both') LIMIT 1");
            $providerCheck->execute([$providerProfileId ?: 0]);
            if (!$providerCheck->fetch()) {
                $error = 'Choose an available helper, or select “Open to anyone”.';
            }
        }

        if ($error === '') {
            // Store the public area separately from the private address.
            // Never include private_address in public job-board queries.
            [$budgetMin, $budgetMax] = $budgetRanges[$budgetLabel];
            $status = $mode === 'direct_hire' ? 'pending_accept' : 'open';
            $assignedProvider = $mode === 'direct_hire' ? (int)$providerProfileId : null;

            try {
                $insert = $pdo->prepare('INSERT INTO jobs (customer_id, category_id, booking_flow, assigned_provider_id, title, description, location_address, private_address, estimated_price, budget_min, budget_max, confirmation_window_hours, budget_label, schedule_type, scheduled_date, preferred_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([
                    (int)$_SESSION['user_id'], $categoryId, $mode, $assignedProvider,
                    $title, $description, $locationAddress,
                    $privateAddress !== '' ? $privateAddress : null,
                    $budgetMin, $budgetMin, $budgetMax, $confirmationWindow, $budgetLabel, $scheduleType,
                    $dateValue, $timeValue, $status
                ]);
                header('Location: post.php?posted=1');
                exit;
            } catch (PDOException $e) {
                $error = 'Your request could not be saved. Confirm that you have run the database update SQL, then try again.';
            }
        }
    }
}

function postValue(string $key, string $fallback = ''): string {
    return htmlspecialchars((string)($_POST[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Post a request — HandyLink</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/post.css">
</head>

<body>

  <div class="app-shell">

    <?php include "sidebar.php"; ?>

    <div class="main">

      <?php include "topBars.php"; ?>

      <main class="content">
        <div class="max-width-narrow">

          <a href="jobs.php" class="back-link"><i data-icon="arrow-left" data-size="16"></i> Back to jobs</a>

          <div id="postFormView" class="<?= $success ? 'hidden' : '' ?>">
            <span class="page-eyebrow">Ask your neighborhood</span>
            <h1 class="page-title">Post a request.</h1>
            <p class="page-sub">Describe what you need. We'll show it to nearby helpers who match your category and area.</p>

            <?php if ($error !== ''): ?>
              <div class="form-card post-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form id="postForm" class="post-form" method="post" action="post.php">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="booking_flow" id="bookingFlow" value="<?= postValue('booking_flow', 'job_post') ?>">
              <div class="main-col">

                <div class="form-card">
                  <label class="form-label">Who should see this?</label>
                  <div class="mode-grid">
                    <button type="button" data-mode="open" class="mode-btn <?= postValue('booking_flow', 'job_post') === 'job_post' ? 'active' : '' ?>">
                      <p class="title">Open to anyone</p>
                      <p class="desc">Nearby helpers apply, you choose who does it.</p>
                    </button>
                    <button type="button" data-mode="direct" class="mode-btn <?= postValue('booking_flow', 'job_post') === 'direct_hire' ? 'active' : '' ?>">
                      <p class="title">A specific helper</p>
                      <p class="desc">Send this straight to one person you already trust.</p>
                    </button>
                  </div>
                  <div id="directProviderList" class="direct-provider-list hidden">
                    <p class="label">Choose a helper</p>
                    <?php if (!$providers): ?>
                      <p class="form-hint">No helper profiles are available yet. Choose “Open to anyone” for now.</p>
                    <?php else: ?>
                      <?php foreach ($providers as $provider): ?>
                        <label class="provider-option">
                          <input type="radio" name="direct_provider_id" value="<?= (int)$provider['profile_id'] ?>" <?= (string)($_POST['direct_provider_id'] ?? '') === (string)$provider['profile_id'] ? 'checked' : '' ?>>
                          <span class="avatar"><?= htmlspecialchars(strtoupper(substr($provider['business_name'] ?: $provider['full_name'] ?: 'H', 0, 2)), ENT_QUOTES, 'UTF-8') ?></span>
                          <span class="name"><?= htmlspecialchars($provider['business_name'] ?: $provider['full_name'] ?: 'HandyLink helper', ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="form-card">
                  <label class="form-label">Title <span class="req">*</span></label>
                  <input required maxlength="150" name="title" class="form-input" value="<?= postValue('title') ?>" placeholder="e.g. Help paint one bedroom wall">

                  <div style="margin-top:20px;">
                    <label class="form-label">Description <span class="req">*</span></label>
                    <textarea required name="description" class="form-textarea" placeholder="What exactly needs doing? Any materials already on site?"><?= postValue('description') ?></textarea>
                  </div>

                  <div class="form-row">
                    <div>
                      <label class="form-label" for="categoryId">Category <span class="req">*</span></label>
                      <select required name="category_id" id="categoryId" class="form-select">
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $category): ?>
                          <option value="<?= (int)$category['id'] ?>" <?= (string)($_POST['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="form-label" for="locationAddress">Public area <span class="req">*</span></label>
                      <input required maxlength="255" name="location_address" id="locationAddress" class="form-input" value="<?= postValue('location_address') ?>" placeholder="e.g. Tynwald North, Westgate, Harare">
                    </div>
                  </div>
                  <div class="location-detect-row">
                    <button type="button" class="location-detect-btn" id="useCurrentLocation">Use my current location</button>
                    <p class="form-hint" id="locationStatus" role="status" aria-live="polite">Optional: OpenStreetMap uses your device's coordinates to suggest a broad area. Only the area text is saved with your request.</p>
                  </div>
                  <div style="margin-top:18px;">
                    <label class="form-label" for="privateAddress">Exact address <span style="font-weight:400;">(optional and private)</span></label>
                    <input maxlength="255" name="private_address" id="privateAddress" class="form-input" value="<?= postValue('private_address') ?>" placeholder="e.g. House number or street address">
                    <p class="form-hint">Helpers see only this broad area while browsing. The exact address is stored separately and should only be available to you and the assigned helper after the job is confirmed.</p>
                  </div>
                </div>

                <div class="form-card">
                  <label class="form-label">When should the job be done?</label>
                  <div class="urgency-chips" id="scheduleChoices">
                    <button type="button" data-schedule="flexible" class="urgency-chip <?= postValue('schedule_type', 'flexible') === 'flexible' ? 'active' : '' ?>">Flexible</button>
                    <button type="button" data-schedule="today" class="urgency-chip <?= postValue('schedule_type') === 'today' ? 'active' : '' ?>">Today</button>
                    <button type="button" data-schedule="this_week" class="urgency-chip <?= postValue('schedule_type') === 'this_week' ? 'active' : '' ?>">This week</button>
                  </div>
                  <input type="hidden" name="schedule_type" id="scheduleType" value="<?= postValue('schedule_type', 'flexible') ?>">
                  <div id="scheduleDateWrap" style="margin-top:18px;display:none;">
                    <label class="form-label" for="scheduledDate">Preferred date <span class="req">*</span></label>
                    <input type="date" name="scheduled_date" id="scheduledDate" class="form-input" value="<?= postValue('scheduled_date') ?>">
                  </div>
                  <div id="scheduleTimeWrap" style="margin-top:18px;display:none;">
                    <label class="form-label" for="preferredTime">Preferred time <span id="timeRequiredMark" class="req" style="display:none;">*</span></label>
                    <input type="time" name="preferred_time" id="preferredTime" class="form-input" value="<?= postValue('preferred_time') ?>">
                    <p class="form-hint" id="timeHint">Choose a time if you have one in mind.</p>
                  </div>
                  <div style="margin-top:20px;">
                    <label class="form-label" for="budgetLabel">Budget range <span class="req">*</span></label>
                    <select required name="budget_label" id="budgetLabel" class="form-select">
                      <?php foreach (['$5 – $10', '$10 – $25', '$25 – $50', '$50+'] as $budget): ?>
                        <option value="<?= htmlspecialchars($budget, ENT_QUOTES, 'UTF-8') ?>" <?= postValue('budget_label', '$5 – $10') === $budget ? 'selected' : '' ?>><?= htmlspecialchars($budget, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="margin-top:20px;">
                    <label class="form-label" for="confirmationWindow">Time to confirm a final agreement <span class="req">*</span></label>
                    <select required name="confirmation_window_hours" id="confirmationWindow" class="form-select">
                      <?php $selectedWindow = (string)($_POST['confirmation_window_hours'] ?? '12'); ?>
                      <option value="">Choose a deadline</option>
                      <?php foreach ([1, 3, 6, 12, 24, 48] as $hours): ?>
                        <option value="<?= $hours ?>" <?= $selectedWindow === (string)$hours ? 'selected' : '' ?>><?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?></option>
                      <?php endforeach; ?>
                      <option value="none" <?= $selectedWindow === 'none' ? 'selected' : '' ?>>No deadline</option>
                    </select>
                    <p class="form-hint">The timer starts only after you and the selected helper mark the complete proposal ready for confirmation.</p>
                  </div>
                </div>

              </div>

              <aside class="aside-col">
                <div class="tips-card">
                  <div class="tips-head"><i data-icon="sparkles" data-size="16"></i><span class="label">Before you post</span></div>
                  <ul class="tips-list">
                    <li>Clear titles get matched faster.</li>
                    <li>Mention if materials or tools are already on site.</li>
                    <li>Your phone number stays private until a job is confirmed.</li>
                  </ul>
                </div>
                <button type="submit" class="submit-btn">Post request <i data-icon="arrow-right" data-size="16"></i></button>
              </aside>
            </form>
          </div>

          <div id="postSuccessView" class="success-view <?= $success ? '' : 'hidden' ?>">
            <span class="success-icon"><i data-icon="check" data-size="28"></i></span>
            <h2 class="success-title">Your request has been saved.</h2>
            <p class="success-detail" id="postSuccessDetail">Your request has been saved. Open requests will appear to helpers in the public area you provided.</p>
            <div class="success-actions">
              <a href="jobs.php" class="btn btn-primary">View job board <i data-icon="arrow-right" data-size="15"></i></a>
              <a href="index.php" class="btn btn-card">Back to overview</a>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script src="js/icons.js"></script>
  <script src="js/shell.js"></script>
  <script src="js/post.js"></script>
</body>

</html>
