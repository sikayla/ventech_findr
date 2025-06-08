-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS ventech_db;
USE ventech_db;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    location VARCHAR(100),
    client_name VARCHAR(100),
    client_email VARCHAR(100),
    client_phone VARCHAR(20),
    client_address TEXT,
    role ENUM('admin', 'guest', 'client') DEFAULT 'guest',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- SAMPLE USER
-- Consider removing this INSERT or changing the credentials for production
INSERT INTO users (username, email, password, contact_number, location, role)
VALUES (
    'kaylaok1234',  -- Change this to a unique username
    'kaylatizon5@gmail.com',
    '$2y$10$4ULv/NJcXUyCZBkFQyDtr.0g6IxE5ZBlAi4pbxv2.67xdWamNEoqC', -- Consider using a stronger, unique password hash
    '09612345678',
    'Manila',
    'client'
);

INSERT INTO users (username, email, password, contact_number, location, role)
VALUES (
    'kaylaok123',   -- Change this to a unique username
    'kaylatizon7@gmail.com',
    '$2y$10$4ULv/NJcXUyCZBkFQyDtr.0g6IxE5ZBlAi4pbxv2.67xdWamNEoqC', -- Consider using a stronger, unique password hash
    '09612345679',
    'Manila',
    'guest'
);

INSERT INTO users (username, email, password, contact_number, location, role)
VALUES (
    'admin',   -- Change this to a unique username
    'kaylatizon8@gmail.com',
    '12345678', -- Consider using a stronger, unique password hash
    '09612345679',
    'Manila',
    'admin' -- Changed role to 'admin' for clarity based on typical use
);


-- VENUE TABLE
CREATE TABLE IF NOT EXISTS venue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    -- Note: You have both 'price' and 'price_per_hour'. Ensure consistency in your application logic.
    -- Assuming 'price' is intended as price per hour based on venue_reservation_form.php
    price DECIMAL(10,2) NOT NULL, -- Used as price per hour in venue_reservation_form.php and reservation_manage.php
    image_path VARCHAR(255),
    description TEXT,
    latitude DOUBLE NOT NULL DEFAULT 0,
    longitude DOUBLE NOT NULL DEFAULT 0,
    location VARCHAR(255),
    additional_info TEXT,
    virtual_tour_url VARCHAR(255) NULL,
    google_map_url VARCHAR(2083) NULL DEFAULT NULL, -- Added Google Map URL field
    reviews INT DEFAULT 0 CHECK (reviews >= 0), -- Note: this might be better calculated dynamically or updated via triggers
    num_persons VARCHAR (100), -- Consider using INT or a range type
    amenities TEXT, -- Consider a separate table for amenities if they are structured
    wifi ENUM('yes', 'no') DEFAULT 'no',
    parking ENUM('yes', 'no') DEFAULT 'no',
    status ENUM('open', 'closed') DEFAULT 'open',
    -- price_per_hour DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- This seems like a duplicate of 'price'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_lat_long ON venue(latitude, longitude);
CREATE FULLTEXT INDEX idx_venue_search ON venue(title, description);


