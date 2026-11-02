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

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

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
        'SELECT id, full_name, phone_number, email, user_type
         FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: auth.php?mode=login');
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT bio, business_name, is_business
         FROM provider_profiles WHERE user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $provider = $stmt->fetch() ?: [
        'bio' => '',
        'business_name' => '',
        'is_business' => 0
    ];

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )) {
            $error = 'Your session expired. Refresh the page and try again.';
        } else {
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $type = $_POST['user_type'] ?? '';
            $bio = trim($_POST['bio'] ?? '');
            $businessName = trim($_POST['business_name'] ?? '');
            $isBusiness = isset($_POST['is_business']) ? 1 : 0;

            if (!in_array($type, ['customer', 'provider', 'both'], true)) {
                $error = 'Choose a valid account type.';
            } elseif ($name === '' || mb_strlen($name) > 100) {
                $error = 'Enter your name (up to 100 characters).';
            } elseif (
                $email !== '' &&
                (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    mb_strlen($email) > 150)
            ) {
                $error = 'Enter a valid email address.';
            } elseif (mb_strlen($bio) > 2000) {
                $error = 'Your description must be 2,000 characters or fewer.';
            } elseif (mb_strlen($businessName) > 150) {
                $error = 'Your business name is too long.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users SET full_name = ?, email = ?, user_type = ? WHERE id = ?'
                );
                $stmt->execute([
                    $name,
                    $email !== '' ? $email : null,
                    $type,
                    $userId
                ]);

                if (in_array($type, ['provider', 'both'], true)) {
                    $stmt = $pdo->prepare(
                        'SELECT id FROM provider_profiles WHERE user_id = ? LIMIT 1'
                    );
                    $stmt->execute([$userId]);
                    $profileExists = $stmt->fetch();

                    if ($profileExists) {
                        $stmt = $pdo->prepare(
                            'UPDATE provider_profiles
                             SET bio = ?, business_name = ?, is_business = ?
                             WHERE user_id = ?'
                        );
                        $stmt->execute([
                            $bio,
                            $businessName !== '' ? $businessName : null,
                            $isBusiness,
                            $userId
                        ]);
                        $providerProfileId = (int)$profileExists['id'];
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO provider_profiles
                             (user_id, bio, business_name, is_business)
                             VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $userId,
                            $bio,
                            $businessName !== '' ? $businessName : null,
                            $isBusiness
                        ]);
                        $providerProfileId = (int)$pdo->lastInsertId();
                    }
                    $walletStmt = $pdo->prepare('INSERT IGNORE INTO wallets (provider_id, credit_balance) VALUES (?, 0)');
                    $walletStmt->execute([$providerProfileId]);
                }

                $_SESSION['user_name'] = $name;
                $success = 'Your profile has been updated.';

                $user['full_name'] = $name;
                $user['email'] = $email;
                $user['user_type'] = $type;
                $_SESSION['user_type'] = $type;
                $provider['bio'] = $bio;
                $provider['business_name'] = $businessName;
                $provider['is_business'] = $isBusiness;
            }
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'We could not load or save your profile. Check your database connection and try again.';
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$typeLabels = [
    'customer' => 'Find help',
    'provider' => 'Offer help',
    'both' => 'Find and offer help'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — HandyLink</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .profile-wrap {
            max-width: 850px;
            margin: 0 auto;
        }

        .profile-heading {
            margin-bottom: 25px;
        }

        .profile-heading h1 {
            margin: 8px 0;
        }

        .profile-card {
            background: #fff;
            border: 1px solid #e4eaf3;
            border-radius: 18px;
            padding: 28px;
            margin-bottom: 22px;
        }

        .profile-identity {
            display: flex;
            align-items: center;
            gap: 17px;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #1747a6;
            color: white;
            display: grid;
            place-items: center;
            font-size: 23px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .profile-identity h2 {
            margin: 0 0 5px;
        }

        .profile-identity p {
            margin: 0;
            color: #69758a;
            font-size: 14px;
        }

        .profile-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .profile-field {
            margin-bottom: 18px;
        }

        .profile-field label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
            color: #34425a;
        }

        .profile-field input,
        .profile-field textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #dfe6f0;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            box-sizing: border-box;
        }

        .profile-field textarea {
            min-height: 115px;
            resize: vertical;
        }

        .profile-field input:focus,
        .profile-field textarea:focus {
            outline: 2px solid #1747a625;
            border-color: #1747a6;
        }

        .profile-field input[readonly] {
            background: #f4f6fa;
            color: #69758a;
        }

        .profile-note {
            font-size: 12px;
            color: #69758a;
            margin-top: 6px;
        }

        .profile-check {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 5px 0 20px;
        }

        .profile-check label {
            font-size: 14px;
        }

        .profile-alert {
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .profile-alert.error {
            background: #fff0ef;
            color: #a52c28;
        }

        .profile-alert.success {
            background: #edf8f0;
            color: #246a3c;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .profile-save {
            background: #1747a6;
            color: white;
            border: 0;
            padding: 13px 20px;
            border-radius: 10px;
            font-weight: 750;
            cursor: pointer;
        }

        .profile-save:hover {
            background: #10377f;
        }

        .profile-cancel {
            display: inline-flex;
            align-items: center;
            padding: 13px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #34425a;
            background: #f1f4fa;
        }

        @media(max-width:600px) {
            .profile-card {
                padding: 20px;
            }

            .profile-form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>

<body>
    <div class="app-shell">
        <?php include 'sidebar.php'; ?>

        <div class="main">
            <?php include 'topBars.php'; ?>

            <main class="content">
                <div class="profile-wrap">
                    <div class="profile-heading">
                        <span class="page-eyebrow">YOUR ACCOUNT</span>
                        <h1 class="page-title">My profile</h1>
                        <p class="page-sub">Keep your HandyLink information up to date.</p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="profile-alert error"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success !== ''): ?>
                        <div class="profile-alert success"><?= e($success) ?></div>
                    <?php endif; ?>

                    <?php if (isset($user)): ?>
                        <form method="post" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                            <section class="profile-card">
                                <div class="profile-identity">
                                    <div class="profile-avatar">
                                        <?php
                                        $parts = preg_split('/\s+/', trim($user['full_name'] ?? 'Neighbor'));
                                        $initials = '';
                                        foreach (array_slice($parts, 0, 2) as $part) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                        }
                                        echo e($initials ?: 'N');
                                        ?>
                                    </div>
                                    <div>
                                        <h2><?= e($user['full_name'] ?: 'HandyLink member') ?></h2>
                                        <p><?= e($typeLabels[$user['user_type']] ?? 'HandyLink member') ?></p>
                                    </div>
                                </div>

                                <div class="profile-form-grid">
                                    <div class="profile-field">
                                        <label for="full_name">Full name</label>
                                        <input id="full_name" name="full_name" maxlength="100"
                                            value="<?= e($user['full_name']) ?>" required>
                                    </div>

                                    <div class="profile-field">
                                        <label for="phone_number">Phone number</label>
                                        <input id="phone_number" value="<?= e($user['phone_number']) ?>" readonly>
                                        <p class="profile-note">Contact support if you need to change this.</p>
                                    </div>
                                </div>

                                <div class="profile-field">
                                    <label for="user_type">How you use HandyLink</label>
                                    <select id="user_type" name="user_type">
                                        <option value="both" <?= $user['user_type'] === 'both' ? 'selected' : '' ?>>Find and offer help</option>
                                        <option value="customer" <?= $user['user_type'] === 'customer' ? 'selected' : '' ?>>Find help</option>
                                        <option value="provider" <?= $user['user_type'] === 'provider' ? 'selected' : '' ?>>Offer help</option>
                                    </select>
                                    <p class="profile-note">You can change this whenever you need. Choosing “Find and offer help” enables Express interest on open jobs.</p>
                                </div>

                                <div class="profile-field">
                                    <label for="email">Email address</label>
                                    <input id="email" name="email" type="email" maxlength="150"
                                        value="<?= e($user['email']) ?>" placeholder="you@example.com">
                                </div>

                                <?php if (in_array($user['user_type'], ['provider', 'both'], true)): ?>
                                    <hr style="border:0;border-top:1px solid #e7ecf4;margin:25px 0">

                                    <h2 style="margin-top:0">Helper profile</h2>
                                    <p class="profile-note" style="margin-bottom:18px">
                                        Tell your neighbors about the services you offer.
                                    </p>

                                    <div class="profile-field">
                                        <label for="bio">About me and my skills</label>
                                        <textarea id="bio" name="bio" maxlength="2000"
                                            placeholder="Describe your experience and the help you can offer..."><?= e($provider['bio']) ?></textarea>
                                    </div>

                                    <div class="profile-field">
                                        <label for="business_name">Business name (optional)</label>
                                        <input id="business_name" name="business_name" maxlength="150"
                                            value="<?= e($provider['business_name']) ?>"
                                            placeholder="Your business name">
                                    </div>

                                    <div class="profile-check">
                                        <input type="checkbox" id="is_business" name="is_business" value="1"
                                            <?= !empty($provider['is_business']) ? 'checked' : '' ?>>
                                        <label for="is_business">I operate as a business</label>
                                    </div>
                                <?php endif; ?>

                                <div class="profile-actions">
                                    <button class="profile-save" type="submit">Save changes</button>
                                    <a class="profile-cancel" href="index.php">Cancel</a>
                                </div>
                            </section>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
