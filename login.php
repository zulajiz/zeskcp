<?php
// login.php
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


$username = $password = "";
$username_err = $password_err = "";

// Jika sudah login, redirect ke admin.php
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: admin.php");
    exit;
}

// Proses form saat data disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) {
        $username_err = "Mohon masukkan username.";
    } else {
        $username = sanitize_input($_POST["username"]);
    }
    if (empty(trim($_POST["password"]))) {
        $password_err = "Mohon masukkan password.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            $_SESSION['loggedin'] = true;
                            $_SESSION['id'] = $id;
                            $_SESSION['username'] = $username;
                            header("location: admin.php");
                            exit;
                        } else {
                            $password_err = "Password yang Anda masukkan salah.";
                        }
                    }
                } else {
                    $username_err = "Tidak ada akun ditemukan dengan username tersebut.";
                }
            } else {
                echo "<div class='message error'>Terjadi kesalahan. Silakan coba lagi nanti.</div>";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel Anda - Login</title>
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
    <h2>Login Admin</h2>
    <?php if (!empty($username_err) || !empty($password_err)): ?>
        <div class="message error">
            <?php echo !empty($username_err) ? "<p>$username_err</p>" : ''; ?>
            <?php echo !empty($password_err) ? "<p>$password_err</p>" : ''; ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password">
        </div>
        <div class="form-group">
            <input type="submit" class="btn" value="Login">
        </div>
    </form>

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