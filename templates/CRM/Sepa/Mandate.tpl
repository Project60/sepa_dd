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

<div id="payment_information">
<fieldset class="billing_mode-group direct_debit_info-group">
<legend>
{ts domain="org.project60.sepa"}Direct Debit Information{/ts}
</legend>

<div class="crm-section {$form.account_holder.name}-section">
	<div class="label">{$form.account_holder.label}</div>
	<div class="content">{$form.account_holder.html}</div>
	<div class="clear"></div>
</div>
<div class="crm-section {$form.bank_iban.name}-section">
	<div class="label">{$form.bank_iban.label}</div>
	<div class="content">{$form.bank_iban.html}</div>
	<div class="clear"></div>
</div>
<div class="crm-section {$form.bank_bic.name}-section">
	<div class="label">{$form.bank_bic.label}</div>
	<div class="content">{$form.bank_bic.html}</div>
	<div class="clear"></div>
</div>
<div class="crm-section {$form.cycle_day.name}-section">
	<div class="label">{$form.cycle_day.label}</div>
	<div class="content">{$form.cycle_day.html}</div>
	<div class="clear"></div>
</div>
<div class="crm-section {$form.frequency.name}-section">
	<div class="label">{$form.frequency.label}</div>
	<div class="content">{$form.frequency.html}</div>
	<div class="clear"></div>
</div>
<div class="crm-section {$form.start_date.name}-section">
	<div class="label">{$form.start_date.label}</div>
	<div class="content">{$form.start_date.html}</div>
	<div class="clear"></div>
</div>


</div>
