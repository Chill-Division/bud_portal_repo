-- BUD Database Schema

SET FOREIGN_KEY_CHECKS=0;

-- 1. Suppliers Table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255),
  `email` VARCHAR(255),
  `phone` VARCHAR(50),
  `address` TEXT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` BOOLEAN DEFAULT TRUE
);

-- 2. Stock Items Table
CREATE TABLE IF NOT EXISTS `stock_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT,
  `name` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(100),
  `category` ENUM('Raw Material', 'Finished Product', 'Packaging', 'Sticker', 'Insert', 'Other') NOT NULL DEFAULT 'Other',
  `description` TEXT,
  `quantity` DECIMAL(10, 2) DEFAULT 0.00,
  `unit` VARCHAR(50) DEFAULT 'units', -- e.g., kg, g, units, boxes
  `reorder_level` DECIMAL(10, 2) DEFAULT 0.00,
  `is_controlled` BOOLEAN DEFAULT FALSE, -- controlled substance flag
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
);

-- 3. Audit Log (Atomic History)
-- Captures ALL changes for replayability
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `table_name` VARCHAR(50) NOT NULL,
  `record_id` INT NOT NULL,
  `action` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
  `changed_by` VARCHAR(100) DEFAULT 'SYSTEM', -- Can be populated via app logic
  `old_values` JSON, -- Snapshot of data before change
  `new_values` JSON, -- Snapshot of data after change
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Staff Time Logs
CREATE TABLE IF NOT EXISTS `time_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_name` VARCHAR(100) NOT NULL,
  `action` ENUM('IN', 'OUT') NOT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT
);

-- 5. Cleaning Schedules
CREATE TABLE IF NOT EXISTS `cleaning_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `frequency` ENUM('Daily', 'Weekly', 'Fortnightly', 'Monthly') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active` BOOLEAN DEFAULT TRUE
);

-- 6. Cleaning Logs
CREATE TABLE IF NOT EXISTS `cleaning_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `schedule_id` INT NOT NULL,
  `staff_name` VARCHAR(100) NOT NULL,
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  FOREIGN KEY (`schedule_id`) REFERENCES `cleaning_schedules`(`id`)
);

-- 7. Chain of Custody (COC)
CREATE TABLE IF NOT EXISTS `chain_of_custody` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `form_date` DATE NOT NULL,
  `origin` VARCHAR(255) DEFAULT 'Main Facility',
  `destination` VARCHAR(255) NOT NULL,
  `transported_by` VARCHAR(255) NOT NULL,
  `received_by` VARCHAR(255),
  `coc_items` JSON NOT NULL, -- Array of items: {name, batch, qty, etc.}
  `signature_image` LONGTEXT, -- Base64 encoded image
  `status` ENUM('In Transit', 'Completed', 'Cancelled') DEFAULT 'In Transit',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL
);

-- 8. Materials Out Report Log (Monthly)
CREATE TABLE IF NOT EXISTS `materials_out_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `report_month` VARCHAR(7) NOT NULL, -- Format YYYY-MM
  `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `report_data` JSON -- Snapshot of the report data
);

SET FOREIGN_KEY_CHECKS=1;
