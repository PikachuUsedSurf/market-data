CREATE TABLE crop_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE,
    lot VARCHAR(255),
    warehouse VARCHAR(255),
    district VARCHAR(255),
    bags INT,
    kgs DECIMAL(10, 2),
    grade VARCHAR(50),
    union_name VARCHAR(255),
    crop VARCHAR(255),
    price DECIMAL(10, 2),
    buyer VARCHAR(255)
);
