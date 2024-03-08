<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

session_start_safe();

// Fetch upcoming classes with trainer name and booked count
$sql = "
    SELECT
        c.Class_id,
        c.Title,
        c.StartsAt,
        c.Capacity,
        c.Room,
        t.Name  AS TrainerName,
        t.Specialty,
        COUNT(b.Booking_id) AS BookedCount
    FROM class c
    JOIN trainer t ON t.Trainer_id = c.Trainer_id
    LEFT JOIN booking b
        ON b.Class_id = c.Class_id AND b.Status = 'booked'
    WHERE c.StartsAt >= NOW()
    GROUP BY c.Class_id
    ORDER BY c.StartsAt ASC
    LIMIT 50
";
$stmt = db()->query($sql);
$classes = $stmt->fetchAll();

page_head('Class Schedule');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>

  <h1 class="mb-3">Upcoming Classes</h1>

  <?php if (empty($classes)): ?>
    <p class="text-muted">No upcoming classes scheduled.</p>
  <?php else: ?>
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    <?php foreach ($classes as $cl):
        $remaining = (int)$cl['Capacity'] - (int)$cl['BookedCount'];
        $date      = date('D, M j Y', strtotime($cl['StartsAt']));
        $time      = date('g:i A', strtotime($cl['StartsAt']));
        $badgeCls  = $remaining > 3 ? 'bg-success' : ($remaining > 0 ? 'bg-warning text-dark' : 'bg-danger');
        $badgeTxt  = $remaining > 0 ? $remaining . ' spots left' : 'Waitlist';
    ?>
    <div class="col">
      <div class="card h-100 shadow-sm class-card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><?= e($cl['Title']) ?></strong>
          <span class="badge <?= $badgeCls ?>"><?= $badgeTxt ?></span>
        </div>
        <div class="card-body">
          <p class="mb-1"><i class="text-muted">📅</i> <?= e($date) ?> at <?= e($time) ?></p>
          <p class="mb-1"><i class="text-muted">🏅</i> <?= e($cl['TrainerName']) ?></p>
          <p class="mb-1"><i class="text-muted">📍</i> <?= e($cl['Room']) ?></p>
          <p class="mb-0 text-muted small"><?= e($cl['Specialty']) ?></p>
        </div>
        <div class="card-footer">
          <?php if (!empty($_SESSION['member_id'])): ?>
            <a href="book_class.php?id=<?= (int)$cl['Class_id'] ?>" class="btn btn-sm btn-primary">
              <?= $remaining > 0 ? 'Book' : 'Join Waitlist' ?>
            </a>
          <?php else: ?>
            <a href="login.php" class="btn btn-sm btn-outline-primary">Login to book</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
