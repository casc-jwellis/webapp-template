-- The tables every app made from this template needs: people who can sign in, settings an administrator changes
-- from the browser, a log of the emails the app sent, and the one-time links behind "Forgot password".
--
-- Everything is utf8mb4 (emoji and every language fit). Write later migrations to be safe to run twice
-- (CREATE TABLE IF NOT EXISTS, ...): MySQL cannot roll back schema changes, so one that fails part-way is retried.

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password` varchar(255) NOT NULL,
  `firstname` varchar(32) NOT NULL DEFAULT '',
  `lastname` varchar(32) NOT NULL DEFAULT '',
  `email` varchar(128) NOT NULL DEFAULT '',
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `timezone` varchar(32) NOT NULL DEFAULT 'UTC',
  `lastlogin` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_date` datetime NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_log` (
  `log_id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_date` datetime NOT NULL,
  `event` varchar(24) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `recipients` varchar(600) NOT NULL,
  `status` varchar(12) NOT NULL,
  `detail` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Only a hash of the link's secret is kept (so reading this table does not let anyone reset a password), each row is
-- good for one hour, and asking again replaces the earlier link.
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_date` datetime NOT NULL,
  `expires_date` datetime NOT NULL,
  `requested_ip` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  KEY `requested_ip` (`requested_ip`, `created_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
