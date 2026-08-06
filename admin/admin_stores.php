<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$message = '';
$uploadDir = '../assets/storebanner/';

// ----- Edit mode -----
$editMode = false;
$editStore = ['id'=>'','name'=>'','slug'=>'','owner_id'=>'','description'=>'','location'=>'','open_time'=>'','category'=>'','latitude'=>'','longitude'=>'','banner'=>'','logo'=>'','cover_banner'=>''];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRes = mysqli_query($conn, "SELECT * FROM stores WHERE id = $editId");
    if ($editRes && mysqli_num_rows($editRes) > 0) {
        $editStore = mysqli_fetch_assoc($editRes);
        $editMode = true;
    }
}

// ----- Handle form submit -----
if (isset($_POST['save_store'])) {
    $rawName = trim($_POST['name']);
    $cleanName = preg_replace('/[^a-zA-Z0-9\s-]/', '', $rawName);
    $slugBase  = strtolower(preg_replace('/[\s-]+/', '-', $cleanName));
    $slugBase  = trim($slugBase, '-');
    $slug = $slugBase;
    $excludeCondition = $editMode ? " AND id != " . (int)$_POST['store_id'] : "";
    $counter = 1;
    while (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM stores WHERE slug = '$slug' $excludeCondition"))) {
        $slug = $slugBase . '-' . $counter;
        $counter++;
    }

    $name         = mysqli_real_escape_string($conn, trim($_POST['name']));
    $owner_id     = (int)$_POST['owner_id'];
    $description  = mysqli_real_escape_string($conn, trim($_POST['description']));
    $location     = mysqli_real_escape_string($conn, trim($_POST['location']));
    $open_time    = mysqli_real_escape_string($conn, trim($_POST['open_time']));
    $category     = mysqli_real_escape_string($conn, trim($_POST['category']));
    $latitude     = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 0;
    $longitude    = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 0;

    // Helper function to handle image upload
    function uploadStoreImage($fileInputName) {
        global $uploadDir, $message;
        if (empty($_FILES[$fileInputName]['name'])) return '';
        $file = $_FILES[$fileInputName];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = '<div class="alert error">❌ Upload error on ' . $fileInputName . ' (code ' . $file['error'] . ')</div>';
            return null;
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedTypes)) {
            $message = '<div class="alert error">❌ ' . $fileInputName . ': Only JPG, PNG, WebP, GIF allowed.</div>';
            return null;
        }
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return $filename;
        } else {
            $message = '<div class="alert error">❌ Failed to save ' . $fileInputName . '.</div>';
            return null;
        }
    }

    $bannerFilename = uploadStoreImage('banner');
    $logoFilename   = uploadStoreImage('logo');
    $coverFilename  = uploadStoreImage('cover_banner');

    if (empty($message)) {
        $banner = $bannerFilename !== null ? mysqli_real_escape_string($conn, $bannerFilename) : ($editMode ? $editStore['banner'] : '');
        $logo   = $logoFilename   !== null ? mysqli_real_escape_string($conn, $logoFilename)   : ($editMode ? $editStore['logo'] : '');
        $cover  = $coverFilename  !== null ? mysqli_real_escape_string($conn, $coverFilename)  : ($editMode ? $editStore['cover_banner'] : '');

        if ($editMode) {
            $storeId = (int)$_POST['store_id'];
            $sql = "UPDATE stores SET 
                    owner_id = $owner_id, name = '$name', slug = '$slug', description = '$description',
                    location = '$location', open_time = '$open_time', category = '$category',
                    latitude = $latitude, longitude = $longitude,
                    banner = '$banner', logo = '$logo', cover_banner = '$cover'
                    WHERE id = $storeId";
            if (mysqli_query($conn, $sql)) {
                $message = '<div class="alert success">✅ Store updated!<br>URL: <a href="../store/' . urlencode($slug) . '" target="_blank">/store/' . htmlspecialchars($slug) . '</a></div>';
                $editMode = false;
            } else {
                $message = '<div class="alert error">❌ Update error: ' . mysqli_error($conn) . '</div>';
            }
        } else {
            $sql = "INSERT INTO stores (owner_id, name, slug, description, location, open_time, category, latitude, longitude, banner, logo, cover_banner) 
                    VALUES ($owner_id, '$name', '$slug', '$description', '$location', '$open_time', '$category', $latitude, $longitude, '$banner', '$logo', '$cover')";
            if (mysqli_query($conn, $sql)) {
                $message = '<div class="alert success">✅ Store added successfully!<br>URL: <a href="../store/' . urlencode($slug) . '" target="_blank">/store/' . htmlspecialchars($slug) . '</a></div>';
            } else {
                $message = '<div class="alert error">❌ Database error: ' . mysqli_error($conn) . '</div>';
            }
        }
    }
}

