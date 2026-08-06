<?php
echo "<h2>Upload Test</h2>";

// 1. Check folder
$dir = __DIR__ . '/uploads/payments/';
echo "Folder path: " . $dir . "<br>";
echo "Exists: " . (is_dir($dir) ? '✅ YES' : '❌ NO') . "<br>";
if (is_dir($dir)) {
    echo "Writable: " . (is_writable($dir) ? '✅ YES' : '❌ NO') . "<br>";
}

// 2. PHP settings
echo "<br><b>PHP Limits:</b><br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";

// 3. Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<br><b>Form submitted.</b><br>";
    echo "FILES array: <pre>" . print_r($_FILES, true) . "</pre>";

    if (!empty($_FILES['testfile'])) {
        $err = $_FILES['testfile']['error'];
        if ($err === UPLOAD_ERR_OK) {
            $tmp = $_FILES['testfile']['tmp_name'];
            $name = basename($_FILES['testfile']['name']);
            $dest = $dir . time() . '_' . $name;
            echo "Temp file: $tmp <br> Destination: $dest <br>";
            if (move_uploaded_file($tmp, $dest)) {
                echo "✅ <b>File uploaded successfully!</b><br>";
                echo "<img src='/uploads/payments/" . basename($dest) . "' style='max-width:300px;'>";
            } else {
                echo "❌ <b>move_uploaded_file failed.</b> Check folder permissions.<br>";
            }
        } else {
            echo "❌ Upload error code: $err<br>";
        }
    } else {
        echo "❌ No file was sent.<br>";
    }
}
?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="testfile" required>
    <button type="submit">Test Upload</button>
</form>