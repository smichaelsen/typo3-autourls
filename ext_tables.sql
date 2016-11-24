CREATE TABLE `tx_autourls_map` (
	`querystring` text,
	`encoding_expires` int(11) unsigned NOT NULL DEFAULT '0',
	`path` text,
	`is_shortcut` tinyint(4) unsigned NOT NULL DEFAULT '0',
	`rootpage_id` int(11) unsigned NOT NULL DEFAULT '0',
	UNIQUE KEY `combination_key` (`querystring`(255),`path`(255),`rootpage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
