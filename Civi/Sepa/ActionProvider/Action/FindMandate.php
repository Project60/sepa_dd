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

use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Sepa_ExtensionUtil as E;

class FindMandate extends CreateRecurringMandate {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    $mandate_types = [
        'RCUR' => E::ts("Recurring"),
        'OOFF' => E::ts("One-Off"),
    ];
    $pick_types = [
        'id desc'   => E::ts("Newest (by ID)"),
        'id asc'    => E::ts("Oldest (by ID)"),
        'date desc' => E::ts("Newest (by date)"),
        'date asc'  => E::ts("Oldest (by date)"),
    ];

    return new SpecificationBag([
        new Specification('creditor_id','Integer', E::ts('Creditor'), false, null, null, $this->getCreditors(), true),
        new Specification('type',       'String', E::ts('Mandate Type'), false, null, null, $mandate_types, false),
        new Specification('active',     'Boolean', E::ts('Still Active'), false, null, null, $pick_types, false),
        new Specification('pick',       'String', E::ts('Pick (if multiple results)'), true, null, null, $pick_types, false),
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
        new Specification('contact_id',     'Integer', E::ts('Contact ID'), false),
        new Specification('account_holder', 'String', E::ts('Account Holder'), false),
        new Specification('iban',           'String',  E::ts('IBAN'), false),
        new Specification('reference',      'String',  E::ts('Mandate Reference'), false),
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
      new Specification('id',                'Integer', E::ts('Mandate ID'), false, null, null, null, false),
      new Specification('reference',         'String',  E::ts('Mandate Reference'), false, null, null, null, false),
      new Specification('type',              'String',  E::ts('Mandate Type'), false, null, null, null, false),
      new Specification('account_holder',    'String',  E::ts('Account Holder'), false, null, null, null, false),
      new Specification('iban',              'String',  E::ts('IBAN'), false, null, null, null, false),
      new Specification('bic',               'String',  E::ts('BIC'), false, null, null, null, false),
      new Specification('contact_id',        'Integer', E::ts('Contact ID'), false, null, null, null, false),
      new Specification('status',            'String',  E::ts('Status'), false, null, null, null, false),
      new Specification('amount',            'Money',   E::ts('Amount'), false, null, null, null, false),
      new Specification('annual_amount',     'Money',   E::ts('Annual Amount'), false, null, null, null, false),
      new Specification('frequency',         'Integer', E::ts('Frequency'), false, null, null, null, false),
      new Specification('cycle_day',         'Integer', E::ts('Collection Day'), false, null, null, null, false),
      new Specification('creditor_id',       'Integer', E::ts('Creditor ID'), false, null, null, null, false),
      new Specification('financial_type_id', 'Integer', E::ts('Financial Type (default)'), false, null, null, null, false),
      new Specification('campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, null, false),
      new Specification('start_date',        'Date',    E::ts('Start Date'), false, null, null, null, false),
      new Specification('date',              'Date',    E::ts('Signature Date'), false, null, null, null, false),
      new Specification('validation_date',   'Date',    E::ts('Validation Date'), false, null, null, null, false),
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
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output)
  {
    // compile search query
    $mandate_search = [];
    if (!empty($this->configuration->getParameter('creditor_id'))) {
      $mandate_search['creditor_id'] = ['IN' => $this->configuration->getParameter('creditor_id')];
    }
    if (!empty($this->configuration->getParameter('type'))) {
      $mandate_search['type'] = $this->configuration->getParameter('type');
    }
    if (!empty($this->configuration->getParameter('active'))) {
      $mandate_search['status'] = ['IN' => ['FRST', 'RCUR', 'OOFF', 'INIT']];
    }
    if (!empty($parameters->getParameter('contact_id'))) {
      $mandate_search['contact_id'] = $parameters->getParameter('contact_id');
    }
    if (!empty($parameters->getParameter('account_holder'))) {
      $mandate_search['account_holder'] = $parameters->getParameter('account_holder');
    }
    if (!empty($parameters->getParameter('iban'))) {
      $mandate_search['iban'] = $parameters->getParameter('iban');
    }
    if (!empty($parameters->getParameter('reference'))) {
      $mandate_search['reference'] = $parameters->getParameter('reference');
    }

    // add order
    $mandate_search['option.sort']  = $this->configuration->getParameter('pick');
    $mandate_search['option.limit'] = 1;

    // search mandate
    $result = \civicrm_api3('SepaMandate', 'get', $mandate_search);
    if ($result['count']) {
      $mandate = reset($result['values']);
      $output->setParameter('id', $mandate['id']);
      $output->setParameter('reference', $mandate['reference']);
      $output->setParameter('type', $mandate['type']);
      $output->setParameter('status', $mandate['status']);
      $output->setParameter('account_holder', $mandate['account_holder']);
      $output->setParameter('iban', $mandate['iban']);
      $output->setParameter('contact_id', $mandate['contact_id']);
      $output->setParameter('creditor_id', $mandate['creditor_id']);
      $output->setParameter('date', $mandate['date']);
      $output->setParameter('validation_date', $mandate['validation_date']);

      // these output values depend on the type
      switch ($mandate['type']) {
        case 'RCUR':
          $recurring_contribution = civicrm_api3('ContributionRecur', 'getsingle', [
            'id' => $mandate['entity_id']
          ]);
          $output->setParameter('amount', $recurring_contribution['amount']);
          $output->setParameter('cycle_day', $recurring_contribution['cycle_day']);
          $output->setParameter('financial_type_id', $recurring_contribution['financial_type_id']);
          $output->setParameter('campaign_id', \CRM_Utils_Array::value('campaign_id', $recurring_contribution));
          $output->setParameter('start_date', $recurring_contribution['start_date']);

          // some need to be calculated
          $frequency = 0; // frequency is 'how ofter per year'
          if ($recurring_contribution['frequency_unit'] == 'month') {
              $frequency = 12 / $recurring_contribution['frequency_interval'];
          } elseif ($recurring_contribution['frequency_unit'] == 'year') {
              $frequency = 1.0 / $recurring_contribution['frequency_interval'];
          } elseif ($recurring_contribution['frequency_unit'] == 'week') {
              $frequency = 52.0 / $recurring_contribution['frequency_interval'];
          }
          $output->setParameter('frequency', $frequency);
          $output->setParameter('annual_amount', number_format($frequency * $recurring_contribution['amount'], 2, '.', ''));
          break;

        case 'OOFF':
          $contribution = civicrm_api3('Contribution', 'getsingle', [
            'id' => $mandate['entity_id']
          ]);
          $output->setParameter('amount', $contribution['total_amount']);
          $output->setParameter('financial_type_id', $contribution['financial_type_id']);
          $output->setParameter('campaign_id', $contribution['campaign_id']);
          $output->setParameter('cycle_day', null);
          $output->setParameter('frequency', 0);
          $output->setParameter('annual_amount', null);
          break;

        default:
          // this shouldn't happen
          break;
      }
    }
  }
}