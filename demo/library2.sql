-- Adminer 4.7.4 MySQL dump

SET FOREIGN_KEY_CHECKS=0

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP DATABASE IF EXISTS `library2`;
CREATE DATABASE `library2` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `library2`;

DROP TABLE IF EXISTS `address`;
CREATE TABLE `address` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `postCode` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `address` (`id`, `city`, `street`, `postCode`) VALUES
(1,	'prague',	'test 122',	'16000'),
(2,	'Atlantis',	'limitless 42',	'55847'),
(3,	'Quicksilver',	'Main square 15',	'64855');

DROP TABLE IF EXISTS `book`;
CREATE TABLE `book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `book` (`id`, `name`) VALUES
(1,	'The Old Man and the Sea'),
(2,	'A Farewell to Arms'),
(4,	'Five Weeks in a Balloon'),
(5,	'The Adventures of Captain Hatteras');

DROP TABLE IF EXISTS `employee`;
CREATE TABLE `employee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `surname` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address` int(11) NOT NULL,
  `workplace` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `address` (`address`),
  KEY `workplace` (`workplace`),
  CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`address`) REFERENCES `address` (`id`),
  CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`workplace`) REFERENCES `library` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `employee` (`id`, `name`, `surname`, `address`, `workplace`) VALUES
(3,	'John',	'Locke',	1,	2),
(4,	'Alexander',	'Nelson',	1,	2),
(5,	'Kate',	'Right',	1,	2),
(6,	'Jack',	'The Little',	1,	3);

DROP TABLE IF EXISTS `library`;
CREATE TABLE `library` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` int(11) DEFAULT NULL,
  `manager` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `address` (`address`),
  KEY `manager` (`manager`),
  CONSTRAINT `library_ibfk_1` FOREIGN KEY (`address`) REFERENCES `address` (`id`),
  CONSTRAINT `library_ibfk_2` FOREIGN KEY (`manager`) REFERENCES `employee` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `library` (`id`, `name`, `address`, `manager`) VALUES
(2,	'Great library of Atlantis city',	2,	3),
(3,	'Quicksilver town little library',	3,	6);

DROP TABLE IF EXISTS `library_book`;
CREATE TABLE `library_book` (
  `library` int(11) NOT NULL,
  `book` int(11) NOT NULL,
  KEY `library` (`library`),
  KEY `book` (`book`),
  CONSTRAINT `library_book_ibfk_1` FOREIGN KEY (`library`) REFERENCES `library` (`id`),
  CONSTRAINT `library_book_ibfk_2` FOREIGN KEY (`book`) REFERENCES `book` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `library_book` (`library`, `book`) VALUES
(2,	1),
(2,	1),
(2,	2),
(2,	4),
(2,	5),
(3,	1),
(3,	4);

SET FOREIGN_KEY_CHECKS=1