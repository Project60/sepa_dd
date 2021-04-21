<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP                           |
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
 * File for the CiviCRM APIv3 sepa_contribution_group functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaTransactionGroup for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaTransactionGroupCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_transaction_group_create}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_transaction_group_create_spec(&$params) {
  $params['reference']['api.required'] = 1;
  $params['type']['api.required'] = 1;
  $params['status_id']['api.default'] = 2; //Close
  $params['sdd_creditor_id']['api.required'] = 1;
  $params['created_date']['api.default'] = 'now';
}

/**
 * Deletes an existing SepaTransactionGroup
 *
 * @param  array  $params
 *
 * @example SepaTransactionGroupDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_transaction_group_delete}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_transaction_groups
 *
 * @param  array input parameters
 *
 *
 * @example SepaTransactionGroupGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_transaction_group_get}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function civicrm_api3_sepa_transaction_group_getdetail($params) {
//  $where = "txgroup.id= txgroup_contrib.txgroup_id AND txgroup_contrib.contribution_id = contrib.id";
  $where = "1";
  $orderby = "ORDER BY txgroup.latest_submission_date ASC";
  if (array_key_exists("id",$params)) {
    $group = (int) $params["id"];
    $where .= " AND txgroup.id = $group ";
  }
  if (array_key_exists("file_id",$params)) {
    $file_id = (int) $params["file_id"];
    $where .= " AND sdd_file_id = $file_id ";
  }
  if (array_key_exists("status_ids",$params)) {
    $status_ids = $params["status_ids"];
    $where .= " AND txgroup.status_id IN ($status_ids) ";
  }
  if (array_key_exists("order_by",$params)) {
    if ($params["order_by"] == 'file.created_date') {
      $orderby = "ORDER BY civicrm_sdd_file.created_date DESC";
    }
  }

  $sql="
    SELECT
      txgroup.id,
      txgroup.reference,
      sdd_file_id                       AS file_id,
      txgroup.type,
      txgroup.collection_date,
      txgroup.latest_submission_date,
      txgroup.status_id,
      txgroup.sdd_creditor_id           AS creditor_id,
      civicrm_sdd_file.created_date     AS file_created_date,
      count(*)                          AS nb_contrib,
      sum( contrib.total_amount)        AS total,
      contrib.currency                  AS currency,
      civicrm_sdd_file.reference        AS file
    FROM civicrm_sdd_txgroup as txgroup
    LEFT JOIN civicrm_sdd_contribution_txgroup as txgroup_contrib on txgroup.id = txgroup_contrib.txgroup_id
    LEFT JOIN civicrm_contribution as contrib on txgroup_contrib.contribution_id = contrib.id
    LEFT JOIN civicrm_sdd_file on sdd_file_id = civicrm_sdd_file.id
    WHERE $where
    GROUP BY 1, 2, 3, 4, 5, 6, 7, 8, 9, 12, 13
    $orderby;";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $result= array();
  $total =0;
  while ($dao->fetch()) {
    $result[] = $dao->toArray();
    $total += $dao->total;
  }
  return civicrm_api3_create_success($result, $params, NULL, NULL, $dao, $extraReturnValues = array("total_amount"=>$total));
}


function _civicrm_api3_sepa_transaction_group_close_spec (&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_transaction_group_close($params) {
  $result=array();
  // a single query much better for perf, but no hook called. To be revised later?
  $sql="update civicrm_contribution as contrib, civicrm_sdd_contribution_txgroup
    set contribution_status_id=1
    where contribution_id = contrib.id and txgroup_id=%1";
  $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($params['id'], 'Integer')));
  return civicrm_api3_sepa_transaction_group_create (array("id"=>$params['id'],status_id=>2));
}


function _civicrm_api3_sepa_transaction_group_createnext_spec (&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_transaction_group_createnext ($params) {
  $errors=$counter=0;
  $values=array();
  $group = (int) $params["id"];
  if (!$group)
    throw new API_Exception("Incorrect or missing value for group id");
  $contribs = civicrm_api("sepa_contribution_group","getdetail", $params);

  foreach ($contribs["values"] as $old) {
    if (!$old['recur_id'])
      throw new API_Exception("Trying to create next payment for non-recurrent contribution?");
    $date = strtotime(substr($old["receive_date"], 0, 10));
    $next_collectionDate = strtotime ("+". $old["frequency_interval"] . " ".$old["frequency_unit"],$date);
    $next_collectionDate = date('YmdHis', $next_collectionDate);
    $new = $old;
    $new["hash"] = md5(uniqid(rand(), true));
    $new["source"] = "SEPA recurring contribution";
    unset($new["id"]);
    unset($new["contribution_id"]);
    $new["receive_date"] = $next_collectionDate;
    $new["contribution_status_id"]= 2;
    $new["contribution_recur_id"] = $new["recur_id"];
    unset($new["recur_id"]);

    /*
       CRM_Core_DAO::executeQuery("
       UPDATE civicrm_contribution_recur
       SET next_sched_contribution = %1
       WHERE id = %2
       ", array(
       1 => array($next_collectionDate, 'String'),
       2 => array($new["contribution_recur_id"], 'Integer')
       )
       );
     */
    $new["version"] =3;
    $new["sequential"] =1;

