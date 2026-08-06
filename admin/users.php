<?php
require_once '../config.php';
if (!isAdmin()) { redirect('../index.php'); }

$msg = '';

// ---- Toggle user status (block/activate) ----
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM users WHERE id = $uid AND user_type='customer'"));
    if ($current) {
        $newStatus = ($current['status'] == 'active') ? 'blocked' : 'active';
        mysqli_query($conn, "UPDATE users SET status = '$newStatus' WHERE id = $uid");
        $msg = "User status updated.";
    }
}

// ---- Delete user ----
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    // Delete related data
    mysqli_query($conn, "DELETE FROM wallet WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM orders WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM notifications WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM contact_messages WHERE user_id = $uid");
    mysqli_query($conn, "DELETE FROM users WHERE id = $uid AND user_type='customer'");
    $msg = "User deleted.";
}

$users = mysqli_query($conn, "SELECT id, full_name, phone, email, created_at, status FROM users WHERE user_type='customer' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users – HelpGo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:20px; }
        .container { max-width:900px; margin:auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:25px; margin-bottom:20px; backdrop-filter:blur(15px); }
        h2 { margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid var(--border); text-align:left; }
        th { color:var(--primary); }
        .badge { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .active { background:rgba(46,213,115,0.2); color:#2ED573; }
        .blocked { background:rgba(255,71,87,0.2); color:#FF4757; }
        .actions a { margin:0 5px; text-decoration:none; }
        .btn-sm { padding:5px 12px; border-radius:8px; font-size:12px; border:none; cursor:pointer; color:#fff; }
        .btn-toggle { background:var(--primary); }
        .btn-delete { background:#FF4757; }
        .msg { color:var(--primary); margin-bottom:15px; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" style="color:var(--primary); margin-bottom:20px; display:block;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <div class="card">
        <h2>👥 Customer Management</h2>
        <?php if ($msg): ?><p class="msg"><?= $msg ?></p><?php endif; ?>
        <table>
            <tr>
                <th>Name</th><th>Phone</th><th>Email</th><th>Joined</th><th>Status</th><th>Actions</th>
            </tr>
            <?php while ($u = mysqli_fetch_assoc($users)): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= $u['phone'] ?></td>
                <td><?= $u['email'] ?: '—' ?></td>
                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td><span class="badge <?= $u['status'] == 'active' ? 'active' : 'blocked' ?>"><?= ucfirst($u['status']) ?></span></td>
                <td class="actions">
                    <a href="?toggle&id=<?= $u['id'] ?>" class="btn-sm btn-toggle"><?= $u['status']=='active' ? 'Block' : 'Activate' ?></a>
                    <a href="?delete&id=<?= $u['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete user and all their data?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>