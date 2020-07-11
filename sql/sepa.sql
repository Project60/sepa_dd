-- /*******************************************************
-- *
-- * civicrm_sdd_creditor
-- *
-- *******************************************************/

CREATE TABLE IF NOT EXISTS `civicrm_sdd_creditor`(
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `creditor_id`          int unsigned        COMMENT 'FK to Contact ID that owns that account',
     `identifier`           varchar(35)         COMMENT 'Provided by the bank. ISO country code+check digit+ZZZ+country specific identifier',
     `label`                varchar(128)        COMMENT 'internally used label for the creditor',
     `name`                 varchar(255)        COMMENT 'official creditor name, passed to exported files',
     `address`              varchar(255)        COMMENT 'official creditor address, passed to exported files',
     `country_id`           int unsigned        COMMENT 'country the creditor is based in',
     `iban`                 varchar(42) NULL    COMMENT 'IBAN of the creditor',
     `bic`                  varchar(11)         COMMENT 'BIC of the creditor',
     `mandate_prefix`       varchar(4)          COMMENT 'prefix for mandate identifiers',
     `currency`             varchar(3)          COMMENT 'currency used by this creditor',
     `payment_processor_id` int unsigned        COMMENT 'used in payment_processor_id',
     `category`             varchar(4)          COMMENT 'Default value',
     `tag`                  varchar(64) NULL    COMMENT 'Place this creditors transaction groups in an XML file tagged with this value.',
     `mandate_active`       tinyint             COMMENT 'If true, new Mandates for this Creditor are set to active directly upon creation; otherwise, they have to be activated explicitly later on.',
     `sepa_file_format_id`  int unsigned        COMMENT 'Variant of the pain.008 format to use when generating SEPA XML files for this creditor. FK to SEPA File Formats in civicrm_option_value.',
     `creditor_type`        varchar(8)          COMMENT 'type of the creditor, values are SEPA (default) and PSP',
     `uses_bic`             tinyint             COMMENT 'If true, BICs are not used for this creditor',
    PRIMARY KEY ( `id` ),
    CONSTRAINT FK_civicrm_sdd_creditor_creditor_id FOREIGN KEY (`creditor_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL,
    CONSTRAINT FK_civicrm_sdd_creditor_country_id  FOREIGN KEY (`country_id`)  REFERENCES `civicrm_country`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_mandate
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_mandate` (
     `id`                    int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference`             varchar(35) NOT NULL                  COMMENT 'The unique mandate reference',
     `source`                varchar(64)                           COMMENT 'Needed or coming from ContributionRecur? phoning/online/face 2 face....',
     `entity_table`          varchar(64)                           COMMENT 'physical tablename for entity being joined, eg contributionRecur or Membership',
     `entity_id`             int unsigned NOT NULL                 COMMENT 'FK to entity table specified in entity_table column.',
     `date`                  datetime NOT NULL                     COMMENT 'signature date, by default now()',
     `creditor_id`           int unsigned                          COMMENT 'FK to sdd_creditor',
     `contact_id`            int unsigned                          COMMENT 'FK to Contact ID that owns that account',
     `account_holder`        varchar(255) NULL DEFAULT NULL        COMMENT 'Name of the account holder',
     `iban`                  varchar(42) NULL                      COMMENT 'IBAN of the debtor',
     `bic`                   varchar(11)                           COMMENT 'BIC of the debtor',
     `type`                  varchar(4) NOT NULL DEFAULT 'RCUR'    COMMENT 'RCUR for recurrent (default), OOFF for one-shot',
     `status`                varchar(8) NOT NULL DEFAULT 'INIT'    COMMENT 'Status of the mandate (INIT, OOFF, FRST, RCUR, SENT, INVALID, COMPLETE, ONHOLD, PARTIAL)',
     `is_enabled`            tinyint NOT NULL  DEFAULT 1           COMMENT 'If the mandate has been validated',
     `creation_date`         datetime                              COMMENT 'by default now()',
     `first_contribution_id` int unsigned                          COMMENT 'FK to civicrm_contribution',
     `validation_date`       datetime,

     PRIMARY KEY (`id`),
     UNIQUE INDEX `reference`      (reference),
     INDEX        `index_entity`   (entity_table, entity_id),
     INDEX        `iban`           (iban),

     CONSTRAINT FK_civicrm_sdd_mandate_creditor_id           FOREIGN KEY (`creditor_id`)           REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,
     CONSTRAINT FK_civicrm_sdd_mandate_contact_id            FOREIGN KEY (`contact_id`)            REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL,
     CONSTRAINT FK_civicrm_sdd_mandate_first_contribution_id FOREIGN KEY (`first_contribution_id`) REFERENCES `civicrm_contribution`(`id`)

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;


-- /*******************************************************
-- *
-- * civicrm_sdd_file
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_file` (
     `id`                     int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference`              varchar(64)            COMMENT 'End-to-end reference for this sdd file.',
     `filename`               char(64)               COMMENT 'Name of the generated file',
     `latest_submission_date` datetime               COMMENT 'Latest submission date',
     `created_date`           datetime               COMMENT 'When was this item created',
     `created_id`             int unsigned           COMMENT 'FK to Contact ID of creator',
     `status_id`              int unsigned NOT NULL  COMMENT 'fk to Batch Status options in civicrm_option_values',
     `comments`               text                   COMMENT 'Comments about processing of this file',
     `tag`                    varchar(64) NULL       COMMENT 'Tag used to group multiple creditors in this XML file.',

    PRIMARY KEY ( `id` ),
    UNIQUE INDEX `UI_reference`(reference),
    UNIQUE INDEX `UI_filename`(filename),

    CONSTRAINT FK_civicrm_sdd_file_created_id FOREIGN KEY (`created_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_txgroup
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_txgroup` (
     `id`                       int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `reference`                varchar(64)             COMMENT 'End-to-end reference for this tx group.',
     `type`                     char(4)                 COMMENT 'FRST, RCUR, OOFF or RTRY',
     `collection_date`          datetime                COMMENT 'Target collection date',
     `latest_submission_date`   datetime                COMMENT 'Latest submission date',
     `created_date`             datetime                COMMENT 'When was this item created',
     `status_id`                int unsigned NOT NULL   COMMENT 'fk to Batch Status options in civicrm_option_values',
     `sdd_creditor_id`          int unsigned            COMMENT 'fk to SDD Creditor Id',
     `sdd_file_id`              int unsigned            COMMENT 'fk to SDD File Id',

    PRIMARY KEY ( `id` ),
    UNIQUE INDEX `UI_reference`(reference),
    CONSTRAINT FK_civicrm_sdd_txgroup_sdd_creditor_id FOREIGN KEY (`sdd_creditor_id`) REFERENCES `civicrm_sdd_creditor`(`id`) ON DELETE SET NULL,
    CONSTRAINT FK_civicrm_sdd_txgroup_sdd_file_id FOREIGN KEY (`sdd_file_id`) REFERENCES `civicrm_sdd_file`(`id`) ON DELETE SET NULL
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicrm_sdd_contribution_txgroup
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_contribution_txgroup` (
     `id`               int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'primary key',
     `contribution_id`  int unsigned NOT NULL                 COMMENT 'FK to Contribution ID',
     `txgroup_id`       int unsigned NOT NULL                 COMMENT 'FK to civicrm_sdd_txgroup',
    PRIMARY KEY ( `id` ),
    UNIQUE INDEX `contriblookup`(contribution_id),
    INDEX `txglookup`(txgroup_id),
    CONSTRAINT FK_civicrm_sdd_cGGoup_id FOREIGN KEY (`txgroup_id`) REFERENCES `civicrm_sdd_txgroup`(`id`),
    CONSTRAINT FK_civicrm_sdd_contribution_id FOREIGN KEY (`contribution_id`) REFERENCES `civicrm_contribution`(`id`) ON DELETE CASCADE
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;



-- /*******************************************************
-- *
-- * civicrm_sdd_entity_mandate
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_sdd_entity_mandate` (
     `id`                    int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `mandate_id`            int unsigned NOT NULL                 COMMENT 'FK to sdd_mandate',
     `entity_table`          varchar(64)  NOT NULL                 COMMENT 'Physical table name for entity being linked, eg civicrm_membership',
     `entity_id`             int unsigned NOT NULL                 COMMENT 'FK to entity table specified in entity_table column.',
     `class`                 varchar(16)                           COMMENT 'Link class, freely defined by client',
     `is_active`             tinyint NOT NULL  DEFAULT 1           COMMENT 'Is this link still active?',
     `creation_date`         datetime NOT NULL                     COMMENT 'by default now()',
     `start_date`            datetime                              COMMENT 'optional start_date of the link',
     `end_date`              datetime                              COMMENT 'optional start_date of the link',

     PRIMARY KEY (`id`),
     INDEX `mandate_id` (mandate_id),
     INDEX `link` (entity_table, entity_id),
     INDEX `class` (class),
     INDEX `is_active` (is_active),
     INDEX `start_date` (start_date),
     INDEX `end_date` (end_date),

     CONSTRAINT FK_civicrm_sdd_entity_mandate_id FOREIGN KEY (`mandate_id`) REFERENCES `civicrm_sdd_mandate`(`id`) ON DELETE CASCADE

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;

