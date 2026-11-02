<?php
// HandyLink login + signup page. Update DB_NAME below if your database has another name.
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = "handymen's";
const DB_USER = 'root';
const DB_PASS = '';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$mode = (isset($_GET['mode']) && $_GET['mode'] === 'signup') ? 'signup' : 'login';

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
} catch (PDOException $e) {
  $pdo = null;
  $error = 'Could not connect to the database. Check the database name and make sure MySQL is running in XAMPP.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
  $mode = ($_POST['action'] ?? 'login') === 'signup' ? 'signup' : 'login';

  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $error = 'Your session expired. Refresh the page and try again.';
  } elseif ($mode === 'signup') {
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $type = $_POST['user_type'] ?? 'both';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || mb_strlen($name) > 100) {
      $error = 'Enter your name (up to 100 characters).';
    } elseif (!preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
      $error = 'Enter a valid phone number.';
    } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
      $error = 'Enter a valid email address, or leave it blank.';
    } elseif (!in_array($type, ['customer', 'provider', 'both'], true)) {
      $error = 'Choose a valid account type.';
    } elseif (strlen($password) < 8) {
      $error = 'Your password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
      $error = 'The passwords do not match.';
    } else {
      $check = $pdo->prepare('SELECT id FROM users WHERE phone_number = ? LIMIT 1');
      $check->execute([$phone]);
      if ($check->fetch()) {
        $error = 'An account with that phone number already exists. Try logging in.';
      } else {
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO users (phone_number, full_name, email, password_hash, user_type, is_phone_verified, is_active) VALUES (?, ?, ?, ?, ?, 0, 1)');
        $insert->execute([$phone, $name, $email !== '' ? $email : null, password_hash($password, PASSWORD_DEFAULT), $type]);
        $userId = (int)$pdo->lastInsertId();

        // Wallets belong to helper profiles, not directly to user IDs.
        if (in_array($type, ['provider', 'both'], true)) {
          $profile = $pdo->prepare('INSERT INTO provider_profiles (user_id, bio, business_name, is_business) VALUES (?, NULL, NULL, 0)');
          $profile->execute([$userId]);
          $providerProfileId = (int)$pdo->lastInsertId();
          $wallet = $pdo->prepare('INSERT INTO wallets (provider_id, credit_balance) VALUES (?, 0)');
          $wallet->execute([$providerProfileId]);
        }
        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_type'] = $type;
        header('Location: index.php');
        exit;
      }
    }
  } else {
    $phone = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($phone === '' || $password === '') {
      $error = 'Enter your phone number and password.';
    } else {
      $stmt = $pdo->prepare('SELECT id, full_name, phone_number, password_hash, user_type, is_active FROM users WHERE phone_number = ? LIMIT 1');
      $stmt->execute([$phone]);
      $user = $stmt->fetch();

      if (!$user || (int)$user['is_active'] !== 1 || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        $error = 'Those login details are incorrect.';
      } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'] ?: $user['phone_number'];
        $_SESSION['user_type'] = $user['user_type'];
        header('Location: index.php');
        exit;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $mode === 'signup' ? 'Create account' : 'Log in' ?> — HandyLink</title>
  <style>
    :root {
      --blue: #1747a6;
      --blue-dark: #10377f;
      --yellow: #ffd34e;
      --ink: #17243b;
      --muted: #69758a;
      --line: #e1e7f0;
      --bg: #f4f7fc
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
      background: var(--bg);
      color: var(--ink);
      display: grid;
      place-items: center;
      padding: 24px
    }

    .layout {
      width: min(980px, 100%);
      min-height: 620px;
      background: #fff;
      border: 1px solid #e7ecf4;
      border-radius: 26px;
      overflow: hidden;
      display: grid;
      grid-template-columns: .92fr 1.08fr;
      box-shadow: 0 24px 70px #1b37641a
    }

    .brand-panel {
      position: relative;
      overflow: hidden;
      padding: 46px 40px;
      background: linear-gradient(145deg, #1747a6, #0f347a);
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: space-between
    }

    .brand-panel:before,
    .brand-panel:after {
      content: "";
      position: absolute;
      border: 1px solid #ffffff25;
      border-radius: 50%;
      width: 360px;
      height: 360px;
      right: -170px;
      top: 120px
    }

    .brand-panel:after {
      width: 260px;
      height: 260px;
      right: -120px;
      top: 170px
    }

    .brand,
    .brand-copy,
    .brand-foot {
      position: relative;
      z-index: 1
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 11px;
      font-size: 21px;
      font-weight: 800;
      letter-spacing: -.7px
    }

    .logo {
      width: 38px;
      height: 38px;
      display: grid;
      place-items: center;
      background: var(--yellow);
      color: #173d87;
      border-radius: 12px;
      font-size: 22px;
      font-weight: 900
    }

    .brand-copy {
      margin: auto 0
    }

    .eyebrow {
      text-transform: uppercase;
      letter-spacing: 2px;
      font-size: 11px;
      font-weight: 800;
      color: #ffdf72
    }

    .brand-copy h1 {
      font-size: clamp(32px, 4vw, 46px);
      line-height: 1.1;
      letter-spacing: -1.7px;
      margin: 17px 0
    }

    .brand-copy p {
      color: #dce7ff;
      line-height: 1.75;
      font-size: 15px;
      max-width: 310px
    }

    .brand-foot {
      font-size: 12px;
      color: #c6d6fa
    }

    .form-panel {
      padding: 48px clamp(25px, 5vw, 64px);
      display: flex;
      flex-direction: column;
      justify-content: center
    }

    .mobile-brand {
      display: none
    }

    .form-heading {
      margin-bottom: 27px
    }

    .form-heading h2 {
      font-size: 29px;
      letter-spacing: -1px;
      margin: 0 0 8px
    }

    .form-heading p {
      color: var(--muted);
      margin: 0;
      font-size: 14px;
      line-height: 1.5
    }

    .tabs {
      display: flex;
      padding: 5px;
      background: #f1f4fa;
      border-radius: 12px;
      margin-bottom: 24px
    }

    .tabs a {
      flex: 1;
      text-align: center;
      text-decoration: none;
      padding: 11px 8px;
      border-radius: 9px;
      font-size: 13px;
      font-weight: 750;
      color: #6b7890
    }

    .tabs a.active {
      background: #fff;
      color: var(--blue);
      box-shadow: 0 2px 7px #1b376410
    }

    .field {
      margin-bottom: 16px
    }

    .field label {
      display: block;
      font-size: 12px;
      font-weight: 750;
      margin-bottom: 7px;
      color: #34425a
    }

    .field input,
    .field select {
      width: 100%;
      border: 1px solid var(--line);
      background: #fff;
      border-radius: 10px;
      padding: 13px 14px;
      font: inherit;
      font-size: 14px;
      color: var(--ink);
      outline: none;
      transition: border .2s, box-shadow .2s
    }

    .field input:focus,
    .field select:focus {
      border-color: #5b85db;
      box-shadow: 0 0 0 3px #1747a612
    }

    .field input::placeholder {
      color: #a4adbc
    }

    .row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    .submit {
      border: 0;
      width: 100%;
      border-radius: 11px;
      background: var(--blue);
      color: #fff;
      font-weight: 800;
      font-size: 14px;
      padding: 14px;
      cursor: pointer;
      margin-top: 4px;
      box-shadow: 0 8px 18px #1747a626
    }

    .submit:hover {
      background: var(--blue-dark)
    }

    .notice {
      font-size: 13px;
      line-height: 1.5;
      border-radius: 10px;
      padding: 12px 13px;
      margin: 0 0 17px
    }

    .error {
      background: #fff0ef;
      color: #a52c28;
      border: 1px solid #ffd5d2
    }

    .note {
      background: #edf8f0;
      color: #246a3c;
      border: 1px solid #d1eed9
    }

    .fine-print {
      text-align: center;
      color: #8a94a5;
      font-size: 11px;
      line-height: 1.6;
      margin: 18px 0 0
    }

    .fine-print a {
      color: var(--blue);
      font-weight: 700;
      text-decoration: none
    }

    @media(max-width:720px) {
      body {
        padding: 12px
      }

      .layout {
        grid-template-columns: 1fr;
        min-height: 0;
        border-radius: 20px
      }

      .brand-panel {
        display: none
      }

      .form-panel {
        padding: 28px 23px
      }

      .mobile-brand {
        display: flex;
        align-items: center;
        gap: 9px;
        font-weight: 850;
        color: var(--blue);
        margin-bottom: 28px
      }

      .mobile-brand .logo {
        background: var(--blue);
        color: #fff
      }

      .form-heading h2 {
        font-size: 26px
      }
    }

    @media(max-width:390px) {
      .row {
        grid-template-columns: 1fr;
        gap: 0
      }
    }
  </style>
</head>

<body>
  <div class="layout">
    <aside class="brand-panel">
      <div class="brand"><span class="logo">h</span> HandyLink</div>
      <div class="brand-copy">
        <div class="eyebrow">Your neighborhood, in motion</div>
        <h1>A little help<br>goes a long way.</h1>
        <p>Find trusted local help, share your skills, and make good things happen right in your neighborhood.</p>
      </div>
      <div class="brand-foot">Built on community. Powered by trust.</div>
    </aside>
    <main class="form-panel">
      <div class="mobile-brand"><span class="logo">h</span> HandyLink</div>
      <div class="form-heading">
        <h2><?= $mode === 'signup' ? 'Join your community' : 'Welcome back' ?></h2>
        <p><?= $mode === 'signup' ? 'Create your HandyLink account in a few steps.' : 'Log in to continue with HandyLink.' ?></p>
      </div>
      <nav class="tabs" aria-label="Account options">
        <a class="<?= $mode === 'login' ? 'active' : '' ?>" href="auth.php?mode=login">Log in</a>
        <a class="<?= $mode === 'signup' ? 'active' : '' ?>" href="auth.php?mode=signup">Sign up</a>
      </nav>
      <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($mode === 'signup'): ?>
        <form method="post" action="auth.php?mode=signup" autocomplete="on">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="signup">
          <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" type="text" maxlength="100" placeholder="Enter your full name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
          <div class="row">
            <div class="field"><label for="phone_number">Phone number</label><input id="phone_number" name="phone_number" type="tel" maxlength="20" placeholder="e.g. 0771234567" required value="<?= htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="field"><label for="user_type">I want to</label><select id="user_type" name="user_type">
                <option value="both">Find and offer help</option>
                <option value="customer">Find help</option>
                <option value="provider">Offer help</option>
              </select></div>
          </div>
          <div class="field"><label for="email">Email <span style="font-weight:400;color:#8a94a5">(optional)</span></label><input id="email" name="email" type="email" maxlength="150" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
          <div class="row">
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required></div>
            <div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" placeholder="Repeat password" required></div>
          </div>
          <button class="submit" type="submit">Create account <span aria-hidden="true">→</span></button>
          <p class="fine-print">By creating an account, you agree to use HandyLink respectfully and safely.</p>
        </form>
      <?php else: ?>
        <form method="post" action="auth.php?mode=login" autocomplete="on">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="login">
          <div class="field"><label for="phone_number">Phone number</label><input id="phone_number" name="phone_number" type="tel" maxlength="20" autocomplete="username" placeholder="Enter your phone number" required value="<?= htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
          <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required></div>
          <button class="submit" type="submit">Log in <span aria-hidden="true">→</span></button>
          <p class="fine-print">New to HandyLink? <a href="auth.php?mode=signup">Create an account</a></p>
        </form>
      <?php endif; ?>
    </main>
  </div>
</body>

</html>
