-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 25 Ağu 2025, 12:43:25
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `teklifpro`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit_type` enum('kg/m','adet','m','m²') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `categories`
--

INSERT INTO `categories` (`id`, `name`, `unit_type`) VALUES
(1, 'Alüminyum', 'm'),
(2, 'Aksesuar', 'adet'),
(3, 'Fitil', 'm');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `company`
--

CREATE TABLE `company` (
  `id` int(11) NOT NULL,
  `logo` text DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `tax_office` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `company_name`, `company`, `email`, `phone`, `address`, `tax_number`, `tax_office`, `city`, `country`, `notes`) VALUES
(1, 'Hakan Berke', 'İÇELLİOĞLU', '', NULL, 'hakanicellioglu@gmail.com', '05466017490', 'Mustafa Şimşek Bulvarı Alpaslan Mahallesi Esen Apartmanı 112/ 37', '', '', 'MELİKGAZİ', 'Türkiye', ''),
(2, 'Berke', 'İçelioğlu', '', NULL, 'berkeicellioglu@gmail.com', '', 'Mustafa Şimşek Bulvarı Alpaslan Mahallesi Esen Apartmanı 112/ 37', '', '', 'MELİKGAZİ', 'Türkiye', '');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `generaloffers`
--

CREATE TABLE `generaloffers` (
  `id` int(11) NOT NULL,
  `quote_no` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `offer_date` date DEFAULT NULL,
  `assembly_type` varchar(100) DEFAULT NULL,
  `delivery_time` varchar(100) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `validity_days` int(11) DEFAULT NULL,
  `installment_term` varchar(100) DEFAULT NULL,
  `payment_type` enum('cash','installment') NOT NULL DEFAULT 'cash',
  `term_months` int(11) DEFAULT NULL,
  `interest_mode` enum('percent','fixed') DEFAULT NULL,
  `interest_value` decimal(12,2) DEFAULT NULL,
  `interest_amount` decimal(12,2) DEFAULT NULL,
  `total_with_interest` decimal(12,2) DEFAULT NULL,
  `monthly_installment` decimal(12,2) DEFAULT NULL,
  `grace_days` int(11) DEFAULT 0,
  `payment_term` varchar(100) DEFAULT NULL,
  `offer_validity` varchar(100) DEFAULT NULL,
  `maturity_period` varchar(100) DEFAULT NULL,
  `discount_rate` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `profit_percent` decimal(5,2) DEFAULT NULL,
  `profit_amount` decimal(15,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `approval_token` varchar(64) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `generaloffers`
--

INSERT INTO `generaloffers` (`id`, `quote_no`, `customer_id`, `company_id`, `offer_date`, `assembly_type`, `delivery_time`, `payment_method`, `validity_days`, `installment_term`, `payment_type`, `term_months`, `interest_mode`, `interest_value`, `interest_amount`, `total_with_interest`, `monthly_installment`, `grace_days`, `payment_term`, `offer_validity`, `maturity_period`, `discount_rate`, `discount_amount`, `vat_rate`, `vat_amount`, `total_amount`, `profit_percent`, `profit_amount`, `status`, `approval_token`, `approved_at`, `note`) VALUES
(11, NULL, 1, NULL, '2025-08-22', 'demonte', NULL, 'cash', 10, NULL, 'cash', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 388944.00, NULL, NULL, 'sent', '6a11fec84c4615436d2015e24e35e184', '2025-08-23 08:36:37', NULL),
(12, NULL, 1, NULL, '2025-08-25', 'demonte', NULL, 'cash', 1, '0', 'cash', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'rejected', NULL, '2025-08-25 13:00:14', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `guillotinesystems`
--

CREATE TABLE `guillotinesystems` (
  `id` int(11) NOT NULL,
  `general_offer_id` int(11) DEFAULT NULL,
  `system_type` varchar(100) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `motor_system` varchar(100) DEFAULT NULL,
  `remote_quantity` int(11) DEFAULT NULL,
  `ral_code` varchar(50) DEFAULT NULL,
  `glass_type` varchar(100) DEFAULT NULL,
  `glass_color` varchar(50) DEFAULT NULL,
  `profit_margin` decimal(5,2) DEFAULT NULL,
  `profit_rate` decimal(5,2) DEFAULT NULL,
  `profit_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `guillotinesystems`
