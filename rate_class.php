<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$member  = require_member();
$db      = db();
$mid     = $member['id'];
$classId = (int)($_GET['class_id'] ?? 0);

if (!$classId) {
    flash_set('error', 'No class specified.');
    header('Location: my_account.php');
    exit;
}

// Load the class and verify it is in the past
$stmt = $db->prepare("SELECT * FROM class WHERE Class_id = ?");
$stmt->execute([$classId]);
$cls = $stmt->fetch();

if (!$cls) {
    flash_set('error', 'Class not found.');
    header('Location: my_account.php');
    exit;
}

if (strtotime($cls['StartsAt']) >= time()) {
    flash_set('error', 'You can only rate classes that have already taken place.');
    header('Location: my_account.php');
    exit;
}

// Verify the member has an attendance record for this class
$stmt = $db->prepare("SELECT CheckedInAt FROM attendance WHERE Class_id = ? AND Member_id = ?");
$stmt->execute([$classId, $mid]);
$attended = $stmt->fetch();

if (!$attended) {
    flash_set('error', 'You can only rate classes you attended.');
    header('Location: my_account.php');
    exit;
}

// Check if already rated
$stmt = $db->prepare("SELECT * FROM class_rating WHERE Class_id = ? AND Member_id = ?");
$stmt->execute([$classId, $mid]);
$existing = $stmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stars   = (int)($_POST['stars'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($stars < 1 || $stars > 5) {
        $error = 'Please select a star rating between 1 and 5.';
    } else {
        try {
            if ($existing) {
                $stmt = $db->prepare(
                    "UPDATE class_rating SET Stars = ?, Comment = ? WHERE Class_id = ? AND Member_id = ?"
                );
                $stmt->execute([$stars, $comment ?: null, $classId, $mid]);
                flash_set('success', 'Your rating has been updated.');
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO class_rating (Class_id, Member_id, Stars, Comment) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$classId, $mid, $stars, $comment ?: null]);
                flash_set('success', 'Thanks for rating this class!');
            }
        } catch (\PDOException $e) {
            // Duplicate key — already rated
            flash_set('error', 'You have already rated this class.');
        }
        header('Location: my_account.php');
        exit;
    }

    // Re-fetch existing in case of error
    $stmt = $db->prepare("SELECT * FROM class_rating WHERE Class_id = ? AND Member_id = ?");
    $stmt->execute([$classId, $mid]);
    $existing = $stmt->fetch();
}

// Load trainer name
$stmt = $db->prepare("SELECT t.Name FROM trainer t JOIN class c ON c.Trainer_id = t.Trainer_id WHERE c.Class_id = ?");
$stmt->execute([$classId]);
$trainerName = $stmt->fetchColumn();

page_head('Rate Class');
page_nav();
?>
<div class="container" style="max-width:600px">
  <?php flash_html(); ?>
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="my_account.php">My Account</a></li>
      <li class="breadcrumb-item active">Rate Class</li>
    </ol>
  </nav>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">
      <?= $existing ? 'Update Your Rating' : 'Rate This Class' ?>
    </div>
    <div class="card-body">
      <h5 class="mb-1"><?= e($cls['Title']) ?></h5>
      <p class="text-muted mb-1">
        <?= e(date('D, M j Y \a\t g:i A', strtotime($cls['StartsAt']))) ?>
        &mdash; <?= e($cls['Room'] ?? '') ?>
      </p>
      <p class="text-muted mb-4">Trainer: <?= e((string)$trainerName) ?></p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-4">
          <label class="form-label fw-semibold">Your Rating</label>
          <div class="d-flex gap-3">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stars"
                     id="star<?= $i ?>" value="<?= $i ?>"
                     <?= ($existing && (int)$existing['Stars'] === $i) ? 'checked' : '' ?>
                     required>
              <label class="form-check-label" for="star<?= $i ?>">
                <?= $i ?> star<?= $i > 1 ? 's' : '' ?>
              </label>
            </div>
            <?php endfor; ?>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Comment <span class="text-muted">(optional)</span></label>
          <textarea name="comment" class="form-control" rows="3"
                    placeholder="Share your thoughts about this class…"><?= e($existing['Comment'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
          <?= $existing ? 'Update Rating' : 'Submit Rating' ?>
        </button>
        <a href="my_account.php" class="btn btn-outline-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
</div>
<?php page_foot(); ?>
