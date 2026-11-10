-- MySQL dump 10.13  Distrib 8.4.11, for Win64 (x86_64)
--
-- Host: localhost    Database: zaylo_ecommerce
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `actor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_role` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logistics_provider_id` bigint unsigned DEFAULT NULL,
  `event` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `subject_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `activity_logs_actor_role_index` (`actor_role`),
  KEY `activity_logs_logistics_provider_id_index` (`logistics_provider_id`),
  KEY `activity_logs_event_index` (`event`),
  KEY `activity_logs_category_index` (`category`),
  KEY `activity_logs_created_at_index` (`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,49,'Shop Test Seller','seller',NULL,'seller.login.submit','auth','Signed in to Seller Center',NULL,NULL,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36','2026-11-01 22:23:56');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Home',
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `line1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PH',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `region_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_municipality_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_user_id_foreign` (`user_id`),
  CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
INSERT INTO `addresses` VALUES (1,1,'Home','Jan Reyben Del Mundo','09123456789','2100 San Luis Rd.',NULL,'Putho Tuntungin','Los Baños','Laguna','4030','PH',1,'2026-09-08 07:38:33','2026-09-08 07:39:08','040000000','CALABARZON','043400000','043411000','043411011'),(2,8,'Home','Jillian Gutierrez','09482672310','Purok 4 221',NULL,'Baanan','Magdalena','Laguna','4007','PH',1,'2026-09-15 14:12:03','2026-09-15 14:12:03','040000000','CALABARZON','043400000','043415000','043415004'),(4,11,'Other','Happy Heels (pickup counter)','09171110000','Unit 3, Halang Commercial Center, National Highway',NULL,'Halang','Calamba','Laguna','4027','PH',1,'2026-10-10 11:30:51','2026-10-10 11:30:51',NULL,'CALABARZON',NULL,NULL,NULL),(7,30,'Home','Juan Dela Cruz','09556644889','purok',NULL,'Ganado','City of Biñan','Laguna','4027','PH',1,'2026-10-10 12:07:23','2026-10-10 12:07:23','040000000','CALABARZON','043400000','043403000','043403007'),(8,21,'Home','Melany CIJO','09171234567','Calusiche Trading Store','Calusiche Trading Store','Calusiche','Pagsanjan','Laguna','4008','PH',1,'2026-10-12 00:36:32','2026-10-12 00:36:32','040000000','CALABARZON','043400000','043419000','043419005'),(9,40,'Home','Test Rider','09170000001','88 Burgos St.',NULL,'Halang','Calamba','Laguna','4027','PH',1,'2026-10-18 23:21:19','2026-10-18 23:21:19',NULL,'CALABARZON',NULL,NULL,NULL);
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `show_in_section` tinyint(1) NOT NULL DEFAULT '1',
  `show_as_modal` tinyint(1) NOT NULL DEFAULT '0',
  `send_email` tinyint(1) NOT NULL DEFAULT '0',
  `emailed_at` datetime DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_created_by_foreign` (`created_by`),
  CONSTRAINT `announcements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buyer_notification_reads`
--

DROP TABLE IF EXISTS `buyer_notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buyer_notification_reads` (
  `user_id` bigint unsigned NOT NULL,
  `notification_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`notification_key`),
  CONSTRAINT `buyer_notification_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buyer_notification_reads`
--

