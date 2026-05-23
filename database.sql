-- =====================================================================
-- FinTrack ET (ፋይናንስ ትራክ) - Relational MySQL Database Schema
-- Tailored for Micro and Small Businesses in Addis Ababa, Ethiopia
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `fintrack_et` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fintrack_et`;

-- 1. USERS TABLE (Business Owners Account Registry)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `business_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CUSTOMERS TABLE (Debtors CRM Management)
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `shop_name` VARCHAR(100) DEFAULT NULL,
    `location` VARCHAR(100) DEFAULT NULL, -- Addis Ababa Subcity (e.g., Bole, Piazza, Kolfe)
    `debt_balance` DECIMAL(12,2) DEFAULT 0.00,
    `last_active` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_customer` (`user_id`),
    INDEX `idx_customer_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PRODUCTS TABLE (Inventory Management)
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_products` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3b. SUPPLIERS TABLE (Stock Suppliers Registry)
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `debt_balance` DECIMAL(12,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_supplier` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TRANSACTIONS TABLE (Shared Sales and Expenses Ledger)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `customer_id` INT DEFAULT NULL, -- Linked only if it's a credit sale
    `product_id` INT DEFAULT NULL, -- Linked if the sale is from inventory
    `date` DATE NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `type` ENUM('sale', 'expense', 'purchase') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `status` ENUM('paid', 'credit') NOT NULL DEFAULT 'paid',
    `due_date` DATE DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    `cost_price` DECIMAL(12,2) DEFAULT NULL,
    `quantity` INT DEFAULT 1,
    `supplier_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    INDEX `idx_user_tx` (`user_id`),
    INDEX `idx_tx_type` (`type`),
    INDEX `idx_tx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PAYMENTS TABLE (Installment Payments history for Credits)
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `method` VARCHAR(50) NOT NULL DEFAULT 'cash', -- cash, telebirr, bank_transfer
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_payments` (`user_id`),
    INDEX `idx_payment_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEEDING SAMPLE DATA (Realistic Ethiopian Business Profiles)
-- =====================================================================

-- Seed 1 User: Almaz Tesfaye (Almaz Grocery - Bole)
-- Plaintext Password is "admin123" (Hashed via PHP password_hash BCrypt)
INSERT INTO `users` (`id`, `username`, `password`, `business_name`) 
VALUES (1, 'almaz', '$2y$10$7Z8bUaR3ZtZk0h4v23v9du8lQ1w1/HkM0V0/P4T6N8Q1.lR2cEFe.', 'Almaz Grocery');

-- Seed Customers linked to Almaz Grocery (user_id = 1)
INSERT INTO `customers` (`id`, `user_id`, `name`, `phone`, `shop_name`, `location`, `debt_balance`, `last_active`) VALUES
(1, 1, 'Abebe Kebede', '0911554433', 'Kebede Kiosk', 'Piazza, Addis Ababa', 3400.00, '2026-05-18'),
(2, 1, 'Marta Balcha', '0912334455', 'Marta Fruit Shop', 'Merkato, Addis Ababa', 750.00, '2026-05-20'),
(3, 1, 'Dr. Yonas Hailu', '0922887766', 'Yonas Dental Clinic', 'Piazza, Addis Ababa', 0.00, '2026-05-15'),
(4, 1, 'Kibrom Tekle', '0933990011', 'Kibrom Stationery', 'Bole, Addis Ababa', 1500.00, '2026-05-19');

-- Seed Products for Almaz Grocery (user_id = 1)
INSERT INTO `products` (`id`, `user_id`, `name`, `category`, `price`, `quantity`) VALUES
(1, 1, 'Sugar (1 kg)', 'Groceries', 120.00, 50),
(2, 1, 'Cooking Oil (1 L)', 'Groceries', 250.00, 30),
(3, 1, 'Detergent Soap', 'Cleaning Supplies', 80.00, 100),
(4, 1, 'Macchiato Coffee Blend', 'Cafe', 150.00, 15);

-- Seed Sales and Expenses Transactions for Almaz Grocery (user_id = 1)
INSERT INTO `transactions` (`id`, `user_id`, `customer_id`, `date`, `description`, `type`, `amount`, `category`, `status`) VALUES
-- 1. Initial Credit Sale to Abebe Kebede
(1, 1, 1, '2026-05-14', 'Sells wholesale beverages to Abebe (Credit)', 'sale', 3400.00, 'Groceries', 'credit'),
-- 2. Shop Rent expense
(2, 1, NULL, '2026-05-15', 'Monthly shop rent in Bole', 'expense', 5000.00, 'Rent', 'paid'),
-- 3. High Volume Cash Sale
(3, 1, NULL, '2026-05-16', 'Sold bulk detergents & sanitizers to Yonas Dental Clinic', 'sale', 8200.00, 'Cleaning Supplies', 'paid'),
-- 4. Supplier Stock Purchase Expense
(4, 1, NULL, '2026-05-17', 'Bulk cereal stocking from Merkato wholesale market', 'expense', 4500.00, 'Stock Purchase', 'paid'),
-- 5. Credit Sale to Kibrom Tekle
(5, 1, 4, '2026-05-18', 'Sold electrical extensions & lightbulbs to Kibrom (Credit)', 'sale', 1500.00, 'Electronics', 'credit'),
-- 6. Credit Sale to Marta Balcha
(6, 1, 2, '2026-05-19', 'Sold soft drinks & water pack to Marta (Credit)', 'sale', 750.00, 'Beverages', 'credit'),
-- 7. Daily transport expense
(7, 1, NULL, '2026-05-20', 'Taxi and loader fees from Merkato market', 'expense', 320.00, 'Transport', 'paid');

-- Seed Repayments History (Dr. Yonas Hailu had a previous debt of 1200 ETB, and repaid it)
INSERT INTO `payments` (`id`, `user_id`, `customer_id`, `amount`, `method`, `date`) VALUES
(1, 1, 3, 1200.00, 'telebirr', '2026-05-15');

-- Log the transaction corresponding to Dr. Yonas' repayment to balance the books
INSERT INTO `transactions` (`id`, `user_id`, `customer_id`, `date`, `description`, `type`, `amount`, `category`, `status`) VALUES
(8, 1, NULL, '2026-05-15', 'Debt repayment from Dr. Yonas Hailu (Telebirr)', 'sale', 1200.00, 'Debt Repayment', 'paid');
