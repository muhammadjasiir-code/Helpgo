<?php
// admin/coupons.php – Manage Coupons (Admin Panel)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

// Helper function
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$msg = '';
$err = '';

// Check if coupons table exists (optional info, we only query it if it exists)
$tableExists = mysqli_query($conn, "SHOW TABLES LIKE 'coupons'");
$hasTable = ($tableExists && mysqli_num_rows($tableExists) > 0);

// --- Create Coupon ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $code            = strtoupper(trim(sanitize($_POST['code'] ?? '')));
    $discountType    = sanitize($_POST['discount_type'] ?? 'percentage');
    $discountValue   = floatval($_POST['discount_value'] ?? 0);
    $minOrderAmount  = floatval($_POST['min_order_amount'] ?? 0);
    $maxDiscount     = floatval($_POST['max_discount'] ?? 0);
    $description     = sanitize($_POST['description'] ?? '');
    $validFrom       = sanitize($_POST['valid_from'] ?? '');
    $validTo         = sanitize($_POST['valid_to'] ?? '');
    $isActive        = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured      = isset($_POST['is_featured']) ? 1 : 0;

    if (empty($code) || $discountValue <= 0) {
        $err = 'Coupon code and a valid discount value are required.';
    } elseif (!$hasTable) {
        $err = 'Coupons table does not exist. Please create it first.';
    } else {
        $check = mysqli_query($conn, "SELECT id FROM coupons WHERE code = '$code' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $err = 'A coupon with this code already exists.';
        } else {
            $vf = !empty($validFrom) ? "'$validFrom'" : "NULL";
            $vt = !empty($validTo)   ? "'$validTo'"   : "NULL";
            $insert = mysqli_query($conn, "
                INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount,
                                     description, valid_from, valid_to, is_active, is_featured)
                VALUES ('$code', '$discountType', $discountValue, $minOrderAmount, $maxDiscount,
                        '$description', $vf, $vt, $isActive, $isFeatured)
            ");
            if ($insert) {
                $msg = 'Coupon "' . $code . '" created successfully!';
            } else {
                $err = 'Database error: ' . mysqli_error($conn);
            }
        }
    }
}

// --- Toggle Active / Delete ---
if (isset($_GET['action']) && isset($_GET['id']) && $hasTable) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'toggle') {
        mysqli_query($conn, "UPDATE coupons SET is_active = NOT is_active WHERE id = $id");
        $msg = 'Coupon status toggled.';
    } elseif ($_GET['action'] === 'delete') {
        mysqli_query($conn, "DELETE FROM coupons WHERE id = $id");
        $msg = 'Coupon deleted.';
    }
    redirect('coupons.php');
}

