<?php
session_start();
date_default_timezone_set('Africa/Harare');
if (empty($_SESSION['user_id'])) { header('Location: auth.php?mode=login'); exit; }
const DB_HOST = '127.0.0.1';
const DB_NAME = "handymen's";
const DB_USER = 'root';
const DB_PASS = '';
function nh($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function failPage(string $message, int $code = 400): never { http_response_code($code); exit($message); }
function notify(PDO $pdo, int $userId, int $jobId, string $type, string $title, string $body): void {
    $q=$pdo->prepare('INSERT INTO notifications(user_id,type,title,body,related_job_id) VALUES(?,?,?,?,?)');
    $q->execute([$userId,$type,$title,$body,$jobId]);
}
function resumeNegotiations(PDO $pdo, int $jobId): void {
    $q = $pdo->prepare("SELECT selected_application_id, selection_expires_at FROM jobs WHERE id=? AND status='open' FOR UPDATE");
    $q->execute([$jobId]); $row = $q->fetch();
    if (!$row || !$row['selected_application_id'] || !$row['selection_expires_at'] || strtotime($row['selection_expires_at']) > time()) return;
    $app = (int)$row['selected_application_id'];
    $pdo->prepare("UPDATE job_applications SET status='negotiating', selection_expires_at=NULL, current_proposal_id=NULL WHERE id=?")->execute([$app]);
    $pdo->prepare("UPDATE job_proposals p JOIN job_applications a ON a.id=p.application_id SET p.status='superseded' WHERE a.id=? AND p.status='ready'")->execute([$app]);
    $pdo->prepare("UPDATE job_applications SET status='submitted' WHERE job_id=? AND status='paused'")->execute([$jobId]);
    $pdo->prepare('UPDATE jobs SET selected_application_id=NULL, selected_at=NULL, selection_expires_at=NULL WHERE id=?')->execute([$jobId]);
    $q=$pdo->prepare('SELECT j.customer_id,j.title,pp.user_id AS helper_user_id FROM jobs j JOIN job_applications a ON a.id=? JOIN provider_profiles pp ON pp.id=a.provider_id WHERE j.id=?');
    $q->execute([$app,$jobId]); $parties=$q->fetch();
    if ($parties) {
        notify($pdo,(int)$parties['customer_id'],$jobId,'selection_expired','Selection expired','The confirmation period for “'.$parties['title'].'” ended. Other offers are active again.');
        notify($pdo,(int)$parties['helper_user_id'],$jobId,'selection_expired','Selection expired','The confirmation period for “'.$parties['title'].'” ended. Negotiations have reopened.');
    }
}
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException $e) { failPage('Could not connect to HandyLink.', 503); }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$uid = (int)$_SESSION['user_id']; $jobId = filter_input(INPUT_GET, 'job_id', FILTER_VALIDATE_INT) ?: 0;
$applicationId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT) ?: 0;
$error = ''; $notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { $error='Your session expired. Refresh and try again.'; }
    else {
        $action = (string)($_POST['action'] ?? ''); $applicationId = (int)($_POST['application_id'] ?? 0); $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            if ($jobId > 0) { $pdo->beginTransaction(); resumeNegotiations($pdo, $jobId); $pdo->commit(); }
            $pdo->beginTransaction();
            $a = $pdo->prepare('SELECT a.*, j.customer_id, j.title, j.status AS job_status, j.booking_flow, j.budget_min, j.budget_max, j.confirmation_window_hours, j.selected_application_id, j.selection_expires_at AS job_deadline, pp.user_id AS helper_user_id FROM job_applications a JOIN jobs j ON j.id=a.job_id JOIN provider_profiles pp ON pp.id=a.provider_id WHERE a.id=? FOR UPDATE');
            if ($applicationId) { $a->execute([$applicationId]); $thread=$a->fetch(); }
            else $thread=null;

            if ($action === 'offer') {
                $j=$pdo->prepare("SELECT * FROM jobs WHERE id=? AND status='open' AND booking_flow='job_post' FOR UPDATE"); $j->execute([$jobId]); $job=$j->fetch();
                $prof=$pdo->prepare("SELECT pp.id FROM provider_profiles pp JOIN users u ON u.id=pp.user_id WHERE u.id=? AND u.is_active=1 AND u.user_type IN ('provider','both')"); $prof->execute([$uid]); $providerId=(int)($prof->fetchColumn() ?: 0);
                $price=filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT); $scope=trim((string)($_POST['scope_description'] ?? ''));
                if (!$job || !$providerId || (int)$job['customer_id']===$uid) throw new DomainException('This job is not available for an offer from your account.');
                if ($price===false || $price<0.01 || $price<(float)$job['budget_min'] || ($job['budget_max']!==null && $price>(float)$job['budget_max'])) throw new DomainException('Your price must be within the customer’s budget range.');
                if ($scope==='') $scope=(string)$job['description'];
                $q=$pdo->prepare('SELECT id FROM job_applications WHERE job_id=? AND provider_id=?'); $q->execute([$jobId,$providerId]); $exists=(int)($q->fetchColumn() ?: 0);
                if ($exists) throw new DomainException('You already have an offer thread for this job. Open it to revise your proposal.');
                $q=$pdo->prepare("INSERT INTO job_applications(job_id,provider_id,proposed_price,message,status) VALUES(?,?,?,?,'submitted')"); $q->execute([$jobId,$providerId,$price,trim((string)($_POST['message'] ?? '')) ?: null]); $applicationId=(int)$pdo->lastInsertId();
                $threadId=$applicationId; $version=1; $proposer=$uid;
                $ins=$pdo->prepare("INSERT INTO job_proposals(job_id,application_id,version_number,proposed_by,proposed_price,scope_description,scheduled_date,start_time,estimated_duration_minutes,materials_arrangement,additional_terms,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,'proposed')");
                $ins->execute([$jobId,$threadId,$version,$proposer,$price,$scope,$_POST['scheduled_date'] ?: null,$_POST['start_time'] ?: null,(int)($_POST['duration'] ?? 0) ?: null,trim((string)($_POST['materials'] ?? '')) ?: null,trim((string)($_POST['terms'] ?? '')) ?: null]);
                $proposalId=(int)$pdo->lastInsertId(); $pdo->prepare('UPDATE job_applications SET current_proposal_id=? WHERE id=?')->execute([$proposalId,$threadId]);
                $applicationId=$threadId; $notice='Offer sent. No credits were charged.';
                notify($pdo,(int)$job['customer_id'],$jobId,'new_offer','New job offer','A helper sent an offer for “'.$job['title'].'”.');
            } elseif (!$thread || ((int)$thread['customer_id']!==$uid && (int)$thread['helper_user_id']!==$uid)) {
                throw new DomainException('You do not have access to this offer.');
            } elseif ($thread['job_status']!=='open') {
                throw new DomainException('This job is no longer available for negotiation.');
            } elseif ($action==='message') {
                $message=trim((string)($_POST['message'] ?? ''));
                if ($message==='' || mb_strlen($message)>2000) throw new DomainException('Enter a message of up to 2,000 characters.');
                $pdo->prepare('INSERT INTO messages(job_id,application_id,sender_id,message_text) VALUES(?,?,?,?)')->execute([$thread['job_id'],$applicationId,$uid,$message]);
                $recipient=$uid===(int)$thread['customer_id']?(int)$thread['helper_user_id']:(int)$thread['customer_id'];
                notify($pdo,$recipient,(int)$thread['job_id'],'offer_message','New negotiation message','You received a private message about “'.$thread['title'].'”.');
                $notice='Message sent.';
            } elseif ($action==='propose') {
                if ((int)$thread['selected_application_id'] && (int)$thread['selected_application_id']!==$applicationId) throw new DomainException('Only the provisionally selected pair can revise terms.');
                $price=filter_var($_POST['price'] ?? null,FILTER_VALIDATE_FLOAT); $scope=trim((string)($_POST['scope_description'] ?? ''));
                if ($price===false || $price<0.01 || $price<(float)$thread['budget_min'] || ($thread['budget_max']!==null && $price>(float)$thread['budget_max'])) throw new DomainException('The proposed price must stay within the original budget.');
                if ($scope==='') throw new DomainException('Work scope is required.');
                $q=$pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM job_proposals WHERE application_id=?'); $q->execute([$applicationId]); $version=(int)$q->fetchColumn();
                if ($thread['current_proposal_id']) $pdo->prepare("UPDATE job_proposals SET status='superseded' WHERE id=?")->execute([(int)$thread['current_proposal_id']]);
                $ins=$pdo->prepare("INSERT INTO job_proposals(job_id,application_id,version_number,proposed_by,proposed_price,scope_description,scheduled_date,start_time,estimated_duration_minutes,materials_arrangement,additional_terms,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,'proposed')");
                $ins->execute([$thread['job_id'],$applicationId,$version,$uid,$price,$scope,$_POST['scheduled_date'] ?: null,$_POST['start_time'] ?: null,(int)($_POST['duration'] ?? 0) ?: null,trim((string)($_POST['materials'] ?? '')) ?: null,trim((string)($_POST['terms'] ?? '')) ?: null]);
                $proposalId=(int)$pdo->lastInsertId(); $pdo->prepare("UPDATE job_applications SET current_proposal_id=?, proposed_price=?, status=IF(status='selected','selected','negotiating') WHERE id=?")->execute([$proposalId,$price,$applicationId]);
                $notice='Counterproposal saved as version '.$version.'. Both parties must confirm the new version.';
                $recipient=$uid===(int)$thread['customer_id']?(int)$thread['helper_user_id']:(int)$thread['customer_id'];
                notify($pdo,$recipient,(int)$thread['job_id'],'new_proposal','New proposal version','A new version of the terms for “'.$thread['title'].'” is ready to review.');
            } elseif ($action==='select') {
                if ((int)$thread['customer_id']!==$uid || $thread['status']!=='submitted' && $thread['status']!=='negotiating') throw new DomainException('Only the customer can select an active offer.');
                $q=$pdo->prepare('UPDATE jobs SET selected_application_id=?, selected_at=NOW(), selection_expires_at=NULL WHERE id=? AND selected_application_id IS NULL AND status=\'open\''); $q->execute([$applicationId,$thread['job_id']]);
                if (!$q->rowCount()) throw new DomainException('Another offer is already selected. Refresh the page.');
                $pdo->prepare("UPDATE job_applications SET status='paused' WHERE job_id=? AND id<>? AND status IN ('pending','submitted','negotiating')")->execute([$thread['job_id'],$applicationId]);
                $pdo->prepare("UPDATE job_applications SET status='selected',selected_at=NOW() WHERE id=?")->execute([$applicationId]); $notice='Helper selected provisionally. The job is not confirmed and no credits were charged.';
                notify($pdo,(int)$thread['helper_user_id'],(int)$thread['job_id'],'offer_selected','You were provisionally selected','The customer selected your offer for “'.$thread['title'].'”.');
            } elseif ($action==='ready') {
                if ((int)$thread['customer_id']!==$uid && (int)$thread['helper_user_id']!==$uid) throw new DomainException('Only the customer and selected helper can proceed.');
                if ((int)$thread['selected_application_id']!==$applicationId) throw new DomainException('Select this helper before preparing final confirmation.');
                $q=$pdo->prepare('SELECT * FROM job_proposals WHERE id=? AND application_id=? FOR UPDATE'); $q->execute([(int)$thread['current_proposal_id'],$applicationId]); $proposal=$q->fetch();
                if (!$proposal || $proposal['status']!=='proposed') throw new DomainException('Create a new proposal version before starting confirmation.');
                $window=$thread['confirmation_window_hours']; $started=date('Y-m-d H:i:s'); $deadline=$window===null ? null : date('Y-m-d H:i:s',time()+((int)$window*3600));
                $pdo->prepare("UPDATE job_proposals SET status='ready',confirmation_started_at=?,confirmation_deadline_at=? WHERE id=?")->execute([$started,$deadline,$proposal['id']]);
                $pdo->prepare('UPDATE jobs SET selection_expires_at=? WHERE id=?')->execute([$deadline,$thread['job_id']]);
                $pdo->prepare('UPDATE job_applications SET selection_expires_at=? WHERE id=?')->execute([$deadline,$applicationId]);
                $notice=$deadline ? 'Agreement ready. The confirmation countdown has started.' : 'Agreement ready. There is no confirmation deadline.';
                notify($pdo,(int)$thread['customer_id'],(int)$thread['job_id'],'agreement_ready','Agreement ready to confirm','The final terms for “'.$thread['title'].'” are ready.');
                notify($pdo,(int)$thread['helper_user_id'],(int)$thread['job_id'],'agreement_ready','Agreement ready to confirm','The final terms for “'.$thread['title'].'” are ready.');
            } elseif ($action==='confirm') {
                if ((int)$thread['selected_application_id']!==$applicationId) throw new DomainException('This offer is not the selected agreement.');
                $proposalId=(int)($_POST['proposal_id'] ?? 0);
                $q=$pdo->prepare('SELECT * FROM job_proposals WHERE id=? AND application_id=? AND id=? FOR UPDATE'); $q->execute([$proposalId,$applicationId,(int)$thread['current_proposal_id']]); $proposal=$q->fetch();
                if (!$proposal || $proposal['status']!=='ready') throw new DomainException('This proposal is no longer ready for confirmation.');
                if ($proposal['confirmation_deadline_at'] && strtotime($proposal['confirmation_deadline_at'])<=time()) throw new DomainException('The confirmation deadline expired. The offers have reopened.');
                $pdo->prepare('INSERT IGNORE INTO proposal_confirmations(proposal_id,user_id) VALUES(?,?)')->execute([$proposalId,$uid]);
                $q=$pdo->prepare('SELECT COUNT(DISTINCT pc.user_id) FROM proposal_confirmations pc WHERE pc.proposal_id=? AND pc.user_id IN (?,?)'); $q->execute([$proposalId,(int)$thread['customer_id'],(int)$thread['helper_user_id']]);
                if ((int)$q->fetchColumn()===2) {
                    $pdo->prepare("UPDATE jobs SET status='confirmed',assigned_provider_id=?,confirmed_proposal_id=?,confirmed_at=NOW(),selection_expires_at=NULL WHERE id=? AND status='open' AND selected_application_id=?")->execute([$thread['provider_id'],$proposalId,$thread['job_id'],$applicationId]);
                    if (!$pdo->query('SELECT ROW_COUNT()')->fetchColumn()) throw new DomainException('The job changed before confirmation completed.');
                    $pdo->prepare("UPDATE job_proposals SET status='confirmed' WHERE id=?")->execute([$proposalId]);
                    $pdo->prepare("UPDATE job_applications SET status=IF(id=?,'accepted','closed') WHERE job_id=?")->execute([$applicationId,$thread['job_id']]);
                    $notice='Both parties confirmed. The job is confirmed. Credits were not charged.';
                    notify($pdo,(int)$thread['customer_id'],(int)$thread['job_id'],'job_confirmed','Job confirmed','Both parties confirmed the agreement for “'.$thread['title'].'”.');
                    notify($pdo,(int)$thread['helper_user_id'],(int)$thread['job_id'],'job_confirmed','Job confirmed','Both parties confirmed the agreement for “'.$thread['title'].'”.');
                } else {
                    $recipient=$uid===(int)$thread['customer_id']?(int)$thread['helper_user_id']:(int)$thread['customer_id'];
                    notify($pdo,$recipient,(int)$thread['job_id'],'agreement_confirmation','Confirmation received','The other party confirmed the current terms for “'.$thread['title'].'”.');
                    $notice='Your confirmation is recorded for this proposal version.';
                }
            } elseif ($action==='decline' || $action==='withdraw') {
                $allowed = $action==='decline' ? (int)$thread['customer_id']===$uid : (int)$thread['helper_user_id']===$uid;
                if (!$allowed) throw new DomainException('You cannot perform that action.');
                $pdo->prepare("UPDATE job_applications SET status=? WHERE id=?")->execute([$action==='decline'?'declined':'withdrawn',$applicationId]);
                if ((int)$thread['selected_application_id']===$applicationId) {
                    $pdo->prepare("UPDATE job_applications SET status='submitted',selection_expires_at=NULL WHERE job_id=? AND status='paused'")->execute([$thread['job_id']]);
                    $pdo->prepare('UPDATE jobs SET selected_application_id=NULL,selected_at=NULL,selection_expires_at=NULL WHERE id=?')->execute([$thread['job_id']]);
                }
                $notice='Offer closed.';
            } else throw new DomainException('Unknown action.');
            $pdo->commit();
        } catch (DomainException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); }
          catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage()); $error='Could not save that update. Refresh and try again.'; }
    }
}

