<?php
// become-rider.php – Premium Rider Application (Emerald Prestige theme)
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$uid = (int)$_SESSION['user_id'];

// Check existing application
$existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT verification_status FROM riders WHERE user_id = $uid"));
if ($existing) {
    if ($existing['verification_status'] == 'pending') $msg = "Your application is under review.";
    elseif ($existing['verification_status'] == 'approved') $msg = "You are already a verified rider.";
    else $msg = "Your previous application was rejected. You can re-apply.";
}

$error = '';
if (isset($_POST['apply'])) {
    $vehicle   = sanitize($_POST['vehicle_type']);
    $veh_num   = ($vehicle != 'Bicycle') ? sanitize($_POST['vehicle_number'] ?? '') : '';
    $license_num = ($vehicle != 'Bicycle') ? sanitize($_POST['license_number'] ?? '') : '';
    $dob       = sanitize($_POST['dob'] ?? '');

    // Age validation
    $minAge = ($vehicle == 'Bicycle') ? 16 : 18;
    if (empty($dob)) {
        $error = "Date of birth is required.";
    } else {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < $minAge) {
            $error = "You must be at least $minAge years old.";
        }
    }

    // File upload helper
    function upload($fileInput) {
        if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
            if (isset($_FILES[$fileInput]['error']) && $_FILES[$fileInput]['error'] != UPLOAD_ERR_NO_FILE) {
                return 'ERROR:' . $_FILES[$fileInput]['error'];
            }
            return '';
        }
        $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];
        if (!in_array($ext, $allowed)) return 'ERROR:Invalid file type';
        $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = UPLOAD_DIR . 'riders/' . $name;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }
        if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $dest)) return $name;
        return 'ERROR:Move failed';
    }

    $aadhaar_file = upload('aadhaar_file');
    $selfie_file  = upload('selfie_file');
    $pan_file     = upload('pan_file');
    $license_file = ($vehicle != 'Bicycle') ? upload('license_file') : '';

    if (strpos($aadhaar_file, 'ERROR') === 0 || strpos($selfie_file, 'ERROR') === 0 ||
        ($vehicle != 'Bicycle' && strpos($license_file, 'ERROR') === 0)) {
        $error = "File upload failed. Check permissions or file type.";
    } elseif (empty($aadhaar_file) || empty($selfie_file)) {
        $error = "Aadhaar card (front/back) and selfie are mandatory.";
    } elseif ($vehicle != 'Bicycle' && empty($license_file)) {
        $error = "Driving license upload is required for Scooter/Bike.";
    } elseif (empty($error)) {
        if ($existing) {
            mysqli_query($conn, "DELETE FROM riders WHERE user_id = $uid");
        }
        $insert = mysqli_query($conn, "INSERT INTO riders (user_id, vehicle_type, vehicle_number, license_number, aadhaar_file, pan_file, selfie_file, license_file, dob, verification_status)
            VALUES ($uid, '$vehicle', '$veh_num', '$license_num', '$aadhaar_file', '$pan_file', '$selfie_file', '$license_file', '$dob', 'pending')");
        if ($insert) {
            $msg = "Application submitted! Admin will review it soon.";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Become a Rider – HelpGo</title>
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
            --shadow-glass: 0 20px 50px rgba(0,0,0,0.4);
            --radius-card: 28px;
            --radius-input: 16px;
            --radius-btn: 20px;
            --font: 'Poppins', sans-serif;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
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

        .container {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gold);
            text-decoration: none;
            margin-bottom: 28px;
            font-weight: 500;
            transition: var(--transition);
        }
        .back-link:hover { gap: 12px; }

        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .hero-text h1 { font-size: 32px; font-weight: 800; line-height: 1.2; }
        .hero-text h1 span { color: var(--gold); }
        .hero-text p { color: var(--gray-soft); font-size: 14px; margin-top: 8px; }
        .hero-icon {
            font-size: 60px;
            color: var(--gold);
            opacity: 0.9;
            animation: floatIcon 4s infinite ease-in-out;
        }
        @keyframes floatIcon { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 28px 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-glass);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .alert.error {
            background: rgba(255,71,87,0.15);
            color: #FF4757;
            border: 1px solid rgba(255,71,87,0.2);
        }
        .alert.success {
            background: rgba(46,213,115,0.15);
            color: #2ED573;
            border: 1px solid rgba(46,213,115,0.2);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i { font-size: 20px; }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-soft);
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group label i { color: var(--gold); font-size: 14px; }

        select, input[type="text"], input[type="date"] {
            width: 100%;
            padding: 14px 18px;
            border-radius: var(--radius-input);
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--white);
            font-size: 15px;
            outline: none;
            font-family: var(--font);
            transition: var(--transition);
        }
        select:focus, input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
            background: rgba(255,255,255,0.08);
        }
        select option { background: var(--emerald); color: var(--white); }

        .file-upload {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,0.05);
            border: 1px dashed var(--glass-border);
            border-radius: var(--radius-input);
            padding: 18px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        .file-upload:hover { border-color: var(--gold); background: rgba(212,175,55,0.05); }
        .file-upload input {
            position: absolute;
            left: 0; top: 0; width: 100%; height: 100%;
            opacity: 0; cursor: pointer;
        }
        .file-upload i { font-size: 28px; color: var(--gold); margin-bottom: 8px; }
        .file-upload p { color: var(--gray-muted); font-size: 13px; }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: var(--radius-btn);
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--emerald-dark);
            font-weight: 700;
            font-size: 17px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 8px 30px rgba(212,175,55,0.3);
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(212,175,55,0.5);
        }

        .hidden { display: none; }

        .help-text {
            font-size: 12px;
            color: var(--gray-muted);
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb"></div>

    <div class="container">
        <a href="profile.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Profile</a>

        <div class="hero">
            <div class="hero-text">
                <h1>Become a <span>Rider</span></h1>
                <p>Join our team and start earning with every delivery.</p>
            </div>
            <div class="hero-icon"><i class="fas fa-motorcycle"></i></div>
        </div>

        <?php if (isset($msg)): ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if (!$existing || $existing['verification_status'] == 'rejected'): ?>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <!-- Vehicle & Basic Info -->
            <div class="card">
                <div class="section-title"><i class="fas fa-motorcycle"></i> Vehicle & Personal Info</div>
                <div class="form-group">
                    <label><i class="fas fa-truck-pickup"></i> Vehicle Type</label>
                    <select name="vehicle_type" id="vehicle_type" required>
                        <option value="Bicycle">🚲 Bicycle</option>
                        <option value="Scooter">🛵 Scooter / Motorcycle</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Date of Birth</label>
                    <input type="date" name="dob" id="dob" max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required>
                    <div class="help-text">Minimum age: 16 for bicycle, 18 for scooter</div>
                </div>
            </div>

            <!-- Scooter-specific fields -->
            <div id="scooter_fields" class="card hidden">
                <div class="section-title"><i class="fas fa-id-card"></i> License Details</div>
                <div class="form-group">
                    <label><i class="fas fa-closed-captioning"></i> Vehicle Number</label>
                    <input type="text" name="vehicle_number" placeholder="e.g. KL07AB1234">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-address-card"></i> Driving License Number</label>
                    <input type="text" name="license_number" placeholder="License number">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-upload"></i> Driving License (upload)</label>
                    <div class="file-upload">
                        <input type="file" name="license_file" accept="image/*,.pdf" id="license_file">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p id="license_text">Tap to upload license</p>
                    </div>
                </div>
            </div>

            <!-- Required Documents -->
            <div class="card">
                <div class="section-title"><i class="fas fa-folder-open"></i> Required Documents</div>
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Aadhaar Card (Front & Back) *</label>
                    <div class="file-upload">
                        <input type="file" name="aadhaar_file" accept="image/*,.pdf" required id="aadhaar_file">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p id="aadhaar_text">Upload Aadhaar (front & back combined)</p>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-camera"></i> Selfie / Live Photo *</label>
                    <div class="file-upload">
                        <input type="file" name="selfie_file" accept="image/*" capture="user" required id="selfie_file">
                        <i class="fas fa-portrait"></i>
                        <p id="selfie_text">Take a selfie now</p>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-invoice"></i> PAN Card (optional)</label>
                    <div class="file-upload">
                        <input type="file" name="pan_file" accept="image/*,.pdf" id="pan_file">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p id="pan_text">Upload PAN card if available</p>
                    </div>
                </div>
            </div>

            <button type="submit" name="apply" class="btn">
                <i class="fas fa-paper-plane"></i> Submit Application
            </button>
        </form>
        <?php endif; ?>
    </div>

    <script>
        // Toggle scooter fields
        const vehicleSelect = document.getElementById('vehicle_type');
        const scooterFields = document.getElementById('scooter_fields');
        const licenseInput = document.getElementById('license_file');
        const dobInput = document.getElementById('dob');

        function toggleFields() {
            if (vehicleSelect.value === 'Bicycle') {
                scooterFields.classList.add('hidden');
                licenseInput.required = false;
                dobInput.max = '<?= date('Y-m-d', strtotime('-16 years')) ?>';
            } else {
                scooterFields.classList.remove('hidden');
                licenseInput.required = true;
                dobInput.max = '<?= date('Y-m-d', strtotime('-18 years')) ?>';
            }
        }
        vehicleSelect.addEventListener('change', toggleFields);
        window.onload = toggleFields;

        // Show selected file name
        const fileMappings = [
            { inputId: 'license_file', textId: 'license_text' },
            { inputId: 'aadhaar_file', textId: 'aadhaar_text' },
            { inputId: 'selfie_file', textId: 'selfie_text' },
            { inputId: 'pan_file', textId: 'pan_text' }
        ];

        fileMappings.forEach(mapping => {
            const input = document.getElementById(mapping.inputId);
            const text = document.getElementById(mapping.textId);
            if (input && text) {
                const originalText = text.textContent;
                input.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        text.textContent = this.files[0].name;
                        text.style.color = '#2ED573';
                    } else {
                        text.textContent = originalText;
                        text.style.color = '';
                    }
                });
            }
        });
    </script>
</body>
</html>