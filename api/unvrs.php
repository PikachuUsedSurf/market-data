<?php
header('Content-Type: application/json');

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Check API key
function getAuthorizationHeader()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

$apiKey = getAuthorizationHeader();
//echo $apiKey;
//echo "<br>";
//echo $_ENV['API_KEY'];

if ($apiKey !== $_ENV['API_KEY']) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Database connection details
$host = 'Localhost';
$user = 'root';
$pass = '';
$dbname = 'market-data';

// Get parameters
$table = $_GET['table'] ?? '';
$parts = isset($_GET['parts']) ? explode(',', $_GET['parts']) : ['*'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

// Calculate offset
$offset = ($page - 1) * $limit;

// Validate table name
if (empty($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid table name']));
}

// Connect to database
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Prepare and execute query
$columns = implode(',', array_map(function ($part) use ($conn) {
    return $conn->real_escape_string($part);
}, $parts));

$query = "SELECT $columns FROM $table LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

if ($result === false) {
    http_response_code(500);
    die(json_encode(['error' => 'Query failed: ' . $conn->error]));
}

// Fetch results
$data = $result->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$countResult = $conn->query("SELECT COUNT(*) as total FROM $table");
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// Prepare response
$response = [
    'data' => $data,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'total_records' => $totalCount
    ]
];

echo json_encode($response);

$conn->close();