LOCK TABLES `buyer_notification_reads` WRITE;
/*!40000 ALTER TABLE `buyer_notification_reads` DISABLE KEYS */;
INSERT INTO `buyer_notification_reads` VALUES (1,'order:1','2026-09-19 03:27:09'),(1,'order:10','2026-09-22 21:11:11'),(1,'order:11','2026-09-22 21:11:47'),(1,'order:12','2026-09-24 20:22:47'),(1,'order:13','2026-09-24 23:59:29'),(1,'order:14','2026-09-24 23:59:29'),(1,'order:15','2026-09-24 23:59:29'),(1,'order:16','2026-09-24 22:25:33'),(1,'order:17','2026-09-24 23:59:29'),(1,'order:18','2026-09-24 23:59:29'),(1,'order:19','2026-09-24 23:59:29'),(1,'order:2','2026-09-19 03:27:09'),(1,'order:3','2026-09-19 03:27:09'),(1,'order:4','2026-09-19 03:27:09'),(1,'order:5','2026-09-19 03:27:09'),(1,'order:6','2026-09-19 17:32:34'),(1,'order:9','2026-09-22 21:11:47'),(1,'product:1','2026-09-19 03:27:09'),(1,'product:10','2026-09-19 03:27:09'),(1,'product:11','2026-09-19 03:27:09'),(1,'product:12','2026-09-19 03:27:09'),(1,'product:13','2026-09-19 03:27:09'),(1,'product:14','2026-09-19 03:27:09'),(1,'product:15','2026-09-19 03:27:09'),(1,'product:16','2026-09-19 03:27:09'),(1,'product:17','2026-09-19 03:27:09'),(1,'product:18','2026-09-19 03:27:09'),(1,'product:19','2026-09-19 03:27:09'),(1,'product:2','2026-09-19 03:27:09'),(1,'product:20','2026-09-19 03:27:09'),(1,'product:21','2026-09-19 03:27:09'),(1,'product:22','2026-09-19 03:27:09'),(1,'product:23','2026-09-19 03:27:09'),(1,'product:24','2026-09-19 03:27:09'),(1,'product:25','2026-09-19 03:27:09'),(1,'product:26','2026-09-19 03:27:09'),(1,'product:27','2026-09-19 03:27:09'),(1,'product:28','2026-09-19 03:27:09'),(1,'product:29','2026-09-19 03:27:09'),(1,'product:3','2026-09-19 03:27:09'),(1,'product:30','2026-09-19 03:27:09'),(1,'product:31','2026-09-19 03:27:09'),(1,'product:32','2026-09-19 03:27:09'),(1,'product:33','2026-09-19 03:27:09'),(1,'product:34','2026-09-19 03:27:09'),(1,'product:35','2026-09-19 03:27:09'),(1,'product:36','2026-09-19 03:27:09'),(1,'product:37','2026-09-19 03:27:09'),(1,'product:38','2026-09-19 03:27:09'),(1,'product:39','2026-09-19 03:27:09'),(1,'product:4','2026-09-19 03:27:09'),(1,'product:40','2026-09-19 03:27:09'),(1,'product:41','2026-09-19 03:27:09'),(1,'product:42','2026-09-19 03:27:09'),(1,'product:43','2026-09-19 03:27:09'),(1,'product:44','2026-09-19 03:27:09'),(1,'product:45','2026-09-19 03:27:09'),(1,'product:46','2026-09-19 03:27:09'),(1,'product:47','2026-09-19 03:27:09'),(1,'product:48','2026-09-19 03:27:09'),(1,'product:49','2026-09-19 03:27:09'),(1,'product:5','2026-09-19 03:27:09'),(1,'product:50','2026-09-19 03:27:09'),(1,'product:51','2026-09-19 03:27:09'),(1,'product:52','2026-09-19 03:27:09'),(1,'product:53','2026-09-19 03:27:09'),(1,'product:54','2026-09-19 03:27:09'),(1,'product:55','2026-09-19 03:27:09'),(1,'product:56','2026-09-19 03:27:09'),(1,'product:57','2026-09-19 03:27:09'),(1,'product:58','2026-09-19 03:27:09'),(1,'product:59','2026-09-19 03:27:09'),(1,'product:6','2026-09-19 03:27:09'),(1,'product:60','2026-09-19 03:27:09'),(1,'product:61','2026-09-19 03:27:09'),(1,'product:62','2026-09-19 03:27:09'),(1,'product:63','2026-09-19 03:27:09'),(1,'product:64','2026-09-19 03:27:09'),(1,'product:65','2026-09-19 03:27:09'),(1,'product:66','2026-09-19 03:27:09'),(1,'product:67','2026-09-19 03:27:09'),(1,'product:68','2026-09-19 03:27:09'),(1,'product:69','2026-09-19 17:33:10'),(1,'product:7','2026-09-19 03:27:09'),(1,'product:8','2026-09-19 03:27:09'),(1,'product:9','2026-09-19 03:27:09'),(1,'voucher:1:available','2026-09-19 03:27:09');
/*!40000 ALTER TABLE `buyer_notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_variant_id` bigint unsigned DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_items_product_id_foreign` (`product_id`),
  KEY `cart_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `cart_items_cart_id_product_id_index` (`cart_id`,`product_id`),
  CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
INSERT INTO `cart_items` VALUES (5,3,4,NULL,1,'2026-09-15 14:16:56','2026-09-15 14:16:56'),(28,3,5,NULL,1,'2026-09-25 00:12:43','2026-09-25 00:12:43'),(49,1,68,NULL,1,'2026-10-06 19:56:41','2026-10-06 19:57:16'),(60,5,1,1,1,'2026-10-18 23:29:01','2026-10-18 23:29:01');
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `carts_user_id_unique` (`user_id`),
  CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
INSERT INTO `carts` VALUES (1,1,'2026-09-08 07:37:00','2026-09-08 07:37:00'),(2,6,'2026-09-12 19:32:16','2026-09-12 19:32:16'),(3,8,'2026-09-12 19:43:58','2026-09-12 19:43:58'),(4,5,'2026-09-22 18:13:21','2026-09-22 18:13:21'),(5,21,'2026-10-08 06:14:04','2026-10-08 06:14:04');
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commissions`
--

DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `settled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commissions_order_id_unique` (`order_id`),
  KEY `commissions_seller_id_foreign` (`seller_id`),
  KEY `commissions_status_index` (`status`),
  CONSTRAINT `commissions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commissions`
--

LOCK TABLES `commissions` WRITE;
/*!40000 ALTER TABLE `commissions` DISABLE KEYS */;
INSERT INTO `commissions` VALUES (1,8,11,10.00,7798.80,'earned','2026-09-24 23:13:53','2026-09-24 23:13:53',NULL),(3,12,11,10.00,1899.70,'earned','2026-10-10 11:43:31','2026-10-10 11:43:31',NULL);
/*!40000 ALTER TABLE `commissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compliance_warnings`
--

DROP TABLE IF EXISTS `compliance_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compliance_warnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `issued_by` bigint unsigned NOT NULL,
  `reason` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `compliance_warnings_seller_id_foreign` (`seller_id`),
  KEY `compliance_warnings_issued_by_foreign` (`issued_by`),
  KEY `compliance_warnings_product_id_index` (`product_id`),
  CONSTRAINT `compliance_warnings_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compliance_warnings_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compliance_warnings`
--

LOCK TABLES `compliance_warnings` WRITE;
/*!40000 ALTER TABLE `compliance_warnings` DISABLE KEYS */;
/*!40000 ALTER TABLE `compliance_warnings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_buyer_id_seller_id_unique` (`buyer_id`,`seller_id`),
  KEY `conversations_product_id_foreign` (`product_id`),
  KEY `conversations_buyer_id_last_message_at_index` (`buyer_id`,`last_message_at`),
  KEY `conversations_seller_id_last_message_at_index` (`seller_id`,`last_message_at`),
  CONSTRAINT `conversations_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
INSERT INTO `conversations` VALUES (1,1,11,60,'2026-10-04 18:16:13','2026-09-24 23:52:09','2026-10-04 18:16:13'),(2,8,11,62,'2026-09-25 00:09:19','2026-09-25 00:07:21','2026-09-25 00:09:19'),(3,21,11,62,NULL,'2026-10-16 23:39:43','2026-10-16 23:39:43');
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courier_profiles`
--

DROP TABLE IF EXISTS `courier_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courier_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `vehicle_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courier_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `courier_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courier_profiles`
--

LOCK TABLES `courier_profiles` WRITE;
/*!40000 ALTER TABLE `courier_profiles` DISABLE KEYS */;
INSERT INTO `courier_profiles` VALUES (2,30,'tricycle','S33-45-666666',NULL,'2026-10-10 10:14:33','2026-10-10 10:14:33');
/*!40000 ALTER TABLE `courier_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_messages`
--

DROP TABLE IF EXISTS `delivery_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned DEFAULT NULL,
  `from_rider` tinyint(1) NOT NULL,
  `client_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_messages_shipment_id_client_id_unique` (`shipment_id`,`client_id`),
  KEY `delivery_messages_sender_id_foreign` (`sender_id`),
  KEY `delivery_messages_shipment_id_created_at_index` (`shipment_id`,`created_at`),
  CONSTRAINT `delivery_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_messages_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_messages`
--

LOCK TABLES `delivery_messages` WRITE;
/*!40000 ALTER TABLE `delivery_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_messages`
--

DROP TABLE IF EXISTS `dispute_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dispute_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispute_messages_sender_id_foreign` (`sender_id`),
  KEY `dispute_messages_dispute_id_index` (`dispute_id`),
  CONSTRAINT `dispute_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_messages`
--

LOCK TABLES `dispute_messages` WRITE;
/*!40000 ALTER TABLE `dispute_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disputes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `opened_by` bigint unsigned NOT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `return_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_courier` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_slot` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_deadline` date DEFAULT NULL,
  `return_tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evidence` text COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `return_received_at` timestamp NULL DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `return_rider_id` bigint unsigned DEFAULT NULL,
  `return_assigned_at` timestamp NULL DEFAULT NULL,
  `return_picked_up_at` timestamp NULL DEFAULT NULL,
  `return_at_hub_at` timestamp NULL DEFAULT NULL,
  `return_pickup_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_hub_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `disputes_order_id_foreign` (`order_id`),
  KEY `disputes_opened_by_foreign` (`opened_by`),
  KEY `disputes_resolved_by_foreign` (`resolved_by`),
  KEY `disputes_status_index` (`status`),
  KEY `disputes_return_tracking_number_index` (`return_tracking_number`),
  KEY `disputes_return_rider_id_index` (`return_rider_id`),
  CONSTRAINT `disputes_opened_by_foreign` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`),
  CONSTRAINT `disputes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disputes`
--

LOCK TABLES `disputes` WRITE;
/*!40000 ALTER TABLE `disputes` DISABLE KEYS */;
/*!40000 ALTER TABLE `disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verification_codes`
--

DROP TABLE IF EXISTS `email_verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_codes` (
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `email_verification_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verification_codes`
--

LOCK TABLES `email_verification_codes` WRITE;
/*!40000 ALTER TABLE `email_verification_codes` DISABLE KEYS */;
INSERT INTO `email_verification_codes` VALUES (2,'chatbotnimaro@gmail.com','$2y$12$PIMZPCtwv6bzYyUxlPUl5.BQTrHWnBh35kiqp6eceDgHHnMOGCohC',1,'2026-09-06 22:43:09','2026-09-06 22:33:09'),(3,'mellowbowl2000@gmail.com','$2y$12$wEDzhupUHI4XZCpG9jF73e/7jtFjMKQT3J6jyjmSB4xDfvaDTJewK',0,'2026-09-07 00:14:42','2026-09-07 00:04:42'),(5,'gutierrezjillianm@gmail.com','$2y$12$D2lwQrZ6nlGGNH6ZDFfW5uOB80kLxfsUGww2wFYYv8MI1OnCD9lJ6',0,'2026-09-07 04:20:09','2026-09-07 04:10:09'),(10,'exampleemail@gmail.com','$2y$12$4dclYfBeA4CxYY.oa4QkDuPrpBLxZLQMLzfqfDzVTbFz2/ruh4kAK',0,'2026-09-19 04:31:28','2026-09-19 04:21:28'),(16,'Raviaonkis@gmailcom','$2y$12$EjQ16SZmNurX.w/Q1fE5DuC50fA0Iq4ooM12Gcm0wT9P8rFpU.Q4.',0,'2026-10-07 03:46:56','2026-10-07 03:36:56'),(17,'tiongsonjundell09@gmail.com','$2y$12$J823eNdAvs1UtYAVUF0Zn.Qw0jYxM5AGJpnySqEZa/Twwbab9NaXq',0,'2026-10-07 04:04:04','2026-10-07 03:54:04'),(18,'buyer.test01@example.com','$2y$12$4S5N1zDBQUTP7.mI64ULneVAEVpqgEPTJFkqD6/EaVI7UABFVgoO.',0,'2026-10-07 04:11:41','2026-10-07 04:01:41'),(19,'cjmlnyann@gmail.com','$2y$12$bXvuw2shQjZejIF.VD4QeuzE7b1Qce7NIjwb6U6kux.21Fdtv7wcC',0,'2026-10-07 09:40:05','2026-10-07 09:30:05'),(20,'jundellsocial@gmail.com','$2y$12$TNdKw.s9ma4fJF/wQPxzIeeeMCbdFGjtiErVeq1EbkXnRtygAbUzS',0,'2026-10-07 15:19:33','2026-10-07 15:09:33'),(38,'gutierrezjillian52@gmail.com','$2y$12$7dsWlVLSWLolQedd/0ISgecPs2rLzFCVi2tLyGoFrnCBuLdzvGGDO',0,'2026-10-19 03:30:24','2026-10-19 03:25:24'),(40,'rider@zaylo.com','$2y$12$p4jntAZMIR3YsA/zuJByu.T0XJdT7bL/kOzmitdsVRkGOG4Prn06q',0,'2026-10-19 07:25:12','2026-10-19 07:20:12');
/*!40000 ALTER TABLE `email_verification_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `identity_verifications`
--

DROP TABLE IF EXISTS `identity_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `identity_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `id_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `identity_verifications_reviewed_by_foreign` (`reviewed_by`),
  KEY `identity_verifications_status_created_at_index` (`status`,`created_at`),
  KEY `identity_verifications_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `identity_verifications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `identity_verifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `identity_verifications`
--

LOCK TABLES `identity_verifications` WRITE;
/*!40000 ALTER TABLE `identity_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `identity_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistics_messages`
--

DROP TABLE IF EXISTS `logistics_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistics_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `logistics_thread_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `logistics_messages_sender_id_foreign` (`sender_id`),
  KEY `logistics_messages_logistics_thread_id_id_index` (`logistics_thread_id`,`id`),
  CONSTRAINT `logistics_messages_logistics_thread_id_foreign` FOREIGN KEY (`logistics_thread_id`) REFERENCES `logistics_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logistics_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistics_messages`
--

LOCK TABLES `logistics_messages` WRITE;
/*!40000 ALTER TABLE `logistics_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `logistics_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistics_payouts`
--

DROP TABLE IF EXISTS `logistics_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistics_payouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `logistics_provider_id` bigint unsigned NOT NULL,
  `parcels` int unsigned NOT NULL,
  `shipping_fees` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `partner_share` decimal(4,2) NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_by` bigint unsigned DEFAULT NULL,
  `paid_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rider_commissions` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `logistics_payouts_paid_by_foreign` (`paid_by`),
  KEY `logistics_payouts_logistics_provider_id_paid_at_index` (`logistics_provider_id`,`paid_at`),
  CONSTRAINT `logistics_payouts_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `logistics_payouts_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistics_payouts`
--

LOCK TABLES `logistics_payouts` WRITE;
/*!40000 ALTER TABLE `logistics_payouts` DISABLE KEYS */;
/*!40000 ALTER TABLE `logistics_payouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistics_providers`
--

DROP TABLE IF EXISTS `logistics_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistics_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sec_registration_no` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `service_area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_areas` json DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `region_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_certificate_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bir_2303_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_permit_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `representative_id_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `logistics_providers_slug_unique` (`slug`),
  UNIQUE KEY `logistics_providers_sec_registration_no_unique` (`sec_registration_no`),
  UNIQUE KEY `logistics_providers_tin_unique` (`tin`),
  KEY `logistics_providers_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `logistics_providers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistics_providers`
--

LOCK TABLES `logistics_providers` WRITE;
/*!40000 ALTER TABLE `logistics_providers` DISABLE KEYS */;
INSERT INTO `logistics_providers` VALUES (1,39,'ZAYLO Express',NULL,NULL,'zaylo-express','approved',NULL,'09170000000',NULL,'2026-10-18 23:19:45','2026-10-18 23:19:45',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `logistics_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistics_threads`
--

DROP TABLE IF EXISTS `logistics_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistics_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `logistics_provider_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `logistics_threads_logistics_provider_id_user_id_unique` (`logistics_provider_id`,`user_id`),
  KEY `logistics_threads_user_id_last_message_at_index` (`user_id`,`last_message_at`),
  CONSTRAINT `logistics_threads_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logistics_threads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistics_threads`
--

LOCK TABLES `logistics_threads` WRITE;
/*!40000 ALTER TABLE `logistics_threads` DISABLE KEYS */;
/*!40000 ALTER TABLE `logistics_threads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `client_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `messages_conversation_id_sender_id_client_id_unique` (`conversation_id`,`sender_id`,`client_id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_conversation_id_id_index` (`conversation_id`,`id`),
  KEY `messages_conversation_id_read_at_sender_id_index` (`conversation_id`,`read_at`,`sender_id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,1,'7334bc1c-8902-4568-b70c-a9cf17249c8d','Hello','2026-09-24 23:52:24','2026-09-24 23:52:20','2026-09-24 23:52:24',NULL,NULL),(2,1,11,'3d313fd7-538e-4d73-8d61-e67e6ceca793','Hello po','2026-09-25 00:02:16','2026-09-25 00:02:14','2026-09-25 00:02:16',NULL,NULL),(3,2,8,'9a71d516-5014-4449-b829-7cd3ec3ebcdf','when po shipout','2026-09-25 00:07:50','2026-09-25 00:07:38','2026-09-25 00:07:50',NULL,NULL),(4,2,11,'ab91b240-f0a8-4e26-9bc0-9db3807da88a','Wala pa po','2026-09-25 00:08:32','2026-09-25 00:07:56','2026-09-25 00:08:32',NULL,NULL),(5,2,8,'bafdf7e9-e602-40df-913d-ed20ede5c9ad','ok cancel nalang','2026-09-25 00:10:19','2026-09-25 00:09:19','2026-09-25 00:10:19',NULL,NULL),(6,1,11,'0e643303-615b-4f6f-9a11-891de63a965a','KAMUSTA KA BOII??','2026-10-03 20:24:14','2026-10-03 20:24:03','2026-10-03 20:24:14',NULL,NULL),(7,1,1,'a1c0982f-2cbe-4020-a739-46629f9c15ee','Okay naman po hehe','2026-10-03 20:24:54','2026-10-03 20:24:49','2026-10-03 20:24:54',NULL,NULL),(8,1,11,'de6fa97b-da30-4d4a-85e4-80ca518bb814','...','2026-10-04 18:16:07','2026-10-04 18:16:00','2026-10-04 18:16:07',NULL,NULL),(9,1,1,'a4796480-b9d5-4a6d-8a11-403fe304f31e','AAA','2026-10-04 18:16:15','2026-10-04 18:16:13','2026-10-04 18:16:15',NULL,NULL);
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_08_30_154203_create_products_table',1),(5,'2026_09_02_000001_add_marketplace_fields_to_users_and_products',2),(6,'2026_09_02_000002_create_marketplace_tables',2),(7,'2026_09_06_221816_add_google_auth_fields_to_users_table',3),(8,'2026_09_07_000001_create_email_verification_codes_table',4),(9,'2026_09_07_000002_add_psgc_fields_to_addresses_table',5),(10,'2026_09_14_000001_add_date_of_birth_to_users_table',6),(11,'2026_09_19_000001_create_vouchers_and_order_discounts',7),(12,'2026_09_19_000002_create_buyer_notification_reads_table',8),(13,'2026_09_25_000001_create_messaging_tables',9),(14,'2026_10_04_000001_add_scheduled_product_sales',10),(15,'2026_10_05_000001_create_product_reviews_table',11),(16,'2026_10_08_125838_create_personal_access_tokens_table',12),(17,'2026_10_10_145811_create_rider_applications_table',13),(18,'2026_10_10_150015_add_rider_to_users_role_enum',14),(19,'2026_10_10_160057_add_plate_number_to_rider_applications_table',15),(20,'2026_10_10_170052_add_terms_accepted_at_to_rider_applications_table',16),(21,'2026_10_10_174101_add_reviewer_to_rider_applications_table',17),(22,'2026_10_10_180432_require_terms_on_rider_applications_table',18),(23,'2026_10_10_193945_create_support_chat_tables',19),(24,'2026_10_10_195604_add_gender_to_users_table',20),(25,'2026_10_12_120000_create_delivery_messages_table',21),(26,'2026_10_09_000001_add_logistics_partners_and_rider_applications',22),(27,'2026_10_10_000001_create_identity_verifications_table',22),(28,'2026_10_13_000001_create_seller_applications_table',22),(29,'2026_10_14_000001_add_license_and_documents_to_riders',22),(30,'2026_10_14_000002_create_pending_registrations_table',22),(31,'2026_10_15_000001_add_hub_steps_and_logistics_chat',22),(32,'2026_10_16_000001_create_admin_modules',22),(33,'2026_10_16_000002_add_application_details_to_logistics_providers',23),(34,'2026_10_16_000002_unify_support_chat',23),(35,'2026_10_18_000001_create_product_categories_table',23),(36,'2026_10_18_000002_add_archived_at_to_products',24),(37,'2026_10_18_000003_allow_kids_product_genders',24),(38,'2026_10_18_000004_create_shop_follows_table',24),(39,'2026_10_20_000001_create_activity_logs_table',24),(40,'2026_10_20_000002_create_personal_access_tokens_table',24),(41,'2026_10_21_000001_add_review_status_to_products',24),(42,'2026_10_21_000002_add_delivery_chat_and_support_client_ids',24),(43,'2026_10_24_000001_add_logistics_provider_to_shipments',24),(44,'2026_10_25_000001_create_rider_delivery_logs_table',25),(45,'2026_10_26_000001_add_return_and_pickup_fields_to_disputes',26),(46,'2026_10_27_000001_add_image_path_to_support_messages',26),(47,'2026_10_27_000002_add_flash_sales_delivery_proof_and_return_refunds',27),(48,'2026_10_28_000001_add_delivered_at_to_orders',27),(49,'2026_10_28_000002_add_return_pickup_rider_to_disputes',28),(50,'2026_10_28_000003_add_return_photos_to_disputes',29),(51,'2026_11_01_000001_move_products_to_finalized_categories',29),(52,'2026_11_01_000002_give_unowned_products_their_own_shops',29),(53,'2026_11_01_000003_add_demo_applications_for_demo_shops',29),(54,'2026_11_01_000006_set_final_demo_shop_emails',29),(55,'2026_11_01_000007_add_delivery_channels_to_announcements',29),(56,'2026_11_02_000001_add_shop_cover_to_users',30),(57,'2026_11_03_000001_add_rider_schedules_and_attendance',31),(58,'2026_11_03_000001_allow_product_reviews_without_a_purchase',31),(59,'2026_11_03_000002_add_photos_and_videos_to_chat_messages',31),(60,'2026_11_03_000003_make_shop_category_optional',31),(61,'2026_11_03_000004_add_custom_tags_to_products',31),(62,'2026_11_04_000001_create_logistics_payouts',31),(63,'2026_11_06_000001_create_shop_sections',31),(64,'2026_11_06_000002_add_is_adult_to_products',31),(65,'2026_11_07_000001_create_rider_wallets',32),(66,'2026_11_07_000002_add_logistics_partner_to_orders',32);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_variant_id` bigint unsigned DEFAULT NULL,
  `seller_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int unsigned NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `order_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `order_items_seller_id_foreign` (`seller_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,2,NULL,NULL,'Bully Tools 14-Gauge Round Point Shovel','ZAY-L1ANEVUN',1599.00,1,1599.00,'2026-09-16 05:56:17','2026-09-16 05:56:17'),(2,2,63,NULL,NULL,'Gold Round LED Decorative Mirror','ZAY-DRV-1qfbPgkLp8BF1yUF3Dmu8ioSVBiD_rDwx-B',3499.00,1,3499.00,'2026-09-18 06:42:00','2026-09-18 06:42:00'),(3,3,69,NULL,NULL,'KITfanz Black Electric Moka Pot','ZAY-DRV-1ON6jXKdPpdQEdtPxbKgCmsBxhT5Lo4hF-B',2499.00,18,44982.00,'2026-09-18 20:57:19','2026-09-18 20:57:19'),(6,6,3,4,NULL,'COOLJOB Gardening Gloves - 6 Pairs — Small','ZAY-CJGLV-6-S',899.00,3,2697.00,'2026-09-22 18:43:17','2026-09-22 18:43:17'),(7,7,69,NULL,NULL,'KITfanz Black Electric Moka Pot','ZAY-DRV-1ON6jXKdPpdQEdtPxbKgCmsBxhT5Lo4hF-B',2499.00,1,2499.00,'2026-09-22 18:55:46','2026-09-22 18:55:46'),(8,7,56,NULL,NULL,'Electric Toaster','ZAY-DRV-1tCceAM2Rum_pOH5O0OSugCR8m26Omtaf',1299.00,1,1299.00,'2026-09-22 18:55:46','2026-09-22 18:55:46'),(9,7,2,NULL,NULL,'Bully Tools 14-Gauge Round Point Shovel','ZAY-L1ANEVUN',1599.00,1,1599.00,'2026-09-22 18:55:46','2026-09-22 18:55:46'),(10,8,60,NULL,11,'Nike Dunk Low Retro Panda','ZAY-DRV-SHOE-1',6499.00,12,77988.00,'2026-09-22 21:36:11','2026-09-22 21:36:11'),(11,10,60,NULL,11,'Nike Dunk Low Retro Panda','ZAY-SAMPLE-60',6499.00,1,6499.00,'2026-10-10 10:11:51','2026-10-10 10:11:51'),(12,10,61,NULL,11,'Nike Dunk Low Pink and White','ZAY-SAMPLE-61',5999.00,2,11998.00,'2026-10-10 10:11:51','2026-10-10 10:11:51'),(13,11,62,NULL,11,'Nike WMNS Dunk Low Olive','ZAY-SAMPLE-62',5999.00,1,5999.00,'2026-10-10 10:07:52','2026-10-10 10:07:52'),(14,12,60,NULL,11,'Nike Dunk Low Retro Panda','ZAY-SAMPLE-60',6499.00,2,12998.00,'2026-10-10 10:06:52','2026-10-10 10:06:52'),(15,12,62,NULL,11,'Nike WMNS Dunk Low Olive','ZAY-SAMPLE-62',5999.00,1,5999.00,'2026-10-10 10:06:52','2026-10-10 10:06:52'),(16,13,47,NULL,NULL,'Ingco Plastic Tool Box','ZAY-DRV-1scolch6Rj0KaQm65qKgqlJMQa3218tCB',999.00,1,999.00,'2026-10-12 00:37:17','2026-10-12 00:37:17'),(17,13,54,NULL,NULL,'Touchscreen Air Fryer','ZAY-DRV-1qtTqgW1FKhUGL2fPieu6rprQN1OVOXHR',3299.00,1,3299.00,'2026-10-12 00:37:17','2026-10-12 00:37:17'),(18,13,65,NULL,NULL,'Kenpoly Wheeled Trash Bin','ZAY-DRV-1wOEOgxxbPWcl9UKmQ8Vzb_pCQTMHMZod-B',899.00,2,1798.00,'2026-10-12 00:37:17','2026-10-12 00:37:17'),(19,14,13,NULL,NULL,'IKEA TOFTBO Bath Mat','ZAY-DRV-1JTiKFojCZCo0_-uhxd42xZA7LrwTZEMM',649.00,1,649.00,'2026-10-16 23:40:54','2026-10-16 23:40:54'),(20,14,65,NULL,NULL,'Kenpoly Wheeled Trash Bin','ZAY-DRV-1wOEOgxxbPWcl9UKmQ8Vzb_pCQTMHMZod-B',899.00,1,899.00,'2026-10-16 23:40:54','2026-10-16 23:40:54'),(21,15,62,NULL,11,'Nike WMNS Dunk Low Olive','ZAY-DRV-SHOE-3',5999.00,9,53991.00,'2026-10-16 23:40:54','2026-10-16 23:40:54'),(22,16,1,1,NULL,'1.2L Long Spout Watering Can — Green','ZAY-WCAN-12-GRN',499.00,2,998.00,'2026-10-17 20:15:52','2026-10-17 20:15:52'),(23,17,1,3,NULL,'1.2L Long Spout Watering Can — White','ZAY-WCAN-12-WHT',499.00,3,1497.00,'2026-10-17 20:16:24','2026-10-17 20:16:24'),(24,18,63,NULL,NULL,'Gold Round LED Decorative Mirror','ZAY-DRV-1qfbPgkLp8BF1yUF3Dmu8ioSVBiD_rDwx-B',3499.00,1,3499.00,'2026-10-18 23:10:29','2026-10-18 23:10:29'),(25,19,60,NULL,11,'Nike Dunk Low Retro Panda','ZAY-SAMPLE-60',6499.00,1,6499.00,'2026-10-19 05:24:57','2026-10-19 05:24:57'),(26,19,61,NULL,11,'Nike Dunk Low Pink and White','ZAY-SAMPLE-61',5999.00,2,11998.00,'2026-10-19 05:24:57','2026-10-19 05:24:57');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_histories`
--

DROP TABLE IF EXISTS `order_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_status_histories_order_id_foreign` (`order_id`),
  KEY `order_status_histories_changed_by_foreign` (`changed_by`),
  CONSTRAINT `order_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_status_histories_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_histories`
--

LOCK TABLES `order_status_histories` WRITE;
/*!40000 ALTER TABLE `order_status_histories` DISABLE KEYS */;
INSERT INTO `order_status_histories` VALUES (1,1,1,'placed','Order placed by buyer.','2026-09-16 05:56:17','2026-09-16 05:56:17'),(2,2,1,'placed','Order placed by buyer.','2026-09-18 06:42:00','2026-09-18 06:42:00'),(3,2,1,'cancelled','Cancelled by buyer.','2026-09-18 06:42:08','2026-09-18 06:42:08'),(4,1,1,'cancelled','Cancelled by buyer.','2026-09-18 06:42:11','2026-09-18 06:42:11'),(5,3,1,'placed','Order placed by buyer.','2026-09-18 20:57:19','2026-09-18 20:57:19'),(6,3,1,'cancelled','Cancelled by buyer.','2026-09-19 17:32:24','2026-09-19 17:32:24'),(9,6,1,'placed','Order placed by buyer.','2026-09-22 18:43:17','2026-09-22 18:43:17'),(10,6,1,'cancelled','Cancelled by buyer.','2026-09-22 18:43:27','2026-09-22 18:43:27'),(11,7,1,'placed','Order placed by buyer.','2026-09-22 18:55:46','2026-09-22 18:55:46'),(12,8,1,'placed','Order placed by buyer.','2026-09-22 21:36:11','2026-09-22 21:36:11'),(13,8,11,'confirmed','Updated by seller.','2026-09-24 22:24:51','2026-09-24 22:24:51'),(14,8,11,'processing','Updated by seller.','2026-09-24 22:24:55','2026-09-24 22:24:55'),(15,8,11,'ready_for_pickup','Updated by seller.','2026-09-24 22:24:57','2026-09-24 22:24:57'),(16,8,11,'picked_up','Updated by seller in self-managed delivery mode.','2026-09-24 22:25:01','2026-09-24 22:25:01'),(17,8,11,'in_transit','Updated by seller in self-managed delivery mode.','2026-09-24 23:13:14','2026-09-24 23:13:14'),(18,8,11,'out_for_delivery','Updated by seller in self-managed delivery mode.','2026-09-24 23:13:31','2026-09-24 23:13:31'),(19,8,11,'completed','Updated by seller in self-managed delivery mode.','2026-09-24 23:13:53','2026-09-24 23:13:53'),(24,10,21,'placed','Order placed (sample).','2026-10-10 11:30:52','2026-10-10 11:30:52'),(25,10,11,'ready_for_pickup','Packed and ready for rider pickup (sample).','2026-10-10 11:30:52','2026-10-10 11:30:52'),(26,11,21,'placed','Order placed (sample).','2026-10-10 11:30:52','2026-10-10 11:30:52'),(27,11,11,'ready_for_pickup','Packed and ready for rider pickup (sample).','2026-10-10 11:30:52','2026-10-10 11:30:52'),(28,12,21,'placed','Order placed (sample).','2026-10-10 11:30:52','2026-10-10 11:30:52'),(29,12,11,'ready_for_pickup','Packed and ready for rider pickup (sample).','2026-10-10 11:30:52','2026-10-10 11:30:52'),(34,12,30,'assigned','Courier accepted delivery.','2026-10-10 11:43:10','2026-10-10 11:43:10'),(35,12,30,'picked_up','Updated by courier.','2026-10-10 11:43:21','2026-10-10 11:43:21'),(36,12,30,'in_transit','Updated by courier.','2026-10-10 11:43:25','2026-10-10 11:43:25'),(37,12,30,'completed','Updated by courier.','2026-10-10 11:43:31','2026-10-10 11:43:31'),(38,13,21,'placed','Order placed by buyer.','2026-10-12 00:37:17','2026-10-12 00:37:17'),(39,14,21,'placed','Order placed by buyer.','2026-10-16 23:40:54','2026-10-16 23:40:54'),(40,15,21,'placed','Order placed by buyer.','2026-10-16 23:40:54','2026-10-16 23:40:54'),(41,16,21,'placed','Order placed by buyer.','2026-10-17 20:15:52','2026-10-17 20:15:52'),(42,17,21,'placed','Order placed by buyer.','2026-10-17 20:16:24','2026-10-17 20:16:24'),(43,18,21,'placed','Order placed by buyer.','2026-10-18 23:10:29','2026-10-18 23:10:29'),(44,19,39,'ready_for_pickup','Pickup confirmed by logistics for Oct 19, 2026.','2026-10-19 05:24:57','2026-10-19 05:24:57'),(45,19,39,'picked_up','Parcel picked up from seller (logistics).','2026-10-19 05:24:57','2026-10-19 05:24:57'),(46,19,39,'in_transit','Parcel at hub, to sort (logistics).','2026-10-19 05:24:57','2026-10-19 05:24:57'),(47,19,39,'in_transit','Sorted at the hub for Cabuyao, Laguna.','2026-10-19 05:24:57','2026-10-19 05:24:57'),(48,19,39,'in_transit','Assigned to rider Test Rider.','2026-10-19 05:24:57','2026-10-19 05:24:57');
/*!40000 ALTER TABLE `order_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'placed',
  `subtotal` decimal(12,2) NOT NULL,
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cod',
  `courier_choice` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `placed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `voucher_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `logistics_provider_id` bigint unsigned DEFAULT NULL,
  `shipping_zone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_user_id_foreign` (`user_id`),
  KEY `orders_seller_id_foreign` (`seller_id`),
  KEY `orders_status_index` (`status`),
  KEY `orders_logistics_provider_id_foreign` (`logistics_provider_id`),
  CONSTRAINT `orders_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ZAY-260916-U80ZHTBC',1,NULL,'cancelled',1599.00,120.00,1719.00,'PHP','cod',NULL,'Jan Reyben Del Mundo','09123456789','2100 San Luis Rd., Putho Tuntungin, Los Baños, Laguna, CALABARZON, 4030',NULL,'2026-09-18 14:42:11',NULL,'2026-09-16 05:56:16','2026-09-18 06:42:11',NULL,0.00,NULL,NULL,NULL),(2,'ZAY-260918-E5WZ8KUF',1,NULL,'cancelled',3499.00,120.00,3619.00,'PHP','gcash',NULL,'Jan Reyben Del Mundo','09123456789','2100 San Luis Rd., Putho Tuntungin, Los Baños, Laguna, CALABARZON, 4030',NULL,'2026-09-18 14:42:08',NULL,'2026-09-18 06:42:00','2026-09-18 06:42:08',NULL,0.00,NULL,NULL,NULL),(3,'ZAY-260919-BAGNZBMU',1,NULL,'cancelled',44982.00,120.00,45102.00,'PHP','cod',NULL,'Jan Reyben Del Mundo','09123456789','2100 San Luis Rd., Putho Tuntungin, Los Baños, Laguna, CALABARZON, 4030',NULL,'2026-09-20 01:32:24',NULL,'2026-09-18 20:57:19','2026-09-19 17:32:24',NULL,0.00,NULL,NULL,NULL),(6,'ZAY-260923-3KVESAYP',1,NULL,'cancelled',2697.00,120.00,2817.00,'PHP','cod',NULL,'Jan Reyben Del Mundo','09123456789','2100 San Luis Rd., Putho Tuntungin, Los Baños, Laguna, CALABARZON, 4030',NULL,'2026-09-23 02:43:27',NULL,'2026-09-22 18:43:17','2026-09-22 18:43:27',NULL,0.00,NULL,NULL,NULL),(7,'ZAY-260923-LAC4SZLX',1,NULL,'placed',5397.00,120.00,5517.00,'PHP','cod',NULL,'Jan Reyben Del Mundo','09123456789','2100 San Luis Rd., Putho Tuntungin, Los Baños, Laguna, CALABARZON, 4030',NULL,'2026-09-22 18:55:46',NULL,'2026-09-22 18:55:46','2026-09-22 18:55:46',NULL,0.00,NULL,NULL,NULL),(8,'ZAY-260923-NKSVCMAH',1,11,'completed',77988.00,120.00,78108.00,'PHP','cod',NULL,'Jan Reyben Del Mundo','09123456789','2100 San Luis Rd., Putho Tuntungin, Los Baños, Laguna, CALABARZON, 4030',NULL,'2026-09-25 07:13:53','2026-09-24 23:13:53','2026-09-22 21:36:11','2026-09-24 23:13:53',NULL,0.00,NULL,NULL,NULL),(10,'ZAY-SAMPLE-QEAKIM7J',21,11,'ready_for_pickup',18497.00,49.00,18546.00,'PHP','cod',NULL,'Maria Santos','09171110001','12 Rizal St., Brgy. San Isidro, Cabuyao, Laguna, CALABARZON, 4025','Please call when you arrive.','2026-10-10 10:11:51',NULL,'2026-10-10 10:11:51','2026-10-10 11:30:51',NULL,0.00,NULL,NULL,NULL),(11,'ZAY-SAMPLE-M4SEUEKI',21,11,'ready_for_pickup',5999.00,49.00,6048.00,'PHP','gcash',NULL,'Paolo Reyes','09171110002','45 Mabini Ave., Brgy. Parian, Calamba, Laguna, CALABARZON, 4027',NULL,'2026-10-10 10:07:52',NULL,'2026-10-10 10:07:52','2026-10-10 11:30:52',NULL,0.00,NULL,NULL,NULL),(12,'ZAY-SAMPLE-FMJHZNOI',21,11,'completed',18997.00,49.00,19046.00,'PHP','cod',NULL,'Andrea Cruz','09171110003','Blk 7 Lot 3, Mabuhay Homes, Brgy. Real, Calamba, Laguna, CALABARZON, 4027','Please call when you arrive.','2026-10-10 19:43:31','2026-10-10 11:43:31','2026-10-10 10:06:52','2026-10-10 11:43:31',NULL,0.00,NULL,NULL,NULL),(13,'ZAY-261012-Z8TXDKSD',21,NULL,'placed',6096.00,120.00,6216.00,'PHP','cod',NULL,'Melany CIJO','09171234567','Calusiche Trading Store, Calusiche Trading Store, Calusiche, Pagsanjan, Laguna, CALABARZON, 4008',NULL,'2026-10-12 00:37:17',NULL,'2026-10-12 00:37:17','2026-10-12 00:37:17',NULL,0.00,NULL,NULL,NULL),(14,'ZAY-261017-XFX6AYAL',21,NULL,'placed',1548.00,120.00,1668.00,'PHP','cod',NULL,'Melany CIJO','09171234567','Calusiche Trading Store, Calusiche Trading Store, Calusiche, Pagsanjan, Laguna, CALABARZON, 4008',NULL,'2026-10-16 23:40:54',NULL,'2026-10-16 23:40:54','2026-10-16 23:40:54',NULL,0.00,NULL,NULL,NULL),(15,'ZAY-261017-42VP1YRK',21,11,'placed',53991.00,120.00,54111.00,'PHP','cod',NULL,'Melany CIJO','09171234567','Calusiche Trading Store, Calusiche Trading Store, Calusiche, Pagsanjan, Laguna, CALABARZON, 4008',NULL,'2026-10-16 23:40:54',NULL,'2026-10-16 23:40:54','2026-10-16 23:40:54',NULL,0.00,NULL,NULL,NULL),(16,'ZAY-261018-M9MZU0CW',21,NULL,'placed',998.00,120.00,1118.00,'PHP','cod',NULL,'Melany CIJO','09171234567','Calusiche Trading Store, Calusiche Trading Store, Calusiche, Pagsanjan, Laguna, CALABARZON, 4008',NULL,'2026-10-17 20:15:52',NULL,'2026-10-17 20:15:52','2026-10-17 20:15:52',NULL,0.00,NULL,NULL,NULL),(17,'ZAY-261018-LTPUJ488',21,NULL,'placed',1497.00,120.00,1617.00,'PHP','cod',NULL,'Melany CIJO','09171234567','Calusiche Trading Store, Calusiche Trading Store, Calusiche, Pagsanjan, Laguna, CALABARZON, 4008',NULL,'2026-10-17 20:16:24',NULL,'2026-10-17 20:16:24','2026-10-17 20:16:24',NULL,0.00,NULL,NULL,NULL),(18,'ZAY-261019-FGOGIUYD',21,NULL,'placed',3499.00,120.00,3619.00,'PHP','cod',NULL,'Melany CIJO','09171234567','Calusiche Trading Store, Calusiche Trading Store, Calusiche, Pagsanjan, Laguna, CALABARZON, 4008',NULL,'2026-10-18 23:10:29',NULL,'2026-10-18 23:10:29','2026-10-18 23:10:29',NULL,0.00,NULL,NULL,NULL),(19,'ZAY-QRTEST-IK5CK9',21,11,'in_transit',18497.00,49.00,18546.00,'PHP','cod',NULL,'Maria Santos','09171110001','12 Rizal St., Brgy. San Isidro, Cabuyao, Laguna, CALABARZON, 4025','Please call when you arrive.','2026-10-19 13:24:57',NULL,'2026-10-19 05:24:57','2026-10-19 05:24:57',NULL,0.00,NULL,NULL,NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES ('reybendelmundo2005@gmail.com','$2y$12$3uqe4RZhnZI2TqX00ksyJu7ZmENzwftiGkdxTdnvP5YTBepq9kwzq','2026-10-03 20:09:28');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cod',
  `provider_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_idempotency_key_unique` (`idempotency_key`),
  UNIQUE KEY `payments_provider_reference_unique` (`provider_reference`),
  KEY `payments_order_id_foreign` (`order_id`),
  KEY `payments_status_index` (`status`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_chk_1` CHECK (json_valid(`payload`))
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,'cod',NULL,'cancelled',1719.00,'PHP','f5355f25-a64b-4ca6-ac0b-3ccc2f6b1b7e-1',NULL,NULL,'2026-09-16 05:56:17','2026-09-18 06:42:11'),(2,2,'gcash',NULL,'cancelled',3619.00,'PHP','42f2c85e-63d1-40a6-b7ba-0038e11beae0-2',NULL,NULL,'2026-09-18 06:42:00','2026-09-18 06:42:08'),(3,3,'cod',NULL,'cancelled',45102.00,'PHP','1f5652fb-0ad0-4870-9e72-9909f462bf2e-3',NULL,NULL,'2026-09-18 20:57:19','2026-09-19 17:32:24'),(6,6,'cod',NULL,'cancelled',2817.00,'PHP','f9bbb8fb-261d-49e2-bec1-911104d1b263-6',NULL,NULL,'2026-09-22 18:43:17','2026-09-22 18:43:27'),(7,7,'cod',NULL,'pending',5517.00,'PHP','38522909-66b6-4267-986b-22ba8fc5f8d1-7',NULL,NULL,'2026-09-22 18:55:46','2026-09-22 18:55:46'),(8,8,'cod',NULL,'paid',78108.00,'PHP','bcf513fa-ba30-4849-a363-e2ba040888f7-8',NULL,'2026-09-24 23:13:53','2026-09-22 21:36:11','2026-09-24 23:13:53'),(9,10,'cod',NULL,'pending',18546.00,'PHP','6dc20553-26ef-4f65-9e3c-26499a0f1ee8',NULL,NULL,'2026-10-10 10:11:51','2026-10-10 10:11:51'),(10,11,'gcash',NULL,'paid',6048.00,'PHP','c1a4af8e-8f3c-4eb0-bfe2-1444acb250a0',NULL,'2026-10-10 10:07:52','2026-10-10 10:07:52','2026-10-10 10:07:52'),(11,12,'cod',NULL,'paid',19046.00,'PHP','d6611b1d-52d1-4bc2-8382-d3bbf8262e6e',NULL,'2026-10-10 11:43:31','2026-10-10 10:06:52','2026-10-10 11:43:31'),(12,13,'cod',NULL,'pending',6216.00,'PHP','c2602709-3ac8-4ff7-9e1c-3c0befd426e3-13',NULL,NULL,'2026-10-12 00:37:17','2026-10-12 00:37:17'),(13,14,'cod',NULL,'pending',1668.00,'PHP','5ca7bdcd-69dd-485f-92f8-8288251d9518-14',NULL,NULL,'2026-10-16 23:40:54','2026-10-16 23:40:54'),(14,15,'cod',NULL,'pending',54111.00,'PHP','5ca7bdcd-69dd-485f-92f8-8288251d9518-15',NULL,NULL,'2026-10-16 23:40:54','2026-10-16 23:40:54'),(15,16,'cod',NULL,'pending',1118.00,'PHP','f18b66ba-d9e9-441f-80ca-f4676b9ad389-16',NULL,NULL,'2026-10-17 20:15:52','2026-10-17 20:15:52'),(16,17,'cod',NULL,'pending',1617.00,'PHP','b42bc1a9-0bdd-4548-9d08-89400b9ebb3b-17',NULL,NULL,'2026-10-17 20:16:24','2026-10-17 20:16:24'),(17,18,'cod',NULL,'pending',3619.00,'PHP','ae0c1ee4-1443-4b7a-8c5f-ed9272dbc49d-18',NULL,NULL,'2026-10-18 23:10:29','2026-10-18 23:10:29'),(18,19,'cod',NULL,'pending',18546.00,'PHP','8bff5e24-90da-4476-9d02-bd43ab4e5fd1',NULL,NULL,'2026-10-19 05:24:57','2026-10-19 05:24:57');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pending_registrations`
--

DROP TABLE IF EXISTS `pending_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `documents` text COLLATE utf8mb4_unicode_ci,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `code_expires_at` timestamp NULL DEFAULT NULL,
  `code_sent_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_registrations_token_unique` (`token`),
  KEY `pending_registrations_email_index` (`email`),
  KEY `pending_registrations_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pending_registrations`
--

LOCK TABLES `pending_registrations` WRITE;
/*!40000 ALTER TABLE `pending_registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `pending_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (2,'App\\Models\\User',21,'ZAYLO android','b75e11582f64a1a7c30b463771d02f2e32f0c658e17670339a1a80acc0c5e2b6','[\"*\"]','2026-10-10 05:19:50',NULL,'2026-10-08 06:14:03','2026-10-10 05:19:50'),(16,'App\\Models\\User',30,'ZAYLO android','ead46ed2c223d3c6afaaf5fd5d3022c2482dbe3d5a80bd8c4d90a03c44fee97a','[\"*\"]','2026-10-10 23:04:29',NULL,'2026-10-10 22:43:29','2026-10-10 23:04:29'),(19,'App\\Models\\User',30,'ZAYLO android','896fef17ec62163cf9d5f20d5fa2301c015bc404963b32c7364f796926b88f6a','[\"*\"]','2026-10-12 00:54:17',NULL,'2026-10-12 00:38:17','2026-10-12 00:54:17'),(21,'App\\Models\\User',30,'ZAYLO android','90087e89818b78fc412695702b39882f21e70375f372a7a5a3e3646e052da903','[\"*\"]','2026-10-15 22:16:30',NULL,'2026-10-15 22:15:58','2026-10-15 22:16:30'),(23,'App\\Models\\User',21,'ZAYLO android','e672fda20da5aca0345109100058251b529a7a03460f7388347c7d0695d6cc8a','[\"*\"]','2026-10-19 05:20:27',NULL,'2026-10-17 20:14:43','2026-10-19 05:20:27'),(26,'App\\Models\\User',21,'ZAYLO android','ecc475c692d94530c71baf6c8434d567c8d08b57260585e01b0a92e3095be566','[\"*\"]','2026-10-18 23:29:01',NULL,'2026-10-18 23:27:58','2026-10-18 23:29:01'),(29,'App\\Models\\User',21,'ZAYLO android','8590784465f8f1ef49b5d630e5501013ab87c8e684ac3e383116322b73b6671b','[\"*\"]','2026-10-19 06:02:58',NULL,'2026-10-19 05:32:55','2026-10-19 06:02:58');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_policies`
--

DROP TABLE IF EXISTS `platform_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_policies_slug_unique` (`slug`),
  KEY `platform_policies_updated_by_foreign` (`updated_by`),
  CONSTRAINT `platform_policies_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_policies`
--

LOCK TABLES `platform_policies` WRITE;
/*!40000 ALTER TABLE `platform_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_product_id_category_unique` (`product_id`,`category`),
  KEY `product_categories_category_index` (`category`),
  CONSTRAINT `product_categories_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,31,'storage_boxes','2026-11-01 07:04:44','2026-11-01 07:04:44'),(2,34,'pots','2026-11-01 07:04:44','2026-11-01 07:04:44'),(3,22,'shoe_racks','2026-11-01 07:04:44','2026-11-01 07:04:44'),(4,29,'pruning_shears','2026-11-01 07:04:44','2026-11-01 07:04:44'),(5,30,'indoor_plant_pots','2026-11-01 07:04:44','2026-11-01 07:04:44');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,1,'/images/1.2L Long Spout Plastic Watering Can Green.webp','1.2L Long Spout Watering Can - photo 1',0,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(2,1,'/images/1.2L Long Spout Plastic Watering Can Pink.webp','1.2L Long Spout Watering Can - photo 2',1,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(3,1,'/images/1.2L Long Spout Plastic Watering Can White.webp','1.2L Long Spout Watering Can - photo 3',2,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(4,2,'/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle.webp','Bully Tools 14-Gauge Round Point Shovel - photo 1',0,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(5,2,'/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle1.webp','Bully Tools 14-Gauge Round Point Shovel - photo 2',1,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(6,2,'/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle2.webp','Bully Tools 14-Gauge Round Point Shovel - photo 3',2,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(7,2,'/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle3.webp','Bully Tools 14-Gauge Round Point Shovel - photo 4',3,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(8,3,'/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green.webp','COOLJOB Gardening Gloves - 6 Pairs - photo 1',0,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(9,3,'/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green1.webp','COOLJOB Gardening Gloves - 6 Pairs - photo 2',1,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(10,3,'/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green2.webp','COOLJOB Gardening Gloves - 6 Pairs - photo 3',2,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(11,3,'/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green3.webp','COOLJOB Gardening Gloves - 6 Pairs - photo 4',3,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(12,4,'/images/Garden Hoe,6 in Steel Blade Westward 2mvt3.webp','Westward 6-Inch Steel Blade Garden Hoe - photo 1',0,'2026-09-09 02:42:43','2026-09-17 22:27:44'),(13,5,'/images/drive-products/102PL7FkCWbre8qYX_o2qPdadiJWSfvhZ.webp','NATTJASMIN Fitted Sheet - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(14,5,'/images/drive-products/1OFNf8F1uvsKgI78OUDxZuH5c6_h2zduI.webp','NATTJASMIN Fitted Sheet - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(15,5,'/images/drive-products/1BhbOyY4FAiExjRzJa4AbisyDBLvlWe4-.webp','NATTJASMIN Fitted Sheet - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(16,5,'/images/drive-products/1hwvhcBVoy2ABaLG8usmMhfPbVxeMsXbB.webp','NATTJASMIN Fitted Sheet - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(17,5,'/images/drive-products/1CRWBqCsVu3e7eNCw3WOPqFa557FYgTzB.webp','NATTJASMIN Fitted Sheet - photo 5',4,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(18,5,'/images/drive-products/1i4NyD6Cuc6GbTlLZ_EdoH-ZpsRz2X7JF.webp','NATTJASMIN Fitted Sheet - photo 6',5,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(19,5,'/images/drive-products/1AMLNlJGra16PUdXnaJxvhpsDAUw5OuLN.webp','NATTJASMIN Fitted Sheet - photo 7',6,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(20,5,'/images/drive-products/1CggSKlB5KknGFYcDhX9XCkimF4jsqNla.webp','NATTJASMIN Fitted Sheet - photo 8',7,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(21,5,'/images/drive-products/1-dIfs4j0hT0HcKcKbWC_pQqVnYWkXuNG.webp','NATTJASMIN Fitted Sheet - photo 9',8,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(22,6,'/images/drive-products/1qhHc5i1cC_kKzIlXwxKeWHLm9fvPBr54.webp','LÖKSTAMFLY Ergonomic Pillow - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(23,6,'/images/drive-products/1sPuivF209oPtyTrKtitLfCJCZnAOcmfe.webp','LÖKSTAMFLY Ergonomic Pillow - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(24,6,'/images/drive-products/1Q-0zCja2hXd4TJvE9x_rklbyNxzFoKvW.webp','LÖKSTAMFLY Ergonomic Pillow - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(25,7,'/images/drive-products/1Dr_3tpQJCJTl_1Y-3GQ3j9K73iqgnjyV.webp','Yedwo Double Layer Knitted Blanket - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(26,7,'/images/drive-products/1cA6RaJLWR0lp1ozANxD5RGewbaLVVn3U.webp','Yedwo Double Layer Knitted Blanket - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(27,7,'/images/drive-products/1-MSepqMpLztbqPhyd_AumUKJJX1tKKkw.webp','Yedwo Double Layer Knitted Blanket - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(28,7,'/images/drive-products/1am-nbgPlQafNFXCwXZwXBo9cLWgL_7Cp.webp','Yedwo Double Layer Knitted Blanket - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(29,8,'/images/drive-products/1zKJlyWw_Up7kMxiCWs3VpDZuk0VinLX-.webp','Luxury Spa Collection Luxe Towel - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(30,8,'/images/drive-products/1T2JYQrkG-2AJpppEsXPU6hV2DPB-78gC.webp','Luxury Spa Collection Luxe Towel - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(31,8,'/images/drive-products/1ZtKPMWb_GJqxYNzlR6bP9yB4tjfjkgrl.webp','Luxury Spa Collection Luxe Towel - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(32,8,'/images/drive-products/1uKkWAVo4uFpp8LsCKcEQqfOKAy1Gf7E2.webp','Luxury Spa Collection Luxe Towel - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(33,9,'/images/drive-products/1RGWc_jRI7Sb5C36-69Mm1u7aBSJefxe5.webp','Liana Geometric Shower Curtain - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(34,9,'/images/drive-products/1HetX-BOz4uWg5bRxa03-wksiJZqltoGi.webp','Liana Geometric Shower Curtain - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(35,9,'/images/drive-products/14Zlz9SQFznuJlcLZN5iB8xBSJ3wNcM2F.webp','Liana Geometric Shower Curtain - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(36,9,'/images/drive-products/1HV86NebKYoguVEIFeJoXbwkLInsNNvKY.webp','Liana Geometric Shower Curtain - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(37,10,'/images/drive-products/1EMl1KTgFLy6HAUbRZpCxg8OxIboz_XVi.webp','Kathy Ireland 6-Piece Comforter Set - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(38,10,'/images/drive-products/15iawaLVjVfCakzTNXh371hSPfn2bHqEJ.webp','Kathy Ireland 6-Piece Comforter Set - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(39,10,'/images/drive-products/17kyS11fIt2DnKtFt_ca0GSAm_V-KJfXX.webp','Kathy Ireland 6-Piece Comforter Set - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(40,10,'/images/drive-products/1MCWfCGkuUrSsHWuWZBrXEVmD3Zi_N34c.webp','Kathy Ireland 6-Piece Comforter Set - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(41,10,'/images/drive-products/1U42_Uu-cY_NnpNcS7jqHSzHABa0yPad7.webp','Kathy Ireland 6-Piece Comforter Set - photo 5',4,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(42,10,'/images/drive-products/1R0dDfj2_2kIT29hpV8VtntRR1eIvrS_A.webp','Kathy Ireland 6-Piece Comforter Set - photo 6',5,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(43,11,'/images/drive-products/1l4MTRyzVEF8yusrfPcTBFsJsGzVNkFeq.webp','Secret Linen Store Linen Pillowcase - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(44,11,'/images/drive-products/1N4caIaIi7N-ZNb1ZKbqRlKmM7sqP6CjO.webp','Secret Linen Store Linen Pillowcase - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(45,11,'/images/drive-products/1Hnn4JB694LoBO6xVbHpAgI-wbyg7sejM.webp','Secret Linen Store Linen Pillowcase - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(46,11,'/images/drive-products/1SiHGVHV1wRbnK5Q_1v09n6vfjT0ikvsc.webp','Secret Linen Store Linen Pillowcase - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(47,12,'/images/drive-products/1tZ5sYRmYt0ESYVHmhea_pcfrsDtd6P-F.webp','LUDDROS Mattress Protector - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(48,12,'/images/drive-products/1hZpFOSTxuPBY2pw36C63Umzf1hPZRPAb.webp','LUDDROS Mattress Protector - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(49,12,'/images/drive-products/1lbGVxlP804c9s8lvCPc_qVYMoI_XK2hx.webp','LUDDROS Mattress Protector - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(50,13,'/images/drive-products/1hL38jLZrFo4Bvh8v58Btjdrh4yt825Ef.webp','IKEA TOFTBO Bath Mat - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(51,13,'/images/drive-products/1rj_oLao3tPR5NyjFuP5-hzcYYt-caPtJ.webp','IKEA TOFTBO Bath Mat - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(52,13,'/images/drive-products/1L1jrfCkpwfJr1KwFuwRmZsbODN28OEMU.webp','IKEA TOFTBO Bath Mat - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(53,13,'/images/drive-products/1axjfHP6TEpwnKGvENnRNQOln5LWY57uZ.webp','IKEA TOFTBO Bath Mat - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(54,13,'/images/drive-products/1bLKVpz7j-IAcWwQPe1aumqKmrSc95HpO.webp','IKEA TOFTBO Bath Mat - photo 5',4,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(55,13,'/images/drive-products/1EYifIvL4yYKVvlmshMbYghDeJUNSAgyE.webp','IKEA TOFTBO Bath Mat - photo 6',5,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(56,14,'/images/drive-products/1e5tVcQm5ZTAh16F0fdU_9VY-jni3j2_B.webp','3-Layer Bathroom Storage Rack - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(57,14,'/images/drive-products/1WQ71-yx_Kup0yAaaMHPxq01ykpNH3J7M.webp','3-Layer Bathroom Storage Rack - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(58,14,'/images/drive-products/1b8SQL5xjKRXQn6g2Cw_sknORWz6-g4kx.webp','3-Layer Bathroom Storage Rack - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(59,14,'/images/drive-products/1TBEeXsw1AXCVk-H2oKr5Id39dQOOKwEK.webp','3-Layer Bathroom Storage Rack - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(60,15,'/images/drive-products/1-BE84UPb1BGnpdn6ctyOqfa-cEj5OHQ_.webp','Mandaue Foam Alara High-Back Office Chair - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(61,15,'/images/drive-products/1kabj_GPrVKVVHU-k6a3tMAbWy-3AmnUs.webp','Mandaue Foam Alara High-Back Office Chair - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(62,15,'/images/drive-products/171r_lqe0n8HbXd_D5QuF2Qeuz-BqDw4A.webp','Mandaue Foam Alara High-Back Office Chair - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(63,15,'/images/drive-products/1gj0sw39laLMVthl4Rcrc-Tx8v6y4DoQI.webp','Mandaue Foam Alara High-Back Office Chair - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(64,16,'/images/drive-products/1ztymv3feTrpoqBQ5TfzOtHDtUdITyoEy.webp','Marble-Top Round Coffee Table - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(65,16,'/images/drive-products/1QaTG58EYS4UZdw-l6wY5Ylc88M_ilQVu.jpg','Marble-Top Round Coffee Table - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(66,16,'/images/drive-products/1n6sHLs06Y_gFj1-gUrnFuxn9kHsFbVB8.jpg','Marble-Top Round Coffee Table - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(67,16,'/images/drive-products/1cjeMcy6ABsVZ798KW8XcCcs8MwvbT4Xt.jpg','Marble-Top Round Coffee Table - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(68,17,'/images/drive-products/1k3t_r5KwjwpTHApgqYLSc6PMTXvYDY3P.webp','Apolda Natural Oak Bookshelf - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(69,17,'/images/drive-products/1d71_qs0tmygGh-7fxbsqt4wKilFKsl31.jpg','Apolda Natural Oak Bookshelf - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(70,17,'/images/drive-products/1sM5CAuT_gb4ImONNZshVgcaGDFPBgr8V.webp','Apolda Natural Oak Bookshelf - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(71,18,'/images/drive-products/1R-26mMG3sjZeozM9O6npSds5b8NVzLet.jpg','Lucent Wall Clock - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(72,18,'/images/drive-products/1polTginWjUIVg-aJmvtcgih9MXV8UG-o.webp','Lucent Wall Clock - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(73,18,'/images/drive-products/1RU7H9Gvg2PPumq7doQVPRGv4s-Ve_2bl.webp','Lucent Wall Clock - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(74,19,'/images/drive-products/1nElnBdqOtRUfGpNuRycTW453eFrp-5KG.webp','Modern Table Lamp - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(75,19,'/images/drive-products/1oJ3rfGq0O7k1hhy4FwNr6giJXEF-AAfa.webp','Modern Table Lamp - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(76,19,'/images/drive-products/1JdusTvrKXHEQzI7axNWTOd5uEg3vuKgK.webp','Modern Table Lamp - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(77,19,'/images/drive-products/1Am1C401b_rPoXB-gMYFjdLwqFR2rlfj1.webp','Modern Table Lamp - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(78,20,'/images/drive-products/1vyzWeGMSPk6S1A5X05RJngRJvw6tkM4D.webp','Nina Two-Drawer Bedside Table - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(79,20,'/images/drive-products/1jxZlWPKCSC9mKZnRlwrfCX1SUIdiLt70.webp','Nina Two-Drawer Bedside Table - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(80,20,'/images/drive-products/17ffhpctzFTFsq8s0CbUgI5qnkFtmWkI9.webp','Nina Two-Drawer Bedside Table - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(81,20,'/images/drive-products/1qbZt0SrhrfvvMZIjkM9lylge6gTZOKMQ.jpg','Nina Two-Drawer Bedside Table - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(82,21,'/images/drive-products/1wP3udCQGhPQDuLgu-K1Y9aZjghEJ8eaq.webp','Farkins TV Stand - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(83,21,'/images/drive-products/1LOvu2m9bYAHDDo3nZSdTzobm6FfXjImP.webp','Farkins TV Stand - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(84,21,'/images/drive-products/1yN82T00SJD3pjwY23uN7el5TzRlUrPon.webp','Farkins TV Stand - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(85,21,'/images/drive-products/1Zm_suvr0KNjLlUA2XrNDxL1IsHc_On8T.webp','Farkins TV Stand - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(86,22,'/images/drive-products/11qs9skjq8KDCgHtCLrE-iyaOLHsbN5s6.webp','Harris Three-Door Shoe Cabinet - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(87,22,'/images/drive-products/1e5eS75Uc75CgRbCxch_BOU3y_ycnAyVh.webp','Harris Three-Door Shoe Cabinet - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(88,22,'/images/drive-products/1o0UtISGUdyW1VmvQHSKHJK1QiJ72zoOS.webp','Harris Three-Door Shoe Cabinet - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(89,22,'/images/drive-products/1If2qpv11naMy4Rwkt1FAjfS-nKQ1yGHC.webp','Harris Three-Door Shoe Cabinet - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(90,23,'/images/drive-products/1PT_7ZyTib92G0myQodtFufcuDhXtCOIC.jpg','Possini Euro Serra Floor Lamp - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(91,23,'/images/drive-products/1HkQ2gn6Y8tLmOF_1cIMLLEEN_1NhybTr.jpg','Possini Euro Serra Floor Lamp - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(92,23,'/images/drive-products/1S29iLQ-BTZkGx1geuAGJRN2dXsxYWZTB.jpg','Possini Euro Serra Floor Lamp - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(93,23,'/images/drive-products/1lD-23yiYgnYMSg0X7fRlIqrGSP-De72U.jpg','Possini Euro Serra Floor Lamp - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(94,23,'/images/drive-products/1IOU064vEjj2ufOhfkqOfqKsJR7f2wP-3.jpg','Possini Euro Serra Floor Lamp - photo 5',4,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(95,24,'/images/drive-products/1jT9ckQKPq6VxP3jD_M_FOc6lYBIUuhE5.webp','Black Round LED Decorative Mirror - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(96,24,'/images/drive-products/1ftr0BJNBqyJBW35g8zDo9bpvfbcj6st9.webp','Black Round LED Decorative Mirror - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(99,25,'/images/drive-products/1HnwpYjbwcekc1eXbzOhSZYbGIp3Y7kKb.webp','Total Pruning Shear - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(100,25,'/images/drive-products/1HxOnYy_lpCaMSSvIjJe45HxdHKKC4JC7.webp','Total Pruning Shear - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(101,25,'/images/drive-products/1NxyeVCGsHzFwkL9RwZub2_Oek_ZR3oas.webp','Total Pruning Shear - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(102,26,'/images/drive-products/1IEcTcuKEq1Kq38naToG_T8PFPCxmLbJH.webp','CT4281 Garden Rake - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(103,26,'/images/drive-products/1H7PHmJho3Ly5USrIXfBiZET6vyBa93gb.webp','CT4281 Garden Rake - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(104,26,'/images/drive-products/1N5ocj4WDzzVXv9eGyi1UYNm8aKvX2zus.webp','CT4281 Garden Rake - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(105,27,'/images/drive-products/10mf45q4io4oo83iW4bVHpLrV8Ego7Wek.webp','Edward Tools Garden Trowel - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(106,27,'/images/drive-products/10wlql2RvpKLwzQWR5vKJsmV6fpa4GRyJ.webp','Edward Tools Garden Trowel - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(107,27,'/images/drive-products/1I3J3PwkgF8170MTSqlcUQxJdfxGFDrOW.webp','Edward Tools Garden Trowel - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(108,28,'/images/drive-products/1ZCHnx96pyRGPI5oqqt0GpqzD3B0UM_R7.webp','Keeper Electric Plant Sprayer - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(109,28,'/images/drive-products/1XUasCPUGBw6TkXeGfecz7RUOuUt_-dFe.webp','Keeper Electric Plant Sprayer - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(110,28,'/images/drive-products/1nGgKyXKp4ycItQWqL05QL07Zv_gw4pFE.webp','Keeper Electric Plant Sprayer - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(111,29,'/images/drive-products/18BqtAoLkuJ7riTCuKNGI3cpqnmpiHRCu.webp','Amtech U0725 Garden Shears - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(112,29,'/images/drive-products/1VWtdzg5uC6MUuGbgRxuGzxQdv3ss29LI.webp','Amtech U0725 Garden Shears - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(113,29,'/images/drive-products/1FvXAArXMuCLvkGcjEH1ru9GiO_mifrgX.webp','Amtech U0725 Garden Shears - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(114,30,'/images/drive-products/1Cq3DU6AXvfocF2v1CV5e6BNfpmLKTmTC.webp','Bergs Potter Simona Pot - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(115,30,'/images/drive-products/14u4dXMSGIjpi0ldGZPifM4P-8gnjp9uR.webp','Bergs Potter Simona Pot - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(116,30,'/images/drive-products/14cdmjcsF77dV4MBZvwNzPqiYmZDlNWD_.webp','Bergs Potter Simona Pot - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(117,31,'/images/drive-products/1kszXE7LCgQEepLMLZiM8AO1E7Tx30J_r.jpg','Set of 4 Home Storage Bins - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(118,31,'/images/drive-products/1v90QyuN9wpBdsblV-h7iuSKU4NrW2ka6.webp','Set of 4 Home Storage Bins - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(119,31,'/images/drive-products/1nDmZOQJvqhAMGA_1G4LdmguoRNwdQ9l4.webp','Set of 4 Home Storage Bins - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(120,32,'/images/drive-products/1BsNMgAmY9SFYM2Qf5BaEA7uHTiHh7-iZ.jpg','Cleaning Basket - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(121,32,'/images/drive-products/1pHCu8tZU5PuCt_Ue8yfWq-sx_bv2OAGH.jpg','Cleaning Basket - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(122,32,'/images/drive-products/1c5hFokrmEpirXXkbednyjqmCvZjaEyHH.jpg','Cleaning Basket - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(123,33,'/images/drive-products/12SwCEYODQWUeUw7yF1JcloF9qEaxPjtv.webp','Multi-Tier Shoe Rack - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(124,33,'/images/drive-products/1JBbmWNGAh1j0ECCqVkx0fuhOFrXuYNG-.webp','Multi-Tier Shoe Rack - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(125,33,'/images/drive-products/1g6AQHO7e0YacuyIL1D6CcWwQ1BMUjodo.webp','Multi-Tier Shoe Rack - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(126,34,'/images/drive-products/1mryRPsGVJpM8NyNRGZYyxMzJ7KRQLxhy.jpg','Indoor Plant Pot - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(127,35,'/images/drive-products/1HbDsd6WBIQmCnfhcXBK7d28lHPMZ6BJ_.jpg','Laundry Basket - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(128,35,'/images/drive-products/1oTrBHK6WMM_v1q-APIq_TQO6x_YOpnCM.jpg','Laundry Basket - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(129,35,'/images/drive-products/1GKvl7JTn4q5iuSA4cH_gAMMTXS43OuvS.jpg','Laundry Basket - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(130,35,'/images/drive-products/1LANPOKCGgvewiKqPgkwWqdK1Yp3yY5xa.jpg','Laundry Basket - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(131,36,'/images/drive-products/1vZT--hgQgEeFiKQ-d_RSlvasQic3CYU8.jpg','Hi-Care Wheeled Trash Bin - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(134,37,'/images/drive-products/1xJtmRTK_P1d5r-7C74AH28TdI7nivpeM.jpg','Clothes Hanger Set - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(135,37,'/images/drive-products/1yXwrjFHS_TiJzhyoeEY49r_RR5v0rlEg.webp','Clothes Hanger Set - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(136,37,'/images/drive-products/11q9piG2RxpgLWc3MMELUBAmEJTSXNX-N.png','Clothes Hanger Set - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(137,38,'/images/drive-products/1xUpYWLCVs2R2EYcHC_-JdsLx_1oDHyBx.webp','Home Doormat - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(138,38,'/images/drive-products/1mLFLUNRdSPQySfmruvxADOT6oi7kEx5D.jpg','Home Doormat - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(139,38,'/images/drive-products/1jt4KSamBfYYVmAzofgW18XXdCqQFT34Y.jpg','Home Doormat - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(140,38,'/images/drive-products/18_cS3CYAMNZkREJJi0EafxeSRI5bOUG6.jpg','Home Doormat - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(141,39,'/images/drive-products/1DagST51dWEd2zrWCjO0t2c0aXTrlUOaH.webp','ATuMan RS1 Plus 42-in-1 Screwdriver Set - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(142,39,'/images/drive-products/1qvFJFTdHj9WF9YFGwf1ZFTBxR33567J8.webp','ATuMan RS1 Plus 42-in-1 Screwdriver Set - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(143,39,'/images/drive-products/1AL8C1tlmIGEbWOU5xPl0H6xE02dDWLe_.webp','ATuMan RS1 Plus 42-in-1 Screwdriver Set - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(144,40,'/images/drive-products/1VgYF-gCs0_2b9v_JJpSHZJ0Qr4tu4tm6.webp','Estwing Straight Claw Hammer - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(145,40,'/images/drive-products/1-9kWPi0GLjUNmmhwuaDG6BNxWdb59qrB.webp','Estwing Straight Claw Hammer - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(146,40,'/images/drive-products/1w-gyOtCGqUMZ1MsfXvZFeFdbeHrP0PaN.webp','Estwing Straight Claw Hammer - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(147,41,'/images/drive-products/1j26SuOoHp2D4Dx-AZRo4fNFL2eukZPNx.webp','RS PRO Steel Pliers Set PC0300 - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(148,41,'/images/drive-products/1rMYS2zVUzqf9dB0tfhn-tW1d_EJzBMhS.webp','RS PRO Steel Pliers Set PC0300 - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(149,41,'/images/drive-products/1WhclRcHafBSHQB-2eg442cMYuhMdMSyW.webp','RS PRO Steel Pliers Set PC0300 - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(150,42,'/images/drive-products/1q9qJbz1GNEiXKoSMarRLjZcbTBeecS2f.webp','RS PRO S85 5m Tape Measure - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(151,42,'/images/drive-products/1VRIuxGMX8UJ2Umfffd31MbGbtz5CXuSL.webp','RS PRO S85 5m Tape Measure - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(152,42,'/images/drive-products/1QMRQKoLi-MbbxBy2BEqrf7dGfjv5xB9Y.webp','RS PRO S85 5m Tape Measure - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(153,43,'/images/drive-products/1LAuG_1TbvYuYRBoiOQTb0KH19QgQKGyW.webp','Fein JMU 404 M Magnetic Base Drill - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(154,43,'/images/drive-products/1tBv0GoZjbcE4q0JvNC-Rj6c9C4fbZQnf.webp','Fein JMU 404 M Magnetic Base Drill - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(155,43,'/images/drive-products/1nHl5KhnaY7BgARMGmQEQQ8fn0Zw8zkHt.webp','Fein JMU 404 M Magnetic Base Drill - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(156,44,'/images/drive-products/1IvaieE0fO4S1GXohm9kOLJAhmI1v0-QT.webp','CT6083 26-Piece Ratcheting Wrench Set - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(157,44,'/images/drive-products/1p23zJIWvJtBMltBqusja5gpZEIQ7jQ28.webp','CT6083 26-Piece Ratcheting Wrench Set - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(158,44,'/images/drive-products/1vtCFTmiZ7oQ6Q4xdhuzfSvKKYmBgcXtX.webp','CT6083 26-Piece Ratcheting Wrench Set - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(159,45,'/images/drive-products/1nPc7KDLUX23loDGFJTMYmJ9dY826fKy4.webp','Stanley Compact Folding Utility Knife - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(160,45,'/images/drive-products/1JaFmH-p289mjmhXArD0mRLjMVs81CJex.webp','Stanley Compact Folding Utility Knife - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(161,45,'/images/drive-products/1AapfS7bsGKmP31dWQR8qTwv0BZSMDlBz.webp','Stanley Compact Folding Utility Knife - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(162,46,'/images/drive-products/11LJdjvxbRTr1Bv7D651lMvsHiqN6a5No.webp','CT0110 12-Inch Spirit Level - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(163,46,'/images/drive-products/1crYH-rf-mZZm-DI1pug7mq2v0qhBIeZ6.webp','CT0110 12-Inch Spirit Level - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(164,46,'/images/drive-products/1L9E3D8VNvUBY-z4Vh5pE0Jl1nhq4heb5.webp','CT0110 12-Inch Spirit Level - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(165,47,'/images/drive-products/1YzMfOksNvkS4gW-UxN8VCK5vZ96nt6SM.webp','Ingco Plastic Tool Box - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(166,47,'/images/drive-products/19CLJUB6Hslj9p3X4n5I4T9-TqFewfAu1.webp','Ingco Plastic Tool Box - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(167,47,'/images/drive-products/15QAUhhVkLkot-45ujV_XjDkIiSp4rT4T.webp','Ingco Plastic Tool Box - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(168,48,'/images/drive-products/1sI2_uYhPIVbZArTi67sH9iafrLtgMtnM.webp','Neilsen CT3997 Power Sander - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(169,48,'/images/drive-products/1tmLSjJh-z7yIyT_bkRV6nchVrJMB95Pt.webp','Neilsen CT3997 Power Sander - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(170,48,'/images/drive-products/1qp-76R2v4tx8e0vfo1B-HTd9kyW5s0WD.webp','Neilsen CT3997 Power Sander - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(171,49,'/images/drive-products/1GpqfZayr1KBwe8kqXf3w-2eCRKwnyU_7.jpg','Elite Gourmet Black Rice Cooker - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(174,50,'/images/drive-products/1AIgG3eSWbUU_zqTOgCbKpA-dCZ4MipXe.png','Professional Quiet Blender - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(175,51,'/images/drive-products/1RMdXr3xJOmvTHj3Ayy95jxUO1cbkMWWf.webp','Commercial Digital Blender - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(176,52,'/images/drive-products/1ut99JHBNfqHmlJPoLeCKH83988_TXJrG.avif','Green Electric Kettle - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(177,52,'/images/drive-products/1Hd0fzyp-7IVx9CSmFIAc0KR15WmT5uM_.jpg','Green Electric Kettle - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(178,53,'/images/drive-products/1fFa1-B5snjy1XhOoBFd8LSZ7uIlxPq5A.avif','Evoloop 1.7L Electric Kettle - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(179,53,'/images/drive-products/1WY76tRlvDbNOrqlYsuPpyc6v_7uwFMwq.avif','Evoloop 1.7L Electric Kettle - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(180,54,'/images/drive-products/1cJc3C7JZobwGXXFivIra4i-4uZ6OvR5P.jpg','Touchscreen Air Fryer - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(182,55,'/images/drive-products/1pNgnd-VwDqS4yrlSmwMbWg4twmMPQE6W.jpg','KITfanz Silver Electric Moka Pot - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:27:44'),(184,56,'/images/drive-products/1vnyNKQehn2J3sfVxWFj2MmXcTyOp4yo4.jpg','Electric Toaster - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(185,56,'/images/drive-products/1q7eVvA7bXtx80chlt2bsUm6l3oqQQpHa.jpg','Electric Toaster - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(186,56,'/images/drive-products/1elG5BqSB5aLSWU3ujdBaOqHHfYRQrZAt.jpg','Electric Toaster - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(187,57,'/images/drive-products/1a-TyBkDsgafjbSMQ-eXdqzso-Nlux5CB.jpg','Countertop Electric Oven - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(188,57,'/images/drive-products/10cBLuGFCbKA4jACRxZAU0Y9QbdsOhzHx.jpg','Countertop Electric Oven - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(189,57,'/images/drive-products/1xFdASl8h_gStstgxNXQEG0E1o4ihNYrv.jpg','Countertop Electric Oven - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(190,58,'/images/drive-products/1p9JGgdBkYsI39ej-cZl401EXZmS1tsBK.jpg','Food Processor - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(191,58,'/images/drive-products/132SWVzn-VhoM95URKX3nvYZaVdGip3Pk.jpg','Food Processor - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(192,58,'/images/drive-products/1If8V-C60zkWwEZRxXkpEsKDeGeeuW2t-.jpg','Food Processor - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(193,58,'/images/drive-products/1iKhB-YeSiHVAlvBVkERD1XrlzhqiufRK.jpg','Food Processor - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(194,59,'/images/drive-products/1dnfLbkrOu9pDeRZB18nbQxHXHBcQoBrJ.webp','Hamilton Beach Professional Hand Mixer - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(195,59,'/images/drive-products/1LDOmCQVHY6issJjCteDtvq8H-IKjr6qg.jpg','Hamilton Beach Professional Hand Mixer - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(196,59,'/images/drive-products/1OGxdMTMVkKIJSDtERDyckf25VVVop_DY.jpg','Hamilton Beach Professional Hand Mixer - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(197,59,'/images/drive-products/1mnmmGSw1pM5cHv9vbnGgZBLwaH3qaamq.avif','Hamilton Beach Professional Hand Mixer - photo 4',3,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(198,60,'/images/drive-products/1_dtQj3XN3uTzFxIIDHNSlcN92HwMyhte.webp','Nike Dunk Low Retro Panda - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(199,60,'/images/drive-products/19y435hjqkYhTIWUericfwrOyfKKXwa2P.webp','Nike Dunk Low Retro Panda - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(200,60,'/images/drive-products/1tSmVsjJrmXp3lKvyJYh2gMtCGUnAml3E.webp','Nike Dunk Low Retro Panda - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(201,61,'/images/drive-products/1eXy0ZOMWuofoegKilqjqJCwWrINvDEUS.webp','Nike Dunk Low Pink and White - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(202,61,'/images/drive-products/1t-aYPuLEYtn6jsqfW6zBNzIBkh08YF2d.webp','Nike Dunk Low Pink and White - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(203,61,'/images/drive-products/1g0SHzvBXxN4SQmj7LBoPwUdWWZHWrl7b.webp','Nike Dunk Low Pink and White - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(204,62,'/images/drive-products/13BgyEQc4GZS1jrogN2BFm_RFN5-7BPeR.webp','Nike WMNS Dunk Low Olive - photo 1',0,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(205,62,'/images/drive-products/1ljJ1m1plf_FZDWvS_LnSyzbBmIEn3f0M.webp','Nike WMNS Dunk Low Olive - photo 2',1,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(206,62,'/images/drive-products/15avkrmrsX6cdtK6Kh5F1zYxqWAFVYgDr.webp','Nike WMNS Dunk Low Olive - photo 3',2,'2026-09-17 22:20:23','2026-09-17 22:20:23'),(207,63,'/images/drive-products/1QTwSce9lur3kFCx6HcZlzPqndd0QFQxk.webp','Gold Round LED Decorative Mirror - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(208,64,'/images/drive-products/1l1Ng0uor4kWo3RBr2ToAelJkVTMKrBT9.webp','Plain Black Round Decorative Mirror - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(209,65,'/images/drive-products/1n47HB1gz9L9P4bVZ6EpXo2Lz1nqZOWgk.webp','Kenpoly Wheeled Trash Bin - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(210,66,'/images/drive-products/1XF7166cpY5JJ6eepblLF-V5m4lbcJ--y.webp','Bentley Wheeled Trash Bin - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(211,67,'/images/drive-products/12Umv1XB0sIds6Lxi6yaK_l30kc4_hLTb.jpg','Elite Gourmet White Rice Cooker - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(212,67,'/images/drive-products/1V--JVwyGmIj4cjNUU03etR-R91sEfdaq.avif','Elite Gourmet White Rice Cooker - photo 2',1,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(213,68,'/images/drive-products/1wA3lxK_e5ezNAOhViVKFo65GFn-YZfHD.jpg','Compact Black Air Fryer - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44'),(214,69,'/images/drive-products/1nTfydyYGxiuww0CL7YesZIDQ1K88MXQg.jpg','KITfanz Black Electric Moka Pot - photo 1',0,'2026-09-17 22:27:44','2026-09-17 22:27:44');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_reviews_order_item_id_unique` (`order_item_id`),
  KEY `product_reviews_user_id_foreign` (`user_id`),
  KEY `product_reviews_product_id_created_at_index` (`product_id`,`created_at`),
  KEY `product_reviews_product_id_user_id_index` (`product_id`,`user_id`),
  CONSTRAINT `product_reviews_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_chk_1` CHECK (json_valid(`photos`))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
INSERT INTO `product_reviews` VALUES (1,10,60,1,4,NULL,'[]','2026-10-05 00:10:47','2026-10-05 00:10:47');
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_shop_section`
--

DROP TABLE IF EXISTS `product_shop_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_shop_section` (
  `shop_section_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`shop_section_id`,`product_id`),
  KEY `product_shop_section_product_id_index` (`product_id`),
  CONSTRAINT `product_shop_section_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_shop_section_shop_section_id_foreign` FOREIGN KEY (`shop_section_id`) REFERENCES `shop_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_shop_section`
--

LOCK TABLES `product_shop_section` WRITE;
/*!40000 ALTER TABLE `product_shop_section` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_shop_section` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  KEY `product_variants_product_id_foreign` (`product_id`),
  CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (1,1,'ZAY-WCAN-12-GRN','Green',NULL,'Green',499.00,16,1,'2026-09-09 02:42:43','2026-10-17 20:15:52'),(2,1,'ZAY-WCAN-12-PNK','Pink',NULL,'Pink',499.00,18,1,'2026-09-09 02:42:43','2026-09-09 02:42:43'),(3,1,'ZAY-WCAN-12-WHT','White',NULL,'White',499.00,15,1,'2026-09-09 02:42:43','2026-10-17 20:16:24'),(4,3,'ZAY-CJGLV-6-S','Small','S','Red & Green',899.00,12,1,'2026-09-09 02:42:43','2026-09-22 18:43:27'),(5,3,'ZAY-CJGLV-6-M','Medium','M','Red & Green',899.00,12,1,'2026-09-09 02:42:43','2026-09-09 02:42:43'),(6,3,'ZAY-CJGLV-6-L','Large','L','Red & Green',899.00,12,1,'2026-09-09 02:42:43','2026-09-09 02:42:43');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `badge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `review_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `low_stock_threshold` int unsigned NOT NULL DEFAULT '5',
  `weight_grams` int unsigned DEFAULT NULL,
  `sale_type` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_value` decimal(10,2) DEFAULT NULL,
  `sale_starts_at` datetime DEFAULT NULL,
  `sale_ends_at` datetime DEFAULT NULL,
  `sale_variant_id` bigint unsigned DEFAULT NULL,
  `sale_is_flash` tinyint(1) NOT NULL DEFAULT '0',
  `flash_quantity` int unsigned DEFAULT NULL,
  `flash_sold` int unsigned NOT NULL DEFAULT '0',
  `custom_tags` json DEFAULT NULL,
  `is_adult` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_seller_id_foreign` (`seller_id`),
  KEY `products_archived_at_index` (`archived_at`),
  KEY `products_review_status_index` (`review_status`),
  KEY `products_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `products_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,45,'1.2L Long Spout Watering Can','watering_cans',NULL,'A compact 1.2-liter watering can with a slim stainless-steel spout for controlled watering around leaves and delicate houseplants.',499.00,649.00,'New','/images/1.2L Long Spout Plastic Watering Can Green.webp',49,1,'approved',NULL,NULL,NULL,NULL,'2026-09-09 02:42:43','2026-11-01 07:04:46','12l-long-spout-watering-can-52c0er','ZAY-LVEKN0JH',9,320,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(2,45,'Bully Tools 14-Gauge Round Point Shovel','garden_shovels',NULL,'A heavy-duty 14-gauge round-point shovel with a reinforced fiberglass handle, designed for digging, planting, and moving compact soil.',1599.00,1899.00,'Best Seller','/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle.webp',15,1,'approved',NULL,NULL,NULL,NULL,'2026-09-09 02:42:43','2026-11-01 07:04:46','bully-tools-14-gauge-round-point-shovel-etoryl','ZAY-L1ANEVUN',4,2300,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(3,45,'COOLJOB Gardening Gloves - 6 Pairs','garden_gloves','women','Six pairs of breathable garden work gloves with flexible latex-coated palms, reinforced fingertips, and elastic wrists for a secure fit.',899.00,1099.00,'Sale','/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green.webp',36,1,'approved',NULL,NULL,NULL,NULL,'2026-09-09 02:42:43','2026-11-01 07:04:46','cooljob-gardening-gloves-6-pairs-h3nv34','ZAY-YDACCCTM',6,540,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(4,45,'Westward 6-Inch Steel Blade Garden Hoe','garden_hoes',NULL,'A durable garden hoe with a six-inch steel blade and long wood handle for cultivating soil, edging beds, and clearing weeds.',1299.00,NULL,'New','/images/Garden Hoe,6 in Steel Blade Westward 2mvt3.webp',14,1,'approved',NULL,NULL,NULL,NULL,'2026-09-09 02:42:43','2026-11-01 07:04:46','westward-6-inch-steel-blade-garden-hoe-w6y2h7','ZAY-PQWWJIMS',4,1700,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(5,47,'NATTJASMIN Fitted Sheet','bedsheets',NULL,NULL,1699.00,NULL,'New','/images/drive-products/102PL7FkCWbre8qYX_o2qPdadiJWSfvhZ.webp',24,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','nattjasmin-fitted-sheet-mazl8q','ZAY-DRV-1vPKWnYYTHvA-4Ng44idoLdKHXUxkEYIB',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(6,47,'LÖKSTAMFLY Ergonomic Pillow','pillows',NULL,NULL,999.00,NULL,'New','/images/drive-products/1qhHc5i1cC_kKzIlXwxKeWHLm9fvPBr54.webp',30,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','lokstamfly-ergonomic-pillow-qnj7yf','ZAY-DRV-19PaD9WBXV_t-h7ncBhy9q3sddSh5b78e',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(7,47,'Yedwo Double Layer Knitted Blanket','blankets',NULL,NULL,1399.00,NULL,'New','/images/drive-products/1Dr_3tpQJCJTl_1Y-3GQ3j9K73iqgnjyV.webp',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','yedwo-double-layer-knitted-blanket-mrc2wu','ZAY-DRV-1pDHJcRtLXNRdKko7DxO9N9kr_37NVlJQ',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(8,47,'Luxury Spa Collection Luxe Towel','bath_towels',NULL,NULL,699.00,NULL,'New','/images/drive-products/1zKJlyWw_Up7kMxiCWs3VpDZuk0VinLX-.webp',40,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','luxury-spa-collection-luxe-towel-nktrls','ZAY-DRV-1zETtroT1gHEAVtrW_1qUVEqHhVqZtm7q',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(9,47,'Liana Geometric Shower Curtain','shower_curtains',NULL,NULL,899.00,NULL,'New','/images/drive-products/1RGWc_jRI7Sb5C36-69Mm1u7aBSJefxe5.webp',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','liana-geometric-shower-curtain-wg0tao','ZAY-DRV-12P2DISyu6qTa6s-VUjxT16aiJ6NnhYGu',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(10,47,'Kathy Ireland 6-Piece Comforter Set','comforters',NULL,NULL,3299.00,NULL,'New','/images/drive-products/1EMl1KTgFLy6HAUbRZpCxg8OxIboz_XVi.webp',12,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','kathy-ireland-6-piece-comforter-set-5uzo9t','ZAY-DRV-1dWOAESC2IoBj62WzvM3IzqBIvHnNw7zc',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(11,47,'Secret Linen Store Linen Pillowcase','pillowcases',NULL,NULL,849.00,NULL,'New','/images/drive-products/1l4MTRyzVEF8yusrfPcTBFsJsGzVNkFeq.webp',30,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','secret-linen-store-linen-pillowcase-2eodbs','ZAY-DRV-1LldTx5V6fOldRTRXIdiQM9osCl-7S99A',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(12,47,'LUDDROS Mattress Protector','mattress_protectors',NULL,NULL,1099.00,NULL,'New','/images/drive-products/1tZ5sYRmYt0ESYVHmhea_pcfrsDtd6P-F.webp',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','luddros-mattress-protector-xpctho','ZAY-DRV-1_1i5WWKH4VXKbwZ6gbDL9-AbXEiuXFS0',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(13,47,'IKEA TOFTBO Bath Mat','bath_mats',NULL,NULL,649.00,NULL,'New','/images/drive-products/1hL38jLZrFo4Bvh8v58Btjdrh4yt825Ef.webp',31,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','ikea-toftbo-bath-mat-4xegzl','ZAY-DRV-1JTiKFojCZCo0_-uhxd42xZA7LrwTZEMM',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(14,47,'3-Layer Bathroom Storage Rack','bathroom_storage_racks',NULL,NULL,1899.00,NULL,'New','/images/drive-products/1e5tVcQm5ZTAh16F0fdU_9VY-jni3j2_B.webp',18,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','3-layer-bathroom-storage-rack-shilmw','ZAY-DRV-1-Ypt1d-e74BMgU7l32WUKAwvlmcBe87Z',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(15,44,'Mandaue Foam Alara High-Back Office Chair','office_chairs',NULL,NULL,5999.00,NULL,'New','/images/drive-products/1-BE84UPb1BGnpdn6ctyOqfa-cEj5OHQ_.webp',12,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','mandaue-foam-alara-high-back-office-chair-hwsdvj','ZAY-DRV-1-DP1PEksXwKhdH3KDnU5mI2V14QNayBn',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(16,44,'Marble-Top Round Coffee Table','coffee_tables',NULL,NULL,4999.00,NULL,'New','/images/drive-products/1ztymv3feTrpoqBQ5TfzOtHDtUdITyoEy.webp',10,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','marble-top-round-coffee-table-umywgi','ZAY-DRV-1flltZNwunSPhMR_4K_4m2U6EgP0pt99J',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(17,44,'Apolda Natural Oak Bookshelf','bookshelves',NULL,NULL,8499.00,NULL,'New','/images/drive-products/1k3t_r5KwjwpTHApgqYLSc6PMTXvYDY3P.webp',8,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','apolda-natural-oak-bookshelf-x1scij','ZAY-DRV-1KRdicFXUPupKVqvacViWZryHoIzyjIIr',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(18,44,'Lucent Wall Clock','wall_clocks',NULL,NULL,999.00,NULL,'New','/images/drive-products/1R-26mMG3sjZeozM9O6npSds5b8NVzLet.jpg',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','lucent-wall-clock-sbihzl','ZAY-DRV-1PYnNQL107OyHAIwdxXv2x9lf2Fct40Re',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(19,44,'Modern Table Lamp','table_lamps',NULL,NULL,1499.00,NULL,'New','/images/drive-products/1nElnBdqOtRUfGpNuRycTW453eFrp-5KG.webp',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','modern-table-lamp-runwui','ZAY-DRV-1RNu1QK-QeJoPKER27F1Ggp99QqlvMy03',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(20,44,'Nina Two-Drawer Bedside Table','bedside_tables',NULL,NULL,2999.00,NULL,'New','/images/drive-products/1vyzWeGMSPk6S1A5X05RJngRJvw6tkM4D.webp',14,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','nina-two-drawer-bedside-table-tfqi1a','ZAY-DRV-1vnOwoU4mblKqdwE6kg2mrkZgSEt4kn3a',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(21,44,'Farkins TV Stand','tv_stands',NULL,NULL,6999.00,NULL,'New','/images/drive-products/1wP3udCQGhPQDuLgu-K1Y9aZjghEJ8eaq.webp',8,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','farkins-tv-stand-28jrft','ZAY-DRV-1QHl97kNU3A5jbsY1zn9_6RI3VEjgdY1J',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(22,44,'Harris Three-Door Shoe Cabinet','shoe_cabinets',NULL,NULL,5499.00,NULL,'New','/images/drive-products/11qs9skjq8KDCgHtCLrE-iyaOLHsbN5s6.webp',10,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','harris-three-door-shoe-cabinet-0xyrsg','ZAY-DRV-1NAMfHUyHcX7RBQxG3mQIvgTfT35oXIgP',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(23,44,'Possini Euro Serra Floor Lamp','floor_lamps',NULL,NULL,6999.00,NULL,'New','/images/drive-products/1PT_7ZyTib92G0myQodtFufcuDhXtCOIC.jpg',6,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','possini-euro-serra-floor-lamp-gvef5e','ZAY-DRV-1sPmDjX_bchh-Bq6WIo59oAgGdRkKoHHj',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(24,44,'Black Round LED Decorative Mirror','decorative_mirrors',NULL,NULL,3499.00,NULL,'New','/images/drive-products/1jT9ckQKPq6VxP3jD_M_FOc6lYBIUuhE5.webp',12,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','round-led-decorative-mirror-6sivhd','ZAY-DRV-1qfbPgkLp8BF1yUF3Dmu8ioSVBiD_rDwx',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(25,45,'Total Pruning Shear','pruning_shears',NULL,NULL,699.00,NULL,'New','/images/drive-products/1HnwpYjbwcekc1eXbzOhSZYbGIp3Y7kKb.webp',30,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','total-pruning-shear-s8menu','ZAY-DRV-1k4aBTOkfdRMMUGKWOvNS8IXv1qac1bAA',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(26,45,'CT4281 Garden Rake','garden_rakes',NULL,NULL,899.00,NULL,'New','/images/drive-products/1IEcTcuKEq1Kq38naToG_T8PFPCxmLbJH.webp',24,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','ct4281-garden-rake-fxn9sk','ZAY-DRV-1NkSv7h8-pLjQCXPc64_suBAZ6qXLKHuo',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(27,45,'Edward Tools Garden Trowel','hand_trowels',NULL,NULL,449.00,NULL,'New','/images/drive-products/10mf45q4io4oo83iW4bVHpLrV8Ego7Wek.webp',36,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','edward-tools-garden-trowel-vpmkzo','ZAY-DRV-1l80JYdomxc_8SiwAjSYr4pOTznv9eMEh',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(28,45,'Keeper Electric Plant Sprayer','plant_sprayers',NULL,NULL,3999.00,NULL,'New','/images/drive-products/1ZCHnx96pyRGPI5oqqt0GpqzD3B0UM_R7.webp',8,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','keeper-electric-plant-sprayer-a7nqu9','ZAY-DRV-1CKNt47KFtHuEa7ATBUQt4kaa3E2PWM93',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(29,45,'Amtech U0725 Garden Shears','garden_scissors',NULL,NULL,799.00,NULL,'New','/images/drive-products/18BqtAoLkuJ7riTCuKNGI3cpqnmpiHRCu.webp',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','amtech-u0725-garden-shears-uqatml','ZAY-DRV-10PklwaOprIe10rb7YdRsJDvDuaVzm4pe',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(30,45,'Bergs Potter Simona Pot','pots',NULL,NULL,1199.00,NULL,'New','/images/drive-products/1Cq3DU6AXvfocF2v1CV5e6BNfpmLKTmTC.webp',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','bergs-potter-simona-pot-seeiqp','ZAY-DRV-10rCMANdFNlgE-BOCN53MZtSfFhDHwNtA',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(31,44,'Set of 4 Home Storage Bins','storage_bins',NULL,NULL,499.00,NULL,'New','/images/drive-products/1kszXE7LCgQEepLMLZiM8AO1E7Tx30J_r.jpg',40,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','home-storage-bin-3trinc','ZAY-DRV-1o9z6iZpcMXKffdP1aWuIoZmNDYaXDaHF',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(32,44,'Cleaning Basket','cleaning_baskets',NULL,NULL,399.00,NULL,'New','/images/drive-products/1BsNMgAmY9SFYM2Qf5BaEA7uHTiHh7-iZ.jpg',40,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','cleaning-basket-x3xurb','ZAY-DRV-1DN56p4ZW9eOjI5u7Ft4kM2XRHCovXvzA',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(33,44,'Multi-Tier Shoe Rack','shoe_racks',NULL,NULL,1599.00,NULL,'New','/images/drive-products/12SwCEYODQWUeUw7yF1JcloF9qEaxPjtv.webp',22,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','multi-tier-shoe-rack-awz9fe','ZAY-DRV-1DpLAtjo9Awnqk1ZtOkPkjUCnqMj1UMVY',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(34,44,'Indoor Plant Pot','indoor_plant_pots',NULL,NULL,499.00,NULL,'New','/images/drive-products/1mryRPsGVJpM8NyNRGZYyxMzJ7KRQLxhy.jpg',30,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','indoor-plant-pot-effvb1','ZAY-DRV-1NhGxgklWGjRdws98b8YV_J0DYzG1CfZG',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(35,44,'Laundry Basket','laundry_baskets',NULL,NULL,799.00,NULL,'New','/images/drive-products/1HbDsd6WBIQmCnfhcXBK7d28lHPMZ6BJ_.jpg',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','laundry-basket-3c6ibo','ZAY-DRV-13BYuqmTZqfwogNrq27FJyA4PKdyrw-IY',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(36,44,'Hi-Care Wheeled Trash Bin','trash_bins',NULL,NULL,899.00,NULL,'New','/images/drive-products/1vZT--hgQgEeFiKQ-d_RSlvasQic3CYU8.jpg',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','household-trash-bin-njjrfg','ZAY-DRV-1wOEOgxxbPWcl9UKmQ8Vzb_pCQTMHMZod',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(37,44,'Clothes Hanger Set','clothes_hangers',NULL,NULL,349.00,NULL,'New','/images/drive-products/1xJtmRTK_P1d5r-7C74AH28TdI7nivpeM.jpg',60,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','clothes-hanger-set-zvihz1','ZAY-DRV-1DgUWrxSMmWf8zAcTxhaQzrwASUwjgFuh',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(38,44,'Home Doormat','doormats',NULL,NULL,449.00,NULL,'New','/images/drive-products/1xUpYWLCVs2R2EYcHC_-JdsLx_1oDHyBx.webp',40,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:46','home-doormat-sybynt','ZAY-DRV-1BngHXG4t_IADoMYDKQqeTm6ZXog5iwAd',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(39,46,'ATuMan RS1 Plus 42-in-1 Screwdriver Set','screwdriver_sets',NULL,NULL,1599.00,NULL,'New','/images/drive-products/1DagST51dWEd2zrWCjO0t2c0aXTrlUOaH.webp',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','atuman-rs1-plus-42-in-1-screwdriver-set-wald1v','ZAY-DRV-1sj_AXW--GeyS4KQlXuvM-ndLJG7WNgH0',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(40,46,'Estwing Straight Claw Hammer','hammers',NULL,NULL,1299.00,NULL,'New','/images/drive-products/1VgYF-gCs0_2b9v_JJpSHZJ0Qr4tu4tm6.webp',22,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','estwing-straight-claw-hammer-wehaft','ZAY-DRV-14n_R4CKM7SWEudjnAByxKRpN_l79sf8H',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(41,46,'RS PRO Steel Pliers Set PC0300','pliers',NULL,NULL,899.00,NULL,'New','/images/drive-products/1j26SuOoHp2D4Dx-AZRo4fNFL2eukZPNx.webp',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','rs-pro-steel-pliers-set-pc0300-xe3boh','ZAY-DRV-1Yp3z40WmG7RjqKkw9oqjxzpSKtlMQ1Zo',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(42,46,'RS PRO S85 5m Tape Measure','measuring_tapes',NULL,NULL,499.00,NULL,'New','/images/drive-products/1q9qJbz1GNEiXKoSMarRLjZcbTBeecS2f.webp',35,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','rs-pro-s85-5m-tape-measure-lblj5t','ZAY-DRV-1rB44ok8GXYBdZtd8eurKJYqPA21Jj896',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(43,46,'Fein JMU 404 M Magnetic Base Drill','drill_machines',NULL,NULL,24999.00,NULL,'New','/images/drive-products/1LAuG_1TbvYuYRBoiOQTb0KH19QgQKGyW.webp',4,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','fein-jmu-404-m-magnetic-base-drill-mq86uv','ZAY-DRV-17o5sSviAwdV2M_gMhEfbe6s9ymXE4Xfr',4,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(44,46,'CT6083 26-Piece Ratcheting Wrench Set','wrench_sets',NULL,NULL,2899.00,NULL,'New','/images/drive-products/1IvaieE0fO4S1GXohm9kOLJAhmI1v0-QT.webp',12,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','ct6083-26-piece-ratcheting-wrench-set-t9b8yh','ZAY-DRV-1CNn9huQAr-J1Do8AwMLTibMYts_lq_ve',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(45,46,'Stanley Compact Folding Utility Knife','utility_knives',NULL,NULL,499.00,NULL,'New','/images/drive-products/1nPc7KDLUX23loDGFJTMYmJ9dY826fKy4.webp',30,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','stanley-compact-folding-utility-knife-h1jncv','ZAY-DRV-10BWjHYd_pUVgrtOrXr-CZtIfOhJL9grv',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(46,46,'CT0110 12-Inch Spirit Level','level_tools',NULL,NULL,649.00,NULL,'New','/images/drive-products/11LJdjvxbRTr1Bv7D651lMvsHiqN6a5No.webp',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','ct0110-12-inch-spirit-level-a4cgy2','ZAY-DRV-1F8kcHz8VrKg1nI_1pcBXaYBSvaugjRrU',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(47,46,'Ingco Plastic Tool Box','tool_boxes',NULL,NULL,999.00,NULL,'New','/images/drive-products/1YzMfOksNvkS4gW-UxN8VCK5vZ96nt6SM.webp',19,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','ingco-plastic-tool-box-lblqmb','ZAY-DRV-1scolch6Rj0KaQm65qKgqlJMQa3218tCB',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(48,46,'Neilsen CT3997 Power Sander','sanding_tools',NULL,NULL,3499.00,NULL,'New','/images/drive-products/1sI2_uYhPIVbZArTi67sH9iafrLtgMtnM.webp',10,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:47','neilsen-ct3997-power-sander-mehhxm','ZAY-DRV-1Uq9YkllaLHvfW1n0IBI9UFMkAmG6vUh_',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(49,43,'Elite Gourmet Black Rice Cooker','rice_cookers',NULL,NULL,1999.00,NULL,'New','/images/drive-products/1GpqfZayr1KBwe8kqXf3w-2eCRKwnyU_7.jpg',18,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','rice-cooker-needqd','ZAY-DRV-1OHwgZsn3YYmHFbfK15XOObaEn5CKT64T',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(50,43,'Professional Quiet Blender','blenders',NULL,NULL,6499.00,NULL,'New','/images/drive-products/1AIgG3eSWbUU_zqTOgCbKpA-dCZ4MipXe.png',8,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','professional-quiet-blender-duiy9u','ZAY-DRV-19N-MFutsF_Xo4JE7XBvTx3jVj6z2SGjd-A',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(51,43,'Commercial Digital Blender','blenders',NULL,NULL,7299.00,NULL,'New','/images/drive-products/1RMdXr3xJOmvTHj3Ayy95jxUO1cbkMWWf.webp',8,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','commercial-digital-blender-qkub8f','ZAY-DRV-19N-MFutsF_Xo4JE7XBvTx3jVj6z2SGjd-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(52,43,'Green Electric Kettle','electric_kettles',NULL,NULL,1299.00,NULL,'New','/images/drive-products/1ut99JHBNfqHmlJPoLeCKH83988_TXJrG.avif',20,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','green-electric-kettle-6ftahc','ZAY-DRV-1ysylRFCJq1zbJMkF8wnUxJFNlfoS5QlS-A',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(53,43,'Evoloop 1.7L Electric Kettle','electric_kettles',NULL,NULL,1899.00,NULL,'New','/images/drive-products/1fFa1-B5snjy1XhOoBFd8LSZ7uIlxPq5A.avif',15,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','evoloop-17l-electric-kettle-nrtdf3','ZAY-DRV-1ysylRFCJq1zbJMkF8wnUxJFNlfoS5QlS-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(54,43,'Touchscreen Air Fryer','air_fryers',NULL,NULL,3299.00,NULL,'New','/images/drive-products/1cJc3C7JZobwGXXFivIra4i-4uZ6OvR5P.jpg',17,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','digital-air-fryer-abjq3v','ZAY-DRV-1qtTqgW1FKhUGL2fPieu6rprQN1OVOXHR',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(55,43,'KITfanz Silver Electric Moka Pot','coffee_maker',NULL,NULL,2499.00,NULL,'New','/images/drive-products/1pNgnd-VwDqS4yrlSmwMbWg4twmMPQE6W.jpg',18,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','coffee-maker-1ifaoy','ZAY-DRV-1ON6jXKdPpdQEdtPxbKgCmsBxhT5Lo4hF',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(56,43,'Electric Toaster','toasters',NULL,NULL,1299.00,NULL,'New','/images/drive-products/1vnyNKQehn2J3sfVxWFj2MmXcTyOp4yo4.jpg',21,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','electric-toaster-ek6rx0','ZAY-DRV-1tCceAM2Rum_pOH5O0OSugCR8m26Omtaf',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(57,43,'Countertop Electric Oven','electric_ovens',NULL,NULL,3999.00,NULL,'New','/images/drive-products/1a-TyBkDsgafjbSMQ-eXdqzso-Nlux5CB.jpg',12,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','countertop-electric-oven-stkygo','ZAY-DRV-10cIWKDWJQDzKdkOGXytZNHdSrdTPpmId',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(58,43,'Food Processor','food_processors',NULL,NULL,2999.00,NULL,'New','/images/drive-products/1p9JGgdBkYsI39ej-cZl401EXZmS1tsBK.jpg',14,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','food-processor-k1pfld','ZAY-DRV-1JG8MLnvh1g4UQbfWTiV240K1CG6PDhHR',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(59,43,'Hamilton Beach Professional Hand Mixer','hand_mixers',NULL,NULL,2299.00,NULL,'New','/images/drive-products/1dnfLbkrOu9pDeRZB18nbQxHXHBcQoBrJ.webp',16,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-11-01 07:04:45','hamilton-beach-professional-hand-mixer-ap5jkg','ZAY-DRV-1UJhLFLoc2Ntdf1OHlvSQRhnXk16jM0m4',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(60,11,'Nike Dunk Low Retro Panda','shoes','unisex','At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.',6499.00,NULL,'New','/images/drive-products/1_dtQj3XN3uTzFxIIDHNSlcN92HwMyhte.webp',10,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-10-03 21:03:26','nike-dunk-low-retro-panda-orbpy9','ZAY-DRV-SHOE-1',5,NULL,'percent',10.00,'2026-10-04 05:03:00','2026-10-04 05:05:00',NULL,0,NULL,0,NULL,0),(61,11,'Nike Dunk Low Pink and White','shoes','unisex',NULL,5999.00,NULL,'New','/images/drive-products/1eXy0ZOMWuofoegKilqjqJCwWrINvDEUS.webp',12,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-09-24 23:15:10','nike-dunk-low-pink-and-white-dmym36','ZAY-DRV-SHOE-2',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(62,11,'Nike WMNS Dunk Low Olive','shoes','women','Masarap isuot sa paa :)',5999.00,NULL,'New','/images/drive-products/13BgyEQc4GZS1jrogN2BFm_RFN5-7BPeR.webp',3,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:20:23','2026-10-16 23:40:54','nike-wmns-dunk-low-olive-r3ylxm','ZAY-DRV-SHOE-3',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(63,44,'Gold Round LED Decorative Mirror','decorative_mirrors',NULL,NULL,3499.00,NULL,'New','/images/drive-products/1QTwSce9lur3kFCx6HcZlzPqndd0QFQxk.webp',11,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:46','gold-round-led-decorative-mirror-q4gryz','ZAY-DRV-1qfbPgkLp8BF1yUF3Dmu8ioSVBiD_rDwx-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(64,44,'Plain Black Round Decorative Mirror','decorative_mirrors',NULL,NULL,1999.00,NULL,'New','/images/drive-products/1l1Ng0uor4kWo3RBr2ToAelJkVTMKrBT9.webp',16,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:46','plain-black-round-decorative-mirror-0hswuh','ZAY-DRV-1qfbPgkLp8BF1yUF3Dmu8ioSVBiD_rDwx-C',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(65,44,'Kenpoly Wheeled Trash Bin','trash_bins',NULL,NULL,899.00,NULL,'New','/images/drive-products/1n47HB1gz9L9P4bVZ6EpXo2Lz1nqZOWgk.webp',22,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:46','kenpoly-wheeled-trash-bin-mjhxdh','ZAY-DRV-1wOEOgxxbPWcl9UKmQ8Vzb_pCQTMHMZod-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(66,44,'Bentley Wheeled Trash Bin','trash_bins',NULL,NULL,899.00,NULL,'New','/images/drive-products/1XF7166cpY5JJ6eepblLF-V5m4lbcJ--y.webp',25,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:46','bentley-wheeled-trash-bin-zjopiq','ZAY-DRV-1wOEOgxxbPWcl9UKmQ8Vzb_pCQTMHMZod-C',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(67,43,'Elite Gourmet White Rice Cooker','rice_cookers',NULL,NULL,1999.00,NULL,'New','/images/drive-products/12Umv1XB0sIds6Lxi6yaK_l30kc4_hLTb.jpg',18,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:45','elite-gourmet-white-rice-cooker-pxk1hh','ZAY-DRV-1OHwgZsn3YYmHFbfK15XOObaEn5CKT64T-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(68,43,'Compact Black Air Fryer','air_fryers',NULL,NULL,2499.00,NULL,'New','/images/drive-products/1wA3lxK_e5ezNAOhViVKFo65GFn-YZfHD.jpg',18,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:45','compact-black-air-fryer-zc5jcd','ZAY-DRV-1qtTqgW1FKhUGL2fPieu6rprQN1OVOXHR-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0),(69,43,'KITfanz Black Electric Moka Pot','coffee_maker',NULL,NULL,2499.00,NULL,'New','/images/drive-products/1nTfydyYGxiuww0CL7YesZIDQ1K88MXQg.jpg',17,1,'approved',NULL,NULL,NULL,NULL,'2026-09-17 22:27:44','2026-11-01 07:04:45','kitfanz-black-electric-moka-pot-mivgwg','ZAY-DRV-1ON6jXKdPpdQEdtPxbKgCmsBxhT5Lo4hF-B',5,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,0,NULL,0);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `order_item_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_order_item_id_unique` (`order_item_id`),
  KEY `reviews_user_id_foreign` (`user_id`),
  KEY `reviews_product_id_foreign` (`product_id`),
  CONSTRAINT `reviews_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rider_applications`
--

DROP TABLE IF EXISTS `rider_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rider_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `license_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_expires_on` date NOT NULL,
  `license_image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate_number` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `or_cr_image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `terms_accepted_at` timestamp NOT NULL,
  `terms_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rider_applications_user_id_unique` (`user_id`),
  UNIQUE KEY `rider_applications_license_number_unique` (`license_number`),
  UNIQUE KEY `rider_applications_plate_number_unique` (`plate_number`),
  KEY `rider_applications_status_index` (`status`),
  KEY `rider_applications_reviewed_by_foreign` (`reviewed_by`),
  KEY `rider_applications_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `rider_applications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rider_applications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rider_applications`
--

LOCK TABLES `rider_applications` WRITE;
/*!40000 ALTER TABLE `rider_applications` DISABLE KEYS */;
INSERT INTO `rider_applications` VALUES (9,30,'S33-45-666666','2027-10-01','rider-documents/30/5AikYvJDmZ54aOKNcTlEJmORZOSwt9jyXMl3Se4Y.jpg','tricycle','U7U778','rider-documents/30/IFF4Wm25MuAIo6QLJpbaL3chP8Fv476LTeGIEC6a.jpg','2026-10-10 10:13:03','2026-10-11','approved',NULL,'2026-10-10 10:14:33',NULL,'2026-10-10 10:13:03','2026-10-10 10:14:33');
/*!40000 ALTER TABLE `rider_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rider_attendances`
--

DROP TABLE IF EXISTS `rider_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rider_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rider_id` bigint unsigned NOT NULL,
  `logistics_provider_id` bigint unsigned DEFAULT NULL,
  `work_date` date NOT NULL,
  `time_in_at` datetime NOT NULL,
  `time_out_at` datetime DEFAULT NULL,
  `scheduled_start` time DEFAULT NULL,
  `scheduled_end` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rider_attendances_rider_id_work_date_unique` (`rider_id`,`work_date`),
  KEY `rider_attendances_logistics_provider_id_work_date_index` (`logistics_provider_id`,`work_date`),
  CONSTRAINT `rider_attendances_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rider_attendances_rider_id_foreign` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rider_attendances`
--

LOCK TABLES `rider_attendances` WRITE;
/*!40000 ALTER TABLE `rider_attendances` DISABLE KEYS */;
/*!40000 ALTER TABLE `rider_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rider_commissions`
--

DROP TABLE IF EXISTS `rider_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rider_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `shipment_id` bigint unsigned NOT NULL,
  `logistics_provider_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rider_commissions_shipment_id_unique` (`shipment_id`),
  KEY `rider_commissions_logistics_provider_id_foreign` (`logistics_provider_id`),
  KEY `rider_commissions_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `rider_commissions_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rider_commissions_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rider_commissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rider_commissions`
--

LOCK TABLES `rider_commissions` WRITE;
/*!40000 ALTER TABLE `rider_commissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `rider_commissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rider_delivery_logs`
--

DROP TABLE IF EXISTS `rider_delivery_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rider_delivery_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rider_delivery_logs_shipment_id_action_index` (`shipment_id`,`action`),
  KEY `rider_delivery_logs_user_id_action_created_at_index` (`user_id`,`action`,`created_at`),
  CONSTRAINT `rider_delivery_logs_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rider_delivery_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rider_delivery_logs`
--

LOCK TABLES `rider_delivery_logs` WRITE;
/*!40000 ALTER TABLE `rider_delivery_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `rider_delivery_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rider_support_messages_old`
--

DROP TABLE IF EXISTS `rider_support_messages_old`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rider_support_messages_old` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_conversation_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned DEFAULT NULL,
  `from_staff` tinyint(1) NOT NULL DEFAULT '0',
  `client_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_messages_support_conversation_id_client_id_unique` (`support_conversation_id`,`client_id`),
  KEY `support_messages_sender_id_foreign` (`sender_id`),
  KEY `support_messages_support_conversation_id_created_at_index` (`support_conversation_id`,`created_at`),
  CONSTRAINT `support_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_messages_support_conversation_id_foreign` FOREIGN KEY (`support_conversation_id`) REFERENCES `support_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rider_support_messages_old`
--

LOCK TABLES `rider_support_messages_old` WRITE;
/*!40000 ALTER TABLE `rider_support_messages_old` DISABLE KEYS */;
/*!40000 ALTER TABLE `rider_support_messages_old` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rider_withdrawals`
--

DROP TABLE IF EXISTS `rider_withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rider_withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `logistics_provider_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `gcash_number` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gcash_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_by` bigint unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rider_withdrawals_processed_by_foreign` (`processed_by`),
  KEY `rider_withdrawals_user_id_status_index` (`user_id`,`status`),
  KEY `rider_withdrawals_logistics_provider_id_status_index` (`logistics_provider_id`,`status`),
  CONSTRAINT `rider_withdrawals_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rider_withdrawals_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rider_withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rider_withdrawals`
--

LOCK TABLES `rider_withdrawals` WRITE;
/*!40000 ALTER TABLE `rider_withdrawals` DISABLE KEYS */;
/*!40000 ALTER TABLE `rider_withdrawals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `riders`
--

DROP TABLE IF EXISTS `riders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `riders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `logistics_provider_id` bigint unsigned NOT NULL,
  `vehicle_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `license_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_expires_on` date DEFAULT NULL,
  `license_document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `or_cr_document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `work_days` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `riders_user_id_unique` (`user_id`),
  KEY `riders_logistics_provider_id_is_active_index` (`logistics_provider_id`,`is_active`),
  KEY `riders_status_index` (`status`),
  CONSTRAINT `riders_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `riders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `riders`
--

LOCK TABLES `riders` WRITE;
/*!40000 ALTER TABLE `riders` DISABLE KEYS */;
INSERT INTO `riders` VALUES (1,40,1,'Motorcycle','TEST001',1,'2026-10-18 23:19:46','2026-10-18 23:23:02','approved','N01-23-456789','2029-10-19','riders/40/license-sample.png','riders/40/or-cr-sample.png',NULL,'2026-10-18 23:23:02',NULL,NULL,NULL);
/*!40000 ALTER TABLE `riders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seller_applications`
--

DROP TABLE IF EXISTS `seller_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seller_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `shop_name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seller_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tin` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_code` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_code` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_code` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay_code` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `government_id_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dti_certificate_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_certificate_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bir_2303_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_applications_user_id_unique` (`user_id`),
  UNIQUE KEY `seller_applications_shop_name_unique` (`shop_name`),
  UNIQUE KEY `seller_applications_tin_unique` (`tin`),
  KEY `seller_applications_reviewed_by_foreign` (`reviewed_by`),
  KEY `seller_applications_status_index` (`status`),
  CONSTRAINT `seller_applications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `seller_applications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seller_applications`
--

LOCK TABLES `seller_applications` WRITE;
/*!40000 ALTER TABLE `seller_applications` DISABLE KEYS */;
INSERT INTO `seller_applications` VALUES (1,43,'Daily Kitchen Co.','kitchen_appliances','sole_proprietorship','999-000-000-001','130000000','National Capital Region (NCR)',NULL,NULL,'137404000','Quezon City','137404001','Alicia','Demo Unit 1, Sample Street','1105','seller-applications/43/demo-government_id.pdf','seller-applications/43/demo-dti_certificate.pdf',NULL,'seller-applications/43/demo-bir_2303.pdf','approved',NULL,'2026-11-01 07:04:59','2026-11-01 07:04:59',NULL,'2026-11-01 07:04:59','2026-11-01 07:04:59'),(2,44,'Homestead Living','furniture_decor','sole_proprietorship','999-000-000-002','130000000','National Capital Region (NCR)',NULL,NULL,'137602000','City of Makati','137602001','Bangkal','Demo Unit 2, Sample Street','1233','seller-applications/44/demo-government_id.pdf','seller-applications/44/demo-dti_certificate.pdf',NULL,'seller-applications/44/demo-bir_2303.pdf','approved',NULL,'2026-11-01 07:05:01','2026-11-01 07:05:01',NULL,'2026-11-01 07:05:01','2026-11-01 07:05:01'),(3,45,'Greenhand Supply','gardening_tools','sole_proprietorship','999-000-000-003','130000000','National Capital Region (NCR)',NULL,NULL,'137403000','City of Pasig','137403001','Bagong Ilog','Demo Unit 3, Sample Street','1600','seller-applications/45/demo-government_id.pdf','seller-applications/45/demo-dti_certificate.pdf',NULL,'seller-applications/45/demo-bir_2303.pdf','approved',NULL,'2026-11-01 07:05:04','2026-11-01 07:05:04',NULL,'2026-11-01 07:05:04','2026-11-01 07:05:04'),(4,46,'Steadfast Works','home_improvement_tools','sole_proprietorship','999-000-000-004','130000000','National Capital Region (NCR)',NULL,NULL,'137402000','City of Marikina','137402001','Barangka','Demo Unit 4, Sample Street','1803','seller-applications/46/demo-government_id.pdf','seller-applications/46/demo-dti_certificate.pdf',NULL,'seller-applications/46/demo-bir_2303.pdf','approved',NULL,'2026-11-01 07:05:06','2026-11-01 07:05:06',NULL,'2026-11-01 07:05:06','2026-11-01 07:05:06'),(5,47,'Softnest Linens','bedding_bath','sole_proprietorship','999-000-000-005','130000000','National Capital Region (NCR)',NULL,NULL,'137401000','City of Mandaluyong','137401001','Addition Hills','Demo Unit 5, Sample Street','1550','seller-applications/47/demo-government_id.pdf','seller-applications/47/demo-dti_certificate.pdf',NULL,'seller-applications/47/demo-bir_2303.pdf','approved',NULL,'2026-11-01 07:05:07','2026-11-01 07:05:07',NULL,'2026-11-01 07:05:07','2026-11-01 07:05:07');
/*!40000 ALTER TABLE `seller_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seller_profiles`
--

DROP TABLE IF EXISTS `seller_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seller_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `store_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_registration` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `seller_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seller_profiles`
--

LOCK TABLES `seller_profiles` WRITE;
/*!40000 ALTER TABLE `seller_profiles` DISABLE KEYS */;
INSERT INTO `seller_profiles` VALUES (1,11,'Happy Heels',NULL,NULL,'2026-09-19 04:16:38','2026-09-20 22:21:24'),(2,43,'Daily Kitchen Co.',NULL,NULL,'2026-11-01 07:04:45','2026-11-01 07:04:45'),(3,44,'Homestead Living',NULL,NULL,'2026-11-01 07:04:46','2026-11-01 07:04:46'),(4,45,'Greenhand Supply',NULL,NULL,'2026-11-01 07:04:46','2026-11-01 07:04:46'),(5,46,'Steadfast Works',NULL,NULL,'2026-11-01 07:04:47','2026-11-01 07:04:47'),(6,47,'Softnest Linens',NULL,NULL,'2026-11-01 07:04:47','2026-11-01 07:04:47'),(7,49,'Shop Test Store',NULL,NULL,'2026-11-01 22:18:40','2026-11-01 22:18:40');
/*!40000 ALTER TABLE `seller_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('n5Mu2MmRj0s99wR0GQLQrIXoSxeJkapGUdmwO2wW',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.135.0 Chrome/148.0.7778.280 Electron/42.8.1 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTFFYazF2Q2hDRmUxNnVxS25VS01ZUFA0MU9TcXRWQ096Z0hnUmh4ViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1788194905),('RQtEkYkFPJIXLOuYhlFDP3ykAGkBszyuH2KFEHQ2',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoic2dhS0k5UVlOMkk0UVdwc0JYN0s4WUtlTUxMSDZyRlJYeElHUUljayI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1788194919);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipments`
--

DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `logistics_provider_id` bigint unsigned DEFAULT NULL,
  `courier_id` bigint unsigned DEFAULT NULL,
  `tracking_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pickup_confirmed_at` timestamp NULL DEFAULT NULL,
  `pickup_scheduled_for` date DEFAULT NULL,
  `sorted_at` timestamp NULL DEFAULT NULL,
  `sort_area` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_proof_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `handover_confirmed_at` timestamp NULL DEFAULT NULL,
  `logistics_payout_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipments_order_id_unique` (`order_id`),
  UNIQUE KEY `shipments_tracking_number_unique` (`tracking_number`),
  KEY `shipments_courier_id_foreign` (`courier_id`),
  KEY `shipments_status_index` (`status`),
  KEY `shipments_sort_area_index` (`sort_area`),
  KEY `shipments_logistics_provider_id_foreign` (`logistics_provider_id`),
  KEY `shipments_logistics_payout_id_foreign` (`logistics_payout_id`),
  CONSTRAINT `shipments_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_logistics_payout_id_foreign` FOREIGN KEY (`logistics_payout_id`) REFERENCES `logistics_payouts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_logistics_provider_id_foreign` FOREIGN KEY (`logistics_provider_id`) REFERENCES `logistics_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipments`
--

LOCK TABLES `shipments` WRITE;
/*!40000 ALTER TABLE `shipments` DISABLE KEYS */;
INSERT INTO `shipments` VALUES (1,8,1,NULL,'TRK-OZIMZ8VNRMDW','delivered','2026-09-24 22:25:01','2026-09-24 23:13:53','2026-09-24 22:24:57','2026-09-24 23:13:53',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,10,1,NULL,'TRK-URDEYSHIFDCK','ready',NULL,NULL,'2026-10-10 11:30:52','2026-10-10 11:30:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(4,11,1,NULL,'TRK-REN78MYVXLKO','ready',NULL,NULL,'2026-10-10 11:30:52','2026-10-10 11:30:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(5,12,1,30,'TRK-VYJIXLX8UTUP','delivered','2026-10-10 11:43:21','2026-10-10 11:43:31','2026-10-10 11:30:52','2026-10-10 11:43:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(6,19,1,40,'TRK-JBDW0QXFVRQO','assigned','2026-10-19 05:24:57',NULL,'2026-10-19 05:24:57','2026-10-19 05:24:57','2026-10-19 05:24:57','2026-10-19','2026-10-19 05:24:57','Cabuyao, Laguna',NULL,NULL,NULL);
/*!40000 ALTER TABLE `shipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shop_follows`
--

DROP TABLE IF EXISTS `shop_follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_follows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `shop_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_follows_user_id_shop_id_unique` (`user_id`,`shop_id`),
  KEY `shop_follows_shop_id_index` (`shop_id`),
  CONSTRAINT `shop_follows_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_follows`
--

LOCK TABLES `shop_follows` WRITE;
/*!40000 ALTER TABLE `shop_follows` DISABLE KEYS */;
/*!40000 ALTER TABLE `shop_follows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shop_sections`
--

DROP TABLE IF EXISTS `shop_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` bigint unsigned NOT NULL,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shop_sections_shop_id_position_index` (`shop_id`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_sections`
--

LOCK TABLES `shop_sections` WRITE;
/*!40000 ALTER TABLE `shop_sections` DISABLE KEYS */;
/*!40000 ALTER TABLE `shop_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_conversations`
--

DROP TABLE IF EXISTS `support_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_conversations_user_id_unique` (`user_id`),
  KEY `support_conversations_last_message_at_index` (`last_message_at`),
  CONSTRAINT `support_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_conversations`
--

LOCK TABLES `support_conversations` WRITE;
/*!40000 ALTER TABLE `support_conversations` DISABLE KEYS */;
INSERT INTO `support_conversations` VALUES (6,30,NULL,'2026-10-10 11:59:50','2026-10-10 11:59:50');
/*!40000 ALTER TABLE `support_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_messages`
--

DROP TABLE IF EXISTS `support_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_thread_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `client_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_messages_support_thread_id_foreign` (`support_thread_id`),
  CONSTRAINT `support_messages_support_thread_id_foreign` FOREIGN KEY (`support_thread_id`) REFERENCES `support_threads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_messages`
--

LOCK TABLES `support_messages` WRITE;
/*!40000 ALTER TABLE `support_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_threads`
--

DROP TABLE IF EXISTS `support_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_threads_user_id_unique` (`user_id`),
  KEY `support_threads_last_message_at_index` (`last_message_at`),
  CONSTRAINT `support_threads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_threads`
--

LOCK TABLES `support_threads` WRITE;
/*!40000 ALTER TABLE `support_threads` DISABLE KEYS */;
INSERT INTO `support_threads` VALUES (1,30,NULL,'2026-10-15 22:16:07','2026-10-15 22:16:07'),(2,40,NULL,'2026-10-18 23:24:09','2026-10-18 23:24:09');
/*!40000 ALTER TABLE `support_threads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'buyer',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `identity_verified_at` timestamp NULL DEFAULT NULL,
  `shop_cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `users_approved_by_foreign` (`approved_by`),
  KEY `users_status_index` (`status`),
  CONSTRAINT `users_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Jan Reyben Del Mundo','reybendelmundo2005@gmail.com','100001932505618334922','https://lh3.googleusercontent.com/a/ACg8ocLqMeWtsRY85r-QPVe8hW6nuusyr01ufXW6Au2Kj21L0MeXLdTc=s96-c','local','2026-09-06 20:04:16','$2y$12$GKJqnRefTNzzZx5DLZFoUOULKVqyelQlx1by42oJrhLiUdb0DElH6','buyer','09123456789','2005-09-20',NULL,'Laguna, Philippines','dNmfd5cRjqARAHlmnRn7eS5IpdnBDCWKap0kTTZK2gr4O9zrCZw8a60SnG2c','2026-08-30 20:09:56','2026-09-14 02:47:29','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(2,'A S','chatbotnimaro@gmail.com',NULL,NULL,'local',NULL,'$2y$12$uhgOMTlPfbJZT9lvzgGCq.ic8KjC01ocvF7I.m5BF/Io3BziOQcVW','buyer','09123456789',NULL,NULL,NULL,NULL,'2026-09-06 14:33:09','2026-09-06 14:33:09','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(3,'A A','mellowbowl2000@gmail.com',NULL,NULL,'local',NULL,'$2y$12$IZ0lV6hJXwoKjRV/Qnl57.rDRVMI44nul7EyCE/85LR8k7/mVGYSu','buyer',NULL,NULL,NULL,NULL,NULL,'2026-09-06 16:04:41','2026-09-06 16:04:41','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(5,'Jillian Gutierrez','gutierrezjillianm@gmail.com','111833478091351617956','https://lh3.googleusercontent.com/a/ACg8ocIvEYgZHbVVxirGNPKwycOnRS9Bw0VhIpwBRvAzBjD352KQoYq7=s96-c','local','2026-09-22 18:13:03','$2y$12$qpAu4Q3G4lp7vhT/3LIkTeZeI4ma1WTq20HAhcKH5VHBbXZu1W.ZO','buyer',NULL,NULL,NULL,NULL,NULL,'2026-09-06 20:08:00','2026-09-22 18:13:03','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(6,'Juan Dela Cruz','lastaccnatalagato@gmail.com',NULL,NULL,'local','2026-09-12 19:30:33','$2y$12$ScgTEpjifljArVOK7B.wg.MIb2kKASQp3H5qUceqljv1u5WcqWPjy','buyer',NULL,NULL,NULL,NULL,NULL,'2026-09-12 19:11:53','2026-09-12 19:30:33','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(7,'Mac Mac','trillesmark11@gmail.com',NULL,NULL,'local','2026-09-12 19:33:10','$2y$12$4jFJwhvsezKXryFFRwLaS.UUJz1OoKA5plyLO48pFX.UuWfG3VYqW','buyer',NULL,NULL,NULL,NULL,'4JwmrdX82vI51Ms2jacWbPYU7woWQdVMZfPsPEfXa8tcflW8kloCVH5F1PXq','2026-09-12 19:32:45','2026-09-12 19:33:10','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(8,'Jillian Gutierrez','zaybuyer1@gmail.com','114727959438237799789','https://lh3.googleusercontent.com/a/ACg8ocJpHo2JG0KHtxE5_QqtBeRF521EbirKQIPFSUlxselsxMt5EA=s96-c','local','2026-09-12 19:43:34','$2y$12$giP6AmVdLdpFtl8ibnTMPeXlu/AAAlmFu5Vz26h.uEEihPXWG.8HC','buyer','09482672310','2006-01-05',NULL,NULL,'Ww9qq0OAdO5CsnQg1jHKaGBPHb8HEGt3B1aFiE019VGhWc8jiWwOypStnMnh','2026-09-12 19:42:48','2026-09-25 00:06:33','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(9,'Del Mundo Vincent','0325-0391@lspu.edu.ph','101287084807254256841','https://lh3.googleusercontent.com/a/ACg8ocLQJa1xPA_kbRFUN8z6LJJCboEDu-ha0fo0XdE1W7Z9NPExDw=s96-c','google','2026-09-14 06:46:02',NULL,'buyer',NULL,NULL,NULL,NULL,NULL,'2026-09-14 06:46:02','2026-09-14 06:46:02','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(10,'Juan Cruz','exampleemail@gmail.com',NULL,NULL,'local',NULL,'$2y$12$IyqOjXvhliRgUjOwPSu1oeiuOidaoSry9NyNMlJQUG15dkCER20S.','buyer','09123456789','2003-06-18',NULL,NULL,NULL,'2026-09-18 20:21:28','2026-09-18 20:21:28','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(11,'Demo Seller','seller-demo@zaylo.test',NULL,NULL,'local','2026-09-19 04:17:36','$2y$12$GEV5/iENq1uusV74y7w4juIK6CDB3GZtcUsifDdffptQ3IYgxTjQm','seller',NULL,NULL,NULL,NULL,'obfAnxsHrLpgnUtZo6P0RHDPHICdDZefQtgoZuvHbPCaOa3P1AocL9jZqQxd','2026-09-19 04:16:38','2026-09-19 04:16:38','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(15,'Ravien Ongkiko','ongkikoravien26@gmail.com',NULL,NULL,'local','2026-10-06 19:29:37','$2y$12$PNItSzRTrEG97DzVwqgDY.EqJUR/L1DYFG46U7PT.SDTDatqA0bSa','buyer','09012345678','2005-11-26',NULL,NULL,NULL,'2026-10-06 19:28:18','2026-10-06 19:29:37','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(16,'Ravia onkis','Raviaonkis@gmailcom',NULL,NULL,'local',NULL,'$2y$12$gS0TDA2d6bJXcgtGZ8ZZm.PvE5dx9t8LE.SSuzvk6lVFvaQst1p96','buyer','09124828428','2008-11-20',NULL,NULL,NULL,'2026-10-06 19:36:55','2026-10-06 19:36:55','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(17,'j tiongson','tiongsonjundell09@gmail.com',NULL,NULL,'local',NULL,'$2y$12$zsHYbFfFc3Uq4XztHQnaTevZKaLTho6BOGgrFjav7nbKWYUuRWmJ6','buyer','09935487520','1998-05-13',NULL,NULL,NULL,'2026-10-06 19:54:04','2026-10-06 19:54:04','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(18,'Jundell Tiongson','buyer.test01@example.com',NULL,NULL,'local',NULL,'$2y$12$4DvDcV6uCFvrGaeG.5nohuO23ayD75Nh9KrkvUb0FavMtCRDAcztm','buyer','09935487520','2006-05-07',NULL,NULL,NULL,'2026-10-06 20:01:41','2026-10-06 20:01:41','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(19,'melany ann cejo','cjmlnyann@gmail.com',NULL,NULL,'local',NULL,'$2y$12$lAIAC4aBawIvVwaib9WFAOwpy9zff/eFRxVvJEWmqf2bfVvU2.Ksq','buyer','09935487522','2004-12-08',NULL,NULL,NULL,'2026-10-07 01:30:04','2026-10-07 01:30:04','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(20,'Jundell Tiongson','jundellsocial@gmail.com',NULL,NULL,'local',NULL,'$2y$12$WT4Sk02pFccEEfPIDYULiOETG0oK6MvGN9C5K/PIQmX3XeIeewFFy','buyer','09157359197','2003-12-08',NULL,NULL,NULL,'2026-10-07 07:09:32','2026-10-07 07:09:32','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(21,'Demo Buyer','buyer.demo@zaylo.test',NULL,NULL,'local','2026-10-08 06:11:17','$2y$12$vT1nx4xGh/Ux8LYN9X7LguEFHbzACuO6kufawiB3PNbKZv0TbQItW','buyer','09171234567','1998-04-12',NULL,NULL,'2Br6FxRFTdDiLlIVXTR6PGcDHxvxagmcrnw3SX1scaZH55ZrNzCVKL7VbYf8','2026-10-08 06:11:17','2026-10-08 06:11:17','active',NULL,NULL,'2026-10-15 20:32:43',NULL),(30,'Juan Dela Cruz','juandelacruz@test.com',NULL,NULL,'local','2026-10-10 10:14:34','$2y$12$rpTeiX6yXknKlkMKd22uBePuTnpjSMfBmLXLrnEcuOXKO7u84wl96','rider','09556644889','2008-10-03','male',NULL,NULL,'2026-10-10 10:13:03','2026-10-10 12:06:53','active','2026-10-10 10:14:33',NULL,'2026-10-15 20:32:43',NULL),(38,'Jillian Gutierrez','gutierrezjillian52@gmail.com',NULL,NULL,'local',NULL,'$2y$12$mEi4kNz4eu1B9vSiJYIAJ.bBiePhLkPaId.DcOu9Gt12W0zb0JzzW','buyer','09482672310','2006-10-19',NULL,NULL,NULL,'2026-10-18 19:25:23','2026-10-18 19:25:23','active',NULL,NULL,NULL,NULL),(39,'Test Logistics Provider','logistics@zaylo.com',NULL,NULL,'local',NULL,'$2y$12$/Yg8iu3bYXQ9sQMW0UGWZOFCRV6t8d7FXLaoK/R76f1F08XQT9Cei','logistics',NULL,NULL,NULL,NULL,NULL,'2026-10-18 23:19:45','2026-10-18 23:19:45','active',NULL,NULL,NULL,NULL),(40,'Test Rider','rider@zaylo.com',NULL,NULL,'local','2026-10-18 23:23:02','$2y$12$xg7c/WDrPw//jKZBkZJ0TuZrfs/ZuKQ1OLvGj/8hpA6OY5k895Kji','rider','09170000001','1998-05-14','male',NULL,NULL,'2026-10-18 23:19:46','2026-10-18 23:23:02','active','2026-10-18 23:23:02',NULL,'2026-10-18 23:23:02',NULL),(43,'Maria Santos','dailykitchenco.shop@gmail.com',NULL,NULL,'local','2026-11-01 07:04:45','$2y$12$.VMY0BbfQmM4WcHUcNadku0cF3OVoHJ602os8IAXBSyTlgRvaZb.W','seller',NULL,NULL,NULL,NULL,NULL,'2026-11-01 07:04:45','2026-11-01 07:05:07','active','2026-11-01 07:04:45',NULL,NULL,NULL),(44,'Paolo Reyes','homesteadliving1.shop@gmail.com',NULL,NULL,'local','2026-11-01 07:04:46','$2y$12$f6GyVB4W/ZTFRo6dIlwDseHIvZ4GlDz/GTroH46dkQeJx5Hsg1gqG','seller',NULL,NULL,NULL,NULL,NULL,'2026-11-01 07:04:46','2026-11-01 07:05:07','active','2026-11-01 07:04:46',NULL,NULL,NULL),(45,'Liza Mendoza','greenhandsupply.shop@gmail.com',NULL,NULL,'local','2026-11-01 07:04:46','$2y$12$CedSowB7YNyFLDnFScpe7eFi3qMKGJzrVdujfWnnZvJYA.rn8Fd4G','seller',NULL,NULL,NULL,NULL,NULL,'2026-11-01 07:04:46','2026-11-01 07:05:07','active','2026-11-01 07:04:46',NULL,NULL,NULL),(46,'Carlo Ramos','steadfast-works@zaylo-zyl.shop',NULL,NULL,'local','2026-11-01 07:04:47','$2y$12$2bIcdfg0Sz04Q1u.tJAFq.DEcjrgDCaifOql8dN7UqJgE147hfAgm','seller',NULL,NULL,NULL,NULL,NULL,'2026-11-01 07:04:47','2026-11-01 07:05:07','active','2026-11-01 07:04:47',NULL,NULL,NULL),(47,'Ana Villanueva','softnest-linens@zaylo-zyl.shop',NULL,NULL,'local','2026-11-01 07:04:47','$2y$12$YsZwOEZPAcwr1RU421H/ZOM8eKOXQHpIumpExY9i7W/QtTECRtldi','seller',NULL,NULL,NULL,NULL,NULL,'2026-11-01 07:04:47','2026-11-01 07:05:07','active','2026-11-01 07:04:47',NULL,NULL,NULL),(48,'Shop Test Buyer','buyer.shoptest@zaylo.test',NULL,NULL,'local','2026-11-01 22:18:39','$2y$12$2HYXSSHdUVpdwCIDQ1t0Kek7uiTSEnYRZvZ7k2dis/ucmSoIVX8aa','buyer',NULL,NULL,NULL,NULL,NULL,'2026-11-01 22:18:39','2026-11-01 22:18:39','active',NULL,NULL,'2026-11-01 22:18:39',NULL),(49,'Shop Test Seller','seller.shoptest@zaylo.test',NULL,NULL,'local','2026-11-01 22:18:40','$2y$12$XAtKK4zacD/0IUdsy.TUKeGt6UarD4pWfjaPSRTq27RWf7INgb6pu','seller','09170000123',NULL,NULL,NULL,NULL,'2026-11-01 22:18:40','2026-11-01 22:18:40','active','2026-11-01 22:18:40',NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `usage_limit` int unsigned DEFAULT NULL,
  `used_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
INSERT INTO `vouchers` VALUES (1,'ZAYLOSHIP','free_shipping',1,NULL,NULL,NULL,0,'2026-09-18 20:43:48','2026-09-18 20:43:48');
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlists`
--

DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlists_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `wishlists_product_id_foreign` (`product_id`),
  CONSTRAINT `wishlists_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlists`
--

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-10 14:42:22
