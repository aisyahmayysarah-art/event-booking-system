CREATE DATABASE IF NOT EXISTS event_booking_db;
USE event_booking_db;


/* =====================================
   USERS TABLE
===================================== */

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM(
        'Administrator',
        'Organiser',
        'Staff',
        'Customer'
    ) DEFAULT 'Customer'
);


/* =====================================
   VENUES TABLE
===================================== */

CREATE TABLE venues (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    status ENUM(
        'Available',
        'Unavailable'
    ) DEFAULT 'Available',

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);


/* =====================================
   EVENTS TABLE
===================================== */

CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    venue_id INT NOT NULL,
    organiser_id INT NOT NULL,
    event_date DATETIME NOT NULL,
    ticket_price DECIMAL(10,2) NOT NULL,
    total_tickets INT NOT NULL,
    available_tickets INT NOT NULL,

    status ENUM(
        'draft',
        'published',
        'cancelled',
        'completed'
    ) DEFAULT 'draft',

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (venue_id)
        REFERENCES venues(venue_id),

    FOREIGN KEY (organiser_id)
        REFERENCES users(user_id)
);


/* =====================================
   BOOKINGS TABLE
===================================== */

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,

    booking_date TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    booking_status ENUM(
        'Pending',
        'Confirmed',
        'Cancelled'
    ) DEFAULT 'Pending',

    FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    FOREIGN KEY (event_id)
        REFERENCES events(event_id)
);