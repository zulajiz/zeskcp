<?php
// web_management.php
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

$domain_name = $web_path = "";
$domain_name_err = $web_path_err = "";
$message = "";
$message_type = "";

// Proses Tambah Web
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_web') {
    if (empty(trim($_POST["domain_name"]))) {
        $domain_name_err = "Mohon masukkan nama domain.";
    } else {
        $domain_name = sanitize_input($_POST["domain_name"]);
    }
    if (empty(trim($_POST["web_path"]))) {
        $web_path_err = "Mohon masukkan path web.";
    } else {
        $web_path = sanitize_input($_POST["web_path"]);
        $web_path = rtrim($web_path, '/');
        if (substr($web_path, 0, 1) !== '/') {
            $web_path = '/' . $web_path;
        }
    }

    if (empty($domain_name_err) && empty($web_path_err)) {
        $sql_check = "SELECT id FROM websites WHERE domain_name = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("s", $domain_name);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $domain_name_err = "Domain ini sudah terdaftar.";
            }
            $stmt_check->close();
        }
    }

    if (empty($domain_name_err) && empty($web_path_err)) {
        $sql = "INSERT INTO websites (domain_name, path) VALUES (?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $domain_name, $web_path);
            if ($stmt->execute()) {
                $message = "Website berhasil ditambahkan ke database.";
                $message_type = "success";
                
                if (!file_exists($web_path)) {
                    if (strpos($web_path, '/var/www/') === 0 || strpos($web_path, '/home/') === 0) {
                        if (mkdir($web_path, 0755, true)) {
                            $message .= " Direktori web berhasil dibuat.";
                        } else {
                            $message .= " Gagal membuat direktori web. Periksa izin.";
                            $message_type = "error";
                        }
                    } else {
                        $message .= " Path direktori web tidak diizinkan untuk pembuatan otomatis.";
                        $message_type = "error";
                    }
                } else {
                    $message .= " Direktori web sudah ada.";
                }
                header("location: web_management.php?msg=" . urlencode($message) . "&type=" . $message_type);
                exit;
            } else {
                $message = "Error: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Proses Hapus Web
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = sanitize_input($_GET['id']);
    $path_to_delete = '';

    $sql_get_path = "SELECT path FROM websites WHERE id = ?";
    if ($stmt_path = $conn->prepare($sql_get_path)) {
        $stmt_path->bind_param("i", $id);
        if ($stmt_path->execute()) {
            $stmt_path->bind_result($path_to_delete_db);
            if ($stmt_path->fetch()) {
                $path_to_delete = $path_to_delete_db;
            }
            $stmt_path->close();
        }
    }

    $sql_delete = "DELETE FROM websites WHERE id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute()) {
            $message = "Website berhasil dihapus dari database.";
            $message_type = "success";

            if (!empty($path_to_delete) && is_dir($path_to_delete) && 
                (strpos($path_to_delete, '/var/www/') === 0 || strpos($path_to_delete, '/home/') === 0)) {
                if (delete_directory_recursive($path_to_delete)) {
                    $message .= " Folder fisik web juga dihapus.";
                } else {
                    $message .= " Gagal menghapus folder fisik web. Periksa izin.";
                    $message_type = "error";
                }
            } else {
                $message .= " Path fisik web tidak valid, tidak ditemukan, atau tidak diizinkan untuk dihapus secara otomatis.";
            }
        } else {
            $message = "Gagal menghapus website dari database: " . $stmt_delete->error;
            $message_type = "error";
        }
        $stmt_delete->close();
    } else {
        $message = "Gagal mempersiapkan statement hapus: " . $conn->error;
        $message_type = "error";
    }
    header("location: admin.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit;
}

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel Anda - Kelola Web</title>
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
    <h2>Kelola Website</h2>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <h3>Tambah Website Baru</h3>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label for="domain_name">Nama Domain:</label>
            <input type="text" id="domain_name" name="domain_name" value="<?php echo htmlspecialchars($domain_name); ?>" placeholder="contoh.com" required>
            <?php if (!empty($domain_name_err)): ?><span class="error"><?php echo $domain_name_err; ?></span><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="web_path">Path Web (contoh: /var/www/nama_domain_anda/public_html):</label>
            <input type="text" id="web_path" name="web_path" value="<?php echo htmlspecialchars($web_path); ?>" placeholder="/var/www/contoh.com/public_html" required>
            <?php if (!empty($web_path_err)): ?><span class="error"><?php echo $web_path_err; ?></span><?php endif; ?>
        </div>
        <div class="form-group">
            <input type="hidden" name="action" value="add_web">
            <input type="submit" class="btn" value="Tambah Web">
        </div>
    </form>

    <p style="margin-top: 20px;">Untuk melihat daftar web yang sudah ada, kembali ke <a href="admin.php">Dashboard</a>.</p>

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