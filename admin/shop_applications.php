<?php
require_once '../config.php';
if (!isAdmin()) { redirect('login.php'); }

$message = '';

// Handle status update or checklist update (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int)($_POST['app_id'] ?? 0);

    if (isset($_POST['update_status'])) {
        $newStatus = mysqli_real_escape_string($conn, $_POST['status']);
        $remarks   = mysqli_real_escape_string($conn, trim($_POST['admin_remarks'] ?? ''));
        mysqli_query($conn, "UPDATE shop_applications SET status = '$newStatus', admin_remarks = '$remarks' WHERE id = $appId");
        $message = '<div class="alert success">Application #'.$appId.' updated to '.ucfirst($newStatus).'.</div>';
    } elseif (isset($_POST['update_checks'])) {
        $mobile_ok         = isset($_POST['mobile_verified']) ? 1 : 0;
        $location_ok       = isset($_POST['shop_location_verified']) ? 1 : 0;
        $docs_ok           = isset($_POST['documents_verified']) ? 1 : 0;
        $photo_ok          = isset($_POST['shop_photo_verified']) ? 1 : 0;
        $licence_ok        = isset($_POST['licence_verified']) ? 1 : 0;
        $remarks           = mysqli_real_escape_string($conn, trim($_POST['admin_remarks'] ?? ''));
        mysqli_query($conn, "UPDATE shop_applications SET 
            mobile_verified = $mobile_ok,
            shop_location_verified = $location_ok,
            documents_verified = $docs_ok,
            shop_photo_verified = $photo_ok,
            licence_verified = $licence_ok,
            admin_remarks = '$remarks'
            WHERE id = $appId");
        $message = '<div class="alert success">Verification checklist updated for Application #'.$appId.'.</div>';
    }
}

