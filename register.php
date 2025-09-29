<?php
require 'inc/config.php';
if (is_logged_in()) header('Location: dashboard.php');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($name == '' || $email == '' || $pass == '') $msg = 'Please complete all fields.';
    else {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $msg = 'Email already registered.';
        else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (full_name,email,password,role) VALUES (?,?,?,?)");
            $ins->execute([$name, $email, $hash, 'Student']); // default role = Student
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Register - CCMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6">
  <div class="card"><div class="card-body">
    <h4>Create account</h4>
    <?php if ($msg): ?><div class="alert alert-danger"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <form method="post">
      <div class="mb-2"><label>Full name</label><input class="form-control" name="full_name" required></div>
      <div class="mb-2"><label>Email</label><input type="email" class="form-control" name="email" required></div>
      <div class="mb-2"><label>Password</label><input type="password" class="form-control" name="password" required></div>
      <button class="btn btn-success">Register</button>
    </form>
    <hr><a href="index.php">Back to login</a>
  </div></div>
</div></div></div>
</body></html>
