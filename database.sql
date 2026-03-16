-- Ocean Expense Manager
DROP DATABASE IF EXISTS `ocean_expense_manager`;
CREATE DATABASE `ocean_expense_manager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ocean_expense_manager`;

CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL UNIQUE,
  `label` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

CREATE TABLE `role_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_permission` (`role_id`,`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
);

CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role_id` int unsigned NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
);

CREATE TABLE `backdate_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `request_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_comment` text DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `is_consumed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backdate_user_date` (`user_id`,`request_date`,`status`),
  CONSTRAINT `fk_backdate_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_backdate_admin` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `expenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `category` enum('Travel','Food','Office','Other') NOT NULL,
  `party_name` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_mode` enum('Cash','UPI','Card') NOT NULL,
  `description` text DEFAULT NULL,
  `bill_file` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` int unsigned DEFAULT NULL,
  `approval_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expense_user_date` (`user_id`,`expense_date`),
  KEY `idx_expense_status` (`status`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expenses_admin` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', 'System administrator with full control'),
(2, 'Manager', 'Manager with approval and reporting rights'),
(3, 'Employee', 'Employee for own expense entries');

INSERT INTO `permissions` (`id`, `code`, `label`) VALUES
(1, 'manage_users', 'Add / Edit / Disable Employees'),
(2, 'manage_roles', 'Create roles and map permissions'),
(3, 'add_expense', 'Add Expense'),
(4, 'view_own_expense', 'View own expense history'),
(5, 'view_all_expense', 'View all expenses'),
(6, 'request_backdate', 'Request backdated expense permission'),
(7, 'approve_backdate', 'Approve or reject backdate requests'),
(8, 'approve_expense', 'Approve or reject expenses'),
(9, 'view_reports', 'View reports'),
(10, 'export_reports', 'Export reports'),
(11, 'view_dashboard', 'View dashboard'),
(12, 'view_notifications', 'View in-app notifications');

-- Admin gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2,5),(2,8),(2,9),(2,10),(2,11),(2,12);

-- Employee permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3,3),(3,4),(3,6),(3,11),(3,12);

-- Password for both users: admin123
INSERT INTO `users` (`name`, `email`, `password`, `role_id`, `status`) VALUES
('Admin User', 'admin@ocean.com', '$2y$10$cMjNBpb1JzW6I6dN5jTfVuLnwDmejpWKRBaFe.A7SJ0CR87YVor9S', 1, 'Active'),
('John Employee', 'john@ocean.com', '$2y$10$cMjNBpb1JzW6I6dN5jTfVuLnwDmejpWKRBaFe.A7SJ0CR87YVor9S', 3, 'Active');
