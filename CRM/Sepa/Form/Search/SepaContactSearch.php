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
 * A custom contact search
 */
class CRM_Sepa_Form_Search_SepaContactSearch extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  /**
   * Prepare a set of search fields
   *
   * @param CRM_Core_Form $form modifiable
   * @return void
   */
  function buildForm(&$form) {
    CRM_Utils_System::setTitle(E::ts('CiviSEPA Contact Search'));

    $form->add('text',
      'reference',
      E::ts('Mandate Reference'),
      TRUE
    );

    $form->add('text',
        'iban',
        E::ts('IBAN'),
        TRUE
    );

    /**
     * if you are using the standard template, this array tells the template what elements
     * are part of the search criteria
     */
    $form->assign('elements', array('reference', 'iban'));
  }

  /**
   * Get a list of summary data points
   *
   * @return mixed; NULL or array with keys:
   *  - summary: string
   *  - total: numeric
   */
  function summary() {
    return NULL;
  }

  /**
   * Get a list of displayable columns
   *
   * @return array, keys are printable column headers and values are SQL column names
   */
  function &columns() {
    // return by reference
    $columns = array(
      E::ts('Reference')      => 'reference',
      E::ts('Account Holder') => 'account_holder',
      E::ts('IBAN')           => 'iban',
      E::ts('BIC')            => 'bic',
      E::ts('Type')           => 'type',
      E::ts('Status')         => 'status',
      E::ts('Contact ID')     => 'contact_id',
      E::ts('Name')           => 'sort_name',
    );
    return $columns;
  }

  /**
   * Construct a full SQL query which returns one page worth of results
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   * @return string, sql
   */
  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    // delegate to $this->sql(), $this->select(), $this->from(), $this->where(), etc.
    $query = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    return $query;
  }

  /**
   * Construct a SQL SELECT clause
   *
   * @return string, sql fragment with SELECT arguments
   */
  function select() {
    return "
      contact_a.id           AS contact_id,
      contact_a.sort_name    AS sort_name,
      mandate.reference      AS reference,
      mandate.account_holder AS account_holder,
      mandate.iban           AS iban,
      mandate.bic            AS bic,
      mandate.type           AS type,
      mandate.status         AS status
    ";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    return "
      FROM civicrm_contact contact_a
      LEFT JOIN civicrm_sdd_mandate mandate ON (mandate.contact_id = contact_a.id)
    ";
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string, sql fragment with conditional expressions
   */
  function where($includeContactIDs = FALSE) {
    $params = array();
    $wheres = array();
    $count  = 1;

    // add reference
    $reference = CRM_Utils_Array::value('reference', $this->_formValues);
    if ($reference) {
      $wheres[] = "mandate.reference LIKE %{$count}";
      $params[$count] = array($reference, 'String');
      $count++;
    }

    // add iban
    $iban = CRM_Utils_Array::value('iban', $this->_formValues);
    if ($iban) {
      $wheres[] = "mandate.iban LIKE %{$count}";
      $params[$count] = array($iban, 'String');
      $count++;
    }

    if (empty($wheres)) {
      return 'TRUE';
    } else {
      $where = '(' . implode(') AND (', $wheres) . ')';
      return $this->whereClause($where, $params);
    }
  }

  /**
   * Determine the Smarty template for the search screen
   *
   * @return string, template path (findable through Smarty template path)
   */
  function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @return void
   */
  function alterRow(&$row) {
//    $row['sort_name'] .= ' ( altered )';
  }
}
