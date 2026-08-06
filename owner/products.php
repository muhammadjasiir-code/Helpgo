<?php
require_once '../config.php';
if (!isLoggedIn()) { redirect('../login.php'); }

$uid = (int)$_SESSION['user_id'];

// Find the store owned by this user
$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stores WHERE owner_id = $uid LIMIT 1"));
if (!$store) die("You don't have a store yet. Please contact the administrator.");
$store_id = $store['id'];
$msg = '';

// ---------- Handle Add Product ----------
if (isset($_POST['add'])) {
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $price       = (float)$_POST['price'];
    $category_id = $_POST['category_id'] ? (int)$_POST['category_id'] : 'NULL';
    $imageFilename = '';

    // Process image upload
    if (!empty($_FILES['product_image']['name'])) {
        $file = $_FILES['product_image'];
        $uploadDir = '../assets/storeproducts/';   // relative to owner folder
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (in_array($mime, $allowed)) {
                if ($file['size'] > 5 * 1024 * 1024) {
                    $msg = '<div class="alert error">Image too large (max 5 MB).</div>';
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destination = $uploadDir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $imageFilename = 'assets/storeproducts/' . $filename;   // stored path
                    } else {
                        $msg = '<div class="alert error">Failed to save image. Check folder permissions.</div>';
                    }
                }
            } else {
                $msg = '<div class="alert error">Invalid image type. Only JPG, PNG, WebP, GIF allowed.</div>';
            }
        } else {
            $msg = '<div class="alert error">Upload error (code ' . $file['error'] . ').</div>';
        }
    }

    if (empty($msg)) {
        $img = mysqli_real_escape_string($conn, $imageFilename);
        $sql = "INSERT INTO store_products (store_id, category_id, name, price, image)
                VALUES ($store_id, $category_id, '$name', $price, '$img')";
        if (mysqli_query($conn, $sql)) {
            $msg = '<div class="alert success">✅ Product added!</div>';
        } else {
            $msg = '<div class="alert error">❌ DB error: ' . mysqli_error($conn) . '</div>';
        }
    }
}

// ---------- Handle Edit Product ----------
if (isset($_POST['edit'])) {
    $prod_id     = (int)$_POST['prod_id'];
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
    $price       = (float)$_POST['price'];
    $category_id = $_POST['category_id'] ? (int)$_POST['category_id'] : 'NULL';

    // Check if a new image was uploaded
    if (!empty($_FILES['edit_image']['name']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['edit_image'];
        $uploadDir = '../assets/storeproducts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (in_array($mime, $allowed) && $file['size'] <= 5*1024*1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $newImg = 'assets/storeproducts/' . $filename;
                // Delete old image if exists
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM store_products WHERE id=$prod_id"));
                if (!empty($old['image']) && file_exists('../' . $old['image'])) {
                    unlink('../' . $old['image']);
                }
                // Update image field
                mysqli_query($conn, "UPDATE store_products SET image='" . mysqli_real_escape_string($conn, $newImg) . "' WHERE id=$prod_id");
            }
        }
    }

    // Update other fields
    $sql = "UPDATE store_products SET name='$name', price=$price, category_id=$category_id WHERE id=$prod_id AND store_id=$store_id";
    if (mysqli_query($conn, $sql)) {
        $msg = '<div class="alert success">✅ Product updated!</div>';
    } else {
        $msg = '<div class="alert error">❌ Update failed: ' . mysqli_error($conn) . '</div>';
    }
}

// ---------- Delete Product ----------
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    // Delete image file if exists
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM store_products WHERE id=$id AND store_id=$store_id"));
    if ($img && !empty($img['image'])) {
        $filepath = '../' . $img['image'];
        if (file_exists($filepath)) unlink($filepath);
    }
    mysqli_query($conn, "DELETE FROM store_products WHERE id=$id AND store_id=$store_id");
    $msg = '<div class="alert error">🗑️ Product deleted.</div>';
}

