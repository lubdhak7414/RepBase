<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

session_start_safe();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // NAIVE: md5 comparison, plain string query
    $hashed = md5($pass);
    $db = db();
    $row = $db->query("SELECT * FROM member WHERE Email = '$email' AND Password = '$hashed'")->fetch();

    if ($row) {
        $_SESSION['member_id']   = $row['Member_id'];
        $_SESSION['member_name'] = $row['Name'];
        header('Location: my_account.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}

page_head('Member Login');
page_nav();
?>
<div class="container" style="max-width:420px">
  <?php flash_html(); ?>
  <h2 class="mb-3">Member Login</h2>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Log In</button>
    <p class="mt-3 text-center">Don't have an account? <a href="register.php">Register</a></p>
  </form>
  <p class="text-center mt-2"><a href="staff_login.php">Staff login →</a></p>
</div>
<?php page_foot(); ?>
