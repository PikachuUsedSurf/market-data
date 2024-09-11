<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Database configuration
$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "market-data"
];

// Connect to database
function connectToDatabase($config) {
    try {
        $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['dbname']);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Process file upload
function processFileUpload($conn) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed. Please try again.");
    }

    $file = $_FILES['file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $stmt = $conn->prepare("INSERT INTO crop_data (date, lot, warehouse, district, bags, kgs, grade, union_name, crop, price, buyer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssidsssds", $date, $lot, $warehouse, $district, $bags, $kgs, $grade, $union_name, $crop, $price, $buyer);

    foreach ($rows as $index => $row) {
        if ($index === 0) continue; // Skip header row
        
        $date = date('Y-m-d', strtotime($row[0]));
        $lot = $row[1];
        $warehouse = $row[2];
        $district = $row[3];
        $bags = (int)str_replace(',', '', $row[4]);
        $kgs = (float)str_replace(',', '', $row[5]);
        $grade = $row[6];
        $union_name = $row[7];
        $crop = $row[8];
        $price = (int)str_replace(',', '', $row[9]);
        $buyer = $row[10];

        if (!$stmt->execute()) {
            throw new Exception("Error inserting row: " . $stmt->error);
        }
    }

    $stmt->close();
    return count($rows) - 1; // Subtract 1 for header row
}

// Delete all data
function deleteAllData($conn) {
    $sql = "DELETE FROM crop_data";
    if (!$conn->query($sql)) {
        throw new Exception("Error deleting data: " . $conn->error);
    }
    return $conn->affected_rows;
}

// Main logic
$message = '';
try {
    $conn = connectToDatabase($config);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['submit'])) {
            $rowsInserted = processFileUpload($conn);
            $message = "Successfully uploaded and inserted $rowsInserted rows.";
        } elseif (isset($_POST['delete_all'])) {
            $rowsDeleted = deleteAllData($conn);
            $message = "Successfully deleted $rowsDeleted rows.";
        }
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Data Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Market Data Management</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Upload XLSX File</h5>
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="file" class="form-label">Select XLSX file to upload:</label>
                        <input class="form-control" type="file" name="file" id="file" accept=".xlsx" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary">Upload XLSX</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Delete All Data</h5>
                <form method="post" onsubmit="return confirm('Are you sure you want to delete all data? This action cannot be undone.');">
                    <button type="submit" name="delete_all" class="btn btn-danger">Delete All Data</button>
                </form>
            </div>
        </div>

        <a href="/market-data/data-report.php" class="btn btn-secondary">View Data Report</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>