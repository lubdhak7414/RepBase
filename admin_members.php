<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff = require_staff('admin');
$db    = db();

// Handle status toggle (deactivate / reactivate membership)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_membership'])) {
    $msid      = (int)$_POST['membership_id'];
    $newActive = (int)$_POST['new_active'];
    $stmt = $db->prepare("UPDATE membership SET Active = ? WHERE Membership_id = ?");
    $stmt->execute([$newActive, $msid]);
    flash_set('success', 'Membership status updated.');
    header('Location: admin_members.php');
    exit;
}

// Handle freeze approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_freeze'])) {
    $freezeId = (int)$_POST['freeze_id'];

    // Fetch freeze details
    $stmt = $db->prepare("SELECT * FROM membership_freeze WHERE Freeze_id = ? AND Status = 'pending'");
    $stmt->execute([$freezeId]);
    $freeze = $stmt->fetch();

    if ($freeze) {
        $msid = (int)$freeze['Membership_id'];
        try {
            $db->beginTransaction();
            // Approve and immediately complete the freeze (30-day extension)
            $upd = $db->prepare("
                UPDATE membership_freeze
                SET Status = 'completed',
                    FrozenAt = CURDATE(),
                    UnfrozenAt = DATE_ADD(CURDATE(), INTERVAL 30 DAY),
                    DaysExtended = 30
                WHERE Freeze_id = ?
            ");
            $upd->execute([$freezeId]);

            // Extend the membership end date by 30 days
            $ext = $db->prepare("UPDATE membership SET EndDate = DATE_ADD(EndDate, INTERVAL 30 DAY) WHERE Membership_id = ?");
            $ext->execute([$msid]);

            $db->commit();
            flash_set('success', 'Freeze approved — membership end date extended by 30 days.');
        } catch (\Exception $e) {
            $db->rollBack();
            flash_set('error', 'Could not process freeze approval.');
        }
    } else {
        flash_set('error', 'Freeze request not found or already processed.');
    }
    header('Location: admin_members.php');
    exit;
}

// Handle freeze rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_freeze'])) {
    $freezeId = (int)$_POST['freeze_id'];
    $stmt = $db->prepare("UPDATE membership_freeze SET Status = 'rejected' WHERE Freeze_id = ? AND Status = 'pending'");
    $stmt->execute([$freezeId]);
    flash_set('success', 'Freeze request rejected.');
    header('Location: admin_members.php');
    exit;
}

// Handle member detail edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_member'])) {
    $id    = (int)$_POST['member_id'];
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $stmt = $db->prepare("UPDATE member SET Name = ?, Email = ?, Phone = ? WHERE Member_id = ?");
    $stmt->execute([$name, $email, $phone, $id]);
    flash_set('success', 'Member details updated.');
    header('Location: admin_members.php');
    exit;
}

// Optional name/email search
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like  = '%' . $search . '%';
    $stmt  = $db->prepare("
        SELECT m.Member_id, m.Name, m.Email, m.Phone, m.JoinDate,
               ms.Membership_id, ms.Active AS MsActive, ms.EndDate,
               p.Name AS PlanName
        FROM member m
        LEFT JOIN membership ms ON ms.Member_id = m.Member_id AND ms.Active = 1
        LEFT JOIN plan p ON p.Plan_id = ms.Plan_id
        WHERE m.Name LIKE ? OR m.Email LIKE ?
        ORDER BY m.Member_id ASC
    ");
    $stmt->execute([$like, $like]);
} else {
    $stmt = $db->query("
        SELECT m.Member_id, m.Name, m.Email, m.Phone, m.JoinDate,
               ms.Membership_id, ms.Active AS MsActive, ms.EndDate,
               p.Name AS PlanName
        FROM member m
        LEFT JOIN membership ms ON ms.Member_id = m.Member_id AND ms.Active = 1
        LEFT JOIN plan p ON p.Plan_id = ms.Plan_id
        ORDER BY m.Member_id ASC
    ");
}
$members = $stmt->fetchAll();

// Edit form target
$editId = (int)($_GET['edit'] ?? 0);
$editMember = null;
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM member WHERE Member_id = ?");
    $stmt->execute([$editId]);
    $editMember = $stmt->fetch();
}

