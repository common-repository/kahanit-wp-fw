<?php
global $kwfdb_sql;

$kwfdb_sql = "
CREATE TABLE IF NOT EXISTS `{prefix}kwf_forms` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
);

CREATE TABLE IF NOT EXISTS `{prefix}kwf_form_fields` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `form_ID` int(11) unsigned DEFAULT NULL,
  `arguments` longtext,
  PRIMARY KEY (`ID`),
  KEY `form_fields_form_id` (`form_ID`)
);

CREATE TABLE IF NOT EXISTS `{prefix}kwf_metaboxes` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `post_type` varchar(255) DEFAULT NULL,
  `context` enum('normal','advanced','side') DEFAULT NULL,
  `priority` enum('high','core','default','low') DEFAULT NULL,
  `before_form` longtext,
  `form_ID` int(11) unsigned DEFAULT NULL,
  `after_form` longtext,
  PRIMARY KEY (`ID`)
);

CREATE TABLE IF NOT EXISTS `{prefix}kwf_options_pages` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_slug` varchar(255) DEFAULT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `menu_title` varchar(255) DEFAULT NULL,
  `capability` varchar(255) DEFAULT NULL,
  `menu_slug` varchar(255) DEFAULT NULL,
  `icon_url` varchar(2000) DEFAULT NULL,
  `position` int(4) DEFAULT NULL,
  `page_heading` text,
  `before_form` longtext,
  `form_ID` int(11) unsigned DEFAULT NULL,
  `after_form` longtext,
  PRIMARY KEY (`ID`)
);

CREATE TABLE IF NOT EXISTS `{prefix}kwf_sidebars` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` longtext,
  `class` varchar(255) DEFAULT NULL,
  `before_widget` longtext,
  `after_widget` longtext,
  `before_title` longtext,
  `after_title` longtext,
  PRIMARY KEY (`ID`)
);

CREATE TABLE IF NOT EXISTS `{prefix}kwf_taxonomies` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `taxonomy` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `post_types` longtext,
  `description` longtext,
  `arguments` longtext,
  `is_url_flushed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UNIQUE_TAXONOMY` (`taxonomy`)
);

CREATE TABLE IF NOT EXISTS `{prefix}kwf_types` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_type` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `taxonomies` longtext,
  `description` longtext,
  `arguments` longtext,
  `is_url_flushed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UNIQUE_POST_TYPE` (`post_type`)
);

ALTER TABLE `{prefix}kwf_form_fields`
  ADD CONSTRAINT `fk_form_fields_forms_form_id` FOREIGN KEY (`form_ID`) REFERENCES `{prefix}kwf_forms` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;
"; ?>