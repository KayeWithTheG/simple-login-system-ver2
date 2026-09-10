<?php
require_once 'db.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = trim(htmlspecialchars($_POST["student_id"]));
    $name = trim(htmlspecialchars($_POST["name"]));
    $department = trim(htmlspecialchars($_POST["department"]));
    $password = trim($_POST["password"]);

    // Validate Student ID length (must be exactly 9 characters)
    if (strlen($student_id) !== 9) {
        $error = "Student ID must be exactly 9 characters (e.g., 25-266124).";
    } 
    // Validate minimum password length
    else if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Tignan kung existing na ang student ID sa MongoDB collection
        $existing = $collection->findOne(['student_id' => $student_id]);

        if ($existing) {
            $error = "Student ID is already registered.";
        } else {
            // I-hash ang password bago i-save para secure
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $collection->insertOne([
                'student_id' => $student_id,
                'name' => $name,
                'department' => $department,
                'pin' => $hashed_password,
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
  </script>
</head>
<body>
  <div class="box" style="width: 520px; padding: 40px;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="JRU Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">Student Access Pass</div>
    </div>
    <h2 style="font-size: 22px; margin-bottom: 20px; color: #002147;">Student Registration</h2>
    
    <?php if ($error) { echo "<p class='error' style='margin-bottom: 20px;'>" . $error . "</p>"; } ?>
    <?php if ($success) { echo "<p style='color: green; background: #e6fffa; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px;'>" . $success . "</p>"; } ?>
    
    <form method="POST" action="register.php">
      <input type="text" name="student_id" placeholder="Student ID (e.g. 25-266124)" maxlength="9" oninput="if(this.value.length > 9) this.value = this.value.slice(0, 9);" required style="padding: 14px; font-size: 15px; margin-bottom: 15px;">
      <input type="text" name="name" placeholder="Full Name" required style="padding: 14px; font-size: 15px; margin-bottom: 15px;">
      <input type="text" name="department" placeholder="Department" required style="padding: 14px; font-size: 15px; margin-bottom: 15px;">
      
      <input type="password" id="password" name="password" placeholder="Password (Min. 8 characters)" required oninput="checkPasswordStrength()" style="padding: 14px; font-size: 15px; margin-bottom: 5px;">
      <div id="strength-text" style="font-size: 12px; font-weight: bold; text-align: left; margin-bottom: 15px;"></div>

      <button type="submit" class="btn-register" style="padding: 14px; font-size: 16px; font-weight: bold; background: #d4af37; color: #002147;">Register Account</button>
    </form>
    
    <a class="link" href="index.php" style="margin-top: 20px; font-size: 14px;">Already have an access pass? Login</a>
  </div>
</body>
</html>