<?php
// file_manager.php
session_start();
require_once 'config.php';

// Fungsi bantu (dipindahkan ke sini untuk kemudahan)
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return mysqli_real_escape_string($conn, $data);
}
function get_server_info() {
    return [
        'php_version' => phpversion(),
        'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A',
        'ip_address' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'N/A',
        'os' => php_uname(),
    ];
}
function delete_directory_recursive($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!delete_directory_recursive($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}


function check_admin_login() {
    session_start();
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("location: login.php");
        exit;
    }
}
check_admin_login();

$base_dir = '/var/www/'; // Root direktori yang diizinkan untuk diakses oleh file manager
$current_dir = $base_dir;
$message = "";
$message_type = "";

if (isset($_GET['dir']) && !empty($_GET['dir'])) {
    $requested_dir = realpath($base_dir . '/' . $_GET['dir']);
    if ($requested_dir !== false && strpos($requested_dir, $base_dir) === 0 && is_dir($requested_dir)) {
        $current_dir = $requested_dir;
    } else {
        $message = "Akses ditolak atau direktori tidak valid.";
        $message_type = "error";
    }
}

// Handle Upload File
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == UPLOAD_ERR_OK) {
    $target_file = $current_dir . '/' . basename($_FILES['upload_file']['name']);
    if (file_exists($target_file)) {
        $message = "Maaf, file sudah ada.";
        $message_type = "error";
    } else {
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target_file)) {
            $message = "File " . htmlspecialchars(basename($_FILES['upload_file']['name'])) . " berhasil diupload.";
            $message_type = "success";
        } else {
            $message = "Gagal mengupload file. Periksa izin direktori.";
            $message_type = "error";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] != UPLOAD_ERR_NO_FILE) {
    $message = "Gagal mengupload file. Kode error: " . $_FILES['upload_file']['error'];
    $message_type = "error";
}

// Handle Hapus File/Folder
if (isset($_GET['action']) && $_GET['action'] == 'delete_file' && isset($_GET['path'])) {
    $path_to_delete_raw = $_GET['path'];
    $file_to_delete = realpath($base_dir . '/' . $path_to_delete_raw);

    if ($file_to_delete !== false && strpos($file_to_delete, $base_dir) === 0) {
        if (is_file($file_to_delete)) {
            if (unlink($file_to_delete)) {
                $message = "File berhasil dihapus.";
                $message_type = "success";
            } else {
                $message = "Gagal menghapus file. Periksa izin.";
                $message_type = "error";
            }
        } elseif (is_dir($file_to_delete)) {
            if (delete_directory_recursive($file_to_delete)) {
                $message = "Folder berhasil dihapus.";
                $message_type = "success";
            } else {
                $message = "Gagal menghapus folder (mungkin tidak kosong atau ada masalah izin).";
                $message_type = "error";
            }
        } else {
            $message = "Path tidak valid atau bukan file/folder.";
            $message_type = "error";
        }
    } else {
        $message = "Akses ditolak: Tidak bisa menghapus di luar direktori yang diizinkan.";
        $message_type = "error";
    }
    header("location: file_manager.php?dir=" . urlencode(str_replace($base_dir, '', dirname($file_to_delete))) . "&msg=" . urlencode($message) . "&type=" . $message_type);
    exit;
}

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
}

$files = [];
if (is_dir($current_dir) && $handle = opendir($current_dir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $path = $current_dir . '/' . $entry;
            $files[] = [
                'name' => $entry,
                'type' => is_dir($path) ? 'folder' : 'file',
                'size' => is_file($path) ? filesize($path) : '-',
                'modified' => date("Y-m-d H:i:s", filemtime($path))
            ];
        }
    }
    closedir($handle);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel Anda - File Manager</title>
    <style>
        /* CSS digabung di sini */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        header { background-color: #333; color: #fff; padding: 1em 0; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        header h1 { margin: 0; padding-bottom: 10px; }
        nav ul { list-style: none; padding: 0; margin: 0; display: flex; justify-content: center; flex-wrap: wrap; }
        nav ul li { margin: 0 15px; }
        nav ul li a { color: #fff; text-decoration: none; padding: 5px 10px; border-radius: 4px; transition: background-color 0.3s ease; }
        nav ul li a:hover, nav ul li a.active { background-color: #555; }
        main { padding: 20px; max-width: 960px; margin: 20px auto; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2, h3 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="file"] { width: calc(100% - 20px); padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s ease; text-decoration: none; display: inline-block; }
        .btn:hover { background-color: #0056b3; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #e0e0e0; }
        th, td { padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; color: #333; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #e9e9e9; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        span.error { color: #dc3545; font-size: 0.9em; display: block; margin-top: 5px; }
        ul.info-list { list-style: none; padding: 0; }
        ul.info-list li { background-color: #e9ecef; margin-bottom: 8px; padding: 10px; border-left: 5px solid #007bff; border-radius: 4px; }
    </style>
</head>
<body>
    <header>
        <h1>Control Panel Anda</h1>
        <nav>
            <ul>
                <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) : ?>
                    <li><a href="admin.php">Dashboard</a></li>
                    <li><a href="web_management.php">Kelola Web</a></li>
                    <li><a href="file_manager.php">File Manager</a></li>
                    <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                <?php else : ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
    <h2>File Manager</h2>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <p>Direktori saat ini: <strong><?php echo htmlspecialchars(str_replace($base_dir, '', $current_dir) ?: '/'); ?></strong></p>
    <p>
        <?php
        $relative_current_dir = str_replace($base_dir, '', $current_dir);
        $parent_dir = dirname($relative_current_dir);
        if ($relative_current_dir == '' || $relative_current_dir == '/') {
            echo '<span class="btn">Kembali ke Root (Sudah di Root)</span>';
        } else {
            echo '<a href="file_manager.php?dir=' . urlencode($parent_dir) . '" class="btn">Kembali ke Atas</a>';
        }
        ?>
    </p>

    <h3>Upload File</h3>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?dir=' . urlencode(str_replace($base_dir, '', $current_dir)); ?>" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="upload_file">Pilih File:</label>
            <input type="file" id="upload_file" name="upload_file" required>
        </div>
        <div class="form-group">
            <input type="submit" class="btn" value="Upload">
        </div>
    </form>

    <h3>Daftar File dan Folder</h3>
    <?php if (empty($files)): ?>
        <p>Direktori kosong.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th>Ukuran</th>
                    <th>Terakhir Diubah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td>
                            <?php if ($file['type'] == 'folder'): ?>
                                <a href="file_manager.php?dir=<?php echo urlencode(str_replace($base_dir, '', $current_dir) . '/' . $file['name']); ?>">📁 <?php echo htmlspecialchars($file['name']); ?></a>
                            <?php else: ?>
                                📄 <?php echo htmlspecialchars($file['name']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($file['type']); ?></td>
                        <td><?php echo htmlspecialchars($file['size']); ?> bytes</td>
                        <td><?php echo htmlspecialchars($file['modified']); ?></td>
                        <td>
                            <a href="file_manager.php?action=delete_file&path=<?php echo urlencode(str_replace($base_dir, '', $current_dir) . '/' . $file['name']); ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus <?php echo $file['type'] == 'folder' ? 'folder dan isinya' : 'file'; ?> ini?');">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Control Panel Anda. All rights reserved.</p>
    </footer>
    <script>
        // JavaScript digabung di sini
        console.log("Control Panel JavaScript Loaded!");
    </script>
</body>
</html>
<?php $conn->close(); ?>