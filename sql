CREATE TABLE crops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    union_name VARCHAR(255) NOT NULL,
    crop VARCHAR(255) NOT NULL,
    highest_price DECIMAL(10, 2) NOT NULL,
    lowest_price DECIMAL(10, 2) NOT NULL,
    total_kgs DECIMAL(10, 2) NOT NULL,
    UNIQUE (union_name, crop)
);
