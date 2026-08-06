<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

// Mark as read/resolve
if (isset($_GET['read']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    mysqli_query($conn, "UPDATE contact_messages SET is_read=1, replied=1 WHERE id=$id");
}
$complaints = mysqli_query($conn, "SELECT * FROM contact_messages ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complaints – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px; }
        .container { max-width:800px; margin:auto; }
        .msg-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; margin-bottom:15px; }
        .unread { border-left:4px solid var(--primary); }
        .actions a { color:var(--primary); margin-right:15px; text-decoration:none; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" style="color:var(--primary);"><i class="fas fa-arrow-left"></i> Back</a>
    <h2>User Complaints / Messages</h2>
    <?php while($c = mysqli_fetch_assoc($complaints)): ?>
        <div class="msg-card <?= $c['is_read'] ? '' : 'unread' ?>">
            <strong><?= htmlspecialchars($c['name']) ?></strong> (<?= $c['email'] ?: $c['phone'] ?>)<br>
            <small><?= $c['created_at'] ?></small>
            <p><?= nl2br(htmlspecialchars($c['message'])) ?></p>
            <div class="actions">
                <?php if (!$c['is_read']): ?><a href="?read&id=<?= $c['id'] ?>"><i class="fas fa-check"></i> Mark Resolved</a><?php else: ?><span style="color:#2ED573;">Resolved</span><?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>