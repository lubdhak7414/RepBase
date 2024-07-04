<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member  = require_member();
$db      = db();
$mid     = $member['id'];
$classId = (int)($_GET['id'] ?? 0);

if (!$classId) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("
    SELECT c.*, t.Name AS TrainerName
    FROM class c JOIN trainer t ON t.Trainer_id = c.Trainer_id
    WHERE c.Class_id = ?
");
$stmt->execute([$classId]);
$class = $stmt->fetch();

if (!$class) {
    flash_set('error', 'Class not found.');
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT Status FROM booking WHERE Class_id = ? AND Member_id = ?");
$stmt->execute([$classId, $mid]);
$existing = $stmt->fetch();

$stmt = $db->prepare("SELECT COUNT(*) FROM booking WHERE Class_id = ? AND Status = 'booked'");
$stmt->execute([$classId]);
$bookedCount = (int)$stmt->fetchColumn();
$remaining = (int)$class['Capacity'] - $bookedCount;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($existing) {
        flash_set('error', 'You already have a booking for this class (status: ' . $existing['Status'] . ').');
    } elseif ($remaining > 0) {
        // Class opened up — book directly
        $ins = $db->prepare("INSERT INTO booking (Class_id, Member_id, BookedAt, Status) VALUES (?, ?, NOW(), 'booked')");
        $ins->execute([$classId, $mid]);
        flash_set('success', 'A spot opened up — you have been booked!');
    } else {
        $ins = $db->prepare("INSERT INTO booking (Class_id, Member_id, BookedAt, Status) VALUES (?, ?, NOW(), 'waitlisted')");
        $ins->execute([$classId, $mid]);
        flash_set('success', 'Added to waitlist. You will be promoted if a spot opens.');
    }
    header('Location: my_account.php');
    exit;
}

page_head('Join Waitlist');
page_nav();
?>
<div class="container" style="max-width:540px">
  <?php flash_html(); ?>
  <h2 class="mb-3">Join Waitlist</h2>
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold"><?= e($class['Title']) ?></div>
    <div class="card-body">
      <p><strong>Date/Time:</strong> <?= e(date('D, M j Y \a\t g:i A', strtotime($class['StartsAt']))) ?></p>
      <p><strong>Trainer:</strong> <?= e($class['TrainerName']) ?></p>
      <p><strong>Status:</strong> <span class="badge bg-danger">Full</span></p>
    </div>
  </div>

  <?php if ($existing): ?>
    <div class="alert alert-info">You already have a booking: <strong><?= e($existing['Status']) ?></strong></div>
  <?php else: ?>
    <p>This class is currently full. Join the waitlist to be automatically promoted if someone cancels.</p>
    <form method="post">
      <button type="submit" class="btn btn-warning">Join Waitlist</button>
      <a href="index.php" class="btn btn-outline-secondary ms-2">Back</a>
    </form>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
