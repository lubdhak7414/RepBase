<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff   = require_staff(['admin', 'trainer']);
$db      = db();
$classId = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

// Get list of classes for the dropdown
$allClasses = $db->query("
    SELECT c.Class_id, c.Title, c.StartsAt, t.Name AS TrainerName
    FROM class c JOIN trainer t ON t.Trainer_id = c.Trainer_id
    ORDER BY c.StartsAt DESC
")->fetchAll();

$classInfo = null;
$bookings  = [];

if ($classId) {
    // NAIVE: direct string queries
    $classInfo = $db->query("
        SELECT c.*, t.Name AS TrainerName
        FROM class c JOIN trainer t ON t.Trainer_id = c.Trainer_id
        WHERE c.Class_id = $classId
    ")->fetch();

    $bookings = $db->query("
        SELECT b.Booking_id, b.Member_id, b.Status,
               m.Name AS MemberName, m.Email,
               a.CheckedInAt
        FROM booking b
        JOIN member m ON m.Member_id = b.Member_id
        LEFT JOIN attendance a
            ON a.Class_id = b.Class_id AND a.Member_id = b.Member_id
        WHERE b.Class_id = $classId AND b.Status = 'booked'
        ORDER BY m.Name ASC
    ")->fetchAll();
}

// Handle check-in POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_member_id'])) {
    $checkMid = (int)$_POST['checkin_member_id'];
    $cid      = (int)$_POST['class_id'];

    // NAIVE: direct string insert/ignore
    $db->query("INSERT IGNORE INTO attendance (Class_id, Member_id, CheckedInAt)
                VALUES ($cid, $checkMid, NOW())");

    flash_set('success', 'Member checked in.');
    header("Location: attendance.php?class_id=$cid");
    exit;
}

page_head('Attendance');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <h2 class="mb-3">Attendance Check-In</h2>

  <form method="get" class="mb-4 d-flex gap-2">
    <select name="class_id" class="form-select" style="max-width:420px">
      <option value="">— Select a class —</option>
      <?php foreach ($allClasses as $cl): ?>
      <option value="<?= (int)$cl['Class_id'] ?>"
              <?= $cl['Class_id'] == $classId ? 'selected' : '' ?>>
        <?= e($cl['Title']) ?> — <?= e(date('M j g:i A', strtotime($cl['StartsAt']))) ?>
        (<?= e($cl['TrainerName']) ?>)
      </option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">View</button>
  </form>

  <?php if ($classInfo): ?>
  <h4><?= e($classInfo['Title']) ?> — <?= e(date('D, M j Y g:i A', strtotime($classInfo['StartsAt']))) ?></h4>

  <?php if (empty($bookings)): ?>
    <p class="text-muted">No confirmed bookings for this class.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-dark">
        <tr><th>#</th><th>Member</th><th>Email</th><th>Checked In</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $i => $bk): ?>
        <tr class="<?= $bk['CheckedInAt'] ? 'table-success' : '' ?>">
          <td><?= $i + 1 ?></td>
          <td><?= e($bk['MemberName']) ?></td>
          <td><?= e($bk['Email']) ?></td>
          <td>
            <?php if ($bk['CheckedInAt']): ?>
              <span class="badge bg-success">✓ <?= e(date('g:i A', strtotime($bk['CheckedInAt']))) ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$bk['CheckedInAt']): ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
              <input type="hidden" name="checkin_member_id" value="<?= (int)$bk['Member_id'] ?>">
              <button class="btn btn-sm btn-success">Check In</button>
            </form>
            <?php else: ?>
              <span class="text-success fw-semibold">Present</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
