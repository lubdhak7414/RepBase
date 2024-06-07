<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff = require_staff('admin');
$db    = db();

// Handle record payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $member_id    = $_POST['member_id'];
    $amount       = $_POST['amount'];
    $method       = $_POST['method'];

    // Get the active membership_id for this member
    // NAIVE: direct string interpolation
    $ms = $db->query("SELECT Membership_id FROM membership WHERE Member_id = $member_id AND Active = 1 ORDER BY EndDate DESC LIMIT 1")->fetch();

    if ($ms) {
        $membership_id = $ms['Membership_id'];
        // NAIVE: direct string interpolation
        $db->query("INSERT INTO payment (Member_id, Membership_id, Amount, PaidAt, Method)
                    VALUES ($member_id, $membership_id, $amount, NOW(), '$method')");
        flash_set('success', 'Payment recorded successfully.');
    } else {
        flash_set('error', 'No active membership found for this member.');
    }
    header('Location: admin_payments.php');
    exit;
}

// Fetch all members for the select dropdown
$members = $db->query("SELECT Member_id, Name, Email FROM member ORDER BY Name ASC")->fetchAll();

// Fetch recent payments with member and plan info
// NAIVE: string query
$payments = $db->query("
    SELECT pay.Payment_id, pay.Amount, pay.PaidAt, pay.Method,
           mem.Name AS MemberName, mem.Email,
           p.Name AS PlanName
    FROM payment pay
    JOIN member mem ON mem.Member_id = pay.Member_id
    JOIN membership ms ON ms.Membership_id = pay.Membership_id
    JOIN plan p ON p.Plan_id = ms.Plan_id
    ORDER BY pay.PaidAt DESC
    LIMIT 50
")->fetchAll();

page_head('Payments & Invoices');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Payments &amp; Invoices</h2>
    <a href="staff_dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  <!-- Record new payment -->
  <div class="card shadow-sm mb-4" style="max-width:540px">
    <div class="card-header fw-bold">Record Payment</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="record_payment" value="1">
        <div class="mb-3">
          <label class="form-label">Member</label>
          <select name="member_id" class="form-select" required>
            <option value="">— Select member —</option>
            <?php foreach ($members as $m): ?>
            <option value="<?= (int)$m['Member_id'] ?>"><?= e($m['Name']) ?> (<?= e($m['Email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Amount ($)</label>
          <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Method</label>
          <select name="method" class="form-select">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Record Payment</button>
      </form>
    </div>
  </div>

  <!-- Payments table -->
  <h4 class="mb-3">Recent Payments</h4>
  <?php if (empty($payments)): ?>
    <p class="text-muted">No payments recorded yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Member</th>
          <th>Plan</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $pay): ?>
        <tr>
          <td><?= (int)$pay['Payment_id'] ?></td>
          <td>
            <?= e($pay['MemberName']) ?>
            <small class="text-muted d-block"><?= e($pay['Email']) ?></small>
          </td>
          <td><?= e($pay['PlanName']) ?></td>
          <td class="fw-semibold">$<?= number_format((float)$pay['Amount'], 2) ?></td>
          <td><?= e($pay['Method']) ?></td>
          <td><?= e(date('M j, Y g:i A', strtotime($pay['PaidAt']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php page_foot(); ?>
