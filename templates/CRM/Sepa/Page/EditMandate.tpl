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

{assign var='mandate_id' value=$sepa.id}

{if not $deleted_mandate}

<h3>{if $contribution.cycle_day}{ts domain="org.project60.sepa"}SEPA Recurring Mandate{/ts}{else}{ts domain="org.project60.sepa"}SEPA Single Payment Mandate{/ts}{/if} [{$sepa.id}]</h3>
<div class="crm-container">
    <div class="crm-block crm-content-block crm-sdd-mandate">
        <table class="crm-info-panel">
            <tr><td class="label">{ts domain="org.project60.sepa"}Status{/ts}</td><td><b>{$sepa.status_text}</b> ({$sepa.status})</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Contact{/ts}</td><td><a href="{$contact1.link}"><div class="icon crm-icon {$contact1.contact_type}-icon"></div>{$contact1.display_name}</a></td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Reference{/ts}</td><td>{$sepa.reference}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}IBAN{/ts}</td><td>{$sepa.iban}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}BIC{/ts}</td><td>{$sepa.bic}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Creditor{/ts}</td><td>{$sepa.creditor_name} [{$sepa.creditor_id}]</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Source{/ts}</td><td>{$sepa.source}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Status{/ts}</td><td>{$sepa.status}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Creation date{/ts}</td><td>{$sepa.creation_date}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Signature date{/ts}</td><td>{$sepa.date}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Validation date{/ts}</td><td>{$sepa.validation_date}</td></tr>
        </table>
    </div>
</div>
<h3>{ts domain="org.project60.sepa"}Payment Details{/ts}: <a href="{$contribution.link}">{if $contribution.cycle_day}{ts domain="org.project60.sepa"}Recurring Contribution{/ts}{else}{ts domain="org.project60.sepa"}Contribution{/ts}{/if} [{$contribution.id}]</a></h3>
<div class="crm-container">
    <div class="crm-block crm-content-block crm-sdd-mandate">
        <table class="crm-info-panel">
            <tr><td class="label">{ts domain="org.project60.sepa"}Contact{/ts}</td><td><a href="{$contact2.link}"><div class="icon crm-icon {$contact2.contact_type}-icon"></div>{$contact2.display_name}</a></td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Financial Type{/ts}</td><td>{$contribution.financial_type}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Campaign{/ts}</td><td>{$contribution.campaign}</td></tr>
            <tr><td class="label">{ts domain="org.project60.sepa"}Amount{/ts}</td><td>{$contribution.amount|crmMoney:$contribution.currency}</td></tr>
            {if $contribution.cycle_day}
            	{* this is a recurring contribution *}
	            <tr><td class="label">{ts domain="org.project60.sepa"}Create Date{/ts}</td><td>{$contribution.create_date}</td></tr>
	            <tr><td class="label">{ts domain="org.project60.sepa"}Last Modified{/ts}</td><td>{$contribution.modified_date}</td></tr>
	            <tr><td class="label">{ts domain="org.project60.sepa"}Frequency{/ts}</td><td>{$contribution.cycle}</td></tr>
	            <tr><td class="label">{ts domain="org.project60.sepa"}Collection Day{/ts}</td><td>{$contribution.cycle_day}

              </td></tr>
                <tr><td class="label">{ts domain="org.project60.sepa"}Start Date{/ts}</td><td>{$contribution.start_date}</td></tr>
                <tr><td class="label">{ts domain="org.project60.sepa"}End Date{/ts}</td><td>{$contribution.end_date}</td></tr>
                <tr><td class="label">{ts domain="org.project60.sepa"}Next Collection{/ts}</td><td>{$contribution.next_sched_contribution_date}</td></tr>
           	{else}
            	{* this is a simple contribution *}
                <tr><td class="label">{ts domain="org.project60.sepa"}Date{/ts}</td><td>{$contribution.receive_date}</td></tr>
	       	{/if}

            {* add note field *}
            {foreach from=$mandate_cancel_reasons item=note}
            <tr><td class="label">{ts domain="org.project60.sepa"}Cancel Reason{/ts}</td><td>{$note}</td></tr>
            {/foreach}
        </table>
    </div>
</div>

