<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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

/**
 * This class holds all SEPA batching functions
 */
class CRM_Sepa_Logic_Batching {

  /**
   * runs a batching update for all RCUR mandates for the given creditor
   *
   * @param $creditor_id  the creaditor to be batched
   * @param $mode         'FRST' or 'RCUR'
   * @param string $now   Overwrite what is used as "now" for batching, can be everything valid for strtotime, a "+n days" is added.
   */
  static function updateRCUR($creditor_id, $mode, $now = 'now', $offset=NULL, $limit=NULL) {
    // check lock
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      return "Batching in progress. Please try again later.";
    }
    $horizon = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.horizon", $creditor_id);
    $grace_period = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.grace", $creditor_id);
    $latest_date = date('Y-m-d', strtotime("$now +$horizon days"));

    $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.$mode.notice", $creditor_id);
    // (virtually) move ahead notice_days, but also go back grace days
    $now = strtotime("$now +$rcur_notice days -$grace_period days");
    $now = strtotime(date('Y-m-d', $now));        // round to full day
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $payment_instrument_id = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', $mode);

    if ($offset !== NULL && $limit!==NULL) {
      $batch_clause = "LIMIT {$limit} OFFSET {$offset}";
    } else {
      $batch_clause = "";
    }

    // RCUR-STEP 1: find all active/pending RCUR mandates within the horizon that are NOT in a closed batch
    $sql_query = "
      SELECT
        mandate.id AS mandate_id,
        mandate.contact_id AS mandate_contact_id,
        mandate.entity_id AS mandate_entity_id,
        mandate.source AS mandate_source,
        mandate.creditor_id AS mandate_creditor_id,
        first_contribution.receive_date AS mandate_first_executed,
        rcontribution.cycle_day AS cycle_day,
        rcontribution.frequency_interval AS frequency_interval,
        rcontribution.frequency_unit AS frequency_unit,
        rcontribution.start_date AS start_date,
        rcontribution.cancel_date AS cancel_date,
        rcontribution.end_date AS end_date,
        rcontribution.amount AS rc_amount,
        rcontribution.is_test AS rc_is_test,
        rcontribution.contact_id AS rc_contact_id,
        rcontribution.financial_type_id AS rc_financial_type_id,
        rcontribution.contribution_status_id AS rc_contribution_status_id,
        rcontribution.currency AS rc_currency,
        rcontribution.campaign_id AS rc_campaign_id,
        rcontribution.payment_instrument_id AS rc_payment_instrument_id
      FROM civicrm_sdd_mandate AS mandate
      INNER JOIN civicrm_contribution_recur AS rcontribution       ON mandate.entity_id = rcontribution.id AND mandate.entity_table = 'civicrm_contribution_recur'
      LEFT  JOIN civicrm_contribution       AS first_contribution  ON mandate.first_contribution_id = first_contribution.id
      WHERE mandate.type = 'RCUR'
        AND mandate.status = '$mode'
        AND mandate.creditor_id = $creditor_id
        {$batch_clause};";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $relevant_mandates = array();
    while ($results->fetch()) {
      // TODO: sanity checks?
      $relevant_mandates[$results->mandate_id] = array(
          'mandate_id'                    => $results->mandate_id,
          'mandate_contact_id'            => $results->mandate_contact_id,
          'mandate_entity_id'             => $results->mandate_entity_id,
          'mandate_first_executed'        => $results->mandate_first_executed,
          'mandate_source'                => $results->mandate_source,
          'mandate_creditor_id'           => $results->mandate_creditor_id,
          'cycle_day'                     => $results->cycle_day,
          'frequency_interval'            => $results->frequency_interval,
          'frequency_unit'                => $results->frequency_unit,
          'start_date'                    => $results->start_date,
          'end_date'                      => $results->end_date,
          'cancel_date'                   => $results->cancel_date,
          'rc_contact_id'                 => $results->rc_contact_id,
          'rc_amount'                     => $results->rc_amount,
          'rc_currency'                   => $results->rc_currency,
          'rc_financial_type_id'          => $results->rc_financial_type_id,
          'rc_contribution_status_id'     => $results->rc_contribution_status_id,
          'rc_campaign_id'                => $results->rc_campaign_id,
          'rc_payment_instrument_id'      => $results->rc_payment_instrument_id,
          'rc_is_test'                    => $results->rc_is_test,
        );
    }

