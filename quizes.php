<?php
    include 'config.php';
    if (isset($_SESSION['qa_user'])) {
        $quizesActive = 'active';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Quizes | <?= $websiteName ?></title>
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
                            <h4>Quiz List</h4>
                        </div>
                    </div>
                    <?php 
                        if ($fetchUser['u_package_type'] == 'Basic' &&  $fetchUser['u_quiz_created'] > 0) {
                            echo '<h6 class="mb-3">You can Only Generate 1 Quiz</h6>';
                        } else {
                    ?>
                    <a href="code.php?type=createLink" class="btn btn-submit me-2 mb-3">Generate Link</a>
                    <?php
                        }
                    ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table datanew">
                                    <thead>
                                        <tr>
                                            <th>S. No</th>
                                            <th>Link</th>
                                            <th>Result</th>
                                            <th>Graph</th>
                                            <th>Attempted By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $sno = 1;
                                            $quizes = mysqli_query($con,"SELECT * FROM `quizzes` WHERE q_user_id='$_SESSION[qa_user]'");
                                            while ($fetchQuizes = mysqli_fetch_assoc($quizes)) {
                                        ?>
                                        <tr>
                                            <td><?= $sno; ?></td>
                                            <td><a target="_blank" href="<?= $url.'quiz.php?code='.$fetchQuizes['q_code'] ?>">
                                                <?= $url.'quiz.php?code='.$fetchQuizes['q_code'] ?>
                                            </a></td>
                                            <td><?= $fetchQuizes['q_result'] ?></td>
                                            <td><img class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#image<?= $fetchQuizes['q_id'] ?>" src="results/<?= $fetchQuizes['q_image'] ?>"></td>
                                            <td>
                                                Name: <?= $fetchQuizes['q_name'] ?>
                                                <br>Email: <?= $fetchQuizes['q_email'] ?>
                                                <br>Phone: <?= $fetchQuizes['q_phone'] ?>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="image<?= $fetchQuizes['q_id'] ?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-body">
                                                        <img src="results/<?= $fetchQuizes['q_image'] ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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