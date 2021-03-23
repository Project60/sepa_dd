<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP                           |
| Author: X+                                             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/**
 * File for the CiviCRM sepa_mandate business logic
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPAMandate extends CRM_Sepa_DAO_SEPAMandate {

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPAMandate object on success, null otherwise
   * @access public
   * @static (I do apologize, I don't want to)
   */
  static function add(&$params) {

    // handle 'normal' creation process including hooks
    $hook = empty($params['id']) ? 'create' : 'edit';

    // run the PRE hook
    CRM_Utils_Hook::pre($hook, 'SepaMandate', CRM_Utils_Array::value('id', $params), $params);

    // load the creditor
    if (empty($params['creditor_id'])) {
      if (empty($params['id'])) {
        // new mandate, use the default creditor
        $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
        $params['creditor_id'] = $default_creditor->id;
      } else {
        // existing mandate, get creditor
        $params['creditor_id'] = civicrm_api3('SepaMandate', 'getvalue', array ('id' => $params['id'], 'return' => 'creditor_id'));
      }
    }
    $creditor = civicrm_api3 ('SepaCreditor', 'getsingle', array ('id' => $params['creditor_id'], 'return' => 'mandate_prefix,creditor_type'));

    if (empty($params['id'])) {
      // we are creating a NEW MANDATE...

      // call the hook
      CRM_Utils_SepaCustomisationHooks::create_mandate($params);

      // set a default (signature) date
      if (empty($params["date"])) {
        $params["date"] = date("YmdHis");
      }

      // make sure it has a reference
      if (empty($params['reference'])) {
        // If no mandate reference was supplied by the caller nor the customisation hook, create a nice default one.
        $dao = new CRM_Core_DAO();
        $database = $dao->database();
        $next_id = CRM_Core_DAO::singleValueQuery("SELECT auto_increment FROM information_schema.tables WHERE table_schema='$database' and table_name='civicrm_sdd_mandate';");
        $params['reference'] = $creditor['mandate_prefix'] . '-' . $params['creditor_id'] . '-' . $params['type'] . '-' . date("Y") . '-' . $next_id;
      }
    }

    // validate IBAN / BIC / reference
    if (!empty($params['iban'])) {
      $params['iban'] = CRM_Sepa_Logic_Verification::formatIBAN($params['iban'], $creditor['creditor_type']);
      $iban_error = CRM_Sepa_Logic_Verification::verifyIBAN($params['iban'], $creditor['creditor_type']);
      if ($iban_error) throw new CRM_Core_Exception($iban_error . ': ' . $params['iban']);
    }

    if (!empty($params['bic'])) {
      $params['bic'] = CRM_Sepa_Logic_Verification::formatBIC($params['bic'], $creditor['creditor_type']);
      $bic_error = CRM_Sepa_Logic_Verification::verifyBIC($params['bic'], $creditor['creditor_type']);
      if ($bic_error) throw new CRM_Core_Exception($bic_error . ':' . $params['bic']);
    }

    if (isset($params['reference']) && strlen($params['reference']) > 0) {
      $reference_error = CRM_Sepa_Logic_Verification::verifyReference($params['reference'], $creditor['creditor_type']);
      if ($reference_error) throw new CRM_Core_Exception($reference_error . ':' . $params['reference']);
    }

    // create the DAO object
    $dao = new CRM_Sepa_DAO_SEPAMandate();
    $dao->copyValues($params);
    if (self::is_active(CRM_Utils_Array::value('status', $params))) {
      if ($dao->validation_date == NULL) {
        $dao->validation_date = date("YmdHis");
      }
    }
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaMandate', $dao->id, $dao);
    return $dao;
  }

  static function is_active($mandateStatus) {
    switch ($mandateStatus) {
      case 'INIT' :
      case 'ONHOLD' :
      case 'CANCELLED' :
      case 'INVALID' :
        return false;
        break;
      default :
        return true;
    }
  }

  /**
   * getContract() returns the contribution or recurring contribution this mandate uses as a contract
   */
  function getContract() {
    $etp = $this->entity_table;
    $eid = $this->entity_id;
    switch ($etp) {
      case 'civicrm_contribution_recur' :
        $recur = new CRM_Contribute_BAO_ContributionRecur();
        $recur->get('id', $eid);
        return $recur;
        break;
      case 'civicrm_contribution' :
        $contr = new CRM_Contribute_BAO_Contribution();
        $contr->get('id', $eid);
        return $contr;
        break;
      default:
        echo 'Huh ? ' . $etp;
    }
    return null;
  }

  /**
   * findContribution() locates a contribution created within the scope of the contract.
   *   MANDATE -> CONTRIBUTION_RECUR -> CONTRIBUTION
   * or
   *   MANDATE -> CONTRIBUTION
   */
  public function findContribution() {
    $etp = $this->entity_table;
    $eid = $this->entity_id;
    switch ($etp) {
      case 'civicrm_contribution_recur' :
        $contr = new CRM_Contribute_BAO_Contribution();
        $contr->get('contribution_recur_id', $eid);
        return $contr;
        break;
      case 'civicrm_contribution' :
        $contr = new CRM_Contribute_BAO_Contribution();
        $contr->get('id', $eid);
        return $contr;
        break;
      default:
        echo 'Huh ? ' . $etp;
    }
    return null;
  }


  /**
   * Looks like an unused function, candidate for deprecation because of nasty code
   *
   * @deprecated
   * @return array
   */
  public function getUnbatchedContributionIds() {
    // 1.determine the contract (ie. find out how to get contributions)
    $contrib = $this->getContract();

    // 2. if it is a recurring one, get the contribs that match the pattern
    if (is_a($contrib, 'CRM_Contribute_BAO_ContributionRecur')) {
      $query = "SELECT        c.id AS id, b.id  AS batch_id
                FROM          civicrm_contribution c
                LEFT JOIN     civicrm_entity_batch eb ON ( c.id = eb.entity_id AND eb.entity_table = 'civicrm_contribution' )
                LEFT JOIN     civicrm_batch b ON ( b.id = eb.batch_id )
                WHERE         c.contribution_recur_id = " . $contrib->id . "
                AND           b.type_id IN ( " . 222 . " )
                HAVING        batch_id IS NULL
                ";
      $dao = CRM_Core_DAO::executeQuery($query);
      $contributions = [];
      while ($dao->fetch()) {
        $contributions[] = $dao->id;
      }
      return $contributions;
    }

    if (is_a($contrib, 'CRM_Contribute_BAO_ContributionRecur')) {
      return array($contrib->id);
    }

    return array();
  }

  /**
   * gracefully terminates OOFF mandates
   *
   * @return boolean success
   * @author endres -at- systopia.de
   */
  static function terminateOOFFMandate($mandate_id, $new_end_date_str, $cancel_reason=NULL, $mandate=NULL) {
    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot close mandate [%s], batching in progress!", array('domain' => 'org.project60.sepa')), $mandate_id), ts('Error'), 'error');
      return FALSE;
    }

    // if not passed by param, load the mandate
    if ($mandate==NULL || $mandate_id != $mandate['id']) {
      $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
      if (isset($mandate['is_error'])) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $mandate['error_message']), ts('Error'), 'error');
        $lock->release();
        return FALSE;
      }
    }

    // check if it's really a OOFF mandate
    if ( $mandate['type']!="OOFF" ) {
      Civi::log()->debug("org.project60.sepa: the terminateOOFFMandate method can only modify OOFF mandates!");
      $lock->release();
      return FALSE;
    }

    // check if it's not been SENT yet
    if ( $mandate['status']!='OOFF' && $mandate['status']!='INIT') {
      Civi::log()->debug("org.project60.sepa: the terminateOOFFMandate method can only modify OOFF mandates!");
      $lock->release();
      return FALSE;
    }

    // check if it's not in a closed group (should not be possible, but just to be sure...)
    $contribution_id = $mandate['entity_id'];
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $is_in_closed_group = CRM_Core_DAO::singleValueQuery("
      SELECT COUNT(civicrm_sdd_contribution_txgroup.id)
      FROM civicrm_sdd_contribution_txgroup
      LEFT JOIN civicrm_sdd_txgroup ON civicrm_sdd_contribution_txgroup.txgroup_id = civicrm_sdd_txgroup.id
      WHERE contribution_id = $contribution_id
      AND status_id <> $group_status_id_open;");
    if ($is_in_closed_group) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot close mandate [%s], it's alread batched in a non-open group!", array('domain' => 'org.project60.sepa')), $mandate_id), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // NOW: cancel this mandate
    // first: cancel the mandate entity
    $result = civicrm_api('SepaMandate', 'create', array(
      'version'   => 3,
      'id'        => $mandate_id,
      'status'    => 'INVALID',
    ));
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot properly end mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $result['error_message']), ts('Error'), 'warn');
    }

    // then: cancel the associated contribution
    $contribution_id_cancelled = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Cancelled');
    $result = civicrm_api('Contribution', 'create', array(
      'version'                   => 3,
      'id'                        => $contribution_id,
      'contribution_status_id'    => $contribution_id_cancelled,
      'cancel_reason'             => $cancel_reason,
      'cancel_date'               => date('YmdHis', strtotime($new_end_date_str))
    ));
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot properly end mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $result['error_message']), ts('Error'), 'warn');
    }

    // finally: remove contribution from any open SEPA groups
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE contribution_id = $contribution_id;");

    $lock->release();
    return TRUE;
  }

  /**
   * gracefully terminates RCUR mandates
   *
   * @param $mandate_id        int    mandate ID
   * @param $new_end_date_str  string requested end_date
   * @param $error_to_ui       bool   if TRUE will write errors to Session::status and return FALSE
   *                                     else will throw errors
   * @param $cancel_reason     string cancel reason to set
   * @return boolean success
   * @author endres -at- systopia.de
   */
  static function terminateMandate($mandate_id, $new_end_date_str, $cancel_reason=NULL, $error_to_ui = TRUE) {
    $contribution_id_pending = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      $error_message = sprintf(ts("Cannot terminate mandate [%s], batching in progress!", array('domain' => 'org.project60.sepa')), $mandate_id);
      if ($error_to_ui) {
        CRM_Core_Session::setStatus($error_message, ts('Error'), 'error');
        return FALSE;
      } else {
        throw new Exception($error_message);
      }
    }

     // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error'])) {
      $lock->release();

      $error_message = sprintf(ts("Cannot read mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $mandate['error_message']);
      if ($error_to_ui) {
        CRM_Core_Session::setStatus($error_message, ts('Error'), 'error');
        return FALSE;
      } else {
        throw new Exception($error_message);
      }
    }

    // check the mandate type
    if ( $mandate['type']=="OOFF" ) {
      return CRM_Sepa_BAO_SEPAMandate::terminateOOFFMandate($mandate_id, $new_end_date_str, $cancel_reason, $mandate);

    } elseif ( $mandate['type']!="RCUR" ) {
      $lock->release();
      $error_message = ts("You can only modify the end date of recurring contribution mandates.", array('domain' => 'org.project60.sepa'));
      if ($error_to_ui) {
        CRM_Core_Session::setStatus($error_message, ts('Error'), 'error');
        return FALSE;
      } else {
        throw new Exception($error_message);
      }
    }

    // load the contribution
    $contribution_id = $mandate['entity_id'];
    $contribution = civicrm_api('ContributionRecur', "getsingle", array('id'=>$contribution_id, 'version'=>3));
    if (isset($contribution['is_error']) && $contribution['is_error']) {
      $lock->release();
      $error_message = sprintf(ts("Cannot read contribution [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contribution_id, $contribution['error_message']);
      if ($error_to_ui) {
        CRM_Core_Session::setStatus($error_message, ts('Error'), 'error');
        return FALSE;
      } else {
        throw new Exception($error_message);
      }
    }

    // check the date
    $today = strtotime("today");
    $new_end_date = strtotime($new_end_date_str);

    if ($new_end_date < $today) {
      $lock->release();
      $error_message = sprintf(ts("You cannot set an end date in the past.", array('domain' => 'org.project60.sepa')), $contribution_id, $contribution['error_message']);
      if ($error_to_ui) {
        CRM_Core_Session::setStatus($error_message, ts('Error'), 'error');
        return FALSE;
      } else {
        throw new Exception($error_message);
      }
    }

    // actually set the date
    $query = array(
      'version'   => 3,
      'id'        => $contribution_id,
      'currency'  => $contribution['currency'],
      'end_date'  => date('YmdHis', $new_end_date));
    if ($cancel_reason) {
      // FIXME: cancel_reason does not exist in contribution_recur!!
      //$query['cancel_reason'] = $cancel_reason;
      $query['cancel_date'] = $query['end_date'];
    }

    $result = civicrm_api("ContributionRecur", "create", $query);
    if (isset($result['is_error']) && $result['is_error']) {
      $lock->release();
      $error_message = sprintf(ts("Cannot modify recurring contribution [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contribution_id, $result['error_message']);
      if ($error_to_ui) {
        CRM_Core_Session::setStatus($error_message, ts('Error'), 'error');
        return FALSE;
      } else {
        throw new Exception($error_message);
      }
    }

    // set the cancel reason
    if ($cancel_reason) {
      // ..and create a note, since the contribution_recur does not have cancel_reason

      // FIXME: this is a workaround due to CRM-14901,
      //   see https://github.com/Project60/org.project60.sepa/issues/401
      $create_note_query = "
      INSERT INTO civicrm_note (entity_table, entity_id, modified_date, subject, note, privacy)
             VALUES('civicrm_contribution_recur', %1, %2, 'cancel_reason', %3, 0)";
      $create_note_parameters = array(
        1 => array($contribution_id, 'Integer'),
        2 => array(date('YmdHis'), 'String'),
        3 => array($cancel_reason, 'String'),
      );
      CRM_Core_DAO::executeQuery($create_note_query, $create_note_parameters);
    }

    // find already created contributions that are now obsolete...
    $obsolete_ids = array();
    $deleted_ids = array();
    $obsolete_query = "
    SELECT id
    FROM civicrm_contribution
    WHERE receive_date > '$new_end_date_str'
      AND contribution_recur_id = $contribution_id
      AND contribution_status_id = $contribution_id_pending;";
    $obsolete_ids_query = CRM_Core_DAO::executeQuery($obsolete_query);
    while ($obsolete_ids_query->fetch()) {
      array_push($obsolete_ids, $obsolete_ids_query->id);
    }

    // ...and delete them:
    foreach ($obsolete_ids as $obsolete_id) {
      $delete_result = civicrm_api("Contribution", "delete", array('id'=>$obsolete_id, 'version'=>3));
      if (isset($delete_result['is_error']) && $delete_result['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot delete scheduled contribution [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $obsolete_id, $delete_result['error_message']), ts('Error'), 'warn');
      } else {
        array_push($deleted_ids, $obsolete_id);
      }
    }
    if (count($deleted_ids)) {
      // also, remove them from the groups
      $deleted_ids_string = implode(',', $deleted_ids);
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE contribution_id IN ($deleted_ids_string);");
    }

    // finally, let the API close the mandate if end_date is now
    if ($new_end_date <= $today) {
      $close_result = civicrm_api("SepaAlternativeBatching", "closeended", array('version'=>3));
      if (isset($close_result['is_error']) && $close_result['is_error']) {
        if ($error_to_ui) {
          CRM_Core_Session::setStatus(sprintf(ts("Closing Mandate failed. Error was: '%s'", array('domain' => 'org.project60.sepa')), $close_result['error_message']), ts('Error'), 'warn');
        }
      }
    }

    if ($error_to_ui) {
      CRM_Core_Session::setStatus(ts("New end date set.", array('domain' => 'org.project60.sepa')), ts('Mandate updated.', array('domain' => 'org.project60.sepa')), 'info');
      CRM_Core_Session::setStatus(ts("Please note, that any <i>closed</i> batches that include this mandate cannot be changed any more - all pending contributions will still be executed.", array('domain' => 'org.project60.sepa')), ts('Mandate updated.', array('domain' => 'org.project60.sepa')), 'warn');

      if (count($deleted_ids)) {
        CRM_Core_Session::setStatus(sprintf(ts("Successfully deleted %d now obsolete contributions.", array('domain' => 'org.project60.sepa')), count($deleted_ids)), ts('Mandate updated.', array('domain' => 'org.project60.sepa')), 'info');
      }
    }

    $lock->release();
    return TRUE;
  }



  /**
   * Allows you to modifiy certain mandate parameters of an active mandate:
   *  - amount
   *  - campaign_id
   *  - financial type
   *  - cycle_day
   *
   * Changes will take effect out to all future contributions,
   *  including already created ones in status 'Pending'
   *
   * @throws Exception
   * @return mixed success
   * @author endres -at- systopia.de
   */
  static function modifyMandate($mandate_id, $changes) {
    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      throw new Exception(ts("Cannot adjust mandate [%1], batching in progress!",
        array(1 => $mandate_id, 'domain' => 'org.project60.sepa')));
    }

    // load the mandate
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate_id));
    if ($mandate['type'] != 'RCUR') {
      $lock->release();
      throw new Exception(ts("You can only modify RCUR mandates.", array('domain' => 'org.project60.sepa')));
    }
    $contribution_rcur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $mandate['entity_id']));

    // collect the changes
    $changes_subjects = array();
    $changes_details = array();

    // BANK DETAIL CHANGES (applies to the MANDATE ENTITY)
    $bank_data_changes = array();
    if (!empty($changes['iban']) && $changes['iban'] != $mandate['iban']) {
      $bank_data_changes['iban'] = $changes['iban'];
      $changes_details[] = ts("IBAN changed from '%1' to '%2'",
        array(1 => $mandate['iban'], 2 => $changes['iban'], 'domain' => 'org.project60.sepa'));
    }
    if (!empty($changes['bic']) && $changes['bic'] != $mandate['bic']) {
      $bank_data_changes['bic'] = $changes['bic'];
      $changes_details[] = ts("BIC changed from '%1' to '%2'",
        array(1 => $mandate['bic'], 2 => $changes['bic'], 'domain' => 'org.project60.sepa'));
    }
    if (!empty($bank_data_changes)) {
      $bank_data_changes['id'] = $mandate['id'];
      $changes_subjects[] = ts("Bank details changed", array('domain' => 'org.project60.sepa'));
      civicrm_api3('SepaMandate', 'create', $bank_data_changes);
    }

    // AMOUNT CHANGE (applied to the CONTRIBUTION)
    $contribution_changes = array();
    if (!empty($changes['amount']) && $changes['amount'] != $contribution_rcur['amount']) {
      if ($changes['amount'] <= 0) {
        $lock->release();
        throw new Exception(ts("The amount has to be positive.", array('domain' => 'org.project60.sepa')));
      }

      // record the change
      $contribution_changes['amount'] = $changes['amount'];
      $change_variables = array('domain' => 'org.project60.sepa',
        1 => CRM_Utils_Money::format($contribution_rcur['amount'], $contribution_rcur['currency']),
        2 => CRM_Utils_Money::format($changes['amount'], $contribution_rcur['currency']));
      if ($changes['amount'] > $contribution_rcur['amount']) {
        $changes_subjects[] = ts("Amount increased");
        $changes_details[] = ts("Amount increased from %1 to %2", $change_variables);
      } else {
        $changes_subjects[] = ts("Amount decreased", $change_variables);
        $changes_details[] = ts("Amount decreased from %1 to %2", $change_variables);
      }
    }

    // FINANCIAL TYPE CHANGE
    if (!empty($changes['financial_type_id']) && $changes['financial_type_id'] != $contribution_rcur['financial_type_id']) {
      $financial_types = CRM_Contribute_PseudoConstant::financialType();
      if (empty($financial_types[$changes['financial_type_id']])) {
        $lock->release();
        throw new Exception(ts("Invalid financial type ID [%1] supplied.", array(1 => $changes['financial_type_id'], 'domain' => 'org.project60.sepa')));
      }
      $contribution_changes['financial_type_id'] = $changes['financial_type_id'];
      $changes_subjects[] = ts("Financial type changed", array('domain' => 'org.project60.sepa'));
      $changes_details[] = ts("Financial type changed from '%1' to '%2'.",
        array(1 => $financial_types[$contribution_rcur['financial_type_id']],
              2 => $financial_types[$changes['financial_type_id']],
              'domain' => 'org.project60.sepa'));
    }

    // CAMPAIGN CHANGE
    if (!empty($changes['campaign_id']) && $changes['campaign_id'] != $contribution_rcur['campaign_id']) {
      $contribution_changes['campaign_id'] = $changes['campaign_id'];
      $old_campaign = civicrm_api3('Campaign', 'getsingle', array('id' => $contribution_rcur['campaign_id']));
      $new_campaign = civicrm_api3('Campaign', 'getsingle', array('id' => $changes['campaign_id']));
      $changes_subjects[] = ts("Campaign changed", array('domain' => 'org.project60.sepa'));
      $changes_details[] = ts("Campaign changed from '%1' [%2] to '%3' [%4].",
        array(1 => $old_campaign['title'],
              2 => $contribution_rcur['campaign_id'],
              3 => $new_campaign['title'],
              4 => $changes['campaign_id'],
              'domain' => 'org.project60.sepa'));
    }

    // CYCLE DAY CHANGE
    if (!empty($changes['cycle_day']) && $changes['cycle_day'] != $contribution_rcur['cycle_day']) {
      $contribution_changes['cycle_day'] = $changes['cycle_day'];
      $changes_subjects[] = ts("Cycle day changed", array('domain' => 'org.project60.sepa'));
      $changes_details[] = ts("Cycle day changed from '%1' to '%2'.",
        array(1 => $contribution_rcur['cycle_day'],
          2 => $changes['cycle_day'],
          'domain' => 'org.project60.sepa'));
    }

    if (!empty($contribution_changes)) try {
      // change the recurring contribution
      $contribution_changes['id'] = $contribution_rcur['id'];
      $contribution_changes['currency'] = $contribution_rcur['currency'];
      civicrm_api3('ContributionRecur', 'create', $contribution_changes);

      // ...AND pending associated contributions
      $contributions2update = civicrm_api3('Contribution', 'get', array(
        'receive_date'           => array('>=' => date('YmdHis')),
        'contribution_recur_id'  => $contribution_rcur['id'],
        'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending'),
        'return'                 => 'id',
        'option.limit'           => 0
        ));

      // save details
      $changes_details[] = ts("%1 pending contributions were adjusted as well.",
          array(1 => $contributions2update['count'], 'domain' => 'org.project60.sepa'));

      // amount is 'total_amount' for contributions
      if (isset($contribution_changes['amount'])) {
        $contribution_changes['total_amount'] = $contribution_changes['amount'];
        unset($contribution_changes['amount']);
      }

      // now update all pending contributions
      foreach ($contributions2update['values'] as $contribution) {
        $contribution_changes['id'] = $contribution['id'];
        civicrm_api3('Contribution', 'create', $contribution_changes);
      }
    } catch (Exception $e) {
      $lock->release();
      throw $e;
    }



    // finally: generate acitivity
    if (!empty($changes_details)) try {
      if (count($changes_subjects) == 1) {
        self::generateModificationActivity($mandate, $changes_subjects[0], $changes_details);
      } else {
        $subject = ts("Multiple changes", array('domain' => 'org.project60.sepa'));
        self::generateModificationActivity($mandate, $subject, $changes_details);
      }
    } catch (Exception $e) {
      $lock->release();
      throw $e;
    }

    $lock->release();
    return $changes_details;
  }


  /**
   * Generate a new activity
   */
  public static function generateModificationActivity($mandate, $subject, $detail_lines) {
    // get / create activity type
    $activity_type_id = CRM_Core_OptionGroup::getValue('activity_type', 'sdd_update', 'name');
    if (!$activity_type_id) {
      // create activity type
      civicrm_api3('OptionValue', 'create', array(
        'label'           => ts('SEPA Mandate Updated', array('domain' => 'org.project60.sepa')),
        'name'            => 'sdd_update',
        'option_group_id' => 'activity_type',
        ));
      $activity_type_id = CRM_Core_OptionGroup::getValue('activity_type', 'sdd_update', 'name');
    }

    // compile activity
    $prefix = "[{$mandate['id']}] ";
    $mandate_link = CRM_Utils_System::url("civicrm/sepa/xmandate", 'reset=1&mid=' . $mandate['id'], TRUE);
    $mandate_ref = "<a href='$mandate_link'>{$mandate['reference']}</a>";
    $details = ts("The following changes have been applied to SDD mandate %1:", array(1 => $mandate_ref, 'domain' => 'org.project60.sepa'));
    $details .= "<ul><li>";
    $details .= implode("</li><li>", $detail_lines);
    $details .= "</li></ul>";

    // create activity
    civicrm_api3('Activity', 'create', array(
      'source_record_id'   => $mandate['id'],
      'activity_type_id'   => $activity_type_id,
      'activity_date_time' => date('YmdHis'),
      'details'            => $details,
      'subject'            => $prefix.$subject,
      'status_id'          => 2, // completed
      'source_contact_id'  => CRM_Core_Session::getLoggedInContactID(),
      'target_id'          => $mandate['contact_id']));
  }


  /**
   * changes the amount of a SEPA mandate
   *
   * @return boolean success
   * @author endres -at- systopia.de
   * @deprecated in favour of modifyMandate
   */
  static function adjustAmount($mandate_id, $adjusted_amount) {
    $adjusted_amount = (float) $adjusted_amount;
    $contribution_id_pending = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot adjust mandate [%s], batching in progress!", array('domain' => 'org.project60.sepa')), $mandate_id), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      return FALSE;
    }

     // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $mandate['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      $lock->release();
      return FALSE;
    }

    // check the mandate type
    if ( $mandate['type']!="RCUR" ) {
      CRM_Core_Session::setStatus(ts("You can only adjust the amount of recurring contribution mandates.", array('domain' => 'org.project60.sepa')), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      $lock->release();
      return FALSE;
    }

    // load the contribution
    $contribution_id = $mandate['entity_id'];
    $contribution = civicrm_api('ContributionRecur', "getsingle", array('id'=>$contribution_id, 'version'=>3));
    if (isset($contribution['is_error']) && $contribution['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read contribution [%s]. Error was: '%s'"), $contribution_id, $contribution['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // check the amount
    if ($adjusted_amount <= 0) {
      CRM_Core_Session::setStatus(ts("The amount cannot be changed to zero or less.", array('domain' => 'org.project60.sepa')), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      $lock->release();
      return FALSE;
    }

    // check the amount
    $old_amount = (float) $contribution['amount'];
    if ($old_amount == $adjusted_amount) {
      CRM_Core_Session::setStatus(ts("The requested amount is the same as the current one.", array('domain' => 'org.project60.sepa')), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      $lock->release();
      return FALSE;
    }

    // modify the amount in the recurring contribution
    $query = array(
      'version'   => 3,
      'id'        => $contribution_id,
      'amount'    => $adjusted_amount,
      'currency'  => $contribution['currency']);
    $result = civicrm_api("ContributionRecur", "create", $query);
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot modify recurring contribution [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contribution_id, $result['error_message'], array('domain' => 'org.project60.sepa')), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      $lock->release();
      return FALSE;
    }

    // find already created contributions. Those also need to be modified
    $contributions2adjust = array();
    $adjusted_ids = array();
    $find_need2adjust = "
    SELECT id
    FROM civicrm_contribution
    WHERE receive_date >= DATE(NOW())
      AND contribution_recur_id = $contribution_id
      AND contribution_status_id = $contribution_id_pending;";
    $contributions2adjust_query = CRM_Core_DAO::executeQuery($find_need2adjust);
    while ($contributions2adjust_query->fetch()) {
      $contributions2adjust[] = $contributions2adjust_query->id;
    }

    // ...and adjust them:
    foreach ($contributions2adjust as $contribution2adjust_id) {
      $update_result = civicrm_api("Contribution", "create", array(
        'version'                => 3,
        'id'                     => $contribution2adjust_id,
        'total_amount'           => $adjusted_amount,
        'contribution_status_id' => $contribution_id_pending));
      if (!empty($update_result['is_error'])) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot update scheduled contribution [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contribution2adjust_id, $update_result['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'warn');
      } else {
        array_push($adjusted_ids, $contribution2adjust_id);
      }
    }

    if (count($adjusted_ids)) {
      CRM_Core_Session::setStatus(sprintf(ts("Successfully updated %d generated contributions.", array('domain' => 'org.project60.sepa')), count($adjusted_ids)), ts('Mandate updated.', array('domain' => 'org.project60.sepa')), 'info');
    }

    $lock->release();
    return TRUE;
  }
}


