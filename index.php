<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "market-data";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];

    // Load the spreadsheet
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO crop_data (date, lot, warehouse, district, bags, kgs, grade, union_name, crop, price, buyer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssidsssds", $date, $lot, $warehouse, $district, $bags, $kgs, $grade, $union_name, $crop, $price, $buyer);

    // Loop through each row in the spreadsheet
    foreach ($rows as $index => $row) {
        if ($index === 0) continue; // Skip the header row
        
        $date = date('Y-m-d', strtotime($row[0]));
        $lot = $row[1];
        $warehouse = $row[2];
        $district = $row[3];

        // Remove commas from bags and kgs and cast to integer/float
        $bags = (int)str_replace(',', '', $row[4]); 
        $kgs = (float)str_replace(',', '', $row[5]);
        
        $grade = $row[6];
        $union_name = $row[7];
        $crop = $row[8];
        
        // Remove commas from price and cast to integer
        $price = (int)str_replace(',', '', $row[9]); 
        
        $buyer = $row[10];
        $union_name = $row[7];
        // echo "Union Name: " . $union_name . "<br>"; 

        $stmt->execute();
    }

    $stmt->close();
    $conn->close();

    echo "Data has been successfully uploaded!";
}


// Handle the button that deletes data from the database
if (isset($_POST['delete_all'])) {
    $sql = "DELETE FROM crop_data";
    if ($conn->query($sql) === TRUE) {
        echo "All data deleted successfully";
    } else {
        echo "Error deleting data: " . $conn->error;
    }
    $conn->close();
    exit;
}

?>

<!DOCTYPE html>
<html>
<body>

<h2>Upload XLSX to MySQL</h2>
<form action="" method="post" enctype="multipart/form-data">
  Select XLSX file to upload:
  <input type="file" name="file" id="file">
  <input type="submit" value="Upload XLSX" name="submit">
</form>

<form method="post">
    <input type="submit" name="delete_all" value="Delete All Data">
</form> 
<button><a href="/market-data/data-report.php">datareport</a></button>

</body>
</html>