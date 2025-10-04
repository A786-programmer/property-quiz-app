<?php
include 'config.php';

if (isset($_SESSION['qa_user'])) {
    $usersActive = 'active';
    $userId = $_SESSION['qa_user'];

    if (isset($_POST['generate_link'])) {
        $uid = $_POST['user_id'];

        $randomCode = substr(md5(time() . rand()), 0, 8);

        $check = mysqli_query($con, "SELECT * FROM Quizzes WHERE q_user_id='$uid' LIMIT 1");

        if (mysqli_num_rows($check) > 0) {
            $update = mysqli_query($con, "UPDATE Quizzes SET q_code='$randomCode' WHERE q_user_id='$uid'");
        } else {
            $update = mysqli_query($con, "INSERT INTO Quizzes (q_user_id, q_code) VALUES ('$uid', '$randomCode')");
        }

        if ($update) {
            $_SESSION['toastr_message'] = "Quiz Link Generated Successfully!";
            $_SESSION['toastr_type'] = "success";
        } else {
            $_SESSION['toastr_message'] = "Error generating link!";
            $_SESSION['toastr_type'] = "error";
        }

        header("Location: index.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Users - Quiz List</title>
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

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table datanew">
                            <thead>
                            <tr>
                                <th>S. No</th>
                                <th>Quiz Link</th>
                                <th>Quiz Result</th>
                                <th>Quiz Image</th>
                                <th>Quiz Name</th>
                                <th>Quiz Email</th>
                                <th>Quiz Phone</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $sno = 1;
                            $Quizzes = mysqli_query($con, "SELECT * FROM Quizzes WHERE q_user_id='$userId'");

                            if (mysqli_num_rows($Quizzes) > 0) {
                                $fetchQuizzes = mysqli_fetch_assoc($Quizzes);
                                ?>
                                <tr>
                                    <td><?= $sno ?></td>
                                    <td>
                                        <?php if (!empty($fetchQuizzes['q_code'])) { ?>
                                            <?php if (empty($fetchQuizzes['q_result']) && empty($fetchQuizzes['q_image'])) { ?>
                                                <!-- Agar result aur image empty hain to link show karo -->
                                                <a href="quiz-app.php?code=<?= $fetchQuizzes['q_code'] ?>" target="_blank">
                                                    <?= "quiz-app.php?code=" . $fetchQuizzes['q_code'] ?>
                                                </a>
                                            <?php } else { ?>
                                                <!-- Agar result ya image available hai to dobara open na ho -->
                                                <span class="badge bg-success">Completed</span>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <form method="post" action="">
                                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                                <button type="submit" name="generate_link" class="btn btn-sm btn-primary">Generate Quiz Link</button>
                                            </form>
                                        <?php } ?>
                                    </td>
                                    <td><?= $fetchQuizzes['q_name'] ?></td>
                                    <td><?= $fetchQuizzes['q_email'] ?></td>
                                    <td><?= $fetchQuizzes['q_phone'] ?></td>
                                    <td><?= !empty($fetchQuizzes['q_result']) ? $fetchQuizzes['q_result'] : '-' ?></td>
                                    <td>
                                        <?php if (!empty($fetchQuizzes['q_image'])) { ?>
                                            <img src="results/<?= $fetchQuizzes['q_image'] ?>" width="50">
                                        <?php } else { echo "-"; } ?>
                                    </td>
                                </tr>
                                <?php
                            } else {
                                ?>
                                <tr>
                                    <td><?= $sno ?></td>
                                    <td>
                                        <form method="post" action="">
                                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                                            <button type="submit" name="generate_link" class="btn btn-sm btn-primary">Generate Quiz Link</button>
                                        </form>
                                    </td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <?php
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
