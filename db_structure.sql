-- =====================================================
-- IT1208 Web Technologies - CA2
-- Unawatuna Cove Villa, Mirissa - Booking System
-- Database structure and sample data
-- Works with MySQL 8+ / MariaDB 10.4+ (XAMPP / WAMP)
-- =====================================================

DROP DATABASE IF EXISTS villa_booking;
CREATE DATABASE villa_booking
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE villa_booking;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE users (
    user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    contact_number VARCHAR(20)  NOT NULL,
    password       VARCHAR(255) NOT NULL,          -- password_hash() output
    role           ENUM('guest','admin') NOT NULL DEFAULT 'guest',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: rooms
-- -----------------------------------------------------
CREATE TABLE rooms (
    room_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_name       VARCHAR(100) NOT NULL,
    room_type       VARCHAR(50)  NOT NULL,
    description     TEXT NOT NULL,
    facilities      VARCHAR(255) NOT NULL,          -- comma-separated list
    price_per_night DECIMAL(10,2) NOT NULL,         -- LKR
    max_guests      TINYINT UNSIGNED NOT NULL,
    image           VARCHAR(255) NOT NULL DEFAULT 'room1.jpg',
    room_status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id),
    KEY idx_rooms_status_type (room_status, room_type),
    CONSTRAINT chk_rooms_price  CHECK (price_per_night > 0),
    CONSTRAINT chk_rooms_guests CHECK (max_guests > 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: bookings
-- -----------------------------------------------------
CREATE TABLE bookings (
    booking_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    room_id        INT UNSIGNED NOT NULL,
    guests         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    check_in       DATE NOT NULL,
    check_out      DATE NOT NULL,
    total_amount   DECIMAL(10,2) NOT NULL,
    booking_status ENUM('Pending','Confirmed','Cancelled') NOT NULL DEFAULT 'Pending',
    special_requests VARCHAR(500) NULL,
    booking_date   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (booking_id),
    KEY idx_bookings_user (user_id),
    KEY idx_bookings_room_dates (room_id, booking_status, check_in, check_out),
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_room
        FOREIGN KEY (room_id) REFERENCES rooms (room_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_bookings_dates  CHECK (check_out > check_in),
    CONSTRAINT chk_bookings_guests CHECK (guests > 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Sample data: users
-- Password for ALL sample accounts is:  password
-- (stored as a bcrypt hash created with PHP password_hash)
-- -----------------------------------------------------
INSERT INTO users (user_id, name, email, contact_number, password, role) VALUES
(1, 'Villa Administrator', 'admin@bluelagoonvilla.lk', '0771234567',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Nimali Perera',   'nimali@example.com', '0712345678',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guest'),
(3, 'Kasun Fernando',  'kasun@example.com',  '0723456789',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guest'),
(4, 'Sarah Johnson',   'sarah@example.com',  '+447700900123',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guest');

-- -----------------------------------------------------
-- Sample data: rooms (prices in LKR)
-- -----------------------------------------------------
INSERT INTO rooms (room_id, room_name, room_type, description, facilities, price_per_night, max_guests, image, room_status) VALUES
(1, 'Ocean View Deluxe', 'Deluxe',
 'A spacious air-conditioned room with a private balcony overlooking the Indian Ocean. Wake up to the sound of waves and enjoy spectacular sunrises.',
 'Air Conditioning,Free Wi-Fi,Sea View Balcony,King Bed,Hot Water,Mini Fridge',
 18500.00, 2, 'room1.jpg', 'active'),
(2, 'Garden Standard Double', 'Standard',
 'A cosy double room surrounded by tropical gardens, just a short walk from the beach. Ideal for budget-friendly stays.',
 'Fan,Free Wi-Fi,Garden View,Double Bed,Hot Water',
 11000.00, 2, 'room2.jpg', 'active'),
(3, 'Family Suite', 'Suite',
 'Two connected bedrooms with a living area, perfect for families. Includes a private terrace and a kitchenette.',
 'Air Conditioning,Free Wi-Fi,Kitchenette,Living Area,Terrace,Hot Water',
 27500.00, 4, 'room3.jpg', 'active'),
(4, 'Beachfront Superior', 'Superior',
 'Step straight onto the sand from your own doorstep. Features a modern bathroom and an outdoor seating area facing the beach.',
 'Air Conditioning,Free Wi-Fi,Beach Access,Queen Bed,Hot Water,Outdoor Seating',
 22000.00, 3, 'room4.jpg', 'active'),
(5, 'Twin Room', 'Standard',
 'A comfortable room with two single beds, ideal for friends or colleagues travelling together.',
 'Fan,Free Wi-Fi,Twin Beds,Hot Water,Garden View',
 10500.00, 2, 'room5.jpg', 'active'),
(6, 'Honeymoon Pool Villa', 'Villa',
 'Our most exclusive accommodation with a private plunge pool, open-air bathroom and direct ocean views. Perfect for couples.',
 'Air Conditioning,Free Wi-Fi,Private Pool,Open-Air Bathroom,King Bed,Breakfast Included',
 35000.00, 2, 'room6.jpg', 'active');

-- -----------------------------------------------------
-- Sample data: bookings
-- -----------------------------------------------------
INSERT INTO bookings (booking_id, user_id, room_id, guests, check_in, check_out, total_amount, booking_status, special_requests) VALUES
(1, 2, 1, 2, '2026-12-20', '2026-12-24',  74000.00, 'Confirmed', 'Late check-in around 9 PM.'),
(2, 3, 2, 2, '2026-12-22', '2026-12-25',  33000.00, 'Pending',   NULL),
(3, 4, 3, 4, '2027-01-05', '2027-01-08',  82500.00, 'Confirmed', 'Need two extra pillows.'),
(4, 2, 4, 2, '2027-01-10', '2027-01-12',  44000.00, 'Cancelled', NULL),
(5, 3, 1, 2, '2026-12-26', '2026-12-28',  37000.00, 'Pending',   'Anniversary trip - decoration if possible.'),
(6, 4, 6, 2, '2027-02-14', '2027-02-17', 105000.00, 'Pending',   NULL);
