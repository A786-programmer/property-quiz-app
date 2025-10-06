<?php
    include 'config.php';
    if (isset($_SESSION['qa_user'])) {
        $indexActive = 'active';
        $quizLeft = 'Unlimited';
        if ($fetchUser['u_package_type'] == 'Basic') {
            $quizLeft = 1-$fetchUser['u_quiz_created'];
        }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Home | <?= $websiteName ?></title>
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
                            <h4>Quizes</h4>
                        </div>
                    </div>
                    <div class="row">
						<div class="col-lg-3 col-sm-6 col-12 d-flex">
							<div class="dash-count das2">
								<div class="dash-counts">
									<h4><?= $fetchUser['u_quiz_created'] ?></h4>
									<h5>Quiz Created</h5>
								</div>
								<div class="dash-imgs">
									<i data-feather="file-text"></i>
								</div>
							</div>
						</div>
						<div class="col-lg-3 col-sm-6 col-12 d-flex">
							<div class="dash-count das3">
								<div class="dash-counts">
									<h4><?= $quizLeft ?></h4>
									<h5>Remaining Quizes</h5>
								</div>
								<div class="dash-imgs">
									<i data-feather="file"></i>  
								</div>
							</div>
						</div>
                    </div>
                    <div class="page-header">
                        <div class="page-title">
                            <h4>Membership</h4>
                        </div>
                    </div>
                    <div class="row">
						<div class="col-md-4">
							<div class="dash-count">
								<div class="dash-counts">
									<h4><?= $fetchUser['u_package_type'] ?></h4>
									<h5>Package Type</h5>
								</div>
								<div class="dash-imgs">
									<i data-feather="user"></i> 
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="dash-count das1">
								<div class="dash-counts">
									<h4><?= $fetchUser['u_registered_at'] ?></h4>
									<h5>Member Since</h5>
								</div>
								<div class="dash-imgs">
									<i data-feather="user-check"></i> 
								</div>
							</div>
						</div>
					</div>
                </div>
            </div>
        </div>
        <?php include 'footer-files.php'; ?>
        <!-- Datatable JS -->
        <script src="assets/js/jquery.dataTables.min.js"></script>
        <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    </body>
</html>
<?php
    } else {
        $_SESSION['toastr_message'] = "Please Login First!";
        $_SESSION['toastr_type'] = "info";
        header("Location: login.php");
        exit();
    }
?>