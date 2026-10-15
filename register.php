<?php
require_once 'db.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $student_id = trim($_POST["student_id"] ?? "");
    $name       = trim($_POST["name"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $password   = trim($_POST["password"] ?? "");

    // 1. Student ID must be XX-XXXXXX (numbers only)
    if (!preg_match('/^\d{2}-\d{6}$/', $student_id)) {
        $error = "Student ID must follow format XX-XXXXXX with numbers only (e.g., 25-266124).";
    }
    // 2. Name: letters, spaces, period, hyphen lang (NO numbers)
    else if (!preg_match("/^[A-Za-z\s.\-]+$/", $name)) {
        $error = "Full Name must contain letters and spaces only (no numbers allowed).";
    }
    // 3. Minimum password length
    else if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $existing = $collection->findOne(['student_id' => $student_id]);

        if ($existing) {
            $error = "Student ID is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $collection->insertOne([
                'student_id' => $student_id,
                'name'       => $name,
                'department' => $department,
                'pin'        => $hashed_password,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $success = "Registration successful! You can now log in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Student Access Pass</title>
  <link rel="stylesheet" href="style.css">
  <script>
    function checkPasswordStrength() {
      let pwd = document.getElementById("password").value;
      let strengthText = document.getElementById("strength-text");
      let strength = 0;

      if (pwd.length >= 8) strength++;
      if (/[A-Z]/.test(pwd)) strength++;
      if (/[0-9]/.test(pwd)) strength++;
      if (/[\W]/.test(pwd)) strength++;

      if (pwd.length === 0) {
        strengthText.innerText = "";
      } else if (strength <= 2) {
        strengthText.innerText = "Strength: Weak 🔴";
        strengthText.style.color = "red";
      } else if (strength === 3) {
        strengthText.innerText = "Strength: Medium 🟡";
        strengthText.style.color = "orange";
      } else {
        strengthText.innerText = "Strength: Strong 🟢";
        strengthText.style.color = "green";
      }
    }

    document.addEventListener("DOMContentLoaded", function () {
      // Student ID: digits only + auto hyphen (XX-XXXXXX)
      const idInput = document.getElementById("studentId");
      idInput.addEventListener("input", function () {
        let v = this.value.replace(/\D/g, "");
        if (v.length > 2) v = v.substring(0, 2) + "-" + v.substring(2, 8);
        this.value = v;
      });

      // Full Name: bawal ang numbers/symbols (typing, paste, autofill)
      const nameInput = document.getElementById("fullName");
      nameInput.addEventListener("input", function () {
        this.value = this.value.replace(/[^A-Za-z\s.\-]/g, "");
      });
      nameInput.addEventListener("keypress", function (e) {
        if (/[0-9]/.test(e.key)) e.preventDefault();
      });
    });
  </script>
</head>
<body>
  <div class="box" style="width: 520px; padding: 40px;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="JRU Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">Student Access Pass</div>
    </div>
    <h2 style="font-size: 22px; margin-bottom: 15px; color: #002147; text-align: center;">Student Registration</h2>

    <!-- REGISTRATION GUIDELINES BANNER -->
    <div style="font-size: 12px; padding: 10px; margin-bottom: 20px; background: #e8f4f8; border-left: 4px solid #002147; text-align: left; border-radius: 4px;">
        <strong>Registration Guidelines:</strong>
        <ul style="margin: 4px 0 0 15px; padding: 0;">
            <li><strong>Student ID:</strong> Format must be <code>XX-XXXXXX</code> (Numbers only).</li>
            <li><strong>Full Name:</strong> Alphabetical characters and spaces only (No numbers allowed).</li>
            <li><strong>Password:</strong> Minimum of 8 characters.</li>
        </ul>
    </div>

    <?php if ($error) { echo "<p class='error' style='margin-bottom: 20px;'>" . htmlspecialchars($error) . "</p>"; } ?>
    <?php if ($success) { echo "<p style='color: green; background: #e6fffa; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; text-align: center;'>" . htmlspecialchars($success) . "</p>"; } ?>

    <form method="POST" action="register.php">

      <!-- Student ID -->
      <input
        type="text"
        id="studentId"
        name="student_id"
        placeholder="Student ID (e.g. 25-266124)"
        maxlength="9"
        pattern="[0-9]{2}-[0-9]{6}"
        title="Format: XX-XXXXXX (numbers only)"
        required
        style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;"
      >

      <!-- Full Name: letters, spaces, period, hyphen only -->
      <input
        type="text"
        id="fullName"
        name="name"
        placeholder="Full Name "
        pattern="[A-Za-z\s.\-]+"
        title="Letters, spaces, periods and hyphens only. No numbers."
        required
        style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;"
      >

      <!-- Department -->
      <input
        type="text"
        name="department"
        placeholder="Department"
        required
        style="padding: 14px; font-size: 15px; margin-bottom: 15px; width: 100%; box-sizing: border-box;"
      >

      <!-- Password -->
      <input
        type="password"
        id="password"
        name="password"
        placeholder="Password (Min. 8 characters)"
        required
        oninput="checkPasswordStrength()"
        style="padding: 14px; font-size: 15px; margin-bottom: 5px; width: 100%; box-sizing: border-box;"
      >

      <div id="strength-text" style="font-size: 12px; font-weight: bold; text-align: left; margin-bottom: 15px;"></div>

      <button type="submit" class="btn-register" style="padding: 14px; font-size: 16px; font-weight: bold; background: #d4af37; color: #002147; width: 100%; border: none; border-radius: 4px; cursor: pointer;">Register Account</button>
    </form>

    <a class="link" href="index.php" style="margin-top: 20px; font-size: 14px; display: block; text-align: center; color: #002147; text-decoration: none;">Already have an access pass? Login</a>
  </div>
</body>
</html>
