<?php
header('Content-Type: application/json');

// Database connection details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'market-data';

// Get parameters
$table = $_GET['table'] ?? '';
$parts = isset($_GET['parts']) ? explode(',', $_GET['parts']) : ['*'];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Validate table name
if (empty($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    die(json_encode(['error' => 'Invalid table name']));
}

// Connect to database
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Prepare and execute query
$columns = implode(',', array_map(function($part) use ($conn) {
    return $conn->real_escape_string($part);
}, $parts));

$query = "SELECT $columns FROM $table LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

if ($result === false) {
    die(json_encode(['error' => 'Query failed: ' . $conn->error]));
}

// Fetch and return results
$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);

$conn->close();
