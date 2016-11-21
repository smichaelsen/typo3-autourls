CREATE TABLE tx_autourls_map (
	combined_hash int(11) unsigned NOT NULL default '0',
	querystring text,
	encoding_expires int(11) unsigned NOT NULL default '0',
	path text,
    rootpage_id int(11) unsigned NOT NULL default '0',
	is_shortcut tinyint(4) unsigned NOT NULL default '0',

	PRIMARY KEY (combined_hash),
    KEY querystring (querystring(255)),
    KEY path (path(255))
);
