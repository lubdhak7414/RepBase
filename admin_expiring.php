<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff = require_staff('admin');
$db    = db();

// Memberships expiring in the next 7 days (no user input — safe without params)
$expiring = $db->query("
    SELECT m.Membership_id, m.EndDate, m.Active,
           mem.Member_id, mem.Name, mem.Email, mem.Phone,
           p.Name AS PlanName
    FROM membership m
    JOIN member mem ON mem.Member_id = m.Member_id
    JOIN plan p ON p.Plan_id = m.Plan_id
    WHERE m.EndDate BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
      AND m.Active = 1
    ORDER BY m.EndDate ASC
")->fetchAll();

// Revenue per plan (no user input)
$revenue = $db->query("
    SELECT p.Plan_id, p.Name AS PlanName,
           COUNT(pay.Payment_id) AS TxCount,
           SUM(pay.Amount) AS TotalRevenue
    FROM payment pay
    JOIN membership ms ON ms.Membership_id = pay.Membership_id
    JOIN plan p ON p.Plan_id = ms.Plan_id
    GROUP BY p.Plan_id
    ORDER BY TotalRevenue DESC
")->fetchAll();

// Overall revenue total
$totalRevenue = array_sum(array_column($revenue, 'TotalRevenue'));

page_head('Expiring Memberships & Reports');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Expiring Memberships &amp; Revenue Reports</h2>
    <a href="staff_dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  <!-- Expiring memberships -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
      Memberships Expiring in the Next 7 Days
      <span class="badge bg-warning text-dark"><?= count($expiring) ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($expiring)): ?>
        <p class="p-3 text-muted">No memberships expiring in the next 7 days.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Member</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Plan</th>
              <th>Expires</th>
              <th>Days Left</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expiring as $row):
                $daysLeft = (int)ceil((strtotime($row['EndDate']) - time()) / 86400);
                $rowCls = $daysLeft <= 2 ? 'expiring-soon' : '';
            ?>
            <tr class="<?= $rowCls ?>">
              <td class="fw-semibold"><?= e($row['Name']) ?></td>
              <td><a href="mailto:<?= e($row['Email']) ?>"><?= e($row['Email']) ?></a></td>
              <td><?= e($row['Phone'] ?? '—') ?></td>
              <td><span class="badge bg-info"><?= e($row['PlanName']) ?></span></td>
              <td><?= e($row['EndDate']) ?></td>
              <td>
                <span class="badge <?= $daysLeft <= 2 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                  <?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Revenue by plan -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">Revenue by Plan</div>
    <div class="card-body p-0">
      <?php if (empty($revenue)): ?>
        <p class="p-3 text-muted">No payment data available.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Plan</th>
              <th>Transactions</th>
              <th>Total Revenue</th>
              <th>% of Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($revenue as $r):
                $pct = $totalRevenue > 0 ? round((float)$r['TotalRevenue'] / $totalRevenue * 100, 1) : 0;
            ?>
            <tr>
              <td class="fw-semibold"><?= e($r['PlanName']) ?></td>
              <td><?= (int)$r['TxCount'] ?></td>
              <td>$<?= number_format((float)$r['TotalRevenue'], 2) ?></td>
              <td>
                <div class="progress" style="height:18px;min-width:80px">
                  <div class="progress-bar" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold">
            <tr>
              <td>Total</td>
              <td><?= array_sum(array_column($revenue, 'TxCount')) ?></td>
              <td>$<?= number_format((float)$totalRevenue, 2) ?></td>
              <td>100%</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_foot(); ?>
