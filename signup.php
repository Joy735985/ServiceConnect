<?php
require 'db.php';
require 'functions.php';

$msg = '';
// Variables to hold submitted values (for stickiness)
$first = ''; $last = ''; $email = ''; $phone = '';

// Prefill role from query (?role=customer|technician)
$prefillRole = '';
if (isset($_GET['role'])) {
  $r = strtolower(trim($_GET['role']));
  if ($r === 'customer')   $prefillRole = 'Customer';
  if ($r === 'technician') $prefillRole = 'Technician';
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $first = trim($_POST['firstName']??'');
  $last  = trim($_POST['lastName']??'');
  $email = trim($_POST['email']??'');
  $phone = trim($_POST['phone']??'');
  $pass  = $_POST['password']??'';
  $cpass = $_POST['confirm']??'';
  $role  = $_POST['role']??'';

  if(!$first||!$last||!$email||!$phone||!$pass||!$cpass||!$role){
    $msg = "All fields are required.";
  } elseif($pass!==$cpass){
    $msg = "Passwords do not match.";
  } elseif(strlen($pass) < 8){ // <<< Minimum password length check
    $msg = "Password must be at least 8 characters long.";
  } else {
    // ✅ Only block duplicates for the SAME ROLE (allow same email for the other role)
    $q = $conn->prepare("SELECT id FROM users WHERE email=? AND role=?");
    $q->bind_param("ss",$email,$role);
    $q->execute(); 
    $q->store_result();
    if($q->num_rows>0){ 
      $msg="This email is already registered for this role."; 
    } else {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $ins = $conn->prepare("INSERT INTO users(first_name,last_name,email,phone,password,role) VALUES(?,?,?,?,?,?)");
      $ins->bind_param("ssssss",$first,$last,$email,$phone,$hash,$role);
      if($ins->execute()){
        $msg="Account created! Please login.";
        header("Refresh:1; url=login.php");
      }else {
        $msg="Something went wrong. Try again.";
      }
    }
  }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up</title>
    <style>
        /* General Reset and Font */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f9;
        }

        /* Container for the Card */
        .auth-card-container {
            width: 700px; /* Overall width */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            background-color: #fff;
        }

        /* Left Panel - Sign Up Form (Blue/Main Color) */
        .signup-panel {
            flex: 2; /* Takes up more space */
            background-color: #e0f2ff; /* Light Blue Color */
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .signup-panel h2 {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
        }

        /* Form Styling */
        .form-group {
            width: 100%;
            margin-bottom: 25px;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 10px 0;
            border: none;
            border-bottom: 1px solid #aaa;
            background: transparent;
            font-size: 16px;
            color: #333;
            outline: none;
            text-align: center;
        }
        
        /* The lines are below the text fields, similar to the GIF */
        .form-input::placeholder {
            color: #aaa;
            opacity: 1; 
        }

        /* Role Select Dropdown Styling */
        .form-select {
            width: 100%;
            padding: 10px 0;
            border: none;
            border-bottom: 1px solid #aaa;
            background: transparent;
            font-size: 16px;
            color: #333;
            outline: none;
            text-align: center;
            margin-top: 10px;
            cursor: pointer;
        }
        
        .form-select option {
            color: #333;
        }

        /* Submit Button Styling */
        .submit-btn {
            width: 80%;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            background-color: #6c757d; /* Darker Gray */
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background-color: #5a6268;
        }

        /* Right Panel - Secondary Action (Gray Color) */
        .signin-panel {
            flex: 1; /* Takes up less space */
            background-color: #b0b0b0; /* Gray Color */
            color: white;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .signin-panel h3 {
            font-size: 20px;
            font-weight: normal;
            margin-bottom: 10px;
        }

        .signin-panel p {
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Sign In Button (Light Outline Style) */
        .signin-btn {
            padding: 8px 30px;
            border: 2px solid white;
            background: transparent;
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
            display: inline-block;
        }

        .signin-btn:hover {
            background-color: white;
            color: #b0b0b0;
        }

        /* Name Row */
        .form-row {
            display: flex;
            gap: 20px;
            width: 100%;
        }
        .form-row > .form-group {
            flex: 1;
        }

        /* Message Display */
        .message-box {
            width: 100%;
            max-width: 300px;
            background: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: left;
        }

    </style>
</head>
<body>

<div class="auth-card-container">
    <div class="signup-panel">
        <h2>Sign Up</h2>
        
        <?php if($msg): ?><div class="message-box"><?=htmlspecialchars($msg)?></div><?php endif; ?>

        <form method="post" style="width: 100%; max-width: 300px;" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <input class="form-input" type="text" name="firstName" placeholder="FIRST NAME" value="<?=htmlspecialchars($first)?>" required>
                </div>
                <div class="form-group">
                    <input class="form-input" type="text" name="lastName" placeholder="LAST NAME" value="<?=htmlspecialchars($last)?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <input class="form-input" type="email" name="email" placeholder="EMAIL ADDRESS" value="<?=htmlspecialchars($email)?>" required>
            </div>
            
            <div class="form-group">
                <input class="form-input" type="text" name="phone" placeholder="PHONE NUMBER" value="<?=htmlspecialchars($phone)?>" required>
            </div>
            
            <div class="form-group">
                <input class="form-input" type="password" name="password" placeholder="PASSWORD" required>
            </div>
            <div class="form-group">
                <input class="form-input" type="password" name="confirm" placeholder="CONFIRM PASSWORD" required>
            </div>
            
            <div class="form-group">
                <select name="role" class="form-select" required>
                  <option value="" <?=!$prefillRole?'selected':''?>>SELECT ROLE</option>
                  <option value="Customer"   <?= $prefillRole==='Customer'?'selected':''; ?>>Customer</option>
                  <option value="Technician" <?= $prefillRole==='Technician'?'selected':''; ?>>Technician</option>
                </select>
            </div>
            
            <button class="submit-btn" type="submit">SIGN UP</button>
        </form>
    </div>

    <div class="signin-panel">
        <h3>Already have an account?</h3>
        <p>Welcome back! Login to continue</p>
        <a href="login.php" class="signin-btn">LOGIN</a>
    </div>
</div>

</body>
</html>