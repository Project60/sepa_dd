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

class CRM_Sepa_Page_MandateTab extends CRM_Core_Page {

  /**
   * load mandate information
   */
  public function run() {
    CRM_Utils_System::setTitle(E::ts('SEPA Mandates'));
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
    $this->assign('date_format', '%Y-%m-%d');
    $this->assign('contact_id', $contact_id);


    // ==============================
    // ==            OOFF          ==
    // ==============================
    $ooff_list = array();
    $ooff_query = "
      SELECT
        civicrm_sdd_mandate.id             AS mandate_id,
        civicrm_contribution.id            AS contribution_id,
        civicrm_contribution.receive_date  AS receive_date,
        civicrm_sdd_mandate.status         AS status,
        civicrm_sdd_mandate.reference      AS reference,
        civicrm_financial_type.name        AS financial_type,
        civicrm_campaign.title             AS campaign,
        civicrm_contribution.total_amount  AS total_amount,
        civicrm_contribution.currency      AS currency,
        civicrm_contribution.cancel_reason AS cancel_reason,
        IF(civicrm_sdd_mandate.status IN ('INIT', 'OOFF'), 'sepa-active', 'sepa-inactive')
                                           AS class
      FROM civicrm_sdd_mandate
      LEFT JOIN civicrm_contribution   ON civicrm_contribution.id = civicrm_sdd_mandate.entity_id
      LEFT JOIN civicrm_financial_type ON civicrm_financial_type.id = civicrm_contribution.financial_type_id
      LEFT JOIN civicrm_campaign       ON civicrm_campaign.id = civicrm_contribution.campaign_id
      WHERE civicrm_sdd_mandate.contact_id = %1
        AND civicrm_sdd_mandate.type = 'OOFF'
        AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution'";

    $ooff_mandates = CRM_Core_DAO::executeQuery($ooff_query,
      array( 1 => array($contact_id, 'Integer')));

    while ($ooff_mandates->fetch()) {
      $ooff = array(
        'receive_date'   => $ooff_mandates->receive_date,
        'status_raw'     => $ooff_mandates->status,
        'status'         => CRM_Sepa_Logic_Status::translateMandateStatus($ooff_mandates->status, TRUE),
        'reference'      => $ooff_mandates->reference,
        'financial_type' => $ooff_mandates->financial_type,
        'campaign'       => $ooff_mandates->campaign,
        'total_amount'   => $ooff_mandates->total_amount,
        'currency'       => $ooff_mandates->currency,
        'cancel_reason'  => $ooff_mandates->cancel_reason,
        'class'          => $ooff_mandates->class,
      );

      // add links
      $ooff['view_link'] = CRM_Utils_System::url('civicrm/contact/view/contribution', "reset=1&id={$ooff_mandates->contribution_id}&cid={$contact_id}&action=view&context=contribution");
      if (CRM_Core_Permission::check('edit sepa mandates')) {
        $ooff['edit_link'] = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$ooff_mandates->mandate_id}");
      }

      $ooff_list[] = $ooff;
    }
    $this->assign('ooffs', $ooff_list);


    // ==============================
    // ==            RCUR          ==
    // ==============================
    $rcur_list = array();
    $rcur_query = "
      SELECT
        civicrm_sdd_mandate.id                                  AS mandate_id,
        civicrm_contribution_recur.id                           AS rcur_id,
        civicrm_contribution_recur.start_date                   AS start_date,
        civicrm_contribution_recur.end_date                     AS end_date,
        civicrm_contribution_recur.next_sched_contribution_date AS next_collection_date,
        last.receive_date                                       AS last_collection_date,
        last.cancel_reason                                      AS last_cancel_reason,
        civicrm_sdd_mandate.status                              AS status,
        civicrm_sdd_mandate.reference                           AS reference,
        cancel_reason.note                                      AS cancel_reason,
        civicrm_financial_type.name                             AS financial_type,
        civicrm_campaign.title                                  AS campaign,
        civicrm_sdd_mandate.reference                           AS reference,
        civicrm_contribution_recur.frequency_interval           AS frequency_interval,
        civicrm_contribution_recur.frequency_unit               AS frequency_unit,
        civicrm_contribution_recur.currency                     AS currency,
        civicrm_contribution_recur.amount                       AS amount,
        IF(civicrm_sdd_mandate.status IN ('FRST', 'RCUR', 'INIT', 'OOFF'), 'sepa-active', 'sepa-inactive')
                                                                AS class
      FROM civicrm_sdd_mandate
      LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_sdd_mandate.entity_id
      LEFT JOIN civicrm_financial_type     ON civicrm_financial_type.id = civicrm_contribution_recur.financial_type_id
      LEFT JOIN civicrm_campaign           ON civicrm_campaign.id = civicrm_contribution_recur.campaign_id
      LEFT JOIN civicrm_contribution last  ON last.receive_date = (SELECT MAX(receive_date) FROM civicrm_contribution
                                                                   WHERE contribution_recur_id = civicrm_contribution_recur.id
                                                                     AND contribution_status_id != 2)
      LEFT JOIN civicrm_note cancel_reason ON cancel_reason.entity_id = civicrm_contribution_recur.id
                                            AND cancel_reason.entity_table = 'civicrm_contribution_recur'
                                            AND cancel_reason.subject = 'cancel_reason'
      WHERE civicrm_sdd_mandate.contact_id = %1
        AND civicrm_sdd_mandate.type = 'RCUR'
        AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
      ORDER BY civicrm_contribution_recur.start_date DESC, civicrm_sdd_mandate.id DESC;";

