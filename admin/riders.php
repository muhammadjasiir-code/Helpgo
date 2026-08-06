<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$msg = '';

// ----- Add Rider -----
if (isset($_POST['add_rider'])) {
    $name        = sanitize($_POST['name']);
    $phone       = sanitize($_POST['phone']);
    $password    = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $vehicle     = sanitize($_POST['vehicle_type']);
    $veh_num     = sanitize($_POST['vehicle_number'] ?? '');
    $license_num = sanitize($_POST['license_number'] ?? '');

    $check = mysqli_query($conn, "SELECT id FROM users WHERE phone = '$phone'");
    if (mysqli_num_rows($check) > 0) {
        $msg = "Phone already registered.";
    } else {
        $aadhaarPath = '';
        $licensePath = '';
        $panPath     = '';
        $selfiePath  = '';

        if (!empty($_FILES['aadhaar_file']['name'])) {
            $aadhaarPath = uploadFile('aadhaar_file');
        } else { $msg = "Aadhaar card is required."; }

        if ($vehicle != 'Bicycle') {
            if (!empty($_FILES['license_file']['name'])) {
                $licensePath = uploadFile('license_file');
            } else { $msg = "Driving license is required for Scooter."; }
        }

        if (!empty($_FILES['pan_file']['name'])) {
            $panPath = uploadFile('pan_file');
        }

        if (!empty($_FILES['selfie_file']['name'])) {
            $selfiePath = uploadFile('selfie_file');
        } else { $msg = "Selfie is required."; }

        if (!$msg) {
            $ins = mysqli_query($conn, "INSERT INTO users (full_name, phone, password, user_type) VALUES ('$name', '$phone', '$password', 'rider')");
            if ($ins) {
                $uid = mysqli_insert_id($conn);
                mysqli_query($conn, "INSERT INTO riders (user_id, vehicle_type, vehicle_number, license_number, aadhaar_file, license_file, pan_file, selfie_file) 
                                     VALUES ($uid, '$vehicle', '$veh_num', '$license_num', '$aadhaarPath', '$licensePath', '$panPath', '$selfiePath')");
                mysqli_query($conn, "INSERT INTO wallet (user_id, balance) VALUES ($uid, 0)");
                $msg = "Rider added successfully!";
            } else {
                $msg = "Database error while creating rider.";
            }
        }
    }
}

// ----- Delete Rider -----
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $docs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT aadhaar_file, license_file, pan_file, selfie_file FROM riders WHERE user_id = $delId"));
    if ($docs) {
        foreach (['aadhaar_file', 'license_file', 'pan_file', 'selfie_file'] as $col) {
            if (!empty($docs[$col]) && file_exists(UPLOAD_DIR . 'riders/' . $docs[$col])) {
                unlink(UPLOAD_DIR . 'riders/' . $docs[$col]);
            }
        }
    }
    mysqli_query($conn, "DELETE FROM riders WHERE user_id = $delId");
    mysqli_query($conn, "DELETE FROM wallet WHERE user_id = $delId");
    mysqli_query($conn, "DELETE FROM users WHERE id = $delId");
    header("Location: riders.php");
    exit;
}

// ----- File Upload Helper -----
function uploadFile($inputName) {
    $file = $_FILES[$inputName];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed)) return '';
    $newName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . 'riders/' . $newName;
    if (move_uploaded_file($file['tmp_name'], $dest)) return $newName;
    return '';
}

