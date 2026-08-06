<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$reviews = mysqli_query($conn, "SELECT r.*, u.full_name as user_name, rd.full_name as rider_name 
    FROM ratings r 
    JOIN users u ON r.user_id=u.id 
    JOIN riders ri ON r.rider_id=ri.user_id 
    JOIN users rd ON ri.user_id=rd.id 
    ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rider Reviews – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.9); --border:rgba(255,255,255,0.06); --text:#fff; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px; }
        .container { max-width:800px; margin:auto; }
        .review-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; margin-bottom:15px; }
        .stars { color:#FFA502; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" style="color:var(--primary);"><i class="fas fa-arrow-left"></i> Back</a>
    <h2>Rider Reviews</h2>
    <?php while ($rev = mysqli_fetch_assoc($reviews)): ?>
        <div class="review-card">
            <strong><?= htmlspecialchars($rev['rider_name']) ?></strong> rated by <?= htmlspecialchars($rev['user_name']) ?><br>
            <div class="stars">
                <?= str_repeat('<i class="fas fa-star"></i>', $rev['rating']) ?><?= str_repeat('<i class="far fa-star"></i>', 5 - $rev['rating']) ?>
            </div>
            <p><?= htmlspecialchars($rev['review']) ?></p>
            <small><?= $rev['created_at'] ?></small>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>