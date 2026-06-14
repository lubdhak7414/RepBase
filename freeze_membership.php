<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member = require_member();
$db     = db();
$mid    = $member['id'];

// Fetch active membership
$stmt = $db->prepare("
    SELECT ms.*, p.Name AS PlanName
    FROM membership ms
    JOIN plan p ON p.Plan_id = ms.Plan_id
    WHERE ms.Member_id = ? AND ms.Active = 1
    ORDER BY ms.EndDate DESC
    LIMIT 1
");
$stmt->execute([$mid]);
$activeMembership = $stmt->fetch();

// Handle freeze request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_freeze'])) {
    if (!$activeMembership) {
        flash_set('error', 'You do not have an active membership to freeze.');
        header('Location: freeze_membership.php');
        exit;
    }

    $msid = (int)$activeMembership['Membership_id'];

    // Check for existing pending or active freeze
    $stmt = $db->prepare("
        SELECT Freeze_id FROM membership_freeze
        WHERE Membership_id = ? AND Status IN ('pending', 'active')
        LIMIT 1
    ");
    $stmt->execute([$msid]);
    $existing = $stmt->fetch();

    if ($existing) {
        flash_set('error', 'You already have a pending or active freeze request.');
    } else {
        $stmt = $db->prepare("
            INSERT INTO membership_freeze (Membership_id, RequestedBy)
            VALUES (?, ?)
        ");
        $stmt->execute([$msid, $mid]);
        flash_set('success', 'Freeze request submitted. An admin will review it shortly.');
    }
    header('Location: freeze_membership.php');
    exit;
}

// Fetch all freeze requests for this member
$stmt = $db->prepare("
    SELECT mf.*, p.Name AS PlanName, ms.EndDate AS MembershipEndDate
    FROM membership_freeze mf
    JOIN membership ms ON ms.Membership_id = mf.Membership_id
    JOIN plan p ON p.Plan_id = ms.Plan_id
    WHERE mf.RequestedBy = ?
    ORDER BY mf.RequestedAt DESC
");
$stmt->execute([$mid]);
$freezeRequests = $stmt->fetchAll();

page_head('Pause Membership');
page_nav();
?>
<div class="container" style="max-width:680px">
  <?php flash_html(); ?>
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="my_account.php">My Account</a></li>
      <li class="breadcrumb-item active">Pause Membership</li>
    </ol>
  </nav>

  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">Request a Membership Pause</div>
    <div class="card-body">
      <?php if ($activeMembership): ?>
        <p class="mb-1">
          <strong>Current plan:</strong> <?= e($activeMembership['PlanName']) ?>
          &mdash; expires <strong><?= e($activeMembership['EndDate']) ?></strong>
        </p>
        <p class="text-muted mb-3">
          Pausing your membership puts it on hold. Once an admin approves your request,
          your membership end date will be extended by 30 days automatically.
        </p>

        <?php
        // Check if there's already a pending/active freeze
        $stmt = $db->prepare("
            SELECT Status FROM membership_freeze
            WHERE Membership_id = ? AND Status IN ('pending','active')
            LIMIT 1
        ");
        $stmt->execute([(int)$activeMembership['Membership_id']]);
        $pendingFreeze = $stmt->fetch();
        ?>

        <?php if ($pendingFreeze): ?>
          <div class="alert alert-info mb-0">
            You already have a freeze request with status
            <strong><?= e($pendingFreeze['Status']) ?></strong>.
            Please wait for it to be processed before submitting a new one.
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="request_freeze" value="1">
            <button type="submit" class="btn btn-warning">
              Request Membership Pause
            </button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted mb-0">You do not have an active membership to pause.</p>
        <a href="membership.php" class="btn btn-outline-primary mt-3">Get a Membership</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($freezeRequests)): ?>
  <div class="card shadow-sm">
    <div class="card-header fw-bold">Your Freeze History</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Plan</th>
              <th>Requested</th>
              <th>Status</th>
              <th>Frozen</th>
              <th>Resumed</th>
              <th>Days Added</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($freezeRequests as $fr):
                $statusCls = match($fr['Status']) {
                    'pending'   => 'bg-warning text-dark',
                    'active'    => 'bg-info text-dark',
                    'completed' => 'bg-success',
                    'rejected'  => 'bg-danger',
                    default     => 'bg-secondary',
                };
            ?>
            <tr>
              <td><?= e($fr['PlanName']) ?></td>
              <td><?= e(date('M j, Y', strtotime($fr['RequestedAt']))) ?></td>
              <td><span class="badge <?= $statusCls ?>"><?= e($fr['Status']) ?></span></td>
              <td><?= $fr['FrozenAt'] ? e($fr['FrozenAt']) : '—' ?></td>
              <td><?= $fr['UnfrozenAt'] ? e($fr['UnfrozenAt']) : '—' ?></td>
              <td><?= $fr['DaysExtended'] ? '+' . (int)$fr['DaysExtended'] . ' days' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
