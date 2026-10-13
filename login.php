<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = request_string($_POST, 'username', 50, false) ?? '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    enforce_login_rate_limit($conn, $username);

    if ($username === '' || $password === '' || strlen($password) > 4096) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, username, password_hash, role, status FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Account not found.';
        } elseif ($user['status'] !== 'active') {
            $error = 'This account has been disabled. Contact your administrator.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect password.';
        } else {

            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['just_logged_in'] = true;

            log_activity($conn, 'Logged in', 'via login form', 'auth');

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login  C1SO TECH Work Log &amp; Inventory</title>
<?php echo render_font_links(); ?>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-brand">
      <div class="brand-mark">C1SO TECH</div>
      <h1>Work Log &amp; Inventory System</h1>
      <p class="login-sub">Sign in with your admin or staff account</p>
    </div>

    <?php if ($error): ?>
      <div class="login-error" id="loginError" role="alert"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="login-form">
      <div class="field">
        <label for="loginUsername">Username</label>
        <input type="text" id="loginUsername" name="username" autocomplete="username" required autofocus value="<?php echo h($_POST['username'] ?? ''); ?>" <?php echo $error ? 'aria-describedby="loginError"' : ''; ?>>
      </div>
      <div class="field">
        <label for="loginPassword">Password</label>
        <input type="password" id="loginPassword" name="password" autocomplete="current-password" required <?php echo $error ? 'aria-describedby="loginError"' : ''; ?>>
      </div>
      <button type="submit" class="btn-primary btn-block">Login</button>
    </form>

   <!-- <p class="login-footnote">First time? Run <code>setup.php</code> once to create the default admin account.</p> -->
  </div>
</body>
</html>
