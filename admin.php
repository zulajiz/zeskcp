<?php
// admin.php
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

$server_info = get_server_info();
$message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$message_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';

$websites = [];
$sql = "SELECT id, domain_name, path FROM websites ORDER BY domain_name ASC";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $websites[] = $row;
    }
    $result->free();
} else {
    $message = "Error mengambil daftar web: " . $conn->error;
    $message_type = "error";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel Anda - Dashboard</title>
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
    <h2>Dashboard Admin - Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <h3>Informasi Server</h3>
    <ul class="info-list">
        <li>Versi PHP: <strong><?php echo htmlspecialchars($server_info['php_version']); ?></strong></li>
        <li>Software Server: <strong><?php echo htmlspecialchars($server_info['server_software']); ?></strong></li>
        <li>IP Server: <strong><?php echo htmlspecialchars($server_info['ip_address']); ?></strong></li>
        <li>Sistem Operasi: <strong><?php echo htmlspecialchars($server_info['os']); ?></strong></li>
    </ul>

    <h3>Website yang Dikelola</h3>
    <?php if (empty($websites)): ?>
        <p>Belum ada website yang ditambahkan. <a href="web_management.php" class="btn">Tambah Sekarang</a></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Domain</th>
                    <th>Path</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($websites as $web): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($web['id']); ?></td>
                        <td><?php echo htmlspecialchars($web['domain_name']); ?></td>
                        <td><?php echo htmlspecialchars($web['path']); ?></td>
                        <td>
                            <a href="web_management.php?action=delete&id=<?php echo $web['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus web ini? Ini juga akan mencoba menghapus folder fisiknya!');">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top: 20px;"><a href="web_management.php" class="btn">Tambah Web Baru</a></p>

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