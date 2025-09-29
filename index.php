<?php
// index.php - login
require 'inc/config.php';
if (is_logged_in()) header('Location: dashboard.php');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $msg = 'Invalid credentials.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login - CCMS</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }
    body {
      height: 100vh;
      display: flex;
    }
    /* Left side */
    .left {
      width: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: #fff;
      padding: 20px;
    }
    .left img {
      width: 120px;
      margin-bottom: 20px;
    }
    .signin-box {
      width: 100%;
      max-width: 320px;
    }
    .signin-box h2 {
      margin-bottom: 20px;
      font-size: 22px;
    }
    .alert {
      background: #ffcccc;
      color: #990000;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      font-size: 14px;
    }
    .input-group {
      margin-bottom: 15px;
    }
    .input-group label {
      display: block;
      margin-bottom: 5px;
      font-size: 14px;
    }
    .input-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
    }
    .signin-btn {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 25px;
      background: #4a3aff;
      color: #fff;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }
    .signin-btn:hover {
      background: #372ac9;
    }
    .create-link {
      display: block;
      margin-top: 15px;
      font-size: 14px;
      text-align: center;
    }
    /* Right side */
    .right {
      width: 50%;
      background: #0d3b8c;
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 40px;
      background: linear-gradient(135deg, #0d3b8c, #1f2f98);

    .right::before {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 77, 0.5); /* dark overlay */
    }
    .right h1 {
      position: relative; 
      z-index: 1;
    }
    .right h1 {
      font-size: 32px;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
  <div class="left">
    <img src="img/logo.png" alt="School Logo"> <!-- Replace logo.png with your logo -->
    <div class="signin-box">
      <h2>Sign in</h2>
      <?php if ($msg): ?><div class="alert"><?=htmlspecialchars($msg)?></div><?php endif; ?>
      <form method="post">
        <div class="input-group">
          <label>Email *</label>
          <input type="email" name="email" required>
        </div>
        <div class="input-group">
          <label>Password *</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="signin-btn">Sign in</button>
      </form>
      <a class="create-link" href="register.php">Create account</a>
    </div>
  </div>

  <div class="right">
    <h1>Club Management System</h1>
  </div>
</body>
</html>