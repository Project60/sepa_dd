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
 * Generic report on SEPA mandates
 */
class CRM_Sepa_Form_Report_SepaMandateGeneric extends CRM_Report_Form {

  protected $_customGroupExtends = NULL;//array('Contact');
  protected $_customGroupGroupBy = FALSE;

  /**
   * generic constructor
   */
  function __construct() {
    $this->_initColumns();
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  /**
   * internal function to init the configuration array (_columns)
   */
  protected function _initColumns() {
    $this->_columns = array(
      'civicrm_sdd_mandate' => array(
        'dao' => 'CRM_Sepa_DAO_SEPAMandate',
        'fields' => array(
          'reference' => array(
            'title' => ts('Mandate Reference', array('domain' => 'org.project60.sepa')),
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'title' => ts('Mandate ID', array('domain' => 'org.project60.sepa')),
            'required' => TRUE,
            'no_display' => TRUE,
          ),
          'mandate_type' => array(
            'name'  => 'type',
            'title' => ts('Type', array('domain' => 'org.project60.sepa')),
          ),
          'status' => array(
            'title' => ts('Mandate Status', array('domain' => 'org.project60.sepa')),
          ),
          'account_holder' => array(
            'title' => ts('Account Holder', array('domain' => 'org.project60.sepa')),
          ),
          'iban' => array(
            'title' => ts('IBAN', array('domain' => 'org.project60.sepa')),
          ),
          'bic' => array(
            'title' => ts('BIC', array('domain' => 'org.project60.sepa')),
          ),
          'source' => array(
            'title' => ts('Source', array('domain' => 'org.project60.sepa')),
          ),
          'date' => array(
            'title' => ts('Signature Date', array('domain' => 'org.project60.sepa')),
          ),
          'creation_date' => array(
            'title' => ts('Creation Date', array('domain' => 'org.project60.sepa')),
          ),
          'validation_date' => array(
            'title' => ts('Validation Date', array('domain' => 'org.project60.sepa')),
          ),
          'amount' => array(
            'dbAlias' => 'amount',
            'title'   => ts('Amount', array('domain' => 'org.project60.sepa')),
            'type'    => CRM_Utils_Type::T_FLOAT,
          ),
          'status_id' => array(
            'dbAlias' => 'status_id',
            'title'   => ts('Contribution Status', array('domain' => 'org.project60.sepa')),
            'type'    => CRM_Utils_Type::T_INT,
          ),
        ),
        'filters' => array(
          'reference' => array(
            'name' => 'reference',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('Mandate Reference', array('domain' => 'org.project60.sepa')),
          ),
          'mandate_type' => array(
            'name' => 'type',
            'title' => ts('Type', array('domain' => 'org.project60.sepa')),
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => array(
              ''         => ts('Any', array('domain' => 'org.project60.sepa')),
              'OOFF'     => ts('One-off', array('domain' => 'org.project60.sepa')),
              'RCUR'     => ts('Recurring', array('domain' => 'org.project60.sepa')),
            ),
          ),
          'status' => array(
            'name' => 'status',
            'title' => ts('Mandate Status', array('domain' => 'org.project60.sepa')),
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Sepa_Logic_Status::getStatusSelectorOptions(TRUE),
          ),
          'account_holder' => array(
            'name' => 'account_holder',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('account_holder', array('domain' => 'org.project60.sepa')),
          ),
          'iban' => array(
            'name' => 'iban',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('IBAN', array('domain' => 'org.project60.sepa')),
          ),
          'bic' => array(
            'name' => 'bic',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('BIC', array('domain' => 'org.project60.sepa')),
          ),
          'source' => array(
            'name' => 'source',
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_STRING,
            'title' => ts('Source', array('domain' => 'org.project60.sepa')),
          ),
          'date' => array(
            'title' => ts('Signature Date', array('domain' => 'org.project60.sepa')),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'creation_date' => array(
            'title' => ts('Creation Date', array('domain' => 'org.project60.sepa')),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'validation_date' => array(
            'title' => ts('Validation Date', array('domain' => 'org.project60.sepa')),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'amount' => array(
            'dbAlias' => 'amount',
            'title' => ts('Amount', array('domain' => 'org.project60.sepa')),
            'type'  => CRM_Utils_Type::T_FLOAT,
            'operatorType' => CRM_Report_Form::OP_FLOAT,
          ),
          'status_id' => array(
            'dbAlias' => 'status_id',
            'title' => ts('Contribution Status', array('domain' => 'org.project60.sepa')),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_OptionGroup::values('contribution_status'),
          ),
        ),
        'order_bys' => array(
          'reference' => array(
            'title' => ts('Mandate Reference', array('domain' => 'org.project60.sepa')),
          ),
          'mandate_type' => array(
            'name'  => 'type',
            'title' => ts('Type', array('domain' => 'org.project60.sepa')),
          ),
          'bic' => array(
            'name'  => 'bic',
            'title' => ts('BIC', array('domain' => 'org.project60.sepa')),
          ),
          'status' => array(
            'title' => ts('Mandate Status', array('domain' => 'org.project60.sepa')),
          ),
          'date' => array(
            'title' => ts('Signature Date', array('domain' => 'org.project60.sepa')),
          ),
          'creation_date' => array(
            'title' => ts('Creation Date', array('domain' => 'org.project60.sepa')),
          ),
          'validation_date' => array(
            'title' => ts('Validation Date', array('domain' => 'org.project60.sepa')),
          ),
        ),
        'grouping' => 'mandate-fields',
      ),


      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => ts('Contact Name', array('domain' => 'org.project60.sepa')),
            // 'required' => TRUE,
            'default' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Contact Name', array('domain' => 'org.project60.sepa')),
            'operator' => 'like',
          ),
          'id' => array(
            'title' => ts('Contact ID', array('domain' => 'org.project60.sepa')),
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Contact Name', array('domain' => 'org.project60.sepa')),
          ),
          'id' => array(
            'title' => ts('Contact ID', array('domain' => 'org.project60.sepa')),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
    );
  }

  /**
   * generate select clause
   */
  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          $select_clause = $this->_getSelectClause($fieldName, $field, $tableName);
          if ($select_clause) {
            $select[] = $select_clause;
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  /**
   * get individual select clauses
   */
  function _getSelectClause($fieldName, $field, $tableName) {
    // add amount from either OOFF or RCUR
    if ($fieldName == 'amount') {
      $this->_columnHeaders['amount']['title'] = $field['title'];
      $this->_columnHeaders['amount']['type']  = CRM_Utils_Array::value('type', $field);
      return "IF(civicrm_contribution.id IS NOT NULL, civicrm_contribution.total_amount, civicrm_contribution_recur.amount) AS amount";
    }

    // add status from either OOFF or RCUR
    if ($fieldName == 'status_id') {
      $this->_columnHeaders['status_id']['title'] = $field['title'];
      $this->_columnHeaders['status_id']['type']  = CRM_Utils_Array::value('type', $field);
      return "IF(civicrm_contribution.id IS NOT NULL, civicrm_contribution.contribution_status_id, civicrm_contribution_recur.contribution_status_id) AS status_id";
    }

    // Fallback: generic selector
    if (CRM_Utils_Array::value('required', $field) || CRM_Utils_Array::value($fieldName, $this->_params['fields'])) {
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
      $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
      return "{$field['dbAlias']} as {$tableName}_{$fieldName}";
    }

    return NULL;
  }


  function from() {
    $this->_from = NULL;
    $this->_from = "
         FROM  civicrm_sdd_mandate {$this->_aliases['civicrm_sdd_mandate']} {$this->_aclFrom}
               INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             {$this->_aliases['civicrm_sdd_mandate']}.contact_id
               LEFT JOIN civicrm_contribution
                          ON 'civicrm_contribution' = {$this->_aliases['civicrm_sdd_mandate']}.entity_table
                          AND civicrm_contribution.id = {$this->_aliases['civicrm_sdd_mandate']}.entity_id
               LEFT JOIN civicrm_contribution_recur
                          ON 'civicrm_contribution_recur' = {$this->_aliases['civicrm_sdd_mandate']}.entity_table
                          AND civicrm_contribution_recur.id = {$this->_aliases['civicrm_sdd_mandate']}.entity_id
         ";
  }

  /**
   * internal function to generate where clauses
   */
  protected function _getWhereClause($fieldName, $field) {
    $clause = NULL;
    if ($fieldName == 'status_id') {
      if (!empty($this->_params["{$fieldName}_value"])) {
        $base_clause = $this->whereClause($field,
            CRM_Utils_Array::value("{$fieldName}_op", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
          );

        // since either OOFF or RCUR is always NULL, we can just use OR...
        $ooff_clause = preg_replace("#$fieldName#", 'civicrm_contribution.contribution_status_id', $base_clause);
        $rcur_clause = preg_replace("#$fieldName#", 'civicrm_contribution_recur.contribution_status_id', $base_clause);
        $clause = "( $ooff_clause OR $rcur_clause )";
      }
    }

    elseif ($fieldName == 'status') {
      if (!empty($this->_params["{$fieldName}_value"])) {
        $mandate_status_values = array();
        foreach ($this->_params["{$fieldName}_value"] as $status_value) {
          $more_mandate_status_values = CRM_Sepa_Logic_Status::translateToMandateStatus($status_value);
          $mandate_status_values = array_merge($mandate_status_values, $more_mandate_status_values);
        }

        $mandate_status_list = '"' . implode('","', $mandate_status_values) . '"';
        $clause = "( `status` IN ($mandate_status_list) )";
      }
    }

    elseif ($fieldName == 'amount') {
      if (!empty($this->_params["{$fieldName}_value"])) {
        $base_clause = $this->whereClause($field,
            CRM_Utils_Array::value("{$fieldName}_op", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
          );

        // since either OOFF or RCUR is always NULL, we can just use OR...
        $ooff_clause = preg_replace("#$fieldName#", 'civicrm_contribution.total_amount', $base_clause);
        $rcur_clause = preg_replace("#$fieldName#", 'civicrm_contribution_recur.amount', $base_clause);
        $clause = "( $ooff_clause OR $rcur_clause )";
      }
    }

    elseif (CRM_Utils_Array::value('operatorType', $field) & CRM_Utils_Type::T_DATE) {
      $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
      $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
      $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

      $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
    }

    else {
      $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
      if ($op) {
        $clause = $this->whereClause($field,
          $op,
          CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
          CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
        );
      }
    }

    return $clause;
  }

  /**
   * internal function to generate where clauses
   */
  protected function _extendWhereClause(&$clauses) {
    // Nothin to do here
  }


  /**
   * build generic WHERE clause
   */
  function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = $this->_getWhereClause($fieldName, $field);
          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }

    // add some more if needed
    $this->_extendWhereClause($clauses);

    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    $contribution_status = CRM_Core_OptionGroup::values('contribution_status');

    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {

      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (CRM_Utils_Array::value($colName, $checkList) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      // alter mandate status
      if (array_key_exists("civicrm_sdd_mandate_status", $row)) {
        $rows[$rowNum]["civicrm_sdd_mandate_status"] = CRM_Sepa_Logic_Status::translateMandateStatus($row["civicrm_sdd_mandate_status"], TRUE);
      }

      // alter contribution status
      if (array_key_exists('status_id', $row)) {
        $rows[$rowNum]['status_id'] = $contribution_status[$row['status_id']];
      }

      // alter amount
      if (array_key_exists('amount', $row)) {
        $rows[$rowNum]['amount'] = CRM_Utils_Money::format($row['amount']);
      }

      // add mandate link
      if (array_key_exists('civicrm_sdd_mandate_reference', $row) && array_key_exists('civicrm_sdd_mandate_id', $row)) {
        $url = CRM_Utils_System::url("civicrm/sepa/xmandate", 'mid=' . $row['civicrm_sdd_mandate_id'], $this->_absoluteUrl );
        $rows[$rowNum]['civicrm_sdd_mandate_reference_link'] = $url;
        $rows[$rowNum]['civicrm_sdd_mandate_reference_hover'] = ts("View Mandate Options.", array('domain' => 'org.project60.sepa'));
      }

      // add contact link
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view", 'reset=1&cid=' . $row['civicrm_contact_id'], $this->_absoluteUrl);
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.", array('domain' => 'org.project60.sepa'));
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }
}
