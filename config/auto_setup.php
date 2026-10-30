<?php
    if(isset($pdo)){
        // Check if the database exists
        $st = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $st->execute([$dbname]);

        if (!$st->fetch()) {
            // If the database doesn't exist, create it
            $res = $pdo->exec("CREATE DATABASE `$dbname`");

            if ($res !== false) {
                echo "Database `'$dbname'` created successfully.<br>";
            } else {
                echo "Error creating database: " . $pdo->errorInfo()[2] . "<br>";
            }
        } else {
            // echo "Database '$dbname' already exists.<br>";
        }

        $pdo->exec("USE `$dbname`");
    }

    require_once __DIR__.'/setup_tables.php';
?>