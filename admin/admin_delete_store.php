<?php
require_once 'config.php';
if (!isLoggedIn() || $_SESSION['role'] != 'admin') die();
$id = (int)$_GET['id'];
mysqli_query($conn, "DELETE FROM stores WHERE id=$id");
redirect('admin_stores.php');
