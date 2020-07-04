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

<div id="sdd-create-mandate">

  {* hidden fields *}
  {$form.cid.html}

  {if $create_mode eq 'replace'}
  <div style="background-color: paleturquoise; padding: 1em; border-radius: 1em;">
    {$form.replace.html}

    <div style="text-align: left; font-size: large;">
      <span><strong>{ts 1=$replace_mandate_reference domain='org.project60.sepa'}You're replacing mandate %1{/ts}</strong></span>
    </div>
    <br/>

    <div class="crm-section">
      <div class="label">{$form.rpl_end_date.label}</div>
      <div class="content">{include file="CRM/common/jcalendar.tpl" elementName='rpl_end_date'}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.rpl_cancel_reason.label}</div>
      <div class="content">{$form.rpl_cancel_reason.html}</div>
      <div class="clear"></div>
    </div>
  </div>

  <hr/>
  {/if}

  <div class="crm-section">
    <div class="label">{$form.creditor_id.label}</div>
    <div class="content">{$form.creditor_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.financial_type_id.label}</div>
    <div class="content">{$form.financial_type_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.campaign_id.label}</div>
    <div class="content">{$form.campaign_id.html}</div>
    <div class="clear"></div>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.bank_account_preset.label}</div>
    <div class="content">{$form.bank_account_preset.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.account_holder.label}</div>
    <div class="content">{$form.account_holder.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.iban.label}</div>
    <div class="content">{$form.iban.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>

  <hr/>

  <div style="text-align: center; font-size: large; padding: 1em;">
    <span id="sdd_summary_text"></span>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.amount.label}</div>
    <div class="content">{$form.amount.html}&nbsp;{$form.currency.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
      <div class="label">{$form.interval.label}</div>
      <div class="content">{$form.interval.html}</div>
      <div class="clear"></div>
  </div>


  <div id="sdd-ooff-data">
    <div class="crm-section">
      <div class="label">{$form.ooff_date.label}</div>
      <div class="content">
          {include file="CRM/common/jcalendar.tpl" elementName='ooff_date'}
          <a id="sdd_ooff_earliest" class="sdd-earliest"></a>
      </div>
      <div class="clear"></div>
    </div>
  </div>


  <div id="sdd-rcur-data">
    <div class="crm-section">
      <div class="label">{$form.rcur_start_date.label}</div>
      <div class="content">
          {include file="CRM/common/jcalendar.tpl" elementName='rcur_start_date'}
          <a id="sdd_rcur_earliest" class="sdd-earliest"></a>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.cycle_day.label}</div>
      <div class="content">{$form.cycle_day.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.rcur_end_date.label}</div>
      <div class="content">{include file="CRM/common/jcalendar.tpl" elementName='rcur_end_date'}</div>
      <div class="clear"></div>
    </div>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.reference.label}</div>
    <div class="content">{$form.reference.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.source.label}</div>
    <div class="content">{$form.source.html}</div>
    <div class="clear"></div>
  </div>

    <div class="crm-section" style="display: none;">
        <div class="content">{include file="CRM/common/jcalendar.tpl" elementName='sdd_converter'}</div>
    </div>
</div>


<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