<h2>{ts domain="org.project60.sepa"}Options{/ts}</h2>
<form id="sepa_action_form" action="{crmURL p="civicrm/sepa/xmandate" q="mid=$mandate_id"}" method="post">
	<input type="hidden" name="action" id="mandate_action_value" value=""/>
	<div class="crm-container">
        <table class="crm-info-panel">
        	{if $sepa.status eq 'INIT'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_activate();">{ts domain="org.project60.sepa"}Activate{/ts}</td>
            	<td>{ts domain="org.project60.sepa"}Activate the mandate when the written permission was received.{/ts}</td>
            </tr>{/if}

        	{if $can_delete}{if $sepa.status eq 'OOFF' or $sepa.status eq 'FRST'}<tr>
            	<td class="label"><a class="button" onclick="mandate_action_delete();">{ts domain="org.project60.sepa"}Delete{/ts}</td>
            	<td>{ts domain="org.project60.sepa"}Completely delete this mandate along with the contribution. This is only possible because it has not yet been submitted to the bank.{/ts}</td>
            </tr>{/if}{/if}

        	{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT' or $sepa.status eq 'OOFF'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_cancel();">{ts domain="org.project60.sepa"}Cancel{/ts}</td>
            	<td>{ts domain="org.project60.sepa"}Cancel this mandate immediately for the following reason:{/ts}&nbsp;<input type="text" name="cancel_reason" size="32" class="crm-form-text" /></td>
            </tr>{/if}

            {if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
            	<td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_end();">{ts domain="org.project60.sepa"}Set End Date{/ts}</td>
            	<td>
                    {ts domain="org.project60.sepa"}Terminate this mandate:{/ts}&nbsp;<input type="text" name="end_date" id="end_date" size="12" class="crm-form-text" value="{$contribution.default_end_date}" />
                    <br/>
                    {ts domain="org.project60.sepa"}Terminate for the following reason:{/ts}&nbsp;
                    <input type="text" name="end_reason" size="32" class="crm-form-text" />
                </td>
            </tr>{/if}{/if}

            {if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
                <td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_replace();">{ts domain="org.project60.sepa"}Replace{/ts}</td>
                <td>
                    {ts domain="org.project60.sepa"}Replace the mandate beginning:{/ts}&nbsp;<input type="text" name="replace_date" id="replace_date" size="12" class="crm-form-text" value="{$contribution.default_end_date}" />
                    <br/>
                    {ts domain="org.project60.sepa"}Replace for the following reason:{/ts}&nbsp;
                    <input type="text" name="replace_reason" size="32" class="crm-form-text" />
                </td>
            </tr>{/if}{/if}

            {if $can_modify}{if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
              <td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_change_cycle_day();">{ts domain="org.project60.sepa"}Change Cycle Day{/ts}</td>
              <td>
                  {ts domain="org.project60.sepa"}New cycle day:{/ts}<a id='template_help' onclick='CRM.help("{ts domain="org.project60.sepa"}Cyle Day{/ts}", {literal}{"id":"id-change-cycleday-help","file":"CRM\/Sepa\/Page\/EditMandate"}{/literal}); return false;' href="#" title="{ts domain="org.project60.sepa"}Help{/ts}" class="helpicon">&nbsp;</a>&nbsp;
                  <select name="new_cycle_day" id="new_cycle_day" class="crm-form-select">
                    {foreach from=$cycle_days item=cycle_day_label key=cycle_day_value}
                      <option value="{$cycle_day_value}" {if ($cycle_day_value == $contribution.cycle_day_raw)}selected="selected"{/if}>{$cycle_day_label}</option>
                    {/foreach}
                  </select>
              </td>
            </tr>{/if}{/if}{/if}

            {if $can_modify}{if $contribution.cycle_day}{if $sepa.status eq 'FRST' or $sepa.status eq 'RCUR' or $sepa.status eq 'INIT'}<tr>
                <td class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_adjust_amount();">{ts domain="org.project60.sepa"}Adjust Amount{/ts}</td>
                <td>
                    {ts domain="org.project60.sepa"}Change amount to:{/ts}&nbsp;<input type="text" name="adjust_amount" id="adjust_amount" size="12" class="crm-form-text" value="{$contribution.amount}" />&nbsp;EUR
                </td>
            </tr>{/if}{/if}{/if}

            <tr>
            	<td class="label" style="vertical-align: middle;"><a href="{crmURL p="civicrm/sepa/createmandate" q="reset=1&clone=$mandate_id"}" class="button">{ts domain="org.project60.sepa"}Clone{/ts}</td>
            	<td>{ts domain="org.project60.sepa"}Create a new mandate similar to this.{/ts}</td>
            </tr>

            <tr>
                <td id='mandate_pdf_action' class="label" style="vertical-align: middle;"><a class="button" onclick="mandate_action_create_pdf();">{ts domain="org.project60.sepa"}PDF Prenotification{/ts}</td>
                <td>
                    {ts domain="org.project60.sepa"}Will generate a Prenotification PDF with this mandate's data.{/ts}
                    <br/>
                    {if !empty($sepa_templates)}
                    {ts domain="org.project60.sepa"}Select the template to be used:{/ts}<a id='template_help' onclick='CRM.help("{ts domain="org.project60.sepa"}Template{/ts}", {literal}{"id":"id-template-help","file":"CRM\/Sepa\/Page\/EditMandate"}{/literal}); return false;' href="#" title="{ts domain="org.project60.sepa"}Help{/ts}" class="helpicon">&nbsp;</a>
                    &nbsp;
                    <select id="sepa_tpl_select" style="">
                        {foreach from=$sepa_templates item=item}
                        <option value="{$item[0]}">{$item[1]}</option>
                        {/foreach}
                    </select>
                    {else}
                    <strong>{ts domain="org.project60.sepa"}No suitable templates found! Reinstall the SEPA extensions and check your message templates.{/ts}</strong>
                    {/if}
                </td>
            </tr>
        </table>
	</div>
</form>

<script type="text/javascript">
cancel_reason_message = "{ts domain="org.project60.sepa"}You need to specify a cancel reason!{/ts}";
end_date_message = "{ts domain="org.project60.sepa"}You need to specify a date!{/ts}";
replace_url = "{crmURL p="civicrm/sepa/createmandate" q="reset=1&replace=$mandate_id"}";

{literal}
function mandate_action_create_pdf() {
  var selected_template = cj("#sepa_tpl_select").val();
  window.open({/literal}'{crmURL p='civicrm/sepa/pdf' h=0 q="reset=1&pdfaction=print&id=$mandate_id"}'{literal} + '&tpl=' + selected_template, '_blank');
}

function mandate_action_delete() {
	cj("#mandate_action_value").val('delete');
	cj("#sepa_action_form").submit();
}

function mandate_action_adjust_amount() {
    cj("#mandate_action_value").val('adjustamount');
    cj("#sepa_action_form").submit();
}

function mandate_action_change_cycle_day() {
  cj("#mandate_action_value").val('changecyleday');
  cj("#sepa_action_form").submit();
}

function mandate_action_cancel() {
	cj("#mandate_action_value").val('cancel');
	if (cj("[name='cancel_reason']").val()) {
		cj("#sepa_action_form").submit();
	} else {
		alert(cancel_reason_message);
	}
}

function mandate_action_end() {
	cj("#mandate_action_value").val('end');
    if (!cj("[name='end_reason']").val()) {
        alert(cancel_reason_message);
        return;
    }
    if (!cj("[name='end_date']").val()) {
        alert(end_date_message);
        return;
    }
	cj("#sepa_action_form").submit();
}

function mandate_action_replace() {
    cj("#mandate_action_value").val('replace');
    if (!cj("[name='replace_reason']").val()) {
        alert(cancel_reason_message);
        return;
    }
    if (!cj("[name='replace_date']").val()) {
        alert(end_date_message);
        return;
    }
    replace_url = cj("<div/>").html(replace_url).text();
    replace_url += '&replace_date=' + encodeURIComponent(cj("[name='replace_date']").val());
    replace_url += '&replace_reason=' + encodeURIComponent(cj("[name='replace_reason']").val());
    location.href = replace_url;
}

// Date picker
var dateOptions = {
    dateFormat: 'yy-mm-dd',
    changeMonth: true,
    changeYear: true,
    minDate: 'now',
    yearRange: '0:+50'
  };
cj('#replace_date').addClass('dateplugin');
cj('#replace_date').datepicker(dateOptions);
cj('#end_date').addClass('dateplugin');
cj('#end_date').datepicker(dateOptions);
</script>
{/literal}


{else}
<p>{ts domain="org.project60.sepa"}Mandate {$deleted_mandate} succesfully deleted.{/ts}
{/if}
