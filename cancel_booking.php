<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member    = require_member();
$db        = db();
$mid       = $member['id'];
$bookingId = (int)($_POST['booking_id'] ?? $_GET['id'] ?? 0);

if (!$bookingId) {
    header('Location: my_account.php');
    exit;
}

$stmt = $db->prepare("
    SELECT b.*, c.Title AS ClassTitle, c.Class_id, c.StartsAt, c.Room
    FROM booking b JOIN class c ON c.Class_id = b.Class_id
    WHERE b.Booking_id = ? AND b.Member_id = ?
");
$stmt->execute([$bookingId, $mid]);
$booking = $stmt->fetch();

if (!$booking) {
    flash_set('error', 'Booking not found.');
    header('Location: my_account.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId = (int)$booking['Class_id'];

    try {
        $db->beginTransaction();

        // Cancel the booking
        $stmt = $db->prepare("UPDATE booking SET Status = 'cancelled' WHERE Booking_id = ?");
        $stmt->execute([$bookingId]);

        // Promote oldest waitlisted booking for this class if the cancelled one was 'booked'
        if ($booking['Status'] === 'booked') {
            $stmt = $db->prepare("
                SELECT Booking_id FROM booking
                WHERE Class_id = ? AND Status = 'waitlisted'
                ORDER BY BookedAt ASC
                LIMIT 1
            ");
            $stmt->execute([$classId]);
            $waitlist = $stmt->fetch();

            if ($waitlist) {
                $wid = (int)$waitlist['Booking_id'];
                $upd = $db->prepare("UPDATE booking SET Status = 'booked' WHERE Booking_id = ?");
                $upd->execute([$wid]);
            }
        }

        $db->commit();
        flash_set('success', 'Booking for "' . $booking['ClassTitle'] . '" has been cancelled.');
    } catch (\Exception $e) {
        $db->rollBack();
        flash_set('error', 'Cancellation failed. Please try again.');
    }

    header('Location: my_account.php');
    exit;
}

page_head('Cancel Booking');
page_nav();
?>
<div class="container" style="max-width:480px">
  <?php flash_html(); ?>
  <h2 class="mb-3">Cancel Booking</h2>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <p>Are you sure you want to cancel your booking for:</p>
      <p class="fw-bold fs-5"><?= e($booking['ClassTitle']) ?></p>
      <p class="text-muted mb-1">
        📅 <?= date('D, M j Y \a\t g:i A', strtotime($booking['StartsAt'])) ?>
      </p>
      <?php if ($booking['Room']): ?>
      <p class="text-muted mb-1">📍 <?= e($booking['Room']) ?></p>
      <?php endif; ?>
      <p class="text-muted mt-2">Booking status: <strong><?= e($booking['Status']) ?></strong></p>
    </div>
  </div>
  <form method="post">
    <input type="hidden" name="booking_id" value="<?= (int)$booking['Booking_id'] ?>">
    <button type="submit" class="btn btn-danger">Yes, Cancel Booking</button>
    <a href="my_account.php" class="btn btn-outline-secondary ms-2">Keep It</a>
  </form>
</div>
<?php page_foot(); ?>