--

INSERT INTO `guillotinesystems` (`id`, `general_offer_id`, `system_type`, `width`, `height`, `quantity`, `motor_system`, `remote_quantity`, `ral_code`, `glass_type`, `glass_color`, `profit_margin`, `profit_rate`, `profit_amount`, `total_amount`) VALUES
(6, 2, 'Guillotine', 1000.00, 1000.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 0.00, NULL, 0.00, 7286.06),
(15, 9, 'Guillotine', 5000.00, 1000.00, 5, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 30.00, NULL, 0.00, 0.00),
(22, 3, 'Guillotine', 2814.00, 2425.00, 6, 'Somfy', 1, '7016', 'Tek Cam', 'Şeffaf', 0.00, NULL, 0.00, 148683.25),
(24, 11, 'Guillotine', 2814.00, 2425.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 0.00, NULL, 0.00, 21976.62),
(25, 11, 'Guillotine', 2814.00, 2425.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 0.00, NULL, 0.00, 22699.22),
(26, 11, 'Guillotine', 2814.00, 2425.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 0.00, NULL, 0.00, 22699.22),
(27, 11, 'Guillotine', 1000.00, 1000.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 0.00, NULL, 0.00, 6761.20),
(28, 11, 'Guillotine', 2150.00, 1870.00, 20, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 0.00, NULL, 0.00, 314807.74);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `channel_count` tinyint(3) UNSIGNED DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `image_data` longblob DEFAULT NULL,
  `image_mime` varchar(100) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `weight_per_meter` decimal(10,3) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price_unit` enum('USD','EUR','TRY') NOT NULL DEFAULT 'TRY'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `unit`, `channel_count`, `color`, `image_data`, `image_mime`, `image_url`, `description`, `unit_price`, `weight_per_meter`, `vat_rate`, `category_id`, `price_unit`) VALUES
(1, 'ALU-001', 'Motor Kutusu', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d81d38629d6.14295177.png', NULL, 5.00, 2.400, 0.00, 1, 'USD'),
(2, 'ALU-002', 'Motor Kapak', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d83510c2a44.96354635.png', NULL, 5.00, 0.761, 0.00, 1, 'USD'),
(3, 'ALU-003', 'Alt Kasa', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d85047bba91.68459718.png', NULL, 5.00, 1.216, 0.00, 1, 'USD'),
(4, 'ALU-004', 'Tutamak', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d884a53bad5.13649486.png', NULL, 5.00, 0.880, 0.00, 1, 'USD'),
(5, 'ALU-005', 'Kenetli Baza', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8990be6917.46468808.png', NULL, 5.00, 0.617, 0.00, 1, 'USD'),
(6, 'ALU-006', 'Küpeşte Bazası', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8a28b1e5c6.80846706.png', NULL, 5.00, 0.491, 0.00, 1, 'USD'),
(7, 'ALU-007', 'Küpeşte', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8be7c30299.00007194.png', NULL, 5.00, 0.399, 0.00, 1, 'USD'),
(8, 'ALU-008', 'Yatay Tek Cam Çıtası', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8c5daf3dc7.71006811.png', NULL, 5.00, 0.240, 0.00, 1, 'USD'),
(9, 'ALU-009', 'Dikey Tek Cam Çıtası', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8c6315c9c9.78615292.png', NULL, 5.00, 0.240, 0.00, 1, 'USD'),
(10, 'ALU-010', 'Dikme', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8d1a3f97f6.66118957.png', NULL, 5.00, 1.692, 0.00, 1, 'USD'),
(11, 'ALU-011', 'Orta Dikme', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8ddde19423.72245843.png', NULL, 5.00, 0.589, 0.00, 1, 'USD'),
(12, 'ALU-012', 'Son Kapatma', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d8e53630e23.99446192.png', NULL, 5.00, 0.980, 0.00, 1, 'USD'),
(13, 'ALU-013', 'Kanat', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d979f9d9095.83107493.png', NULL, 5.00, 1.499, 0.00, 1, 'USD'),
(14, 'ALU-014', 'Dikey Baza', 'kg/m', NULL, NULL, NULL, NULL, 'uploads/prod_689d98fb17a318.00944727.png', NULL, 5.00, 0.627, 0.00, 1, 'USD'),
(15, 'AKS-001', 'Zincir', 'adet', NULL, NULL, NULL, NULL, 'uploads/prod_689daf670db319.58780229.png', NULL, 5.00, 1.000, 20.00, 2, 'USD'),
(17, 'ALU-015', 'Motor Borusu', 'm', NULL, NULL, NULL, NULL, 'uploads/prod_689dba8ccbd6a4.05135182.png', NULL, 5.00, 1.000, 0.00, 1, 'USD'),
(18, 'FIT-001', 'Motor Kutu Contası', 'm', NULL, NULL, NULL, NULL, 'uploads/prod_689dbaa5c1c036.57568229.png', NULL, 5.00, 1.000, 20.00, 3, 'USD'),
(19, 'FIT-002', 'Kanat Contası', 'm', NULL, NULL, NULL, NULL, 'uploads/prod_689dbabf730f57.59776694.png', NULL, 5.00, 1.000, 20.00, 3, 'USD'),
(20, 'PRD-01', 'Kıl Fitil', 'm', NULL, NULL, NULL, NULL, 'uploads/prod_689dbad192dca3.02797000.jpg', NULL, 25.00, 1.000, 20.00, NULL, 'USD'),
(21, 'AKS-001', 'Plastik Set', 'set', NULL, NULL, NULL, NULL, 'uploads/prod_689daf670db319.58780229.png', NULL, 5.00, 1.000, 0.00, 2, 'USD'),
(22, 'AKS-021', 'Zincir', 'set', NULL, NULL, NULL, NULL, NULL, NULL, 5.00, 1.000, 0.00, 2, 'USD');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'admin'),
(2, 'user');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `slidingsystems`
--

CREATE TABLE `slidingsystems` (
  `id` int(11) NOT NULL,
  `general_offer_id` int(11) DEFAULT NULL,
  `system_type` varchar(100) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `wing_type` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `ral_code` varchar(50) DEFAULT NULL,
  `lock_type` varchar(100) DEFAULT NULL,
  `glass_type` varchar(100) DEFAULT NULL,
  `glass_color` varchar(50) DEFAULT NULL,
  `profit_rate` decimal(5,2) DEFAULT NULL,
  `profit_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `themes`
