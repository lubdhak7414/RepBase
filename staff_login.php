<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

session_start_safe();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';

    // NAIVE: md5 comparison, plain string query
    $hashed = md5($pass);
    $db = db();
    $row = $db->query("SELECT * FROM staff WHERE Username = '$username' AND Password = '$hashed'")->fetch();

    if ($row) {
        $_SESSION['staff_id']       = $row['Staff_id'];
        $_SESSION['staff_username'] = $row['Username'];
        $_SESSION['staff_role']     = $row['Role'];

        if ($row['Role'] === 'admin') {
            header('Location: admin_members.php');
        } else {
            header('Location: trainer_roster.php');
        }
        exit;
    } else {
        $error = 'Invalid credentials.';
    }
}

page_head('Staff Login');
page_nav();
?>
<div class="container" style="max-width:420px">
  <h2 class="mb-3">Staff Login</h2>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-dark w-100">Staff Login</button>
    <p class="mt-3 text-center"><a href="login.php">← Member login</a></p>
  </form>
</div>
<?php page_foot(); ?>
