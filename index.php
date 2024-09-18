<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

$config = [
    'servername' => "localhost",
    'username' => "root",
    'password' => "",
    'dbname' => "market-data"
];

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

// Process file upload
function processFileUpload($conn)
{
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed. Please try again.");
    }

    $file = $_FILES['file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $stmt = $conn->prepare("INSERT INTO crop_data (date, lot, warehouse, district, bags, kgs, grade, union_name, crop, price, buyer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssidsssds", $date, $lot, $warehouse, $district, $bags, $kgs, $grade, $union_name, $crop, $price, $buyer);

    foreach ($rows as $index => $row) {
        if ($index === 0) continue; // Skip header row

        $date = date('Y-m-d', strtotime($row[0]));
        $lot = $row[1];
        $warehouse = $row[2];
        $district = $row[3];
        $bags = (int)str_replace(',', '', $row[4]);
        $kgs = (float)str_replace(',', '', $row[5]);
        $grade = $row[6];
        $union_name = $row[7];
        $crop = $row[8];
        $price = (int)str_replace(',', '', $row[9]);
        $buyer = $row[10];

        if (!$stmt->execute()) {
            throw new Exception("Error inserting row: " . $stmt->error);
        }
    }

    $stmt->close();
    return count($rows) - 1; // Subtract 1 for header row
}

// Delete all data
function deleteAllData($conn)
{
    $sql = "DELETE FROM crop_data";
    if (!$conn->query($sql)) {
        throw new Exception("Error deleting data: " . $conn->error);
    }
    return $conn->affected_rows;
}

function fetchData($conn, $limit = 10, $offset = 0)
{
    $sql = "SELECT * FROM crop_data ORDER BY date DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result();
}

function deleteRecord($conn, $id)
{
    $sql = "DELETE FROM crop_data WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        return "Record deleted successfully";
    } else {
        throw new Exception("Error deleting record: " . $conn->error);
    }
}

function getRecord($conn, $id)
{
    $sql = "SELECT * FROM crop_data WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function updateRecord($conn, $id, $data)
{
    $sql = "UPDATE crop_data SET date = ?, lot = ?, warehouse = ?, district = ?, bags = ?, kgs = ?, grade = ?, union_name = ?, crop = ?, price = ?, buyer = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssidsssdsi", $data['date'], $data['lot'], $data['warehouse'], $data['district'], $data['bags'], $data['kgs'], $data['grade'], $data['union_name'], $data['crop'], $data['price'], $data['buyer'], $id);
    if ($stmt->execute()) {
        return "Record updated successfully";
    } else {
        throw new Exception("Error updating record: " . $conn->error);
    }
}

function addRecord($conn, $data)
{
    $sql = "INSERT INTO crop_data (date, lot, warehouse, district, bags, kgs, grade, union_name, crop, price, buyer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssidsssdss", $data['date'], $data['lot'], $data['warehouse'], $data['district'], $data['bags'], $data['kgs'], $data['grade'], $data['union_name'], $data['crop'], $data['price'], $data['buyer']);
    if ($stmt->execute()) {
        return "Record added successfully";
    } else {
        throw new Exception("Error adding record: " . $conn->error);
    }
}

