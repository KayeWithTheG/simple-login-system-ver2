<?php
session_start();
if (!isset($_SESSION["student_id"])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Student Access Pass</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="box" style="width: 520px; padding: 40px; text-align: left;">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="Logo.png" alt="JRU Logo" style="width: 75px; height: auto;">
      <div style="font-weight: bold; color: #002147; font-size: 16px; margin-top: 6px;">Student Access Pass</div>
    </div>
    
    <h2 style="font-size: 22px; margin-bottom: 8px; line-height: 1.3; text-align: center;">Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?>!</h2>
    <p class="slogan" style="font-size: 14px; text-align: center; color: #d4af37; font-style: italic; margin-top: 4px; margin-bottom: 25px;">Find your Inner Hero</p>
    
    <div class="account-info" style="padding: 18px; margin-bottom: 25px; text-align: left;">
      <p style="font-size: 15px; margin: 6px 0;"><strong>Student ID:</strong> <?php echo htmlspecialchars($_SESSION["student_id"]); ?></p>
      <p style="font-size: 15px; margin: 6px 0;"><strong>Department:</strong> <?php echo htmlspecialchars($_SESSION["department"]); ?></p>
      <p style="font-size: 15px; margin: 6px 0;"><strong>Account Status:</strong> <span style="color: #2f855a; font-weight: bold;">Active</span></p>
    </div>
    
    <div>
      <a href="logout.php" class="btn-logout" style="display: block; text-align: center; background: #c53030; color: #fff; padding: 14px; border-radius: 4px; text-decoration: none; font-size: 16px; font-weight: bold;">Logout</a>
    </div>
  </div>
</body>
</html>


