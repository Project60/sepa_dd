{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

{if $bank_account_number}
<div id="sepa-new-info-block" class="crm-group">
  <div class="header-dark">{ts domain="org.project60.sepa"}Direct Debit Payment{/ts}</div>
  <p id="sepa-confirm-text-account">{ts domain="org.project60.sepa"}This payment will be debited from the following account:{/ts}</p>
  <table class="sepa-confirm-text-account-details display" id="sepa-confirm-text-account-details">
    <tr><td>{ts domain="org.project60.sepa"}Account Holder{/ts}</td> <td>{$account_holder}</td> </tr>
    <tr><td>{ts domain="org.project60.sepa"}IBAN{/ts}</td> <td>{$bank_account_number}</td> </tr>
    <tr><td>{ts domain="org.project60.sepa"}BIC{/ts}</td>  <td>{$bank_identification_number}</td>  </tr>
  </table>
</div>

<script type="text/javascript">
cj("div.credit_card-group").replaceWith(cj("#sepa-new-info-block"));
cj("div.billing_name_address-group").hide();
</script>
{/if}