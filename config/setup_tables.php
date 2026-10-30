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
	$tables = [
		"Users" => "CREATE TABLE Users (
			Id INT PRIMARY KEY AUTO_INCREMENT,
			Username TEXT,
			Password VARCHAR(30),
			status text default 'active',
			Date_added DATETIME DEFAULT CURRENT_TIMESTAMP
		)",
		"Greenhouses" => "CREATE TABLE Greenhouses (
			id int PRIMARY KEY AUTO_INCREMENT,
			gr_name text,
			gr_desc text,
			gr_crop text,
			gr_location text,
			gr_sensor int,
			status text default 'not monitored',
			Date_added DATETIME DEFAULT CURRENT_TIMESTAMP
		)",
		"Sensors" => "CREATE TABLE Sensors (
			Id INT PRIMARY KEY AUTO_INCREMENT,
			sen_serial text,
			sen_name text,
			sen_desc text,
			status text default 'idle',
			Date_added DATETIME DEFAULT CURRENT_TIMESTAMP
		)",
		"datalog" => "CREATE TABLE datalog (
			Id INT PRIMARY KEY AUTO_INCREMENT,
			sen_id int,
			gr_id int,
			tel_temp varchar(7),
			tel_humidity varchar(7),
			time_recorded datetime DEFAULT CURRENT_TIMESTAMP
		)"
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
?>
