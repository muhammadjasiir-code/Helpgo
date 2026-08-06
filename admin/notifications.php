<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }
$msg = '';
if (isset($_POST['send'])) {
    $title   = sanitize($_POST['title']);
    $message = sanitize($_POST['message']);
    $target  = $_POST['target']; // all, customers, riders, single
    $singlePhone = sanitize($_POST['single_phone'] ?? '');

    if ($target == 'single' && !empty($singlePhone)) {
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE phone='$singlePhone'"));
        if ($user) {
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ({$user['id']}, '$title', '$message')");
        }
    } elseif ($target == 'customers') {
        $users = mysqli_query($conn, "SELECT id FROM users WHERE user_type='customer'");
        while ($u = mysqli_fetch_assoc($users)) {
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ({$u['id']}, '$title', '$message')");
        }
    } elseif ($target == 'riders') {
        $riders = mysqli_query($conn, "SELECT id FROM users WHERE user_type='rider'");
        while ($u = mysqli_fetch_assoc($riders)) {
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ({$u['id']}, '$title', '$message')");
        }
    } elseif ($target == 'all') {
        $all = mysqli_query($conn, "SELECT id FROM users WHERE user_type IN ('customer','rider')");
        while ($u = mysqli_fetch_assoc($all)) {
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ({$u['id']}, '$title', '$message')");
        }
    }
    $msg = "Notification sent!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Notification – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px; }
        .container { max-width:600px; margin:auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:25px; }
        input, textarea, select { width:100%; padding:12px; margin:10px 0; border-radius:10px; border:1px solid var(--border); background:rgba(255,255,255,0.05); color:#fff; }
        .btn { background:var(--primary); color:#fff; padding:12px 25px; border:none; border-radius:10px; cursor:pointer; }
        .back { color:var(--primary); text-decoration:none; margin-bottom:20px; display:inline-block; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back</a>
    <h2>Send Push Notification</h2>
    <?php if ($msg): ?><p style="color:var(--primary);"><?= $msg ?></p><?php endif; ?>
    <form method="POST">
        <label>Title</label><input type="text" name="title" required>
        <label>Message</label><textarea name="message" rows="3" required></textarea>
        <label>Target</label>
        <select name="target" id="target" onchange="document.getElementById('singlePhone').style.display=(this.value=='single'?'block':'none')">
            <option value="all">All Users</option>
            <option value="customers">Customers</option>
            <option value="riders">Riders</option>
            <option value="single">Single User (by phone)</option>
        </select>
        <input type="text" name="single_phone" id="singlePhone" placeholder="Enter phone number" style="display:none;">
        <button type="submit" name="send" class="btn"><i class="fas fa-paper-plane"></i> Send</button>
    </form>
</div>
</body>
</html>