<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'admin') die('Access denied.');

$edit = false;
$store = ['name'=>'', 'owner_id'=>'', 'description'=>'', 'banner'=>''];
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = mysqli_query($conn, "SELECT * FROM stores WHERE id=$id");
    if ($row = mysqli_fetch_assoc($res)) {
        $store = $row;
        $edit = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $owner_id = (int)$_POST['owner_id'];
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $banner = mysqli_real_escape_string($conn, $_POST['banner']);

    if ($edit) {
        mysqli_query($conn, "UPDATE stores SET name='$name', owner_id=$owner_id, description='$desc', banner='$banner' WHERE id=" . $store['id']);
    } else {
        mysqli_query($conn, "INSERT INTO stores (owner_id, name, description, banner) VALUES ($owner_id, '$name', '$desc', '$banner')");
    }
    redirect('admin_stores.php');
}

// Fetch all owners (users with role 'owner' or admin)
$owners = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role IN ('owner','admin') ORDER BY full_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $edit ? 'Edit' : 'Add' ?> Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background:#052e24; color:#f5f0e0; font-family:'Manrope',sans-serif; padding:20px; max-width:500px; margin:auto; }
        input, textarea, select { width:100%; padding:10px; margin:6px 0; border-radius:8px; border:1px solid #c9a84c; background:#064e3b; color:#f5f0e0; }
        .btn { padding:10px 20px; background:#c9a84c; color:#052e24; border:none; border-radius:8px; font-weight:700; cursor:pointer; }
    </style>
</head>
<body>
    <h2><?= $edit ? 'Edit Store' : 'New Store' ?></h2>
    <form method="post">
        <label>Store Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($store['name']) ?>" required>
        
        <label>Owner</label>
        <select name="owner_id" required>
            <option value="">Select owner</option>
            <?php while ($u = mysqli_fetch_assoc($owners)): ?>
                <option value="<?= $u['id'] ?>" <?= $store['owner_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
            <?php endwhile; ?>
        </select>
        
        <label>Description</label>
        <textarea name="description"><?= htmlspecialchars($store['description']) ?></textarea>
        
        <label>Banner URL</label>
        <input type="text" name="banner" value="<?= htmlspecialchars($store['banner']) ?>" placeholder="https://...">
        
        <button type="submit" class="btn"><?= $edit ? 'Update' : 'Create' ?> Store</button>
        <a href="admin_stores.php" style="margin-left:10px;">Cancel</a>
    </form>
</body>
</html>