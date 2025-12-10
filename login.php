<?php
require 'db.php';
require 'functions.php';

$msg = '';

// FIX: Start session only if one isn't already active (prevents the Notice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  if (!$email || !$pass) {
    $msg = "Please enter email and password.";
  } else {
    $q = $conn->prepare("SELECT id, first_name, last_name, email, phone, password, role FROM users WHERE email=?");
    $q->bind_param("s", $email);
    $q->execute();
    $res = $q->get_result();
    if ($u = $res->fetch_assoc()) {
      if (password_verify($pass, $u['password']) || $u['password']===$pass) { // dev fallback
        $_SESSION['user'] = [
          'id'=>$u['id'],'first_name'=>$u['first_name'],'last_name'=>$u['last_name'],
          'email'=>$u['email'],'phone'=>$u['phone'],'role'=>$u['role']
        ];
        if ($u['role']==='Customer') header("Location: customer.php");
        elseif ($u['role']==='Technician') header("Location: technician.php");
        else header("Location: admin.php");
        exit;
      } else { $msg = "Incorrect password."; }
    } else { $msg = "No account found for this email."; }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login</title>
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

        /* Left Panel - Sign In Form (Blue/Main Color) */
        .signin-panel {
            flex: 2; /* Takes up more space */
            background-color: #e0f2ff; /* Light Blue Color */
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .signin-panel h2 {
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
        
        .form-input::placeholder {
            color: #aaa;
            opacity: 1; 
        }

        /* Submit Button Styling (Sign In) */
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
        .signup-panel {
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

        .signup-panel h3 {
            font-size: 20px;
            font-weight: normal;
            margin-bottom: 10px;
        }

        .signup-panel p {
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Sign Up Button (Light Outline Style) */
        .signup-btn {
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

        .signup-btn:hover {
            background-color: white;
            color: #b0b0b0;
        }
        
        /* Message Display */
        .message-box {
            width: 100%;
            max-width: 300px;
            background: #ffe9e9;
            color: #8a1f1f;
            border: 1px solid #ffc9c9;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: left;
        }

    </style>
</head>
<body>

<div class="auth-card-container">
    <div class="signin-panel">
        <h2>LOGIN</h2>

        <?php if($msg): ?><div class="message-box"><?=htmlspecialchars($msg)?></div><?php endif; ?>

        <form method="post" style="width: 100%; max-width: 300px;" novalidate>
            <div class="form-group">
                <input class="form-input" type="email" name="email" placeholder="EMAIL ADDRESS" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
            </div>
            
            <div class="form-group">
                <input class="form-input" type="password" name="password" placeholder="PASSWORD" required>
            </div>

            <button class="submit-btn" type="submit">LOGIN</button>
        </form>
    </div>

    <div class="signup-panel">
        <h3>New here?</h3>
        <p>Sign up and discover</p>
        <a href="signup.php" class="signup-btn">SIGN UP</a>
    </div>
</div>

</body>
</html>