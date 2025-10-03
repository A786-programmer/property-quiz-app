<?php 
    include 'config.php';
        if ($hasAdminRights) {
          //  if (isset($_SESSION['as_user'])) {
                $usersActive = 'active';

                $userId = $_GET['userId'];
                $user = mysqli_query($con,"SELECT * FROM `users` WHERE u_id='$userId'");
                if ($userId && mysqli_num_rows($user) == 0) {
                    $_SESSION['toastr_message'] = "Invalid Access!";
                    $_SESSION['toastr_type'] = "error";
                    header("Location: users.php");
                    exit();
                }
                $fetchUser = mysqli_fetch_assoc($user);

                if(isset($_POST['update'])){
                    try {
                        $name = $_POST['name'];
                        $email = $_POST['email'];
                        $password = $_POST['password'];
                        mysqli_query($con,"UPDATE users SET u_name='$name', u_email='$email', u_password='$password' WHERE u_id='$userId'");
                        $_SESSION['toastr_message'] = "User Has been Updated Successfully!";
                        $_SESSION['toastr_type'] = "success";
                        header("Location: users.php");
                        exit();
                    } catch (Exception $e) {
                        $_SESSION['toastr_message'] = "Something went wrong: " . $e->getMessage();
                        $_SESSION['toastr_type'] = "error";
                        header("Location: users.php");
                        exit();
                    }
                }     
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Users</title>
        <?php include 'header-files.php' ?>
        <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
	</head>
	<body>
		<div class="main-wrapper">
            <?php include 'header.php' ?>
            <?php include 'sidebar.php' ?>
			<div class="page-wrapper">
				<div class="content">
					<div class="page-header">
						<div class="page-title">
							<h4><?= ($userId) ? 'Update' : 'Add' ?> User</h4>
						</div>
					</div>
                    <div class="card">
						<div class="card-body">
							<form class="row" method="post">
								<div class="col-md-6">
									<div class="form-group">
										<label>User Name</label>
										<input type="text" name="name" value="<?= $fetchUser['u_name'] ?>">
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label>User Email</label>
										<input type="text" name="email" value="<?= $fetchUser['u_email'] ?>">
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label>Password</label>
										<input type="text" name="password" value="<?= $fetchUser['u_password'] ?>">
									</div>
								<div class="col-lg-12">
                                    <?php if ($userId) { ?>
									<button type="submit" name="update" class="btn btn-submit me-2">Update User</button>
                                    <?php } else { ?>
									<button type="submit" name="add" class="btn btn-submit me-2">Add User</button>
                                    <?php } ?>
								</div>
                            </form>
						</div>
					</div>
                    <div class="page-header">
						<div class="page-title">
							<h4>Users List</h4>
						</div>
					</div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table  datanew ">
                                    <thead>
                                        <tr>
                                            <th>S. No</th>
                                            <th>Profile</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Password</th>
                                            <th>Register At</th>
                                            <th>Expired At</th>
                                            <th>Is Expired</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $sno = 1;
                                            $users = mysqli_query($con,"SELECT * FROM `users` WHERE u_role='Landlord'");
                                            while ($fetchUsers = mysqli_fetch_assoc($users)) {
                                                $img = '';
                                                $status = '<span class="badges bg-lightgreen">Active</span><br>
                                                <a href="code.php?type=deactivateUser&userId='.$fetchUsers['u_id'].'" style="color: red">Deactivate</a>';
                                                if ($fetchUsers['u_profile_img']) {
                                                    $img = '<img height="50px" width="50px" src="user-profile-imgs/'.$fetchUsers['u_profile_img'].'" alt="">';
                                                }
                                                if ($fetchUsers['u_status'] == 0) {
                                                    $status = '<span class="badges bg-lightred">Inactive</span><br>
                                                    <a href="code.php?type=activateUser&userId='.$fetchUsers['u_id'].'" style="color: green">Activate</a>';
                                                }if ($fetchUsers['u_is_expired'] == 0) {
                                                    $expiredStatus = "<span class='badges bg-lightgreen'>Yes</span>";
                                                } else {
                                                    $expiredStatus = "<span class='badges bg-lightred'>No</span>";
                                                }
                                        ?>
                                        <tr>
                                            <td><?= $sno ?></td>
                                            <td><?= $img ?></td>
                                            <td><?= $fetchUsers['u_name'] ?></td>
                                            <td><?= $fetchUsers['u_email'] ?></td>
                                            <td><?= $fetchUsers['u_password'] ?></td>
                                            <td><?= $fetchUsers['u_registered_at'] ?></td>
                                            <td><?= $fetchUsers['u_expired_at'] ?></td>
                                            <td><?= $expiredStatus ?></td>
                                            <td><?= $status ?></td>

                                            <td>
                                                <a href="users.php?userId=<?= $fetchUsers['u_id'] ?>"><img src="assets/img/icons/edit.svg" alt="img" data-bs-toggle="tooltip" title="Edit"></a>
                                                <a href="code.php?type=deleteUser&userId=<?= $fetchUsers['u_id'] ?>"><img src="assets/img/icons/delete.svg" alt="img" data-bs-toggle="tooltip" title="Delete"></a>
                                            </td>
                                        </tr>
                                        <?php 
                                                $sno++;
                                            }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
				</div>
			</div>
		</div>	
	</body>
</html>
<?php 
            // }
            //  else {
            //     $_SESSION['toastr_message'] = "Please Login First!";
            //     $_SESSION['toastr_type'] = "info";
            //     header("Location: login.php");
            //     exit();
            // }
        } else {
            $_SESSION['toastr_message'] = "You don't have right to access the desired Resource!";
            $_SESSION['toastr_type'] = "info";
            header("Location: index.php");
            exit();
        }
    include 'footer-files.php';
?>	
<!-- Datatable JS -->
<script src="assets/js/jquery.dataTables.min.js"></script>
<script src="assets/js/dataTables.bootstrap4.min.js"></script>