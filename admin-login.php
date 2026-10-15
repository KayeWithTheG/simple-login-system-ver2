<?php
session_start();
require_once 'db.php';

$adminLockoutCollection = $database->selectCollection('admin_lockouts');
$auditCollection = $database->selectCollection('audit_logs');

$error = "";
$is_locked = false;
$is_permanent = false;
$remaining = 0;
$admin_user = "admin";

if (isset($_SESSION['locked_admin_user'])) {
    $lockData = $adminLockoutCollection->findOne(['username' => $admin_user]);
    
    if ($lockData) {
        if (!empty($lockData['is_permanent'])) {
            $is_locked = true;
            $is_permanent = true;
            $error = "Admin account is permanently locked due to excessive failed attempts. Please contact the Database Administrator.";
        } elseif (time() < $lockData['lockout_time']) {
            $remaining = $lockData['lockout_time'] - time();
            $is_locked = true;
            $error = "Admin login temporarily locked. Try again in $remaining seconds.";
        } else {
            if ($lockData['lockout_time'] > 0 && time() >= $lockData['lockout_time']) {
                $adminLockoutCollection->updateOne(
                    ['username' => $admin_user],
                    ['$set' => ['failed_attempts' => 0, 'lockout_time' => 0]]
                );
            }
            unset($_SESSION['locked_admin_user']);
        }
    } else {
        unset($_SESSION['locked_admin_user']);
    }
}

