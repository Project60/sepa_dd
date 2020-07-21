<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP / SYSTOPIA                |
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
 * Add an SepaCreditor for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaCreditorCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_mandate_create}
 * @access public
 */
function civicrm_api3_sepa_mandate_create($params) {
  _civicrm_api3_sepa_mandate_adddefaultcreditor($params);
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Provide Metadata for SepaMandate.create
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_mandate_create_spec(&$params) {
  $params['entity_id'] = array(
    'name'         => 'entity_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Entity ID of linked contribution',
  );
  $params['entity_table'] = array(
    'name'         => 'entity_table',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Table name of linked contribution',
    );
  $params['type'] = array(
    'name'         => 'type',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'RCUR for recurring, OOFF for one-off mandate',
  );
  $params['status'] = array(
    'name'         => 'status',
    'api.default'  => 'INIT',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate status',
  );
}


/**
 * Creates a mandate object along with its "contract",
 * i.e. the payment details as recorded in an
 * associated contribution or recurring contribution
 *
 * @author endres -at- systopia.de
 *
 * @return array API result array
 */
function civicrm_api3_sepa_mandate_createfull($params) {
    // create the "contract" first: a contribution
    // TODO: sanity checks

    // get creditor
    try {
      _civicrm_api3_sepa_mandate_adddefaultcreditor($params);
      $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $params['creditor_id']));
    } catch (Exception $e) {
      throw new Exception("Couldn't load creditor [{$params['creditor_id']}].");
    }

    // if BIC is used for this creditor, it is required (see #245)
    if (empty($params['bic'])) {
      if ($creditor['uses_bic']) {
        return civicrm_api3_create_error("BIC is required for creditor [{$params['creditor_id']}].");
      } else {
        $params['bic'] = 'NOTPROVIDED';
      }
    }

    $create_contribution = $params; // copy array
    $create_contribution['version'] = 3;
    if (isset($create_contribution['contribution_contact_id'])) {
    	// in case someone wants another contact for the contribution than for the mandate...
    	$create_contribution['contact_id'] = $create_contribution['contribution_contact_id'];
    }
	  if (empty($create_contribution['currency']))
		  $create_contribution['currency'] = $creditor['currency'];

	  if (empty($create_contribution['contribution_status_id']))
		  $create_contribution['contribution_status_id'] = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

    if ($params['type']=='RCUR') {
    	$contribution_entity = 'ContributionRecur';
	    $contribution_table  = 'civicrm_contribution_recur';
	    if (!isset($create_contribution['payment_instrument_id'])) {
        $create_contribution['payment_instrument_id'] = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'RCUR');
      }
      if (empty($create_contribution['status'])) {
        $create_contribution['status'] = 'FRST'; // set default status
      }
      if (empty($create_contribution['is_pay_later'])) {
        $create_contribution['is_pay_later'] = 1; // set default pay_later
      }
    } elseif ($params['type']=='OOFF') {
	 	$contribution_entity = 'Contribution';
	    $contribution_table  = 'civicrm_contribution';
      	$create_contribution['payment_instrument_id'] = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'OOFF');
      	if (empty($create_contribution['status']))
      		$create_contribution['status'] = 'OOFF'; // set default status
      	if (empty($create_contribution['total_amount']))
      		$create_contribution['total_amount'] = $create_contribution['amount']; // copy from amount

    } else {
    	return civicrm_api3_create_error('Unknown mandata type: '.$params['type']);
    }

    // create the contribution
    $contribution = civicrm_api($contribution_entity, "create", $create_contribution);
    if (!empty($contribution['is_error'])) {
    	return $contribution;
    }

    // create the mandate object itself
    // TODO: sanity checks
    $create_mandate = $create_contribution; // copy array
    $create_mandate['version'] = 3;
    $create_mandate['entity_table'] = $contribution_table;
    $create_mandate['entity_id'] = $contribution['id'];
    $mandate = civicrm_api("SepaMandate", "create", $create_mandate);
    if (!empty($mandate['is_error'])) {
    	// this didn't work, so we also have to roll back the created contribution
    	$delete = civicrm_api($contribution_entity, "delete", array('id'=>$contribution['id'], 'version'=>3));
    	if (!empty($delete['is_error'])) {
    		Civi::log()->debug("org.project60.sepa: createfull couldn't roll back created contribution: ".$delete['error_message']);
    	}
    }
	return $mandate;
}

/**
 * Provide Metadata for SepaMandate.createfull
 */
