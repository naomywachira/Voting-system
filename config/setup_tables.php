<?php

    $conn = new mysqli($host, $username, $password, $dbname);

	// Check connection
	if ($conn->connect_error) {
        die("Connection failed: " . $conn->error);
	}

	// Function to check if a table exists
	function tableExists($conn, $tableName) {
		$checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
		return $checkTable && $checkTable->num_rows > 0;
	}

	// Tables and their structures
    $must_have = "
        publish INT DEFAULT 1,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ";
	$tables = [
		"Users" => "CREATE TABLE Users (
			id INT PRIMARY KEY AUTO_INCREMENT,
			Username TEXT,
			Password VARCHAR(30),
			status text default 'active',
			$must_have
		)",
        "categories" => "CREATE TABLE Categories(
			id INT PRIMARY KEY AUTO_INCREMENT,
			name VARCHAR(64),
			$must_have
        )",
        "nominations" => "CREATE TABLE Nominations(
			id INT PRIMARY KEY AUTO_INCREMENT,
			status VARCHAR(48),
            category_id INT NOT NULL,
            nominee_name VARCHAR(48),
            nominee_description VARCHAR(48),
            nominee_email VARCHAR(48),
            nominee_phone VARCHAR(32),
            submitted_by INT NOT NULL,
            submitter_email VARCHAR(48),
            admin_remarks TEXT,
			$must_have
        )",
        "settings" => "CREATE TABLE Settings(
			id INT PRIMARY KEY AUTO_INCREMENT,
			setting_value TEXT,
			setting_key TEXT,
			$must_have
        )",
        "Votes" => "CREATE TABLE Votes(
			id INT PRIMARY KEY AUTO_INCREMENT,
            nomination_id INT NOT NULL,
            voter_id INT NOT NULL,
            voter_ip TEXT,
            voter_location TEXT,
			status INT DEFAULT 0,
			$must_have
        )",
	];

	// Create tables if they do not exist
	foreach ($tables as $tableName => $createSQL) {
		if (!tableExists($conn, $tableName)) {
			echo "$tableName table created<br>";
			$conn->query($createSQL);
		}
	}

	// Insert default admin user if no records exist in Users table
	$checkusersquery = "SELECT COUNT(*) AS count FROM Users";
	$userCheck = $conn->query($checkusersquery);
	$userRow = $userCheck->fetch_assoc();

	if ($userRow['count'] == 0) {
		$conn->query("INSERT INTO Users (Username, Password) VALUES ('admin', '123')");

		echo "default admin record setup successfully, you may need to log in again";
	}

    // set up the settings items
    $stuff = [
        '("voting_status","closed")',
        '("nomination_status","closed")',
    ];

    $check = "SELECT COUNT(*) AS count FROM settings";
    $settingscheck = $conn->query($check);
    $row = $settingscheck->fetch_assoc();

    if($row['count'] == 0){
        $sql = implode(",",$stuff);
        $qr = "INSERT INTO settings (setting_key, setting_value) VALUES $sql";
        $query = $conn->query($qr);

        if($query){
            echo "default settings added successfully";
        } else {
            echo "error adding default settings";
        }
    }

    // set up categories
    $stuff = [
        '("governor")',
        '("MCA")',
        '("Chief")',
    ];
    $check = "SELECT COUNT(*) AS count FROM categories";
    $settingscheck = $conn->query($check);
    $row = $settingscheck->fetch_assoc();

    if($row['count'] == 0){
        $sql = implode(",",$stuff);
        $qr = "INSERT INTO categories (name) VALUES $sql";
        $query = $conn->query($qr);

        if($query){
            echo "default categories added successfully";
        } else {
            echo "error adding default categories";
        }
    }
?>
