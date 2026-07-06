-- MySQL dump 10.19  Distrib 10.3.39-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: tempus_db
-- ------------------------------------------------------
-- Server version	10.3.39-MariaDB

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
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `page_id` int(10) unsigned DEFAULT NULL,
  `label` varchar(150) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `menu_group` varchar(100) NOT NULL DEFAULT 'main',
  `target` varchar(20) NOT NULL DEFAULT '_self',
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_items_parent` (`parent_id`),
  KEY `idx_menu_items_page` (`page_id`),
  KEY `idx_menu_items_group_visible_sort` (`menu_group`,`is_visible`,`sort_order`),
  CONSTRAINT `fk_menu_items_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,NULL,5,'Dashboard',NULL,'main','_self',10,1,0,'2026-04-17 08:44:22','2026-04-30 07:59:49'),(2,NULL,NULL,'Ustawienia','internal:container:ustawienia','main','_self',100,1,0,'2026-04-17 08:44:22','2026-04-30 07:59:59'),(4,2,6,'Użytkownicy',NULL,'main','_self',10,1,0,'2026-04-17 08:44:22','2026-04-30 08:03:35'),(5,2,7,'Role',NULL,'main','_self',20,1,0,'2026-04-17 08:44:22','2026-04-30 08:04:04'),(6,2,8,'Uprawnienia',NULL,'main','_self',30,1,0,'2026-04-17 08:44:22','2026-04-30 08:03:54'),(7,2,9,'Menu',NULL,'main','_self',40,1,0,'2026-04-17 08:44:22','2026-04-30 08:03:46'),(9,2,NULL,'phpMyAdmin','http://patrol/pma/index.php','main','_blank',50,1,0,'2026-04-17 11:23:49','2026-04-17 11:23:49'),(10,2,NULL,'Separator','internal:separator','main','_self',35,1,0,'2026-04-20 09:51:50','2026-04-20 09:52:25'),(11,2,NULL,'Separator','internal:separator','main','_self',45,1,0,'2026-04-20 09:52:54','2026-04-20 09:52:54'),(12,2,10,'Strony',NULL,'main','_self',39,1,0,'2026-04-20 11:35:21','2026-04-20 11:37:07'),(13,20,11,'MSSqlView',NULL,'main','_self',15,1,0,'2026-04-20 11:42:31','2026-04-30 07:42:06'),(14,20,12,'Czas pracy',NULL,'main','_self',30,1,0,'2026-04-21 07:08:16','2026-04-30 07:42:22'),(15,19,13,'Punktualnik',NULL,'main','_self',40,1,0,'2026-04-24 06:14:35','2026-04-30 07:44:28'),(16,19,16,'Monitor',NULL,'main','_self',50,1,0,'2026-04-24 06:15:51','2026-04-30 07:46:12'),(17,20,18,'Harmonogram',NULL,'main','_self',35,1,0,'2026-04-29 06:36:25','2026-04-30 07:42:30'),(18,20,19,'RCP',NULL,'main','_self',36,1,0,'2026-04-29 08:47:09','2026-04-30 07:43:48'),(19,NULL,NULL,'Kontrola dostępu','internal:container:kontrola_dost__pu','main','_self',30,1,0,'2026-04-30 07:40:27','2026-04-30 07:41:51'),(20,NULL,NULL,'Ewidencja Czasu Pracy','internal:container:ewidencja_czasu_pracy','main','_self',20,1,0,'2026-04-30 07:41:40','2026-04-30 07:41:40'),(21,20,21,'RCP harmonogram',NULL,'main','_self',50,1,0,'2026-05-04 11:57:59','2026-05-04 11:57:59'),(22,20,22,'Widoki',NULL,'main','_self',60,1,0,'2026-05-04 12:15:46','2026-05-04 12:15:46'),(23,19,25,'Kołowroty',NULL,'main','_self',60,1,0,'2026-05-15 07:50:12','2026-05-15 07:52:02'),(24,20,27,'Lista obecnych',NULL,'main','_self',40,1,0,'2026-05-27 09:15:09','2026-05-27 09:15:09');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_item_permissions`
--

DROP TABLE IF EXISTS `menu_item_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_item_permissions` (
  `menu_item_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`menu_item_id`,`permission_id`),
  KEY `fk_menu_item_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_menu_item_permissions_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_item_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_item_permissions`
