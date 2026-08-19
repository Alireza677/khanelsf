-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: noor
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('khanelsf_cache_livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3','i:1;',1786849462),('khanelsf_cache_livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3:timer','i:1786849461;',1786849461);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
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
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_project_activities`
--

DROP TABLE IF EXISTS `client_project_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_project_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_project_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `service_name_snapshot` varchar(255) DEFAULT NULL,
  `service_unit_snapshot` varchar(255) DEFAULT NULL,
  `service_unit_label_snapshot` varchar(255) DEFAULT NULL,
  `pricing_mode_snapshot` varchar(255) DEFAULT NULL,
  `currency_snapshot` char(3) DEFAULT NULL,
  `unit_price_snapshot` decimal(18,4) DEFAULT NULL,
  `quantity` decimal(18,4) DEFAULT NULL,
  `total_amount` decimal(18,2) DEFAULT NULL,
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `activity_date` date NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration_minutes` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `visibility` varchar(255) NOT NULL DEFAULT 'internal',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_project_activities_performed_by_foreign` (`performed_by`),
  KEY `client_project_activities_client_project_id_activity_date_index` (`client_project_id`,`activity_date`),
  KEY `client_project_activities_activity_date_status_index` (`activity_date`,`status`),
  KEY `client_project_activities_visibility_index` (`visibility`),
  KEY `client_project_activities_status_index` (`status`),
  KEY `client_project_activities_service_id_foreign` (`service_id`),
  CONSTRAINT `client_project_activities_client_project_id_foreign` FOREIGN KEY (`client_project_id`) REFERENCES `client_projects` (`id`),
  CONSTRAINT `client_project_activities_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `client_project_activities_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_project_activities`
--

LOCK TABLES `client_project_activities` WRITE;
/*!40000 ALTER TABLE `client_project_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_project_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_projects`
--

DROP TABLE IF EXISTS `client_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `monthly_hour_limit_minutes` int(10) unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_projects_customer_id_status_index` (`customer_id`,`status`),
  KEY `client_projects_customer_id_updated_at_index` (`customer_id`,`updated_at`),
  KEY `client_projects_status_index` (`status`),
  CONSTRAINT `client_projects_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_projects`
--

LOCK TABLES `client_projects` WRITE;
/*!40000 ALTER TABLE `client_projects` DISABLE KEYS */;
INSERT INTO `client_projects` VALUES (1,1,'طراحی سایت',NULL,'قراردادی - ساعتی','active',0,1800,NULL,NULL,'2026-08-14 14:30:27','2026-08-14 14:30:27');
/*!40000 ALTER TABLE `client_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_messages_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_user`
--

DROP TABLE IF EXISTS `customer_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `membership_role` varchar(255) NOT NULL DEFAULT 'member',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_user_customer_id_user_id_unique` (`customer_id`,`user_id`),
  KEY `customer_user_user_id_customer_id_index` (`user_id`,`customer_id`),
  CONSTRAINT `customer_user_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_user`
--

LOCK TABLES `customer_user` WRITE;
/*!40000 ALTER TABLE `customer_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `display_name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customers_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Amer',NULL,'09137132241','alirezaameri677@gmail.com',NULL,NULL,'active','2026-08-14 14:29:53','2026-08-14 14:29:53');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
-- Table structure for table `form_submissions`
--

DROP TABLE IF EXISTS `form_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'website',
  `page_id` bigint(20) unsigned DEFAULT NULL,
  `page_url` text DEFAULT NULL,
  `block_id` varchar(26) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `calculation_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_result`)),
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `form_submissions_form_id_foreign` (`form_id`),
  KEY `form_submissions_page_id_foreign` (`page_id`),
  KEY `form_submissions_source_index` (`source`),
  KEY `form_submissions_block_id_index` (`block_id`),
  CONSTRAINT `form_submissions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`),
  CONSTRAINT `form_submissions_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_submissions`
--