// ----- Delete store -----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $res = mysqli_query($conn, "SELECT banner, logo, cover_banner FROM stores WHERE id = $id");
    if ($row = mysqli_fetch_assoc($res)) {
        foreach (['banner', 'logo', 'cover_banner'] as $col) {
            if (!empty($row[$col]) && !filter_var($row[$col], FILTER_VALIDATE_URL)) {
                $path = $uploadDir . $row[$col];
                if (file_exists($path)) unlink($path);
            }
        }
    }
    mysqli_query($conn, "DELETE FROM stores WHERE id = $id");
    $message = '<div class="alert error">🗑️ Store deleted.</div>';
    if ($editMode && $editStore['id'] == $id) {
        $editMode = false;
        $editStore = ['id'=>'','name'=>'','slug'=>'','owner_id'=>'','description'=>'','location'=>'','open_time'=>'','category'=>'','latitude'=>'','longitude'=>'','banner'=>'','logo'=>'','cover_banner'=>''];
    }
}

// Fetch stores
$storesQuery = mysqli_query($conn, "
    SELECT s.*, u.full_name AS owner_name 
    FROM stores s 
    JOIN users u ON s.owner_id = u.id 
    ORDER BY s.id DESC
");
$ownersQuery = mysqli_query($conn, "SELECT id, full_name FROM users ORDER BY full_name");

// Predefined category list
$categoriesList = [
    'Restaurant', 'Thattukada', 'Grocery', 'Bakery', 'Pharmacy',
    'Fruits & Vegetables', 'Meat & Fish', 'Stationery', 'Electronics',
    'Fashion', 'Chicken Shop', 'Metal Shop', 'Pet Shop', 'Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Store Management – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --bg: #052e24; --surface: #064e3b; --elevated: #0d7a5f;
            --primary: #c9a84c; --primary-soft: rgba(201,168,76,0.15);
            --foreground: #f5f0e0; --foreground-muted: #b7b09a;
            --border: rgba(245,240,224,0.06); --radius: 20px; --transition: 0.2s ease;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Manrope',sans-serif; background:var(--bg); color:var(--foreground); min-height:100vh; display:flex; justify-content:center; align-items:flex-start; }
        .page-container { width:100%; max-width:800px; padding:20px 16px 40px; }
        .top-bar { display:flex; align-items:center; gap:14px; margin-bottom:30px; }
        .back-link { width:42px; height:42px; border-radius:50%; background:var(--surface); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--foreground-muted); font-size:16px; transition:var(--transition); text-decoration:none; }
        .back-link:hover { background:var(--elevated); color:var(--foreground); }
        .page-title { font-family:'Sora',sans-serif; font-size:22px; font-weight:800; color:var(--foreground); flex:1; }
        .logout-link { color:var(--foreground-muted); font-size:14px; text-decoration:none; }
        .logout-link:hover { color:var(--primary); }
        h1 { font-family:'Sora',sans-serif; font-size:24px; font-weight:700; margin-bottom:20px; color:var(--foreground); }
        .alert { padding:12px 16px; border-radius:12px; margin-bottom:20px; font-size:14px; }
        .alert.success { background:rgba(13,122,95,0.4); color:#a3e4bc; border:1px solid #0d7a5f; }
        .alert.success a { color:var(--primary); text-decoration:underline; }
        .alert.error { background:rgba(220,53,69,0.2); color:#ffb3b3; border:1px solid #dc3545; }
        .form-card, .table-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:30px; }
        .form-card h3, .table-card h3 { font-family:'Sora',sans-serif; font-size:18px; margin-bottom:15px; color:var(--foreground); }
        .form-group { margin-bottom:14px; }
        .form-group label { display:block; margin-bottom:4px; font-size:13px; color:var(--foreground-muted); }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:10px 14px; border-radius:12px;
            background:var(--bg); border:1px solid var(--border); color:var(--foreground);
            font-size:14px; font-family:inherit;
        }
        .form-group textarea { resize:vertical; min-height:70px; }
        .form-group input[type="file"] { padding:8px; }
        .btn {
            display:inline-block; padding:10px 22px; border-radius:30px;
            background:var(--primary); color:#052e24; font-weight:700; font-size:14px;
            border:none; cursor:pointer; transition:var(--transition); text-decoration:none;
        }
        .btn:hover { background:#dbbf5a; }
        .btn-sm { padding:6px 14px; font-size:13px; border-radius:20px; }
        .btn-danger { background:#dc3545; color:#fff; }
        .btn-danger:hover { background:#c82333; }
        .btn-outline { background:transparent; border:1px solid var(--primary); color:var(--primary); }
        .btn-outline:hover { background:var(--primary-soft); }
        .table-container { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px 14px; text-align:left; border-bottom:1px solid var(--border); font-size:14px; }
        th { font-weight:600; color:var(--foreground-muted); }
        tr:last-child td { border-bottom:none; }
        .logo-thumb { width:40px; height:40px; border-radius:50%; object-fit:cover; background:var(--bg); }
        .actions { display:flex; gap:8px; }
        #mapPicker { height: 250px; border-radius:12px; margin-top:10px; border:1px solid var(--border); }
    </style>
</head>
<body>
<div class="page-container">
    <div class="top-bar">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i></a>
        <div class="page-title">Help<span style="color:var(--primary);">Go</span> Admin</div>
        <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h1>🏪 Store Management</h1>
    <?= $message ?>

    <!-- Add / Edit Store Form -->
    <div class="form-card">
        <h3><?= $editMode ? 'Edit Store' : 'Add New Store' ?></h3>
        <form method="POST" enctype="multipart/form-data" id="storeForm">
            <?php if ($editMode): ?>
                <input type="hidden" name="store_id" value="<?= $editStore['id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Store Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($editStore['name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Owner</label>
                <select name="owner_id" required>
                    <option value="">Select owner...</option>
                    <?php mysqli_data_seek($ownersQuery, 0); while ($owner = mysqli_fetch_assoc($ownersQuery)): ?>
                        <option value="<?= $owner['id'] ?>" <?= $editMode && $editStore['owner_id'] == $owner['id'] ? 'selected' : '' ?>><?= htmlspecialchars($owner['full_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?= htmlspecialchars($editStore['description']) ?></textarea>
            </div>
            <div class="form-group">
                <label>📍 Address / Location Description</label>
                <input type="text" name="location" value="<?= htmlspecialchars($editStore['location']) ?>" placeholder="e.g., Pazhaya Bus Stand, Thrikkaripur">
            </div>
            <div class="form-group">
                <label>🕒 Open Time</label>
                <input type="text" name="open_time" value="<?= htmlspecialchars($editStore['open_time']) ?>" placeholder="e.g., 6:00 AM - 11:00 PM">
            </div>

            <!-- Category dropdown -->
            <div class="form-group">
                <label>Shop Category</label>
                <select name="category" required>
                    <option value="">Select category...</option>
                    <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($editMode && $editStore['category'] == $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Latitude / Longitude fields -->
            <div class="form-group">
                <label>Latitude (GPS)</label>
                <input type="text" name="latitude" id="latField" value="<?= $editStore['latitude'] ?>" placeholder="e.g., 12.3456" step="any">
            </div>
            <div class="form-group">
                <label>Longitude (GPS)</label>
                <input type="text" name="longitude" id="lngField" value="<?= $editStore['longitude'] ?>" placeholder="e.g., 77.6543" step="any">
            </div>

            <!-- Map picker -->
            <div class="form-group">
                <label>Click on the map to set exact location</label>
                <div id="mapPicker"></div>
            </div>

            <div class="form-group">
                <label>Banner Image</label>
                <input type="file" name="banner" accept="image/*">
            </div>
            <div class="form-group">
                <label>Store Logo</label>
                <input type="file" name="logo" accept="image/*">
            </div>
            <div class="form-group">
                <label>Cover Banner</label>
                <input type="file" name="cover_banner" accept="image/*">
            </div>
            <button type="submit" name="save_store" class="btn">
                <?= $editMode ? '<i class="fas fa-save"></i> Update Store' : '<i class="fas fa-plus"></i> Create Store' ?>
            </button>
            <?php if ($editMode): ?>
                <a href="admin_stores.php" class="btn btn-outline" style="margin-left:10px;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Store List -->
    <div class="table-card">
        <h3>All Stores</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Name / Slug</th>
                        <th>Owner</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($storesQuery) > 0): ?>
                        <?php while ($store = mysqli_fetch_assoc($storesQuery)):
                            $displayLogo = '';
                            if (!empty($store['logo'])) {
                                $displayLogo = filter_var($store['logo'], FILTER_VALIDATE_URL) ? $store['logo'] : '../assets/storebanner/' . $store['logo'];
                            } elseif (!empty($store['banner'])) {
                                $displayLogo = filter_var($store['banner'], FILTER_VALIDATE_URL) ? $store['banner'] : '../assets/storebanner/' . $store['banner'];
                            }
                            if (empty($displayLogo)) $displayLogo = 'https://placehold.co/40x40/064e3b/f5f0e0?text=S';
                        ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($displayLogo) ?>" class="logo-thumb" onerror="this.src='https://placehold.co/40x40/064e3b/f5f0e0?text=S'"></td>
                            <td>
                                <strong><?= htmlspecialchars($store['name']) ?></strong><br>
                                <small style="color:var(--foreground-muted);">/store/<?= htmlspecialchars($store['slug']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($store['owner_name']) ?></td>
                            <td><?= htmlspecialchars($store['category'] ?? 'Other') ?></td>
                            <td class="actions">
                                <a href="../store/<?= htmlspecialchars($store['slug']) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link-alt"></i> View</a>
                                <a href="?edit=<?= $store['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
                                <a href="?delete=<?= $store['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this store?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px;">No stores yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Map picker initialization
document.addEventListener('DOMContentLoaded', function() {
    const latField = document.getElementById('latField');
    const lngField = document.getElementById('lngField');
    let centerLat = <?= $editMode && $editStore['latitude'] ? $editStore['latitude'] : 10.8505 ?>;
    let centerLng = <?= $editMode && $editStore['longitude'] ? $editStore['longitude'] : 76.2711 ?>;

    const map = L.map('mapPicker', { center: [centerLat, centerLng], zoom: 13, zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    let marker;
    if (latField.value && lngField.value) {
        marker = L.marker([parseFloat(latField.value), parseFloat(lngField.value)]).addTo(map);
    }

    map.on('click', function(e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
        latField.value = lat;
        lngField.value = lng;
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }
    });
});
</script>
</body>
</html>