    $mandate_ids = array();
    $rcur_mandates = CRM_Core_DAO::executeQuery($rcur_query,
      array( 1 => array($contact_id, 'Integer')));
    while ($rcur_mandates->fetch()) {
      $rcur = array(
        'mandate_id'           => $rcur_mandates->mandate_id,
        'start_date'           => $rcur_mandates->start_date,
        'cycle_day'            => $rcur_mandates->cycle_day,
        'status_raw'           => $rcur_mandates->status,
        'reference'            => $rcur_mandates->reference,
        'financial_type'       => $rcur_mandates->financial_type,
        'campaign'             => $rcur_mandates->campaign,
        'status'               => CRM_Sepa_Logic_Status::translateMandateStatus($rcur_mandates->status, TRUE),
        'frequency'            => CRM_Utils_SepaOptionGroupTools::getFrequencyText($rcur_mandates->frequency_interval, $rcur_mandates->frequency_unit, TRUE),
        'next_collection_date' => $rcur_mandates->next_collection_date,
        'last_collection_date' => $rcur_mandates->last_collection_date,
        'cancel_reason'        => $rcur_mandates->cancel_reason,
        'last_cancel_reason'   => $rcur_mandates->last_cancel_reason,
        'reference'            => $rcur_mandates->reference,
        'end_date'             => $rcur_mandates->end_date,
        'currency'             => $rcur_mandates->currency,
        'amount'               => $rcur_mandates->amount,
        'class'                => $rcur_mandates->class
      );

      // calculate annual amount
      if ($rcur_mandates->frequency_unit == 'year') {
        $rcur['total_amount'] = $rcur_mandates->amount / $rcur_mandates->frequency_interval;
      } elseif ($rcur_mandates->frequency_unit == 'month') {
        $rcur['total_amount'] = $rcur_mandates->amount * 12.0 / $rcur_mandates->frequency_interval;
      }

      // add links
      $rcur['view_link'] = CRM_Utils_System::url('civicrm/contact/view/contributionrecur', "reset=1&id={$rcur_mandates->rcur_id}&cid={$contact_id}&context=contribution");
      if (CRM_Core_Permission::check('edit sepa mandates')) {
        $rcur['edit_link'] = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$rcur_mandates->mandate_id}");
      }

      $rcur_list[$rcur_mandates->mandate_id] = $rcur;
    }

    // add cancellation info
    if (!empty($rcur_list)) {
      $mandate_id_list = implode(',', array_keys($rcur_list));
      $fail_sequence = "
        SELECT
         civicrm_sdd_mandate.id AS mandate_id,
         GROUP_CONCAT(
          IF(civicrm_contribution.contribution_status_id IN (1,2,5), '0', '1')
          SEPARATOR '')        AS fail_sequence
        FROM civicrm_sdd_mandate
        LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_sdd_mandate.entity_id
        LEFT JOIN civicrm_contribution       ON civicrm_contribution.contribution_recur_id = civicrm_contribution_recur.id
        WHERE civicrm_sdd_mandate.id IN ({$mandate_id_list})
          AND civicrm_sdd_mandate.type = 'RCUR'
          AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
          AND civicrm_contribution.id IS NOT NULL
        GROUP BY civicrm_sdd_mandate.id";
      $fail_query = CRM_Core_DAO::executeQuery($fail_sequence);
      while ($fail_query->fetch()) {
        if (preg_match("#(?<last_fails>1+)$#", $fail_query->fail_sequence, $match)) {
          $last_sequence = $match['last_fails'];
          $rcur_list[$fail_query->mandate_id]['fail_sequence'] = strlen($last_sequence);
        }
      }
    }

    $this->assign('rcurs', $rcur_list);

    parent::run();
  }

  /**
   * get the number of mandates
   * for the given contact
   */
  public static function getMandateCount($contact_id) {
    return CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(id) FROM civicrm_sdd_mandate
        WHERE contact_id = %1
          AND status IN ('FRST', 'RCUR', 'OOFF', 'INIT');",
            array( 1 => array($contact_id, 'Integer')));
  }

}
