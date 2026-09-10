<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once 'db.php';

$lockoutCollection = $database->selectCollection('lockouts');
$auditCollection = $database->selectCollection('audit_logs');

$error = "";
$is_locked = false;
$is_permanent = false;
$remaining = 0;
$locked_student_id = "";

$currentTime = time();

if (isset($_SESSION['locked_student_id'])) {
    $check_id = $_SESSION['locked_student_id'];
    $lockData = $lockoutCollection->findOne(['student_id' => $check_id]);
    
    if ($lockData) {
        if (!empty($lockData['is_permanent'])) {
            $is_locked = true;
            $is_permanent = true;
            $locked_student_id = $check_id;
            $error = "Account $check_id is permanently locked due to excessive failed attempts. Please contact the Admin.";
        } elseif ($currentTime < $lockData['lockout_time']) {
            $remaining = $lockData['lockout_time'] - $currentTime;
            $is_locked = true;
            $locked_student_id = $check_id;
            $error = "Account $check_id temporarily locked. Try again in $remaining seconds.";
        } else {
            // Timer expired, but keep record for level tracking or reset safely
            unset($_SESSION['locked_student_id']);
        }
    } else {
        unset($_SESSION['locked_student_id']);
    }
}

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_locked) {
    $student_id = trim(htmlspecialchars($_POST["student_id"]));
    $pin = trim($_POST["pin"]);
    $timestamp = date("Y-m-d H:i:s");
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $valid = false;
    $student_name = "";
    $student_dept = "";

    $lockData = $lockoutCollection->findOne(['student_id' => $student_id]);
    if (!$lockData) {
        $lockoutCollection->insertOne([
            'student_id' => $student_id,
            'failed_attempts' => 0,
            'lockout_time' => 0,
            'lockout_level' => 0,
            'is_permanent' => false
        ]);
        $lockData = $lockoutCollection->findOne(['student_id' => $student_id]);
    }

    // Check if currently locked based on DB
    if (!empty($lockData['is_permanent'])) {
        $is_locked = true;
        $is_permanent = true;
        $locked_student_id = $student_id;
        $_SESSION['locked_student_id'] = $student_id;
        $error = "Account is permanently locked. Please contact the Administrator.";
    } elseif ($currentTime < $lockData['lockout_time']) {
        $remaining = $lockData['lockout_time'] - $currentTime;
        $is_locked = true;
        $locked_student_id = $student_id;
        $_SESSION['locked_student_id'] = $student_id;
        $error = "Account temporarily locked. Try again in $remaining seconds.";
    } else {
        // If previous lockout time has passed, reset time/attempts for this round but keep level tracking if needed, or handle expiration
        if ($lockData['lockout_time'] > 0 && $currentTime >= $lockData['lockout_time']) {
            $lockoutCollection->updateOne(
                ['student_id' => $student_id],
                ['$set' => ['failed_attempts' => 0, 'lockout_time' => 0]]
            );
            $lockData = $lockoutCollection->findOne(['student_id' => $student_id]);
        }

        if (strlen($student_id) !== 9) {
            $error = "Student ID must be exactly 9 characters (e.g., 25-266124).";
        } else {
            $student = $collection->findOne(['student_id' => $student_id]);

            if ($student && password_verify($pin, $student['pin'])) {
                $valid = true;
                $student_name = $student['name'];
                $student_dept = $student['department'];
            }

            if ($valid) {
                // Successful login: wipe out lockout record entirely so user starts fresh next time
                $lockoutCollection->deleteOne(['student_id' => $student_id]);
                unset($_SESSION['locked_student_id']);

                $_SESSION["student_id"] = $student_id;
                $_SESSION["name"] = $student_name;
                $_SESSION["department"] = $student_dept;

                try {
                    $auditCollection->insertOne([
                        'timestamp' => $timestamp,
                        'action' => 'SUCCESS',
                        'ip_address' => $ip_address,
                        'student_id' => $student_id,
                        'details' => "SUCCESS (IP: $ip_address): $student_id logged in."
                    ]);
                } catch (Exception $e) {}

                header("Location: dashboard.php");
                exit;
            } else {
                $current_level = (int)($lockData['lockout_level'] ?? 0);
                $current_attempts = (int)($lockData['failed_attempts'] ?? 0);

                if ($current_level === 0) {
                    $current_attempts++;
                    if ($current_attempts >= 3) {
                        $lock_duration = 30; 
                        $new_level = 1;
                        $lockout_time = $currentTime + $lock_duration;
                        $remaining = $lock_duration;
                        $is_locked = true;
                        $error = "Too many failed attempts. Locked for 30 seconds.";

                        $lockoutCollection->updateOne(
                            ['student_id' => $student_id],
                            ['$set' => ['failed_attempts' => $current_attempts, 'lockout_level' => $new_level, 'lockout_time' => $lockout_time]]
                        );
                    } else {
                        $error = "Invalid Student ID or Password.";
                        $lockoutCollection->updateOne(
                            ['student_id' => $student_id],
                            ['$set' => ['failed_attempts' => $current_attempts]]
                        );
                    }
                } else {
                    if ($current_level === 1) {
                        $lock_duration = 60; 
                        $new_level = 2;
                        $error = "Repeated failed attempts. Locked for 1 minute.";
                    } elseif ($current_level >= 2 && $current_level < 6) {
                        $lock_duration = 120; 
                        $new_level = $current_level + 1;
                        $error = "Multiple failed attempts. Locked for 2 minutes.";
                    } else {
                        $lockoutCollection->updateOne(
                            ['student_id' => $student_id],
                            ['$set' => ['is_permanent' => true, 'lockout_level' => 6]]
                        );
                        $is_permanent = true;
                        $is_locked = true;
                        $error = "Account permanently locked due to repeated security violations. Please contact the Admin.";
                    }

                    if (!$is_permanent) {
                        $lockout_time = $currentTime + $lock_duration;
                        $remaining = $lock_duration;
                        $is_locked = true;

                        $lockoutCollection->updateOne(
                            ['student_id' => $student_id],
                            ['$set' => ['lockout_level' => $new_level, 'lockout_time' => $lockout_time]]
                        );
                    }
                }

                $locked_student_id = $student_id;
                $_SESSION['locked_student_id'] = $student_id;

                try {
                    $auditCollection->insertOne([
                        'timestamp' => $timestamp,
                        'action' => 'FAILED',
                        'ip_address' => $ip_address,
                        'student_id' => $student_id,
                        'details' => "FAILED (IP: $ip_address): Attempt for ID $student_id."
                    ]);
                } catch (Exception $e) {}
            }
        }
    }

    $_SESSION['flash_error'] = $error;
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Student Access Pass</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="box" style="width: 520px; padding: 40px;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="JRU Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">Student Access Pass</div>
    </div>
    <h2 style="font-size: 22px; margin-bottom: 20px; color: #002147; text-align: center;">Access Pass Login</h2>

    <div id="error-container">
      <?php if ($error) { echo "<p class='error' style='margin-bottom: 20px;'>" . $error . "</p>"; } ?>
    </div>

    <form method="POST" action="index.php">
      <input type="text" id="student_id" name="student_id" placeholder="Student ID (e.g. 25-266124)" maxlength="9" value="<?php echo htmlspecialchars($locked_student_id); ?>" <?php echo $is_locked ? 'disabled' : ''; ?> required style="padding: 14px; font-size: 15px; margin-bottom: 15px;">
      <input type="password" id="pin" name="pin" placeholder="Password" <?php echo $is_locked ? 'disabled' : ''; ?> required style="padding: 14px; font-size: 15px; margin-bottom: 20px;">
      <button type="submit" id="login-btn" class="btn-login" style="padding: 14px; font-size: 16px; font-weight: bold; background: #002147; width: 100%; border: none; border-radius: 4px; color: white; cursor: pointer; <?php echo $is_locked ? 'pointer-events: none; opacity: 0.6;' : ''; ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
        <?php
            if ($is_permanent) {
                echo "Permanently Locked";
            } elseif ($is_locked) {
                echo "Locked (<span id='countdown-timer'>$remaining</span>s)";
            } else {
                echo "Login";
            }
        ?>
      </button>
    </form>

    <div style="width: 340px; margin: 20px auto 0 auto; display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; align-items: center; font-size: 14px; <?php echo $is_locked ? 'pointer-events: none; opacity: 0.4;' : ''; ?>">
      <div style="text-align: right;"><a href="register.php" style="color: #002147; text-decoration: none;">Register</a></div>
      <div style="text-align: center; color: #ccc; padding: 0 8px;">|</div>
      <div style="text-align: center;"><a href="admin-login.php" style="color: #002147; text-decoration: none;">Admin</a></div>
      <div style="text-align: center; color: #ccc; padding: 0 8px;">|</div>
      <div style="text-align: left;"><a href="forgot-password.php" style="color: #002147; text-decoration: none;">Forgot password?</a></div>
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
