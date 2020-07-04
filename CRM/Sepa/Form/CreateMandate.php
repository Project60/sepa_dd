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
 * SEPA Create Mandate form
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Sepa_Form_CreateMandate extends CRM_Core_Form {

  protected $create_mode = 'create'; // or 'clone' or 'replace'
  protected $contact_id  = null;
  protected $replace_id  = null;
  protected $clone_id    = null;
  protected $rpl_date    = null;
  protected $rpl_reason  = null;
  protected $old_mandate = null;
  protected $old_contrib = null;


  public function buildQuickForm() {
    // get parameters
    $this->contact_id  = (int) CRM_Utils_Request::retrieve('cid', 'Positive');
    $this->replace_id  = (int) CRM_Utils_Request::retrieve('replace', 'Positive');
    $this->clone_id    = (int) CRM_Utils_Request::retrieve('clone', 'Positive');
    $this->rpl_reason  = CRM_Utils_Request::retrieve('replace_reason', 'String');
    $this->rpl_date    = CRM_Utils_Request::retrieve('replace_date', 'String');

    // prepare replace/clone
    if ($this->replace_id || $this->clone_id) {
      // set create_mode
      if ($this->replace_id) {
        $this->create_mode = 'replace';
        $mandate_id = $this->replace_id;
      } else {
        $this->create_mode = 'clone';
        $mandate_id = $this->clone_id;
      }

      // load mandate
      $this->old_mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate_id));
      if ($this->old_mandate['type'] != 'RCUR') {
        CRM_Core_Error::fatal(E::ts("You can only replace RCUR mandates"));
      }
      $this->contact_id = (int) $this->old_mandate['contact_id'];

      $this->old_contrib = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->old_mandate['entity_id']));
    }

    if (empty($this->contact_id)) {
      CRM_Core_Error::fatal(E::ts("No contact ID (cid) given."));
    }

    // load the contact and set the title
    $contact_name = civicrm_api3('Contact', 'getvalue', array(
        'id'     => $this->contact_id,
        'return' => 'display_name'));
    switch ($this->create_mode) {
      case 'clone':
        CRM_Utils_System::setTitle(E::ts("Clone SEPA Mandate for Contact [%1]: %2", array(
            1 => $this->contact_id, 2 => $contact_name)));
        break;

      case 'replace':
        CRM_Utils_System::setTitle(E::ts("Replace SEPA Mandate for Contact [%1]: %2", array(
            1 => $this->contact_id, 2 => $contact_name)));
        $this->assign('replace_mandate_reference', $this->old_mandate['reference']);
        break;

      default:
      case 'create':
        CRM_Utils_System::setTitle(E::ts("Create new SEPA Mandate for Contact [%1]: %2", array(
            1 => $this->contact_id, 2 => $contact_name)));
        break;

    }

    // assign the mode
    $this->assign('create_mode', $this->create_mode);

    // add contact_id field
    $this->add('hidden', 'cid', $this->contact_id);

    // add creditor field
    $js_vars['creditor_data'] = $this->getCreditors();
    $this->add(
      'select',
      'creditor_id',
      E::ts('Creditor'),
      $this->getCreditorList($js_vars['creditor_data']),
      TRUE,
       array('class' => 'crm-select2')
    );

    // add financial type
    $this->add(
        'select',
        'financial_type_id',
        E::ts('Financial Type'),
        $this->getFinancialTypeList(),
        TRUE,
        array('class' => 'crm-select2')
    );

    // add campaign
    $this->add(
        'select',
        'campaign_id',
        E::ts('Campaign'),
        $this->getCampaignList(),
        FALSE,
        array('class' => 'crm-select2')
    );

    // add mandate reference
    $this->add(
        'text',
        'reference',
        E::ts('Reference'),
        array('placeholder' => E::ts("not required, will be generated"), 'size' => '34')
    );

    // add source field
    $this->add(
        'text',
        'source',
        E::ts('Source'),
        array('placeholder' => E::ts("not required"), 'size' => '64')
    );

    // add bank account selector
    $this->add(
        'select',
        'bank_account_preset',
        E::ts('Account'),
        $this->getKnownBankAccounts(),
        FALSE,
        array('class' => 'crm-select2 huge')
    );

    // add account_holder field
    $this->add(
        'text',
        'account_holder',
        E::ts('Account Holder'),
        array('placeholder' => E::ts("not required if same as contact"), 'size' => '255')
        // TODO what is TRUE or FALSE here?
    );

    // add iban field
    $this->add(
        'text',
        'iban',
        E::ts('IBAN'),
        array('placeholder' => E::ts("required"), 'size' => '32'),
        TRUE
    );

    // add bic field
    $this->add(
        'text',
        'bic',
        E::ts('BIC'),
        array('placeholder' => E::ts("required"), 'size' => '14'),
        FALSE
    );

    // add amount field
    $this->addMoney(
        'amount',
        ts('Amount'),
        TRUE,
        array('class' => 'tiny')
    );

    // add currency field
    $this->add(
        'select',
        'currency',
        E::ts('Currency'),
        $this->getCurrencyList(),
        TRUE,
        array('class' => 'tiny')
    );

    // add 'replaces' fields
    // store ID of mandate to be replaced
    $this->add('hidden', 'replace', $this->replace_id);

    // add the replace date
    $this->addDate(
        'rpl_end_date',
        E::ts("Replacement Date"),
        $this->replace_id,
        array('formatType' => 'activityDate'));

    // add the replacement/cancel reason
    $this->add(
        'text',
        'rpl_cancel_reason',
        E::ts('Replacement Reason'),
        array('placeholder' => E::ts("required"), 'class'=> 'huge'),
        $this->replace_id);

    // add OOFF fields
    // add collection date
    $this->addDate(
        'ooff_date',
        E::ts("Collection Date"),
        FALSE,
        array('formatType' => 'activityDate'));

    // add RCUR fields
    // add start date
    $this->addDate(
        'rcur_start_date',
        E::ts("Start Date"),
        FALSE,
        array('formatType' => 'activityDate'));

    // add collection day
    $this->add(
        'select',
        'cycle_day',
        E::ts('Day of Month'),
        range(1,31),
        FALSE,
        array()
    );

    // add interval
    $this->add(
        'select',
        'interval',
        E::ts('Frequency'),
        $this->getFrequencyList(),
        FALSE,
        array()
    );

    // add end_date
    $this->addDate(
        'rcur_end_date',
        E::ts("End Date"),
        FALSE,
        array('formatType' => 'activityDate'));

    // finally, add a date field just as a converter
    $this->addDate('sdd_converter', 'just for date conversion', FALSE, array('formatType' => 'activityDate'));


    // inject JS logic
    CRM_Core_Resources::singleton()->addScriptFile('org.project60.sepa', 'js/CreateMandate.js');
    if (function_exists('bic_civicrm_install')) {
      $config = CRM_Core_Config::singleton();
      $js_vars['busy_icon_url'] = $config->resourceBase . "i/loading.gif";
      CRM_Core_Resources::singleton()->addScriptFile('org.project60.sepa', 'js/LittleBicLookup.js');
    }
    CRM_Core_Resources::singleton()->addVars('p60sdd', $js_vars);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Create'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }


  /**
   * Set the defaults
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();

    if ($this->old_mandate) {
      // set all parameters to the mandate-to-be-replaced
      $defaults['creditor_id']       = $this->old_mandate['creditor_id'];
      $defaults['financial_type_id'] = $this->old_contrib['financial_type_id'];
      $defaults['campaign_id']       = CRM_Utils_Array::value('campaign_id', $this->old_contrib, '');
      $defaults['account_holder']    = $this->old_mandate['account_holder'];
      $defaults['iban']              = $this->old_mandate['iban'];
      $defaults['bic']               = $this->old_mandate['bic'];
      $defaults['amount']            = $this->old_contrib['amount'];
      $defaults['currency']          = $this->old_contrib['currency'];
      $defaults['cycle_day']         = $this->old_contrib['cycle_day'];

      // calculate and set interval
      if ($this->old_contrib['frequency_unit'] == 'month') {
        $defaults['interval'] = 12 / $this->old_contrib['frequency_interval'];
      } elseif ($this->old_contrib['frequency_unit'] == 'year') {
        $defaults['interval'] = $this->old_contrib['frequency_interval'];
      } else {
        $defaults['interval'] = 1;
        CRM_Core_Session::setStatus(E::ts("Incompatible frequency unit '%1' in mandate.", array(
            1 => $this->old_contrib['frequency_unit'])), E::ts("Warning"), 'warning');
      }

      if ($this->create_mode == 'replace') {
        // set start date for replace
        if (!empty($this->rpl_date)) {
          $formatted_date = CRM_Utils_Date::setDateDefaults($this->rpl_date, 'activityDateTime');
          $defaults['rcur_start_date'] = $formatted_date[0];
          $defaults['rpl_end_date'] = $formatted_date[0];
        } else {
          $formatted_date = CRM_Utils_Date::setDateDefaults(date('YmdHis'), 'activityDateTime');
          $defaults['rpl_end_date'] = $formatted_date[0];
        }

        // also set the replacement reason
        if (!empty($this->rpl_reason)) {
          $defaults['rpl_cancel_reason'] = trim($this->rpl_reason);
        }
      }
    } else {
      // set default creditor
      $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
      if ($default_creditor_id) {
        $defaults['creditor_id'] = $default_creditor_id;
      }
    }

    return $defaults;
  }

  /**
   * Validate input data
   */
  public function validate() {
    parent::validate();

    // load creditor, check mode
    $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $this->_submitValues['creditor_id']));
    $creditor_mode = empty($creditor['creditor_type']) ? 'SEPA' : $creditor['creditor_type'];

    // validate IBAN
    $iban_error = CRM_Sepa_Logic_Verification::verifyIBAN(
      CRM_Sepa_Logic_Verification::formatIBAN(
        $this->_submitValues['iban'],
        $creditor_mode
      ),
      $creditor_mode
    );
    if ($iban_error) {
      $this->_errors['iban'] = $iban_error;
    }

    // validate BIC
    $bic_error = NULL;
    if ($creditor_mode == 'SEPA') {
      if (empty($this->_submitValues['bic'])) {
        if ($creditor['uses_bic']) {
          $bic_error = E::ts("BIC is required");
        }
      } else {
        $bic_error = CRM_Sepa_Logic_Verification::verifyBIC(
            CRM_Sepa_Logic_Verification::formatBIC(
                $this->_submitValues['bic'],
                $creditor_mode
            ),
          $creditor_mode);
      }
    }
    if ($bic_error) {
      $this->_errors['bic'] = $bic_error;
    }

    // validate amount
    if ($this->_submitValues['amount'] <= 0.0) {
      $this->_errors['amount'] = E::ts("Amount has to be positive.");
    }

    // validate the reference
    if (strlen($this->_submitValues['reference']) > 0) {
      if ($creditor_mode == 'SEPA') {
        $reference_error = CRM_Sepa_Logic_Verification::verifyReference($this->_submitValues['reference']);
      } else {
        $reference_error = CRM_Sepa_Logic_Verification::verifyReference($this->_submitValues['reference'], $creditor_mode);
      }
      if ($reference_error) {
        $this->_errors['reference'] = $reference_error;
      } else {
        // check if the reference is available
        $in_use = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_sdd_mandate WHERE reference = %1",
            array(1 => array($this->_submitValues['reference'], 'String')));
        if ($in_use) {
          $this->_errors['reference'] = E::ts("Already in use");
        }

      }
    }

    return (0 == count($this->_errors));
  }


  /**
   * Create the mandate
   */
  public function postProcess() {
    $values = $this->exportValues();

    // create a new mandate
    $type = $values['interval'] ? 'RCUR' : 'OOFF';
    $mandate_data = array(
        'type'                      => $type,
        'creation_date'             => date('YmdHis'),
        'creditor_id'               => $values['creditor_id'],
        'contact_id'                => $values['cid'],
        'campaign_id'               => $values['campaign_id'],
        'financial_type_id'         => $values['financial_type_id'],
        'currency'                  => $values['currency'],
        'account_holder'            => $values['account_holder'],
        'iban'                      => $values['iban'],
        'bic'                       => empty($values['bic']) ? 'NOTPROVIDED' : $values['bic'],
        'cycle_day'                 => $values['cycle_day'],
        'amount'                    => $values['amount'],
        'frequency_interval'        => $type == 'RCUR' ? 12 / $values['interval'] : 0,
        'frequency_unit'            => 'month',
        'reference'                 => $values['reference'],
        'source'                    => $values['source'],
        'receive_date'              => $type == 'OOFF' ? CRM_Utils_Date::processDate($values['ooff_date']) : '',
        'start_date'                => $type == 'RCUR' ? CRM_Utils_Date::processDate($values['rcur_start_date']) : '',
        'end_date'                  => empty($values['rcur_end_date']) ? '' : CRM_Utils_Date::processDate($values['rcur_end_date']),
    );

    try {
      $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = civicrm_api3('SepaMandate', 'getsingle', array(
          'id'     => $mandate['id'],
          'return' => 'reference,id,type'));

      // if we get here, everything went o.k.
      CRM_Core_Session::setStatus(E::ts("'%3' SEPA Mandate <a href=\"%2\">%1</a> created.", array(
          1 => $mandate['reference'],
          2 => CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$mandate['id']}"),
          3 => $mandate['type'])),
          E::ts("Success"),
          'info');

      // terminate old mandate, of requested
      if (!empty($values['replace'])) {
        $rpl_mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $values['replace']));

        CRM_Sepa_BAO_SEPAMandate::terminateMandate(
            $values['replace'],
            CRM_Utils_Date::processDate($values['rpl_end_date'], NULL, FALSE, 'Y-m-d'),
            $values['rpl_cancel_reason']);

        CRM_Sepa_BAO_SepaMandateLink::addReplaceMandateLink(
            $values['replace'],
            $mandate['id'],
            CRM_Utils_Date::processDate($values['rpl_end_date'], NULL, FALSE, 'Y-m-d'));

        /*CRM_Core_Session::setStatus(E::ts("Mandate <a href=\"%2\">%1</a> was scheduled to end on %3", array(
            1 => $rpl_mandate['reference'],
            2 => CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$rpl_mandate['id']}"),
            3 => CRM_Utils_Date::formatDate($values['rpl_end_date'], 'activityDate'))),
            E::ts("Success"),
            'info'); */
      }

    } catch (Exception $ex) {
      // there was a problem: create error message
      CRM_Core_Session::setStatus(E::ts("Failed to create %1 mandate. Error was: %2", array(
          1 => $type,
          2 => $ex->getMessage())),
          E::ts("Error"),
          'error');
    }

    // where to go from here?
    $session = CRM_Core_Session::singleton();
    $user_context = $session->readUserContext();
    if ((strpos($user_context, 'civicrm/contribute/search') !== FALSE)
       || (strpos($user_context, 'civicrm/sepa/create') !== FALSE)
       || (strpos($user_context, 'civicrm/contact/view') !== FALSE)) {
      // I'm not even sure where the first one is coming from... but replace!
      $session->popUserContext();
      $session->pushUserContext(CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$values['cid']}&selectedChild=sepa"));
    }
    // this is not a popup -> redirect
    if (!CRM_Utils_Array::value('snippet', $_REQUEST)) {
      CRM_Utils_System::redirect(CRM_Core_Session::singleton()->readUserContext());
    }

    parent::postProcess();
  }



  // ############################# HELPER FUNCTIONS #############################

  /**
   * Get a list of all creditors, extended by
   *  - the list of cycle_days
   *  - a 'default' flag
   *  - the currency
   */
  protected function getCreditors() {
    $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');

    $creditor_query = civicrm_api3('SepaCreditor', 'get', ['option.limit' => 0]);
    $creditors = $creditor_query['values'];

    foreach ($creditors as &$creditor) {
      // add default flag
      if ($creditor['id'] == $default_creditor_id) {
        $creditor['is_default'] = 1;
      } else {
        $creditor['is_default'] = 0;
      }

      // add cycle days
      $creditor['cycle_days'] = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor['id']);
      $creditor['buffer_days'] = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days");
      $creditor['ooff_notice'] = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $creditor['id']);
      $creditor['frst_notice'] = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor['id']);
    }

    return $creditors;
  }

  /**
   * Creates a neat dropdown list of the eligible creditors
   * @param $creditors
   * @return array of eligible creditors
   */
  protected function getCreditorList($creditors) {
    $creditor_list = array();
    foreach ($creditors as $creditor) {
      $creditor_list[$creditor['id']] = "[{$creditor['id']}] {$creditor['label']}";
    }
    return $creditor_list;
  }

  /**
   * Get the list of (active) financial types
   */
  protected function getFinancialTypeList() {
    $list = array();
    $query = civicrm_api3('FinancialType', 'get',array(
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,name'
    ));

    foreach ($query['values'] as $value) {
      $list[$value['id']] = $value['name'];
    }

    return $list;
  }

  /**
   * Get the list of (active) financial types
   */
  protected function getCampaignList() {
    $list = array('' => E::ts("- none -"));
    $query = civicrm_api3('Campaign', 'get',array(
        'is_active'    => 1,
        'option.limit' => 0,
        'option.sort'  => 'title asc',
        'return'       => 'id,title'
    ));

    foreach ($query['values'] as $value) {
      $list[$value['id']] = $value['title'];
    }

    return $list;
  }

  /**
   * Get a list of known bank accounts from:
   *  - successful SEPA mandates
   *  - known CiviBanking accounts
   * 
   * TODO(account_holder): unclear if it should be added here.
   */
  protected function getKnownBankAccounts() {
    $known_accounts = array('' => E::ts("new account"));

    // get data from SepaMandates
    $mandates = civicrm_api3('SepaMandate', 'get', array(
      'contact_id'   => $this->contact_id,
      'status'       => array('IN' => array('RCUR', 'COMPLETE', 'SENT')),
      'option.limit' => 0,
      'return'       => 'iban,bic,reference',
      'option.sort'  => 'id desc'
    ));
    foreach ($mandates['values'] as $mandate) {
      $key = "{$mandate['iban']}/{$mandate['bic']}";
      if (!isset($known_accounts[$key])) {
        $known_accounts[$key] = "{$mandate['iban']} ({$mandate['reference']})";
      }
    }

    // get data from CiviBanking (if installed)
    if (class_exists('CRM_Banking_BAO_BankAccountReference')) {
      $iban_reference_type_id = civicrm_api3('OptionValue', 'getvalue', array(
          'return'          => 'id',
          'option_group_id' => 'civicrm_banking.reference_types',
          'value'           => 'IBAN',
      ));

      if ($iban_reference_type_id) {
        $accounts = civicrm_api3('BankingAccount', 'get', array(
            'contact_id'   => $this->contact_id,
            'option.limit' => 0,
            'return'       => 'id,data_parsed',
            'sequential'   => 0,
        ));

        if (empty($accounts['values'])) {
          $account_references = array('values' => array());
        } else {
          $account_references = civicrm_api3('BankingAccountReference', 'get', array(
              'ba_id'             => array('IN' => array_keys($accounts['values'])),
              'reference_type_id' => $iban_reference_type_id,
              'option.limit'      => 0,
              'return'            => 'reference,ba_id',
              'sequential'        => 1,
              'option.sort'       => 'id desc'
          ));
        }

        foreach ($account_references['values'] as $account_reference) {
          $account = $accounts['values'][$account_reference['ba_id']];
          $account_data = json_decode($account['data_parsed'], TRUE);
          $bic = CRM_Utils_Array::value('BIC', $account_data, CRM_Utils_Array::value('bic', $account_data, ''));
          $key = "{$account_reference['reference']}/{$bic}";
          $account_already_in_list = FALSE;
          if ($bic) {
            $account_already_in_list = isset($known_accounts[$key]);
          } else { // no BIC? we'll have to search
            foreach ($known_accounts as $existing_key => $value) {
              if ($key == substr($existing_key, 0, strlen($key))) {
                $account_already_in_list = TRUE;
                break;
              }
            }
          }
          if (!$account_already_in_list) {
            $account_name = empty($account_data['name']) ? E::ts("CiviBanking") : "'{$account_data['name']}'";
            $known_accounts[$key] = "{$account_reference['reference']} ({$account_name})";
          }
        }
      }
    }

    return $known_accounts;
  }

  /**
   * Get available currencies
   */
  protected function getCurrencyList() {
    return CRM_Core_OptionGroup::values('currencies_enabled');
  }

  /**
   * Get allowed frequencies
   *
   * @return array list of frequency to title
   */
  protected function getFrequencyList() {
    return array(
        '0'  => E::ts("One-time only (OOFF)"),
        '12' => CRM_Utils_SepaOptionGroupTools::getFrequencyText( 1, 'month', TRUE),
        '4'  => CRM_Utils_SepaOptionGroupTools::getFrequencyText( 3, 'month', TRUE),
        '2'  => CRM_Utils_SepaOptionGroupTools::getFrequencyText( 6, 'month', TRUE),
        '1'  => CRM_Utils_SepaOptionGroupTools::getFrequencyText(12, 'month', TRUE),
    );
  }
}
