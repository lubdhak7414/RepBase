<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member = require_member();
$db     = db();
$mid    = $member['id'];
$classId = (int)($_GET['id'] ?? 0);

if (!$classId) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("
    SELECT c.*, t.Name AS TrainerName
    FROM class c
    JOIN trainer t ON t.Trainer_id = c.Trainer_id
    WHERE c.Class_id = ?
");
$stmt->execute([$classId]);
$class = $stmt->fetch();

if (!$class) {
    flash_set('error', 'Class not found.');
    header('Location: index.php');
    exit;
}

// Check already booked
$stmt = $db->prepare("SELECT Status FROM booking WHERE Class_id = ? AND Member_id = ?");
$stmt->execute([$classId, $mid]);
$existing = $stmt->fetch();

$stmt = $db->prepare("SELECT COUNT(*) FROM booking WHERE Class_id = ? AND Status = 'booked'");
$stmt->execute([$classId]);
$bookedCount = (int)$stmt->fetchColumn();

$capacity  = (int)$class['Capacity'];
$remaining = $capacity - $bookedCount;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($existing) {
        flash_set('error', 'You already have a booking for this class.');
    } elseif ($remaining > 0) {
        $ins = $db->prepare("INSERT INTO booking (Class_id, Member_id, BookedAt, Status) VALUES (?, ?, NOW(), 'booked')");
        $ins->execute([$classId, $mid]);
        flash_set('success', 'Class booked successfully!');
        header('Location: my_account.php');
        exit;
    } else {
        flash_set('error', 'No spots left. Use the waitlist option.');
    }
    header('Location: book_class.php?id=' . $classId);
    exit;
}

page_head('Book Class');
page_nav();
?>
<div class="container" style="max-width:540px">
  <?php flash_html(); ?>
  <h2 class="mb-3">Book a Class</h2>
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold"><?= e($class['Title']) ?></div>
    <div class="card-body">
      <p><strong>Date/Time:</strong> <?= e(date('D, M j Y \a\t g:i A', strtotime($class['StartsAt']))) ?></p>
      <p><strong>Trainer:</strong> <?= e($class['TrainerName']) ?></p>
      <p><strong>Room:</strong> <?= e($class['Room']) ?></p>
      <p><strong>Capacity:</strong> <?= $capacity ?> total · <?= $remaining > 0 ? $remaining . ' spots left' : 'Full' ?></p>
    </div>
  </div>

  <?php if ($existing): ?>
    <div class="alert alert-info">
      You already have a booking with status: <strong><?= e($existing['Status']) ?></strong>.
      <a href="my_account.php" class="btn btn-sm btn-outline-primary ms-2">View Account</a>
    </div>
  <?php elseif ($remaining > 0): ?>
    <form method="post">
      <button type="submit" class="btn btn-primary">Confirm Booking</button>
      <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
  <?php else: ?>
    <a href="waitlist.php?id=<?= $classId ?>" class="btn btn-warning">Join Waitlist</a>
    <a href="index.php" class="btn btn-outline-secondary ms-2">Back</a>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
