<?php
// Config
$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "market-data"
];

// Database connection
function connectToDatabase($config)
{
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

// Fetch data
function fetchCropData($conn)
{
    $sql = "SELECT date, district, crop, 
                   SUM(kgs) as total_kgs, 
                   MAX(price) as highest_price, 
                   MIN(price) as lowest_price,
                   COUNT(*) as transaction_count,
                   GROUP_CONCAT(CONCAT_WS('|', lot, warehouse, union_name, bags, kgs, grade, price) ORDER BY district, crop SEPARATOR ';;') as details
            FROM crop_data 
            GROUP BY date, district, crop
            ORDER BY date DESC, district, crop";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    return $result;
}

// Generate HTML for date summary (accordion header)
function generateDateSummaryHTML($date, $summaryData, $index)
{
    $dateFormatted = date("d F, Y", strtotime($date));
    $html = "<div class='accordion-item'>";
    $html .= "<h2 class='accordion-header' id='heading{$index}'>";
    $html .= "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' 
              data-bs-target='#collapse{$index}' aria-expanded='false' aria-controls='collapse{$index}'>";
    $html .= "DAILY MARKET REPORT OF {$dateFormatted}";
    $html .= "</button></h2>";
    $html .= "<div id='collapse{$index}' class='accordion-collapse collapse' 
              aria-labelledby='heading{$index}' data-bs-parent='#marketReportAccordion'>";
    $html .= "<div class='accordion-body'>";

    // Generate summary table
    $html .= "<table class='table table-striped table-hover'>";
    $html .= "<thead><tr><th>District</th><th>Crop</th><th>Total Kgs</th><th>Highest Price</th><th>Lowest Price</th></tr></thead>";
    $html .= "<tbody>";
    foreach ($summaryData as $row) {
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($row['district']) . "</td>";
        $html .= "<td>" . htmlspecialchars($row['crop']) . "</td>";
        $html .= "<td>" . number_format($row['total_kgs'], 2) . "</td>";
        $html .= "<td>" . number_format($row['highest_price'], 2) . "</td>";
        $html .= "<td>" . number_format($row['lowest_price'], 2) . "</td>";
        //$html .= "<td>" . $row['transaction_count'] . "</td>";
        $html .= "</tr>";
    }
    $html .= "</tbody></table>";

    // Generate detailed tables for each district and crop
    foreach ($summaryData as $row) {
        $html .= generateDetailedTable($row);
    }

    $html .= "</div></div></div>";
    return $html;
}

// Generate HTML for detailed table
function generateDetailedTable($data)
{
    $html = "<h4 class='mt-4'>" . htmlspecialchars($data['district']) . " - " . htmlspecialchars($data['crop']) . "</h4>";
    $html .= "<table class='table table-sm table-bordered'>";
    $html .= "<thead><tr><th>Lot</th><th>Warehouse</th><th>Union</th><th>Bags</th><th>Kgs</th><th>Grade</th><th>Price</th></tr></thead>";
    $html .= "<tbody>";

    $details = explode(";;", $data['details']);
    foreach ($details as $detail) {
        list($lot, $warehouse, $union_name, $bags, $kgs, $grade, $price) = explode("|", $detail);
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($lot) . "</td>";
        $html .= "<td>" . htmlspecialchars($warehouse) . "</td>";
        $html .= "<td>" . htmlspecialchars($union_name) . "</td>";
        $html .= "<td>" . htmlspecialchars($bags) . "</td>";
        $html .= "<td>" . number_format($kgs, 2) . "</td>";
        $html .= "<td>" . htmlspecialchars($grade) . "</td>";
        $html .= "<td>" . number_format($price, 2) . "</td>";
        $html .= "</tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}

// Main logic
try {
    $conn = connectToDatabase($config);
    $result = fetchCropData($conn);

    $accordionHtml = "";
    $index = 0;
    $currentDate = null;
    $dateSummary = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($row["date"] != $currentDate) {
                if ($currentDate !== null) {
                    $accordionHtml .= generateDateSummaryHTML($currentDate, $dateSummary, $index);
                    $index++;
                }
                $currentDate = $row["date"];
                $dateSummary = [];
            }
            $dateSummary[] = $row;
        }
        // Handle the last date
        if ($currentDate !== null) {
            $accordionHtml .= generateDateSummaryHTML($currentDate, $dateSummary, $index);
        }
    } else {
        $accordionHtml = "<p>No data found.</p>";
    }

    $conn->close();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date-based Market Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0c63e4;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <h1 class="mb-4">Date-based Market Report</h1>
        <div class="accordion" id="marketReportAccordion">
            <?php echo $accordionHtml; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>