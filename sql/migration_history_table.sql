-- Migration History Table
CREATE TABLE IF NOT EXISTS migration_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    old_extension VARCHAR(10),
    new_extension VARCHAR(10),
    old_department_id INT,
    new_department_id INT,
    reason TEXT,
    changes JSON,
    admin_user VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Department Queues Junction Table
CREATE TABLE IF NOT EXISTS department_queues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    queue_name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dept_queue (department_id, queue_name),
    INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
