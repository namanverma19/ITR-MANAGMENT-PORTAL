<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "itr_data_base";

$connection = new mysqli($servername, $username, $password, $dbname);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$connection->set_charset("utf8");
?>
