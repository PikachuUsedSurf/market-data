<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $fileName = $_FILES['file']['tmp_name'];

    // Load the uploaded XLSX file
    $spreadsheet = IOFactory::load($fileName);
    $worksheet = $spreadsheet->getActiveSheet();

    // Database connection
    $host = 'localhost';
    $db = 'your_database_name';
    $user = 'your_username';
    $pass = 'your_password';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);

        // Process the data
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $crop = '';
            $price = 0;
            $kilograms = 0;

            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();

                if ($column == 'A') { // Crop column
                    $crop = $value;
                } elseif ($column == 'B') { // Price column
                    $price = (float)$value;
                } elseif ($column == 'C') { // Kilograms column
                    $kilograms = (float)$value;
                }
            }

            // Insert or update data in the database
            $stmt = $pdo->prepare("
                INSERT INTO crops (crop, highest_price, lowest_price, total_kgs)
                VALUES (:crop, :price, :price, :kilograms)
                ON DUPLICATE KEY UPDATE
                highest_price = GREATEST(highest_price, :price),
                lowest_price = LEAST(lowest_price, :price),
                total_kgs = total_kgs + :kilograms
            ");

            $stmt->execute([
                ':crop' => $crop,
                ':price' => $price,
                ':kilograms' => $kilograms,
            ]);
        }

        echo "Data successfully uploaded and stored in the database.";

    } catch (\PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
} else {
    echo "No file uploaded.";
}
?>
