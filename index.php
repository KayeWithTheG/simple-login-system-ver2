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
$ip_address = $_SERVER['REMOTE_ADDR'];

// Quick manual reset URL: index.php?clear=1
if (isset($_GET['clear'])) {
    unset($_SESSION['active_lockout_id']);
    header("Location: index.php");
    exit;
}

// 1. GET ACTIVE LOCKOUT STUDENT ID FROM SESSION OR URL
if (isset($_SESSION['active_lockout_id'])) {
    $locked_student_id = $_SESSION['active_lockout_id'];
} elseif (isset($_GET['locked'])) {
    $locked_student_id = trim($_GET['locked']);
}

// 2. CHECK LOCKOUT EXPIRATION IN DATABASE
if (!empty($locked_student_id)) {
    $lockData = $lockoutCollection->findOne(['student_id' => $locked_student_id]);

    if ($lockData) {
        if (!empty($lockData['is_permanent'])) {
            $is_locked = true;
            $is_permanent = true;
            $_SESSION['active_lockout_id'] = $locked_student_id;
            $error = "Account $locked_student_id is permanently locked due to excessive failed attempts. Please contact the Admin.";
        } elseif ($currentTime < $lockData['lockout_time']) {
            $remaining = $lockData['lockout_time'] - $currentTime;
            $is_locked = true;
            $_SESSION['active_lockout_id'] = $locked_student_id;
            $error = "Too many failed attempts. Login locked for account $locked_student_id. Try again in $remaining seconds.";
        } else {
            // AUTOMATIC UNLOCK PAG TAPOS NA ANG TIMER
            unset($_SESSION['active_lockout_id']);
            $locked_student_id = "";
            $is_locked = false;
        }
    } else {
        unset($_SESSION['active_lockout_id']);
    }
}

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 3. POST LOGIN ATTEMPT
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_locked) {

    $student_id = trim(htmlspecialchars($_POST["student_id"]));
    $pin = trim($_POST["pin"]);
    $timestamp = date("Y-m-d H:i:s");

    $valid = false;
    $student_name = "";
    $student_dept = "";

    if (!preg_match('/^\d{2}-\d{6}$/', $student_id)) {
        $error = "Invalid Student ID format. Must contain digits only (e.g., 25-266124).";
    } else {

        $lockData = $lockoutCollection->findOne(['student_id' => $student_id]);

        // KUNG WALA PA SA LOCKOUTS COLLECTION, I-INSERT AS LEVEL 0, 0 ATTEMPTS
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

        // RE-CHECK DB STATUS
        if (!empty($lockData['is_permanent'])) {
            $is_locked = true;
            $is_permanent = true;
            $_SESSION['active_lockout_id'] = $student_id;
            $error = "Account is permanently locked. Please contact the Administrator.";
        } elseif ($currentTime < $lockData['lockout_time']) {
            $remaining = $lockData['lockout_time'] - $currentTime;
            $is_locked = true;
            $_SESSION['active_lockout_id'] = $student_id;
            $error = "Too many failed attempts. Try again in $remaining seconds.";
        } else {

            // CHECK CREDENTIALS
            $student = $collection->findOne(['student_id' => $student_id]);

            if ($student && password_verify($pin, $student['pin'])) {
                $valid = true;
                $student_name = $student['name'];
                $student_dept = $student['department'];
            }

            if ($valid) {
                // SUCCESSFUL LOGIN -> FULL RESET
                $lockoutCollection->deleteOne(['student_id' => $student_id]);
                unset($_SESSION['active_lockout_id']);

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
                // FAILED ATTEMPT LOGIC (FIXED GRADUATED TIERS)
                $current_level = isset($lockData['lockout_level']) ? (int)$lockData['lockout_level'] : 0;
                $current_attempts = isset($lockData['failed_attempts']) ? (int)$lockData['failed_attempts'] : 0;

                if ($current_level == 0) {
                    $current_attempts++;

                    if ($current_attempts >= 3) {
                        // 1ST PENALTY: 30 SECONDS
                        $lock_duration = 30;
                        $lockout_time = $currentTime + $lock_duration;
                        $remaining = $lock_duration;
                        $is_locked = true;
                        $_SESSION['active_lockout_id'] = $student_id;

                        $error = "Too many failed attempts. Account temporarily locked for 30 seconds.";

                        $lockoutCollection->updateOne(
                            ['student_id' => $student_id],
                            ['$set' => [
                                'failed_attempts' => $current_attempts,
                                'lockout_level' => 1,
                                'lockout_time' => $lockout_time
                            ]]
                        );
                    } else {
                        // ATTEMPT 1 & 2 WARNINGS
                        if ($current_attempts == 2) {
                            $error = "Invalid Student ID or password (Attempt 2 of 3). Having trouble? <a href='forgot-password.php' style='color: #002147; font-weight: bold;'>Reset your password</a> to avoid account lockout.";
                        } else {
                            $error = "Invalid Student ID or password (Attempt 1 of 3).";
                        }

                        $lockoutCollection->updateOne(
                            ['student_id' => $student_id],
                            ['$set' => ['failed_attempts' => $current_attempts]]
                        );
                    }
                } elseif ($current_level == 1) {
                    // 2ND PENALTY: 60 SECONDS
                    $lock_duration = 60;
                    $lockout_time = $currentTime + $lock_duration;
                    $remaining = $lock_duration;
                    $is_locked = true;
                    $_SESSION['active_lockout_id'] = $student_id;

                    $error = "Repeated failed attempts. Account locked for 60 seconds.";

                    $lockoutCollection->updateOne(
                        ['student_id' => $student_id],
                        ['$set' => ['lockout_level' => 2, 'lockout_time' => $lockout_time]]
                    );
                } elseif ($current_level == 2) {
                    // 3RD PENALTY: 120 SECONDS
                    $lock_duration = 120;
                    $lockout_time = $currentTime + $lock_duration;
                    $remaining = $lock_duration;
                    $is_locked = true;
                    $_SESSION['active_lockout_id'] = $student_id;

                    $error = "Multiple failed attempts. Account locked for 120 seconds.";

                    $lockoutCollection->updateOne(
                        ['student_id' => $student_id],
                        ['$set' => ['lockout_level' => 3, 'lockout_time' => $lockout_time]]
                    );
                } else {
                    // 4TH PENALTY: PERMANENT LOCK
                    $lockoutCollection->updateOne(
                        ['student_id' => $student_id],
                        ['$set' => ['is_permanent' => true]]
                    );
                    $is_permanent = true;
                    $is_locked = true;
                    $_SESSION['active_lockout_id'] = $student_id;
                    $error = "Account permanently locked due to multiple security violations. Contact Admin.";
                }

                $locked_student_id = $student_id;

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

    if ($is_locked) {
        header("Location: index.php?locked=" . urlencode($student_id));
    } else {
        header("Location: index.php");
    }
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
            <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">
                Student Access Pass
            </div>
        </div>

        <h2 style="font-size: 22px; margin-bottom: 15px; color: #002147; text-align: center;">
            Access Pass Login
        </h2>

        <!-- LOGIN POLICY BANNER -->
        <div style="font-size: 12px; padding: 10px; margin-bottom: 20px; background: #e8f4f8; border-left: 4px solid #002147; text-align: left; border-radius: 4px;">
            <strong>Login Policy:</strong>
            <ul style="margin: 4px 0 0 15px; padding: 0;">
                <li>Student ID format must be <code>XX-XXXXXX</code> (Digits only)[cite: 8].</li>
                <li>Maximum 3 failed attempts allows 30s lockout[cite: 8].</li>
            </ul>
        </div>

        <div id="error-container">
            <?php if ($error): ?>
                <p class='error' style='margin-bottom: 20px;'><?php echo $error; ?></p>
            <?php endif; ?>
        </div>

        <form method="POST" action="index.php">

            <input
                type="text"
                id="student_id"
                name="student_id"
                placeholder="Student ID (e.g. 25-266124)"
                maxlength="9"
                pattern="[0-9]{2}-[0-9]{6}"
                oninput="let v = this.value.replace(/\D/g, ''); if (v.length > 2) v = v.substring(0,2) + '-' + v.substring(2,8); this.value = v;"
                value="<?php echo htmlspecialchars($locked_student_id); ?>"
                <?php echo $is_locked ? 'disabled' : ''; ?>
                required
                style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;"
            >

            <input
                type="password"
                id="pin"
                name="pin"
                placeholder="Password"
                <?php echo $is_locked ? 'disabled' : ''; ?>
                required
                style="padding: 14px; font-size: 15px; margin-bottom: 20px; width: 100%; box-sizing: border-box;"
            >

            <button
                type="submit"
                id="login-btn"
                class="btn-login"
                style="padding: 14px; font-size: 16px; font-weight: bold; background: #002147; width: 100%; border: none; border-radius: 4px; color: white; cursor: pointer; <?php echo $is_locked ? 'opacity: 0.6;' : ''; ?>"
                <?php echo $is_locked ? 'disabled' : ''; ?>
            >
                <?php
                if ($is_permanent) {
                    echo "Permanently Locked";
                } elseif ($is_locked) {
                    echo "Locked (<span id='countdown-timer'>" . $remaining . "</span>s)";
                } else {
                    echo "Login";
                }
                ?>
            </button>

        </form>

        <div style="width: 340px; margin: 20px auto 0 auto; display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; align-items: center; font-size: 14px;">
            
            <div style="text-align: right;">
                <?php if ($is_locked): ?>
                    <span style="color: #999; cursor: not-allowed; text-decoration: none;">Register</span>
                <?php else: ?>
                    <a href="register.php" style="color: #002147; text-decoration: none;">Register</a>
                <?php endif; ?>
            </div>

            <div style="text-align: center; color: #ccc; padding: 0 8px;">|</div>

            <div style="text-align: center;">
                <?php if ($is_locked): ?>
                    <span style="color: #999; cursor: not-allowed; text-decoration: none;">Admin</span>
                <?php else: ?>
                    <a href="admin-login.php" style="color: #002147; text-decoration: none;">Admin</a>
                <?php endif; ?>
            </div>

            <div style="text-align: center; color: #ccc; padding: 0 8px;">|</div>

            <div style="text-align: left;">
                <a href="forgot-password.php" style="color: #002147; text-decoration: none; font-weight: bold;">
                    Forgot password?
                </a>
            </div>

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