LOCK TABLES `form_submissions` WRITE;
/*!40000 ALTER TABLE `form_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forms`
--

DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `display_mode` varchar(255) NOT NULL DEFAULT 'page',
  `type` varchar(255) NOT NULL DEFAULT 'normal',
  `calculator_identifier` varchar(255) DEFAULT NULL,
  `schema_version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forms_slug_unique` (`slug`),
  KEY `forms_status_index` (`status`),
  KEY `forms_display_mode_index` (`display_mode`),
  KEY `forms_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forms`
--

LOCK TABLES `forms` WRITE;
/*!40000 ALTER TABLE `forms` DISABLE KEYS */;
/*!40000 ALTER TABLE `forms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `galleries`
--

DROP TABLE IF EXISTS `galleries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `galleries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gallery_category_id` bigint(20) unsigned DEFAULT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` longtext DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'image',
  `video_url` varchar(255) DEFAULT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `galleries_slug_unique` (`slug`),
  KEY `galleries_gallery_category_id_foreign` (`gallery_category_id`),
  KEY `galleries_project_id_foreign` (`project_id`),
  KEY `galleries_type_index` (`type`),
  KEY `galleries_status_index` (`status`),
  KEY `galleries_published_at_index` (`published_at`),
  KEY `galleries_is_featured_index` (`is_featured`),
  KEY `galleries_sort_order_index` (`sort_order`),
  CONSTRAINT `galleries_gallery_category_id_foreign` FOREIGN KEY (`gallery_category_id`) REFERENCES `gallery_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `galleries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `galleries`
--

LOCK TABLES `galleries` WRITE;
/*!40000 ALTER TABLE `galleries` DISABLE KEYS */;
/*!40000 ALTER TABLE `galleries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery_categories`
--

DROP TABLE IF EXISTS `gallery_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gallery_categories_slug_unique` (`slug`),
  KEY `gallery_categories_status_index` (`status`),
  KEY `gallery_categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery_categories`
--

LOCK TABLES `gallery_categories` WRITE;
/*!40000 ALTER TABLE `gallery_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
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
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_submission_id` bigint(20) unsigned DEFAULT NULL,
  `form_id` bigint(20) unsigned DEFAULT NULL,
  `page_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `calculation_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_result`)),
  `status` varchar(255) NOT NULL DEFAULT 'new',
  `source` varchar(255) NOT NULL DEFAULT 'website',
  `page_url` text DEFAULT NULL,
  `block_id` varchar(26) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_form_submission_id_unique` (`form_submission_id`),
  KEY `leads_form_id_foreign` (`form_id`),
  KEY `leads_page_id_foreign` (`page_id`),
  KEY `leads_status_index` (`status`),
  KEY `leads_source_index` (`source`),
  KEY `leads_block_id_index` (`block_id`),
  CONSTRAINT `leads_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`),
  CONSTRAINT `leads_form_submission_id_foreign` FOREIGN KEY (`form_submission_id`) REFERENCES `form_submissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `collection_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `disk` varchar(255) NOT NULL,
  `conversions_disk` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `manipulations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`manipulations`)),
  `custom_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`custom_properties`)),
  `generated_conversions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`generated_conversions`)),
  `responsive_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`responsive_images`)),
  `order_column` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media`
--

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
INSERT INTO `media` VALUES (1,'App\\Models\\User',1,'3a96601b-99ed-4025-a717-624b20ed492a','media_library','عایق‌کاری دیوارها و سقف در سازه LSF نبکاسازه','عایقکاری-دیوارها-و-سقف-در-سازه-LSF-نبکاسازه.jpg','image/jpeg','public','public',270996,'[]','[]','[]','[]',1,'2026-08-14 14:22:54','2026-08-14 14:22:54'),(6,'App\\Models\\Project',1,'8f4c2e8b-79e1-4871-a86c-a1ae4f752134','featured_image','عایق‌کاری دیوارها و سقف در سازه LSF نبکاسازه','عایقکاری-دیوارها-و-سقف-در-سازه-LSF-نبکاسازه.jpg','image/jpeg','public','public',270996,'[]','{\"source_media_id\":1}','{\"thumb\":true,\"card\":true}','[]',1,'2026-08-14 18:05:44','2026-08-14 18:05:45'),(10,'App\\Models\\Project',2,'4e0ed3bc-6abc-4730-98c8-4d7e48d53e54','featured_image','عایق‌کاری دیوارها و سقف در سازه LSF نبکاسازه','عایقکاری-دیوارها-و-سقف-در-سازه-LSF-نبکاسازه.jpg','image/jpeg','public','public',270996,'[]','{\"source_media_id\":1}','{\"thumb\":true,\"card\":true}','[]',1,'2026-08-16 03:05:13','2026-08-16 03:05:15'),(11,'App\\Models\\Service',1,'e51ee136-9064-4cf5-b350-49765481cc03','featured_image','عایق‌کاری دیوارها و سقف در سازه LSF نبکاسازه','عایقکاری-دیوارها-و-سقف-در-سازه-LSF-نبکاسازه.jpg','image/jpeg','public','public',270996,'[]','{\"source_media_id\":1}','{\"thumb\":true}','[]',8,'2026-08-16 03:05:51','2026-08-16 03:05:51');
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'custom_url',
  `source_key` varchar(128) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `target` varchar(255) NOT NULL DEFAULT '_self',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_items_menu_id_foreign` (`menu_id`),
  KEY `menu_items_parent_id_foreign` (`parent_id`),
  KEY `menu_items_status_index` (`status`),
  KEY `menu_items_reference_index` (`reference_type`,`reference_id`),
  KEY `menu_items_type_index` (`type`),
  KEY `menu_items_source_key_index` (`source_key`),
  CONSTRAINT `menu_items_menu_id_foreign` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_items_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,1,NULL,'source','galleries.archive',NULL,NULL,'گالری پروژه‌ها',NULL,'_self',0,'active','2026-08-14 14:16:41','2026-08-14 14:16:41'),(2,1,NULL,'source','services.archive',NULL,NULL,'خدمات',NULL,'_self',1,'active','2026-08-14 14:16:41','2026-08-14 14:16:41'),(3,1,NULL,'source','shop.index',NULL,NULL,'فروشگاه',NULL,'_self',2,'active','2026-08-14 14:22:15','2026-08-14 14:22:15');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menus_slug_unique` (`slug`),
  KEY `menus_location_index` (`location`),
  KEY `menus_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menus`
--

LOCK TABLES `menus` WRITE;
/*!40000 ALTER TABLE `menus` DISABLE KEYS */;
INSERT INTO `menus` VALUES (1,'هدر','hdr',NULL,'active','2026-08-14 14:16:08','2026-08-14 14:16:08');
/*!40000 ALTER TABLE `menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_05_30_000000_create_cms_tables',1),(5,'2026_05_30_000001_create_media_table',1),(6,'2026_06_11_000000_add_seo_core_fields_to_pages_and_posts',1),(7,'2026_06_11_000001_add_published_at_to_pages',1),(8,'2026_06_11_000002_add_blocks_to_pages',1),(9,'2026_06_11_000003_create_project_tables',1),(10,'2026_06_12_000000_create_shop_tables',1),(11,'2026_06_12_000001_add_admin_note_to_orders_table',1),(12,'2026_06_12_000002_create_redirects_table',1),(13,'2026_06_12_000003_create_gallery_categories_table',1),(14,'2026_06_12_000004_create_galleries_table',1),(15,'2026_06_13_000000_create_templates_table',1),(16,'2026_06_16_000000_localize_defaults_to_persian',1),(17,'2026_06_25_000000_expand_content_text_columns',1),(18,'2026_07_16_000000_create_lead_generation_tables',1),(19,'2026_07_16_000001_add_display_mode_to_forms_table',1),(20,'2026_07_16_000002_add_calculator_support_to_lead_generation_tables',1),(21,'2026_07_16_000003_migrate_construction_calculator_to_schema',1),(22,'2026_07_24_000000_add_link_reference_to_menu_items_table',1),(23,'2026_07_25_000000_add_case_study_fields_to_projects_table',1),(24,'2026_07_25_000001_create_services_and_project_service_tables',1),(25,'2026_07_25_000002_create_project_metrics_table',1),(26,'2026_07_27_000000_create_product_specifications_table',1),(27,'2026_07_27_000001_create_product_documents_table',1),(28,'2026_07_27_000002_create_product_related_product_table',1),(29,'2026_07_28_000000_add_source_key_to_menu_items_table',1),(30,'2026_07_28_000001_add_cms_foundation_fields_to_services_table',1),(31,'2026_08_06_000000_add_client_authentication_fields_to_users_table',1),(32,'2026_08_06_000001_create_customers_and_customer_user_tables',1),(33,'2026_08_06_000002_create_client_projects_table',1),(34,'2026_08_06_000003_add_monthly_hour_limit_to_client_projects_table',1),(35,'2026_08_06_000004_create_client_project_activities_table',1),(36,'2026_08_07_000000_create_project_videos_table',1),(37,'2026_08_07_000001_create_project_discovery_taxonomy_tables',1),(38,'2026_08_09_000000_add_user_id_to_orders_table',1),(39,'2026_08_14_000000_add_commercial_fields_to_services_table',1),(40,'2026_08_14_000001_add_service_snapshot_to_client_project_activities_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_title` varchar(255) NOT NULL,
  `product_sku` varchar(255) DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `order_number` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(255) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_status_index` (`status`),
  KEY `orders_payment_status_index` (`payment_status`),
  KEY `orders_user_id_index` (`user_id`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`blocks`)),
  `template` varchar(255) NOT NULL DEFAULT 'default',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `seo_keywords` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_slug_unique` (`slug`),
  KEY `pages_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` longtext DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `seo_keywords` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `posts_slug_unique` (`slug`),
  KEY `posts_category_id_foreign` (`category_id`),
  KEY `posts_status_index` (`status`),
  CONSTRAINT `posts_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_slug_unique` (`slug`),
  KEY `product_categories_status_index` (`status`),
  KEY `product_categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_documents`
--

DROP TABLE IF EXISTS `product_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `disk` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `external_url` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_documents_product_id_sort_order_index` (`product_id`,`sort_order`),
  CONSTRAINT `product_documents_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_documents`
--

LOCK TABLES `product_documents` WRITE;
/*!40000 ALTER TABLE `product_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_related_product`
--

DROP TABLE IF EXISTS `product_related_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_related_product` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `related_product_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_related_product_product_id_related_product_id_unique` (`product_id`,`related_product_id`),
  KEY `product_related_product_product_id_sort_order_index` (`product_id`,`sort_order`),
  KEY `product_related_product_related_product_id_sort_order_index` (`related_product_id`,`sort_order`),
  CONSTRAINT `product_related_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_related_product_related_product_id_foreign` FOREIGN KEY (`related_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_related_product`
--

LOCK TABLES `product_related_product` WRITE;
/*!40000 ALTER TABLE `product_related_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_related_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_specifications`
--

DROP TABLE IF EXISTS `product_specifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_specifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_specifications_product_id_sort_order_index` (`product_id`,`sort_order`),
  KEY `product_specifications_product_id_key_index` (`product_id`,`key`),
  CONSTRAINT `product_specifications_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_specifications`
--

LOCK TABLES `product_specifications` WRITE;
/*!40000 ALTER TABLE `product_specifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_specifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` longtext DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `has_stock` tinyint(1) NOT NULL DEFAULT 1,
  `stock_status` varchar(255) NOT NULL DEFAULT 'in_stock',
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  KEY `products_product_category_id_foreign` (`product_category_id`),
  KEY `products_sku_index` (`sku`),
  KEY `products_status_index` (`status`),
  KEY `products_published_at_index` (`published_at`),
  KEY `products_is_featured_index` (`is_featured`),
  KEY `products_sort_order_index` (`sort_order`),
  KEY `products_stock_status_index` (`stock_status`),
  CONSTRAINT `products_product_category_id_foreign` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,NULL,'ابزار','abzar',NULL,NULL,4544444.00,444444.00,NULL,'published',NULL,0,0,1,'in_stock',NULL,NULL,NULL,1,1,'2026-08-14 14:23:33','2026-08-14 14:23:43');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_categories`
--

DROP TABLE IF EXISTS `project_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_categories_slug_unique` (`slug`),
  KEY `project_categories_status_index` (`status`),
  KEY `project_categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_categories`
--

LOCK TABLES `project_categories` WRITE;
/*!40000 ALTER TABLE `project_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_discovery_term_project`
--

DROP TABLE IF EXISTS `project_discovery_term_project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_discovery_term_project` (
  `project_id` bigint(20) unsigned NOT NULL,
  `project_discovery_term_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`project_id`,`project_discovery_term_id`),
  KEY `project_discovery_term_project_project_discovery_term_id_foreign` (`project_discovery_term_id`),
  CONSTRAINT `project_discovery_term_project_project_discovery_term_id_foreign` FOREIGN KEY (`project_discovery_term_id`) REFERENCES `project_discovery_terms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_discovery_term_project_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_discovery_term_project`
--

LOCK TABLES `project_discovery_term_project` WRITE;
/*!40000 ALTER TABLE `project_discovery_term_project` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_discovery_term_project` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_discovery_terms`
--

DROP TABLE IF EXISTS `project_discovery_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_discovery_terms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_discovery_vocabulary_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_discovery_terms_vocabulary_slug_unique` (`project_discovery_vocabulary_id`,`slug`),
  KEY `project_discovery_terms_browse_index` (`project_discovery_vocabulary_id`,`is_active`,`sort_order`),
  KEY `project_discovery_terms_is_active_index` (`is_active`),
  CONSTRAINT `project_discovery_terms_project_discovery_vocabulary_id_foreign` FOREIGN KEY (`project_discovery_vocabulary_id`) REFERENCES `project_discovery_vocabularies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_discovery_terms`
--

LOCK TABLES `project_discovery_terms` WRITE;
/*!40000 ALTER TABLE `project_discovery_terms` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_discovery_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_discovery_vocabularies`
--

DROP TABLE IF EXISTS `project_discovery_vocabularies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_discovery_vocabularies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_discovery_vocabularies_slug_unique` (`slug`),
  KEY `project_discovery_vocabularies_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `project_discovery_vocabularies_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_discovery_vocabularies`
--

LOCK TABLES `project_discovery_vocabularies` WRITE;
/*!40000 ALTER TABLE `project_discovery_vocabularies` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_discovery_vocabularies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_metrics`
--

DROP TABLE IF EXISTS `project_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `prefix` varchar(255) DEFAULT NULL,
  `suffix` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_metrics_project_id_sort_order_index` (`project_id`,`sort_order`),
  CONSTRAINT `project_metrics_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_metrics`
--

LOCK TABLES `project_metrics` WRITE;
/*!40000 ALTER TABLE `project_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_service`
--

DROP TABLE IF EXISTS `project_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_service` (
  `project_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`project_id`,`service_id`),
  KEY `project_service_service_id_foreign` (`service_id`),
  CONSTRAINT `project_service_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_service`
--

LOCK TABLES `project_service` WRITE;
/*!40000 ALTER TABLE `project_service` DISABLE KEYS */;
INSERT INTO `project_service` VALUES (1,1,'2026-08-14 18:05:58','2026-08-14 18:05:58'),(2,1,'2026-08-16 03:05:51','2026-08-16 03:05:51');
/*!40000 ALTER TABLE `project_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_videos`
--

DROP TABLE IF EXISTS `project_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_videos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `url` varchar(2048) NOT NULL,
  `provider` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `thumbnail_url` varchar(2048) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_videos_project_id_sort_order_index` (`project_id`,`sort_order`),
  CONSTRAINT `project_videos_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_videos`
--

LOCK TABLES `project_videos` WRITE;
/*!40000 ALTER TABLE `project_videos` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` longtext DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `challenge` longtext DEFAULT NULL,
  `solution` longtext DEFAULT NULL,
  `results_summary` longtext DEFAULT NULL,
  `client_quote` longtext DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `project_type` varchar(255) DEFAULT NULL,
  `project_date` date DEFAULT NULL,
  `project_started_at` date DEFAULT NULL,
  `project_completed_at` date DEFAULT NULL,
  `services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`services`)),
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `external_url` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` longtext DEFAULT NULL,
  `seo_image` varchar(255) DEFAULT NULL,
  `robots_index` tinyint(1) NOT NULL DEFAULT 1,
  `robots_follow` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_slug_unique` (`slug`),
  KEY `projects_project_category_id_foreign` (`project_category_id`),
  KEY `projects_status_index` (`status`),
  KEY `projects_published_at_index` (`published_at`),
  KEY `projects_is_featured_index` (`is_featured`),
  KEY `projects_sort_order_index` (`sort_order`),
  CONSTRAINT `projects_project_category_id_foreign` FOREIGN KEY (`project_category_id`) REFERENCES `project_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,NULL,'یییییییییی','yyyyyyyyyy',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[{\"name\":\"\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\"}]','[{\"label\":\"\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\",\"value\":\"\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\\u06cc\"}]',NULL,'published',NULL,0,0,NULL,NULL,NULL,1,1,'2026-08-14 18:05:44','2026-08-14 18:05:44'),(2,NULL,'fffffffffffffff','fffffffffffffff',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[{\"name\":\"ddddddd\"}]','[{\"label\":\"dddddd\",\"value\":\"dddddd\"}]',NULL,'draft',NULL,0,0,NULL,NULL,NULL,1,1,'2026-08-16 03:05:03','2026-08-16 03:05:03');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redirects`
--

DROP TABLE IF EXISTS `redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `redirects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_path` varchar(255) NOT NULL,
  `target_url` varchar(255) NOT NULL,
  `status_code` smallint(5) unsigned NOT NULL DEFAULT 301,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `hits_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `last_hit_at` timestamp NULL DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `redirects_source_path_unique` (`source_path`),
  KEY `redirects_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redirects`
--

LOCK TABLES `redirects` WRITE;
/*!40000 ALTER TABLE `redirects` DISABLE KEYS */;
/*!40000 ALTER TABLE `redirects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `overview` longtext DEFAULT NULL,
  `benefits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`benefits`)),
  `process` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`process`)),
  `deliverables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deliverables`)),
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `published_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `available_for_activities` tinyint(1) NOT NULL DEFAULT 0,
  `pricing_mode` varchar(255) DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `custom_unit_label` varchar(255) DEFAULT NULL,
  `default_unit_price` decimal(18,4) DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_slug_unique` (`slug`),
  KEY `services_status_index` (`status`),
  KEY `services_sort_order_index` (`sort_order`),
  KEY `services_published_at_index` (`published_at`),
  KEY `services_available_for_activities_index` (`available_for_activities`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'توسعه وبسایت','tosaah-obsayt','طراحی، توسعه و بهبود وب‌سایت‌های حرفه‌ای با تمرکز بر سرعت، تجربه کاربری، سئو و قابلیت توسعه متناسب با نیاز کسب‌وکار.','<p dir=\"rtl\"><strong>توسعه وب‌سایت متناسب با نیاز واقعی کسب‌وکار</strong></p><p dir=\"rtl\">این خدمت شامل طراحی، پیاده‌سازی، توسعه و بهبود وب‌سایت‌های حرفه‌ای است؛ از راه‌اندازی اولیه یک وب‌سایت شرکتی یا فروشگاهی تا توسعه قابلیت‌های اختصاصی برای کسب‌وکارهایی که به امکانات بیشتری نیاز دارند.</p><p dir=\"rtl\">در فرآیند توسعه، علاوه بر ظاهر وب‌سایت، مواردی مانند ساختار فنی، سرعت، نمایش صحیح در موبایل، تجربه کاربری، سئو، امنیت و قابلیت توسعه در آینده نیز در نظر گرفته می‌شود.</p><p dir=\"rtl\">بسته به نیاز پروژه، خدمات توسعه وب‌سایت می‌تواند شامل طراحی صفحات جدید، توسعه بخش‌های اختصاصی، اتصال فرم‌ها و فرآیندهای کسب‌وکار، فروشگاه اینترنتی، پنل کاربران، سیستم‌های مدیریتی، بهینه‌سازی فنی و توسعه قابلیت‌های موجود باشد.</p><p dir=\"rtl\">هدف این است که وب‌سایت صرفاً یک حضور آنلاین نباشد، بلکه به ابزاری قابل توسعه برای معرفی خدمات، جذب مشتری، فروش و مدیریت ارتباط با مخاطبان تبدیل شود.</p><p dir=\"rtl\"><strong>نمونه فعالیت‌هایی که در این خدمت قابل انجام است:</strong></p><ul dir=\"rtl\"><li>&nbsp;طراحی و توسعه صفحات وب‌سایت&nbsp;</li><li>&nbsp;توسعه قابلیت‌ها و ماژول‌های اختصاصی&nbsp;</li><li>&nbsp;راه‌اندازی یا توسعه فروشگاه اینترنتی&nbsp;</li><li>&nbsp;طراحی پنل و حساب کاربری مشتریان&nbsp;</li><li>&nbsp;اتصال فرم‌ها و فرآیندهای دریافت سرنخ&nbsp;</li><li>&nbsp;بهبود تجربه کاربری و نسخه موبایل&nbsp;</li><li>&nbsp;افزایش سرعت و بهینه‌سازی فنی&nbsp;</li><li>&nbsp;پیاده‌سازی ساختارهای مناسب سئو&nbsp;</li><li>&nbsp;رفع خطاها و توسعه امکانات وب‌سایت موجود&nbsp;</li><li>&nbsp;توسعه مرحله‌ای وب‌سایت بر اساس نیازهای آینده کسب‌وکار</li></ul>','[{\"title\":\"توسعه سریع و مرحله‌ای\",\"description\":\"ساختار فنی وب‌سایت به‌گونه‌ای طراحی می‌شود که در آینده بتوان قابلیت‌هایی مانند فروشگاه، حساب کاربری، فرم‌های پیشرفته، پنل مشتریان، سیستم‌های اختصاصی و سایر ماژول‌ها را به آن اضافه کرد.\",\"icon\":\"icon-square\"},{\"title\":\"زیرساخت قابل توسعه\",\"description\":\"صفحات و امکانات وب‌سایت برای موبایل، تبلت و دسکتاپ بهینه می‌شوند تا کاربران در تمام دستگاه‌ها تجربه‌ای سریع، ساده و حرفه‌ای داشته باشند.\",\"icon\":\"icon-align-vertically\"},{\"title\":\"ساختار مناسب سئو\",\"description\":\"ساختار صفحات، آدرس‌ها، محتوا و بخش‌های فنی وب‌سایت با درنظرگرفتن اصول سئو ایجاد می‌شود تا بستر مناسبی برای تولید محتوا و افزایش ورودی گوگل فراهم باشد.\",\"icon\":\"icon-arrow-circle-left\"},{\"title\":\"توسعه امکانات اختصاصی\",\"description\":\"به‌جای محدودشدن به قابلیت‌های یک قالب آماده، امکانات موردنیاز کسب‌وکار می‌توانند متناسب با فرآیند واقعی آن طراحی و به وب‌سایت اضافه شوند.\",\"icon\":\"icon-arrow-circle-left\"},{\"title\":\"توسعه و بهبود مستمر\",\"description\":\"راه‌اندازی وب‌سایت پایان کار نیست. بعد از شروع فعالیت سایت می‌توان بر اساس نیاز کاربران، اطلاعات واقعی کسب‌وکار و اهداف جدید، بخش‌ها و قابلیت‌های آن را به‌صورت مستمر توسعه داد.\",\"icon\":\"icon-align-bottom\"}]','[{\"title\":\"بررسی نیاز و هدف پروژه\",\"description\":\"در ابتدای همکاری، نیازهای کسب‌وکار، هدف وب‌سایت، مخاطبان، امکانات موردنیاز و مسیر کلی پروژه بررسی می‌شود تا ساختار توسعه بر اساس نیاز واقعی پروژه مشخص شود.\",\"step\":1},{\"title\":\"طراحی ساختار و برنامه توسعه\",\"description\":\"پس از بررسی اولیه، ساختار صفحات، بخش‌های اصلی، قابلیت‌های موردنیاز و اولویت‌های توسعه مشخص می‌شود تا مسیر اجرا شفاف و قابل برنامه‌ریزی باشد.\",\"step\":2},{\"title\":\"پیاده‌سازی و توسعه بخش‌ها\",\"description\":\"در این مرحله، صفحات، امکانات و بخش‌های موردنیاز وب‌سایت به‌صورت مرحله‌ای پیاده‌سازی می‌شوند. توسعه می‌تواند شامل طراحی رابط، برنامه‌نویسی قابلیت‌ها، بهینه‌سازی تجربه کاربری و تکمیل بخش‌های اختصاصی باشد.\",\"step\":3},{\"title\":\"بازبینی، اصلاح و بهینه‌سازی\",\"description\":\"بخش‌های اجراشده بررسی می‌شوند و اصلاحات لازم از نظر عملکرد، نمایش در موبایل، تجربه کاربری، سرعت، سئو و نیازهای پروژه انجام می‌شود تا خروجی نهایی کیفیت بهتری داشته باشد.\",\"step\":4}]','[{\"title\":\"صفحات و بخش‌های توسعه‌یافته\",\"description\":\"صفحات، فرم‌ها، بخش‌ها و قابلیت‌هایی که در محدوده توافق‌شده پروژه طراحی یا توسعه داده شده‌اند و آماده استفاده در وب‌سایت هستند.\"},{\"title\":\"نسخه بهینه موبایل و دسکتاپ\",\"description\":\"نمایش صحیح و هماهنگ بخش‌های توسعه‌یافته در موبایل، تبلت و دسکتاپ با تمرکز بر خوانایی و تجربه کاربری مناسب.\"},{\"title\":\"قابلیت‌های اختصاصی\",\"description\":\"امکانات اختصاصی موردنیاز کسب‌وکار مانند فرم‌ها، پنل کاربران، فرآیندهای داخلی، اتصال بخش‌های مختلف یا سایر قابلیت‌های توافق‌شده.\"}]','published',NULL,0,NULL,NULL,'icon-activity',1,NULL,NULL,NULL,NULL,NULL,'2026-08-14 14:18:30','2026-08-15 14:53:32');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
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
INSERT INTO `sessions` VALUES ('4DRK8S6POEMqBLcTuLCtLlVf5BrEaaMlOoxoeQ2b',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.19041.6456','YTozOntzOjY6Il90b2tlbiI7czo0MDoiMmZqVE56ZWZGY25lVzY3SjNOMnZJbnRMRHR4WHJRNk5PVGVkZVN6dSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NDQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zZXJ2aWNlcy90b3NhYWgtb2JzYXl0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1786850752),('f5Gtfxi34AtgPKZYd0dhZPpdq0I3LTi9kOwTrjnW',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36','YTo3OntzOjY6Il90b2tlbiI7czo0MDoiSWNLMWxTanZQbHJEdVB5b1dWYUNQVDZjaVFwandua05FdVpoVDg0OCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjI3OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvc3cuanMiO31zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjYwOiIkMnkkMTIkQXhQbVg4bWsvbjFRdlhNaUJHczNSdS9iVzV4ZjdNTjVXcHVjbGpDZElwM3hLUWt2T1FQVXEiO3M6ODoiZmlsYW1lbnQiO2E6MDp7fX0=',1786850724),('M6ZIFgN8fwnSvU63vcV0o3BXfG4Y1J2tjvj4F97v',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.19041.6456','YTozOntzOjY6Il90b2tlbiI7czo0MDoiak5HQmg2OHJNSXd1dDQyMDNTdHQ2NzNTZzdBRnpBb0RhVjdQeVR5biI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NDQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zZXJ2aWNlcy90b3NhYWgtb2JzYXl0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1786850008);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `group` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_group_index` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_name','خانه LSF','general','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(2,'site_description',NULL,'general','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(3,'image_placeholder',NULL,'general','image','2026-08-14 14:17:19','2026-08-14 14:17:19'),(4,'health_check_enabled','0','general','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(5,'site_logo',NULL,'branding','image','2026-08-14 14:17:19','2026-08-14 14:17:19'),(6,'site_favicon',NULL,'branding','image','2026-08-14 14:17:19','2026-08-14 14:17:19'),(7,'contact_phone',NULL,'contact','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(8,'contact_email',NULL,'contact','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(9,'contact_address',NULL,'contact','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(10,'social_instagram_url',NULL,'social','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(11,'social_telegram_url',NULL,'social','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(12,'social_whatsapp_url',NULL,'social','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(13,'social_linkedin_url',NULL,'social','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(14,'social_x_url',NULL,'social','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(15,'header_cta_label',NULL,'header','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(16,'header_cta_url',NULL,'header','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(17,'header_menu_id','1','header','select','2026-08-14 14:17:19','2026-08-14 14:17:19'),(18,'header_template_id','1','header','select','2026-08-14 14:17:19','2026-08-14 14:26:10'),(19,'footer_text',NULL,'footer','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(20,'footer_menu_id',NULL,'footer','select','2026-08-14 14:17:19','2026-08-14 14:17:19'),(21,'site_title',NULL,'seo','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(22,'default_meta_description',NULL,'seo','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(23,'default_og_image',NULL,'seo','image','2026-08-14 14:17:19','2026-08-14 14:17:19'),(24,'google_site_verification',NULL,'seo','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(25,'robots_disallow',NULL,'seo','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(26,'robots_txt',NULL,'seo','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(27,'sitemap_enabled','0','seo','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(28,'public_services_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(29,'service_activity_catalog_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:20:29'),(30,'service_pricing_enabled','0','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(31,'default_service_currency','IRT','services','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(32,'service_allowed_units','[\"hour\",\"count\",\"session\",\"meter\",\"square_meter\",\"day\",\"kilogram\",\"fixed\",\"custom\"]','services','json','2026-08-14 14:17:19','2026-08-14 14:17:19'),(33,'service_form_benefits_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(34,'service_form_process_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(35,'service_form_deliverables_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(36,'service_form_media_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(37,'service_form_related_projects_enabled','1','services','boolean','2026-08-14 14:17:19','2026-08-14 14:17:19'),(38,'projects_enabled','1','projects','boolean','2026-08-14 14:17:19','2026-08-14 14:21:57'),(39,'projects_label','Projects','projects','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(40,'projects_index_title','Projects','projects','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(41,'projects_index_description',NULL,'projects','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(42,'projects_per_page',NULL,'projects','number','2026-08-14 14:17:19','2026-08-14 14:17:19'),(43,'projects_seo_title',NULL,'projects','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(44,'projects_seo_description',NULL,'projects','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(45,'projects_og_image',NULL,'projects','image','2026-08-14 14:17:19','2026-08-14 14:17:19'),(46,'galleries_enabled','1','galleries','boolean','2026-08-14 14:17:19','2026-08-14 14:21:57'),(47,'galleries_label','گالری','galleries','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(48,'galleries_index_title','گالری پروژه ها','galleries','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(49,'galleries_index_description',NULL,'galleries','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(50,'galleries_per_page',NULL,'galleries','number','2026-08-14 14:17:19','2026-08-14 14:17:19'),(51,'galleries_seo_title',NULL,'galleries','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(52,'galleries_seo_description',NULL,'galleries','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(53,'shop_enabled','1','shop','boolean','2026-08-14 14:17:19','2026-08-14 14:21:57'),(54,'shop_label','فروشگاه','shop','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(55,'shop_index_title','فروشگاه','shop','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(56,'shop_index_description',NULL,'shop','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(57,'shop_per_page',NULL,'shop','number','2026-08-14 14:17:19','2026-08-14 14:17:19'),(58,'shop_seo_title',NULL,'shop','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(59,'shop_seo_description',NULL,'shop','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(60,'shop_order_admin_email','alirezaameri677@gmail.com','shop','text','2026-08-14 14:17:19','2026-08-14 14:21:57'),(61,'shop_manual_payment_message',NULL,'shop','textarea','2026-08-14 14:17:19','2026-08-14 14:17:19'),(62,'payment_gateway',NULL,'payment','select','2026-08-14 14:17:19','2026-08-14 14:17:19'),(63,'zarinpal_access_token','Alireza123','payment','password','2026-08-14 14:17:19','2026-08-14 14:21:57'),(64,'zarinpal_graphql_endpoint',NULL,'payment','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(65,'zarinpal_callback_url',NULL,'payment','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(66,'primary_color',NULL,'theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(67,'secondary_color',NULL,'theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(68,'accent_color',NULL,'theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(69,'text_color',NULL,'theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(70,'link_color',NULL,'theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(71,'background_color',NULL,'theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(72,'admin_dashboard_background_light','#f0f0f0','theme','color','2026-08-14 14:17:19','2026-08-14 14:17:19'),(73,'font_family','custom','theme','select','2026-08-14 14:17:19','2026-08-14 14:21:57'),(74,'custom_font_name',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(75,'custom_font_file',NULL,'theme','file','2026-08-14 14:17:19','2026-08-14 14:17:19'),(76,'base_font_size','16px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(77,'h1_font_size','24px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(78,'h2_font_size','22px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(79,'h3_font_size','20px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(80,'h4_font_size','18px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(81,'button_font_size','16px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(82,'base_font_size_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(83,'h1_font_size_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(84,'h2_font_size_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(85,'h3_font_size_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(86,'h4_font_size_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(87,'button_font_size_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(88,'button_radius_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(89,'container_width_mobile',NULL,'theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(90,'button_radius','10px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19'),(91,'container_width','1200px','theme','text','2026-08-14 14:17:19','2026-08-14 14:17:19');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`blocks`)),
  `priority` int(11) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditions`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `templates_slug_unique` (`slug`),
  KEY `templates_type_status_is_default_priority_index` (`type`,`status`,`is_default`,`priority`),
  KEY `templates_status_index` (`status`),
  KEY `templates_priority_index` (`priority`),
  KEY `templates_is_default_index` (`is_default`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `templates`
--

LOCK TABLES `templates` WRITE;
/*!40000 ALTER TABLE `templates` DISABLE KEYS */;
INSERT INTO `templates` VALUES (1,'هدر صنعتی دو سطحی','industrial-header-v1','site_header','published','[{\"type\":\"site_header\",\"data\":{\"block_id\":\"01JHEADER00000000000000000\",\"schema_version\":1,\"template\":\"industrial-header-v1\",\"content\":{\"top_actions\":[{\"label\":\"\\u062e\\u062f\\u0645\\u0627\\u062a \\u0648 \\u067e\\u0634\\u062a\\u06cc\\u0628\\u0627\\u0646\\u06cc\",\"action\":{\"schema_version\":1,\"type\":\"custom_url\",\"value\":\"#\",\"open_in_new_tab\":false}},{\"label\":\"\\u0647\\u0645\\u06a9\\u0627\\u0631\\u06cc \\u0628\\u0627 \\u0645\\u0627\",\"action\":{\"schema_version\":1,\"type\":\"custom_url\",\"value\":\"#\",\"open_in_new_tab\":false}}],\"primary_action\":{\"label\":\"\\u0645\\u062d\\u0627\\u0633\\u0628\\u0647 \\u0647\\u0632\\u06cc\\u0646\\u0647 \\u0633\\u0627\\u062e\\u062a\",\"action\":{\"schema_version\":1,\"type\":\"custom_url\",\"value\":\"#\",\"open_in_new_tab\":false}}},\"settings\":{\"menu_id\":null,\"search_enabled\":true,\"sticky_enabled\":true,\"top_bar_enabled\":true}}}]',0,0,'{\"type\":\"all\"}','2026-08-14 14:25:55','2026-08-14 14:27:07'),(3,'قالب استاندارد جزئیات خدمت','service-standard-fa-v1','service_single','published','[{\"type\":\"service_header\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGS\",\"schema_version\":1,\"template\":\"default\",\"content\":[],\"settings\":{\"show_excerpt\":true,\"show_image\":true,\"alignment\":\"start\",\"variant\":\"modern-split\",\"primary_action\":{\"label\":\"\\u0634\\u0631\\u0648\\u0639 \\u0647\\u0645\\u06a9\\u0627\\u0631\\u06cc\",\"action\":{\"schema_version\":1,\"type\":\"custom_url\",\"value\":\"#\",\"open_in_new_tab\":false}},\"secondary_action\":{\"label\":\"\\u0645\\u0634\\u0627\\u0648\\u0631\\u0647 \\u0648 \\u06af\\u0641\\u062a\\u06af\\u0648\",\"action\":{\"schema_version\":1,\"type\":\"custom_url\",\"value\":\"#\",\"open_in_new_tab\":false}},\"heading_tag\":\"h1\"}}},{\"type\":\"service_overview\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGT\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u0645\\u0639\\u0631\\u0641\\u06cc \\u062e\\u062f\\u0645\\u062a\"},\"settings\":{\"width\":\"default\",\"variant\":\"professional\",\"heading_tag\":\"h2\"}}},{\"type\":\"service_benefits\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGV\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u0645\\u0632\\u0627\\u06cc\\u0627\\u06cc \\u0627\\u06cc\\u0646 \\u062e\\u062f\\u0645\\u062a\"},\"settings\":{\"columns\":3,\"show_icons\":true,\"variant\":\"icon-cards\",\"heading_tag\":\"h2\"}}},{\"type\":\"service_process\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGW\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u0641\\u0631\\u0622\\u06cc\\u0646\\u062f \\u0627\\u062c\\u0631\\u0627\\u06cc \\u062e\\u062f\\u0645\\u062a\"},\"settings\":{\"layout\":\"horizontal\",\"show_steps\":true,\"variant\":\"connected-steps\",\"heading_tag\":\"h2\"}}},{\"type\":\"service_deliverables\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGX\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u062e\\u0631\\u0648\\u062c\\u06cc\\u200c\\u0647\\u0627 \\u0648 \\u0627\\u0642\\u0644\\u0627\\u0645 \\u062a\\u062d\\u0648\\u06cc\\u0644\\u06cc\"},\"settings\":{\"style\":\"cards\",\"columns\":3,\"variant\":\"compact-grid\",\"heading_tag\":\"h2\"}}},{\"type\":\"service_projects\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGY\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u067e\\u0631\\u0648\\u0698\\u0647\\u200c\\u0647\\u0627\\u06cc \\u0645\\u0631\\u062a\\u0628\\u0637\"},\"settings\":{\"columns\":3,\"variant\":\"visual-cards\",\"heading_tag\":\"h2\"}}},{\"type\":\"service_gallery\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPGZ\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u06af\\u0627\\u0644\\u0631\\u06cc \\u062a\\u0635\\u0627\\u0648\\u06cc\\u0631\"},\"settings\":{\"columns\":3,\"lightbox\":true,\"variant\":\"horizontal-gallery\",\"heading_tag\":\"h2\"}}},{\"type\":\"related_services\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPH0\",\"schema_version\":1,\"template\":\"default\",\"content\":{\"title\":\"\\u062e\\u062f\\u0645\\u0627\\u062a \\u0645\\u0631\\u062a\\u0628\\u0637\"},\"settings\":{\"columns\":3,\"heading_tag\":\"h2\"}}},{\"type\":\"cta\",\"data\":{\"block_id\":\"01M00C7XVMBMBYJSH241VPVPH1\",\"schema_version\":2,\"template\":\"classic\",\"content\":{\"eyebrow\":null,\"title\":null,\"description\":null,\"primary_cta\":{\"label\":null,\"action\":null},\"secondary_cta\":{\"label\":null,\"action\":null},\"media\":{\"url\":null}},\"settings\":{\"heading_tag\":\"h2\",\"alignment\":\"center\",\"background\":\"dark\",\"content_width\":null,\"media\":{\"desktop\":{\"width\":{\"value\":null,\"unit\":null},\"height\":{\"value\":null,\"unit\":null},\"fit\":\"normal\"},\"mobile\":{\"width\":{\"value\":null,\"unit\":null},\"height\":{\"value\":null,\"unit\":null},\"fit\":\"normal\"}}}}}]',0,1,'{\"type\":\"all\"}','2026-08-14 14:53:58','2026-08-15 17:46:02');
/*!40000 ALTER TABLE `templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_mobile_unique` (`mobile`),
  KEY `users_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin',NULL,'admin@example.com',NULL,'$2y$12$AxPmX8mk/n1QvXMiBGs3Ru/bW5xf7MN5WpucljCdIp3xKQkvOQPUq',1,'active',NULL,'2026-08-14 14:12:24','2026-08-14 14:12:24'),(2,'Amer','09137132241','m@gmail.com',NULL,'$2y$12$1uv8MkMJuz3.QOCiWBuxJ.wkJDF6r1Zw5YAfGYgHE5F19quos1yc6',0,'active',NULL,'2026-08-14 14:29:02','2026-08-14 14:29:02');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'noor'
--

--
-- Dumping routines for database 'noor'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-16  7:15:03