    // RCUR-STEP 2: calculate next execution date
    $mandates_by_nextdate = array();
    foreach ($relevant_mandates as $mandate) {
      $next_date = self::getNextExecutionDate($mandate, $now, ($mode=='FRST'));
      if ($next_date==NULL) continue;
      if ($next_date > $latest_date) continue;

      if (!isset($mandates_by_nextdate[$next_date]))
        $mandates_by_nextdate[$next_date] = array();
      array_push($mandates_by_nextdate[$next_date], $mandate);
    }
    // apply any deferrals:
    $collection_dates = array_keys($mandates_by_nextdate);
    foreach ($collection_dates as $collection_date) {
      $deferred_collection_date = $collection_date;
      self::deferCollectionDate($deferred_collection_date, $creditor_id);
      if ($deferred_collection_date != $collection_date) {
        $mandates_by_nextdate[$deferred_collection_date] = $mandates_by_nextdate[$collection_date];
        unset($mandates_by_nextdate[$collection_date]);
      }
    }


    // RCUR-STEP 3: find already created contributions
    $existing_contributions_by_recur_id = array();
    foreach ($mandates_by_nextdate as $collection_date => $mandates) {
      $mandates_by_payment_instrument = [];
      foreach ($mandates as $mandate) {
        $mandates_by_payment_instrument[$mandate['rc_payment_instrument_id']][] = $mandate['mandate_entity_id'];
      }
      foreach($mandates_by_payment_instrument as $mandate_payment_instrument_id => $rcontrib_ids) {
        $rcontrib_id_strings = implode(',', $rcontrib_ids);
        $rcontrib_payment_instrument = $mode == 'FRST' ? $payment_instrument_id : $mandate_payment_instrument_id;

        $sql_query = "
          SELECT
            contribution.contribution_recur_id AS contribution_recur_id,
            contribution.id                    AS contribution_id
          FROM civicrm_contribution contribution
          LEFT JOIN civicrm_sdd_contribution_txgroup ctxg ON ctxg.contribution_id = contribution.id
          LEFT JOIN civicrm_sdd_txgroup               txg ON txg.id = ctxg.txgroup_id
          WHERE contribution.contribution_recur_id IN ({$rcontrib_id_strings})
            AND DATE(contribution.receive_date) = DATE('{$collection_date}')
            AND (txg.type IS NULL OR txg.type IN ('RCUR', 'FRST'))
            AND contribution.payment_instrument_id = {$rcontrib_payment_instrument};";
        $results = CRM_Core_DAO::executeQuery($sql_query);
        while ($results->fetch()) {
          $existing_contributions_by_recur_id[$results->contribution_recur_id] = $results->contribution_id;
        }
      }
    }

