<?php
require_once "config.php";
if (!isLoggedIn()) { redirect('index.php'); }

$user = getUserData($_SESSION['user_id']);
$uid = (int)$_SESSION['user_id'];

$message = '';
$uploadDir = 'uploads/shop_applications/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_shop'])) {
    // Text fields
    $shop_name          = mysqli_real_escape_string($conn, trim($_POST['shop_name']));
    $owner_name         = mysqli_real_escape_string($conn, trim($_POST['owner_name']));
    $mobile             = mysqli_real_escape_string($conn, trim($_POST['mobile']));
    $whatsapp           = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $email              = mysqli_real_escape_string($conn, trim($_POST['email']));
    $full_address       = mysqli_real_escape_string($conn, trim($_POST['full_address']));
    $town_area          = mysqli_real_escape_string($conn, trim($_POST['town_area']));
    $landmark           = mysqli_real_escape_string($conn, trim($_POST['landmark']));
    $google_maps_link   = mysqli_real_escape_string($conn, trim($_POST['google_maps_link']));
    $shop_category      = mysqli_real_escape_string($conn, trim($_POST['shop_category']));
    $business_description = mysqli_real_escape_string($conn, trim($_POST['business_description']));
    $opening_time       = mysqli_real_escape_string($conn, trim($_POST['opening_time']));
    $closing_time       = mysqli_real_escape_string($conn, trim($_POST['closing_time']));
    $weekly_holiday     = mysqli_real_escape_string($conn, trim($_POST['weekly_holiday']));
    $delivery_available = isset($_POST['delivery_available']) ? 1 : 0;
    $delivery_type      = mysqli_real_escape_string($conn, trim($_POST['delivery_type']));
    $delivery_radius    = (float)$_POST['delivery_radius'];
    $min_order_amount   = (float)$_POST['min_order_amount'];
    $account_holder_name = mysqli_real_escape_string($conn, trim($_POST['account_holder_name']));
    $bank_name          = mysqli_real_escape_string($conn, trim($_POST['bank_name']));
    $account_number     = mysqli_real_escape_string($conn, trim($_POST['account_number']));
    $ifsc_code          = mysqli_real_escape_string($conn, trim($_POST['ifsc_code']));
    $upi_id             = mysqli_real_escape_string($conn, trim($_POST['upi_id']));
    $agree_info         = isset($_POST['agree_info']) ? 1 : 0;
    $agree_responsibility = isset($_POST['agree_responsibility']) ? 1 : 0;
    $agree_terms        = isset($_POST['agree_terms']) ? 1 : 0;
    $agree_no_false_info = isset($_POST['agree_no_false_info']) ? 1 : 0;

    // File uploads helper
    function uploadFile($fieldName, $allowedExtensions = ['jpg','jpeg','png','pdf','webp']) {
        global $uploadDir, $message;
        if (empty($_FILES[$fieldName]['name'])) return '';
        $file = $_FILES[$fieldName];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = '<div class="alert error">Upload error on ' . $fieldName . ' (code ' . $file['error'] . ')</div>';
            return null;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $message = '<div class="alert error">' . $fieldName . ' only allows: ' . implode(', ', $allowedExtensions) . '</div>';
            return null;
        }
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return $filename;
        } else {
            $message = '<div class="alert error">Failed to save ' . $fieldName . '</div>';
            return null;
        }
    }

    // Upload required files
    $shop_photo      = uploadFile('shop_photo');
    $shop_logo       = uploadFile('shop_logo');
    $owner_photo     = uploadFile('owner_photo');
    $govt_id         = uploadFile('govt_id');
    $business_licence = uploadFile('business_licence');
    $product_list_file = uploadFile('product_list_file');
    $price_list_file  = uploadFile('price_list_file');

    // Sample product images (multiple) – store as comma-separated filenames
    $sample_images = '';
    if (!empty($_FILES['sample_product_images']['name'][0])) {
        $filenames = [];
        foreach ($_FILES['sample_product_images']['name'] as $idx => $name) {
            if ($_FILES['sample_product_images']['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['sample_product_images']['tmp_name'][$idx], $uploadDir . $filename);
                    $filenames[] = $filename;
                }
            }
        }
        $sample_images = implode(',', $filenames);
    }

    // Stop if any upload error occurred
    if (!empty($message)) {
        // $message already set
    } else {
        // Insert into database
        $sql = "INSERT INTO shop_applications (
            user_id, shop_name, owner_name, mobile, whatsapp, email,
            full_address, town_area, landmark, google_maps_link,
            shop_category, business_description, opening_time, closing_time, weekly_holiday,
            shop_photo, shop_logo, owner_photo, govt_id, business_licence, gst_number,
            delivery_available, delivery_type, delivery_radius, min_order_amount,
            account_holder_name, bank_name, account_number, ifsc_code, upi_id,
            product_list_file, sample_product_images, price_list_file,
            agree_info, agree_responsibility, agree_terms, agree_no_false_info
        ) VALUES (
            $uid, '$shop_name', '$owner_name', '$mobile', '$whatsapp', '$email',
            '$full_address', '$town_area', '$landmark', '$google_maps_link',
            '$shop_category', '$business_description', '$opening_time', '$closing_time', '$weekly_holiday',
            '$shop_photo', '$shop_logo', '$owner_photo', '$govt_id', '$business_licence', '".mysqli_real_escape_string($conn, $_POST['gst_number'])."',
            $delivery_available, '$delivery_type', $delivery_radius, $min_order_amount,
            '$account_holder_name', '$bank_name', '$account_number', '$ifsc_code', '$upi_id',
            '$product_list_file', '$sample_images', '$price_list_file',
            $agree_info, $agree_responsibility, $agree_terms, $agree_no_false_info
        )";

        if (mysqli_query($conn, $sql)) {
            $message = '<div class="alert success">✅ Your application has been submitted! We will review and contact you soon.</div>';
        } else {
            $message = '<div class="alert error">❌ Database error: ' . mysqli_error($conn) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>List Your Shop – HelpGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same dark green/gold theme as your store pages */
        :root {
            --bg: #03271e;
            --surface: rgba(255,255,255,0.04);
            --line: rgba(201,168,76,0.28);
            --gold: #d4b45a;
            --gold-2: #e8c976;
            --gold-deep: #a8873a;
            --text: #f4ecd4;
            --muted: #9db3a8;
            --red: #ef4444;
            --radius: 16px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(1200px 500px at 50% -100px, #0a4a37 0%, transparent 60%), var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex; justify-content: center;
        }
        a { color: var(--gold); text-decoration: none; }
        .app { width: 100%; max-width: 500px; padding: 20px 16px 40px; }
        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--gold); margin-bottom: 20px; font-weight: 600;
        }
        h1 { font-family: 'Sora', sans-serif; font-size: 26px; margin-bottom: 20px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 { font-size: 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px; }
        input, textarea, select {
            width: 100%; padding: 10px 12px; background: rgba(0,0,0,0.3); border: 1px solid var(--line);
            border-radius: 10px; color: var(--text); font-family: inherit; font-size: 14px;
        }
        input[type="file"] { padding: 8px; background: rgba(0,0,0,0.3); }
        textarea { min-height: 80px; resize: vertical; }
        .checkbox-group { display: flex; flex-direction: column; gap: 10px; margin: 12px 0; }
        .checkbox-group label { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--text); }
        .checkbox-group input[type="checkbox"] { width: 16px; height: 16px; margin-top: 2px; }
        .btn {
            display: block; width: 100%; padding: 14px; background: linear-gradient(135deg, var(--gold-2), var(--gold-deep));
            color: #03271e; border: none; border-radius: 12px; font-weight: 800; font-size: 16px;
            cursor: pointer; margin-top: 20px;
            box-shadow: 0 8px 18px rgba(212,180,90,0.25);
            transition: 0.2s;
        }
        .btn:hover { transform: translateY(-1px); }
        .alert { padding: 12px; border-radius: 12px; margin-bottom: 15px; font-size: 14px; }
        .alert.success { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid #4ade80; }
        .alert.error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444; }
        small { color: var(--muted); font-size: 12px; }
    </style>
