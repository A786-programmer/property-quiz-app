<?php
include 'config.php';
$type = $_GET['type'];

switch($type){
    case 'logout':
        session_destroy();
        echo '<script>window.location="login.php";</script>';
    break;
    case 'createLink':
        $randomCode = substr(md5(time() . rand()), 0, 8);
        $user = mysqli_query($con, "SELECT u_quiz_created FROM `users` WHERE u_id='$_SESSION[qa_user]'");
        $fetchUser = mysqli_fetch_assoc($user);
        $quizCreated = $fetchUser['u_quiz_created'] + 1;
        mysqli_query($con, "UPDATE users SET u_quiz_created='$quizCreated' WHERE u_id='$_SESSION[qa_user]'");
        mysqli_query($con, "INSERT INTO quizzes (q_user_id, q_code) VALUES ('$_SESSION[qa_user]', '$randomCode')");
        $_SESSION['toastr_message'] = "Quiz Link Generated Successfully!";
        $_SESSION['toastr_type'] = "success";
        header("Location: index.php");
    break;
    default:
        echo '<script>alert("Invalid Access");
        window.location="settings.php";</script>';
    break;
}
?>