if ($applicationId) {
    $q=$pdo->prepare('SELECT a.*,j.id AS job_id,j.customer_id,j.title,j.description,j.location_address,j.budget_min,j.budget_max,j.budget_label,j.schedule_type,j.scheduled_date AS job_date,j.preferred_time AS job_time,j.status AS job_status,j.selected_application_id,j.confirmation_window_hours,j.selection_expires_at AS job_deadline,sc.name AS category_name,pp.user_id AS helper_user_id,pp.business_name,pp.bio,u.full_name AS helper_name,u.profile_photo_url FROM job_applications a JOIN jobs j ON j.id=a.job_id JOIN service_categories sc ON sc.id=j.category_id JOIN provider_profiles pp ON pp.id=a.provider_id JOIN users u ON u.id=pp.user_id WHERE a.id=?');
    $q->execute([$applicationId]); $thread=$q->fetch();
    if (!$thread || ((int)$thread['customer_id']!==$uid && (int)$thread['helper_user_id']!==$uid)) failPage('Offer not found.',404);
    $jobId=(int)$thread['job_id'];
    if ($thread['job_status']==='open' && (int)$thread['selected_application_id']===$applicationId && $thread['job_deadline'] && strtotime($thread['job_deadline'])<=time()) {
        $pdo->beginTransaction(); resumeNegotiations($pdo,$jobId); $pdo->commit();
        header('Location: job-negotiation.php?job_id='.$jobId.'&expired=1'); exit;
    }
    $q=$pdo->prepare('SELECT * FROM job_proposals WHERE application_id=? ORDER BY version_number DESC'); $q->execute([$applicationId]); $versions=$q->fetchAll();
    $q=$pdo->prepare('SELECT m.*,u.full_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.application_id=? AND m.job_id=? ORDER BY m.created_at ASC'); $q->execute([$applicationId,$jobId]); $messages=$q->fetchAll();
    $q=$pdo->prepare('SELECT user_id FROM proposal_confirmations WHERE proposal_id=?'); $q->execute([(int)$thread['current_proposal_id']]); $confirmedUsers=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    $isCustomer=(int)$thread['customer_id']===$uid; $isSelected=(int)$thread['selected_application_id']===$applicationId;
    $current=null; foreach($versions as $v) if((int)$v['id']===(int)$thread['current_proposal_id']) {$current=$v;break;}
} else {
    if (!$jobId) failPage('Choose a job.',400);
    $q=$pdo->prepare('SELECT j.id,j.customer_id,j.category_id,j.title,j.description,j.location_address,j.estimated_price,j.budget_min,j.budget_max,j.budget_label,j.schedule_type,j.scheduled_date,j.preferred_time,j.status,j.selected_application_id,j.confirmation_window_hours,j.selection_expires_at,sc.name AS category_name FROM jobs j JOIN service_categories sc ON sc.id=j.category_id WHERE j.id=?'); $q->execute([$jobId]); $job=$q->fetch();
    if (!$job) failPage('Job not found.',404);
    if ($job['status']==='open' && $job['selected_application_id'] && $job['selection_expires_at'] && strtotime($job['selection_expires_at'])<=time()) {
        $pdo->beginTransaction(); resumeNegotiations($pdo,$jobId); $pdo->commit();
        header('Location: job-negotiation.php?job_id='.$jobId.'&expired=1'); exit;
    }
    $isCustomer=(int)$job['customer_id']===$uid;
    $q=$pdo->prepare('SELECT pp.id FROM provider_profiles pp JOIN users u ON u.id=pp.user_id WHERE pp.user_id=? AND u.is_active=1 AND u.user_type IN (\'provider\',\'both\')'); $q->execute([$uid]); $helperProfile=(int)($q->fetchColumn() ?: 0);
    if (!$isCustomer && !$helperProfile) failPage('This page is for the customer or a helper account.',403);
    if (!$isCustomer) {
        $existingOffer=$pdo->prepare('SELECT id FROM job_applications WHERE job_id=? AND provider_id=? LIMIT 1'); $existingOffer->execute([$jobId,$helperProfile]);
        $existingOfferId=(int)($existingOffer->fetchColumn() ?: 0);
        if ($existingOfferId) { header('Location: job-negotiation.php?application_id='.$existingOfferId); exit; }
    }
    if ($isCustomer) {
        $q=$pdo->prepare('SELECT a.*,pp.user_id AS helper_user_id,pp.business_name,pp.bio,pp.average_rating,pp.completed_jobs_count,u.full_name AS helper_name,u.profile_photo_url,p.proposed_price AS latest_price,p.scope_description,p.scheduled_date AS proposal_date,p.start_time,p.estimated_duration_minutes,p.materials_arrangement,p.additional_terms,p.version_number,p.status AS proposal_status FROM job_applications a JOIN provider_profiles pp ON pp.id=a.provider_id JOIN users u ON u.id=pp.user_id LEFT JOIN job_proposals p ON p.id=a.current_proposal_id WHERE a.job_id=? ORDER BY a.created_at DESC'); $q->execute([$jobId]); $offers=$q->fetchAll();
    } else $offers=[];
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Job negotiation — HandyLink</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/negotiation.css"></head><body>
<div class="app-shell"><?php include 'sidebar.php'; ?><div class="main"><?php include 'topBars.php'; ?><main class="content"><div class="max-width">
<?php if($error): ?><div class="neg-alert" role="alert"><?=nh($error)?></div><?php endif; ?><?php if($notice || isset($_GET['expired'])): ?><div class="neg-notice" role="status"><?=nh($notice ?: 'The confirmation deadline passed. Other offers are active again.')?></div><?php endif; ?>
<?php if($applicationId && $thread): ?>
  <a class="back-link" href="job-negotiation.php?job_id=<?=$jobId?>">← Back to offers</a><header class="neg-heading"><p class="page-eyebrow"><?=nh($thread['category_name'])?> · <?=nh($thread['location_address'])?></p><h1><?=nh($thread['title'])?></h1><p class="page-sub">Private negotiation with <?=nh($thread['helper_name'])?>. Only you and this helper can see this conversation.</p></header>
  <div class="neg-thread-grid"><section class="neg-panel"><div class="neg-panel-head"><h2>Proposal history</h2><span class="neg-status"><?=nh(ucwords(str_replace('_',' ',$thread['status'])))?></span></div>
  <?php foreach($versions as $v): ?><article class="proposal-version"><div class="version-head"><strong>Version <?= (int)$v['version_number'] ?></strong><span><?=nh(ucwords(str_replace('_',' ',$v['status'])))?></span></div><p class="proposal-price">$<?=number_format((float)$v['proposed_price'],2)?></p><dl><dt>Work scope</dt><dd><?=nl2br(nh($v['scope_description']))?></dd><dt>Date and start time</dt><dd><?=nh($v['scheduled_date'] ?: 'Flexible')?><?= $v['start_time'] ? ' · '.nh(date('g:i A',strtotime($v['start_time']))) : '' ?></dd><dt>Duration</dt><dd><?= $v['estimated_duration_minutes'] ? (int)$v['estimated_duration_minutes'].' minutes' : 'Not specified' ?></dd><dt>Materials</dt><dd><?=nl2br(nh($v['materials_arrangement'] ?: 'Not specified'))?></dd><dt>Other terms</dt><dd><?=nl2br(nh($v['additional_terms'] ?: 'None'))?></dd></dl></article><?php endforeach; ?>
  <?php if($thread['job_status']==='open' && !in_array($thread['status'],['declined','withdrawn','closed','expired'],true)): ?><form method="post" class="proposal-form"><h3><?= $current ? 'Make a counterproposal' : 'Propose terms' ?></h3><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="propose"><input type="hidden" name="application_id" value="<?=$applicationId?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><label>Price within customer budget<input type="number" min="0.01" step="0.01" name="price" required value="<?=nh($current['proposed_price'] ?? $thread['proposed_price'] ?? '')?>"></label><label>Work scope<textarea name="scope_description" required><?=nh($current['scope_description'] ?? $thread['description'])?></textarea></label><div class="neg-fields"><label>Date<input type="date" name="scheduled_date" value="<?=nh($current['scheduled_date'] ?? $thread['job_date'] ?? '')?>"></label><label>Start time<input type="time" name="start_time" value="<?=nh($current['start_time'] ?? $thread['job_time'] ?? '')?>"></label><label>Duration (minutes)<input type="number" min="1" max="10080" name="duration" value="<?=nh($current['estimated_duration_minutes'] ?? '')?>"></label></div><label>Materials arrangements<textarea name="materials"><?=nh($current['materials_arrangement'] ?? '')?></textarea></label><label>Additional conditions<textarea name="terms"><?=nh($current['additional_terms'] ?? '')?></textarea></label><button class="btn btn-primary" type="submit">Save new proposal version</button></form><?php endif; ?></section>
  <aside class="neg-side"><section class="neg-panel"><h2>Private conversation</h2><div class="chat-log" aria-live="polite"><?php foreach($messages as $m): ?><div class="chat-message <?=(int)$m['sender_id']===$uid?'mine':''?>"><strong><?=(int)$m['sender_id']===$uid?'You':nh($m['full_name'])?></strong><p><?=nl2br(nh($m['message_text']))?></p><time><?=nh(date('j M, g:i A',strtotime($m['created_at'])))?></time></div><?php endforeach; ?><?php if(!$messages): ?><p class="muted">No messages yet. Send a note to discuss the offer.</p><?php endif; ?></div><?php if($thread['job_status']==='open' && !in_array($thread['status'],['declined','withdrawn','closed'],true)): ?><form method="post" class="message-form"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="message"><input type="hidden" name="application_id" value="<?=$applicationId?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><textarea name="message" required maxlength="2000" placeholder="Write a private message..."></textarea><button class="btn btn-card" type="submit">Send message</button></form><?php endif; ?></section>
  <?php if($isSelected && $current): ?><section class="neg-panel agreement"><h2>Final agreement</h2><p class="muted">Confirming means you agree to all terms in version <?= (int)$current['version_number'] ?>.</p><?php if($current['status']==='ready'): ?><p>Customer: <strong><?=in_array((int)$thread['customer_id'],$confirmedUsers,true)?'Confirmed':'Waiting'?></strong></p><p>Helper: <strong><?=in_array((int)$thread['helper_user_id'],$confirmedUsers,true)?'Confirmed':'Waiting'?></strong></p><?php if($current['confirmation_deadline_at']): ?><p class="deadline" data-deadline="<?=nh(strtotime($current['confirmation_deadline_at']))?>">Confirmation time remaining: <strong class="countdown">...</strong></p><?php else: ?><p>No deadline was selected.</p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="application_id" value="<?=$applicationId?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><input type="hidden" name="proposal_id" value="<?=(int)$current['id']?>"><button class="btn btn-primary" type="submit">Confirm these terms</button></form><?php else: ?><p>Selection is provisional. The final confirmation timer has not started.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="ready"><input type="hidden" name="application_id" value="<?=$applicationId?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><button class="btn btn-primary" type="submit">Ready for confirmation</button></form><?php endif; ?></section><?php endif; ?>
  <?php if($thread['job_status']==='open'): ?><div class="neg-actions"><?php if($isCustomer): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="decline"><input type="hidden" name="application_id" value="<?=$applicationId?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><button class="btn btn-card" type="submit">Decline offer</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="withdraw"><input type="hidden" name="application_id" value="<?=$applicationId?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><button class="btn btn-card" type="submit">Withdraw offer</button></form><?php endif; ?></div><?php endif; ?></aside></div>
<?php else: ?>
  <?php if(!$isCustomer): ?><header class="neg-heading"><p class="page-eyebrow"><?=nh($job['category_name'])?> · <?=nh($job['location_address'])?></p><h1><?=nh($job['title'])?></h1><p class="page-sub"><?=nl2br(nh($job['description']))?></p></header><section class="neg-panel offer-submit"><h2>Send an offer</h2><p>Budget: <?=nh($job['budget_label'] ?: ('$'.number_format((float)$job['budget_min'],2).($job['budget_max']?'–$'.number_format((float)$job['budget_max'],2):'+')))?></p><form method="post"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="offer"><input type="hidden" name="job_id" value="<?=$jobId?>"><label>Proposed price<input type="number" min="0.01" step="0.01" name="price" required></label><label>Work scope<textarea name="scope_description" required><?=nh($job['description'])?></textarea></label><div class="neg-fields"><label>Date<input type="date" name="scheduled_date" value="<?=nh($job['scheduled_date'] ?? '')?>"></label><label>Start time<input type="time" name="start_time" value="<?=nh($job['preferred_time'] ?? '')?>"></label><label>Duration (minutes)<input type="number" min="1" max="10080" name="duration"></label></div><label>Materials<textarea name="materials"></textarea></label><label>Additional conditions<textarea name="terms"></textarea></label><label>Message to customer<textarea name="message" maxlength="1000"></textarea></label><button class="btn btn-primary" type="submit">Submit offer</button></form></section><?php else: ?>
  <header class="neg-heading"><p class="page-eyebrow"><?=nh($job['category_name'])?> · <?=nh($job['location_address'])?></p><h1><?=nh($job['title'])?></h1><p class="page-sub"><?=nl2br(nh($job['description']))?></p><div class="job-overview"><span>Budget: <?=nh($job['budget_label'] ?: ('$'.number_format((float)$job['budget_min'],2).($job['budget_max']?'–$'.number_format((float)$job['budget_max'],2):'+')))?></span><span>Schedule: <?=nh($job['schedule_type']==='flexible'?'Flexible':ucwords(str_replace('_',' ',$job['schedule_type'])))?></span><span>Status: <?=nh(ucwords(str_replace('_',' ',$job['status'])))?></span><span><?=count($offers)?> offer(s)</span></div></header>
  <?php if($job['selected_application_id']): ?><div class="neg-notice">A helper is provisionally selected. Other offers are paused.</div><?php endif; ?><div class="offer-grid"><?php foreach($offers as $offer): ?><article class="offer-card <?=$job['selected_application_id']==$offer['id']?'selected':''?>"><div class="helper-row"><?php if($offer['profile_photo_url']): ?><img class="helper-photo" src="<?=nh($offer['profile_photo_url'])?>" alt=""><?php else: ?><span class="helper-photo initials"><?=nh(strtoupper(substr($offer['helper_name'],0,1)))?></span><?php endif; ?><div><h2><?=nh($offer['business_name'] ?: $offer['helper_name'])?></h2><p><?=nh($offer['helper_name'])?></p></div></div><?php if($offer['latest_price']!==null): ?><p class="offer-price">$<?=number_format((float)$offer['latest_price'],2)?></p><p><?=nh($offer['scope_description'])?></p><p class="muted"><?=nh($offer['proposal_date'] ?: 'Flexible')?> <?= $offer['start_time']?'· '.nh(date('g:i A',strtotime($offer['start_time']))):'' ?><?= $offer['estimated_duration_minutes']?' · '.(int)$offer['estimated_duration_minutes'].' min':'' ?></p><p>Status: <?=nh($offer['proposal_status']==='ready'?'Awaiting confirmation':ucwords(str_replace('_',' ',$offer['status'])))?></p><?php else: ?><p class="muted">Offer has not been priced yet.</p><?php endif; ?><a class="btn btn-card" href="job-negotiation.php?application_id=<?=(int)$offer['id']?>">Open private negotiation</a><?php if(!$job['selected_application_id'] && in_array($offer['status'],['pending','submitted','negotiating'],true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=nh($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="select"><input type="hidden" name="application_id" value="<?=(int)$offer['id']?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><button class="btn btn-primary" type="submit">Select provisionally</button></form><?php endif; ?></article><?php endforeach; ?><?php if(!$offers): ?><p class="muted">No offers yet. Helpers will appear here when they respond.</p><?php endif; ?></div><?php endif; ?>
<?php endif; ?>
</div></main></div></div><script src="js/icons.js"></script><script src="js/shell.js"></script><script src="js/negotiation.js"></script></body></html>
