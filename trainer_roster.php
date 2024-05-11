<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff   = require_staff(['admin', 'trainer']);
$db      = db();
$staffId = $staff['id'];
$role    = $staff['role'];

// NAIVE: direct string queries
// For trainers, show only their classes; admin sees all
if ($role === 'trainer') {
    // Link staff username to trainer name
    $trainer = $db->query("
        SELECT t.* FROM trainer t
        JOIN staff s ON s.Username = REPLACE(LOWER(t.Name), ' ', '.')
        WHERE s.Staff_id = $staffId
        LIMIT 1
    ")->fetch();

    if (!$trainer) {
        // Fallback: try partial match
        $uname = $db->query("SELECT Username FROM staff WHERE Staff_id = $staffId")->fetchColumn();
        $trainer = $db->query("
            SELECT * FROM trainer
            WHERE LOWER(REPLACE(Name, ' ', '.')) = LOWER('$uname')
            LIMIT 1
        ")->fetch();
    }

    if ($trainer) {
        $tid = (int)$trainer['Trainer_id'];
        $classes = $db->query("
            SELECT c.*,
                   COUNT(b.Booking_id) AS BookedCount
            FROM class c
            LEFT JOIN booking b ON b.Class_id = c.Class_id AND b.Status = 'booked'
            WHERE c.Trainer_id = $tid
            GROUP BY c.Class_id
            ORDER BY c.StartsAt ASC
        ")->fetchAll();
    } else {
        $classes = [];
    }
} else {
    $classes = $db->query("
        SELECT c.*, t.Name AS TrainerName,
               COUNT(b.Booking_id) AS BookedCount
        FROM class c
        JOIN trainer t ON t.Trainer_id = c.Trainer_id
        LEFT JOIN booking b ON b.Class_id = c.Class_id AND b.Status = 'booked'
        GROUP BY c.Class_id
        ORDER BY c.StartsAt ASC
    ")->fetchAll();
}

page_head('Class Roster');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <h2 class="mb-3">
    <?= $role === 'trainer' ? 'My Classes' : 'All Classes — Roster View' ?>
  </h2>

  <?php if (empty($classes)): ?>
    <p class="text-muted">No classes found.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>Class</th>
          <?php if ($role === 'admin'): ?><th>Trainer</th><?php endif; ?>
          <th>Date &amp; Time</th>
          <th>Room</th>
          <th>Capacity</th>
          <th>Booked</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($classes as $cl):
            $booked    = (int)$cl['BookedCount'];
            $cap       = (int)$cl['Capacity'];
            $remaining = $cap - $booked;
            $fullCls   = $remaining === 0 ? 'table-danger' : ($remaining <= 3 ? 'table-warning' : '');
        ?>
        <tr class="<?= $fullCls ?>">
          <td class="fw-semibold"><?= e($cl['Title']) ?></td>
          <?php if ($role === 'admin'): ?>
          <td><?= e($cl['TrainerName'] ?? '') ?></td>
          <?php endif; ?>
          <td><?= e(date('D M j, g:i A', strtotime($cl['StartsAt']))) ?></td>
          <td><?= e($cl['Room']) ?></td>
          <td><?= $cap ?></td>
          <td><?= $booked ?> / <?= $cap ?></td>
          <td>
            <a href="attendance.php?class_id=<?= (int)$cl['Class_id'] ?>"
               class="btn btn-sm btn-outline-primary">Attendance</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
