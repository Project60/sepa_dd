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

/**
 * back office mandate manipulation form
 *
 * @todo this implementation should use the CiviCRM Form pattern
 *        and should be refactored
 *
 * @package CiviCRM_SEPA
 *
 */

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_EditMandate extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('Edit SEPA Mandate', array('domain' => 'org.project60.sepa')));

    if (!isset($_REQUEST['mid'])) {
      die(ts("This page needs a mandate id ('mid') parameter.", array('domain' => 'org.project60.sepa')));
    } else {
      $mandate_id = (int) $_REQUEST['mid'];
    }

    if (isset($_REQUEST['action'])) {
      if ($_REQUEST['action']=='delete') {
        $this->deleteMandate($mandate_id);
        $this->assign('deleted_mandate', $mandate_id);
        parent::run();
        return;

      } else if ($_REQUEST['action']=='end') {
        $this->endMandate($mandate_id);

      } else if ($_REQUEST['action']=='cancel') {
        $this->cancelMandate($mandate_id);

      } else if ($_REQUEST['action']=='adjustamount') {
        $this->adjustAmount($mandate_id);
      } else if ($_REQUEST['action']=='changecyleday') {
        $this->changecyleday($mandate_id);
      } else {
        CRM_Core_Session::setStatus(sprintf(ts("Unkown action '%s'. Ignored.", array('domain' => 'org.project60.sepa')), $_REQUEST['action']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      }
    }

    // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error']) && $mandate['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $mandate['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      die(sprintf(ts("Cannot find mandate [%s].", array('domain' => 'org.project60.sepa')), $mandate_id));
    }

    // load the contribution
    $contribution_id = $mandate['entity_id'];
    $contribution_type = ($mandate['entity_table']=='civicrm_contribution')?'Contribution':'ContributionRecur';
    $contribution = civicrm_api($contribution_type, "getsingle", array('id'=>$contribution_id, 'version'=>3));
    if (isset($contribution['is_error']) && $contribution['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read contribution [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contribution_id, $contribution['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }

    // load the mandate's contact
    $contact1 = civicrm_api("Contact", "getsingle", array('id'=>$mandate['contact_id'], 'version'=>3));
    if (isset($contact1['is_error']) && $contact1['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read contact [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contact1, $contact1['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }

    // load the mandate's cancel reason(s)
    $mandate_cancel_reasons = array();
    if ($mandate['type'] == 'RCUR') {
      $contribution_recur_id = (int) $mandate['entity_id'];
      $cancel_reason_query = CRM_Core_DAO::executeQuery("SELECT note FROM civicrm_note WHERE entity_id = {$contribution_recur_id} AND entity_table = 'civicrm_contribution_recur' AND subject = 'cancel_reason' ORDER BY modified_date DESC;");
      while ($cancel_reason_query->fetch()) {
        $mandate_cancel_reasons[] = $cancel_reason_query->note;
      }
    }

    // load the contribtion's contact
    if ($mandate['contact_id']==$contribution['contact_id']) {
      $contact2 = $contact1;
    } else {
      $contact2 = civicrm_api("Contact", "getsingle", array('id'=>$contribution['contact_id'], 'version'=>3));
      if (isset($contact2['is_error']) && $contact2['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot read contact [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $contact2, $contact2['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      }
    }

    // load the creditor
    $cycle_days = [];
    if (!empty($mandate['creditor_id'])) {
      $creditor = civicrm_api("SepaCreditor", "getsingle", array('id'=>$mandate['creditor_id'], 'version'=>3));
      if (!empty($creditor['is_error'])) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot read creditor [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate['creditor_id'], $creditor['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      } else {
        $mandate['creditor_name'] = $creditor['label'];
        $cycle_days = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor['id']);
      }
    }

    // load the campaign
    if (isset($contribution['contribution_campaign_id']) && $contribution['contribution_campaign_id']) {
      $campaign_id = $contribution['contribution_campaign_id'];
    } elseif (isset($contribution['campaign_id']) && $contribution['campaign_id']) {
      $campaign_id = $contribution['campaign_id'];
    }
    if (isset($campaign_id)) {
      $campaign = civicrm_api("Campaign", "getsingle", array('id'=>$campaign_id, 'version'=>3));
      if (isset($campaign['is_error'])) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot read contact [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $campaign, $campaign['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      } else {
        $contribution['campaign'] = $campaign['title'];
      }
    } else {
      $contribution['campaign'] = '';
    }

    // prepare the data
    $mandate['status_text'] = CRM_Sepa_Logic_Status::translateMandateStatus($mandate['status'], TRUE);
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $contact1['link'] = CRM_Utils_System::url('civicrm/contact/view', "&reset=1&cid=".$contact1['id']);
    $contact2['link'] = CRM_Utils_System::url('civicrm/contact/view', "&reset=1&cid=".$contact2['id']);
    $contribution['financial_type'] = $financial_types[$contribution['financial_type_id']];
    if (isset($contribution['amount']) && $contribution['amount']) {
      // this is a recurring contribution
      $contribution['link']      = CRM_Utils_System::url('civicrm/contact/view/contributionrecur', "&reset=1&id=".$contribution['id']."&cid=".$contact2['id']);
      $contribution['currency']  = $contribution['currency'];
      $contribution['cycle']     = CRM_Utils_SepaOptionGroupTools::getFrequencyText($contribution['frequency_interval'], $contribution['frequency_unit'], true);
      $contribution['cycle_day_raw'] = $contribution['cycle_day'];
      $contribution['cycle_day'] = CRM_Sepa_Logic_Batching::getCycleDay($contribution, $mandate['creditor_id']);
      if (isset($contribution['next_sched_contribution_date'])) {
        $contribution['next_sched_contribution_date'] = substr($contribution['next_sched_contribution_date'], 0, 10); // the date is enough
      } else {
        $contribution['next_sched_contribution_date'] = ts("None", array('domain' => 'org.project60.sepa'));
      }
      if (isset($contribution['end_date']) && $contribution['end_date']) {
        $contribution['default_end_date'] = date('Y-m-d', strtotime($contribution['end_date']));
      } else {
        $contribution['default_end_date'] = date('Y-m-d');
      }
    } else {
      // this is a simple contribution
      $contribution['link'] = CRM_Utils_System::url('civicrm/contact/view/contribution', "reset=1&action=view&id=".$contribution['id']."&cid=".$contact2['id']);
      $contribution['amount'] = $contribution['total_amount'];
      $contribution['currency'] = $contribution['currency'];
    }

    // load eligeble templates
    // first: the dafault template
    $template_entry = civicrm_api('OptionValue', 'getsingle', array(
                                  'version'           => 3,
                                  'option_group_name' => 'msg_tpl_workflow_contribution',
                                  'name'              => 'sepa_mandate_pdf'));
    $tpl_ids = array();
    if (!empty($template_entry['id'])) {
      $query = "SELECT `id`, `msg_title`, `msg_subject`
              FROM   `civicrm_msg_template`
              WHERE  `is_active` = 1
              AND (  (`workflow_id` = '{$template_entry['id']}')
                  OR (`msg_title` LIKE 'SEPA%' AND `workflow_id` IS NULL) );";
      $result = CRM_Core_DAO::executeQuery($query);
      while ($result->fetch()) {
        $tpl_ids[] = array($result->id, $result->msg_title);
      }
    }

    $this->assign('sepa', $mandate);
    $this->assign('contribution', $contribution);
    $this->assign('contact1', $contact1);
    $this->assign('contact2', $contact2);
    $this->assign('mandate_cancel_reasons', $mandate_cancel_reasons);
    $this->assign('can_delete', CRM_Core_Permission::check('delete sepa groups'));
    $this->assign('can_modify', CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification'));
    $this->assign('sepa_templates', $tpl_ids);
    $this->assign('cycle_days', $cycle_days);

    parent::run();
  }


  function deleteMandate($mandate_id) {
    // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error']) && $mandate['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'", array('domain' => 'org.project60.sepa')), $mandate_id, $mandate['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      return;
    }

    if ( !($mandate['status']=="INIT" || $mandate['status']=="OOFF" || $mandate['status']=="FRST") ) {
      CRM_Core_Session::setStatus(sprintf(ts("Mandate [%s] is already in use! It cannot be deleted.", array('domain' => 'org.project60.sepa')), $mandate_id), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      return;
    }

    // then, delete the mandate
    // TODO: move the following into API or BAO
    $delete = civicrm_api('SepaMandate', "delete", array('id' => $mandate['id'], 'version'=>3));
    if (isset($delete['is_error']) && $delete['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Error deleting mandate: '%s'", array('domain' => 'org.project60.sepa')),
        $delete['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      return;
    }

    $rcontribution_count = 0;
    $contributions = array();

    // start by deleting the contributions
    if ($mandate['type']=="RCUR") {
      $cquery = civicrm_api('Contribution', "get",
        array('contribution_recur_id' => $mandate['entity_id'], 'version'=>3, 'option.limit' => 999));
      if (isset($cquery['is_error']) && $cquery['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot find contributions. Error was: '%s'", array('domain' => 'org.project60.sepa')),
          $cquery['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
        return;
      }

      foreach ($cquery['values'] as $contribution) {
        $delete = civicrm_api('Contribution', "delete",
          array('id' => $contribution['id'], 'version'=>3));
        if (isset($delete['is_error']) && $delete['is_error']) {
          CRM_Core_Session::setStatus(sprintf(ts("Error deleting contribution [%s]: '%s'", array('domain' => 'org.project60.sepa')), $contribution['id'], $delete['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
          return;
        }
        array_push($contributions, $contribution['id']);
      }

      $delete = civicrm_api('ContributionRecur', "delete",
        array('id' => $mandate['entity_id'], 'version'=>3));
      if (isset($delete['is_error']) && $delete['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Error deleting recurring contribution: '%s'", array('domain' => 'org.project60.sepa')),
          $delete['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
        return;
      }
      $rcontribution_count = 1;

    } else {    // $mandate['type']=="OOFF"
      $delete = civicrm_api('Contribution', "delete",
        array('id' => $mandate['entity_id'], 'version'=>3));
      if (isset($delete['is_error']) && $delete['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Error deleting contribution [%s]: '%s'", array('domain' => 'org.project60.sepa')), $mandate['entity_id'], $delete['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
        return;
      }
      array_push($contributions, $mandate['entity_id']);
    }

    // remove all contributions from the groups
    $contribution_id_list = implode(",", $contributions);
    if (strlen($contribution_id_list)>0) {
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE contribution_id IN ($contribution_id_list);");
    }

    CRM_Core_Session::setStatus(sprintf(ts("Succesfully deleted mandate [%s] and %s associated contribution(s)", array('domain' => 'org.project60.sepa')), $mandate_id, (count($contributions)+$rcontribution_count)), ts('Mandate deleted', array('domain' => 'org.project60.sepa')), 'info');
  }


  function endMandate($mandate_id) {
    $end_date = $_REQUEST['end_date'];
    if ($end_date) {
      if (isset($_REQUEST['end_reason'])) {
        CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate_id, $end_date, $_REQUEST['end_reason']);
      } else {
        CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate_id, $end_date);
      }
    } else {
      CRM_Core_Session::setStatus(sprintf(ts("You need to provide an end date.", array('domain' => 'org.project60.sepa'))), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }
  }


  function cancelMandate($mandate_id) {
    $cancel_reason = $_REQUEST['cancel_reason'];
    if ($cancel_reason) {
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate_id, date("Y-m-d"), $cancel_reason);
    } else {
      CRM_Core_Session::setStatus(sprintf(ts("You need to provide a cancel reason.", array('domain' => 'org.project60.sepa'))), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }
  }

  function adjustAmount($mandate_id) {
    // check if we are allowed to...
    if (CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')) {
      $adjusted_amount = (float) $_REQUEST['adjust_amount'];
      if ($adjusted_amount > 0) {
        if (CRM_Sepa_BAO_SEPAMandate::adjustAmount($mandate_id, $adjusted_amount)) {
          CRM_Core_Session::setStatus(sprintf(ts("The amount of this mandate was modified. You should send out a new prenotification to the debtor.", array('domain' => 'org.project60.sepa'))), ts('Advice', array('domain' => 'org.project60.sepa')), 'info');
        }
      } else {
        CRM_Core_Session::setStatus(sprintf(ts("Invalid amount. Mandate not modified.", array('domain' => 'org.project60.sepa'))), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
      }
    } else {
      CRM_Core_Session::setStatus(sprintf(ts("Modifying an existing mandate is currently not allowed. You can change this on the SEPA settings page.", array('domain' => 'org.project60.sepa'))), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }
  }

  function changecyleday($mandate_id) {
    if (CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')) {
      $new_cycle_day = $_REQUEST['new_cycle_day'];
      $statuses = CRM_Sepa_BAO_SEPAMandate::modifyMandate($mandate_id, ['cycle_day' => $new_cycle_day]);
      foreach($statuses as $status) {
        CRM_Core_Session::setStatus($status, '', 'info');
      }
    } else {
      CRM_Core_Session::setStatus(sprintf(ts("Modifying an existing mandate is currently not allowed. You can change this on the SEPA settings page.", array('domain' => 'org.project60.sepa'))), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }
  }
}
