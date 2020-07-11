<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit - PHPUnit tests         |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
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
 * Tests for mandates.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *  Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *  rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *  If this test needs to manipulate schema or truncate tables, then either:
 *     a. Do all that using setupHeadless() and Civi\Test.
 *     b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Sepa_MandateTest extends CRM_Sepa_TestBase
{
  /**
   * Test the creation of an OOFF mandate.
   * @see Case_ID M01
   */
  public function testOOFFCreate()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );
    $contribution = $this->getLatestContributionForMandate($mandate);

    $this->assertSame(self::MANDATE_TYPE_OOFF, $mandate['status'], E::ts('OOFF Mandate after creation is incorrect.'));
    $this->assertSame(
      self::CONTRIBUTION_STATUS_PENDING,
      $contribution['contribution_status_id'],
      E::ts('OOFF contribution after creation is incorrect.')
    );
  }

  /**
   * Test the creation of a RCUR mandate.
   * @see Case_ID M02
   */
  public function testRCURCreate()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    $recurringContribution = $this->getRecurringContributionForMandate($mandate);

    $this->assertSame(self::MANDATE_TYPE_FRST, $mandate['status'], E::ts('RCUR Mandate after creation is incorrect.'));
    $this->assertSame(
      self::RECURRING_CONTRIBUTION_STATUS_PENDING,
      $recurringContribution['contribution_status_id'],
      E::ts('RCUR contribution after creation is incorrect.')
    );
  }

  /**
   * Test the batching for an OOFF mandate.
   * @see Case_ID M01
   */
  public function testOOFFBatch()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $batchedMandate = $this->getMandate($mandate['id']);
    $batchedContribution = $this->getLatestContributionForMandate($batchedMandate);
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_OOFF);

    $this->assertSame(
      self::MANDATE_TYPE_OOFF,
      $batchedMandate['status'],
      E::ts('OOFF Mandate status after batching is incorrect.')
    );
    $this->assertSame(
      self::CONTRIBUTION_STATUS_PENDING,
      $batchedContribution['contribution_status_id'],
      E::ts('OOFF contribution status after batching is incorrect.')
    );
    $this->assertSame(
      self::BATCH_STATUS_OPEN,
      $transactionGroup['status_id'],
      E::ts('OOFF transaction group status after batching is incorrect.')
    );
  }

  /**
   * Test the batching for a RCUR mandate.
   * @see Case_ID M02
   */
  public function testRCURBatch()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    // RCUR mandates are splitted into two types: FRST for the first contribution, RCUR for every one after that:
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $batchedMandate = $this->getMandate($mandate['id']);
    $batchedContribution = $this->getLatestContributionForMandate($batchedMandate);
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);

    $this->assertSame(
      self::MANDATE_TYPE_FRST,
      $batchedMandate['status'],
      E::ts('RCUR Mandate status after batching is incorrect.')
    );
    $this->assertSame(
      self::CONTRIBUTION_STATUS_PENDING,
      $batchedContribution['contribution_status_id'],
      E::ts('RCUR contribution status after batching is incorrect.')
    );
    $this->assertSame(
      self::BATCH_STATUS_OPEN,
      $transactionGroup['status_id'],
      E::ts('RCUR transaction group status after batching is incorrect.')
    );
  }

  /**
   * Test the closing of an OOFF mandate.
   * @see Case_ID M01
   */
  public function testOOFFClose()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
      ]
    );

    $this->executeBatching(self::MANDATE_TYPE_OOFF);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_OOFF);

    $this->closeTransactionGroup($transactionGroup['id']);

    $closedMandate = $this->getMandate($mandate['id']);
    $closedContribution = $this->getLatestContributionForMandate($closedMandate);
    $closedTransactionGroup = $this->getTransactionGroup($transactionGroup['id']);

    $this->assertSame(
      self::MANDATE_STATUS_SENT,
      $closedMandate['status'],
      E::ts('OOFF Mandate status after closing is incorrect.')
    );
    $this->assertSame(
      self::CONTRIBUTION_STATUS_IN_PROGRESS,
      $closedContribution['contribution_status_id'],
      E::ts('OOFF contribution status after closing is incorrect.')
    );
    $this->assertSame(
      self::BATCH_STATUS_CLOSED,
      $closedTransactionGroup['status_id'],
      E::ts('OOFF transaction group status after closing is incorrect.')
    );
  }

  /**
   * Test the closing of a RCUR mandate group.
   * @see Case_ID M02
   */
  public function testRCURClose()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    // RCUR mandates are splitted into two types: FRST for the first contribution, RCUR for every one after that:
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $this->executeBatching(self::MANDATE_TYPE_RCUR);

    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);

    $this->closeTransactionGroup($transactionGroup['id']);

    $closedMandate = $this->getMandate($mandate['id']);
    $closedRecurringContribution = $this->getRecurringContributionForMandate($closedMandate);
    $closedTransactionGroup = $this->getTransactionGroup($transactionGroup['id']);

    $this->assertSame(
      self::MANDATE_TYPE_RCUR,
      $closedMandate['status'],
      E::ts('RCUR Mandate status after closing is incorrect.')
    );
    $this->assertSame(
      self::RECURRING_CONTRIBUTION_STATUS_PENDING,
      $closedRecurringContribution['contribution_status_id'],
      E::ts('RCUR contribution status after closing is incorrect.')
    );
    $this->assertSame(
      self::BATCH_STATUS_CLOSED,
      $closedTransactionGroup['status_id'],
      E::ts('RCUR transaction group status after closing is incorrect.')
    );
  }

  /**
   * Test if status and first_contribution_id are correctly set in FRST -> RCUR transition
   *
   * @see Case_ID M02
   */
  public function testRCURFirstContributionID()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_RCUR,
      ]
    );

    // the status should be FRST, and first_contribution_id empty
    $this->assertSame(self::MANDATE_TYPE_FRST,  $mandate['status'],  E::ts('Mandate status of new mandate is incorrect.'));
    $this->assertEmpty(CRM_Utils_Array::value('first_contribution_id', $mandate), "first_contribution_id should not be set yet.");

    // batch & check
    $this->executeBatching(self::MANDATE_TYPE_FRST);
    $batchedMandate = $this->getMandate($mandate['id']);
    $this->assertSame(self::MANDATE_TYPE_FRST,  $batchedMandate['status'],  E::ts('Mandate status of new mandate is incorrect.'));
    $this->assertEmpty(CRM_Utils_Array::value('first_contribution_id', $batchedMandate), "first_contribution_id should not be set yet.");

    // close & check
    $transactionGroup = $this->getActiveTransactionGroup(self::MANDATE_TYPE_FRST);
    $this->closeTransactionGroup($transactionGroup['id']);
    $rcurMandate = $this->getMandate($mandate['id']);
    $this->assertSame(self::MANDATE_TYPE_RCUR,  $rcurMandate['status'],  E::ts('Mandate status of new mandate is incorrect.'));
    $this->assertNotEmpty(CRM_Utils_Array::value('first_contribution_id', $rcurMandate), "first_contribution_id should be set now.");
  }

  /**
   * Test the creation of a mandate with non-empty account holder.
   * @see Case_ID M01
   */
  public function testOOFFCreateAccountHolder()
  {
    $mandate = $this->createMandate(
      [
        'type' => self::MANDATE_TYPE_OOFF,
        'account_holder' => "Test Account Holder"
      ]
    );

    $this->assertSame("Test Account Holder", $mandate['account_holder'], E::ts('Mandate account holder after creation is incorrect.'));
  }
}
