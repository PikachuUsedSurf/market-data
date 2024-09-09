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
    $db = 'market-data';
    $user = 'root';
    $pass = '';
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

            $date = '';
            $lot = '';
            $warehouse = '';
            $district = '';
            $bags = 0;
            $kgs = 0;
            $grade = '';
            $union = '';
            $crop = '';
            $price = 0;
            $buyer = '';

            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();

                switch ($column) {
                    case 'A':
                        $date = $value;
                        break;
                    case 'B':
                        $lot = $value;
                        break;
                    case 'C':
                        $warehouse = $value;
                        break;
                    case 'D':
                        $district = $value;
                        break;
                    case 'E':
                        $bags = (int)$value;
                        break;
                    case 'F':
                        $kgs = (float)$value;
                        break;
                    case 'G':
                        $grade = $value;
                        break;
                    case 'H':
                        $union = $value;
                        break;
                    case 'I':
                        $crop = $value;
                        break;
                    case 'J':
                        $price = (float)$value;
                        break;
                    case 'K':
                        $buyer = $value;
                        break;
                }
            }

            // Insert or update data in the database
            $stmt = $pdo->prepare("
            INSERT INTO trade_results (union_name, crop, highest_price, lowest_price, total_kgs)
            VALUES (union_name, crop, highest_price, lowest_price, kgs)
            ON DUPLICATE KEY UPDATE
            highest_price = GREATEST(highest_price, VALUES(highest_price)),
            lowest_price = LEAST(lowest_price, VALUES(lowest_price)),
            total_kgs = total_kgs + VALUES(total_kgs)
        ");
        
        $stmt->execute([
            'union_name' => $union,
            'crop' => $crop,
            'highest_price' => $price,
            'lowest_price' => $price,
            'kgs' => $kgs,
        ]);
        
        
        }
            //end
        echo "Data successfully uploaded and stored in the database.";

    } catch (\PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
} else {
    echo "No file uploaded.";
}
?>