    // RCUR-STEP 4: create the missing contributions, store all in $mandate['mandate_entity_id']
    foreach ($mandates_by_nextdate as $collection_date => $mandates) {
      foreach ($mandates as $index => $mandate) {
        $recur_id = $mandate['mandate_entity_id'];
        if (isset($existing_contributions_by_recur_id[$recur_id])) {
          // if the contribution already exists, store it
          $contribution_id = $existing_contributions_by_recur_id[$recur_id];
          unset($existing_contributions_by_recur_id[$recur_id]);
          $mandates_by_nextdate[$collection_date][$index]['mandate_entity_id'] = $contribution_id;
        } else {
          // else: create it
          $contribution_data = array(
              "version"                             => 3,
              "total_amount"                        => $mandate['rc_amount'],
              "currency"                            => $mandate['rc_currency'],
              "receive_date"                        => $collection_date,
              "contact_id"                          => $mandate['rc_contact_id'],
              "contribution_recur_id"               => $recur_id,
              "source"                              => $mandate['mandate_source'],
              "financial_type_id"                   => $mandate['rc_financial_type_id'],
              "contribution_status_id"              => $mandate['rc_contribution_status_id'],
              "campaign_id"                         => $mandate['rc_campaign_id'],
              "is_test"                             => $mandate['rc_is_test'],
              "payment_instrument_id"               => $mode == 'FRST' ? $payment_instrument_id : $mandate['rc_payment_instrument_id'],
            );
          $contribution = civicrm_api('Contribution', 'create', $contribution_data);
          if (empty($contribution['is_error'])) {
            // Success! Call the post_create hook
            CRM_Utils_SepaCustomisationHooks::installment_created($mandate['mandate_id'], $recur_id, $contribution['id']);

            // 'mandate_entity_id' will now be overwritten with the contribution instance ID
            //  to allow compatibility in with OOFF groups in the syncGroups function
            $mandates_by_nextdate[$collection_date][$index]['mandate_entity_id'] = $contribution['id'];
          } else {
            // in case of an error, we will unset 'mandate_entity_id', so it cannot be
            //  interpreted as the contribution instance ID (see above)
            unset($mandates_by_nextdate[$collection_date][$index]['mandate_entity_id']);

            // log the error
            Civi::log()->debug("org.project60.sepa: batching:updateRCUR/createContrib ".$contribution['error_message']);

            // TODO: Error handling?
          }
          unset($existing_contributions_by_recur_id[$recur_id]);
        }
      }
    }

    // delete unused contributions:
    foreach ($existing_contributions_by_recur_id as $contribution_id) {
      // TODO: is this needed?
      Civi::log()->debug("org.project60.sepa: batching: contribution $contribution_id should be deleted...");
    }

    // step 5: find all existing OPEN groups
    $sql_query = "
      SELECT
        txgroup.collection_date AS collection_date,
        txgroup.id AS txgroup_id
      FROM civicrm_sdd_txgroup AS txgroup
      WHERE txgroup.type = '$mode'
        AND txgroup.sdd_creditor_id = $creditor_id
        AND txgroup.status_id = $group_status_id_open;";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $existing_groups = array();
    while ($results->fetch()) {
      $collection_date = date('Y-m-d', strtotime($results->collection_date));
      $existing_groups[$collection_date] = $results->txgroup_id;
    }

    // step 6: sync calculated group structure with existing (open) groups
    self::syncGroups($mandates_by_nextdate, $existing_groups, $mode, 'RCUR', $rcur_notice, $creditor_id, $offset!==NULL, $offset===0);

