-- Vellisys Hostinger import
-- In phpMyAdmin: select database u454222977_Vell, then Import this file.
-- There is no CREATE DATABASE statement. Import into the selected database only.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: folio
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

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
-- Table structure for table `branding`
--

DROP TABLE IF EXISTS `branding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branding` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `tagline` varchar(180) DEFAULT '',
  `tin` varchar(40) DEFAULT '',
  `vat_no` varchar(40) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `city` varchar(120) DEFAULT '',
  `phone` varchar(40) DEFAULT '',
  `email` varchar(190) DEFAULT '',
  `website` varchar(190) DEFAULT '',
  `bank_name` varchar(120) DEFAULT '',
  `account_name` varchar(160) DEFAULT '',
  `account_number` varchar(80) DEFAULT '',
  `brand_color` varchar(7) NOT NULL DEFAULT '#82B440',
  `logo_path` varchar(255) DEFAULT 'assets/img/ofagros-logo.png',
  `prefix` varchar(12) NOT NULL DEFAULT 'OFG',
  `payment_note` text DEFAULT NULL,
  `invoice_comments` text DEFAULT NULL,
  `plan` enum('starter','sme','office') NOT NULL DEFAULT 'sme',
  `company_id` int(10) unsigned DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'UGX',
  `letter_templates` text DEFAULT NULL,
  `doc_template` varchar(40) NOT NULL DEFAULT 'folio',
  `brand_accent` varchar(7) NOT NULL DEFAULT '#C6A15B',
  `brand_deep` varchar(7) NOT NULL DEFAULT '#1F3A12',
  `fx_ugx_per_usd` decimal(12,4) NOT NULL DEFAULT 3700.0000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branding`
--

LOCK TABLES `branding` WRITE;
/*!40000 ALTER TABLE `branding` DISABLE KEYS */;
INSERT INTO `branding` VALUES
(1,'Ofagros Limited','Solutions for agriculture','1000890123','1000890123','Kampala, Central Region, Uganda','Kampala, Uganda','+256 788 141 342','ofagrosltd@gmail.com','www.ofagros.org','Stanbic Bank Uganda','Ofagros Limited','9030008844211','#C92CA6','uploads/logos/logo-20260909161722.png','OFG','Make payment to Ofagros Limited, Kampala.','1. Payment is due by the date shown above.\r\n2. Pay through the Ofagros client portal.\r\n3. Farm work starts after this invoice is marked paid.','starter',1,'UGX','{\"demand\":{\"title\":\"Demand for payment (desk)\",\"heading\":\"DEMAND FOR PAYMENT\",\"subject\":\"Demand for payment\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nWe write in respect of amounts that remain unpaid on your account with {company}. Kindly settle the balance within seven (7) days of this note.\\r\\n\\r\\nIf payment has already been made, please send the reference so we may update our books.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\\r\\n{company}\"},\"covering\":{\"title\":\"Covering note\",\"heading\":\"COVERING NOTE\",\"subject\":\"Documents enclosed\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nPlease find enclosed the documents listed below. Kindly acknowledge receipt.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\\r\\n{company}\"},\"appointment\":{\"title\":\"Appointment\",\"heading\":\"APPOINTMENT\",\"subject\":\"Confirmation of appointment\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nThis confirms our appointment as agreed. Please let us know if the date or time needs to change.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\\r\\n{company}\"},\"credit\":{\"title\":\"Credit and goodwill\",\"heading\":\"CREDIT NOTE\",\"subject\":\"Credit on your account\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nWe have credited your account as a gesture of goodwill / in correction of the items discussed. The credit will appear on your next statement.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\\r\\n{company}\"},\"notice\":{\"title\":\"Overdue notice\",\"heading\":\"OVERDUE NOTICE\",\"subject\":\"Account overdue\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nYour account with {company} is now overdue. Please arrange payment at once to avoid interruption of supply.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\\r\\n{company}\"},\"thanks\":{\"title\":\"Thank you note\",\"heading\":\"THANK YOU\",\"subject\":\"Thank you\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nThank you for your business with {company}.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\\r\\n{company}\"},\"c1788957916237\":{\"title\":\"Site visit note\",\"heading\":\"SITE VISIT\",\"subject\":\"Site visit\",\"body\":\"Dear Sir / Madam,\\r\\n\\r\\nWe visited {company} today.\\r\\n\\r\\nYours faithfully,\\r\\nAccounts\"}}','night','#3CD733','#000040',3700.0000),
(3,'Harbour Craft Ltd','','','','','','','mira.harbour.craft.test@example.com','','','Harbour Craft Ltd','','#1E4EFF','','HAR','Make payment to Harbour Craft Ltd.','1. Payment is due by the date shown above.\n2. Quote the invoice number on the transfer.','sme',3,'USD',NULL,'folio','#C6A15B','#08143A',3700.0000);
/*!40000 ALTER TABLE `branding` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `status` enum('onboarding','live','suspended') NOT NULL DEFAULT 'onboarding',
  `plan` enum('starter','sme','office') NOT NULL DEFAULT 'sme',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_term` int(10) unsigned NOT NULL DEFAULT 0,
  `paid_unit` enum('months','years') NOT NULL DEFAULT 'months',
  `paid_from` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `renewal_notice_sent_at` datetime DEFAULT NULL,
  `mail_provider` varchar(20) NOT NULL DEFAULT 'hostinger',
  `mail_email` varchar(190) NOT NULL DEFAULT '',
  `mail_password` text DEFAULT NULL,
  `mail_from_name` varchar(160) NOT NULL DEFAULT '',
  `smtp_host` varchar(190) NOT NULL DEFAULT 'smtp.hostinger.com',
  `smtp_port` int(10) unsigned NOT NULL DEFAULT 465,
  `smtp_secure` varchar(10) NOT NULL DEFAULT 'ssl',
  `pop_host` varchar(190) NOT NULL DEFAULT 'pop.hostinger.com',
  `pop_port` int(10) unsigned NOT NULL DEFAULT 995,
  `imap_host` varchar(190) NOT NULL DEFAULT 'imap.hostinger.com',
  `imap_port` int(10) unsigned NOT NULL DEFAULT 993,
  `fee_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fee_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fee_currency` char(3) NOT NULL DEFAULT 'UGX',
  `enabled_kinds` text DEFAULT NULL,
  `custom_doc` text DEFAULT NULL,
  `user_limit` tinyint(3) unsigned NOT NULL DEFAULT 3,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES
