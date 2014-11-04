<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmFeedItemPopup">
<input type="hidden" name="c" value="feeds">
<input type="hidden" name="a" value="saveFeedItemPopup">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>

	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.title'|devblocks_translate|capitalize}:</b></td>
			<td width="99%" valign="top">
				{$model->title}
			</td>
		</tr>
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.url'|devblocks_translate|upper}:</b></td>
			<td width="99%" valign="top">
				<a href="{$model->url}" target="_blank">{$model->url|truncate:64}</a>
			</td>
		</tr>
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.status'|devblocks_translate|capitalize}:</b></td>
			<td width="99%" valign="top">
				<label><input type="radio" name="is_closed" value="0" {if empty($model) || !$model->is_closed}checked="checked"{/if}> {'status.open'|devblocks_translate|capitalize}</label>
				<label><input type="radio" name="is_closed" value="1" {if !empty($model) && $model->is_closed}checked="checked"{/if}> {'status.closed'|devblocks_translate|capitalize}</label>
			</td>
		</tr>
		

		{* Watchers *}
		<tr>
			<td width="0%" nowrap="nowrap" valign="middle" align="right">{'common.watchers'|devblocks_translate|capitalize}: </td>
			<td width="100%">
				{if !empty($model->id)}
					{$object_watchers = DAO_ContextLink::getContextLinks(CerberusContexts::CONTEXT_FEED_ITEM, array($model->id), CerberusContexts::CONTEXT_WORKER)}
					{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=CerberusContexts::CONTEXT_FEED_ITEM context_id=$model->id full=true}
				{/if}
			</td>
		</tr>
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_FEED_ITEM context_id=$model->id}

{* Comments *}
{include file="devblocks:cerberusweb.core::internal/peek/peek_comments_pager.tpl" comments=$comments}

<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<div class="cerb-form-hint">{'comment.notify.at_mention'|devblocks_translate}</div>
	<textarea name="comment" rows="5" cols="45" style="width:98%;"></textarea>
</fieldset>

<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmFeedItemPopup','{$view_id}',false,'feeditem_save');"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
{if $model->id && ($active_worker->is_superuser || $active_worker->id == $model->worker_id)}<button type="button" onclick="if(confirm('Permanently delete this feed item?')) { this.form.do_delete.value='1';genericAjaxPopupPostCloseReloadView(null,'frmFeedItemPopup','{$view_id}'); } "><span class="cerb-sprite2 sprite-minus-circle"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=feed_item&id={$model->id}-{$model->title|devblocks_permalink}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		var $textarea = $(this).find('textarea[name=comment]');
		
		$(this).dialog('option','title',"{'feeds.item'|devblocks_translate|escape:'javascript' nofilter}");

		// Form hints
		
		$textarea
			.focusin(function() {
				$(this).siblings('div.cerb-form-hint').fadeIn();
			})
			.focusout(function() {
				$(this).siblings('div.cerb-form-hint').fadeOut();
			})
			;
		
		// @mentions
		
		var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

		$textarea.atwho({
			at: '@',
			{literal}tpl: '<li data-value="@${at_mention}">${name} <small style="margin-left:10px;">${title}</small></li>',{/literal}
			data: atwho_workers,
			limit: 10
		});
	});
</script>
