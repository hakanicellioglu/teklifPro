-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 11 Ağu 2025, 14:05:42
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
  `last_name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `company`, `email`, `phone`, `address`) VALUES
(1, 'Hakan Berke', 'İÇELLİOĞLU', NULL, 'hakanicellioglu@gmail.com', '05466017490', 'Mustafa Şimşek Bulvarı Alpaslan Mahallesi Esen Apartmanı 112/ 37'),
(2, 'Hakan', 'İÇELLİOĞLU', NULL, 'hakanicellioglu@gmail.com', '05466017490', 'Yahya Kemal caddesi Yılmaz apartmanı 21/6');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `generaloffers`
--

CREATE TABLE `generaloffers` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `offer_date` date DEFAULT NULL,
  `assembly_type` varchar(100) DEFAULT NULL,
  `delivery_time` varchar(100) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_term` varchar(100) DEFAULT NULL,
  `offer_validity` varchar(100) DEFAULT NULL,
  `maturity_period` varchar(100) DEFAULT NULL,
  `discount_rate` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `generaloffers`
--

INSERT INTO `generaloffers` (`id`, `customer_id`, `company_id`, `offer_date`, `assembly_type`, `delivery_time`, `payment_method`, `payment_term`, `offer_validity`, `maturity_period`, `discount_rate`, `discount_amount`, `vat_rate`, `vat_amount`, `total_amount`) VALUES
(2, 2, NULL, '2025-08-11', 'demonte', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00);

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
(2, 2, 'Guillotine', 1000.00, 1000.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 30.00, NULL, 0.00, 0.00),
(3, 2, 'Guillotine', 1000.00, 1000.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 30.00, NULL, 0.00, 0.00),
(4, 2, 'Guillotine', 1000.00, 1000.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 30.00, NULL, 0.00, 0.00),
(5, 2, 'Guillotine', 1000.00, 1000.00, 1, 'Somfy', 1, '7016', 'Isıcam', 'Şeffaf', 30.00, NULL, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `image_data` longblob DEFAULT NULL,
  `image_mime` varchar(100) DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `weight_per_meter` decimal(10,3) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `category`, `unit`, `color`, `image_data`, `image_mime`, `image_url`, `description`, `unit_price`, `weight_per_meter`, `vat_rate`, `category_id`) VALUES
(5, 'PRD-01', 'Tek Cam Çıtası', 'test', 'metre', '7016 Mat', NULL, NULL, NULL, NULL, 1000.00, NULL, 20.00, NULL);

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

--
-- Tablo döküm verisi `themes`
--

INSERT INTO `themes` (`id`, `user_id`, `theme`, `primary_color`, `secondary_color`) VALUES
(1, 1, NULL, '#0080ff', NULL),
(2, 2, NULL, '#0080ff', NULL),
(3, 2, NULL, '#0080ff', NULL),
(4, 2, NULL, '#ff0000', NULL),
(5, 2, NULL, '#ffffff', NULL),
(6, 2, NULL, '#ffffff', NULL),
(7, 2, NULL, '#c2c2c2', NULL),
(8, 2, NULL, '#ffae00', NULL);

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
(1, 'Hakan Berke', 'İÇELLİOĞLU', 'test', '$2y$10$DUUBza1Cb8jX5W3KOz86Dun5wnStXbNy4FKJfYSwp61VsR3vbtpx.', 'hakanicellioglu@gmail.com', '2025-08-08 15:48:40', 'active', 2),
(2, 'Hakan Berke', 'İÇELLİOĞLU', 'berkeicellioglu', '$2y$10$HhrRodC3PPyYQnoEZjBBCuDNUMmIChruGAILddp8HoKPeJqvkABO.', 'berkeicellioglu@gmail.com', '2025-08-09 12:41:09', 'active', 1);

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
  ADD KEY `company_id` (`company_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `company`
--
ALTER TABLE `company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `generaloffers`
--
ALTER TABLE `generaloffers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `guillotinesystems`
--
ALTER TABLE `guillotinesystems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- Tablo kısıtlamaları `guillotinesystems`
--
ALTER TABLE `guillotinesystems`
  ADD CONSTRAINT `guillotinesystems_ibfk_1` FOREIGN KEY (`general_offer_id`) REFERENCES `generaloffers` (`id`);

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
