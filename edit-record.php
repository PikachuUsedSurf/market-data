<?php
require 'vendor/autoload.php';
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

$conn = connectToDatabase($config);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $message = updateRecord($conn, $_POST['record_id'], $_POST);
        $_SESSION['success_message'] = $message;
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$record = getRecord($conn, $id);

if (!$record) {
    $_SESSION['error_message'] = "Record not found";
    header("Location: index.php");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Edit Record</h2>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo "<div class='alert alert-danger'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
            unset($_SESSION['error_message']);
        }
        ?>

        <form method="post">
            <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
            <div class="mb-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($record['date']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="lot" class="form-label">Lot</label>
                <input type="text" class="form-control" id="lot" name="lot" value="<?= htmlspecialchars($record['lot']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="warehouse" class="form-label">Warehouse</label>
                <input type="text" class="form-control" id="warehouse" name="warehouse" value="<?= htmlspecialchars($record['warehouse']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="district" class="form-label">District</label>
                <input type="text" class="form-control" id="district" name="district" value="<?= htmlspecialchars($record['district']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="bags" class="form-label">Bags</label>
                <input type="number" class="form-control" id="bags" name="bags" value="<?= htmlspecialchars($record['bags']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="kgs" class="form-label">Kgs</label>
                <input type="number" class="form-control" id="kgs" name="kgs" step="0.01" value="<?= htmlspecialchars($record['kgs']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="grade" class="form-label">Grade</label>
                <input type="text" class="form-control" id="grade" name="grade" value="<?= htmlspecialchars($record['grade']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="union_name" class="form-label">Union Name</label>
                <input type="text" class="form-control" id="union_name" name="union_name" value="<?= htmlspecialchars($record['union_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="crop" class="form-label">Crop</label>
                <input type="text" class="form-control" id="crop" name="crop" value="<?= htmlspecialchars($record['crop']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" value="<?= htmlspecialchars($record['price']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="buyer" class="form-label">Buyer</label>
                <input type="text" class="form-control" id="buyer" name="buyer" value="<?= htmlspecialchars($record['buyer']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Record</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>