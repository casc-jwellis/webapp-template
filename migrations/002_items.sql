-- The Items example module (modules/Items). Delete this file together with that module when you start a real project
-- (before you apply it anywhere), or keep it as a model for your own first table.

CREATE TABLE IF NOT EXISTS `items` (
  `item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(24) NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `notes` text NOT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int unsigned NOT NULL,
  `created_date` datetime NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `name` (`name`),
  KEY `category` (`category`),
  KEY `due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
