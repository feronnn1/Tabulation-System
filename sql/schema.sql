-- =========================================================
-- Multi-Criteria Event Tabulation System
-- Database: tabulation_system
-- =========================================================

DROP DATABASE IF EXISTS tabulation_system;
CREATE DATABASE tabulation_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tabulation_system;

-- ---------------------------------------------------------
-- Table: events
-- ---------------------------------------------------------
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('upcoming', 'ongoing', 'completed') NOT NULL DEFAULT 'upcoming',
    max_score INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: contestants
-- ---------------------------------------------------------
CREATE TABLE contestants (
    contestant_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    contestant_number INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    UNIQUE KEY unique_number_per_event (event_id, contestant_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: judges (short passcode instead of username/password)
-- ---------------------------------------------------------
CREATE TABLE judges (
    judge_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    judge_name VARCHAR(150) NOT NULL,
    passcode VARCHAR(10) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: criteria (weights validated to total 100% in PHP)
-- ---------------------------------------------------------
CREATE TABLE criteria (
    criteria_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    criteria_name VARCHAR(150) NOT NULL,
    weight_percentage DECIMAL(5,2) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    CONSTRAINT chk_weight_range CHECK (weight_percentage >= 0 AND weight_percentage <= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: scores (one row per judge+contestant+criteria; upserted via AJAX)
-- ---------------------------------------------------------
CREATE TABLE scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    judge_id INT NOT NULL,
    contestant_id INT NOT NULL,
    criteria_id INT NOT NULL,
    raw_score DECIMAL(6,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (judge_id) REFERENCES judges(judge_id) ON DELETE CASCADE,
    FOREIGN KEY (contestant_id) REFERENCES contestants(contestant_id) ON DELETE CASCADE,
    FOREIGN KEY (criteria_id) REFERENCES criteria(criteria_id) ON DELETE CASCADE,
    UNIQUE KEY unique_score_entry (judge_id, contestant_id, criteria_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: remarks (one optional free-text comment per judge, per contestant)
-- ---------------------------------------------------------
CREATE TABLE remarks (
    remark_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    judge_id INT NOT NULL,
    contestant_id INT NOT NULL,
    comment TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (judge_id) REFERENCES judges(judge_id) ON DELETE CASCADE,
    FOREIGN KEY (contestant_id) REFERENCES contestants(contestant_id) ON DELETE CASCADE,
    UNIQUE KEY unique_remark_entry (judge_id, contestant_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: score_locks (once a judge confirms a contestant, no more
-- changes to that judge's scores or comment for that contestant
-- are accepted - enforced server-side, not just in the UI)
-- ---------------------------------------------------------
CREATE TABLE score_locks (
    lock_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    judge_id INT NOT NULL,
    contestant_id INT NOT NULL,
    confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (judge_id) REFERENCES judges(judge_id) ON DELETE CASCADE,
    FOREIGN KEY (contestant_id) REFERENCES contestants(contestant_id) ON DELETE CASCADE,
    UNIQUE KEY unique_lock_entry (judge_id, contestant_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table: admin_users
-- No seed row here on purpose — the app ships a one-time setup
-- page (admin/setup.php) that creates the first admin account
-- with a correctly-generated password_hash() on your own server.
-- ---------------------------------------------------------
CREATE TABLE admin_users (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- Optional sample seed data — comment out if you don't want it
-- =========================================================
INSERT INTO events (event_name, description, status, max_score)
VALUES ('Mr. & Ms. University 2026', 'Annual university pageant', 'upcoming', 100);

INSERT INTO contestants (event_id, contestant_number, full_name)
VALUES (1, 1, 'Juan Dela Cruz'), (1, 2, 'Maria Santos');

INSERT INTO criteria (event_id, criteria_name, weight_percentage, display_order)
VALUES
(1, 'Talent', 30.00, 1),
(1, 'Q&A / Intelligence', 30.00, 2),
(1, 'Poise & Presence', 20.00, 3),
(1, 'Physical Fitness', 20.00, 4);

INSERT INTO judges (event_id, judge_name, passcode)
VALUES (1, 'Judge One', 'JX9K2'), (1, 'Judge Two', 'QM3P7');
