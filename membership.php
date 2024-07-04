<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member = require_member();
$db     = db();
$mid    = $member['id'];

// Fetch available plans
$plans = $db->query("SELECT * FROM plan ORDER BY PriceMonthly ASC")->fetchAll();

// Current membership
$stmt = $db->prepare("SELECT ms.*, p.Name AS PlanName
    FROM membership ms JOIN plan p ON p.Plan_id = ms.Plan_id
    WHERE ms.Member_id = ? AND ms.Active = 1
    ORDER BY ms.EndDate DESC LIMIT 1");
$stmt->execute([$mid]);
$current = $stmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM plan WHERE Plan_id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        $error = 'Invalid plan selected.';
    } else {
        // Deactivate existing memberships
        $upd = $db->prepare("UPDATE membership SET Active = 0 WHERE Member_id = ?");
        $upd->execute([$mid]);
        // Create new membership
        $dur = (int)$plan['DurationDays'];
        $ins = $db->prepare("INSERT INTO membership (Member_id, Plan_id, StartDate, EndDate, Active)
            VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 1)");
        $ins->execute([$mid, $planId, $dur]);
        $newMsId = (int)$db->lastInsertId();
        // Record payment
        $amount = $plan['PriceMonthly'];
        $method = $_POST['method'] ?? 'card';
        $pay = $db->prepare("INSERT INTO payment (Member_id, Membership_id, Amount, PaidAt, Method)
            VALUES (?, ?, ?, NOW(), ?)");
        $pay->execute([$mid, $newMsId, $amount, $method]);
        flash_set('success', 'Membership updated to ' . $plan['Name'] . '!');
        header('Location: my_account.php');
        exit;
    }
}

page_head('Change Membership');
page_nav();
?>
<div class="container" style="max-width:640px">
  <?php flash_html(); ?>
  <h2 class="mb-3">Choose a Membership Plan</h2>

  <?php if ($current): ?>
  <div class="alert alert-info">
    Current plan: <strong><?= e($current['PlanName']) ?></strong> — expires <?= e($current['EndDate']) ?>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Select Plan</label>
      <div class="row g-3">
        <?php foreach ($plans as $p):
            $selected = $current && $current['Plan_id'] == $p['Plan_id'];
        ?>
        <div class="col-md-6">
          <div class="card <?= $selected ? 'border-primary' : '' ?>">
            <div class="card-body">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="plan_id"
                       id="plan<?= $p['Plan_id'] ?>" value="<?= $p['Plan_id'] ?>"
                       <?= $selected ? 'checked' : '' ?> required>
                <label class="form-check-label fw-bold" for="plan<?= $p['Plan_id'] ?>">
                  <?= e($p['Name']) ?>
                </label>
              </div>
              <p class="mb-0 mt-1">$<?= number_format((float)$p['PriceMonthly'], 2) ?>/month</p>
              <p class="mb-0 text-muted small"><?= (int)$p['DurationDays'] ?> days</p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Payment Method</label>
      <select name="method" class="form-select">
        <option value="card">Card</option>
        <option value="cash">Cash</option>
        <option value="bank_transfer">Bank Transfer</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Confirm &amp; Pay</button>
    <a href="my_account.php" class="btn btn-outline-secondary ms-2">Cancel</a>
  </form>
</div>
<?php page_foot(); ?>
