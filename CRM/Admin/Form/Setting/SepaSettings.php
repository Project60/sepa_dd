<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: N. Bochan (bochan -at- systopia.de)            |
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

require_once 'CRM/Admin/Form/Setting.php';
require_once 'CRM/Core/BAO/CustomField.php';

class CRM_Admin_Form_Setting_SepaSettings extends CRM_Admin_Form_Setting
{
    private $config_fields;
    private $custom_fields;

    function __construct() {
       parent::__construct();

       $this->config_fields = array(
                         array('cycledays',              ts('Cycle Day(s)', array('domain' => 'org.project60.sepa')), array('size' => 6)),
                         array('batching.OOFF.horizon',  ts('One-off horizon', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('batching.OOFF.notice',   ts('One-off&nbsp;notice&nbsp;days', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('batching.RCUR.horizon',  ts('Recurring horizon', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('batching.RCUR.grace',    ts('Recurring grace', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('batching.RCUR.notice',   ts('Recurring&nbsp;notice&nbsp;days (follow-up)', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('batching.FRST.notice',   ts('Recurring&nbsp;notice&nbsp;days (initial)', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('batching.UPDATE.lock.timeout', ts('Update lock timeout', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_txmsg', ts('Transaction Message', array('domain' => 'org.project60.sepa')), array('size' => 60, 'placeholder' => CRM_Sepa_Logic_Settings::getGenericSetting('custom_txmsg'))));

      $this->custom_fields = array(
                         array('custom_cycledays',       ts('Cycle Day(s)', array('domain' => 'org.project60.sepa')), array('size' => 6)),
                         array('custom_OOFF_horizon',    ts('One-off horizon', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_OOFF_notice',     ts('One-off&nbsp;notice&nbsp;days', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_RCUR_horizon',    ts('Recurring horizon', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_RCUR_grace',      ts('Recurring grace', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_RCUR_notice',     ts('Recurring&nbsp;notice&nbsp;days (follow-up)', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_FRST_notice',     ts('Recurring&nbsp;notice&nbsp;days (initial)', array('domain' => 'org.project60.sepa')), array('size' => 2)),
                         array('custom_update_lock_timeout', ts('Update lock timeout', array('domain' => 'org.project60.sepa')), array('size' => 2)));
    }

    function domainToString($raw) {
      return str_replace('.', '_', $raw);
    }

    function stringToDomain($raw) {
      return str_replace('_', '.', $raw);
    }


    function setDefaultValues() {
        $fields = array();
        // get all default values (they are set once when the extension is being enabled)
        foreach ($this->config_fields as $key => $value) {
            $fields[$this->domainToString($value[0])] = CRM_Sepa_Logic_Settings::getGenericSetting($this->domainToString($value[0]));
        }
        return $fields;
    }

    public function buildQuickForm( ) {
        CRM_Utils_System::setTitle(ts('Sepa Direct Debit - Settings', array('domain' => 'org.project60.sepa')));

        $customFields = CRM_Core_BAO_CustomField::getFields();
        $cf = array();
        foreach ($customFields as $k => $v) {
            $cf[$k] = $v['label'];
        }

        // add all form elements and validation rules
        foreach ($this->config_fields as $key => $value) {
            $elementName = $this->domainToString($value[0]);
            $elem = $this->addElement('text', $elementName, $value[1], (isset($value[2]) ? $value[2] : array()));
            if (!in_array($elementName, array('cycledays', 'custom_txmsg'))) {
                // integer only rules, except for cycledays (list)
              $this->addRule($this->domainToString($value[0]),
                         sprintf(ts("Please enter the %s as number (integers only).", array('domain' => 'org.project60.sepa')), $value[1]),
                        'positiveInteger');
              $this->addRule($this->domainToString($value[0]),
                         sprintf(ts("Please enter the %s as number (integers only).", array('domain' => 'org.project60.sepa')), $value[1]),
                        'required');
            }
        }

        // country drop down field
        $i18n = CRM_Core_I18n::singleton();
        $climit = array();
        $cnames = array();
        $ciso = array();
        $filtered = array();

        // country-limit fix by @scardinius (see https://github.com/Project60/org.project60.sepa/pull/388)
        if (version_compare(CRM_Utils_System::version(), '4.7', '>=')) {
          $climit = CRM_Core_BAO_Country::countryLimit();
        } else {
          $config = CRM_Core_Config::singleton();
          $climit = $config->countryLimit();
        }

        CRM_Core_PseudoConstant::populate($cnames, 'CRM_Core_DAO_Country', TRUE, 'name', 'is_active');
        CRM_Core_PseudoConstant::populate($ciso, 'CRM_Core_DAO_Country', TRUE, 'iso_code');

        foreach ($ciso as $key => $value) {
          foreach ($climit as $active_country) {
            if ($active_country == $value) {
              $filtered[$key] = $cnames[$key];
            }
          }
        }

        $i18n->localizeArray($filtered, array('context' => 'country'));
        asort($filtered);

        // do not use array_merge() because it discards the original indizes
        $country_ids = array('' => ts('- select -', array('domain' => 'org.project60.sepa'))) + $filtered;
        $currencies = CRM_Core_OptionGroup::values('currencies_enabled');
        if (!isset($currencies['EUR'])) {
          $currencies['EUR'] = 'EUR';
        }
        $creditor_types = array(
          'SEPA' => ts('SEPA', array('domain' => 'org.project60.sepa')),
          'PSP'  => ts('PSP', array('domain' => 'org.project60.sepa')));

        // look up some values
        $async_batch = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching');
        $skip_closed = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_skip_closed');
        $no_draftxml = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_no_draft_xml');
        $excld_we = CRM_Sepa_Logic_Settings::getGenericSetting('exclude_weekends');
        $bffrdays = CRM_Sepa_Logic_Settings::getGenericSetting('pp_buffer_days');

        // add creditor form elements
        $this->addElement('text',       'addcreditor_creditor_id',    ts("Creditor Contact", array('domain' => 'org.project60.sepa')));
        $this->addElement('text',       'addcreditor_label',          ts("Label", array('domain' => 'org.project60.sepa')));
        $this->addElement('text',       'addcreditor_name',           ts("Name", array('domain' => 'org.project60.sepa')));
        $this->addElement('text',       'addcreditor_id',             ts("Identifier", array('domain' => 'org.project60.sepa')));
        $this->addElement('text',       'addcreditor_address',        ts("Address", array('domain' => 'org.project60.sepa')), array('size' => 60));
        $this->addElement('select',     'addcreditor_country_id',     ts("Country", array('domain' => 'org.project60.sepa')), $country_ids);
        $this->addElement('text',       'addcreditor_account_holder', ts("Account Holder", array('domain' => 'org.project60.sepa')));
        $this->addElement('text',       'addcreditor_bic',            ts("BIC", array('domain' => 'org.project60.sepa')));
        $this->addElement('text',       'addcreditor_iban',           ts("IBAN", array('domain' => 'org.project60.sepa')), array('size' => 30));
        $this->addElement('select',     'addcreditor_currency',       ts("Currency", array('domain' => 'org.project60.sepa')), $currencies);
        $this->addElement('select',     'addcreditor_type',           ts("Type", array('domain' => 'org.project60.sepa')), $creditor_types);
        $this->addElement('select',     'addcreditor_pain_version',   ts("PAIN Version", array('domain' => 'org.project60.sepa')), array('' => ts('- select -', array('domain' => 'org.project60.sepa'))) + CRM_Core_OptionGroup::values('sepa_file_format'));
        $this->addElement('checkbox',   'addcreditor_uses_bic',       ts("Use BICs", array('domain' => 'org.project60.sepa')), "", (array('checked'=>'checked')));
        $this->addElement('checkbox',   'is_test_creditor',           ts("Is a Test Creditor", array('domain' => 'org.project60.sepa')), "", array('value' =>'0'));
        $this->addElement('checkbox',   'exclude_weekends',           ts("Exclude Weekends", array('domain' => 'org.project60.sepa')), "", ($excld_we?array('checked'=>'checked'):array()));
        $this->addElement('checkbox',   'sdd_async_batching',         ts("Large Groups", array('domain' => 'org.project60.sepa')), "", ($async_batch?array('checked'=>'checked'):array()));
        $this->addElement('checkbox',   'sdd_skip_closed',            ts("Only Completed Contributions", array('domain' => 'org.project60.sepa')), "", ($skip_closed?array('checked'=>'checked'):array()));
        $this->addElement('checkbox',   'sdd_no_draft_xml',           ts("No XML drafts", array('domain' => 'org.project60.sepa')), "", ($no_draftxml?array('checked'=>'checked'):array()));
        $this->addElement('text',       'pp_buffer_days',             ts("Buffer Days", array('domain' => 'org.project60.sepa')), array('size' => 2, 'value' => $bffrdays));
        $this->addElement('hidden',     'edit_creditor_id',           '', array('id' => 'edit_creditor_id'));
        $this->addElement('hidden',     'add_creditor_id',            '', array('id' => 'add_creditor_id'));

        // add custom form elements and validation rules
        $index = 0;
        foreach ($this->custom_fields as $key => $value) {
            if (isset($value[2])) {
              $properties = $value[2];
            } else {
              $properties = array();
            }
            $properties['placeholder'] = CRM_Sepa_Logic_Settings::getGenericSetting($this->domainToString($this->config_fields[$index][0]));
            $this->addElement('text', $this->domainToString($value[0]), $value[1], $properties);
            $elementName = $this->domainToString($value[0]);
            if (!in_array($elementName, array('custom_cycledays', 'custom_txmsg'))) {
              // integer only rules, except for cycledays (list)
              $this->addRule($elementName,
                       sprintf(ts("Please enter the %s as number (integers only).", array('domain' => 'org.project60.sepa')), $value[1]),
                      'positiveInteger');
            }
            $index++;
        }

        // register and add extra validation rules
        $this->registerRule('sepa_cycle_day_list', 'callback', 'sepa_cycle_day_list', 'CRM_Sepa_Logic_Settings');
        $this->addRule('cycledays',        ts('Please give a comma separated list of valid days.', array('domain' => 'org.project60.sepa')), 'sepa_cycle_day_list');
        $this->addRule('custom_cycledays', ts('Please give a comma separated list of valid days.', array('domain' => 'org.project60.sepa')), 'sepa_cycle_day_list');
        $this->addRule('pp_buffer_days',   ts('Please enter the number of days', array('domain' => 'org.project60.sepa')), 'positiveInteger');

        // get creditor list
        $creditors_default_list = array();
        $creditor_query = civicrm_api('SepaCreditor', 'get', array('version' => 3, 'option.limit' => 99999));
        if (!empty($creditor_query['is_error'])) {
          return civicrm_api3_create_error("Cannot get creditor list: " . $creditor_query['error_message']);
        } else {
          $creditors = array();
          foreach ($creditor_query['values'] as $creditor) {
              $creditors[] = $creditor;
              $creditors_default_list[$creditor['id']] = "[{$creditor['id']}] {$creditor['label']}";
          }
        }
        $this->assign('creditors', $creditors);
        $default_creditors = $this->addElement('select', 'batching_default_creditor', ts("Default Creditor", array('domain' => 'org.project60.sepa')), array('' => ts('- select -', array('domain' => 'org.project60.sepa'))) + $creditors_default_list);
        $default_creditors->setSelected(CRM_Sepa_Logic_Settings::getSetting('batching.default.creditor'));

        // add general config options
        $amm_options = CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')?array('checked'=>'checked'):array();
        $this->addElement('checkbox', 'allow_mandate_modification', ts("Mandate Modifications", array('domain' => 'org.project60.sepa')), NULL, $amm_options);

        parent::buildQuickForm();
    }

    function postProcess() {
        $values = $this->controller->exportValues($this->_name);

        // save field values
        foreach ($this->config_fields as $key => $value) {
            if(array_key_exists($this->domainToString($value[0]), $values)) {
              CRM_Sepa_Logic_Settings::setSetting($values[$this->domainToString($value[0])], $this->domainToString($value[0]));
            }
        }

        // save general config options:
        // default creditor
        CRM_Sepa_Logic_Settings::setSetting($values['batching_default_creditor'], 'batching_default_creditor');

        // mandate modification
        $allow_mandate_modification = empty($values['allow_mandate_modification'])?'0':'1';
        CRM_Sepa_Logic_Settings::setSetting($allow_mandate_modification, 'allow_mandate_modification');

        CRM_Sepa_Logic_Settings::setSetting((isset($values['exclude_weekends'])     ? "1" : "0"), 'exclude_weekends');
        CRM_Sepa_Logic_Settings::setSetting((isset($values['sdd_async_batching'])   ? "1" : "0"), 'sdd_async_batching');
        CRM_Sepa_Logic_Settings::setSetting((isset($values['sdd_skip_closed'])      ? "1" : "0"), 'sdd_skip_closed');
        CRM_Sepa_Logic_Settings::setSetting((isset($values['sdd_no_draft_xml'])     ? "1" : "0"), 'sdd_no_draft_xml');
        CRM_Sepa_Logic_Settings::setSetting((isset($values['pp_buffer_days'])       ? (int) $values['pp_buffer_days'] : "0"), 'pp_buffer_days');

        $session = CRM_Core_Session::singleton();
        $session->setStatus(ts("Settings successfully saved", array('domain' => 'org.project60.sepa')));

        CRM_Core_DAO::triggerRebuild();
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/setting/sepa'));
    }
}
