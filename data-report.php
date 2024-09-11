<?php
// Config
$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "market-data"
];

// Database connection
function connectToDatabase($config) {
    $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['dbname']);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Fetch data
function fetchCropData($conn) {
    $sql = "SELECT * FROM crop_data ORDER BY union_name, crop";
    return $conn->query($sql);
}

// Calculate union summary
function getUnionSummary($conn, $unionName) {
    $sql = "SELECT SUM(kgs) as total_weight, MAX(price) as highest_price, MIN(price) as lowest_price 
            FROM crop_data WHERE union_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $unionName);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}



// Generate HTML for union summary (modified to be an accordion header)
function generateUnionSummaryHTML($unionName, $crop, $date, $summary, $index) {
    $dateFormatted = date("d F, Y", strtotime($date));
    $html = "<div class='accordion-item'>";
    $html .= "<h2 class='accordion-header' id='heading{$index}'>";
    $html .= "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' 
              data-bs-target='#collapse{$index}' aria-expanded='false' aria-controls='collapse{$index}'>";
    $html .= "DAILY MARKET REPORT OF {$dateFormatted} - " . htmlspecialchars($unionName);
    $html .= "</button></h2>";
    $html .= "<div id='collapse{$index}' class='accordion-collapse collapse' 
              aria-labelledby='heading{$index}' data-bs-parent='#marketReportAccordion'>";
    $html .= "<div class='accordion-body'>";
    $html .= "<h3>SELLER: " . htmlspecialchars($unionName) . "</h3>";
    $html .= "<h3>COMMODITY: " . htmlspecialchars($crop) . "</h3>";
    $html .= "<p>HIGH PRICE: " . number_format($summary["highest_price"], 2) . " Tsh</p>";
    $html .= "<p>LOW PRICE: " . number_format($summary["lowest_price"], 2) . " Tsh</p>";
    $html .= "<p>TOTAL QUANTITY TRADED: " . number_format($summary["total_weight"], 2) . " Kgs</p>";
    return $html;
}

// Generate HTML for table (now part of the accordion content)
function generateTableHTML($data) {
    $html = "<table class='table table-striped'>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Warehouse</th>
                    <th>District</th>
                    <th>Bags</th>
                    <th>Kgs</th>
                    <th>Grade</th>
                    <th>Crop</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($data as $row) {
        $html .= "<tr>";
        $html .= "<td>{$row['date']}</td>";
        $html .= "<td>{$row['lot']}</td>";
        $html .= "<td>{$row['warehouse']}</td>";
        $html .= "<td>{$row['district']}</td>";
        $html .= "<td>{$row['bags']}</td>";
        $html .= "<td>{$row['kgs']}</td>";
        $html .= "<td>{$row['grade']}</td>";
        $html .= "<td>{$row['crop']}</td>";
        $html .= "<td>{$row['price']}</td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table>";
    $html .= "</div></div></div>";
    return $html;
}

// Main logic
$conn = connectToDatabase($config);
$result = fetchCropData($conn);

$accordionHtml = "";
$index = 0;

if ($result->num_rows > 0) {
    $currentUnion = "";
    $unionData = [];

    while($row = $result->fetch_assoc()) {
        if ($row["union_name"] != $currentUnion) {
            if ($currentUnion !== "") {
                $unionSummary = getUnionSummary($conn, $currentUnion);
                $accordionHtml .= generateUnionSummaryHTML($currentUnion, $unionData[0]["crop"], $unionData[0]["date"], $unionSummary, $index);
                $accordionHtml .= generateTableHTML($unionData);
                $index++;
            }
            $currentUnion = $row["union_name"];
            $unionData = [];
        }
        $unionData[] = $row;
    }
    
    // Handle the last union
    $unionSummary = getUnionSummary($conn, $currentUnion);
    $accordionHtml .= generateUnionSummaryHTML($currentUnion, $unionData[0]["crop"], $unionData[0]["date"], $unionSummary, $index);
    $accordionHtml .= generateTableHTML($unionData);
} else {
    $accordionHtml = "<p>No data found.</p>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Report Accordion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0c63e4;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Market Report</h1>
        <div class="accordion" id="marketReportAccordion">
            <?php echo $accordionHtml; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>