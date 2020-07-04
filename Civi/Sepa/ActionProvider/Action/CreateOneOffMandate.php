<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2019 SYSTOPIA                            |
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

namespace Civi\Sepa\ActionProvider\Action;

use \Civi\ActionProvider\Action\AbstractAction;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Sepa_ExtensionUtil as E;

class CreateOneOffMandate extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
        new Specification('default_creditor_id',       'Integer', E::ts('Creditor (default)'), true, null, null, $this->getCreditors(), false),
        new Specification('default_financial_type_id', 'Integer', E::ts('Financial Type (default)'), true, null, null, $this->getFinancialTypes(), false),
        new Specification('default_campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, $this->getCampaigns(), false),
        new Specification('default_amount',            'Money',   E::ts('Amount (default)'), true),
    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
        // required fields
        new Specification('contact_id',     'Integer', E::ts('Contact ID'), true),
        new Specification('account_holder', 'String',  E::ts('Account Holder'), true),
        new Specification('iban',           'String',  E::ts('IBAN'), true),
        new Specification('bic',            'String',  E::ts('BIC'), true),
        new Specification('reference',      'String',  E::ts('Mandate Reference'), false),
        new Specification('amount',         'Money',   E::ts('Amount'), false),

        // basic overrides
        new Specification('creditor_id',       'Integer', E::ts('Creditor (default)'), false, null, null, $this->getCreditors(), false),
        new Specification('financial_type_id', 'Integer', E::ts('Financial Type (default)'), false, null, null, $this->getFinancialTypes(), false),
        new Specification('campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, $this->getCampaigns(), false),

        // dates
        new Specification('receive_date',    'Date', E::ts('Collection Date'), false, date('Y-m-d H:i:s')),
        new Specification('date',            'Date', E::ts('Signature Date'),  false, date('Y-m-d H:i:s')),
        new Specification('validation_date', 'Date', E::ts('Validation Date'), false, date('Y-m-d H:i:s')),
    ]);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overridden by child classes.
   *
   * @return SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('mandate_id',        'Integer', E::ts('Mandate ID'), false, null, null, null, false),
      new Specification('mandate_reference', 'String',  E::ts('Mandate Reference'), false, null, null, null, false),
      new Specification('error',             'String',  E::ts('Error Message (if creation failed)'), false, null, null, null, false),
    ]);
  }

  /**
   * Run the action
   *
   * @param ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param ParameterBagInterface $output
   * 	 The parameters this action can send back
   * @return void
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $mandate_data = ['type' => 'OOFF'];
    // add basic fields
    foreach (['contact_id', 'account_holder', 'iban', 'bic', 'reference', 'amount', 'receive_date', 'date', 'validation_date'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $mandate_data[$parameter_name] = $value;
      }
    }

    // add override fields
    foreach (['creditor_id', 'financial_type_id', 'campaign_id', 'amount'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $mandate_data[$parameter_name] = $value;
    }

    // create mandate
    try {
      $mandate = \civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = \civicrm_api3('SepaMandate', 'getsingle', ['id' => $mandate['id'], 'return' => 'id,reference']);
      $output->setParameter('mandate_id', $mandate['id']);
      $output->setParameter('mandate_reference', $mandate['reference']);
      $output->setParameter('error', '');

    } catch (\Exception $ex) {
      $output->setParameter('mandate_id', '');
      $output->setParameter('mandate_reference', '');
      $output->setParameter('error', $ex->getMessage());
    }
  }



  /**
   * Get a list of all creditors
   */
  protected function getCreditors() {
    $creditor_list = [];
    $creditor_query = \civicrm_api3('SepaCreditor', 'get', ['option.limit' => 0]);
    foreach ($creditor_query['values'] as $creditor) {
      $creditor_list[$creditor['id']] = $creditor['name'];
    }
    return $creditor_list;
  }

  /**
   * Get a list of all financial types
   */
  protected function getFinancialTypes() {
    $list = [];
    $query = \civicrm_api3('FinancialType', 'get', [
        'option.limit' => 0,
        'is_enabled'   => 1,
        'return'       => 'id,name']);
    foreach ($query['values'] as $entity) {
      $list[$entity['id']] = $entity['name'];
    }
    return $list;
  }

  /**
   * Get a list of all campaigns
   */
  protected function getCampaigns() {
    $list = [];
    $query = \civicrm_api3('Campaign', 'get', [
        'option.limit' => 0,
        'is_active'    => 1,
        'return'       => 'id,title']);
    foreach ($query['values'] as $entity) {
      $list[$entity['id']] = $entity['title'];
    }
    return $list;
  }
}