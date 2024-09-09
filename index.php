<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload XLSX File</title>
</head>
<body>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <label for="file">Choose an XLSX file:</label>
        <input type="file" name="file" id="file" accept=".xlsx" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
