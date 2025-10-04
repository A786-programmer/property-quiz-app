<?php
    include 'config.php';

    if (isset($_SESSION['qa_user'])) {
        $_SESSION['toastr_message'] = "You are already logged in!";
        $_SESSION['toastr_type'] = "info";
        header("Location: index.php");
        exit();
    }

    $packageAmount = [
        'Basic'     => 30,
        'Silver'    => 5000,
        'Gold'      => 10000,
        'Platinum'  => 20000
    ];

    // Package expiry duration in months (0 for basic which will be 24 hours)
    $packageExpiry = [
        'Basic'     => 0, // 24 hours
        'Silver'    => 3, // 3 months
        'Gold'      => 6, // 6 months
        'Platinum'  => 9  // 9 months
    ];

    if (isset($_POST['register'])) {
        try {
            $_SESSION['password'] = $_POST['password'];
            $_SESSION['email'] = $_POST['email'];
            $_SESSION['package_type'] = $_POST['package_type'];
            $_SESSION['payment_method'] = $_POST['payment_method'];
            $_SESSION['hashcode'] = hash('md5', $_SESSION['email'].'-quiz-app');

            $checkUser = mysqli_query($con,"SELECT * FROM `users` WHERE u_email = '{$_SESSION['email']}'");

            if (mysqli_num_rows($checkUser) > 0) {
                unset($_SESSION['password'], $_SESSION['email'], $_SESSION['package_type'], $_SESSION['payment_method']);
                $_SESSION['toastr_message'] = "Your selected email is already registered!";
                $_SESSION['toastr_type'] = "warning";
                header("Location: register.php");
                exit();
            }

            $_SESSION['name'] = $_POST['name'];
            $_SESSION['phone'] = $_POST['phone'];

            // Amount Calculation based on Package Type
            $totalAmount = $packageAmount[$_SESSION['package_type']];
            $item_name = ucfirst($_SESSION['package_type'])." Package";

            // Payment Method Selection
            if ($_SESSION['payment_method'] == 'paypal') {
                // PayPal Integration
                $paypalURL = "https://www.sandbox.paypal.com/cgi-bin/webscr";
                $query = http_build_query([
                    'business'      => $paypalKey,
                    'item_name'     => $item_name,
                    'amount'        => $totalAmount,
                    'currency_code' => 'CAD',
                    'no_shipping'   => 1,
                    'cmd'           => '_xclick',
                    'return'        => $url . 'register.php?success=1&method=paypal',
                    'cancel_return' => $url . 'register.php?cancel=1&method=paypal',
                ]);

                header("Location: $paypalURL?$query");
                exit();

            } else if ($_SESSION['payment_method'] == 'stripe') {
                // Stripe Integration
                require_once 'vendor/autoload.php';
                
                \Stripe\Stripe::setApiKey($stripeSecretKey);
                
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'cad',
                            'product_data' => [
                                'name' => $item_name,
                            ],
                            'unit_amount' => $totalAmount * 100,
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => $url . 'register.php?success=1&method=stripe&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $url . 'register.php?cancel=1&method=stripe',
                    'metadata' => [
                        'user_email' => $_SESSION['email'],
                        'package_type' => $_SESSION['package_type']
                    ]
                ]);

                header("Location: " . $session->url);
                exit();
            }

        } catch (Exception $e) {
            $_SESSION['toastr_message'] = "An Error Occurred: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $_SESSION['toastr_type'] = "error";
            header("Location: register.php");
            exit();
        }
    }

    // Function to calculate expiry date based on package type
    function calculateExpiryDate($packageType) {
        $currentDateTime = date("Y-m-d H:i:s");
        
        switch($packageType) {
            case 'Basic':
                // 24 hours from now
                return date("Y-m-d H:i:s", strtotime('+24 hours'));
            case 'Silver':
                // 3 months from now
                return date("Y-m-d H:i:s", strtotime('+3 months'));
            case 'Gold':
                // 6 months from now
                return date("Y-m-d H:i:s", strtotime('+6 months'));
            case 'Platinum':
                // 9 months from now
                return date("Y-m-d H:i:s", strtotime('+9 months'));
            default:
                return date("Y-m-d H:i:s", strtotime('+24 hours'));
        }
    }

    // PayPal Success Handler
    if (isset($_GET['tx']) && $_GET['st'] == 'Completed' && isset($_GET['method']) && $_GET['method'] == 'paypal') {
        $type = $_SESSION['package_type'];
        $amount = $packageAmount[$type];
        $transaction_id = $_GET['tx'];
        $payer_email = $_GET['payer_email'];
        $payer_name = $_GET['first_name'] . ' ' . $_GET['last_name'];
        $currentDateTime = date("Y-m-d H:i:s");
        $expiryDate = calculateExpiryDate($type);
      
        $query = "INSERT INTO `users`(u_name, u_email, u_password, u_package_type, u_registered_at, u_expired_at, u_is_expired, u_role, u_status, u_quiz_created) 
        VALUES ('{$_SESSION['name']}','{$_SESSION['email']}','{$_SESSION['password']}','$type','$currentDateTime','$expiryDate','0','Landlord','1','0')";
        mysqli_query($con, $query) or die(mysqli_error($con));
        $userId = $con->insert_id;

        $_SESSION['qa_user'] = $userId;

        mysqli_query($con, "INSERT INTO `payments`(p_payment, p_transaction_id, p_channel, p_payer_email, p_payer_name, p_user_id, p_paid_at)
        VALUES ('$amount','$transaction_id','PAYPAL','$payer_email','$payer_name','$userId','$currentDateTime')");

        $_SESSION['toastr_message'] = "Payment Successful! Welcome, {$_SESSION['name']}! Your package expires on " . date('d M, Y h:i A', strtotime($expiryDate));
        $_SESSION['toastr_type'] = "success";

        // Clear session data
        unset($_SESSION['password'], $_SESSION['email'], $_SESSION['package_type'], $_SESSION['payment_method'], $_SESSION['name'], $_SESSION['phone']);

        header("Location: index.php");
        exit();
    }

    // Stripe Success Handler
    if (isset($_GET['method']) && $_GET['method'] == 'stripe' && isset($_GET['session_id'])) {
        try {
            require_once 'vendor/autoload.php';
            \Stripe\Stripe::setApiKey($stripeSecretKey);
            
            $session = \Stripe\Checkout\Session::retrieve($_GET['session_id']);
            $payment_intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
            
            $type = $_SESSION['package_type'];
            $amount = $packageAmount[$type];
            $transaction_id = $payment_intent->id;
            $payer_email = $session->customer_details->email;
            $payer_name = $session->customer_details->name;
            $currentDateTime = date("Y-m-d H:i:s");
            $expiryDate = calculateExpiryDate($type);

            $query = "INSERT INTO `users`(u_name, u_email, u_password, u_package_type, u_registered_at, u_expired_at, u_is_expired, u_role, u_status, u_quiz_created) 
            VALUES ('{$_SESSION['name']}','{$_SESSION['email']}','{$_SESSION['password']}','$type','$currentDateTime','$expiryDate','0','Landlord','1','0')";

            mysqli_query($con, $query) or die(mysqli_error($con));
            $userId = $con->insert_id;

            $_SESSION['qa_user'] = $userId;  

            mysqli_query($con, "INSERT INTO `payments`(p_payment, p_transaction_id, p_channel, p_payer_email, p_payer_name, p_table, p_table_id, p_user_id, p_paid_at)
            VALUES ('$amount','$transaction_id','STRIPE','$payer_email','$payer_name','Basic','$userId','$userId','$currentDateTime')");

            $_SESSION['toastr_message'] = "Payment Successful! Welcome, {$_SESSION['name']}! Your package expires on " . date('d M, Y h:i A', strtotime($expiryDate));
            $_SESSION['toastr_type'] = "success";

            // Clear session data
            unset($_SESSION['password'], $_SESSION['email'], $_SESSION['package_type'], $_SESSION['payment_method'], $_SESSION['name'], $_SESSION['phone']);

            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['toastr_message'] = "Stripe Payment Error: " . $e->getMessage();
            $_SESSION['toastr_type'] = "error";
            header("Location: register.php");
            exit();
        }
    }

    if (isset($_GET['cancel'])) {
        $method = isset($_GET['method']) ? $_GET['method'] : 'payment';
        $_SESSION['toastr_message'] = ucfirst($method) . " Payment Cancelled!";
        $_SESSION['toastr_type'] = "warning";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register | <?= $websiteName ?></title>
    <?php include 'header-files.php' ?>
    <style>
        .payment-option input[type="radio"] {
            display: none;
        }

        .payment-option label {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            transition: 0.3s;
        }

        .payment-option input[type="radio"]:checked + label {
            border-color: #007bff;
            background: #f0f8ff;
            color: #007bff;
            font-weight: bold;
        }
        
        .package-info {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body class="account-page">
    <div class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper">
                <div class="login-content">
                    <form class="login-userset" method="post">
                        <div class="login-userheading">
                            <h3>May Your Day be Happy Ahead!</h3>
                            <h4>Please register your account</h4>
                        </div>

                        <div class="form-login">
                            <label>Full Name</label>
                            <div class="form-addons">
                                <input required type="text" name="name" placeholder="Enter your full name">
                                <img src="assets/img/icons/user.svg" alt="img">
                            </div>
                        </div>

                        <div class="form-login">
                            <label>Email</label>
                            <div class="form-addons">
                                <input required type="email" name="email" placeholder="Enter your email address">
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

                        <div class="form-login">
                            <label>Select Package</label>
                            <div class="pass-group">
                                <select name="package_type" required>
                                    <option value="Basic">Basic - $30 [Validity: 1 Day]</option>
                                    <option value="Silver">Silver - $5,000 [Validity: 3 months]</option>
                                    <option value="Gold">Gold - $10,000 [Validity: 6 months]</option>
                                    <option value="Platinum">Platinum - $20,000 [Validity: 9 months]</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-login">
                            <label>Payment Method</label>
                            <div class="payment-methods">
                                <div class="payment-option">
                                    <input type="radio" id="paypal" name="payment_method" value="paypal" required>
                                    <label for="paypal">
                                        PayPal
                                    </label>
                                </div>
                                <!-- <div class="payment-option">
                                    <input type="radio" id="stripe" name="payment_method" value="stripe" required>
                                    <label for="stripe">
                                        Credit/Debit Card (Stripe)
                                    </label>
                                </div> -->
                            </div>
                        </div>

                        <p>Already Have An Account? <a href="login.php">Login Now</a></p>
                        <div class="form-login">
                            <button class="btn btn-login" type="submit" name="register">Proceed to Payment</button>
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
<?php include 'footer-files.php'; ?>