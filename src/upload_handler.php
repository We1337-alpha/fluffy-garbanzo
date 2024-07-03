<?php

require 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is running as user: " . exec('whoami') . "<br>";

$ftp_server = FTP_HOST;
$ftp_username = FTP_USER;
$ftp_password = FTP_PASS;

$target_dir = "/home/we1337/files/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);

echo "Attempting to upload: " . $target_file . "<br>";

$conn_id = ftp_connect($ftp_server);
if (!$conn_id) {
    die("Could not connect to FTP server. Error: " . error_get_last()['message']);
}

$login_result = ftp_login($conn_id, $ftp_username, $ftp_password);
if (!$login_result) {
    die("Could not login to FTP server. Error: " . error_get_last()['message']);
}

ftp_pasv($conn_id, true);

echo "FTP initial directory: " . ftp_pwd($conn_id) . "<br>";

// Check if directory exists and has write permissions
if (@ftp_chdir($conn_id, $target_dir)) {
    echo "Directory exists and is writable<br>";
} else {
    echo "Directory does not exist or is not writable<br>";
    if (@ftp_mkdir($conn_id, $target_dir)) {
        echo "Directory created successfully<br>";
    } else {
        die("Failed to create directory<br>");
    }
}

// Change to target directory
if (ftp_chdir($conn_id, $target_dir)) {
    echo "Successfully changed to target directory<br>";
} else {
    die("Failed to change to target directory<br>");
}

echo "Temporary file permissions: " . substr(sprintf('%o', fileperms($_FILES["fileToUpload"]["tmp_name"])), -4) . "<br>";

$home_upload = basename($_FILES["fileToUpload"]["name"]);
if (ftp_put($conn_id, $home_upload, $_FILES["fileToUpload"]["tmp_name"], FTP_BINARY)) {
    echo "File uploaded to home directory successfully<br>";
    ftp_delete($conn_id, $home_upload);
} else {
    echo "Failed to upload to home directory<br>";
}

// File upload to target directory
if (ftp_put($conn_id, $target_file, $_FILES["fileToUpload"]["tmp_name"], FTP_BINARY)) {
    echo "The file " . basename($_FILES["fileToUpload"]["name"]) . " has been uploaded.<br>";

    // Insert file details into MySQL database
    $filename = basename($_FILES["fileToUpload"]["name"]);
    $size = $_FILES["fileToUpload"]["size"];
    $type = $_FILES["fileToUpload"]["type"];

    // Establish database connection (assuming PDO)
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare SQL statement
        $stmt = $pdo->prepare("INSERT INTO uploaded_files (filename, size, type) VALUES (:filename, :size, :type)");
        
        // Bind parameters
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':size', $size);
        $stmt->bindParam(':type', $type);

        // Execute the statement
        $stmt->execute();

        echo "File details inserted into database successfully.<br>";
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    echo "Sorry, there was an error uploading your file.<br>";
    echo "FTP Error: " . error_get_last()['message'] . "<br>";
}

// Close FTP connection
ftp_close($conn_id);

?>
