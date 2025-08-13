-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 13 Ağu 2025, 16:47:28
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
  `last_name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `company_name`, `company`, `email`, `phone`, `address`) VALUES
(1, 'Hakan Berke', 'İÇELLİOĞLU', 'Moza', NULL, 'hakanicellioglu@gmail.com', '05466017490', 'Mustafa Şimşek Bulvarı Alpaslan Mahallesi Esen Apartmanı 112/ 37');

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
  `status` varchar(20) NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `generaloffers`
--

INSERT INTO `generaloffers` (`id`, `quote_no`, `customer_id`, `company_id`, `offer_date`, `assembly_type`, `delivery_time`, `payment_method`, `validity_days`, `installment_term`, `payment_type`, `term_months`, `interest_mode`, `interest_value`, `interest_amount`, `total_with_interest`, `monthly_installment`, `grace_days`, `payment_term`, `offer_validity`, `maturity_period`, `discount_rate`, `discount_amount`, `vat_rate`, `vat_amount`, `total_amount`, `status`) VALUES
(8, NULL, 1, NULL, '2025-08-13', 'demonte', NULL, 'vadeli', 15, NULL, 'installment', 1, 'percent', 5.00, 0.00, 0.00, 0.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `generaloffers`
--
ALTER TABLE `generaloffers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `guillotinesystems`
--
ALTER TABLE `guillotinesystems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