    $lock->release();
  }



  /**
   * runs a batching update for all OOFF mandates
   *
   * @param $creditor_id  the creditor ID to run this for
   * @param string $now   Overwrite what is used as "now" for batching, can be everything valid for strtotime, a "+n days" is added.
   * @param $offset       used for segmented updates
   * @param $limit        used for segmented updates
   */
  static function updateOOFF($creditor_id, $now = 'now', $offset=NULL, $limit=NULL) {
    // check lock
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      return "Batching in progress. Please try again later.";
    }

    if ($offset !== NULL && $limit!==NULL) {
      $batch_clause = "LIMIT {$limit} OFFSET {$offset}";
    } else {
      $batch_clause = "";
    }

    $horizon = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.horizon', $creditor_id);
    $ooff_notice = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.notice', $creditor_id);
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $date_limit = date('Y-m-d', strtotime("$now +$horizon days"));

    // step 1: find all active/pending OOFF mandates within the horizon that are NOT in a closed batch
    $sql_query = "
      SELECT
        mandate.id                AS mandate_id,
        mandate.contact_id        AS mandate_contact_id,
        mandate.entity_id         AS mandate_entity_id,
        contribution.receive_date AS start_date
      FROM civicrm_sdd_mandate AS mandate
      INNER JOIN civicrm_contribution AS contribution  ON mandate.entity_id = contribution.id AND mandate.entity_table = 'civicrm_contribution'
      WHERE contribution.receive_date <= DATE('$date_limit')
        AND mandate.type = 'OOFF'
        AND mandate.status = 'OOFF'
        AND mandate.creditor_id = $creditor_id
        {$batch_clause};";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $relevant_mandates = array();
    while ($results->fetch()) {
      // TODO: sanity checks?
      $relevant_mandates[$results->mandate_id] = array(
          'mandate_id'          => $results->mandate_id,
          'mandate_contact_id'  => $results->mandate_contact_id,
          'mandate_entity_id'   => $results->mandate_entity_id,
          'start_date'          => $results->start_date,
        );
    }

    // step 2: group mandates in collection dates
    $calculated_groups = array();
    $earliest_collection_date = date('Y-m-d', strtotime("$now +$ooff_notice days"));
    $latest_collection_date = '';

    foreach ($relevant_mandates as $mandate_id => $mandate) {
      $collection_date = date('Y-m-d', strtotime($mandate['start_date']));
      if ($collection_date <= $earliest_collection_date) {
        $collection_date = $earliest_collection_date;
      }

      if (!isset($calculated_groups[$collection_date])) {
        $calculated_groups[$collection_date] = array();
      }

      array_push($calculated_groups[$collection_date], $mandate);

      if ($collection_date > $latest_collection_date) {
        $latest_collection_date = $collection_date;
      }
    }
    if (!$latest_collection_date) {
      // nothing to do...
      return array();
    }

    // step 3: find all existing OPEN groups in the horizon
    $sql_query = "
      SELECT
        txgroup.collection_date AS collection_date,
        txgroup.id AS txgroup_id
      FROM civicrm_sdd_txgroup AS txgroup
      WHERE txgroup.sdd_creditor_id = $creditor_id
        AND txgroup.type = 'OOFF'
        AND txgroup.status_id = $group_status_id_open;";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $existing_groups = array();
    while ($results->fetch()) {
      $collection_date = date('Y-m-d', strtotime($results->collection_date));
      $existing_groups[$collection_date] = $results->txgroup_id;
    }

    // step 4: sync calculated group structure with existing (open) groups
    self::syncGroups($calculated_groups, $existing_groups, 'OOFF', 'OOFF', $ooff_notice, $creditor_id, $offset!==NULL, $offset===0);

    $lock->release();
  }



  /**
   * Maintenance: Close all mandates that have expired
   */
  static function closeEnded() {
    // check lock
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      return "Batching in progress. Please try again later.";
    }

    $contribution_status_closed = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');

    // first, load all of the mandates, that have run out
    $sql_query = "
      SELECT
        mandate.id              AS mandate_id,
        mandate.date            AS mandate_date,
        mandate.entity_id       AS mandate_entity_id,
        mandate.creation_date   AS mandate_creation_date,
        mandate.validation_date AS mandate_validation_date,
        rcontribution.currency  AS currency,
        rcontribution.end_date  AS end_date
      FROM civicrm_sdd_mandate AS mandate
      INNER JOIN civicrm_contribution_recur AS rcontribution       ON mandate.entity_id = rcontribution.id AND mandate.entity_table = 'civicrm_contribution_recur'
      WHERE mandate.type = 'RCUR'
        AND mandate.status IN ('RCUR','FRST')
        AND end_date <= DATE(NOW());";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    $mandates_to_end = array();
    while ($results->fetch()) {
      $mandates_to_end[] = array(
        'mandate_id'      => $results->mandate_id,
        'recur_id'        => $results->mandate_entity_id,
        'creation_date'   => $results->mandate_creation_date,
        'validation_date' => $results->mandate_validation_date,
        'date'            => $results->mandate_date,
        'currency'        => $results->currency);
    }

    // then, end them one by one
    foreach ($mandates_to_end as $mandate_to_end) {
      $change_mandate = civicrm_api('SepaMandate', 'create', array(
        'id'                      => $mandate_to_end['mandate_id'],
        'date'                    => $mandate_to_end['date'],
        'creation_date'           => $mandate_to_end['creation_date'],
        'validation_date'         => $mandate_to_end['validation_date'],
        'status'                  => 'COMPLETE',
        'version'                 => 3));
      if (isset($change_mandate['is_error']) && $change_mandate['is_error']) {
        $lock->release();
        return sprintf("Couldn't set mandate '%s' to 'complete. Error was: '%s'", $mandates_to_end['mandate_id'], $change_mandate['error_message']);
      }

      $change_rcur = civicrm_api('ContributionRecur', 'create', array(
        'id'                      => $mandate_to_end['recur_id'],
        'contribution_status_id'  => $contribution_status_closed,
        'modified_date'           => date('YmdHis'),
        'currency'                => $mandate_to_end['currency'],
        'version'                 => 3));
      if (isset($change_rcur['is_error']) && $change_rcur['is_error']) {
        $lock->release();
        return sprintf("Couldn't set recurring contribution '%s' to 'complete. Error was: '%s'", $mandates_to_end['recur_id'], $change_rcur['error_message']);
      }
    }

    $lock->release();
  }







  /****************************************************************************
   **                                                                        **
   **                            HELPERS                                     **
   **                                                                        **
   ****************************************************************************/

  /**
   * subroutine to create the group/contribution structure as calculated
   * @param $calculated_groups  array [collection_date] -> array(contributions) as calculated
   * @param $existing_groups    array [collection_date] -> array(contributions) as currently present
   * @param $mode               SEPA mode (OOFF, RCUR, FRST)
   * @param $type               SEPA type (RCUR, FRST)
   * @param $notice             notice days
   * @param $creditor_id        SDD creditor ID
   * @param $partial_groups     Is this a partial update?
   * @param $partial_first      Is this the first call in a partial update?
   */
  protected static function syncGroups($calculated_groups, $existing_groups, $mode, $type, $notice, $creditor_id, $partial_groups=FALSE, $partial_first=FALSE) {
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    foreach ($calculated_groups as $collection_date => $mandates) {
      // check if we need to defer the collection date (e.g. due to bank holidays)
      self::deferCollectionDate($collection_date, $creditor_id);

      if (!isset($existing_groups[$collection_date])) {
        // this group does not yet exist -> create

        // find unused reference
        $reference = "TXG-${creditor_id}-${mode}-${collection_date}";
        $counter = 0;
        while (self::referenceExists($reference)) {
          $counter += 1;
          $reference = "TXG-${creditor_id}-${mode}-${collection_date}--".$counter;
        }

        // call the hook
        CRM_Utils_SepaCustomisationHooks::modify_txgroup_reference($reference, $creditor_id, $mode, $collection_date);

        $group = civicrm_api('SepaTransactionGroup', 'create', array(
            'version'                 => 3,
            'reference'               => $reference,
            'type'                    => $mode,
            'collection_date'         => $collection_date,
            'latest_submission_date'  => date('Y-m-d', strtotime("-$notice days", strtotime($collection_date))),
            'created_date'            => date('Y-m-d'),
            'status_id'               => $group_status_id_open,
            'sdd_creditor_id'         => $creditor_id,
            ));
        if (!empty($group['is_error'])) {
          // TODO: Error handling
          Civi::log()->debug("org.project60.sepa: batching:syncGroups/createGroup ".$group['error_message']);
        }
      } else {
        $group = civicrm_api('SepaTransactionGroup', 'getsingle', array('version' => 3, 'id' => $existing_groups[$collection_date], 'status_id' => $group_status_id_open));
        if (!empty($group['is_error'])) {
          // TODO: Error handling
          Civi::log()->debug("org.project60.sepa: batching:syncGroups/getGroup ".$group['error_message']);
        }
        unset($existing_groups[$collection_date]);
      }

      // now we have the right group. Prepare some parameters...
      $group_id = $group['id'];
      $entity_ids = array();
      foreach ($mandates as $mandate) {
        // remark: "mandate_entity_id" in this case means the contribution ID
        if (empty($mandate['mandate_entity_id'])) {
          // this shouldn't happen
          Civi::log()->debug("org.project60.sepa: batching:syncGroups mandate with bad mandate_entity_id ignored:" . $mandate['mandate_id']);
        } else {
          array_push($entity_ids, $mandate['mandate_entity_id']);
        }
      }
      if (count($entity_ids)<=0) continue;

      // now, filter out the entity_ids that are are already in a non-open group
      //   (DO NOT CHANGE CLOSED GROUPS!)
      $entity_ids_list = implode(',', $entity_ids);
      $already_sent_contributions = CRM_Core_DAO::executeQuery("
        SELECT contribution_id
        FROM civicrm_sdd_contribution_txgroup
        LEFT JOIN civicrm_sdd_txgroup ON civicrm_sdd_contribution_txgroup.txgroup_id = civicrm_sdd_txgroup.id
        WHERE contribution_id IN ($entity_ids_list)
         AND  civicrm_sdd_txgroup.status_id <> $group_status_id_open;");
      while ($already_sent_contributions->fetch()) {
        $index = array_search($already_sent_contributions->contribution_id, $entity_ids);
        if ($index !== false) unset($entity_ids[$index]);
      }
      if (count($entity_ids)<=0) continue;

      // remove all the unwanted entries from our group
      $entity_ids_list = implode(',', $entity_ids);
      if (!$partial_groups || $partial_first) {
        CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$group_id AND contribution_id NOT IN ($entity_ids_list);");
      }

      // remove all our entries from other groups, if necessary
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id!=$group_id AND contribution_id IN ($entity_ids_list);");

      // now check which ones are already in our group...
      $existing = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$group_id AND contribution_id IN ($entity_ids_list);");
      while ($existing->fetch()) {
        // remove from entity ids, if in there:
        if(($key = array_search($existing->contribution_id, $entity_ids)) !== false) {
          unset($entity_ids[$key]);
        }
      }

      // the remaining must be added
      foreach ($entity_ids as $entity_id) {
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_sdd_contribution_txgroup (txgroup_id, contribution_id) VALUES ($group_id, $entity_id);");
      }
    }

    if (!$partial_groups) {
      // do some cleanup
      CRM_Sepa_Logic_Group::cleanup($mode);
    }
  }


  /**
   * Check if a transaction group reference is already in use
   */
  public static function referenceExists($reference) {
    $query = civicrm_api('SepaTransactionGroup', 'getsingle', array('reference'=>$reference, 'version'=>3));
    // this should return an error, if the group exists
    return !(isset($query['is_error']) && $query['is_error']);
  }

  /**
   * Calculate the next execution date for a recurring contribution
   */
  public static function getNextExecutionDate($rcontribution, $now, $FRST = FALSE) {
    $now =  strtotime(date('Y-m-d', $now));     // ignore time of day
    $cycle_day = $rcontribution['cycle_day'];
    $interval = $rcontribution['frequency_interval'];
    $unit = $rcontribution['frequency_unit'];

    // calculate the first date
    $start_date = strtotime($rcontribution['start_date']);
    $next_date = mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));
    if (!$FRST && !empty($rcontribution['mandate_first_executed'])) {
      // if there is a first contribution, that dictates the cycle (12am)
      $next_date = strtotime(date('Y-m-d', strtotime($rcontribution['mandate_first_executed'])));

      // go back to last cycle day (in case the collection was delayed)
      while (date('j', $next_date) != $cycle_day) {
        $next_date = strtotime("-1 day", $next_date);
      }

      // then add one full cyle (to avoid problems with the FRST/RCUR status change)
      $next_date = strtotime("+{$interval} {$unit}", $next_date);
    }

    // for the FRST (first, start) contribution, only
    //  advance monthly, see ticket #309
    if ($FRST && ($unit=='month' || $unit=='year')) {
      $search_step = "+1 month";
    } else {
      $search_step = "+{$interval} {$unit}";
    }

    // take the first next_date that is in the future
    while ($next_date < $now) {
      $next_date = strtotime($search_step, $next_date);
    }

    // and check if it's not after the end_date
    $return_date = date('Y-m-d', $next_date);

    // Call a hook so extensions could alter the next collection date.
    CRM_Utils_SepaCustomisationHooks::alter_next_collection_date($return_date, $rcontribution);
    if (!empty($rcontribution['end_date']) && strtotime($rcontribution['end_date']) < strtotime($return_date)) {
      return NULL;
    }
    // ..or the cancel_date
    if (!empty($rcontribution['cancel_date']) && strtotime($rcontribution['cancel_date']) < strtotime($next_date)) {
      return NULL;
    }

    return $return_date;
  }

  /**
   * Get a string representation of the recurring contribution cycle day.
   * For monthly payments, this would be the cycle day,
   * while for annual payments this would be the cycle day and the month.
   */
  public static function getCycleDay($rcontribution, $creditor_id) {
    $cycle_day = $rcontribution['cycle_day'];
    $interval  = $rcontribution['frequency_interval'];
    $unit      = $rcontribution['frequency_unit'];
    if ($unit == 'year' || ($unit == 'month' && !($interval % 12))) {
      // this is an annual payment
      if (!empty($rcontribution['mandate_first_executed'])) {
        $date = $rcontribution['mandate_first_executed'];
      } else {
        $mandate = civicrm_api3('SepaMandate', 'getsingle', array(
            'entity_table' => 'civicrm_contribution_recur',
            'entity_id'    => $rcontribution['id'],
            'return'       => 'status'));

        if ($mandate['status'] == 'RCUR') {
          // support for RCUR without first contribution
          $now = strtotime("now");
          $date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, FALSE);

        } else {
          // hasn't been collected yet
          $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor_id);
          $now = strtotime("now +$rcur_notice days");
          $date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, TRUE);
        }
      }
      return CRM_Utils_Date::customFormat($date, E::ts("%B %E%f"));
    } elseif ($unit == 'week') {
      // FIXME: weekly not supported yet
      return '';

    } else {
      // this is a x-monthly payment
      return ts("%1.", array(1=>$cycle_day, 'domain' => 'org.project60.sepa'));
    }
  }

  /**
   * Get a string representation of the recurring contribution cycle,
   * e.g. 'weekly' or 'annually'
   */
  public static function getCycle($rcontribution) {
    $interval  = $rcontribution['frequency_interval'];
    $unit      = $rcontribution['frequency_unit'];
    return CRM_Utils_SepaOptionGroupTools::getFrequencyText($interval, $unit, TRUE);
  }

  /**
   * Apply any (date-based) defers on the collection date
   */
  public static function deferCollectionDate(&$collection_date, $creditor_id) {
    // first check if the weekends are to be excluded
    $exclude_weekends = CRM_Sepa_Logic_Settings::getGenericSetting('exclude_weekends');
    if ($exclude_weekends) {
      // skip (western) week ends, if the option is activated.
      $day_of_week = date('N', strtotime($collection_date));
      if ($day_of_week > 5) {
        // this is a weekend -> skip to Monday
        $defer_days = 8 - $day_of_week;
        $collection_date = date('Y-m-d', strtotime("+$defer_days day", strtotime($collection_date)));
      }
    }

    // also run the hook, in case somebody has implemented special holidays
    CRM_Utils_SepaCustomisationHooks::defer_collection_date($collection_date, $creditor_id);
  }
}