// ----- Fetch all riders (including those who applied via profile) -----
$riders = mysqli_query($conn, "
    SELECT u.id, u.full_name, u.phone, r.vehicle_type, r.vehicle_number, 
           r.license_number, r.aadhaar_file, r.license_file, r.pan_file, r.selfie_file, 
           r.verification_status, r.status
    FROM users u 
    JOIN riders r ON u.id = r.user_id 
    ORDER BY u.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Riders – HelpGo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary: #FF6B35; --bg: #0A0A0A; --card: rgba(20,20,20,0.9); --border: rgba(255,255,255,0.06); --text: #fff; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        .card { background: var(--card); backdrop-filter:blur(15px); border:1px solid var(--border); border-radius:20px; padding:25px; margin-bottom:20px; }
        h2 { margin-bottom:20px; }
        .input-group { margin-bottom:15px; }
        .input-group label { display:block; font-size:13px; color:#aaa; margin-bottom:5px; }
        .input-group input, .input-group select { width:100%; padding:12px; border-radius:10px; border:1px solid var(--border); background:rgba(255,255,255,0.05); color:#fff; font-size:15px; }
        .btn { background:var(--primary); color:#fff; padding:12px 25px; border:none; border-radius:10px; font-weight:600; cursor:pointer; }
        table { width:100%; border-collapse:collapse; margin-top:15px; font-size:14px; }
        th, td { padding:10px; border-bottom:1px solid var(--border); text-align:left; }
        .delete { color: #FF4757; }
        .preview-img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border:1px solid var(--border); margin-right: 3px; transition: transform 0.2s; }
        .preview-img:hover { transform: scale(1.1); }
        .badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 600; text-transform: capitalize;
        }
        .badge-approved { background: rgba(46,213,115,0.2); color: #2ED573; }
        .badge-pending { background: rgba(255,165,2,0.2); color: #FFA502; }
        .badge-rejected { background: rgba(255,71,87,0.2); color: #FF4757; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" style="color:var(--primary); margin-bottom:20px; display:block;"><i class="fas fa-arrow-left"></i> Back</a>

    <div class="card">
        <h2>Add New Rider</h2>
        <?php if ($msg): ?><p style="color:var(--primary); margin-bottom:15px;"><?= $msg ?></p><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" id="riderForm">
            <div class="input-group"><label>Full Name *</label><input type="text" name="name" required></div>
            <div class="input-group"><label>Phone *</label><input type="tel" name="phone" maxlength="10" required></div>
            <div class="input-group"><label>Password *</label><input type="password" name="password" required></div>
            <div class="input-group">
                <label>Vehicle Type *</label>
                <select name="vehicle_type" id="vehicle_type" required>
                    <option value="Bicycle">Bicycle</option>
                    <option value="Scooter" selected>Scooter / Motorcycle</option>
                </select>
            </div>
            <div class="input-group"><label>Vehicle Number</label><input type="text" name="vehicle_number" placeholder="e.g. KL07AB1234"></div>
            <div class="input-group" id="license_number_group"><label>Driving License Number</label><input type="text" name="license_number" placeholder="DL number"></div>
            <div class="input-group"><label>Aadhaar Card * (jpg, png, pdf)</label><input type="file" name="aadhaar_file" accept=".jpg,.jpeg,.png,.pdf" required></div>
            <div class="input-group" id="license_file_group"><label>Driving License * (jpg, png, pdf)</label><input type="file" name="license_file" accept=".jpg,.jpeg,.png,.pdf" id="license_file_input"></div>
            <div class="input-group"><label>PAN Card (optional)</label><input type="file" name="pan_file" accept=".jpg,.jpeg,.png,.pdf"></div>
            <div class="input-group"><label>Selfie / Photo *</label><input type="file" name="selfie_file" accept=".jpg,.jpeg,.png" required></div>
            <button type="submit" name="add_rider" class="btn">Add Rider</button>
        </form>
    </div>

    <div class="card">
        <h2>All Riders</h2>
        <table>
            <tr>
                <th>Name</th><th>Phone</th><th>Vehicle</th><th>Documents</th><th>Verification</th><th>Status</th><th>Action</th>
            </tr>
            <?php while ($r = mysqli_fetch_assoc($riders)): ?>
            <tr>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= $r['phone'] ?></td>
                <td><?= $r['vehicle_type'] ?> (<?= $r['vehicle_number'] ?>)</td>
                <td style="display:flex; gap:4px; flex-wrap:wrap;">
                    <?php
                    $docs = [
                        'Aadhaar' => $r['aadhaar_file'],
                        'License' => $r['license_file'],
                        'PAN'     => $r['pan_file'],
                        'Selfie'  => $r['selfie_file']
                    ];
                    foreach ($docs as $label => $file):
                        if (empty($file)) continue;
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg','jpeg','png']);
                        $proxyUrl = 'image.php?file=' . rawurlencode($file);
                    ?>
                        <a href="<?= $proxyUrl ?>" target="_blank" title="<?= $label ?>">
                            <?php if ($isImage): ?>
                                <img src="<?= $proxyUrl ?>" class="preview-img" alt="<?= $label ?>">
                            <?php else: ?>
                                <div style="width:50px;height:50px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-file-pdf" style="color:var(--primary);"></i>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php
                    $vStatus = $r['verification_status'] ?? 'pending';
                    $badgeClass = 'badge-pending';
                    if ($vStatus == 'approved') $badgeClass = 'badge-approved';
                    elseif ($vStatus == 'rejected') $badgeClass = 'badge-rejected';
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($vStatus) ?></span>
                </td>
                <td><?= $r['status'] ?? '—' ?></td>
                <td><a href="?delete=<?= $r['id'] ?>" class="delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
            </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($riders) == 0): ?>
                <tr><td colspan="7" style="text-align:center; padding:20px;">No riders found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
    const vehicleSelect = document.getElementById('vehicle_type');
    const licenseNumberGroup = document.getElementById('license_number_group');
    const licenseFileGroup = document.getElementById('license_file_group');
    const licenseFileInput = document.getElementById('license_file_input');

    function toggleLicenseFields() {
        if (vehicleSelect.value === 'Bicycle') {
            licenseNumberGroup.style.display = 'none';
            licenseFileGroup.style.display = 'none';
            licenseFileInput.required = false;
        } else {
            licenseNumberGroup.style.display = 'block';
            licenseFileGroup.style.display = 'block';
            licenseFileInput.required = true;
        }
    }

    vehicleSelect.addEventListener('change', toggleLicenseFields);
    window.onload = toggleLicenseFields;
</script>
</body>
</html>