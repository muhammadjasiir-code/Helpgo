<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

// Approve/Reject action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $riderId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Update verification status & set user as rider
        mysqli_query($conn, "UPDATE riders SET verification_status = 'approved' WHERE user_id = $riderId");
        mysqli_query($conn, "UPDATE users SET user_type = 'rider' WHERE id = $riderId");
    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE riders SET verification_status = 'rejected' WHERE user_id = $riderId");
    }
    header("Location: verification.php");
    exit;
}

// Fetch pending applications
$pendingRiders = mysqli_query($conn, "
    SELECT u.id, u.full_name, u.phone, r.vehicle_type, r.vehicle_number, 
           r.license_number, r.aadhaar_file, r.license_file, r.pan_file, r.selfie_file, 
           r.dob, r.verification_status 
    FROM users u 
    JOIN riders r ON u.id = r.user_id 
    WHERE r.verification_status = 'pending' 
    ORDER BY r.user_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rider Verification – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.95); --border:rgba(255,255,255,0.08); --text:#fff; --danger:#FF4757; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); padding:30px 20px; }
        .container { max-width:1000px; margin:0 auto; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:20px; margin-bottom:25px; backdrop-filter:blur(15px); }
        h2 { margin-bottom:20px; }
        .rider-header { margin-bottom:15px; }
        .docs { display:flex; flex-wrap:wrap; gap:20px; margin:15px 0; }
        .doc-box { border:1px solid var(--border); border-radius:12px; padding:10px; text-align:center; width:160px; background:rgba(255,255,255,0.03); }
        .doc-box img { max-width:100%; max-height:150px; border-radius:8px; object-fit:cover; }
        .doc-box p { font-size:12px; color:#aaa; margin-top:6px; }
        .btn-group { margin-top:15px; }
        .btn { padding:10px 25px; border-radius:8px; font-weight:600; cursor:pointer; border:none; color:#fff; }
        .btn-approve { background:var(--primary); }
        .btn-reject { background:var(--danger); }
        .back { color:var(--primary); text-decoration:none; display:inline-block; margin-bottom:20px; }
        .no-image { height:120px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.05); border-radius:8px; color:#555; flex-direction:column; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>📋 Pending Rider Verifications</h2>

    <?php if (mysqli_num_rows($pendingRiders) == 0): ?>
        <div class="card">No pending applications.</div>
    <?php endif; ?>

    <?php while ($r = mysqli_fetch_assoc($pendingRiders)): ?>
        <div class="card">
            <div class="rider-header">
                <strong style="font-size:18px;"><?= htmlspecialchars($r['full_name']) ?></strong>
                (<?= $r['phone'] ?>) – <?= $r['vehicle_type'] ?><br>
                <small>DOB: <?= $r['dob'] ? date('d M Y', strtotime($r['dob'])) : '—' ?></small>
            </div>

            <div class="docs">
                <?php
                $docs = [
                    'Aadhaar' => $r['aadhaar_file'],
                    'Selfie'  => $r['selfie_file'],
                    'PAN'     => $r['pan_file'],
                    'License' => $r['license_file'],
                ];
                foreach ($docs as $label => $file):
                    if (empty($file)) continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg','jpeg','png']);
                    $proxyUrl = 'image.php?file=' . rawurlencode($file);
                ?>
                <div class="doc-box">
                    <?php if ($isImage): ?>
                        <img src="<?= $proxyUrl ?>" alt="<?= $label ?>">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-file-pdf" style="font-size:28px; color:var(--primary);"></i>
                            <p>PDF Document</p>
                        </div>
                    <?php endif; ?>
                    <p><?= $label ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="btn-group">
                <a href="?action=approve&id=<?= $r['id'] ?>" class="btn btn-approve">✅ Approve</a>
                <a href="?action=reject&id=<?= $r['id'] ?>" class="btn btn-reject" onclick="return confirm('Reject this rider?')">❌ Reject</a>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>