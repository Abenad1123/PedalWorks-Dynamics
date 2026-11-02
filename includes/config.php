<?php
$db_host = "localhost";
$db_username = "root";
$db_passwd = "";
$db_name = "pedalworks_db";

$passwords = ["", "1234"];
$conn = false;

foreach ($passwords as $pwd) {
    try {
        $conn = @mysqli_connect($db_host, $db_username, $pwd, $db_name);
        if (!$conn) {
            $conn = @mysqli_connect($db_host, $db_username, $pwd);
            if ($conn) {
                @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name`");
                @mysqli_select_db($conn, $db_name);
            }
        }
        if ($conn) {
            $db_passwd = $pwd;
            @mysqli_set_charset($conn, "utf8mb4");
            break;
        }
    } catch (mysqli_sql_exception $e) {
        $conn = false;
    }
}
