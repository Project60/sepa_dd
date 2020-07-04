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

{literal}
<style>
.create_mandate td {
	white-space: nowrap;
	min-width: 20em;
	vertical-align: middle;
}
</style>
{/literal}


{if $submit_url}
<form id='new_sepa_mandate' action="{$submit_url}" method="post">
	<input type="hidden" name="contact_id" value="{$contact_id}" />
	<table>
		<tr>	<!-- CREDITOR -->
			<td>{ts domain="org.project60.sepa"}Creditor{/ts}:</td>
			<td>
				<select name="creditor_id" onChange='sepa_update_cycledays();' >
					{foreach from=$creditors item=name key=id}
					<option value="{$id}" {if $id eq $creditor_id}selected{/if}>{$name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		<tr>	<!-- CONTACT -->
			<td>{ts domain="org.project60.sepa"}Contact{/ts}:</td>
			<td><input disabled name="contact" type="text" size="40" value="{$display_name}"/></td>
		</tr>
		<tr>	<!-- AMOUNT -->
			<td>{ts domain="org.project60.sepa"}Amount{/ts}:</td>
			<td><input name="total_amount" type="number" size="6" step="0.01" value="{$total_amount}" /></td>
		</tr>
		<tr>	<!-- FINANCIAL TYPE -->
			<td>{ts domain="org.project60.sepa"}Financial Type{/ts}:</td>
			<td>
				<select name="financial_type_id">
					{foreach from=$financial_types item=name key=id}
					<option value="{$id}" {if $id eq $financial_type_id}selected{/if}>{$name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		<tr>	<!-- CAMPAIGN -->
			<td>{ts domain="org.project60.sepa"}Campaign{/ts}:</td>
			<td>
				<select name="campaign_id">
					{foreach from=$campaigns item=name key=id}
					<option value="{$id}" {if $id eq $campaign_id}selected{/if}>{$name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		<tr>	<!-- MANDATE REFERENCE -->
			<td>{ts domain="org.project60.sepa"}Mandate Reference{/ts}:</td>
			<td><input name="reference" type="text" size="34" value="{$reference}" placeholder="{ts domain="org.project60.sepa"}not required, will be generated{/ts}"/></td>
		</tr>
		<tr>	<!-- SOURCE -->
			<td>{ts domain="org.project60.sepa"}Source{/ts}:</td>
			<td><input name="source" type="text" value="{$source}" placeholder="{ts domain="org.project60.sepa"}not required{/ts}"/></td>
		</tr>
		<tr>	<!-- NOTE -->
			<td id="mandate_note_label">{ts domain="org.project60.sepa"}Note{/ts}:</td>
			<td><input name="note" type="text" size="32" value="{$note}" placeholder="{ts domain="org.project60.sepa"}not required{/ts}"/></td>
		</tr>

		<tr><td colspan="4"><hr></td></tr>

		<tr>	<!-- bank account selector -->
			<td>Bank Account:</td>
			<td>
				<select name="account" id="account">
				{foreach from=$known_accounts item=account}
				<option value="{$account.value}">{$account.name}</option>
				{/foreach}
				</select>
			</td>
			<td id="iban_converter" rowspan="3" style="vertical-align: middle;">
			</td>
		</tr>
		<tr>	<!-- Account Holder -->
			<td>Account Holder:</td>
			<td>
				<input name="account_holder" type="text" size="32"  maxlength="255" value="{$account_holder}"/>
			</td>
		</tr>
		<tr>	<!-- IBAN -->
			<td>IBAN:</td>
			<td>
				<input name="iban" type="text" size="32"  maxlength="35" value="{$iban}"/>
				<a id="bic_lookup_btn" onClick="sepa_lookup_bic();" hidden="1">lookup BIC</a>
			</td>
		</tr>
		<tr>	<!-- BIC -->
			<td>BIC:</td>
			<td>
				<input name="bic" type="text" size="14" maxlength="11" value="{$bic}"/>&nbsp;&nbsp;
				<img id="bic_busy" height="8" src="{$config->resourceBase}i/loading.gif" hidden="1" />
				<font color="gray"><span id="bank_name"></span></font>
			</td>
		</tr>
	</table>

	{if $replace}
	<h3>{ts domain="org.project60.sepa"}Replacing Mandate{/ts}&nbsp;[{$replace}]</h3>
	<input type="hidden" name="replace" value="{$replace}" />
	<table class="create_mandate">
		<tr>	<!-- REPLACE::DATE -->
			<td>{ts domain="org.project60.sepa"}Replacement Date{/ts}:</td>
			<td>
				<input id="replace_date" name="replace_date" type="text" value="{$replace_date}"/>
			</td>
		</tr>
		<tr>	<!-- REPLACE::REASON -->
			<td>{ts domain="org.project60.sepa"}Replacement Reason{/ts}:</td>
			<td>
				<input name="replace_reason" type="text" value="{$replace_reason}"/>
			</td>
		</tr>
	</table>
	{/if}

	<h3>{ts domain="org.project60.sepa"}Mandate Type{/ts}</h3>
	<table class="create_mandate">
		{if not $replace}
		<tr>	<!-- ONE OFF -->
			<td style="vertical-align: top;"><input name="mandate_type" id='mtype_OOFF' type='radio' value="OOFF" {if $mandate_type eq "OOFF" or not $mandate_type}checked{/if}>{ts domain="org.project60.sepa"}One Time{/ts}</input></td>
			<td>{ts domain="org.project60.sepa"}Earliest execution date{/ts}:</td>
			<td>
				<input id="date" name="date" type="text" value="{$date}" onChange='cj("#mtype_OOFF").prop("checked",true);'/>
			</td>
			<td></td>
		</tr>

		<tr><td colspan="3"><div>&nbsp;</div></td></tr>
		{/if}

		<tr>	<!-- RECURRING -->
			<td style="vertical-align: top;" rowspan="4"><input name="mandate_type" id='mtype_RCUR' type='radio' value="RCUR" {if $mandate_type eq "RCUR"}checked{/if}>{ts domain="org.project60.sepa"}Recurring{/ts}</input></td>
			<td>{ts domain="org.project60.sepa"}Start Date{/ts}:</td>
			<td>
				<input id="start_date" name="start_date" type="text" value="{$start_date}" onChange='cj("#mtype_RCUR").prop("checked",true);' />
			<td></td>
		</tr>
		<tr>
			<td>{ts domain="org.project60.sepa"}Collection Date{/ts}:</td>
			<td>
				<select id="default_element_cycle_day" name="cycle_day" onChange='cj("#mtype_RCUR").prop("checked",true);' />
			</td>
			<td></td>
		</tr>
		<tr>
			<td>{ts domain="org.project60.sepa"}Interval{/ts}:</td>
			<td>
				<select class="form-select" id="default_frequency_interval" name="interval" onChange='cj("#mtype_RCUR").prop("checked",true);'>
					<option value="1">{ts domain="org.project60.sepa"}monthly{/ts}</option>
					<option value="3">{ts domain="org.project60.sepa"}quarterly{/ts}</option>
					<option value="6">{ts domain="org.project60.sepa"}semi-annually{/ts}</option>
					<option value="12">{ts domain="org.project60.sepa"}annually{/ts}</option>
				</select>
			</td>
			<td></td>
		</tr>
		<tr>
			<td>{ts domain="org.project60.sepa"}End Date{/ts}:</td>
			<td>
				<input id="end_date" name="end_date" type="text" value="{$end_date}" onChange='cj("#mtype_RCUR").prop("checked",true);' />
			<td></td>
		</tr>
	</table>
	<input type="submit" value="{ts domain="org.project60.sepa"}create{/ts}" />
</form>

{else}
	{* if this is a popup - close it *}
	<script type="text/javascript">
		// update tab
		cj("#sepa_payment_extra_button").closest("div.crm-ajax-container").crmSnippet('refresh');
		// ...then destroy
		cj(".ui-dialog > [id^=crm-ajax-dialog-]").dialog("destroy");
	</script>
	{if $error_message}
		<h2>{ts domain="org.project60.sepa"}Error!{/ts} {$error_title}</h2>
		<p>{$error_message}</p>
	{else}
		<h2>{ts domain="org.project60.sepa"}Mandate successfully created.{/ts}<br/>
		{ts domain="org.project60.sepa"}Reference is{/ts}: <font face="Courier New, monospace">{$reference}</font></h2>
	{/if}
	<br/><br/>
	<a href="{$back_url}" class="view button" title="{ts domain="org.project60.sepa"}back to contact{/ts}">
		<span><div class="icon preview-icon"></div>{ts domain="org.project60.sepa"}view contact{/ts}</span>
	</a>
	<a href="{$mandate_url}" class="view button" title="{ts domain="org.project60.sepa"}view mandate{/ts}">
		<span><div class="icon preview-icon"></div>{ts domain="org.project60.sepa"}view mandate{/ts}</span>
	</a>
{/if}


<script type="text/javascript">
var creditor_types = {$creditor_types};
{if $creditor2cycledays}
var creditor2cycledays = {$creditor2cycledays};
{else}
var creditor2cycledays = [];
{/if}
{literal}
// logic for the bank account selector
cj("#account").change(change_bank_account);
change_bank_account();
function change_bank_account() {
	var values = cj("#account").val().split("/");
	cj("[name='iban']").val(values[0]);
	cj("[name='bic']").val(values[1]);
	if (typeof sepa_lookup_bic != 'undefined') sepa_lookup_bic();
}

// cycle days depend on the creditor settings
function sepa_update_cycledays() {
	var default_element_cycle_day = cj('#default_element_cycle_day');
	if (default_element_cycle_day.length==0) return;

	var creditor_id = cj("[name='creditor_id']").val();
	var old_value = default_element_cycle_day.val();

	cj('#default_element_cycle_day').empty();
	for (var value in creditor2cycledays[creditor_id]){
		var label = creditor2cycledays[creditor_id][value];
		var is_selected = (value == old_value)?'selected':'';
		cj('#default_element_cycle_day').append('<option value="' + value + '" ' + is_selected + '>' + label + '.</option>');
	}
}
sepa_update_cycledays();
{/literal}

// Validation handling
{foreach from=$validation_errors item=message key=field}
cj("[name='{$field}']").parent().parent().css("background-color", "#FFBBBB");
cj("[name='{$field}']").parent().parent().attr("title", "{$message}");
{/foreach}

// Date picker
{literal}
var dateOptions = {
    dateFormat: 'yy-mm-dd',
    changeMonth: true,
    changeYear: true,
    minDate: 'now',
    yearRange: '0:+5'
  };
cj('#date').addClass('dateplugin');
cj('#date').datepicker(dateOptions);
cj('#start_date').addClass('dateplugin');
cj('#start_date').datepicker(dateOptions);
cj('#end_date').addClass('dateplugin');
cj('#end_date').datepicker(dateOptions);
cj('#replace_date').addClass('dateplugin');
cj('#replace_date').datepicker(dateOptions);

// normalise IBAN upon change
function sepa_iban_changed() {
	// get type first
	var creditor_id = cj("[name='creditor_id']").val();
	var creditor_type = creditor_types[creditor_id];

	// normalise SEPA IBAN
	var sanitized_iban = cj("[name='iban']").val();
	if (creditor_type == 'SEPA') {
		var reSpaceAndMinus = new RegExp('[\\s-]', 'g');
		sanitized_iban = sanitized_iban.replace(reSpaceAndMinus, "");
		sanitized_iban = sanitized_iban.toUpperCase();
		cj("[name='iban']").val(sanitized_iban);

		{/literal}{if $bic_extension_installed}
		sepa_lookup_bic();
		{/if}{literal}

	} else {
		// non-SEPA: simply remove whitespacse
		var whiteSpaces = new RegExp('[\\s]', 'g');
		sanitized_iban = sanitized_iban.replace(whiteSpaces, "");
		cj("[name='iban']").val(sanitized_iban);
	}
}
cj("[name='iban']").change(sepa_iban_changed);
{/literal}
</script>



{if $bic_extension_installed}
<script type="text/javascript">
cj("[name='bic']").change(sepa_clear_bank);
//cj("#bic_lookup_btn").show();
{literal}

function sepa_clear_bank() {
  cj("#bank_name").text('');
  cj("#bic_busy").hide();
}


function sepa_lookup_bic() {
	var iban_partial = cj("[name='iban']").val();
	cj("#bic_busy").show();
	cj("#bank_name").text('');
  CRM.api('Bic', 'findbyiban', {'q': 'civicrm/ajax/rest', 'iban': iban_partial},
    {success: function(data) {
    	if ('bic' in data) {
        // use the following to urldecode the link url
        cj("[name='bic']").val(data['bic']);
        cj("#bank_name").text(data['title']);
        cj("#bic_busy").hide();
      } else {
      	sepa_clear_bank();
      }
    }});
}

// call it once
sepa_lookup_bic();
{/literal}
</script>
{/if}