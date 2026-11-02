<aside class="sidebar">
    <a href="index.php" class="logo">
        <span class="logo-badge"><span class="dot"></span><i data-icon="hand-helping" data-size="21" data-stroke="2.4"></i></span>
        <span class="logo-text">HandyLink</span>
    </a>

    <div class="sidebar-user">
        <?php
        $name = $_SESSION['user_name'] ?? 'Neighbor';
        $words = preg_split('/\s+/', trim($name));
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        $accountType = $_SESSION['user_type'] ?? 'both';

        $typeLabels = [
            'customer' => 'Find help',
            'provider' => 'Offer help',
            'both' => 'Find and offer help'
        ];

        $typeLabel = $typeLabels[$accountType] ?? 'HandyLink member';
        ?>

        <span class="avatar">
            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
        </span>

        <div class="user-info">
            <p class="name">
                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="sub">
                <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <button id="profileMenuBtn" class="profile-toggle" aria-label="Open profile menu"><i data-icon="chevron-right" data-size="16"></i></button>
    </div>
    <div id="profilePanel" class="profile-panel hidden">
        <p><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
        <p class="nav-kicker">Your workspace</p>
        <a href="index.php" class="nav-link"><i data-icon="grid" data-size="18"></i><span>Overview</span></a>
        <a href="jobs.php" class="nav-link"><i data-icon="compass" data-size="18"></i><span>Find a job</span><span class="nav-badge">24</span></a>
        <a href="providers.php" class="nav-link active"><i data-icon="user-round" data-size="18"></i><span>Nearby helpers</span></a>
        <a href="wallet.php" class="nav-link"><i data-icon="wallet-cards" data-size="18"></i><span>My credits</span></a>
    </nav>

    <div class="sidebar-tip">
        <div class="sidebar-tip-head">
            <span class="sidebar-tip-icon"><i data-icon="sparkles" data-size="16"></i></span>
            <span class="sidebar-tip-label">TIP 04</span>
        </div>
        <p class="title">Good neighbors make light work.</p>
        <p class="body">Post what you can't do. Offer what you can.</p>
    </div>
    <a href="post.php" class="sidebar-post-btn"><i data-icon="plus" data-size="17"></i> Post a request</a>
    <a href="logout.php">Log out</a>
</aside>