-- Create users table for admin accounts
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL UNIQUE,
  passwordHash VARCHAR(255) NOT NULL,
  isActive TINYINT(1) DEFAULT 1,
  role VARCHAR(32) DEFAULT 'admin',
  createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Use the script scripts/generate-superadmin.js to generate an INSERT statement
-- and run it here to create a super admin with a bcrypt hash.
