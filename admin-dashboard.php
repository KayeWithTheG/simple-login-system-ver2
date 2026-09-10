<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: admin-login.php");
    exit;
}

$lockoutCollection = $database->selectCollection('lockouts');

// Handle Unlock Action kung pinindot ni admin
if (isset($_GET['unlock'])) {
    $unlock_id = trim($_GET['unlock']);
    $lockoutCollection->deleteOne(['student_id' => $unlock_id]);
    $_SESSION['flash_success'] = "Student $unlock_id has been successfully unlocked.";
    header("Location: admin-dashboard.php");
    exit;
}

$students = $collection->find([], ['sort' => ['created_at' => -1]]);
$lockouts = $lockoutCollection->find([], ['sort' => ['lockout_time' => -1]]);

$locked_students = [];
foreach ($lockouts as $l) {
    if ($l['is_permanent'] || (isset($l['lockout_time']) && time() < $l['lockout_time'])) {
        $locked_students[$l['student_id']] = $l;
    }
}

$success = "";
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Student Access Pass</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="box" style="width: 780px; padding: 40px; text-align: left;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">System Administrator Console</div>
      <div style="font-size: 12px; color: #666; margin-top: 4px;">Logged in as: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "admin"); ?></div>
    </div>

    <?php if ($success): ?>
      <div style="background: #e6fffa; color: #234e52; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; border: 1px solid #b2f5ea;">
        <?php echo $success; ?>
      </div>
    <?php endif; ?>

    <h2 style="font-size: 18px; border-bottom: 2px solid #002147; padding-bottom: 6px; margin-bottom: 15px; color: #002147;">Locked Accounts Management</h2>
    
    <div style="max-height: 160px; overflow-y: auto; margin-bottom: 25px;">
      <table border="1" width="100%" style="border-collapse: collapse; text-align: left; font-size: 13px;">
        <tr style="background: #c53030; color: white;">
          <th style="padding: 8px;">Student ID</th>
          <th style="padding: 8px;">Failed Attempts</th>
          <th style="padding: 8px;">Status</th>
          <th style="padding: 8px; text-align: center;">Action</th>
        </tr>
        <?php if (empty($locked_students)): ?>
        <tr>
          <td colspan="4" style="padding: 10px; text-align: center; color: #666;">No currently locked accounts.</td>
        </tr>
        <?php else: ?>
          <?php foreach($locked_students as $id => $lock): ?>
          <tr style="border-bottom: 1px solid #ddd;">
            <td style="padding: 8px;"><?php echo htmlspecialchars($id); ?></td>
            <td style="padding: 8px;"><?php echo htmlspecialchars($lock['failed_attempts'] ?? 0); ?></td>
            <td style="padding: 8px; color: #c53030; font-weight: bold;">
              <?php echo $lock['is_permanent'] ? 'Permanent Lock' : 'Temporary Lock'; ?>
            </td>
            <td style="padding: 8px; text-align: center;">
              <a href="admin-dashboard.php?unlock=<?php echo urlencode($id); ?>" style="background: #2f855a; color: white; padding: 4px 10px; border-radius: 3px; text-decoration: none; font-size: 12px; font-weight: bold;">Unlock</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </table>
    </div>

    <h2 style="font-size: 18px; border-bottom: 2px solid #002147; padding-bottom: 6px; margin-bottom: 15px; color: #002147;">Registered Students</h2>
    
    <div style="max-height: 200px; overflow-y: auto; margin-bottom: 25px;">
      <table border="1" width="100%" style="border-collapse: collapse; text-align: left; font-size: 13px;">
        <tr style="background: #002147; color: white;">
          <th style="padding: 8px;">Student ID</th>
          <th style="padding: 8px;">Name</th>
          <th style="padding: 8px;">Department</th>
          <th style="padding: 8px;">Status</th>
          <th style="padding: 8px;">Registered On</th>
        </tr>
        <?php foreach($students as $s): 
            $sid = $s['student_id'];
            $is_currently_locked = isset($locked_students[$sid]);
        ?>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 8px;"><?php echo htmlspecialchars($sid); ?></td>
          <td style="padding: 8px;"><?php echo htmlspecialchars($s['name'] ?? ''); ?></td>
          <td style="padding: 8px;"><?php echo htmlspecialchars($s['department'] ?? ''); ?></td>
          <td style="padding: 8px; font-weight: bold; color: <?php echo $is_currently_locked ? '#c53030' : '#2f855a'; ?>;">
            <?php echo $is_currently_locked ? 'Locked' : 'Active'; ?>
          </td>
          <td style="padding: 8px;"><?php echo htmlspecialchars($s['created_at'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div style="text-align: center; margin-top: 25px;">
      <a href="admin-logout.php" style="background: #c53030; color: white; padding: 10px 24px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: bold; display: inline-block;">Logout</a>
    </div>
  </div>
</body>
</html>