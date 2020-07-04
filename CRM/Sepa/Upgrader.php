<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Sepa_ExtensionUtil as E;

require_once 'CRM/Sepa/CustomData.php';

/**
 * Collection of upgrade steps.
 */
class CRM_Sepa_Upgrader extends CRM_Sepa_Upgrader_Base {

  /**
   * Installation
   */
  public function install() {
    $this->executeSqlFile('sql/sepa.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  public function postInstall() {
    // TODO: anything?
  }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  public function disable() {
    // TODO: anything?
  }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  public function enable() {
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(E::path('resources/batch_status_option_group.json'));
    $customData->syncOptionGroup(E::path('resources/formats_option_group.json'));
    $customData->syncOptionGroup(E::path('resources/msg_tpl_workflow_contribution_option_group.json'));
    $customData->syncOptionGroup(E::path('resources/payment_instrument_option_group.json'));
    $customData->syncOptionGroup(E::path('resources/iban_blacklist_option_group.json'));
  }


  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1261() {
    $this->ctx->log->info('Adding new file formats');
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/formats_option_group.json');
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1400() {
    // add currency
    $this->ctx->log->info('Added currency field');
    $currency_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_sdd_creditor` LIKE 'currency';");
    if (!$currency_column) {
      // doesn't exist yet
      $this->executeSql("ALTER TABLE civicrm_sdd_creditor ADD COLUMN `currency` varchar(3) COMMENT 'currency used by this creditor';");
    }
    $this->executeSql("UPDATE civicrm_sdd_creditor SET currency = 'EUR' WHERE currency IS NULL;");
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1403() {
    // add currency
    $this->ctx->log->info('Added SepaMandateLink entity');
    $this->executeSqlFile('sql/update_1403.sql');
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1410() {
    // add currency
    $this->ctx->log->info('Adding creditor_type field');
    $currency_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_sdd_creditor` LIKE 'creditor_type';");
    if (!$currency_column) {
      // doesn't exist yet
      $this->executeSql("ALTER TABLE civicrm_sdd_creditor ADD COLUMN `creditor_type` varchar(8) COMMENT 'type of the creditor, values are SEPA (default) and PSP';");
    }

    $this->executeSql("UPDATE civicrm_sdd_creditor SET creditor_type = 'SEPA' WHERE creditor_type IS NULL;");
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1411() {
    $this->ctx->log->info('Adding new file formats');
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(E::path('resources/formats_option_group.json'));
    return TRUE;
  }

  /**
   * Fixes the damages caused by SEPA-514
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1412() {
    $this->ctx->log->info('Applying update 1412');
    // set all SEPA recurring contributions in status 'In Progress' to 'Pending'
    $status_pending    = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $status_inprogress = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');
    CRM_Core_DAO::executeQuery("
        UPDATE civicrm_contribution_recur rcur
        LEFT JOIN civicrm_sdd_mandate  mandate ON mandate.entity_id = rcur.id 
                                               AND mandate.entity_table = 'civicrm_contribution_recur'
          SET rcur.contribution_status_id = {$status_pending}
        WHERE rcur.contribution_status_id = {$status_inprogress}
          AND mandate.id IS NOT NULL;");

    // count number of loose 'In Progress' SEPA contributions,
    //  i.e. the ones that are not in any batch group
    $lost_contributions = CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(*)
        FROM civicrm_contribution contribution
        LEFT JOIN  civicrm_sdd_contribution_txgroup c2txg ON c2txg.contribution_id = contribution.id 
        LEFT JOIN civicrm_sdd_mandate  mandate ON mandate.entity_id = contribution.contribution_recur_id 
                                               AND mandate.entity_table = 'civicrm_contribution_recur'
        WHERE contribution.contribution_status_id = {$status_inprogress}
          AND mandate.id IS NOT NULL
          AND c2txg.id IS NULL;");
    if ($lost_contributions) {
      CRM_Core_Session::setStatus("There seems to be {$lost_contributions} SEPA contributions in status 'In Progress', that are not in any transaction group. This is likely due to the bug SEPA-514, and you might want to check, if these shouldn't be deleted.");
    }

    return TRUE;
  }

  /**
   * Make sure, the payment_processor_id in civicrm_sdd_creditor is NULL.
   *  This *shouldn't* be the set, but if it is, it can cause errors.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1414() {
    $this->ctx->log->info('Applying update 1414');
    CRM_Core_DAO::executeQuery('UPDATE civicrm_sdd_creditor SET payment_processor_id = NULL;');
    return TRUE;
  }

  /**
   * Apply schema changes from previous upgrades to logging schema
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1415() {
    $this->ctx->log->info('Applying update 1415: Fix logging schema');
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    return TRUE;
  }

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1501() {
    // add currency
    $this->ctx->log->info('Adding uses_bic field');
    $uses_bic_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_sdd_creditor` LIKE 'uses_bic';");
    if (!$uses_bic_column) {
      // doesn't exist yet, add the column and set to '1'
      $this->executeSql("ALTER TABLE civicrm_sdd_creditor ADD COLUMN `uses_bic` tinyint COMMENT 'If true, BICs are not used for this creditor';");
      $this->executeSql("UPDATE civicrm_sdd_creditor SET `uses_bic`=1 WHERE uses_bic IS NULL");
    }
    return TRUE;
  }

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1502() {
    $this->ctx->log->info('Adding IBAN Blacklist');
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(E::path('resources/iban_blacklist_option_group.json'));
    return TRUE;
  }

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1503() {
    // add currency
    $this->ctx->log->info('Adding creditor.label field');
    $uses_bic_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_sdd_creditor` LIKE 'label';");
    if (!$uses_bic_column) {
      // doesn't exist yet, add the column and set to '1'
      $this->executeSql("ALTER TABLE civicrm_sdd_creditor ADD COLUMN `label` varchar(128) COMMENT 'internally used label for the creditor';");
      $this->executeSql("UPDATE civicrm_sdd_creditor SET label=name WHERE label IS NULL");
    }
    return TRUE;
  }

  /**
   * Fix civicrm_sdd_contribution_txgroup constraint (#548)
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1504() {
    $dsn = DB::parseDSN(CIVICRM_DSN);
    $this->ctx->log->info('Adding civicrm_sdd_contribution_txgroup.FK_civicrm_sdd_contribution_id constraint');
    $constraint_exists = (int) CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '{$dsn['database']}' AND TABLE_NAME = 'civicrm_sdd_contribution_txgroup' AND CONSTRAINT_NAME='FK_civicrm_sdd_contribution_id';");
    if (!$constraint_exists) {
      $this->executeSql("ALTER TABLE `civicrm_sdd_contribution_txgroup` ADD CONSTRAINT FK_civicrm_sdd_contribution_id FOREIGN KEY (`contribution_id`) REFERENCES `civicrm_contribution`(`id`) ON DELETE CASCADE;");
    }
    return TRUE;
  }

  /**
   * add new file format (#549)
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1506() {
    $dsn = DB::parseDSN(CIVICRM_DSN);
    $this->ctx->log->info("Adding new 'pain.008.001.02 without BIC.");
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(E::path('resources/formats_option_group.json'));
    return TRUE;
  }

    /**
     * SDD Payment processors have been moved to another extension,
     *  so remove SDD payment processors if not used,
     *  and disable/warn user otherwise
     *
     * @see https://github.com/project60/org.project60.sepa/issues/534
     *
     * @return TRUE on success
     * @throws Exception
     */
    public function upgrade_1507() {
        $this->ctx->log->info("Dealing with migrated payment processor code...");

        // get the IDs of the SDD processor types
        $sdd_processor_type_ids = [];
        $sdd_processor_type_query = civicrm_api3('PaymentProcessorType', 'get', [
            'name'         => ['IN' => ['SEPA_Direct_Debit', 'SEPA_Direct_Debit_NG']],
            'return'       => 'id',
            'option.limit' => 0,
        ]);
        foreach ($sdd_processor_type_query['values'] as $pp_type) {
            $sdd_processor_type_ids[] = (int) $pp_type['id'];
        }

        // if there is SDD types registered (which should be the case), we have to deal with them
        if (!empty($sdd_processor_type_ids)) {
            // find out, if they're being used
            $sdd_processor_type_id_list = implode(',', $sdd_processor_type_ids);
            $use_count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_payment_processor WHERE payment_processor_type_id IN ({$sdd_processor_type_id_list});");

            if ($use_count) {
                // if the payment processors are being used, divert them to the dummy processor
                //  and issue a warning to install the SDD PP extension
                $message = E::ts("Your CiviSEPA payment processors have been disabled, the code was moved into a new extension. If you want to continue using your CiviSEPA payment processors, please install the latest version of the <a href=\"https://github.com/Project60/org.project60.sepapp/releases\">CiviSEPA Payment Processor</a> Extension.");
                CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor SET class_name='Payment_Dummy' WHERE payment_processor_type_id IN ({$sdd_processor_type_id_list});");
                CRM_Core_Session::setStatus($message, E::ts("%1 Payment Processor(s) Disabled!", [1 => $use_count]), 'warn');
                Civi::log()->warning($message);

            } else {
                // if they are _not_ used, we can simply delete them.
                foreach ($sdd_processor_type_ids as $sdd_processor_type_id) {
                    civicrm_api3('PaymentProcessorType', 'delete', ['id' => $sdd_processor_type_id]);
                }
            }
        }
        return TRUE;
    }

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1508() {
    $this->ctx->log->info('Adding mandate.account_holder field');
    $this->executeSql("ALTER TABLE civicrm_sdd_mandate ADD COLUMN `account_holder` varchar(255) NULL DEFAULT NULL COMMENT 'Name of the account holder';");
    return TRUE;
  }
}