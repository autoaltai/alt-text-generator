CREATE TABLE tx_alttextgenerator_errorlog (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    level varchar(16) DEFAULT 'error' NOT NULL,
    message text,
    context text,

    PRIMARY KEY (uid),
    KEY crdate (crdate)
);

CREATE TABLE tx_alttextgenerator_configuration (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    configuration mediumtext,

    PRIMARY KEY (uid)
);

CREATE TABLE tx_alttextgenerator_history (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    file_uid int(11) unsigned DEFAULT '0' NOT NULL,
    metadata_uid int(11) unsigned DEFAULT '0' NOT NULL,
    file_identifier varchar(2048) DEFAULT '' NOT NULL,
    file_name varchar(255) DEFAULT '' NOT NULL,
    source varchar(32) DEFAULT 'bulk' NOT NULL,
    language varchar(16) DEFAULT '' NOT NULL,
    status varchar(32) DEFAULT 'success' NOT NULL,
    generated_alt_text text,
    generated_title text,
    generated_description text,
    error_message text,
    website_domain varchar(255) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    KEY file_uid (file_uid),
    KEY status (status),
    KEY status_file_uid (status, file_uid),
    KEY source (source),
    KEY language (language),
    KEY status_source_language_crdate (status, source, language, crdate),
    KEY crdate (crdate)
);

CREATE TABLE tx_alttextgenerator_rename_history (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    file_uid int(11) unsigned DEFAULT '0' NOT NULL,
    storage_uid int(11) unsigned DEFAULT '0' NOT NULL,
    old_identifier varchar(2048) DEFAULT '' NOT NULL,
    new_identifier varchar(2048) DEFAULT '' NOT NULL,
    old_filename varchar(255) DEFAULT '' NOT NULL,
    new_filename varchar(255) DEFAULT '' NOT NULL,
    rename_method varchar(16) DEFAULT 'manual' NOT NULL,
    status varchar(32) DEFAULT 'success' NOT NULL,
    error_message text,
    api_request_id varchar(255) DEFAULT '' NOT NULL,
    backend_user_uid int(11) unsigned DEFAULT '0' NOT NULL,
    created_at int(11) unsigned DEFAULT '0' NOT NULL,
    undone_at int(11) unsigned DEFAULT '0' NOT NULL,
    metadata text,

    PRIMARY KEY (uid),
    KEY file_uid (file_uid),
    KEY file_status_uid (file_uid, status, uid),
    KEY method_status (rename_method, status),
    KEY created_at (created_at),
    KEY backend_user_uid (backend_user_uid)
);
