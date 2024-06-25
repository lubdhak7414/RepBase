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
        $hashed = password_hash($pass, PASSWORD_BCRYPT);
        $db = db();
        $chk = $db->prepare("SELECT Member_id FROM member WHERE Email = ?");
        $chk->execute([$email]);
        if ($chk->rowCount() > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $stmt = $db->prepare("INSERT INTO member (Name, Email, Password, Phone, JoinDate) VALUES (?, ?, ?, ?, CURDATE())");
            $stmt->execute([$name, $email, $hashed, $phone]);
            $newId = (int)$db->lastInsertId();
            // Assign default Basic plan
            $ms = $db->prepare("INSERT INTO membership (Member_id, Plan_id, StartDate, EndDate, Active) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1)");
            $ms->execute([$newId]);
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
