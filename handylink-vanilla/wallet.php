<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: auth.php?mode=login');
  exit;
}

const DB_HOST = '127.0.0.1';
const DB_NAME = "handymen's";
const DB_USER = 'root';
const DB_PASS = '';

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
  );

  $stmt = $pdo->prepare(
    'SELECT w.id, w.credit_balance
         FROM wallets w
         JOIN provider_profiles pp ON pp.id = w.provider_id
         WHERE pp.user_id = ?
         LIMIT 1'
  );

  $stmt->execute([(int) $_SESSION['user_id']]);
  $wallet = $stmt->fetch();

  $walletId = $wallet ? (int) $wallet['id'] : null;
  $creditsBalance = $wallet
    ? (float) $wallet['credit_balance']
    : 0.00;
} catch (PDOException $e) {
  error_log('Wallet error: ' . $e->getMessage());
  http_response_code(500);
  exit('Unable to load your credits. Please try again later.');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My credits — HandyLink</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <div class="app-shell">

    <?php include "sidebar.php"; ?>

    <div class="main">

      <?php include "topBars.php"; ?>

      <main class="content">
        <div class="max-width-narrow">
          <a href="index.php" class="back-link"><i data-icon="arrow-left" data-size="16"></i> Back to overview</a>

          <div class="wallet-layout">
            <div>
              <section class="balance-hero">
                <span class="ring"></span>
                <p class="label">Current balance</p>
                <p class="amount">
                  <?= number_format($creditsBalance, 2) ?>
                  <span class="unit">credits</span>
                </p>
                ```

                <p class="note">Credits unlock job details and let you message helpers directly. They never expire.</p>
              </section>

              <h2 class="bundle-section-title">Top up your credits</h2>
              <p class="bundle-section-sub">Pick a bundle — paid through EcoCash, added to your wallet instantly.</p>

              <div class="bundle-grid" id="bundleGrid">
                <button type="button" data-bundle="starter" data-credits="5" data-price="$2.00" class="bundle-card">

                  <p class="label">Bundle</p>
                  <p class="credits">5 <span class="unit">credits</span></p>
                  <p class="price">$2.00</p>
                  <p class="per">$0.40 / credit</p>
                </button>
                <button type="button" data-bundle="popular" data-credits="13" data-price="$5.00" class="bundle-card selected">
                  <span class="bundle-tag">Most popular</span>
                  <p class="label">Bundle</p>
                  <p class="credits">13 <span class="unit">credits</span></p>
                  <p class="price">$5.00</p>
                  <p class="per">$0.38 / credit</p>
                </button>
                <button type="button" data-bundle="pro" data-credits="28" data-price="$10.00" class="bundle-card">
                  <span class="bundle-tag">Best value</span>
                  <p class="label">Bundle</p>
                  <p class="credits">28 <span class="unit">credits</span></p>
                  <p class="price">$10.00</p>
                  <p class="per">$0.36 / credit</p>
                </button>
              </div>

              <div id="walletSuccessState" class="wallet-success">
                <span class="icon"><i data-icon="check" data-size="18"></i></span>
                <div>
                  <p class="title">Credits added</p>
                  <p class="detail" id="walletSuccessDetail">Your new balance is ready to use.</p>
                </div>
              </div>
            </div>

            <aside class="aside-col summary-card">
              <h3>Order summary</h3>
              <div class="summary-rows">
                <div class="summary-row"><span class="k">Bundle</span><span class="v" id="summaryBundle">13 credits</span></div>
                <div class="summary-row"><span class="k">Price</span><span class="v mono" id="summaryPrice">$5.00</span></div>
                <div class="summary-row"><span class="k">Payment method</span><span class="v">EcoCash</span></div>
              </div>
              <div class="summary-total"><span>New balance</span><span class="v mono" id="summaryNewBalance">29 credits</span></div>
              <button id="purchaseBtn" class="submit-btn" onclick="window.prompt('Enter your ecocash pin to continue.')"><i data-icon="credit-card" data-size="16"></i> Pay with EcoCash</button>
              <div class="wallet-note"><i data-icon="lock-keyhole" data-size="14"></i> You'll get an EcoCash prompt on your phone to confirm this payment.</div>
            </aside>
          </div>

          <div class="wallet-footer-banner">
            <span class="icon"><i data-icon="shield-check" data-size="20"></i></span>
            <div>
              <p class="title">Credits move. Trust stays.</p>
              <p class="desc">You only spend credits once a job is actually confirmed — never just for browsing.</p>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="js/icons.js"></script>
  <script src="js/shell.js"></script>
  <script src="js/wallet.js"></script>
</body>

</html>
