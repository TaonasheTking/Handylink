<?php
$topbarNotifications = [];
$topbarUnreadCount = 0;
if (!empty($_SESSION['user_id'])) {
    try {
        $notificationPdo = new PDO("mysql:host=127.0.0.1;dbname=handymen's;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $notificationQuery = $notificationPdo->prepare('SELECT id,type,title,body,related_job_id,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5');
        $notificationQuery->execute([(int)$_SESSION['user_id']]);
        $topbarNotifications = $notificationQuery->fetchAll();
        $notificationCountQuery = $notificationPdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $notificationCountQuery->execute([(int)$_SESSION['user_id']]);
        $topbarUnreadCount = (int)$notificationCountQuery->fetchColumn();
    } catch (PDOException $e) { $topbarNotifications = []; }
}
?>
<header class="topbar">
    <div class="topbar-mobile-left">
        <button id="mobileMenuBtn" class="icon-btn" aria-label="Toggle navigation"><i data-icon="menu" data-size="19"></i></button>
        <span class="topbar-logo-badge"><i data-icon="hand-helping" data-size="16"></i></span>
    </div>
    <div class="topbar-date">
        <span class="day">Wednesday, October 09</span>
        <span class="sep"></span>
        <span>Oakland, CA</span>
    </div>
    <div class="topbar-right">
        <a href="wallet.php" class="credits-pill">
            <i data-icon="coins" data-size="16" class="coin-icon"></i>
            <span class="label">Credits</span>
            <span class="value">16</span>
        </a>
        <div class="notif-wrap">
            <button id="notificationBtn" class="icon-btn notif" aria-label="Open notifications">
                <i data-icon="bell" data-size="17"></i>
                <?php if ($topbarUnreadCount): ?><span class="notif-dot"></span><?php endif; ?>
            </button>
            <div id="notificationPanel" class="notif-panel hidden">
                <div class="notif-panel-head">
                    <div>
                        <p class="title">Notifications</p>
                        <p class="sub"><?= $topbarUnreadCount ?> unread update<?= $topbarUnreadCount === 1 ? '' : 's' ?></p>
                    </div>
                    <i data-icon="bell" data-size="16"></i>
                </div>
                <?php foreach ($topbarNotifications as $notification): ?>
                    <a href="notifications.php" class="notif-item">
                        <p class="t"><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="d"><?= htmlspecialchars($notification['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (!$topbarNotifications): ?><p class="notif-item"><span class="d">No notifications yet.</span></p><?php endif; ?>
                <a href="notifications.php" class="notif-panel-foot">View notification center <i data-icon="chevron-right" data-size="14"></i></a>
            </div>
        </div>
        <span class="avatar" style="width:32px;height:32px;font-size:10px;"><a href="profile.php" style="text-decoration: none;">MC</a></span>
    </div>
</header>

<div id="mobileNav" class="mobile-nav hidden">
    <a href="index.php" class="mobile-nav-link"><i data-icon="grid" data-size="18"></i>Overview</a>
    <a href="jobs.php" class="mobile-nav-link"><i data-icon="compass" data-size="18"></i>Find a job</a>
    <a href="providers.php" class="mobile-nav-link"><i data-icon="user-round" data-size="18"></i>Nearby helpers</a>
    <a href="wallet.php" class="mobile-nav-link active"><i data-icon="wallet-cards" data-size="18"></i>My credits</a>
    <a href="post.php" class="mobile-nav-link post"><i data-icon="plus" data-size="18"></i>Post a request</a>
</div>