// Fetch all coupons (if table exists)
$coupons = [];
if ($hasTable) {
    $res = mysqli_query($conn, "SELECT * FROM coupons ORDER BY id DESC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $coupons[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Coupons – HelpGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --emerald: #083C33; --emerald-light: #0E5548; --gold: #D4AF37;
            --gold-dark: #B8962E; --white: #FFFFFF; --gray-soft: #AEB8B2; --gray-muted: #6B7A73;
            --glass-bg: rgba(8, 60, 51, 0.7); --glass-border: rgba(212, 175, 55, 0.3);
            --shadow-glass: 0 20px 50px rgba(0,0,0,0.5);
            --radius-card: 24px; --radius-input: 14px; --radius-btn: 16px;
            --font: 'Poppins', sans-serif;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font);
            background: linear-gradient(145deg, #0B2E2A, #1A4A44);
            color: var(--white);
            display: flex; justify-content: center; min-height: 100vh; padding: 20px 16px 60px;
        }
        .container { width: 100%; max-width: 600px; }
        .back-link { color: var(--gold); text-decoration: none; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px; }
        h2 { font-size: 28px; font-weight: 800; margin-bottom: 20px; color: var(--white); }
        h2 span { color: var(--gold); }

        .card {
            background: var(--glass-bg); backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border); border-radius: var(--radius-card);
            padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-glass);
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; color: var(--gray-soft); margin-bottom: 6px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 12px 16px; border-radius: var(--radius-input);
            background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);
            color: var(--white); font-family: var(--font); font-size: 15px; outline: none;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--gold); box-shadow: 0 0 0 3px rgba(212,175,55,0.2);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .row { display: flex; gap: 12px; }
        .row .form-group { flex: 1; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .btn {
            width: 100%; padding: 14px; border: none; border-radius: var(--radius-btn);
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: #0B2E2A; font-weight: 700; font-size: 16px; cursor: pointer;
            transition: 0.3s; box-shadow: 0 8px 30px rgba(212,175,55,0.3);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 14px 40px rgba(212,175,55,0.5); }
        .msg { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .msg.success { background: rgba(46,213,115,0.15); color: #2ED573; }
        .msg.error { background: rgba(255,71,87,0.15); color: #FF4757; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { color: var(--gold); font-weight: 600; }
        .badge { padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 11px; }
        .badge.active { background: rgba(46,213,115,0.2); color: #2ED573; }
        .badge.inactive { background: rgba(255,71,87,0.2); color: #FF4757; }
        .action-icons a { color: var(--gold); margin: 0 6px; text-decoration: none; transition: 0.2s; }
        .action-icons a:hover { opacity: 0.7; }
        .action-icons .delete { color: #FF4757; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2>Manage <span>Coupons</span></h2>

    <?php if ($msg): ?><div class="msg success"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg error"><?= h($err) ?></div><?php endif; ?>

    <!-- Create Coupon Form -->
    <div class="card">
        <h3 style="margin-bottom:20px; color:var(--gold);"><i class="fas fa-plus-circle"></i> Create New Coupon</h3>
        <?php if (!$hasTable): ?>
            <p style="color:var(--gray-soft);">The <code>coupons</code> table does not exist. Please create it in your database.</p>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Coupon Code *</label>
                <input type="text" name="code" placeholder="e.g. WELCOME50" required>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Discount Type</label>
                    <select name="discount_type">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Discount Value *</label>
                    <input type="number" name="discount_value" step="0.01" placeholder="10 or 50" required>
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Min. Order Amount</label>
                    <input type="number" name="min_order_amount" step="0.01" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Max Discount Cap</label>
                    <input type="number" name="max_discount" step="0.01" placeholder="0 (no cap)">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Optional description..."></textarea>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Valid From</label>
                    <input type="datetime-local" name="valid_from">
                </div>
                <div class="form-group">
                    <label>Valid To</label>
                    <input type="datetime-local" name="valid_to">
                </div>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="is_active" id="is_active" checked>
                <label for="is_active">Active immediately</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="is_featured" id="is_featured">
                <label for="is_featured">Featured (show in banner)</label>
            </div>
            <button type="submit" class="btn">Create Coupon</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Existing Coupons List -->
    <div class="card">
        <h3 style="margin-bottom:20px; color:var(--gold);"><i class="fas fa-list-ul"></i> All Coupons</h3>
        <?php if (empty($coupons)): ?>
            <p style="color:var(--gray-soft);">No coupons created yet.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Status</th>
                            <th>Valid Until</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $c): ?>
                            <tr>
                                <td><strong><?= h($c['code']) ?></strong></td>
                                <td><?= ($c['discount_type'] == 'percentage') ? $c['discount_value'].'%' : '₹'.$c['discount_value'] ?></td>
                                <td>
                                    <span class="badge <?= $c['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= $c['valid_to'] ? date('d M Y', strtotime($c['valid_to'])) : 'No expiry' ?></td>
                                <td class="action-icons">
                                    <a href="?action=toggle&id=<?= $c['id'] ?>" title="Toggle active"><?= $c['is_active'] ? '<i class="fas fa-toggle-on"></i>' : '<i class="fas fa-toggle-off"></i>' ?></a>
                                    <a href="?action=delete&id=<?= $c['id'] ?>" class="delete" title="Delete" onclick="return confirm('Delete this coupon?')"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>