<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <li class="<?= $indexActive ?>">
                    <a href="quiz-app.php" ><img src="assets/img/icons/dashboard.svg" alt="img"><span>Quiz</span></a>
                </li>
                <?php if ($hasAdminRights) { ?>
                <li class="<?= $usersActive ?>">
                    <a href="users.php"><img src="assets/img/icons/users1.svg" alt="img"><span>Users</span></a>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</div>
<!-- /Sidebar -->