// Fetch applications
$appsResult = mysqli_query($conn, "
    SELECT sa.*, u.full_name AS applicant_name, u.phone AS applicant_phone
    FROM shop_applications sa
    JOIN users u ON sa.user_id = u.id
    ORDER BY sa.id DESC
");

$applications = [];
if ($appsResult) {
    while ($app = mysqli_fetch_assoc($appsResult)) {
        $applications[] = $app;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Applications – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --primary:#FF6B35; --bg:#0A0A0A; --card:rgba(20,20,20,0.95); --border:rgba(255,255,255,0.08); --text:#fff; --text-secondary:#B0B0B0; --sidebar-width:260px; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; overflow-x:hidden; }
        .sidebar { position:fixed; top:0; left:0; height:100%; width:var(--sidebar-width); background:var(--card); border-right:1px solid var(--border); padding:30px 20px; display:flex; flex-direction:column; gap:4px; z-index:1000; transform:translateX(-100%); transition:transform 0.3s ease; }
        .sidebar.open { transform:translateX(0); }
        .sidebar h2 { font-size:22px; margin-bottom:20px; color:var(--primary); }
        .sidebar a { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:10px; color:var(--text-secondary); text-decoration:none; font-weight:500; transition:0.2s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.05); color:#fff; }
        .sidebar a i { width:20px; color:var(--primary); }
        .badge { background:var(--primary); color:#fff; padding:2px 8px; border-radius:20px; font-size:12px; margin-left:auto; }
        .sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; opacity:0; pointer-events:none; transition:opacity 0.3s; }
        .sidebar-overlay.show { opacity:1; pointer-events:auto; }

        .main { flex:1; padding:30px; margin-left:0; transition:margin-left 0.3s; }
        .top-bar { display:flex; align-items:center; gap:15px; margin-bottom:30px; }
        .hamburger { width:42px; height:42px; border-radius:12px; background:var(--card); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text); font-size:20px; cursor:pointer; }
        .hamburger:hover { background:rgba(255,255,255,0.05); }
        .page-title { font-size:24px; font-weight:700; }

        .alert { padding:10px 15px; border-radius:8px; margin-bottom:15px; }
        .alert.success { background:#1b5e20; color:#a5d6a7; }
        .btn { padding:8px 16px; border-radius:6px; border:none; cursor:pointer; font-weight:600; font-size:14px; text-decoration:none; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-sm { padding:4px 10px; font-size:13px; }
        table { width:100%; border-collapse:collapse; background:var(--card); border-radius:12px; overflow:hidden; margin-top:20px; }
        th, td { padding:12px; text-align:left; border-bottom:1px solid var(--border); }
        th { background:rgba(255,255,255,0.03); color:var(--text-secondary); }
        .status { font-weight:600; }
        .status.pending { color:#ffc107; }
        .status.approved { color:#4ade80; }
        .status.rejected { color:#ef4444; }

        /* Modal */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); justify-content:center; align-items:flex-start; padding:40px 20px; z-index:9999; }
        .modal.active { display:flex; }
        .modal-content { background:var(--card); border:1px solid var(--border); border-radius:14px; width:100%; max-width:700px; max-height:85vh; overflow-y:auto; padding:25px; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .modal-header h2 { color:var(--primary); }
        .close-btn { background:none; border:none; color:var(--text-secondary); font-size:20px; cursor:pointer; }
        .info-row { display:flex; margin-bottom:8px; }
        .info-label { width:180px; color:var(--text-secondary); flex-shrink:0; }
        .info-value { color:var(--text); word-break:break-word; }
        .file-link { color:var(--primary); text-decoration:underline; display:inline-flex; align-items:center; gap:4px; }
        .file-link:hover { opacity:0.8; }
        .img-preview { max-width:200px; max-height:150px; border-radius:8px; margin-top:4px; display:block; cursor:pointer; }
        .checklist { display:flex; flex-direction:column; gap:8px; margin:10px 0; }
        .checklist label { display:flex; align-items:center; gap:8px; color:var(--text); cursor:pointer; }
        hr { border-color:var(--border); margin:15px 0; }

        @media (min-width: 768px) {
            .sidebar { transform:translateX(0); }
            .hamburger { display:none; }
            .main { margin-left:var(--sidebar-width); }
            .sidebar-overlay { display:none; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <h2>HelpGo</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="riders.php"><i class="fas fa-user-plus"></i> Manage Riders</a>
    <a href="verification.php"><i class="fas fa-id-card"></i> Verification</a>
    <a href="orders.php"><i class="fas fa-list-check"></i> Orders</a>
    <a href="users.php"><i class="fas fa-users"></i> Customers</a>
    <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
    <a href="withdrawals.php"><i class="fas fa-money-bill-transfer"></i> Withdrawals</a>
    <a href="live_orders.php"><i class="fas fa-map-marked-alt"></i> Live Orders</a>
    <a href="profits.php"><i class="fas fa-chart-line"></i> Profits</a>
    <a href="coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a href="admin_stores.php"><i class="fas fa-store"></i> Stores</a>
    <a href="delivery_fee.php"><i class="fas fa-truck"></i> Delivery Fee</a>
    <a href="shop_applications.php" class="active"><i class="fas fa-clipboard-check"></i> Shop Applications</a>
    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
    <a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a>
    <a href="reviews.php"><i class="fas fa-star"></i> Reviews</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="../logout.php" style="margin-top:20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>

<div class="main">
    <div class="top-bar">
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
        <h1 class="page-title">📋 Shop Applications</h1>
    </div>
    <?= $message ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Shop Name</th>
                <th>Owner</th>
                <th>Category</th>
                <th>Status</th>
                <th>Applied</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td>#<?= $app['id'] ?></td>
                <td><?= htmlspecialchars($app['shop_name']) ?></td>
                <td><?= htmlspecialchars($app['owner_name']) ?></td>
                <td><?= htmlspecialchars($app['shop_category']) ?></td>
                <td class="status <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></td>
                <td><?= date('d M Y', strtotime($app['created_at'])) ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="viewApplication(<?= $app['id'] ?>)"><i class="fas fa-eye"></i> View</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- View/Edit Modal -->
<div class="modal" id="appModal">
    <div class="modal-content" id="modalContent"></div>
</div>

<script>
// ---------- Sidebar toggle ----------
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
}

document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('open')) {
            e.preventDefault();
            const href = this.getAttribute('href');
            toggleSidebar();
            setTimeout(() => { window.location = href; }, 150);
        }
    });
});

// ---------- Applications data ----------
const applications = <?= json_encode($applications) ?>;

// Helper to generate file preview/link for uploads
function fileDisplay(filename, isImage = false) {
    if (!filename) return '<span class="info-value" style="color:var(--text-secondary);">Not provided</span>';
    const url = '../uploads/shop_applications/' + filename;
    if (isImage) {
        // Assume common image extensions
        const ext = filename.split('.').pop().toLowerCase();
        if (['jpg','jpeg','png','webp','gif'].includes(ext)) {
            return `<a href="${url}" target="_blank"><img src="${url}" class="img-preview" onerror="this.style.display='none'" alt="Image"></a>
                    <a href="${url}" target="_blank" class="file-link"><i class="fas fa-download"></i> Download</a>`;
        }
    }
    return `<a href="${url}" target="_blank" class="file-link"><i class="fas fa-file"></i> View / Download</a>`;
}

function viewApplication(id) {
    const app = applications.find(a => a.id == id);
    if (!app) {
        alert("Application not found.");
        return;
    }
    const modal = document.getElementById('appModal');
    const content = document.getElementById('modalContent');

    // Build full details
    let html = `
        <div class="modal-header">
            <h2>${app.shop_name} (#${app.id})</h2>
            <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <h3 style="margin-bottom:10px;color:var(--primary)">Shop Information</h3>
        <div class="info-row"><span class="info-label">Shop Name:</span><span class="info-value">${app.shop_name}</span></div>
        <div class="info-row"><span class="info-label">Owner Name:</span><span class="info-value">${app.owner_name}</span></div>
        <div class="info-row"><span class="info-label">Mobile:</span><span class="info-value">${app.mobile}</span></div>
        <div class="info-row"><span class="info-label">WhatsApp:</span><span class="info-value">${app.whatsapp || '—'}</span></div>
        <div class="info-row"><span class="info-label">Email:</span><span class="info-value">${app.email || '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Address</h3>
        <div class="info-row"><span class="info-label">Full Address:</span><span class="info-value">${app.full_address}</span></div>
        <div class="info-row"><span class="info-label">Town/Area:</span><span class="info-value">${app.town_area}</span></div>
        <div class="info-row"><span class="info-label">Landmark:</span><span class="info-value">${app.landmark || '—'}</span></div>
        <div class="info-row"><span class="info-label">Google Maps:</span><span class="info-value">${app.google_maps_link ? `<a href="${app.google_maps_link}" target="_blank" class="file-link">Open Map</a>` : '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Business Details</h3>
        <div class="info-row"><span class="info-label">Category:</span><span class="info-value">${app.shop_category}</span></div>
        <div class="info-row"><span class="info-label">Description:</span><span class="info-value">${app.business_description || '—'}</span></div>
        <div class="info-row"><span class="info-label">Opening Time:</span><span class="info-value">${app.opening_time || '—'}</span></div>
        <div class="info-row"><span class="info-label">Closing Time:</span><span class="info-value">${app.closing_time || '—'}</span></div>
        <div class="info-row"><span class="info-label">Weekly Holiday:</span><span class="info-value">${app.weekly_holiday || '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Documents</h3>
        <div class="info-row"><span class="info-label">Shop Photo:</span><span class="info-value">${fileDisplay(app.shop_photo, true)}</span></div>
        <div class="info-row"><span class="info-label">Shop Logo:</span><span class="info-value">${fileDisplay(app.shop_logo, true)}</span></div>
        <div class="info-row"><span class="info-label">Owner Photo:</span><span class="info-value">${fileDisplay(app.owner_photo, true)}</span></div>
        <div class="info-row"><span class="info-label">Government ID:</span><span class="info-value">${fileDisplay(app.govt_id, false)}</span></div>
        <div class="info-row"><span class="info-label">Business Licence:</span><span class="info-value">${fileDisplay(app.business_licence, false)}</span></div>
        <div class="info-row"><span class="info-label">GST Number:</span><span class="info-value">${app.gst_number || '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Delivery Details</h3>
        <div class="info-row"><span class="info-label">Delivery Available:</span><span class="info-value">${app.delivery_available == 1 ? 'Yes' : 'No'}</span></div>
        <div class="info-row"><span class="info-label">Delivery Type:</span><span class="info-value">${app.delivery_type}</span></div>
        <div class="info-row"><span class="info-label">Delivery Radius:</span><span class="info-value">${app.delivery_radius ? app.delivery_radius + ' KM' : '—'}</span></div>
        <div class="info-row"><span class="info-label">Min Order Amount:</span><span class="info-value">${app.min_order_amount ? '₹' + app.min_order_amount : '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Bank Details</h3>
        <div class="info-row"><span class="info-label">Account Holder:</span><span class="info-value">${app.account_holder_name || '—'}</span></div>
        <div class="info-row"><span class="info-label">Bank Name:</span><span class="info-value">${app.bank_name || '—'}</span></div>
        <div class="info-row"><span class="info-label">Account Number:</span><span class="info-value">${app.account_number || '—'}</span></div>
        <div class="info-row"><span class="info-label">IFSC Code:</span><span class="info-value">${app.ifsc_code || '—'}</span></div>
        <div class="info-row"><span class="info-label">UPI ID:</span><span class="info-value">${app.upi_id || '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Product Details</h3>
        <div class="info-row"><span class="info-label">Product List File:</span><span class="info-value">${fileDisplay(app.product_list_file, false)}</span></div>
        <div class="info-row"><span class="info-label">Price List File:</span><span class="info-value">${fileDisplay(app.price_list_file, false)}</span></div>
        <div class="info-row"><span class="info-label">Sample Images:</span><span class="info-value">${app.sample_product_images ? app.sample_product_images.split(',').map(img => fileDisplay(img.trim(), true)).join('<br>') : '—'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Agreements</h3>
        <div class="info-row"><span class="info-label">Info Correct:</span><span class="info-value">${app.agree_info == 1 ? '✅ Yes' : '❌ No'}</span></div>
        <div class="info-row"><span class="info-label">Responsibility:</span><span class="info-value">${app.agree_responsibility == 1 ? '✅ Yes' : '❌ No'}</span></div>
        <div class="info-row"><span class="info-label">Terms Accepted:</span><span class="info-value">${app.agree_terms == 1 ? '✅ Yes' : '❌ No'}</span></div>
        <div class="info-row"><span class="info-label">No False Info:</span><span class="info-value">${app.agree_no_false_info == 1 ? '✅ Yes' : '❌ No'}</span></div>
        <hr>
        <h3 style="margin-bottom:10px;color:var(--primary)">Admin Actions</h3>
        <div class="info-row"><span class="info-label">Current Status:</span><span class="info-value"><span class="status ${app.status}">${app.status.charAt(0).toUpperCase()+app.status.slice(1)}</span></span></div>
        <div class="info-row"><span class="info-label">Admin Remarks:</span><span class="info-value">${app.admin_remarks || '—'}</span></div>
    `;

    // Add checklist and status update forms
    html += `
        <form method="POST" style="margin-top:15px;">
            <input type="hidden" name="app_id" value="${app.id}">
            <h4 style="margin-bottom:8px;">Verification Checklist</h4>
            <div class="checklist">
                <label><input type="checkbox" name="mobile_verified" ${app.mobile_verified==1?'checked':''}> Mobile Verified</label>
                <label><input type="checkbox" name="shop_location_verified" ${app.shop_location_verified==1?'checked':''}> Shop Location Verified</label>
                <label><input type="checkbox" name="documents_verified" ${app.documents_verified==1?'checked':''}> Documents Verified</label>
                <label><input type="checkbox" name="shop_photo_verified" ${app.shop_photo_verified==1?'checked':''}> Shop Photo Verified</label>
                <label><input type="checkbox" name="licence_verified" ${app.licence_verified==1?'checked':''}> Licence Verified</label>
            </div>
            <textarea name="admin_remarks" placeholder="Remarks..." style="width:100%;margin:10px 0;background:#111;border:1px solid #333;color:#fff;padding:8px;border-radius:6px;">${app.admin_remarks||''}</textarea>
            <button type="submit" name="update_checks" class="btn btn-primary btn-sm">Save Checklist</button>
        </form>
        <hr>
        <form method="POST" style="margin-top:15px;">
            <input type="hidden" name="app_id" value="${app.id}">
            <input type="hidden" name="update_status" value="1">
            <h4 style="margin-bottom:8px;">Change Status</h4>
            <select name="status" style="background:#111;border:1px solid #333;color:#fff;padding:6px;border-radius:6px;">
                <option value="pending" ${app.status=='pending'?'selected':''}>Pending</option>
                <option value="approved" ${app.status=='approved'?'selected':''}>Approved</option>
                <option value="rejected" ${app.status=='rejected'?'selected':''}>Rejected</option>
            </select>
            <textarea name="admin_remarks" placeholder="Remarks..." style="width:100%;margin:10px 0;background:#111;border:1px solid #333;color:#fff;padding:8px;border-radius:6px;">${app.admin_remarks||''}</textarea>
            <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
        </form>
    `;

    content.innerHTML = html;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('appModal').classList.remove('active');
}

document.getElementById('appModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>