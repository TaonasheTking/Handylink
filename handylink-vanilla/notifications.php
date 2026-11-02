<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: auth.php?mode=login'); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$uid=(int)$_SESSION['user_id']; $error=''; $notifications=[];
try {
    $pdo=new PDO("mysql:host=127.0.0.1;dbname=handymen's;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token'] ?? '')) $error='Your session expired. Refresh and try again.';
        elseif (($_POST['action'] ?? '')==='read_all') $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$uid]);
        elseif (($_POST['action'] ?? '')==='read') $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int)($_POST['notification_id'] ?? 0),$uid]);
    }
    $q=$pdo->prepare('SELECT id,type,title,body,related_job_id,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100'); $q->execute([$uid]); $notifications=$q->fetchAll();
} catch (PDOException $e) { $error='Could not load notifications. Try again shortly.'; }
function nhn($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications — HandyLink</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/negotiation.css"></head><body><div class="app-shell"><?php include 'sidebar.php'; ?><div class="main"><?php include 'topBars.php'; ?><main class="content"><div class="max-width-narrow"><a class="back-link" href="index.php">← Back to overview</a><header class="neg-heading"><p class="page-eyebrow">Updates</p><h1>Notifications</h1><p class="page-sub">Offers, messages, selections and agreement updates.</p></header><?php if($error): ?><div class="neg-alert"><?=nhn($error)?></div><?php endif; ?><form method="post" style="margin-bottom:14px"><input type="hidden" name="csrf_token" value="<?=nhn($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="read_all"><button class="btn btn-card" type="submit">Mark all as read</button></form><?php foreach($notifications as $n): ?><article class="neg-panel" style="opacity:<?=$n['is_read']?'.72':'1'?>"><div class="neg-panel-head"><h2><?=nhn($n['title'])?></h2><time><?=nhn(date('j M Y, g:i A',strtotime($n['created_at'])))?></time></div><p><?=nl2br(nhn($n['body']))?></p><?php if($n['related_job_id']): ?><a class="btn btn-card" href="job-negotiation.php?job_id=<?=(int)$n['related_job_id']?>">Open job</a><?php endif; ?><?php if(!$n['is_read']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=nhn($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?=(int)$n['id']?>"><button class="btn btn-card" type="submit">Mark as read</button></form><?php endif; ?></article><?php endforeach; ?><?php if(!$notifications): ?><p class="muted">You’re all caught up.</p><?php endif; ?></div></main></div></div><script src="js/icons.js"></script><script src="js/shell.js"></script></body></html>
