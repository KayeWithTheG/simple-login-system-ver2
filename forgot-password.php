<?php
session_start();
require_once 'db.php';

$error = "";
$success = "";

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = trim(htmlspecialchars($_POST["student_id"]));
    $new_pin = trim($_POST["pin"]);
    $confirm_pin = trim($_POST["confirm_pin"]);

    if (strlen($student_id) !== 9) {
        $error = "Student ID must be exactly 9 characters (e.g., 25-266124).";
    } elseif (empty($new_pin) || empty($confirm_pin)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_pin !== $confirm_pin) {
        $error = "Passwords do not match.";
    } else {
        $student = $collection->findOne(['student_id' => $student_id]);

        if ($student) {
            $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);

            $collection->updateOne(
                ['student_id' => $student_id],
                ['$set' => ['pin' => $hashed_pin]]
            );

            $_SESSION['flash_success'] = "Password successfully updated! You can now log in with your new password.";
            header("Location: forgot-password.php");
            exit;
        } else {
            $error = "Student ID not found in the database.";
        }
    }

    $_SESSION['flash_error'] = $error;
    header("Location: forgot-password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - Student Access Pass</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .strength-indicator {
      font-size: 13px;
      margin-top: 6px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .dot {
      height: 10px;
      width: 10px;
      background-color: #bbb;
      border-radius: 50%;
      display: inline-block;
    }
  </style>
</head>
<body>
  <div class="box" style="width: 520px; padding: 40px;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="JRU Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">Student Access Pass</div>
    </div>
    <h2 style="font-size: 22px; margin-bottom: 20px; color: #002147; text-align: center;">Reset Password</h2>

    <div id="error-container">
      <?php if ($error) { echo "<p class='error' style='margin-bottom: 20px; color: #c53030;'>" . $error . "</p>"; } ?>
      <?php if ($success) { echo "<p style='margin-bottom: 20px; color: #2f855a; font-weight: bold;'>" . $success . "</p>"; } ?>
    </div>

    <form method="POST" action="forgot-password.php">
      <input type="text" name="student_id" placeholder="Student ID (e.g. 25-266124)" maxlength="9" required style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;">
      
      <input type="password" id="pin" name="pin" placeholder="Enter New Password" required style="padding: 14px; font-size: 15px; margin-bottom: 5px; width: 100%; box-sizing: border-box;" oninput="checkStrength(this.value)">
      
      <!-- Strength Indicator with Dot -->
      <div class="strength-indicator" id="strengthContainer" style="display: none;">
        <span id="strengthText">Strength: Weak</span>
        <span class="dot" id="strengthDot"></span>
      </div>

      <input type="password" name="confirm_pin" placeholder="Confirm New Password" required style="padding: 14px; font-size: 15px; margin-bottom: 20px; width: 100%; box-sizing: border-box;">
      
      <button type="submit" class="btn-login" style="padding: 14px; font-size: 16px; font-weight: bold; background: #002147; width: 100%; color: white; border: none; border-radius: 4px; cursor: pointer;">Update Password</button>
    </form>

    <div style="text-align: center; margin-top: 20px;">
      <a class="link" href="index.php" style="font-size: 14px; color: #002147; text-decoration: none;">Back to Student Login</a>
    </div>
  </div>

  <script>
    function checkStrength(pass) {
      const container = document.getElementById('strengthContainer');
      const text = document.getElementById('strengthText');
      const dot = document.getElementById('strengthDot');

      if (pass.length === 0) {
        container.style.display = "none";
        return;
      }

      container.style.display = "flex";

      if (pass.length < 6) {
        text.textContent = "Strength: Weak";
        text.style.color = "#c53030";
        dot.style.backgroundColor = "#c53030";
      } else if (pass.length < 10) {
        text.textContent = "Strength: Medium";
        text.style.color = "#d69e2e";
        dot.style.backgroundColor = "#d69e2e";
      } else {
        text.textContent = "Strength: Strong";
        text.style.color = "#2f855a";
        dot.style.backgroundColor = "#2f855a";
      }
    }
  </script>
</body>
</html>