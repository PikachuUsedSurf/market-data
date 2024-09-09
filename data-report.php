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

// Fetch all data from the table, ordered by union and then crop
$sql = "SELECT * FROM crop_data ORDER BY union_name, crop";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Data Report</title>
    <style>
        body {
            font-family: sans-serif;
        }
        table {
            width: 80%;
            margin: 20px auto;
            border-collapse: collapse;
            border-spacing: 0;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        h3 {
            text-align: center;
            margin-bottom: 10px;
        }
        p {
            text-align: center;
        }
        .union-summary {
            text-align: left;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php
if ($result->num_rows > 0) {
    $currentUnion = "";
    $currentCrop = "";

    while($row = $result->fetch_assoc()) {
        // Start a new table for a new union
        if ($row["union_name"] != $currentUnion) {
            if ($currentUnion !== "") {
                echo "</table>";
            }

            $currentUnion = $row["union_name"];
            $currentCrop = $row["crop"];
            
            

            // Calculate and display union summary
            $unionSummary = getUnionSummary($conn, $currentUnion);
            echo "<div class='union-summary'>";
            echo "<h3>Seller: " . htmlspecialchars($currentUnion) . "</h3>";
            echo "<h3>Crop: " . htmlspecialchars($currentCrop) . "</h3>";
            echo "<p>Total Weight: " . number_format($unionSummary["total_weight"], 2) . " Kgs</p>";
            echo "<p>Highest Price: " . number_format($unionSummary["highest_price"], 2) . " Tsh</p>";
            echo "<p>Lowest Price: " . number_format($unionSummary["lowest_price"], 2) . " Tsh</p>";
            echo "</div>";

            echo "<table border='1'>";
            echo "<tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Warehouse</th>
                    <th>District</th>
                    <th>Bags</th>
                    <th>Kgs</th>
                    <th>Grade</th>
                    <th>Crop</th>
                    <th>Price</th>
                  </tr>";
        }

        // Add separator for new crop within the union
        if ($row["crop"] != $currentCrop) {
            if (isset($currentCrop)) {
                echo "<tr><td colspan='10'></td></tr>";
            }
            $currentCrop = $row["crop"];
            echo "<tr><td colspan='10'><strong>Crop: " . $currentCrop . "</strong></td></tr>";
        }

        // Display row data
        echo "<tr>";
        echo "<td>" . $row["date"] . "</td>";
        echo "<td>" . $row["lot"] . "</td>";
        echo "<td>" . $row["warehouse"] . "</td>";
        echo "<td>" . $row["district"] . "</td>";
        echo "<td>" . $row["bags"] . "</td>";
        echo "<td>" . $row["kgs"] . "</td>";
        echo "<td>" . $row["grade"] . "</td>";
        echo "<td>" . $row["crop"] . "</td>";
        echo "<td>" . $row["price"] . "</td>";
        echo "</tr>";
    }

    // Close the last table
    echo "</table>";

} else {
    echo "<h3>No data found.</h3>";
}
$conn->close();

// Function to calculate union summary
function getUnionSummary($conn, $unionName) {
    $sql = "SELECT SUM(kgs) as total_weight, MAX(price) as highest_price, MIN(price) as lowest_price FROM crop_data WHERE union_name = '$unionName'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

?>

</body>
</html>