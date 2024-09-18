<?php

// Database Config
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

// Function to get region based on district
function getRegion($district)
{
    $regionMap = [
        'Chemba' => 'Dodoma',
        'KONDOA' => 'Dodoma',
        'Babati' => 'Manyara',
        'Mkalama' => 'Mkalama',
        'Hannang' => 'Manyara',
        'ITIGI' => 'Singida',
        'Singida' => 'Singida',
        'KILWA' => 'Lindi',
        'LINDI' => 'Lindi',
        'LINDI_RURAL' => 'Lindi',
        'Nyaghwale' => 'Geita',

        // Add more regions hereee
    ];
    return isset($regionMap[$district]) ? $regionMap[$district] : 'Unknown';
}

// Fetch data
function fetchCropData($conn)
{
    $sql = "SELECT date, district, union_name, crop, 
                   SUM(kgs) as total_kgs, 
                   MAX(price) as highest_price, 
                   MIN(price) as lowest_price,
                   COUNT(*) as transaction_count,
                   GROUP_CONCAT(CONCAT_WS('|', lot, warehouse, bags, kgs, grade, price) ORDER BY union_name, crop SEPARATOR ';;') as details
            FROM crop_data 
            GROUP BY date, district, union_name, crop
            ORDER BY date DESC, district, union_name, crop";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    return $result;
}

// Generate HTML for date summary (accordion header)
function generateDateSummaryHTML($date, $regionData, $index)
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

    // Generate HTML for regions
    foreach ($regionData as $region => $districtData) {
        $html .= "<h3>{$region} Region</h3>";
        $html .= "<div class='accordion' id='region-{$region}'>"; // Start region accordion

        // Generate HTML for districts within the region
        foreach ($districtData as $district => $unionData) {
            $html .= "<div class='accordion-item'>";
            $html .= "<h4 class='accordion-header' id='heading-{$district}'>";
            $html .= "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' 
                      data-bs-target='#collapse-{$district}' aria-expanded='false' aria-controls='collapse-{$district}'>";
            $html .= "{$district} District";
            $html .= "</button></h4>";
            $html .= "<div id='collapse-{$district}' class='accordion-collapse collapse' 
                      aria-labelledby='heading-{$district}' data-bs-parent='#region-{$region}'>";
            $html .= "<div class='accordion-body'>";

            // Generate HTML for unions within the district
            foreach ($unionData as $union => $cropData) {
                $html .= "<h5 class='mt-3'>{$union} Union</h5>";
                $html .= generateUnionDetailTable($cropData);
            }

            $html .= "</div></div></div>";
        }

        $html .= "</div>"; // End region accordion
    }

    $html .= "</div></div></div>";
    return $html;
}

// Generate HTML for region summary table
function generateRegionSummaryTable($districtData)
{
    $html = "<table class='table table-striped table-hover'>";
    $html .= "<thead><tr><th>District</th><th>Total Kgs</th><th>Highest Price</th><th>Lowest Price</th></tr></thead>";
    $html .= "<tbody>";
    foreach ($districtData as $district => $unionData) {
        $totalKgs = $highestPrice = 0;
        $lowestPrice = PHP_INT_MAX;
        foreach ($unionData as $union => $cropData) {
            foreach ($cropData as $crop => $data) {
                $totalKgs += $data['total_kgs'];
                $highestPrice = max($highestPrice, $data['highest_price']);
                $lowestPrice = min($lowestPrice, $data['lowest_price']);
            }
        }
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($district) . "</td>";
        $html .= "<td>" . number_format($totalKgs, 2) . "</td>";
        $html .= "<td>" . number_format($highestPrice, 2) . "</td>";
        $html .= "<td>" . number_format($lowestPrice, 2) . "</td>";
        $html .= "</tr>";
    }
    $html .= "</tbody></table>";
    return $html;
}

// Generate HTML for district summary table
function generateDistrictSummaryTable($unionData)
{
    $html = "<table class='table table-striped table-hover'>";
    $html .= "<thead><tr><th>Union</th><th>Crop</th><th>Total Kgs</th><th>Highest Price</th><th>Lowest Price</th></tr></thead>";
    $html .= "<tbody>";
    foreach ($unionData as $union => $cropData) {
        foreach ($cropData as $crop => $data) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($union) . "</td>";
            $html .= "<td>" . htmlspecialchars($crop) . "</td>";
            $html .= "<td>" . number_format($data['total_kgs'], 2) . "</td>";
            $html .= "<td>" . number_format($data['highest_price'], 2) . "</td>";
            $html .= "<td>" . number_format($data['lowest_price'], 2) . "</td>";
            $html .= "</tr>";
        }
    }
    $html .= "</tbody></table>";
    return $html;
}

// Generate HTML for union detail table
function generateUnionDetailTable($cropData)
{
    $html = "<table class='table table-sm table-bordered'>";
    $html .= "<thead><tr><th>Crop</th><th>Lot</th><th>Warehouse</th><th>Bags</th><th>Kgs</th><th>Grade</th><th>Price</th></tr></thead>";
    $html .= "<tbody>";
    foreach ($cropData as $crop => $data) {
        $details = explode(";;", $data['details']);
        foreach ($details as $detail) {
            list($lot, $warehouse, $bags, $kgs, $grade, $price) = explode("|", $detail);
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($crop) . "</td>";
            $html .= "<td>" . htmlspecialchars($lot) . "</td>";
            $html .= "<td>" . htmlspecialchars($warehouse) . "</td>";
            $html .= "<td>" . htmlspecialchars($bags) . "</td>";
            $html .= "<td>" . number_format($kgs, 2) . "</td>";
            $html .= "<td>" . htmlspecialchars($grade) . "</td>";
            $html .= "<td>" . number_format($price, 2) . "</td>";
            $html .= "</tr>";
        }
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
    $dateData = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $date = $row['date'];
            $region = getRegion($row['district']);
            $district = $row['district'];
            $union = $row['union_name'];
            $crop = $row['crop'];

            if ($date != $currentDate) {
                if ($currentDate !== null) {
                    $accordionHtml .= generateDateSummaryHTML($currentDate, $dateData, $index);
                    $index++;
                }
                $currentDate = $date;
                $dateData = [];
            }

            $dateData[$region][$district][$union][$crop] = $row;
        }
        // Handle the last date
        if ($currentDate !== null) {
            $accordionHtml .= generateDateSummaryHTML($currentDate, $dateData, $index);
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
    <title>Market Report: Date, Region, and Union Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0c63e4;
        }

        .table {
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <h1 class="mb-4">Market Report: Date, Region, and Union Summary</h1>
        <div class="accordion" id="marketReportAccordion">
            <?php echo $accordionHtml; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>