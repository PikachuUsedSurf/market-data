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
    $sql = "SELECT * FROM crop_data ORDER BY district, crop";
    return $conn->query($sql);
}

// Calculate district summary
function getDistrictSummary($conn, $district) {
    $sql = "SELECT SUM(kgs) as total_weight, MAX(price) as highest_price, MIN(price) as lowest_price 
            FROM crop_data WHERE district = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $district);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Generate HTML for district summary (accordion header)
function generateDistrictSummaryHTML($district, $crop, $date, $summary, $index) {
    $dateFormatted = date("d F, Y", strtotime($date));
    $html = "<div class='accordion-item'>";
    $html .= "<h2 class='accordion-header' id='heading{$index}'>";
    $html .= "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' 
              data-bs-target='#collapse{$index}' aria-expanded='false' aria-controls='collapse{$index}'>";
    $html .= "DAILY MARKET REPORT OF {$dateFormatted} - " . htmlspecialchars($district);
    $html .= "</button></h2>";
    $html .= "<div id='collapse{$index}' class='accordion-collapse collapse' 
              aria-labelledby='heading{$index}' data-bs-parent='#marketReportAccordion'>";
    $html .= "<div class='accordion-body'>";
    $html .= "<h3>DISTRICT: " . htmlspecialchars($district) . "</h3>";
    $html .= "<h3>COMMODITY: " . htmlspecialchars($crop) . "</h3>";
    $html .= "<p>HIGH PRICE: " . number_format($summary["highest_price"], 2) . " Tsh</p>";
    $html .= "<p>LOW PRICE: " . number_format($summary["lowest_price"], 2) . " Tsh</p>";
    $html .= "<p>TOTAL QUANTITY TRADED: " . number_format($summary["total_weight"], 2) . " Kgs</p>";
    return $html;
}

// Generate HTML for table (part of the accordion content)
function generateTableHTML($data) {
    $html = "<table class='table table-striped'>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Warehouse</th>
                    <th>Union</th>
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
        $html .= "<td>{$row['union_name']}</td>";
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
    $currentDistrict = "";
    $districtData = [];

    while($row = $result->fetch_assoc()) {
        if ($row["district"] != $currentDistrict) {
            if ($currentDistrict !== "") {
                $districtSummary = getDistrictSummary($conn, $currentDistrict);
                $accordionHtml .= generateDistrictSummaryHTML($currentDistrict, $districtData[0]["crop"], $districtData[0]["date"], $districtSummary, $index);
                $accordionHtml .= generateTableHTML($districtData);
                $index++;
            }
            $currentDistrict = $row["district"];
            $districtData = [];
        }
        $districtData[] = $row;
    }
    
    // Handle the last district
    $districtSummary = getDistrictSummary($conn, $currentDistrict);
    $accordionHtml .= generateDistrictSummaryHTML($currentDistrict, $districtData[0]["crop"], $districtData[0]["date"], $districtSummary, $index);
    $accordionHtml .= generateTableHTML($districtData);
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
    <title>District-based Market Report</title>
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
        <h1 class="mb-4">District-based Market Report</h1>
        <div class="accordion" id="marketReportAccordion">
            <?php echo $accordionHtml; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>