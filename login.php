<?php
    include 'config.php';
    if (isset($_SESSION['qa_user'])) {
        $_SESSION['toastr_message'] = "You are already logged in!";
        $_SESSION['toastr_type'] = "info";
        header("Location: settings.php");
        exit();
    }

    if (isset($_POST['login'])) {
        try {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            if (empty($email) || empty($password)) {
                $_SESSION['toastr_message'] = "Please provide both email and password.";
                $_SESSION['toastr_type'] = "error";
                header("Location: login.php");
                exit();
            }

            // 👇 expiry field bhi select kar liya
            $stmt = $con->prepare("SELECT u_id, u_password, u_status, u_name, u_expired_at FROM `users` WHERE u_email = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $fetchUser = $result->fetch_assoc();

                // Disabled account check
                if ($fetchUser['u_status'] == 0) {
                    $_SESSION['toastr_message'] = "Your Account has been Disabled by Admin!";
                    $_SESSION['toastr_type'] = "error";
                    header("Location: login.php");
                    exit();
                }

                // 👇 Expiry check
                $currentDateTime = date("Y-m-d H:i:s");
                if (!empty($fetchUser['u_expired_at']) && $fetchUser['u_expired_at'] < $currentDateTime) {
                    $_SESSION['toastr_message'] = "Your account has expired. Please renew your subscription.";
                    $_SESSION['toastr_type'] = "error";
                    header("Location: login.php");
                    exit();
                }

                $dbPassword = $fetchUser['u_password'];
                $isPasswordValid = false;

                // hashed password check
                if (strlen($dbPassword) > 20 && $dbPassword[0] === '$') {
                    if (password_verify($password, $dbPassword)) {
                        $isPasswordValid = true;
                    }
                } else {
                    // plain text fallback
                    if ($password === $dbPassword) {
                        $isPasswordValid = true;
                    }
                }

                if ($isPasswordValid) {
                    $_SESSION['qa_user'] = $fetchUser['u_id'];
                    $_SESSION['qa_user_name'] = $fetchUser['u_name'];

                    $_SESSION['toastr_message'] = "Login Successful! Welcome, " . htmlspecialchars($fetchUser['u_name'], ENT_QUOTES, 'UTF-8') . "!";
                    $_SESSION['toastr_type'] = "success";

                    header("Location: settings.php");
                    exit();
                } else {
                    $_SESSION['toastr_message'] = "Invalid Credentials!";
                    $_SESSION['toastr_type'] = "error";
                    header("Location: login.php");
                    exit();
                }
            } else {
                $_SESSION['toastr_message'] = "Invalid Credentials!";
                $_SESSION['toastr_type'] = "error";
                header("Location: login.php");
                exit();
            }

        } catch (Exception $e) {
            $_SESSION['toastr_message'] = "Something went wrong: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $_SESSION['toastr_type'] = "error";
            header("Location: login.php");
            exit();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login | <?= isset($websiteName) ? htmlspecialchars($websiteName, ENT_QUOTES, 'UTF-8') : 'Website' ?></title>
    <?php include 'header-files.php' ?>
</head>
<body class="account-page">
    <div class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper">
                <div class="login-content">
                    <form class="login-userset" method="post" autocomplete="off">
                        <div class="login-userheading">
                            <h3>May Your Day be Happy Ahead!</h3>
                            <h4>Please login to your account</h4>
                        </div>
                        <div class="form-login">
                            <label>Email</label>
                            <div class="form-addons">
                                <input required type="email" name="email" placeholder="Enter your email address" value="<?= isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '' ?>">
                                <img src="assets/img/icons/mail.svg" alt="img">
                            </div>
                        </div>
                        <div class="form-login">
                            <label>Password</label>
                            <div class="pass-group">
                                <input required type="password" name="password" class="pass-input" placeholder="Enter your password">
                                <span class="fas toggle-password fa-eye-slash"></span>
                            </div>
                        </div>
                        <p>Don't Have An Account? <a href="register.php">Register Now</a></p>
                        <div class="form-login">
                            <button class="btn btn-login" type="submit" name="login">Login</button>
                        </div>
                    </form>
                </div>
                <div class="login-img">
                    <img src="assets/img/login.jpg" alt="img">
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
include 'footer-files.php';
?>
