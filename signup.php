<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['signup_customer'])) {
        header('Location: /project/PedalWorks-Dynamics/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Sign Up - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Customer Sign Up</h1>
    <form action="" method="post">
        <div>
            <label for="firstName">First Name:</label>
            <input type="text" id="firstName" name="firstName">
        </div>
        <br>
        <div>
            <label for="lastName">Last Name:</label>
            <input type="text" id="lastName" name="lastName">
        </div>
        <br>
        <div>
            <label for="birthDate">Birth Date:</label>
            <input type="date" id="birthDate" name="birthDate">
        </div>
        <br>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email">
        </div>
        <br>
        <div>
            <label for="address">Address:</label>
            <textarea id="address" name="address"></textarea>
        </div>
        <br>
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
            <button type="submit" name="signup_customer">Submit Sign Up</button>
        </div>
    </form>
    <br>
    <p>Already have an account? <a href="/project/PedalWorks-Dynamics/login.php"><button type="button">Go to Login</button></a></p>
    <p><a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Back to Homepage</button></a></p>
</body>
</html>