(1,'Ofagros Limited','live','sme',NULL,'2026-09-09 11:45:55',1,'months','2026-09-10','2026-10-10',NULL,'hostinger','',NULL,'','smtp.hostinger.com',465,'ssl','pop.hostinger.com',995,'imap.hostinger.com',993,450000.00,450000.00,'UGX','[\"quotation\",\"invoice\",\"receipt\",\"letter\"]',NULL,3),
(3,'Harbour Craft Ltd','live','sme',NULL,'2026-09-10 12:07:08',0,'months',NULL,NULL,NULL,'hostinger','',NULL,'','smtp.hostinger.com',465,'ssl','pop.hostinger.com',995,'imap.hostinger.com',993,0.00,0.00,'UGX','[\"quotation\",\"invoice\",\"receipt\",\"letter\"]','{\"title\":\"Custom document\",\"has_body\":true,\"fields\":[]}',3);
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_items`
--

DROP TABLE IF EXISTS `document_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `item_name` varchar(160) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT 'lot',
  `rate` decimal(16,2) NOT NULL DEFAULT 0.00,
  `taxed` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_item_doc` (`document_id`),
  CONSTRAINT `fk_item_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_items`
--

LOCK TABLES `document_items` WRITE;
/*!40000 ALTER TABLE `document_items` DISABLE KEYS */;
INSERT INTO `document_items` VALUES
(1,1,'','Shade-grown robusta seedlings, 6 months',400.00,'pcs',4500.00,1),
(2,1,'','Farm visit and planting supervision',1.00,'lot',850000.00,1),
(3,2,'','Coffee Estate Share',1.00,'lot',12500000.00,0),
(4,3,'','Washed arabica, FAQ, 60kg bags',80.00,'bags',980000.00,1),
(5,3,'','Transport to Kampala warehouse',1.00,'trip',1200000.00,1),
(6,4,'','Breakfast blend, 1kg retail packs',240.00,'packs',28000.00,1),
(7,5,'','Pruning and rejuvenation, 12 acres',1.00,'lot',6800000.00,1),
(8,6,'','Payment on account',1.00,'lot',20000000.00,0),
(9,7,'','NPK 17:17:17, 50kg',40.00,'bags',148000.00,1),
(10,8,'','Diesel',1.00,'lot',640000.00,0),
(11,9,'','Store rent, Industrial Area, July',1.00,'month',2400000.00,0),
(12,10,'','—',1.00,'lot',0.00,0),
(13,11,'','Payment on OFG-EXP-2026-0002',1.00,'lot',600000.00,0),
(14,12,'','hf',1.00,'4',50000.00,1),
(15,12,'','sd',2.00,'5',60000.00,0),
(16,12,'','ghgh',6.00,'4',600000.00,1),
(17,12,'','nbbbn',1.00,'8',90000.00,1),
(18,13,'','Arabica lot A',1.50,'kg',120.50,1),
(19,13,'','Shade net extra row',3.00,'pcs',45.00,1),
(22,15,'','Robusta test',1.00,'lot',100.00,1),
(27,17,'','Part payment on OFG-INV-2026-0007',1.00,'lot',1930000.00,0),
(29,16,'','Test Product',1.00,'4',50000.00,1),
(30,16,'','sd',2.00,'5',60000.00,0),
(31,16,'','ghgh',6.00,'4',600000.00,1),
(32,16,'','nbbbn',1.00,'8',90000.00,1),
(35,14,'','Robusta test',2.50,'lot',80.00,1),
(36,14,'','Extra row bags',2.00,'lot',15.00,1),
(37,18,'','Part payment on OFG-INV-2026-0007',1.00,'lot',135.14,0);
/*!40000 ALTER TABLE `document_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kind` enum('quotation','invoice','receipt','expense','letter','delivery','custom') NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `number` varchar(64) NOT NULL,
  `date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `party_id` int(10) unsigned NOT NULL,
  `vat_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `notes` text DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` enum('issued','void') NOT NULL DEFAULT 'issued',
  `void_reason` varchar(255) DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `payment_method` varchar(40) DEFAULT NULL,
  `payment_ref` varchar(80) DEFAULT NULL,
  `allocated_amount` decimal(16,2) DEFAULT NULL,
  `expense_category` varchar(80) DEFAULT NULL,
  `efris_fdn` varchar(40) DEFAULT NULL,
  `efris_verification` varchar(16) DEFAULT NULL,
  `efris_payload` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(10) unsigned DEFAULT NULL,
  `letter_template` varchar(40) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'UGX',
  `doc_template` varchar(40) DEFAULT NULL,
  `custom_values` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_number` (`company_id`,`number`),
  KEY `kind_seq` (`kind`,`sequence`),
  KEY `party_id` (`party_id`),
  KEY `company_kind_date` (`company_id`,`kind`,`date`),
  CONSTRAINT `fk_doc_party` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES
(1,'quotation',1,'OFG-QTN-2026-0001','2026-07-28','2026-08-28',3,0.1800,'Prices held 30 days. Delivery to Mukono.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(2,'invoice',1,'OFG-INV-20260815-61C38E','2026-08-15','2026-08-22',1,0.0000,'1. Payment is due by the date shown above.\n2. Pay through the Ofagros client portal. Pesapal processes the payment.\n3. Farm work starts after this invoice is marked paid.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,'256202608150B51DB','0B51DBFC','{\"fdn\":\"256202608150B51DB\",\"tin\":\"1000890123\",\"invoice\":\"OFG-INV-20260815-61C38E\",\"date\":\"2026-08-15\",\"amount\":12500000,\"verification\":\"0B51DBFC\"}',1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(3,'invoice',2,'OFG-INV-2026-0002','2026-06-04','2026-06-18',2,0.1800,'Net 14 days. LPO NCT/26/441.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,'256202606044341B6','4341B632','{\"fdn\":\"256202606044341B6\",\"tin\":\"1000890123\",\"invoice\":\"OFG-INV-2026-0002\",\"date\":\"2026-06-04\",\"amount\":93928000,\"verification\":\"4341B632\"}',1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(4,'invoice',3,'OFG-INV-2026-0003','2026-08-29','2026-09-12',4,0.1800,'Breakfast blend supply, September.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,'25620260829FBDB67','FBDB676E','{\"fdn\":\"25620260829FBDB67\",\"tin\":\"1000890123\",\"invoice\":\"OFG-INV-2026-0003\",\"date\":\"2026-08-29\",\"amount\":7929600,\"verification\":\"FBDB676E\"}',1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(5,'invoice',4,'OFG-INV-2026-0004','2026-03-20','2026-04-03',3,0.1800,'Overdue. Followed up 12 May and 3 August.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,'25620260320ADEC61','ADEC6122','{\"fdn\":\"25620260320ADEC61\",\"tin\":\"1000890123\",\"invoice\":\"OFG-INV-2026-0004\",\"date\":\"2026-03-20\",\"amount\":8024000,\"verification\":\"ADEC6122\"}',1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(6,'receipt',1,'OFG-RCT-2026-0001','2026-06-20',NULL,2,0.0000,'Part payment, received with thanks.',NULL,NULL,'issued',NULL,3,'bank-transfer','STN-4412901',20000000.00,NULL,'25620260620A7CA72','A7CA727E','{\"fdn\":\"25620260620A7CA72\",\"tin\":\"1000890123\",\"invoice\":\"OFG-RCT-2026-0001\",\"date\":\"2026-06-20\",\"amount\":20000000,\"verification\":\"A7CA727E\"}',1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(7,'expense',1,'OFG-EXP-2026-0001','2026-08-02',NULL,5,0.1800,'Supplier invoice SC/26/1902',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,'Farm inputs',NULL,NULL,NULL,1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(8,'expense',2,'OFG-EXP-2026-0002','2026-08-18',NULL,6,0.0000,'Field team, Mukono run',NULL,NULL,'issued',NULL,NULL,'mobile-money',NULL,NULL,'Fuel',NULL,NULL,NULL,1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(9,'expense',3,'OFG-EXP-2026-0003','2026-07-01',NULL,5,0.0000,NULL,NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,'Rent',NULL,NULL,NULL,1,'2026-09-09 10:41:15',1,NULL,'UGX','night',NULL),
(10,'letter',1,'OFG-LTR-2026-0001','2026-09-01',NULL,2,0.0000,NULL,'Demand for the balance on OFG-INV-2026-0002','Dear Accounts,\n\nWe write in respect of our invoice for washed arabica delivered in June. We acknowledge your transfer of UGX 20,000,000 and kindly request settlement of the remaining balance within seven days.\n\nYours faithfully,\nAccounts\nOfagros Limited','issued',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 10:41:15',1,'demand','UGX','night',NULL),
(11,'receipt',2,'OFG-RCT-2026-0002','2026-09-09',NULL,6,0.0000,'Payment to supplier against OFG-EXP-2026-0002.',NULL,NULL,'issued',NULL,8,'mobile-money',NULL,600000.00,NULL,'25620260909100526','100526A2','{\"fdn\":\"25620260909100526\",\"tin\":\"1000890123\",\"invoice\":\"OFG-RCT-2026-0002\",\"date\":\"2026-09-09\",\"amount\":600000,\"verification\":\"100526A2\",\"demo\":true}',1,'2026-09-09 12:02:03',1,NULL,'UGX','night',NULL),
(12,'quotation',2,'OFG-QTN-2026-0002','2026-09-09','2026-09-30',3,0.0000,'hhjh',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 12:20:12',1,NULL,'UGX','night',NULL),
(13,'invoice',5,'OFG-INV-2026-0005','2026-09-09','2026-09-23',1,0.1800,'USD test invoice with decimal qty and extra row.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 12:35:19',1,NULL,'USD','night',NULL),
(14,'invoice',6,'OFG-INV-2026-0006','2026-09-09','2026-09-23',1,0.1800,'1. Payment is due by the date shown above.\r\n2. Pay through the Ofagros client portal.\r\n3. Farm work starts after this invoice is marked paid.',NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 12:40:25',1,NULL,'UGX','night',NULL),
(15,'quotation',3,'OFG-QTN-2026-0003','2026-09-09','2026-09-23',1,0.1800,NULL,NULL,NULL,'issued',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 13:18:52',1,NULL,'UGX','night',NULL),
(16,'invoice',7,'OFG-INV-2026-0007','2026-09-09','2026-09-23',3,0.0000,'hhjh',NULL,NULL,'issued',NULL,12,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 13:25:31',1,NULL,'UGX','night',NULL),
(17,'receipt',3,'OFG-RCT-2026-0003','2026-09-09',NULL,3,0.0000,'Part payment against OFG-INV-2026-0007. Balance remaining on the invoice.',NULL,NULL,'issued',NULL,16,'mobile-money','TEST-PART',1930000.00,NULL,NULL,NULL,NULL,1,'2026-09-09 13:25:31',1,NULL,'UGX','night',NULL),
(18,'receipt',4,'OFG-RCT-2026-0004','2026-09-09',NULL,3,0.0000,'Part payment against OFG-INV-2026-0007. Balance remaining on the invoice.',NULL,NULL,'issued',NULL,16,'bank-transfer',NULL,135.14,NULL,NULL,NULL,NULL,1,'2026-09-09 13:34:06',1,NULL,'USD','night',NULL);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `emails`
--

DROP TABLE IF EXISTS `emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `emails` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `to_email` varchar(190) NOT NULL,
  `from_email` varchar(190) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `status` enum('sent','queued','failed') NOT NULL DEFAULT 'queued',
  `error` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `emails`
