-- =========================================================
-- Migration: add judge comments ("remarks")
-- Run this ONCE against your EXISTING tabulation_system database
-- (phpMyAdmin -> SQL tab -> paste -> Go). It does NOT drop or
-- touch any existing table, so your events/scores are untouched.
-- =========================================================
USE tabulation_system;

CREATE TABLE IF NOT EXISTS remarks (
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
