<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

session_start_safe();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    if (!$name || !$email || !$pass) {
        $error = 'Name, email and password are required.';
    } else {
        // NAIVE: md5 hashing, direct string interpolation
        $hashed = md5($pass);
        $db = db();
        $check = $db->query("SELECT Member_id FROM member WHERE Email = '$email'");
        if ($check->rowCount() > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $db->query("INSERT INTO member (Name, Email, Password, Phone, JoinDate)
                        VALUES ('$name', '$email', '$hashed', '$phone', CURDATE())");
            $newId = (int)$db->lastInsertId();
            // Assign default Basic plan
            $db->query("INSERT INTO membership (Member_id, Plan_id, StartDate, EndDate, Active)
                        VALUES ($newId, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1)");
            flash_set('success', 'Account created! Please log in.');
            header('Location: login.php');
            exit;
        }
    }
}

page_head('Register');
page_nav();
?>
<div class="container" style="max-width:480px">
  <?php flash_html(); ?>
  <h2 class="mb-3">Create Account</h2>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required minlength="6">
    </div>
    <div class="mb-3">
      <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
      <input type="text" name="phone" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary w-100">Register</button>
    <p class="mt-3 text-center">Already have an account? <a href="login.php">Log in</a></p>
  </form>
</div>
<?php page_foot(); ?>