// Fetch products and categories
$products = mysqli_query($conn, "
    SELECT p.*, c.name AS cat_name
    FROM store_products p
    LEFT JOIN store_categories c ON p.category_id = c.id
    WHERE p.store_id = $store_id
    ORDER BY p.id DESC
");
$categories = mysqli_query($conn, "SELECT * FROM store_categories WHERE store_id = $store_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products – <?= htmlspecialchars($store['name']) ?> | HelpGo Owner</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --bg: #052e24;
            --surface: #064e3b;
            --primary: #c9a84c;
            --primary-soft: rgba(201,168,76,0.15);
            --foreground: #f5f0e0;
            --foreground-muted: #b7b09a;
            --border: rgba(245,240,224,0.08);
            --radius: 16px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--foreground); padding:20px; min-height:100vh; display:flex; justify-content:center; align-items:flex-start; }
        .container { max-width:700px; width:100%; }
        .top-bar { display:flex; align-items:center; gap:15px; margin-bottom:25px; }
        .back-btn { background:var(--surface); border:1px solid var(--border); width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--foreground-muted); text-decoration:none; transition:0.2s; }
        .back-btn:hover { background:var(--primary-soft); color:var(--foreground); }
        h1 { font-size:24px; font-weight:700; }
        .alert { padding:12px 16px; border-radius:12px; margin-bottom:20px; font-size:14px; }
        .alert.success { background:rgba(13,122,95,0.4); color:#a3e4bc; border:1px solid #0d7a5f; }
        .alert.error { background:rgba(220,53,69,0.2); color:#ffb3b3; border:1px solid #dc3545; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:20px; }
        .card h3 { margin-bottom:15px; font-size:18px; }
        form .form-group { margin-bottom:12px; }
        label { display:block; font-size:13px; color:var(--foreground-muted); margin-bottom:4px; }
        input, select { width:100%; padding:10px 14px; background:var(--bg); border:1px solid var(--border); border-radius:10px; color:var(--foreground); font-size:14px; }
        input[type="file"] { padding:8px; background:var(--bg); }
        .btn { display:inline-block; padding:10px 20px; border-radius:30px; background:var(--primary); color:#052e24; font-weight:700; border:none; cursor:pointer; transition:0.2s; font-size:14px; text-decoration:none; }
        .btn:hover { background:#dbbf5a; }
        .btn-sm { padding:6px 14px; font-size:13px; border-radius:20px; }
        .btn-danger { background:#dc3545; color:#fff; }
        .btn-outline { background:transparent; border:1px solid var(--primary); color:var(--primary); }
        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th, td { padding:10px 12px; text-align:left; border-bottom:1px solid var(--border); font-size:14px; }
        th { color:var(--foreground-muted); font-weight:600; }
        .product-thumb { width:40px; height:40px; border-radius:8px; object-fit:cover; background:var(--bg); }
        .actions { display:flex; gap:8px; }

        /* Modal styles */
        .modal { display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; }
        .modal-content { background:var(--surface); border-radius:var(--radius); padding:20px; width:90%; max-width:400px; max-height:90vh; overflow-y:auto; }
        .modal-content h3 { margin-bottom:15px; }
        .modal-close { float:right; background:none; border:none; color:var(--foreground-muted); font-size:20px; cursor:pointer; }
        @media (max-width:480px) { .container { padding:0; } }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1>📦 Products</h1>
    </div>
    <?= $msg ?>

    <!-- Add Product Form -->
    <div class="card">
        <h3>Add New Product</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Price (₹)</label>
                <input type="number" step="0.01" name="price" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id">
                    <option value="">None</option>
                    <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="product_image" accept="image/*">
            </div>
            <button type="submit" name="add" class="btn"><i class="fas fa-plus"></i> Add Product</button>
        </form>
    </div>

    <!-- Product List -->
    <div class="card">
        <h3>All Products</h3>
        <?php if (mysqli_num_rows($products) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($p = mysqli_fetch_assoc($products)): ?>
                <tr>
                    <td>
                        <?php if (!empty($p['image'])): ?>
                            <img src="../<?= htmlspecialchars($p['image']) ?>" class="product-thumb" onerror="this.style.display='none'">
                        <?php else: ?> 📦 <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>₹<?= number_format($p['price'],2) ?></td>
                    <td><?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
                    <td class="actions">
                        <button class="btn btn-sm btn-outline edit-btn" 
                            data-id="<?= $p['id'] ?>"
                            data-name="<?= htmlspecialchars($p['name']) ?>"
                            data-price="<?= $p['price'] ?>"
                            data-cat="<?= $p['category_id'] ?>"
                        ><i class="fas fa-edit"></i></button>
                        <a href="?del=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--foreground-muted); text-align:center;">No products yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3>Edit Product</h3>
        <form method="POST" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="edit" value="1">
            <input type="hidden" name="prod_id" id="edit-id">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="edit-name" required>
            </div>
            <div class="form-group">
                <label>Price</label>
                <input type="number" step="0.01" name="price" id="edit-price" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" id="edit-cat">
                    <option value="">None</option>
                    <?php
                    mysqli_data_seek($categories, 0);
                    while($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>New Image (optional)</label>
                <input type="file" name="edit_image" accept="image/*">
            </div>
            <button type="submit" class="btn">Update Product</button>
        </form>
    </div>
</div>

<script>
    // Edit button -> fill modal
    const modal = document.getElementById('editModal');
    const editBtns = document.querySelectorAll('.edit-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-id').value = btn.dataset.id;
            document.getElementById('edit-name').value = btn.dataset.name;
            document.getElementById('edit-price').value = btn.dataset.price;
            document.getElementById('edit-cat').value = btn.dataset.cat || '';
            modal.style.display = 'flex';
        });
    });

    function closeModal() {
        modal.style.display = 'none';
    }

    window.onclick = function(e) {
        if (e.target === modal) modal.style.display = 'none';
    }
</script>
</body>
</html>