--

LOCK TABLES `emails` WRITE;
/*!40000 ALTER TABLE `emails` DISABLE KEYS */;
INSERT INTO `emails` VALUES
(1,NULL,0,'info@vellisys.com','','Vellisys sign-up: Front Star','New sign-up: Front Star / Nsamba Marvin / frontstargroupofcompanies@gmail.com / +256779971024','sent','','2026-09-10 07:58:15'),
(2,NULL,3,'frontstargroupofcompanies@gmail.com','','REACH OUT','Dear FRONT STAR,\n\nHello, you reached out\n\nKind regards,\nVellisys\ninfo@vellisys.com','sent','','2026-09-10 08:06:41'),
(3,NULL,0,'frontstargroupofcompanies@gmail.com','','We have your Vellisys registration','Dear Nsamba Marvin,\n\nThank you for registering Front Star for a Vellisys desk.\n\nWe have your request. A Vellisys admin will call you to onboard the company. There is no password yet - you receive one when the desk is opened.\n\nIf you need us sooner, write to info@vellisys.com or call +256 779 971 024 or +256 756 524 451.\n\nKind regards,\nVellisys\ninfo@vellisys.com','sent','','2026-09-10 08:12:28'),
(4,NULL,0,'frontstargroupofcompanies@gmail.com','','Vellisys stationery','Dear Marvin,\n\nThis is the redesigned Vellisys letter with our logo and brand colours.\n\nKind regards,\nVellisys\ninfo@vellisys.com','sent','','2026-09-10 08:20:26'),
(5,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys question from mark oscar','A visitor asked a question on the Vellisys site.\n\nName: mark oscar\nEmail: arimpaoscarmark7@gmail.com\nPhone: +256779971024\n\nWhats the pricing please tell me\n\nOpen: http://127.0.0.1:43219/admin_questions.php','sent','','2026-09-10 09:09:03'),
(6,NULL,0,'arimpaoscarmark7@gmail.com','info@vellisys.com','We have your question - Vellisys','Dear mark oscar,\n\nThank you for writing to Vellisys. We have your question and a member of the team will reply to this email.\n\nYour note:\nWhats the pricing please tell me\n\nIf it is urgent, call +256 779 971 024 or +256 756 524 451.\n\nKind regards,\nVellisys\ninfo@vellisys.com','sent','','2026-09-10 09:09:04'),
(7,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Incomplete checkout: Lakeview Studio Ltd','Incomplete checkout: Lakeview Studio Ltd / Solo / amina.lakeview.checkout.test@example.com / 0 150000.00','sent','','2026-09-10 12:35:11'),
(8,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Incomplete checkout: Ridge Books Ltd','Incomplete checkout: Ridge Books Ltd / Practice / peter.ridge.checkout.test@example.com / UGX 250000.00','sent','','2026-09-10 12:35:42'),
(9,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Checkout started (Pesapal): Nile Studio Ltd','Checkout started (Pesapal): Nile Studio Ltd / Studio / grace.nile.checkout.test@example.com / UGX 200000.00','sent','','2026-09-10 12:36:03'),
(10,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Incomplete checkout: front star','Incomplete checkout: front star / Crest / arimpaoscarmark7@gmail.com / EUR 61.73','sent','','2026-09-10 12:50:57'),
(11,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Checkout started (Pesapal): front star','Checkout started (Pesapal): front star / Crest / arimpaoscarmark7@gmail.com / EUR 61.73','sent','','2026-09-10 12:51:15'),
(12,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Checkout started (Pesapal): front star','Checkout started (Pesapal): front star / Crest / arimpaoscarmark7@gmail.com / EUR 61.73','sent','','2026-09-10 12:51:18'),
(13,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Incomplete checkout: front star','Incomplete checkout: front star / Ledger / arimpaoscarmark7@gmail.com / UGX 200000.00','sent','','2026-09-10 13:13:13'),
(14,NULL,0,'info@vellisys.com','info@vellisys.com','Vellisys Checkout started (Pesapal): front star','Checkout started (Pesapal): front star / Ledger / arimpaoscarmark7@gmail.com / UGX 200000.00','sent','','2026-09-10 13:13:26'),
(15,NULL,0,'info@vellisys.com','info@vellisys.com','Copy - Your Vellisys payment awaits - front star','Copy for Vellisys. The client received this from info@vellisys.com. Reply to write to arimpaoscarmark7@gmail.com.\n\nDear arimpa,\n\nThank you for choosing the Ledger desk for front star. Your payment awaits.\n\nThe amount due is UGX 200,000 for the first year. Unpaid invoice VEL-INV-VS6FEEB8C93E84 follows in a separate email.\n\nComplete payment on Pesapal to confirm the desk. If the payment page closed, open http://127.0.0.1:43219/checkout.php?plan=studio&o=60e81d34b4e8fb76 again. A Vellisys admin contacts you after payment to onboard. You do not get a password until the desk is opened.\n\nIf you need us, write to info@vellisys.com or call +256 779 971 024 or +256 756 524 451.\n\nKind regards,\nVellisys','sent','','2026-09-10 13:13:29'),
(16,NULL,0,'arimpaoscarmark7@gmail.com','info@vellisys.com','Your Vellisys payment awaits - front star','Dear arimpa,\n\nThank you for choosing the Ledger desk for front star. Your payment awaits.\n\nThe amount due is UGX 200,000 for the first year. Unpaid invoice VEL-INV-VS6FEEB8C93E84 follows in a separate email.\n\nComplete payment on Pesapal to confirm the desk. If the payment page closed, open http://127.0.0.1:43219/checkout.php?plan=studio&o=60e81d34b4e8fb76 again. A Vellisys admin contacts you after payment to onboard. You do not get a password until the desk is opened.\n\nIf you need us, write to info@vellisys.com or call +256 779 971 024 or +256 756 524 451.\n\nKind regards,\nVellisys','sent','','2026-09-10 13:13:29'),
(17,NULL,0,'info@vellisys.com','info@vellisys.com','Copy - Unpaid invoice VEL-INV-VS6FEEB8C93E84 - front star','Copy for Vellisys. The client received this from info@vellisys.com. Reply to write to arimpaoscarmark7@gmail.com.\n\nDear arimpa,\n\nUNPAID INVOICE VEL-INV-VS6FEEB8C93E84\nBill to: front star\nEmail: arimpaoscarmark7@gmail.com\nPhone: +256779971024\nPlace: Kampala, Uganda\nIssued: 10/09/2026\nDue: On receipt\nStatus: UNPAID\n\nVellisys Ledger desk (2 logins), first year\nAmount due: UGX 200,000\n\nPay: http://127.0.0.1:43219/checkout.php?plan=studio&o=60e81d34b4e8fb76\n\nQuestions: info@vellisys.com · +256 779 971 024 or +256 756 524 451\n\nKind regards,\nVellisys','sent','','2026-09-10 13:13:31'),
(18,NULL,0,'arimpaoscarmark7@gmail.com','info@vellisys.com','Unpaid invoice VEL-INV-VS6FEEB8C93E84 - front star','Dear arimpa,\n\nUNPAID INVOICE VEL-INV-VS6FEEB8C93E84\nBill to: front star\nEmail: arimpaoscarmark7@gmail.com\nPhone: +256779971024\nPlace: Kampala, Uganda\nIssued: 10/09/2026\nDue: On receipt\nStatus: UNPAID\n\nVellisys Ledger desk (2 logins), first year\nAmount due: UGX 200,000\n\nPay: http://127.0.0.1:43219/checkout.php?plan=studio&o=60e81d34b4e8fb76\n\nQuestions: info@vellisys.com · +256 779 971 024 or +256 756 524 451\n\nKind regards,\nVellisys','sent','','2026-09-10 13:13:31');
/*!40000 ALTER TABLE `emails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_cards`
--

DROP TABLE IF EXISTS `landing_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slot` varchar(40) NOT NULL,
  `section` varchar(40) NOT NULL,
  `title` varchar(180) NOT NULL,
  `body` text NOT NULL,
  `image_path` varchar(255) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slot` (`slot`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_cards`
--

LOCK TABLES `landing_cards` WRITE;
/*!40000 ALTER TABLE `landing_cards` DISABLE KEYS */;
INSERT INTO `landing_cards` VALUES
(1,'familiar_1','familiar','Still stuffing receipts in a drawer?','Slips, phone photos, part payments in whatever currency you actually use. By month-end you are guessing what is still owed.','assets/img/landing/landing-receipts.png',1),
(2,'familiar_2','familiar','Did that invoice vanish into WhatsApp?','Quotes in email. Invoices in a chat. Nobody has one number for who still owes the company.','assets/img/landing/landing-whatsapp.png',2),
(3,'familiar_3','familiar','Can you only open the books at the office?','If you are on the road, the PC is off, or the accountant is out, the records are out of reach.','assets/img/landing/landing-office.png',3),
(4,'help_1','help','Your client\'s brand. Many templates.','Every quotation, invoice and receipt is customised to the client\'s logo and colours. Choose from many templates, then email, WhatsApp or print the sheet.','assets/img/landing/landing-share.png',4),
(5,'help_2','help','Open the books from wherever you are.','Anywhere in the world. Sign in and this month is there - invoices, receipts, expenses, reports - on the screen in front of you.','assets/img/landing/landing-anywhere.png',5),
(6,'help_3','help','Quotes, invoices, receipts. One desk.','Pick a template once. The whole books print in that layout, in the company colours. Quotations convert to invoices. Invoices take full or part receipts.','assets/img/landing/landing-desk.png',6),
(7,'steps_1','steps','Register','Name, company, email, phone. That is the whole form. No password to invent. From any country.','assets/img/landing/landing-form.png',7),
(8,'steps_2','steps','Request a quote','A Vellisys admin sees the sign-up, sends a quote, and reaches out to onboard your company.','assets/img/landing/landing-call.png',8),
(9,'steps_3','steps','Get onboarded','You get a login. The books are yours, in your currency, on any device, any time.','assets/img/landing/landing-live.png',9);
/*!40000 ALTER TABLE `landing_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_packages`
--

DROP TABLE IF EXISTS `landing_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_packages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pkg_key` varchar(20) NOT NULL,
  `name` varchar(80) NOT NULL,
  `kicker` varchar(80) NOT NULL DEFAULT '',
  `ribbon` varchar(80) NOT NULL DEFAULT '',
  `seats` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `price_ugx` decimal(14,2) NOT NULL DEFAULT 0.00,
  `was_ugx` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cta` varchar(80) NOT NULL DEFAULT '',
  `lead` text NOT NULL,
  `points` text NOT NULL,
  `popular` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pkg_key` (`pkg_key`),
  KEY `sort_id` (`sort`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_packages`
--

LOCK TABLES `landing_packages` WRITE;
/*!40000 ALTER TABLE `landing_packages` DISABLE KEYS */;
INSERT INTO `landing_packages` VALUES
(1,'solo','Quill','Starting package','',1,150000.00,200000.00,'Select Quill','One login. The books in your colours. Enough for a founder who writes every sheet.','1 company admin login\nBranded quotations, invoices and receipts\nClients, debtors and share by email or WhatsApp\nPrint and PDF from the browser\nReports for the person who signs in',0,10),
(2,'studio','Ledger','','Most companies',2,200000.00,280000.00,'Select Ledger','The common desk: two seats, access levels, and the full sales loop.','2 logins: company admin plus one\nAccess levels: Books or Sales\nEverything in Quill\nExpenses, creditors and delivery notes\nHeaded correspondence from the company mailbox',1,20),
(3,'practice','Crest','Full house','',3,250000.00,350000.00,'Select Crest','Three seats, access levels, and every document the desk can print.','3 logins: admin plus two\nAccess levels for each extra seat\nEverything in Ledger\nCustom documents and all letter layouts\nPriority onboarding from Vellisys',0,30);
/*!40000 ALTER TABLE `landing_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_pricing`
--

DROP TABLE IF EXISTS `landing_pricing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_pricing` (
  `id` tinyint(3) unsigned NOT NULL,
  `kicker` varchar(80) NOT NULL DEFAULT '',
  `heading` varchar(180) NOT NULL DEFAULT '',
  `lead` text NOT NULL,
  `clock_label` varchar(80) NOT NULL DEFAULT '',
  `term_label` varchar(40) NOT NULL DEFAULT '',
  `register_copy` text NOT NULL,
  `register_label` varchar(80) NOT NULL DEFAULT '',
  `countdown_days` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `countdown_hours` tinyint(3) unsigned NOT NULL DEFAULT 12,
  `rates_json` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_pricing`
--

LOCK TABLES `landing_pricing` WRITE;
/*!40000 ALTER TABLE `landing_pricing` DISABLE KEYS */;
INSERT INTO `landing_pricing` VALUES
(1,'Packages','Onboard as the discount lasts','First year, shown in {currency}. Change currency in the header. Pay, then a Vellisys admin contacts you to open the desk.','Discount ends in','first year','Prefer a call first? {register} - a Vellisys admin contacts you to onboard.','Register without paying',3,12,'{\"KES\":28.5,\"USD\":3700,\"EUR\":4050,\"GBP\":4750,\"RWF\":2.55}');
/*!40000 ALTER TABLE `landing_pricing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_review_section`
--

DROP TABLE IF EXISTS `landing_review_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_review_section` (
  `id` tinyint(3) unsigned NOT NULL,
  `kicker` varchar(80) NOT NULL DEFAULT '',
  `heading` varchar(180) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_review_section`
--

LOCK TABLES `landing_review_section` WRITE;
/*!40000 ALTER TABLE `landing_review_section` DISABLE KEYS */;
INSERT INTO `landing_review_section` VALUES
(1,'Testimonials','What clients say');
/*!40000 ALTER TABLE `landing_review_section` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_reviews`
--

DROP TABLE IF EXISTS `landing_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `role` varchar(160) NOT NULL DEFAULT '',
  `quote` text NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sort_id` (`sort`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_reviews`
--

LOCK TABLES `landing_reviews` WRITE;
/*!40000 ALTER TABLE `landing_reviews` DISABLE KEYS */;
INSERT INTO `landing_reviews` VALUES
(1,'Priya Menon','Accounts, Harbour & Co.','Our quotations finally look like us. Clients stopped asking if the PDF came from our office.',10,'2026-09-10 09:09:01'),
(2,'Daniel Okello','Finance lead, Westland Traders','Invoices and receipts match. Debtors is honest by Friday without a spreadsheet chase.',20,'2026-09-10 09:09:01'),
(3,'Sofia Alvarez','Practice manager, Nueve Studio','Headed letters and invoices share one letterhead. We send from the desk in a tap.',30,'2026-09-10 09:09:01'),
(4,'James Boateng','Owner, Accra Millworks','We bill in GHS. Reports still add up. The yard team opens the books on phones.',40,'2026-09-10 09:09:01'),
(5,'Mei Chen','Operations, Pacific Supply','Ten templates, our colours. Automated reminders replaced the awkward WhatsApp chase.',50,'2026-09-10 09:09:01'),
(6,'Amina Yusuf','Director, Sahel Goods','Onboarding was a login in the mailbox. The desk is the professional we needed.',60,'2026-09-10 09:09:01');
/*!40000 ALTER TABLE `landing_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_ticker`
--

DROP TABLE IF EXISTS `landing_ticker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_ticker` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `body` varchar(220) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sort_id` (`sort`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_ticker`
--

LOCK TABLES `landing_ticker` WRITE;
/*!40000 ALTER TABLE `landing_ticker` DISABLE KEYS */;
INSERT INTO `landing_ticker` VALUES
(1,'Join 100+ businesses and corporate companies using Vellisys',10,'2026-09-10 10:31:11'),
(2,'Stop losing the books. Share them branded, in one click.',20,'2026-09-10 10:31:11'),
(3,'Built for East Africa. Used across Africa and worldwide.',30,'2026-09-10 13:31:24'),
(4,'The branded alternative to QuickBooks and other finance software.',40,'2026-09-10 13:31:24');
/*!40000 ALTER TABLE `landing_ticker` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parties`
--

DROP TABLE IF EXISTS `parties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parties` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `kind` enum('customer','supplier','both') NOT NULL DEFAULT 'customer',
  `tin` varchar(40) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(10) unsigned DEFAULT NULL,
  `contact_person` varchar(160) DEFAULT NULL,
  `phone2` varchar(40) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parties`
--

LOCK TABLES `parties` WRITE;
/*!40000 ALTER TABLE `parties` DISABLE KEYS */;
INSERT INTO `parties` VALUES
(1,'Demo Visitor','customer',NULL,'+256700000001','demo@ofagros.com','Kampala, Uganda','2026-09-09 10:41:15',1,NULL,NULL,NULL,NULL,NULL),
(2,'Nile Coffee Traders Ltd','customer','1000456710','+256 414 220 118','accounts@nilecoffee.ug','Plot 8, Portal Avenue, Kampala','2026-09-09 10:41:15',1,NULL,NULL,NULL,NULL,NULL),
(3,'Kituza Estate Growers','customer',NULL,'+256 772 441 090','kituza@growers.ug','Mukono District','2026-09-09 10:41:15',1,NULL,NULL,NULL,NULL,NULL),
(4,'Pearl Hotel Kampala','customer','1000021766','+256 414 251 510','purchasing@pearlhotel.ug','Kololo, Kampala','2026-09-09 10:41:15',1,NULL,NULL,NULL,NULL,NULL),
(5,'SeedCo Uganda Ltd','supplier','1000038891','+256 414 566 200',NULL,'Namanve Industrial Park','2026-09-09 10:41:15',1,NULL,NULL,NULL,NULL,NULL),
(6,'Vivo Energy Uganda','supplier','1000023301',NULL,NULL,'Kampala','2026-09-09 10:41:15',1,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `parties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `ip_hash` char(64) NOT NULL DEFAULT '',
  `status` enum('new','read','replied') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status_created` (`status`,`created_at`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES
(1,'Jane Tester','jane@example.com','+256700000000','How do we add a second user on the company desk please?','cb0c1f2075b42826b015c6387e925b9765b81774986d4b9b31ab7689aef05bd3','read','2026-09-10 06:13:19'),
(2,'Sarah Mukasa','sarah.mukasa@example.com','','Can I export my financial data to Excel or PDF for my accountant to review at year end?','cb0c1f2075b42826b015c6387e925b9765b81774986d4b9b31ab7689aef05bd3','replied','2026-09-10 06:20:25'),
(3,'mark oscar','arimpaoscarmark7@gmail.com','+256779971024','Whats the pricing please tell me','cb0c1f2075b42826b015c6387e925b9765b81774986d4b9b31ab7689aef05bd3','new','2026-09-10 09:09:01');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `renewal_notices`
--

DROP TABLE IF EXISTS `renewal_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `renewal_notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `to_email` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `status` enum('sent','queued','failed') NOT NULL DEFAULT 'queued',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `renewal_notices`
--

LOCK TABLES `renewal_notices` WRITE;
/*!40000 ALTER TABLE `renewal_notices` DISABLE KEYS */;
/*!40000 ALTER TABLE `renewal_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_meta`
--

DROP TABLE IF EXISTS `schema_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_meta` (
  `k` varchar(40) NOT NULL,
  `v` varchar(40) NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_meta`
--

LOCK TABLES `schema_meta` WRITE;
/*!40000 ALTER TABLE `schema_meta` DISABLE KEYS */;
INSERT INTO `schema_meta` VALUES
('pesapal_ipn_id','a603c88f-600f-4fde-b038-d9eb2c8f002d'),
('version','31');
/*!40000 ALTER TABLE `schema_meta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `signups`
--

DROP TABLE IF EXISTS `signups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `signups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `company` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `status` enum('new','contacted','onboarded','declined') NOT NULL DEFAULT 'new',
  `company_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `source` varchar(20) NOT NULL DEFAULT 'register',
  `note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status_created` (`status`,`created_at`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `signups`
--

LOCK TABLES `signups` WRITE;
/*!40000 ALTER TABLE `signups` DISABLE KEYS */;
INSERT INTO `signups` VALUES
(1,'Amina Nsubuga','Pearl Spices Ltd','amina@pearlspices.ug','+256700111222','new',NULL,'2026-09-09 15:44:31','register',NULL),
(2,'David Kato','Kato Hardware Ltd','david@katohardware.ug','+256 788 141 342','new',NULL,'2026-09-09 15:49:05','register',NULL),
(3,'Nsamba Marvin','Front Star','frontstargroupofcompanies@gmail.com','+256779971024','contacted',NULL,'2026-09-10 07:58:12','register',NULL),
(4,'Amina Nalwoga','Lakeview Studio Ltd','amina.lakeview.checkout.test@example.com','256700111222','new',NULL,'2026-09-10 12:35:09','checkout','Solo · UGX 150000.00 · payment draft'),
(5,'Peter Mwangi','Ridge Books Ltd','peter.ridge.checkout.test@example.com','254700333444','new',NULL,'2026-09-10 12:35:41','checkout','Practice · UGX 250000.00 · payment draft'),
(6,'Grace Atim','Nile Studio Ltd','grace.nile.checkout.test@example.com','256701222333','new',NULL,'2026-09-10 12:36:01','checkout','Studio · UGX 200000.00 · payment pending'),
(7,'arimpa','front star','arimpaoscarmark7@gmail.com','','new',NULL,'2026-09-10 12:50:55','checkout','Ledger · UGX 200000.00 · payment pending · Kampala, Uganda');
/*!40000 ALTER TABLE `signups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trust_clients`
--

DROP TABLE IF EXISTS `trust_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trust_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sort_id` (`sort`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trust_clients`
--

LOCK TABLES `trust_clients` WRITE;
/*!40000 ALTER TABLE `trust_clients` DISABLE KEYS */;
INSERT INTO `trust_clients` VALUES
(1,'Ofagros','assets/img/landing/clients/ofagros.svg',10,'2026-09-10 06:12:51'),
(2,'Kira Estates','assets/img/landing/clients/kira-estates.svg',20,'2026-09-10 06:12:51'),
(3,'Nile Hardware','assets/img/landing/clients/nile-hardware.svg',30,'2026-09-10 06:12:51'),
(4,'Jinja Pack','assets/img/landing/clients/jinja-pack.svg',40,'2026-09-10 06:12:51'),
(5,'Rwenzori Mills','assets/img/landing/clients/rwenzori-mills.svg',50,'2026-09-10 06:12:51'),
(6,'Gulu Trade','assets/img/landing/clients/gulu-trade.svg',60,'2026-09-10 06:12:51'),
(7,'Entebbe Marine','assets/img/landing/clients/entebbe-marine.svg',70,'2026-09-10 06:12:51'),
(8,'Mbale Grain','assets/img/landing/clients/mbale-grain.svg',80,'2026-09-10 06:12:51'),
(9,'Fort Portal Tea','assets/img/landing/clients/fort-portal-tea.svg',90,'2026-09-10 06:12:51'),
(10,'Masaka Dairy','assets/img/landing/clients/masaka-dairy.svg',100,'2026-09-10 06:12:51');
/*!40000 ALTER TABLE `trust_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `job_title` varchar(80) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `role` enum('platform','admin','member') NOT NULL DEFAULT 'member',
  `access` varchar(20) NOT NULL DEFAULT 'books',
  `company_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Accounts','','accounts@ofagros.org','$2y$10$U98i.zjR9CNQhJbMxQA2puYwhMYbCjGp3RMxT46G7k/uclg3Mch1O','2026-09-09 10:41:15','admin','admin',1),
(2,'Vellisys Admin','','admin@folio.ug','$2y$10$VporSiVXqLwi/RIENDzUP.2kGaSzbeG8U5QsEP36A0rdtL7VLhVwa','2026-09-09 11:45:55','platform','books',NULL),
(3,'Vellisys Admin','','admin@vellisys.ug','$2y$10$WINWyjBOgZzOcGeKsGdDJOFDHZbDJZWh4IOtPm3koR3PtpEzqW2aG','2026-09-09 15:44:24','platform','books',NULL),
(5,'Mira Accounts','Administrator','mira.harbour.craft.test@example.com','$2y$10$MtXphKMM8hLI.CX7Epa5tOF7DN.9/IVA8tc8m1dEHkiw2wSNWIjNC','2026-09-10 12:07:08','admin','admin',3);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `website_orders`
--

DROP TABLE IF EXISTS `website_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `website_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(16) NOT NULL,
  `merchant_ref` varchar(50) NOT NULL,
  `plan` varchar(20) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'UGX',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount_ugx` decimal(14,2) NOT NULL DEFAULT 0.00,
  `name` varchar(160) NOT NULL DEFAULT '',
  `company` varchar(160) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `city` varchar(120) NOT NULL DEFAULT '',
  `country` varchar(80) NOT NULL DEFAULT '',
  `status` enum('draft','pending','paid','failed','cancelled') NOT NULL DEFAULT 'draft',
  `pesapal_tracking` varchar(80) NOT NULL DEFAULT '',
  `pesapal_redirect` text DEFAULT NULL,
  `signup_id` int(10) unsigned DEFAULT NULL,
  `notified_draft` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `notified_pending` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `merchant_ref` (`merchant_ref`),
  KEY `status_created` (`status`,`created_at`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `website_orders`
--

LOCK TABLES `website_orders` WRITE;
/*!40000 ALTER TABLE `website_orders` DISABLE KEYS */;
INSERT INTO `website_orders` VALUES
(1,'ec9d78e2181b59e1','VS-DD6307F5D531','solo','UGX',150000.00,150000.00,'Amina Nalwoga','Lakeview Studio Ltd','amina.lakeview.checkout.test@example.com','256700111222','Kampala','','draft','',NULL,4,1,0,NULL,'2026-09-10 15:35:09','2026-09-10 15:35:09'),
(2,'2a117b5647bae061','VS-AF28F81C83D7','practice','UGX',250000.00,250000.00,'Peter Mwangi','Ridge Books Ltd','peter.ridge.checkout.test@example.com','254700333444','Nairobi','','draft','',NULL,5,1,0,NULL,'2026-09-10 15:35:41','2026-09-10 15:35:41'),
(3,'7d8451dc70b05ae8','VS-FD13250698AF','studio','UGX',200000.00,200000.00,'Grace Atim','Nile Studio Ltd','grace.nile.checkout.test@example.com','256701222333','Gulu','','pending','e25cf3c5-773a-412c-8465-d9ebec8f0b80','https://pay.pesapal.com/iframe/PesapalIframe3/Index?OrderTrackingId=e25cf3c5-773a-412c-8465-d9ebec8f0b80',6,0,0,NULL,'2026-09-10 15:36:01','2026-09-10 15:36:04'),
(4,'61fe3fad49ab311a','VS-7ACF39DFA21E','practice','EUR',61.73,250000.00,'arimpa','front star','arimpaoscarmark7@gmail.com','+256779971024','Kampala','','draft','99b40907-64eb-41a0-a069-d9eb6032466f','https://pay.pesapal.com/iframe/PesapalIframe3/Index?OrderTrackingId=99b40907-64eb-41a0-a069-d9eb6032466f',7,1,0,NULL,'2026-09-10 15:48:23','2026-09-10 15:51:18'),
(5,'affc9c30f5cda48d','VS-DAAB59E7BA96','studio','UGX',200000.00,200000.00,'Test User','Test Co','','','','','draft','',NULL,NULL,0,0,NULL,'2026-09-10 15:50:00','2026-09-10 15:50:00'),
(7,'60e81d34b4e8fb76','VS-6FEEB8C93E84','studio','UGX',200000.00,200000.00,'arimpa','front star','arimpaoscarmark7@gmail.com','+256779971024','Kampala','Uganda','pending','000aee34-50a1-45af-a067-d9eb4409c79b','https://pay.pesapal.com/iframe/PesapalIframe3/Index?OrderTrackingId=000aee34-50a1-45af-a067-d9eb4409c79b',7,1,1,NULL,'2026-09-10 16:13:07','2026-09-10 16:13:33');
/*!40000 ALTER TABLE `website_orders` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
