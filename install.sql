-- =====================================================
--  Cafe Master - نظام إدارة الكافيه
--  Database Installation Script
-- =====================================================

CREATE DATABASE IF NOT EXISTS `cafe_management`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `cafe_management`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `cafe_tables`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ===== USERS =====
CREATE TABLE `users` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `username`    VARCHAR(50)  NOT NULL UNIQUE,
  `password`    VARCHAR(255) NOT NULL,
  `role`        ENUM('admin','cashier','waiter') DEFAULT 'cashier',
  `phone`       VARCHAR(20),
  `permissions` JSON,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===== CATEGORIES =====
CREATE TABLE `categories` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `description` TEXT,
  `sort_order`  INT DEFAULT 0,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===== ITEMS (MENU) =====
CREATE TABLE `items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED,
  `name`        VARCHAR(100)   NOT NULL,
  `price`       DECIMAL(10,2)  NOT NULL DEFAULT 0,
  `cost`        DECIMAL(10,2)  DEFAULT 0,
  `description` TEXT,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===== CAFE TABLES =====
CREATE TABLE `cafe_tables` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `number`     INT UNIQUE NOT NULL,
  `name`       VARCHAR(50),
  `capacity`   INT DEFAULT 4,
  `status`     ENUM('available','occupied') DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===== SHIFTS =====
CREATE TABLE `shifts` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `check_in`     DATETIME NOT NULL,
  `check_out`    DATETIME,
  `total_sales`  DECIMAL(10,2) DEFAULT 0,
  `total_orders` INT DEFAULT 0,
  `notes`        TEXT,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ===== ORDERS =====
CREATE TABLE `orders` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `table_id`       INT UNSIGNED NOT NULL,
  `shift_id`       INT UNSIGNED,
  `user_id`        INT UNSIGNED NOT NULL,
  `status`         ENUM('open','closed','cancelled') DEFAULT 'open',
  `total`          DECIMAL(10,2) DEFAULT 0,
  `discount`       DECIMAL(10,2) DEFAULT 0,
  `discount_type`  ENUM('amount','percent') DEFAULT 'amount',
  `final_total`    DECIMAL(10,2) DEFAULT 0,
  `payment_method` ENUM('cash','card','other') DEFAULT 'cash',
  `notes`          TEXT,
  `opened_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `closed_at`      DATETIME,
  FOREIGN KEY (`table_id`) REFERENCES `cafe_tables`(`id`),
  FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ===== ORDER ITEMS =====
CREATE TABLE `order_items` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`   INT UNSIGNED NOT NULL,
  `item_id`    INT UNSIGNED NOT NULL,
  `item_name`  VARCHAR(100) NOT NULL,
  `quantity`   INT NOT NULL DEFAULT 1,
  `price`      DECIMAL(10,2) NOT NULL,
  `subtotal`   DECIMAL(10,2) NOT NULL,
  `notes`      TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`)  REFERENCES `items`(`id`)
) ENGINE=InnoDB;

-- ===== INVENTORY =====
CREATE TABLE `inventory` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100) NOT NULL,
  `unit`          VARCHAR(20) DEFAULT 'قطعة',
  `quantity`      DECIMAL(10,3) DEFAULT 0,
  `min_quantity`  DECIMAL(10,3) DEFAULT 0,
  `cost_per_unit` DECIMAL(10,2) DEFAULT 0,
  `notes`         TEXT,
  `is_active`     TINYINT(1) DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===== INVENTORY TRANSACTIONS =====
CREATE TABLE `inventory_transactions` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `inventory_id`  INT UNSIGNED NOT NULL,
  `type`          ENUM('in','out','adjustment') NOT NULL,
  `quantity`      DECIMAL(10,3) NOT NULL,
  `balance_after` DECIMAL(10,3) NOT NULL,
  `notes`         TEXT,
  `user_id`       INT UNSIGNED,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`),
  FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
--  DEFAULT DATA
-- =====================================================

-- Default Tables (1-10)
INSERT INTO `cafe_tables` (`number`, `name`, `capacity`) VALUES
(1,'طاولة 1',4),(2,'طاولة 2',4),(3,'طاولة 3',4),
(4,'طاولة 4',6),(5,'طاولة 5',2),(6,'طاولة 6',4),
(7,'طاولة 7',4),(8,'طاولة 8',6),(9,'طاولة 9',4),
(10,'طاولة 10',4);

-- Default Categories
INSERT INTO `categories` (`name`,`description`,`sort_order`) VALUES
('مشروبات ساخنة','القهوة والشاي',1),
('مشروبات باردة','العصائر والمشروبات الباردة',2),
('وجبات خفيفة','السناكس والسندوتشات',3),
('حلويات وكيك','الكيك والحلويات',4);

-- Default Items
INSERT INTO `items` (`category_id`,`name`,`price`,`cost`) VALUES
(1,'إسبريسو',15,5),(1,'كابتشينو',20,7),(1,'لاتيه',22,8),
(1,'أمريكانو',18,6),(1,'شاي كُرك',15,4),(1,'قهوة عربي',12,3),
(2,'عصير برتقال',18,6),(2,'عصير مانجو',20,7),(2,'ليمون بالنعناع',15,5),
(2,'موهيتو',25,9),(2,'سموذي فراولة',22,8),
(3,'كلوب سندوتش',35,15),(3,'برجر كلاسيك',45,20),(3,'سيزر صغير',30,12),
(4,'تشيز كيك',25,10),(4,'براونيز',20,8),(4,'كيك شوكولاتة',22,9);

-- Default Inventory
INSERT INTO `inventory` (`name`,`unit`,`quantity`,`min_quantity`,`cost_per_unit`) VALUES
('حبوب قهوة','كيلو',10,2,50),
('حليب','لتر',20,5,8),
('سكر','كيلو',15,3,5),
('شاي','علبة',10,2,15),
('مياه معدنية','كرتون',5,2,30),
('كريمة مخفوقة','علبة',8,3,12),
('شوكولاتة','كيلو',3,1,40);

-- NOTE: Run setup.php to create the admin account