--

CREATE TABLE `themes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `theme` varchar(100) DEFAULT NULL,
  `primary_color` varchar(20) DEFAULT NULL,
  `secondary_color` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `role_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `password`, `email`, `created_at`, `status`, `role_id`) VALUES
(4, 'Hakan Berke', 'İÇELLİOĞLU', 'berkeicellioglu', '$2y$10$zVl/QQnU9tNHNYSg6vTFf.YOcoXaUzdsOMyi6xnCxs3h4gcMXijpa', 'hakanicellioglu@gmail.com', '2025-08-13 11:12:51', 'active', 1);

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `generaloffers`
--
ALTER TABLE `generaloffers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `idx_generaloffers_status` (`status`);

--
-- Tablo için indeksler `guillotinesystems`
--
ALTER TABLE `guillotinesystems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `general_offer_id` (`general_offer_id`);

--
-- Tablo için indeksler `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Tablo için indeksler `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `slidingsystems`
--
ALTER TABLE `slidingsystems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `general_offer_id` (`general_offer_id`);

--
-- Tablo için indeksler `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `company`
--
ALTER TABLE `company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `generaloffers`
--
ALTER TABLE `generaloffers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `guillotinesystems`
--
ALTER TABLE `guillotinesystems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Tablo için AUTO_INCREMENT değeri `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Tablo için AUTO_INCREMENT değeri `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `slidingsystems`
--
ALTER TABLE `slidingsystems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `generaloffers`
--
ALTER TABLE `generaloffers`
  ADD CONSTRAINT `generaloffers_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `generaloffers_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`);

--
-- Tablo kısıtlamaları `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Tablo kısıtlamaları `slidingsystems`
--
ALTER TABLE `slidingsystems`
  ADD CONSTRAINT `slidingsystems_ibfk_1` FOREIGN KEY (`general_offer_id`) REFERENCES `generaloffers` (`id`);

--
-- Tablo kısıtlamaları `themes`
--
ALTER TABLE `themes`
  ADD CONSTRAINT `themes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
