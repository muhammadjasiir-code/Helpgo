<?php
require_once '../config.php';
if (!isLoggedIn()) { redirect('../login.php'); }

$uid = (int)$_SESSION['user_id'];

// Find the store owned by this user
$store = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stores WHERE owner_id = $uid LIMIT 1"));
if (!$store) {
    die("You don't have a store yet. Please contact the administrator.");
}
$store_id = $store['id'];
$message = '';

// ---------- Handle Add / Edit / Delete ----------

// Add a new category
if (isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $icon = mysqli_real_escape_string($conn, trim($_POST['icon']));
    if (empty($name)) {
        $message = '<div class="alert error">Category name is required.</div>';
    } else {
        mysqli_query($conn, "INSERT INTO store_categories (store_id, name, icon) VALUES ($store_id, '$name', '$icon')");
        $message = '<div class="alert success">✅ Category added.</div>';
    }
}

// Edit an existing category (via POST)
if (isset($_POST['edit_category'])) {
    $cat_id = (int)$_POST['cat_id'];
    $name   = mysqli_real_escape_string($conn, trim($_POST['name']));
    $icon   = mysqli_real_escape_string($conn, trim($_POST['icon']));
    if (empty($name)) {
        $message = '<div class="alert error">Category name is required.</div>';
    } else {
        mysqli_query($conn, "UPDATE store_categories SET name='$name', icon='$icon' WHERE id=$cat_id AND store_id=$store_id");
        $message = '<div class="alert success">✅ Category updated.</div>';
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM store_categories WHERE id=$del_id AND store_id=$store_id");
    $message = '<div class="alert error">🗑️ Category deleted.</div>';
}

// Fetch all categories for this store
$categories = mysqli_query($conn, "SELECT * FROM store_categories WHERE store_id = $store_id ORDER BY id");

// Common food-related Font Awesome icons for quick selection
$commonIcons = [
    'fa-mug-hot'       => '☕ Tea',
    'fa-cookie-bite'   => '🍪 Snack',
    'fa-utensils'      => '🍽️ Meal',
    'fa-hamburger'     => '🍔 Burger',
    'fa-pizza-slice'   => '🍕 Pizza',
    'fa-coffee'        => '☕ Coffee',
    'fa-wine-glass-alt'=> '🍷 Drink',
    'fa-apple-alt'     => '🍎 Fruit',
    'fa-ice-cream'     => '🍦 Dessert',
    'fa-birthday-cake' => '🎂 Cake',
    'fa-shopping-basket' => '🛒 Grocery',
    'fa-box'           => '📦 Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories – <?= htmlspecialchars($store['name']) ?> | HelpGo Owner</title>
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
        .container { max-width:650px; width:100%; }
        .top-bar { display:flex; align-items:center; gap:15px; margin-bottom:25px; }
        .back-btn { background:var(--surface); border:1px solid var(--border); width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--foreground-muted); text-decoration:none; transition:0.2s; }
        .back-btn:hover { background:var(--primary-soft); color:var(--foreground); }
        h1 { font-size:24px; font-weight:700; color:var(--foreground); }

        .alert { padding:12px 16px; border-radius:12px; margin-bottom:20px; font-size:14px; }
        .alert.success { background:rgba(13,122,95,0.4); color:#a3e4bc; border:1px solid #0d7a5f; }
        .alert.error { background:rgba(220,53,69,0.2); color:#ffb3b3; border:1px solid #dc3545; }

        .card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:20px; }
        .card h3 { margin-bottom:15px; font-size:18px; }

        form .form-group { margin-bottom:12px; }
        label { display:block; font-size:13px; color:var(--foreground-muted); margin-bottom:4px; }
        input, select { width:100%; padding:10px 14px; background:var(--bg); border:1px solid var(--border); border-radius:10px; color:var(--foreground); font-size:14px; font-family:inherit; }

        .icon-picker { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .icon-option { background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:10px 14px; cursor:pointer; font-size:13px; display:flex; align-items:center; gap:6px; transition:0.2s; }
        .icon-option:hover, .icon-option.selected { background:var(--primary-soft); border-color:var(--primary); color:var(--foreground); }
        .icon-option i { font-size:16px; }

        .btn { display:inline-block; padding:10px 20px; border-radius:30px; background:var(--primary); color:#052e24; font-weight:700; border:none; cursor:pointer; transition:0.2s; font-size:14px; text-decoration:none; }
        .btn:hover { background:#dbbf5a; }
        .btn-sm { padding:6px 14px; font-size:13px; border-radius:20px; }
        .btn-danger { background:#dc3545; color:#fff; }
        .btn-outline { background:transparent; border:1px solid var(--primary); color:var(--primary); }
        .btn-outline:hover { background:var(--primary-soft); }

        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th, td { padding:10px 12px; text-align:left; border-bottom:1px solid var(--border); font-size:14px; }
        th { color:var(--foreground-muted); font-weight:600; }
        .icon-cell { width:40px; text-align:center; }
        .icon-cell i { color:var(--primary); font-size:18px; }
        .actions { display:flex; gap:8px; }

        /* Inline edit form (hidden by default) */
        .inline-edit { display:none; }

        @media (max-width:480px) { .container { padding:0; } }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1>📂 Categories</h1>
    </div>
    <?= $message ?>

    <!-- Add Category Form -->
    <div class="card">
        <h3>Add New Category</h3>
        <form method="POST">
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" placeholder="e.g., Tea & Coffee" required>
            </div>
            <div class="form-group">
                <label>Icon (click to select)</label>
                <input type="text" name="icon" id="iconField" placeholder="fas fa-mug-hot" style="margin-bottom:10px;">
                <div class="icon-picker">
                    <?php foreach ($commonIcons as $class => $label): ?>
                        <div class="icon-option" data-icon="<?= $class ?>">
                            <i class="fas <?= $class ?>"></i> <?= $label ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" name="add_category" class="btn"><i class="fas fa-plus"></i> Add Category</button>
        </form>
    </div>

    <!-- Category List -->
    <div class="card">
        <h3>Your Categories</h3>
        <?php if (mysqli_num_rows($categories) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                <tr id="row-<?= $cat['id'] ?>">
                    <td class="icon-cell"><i class="fas <?= htmlspecialchars($cat['icon'] ?: 'fa-box') ?>"></i></td>
                    <td class="cat-name"><?= htmlspecialchars($cat['name']) ?></td>
                    <td class="actions">
                        <button class="btn btn-sm btn-outline edit-btn" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-icon="<?= htmlspecialchars($cat['icon']) ?>"><i class="fas fa-edit"></i></button>
                        <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <!-- Inline edit row (hidden) -->
                <tr class="inline-edit" id="edit-<?= $cat['id'] ?>">
                    <td colspan="3">
                        <form method="POST" style="display:flex; gap:10px; align-items:center;">
                            <input type="hidden" name="edit_category" value="1">
                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                            <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required style="flex:1;">
                            <input type="text" name="icon" value="<?= htmlspecialchars($cat['icon']) ?>" placeholder="icon class">
                            <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Save</button>
                            <button type="button" class="btn btn-sm btn-outline cancel-edit"><i class="fas fa-times"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--foreground-muted); text-align:center;">No categories yet. Add one above.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    // Icon picker: fill the hidden input and highlight selection
    document.querySelectorAll('.icon-option').forEach(opt => {
        opt.addEventListener('click', () => {
            document.getElementById('iconField').value = opt.dataset.icon;
            document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
        });
    });

    // Edit button: show inline edit row and hide the regular row
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            document.getElementById('row-' + id).style.display = 'none';
            document.getElementById('edit-' + id).style.display = 'table-row';
        });
    });

    // Cancel edit: hide inline row and show regular row
    document.querySelectorAll('.cancel-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const editRow = this.closest('tr');
            const id = editRow.id.replace('edit-', '');
            editRow.style.display = 'none';
            document.getElementById('row-' + id).style.display = 'table-row';
        });
    });
</script>
</body>
</html>