<?php
$db_host = "localhost";
$db_username = "root";
$db_passwd = "";
$db_name = "pedalworks_db";

try {
    $conn = @mysqli_connect($db_host, $db_username, $db_passwd, $db_name);
    if (!$conn) {
        $conn = @mysqli_connect($db_host, $db_username, $db_passwd);
        if ($conn) {
            @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name`");
            @mysqli_select_db($conn, $db_name);
        }
    }
} catch (mysqli_sql_exception $e) {
    $conn = false;
}
