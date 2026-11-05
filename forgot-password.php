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

    // 1. STRICT VALIDATION: Student ID must be XX-XXXXXX
    if (!preg_match('/^\d{2}-\d{6}$/', $student_id)) {
        $error = "Student ID must follow format XX-XXXXXX with numbers only (e.g., 25-266124).";
    } elseif (empty($new_pin) || empty($confirm_pin)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_pin !== $confirm_pin) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pin) < 8) {
        $error = "New password must be at least 8 characters long.";
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
    .pw-wrap {
      position: relative;
      width: 100%;
    }
    .pw-wrap input {
      padding-right: 46px !important;
    }
    .toggle-pass {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      padding: 4px;
      cursor: pointer;
      color: #666;
      display: flex;
      align-items: center;
    }
    .toggle-pass:hover {
      color: #002147;
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
      <?php if ($error) { echo "<p class='error' style='margin-bottom: 20px; color: #c53030; text-align: center;'>" . $error . "</p>"; } ?>
      <?php if ($success) { echo "<p style='margin-bottom: 20px; color: #2f855a; font-weight: bold; text-align: center; background: #e6fffa; padding: 10px; border-radius: 4px;'>" . $success . "</p>"; } ?>
    </div>

    <form method="POST" action="forgot-password.php">

      <!-- Student ID with Auto-Hyphenation (XX-XXXXXX) -->
      <input
        type="text"
        name="student_id"
        placeholder="Student ID (e.g. 25-266124)"
        maxlength="9"
        pattern="[0-9]{2}-[0-9]{6}"
        oninput="let v = this.value.replace(/\D/g, ''); if (v.length > 2) v = v.substring(0,2) + '-' + v.substring(2,8); this.value = v;"
        required
        style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;"
      >

      <!-- New Password -->
      <div class="pw-wrap" style="margin-bottom: 5px;">
        <input
          type="password"
          id="pin"
          name="pin"
          placeholder="Enter New Password (Min. 8 characters)"
          required
          style="padding: 14px; font-size: 15px; margin-bottom: 0; width: 100%; box-sizing: border-box;"
          oninput="checkStrength(this.value)"
        >
        <button type="button" class="toggle-pass" data-target="pin" title="Show password" aria-label="Show password">
          <svg class="eye-open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
          <svg class="eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
          </svg>
        </button>
      </div>

      <!-- Strength Indicator with Dot -->
      <div class="strength-indicator" id="strengthContainer" style="display: none;">
        <span id="strengthText">Strength: Weak</span>
        <span class="dot" id="strengthDot"></span>
      </div>

      <!-- Confirm Password -->
      <div class="pw-wrap" style="margin-bottom: 20px;">
        <input
          type="password"
          id="confirm_pin"
          name="confirm_pin"
          placeholder="Confirm New Password"
          required
          style="padding: 14px; font-size: 15px; margin-bottom: 0; width: 100%; box-sizing: border-box;"
        >
        <button type="button" class="toggle-pass" data-target="confirm_pin" title="Show password" aria-label="Show password">
          <svg class="eye-open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
          <svg class="eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
          </svg>
        </button>
      </div>

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

    // Show / hide password toggle (works for both password fields)
    document.querySelectorAll(".toggle-pass").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const input = document.getElementById(btn.dataset.target);
        const showing = input.type === "password";
        input.type = showing ? "text" : "password";

        btn.title = showing ? "Hide password" : "Show password";
        btn.setAttribute("aria-label", btn.title);
        btn.querySelector(".eye-open").style.display = showing ? "none" : "";
        btn.querySelector(".eye-off").style.display = showing ? "" : "none";
      });
    });
  </script>
</body>
</html>