function _civicrm_api3_sepa_mandate_createfull_spec(&$params) {
  $params['contact_id'] = array(
    'name'         => 'contact_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Contact ID for the new mandate',
  );
  $params['type'] = array(
    'name'         => 'type',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'RCUR for recurring, OOFF for one-off',
  );
  $params['iban'] = array(
    'name'         => 'iban',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'IBAN (account number) for the mandate',
  );
  $params['bic'] = array(
    'name'         => 'bic',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'BIC (bank number) for the mandate',
  );
  $params['amount'] = array(
    'name'         => 'amount',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_FLOAT,
    'title'        => 'Nominal (installment) amount',
  );
  $params['reference'] = array(
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate reference. Will be calculated if omitted',
  );
  $params['source'] = array(
    'name'         => 'source',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Source of the mandate',
  );
  $params['date'] = array(
    'name'         => 'date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE+CRM_Utils_Type::T_TIME,
    'title'        => 'Signature date',
  );
  $params['creation_date'] = array(
    'name'         => 'creation_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE+CRM_Utils_Type::T_TIME,
    'title'        => 'Creation date',
  );
  $params['validation_date'] = array(
    'name'         => 'validation_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE+CRM_Utils_Type::T_TIME,
    'title'        => 'Validation date',
  );
  $params['is_enabled'] = array(
    'name'         => 'is_enabled',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate enabled?',
  );
  $params['creditor_id'] = array(
    'name'         => 'creditor_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Creditor ID. Default creditor used if omitted',
  );
  $params['financial_type_id'] = array(
    'name'         => 'financial_type_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Financial type of the contribution(s)',
  );
  $params['campaign_id'] = array(
    'name'         => 'campaign_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Campaign of the contribution(s)',
  );
  $params['receive_date'] = array(
    'name'         => 'receive_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE+CRM_Utils_Type::T_TIME,
    'title'        => 'Collection date (only for OOFF)',
  );
  $params['start_date'] = array(
    'name'         => 'start_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE+CRM_Utils_Type::T_TIME,
    'title'        => 'Start of collection (only for RCUR)',
  );
  $params['end_date'] = array(
    'name'         => 'end_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_DATE+CRM_Utils_Type::T_TIME,
    'title'        => 'Start of collection (only for RCUR)',
  );
  $params['frequency_interval'] = array(
    'name'         => 'frequency_interval',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Collection interval (together with frequency_unit, only for RCUR)',
  );
  $params['frequency_unit'] = array(
    'name'         => 'frequency_unit',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Collection interval unit (together with frequency_interval, only for RCUR)',
  );
  $params['cycle_day'] = array(
    'name'         => 'cycle_day',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Day of the month (for collection, only for RCUR)',
  );
}



/**
 * Update next scheduled collection day
 *
 * This can be used for migration or recovery after profound changes
 */
function civicrm_api3_sepa_mandate_update_next_scheduled_date($params) {
  $restrictions = array();
  if ($params['mode'] == 'empty') {
    $restrictions[] = 'civicrm_contribution_recur.next_sched_contribution_date IS NULL';
  } elseif ($params['mode'] == 'all') {
    // no extra restrictions
  } else {
    return civicrm_api3_create_error("Please select mode 'empty' or 'all'.");
  }

  if (!empty($params['mandate_ids'])) {
    // sanitize input string
    $mandate_id_list = preg_replace("#[^0-9,]#", '', $params['mandate_ids']);
    if (!empty($mandate_id_list)) {
      $restrictions[] = "civicrm_sdd_mandate.id IN ($mandate_id_list)";
    }
  }

  // add general restrictions
  $restrictions[] = "civicrm_sdd_mandate.type = 'RCUR'"; // only type RCUR
  $restrictions[] = "civicrm_sdd_mandate.status IN ('FRST','RCUR')"; // only active ones
  $restrictions_sql = '(' . implode(') AND (', $restrictions) . ')';

  $query = "
  SELECT
    civicrm_contribution_recur.id   AS civicrm_contribution_recur_id,
    civicrm_sdd_mandate.creditor_id AS creditor_id
  FROM civicrm_contribution_recur
  LEFT JOIN civicrm_sdd_mandate ON civicrm_contribution_recur.id = civicrm_sdd_mandate.entity_id AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
  WHERE {$restrictions_sql}
  ORDER BY civicrm_sdd_mandate.creditor_id";

  $recurring_contributions = CRM_Core_DAO::executeQuery($query);
  $counter = 0;
  $updater = NULL;
  while ($recurring_contributions->fetch()) {
    $counter++;

    if (!$updater || !$updater->usesCreditor($recurring_contributions->creditor_id)) {
      $updater = new CRM_Sepa_Logic_NextCollectionDate($recurring_contributions->creditor_id);
    }

    $updater->updateNextCollectionDate($recurring_contributions->civicrm_contribution_recur_id, NULL);
  }

  $null = NULL;
  return civicrm_api3_create_success(array(), $params, $null, $null, $null, array('count' => $counter));
}

/**
 * Provide Metadata for SepaMandate.create
 */