if (isset($_SESSION['admin_flash_error'])) {
    $error = $_SESSION['admin_flash_error'];
    unset($_SESSION['admin_flash_error']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_locked) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $timestamp = date("Y-m-d H:i:s");
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $lockData = $adminLockoutCollection->findOne(['username' => $admin_user]);
    if (!$lockData) {
        $adminLockoutCollection->insertOne([
            'username' => $admin_user,
            'failed_attempts' => 0,
            'lockout_time' => 0,
            'lockout_level' => 0,
            'is_permanent' => false
        ]);
        $lockData = $adminLockoutCollection->findOne(['username' => $admin_user]);
    }

    if (!empty($lockData['is_permanent'])) {
        $is_locked = true;
        $is_permanent = true;
        $_SESSION['locked_admin_user'] = true;
        $error = "Admin account is permanently locked. Please contact the Database Administrator.";
    } elseif (time() < $lockData['lockout_time']) {
        $remaining = $lockData['lockout_time'] - time();
        $is_locked = true;
        $_SESSION['locked_admin_user'] = true;
        $error = "Admin login temporarily locked. Try again in $remaining seconds.";
    } else {
        if ($lockData['lockout_time'] > 0 && time() >= $lockData['lockout_time']) {
            $adminLockoutCollection->updateOne(
                ['username' => $admin_user],
                ['$set' => ['failed_attempts' => 0, 'lockout_time' => 0]]
            );
        }

        if ($username === "admin" && $password === "admin123") {
            $adminLockoutCollection->deleteOne(['username' => $admin_user]);
            unset($_SESSION['locked_admin_user']);

            $_SESSION["admin_logged_in"] = true;

            $auditCollection->insertOne([
                'timestamp' => $timestamp,
                'action' => 'ADMIN_SUCCESS',
                'ip_address' => $ip_address,
                'student_id' => 'ADMIN',
                'details' => "ADMIN SUCCESS (IP: $ip_address): Admin logged in."
            ]);

            header("Location: admin-dashboard.php");
            exit;
        } else {
            $current_level = (int)($lockData['lockout_level'] ?? 0);
            $current_attempts = (int)($lockData['failed_attempts'] ?? 0);

            if ($current_level === 0) {
                $current_attempts++;
                if ($current_attempts >= 3) {
                    $lock_duration = 30; 
                    $new_level = 1;
                    $lockout_time = time() + $lock_duration;
                    $remaining = $lock_duration;
                    $is_locked = true;
                    $error = "Too many failed attempts. Locked for 30 seconds.";

                    $adminLockoutCollection->updateOne(
                        ['username' => $admin_user],
                        ['$set' => ['failed_attempts' => $current_attempts, 'lockout_level' => $new_level, 'lockout_time' => $lockout_time]]
                    );
                } else {
                    $error = "Invalid admin username or password (Attempt $current_attempts of 3).";
                    $adminLockoutCollection->updateOne(
                        ['username' => $admin_user],
                        ['$set' => ['failed_attempts' => $current_attempts]]
                    );
                }
            } else {
                if ($current_level === 1) {
                    $lock_duration = 60; 
                    $new_level = 2;
                    $error = "Repeated failed attempts. Locked for 1 minute.";
                } elseif ($current_level >= 2 && $current_level <= 5) {
                    $lock_duration = 120; 
                    $new_level = $current_level + 1;
                    $error = "Multiple failed attempts. Locked for 2 minutes.";
                } else {
                    $adminLockoutCollection->updateOne(
                        ['username' => $admin_user],
                        ['$set' => ['is_permanent' => true, 'lockout_level' => 6]]
                    );
                    $is_permanent = true;
                    $is_locked = true;
                    $error = "Admin account permanently locked due to repeated security violations. Please contact the Database Administrator.";
                }

                if (!$is_permanent) {
                    $lockout_time = time() + $lock_duration;
                    $remaining = $lock_duration;
                    $is_locked = true;

                    $adminLockoutCollection->updateOne(
                        ['username' => $admin_user],
                        ['$set' => ['lockout_level' => $new_level, 'lockout_time' => $lockout_time]]
                    );
                }
            }

            $_SESSION['locked_admin_user'] = true;

            $auditCollection->insertOne([
                'timestamp' => $timestamp,
                'action' => 'ADMIN_FAILED',
                'ip_address' => $ip_address,
                'student_id' => 'ADMIN',
                'details' => "ADMIN FAILED (IP: $ip_address): Failed admin login attempt."
            ]);
        }
    }

    $_SESSION['admin_flash_error'] = $error;
    header("Location: admin-login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login - Student Access Pass</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="box" style="width: 520px; padding: 40px;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="JRU Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">Student Access Pass</div>
    </div>
    <h2 style="font-size: 22px; margin-bottom: 15px; color: #002147; text-align: center;">Admin Login</h2>

    <!-- ADMIN LOGIN POLICY BANNER -->
    <div style="font-size: 12px; padding: 10px; margin-bottom: 20px; background: #fdf2f2; border-left: 4px solid #c53030; text-align: left; border-radius: 4px;">
        <strong>Admin Security Policy:</strong>
        <ul style="margin: 4px 0 0 15px; padding: 0;">
            <li>Admin credentials are required for database management.</li>
            <li>Maximum 3 failed attempts allows 30s lockout.</li>
        </ul>
    </div>

    <div id="error-container">
      <?php if ($error) { echo "<p class='error' style='margin-bottom: 20px; text-align: center;'>" . $error . "</p>"; } ?>
    </div>

    <form method="POST" action="admin-login.php">
      <input 
        type="text" 
        id="username" 
        name="username" 
        placeholder="Admin Username" 
        <?php echo $is_locked ? 'disabled' : ''; ?> 
        required 
        style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;"
      >
      
      <input 
        type="password" 
        id="password" 
        name="password" 
        placeholder="Admin Password" 
        <?php echo $is_locked ? 'disabled' : ''; ?> 
        required 
        style="padding: 14px; font-size: 15px; margin-bottom: 20px; width: 100%; box-sizing: border-box;"
      >
      
      <button 
        type="submit" 
        id="login-btn" 
        class="btn-login" 
        style="padding: 14px; font-size: 16px; font-weight: bold; background: #c53030; width: 100%; color: white; border: none; border-radius: 4px; cursor: pointer; <?php echo $is_locked ? 'opacity: 0.6;' : ''; ?>" 
        <?php echo $is_locked ? 'disabled' : ''; ?>
      >
        <?php
            if ($is_permanent) {
                echo "Permanently Locked";
            } elseif ($is_locked) {
                echo "Locked (<span id='countdown-timer'>$remaining</span>s)";
            } else {
                echo "Login as Admin";
            }
        ?>
      </button>
    </form>

    <div style="text-align: center; margin-top: 20px;">
      <a class="link" href="index.php" style="font-size: 14px; color: #002147; text-decoration: none; font-weight: bold;">
        &larr; Back to Student Login
      </a>
    </div>
  </div>

  <?php if ($is_locked && !$is_permanent): ?>
  <script>
    let timeLeft = <?php echo (int)$remaining; ?>;
    const timerSpan = document.getElementById('countdown-timer');

    const countdownInterval = setInterval(() => {
        timeLeft--;
        if (timerSpan) {
            timerSpan.textContent = timeLeft;
        }

        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            window.location.reload();
        }
    }, 1000);
  </script>
  <?php endif; ?>
</body>
</html>
