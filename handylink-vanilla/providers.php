<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user_id'])) {
  header('Location: auth.php?mode=login');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nearby helpers — HandyLink</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <div class="app-shell">

    <?php include "sidebar.php"; ?>

    <div class="main">

      <?php include "topBars.php"; ?>

      <main class="content">
        <div class="max-width">

          <a href="index.php" class="back-link"><i data-icon="arrow-left" data-size="16"></i> Back to overview</a>

          <div class="page-head-row">
            <div>
              <span class="page-eyebrow">People who make things happen</span>
              <h1 class="page-title">Meet your<br>nearby helpers.</h1>
              <p class="page-sub">Neighbors with practical skills, generous instincts, and a little room in their schedule.</p>
            </div>
            <span class="network-pill"><i data-icon="map-pin" data-size="15"></i> Oakland neighborhood network</span>
          </div>

          <div class="filter-bar">
            <label class="search-field">
              <i data-icon="search" data-size="17"></i>
              <input id="providerSearchInput" placeholder="Search a name or skill">
            </label>
            <a href="post.php" class="btn btn-primary">Know who you need? <i data-icon="arrow-right" data-size="15"></i></a>
          </div>

          <div class="results-head">
            <div>
              <h2 id="providersCountHeading" class="section-title">6 helpers nearby</h2>
              <p class="section-sub">Verified through completed jobs and neighbor reviews.</p>
            </div>
          </div>

          <div class="job-grid providers-grid" id="providersGrid">
            <article class="provider-card" data-search="tendai moyo electrical wiring">
              <div class="provider-card-top">
                <div class="provider-card-who">
                  <span class="avatar">TM</span>
                  <div>
                    <div class="provider-name-row">
                      <h3>Tendai Moyo</h3><span class="verified-dot" title="Verified helper"><i data-icon="check" data-size="10"></i></span>
                    </div>
                    <p class="provider-distance"><i data-icon="map-pin" data-size="12"></i>0.4 mi away</p>
                  </div>
                </div>
                <div class="provider-rating"><i data-icon="star" data-size="14"></i>4.9</div>
              </div>
              <div class="specialty-pills"><span class="specialty-pill">Electrical</span><span class="specialty-pill">Wiring</span></div>
              <div class="provider-card-foot">
                <span class="meta">112 jobs · 98 reviews</span>
                <a href="post.php" class="hire-btn">Hire directly <i data-icon="arrow-right" data-size="13"></i></a>
              </div>
            </article>
            <article class="provider-card" data-search="rue's car care car wash detailing">
              <div class="provider-card-top">
                <div class="provider-card-who">
                  <span class="avatar">RC</span>
                  <div>
                    <div class="provider-name-row">
                      <h3>Rue's Car Care</h3><span class="verified-dot" title="Verified helper"><i data-icon="check" data-size="10"></i></span>
                    </div>
                    <p class="provider-distance"><i data-icon="map-pin" data-size="12"></i>0.9 mi away</p>
                  </div>
                </div>
                <div class="provider-rating"><i data-icon="star" data-size="14"></i>4.7</div>
              </div>
              <div class="specialty-pills"><span class="specialty-pill">Car wash</span><span class="specialty-pill">Detailing</span></div>
              <div class="provider-card-foot">
                <span class="meta">340 jobs · 301 reviews</span>
                <a href="post.php" class="hire-btn">Hire directly <i data-icon="arrow-right" data-size="13"></i></a>
              </div>
            </article>
            <article class="provider-card" data-search="simba mutasa carpentry furniture">
              <div class="provider-card-top">
                <div class="provider-card-who">
                  <span class="avatar">SM</span>
                  <div>
                    <div class="provider-name-row">
                      <h3>Simba Mutasa</h3><span class="verified-dot" title="Verified helper"><i data-icon="check" data-size="10"></i></span>
                    </div>
                    <p class="provider-distance"><i data-icon="map-pin" data-size="12"></i>1.2 mi away</p>
                  </div>
                </div>
                <div class="provider-rating"><i data-icon="star" data-size="14"></i>4.8</div>
              </div>
              <div class="specialty-pills"><span class="specialty-pill">Carpentry</span><span class="specialty-pill">Furniture</span></div>
              <div class="provider-card-foot">
                <span class="meta">76 jobs · 64 reviews</span>
                <a href="post.php" class="hire-btn">Hire directly <i data-icon="arrow-right" data-size="13"></i></a>
              </div>
            </article>
            <article class="provider-card" data-search="layla k. painting touch-ups">
              <div class="provider-card-top">
                <div class="provider-card-who">
                  <span class="avatar">LK</span>
                  <div>
                    <div class="provider-name-row">
                      <h3>Layla K.</h3>
                    </div>
                    <p class="provider-distance"><i data-icon="map-pin" data-size="12"></i>0.6 mi away</p>
                  </div>
                </div>
                <div class="provider-rating"><i data-icon="star" data-size="14"></i>4.5</div>
              </div>
              <div class="specialty-pills"><span class="specialty-pill">Painting</span><span class="specialty-pill">Touch-ups</span></div>
              <div class="provider-card-foot">
                <span class="meta">21 jobs · 18 reviews</span>
                <a href="post.php" class="hire-btn">Hire directly <i data-icon="arrow-right" data-size="13"></i></a>
              </div>
            </article>
            <article class="provider-card" data-search="omar hassan plumbing repairs">
              <div class="provider-card-top">
                <div class="provider-card-who">
                  <span class="avatar">OH</span>
                  <div>
                    <div class="provider-name-row">
                      <h3>Omar Hassan</h3><span class="verified-dot" title="Verified helper"><i data-icon="check" data-size="10"></i></span>
                    </div>
                    <p class="provider-distance"><i data-icon="map-pin" data-size="12"></i>1.5 mi away</p>
                  </div>
                </div>
                <div class="provider-rating"><i data-icon="star" data-size="14"></i>4.9</div>
              </div>
              <div class="specialty-pills"><span class="specialty-pill">Plumbing</span><span class="specialty-pill">Repairs</span></div>
              <div class="provider-card-foot">
                <span class="meta">154 jobs · 140 reviews</span>
                <a href="post.php" class="hire-btn">Hire directly <i data-icon="arrow-right" data-size="13"></i></a>
              </div>
            </article>
            <article class="provider-card" data-search="noura ali errands queue waiting">
              <div class="provider-card-top">
                <div class="provider-card-who">
                  <span class="avatar">NA</span>
                  <div>
                    <div class="provider-name-row">
                      <h3>Noura Ali</h3>
                    </div>
                    <p class="provider-distance"><i data-icon="map-pin" data-size="12"></i>2.0 mi away</p>
                  </div>
                </div>
                <div class="provider-rating"><i data-icon="star" data-size="14"></i>4.6</div>
              </div>
              <div class="specialty-pills"><span class="specialty-pill">Errands</span><span class="specialty-pill">Queue waiting</span></div>
              <div class="provider-card-foot">
                <span class="meta">39 jobs · 30 reviews</span>
                <a href="post.php" class="hire-btn">Hire directly <i data-icon="arrow-right" data-size="13"></i></a>
              </div>
            </article>
          </div>

          <div id="providersEmptyState" class="empty-state hidden">
            <span class="empty-state-icon"><i data-icon="circle-help" data-size="22"></i></span>
            <h3>No match just yet</h3>
            <p>Try another skill or name. The right person may be listed under a specialty.</p>
            <button id="providersResetBtn" class="btn btn-card">Clear search</button>
          </div>

          <div class="become-helper-banner">
            <div class="left">
              <span class="icon"><i data-icon="sparkles" data-size="18"></i></span>
              <div>
                <p class="title">Want to become a helper?</p>
                <p class="desc">Share a skill, earn credits, and make someone's day easier.</p>
              </div>
            </div>
            <a href="jobs.php" class="link">Find ways to help <i data-icon="arrow-right" data-size="15"></i></a>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="js/icons.js"></script>
  <script src="js/shell.js"></script>
  <script src="js/providers.js"></script>
</body>

</html>