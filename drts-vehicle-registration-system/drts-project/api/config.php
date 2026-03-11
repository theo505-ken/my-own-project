<?php

// Database configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', '123456789Theo');
define('DB_NAME', 'database_name');

// Helper functions

function connect() {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }
    return $connection;
}

function close($connection) {
    $connection->close();
}

function query($sql) {
    $connection = connect();
    $result = $connection->query($sql);
    close($connection);
    return $result;
}

?>