// Pending freeze requests
$pendingFreezes = $db->query("
    SELECT mf.Freeze_id, mf.RequestedAt,
           mem.Name AS MemberName, mem.Email,
           p.Name AS PlanName,
           ms.EndDate AS CurrentEndDate,
           ms.Membership_id
    FROM membership_freeze mf
    JOIN membership ms  ON ms.Membership_id  = mf.Membership_id
    JOIN plan p         ON p.Plan_id          = ms.Plan_id
    JOIN member mem     ON mem.Member_id      = mf.RequestedBy
    WHERE mf.Status = 'pending'
    ORDER BY mf.RequestedAt ASC
")->fetchAll();

page_head('Manage Members');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Manage Members <span class="text-muted fs-6">(<?= count($members) ?>)</span></h2>
    <a href="staff_dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  <?php if (!empty($pendingFreezes)): ?>
  <div class="card shadow-sm mb-4 border-warning">
    <div class="card-header fw-bold bg-warning text-dark">
      Pending Freeze Requests (<?= count($pendingFreezes) ?>)
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Member</th>
              <th>Plan</th>
              <th>Current End Date</th>
              <th>Requested</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingFreezes as $fr): ?>
            <tr>
              <td>
                <?= e($fr['MemberName']) ?>
                <small class="text-muted d-block"><?= e($fr['Email']) ?></small>
              </td>
              <td><?= e($fr['PlanName']) ?></td>
              <td><?= e($fr['CurrentEndDate']) ?></td>
              <td><?= e(date('M j, Y', strtotime($fr['RequestedAt']))) ?></td>
              <td class="d-flex gap-1">
                <form method="post" class="d-inline">
                  <input type="hidden" name="approve_freeze" value="1">
                  <input type="hidden" name="freeze_id" value="<?= (int)$fr['Freeze_id'] ?>">
                  <button class="btn btn-sm btn-success" type="submit">Approve (+30 days)</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="reject_freeze" value="1">
                  <input type="hidden" name="freeze_id" value="<?= (int)$fr['Freeze_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <form method="get" class="row g-2 mb-4">
    <div class="col-auto">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Search by name or email…" value="<?= e($search) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-secondary btn-sm">Search</button>
      <?php if ($search): ?>
      <a href="admin_members.php" class="btn btn-outline-secondary btn-sm">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($editMember): ?>
  <div class="card shadow-sm mb-4" style="max-width:500px">
    <div class="card-header fw-bold">Edit Member #<?= (int)$editMember['Member_id'] ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="edit_member" value="1">
        <input type="hidden" name="member_id" value="<?= (int)$editMember['Member_id'] ?>">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($editMember['Name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($editMember['Email']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($editMember['Phone'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="admin_members.php" class="btn btn-outline-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Joined</th>
          <th>Plan</th>
          <th>Expires</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td><?= (int)$m['Member_id'] ?></td>
          <td><?= e($m['Name']) ?></td>
          <td><?= e($m['Email']) ?></td>
          <td><?= e($m['Phone'] ?? '—') ?></td>
          <td><?= e($m['JoinDate']) ?></td>
          <td>
            <?php if ($m['PlanName']): ?>
              <span class="badge bg-success"><?= e($m['PlanName']) ?></span>
            <?php else: ?>
              <span class="text-muted">None</span>
            <?php endif; ?>
          </td>
          <td><?= $m['EndDate'] ? e($m['EndDate']) : '—' ?></td>
          <td class="d-flex gap-1 flex-wrap">
            <a href="admin_members.php?edit=<?= (int)$m['Member_id'] ?>"
               class="btn btn-sm btn-outline-primary">Edit</a>
            <?php if ($m['Membership_id']): ?>
              <?php if ($m['MsActive']): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="toggle_membership" value="1">
                <input type="hidden" name="membership_id" value="<?= (int)$m['Membership_id'] ?>">
                <input type="hidden" name="new_active" value="0">
                <button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
              </form>
              <?php else: ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="toggle_membership" value="1">
                <input type="hidden" name="membership_id" value="<?= (int)$m['Membership_id'] ?>">
                <input type="hidden" name="new_active" value="1">
                <button class="btn btn-sm btn-outline-success" type="submit">Reactivate</button>
              </form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_foot(); ?>