function _civicrm_api3_sepa_mandate_update_next_scheduled_date_spec(&$params) {
  $params['mode'] = array(
    'name'         => 'mode',
    'api.default'  => 'empty',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Should all be updated ("all") or only empty ones ("empty")',
    );
  $params['mandate_ids'] = array(
    'name'         => 'mandate_ids',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Restrict to the given (comma separated) list of mandate IDs',
    );
}

/**
 * Deletes an existing Mandate
 *
 * @param  array  $params
 *
 * @return array true if successfull, error otherwise
 * {@getfields sepa_mandate_delete}
 * @access public
 */
function civicrm_api3_sepa_mandate_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_mandates
 *
 * @param  array input parameters
 *
 *
 * @example SepaCreditorGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_mandate_get}
 * @access public
 */
function civicrm_api3_sepa_mandate_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}


/**
 * Modify/update mandates
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/413
 */
function civicrm_api3_sepa_mandate_modify($params) {
  if (!CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')) {
    return civicrm_api3_create_error("Mandate modification not allowed. Check your settings.");
  }

  // look up mandate ID if only reference is given
  if (empty($params['mandate_id']) && !empty($params['reference'])) {
    $mandate = civicrm_api3('SepaMandate', 'get', array('reference' => $params['reference'], 'return' => 'id'));
    if ($mandate['id']) {
      $params['mandate_id'] = $mandate['id'];
    } else {
      return civicrm_api3_create_error("Couldn't identify mandate with reference '{$params['reference']}'.");
    }
  }

  // no mandate could be identified
  if (empty($params['mandate_id'])) {
    return civicrm_api3_create_error("You need to provide either 'mandate_id' or 'reference'.");
  }

  try {
    $changes = CRM_Sepa_BAO_SEPAMandate::modifyMandate($params['mandate_id'], $params);
    return civicrm_api3_create_success($changes);
  } catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
}

/**
 * API specs for updating mandates
 */
function _civicrm_api3_sepa_mandate_modify_spec(&$params) {
  $params['mandate_id'] = array(
    'name'         => 'mandate_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate ID',
  );
  $params['reference'] = array(
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate Reference',
  );
  $params['amount'] = array(
    'name'         => 'amount',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New Amount',
  );
  $params['iban'] = array(
    'name'         => 'iban',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New IBAN',
  );
  $params['bic'] = array(
    'name'         => 'bic',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New BIC',
  );
  $params['financial_type_id'] = array(
    'name'         => 'financial_type_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'New Financial Type ID',
  );
  $params['campaign_id'] = array(
    'name'         => 'campaign_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'New Campaign ID',
  );
}


/**
 * Terminate mandates responsibly
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/483
 */
function civicrm_api3_sepa_mandate_terminate($params) {
  // look up mandate ID if only reference is given
  if (empty($params['mandate_id']) && !empty($params['reference'])) {
    $mandate = civicrm_api3('SepaMandate', 'get', array('reference' => $params['reference'], 'return' => 'id'));
    if ($mandate['id']) {
      $params['mandate_id'] = $mandate['id'];
    } else {
      return civicrm_api3_create_error("Couldn't identify mandate with reference '{$params['reference']}'.");
    }
  }

  // no mandate could be identified
  if (empty($params['mandate_id'])) {
    return civicrm_api3_create_error("You need to provide either 'mandate_id' or 'reference'.");
  }

  try {
    $success = CRM_Sepa_BAO_SEPAMandate::terminateMandate(
      $params['mandate_id'],
      date('Y-m-d', strtotime(CRM_Utils_Array::value('end_date', $params, 'today'))),
      CRM_Utils_Array::value('cancel_reason', $params),
      FALSE);
    if ($success) {
      return civicrm_api3_create_success();
    } else {
      return civicrm_api3_create_error("Mandate couldn't be closed (again).");
    }

  } catch (Exception $e) {

    return civicrm_api3_create_error($e->getMessage());
  }
}

/**
 * API specs for updating mandates
 */
function _civicrm_api3_sepa_mandate_terminate_spec(&$params) {
  $params['mandate_id'] = array(
    'name'         => 'mandate_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate ID',
  );
  $params['reference'] = array(
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate Reference',
  );
  $params['end_date'] = array(
    'name'         => 'end_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'End Date',
    'description'  => 'Default is NOW',
  );
  $params['cancel_reason'] = array(
    'name'         => 'cancel_reason',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Cancel Reason',
  );
}


/**
 * HELPER FUNCTION
 *
 * will add the default creditor_id if no id and creditor_id is given, and the
 * default creditor is valid
 */
function _civicrm_api3_sepa_mandate_adddefaultcreditor(&$params) {
  if (empty($params['id']) && empty($params['creditor_id'])) {
    $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    if ($default_creditor != NULL) {
      $params['creditor_id'] = $default_creditor->id;
    }
  }
}

