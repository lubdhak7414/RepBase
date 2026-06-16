<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff = require_staff('admin');
$db    = db();

// 1. Monthly revenue (with new membership count)
$monthlyRevenue = $db->query("
    SELECT YEAR(p.PaidAt) AS yr, MONTH(p.PaidAt) AS mo,
           DATE_FORMAT(p.PaidAt, '%M %Y') AS month_label,
           COUNT(DISTINCT p.Payment_id) AS payment_count,
           SUM(p.Amount) AS total,
           COUNT(DISTINCT ms.Membership_id) AS new_memberships
    FROM payment p
    JOIN membership ms ON ms.Membership_id = p.Membership_id
    GROUP BY yr, mo
    ORDER BY yr DESC, mo DESC
")->fetchAll();

// 2. Revenue by plan
$revenueByPlan = $db->query("
    SELECT pl.Name AS PlanName,
           COUNT(DISTINCT ms.Member_id) AS members,
           SUM(p.Amount) AS revenue
    FROM payment p
    JOIN membership ms ON ms.Membership_id = p.Membership_id
    JOIN plan pl ON pl.Plan_id = ms.Plan_id
    GROUP BY pl.Plan_id
    ORDER BY revenue DESC
")->fetchAll();

// 3. Top 10 spending members
$topSpenders = $db->query("
    SELECT mem.Name, mem.Email, pl.Name AS PlanName,
           SUM(p.Amount) AS total_spent
    FROM payment p
    JOIN membership ms ON ms.Membership_id = p.Membership_id
    JOIN member mem ON mem.Member_id = p.Member_id
    JOIN plan pl ON pl.Plan_id = ms.Plan_id
    GROUP BY p.Member_id
    ORDER BY total_spent DESC
    LIMIT 10
")->fetchAll();

// 4. This month vs last month
$thisMonth = $db->query("
    SELECT COALESCE(SUM(Amount), 0) AS total, COUNT(*) AS count
    FROM payment
    WHERE YEAR(PaidAt) = YEAR(CURDATE()) AND MONTH(PaidAt) = MONTH(CURDATE())
")->fetch();

$lastMonth = $db->query("
    SELECT COALESCE(SUM(Amount), 0) AS total, COUNT(*) AS count
    FROM payment
    WHERE YEAR(PaidAt) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND MONTH(PaidAt) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch();

$thisMoTotal  = (float)$thisMonth['total'];
$lastMoTotal  = (float)$lastMonth['total'];
$momDiff      = $thisMoTotal - $lastMoTotal;
$momPct       = $lastMoTotal > 0 ? round($momDiff / $lastMoTotal * 100, 1) : null;

page_head('Revenue Dashboard');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Revenue Dashboard</h2>
    <a href="staff_dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  <!-- Month-over-month stat cards -->
  <div class="row g-3 mb-5">
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1">This Month</div>
          <div class="fs-3 fw-bold text-success">$<?= number_format($thisMoTotal, 2) ?></div>
          <div class="text-muted small"><?= (int)$thisMonth['count'] ?> payments</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1">Last Month</div>
          <div class="fs-3 fw-bold text-primary">$<?= number_format($lastMoTotal, 2) ?></div>
          <div class="text-muted small"><?= (int)$lastMonth['count'] ?> payments</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1">Month-over-Month</div>
          <?php
          $momCls = $momDiff >= 0 ? 'text-success' : 'text-danger';
          $momSign = $momDiff >= 0 ? '+' : '';
          ?>
          <div class="fs-3 fw-bold <?= $momCls ?>">
            <?= $momSign ?>$<?= number_format(abs($momDiff), 2) ?>
          </div>
          <?php if ($momPct !== null): ?>
          <div class="text-muted small"><?= $momSign ?><?= $momPct ?>%</div>
          <?php else: ?>
          <div class="text-muted small">No prior data</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1">All-Time Revenue</div>
          <?php
          $allTime = array_sum(array_column($monthlyRevenue, 'total'));
          ?>
          <div class="fs-3 fw-bold text-dark">$<?= number_format((float)$allTime, 2) ?></div>
          <div class="text-muted small"><?= count($monthlyRevenue) ?> month(s)</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Monthly breakdown -->
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-bold">Monthly Revenue</div>
        <div class="card-body p-0">
          <?php if (empty($monthlyRevenue)): ?>
            <p class="p-3 text-muted">No payment data available.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Month</th>
                  <th class="text-end">Payments</th>
                  <th class="text-end">New Memberships</th>
                  <th class="text-end">Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($monthlyRevenue as $row): ?>
                <tr>
                  <td><?= e($row['month_label']) ?></td>
                  <td class="text-end"><?= (int)$row['payment_count'] ?></td>
                  <td class="text-end"><?= (int)$row['new_memberships'] ?></td>
                  <td class="text-end fw-semibold">$<?= number_format((float)$row['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Revenue by plan -->
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-bold">Revenue by Plan</div>
        <div class="card-body p-0">
          <?php if (empty($revenueByPlan)): ?>
            <p class="p-3 text-muted">No payment data available.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Plan</th>
                  <th class="text-end">Members</th>
                  <th class="text-end">Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($revenueByPlan as $row): ?>
                <tr>
                  <td><?= e($row['PlanName']) ?></td>
                  <td class="text-end"><?= (int)$row['members'] ?></td>
                  <td class="text-end fw-semibold">$<?= number_format((float)$row['revenue'], 2) ?></td>
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

  <!-- Top spending members -->
  <div class="card shadow-sm mt-4">
    <div class="card-header fw-bold">Top 10 Spending Members</div>
    <div class="card-body p-0">
      <?php if (empty($topSpenders)): ?>
        <p class="p-3 text-muted">No payment data available.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Plan</th>
              <th class="text-end">Total Spent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topSpenders as $i => $row): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($row['Name']) ?></td>
              <td><?= e($row['Email']) ?></td>
              <td><?= e($row['PlanName']) ?></td>
              <td class="text-end fw-semibold">$<?= number_format((float)$row['total_spent'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_foot(); ?>
