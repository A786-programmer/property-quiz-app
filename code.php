<?php
include 'config.php';
$type = $_GET['type'];

switch($type){
    case 'logout':
        session_destroy();
        echo '<script>window.location="login.php";</script>';
    break;
    case 'deleteUser':
        $userId = $_GET['userId'];
        try {
            mysqli_query($con,"DELETE FROM `users` WHERE u_id='$userId'");
            $_SESSION['toastr_message'] = "User Has been Deleted Successfully!";
            $_SESSION['toastr_type'] = "success";
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['toastr_message'] = "Something went wrong: " . $e->getMessage();
            $_SESSION['toastr_type'] = "error";
            header("Location: users.php");
            exit();
        }
    break;
    case 'deactivateUser':
        $userId = $_GET['userId'];
        try {
            mysqli_query($con,"UPDATE `users` SET u_status = '0' WHERE u_id='$userId'");
            $_SESSION['toastr_message'] = "User Has been Deactived Successfully!";
            $_SESSION['toastr_type'] = "success";
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['toastr_message'] = "Something went wrong: " . $e->getMessage();
            $_SESSION['toastr_type'] = "error";
            header("Location: users.php");
            exit();
        }
    break;
    case 'activateUser':
        $userId = $_GET['userId'];
        try {
            mysqli_query($con,"UPDATE `users` SET u_status = '1' WHERE u_id='$userId'");
            $_SESSION['toastr_message'] = "User Has been Activated Successfully!";
            $_SESSION['toastr_type'] = "success";
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['toastr_message'] = "Something went wrong: " . $e->getMessage();
            $_SESSION['toastr_type'] = "error";
            header("Location: users.php");
            exit();
        }
    break;
    default:
        echo '<script>alert("Invalid Access");
        window.location="settings.php";</script>';
}
?>