/*
$total += $new["total_amount"];
++$counter;
continue;
*/
    $result = civicrm_api('contribution', 'create',$new);
    if ($result['is_error']) {
      $output[] = $result['error_message'];
      ++$errors;
      continue;
    } else {
      ++$counter;
      $total += $result["total_amount"];
      $mandate = new CRM_Sepa_BAO_SEPAMandate();
      $contrib = new CRM_Contribute_BAO_Contribution();
      $contrib->get('id', $result["id"]);//it sucks to have to fetch again, just to get the BAO
//      $mandate->get('id', $old["mandate_id"]);
//      $values[] = $result["values"];
      $group = CRM_Sepa_Logic_Batching::batchContributionByCreditor ($contrib, $old["creditor_id"],$old["payment_instrument_id"]);
      $values = $group->toArray();
    }
  }
  if (!$errors) {
    $values["nb_contrib"] = $counter;
    $values["total"]=$total;
    return civicrm_api3_create_success(array($values), $params, 'address', $contrib);
  } else {
    civicrm_api3_create_error("Could not create ".$errors. " new contributions",$output);
  }
}



/**
 * This API call creates a corresponding accounting batch for a SEPA group
 *
 * @param txgroup_id
 * @author endres -at- systopia.de
 */
function civicrm_api3_sepa_transaction_group_toaccgroup($params) {
  // first, load the txgroup
  $txgroup_id = $params['txgroup_id'];
  $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id' => $txgroup_id, 'version' => 3));
  if (isset($txgroup['is_error']) && $txgroup['is_error']) {
    return civicrm_api3_create_error("Cannot read transaction group ".$txgroup_id);
  }

  if (isset($txgroup['sdd_file_id'])) {
    $sdd_file = civicrm_api('SepaSddFile', 'getsingle', array('id' => $txgroup['sdd_file_id'], 'version' => 3));
    if (isset($sdd_file['is_error']) && $sdd_file['is_error']) {
      return civicrm_api3_create_error("Cannot read sdd file ".$txgroup['sdd_file_id']);
    }
  } else {
    $sdd_file = array(
        'created_id' => CRM_Core_Session::singleton()->get('userID'),
        'created_date' => date('YmdHis'));
  }

  // gather information on the group
  $contributions_query_sql = "
  SELECT
    contribution.total_amount     AS amount,
    entity_trxn.financial_trxn_id AS financial_trxn_id,
    contribution.id               AS contribution_id
  FROM civicrm_sdd_contribution_txgroup   AS txgroup_contrib
  LEFT JOIN civicrm_contribution          AS contribution ON txgroup_contrib.contribution_id = contribution.id
  LEFT JOIN civicrm_entity_financial_trxn AS entity_trxn  ON entity_trxn.entity_id = contribution.id AND entity_trxn.entity_table='civicrm_contribution'
  WHERE txgroup_contrib.txgroup_id = $txgroup_id
  GROUP BY contribution.id;";

  $contributions_query = CRM_Core_DAO::executeQuery($contributions_query_sql);
  $transactions = array();
  $contributions_missing_transaction = array();
  $total = 0.0;
  while ($contributions_query->fetch()) {
    if ($contributions_query->financial_trxn_id) {
      array_push($transactions, $contributions_query->financial_trxn_id);
      $total += $contributions_query->amount;
    } else {
      array_push($contributions_missing_transaction, $contributions_query->contribution_id);
    }
  }

  // find a name
  $name = $wanted_name = 'SEPA '.$txgroup['reference'];
  $counter = 0;
  while (CRM_Core_DAO::executeQuery("SELECT id FROM civicrm_batch WHERE title='$name';")->fetch()) {
    $counter++;
    $name = $wanted_name.'_'.$counter;
  }

  // get type id
  $type_id = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'type_id', 'SEPA DD Transaction Batch');
  if (!$type_id) {
    // create SEPA type entry if not exists
    $value_spec = array('name' => 'SEPA DD Transaction Batch', 'label' => ts('SEPA DD Transaction Batch', array('domain' => 'org.project60.sepa')), 'is_active' => 1);
    $group_spec = array('name' => 'batch_type');
    $action = CRM_Core_Action::ADD;
    $type_id = CRM_Core_OptionValue::addOptionValue($value_spec, $group_spec, $action)->value;
  }

  // then, finally, create the accounting group
  $description = sprintf(ts('This group corresponds to <a href="%s">SEPA transaction group [%s]</a>', array('domain' => 'org.project60.sepa')),
      CRM_Utils_System::url('civicrm/sepa/listgroup', "group_id=$txgroup_id"), $txgroup_id);

  $batch = array( 'title'                 => $name,
                  'description'           => $description,
                  'created_id'            => $sdd_file['created_id'],
                  'created_date'          => $txgroup['created_date'],
                  'modified_id'           => CRM_Core_Session::singleton()->get('userID'),
                  'modified_date'         => date('YmdHis'),
                  'status_id'             => $txgroup['status_id'],
                  'type_id'               => $type_id,
                  'mode_id'               => (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'mode_id', 'Automatic Batch'),
                  'total'                 => $total,
                  'item_count'            => count($transactions),
                  'payment_instrument_id' => (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', $txgroup['type']),
                  'exported_date'         => $sdd_file['created_date'],
                  'version'               => 3);
  $batch_create = civicrm_api('Batch', 'create', $batch);
  if (isset($batch_create['is_error']) && $batch_create['is_error']) {
    return civicrm_api3_create_error("Cannot create batch for SEPA transaction group ".$txgroup_id);
  } else {
    $batch_id = $batch_create['id'];
    if (count($contributions_missing_transaction)) {
      $batch_create['contributions_missing_transaction'] = $contributions_missing_transaction;
    }
  }

  // add all the financial transactions to the group
  foreach ($transactions as $trxn_id) {
    CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_entity_batch ( entity_table, entity_id, batch_id ) VALUES ('civicrm_financial_trxn', $trxn_id, $batch_id);");
  }

  return civicrm_api3_create_success($batch_create, $params);
}

function _civicrm_api3_sepa_transaction_group_toaccgroup_spec(&$params) {
  $params['txgroup_id']['api.required'] = 1;
}

