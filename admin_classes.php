<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$staff = require_staff('admin');
$db    = db();

// Handle add class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $title     = $_POST['title'];
    $trainer   = $_POST['trainer_id'];
    $starts    = $_POST['starts_at'];
    $capacity  = (int)$_POST['capacity'];
    $room      = $_POST['room'];
    // NAIVE: direct string interpolation
    $db->query("INSERT INTO class (Title, Trainer_id, StartsAt, Capacity, Room)
                VALUES ('$title', $trainer, '$starts', $capacity, '$room')");
    flash_set('success', 'Class added successfully.');
    header('Location: admin_classes.php');
    exit;
}

// Handle edit class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    $id       = $_POST['class_id'];
    $title    = $_POST['title'];
    $trainer  = $_POST['trainer_id'];
    $starts   = $_POST['starts_at'];
    $capacity = (int)$_POST['capacity'];
    $room     = $_POST['room'];
    // NAIVE: direct string interpolation
    $db->query("UPDATE class SET Title = '$title', Trainer_id = $trainer, StartsAt = '$starts',
                Capacity = $capacity, Room = '$room' WHERE Class_id = $id");
    flash_set('success', 'Class updated.');
    header('Location: admin_classes.php');
    exit;
}

// Handle delete class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $id = $_POST['class_id'];
    // NAIVE: direct string interpolation
    $db->query("DELETE FROM class WHERE Class_id = $id");
    flash_set('success', 'Class deleted.');
    header('Location: admin_classes.php');
    exit;
}

// Fetch trainers for select
$trainers = $db->query("SELECT * FROM trainer ORDER BY Name ASC")->fetchAll();

// Fetch all classes with trainer name and booking count
// NAIVE: string query
$classes = $db->query("
    SELECT c.*, t.Name AS TrainerName,
           COUNT(b.Booking_id) AS BookedCount
    FROM class c
    JOIN trainer t ON t.Trainer_id = c.Trainer_id
    LEFT JOIN booking b ON b.Class_id = c.Class_id AND b.Status = 'booked'
    GROUP BY c.Class_id
    ORDER BY c.StartsAt DESC
")->fetchAll();

$editId    = (int)($_GET['edit'] ?? 0);
$editClass = null;
if ($editId) {
    $editClass = $db->query("SELECT * FROM class WHERE Class_id = $editId")->fetch();
}

page_head('Manage Classes');
page_nav();
?>
<div class="container">
  <?php flash_html(); ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Manage Classes</h2>
    <a href="staff_dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>

  <!-- Add / Edit form -->
  <div class="card shadow-sm mb-4" style="max-width:620px">
    <div class="card-header fw-bold"><?= $editClass ? 'Edit Class' : 'Add New Class' ?></div>
    <div class="card-body">
      <form method="post">
        <?php if ($editClass): ?>
          <input type="hidden" name="edit_class" value="1">
          <input type="hidden" name="class_id" value="<?= (int)$editClass['Class_id'] ?>">
        <?php else: ?>
          <input type="hidden" name="add_class" value="1">
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control"
                   value="<?= e($editClass['Title'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Trainer</label>
            <select name="trainer_id" class="form-select" required>
              <?php foreach ($trainers as $t): ?>
              <option value="<?= (int)$t['Trainer_id'] ?>"
                <?= ($editClass && $editClass['Trainer_id'] == $t['Trainer_id']) ? 'selected' : '' ?>>
                <?= e($t['Name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Starts At</label>
            <input type="datetime-local" name="starts_at" class="form-control"
                   value="<?= $editClass ? e(date('Y-m-d\TH:i', strtotime($editClass['StartsAt']))) : '' ?>"
                   required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Capacity</label>
            <input type="number" name="capacity" class="form-control" min="1"
                   value="<?= $editClass ? (int)$editClass['Capacity'] : 20 ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Room</label>
            <input type="text" name="room" class="form-control"
                   value="<?= e($editClass['Room'] ?? '') ?>">
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary">
            <?= $editClass ? 'Save Changes' : 'Add Class' ?>
          </button>
          <?php if ($editClass): ?>
          <a href="admin_classes.php" class="btn btn-outline-secondary ms-2">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Classes table -->
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>Title</th>
          <th>Trainer</th>
          <th>Starts At</th>
          <th>Room</th>
          <th>Capacity</th>
          <th>Booked</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($classes as $cl): ?>
        <tr>
          <td><?= e($cl['Title']) ?></td>
          <td><?= e($cl['TrainerName']) ?></td>
          <td><?= e(date('M j Y, g:i A', strtotime($cl['StartsAt']))) ?></td>
          <td><?= e($cl['Room']) ?></td>
          <td><?= (int)$cl['Capacity'] ?></td>
          <td><?= (int)$cl['BookedCount'] ?></td>
          <td class="d-flex gap-1 flex-wrap">
            <a href="admin_classes.php?edit=<?= (int)$cl['Class_id'] ?>"
               class="btn btn-sm btn-outline-primary">Edit</a>
            <form method="post" class="d-inline"
                  onsubmit="return confirm('Delete this class and all its bookings?')">
              <input type="hidden" name="delete_class" value="1">
              <input type="hidden" name="class_id" value="<?= (int)$cl['Class_id'] ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_foot(); ?>