</head>
<body>
<div class="app">
    <a href="store.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Stores</a>
    <h1>📋 List Your Shop</h1>
    <?= $message ?>
    <form method="POST" enctype="multipart/form-data">
        <!-- Shop Information -->
        <div class="card">
            <h2><i class="fas fa-store"></i> Shop Information</h2>
            <div class="form-group"><label>Shop Name *</label><input type="text" name="shop_name" required></div>
            <div class="form-group"><label>Owner Name *</label><input type="text" name="owner_name" required></div>
            <div class="form-group"><label>Mobile Number *</label><input type="tel" name="mobile" required></div>
            <div class="form-group"><label>WhatsApp Number</label><input type="tel" name="whatsapp"></div>
            <div class="form-group"><label>Email Address (Optional)</label><input type="email" name="email"></div>
        </div>

        <!-- Shop Address -->
        <div class="card">
            <h2><i class="fas fa-map-marker-alt"></i> Shop Address</h2>
            <div class="form-group"><label>Full Address *</label><textarea name="full_address" required></textarea></div>
            <div class="form-group"><label>Town/Area *</label><input type="text" name="town_area" required></div>
            <div class="form-group"><label>Landmark</label><input type="text" name="landmark"></div>
            <div class="form-group"><label>Google Maps Location (Pin)</label><input type="text" name="google_maps_link" placeholder="https://maps.app.goo.gl/..."></div>
        </div>

        <!-- Business Details -->
        <div class="card">
            <h2><i class="fas fa-briefcase"></i> Business Details</h2>
            <div class="form-group">
                <label>Shop Category *</label>
                <select name="shop_category" required>
                    <option value="">-- Select --</option>
                    <option>Restaurant</option><option>Thattukada</option><option>Grocery</option>
                    <option>Bakery</option><option>Pharmacy</option><option>Fruits & Vegetables</option>
                    <option>Meat & Fish</option><option>Stationery</option><option>Electronics</option>
                    <option>Fashion</option><option>Other</option>
                </select>
            </div>
            <div class="form-group"><label>Business Description</label><textarea name="business_description"></textarea></div>
            <div class="form-group"><label>Opening Time</label><input type="text" name="opening_time" placeholder="e.g., 9:00 AM"></div>
            <div class="form-group"><label>Closing Time</label><input type="text" name="closing_time" placeholder="e.g., 9:00 PM"></div>
            <div class="form-group"><label>Weekly Holiday</label><input type="text" name="weekly_holiday" placeholder="e.g., Tuesday"></div>
        </div>

        <!-- Documents -->
        <div class="card">
            <h2><i class="fas fa-file-alt"></i> Documents</h2>
            <div class="form-group"><label>Shop Photo * <small>(front view)</small></label><input type="file" name="shop_photo" accept="image/*" required></div>
            <div class="form-group"><label>Shop Logo <small>(optional)</small></label><input type="file" name="shop_logo" accept="image/*"></div>
            <div class="form-group"><label>Owner Photo</label><input type="file" name="owner_photo" accept="image/*"></div>
            <div class="form-group"><label>Government ID (Aadhaar/PAN) *</label><input type="file" name="govt_id" accept="image/*,.pdf" required></div>
            <div class="form-group"><label>Business/FSSAI Licence (if applicable)</label><input type="file" name="business_licence" accept="image/*,.pdf"></div>
            <div class="form-group"><label>GST Number (Optional)</label><input type="text" name="gst_number"></div>
        </div>

        <!-- Delivery Details -->
        <div class="card">
            <h2><i class="fas fa-truck"></i> Delivery Details</h2>
            <div class="form-group"><label>Delivery Available?</label><input type="checkbox" name="delivery_available" checked style="width:auto;"> Yes</div>
            <div class="form-group">
                <label>Delivery Type</label>
                <select name="delivery_type">
                    <option value="both">Both (Self & HelpGo)</option>
                    <option value="self">Self Delivery</option>
                    <option value="helgo">HelpGo Delivery</option>
                </select>
            </div>
            <div class="form-group"><label>Delivery Radius (KM)</label><input type="number" step="0.1" name="delivery_radius" placeholder="e.g., 5"></div>
            <div class="form-group"><label>Minimum Order Amount (₹)</label><input type="number" step="0.01" name="min_order_amount" placeholder="e.g., 100"></div>
        </div>

        <!-- Bank Details -->
        <div class="card">
            <h2><i class="fas fa-university"></i> Bank Details</h2>
            <div class="form-group"><label>Account Holder Name</label><input type="text" name="account_holder_name"></div>
            <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name"></div>
            <div class="form-group"><label>Account Number</label><input type="text" name="account_number"></div>
            <div class="form-group"><label>IFSC Code</label><input type="text" name="ifsc_code"></div>
            <div class="form-group"><label>UPI ID</label><input type="text" name="upi_id"></div>
        </div>

        <!-- Product Details -->
        <div class="card">
            <h2><i class="fas fa-boxes"></i> Product Details</h2>
            <div class="form-group"><label>Product List Upload (Excel/PDF)</label><input type="file" name="product_list_file" accept=".xlsx,.xls,.pdf,.csv"></div>
            <div class="form-group"><label>Sample Product Images</label><input type="file" name="sample_product_images[]" accept="image/*" multiple></div>
            <div class="form-group"><label>Price List File</label><input type="file" name="price_list_file" accept="image/*,.pdf,.xlsx"></div>
        </div>

        <!-- Agreements -->
        <div class="card">
            <h2><i class="fas fa-check-circle"></i> Agreements</h2>
            <div class="checkbox-group">
                <label><input type="checkbox" name="agree_info" value="1" required> I confirm that all information provided is correct.</label>
                <label><input type="checkbox" name="agree_responsibility" value="1" required> I agree that I am responsible for the quality, safety, legality and pricing of the products sold by my shop.</label>
                <label><input type="checkbox" name="agree_terms" value="1" required> I agree to HelpGo's Partner Terms & Conditions.</label>
                <label><input type="checkbox" name="agree_no_false_info" value="1" required> I understand that false information may result in removal from the HelpGo platform.</label>
            </div>
        </div>

        <button type="submit" name="apply_shop" class="btn"><i class="fas fa-paper-plane"></i> Apply for Verification</button>
    </form>
</div>
</body>
</html>