$conn = connectToDatabase($config);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['submit'])) {
            $message = processFileUpload($conn);
        } elseif (isset($_POST['delete_all'])) {
            $message = deleteAllData($conn);
        } elseif (isset($_POST['delete_record'])) {
            $message = deleteRecord($conn, $_POST['record_id']);
        } elseif (isset($_POST['update_record'])) {
            $message = updateRecord($conn, $_POST['record_id'], $_POST);
        } elseif (isset($_POST['add_record'])) {
            $message = addRecord($conn, $_POST);
        }
        $_SESSION['success_message'] = $message;
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$result = fetchData($conn, $limit, $offset);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Data Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Market Data Management</h2>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo "<div class='alert alert-danger'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
            unset($_SESSION['error_message']);
        }
        ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Upload XLSX to MySQL</h5>
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="file" class="form-label">Select XLSX file to upload:</label>
                        <input type="file" class="form-control" name="file" id="file" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="submit">Upload XLSX</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Delete All Data</h5>
                <form method="post" onsubmit="return confirm('Are you sure you want to delete all data? This action cannot be undone.');">
                    <button type="submit" class="btn btn-danger" name="delete_all">Delete All Data</button>
                </form>
            </div>
        </div>

        <h3 class="mb-3">Data Records</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>District</th>
                    <th>Crop</th>
                    <th>Kgs</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['district']) ?></td>
                        <td><?= htmlspecialchars($row['crop']) ?></td>
                        <td><?= htmlspecialchars(string: $row['kgs']) ?></td>
                        <td><?= htmlspecialchars($row['price']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editRecord(<?= $row['id'] ?>)">Edit</button>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                <input type="hidden" name="record_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" name="delete_record">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a></li>
                <?php endif; ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
            </ul>
        </nav>

        <button class="btn btn-success mb-3" onclick="showAddForm()">Add New Record</button>

        <div id="recordForm" style="display: none;">
            <h4 id="formTitle">Add/Edit Record</h4>
            <form method="post">
                <input type="hidden" name="record_id" id="record_id">
                <div class="mb-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" required>
                </div>
                <div class="mb-3">
                    <label for="lot" class="form-label">Lot</label>
                    <input type="text" class="form-control" id="lot" name="lot" required>
                </div>
                <div class="mb-3">
                    <label for="warehouse" class="form-label">Warehouse</label>
                    <input type="text" class="form-control" id="warehouse" name="warehouse" required>
                </div>
                <div class="mb-3">
                    <label for="district" class="form-label">District</label>
                    <input type="text" class="form-control" id="district" name="district" required>
                </div>
                <div class="mb-3">
                    <label for="bags" class="form-label">Bags</label>
                    <input type="number" class="form-control" id="bags" name="bags" required>
                </div>
                <div class="mb-3">
                    <label for="kgs" class="form-label">Kgs</label>
                    <input type="number" class="form-control" id="kgs" name="kgs" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="grade" class="form-label">Grade</label>
                    <input type="text" class="form-control" id="grade" name="grade" required>
                </div>
                <div class="mb-3">
                    <label for="union_name" class="form-label">Union Name</label>
                    <input type="text" class="form-control" id="union_name" name="union_name" required>
                </div>
                <div class="mb-3">
                    <label for="crop" class="form-label">Crop</label>
                    <input type="text" class="form-control" id="crop" name="crop" required>
                </div>
                <div class="mb-3">
                    <label for="price" class="form-label">Price</label>
                    <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="buyer" class="form-label">Buyer</label>
                    <input type="text" class="form-control" id="buyer" name="buyer" required>
                </div>
                <button type="submit" class="btn btn-primary" name="add_record" id="submitBtn">Add Record</button>
                <button type="button" class="btn btn-secondary" onclick="hideForm()">Cancel</button>
            </form>
        </div>
        <a href="/market-data/data-report.php" class="btn btn-secondary">View Data Report sorted by UNION</a>
        <a href="/market-data/data-report-district.php" class="btn btn-secondary">View Data Report sorted by DISTRICT</a>
        <a href="/market-data/data-report-region.php" class="btn btn-secondary">View Data Report sorted by REGION</a>
        <a href="/market-data/data-report-date.php" class="btn btn-secondary">View Data Report sorted by Date</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('formTitle').textContent = 'Add New Record';
            document.getElementById('submitBtn').textContent = 'Add Record';
            document.getElementById('submitBtn').name = 'add_record';
            document.getElementById('recordForm').style.display = 'block';
            document.getElementById('record_id').value = '';
            document.getElementById('date').value = '';
            document.getElementById('lot').value = '';
            document.getElementById('warehouse').value = '';
            document.getElementById('district').value = '';
            document.getElementById('bags').value = '';
            document.getElementById('kgs').value = '';
            document.getElementById('grade').value = '';
            document.getElementById('union_name').value = '';
            document.getElementById('crop').value = '';
            document.getElementById('price').value = '';
            document.getElementById('buyer').value = '';
        }

        function editRecord(id) {
            fetch(`get_record.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('formTitle').textContent = 'Edit Record';
                    document.getElementById('submitBtn').textContent = 'Update Record';
                    document.getElementById('submitBtn').name = 'update_record';
                    document.getElementById('recordForm').style.display = 'block';
                    document.getElementById('record_id').value = data.id;
                    document.getElementById('date').value = data.date;
                    document.getElementById('lot').value = data.lot;
                    document.getElementById('warehouse').value = data.warehouse;
                    document.getElementById('district').value = data.district;
                    document.getElementById('bags').value = data.bags;
                    document.getElementById('kgs').value = data.kgs;
                    document.getElementById('grade').value = data.grade;
                    document.getElementById('union_name').value = data.union_name;
                    document.getElementById('crop').value = data.crop;
                    document.getElementById('price').value = data.price;
                    document.getElementById('buyer').value = data.buyer;
                });
        }

        function hideForm() {
            document.getElementById('recordForm').style.display = 'none';
        }
    </script>
</body>

</html>