-- RESERVATIONS TABLE
-- Added price_per_hour column as requested
CREATE TABLE IF NOT EXISTS venue_reservations (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `venue_id` INT NOT NULL,                      -- Foreign key to your venue table
  `user_id` INT NULL,                           -- Foreign key to your users table (allow NULL for guest reservations?)
  `event_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `mobile_country_code` VARCHAR(10) NULL,
  `mobile_number` VARCHAR(20) NULL,
  `address` VARCHAR(255) NULL,
  `country` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `voucher_code` VARCHAR(50) NULL,
  `total_cost` DECIMAL(10, 2) DEFAULT 0.00,
  `price_per_hour` DECIMAL(10, 2) NULL, -- Added column to store the price per hour at the time of booking
  -- Updated status ENUM to include 'cancellation_requested'
  `status` ENUM('pending', 'accepted', 'rejected', 'cancelled', 'cancellation_requested', 'completed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Optional: Add foreign key constraints if you have 'venue' and 'users' tables
  CONSTRAINT `fk_reservation_venue` FOREIGN KEY (`venue_id`) REFERENCES `venue`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reservation_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL, -- Or CASCADE depending on logic

  -- Optional: Add indexes for faster lookups
  INDEX `idx_venue_id` (`venue_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_email` (`email`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- UNAVAILABLE DATES
CREATE TABLE IF NOT EXISTS unavailable_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    unavailable_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Adding a timestamp to track when the date was added
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Automatically updates when modified
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE CASCADE,
    UNIQUE (venue_id, unavailable_date) -- Ensures that the same venue cannot have multiple entries for the same date
) ENGINE=InnoDB;


-- VENUE IMAGES
-- Note: venue_images and venue_media seem redundant if image_path and virtual_tour_url are in the venue table.
-- Consider if you need multiple images/videos per venue, in which case these tables are appropriate.
CREATE TABLE IF NOT EXISTS venue_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- VENUE DETAILS
-- This table seems to be missing from your script or is perhaps meant as a comment/placeholder?

-- MEDIA (IMAGE/VIDEO)
CREATE TABLE IF NOT EXISTS venue_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    media_type ENUM('image', 'video') NOT NULL,
    media_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- VENUE REVIEWS
CREATE TABLE IF NOT EXISTS venue_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_reviews ON venue_reviews(venue_id, rating);

-- CLIENT INFO (For storing backup client profile optionally)
-- Note: This table seems redundant with the users table and storing client info in reservations.
-- Consider if you need this table based on your specific application logic.
CREATE TABLE IF NOT EXISTS client_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT, -- This link seems unusual for a general client_info table
    client_name VARCHAR(100) NOT NULL,
    client_email VARCHAR(100) NOT NULL,
    client_phone VARCHAR(20),
    client_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE SET NULL -- This foreign key seems out of place here
) ENGINE=InnoDB;

-- USER NOTIFICATIONS TABLE
-- This table stores notifications for individual users.
CREATE TABLE IF NOT EXISTS user_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- The user who should receive this notification
    reservation_id INT NULL, -- Optional: Link to a specific reservation if the notification is reservation-related
    message TEXT NOT NULL, -- The content of the notification message
    is_read BOOLEAN NOT NULL DEFAULT FALSE, -- Status: TRUE if read, FALSE if unread
    status_changed_to VARCHAR(20) NULL, -- Optional: Stores the new status for reservation updates
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When the notification was created
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- When the notification was last updated (e.g., marked read)

    -- Foreign key constraint linking to the users table
    CONSTRAINT fk_user_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Optional: Foreign key constraint linking to the venue_reservations table
    -- Use ON DELETE CASCADE if deleting a reservation should delete its notifications
    -- Use ON DELETE SET NULL if deleting a reservation should keep the notification but set reservation_id to NULL
    CONSTRAINT fk_user_notification_reservation FOREIGN KEY (reservation_id) REFERENCES venue_reservations(id) ON DELETE CASCADE,

    -- Index for faster lookup by user and read status
    INDEX idx_user_is_read (user_id, is_read),
    -- Index for faster lookup by reservation
    INDEX idx_notification_reservation (reservation_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USER FAVORITES TABLE
-- This table stores a user's favorited venues.
CREATE TABLE IF NOT EXISTS user_favorites (
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    favorited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, venue_id), -- Ensures a user can favorite a venue only once
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- NEW TABLES FOR CHAT FUNCTIONALITY

-- CONVERSATIONS TABLE
-- Stores general information about a conversation between two users (e.g., a user and a client/owner).
-- A conversation might be specific to a venue, or a general chat.
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL, -- The ID of the first participant (e.g., the customer)
    user2_id INT NOT NULL, -- The ID of the second participant (e.g., the venue owner/client)
    venue_id INT NULL,      -- Optional: Link conversation to a specific venue if applicable
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Timestamp of the last message in this conversation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venue(id) ON DELETE SET NULL, -- If venue deleted, conversation remains
    UNIQUE (user1_id, user2_id, venue_id) -- Ensures only one conversation per pair per venue
) ENGINE=InnoDB;

-- MESSAGES TABLE
-- Stores individual messages within a conversation.
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL, -- Foreign key to the conversations table
    sender_id INT NOT NULL,       -- The user who sent the message
    receiver_id INT NOT NULL,     -- The user who received the message
    message_content TEXT NOT NULL, -- The actual message text
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN NOT NULL DEFAULT FALSE, -- Track if the message has been read by the receiver

    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_conversation_id (conversation_id),
    INDEX idx_sender_receiver (sender_id, receiver_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB;
