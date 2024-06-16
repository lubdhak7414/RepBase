<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff = require_staff(['admin', 'trainer']);
$db    = db();
$role  = $staff['role'];

// Quick stats for the dashboard
$totalMembers  = (int)$db->query("SELECT COUNT(*) FROM member")->fetchColumn();
$activeMembers = (int)$db->query("SELECT COUNT(DISTINCT Member_id) FROM membership WHERE Active = 1")->fetchColumn();
$totalClasses  = (int)$db->query("SELECT COUNT(*) FROM class WHERE StartsAt >= NOW()")->fetchColumn();
$todayBookings = (int)$db->query("SELECT COUNT(*) FROM booking WHERE Status = 'booked' AND BookedAt >= CURDATE()")->fetchColumn();

page_head('Staff Dashboard');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <h2 class="mb-4">Staff Dashboard
    <small class="text-muted fs-6 ms-2">Welcome, <?= e($staff['name']) ?></small>
  </h2>

  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 fw-bold text-primary"><?= $totalMembers ?></div>
          <div class="text-muted">Total Members</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 fw-bold text-success"><?= $activeMembers ?></div>
          <div class="text-muted">Active Memberships</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 fw-bold text-info"><?= $totalClasses ?></div>
          <div class="text-muted">Upcoming Classes</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 fw-bold text-warning"><?= $todayBookings ?></div>
          <div class="text-muted">New Bookings Today</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <?php if ($role === 'admin'): ?>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">Admin Tools</div>
        <div class="list-group list-group-flush">
          <a href="admin_members.php" class="list-group-item list-group-item-action">Manage Members</a>
          <a href="admin_classes.php" class="list-group-item list-group-item-action">Manage Classes</a>
          <a href="admin_payments.php" class="list-group-item list-group-item-action">Payments &amp; Invoices</a>
          <a href="admin_expiring.php" class="list-group-item list-group-item-action">Expiring Memberships &amp; Reports</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">Quick Links</div>
        <div class="list-group list-group-flush">
          <a href="trainer_roster.php" class="list-group-item list-group-item-action">Class Roster</a>
          <a href="attendance.php" class="list-group-item list-group-item-action">Attendance Check-In</a>
          <a href="index.php" class="list-group-item list-group-item-action">Public Schedule</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php page_foot(); ?>
