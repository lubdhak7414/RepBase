<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member = require_member();
$db     = db();
$mid    = $member['id'];

$stmt = $db->prepare("SELECT * FROM member WHERE Member_id = ?");
$stmt->execute([$mid]);
$memberInfo = $stmt->fetch();

$stmt = $db->prepare("
    SELECT ms.*, p.Name AS PlanName, p.PriceMonthly
    FROM membership ms
    JOIN plan p ON p.Plan_id = ms.Plan_id
    WHERE ms.Member_id = ? AND ms.Active = 1
    ORDER BY ms.EndDate DESC
    LIMIT 1
");
$stmt->execute([$mid]);
$activeMembership = $stmt->fetch();

$stmt = $db->prepare("
    SELECT b.*, c.Title AS ClassTitle, c.StartsAt, c.Room, t.Name AS TrainerName
    FROM booking b
    JOIN class c ON c.Class_id = b.Class_id
    JOIN trainer t ON t.Trainer_id = c.Trainer_id
    WHERE b.Member_id = ?
    ORDER BY c.StartsAt DESC
");
$stmt->execute([$mid]);
$bookings = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT pay.*, p.Name AS PlanName
    FROM payment pay
    JOIN membership ms ON ms.Membership_id = pay.Membership_id
    JOIN plan p ON p.Plan_id = ms.Plan_id
    WHERE pay.Member_id = ?
    ORDER BY pay.PaidAt DESC
");
$stmt->execute([$mid]);
$payments = $stmt->fetchAll();

page_head('My Account');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>

  <div class="row">
    <!-- Profile + Membership -->
    <div class="col-md-4 mb-4">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">My Profile</div>
        <div class="card-body">
          <p><strong>Name:</strong> <?= e($memberInfo['Name']) ?></p>
          <p><strong>Email:</strong> <?= e($memberInfo['Email']) ?></p>
          <p><strong>Phone:</strong> <?= e($memberInfo['Phone'] ?? '—') ?></p>
          <p><strong>Joined:</strong> <?= e($memberInfo['JoinDate']) ?></p>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-header fw-bold">Membership</div>
        <div class="card-body">
          <?php if ($activeMembership): ?>
            <p><strong>Plan:</strong> <?= e($activeMembership['PlanName']) ?></p>
            <p><strong>Valid until:</strong> <?= e($activeMembership['EndDate']) ?></p>
            <?php
            $daysLeft = (int)ceil((strtotime($activeMembership['EndDate']) - time()) / 86400);
            $badgeCls = $daysLeft <= 7 ? 'bg-danger' : ($daysLeft <= 14 ? 'bg-warning text-dark' : 'bg-success');
            ?>
            <span class="badge <?= $badgeCls ?>"><?= $daysLeft ?> days left</span>
          <?php else: ?>
            <p class="text-muted">No active membership.</p>
          <?php endif; ?>
          <a href="membership.php" class="btn btn-sm btn-outline-primary mt-2">
            <?= $activeMembership ? 'Change Plan' : 'Get a Membership' ?>
          </a>
        </div>
      </div>
    </div>

    <!-- Bookings -->
    <div class="col-md-8 mb-4">
      <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold d-flex justify-content-between">
          My Class Bookings
          <a href="index.php" class="btn btn-sm btn-primary">Browse Classes</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($bookings)): ?>
            <p class="p-3 text-muted">No bookings yet.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Class</th><th>Date</th><th>Room</th><th>Trainer</th><th>Status</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $bk):
                    $statusCls = match($bk['Status']) {
                        'booked'     => 'bg-success',
                        'waitlisted' => 'bg-warning text-dark',
                        'cancelled'  => 'bg-secondary',
                        default      => 'bg-light text-dark',
                    };
                ?>
                <tr>
                  <td><?= e($bk['ClassTitle']) ?></td>
                  <td><?= e(date('M j, g:i A', strtotime($bk['StartsAt']))) ?></td>
                  <td><?= e($bk['Room']) ?></td>
                  <td><?= e($bk['TrainerName']) ?></td>
                  <td><span class="badge <?= $statusCls ?>"><?= e($bk['Status']) ?></span></td>
                  <td>
                    <?php if ($bk['Status'] !== 'cancelled'): ?>
                    <a href="cancel_booking.php?id=<?= (int)$bk['Booking_id'] ?>"
                       class="btn btn-sm btn-outline-danger">Cancel</a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payment History -->
      <div class="card shadow-sm">
        <div class="card-header fw-bold">Payment History</div>
        <div class="card-body p-0">
          <?php if (empty($payments)): ?>
            <p class="p-3 text-muted">No payments recorded.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr><th>Plan</th><th>Amount</th><th>Method</th><th>Date</th></tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $pay): ?>
                <tr>
                  <td><?= e($pay['PlanName']) ?></td>
                  <td>$<?= number_format((float)$pay['Amount'], 2) ?></td>
                  <td><?= e($pay['Method']) ?></td>
                  <td><?= e(date('M j, Y', strtotime($pay['PaidAt']))) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php page_foot(); ?>
