CREATE DATABASE IF NOT EXISTS spinboost
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE spinboost;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  wallet DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  plan ENUM('NONE','REGULAR','PREMIUM','PREMIUM+') NOT NULL DEFAULT 'NONE',
  referral_code VARCHAR(20) NOT NULL UNIQUE,
  referred_by VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE spins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  stake DECIMAL(10,2) NOT NULL,
  multiplier DECIMAL(5,2) NOT NULL,
  win_amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE word_puzzles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  word VARCHAR(50) NOT NULL,
  scrambled VARCHAR(100) NOT NULL,
  user_answer VARCHAR(50) NOT NULL,
  stake DECIMAL(10,2) NOT NULL,
  reward DECIMAL(10,2) NOT NULL,
  difficulty ENUM('EASY','MEDIUM','HARD') NOT NULL,
  status ENUM('win','lose') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE deposits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  provider VARCHAR(50) NOT NULL COMMENT 'flutterwave or intasend',
  provider_reference VARCHAR(255) NOT NULL UNIQUE COMMENT 'Payment provider transaction ID',
  your_reference VARCHAR(255) NOT NULL UNIQUE COMMENT 'Your unique transaction identifier',
  status ENUM('pending','completed','failed','expired') NOT NULL DEFAULT 'pending',
  verification_timestamp TIMESTAMP NULL COMMENT 'When payment was verified',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_your_reference (your_reference),
  INDEX idx_provider_reference (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE withdrawals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
