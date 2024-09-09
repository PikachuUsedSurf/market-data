<?php

// Database credentials
$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "market-data"
];

// Create connection
$conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['dbname']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate union summary
function getUnionSummary($conn, $unionName) {
    $sql = "SELECT SUM(kgs) as total_weight, MAX(price) as highest_price, MIN(price) as lowest_price FROM crop_data WHERE union_name = '$unionName'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// API endpoint for fetching data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get the date from the request parameters
    $date = $_GET['date'] ?? null;

    // Fetch the data from the database
    $sql = "SELECT * FROM crop_data";

    if ($date) {
        $sql .= " WHERE date = '$date'";
    }

    $sql .= " ORDER BY union_name, date";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $data = [];
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        // Format the data for the API response
        $response = [
            'status' => 'success',
            'data' => $data,
            'message' => 'Data fetched successfully'
        ];

        // Set the content type to JSON
        header('Content-Type: application/json');

        // Output the JSON response
        echo json_encode($response);

    } else {
        // No data found
        $response = [
            'status' => 'error',
            'message' => 'No data found'
        ];

        // Set the content type to JSON
        header('Content-Type: application/json');

        // Output the JSON response
        echo json_encode($response);
    }
} else {
    // Invalid request method
    $response = [
        'status' => 'error',
        'message' => 'Invalid request method'
    ];

    // Set the content type to JSON
    header('Content-Type: application/json');

    // Output the JSON response
    echo json_encode($response);
}

$conn->close();
?>