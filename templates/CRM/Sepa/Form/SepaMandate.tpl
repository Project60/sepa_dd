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

<div class="crm-accordion-wrapper crm-accordion_title-accordion crm-accordion-processed" id="sepa">
      <div class="crm-accordion-header">
        {ts domain="org.project60.sepa"}Sepa Mandate{/ts}
      </div>
      <div class="crm-accordion-body">
        <table class="form-layout-compressed" >
<tr id="crmf-is_active">
  <td class="label">{$form.sepa_active.label}</td>
  <td>{$form.sepa_active.html}</td>
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
</div>
</div>

{literal}
<script>
cj(function($) {
  if ($('#paymentDetails_Information').length >0) {
    $('#sepa').insertAfter('#paymentDetails_Information');
  } else {
    $('#sepa').insertAfter('.form-layout');
  }
});
</script>
{/literal}


