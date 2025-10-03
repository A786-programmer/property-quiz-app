<?php 
    $con = mysqli_connect('localhost','root','','property_quiz_app');
    session_start();
    $currentDate = date('Y-m-d');
    $currentDateTime = date('Y-m-d h:i:s');  
    date_default_timezone_set('Asia/Karachi');
    // date_default_timezone_set("Canada/Central");
    error_reporting(0);
    $websiteName = 'Quiz App';

    $user = mysqli_query($con,"SELECT * FROM users WHERE u_id = '$_SESSION[qa_user]'");
    $fetchUser = mysqli_fetch_array($user);
    $role = $fetchUser['u_role'];
    $profileImg = 'logo.png';
    $hasAdminRights = $fetchUser['u_role'] == 'Admin' ? true : false;
    if ($fetchUser['u_profile_img']) {
        $profileImg = $fetchUser['u_profile_img'];
    }
    $url = 'http://localhost/property-quiz-app/';
    $paypalKey ='sb-9lhkk34049101@business.example.com';
    $stripePublishableKey = "pk_test_51JRaTcBDNMf1yHJjixB5O7N3YPep7be9JdNR8DpvuXdN2VQXQ9xWSSl47lJGiwVcSpa4tLHwRwGzO45vHw5dut9600P80gfzHR";
    $stripeSecretKey = "sk_test_51JRaTcBDNMf1yHJjy8wc8MJC5ZkDCK5ResdaBepbOG5KCOrbh0R1woOty4Zjr5qg3IkEXiMe3NzweZQzeWGFGHaX00YbO9SBYO";
?>