--

LOCK TABLES `menu_item_permissions` WRITE;
/*!40000 ALTER TABLE `menu_item_permissions` DISABLE KEYS */;
INSERT INTO `menu_item_permissions` VALUES (1,16),(2,25),(4,26),(5,27),(6,28),(7,30),(9,31),(10,25),(11,25),(12,29),(13,18),(14,19),(15,23),(16,24),(17,20),(18,21),(19,22),(20,17),(21,62),(22,65),(23,95),(24,100);
/*!40000 ALTER TABLE `menu_item_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `title` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_pages_is_public` (`is_public`),
  KEY `idx_pages_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'home','Strona główna','pages/home.php',1,0,1,'2026-04-17 08:44:22','2026-04-30 08:11:33'),(2,'login','Logowanie','pages/login.php',1,0,1,'2026-04-17 08:44:22','2026-04-30 08:11:26'),(3,'logout','Wylogowanie','pages/logout.php',0,0,1,'2026-04-17 08:44:22','2026-04-30 08:11:02'),(4,'forbidden','Brak dostępu','pages/forbidden.php',1,0,1,'2026-04-17 08:44:22','2026-04-30 08:10:50'),(5,'dashboard','Dashboard','pages/dashboard.php',0,0,1,'2026-04-17 08:44:22','2026-04-30 08:10:41'),(6,'users','Użytkownicy','pages/users.php',0,0,1,'2026-04-17 08:44:22','2026-04-30 08:10:29'),(7,'roles','Role','pages/roles.php',0,0,1,'2026-04-17 08:44:22','2026-04-30 08:10:21'),(8,'permissions','Uprawnienia','pages/permissions.php',0,0,1,'2026-04-17 08:44:22','2026-04-30 08:10:13'),(9,'menu_manager','Menedżer menu','pages/menu_manager.php',0,0,1,'2026-04-17 08:44:22','2026-04-30 08:10:00'),(10,'pages_manager','Zarządzanie stronami','pages/pages_manager.php',0,0,1,'2026-04-20 11:35:21','2026-04-20 11:35:21'),(11,'mssql_view','MSSQL View','pages/mssql_view.php',0,0,1,'2026-04-20 11:41:33','2026-04-20 11:41:33'),(12,'czas_pracy','Czas pracy','pages/czas_pracy.php',0,0,1,'2026-04-21 07:07:31','2026-04-21 07:09:19'),(13,'punktualnik','Punktualnik','pages/punktualnik.php',0,0,1,'2026-04-23 08:27:11','2026-04-23 08:27:11'),(14,'photo','Photo','pages/photo.php',0,0,1,'2026-04-23 10:07:38','2026-04-23 10:07:38'),(16,'monitor','Monitor','pages/monitor.php',0,0,1,'2026-04-24 06:15:05','2026-04-24 06:15:05'),(17,'monitor_data','Monitor Data','pages/monitor_data.php',0,0,1,'2026-04-24 06:46:34','2026-04-24 06:46:34'),(18,'harmonogram','Harmonogramy','pages/harmonogram.php',0,0,1,'2026-04-29 06:35:34','2026-04-29 06:38:34'),(19,'rcp','Rozliczenie czasu pracy','pages/rcp.php',0,0,1,'2026-04-29 08:46:38','2026-04-29 08:46:38'),(20,'rcp_export','RCP export XLSX','pages/rcp_export.php',0,0,1,'2026-04-29 12:53:34','2026-04-29 12:53:34'),(21,'rcp_harmonogram','RCP harmonogramy','pages/rcp_harmonogram.php',0,0,1,'2026-05-04 11:56:01','2026-05-04 11:56:01'),(22,'widoki','Widoki','pages/widoki.php',0,0,1,'2026-05-04 12:15:11','2026-05-04 12:15:11'),(23,'rcp_comments','RCP komentarze','pages/rcp_comments.php',0,0,1,'2026-05-05 08:58:42','2026-05-05 08:58:42'),(24,'change_password','Zmiana hasła','pages/change_password.php',0,0,1,'2026-05-13 12:02:50','2026-05-13 12:02:50'),(25,'kolowroty','Kołowroty','pages/kolowroty.php',0,0,1,'2026-05-15 07:49:21','2026-05-15 07:49:21'),(26,'kolowroty_data','Kołowroty Data','pages/kolowroty_data.php',0,0,1,'2026-05-15 07:59:10','2026-05-15 07:59:10'),(27,'obecnosc','Obecność','pages/obecnosc.php',0,0,1,'2026-05-26 12:49:51','2026-05-26 12:49:51');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `page_permissions`
--

DROP TABLE IF EXISTS `page_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_permissions` (
  `page_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`page_id`,`permission_id`),
  KEY `fk_page_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_page_permissions_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_page_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_permissions`
--

LOCK TABLES `page_permissions` WRITE;
/*!40000 ALTER TABLE `page_permissions` DISABLE KEYS */;
INSERT INTO `page_permissions` VALUES (5,32),(5,61),(6,42),(6,51),(7,43),(7,50),(8,44),(8,49),(9,46),(9,47),(10,45),(10,48),(11,34),(11,57),(12,35),(12,58),(13,39),(13,54),(16,40),(16,55),(17,40),(17,55),(18,36),(18,59),(19,37),(19,60),(20,37),(20,60),(21,63),(21,64),(22,66),(22,67),(24,93),(24,94),(25,96),(25,97),(26,96),(26,97),(27,98),(27,99);
/*!40000 ALTER TABLE `page_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (16,'menu.dashboard','','2026-04-30 07:47:54','2026-04-30 07:47:54'),(17,'menu.ewidencja_czasu_pracy','','2026-04-30 07:48:14','2026-04-30 07:48:14'),(18,'menu.mssqlview','','2026-04-30 07:48:50','2026-04-30 07:48:50'),(19,'menu.czas_pracy','','2026-04-30 07:49:02','2026-04-30 07:49:02'),(20,'menu.harmonogram','','2026-04-30 07:49:19','2026-04-30 07:49:19'),(21,'menu.rcp','','2026-04-30 07:49:30','2026-04-30 07:49:30'),(22,'menu.kontrola_dostepu','','2026-04-30 07:49:47','2026-04-30 07:49:58'),(23,'menu.punktualnik','','2026-04-30 07:50:16','2026-04-30 07:50:16'),(24,'menu.monitor','','2026-04-30 07:50:26','2026-04-30 07:50:26'),(25,'menu.ustawienia','','2026-04-30 07:50:40','2026-04-30 07:50:40'),(26,'menu.uzytkownicy','','2026-04-30 07:51:20','2026-04-30 07:51:20'),(27,'menu.role','','2026-04-30 07:51:41','2026-04-30 07:51:41'),(28,'menu.uprawnienia','','2026-04-30 07:51:52','2026-04-30 07:51:52'),(29,'menu.strony','','2026-04-30 07:52:03','2026-04-30 07:52:03'),(30,'menu.menu','','2026-04-30 07:52:12','2026-04-30 07:52:12'),(31,'menu.phpmyadmin','','2026-04-30 07:52:26','2026-04-30 07:52:26'),(32,'pages.dashboard.view','','2026-04-30 08:52:14','2026-04-30 08:52:14'),(34,'pages.mssqlview.view','','2026-04-30 08:53:36','2026-04-30 08:53:36'),(35,'pages.czas_pracy.view','','2026-04-30 08:53:52','2026-04-30 09:24:56'),(36,'pages.harmonogram.view','','2026-04-30 08:54:14','2026-04-30 08:54:14'),(37,'pages.rcp.view','','2026-04-30 08:54:28','2026-04-30 08:54:28'),(39,'pages.punktualnik.view','','2026-04-30 08:54:56','2026-04-30 08:54:56'),(40,'pages.monitor.view','','2026-04-30 08:55:06','2026-04-30 08:55:06'),(42,'pages.uzytkownicy.view','','2026-04-30 08:55:34','2026-04-30 08:55:34'),(43,'pages.role.view','','2026-04-30 08:55:45','2026-04-30 08:55:45'),(44,'pages.uprawnienia.view','','2026-04-30 08:55:57','2026-04-30 08:55:57'),(45,'pages.strony.view','','2026-04-30 08:56:08','2026-04-30 08:56:08'),(46,'pages.menu.view','','2026-04-30 08:56:19','2026-04-30 08:56:19'),(47,'pages.menu.edit','','2026-04-30 08:56:39','2026-04-30 08:56:39'),(48,'pages.strony.edit','','2026-04-30 08:56:51','2026-04-30 08:56:51'),(49,'pages.uprawnienia.edit','','2026-04-30 08:57:05','2026-04-30 08:57:05'),(50,'pages.role.edit','','2026-04-30 08:57:15','2026-04-30 08:57:15'),(51,'pages.uzytkownicy.edit','','2026-04-30 08:57:27','2026-04-30 08:57:27'),(54,'pages.punktualnik.edit','','2026-04-30 08:58:07','2026-04-30 08:58:07'),(55,'pages.monitor.edit','','2026-04-30 08:58:17','2026-04-30 08:58:17'),(57,'pages.mssqlview.edit','','2026-04-30 08:58:54','2026-04-30 08:58:54'),(58,'pages.czas_pracy.edit','','2026-04-30 08:59:09','2026-04-30 08:59:09'),(59,'pages.harmonogram.edit','','2026-04-30 08:59:21','2026-04-30 08:59:21'),(60,'pages.rcp.edit','','2026-04-30 08:59:32','2026-04-30 08:59:32'),(61,'pages.dashboard.edit','','2026-04-30 08:59:44','2026-04-30 08:59:44'),(62,'menu.rcp_harmonogram','','2026-05-04 11:56:30','2026-05-04 11:56:30'),(63,'pages.rcp_harmonogram.view','','2026-05-04 11:56:47','2026-05-04 11:56:47'),(64,'pages.rcp_harmonogram.edit','','2026-05-04 11:56:58','2026-05-04 11:56:58'),(65,'menu.widoki','','2026-05-04 12:13:43','2026-05-04 12:13:43'),(66,'pages.widoki.view','','2026-05-04 12:14:04','2026-05-04 12:14:04'),(67,'pages.widoki.edit','','2026-05-04 12:14:22','2026-05-04 12:14:22'),(68,'rcp_zmiana.1','Zmiana A','2026-05-05 06:15:29','2026-05-05 06:15:29'),(69,'rcp_zmiana.2','Zmiana B','2026-05-05 04:15:29','2026-05-05 04:15:29'),(70,'rcp_zmiana.3','Zmiana C','2026-05-05 04:15:29','2026-05-05 04:15:29'),(71,'rcp_zmiana.4','Zmiana D','2026-05-05 04:15:29','2026-05-05 04:15:29'),(72,'rcp_zmiana.5','Zmiana E','2026-05-05 04:15:29','2026-05-05 04:15:29'),(74,'rcp_zmiana.7','Zmiana M','2026-05-05 04:15:29','2026-05-05 04:15:29'),(75,'rcp_zmiana.8','Zmiana W','2026-05-05 04:15:29','2026-05-05 04:15:29'),(76,'rcp_zmiana.9','Zmiana DZ','2026-05-05 04:15:29','2026-05-05 04:15:29'),(77,'rcp_zmiana.10','Zmiana DRP','2026-05-05 04:15:29','2026-05-05 04:15:29'),(78,'rcp_zmiana.11','Zmiana DFIN','2026-05-05 04:15:29','2026-05-05 04:15:29'),(79,'rcp_zmiana.12','Zmiana DKJ','2026-05-05 04:15:29','2026-05-05 04:15:29'),(80,'rcp_zmiana.13','Zmiana DZK','2026-05-05 04:15:29','2026-05-05 04:15:29'),(81,'rcp_zmiana.14','Zmiana DSP','2026-05-05 04:15:29','2026-05-05 04:15:29'),(82,'rcp_zmiana.15','Zmiana PA','2026-05-05 04:15:29','2026-05-05 04:15:29'),(83,'rcp_zmiana.16','Zmiana PB','2026-05-05 04:15:29','2026-05-05 04:15:29'),(84,'rcp_zmiana.17','Zmiana PC','2026-05-05 04:15:29','2026-05-05 04:15:29'),(85,'rcp_zmiana.18','Zmiana DGK','2026-05-05 04:15:29','2026-05-05 04:15:29'),(86,'rcp_zmiana.19','Zmiana DON','2026-05-05 04:15:29','2026-05-05 04:15:29'),(87,'rcp_zmiana.20','Zmiana DPN','2026-05-05 04:15:29','2026-05-05 04:15:29'),(88,'rcp_zmiana.21','Zmiana DL','2026-05-05 04:15:29','2026-05-05 04:15:29'),(89,'rcp_zmiana.22','Zmiana Umowa zlecenie','2026-05-05 04:15:29','2026-05-05 04:15:29'),(90,'pages.rcp_comments.view','','2026-05-05 08:59:08','2026-05-05 08:59:08'),(91,'pages.rcp_comments.edit','','2026-05-05 08:59:27','2026-05-05 08:59:27'),(92,'menu.change_password','','2026-05-13 12:01:15','2026-05-13 12:01:15'),(93,'pages.change_password.view','','2026-05-13 12:01:30','2026-05-13 12:01:30'),(94,'pages.change_password.edit','','2026-05-13 12:01:45','2026-05-13 12:01:45'),(95,'menu.kolowroty','','2026-05-15 07:47:31','2026-05-15 07:47:31'),(96,'pages.kolowroty.view','','2026-05-15 07:47:48','2026-05-15 07:47:48'),(97,'pages.kolowroty.edit','','2026-05-15 07:48:04','2026-05-15 07:50:30'),(98,'pages.obecnosc.view','','2026-05-27 09:15:52','2026-05-27 09:15:52'),(99,'pages.obecnosc.edit','','2026-05-27 09:16:02','2026-05-27 09:16:02'),(100,'menu.obecnosc','','2026-05-27 09:16:14','2026-05-27 09:16:14');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','Pełny dostęp do systemu','2026-04-17 08:44:21','2026-04-17 08:44:21'),(2,'Użytkownik','Podstawowy dostęp do systemu','2026-04-17 08:44:21','2026-04-17 08:44:21'),(3,'Ochrona','','2026-04-27 12:02:06','2026-04-27 12:02:06'),(4,'RCP: A','Nadzór zmiany A','2026-05-05 07:02:06','2026-05-05 07:02:06'),(5,'RCP: B','Nadzór zmiany B','2026-05-05 07:02:06','2026-05-05 07:02:06'),(6,'RCP: C','Nadzór zmiany C','2026-05-05 07:02:06','2026-05-05 07:02:06'),(7,'RCP: D','Nadzór zmiany D','2026-05-05 07:02:06','2026-05-05 07:02:06'),(8,'RCP: E','Nadzór zmiany E','2026-05-05 07:02:06','2026-05-05 07:02:06'),(9,'RCP: M','Nadzór zmiany M','2026-05-05 07:02:06','2026-05-05 07:02:06'),(10,'RCP: W','Nadzór zmiany W','2026-05-05 07:02:06','2026-05-05 07:02:06'),(11,'RCP: DZ','Nadzór DZ','2026-05-05 07:02:06','2026-05-05 07:02:06'),(12,'RCP: DRP','Nadzór DRP','2026-05-05 07:02:06','2026-05-05 07:02:06'),(13,'RCP: DFIN','Nadzór DFIN','2026-05-05 07:02:06','2026-05-05 07:02:06'),(14,'RCP: DKJ','Nadzór DKJ','2026-05-05 07:02:06','2026-05-05 07:02:06'),(15,'RCP: DZK','Nadzór DZK','2026-05-05 07:02:06','2026-05-05 07:02:06'),(16,'RCP: DSP','Nadzór DSP','2026-05-05 07:02:06','2026-05-05 07:02:06'),(17,'RCP: PA','Nadzór PA','2026-05-05 07:02:06','2026-05-05 07:02:06'),(18,'RCP: PB','Nadzór PB','2026-05-05 07:02:06','2026-05-05 07:02:06'),(19,'RCP: PC','Nadzór PC','2026-05-05 07:02:06','2026-05-05 07:02:06'),(20,'RCP: DGK','Nadzór DGK','2026-05-05 07:02:06','2026-05-05 07:02:06'),(21,'RCP: DON','Nadzór DON','2026-05-05 07:02:06','2026-05-05 07:02:06'),(22,'RCP: DPN','Nadzór DPN','2026-05-05 07:02:06','2026-05-05 07:02:06'),(23,'RCP: DL','Nadzór DL','2026-05-05 07:02:06','2026-05-05 07:02:06'),(24,'RCP: U','Nadzór Umowa Zlecenie','2026-05-05 07:02:06','2026-05-05 07:02:06'),(25,'Zarząd','','2026-05-27 09:18:33','2026-05-27 09:18:33');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,18),(1,19),(1,21),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,34),(1,35),(1,36),(1,37),(1,39),(1,40),(1,42),(1,43),(1,44),(1,45),(1,46),(1,47),(1,48),(1,49),(1,50),(1,51),(1,54),(1,55),(1,57),(1,58),(1,59),(1,60),(1,61),(1,62),(1,63),(1,64),(1,65),(1,66),(1,67),(1,90),(1,91),(1,92),(1,93),(1,94),(1,96),(1,97),(1,98),(1,99),(1,100),(2,16),(2,17),(2,19),(2,21),(2,32),(2,37),(2,60),(2,61),(2,92),(2,93),(2,94),(3,16),(3,22),(3,23),(3,24),(3,32),(3,39),(3,40),(4,68),(5,69),(6,70),(7,71),(8,72),(9,74),(10,75),(11,76),(12,77),(13,78),(14,79),(15,80),(16,81),(17,82),(18,83),(19,84),(20,85),(21,86),(22,87),(23,88),(24,89),(25,98),(25,99),(25,100);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@example.com','$2y$10$KWdaLoskAuz5WpcIc2v1dOyuF.M8tem/ZcatPa1IHIcbJKpcAl6UO','System','Administrator',1,'2026-07-03 07:58:38','2026-04-17 08:44:21','2026-07-03 05:58:38'),(2,'big798','dariusz.szulhaniuk@meblomaster.pl','$2y$10$YHoyjGZQ2uwPrlq5Thev2uDnv6fxfip0Nj3prKQlhgrkFTUFmQJeK','Dariusz','Szulhaniuk',1,'2026-06-08 07:18:49','2026-04-17 11:25:08','2026-06-08 05:18:49'),(3,'ochrona','ochrona@meblomaster.pl','$2y$10$Isiz4rZVBu2U3J0jxSA0zORsMzBiaSI3xxANKwyyG2f92rS7elwW.','Ochrona','Meblomaster',1,'2026-07-02 07:15:05','2026-04-27 12:01:43','2026-07-02 05:15:05'),(4,'adam.kraszewski','adam.kraszewski@meblomaster.pl','$2y$10$diVFIK6kZn/NcboOYnPtre450QVm7aTp1iQN5WCLAyFhh2YVllVFi','Adam','Kraszewski',1,'2026-05-27 11:13:35','2026-05-14 07:45:04','2026-05-27 09:13:35'),(5,'andrzej.bieniak','andrzej.bieniak@meblomaster.pl','$2y$10$7n.S02wf5WI609T9E7S7eeX3WPG5JRf7jedbykrW2Pl5iWzxHRW7G','Andrzej','Bieniak',1,'2026-06-25 08:49:24','2026-05-14 07:45:04','2026-06-25 06:49:24'),(6,'artur.gryglas','artur.gryglas@meblomaster.pl','$2y$10$WdTwkfcAI9lnovJEgmMBp.nv.viGSNfOfkhuT.HqTee7.giyOA3qK','Artur','Gryglas',1,'2026-05-27 11:28:01','2026-05-14 07:45:04','2026-05-27 09:28:01'),(7,'artur.swiesciak','artur.swiesciak@meblomaster.pl','$2y$10$Vr.M.x2HwPKR7cWSMxOIreWdjDJOIxJzRbnJHf9bgww2mtQusoao2','Artur','Świeściak',1,'2026-05-29 08:36:30','2026-05-14 07:45:04','2026-05-29 06:36:30'),(8,'jacek.sasin','jacek.sasin@meblomaster.pl','$2y$10$PsqnlaGY7HXQgiCe4ys.re5/bt61jDY0cV9QX9cKDCJfXFapT8lv6','Jacek','Sasin',1,'2026-06-30 07:34:51','2026-05-14 07:45:04','2026-06-30 05:34:51'),(9,'joanna.maksymowicz','joanna.maksymowicz@meblomaster.pl','$2y$10$qFgQPelV44bnd3qDbDc50OX/ktwgq6tVRjsZASI.1Q.IbuXVSYJzi','Joanna','Maksymowicz',1,'2026-07-03 10:29:04','2026-05-14 07:45:04','2026-07-03 08:29:04'),(10,'kamil.golos','kamil.golos@meblomaster.pl','$2y$10$UTTJg2obtfhFdFrcvdtCy.r3/wOry4f6Ilo7iVDtFoL6re4K47fKK','Kamil','Gołoś',1,'2026-07-02 08:34:25','2026-05-14 07:45:04','2026-07-02 06:34:25'),(11,'kamila.kalita','kamila.kalita@meblomaster.pl','$2y$10$invVu9uPvBjfvlXn9qOhbOXdj0mEFCsXs98tAeIKon.EkQu69x2sK','Kamila','Kalita',1,'2026-07-02 10:46:52','2026-05-14 07:45:04','2026-07-02 08:46:52'),(12,'krzysztof.gibek','krzysztof.gibek@meblomaster.pl','$2y$10$oy3PHClqccbEIDRTWzaVlOO2xIKj3b1R3UcfOzYqSGHyn6C4XpDn.','Krzysztof','Gibek',1,'2026-05-28 08:27:01','2026-05-14 07:45:04','2026-05-28 06:27:01'),(13,'lukasz.filipek','lukasz.filipek@meblomaster.pl','$2y$10$nB05j3OGpYlzjEh9p61s6..2EOCl9tZQej5cSAjNySMGtfLumI1bi','Łukasz','Filipek',1,'2026-07-01 09:13:09','2026-05-14 07:45:04','2026-07-01 07:13:09'),(14,'lukasz.siwek','lukasz.siwek@meblomaster.pl','$2y$10$lZgWiw08WaYVD2TqX2Nn5.s5JK6PsL/SEPnZOiFvNRIwC.8lDmrRu','Łukasz','Siwek',1,'2026-05-26 15:25:30','2026-05-14 07:45:04','2026-05-26 13:25:30'),(15,'marcin.sierota','marcin.sierota@meblomaster.pl','$2y$10$JRnTLKjh1qgBjlwIdWEIiuSjitvcX.NR2epj2Mk1yaxXwqzwLlYVW','Marcin','Sierota',1,'2026-06-16 14:09:16','2026-05-14 07:45:04','2026-06-16 12:09:16'),(16,'marcin.stepien','marcin.stepien@meblomaster.pl','$2y$10$JAsQwKyzspDCM7OAAePrZeRddjrURMIj87gHpocH8e.3RJGo74AuC','Marcin','Stępień',1,'2026-05-14 11:45:34','2026-05-14 07:45:04','2026-05-14 06:13:20'),(17,'pawel.pabian','pawel.pabian@meblomaster.pl','$2y$10$FHsCbYQ.stlwhF/1poWCyeGsdY6W00m.aHavrdm1bFxxANZiAFOyK','Paweł','Pabian',1,'2026-07-02 13:52:05','2026-05-14 07:45:04','2026-07-02 11:52:05'),(18,'sambor.waszkiewicz','sambor.waszkiewicz@meblomaster.pl','$2y$10$2DvoH4hsh4lnX5pxI4g4au2GYA6g8QURU83MgUOoDODDJvPN/Avr6','Sambor','Waszkiewicz',1,'2026-05-26 09:57:24','2026-05-14 07:45:04','2026-05-26 07:57:24'),(19,'teresa.sadowska','teresa.sadowska@meblomaster.pl','$2y$10$qVaMcHM5s1jlWxnOk1IFmutimjBOUR5tsXVVeuduLHduRe.uLhdaG','Teresa','Sadowska',1,'2026-07-03 08:08:37','2026-05-14 07:45:04','2026-07-03 06:08:37'),(20,'urszula.szczech','urszula.szczech@meblomaster.pl','$2y$10$RV1XLQoP4bfsjxUG2qd82eS5IAwwRFRqvIH5UTszFyIjCGzCcM5ri','Urszula','Szczęch',1,'2026-05-14 11:45:34','2026-05-14 07:45:04','2026-05-14 06:14:18'),(22,'marzena.maka','marzena.maka@meblomaster.pl','$2y$10$ibbKJyy2pHeQ1honxJoXWOR7FWyYDxvij5MtQ8NdlR57rXLt21Mt.','Marzena','Mąka',1,'2026-07-01 14:22:46','2026-06-11 12:43:33','2026-07-01 12:22:46');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(2,1),(3,3),(4,2),(4,4),(4,5),(4,6),(4,7),(4,8),(4,9),(4,10),(4,11),(4,12),(4,13),(4,14),(4,15),(4,16),(4,17),(4,18),(4,19),(4,20),(4,21),(4,22),(4,23),(4,24),(4,25),(5,2),(5,12),(6,2),(6,4),(6,5),(6,6),(6,7),(6,8),(6,9),(6,10),(6,11),(6,12),(6,13),(6,14),(6,15),(6,16),(6,17),(6,18),(6,19),(6,20),(6,21),(6,22),(6,23),(6,24),(6,25),(7,2),(7,4),(7,5),(7,6),(7,8),(7,17),(7,18),(7,19),(7,22),(7,24),(8,2),(8,12),(9,2),(9,4),(9,5),(9,6),(9,7),(9,8),(9,9),(9,10),(9,11),(9,12),(9,13),(9,14),(9,15),(9,16),(9,17),(9,18),(9,19),(9,20),(9,21),(9,22),(9,23),(9,24),(10,2),(10,17),(10,18),(10,19),(10,24),(11,2),(11,14),(12,2),(12,10),(13,2),(13,8),(14,2),(14,15),(14,21),(15,2),(15,9),(16,2),(16,10),(17,2),(17,4),(17,5),(17,6),(17,8),(17,17),(17,18),(17,19),(17,22),(17,24),(18,2),(18,4),(18,5),(18,6),(18,7),(18,8),(18,9),(18,10),(18,11),(18,12),(18,13),(18,14),(18,15),(18,16),(18,17),(18,18),(18,19),(18,20),(18,21),(18,22),(18,23),(18,24),(18,25),(19,2),(19,4),(19,5),(19,6),(19,7),(19,8),(19,9),(19,10),(19,11),(19,12),(19,13),(19,14),(19,15),(19,16),(19,17),(19,18),(19,19),(19,20),(19,21),(19,22),(19,23),(19,24),(20,2),(20,20),(22,2),(22,23);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-03 12:21:22
