<?php 
	include 'config.php';
		if (isset($_SESSION['qa_user'])) {
			if(isset($_POST['update'])){
				try {
					$userName = $_POST['userName'];
					$userEmail = $_POST['userEmail'];
					$userPassword = $_POST['userPassword'];
					$userProfile = $_FILES['userProfile']['name'];
					if ($userProfile) {
						move_uploaded_file($_FILES['userProfile']['tmp_name'], "user-profile-imgs/".$userProfile);
					} else {
						$userProfile = $fetchUser['u_profile_img'];
					}
					mysqli_query($con,"UPDATE users SET u_name='$userName', u_email='$userEmail', u_password='$userPassword', u_profile='$userProfile' WHERE u_id='$_SESSION[qa_user]'");
					$_SESSION['toastr_message'] = "Details Updated Successfully!";
					$_SESSION['toastr_type'] = "success";
					header("Location: settings.php");
					exit();
				} catch (Exception $e) {
					$_SESSION['toastr_message'] = "Something went wrong: " . $e->getMessage();
					$_SESSION['toastr_type'] = "error";
					header("Location: settings.php");
					exit();
				}
			}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Settings | <?= $websiteName ?></title>
        <?php include 'header-files.php' ?>
	</head>
	<body>
		<div class="main-wrapper">
            <?php include 'header.php' ?>
            <?php include 'sidebar.php' ?>
			<div class="page-wrapper">
				<div class="content">
					<div class="page-header">
						<div class="page-title">
							<h4>Update User Settings</h4>
						</div>
					</div>
					<div class="card">
						<div class="card-body">
							<form class="row" method="post" enctype="multipart/form-data">
								<div class="col-md-6">
									<div class="form-group">
										<label>User Name</label>
										<input type="text" name="userName" value="<?= $fetchUser['u_name'] ?>">
									</div>
								</div>	
								<div class="col-md-6">
									<div class="form-group">
										<label>User Profile</label>
										<input type="file" name="userProfile">
									</div>
								</div>	
								<div class="col-md-6">
									<div class="form-group">
										<label>User Email</label>
										<input type="text" name="userEmail" value="<?= $fetchUser['u_email'] ?>">
									</div>
								</div>	
								<div class="col-md-6">
									<div class="form-group">
										<label>Password</label>
										<input type="password" name="userPassword" value="<?= $fetchUser['u_password'] ?>">
									</div>
								</div>	
								<div class="col-lg-12">
									<button href="javascript:void(0)" type="submit" name="update" class="btn btn-submit me-2">Update</button>
								</div>
                            </form>
						</div>
					</div>
				</div>
			</div>
		</div>	
	</body>
</html>
<?php 
	} else {
		$_SESSION['toastr_message'] = "Please Login First!";
		$_SESSION['toastr_type'] = "info";
		header("Location: login.php");
		exit();
	}
	include 'footer-files.php';
?>	