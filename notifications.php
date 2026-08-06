<?php
// notifications.php – Customer Notifications (Emerald Prestige theme)
require_once __DIR__ . '/config.php';

if (!isLoggedIn()) { redirect(SITE_URL . 'login.php'); }
$uid = (int)$_SESSION['user_id'];

// Helper function (if not defined)
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Mark all as read (AJAX or via query param)
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $uid AND is_read = 0");
    // Redirect to remove the action from URL
    redirect(SITE_URL . 'notifications.php');
}

// Mark single as read (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    $id = (int)$_POST['mark_read_id'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = $id AND user_id = $uid");
    echo json_encode(['success' => true]);
    exit;
}

// Fetch notifications
$notifications = [];
$res = mysqli_query($conn, "
    SELECT id, title, message, is_read, created_at
    FROM notifications
    WHERE user_id = $uid
    ORDER BY id DESC
    LIMIT 50
");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $notifications[] = $row;
    }
}

// Count unread
$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) $unreadCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --emerald: #083C33;
            --emerald-light: #0E5548;
            --emerald-dark: #04261F;
            --gold: #D4AF37;
            --gold-light: #E8C84A;
            --gold-dark: #B8962E;
            --white: #FFFFFF;
            --gray-soft: #AEB8B2;
            --gray-muted: #6B7A73;
            --glass-bg: rgba(8, 60, 51, 0.6);
            --glass-border: rgba(212, 175, 55, 0.2);
            --shadow-glass: 0 20px 50px rgba(0, 0, 0, 0.4);
            --radius-card: 24px;
            --radius-btn: 16px;
            --font: 'Poppins', sans-serif;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            background: radial-gradient(ellipse at 20% 0%, var(--emerald-light) 0%, var(--emerald-dark) 70%, var(--emerald) 100%);
            color: var(--white);
            display: flex;
            justify-content: center;
            min-height: 100vh;
            padding: 20px 16px 60px;
            overflow-x: hidden;
            position: relative;
        }

        /* Floating background orbs */
        .bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(130px);
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 20s infinite alternate;
        }
        .bg-orb:nth-child(1) { width: 500px; height: 500px; background: var(--gold); top: -200px; right: -150px; }
        .bg-orb:nth-child(2) { width: 350px; height: 350px; background: var(--gold); bottom: -100px; left: -120px; animation-delay: -10s; }
        @keyframes orbFloat { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(40px, -30px) scale(1.1); } }

        .container { width: 100%; max-width: 500px; position: relative; z-index: 2; }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .back-btn {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .back-btn:hover { background: rgba(212,175,55,0.15); border-color: var(--gold); }
        .header h1 { font-size: 28px; font-weight: 800; color: var(--white); }
        .header h1 span { color: var(--gold); }
        .mark-all-btn {
            padding: 10px 20px;
            border-radius: 50px;
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            color: var(--gold);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            white-space: nowrap;
        }
        .mark-all-btn:hover { background: rgba(212,175,55,0.15); border-color: var(--gold); box-shadow: 0 0 20px rgba(212,175,55,0.3); }

        /* Notification card */
        .notification-list { display: flex; flex-direction: column; gap: 12px; }
        .notif-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--shadow-glass);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .notif-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .notif-card.unread { border-left: 4px solid var(--gold); }
        .notif-card.read { opacity: 0.7; }
        .notif-icon {
            width: 44px; height: 44px;
            border-radius: 14px;
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--gold);
            flex-shrink: 0;
        }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 700; font-size: 15px; margin-bottom: 4px; color: var(--white); }
        .notif-message { font-size: 14px; color: var(--gray-soft); line-height: 1.4; }
        .notif-time { font-size: 11px; color: var(--gray-muted); margin-top: 8px; display: flex; align-items: center; gap: 8px; }
        .notif-time .unread-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--gold);
            display: inline-block;
        }
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-card);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glass);
            margin-top: 20px;
        }
        .empty-state i { font-size: 60px; color: var(--gold); opacity: 0.5; margin-bottom: 16px; }
        .empty-state h3 { font-size: 20px; font-weight: 700; color: var(--white); margin-bottom: 8px; }
        .empty-state p { color: var(--gray-soft); }

        /* Responsive */
        @media (max-width: 400px) {
            .header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="home.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1><span>Notifications</span></h1>
            <?php if ($unreadCount > 0): ?>
                <a href="notifications.php?action=mark_all_read" class="mark-all-btn">Mark All Read</a>
            <?php endif; ?>
        </div>

        <!-- Notification List -->
        <div class="notification-list" id="notifList">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! We'll notify you when something new arrives.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    $isUnread = $notif['is_read'] == 0;
                    $icon = 'fa-bell';
                    if (stripos($notif['title'], 'payment') !== false) $icon = 'fa-credit-card';
                    elseif (stripos($notif['title'], 'order') !== false) $icon = 'fa-box';
                    elseif (stripos($notif['title'], 'rider') !== false) $icon = 'fa-motorcycle';
                ?>
                    <div class="notif-card <?= $isUnread ? 'unread' : 'read' ?>" data-id="<?= $notif['id'] ?>" onclick="markAsRead(<?= $notif['id'] ?>, this)">
                        <div class="notif-icon">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div class="notif-content">
                            <div class="notif-title"><?= h($notif['title']) ?></div>
                            <div class="notif-message"><?= nl2br(h($notif['message'])) ?></div>
                            <div class="notif-time">
                                <?php if ($isUnread): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                                <?= date('d M, h:i A', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mark single notification as read
        function markAsRead(id, element) {
            // If already read, do nothing
            if (!element.classList.contains('unread')) return;

            // Send AJAX request
            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_read_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    element.classList.remove('unread');
                    element.classList.add('read');
                    const dot = element.querySelector('.unread-dot');
                    if (dot) dot.style.display = 'none';
                }
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>