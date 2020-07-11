{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

<table>
  <tr id="selectSDD">
    <td class="label">{$form.is_sdd.label}</td>
    <td>
      {$form.is_sdd.html}
      <br/>
      <span class="description">
        Check this box to create an SDD mandate for this membership. You will be able to enter the debtor details.
      </span>
    </td>
  </tr>
  <tr id="selectSDDParams" {if $form.is_sdd.value eq 0}style="display: none; "{/if}>
    <td class="label"></td>
    <td>
      <table class="form-layout-compressed" style="border: 1px solid white;background-color: #E9E9E9;">
        <tr id="crmf-creditor_name">
          <td class="label">Creditor</td>
          <td><input type="test" readonly value="{$creditor_name}"></td>
        </tr>
        <tr id="crmf-mref">
          <td class="label">{$form.mref.label}</td>
          <td>{$form.mref.html}</td>
        </tr>
        <tr id="crmf-account-holder">
          <td class="label">{$form.account_holder.label}</td>
          <td>{$form.account_holder.html}</td>
        </tr>
        <tr id="crmf-iban">
          <td class="label">{$form.bank_iban.label}</td>
          <td>{$form.bank_iban.html}</td>
        </tr>
        <tr id="crmf-bic">
          <td class="label">{$form.bank_bic.label}</td>
          <td>{$form.bank_bic.html}</td>
        </tr>
      </table>
      <table class="form-layout-compressed" style="">
        <tr id="crmf-sdd_amount">
          <td class="label">{$form.sdd_amount.label}</td>
          <td>{$form.sdd_amount.html} {$form.sdd_curr.html}</td>
        </tr>
        <tr id="crmf-sdd_frequency">
          <td class="label"><label for="sdd_frequency">{ts domain="org.project60.sepa"}Debit frequency{/ts}</label></td>
          <td>
            {$form.sdd_frequency.0.html}
            <br/>
            {$form.sdd_frequency.1.html}
            <br/>
            {$form.sdd_frequency.3.html}
            <br/>
            {$form.sdd_frequency.6.html}
            <br/>
            {$form.sdd_frequency.12.html}
          </td>
        </tr>
        <tr id="crmf-sdd_start_date">
          <td class="label">{$form.sdd_start_date.label}</td>
          <td>{include file="CRM/common/jcalendar.tpl" elementName="sdd_start_date"}</td>
        </tr>
        <tr id="crmf-is_active">
          <td class="label">{$form.sepa_active.label}</td>
          <td>{$form.sepa_active.html}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

{literal}
  <script>
    cj(function($) {
      $('#selectSDD').insertAfter('#contri');
      $('#is_sdd').click(function(){
        if ($(this).is(':checked')) $('#selectSDDParams').show();
        else $('#selectSDDParams').hide();
        });
      $('#selectSDDParams').insertAfter('#selectSDD');
    });
  </script>
{/literal}


