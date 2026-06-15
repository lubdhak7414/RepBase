<?php
declare(strict_types=1);

/**
 * Shared view / utility helpers for RepBase.
 */

// -------------------------------------------------------
// Output escaping
// -------------------------------------------------------

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -------------------------------------------------------
// Session helpers
// -------------------------------------------------------

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Require a logged-in member; redirect to login.php otherwise.
 */
function require_member(): array
{
    session_start_safe();
    if (empty($_SESSION['member_id'])) {
        header('Location: login.php');
        exit;
    }
    return [
        'id'   => (int) $_SESSION['member_id'],
        'name' => $_SESSION['member_name'] ?? 'Member',
    ];
}

/**
 * Require a logged-in staff account with the given role(s).
 * $roles can be 'admin', 'trainer', or ['admin','trainer'].
 */
function require_staff(array|string $roles = ['admin', 'trainer']): array
{
    session_start_safe();
    $roles = (array) $roles;

    if (empty($_SESSION['staff_id']) || !in_array($_SESSION['staff_role'], $roles, true)) {
        header('Location: staff_login.php');
        exit;
    }
    return [
        'id'   => (int) $_SESSION['staff_id'],
        'name' => $_SESSION['staff_username'] ?? 'Staff',
        'role' => $_SESSION['staff_role'],
    ];
}

// -------------------------------------------------------
// Flash messages
// -------------------------------------------------------

function flash_set(string $type, string $msg): void
{
    session_start_safe();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    session_start_safe();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// -------------------------------------------------------
// HTML layout helpers
// -------------------------------------------------------

function page_head(string $title): void
{
    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="en">' . "\n";
    echo '<head>' . "\n";
    echo '  <meta charset="UTF-8">' . "\n";
    echo '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '  <title>' . e($title) . ' — ' . APP_NAME . '</title>' . "\n";
    echo '  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">' . "\n";
    echo '  <link rel="stylesheet" href="styles.css">' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
}

function page_nav(bool $staffMode = false): void
{
    session_start_safe();
    $isStaff  = !empty($_SESSION['staff_id']);
    $isMember = !empty($_SESSION['member_id']);
    $role     = $_SESSION['staff_role'] ?? '';

    echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">' . "\n";
    echo '  <div class="container">' . "\n";
    echo '    <a class="navbar-brand fw-bold" href="index.php">🏋️ ' . APP_NAME . '</a>' . "\n";
    echo '    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">' . "\n";
    echo '      <span class="navbar-toggler-icon"></span>' . "\n";
    echo '    </button>' . "\n";
    echo '    <div class="collapse navbar-collapse" id="navMenu">' . "\n";
    echo '      <ul class="navbar-nav me-auto">' . "\n";
    echo '        <li class="nav-item"><a class="nav-link" href="index.php">Schedule</a></li>' . "\n";

    if ($isMember) {
        echo '        <li class="nav-item"><a class="nav-link" href="my_account.php">My Account</a></li>' . "\n";
    }
    if ($isStaff && $role === 'trainer') {
        echo '        <li class="nav-item"><a class="nav-link" href="trainer_roster.php">My Classes</a></li>' . "\n";
        echo '        <li class="nav-item"><a class="nav-link" href="attendance.php">Attendance</a></li>' . "\n";
    }
    if ($isStaff && $role === 'admin') {
        echo '        <li class="nav-item dropdown">' . "\n";
        echo '          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Admin</a>' . "\n";
        echo '          <ul class="dropdown-menu">' . "\n";
        echo '            <li><a class="dropdown-item" href="admin_members.php">Members</a></li>' . "\n";
        echo '            <li><a class="dropdown-item" href="admin_classes.php">Classes</a></li>' . "\n";
        echo '            <li><a class="dropdown-item" href="admin_payments.php">Payments</a></li>' . "\n";
        echo '            <li><a class="dropdown-item" href="admin_expiring.php">Expiring Memberships</a></li>' . "\n";
        echo '            <li><a class="dropdown-item" href="revenue.php">Revenue Dashboard</a></li>' . "\n";
        echo '          </ul>' . "\n";
        echo '        </li>' . "\n";
    }

    echo '      </ul>' . "\n";
    echo '      <ul class="navbar-nav">' . "\n";
    if ($isMember) {
        echo '        <li class="nav-item"><span class="nav-link text-light">👤 ' . e($_SESSION['member_name'] ?? '') . '</span></li>' . "\n";
        echo '        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>' . "\n";
    } elseif ($isStaff) {
        echo '        <li class="nav-item"><span class="nav-link text-light">🔑 ' . e($_SESSION['staff_username'] ?? '') . ' (' . e($role) . ')</span></li>' . "\n";
        echo '        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>' . "\n";
    } else {
        echo '        <li class="nav-item"><a class="nav-link" href="login.php">Member Login</a></li>' . "\n";
        echo '        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>' . "\n";
        echo '        <li class="nav-item"><a class="nav-link" href="staff_login.php">Staff</a></li>' . "\n";
    }
    echo '      </ul>' . "\n";
    echo '    </div>' . "\n";
    echo '  </div>' . "\n";
    echo '</nav>' . "\n";
}

function flash_html(): void
{
    $f = flash_get();
    if ($f === null) {
        return;
    }
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    echo '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">' . "\n";
    echo '  ' . e($f['msg']) . "\n";
    echo '  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . "\n";
    echo '</div>' . "\n";
}

function page_foot(): void
{
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}
