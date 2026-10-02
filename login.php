<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_admin'])) {
        header('Location: /project/PedalWorks-Dynamics/admin/dashboard.php');
        exit;
    }
    if (isset($_POST['login_customer'])) {
        header('Location: /project/PedalWorks-Dynamics/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Login</h1>
    <form action="" method="post">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username">
        </div>
        <br>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password">
        </div>
        <br>
        <div>
            <button type="submit" name="login_admin">Submit as Admin</button>
            <button type="submit" name="login_customer">Submit as Customer</button>
        </div>
    </form>
    <br>
    <p>New customer? <a href="/project/PedalWorks-Dynamics/signup.php"><button type="button">Go to Sign Up</button></a></p>
    <p><a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Back to Homepage</button></a></p>
</body>
</html>
