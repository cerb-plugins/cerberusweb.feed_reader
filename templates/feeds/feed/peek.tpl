{$peek_context = CerberusContexts::CONTEXT_FEED}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmFeedPopup">
<input type="hidden" name="c" value="feeds">
<input type="hidden" name="a" value="saveFeedPopup">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>

	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.name'|devblocks_translate|capitalize}:</b></td>
			<td width="99%" valign="top">
				<input type="text" name="name" value="{$model->name}" size="24" style="width:100%;">
			</td>
		</tr>
		<tr>
			<td width="1%" valign="top" nowrap="nowrap"><b>{'common.url'|devblocks_translate|upper}:</b></td>
			<td width="99%" valign="top">
				<input type="text" name="url" value="{$model->url}" size="24" style="width:100%;">
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

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=$peek_context context_id=$model->id}

{if $active_worker->hasPriv("contexts.{$peek_context}.comment")}
<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="2" cols="45" style="width:98%;" placeholder="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
</fieldset>
{/if}

{if (!$model->id && $active_worker->hasPriv("contexts.{$peek_context}.create")) || ($model->id && $active_worker->hasPriv("contexts.{$peek_context}.update"))}<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmFeedPopup','{$view_id}',false,'feed_save');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>{/if}
{if $model->id && $active_worker->hasPriv("contexts.{$peek_context}.delete")}<button type="button" onclick="if(confirm('Permanently delete this feed item?')) { this.form.do_delete.value='1';genericAjaxPopupPostCloseReloadView(null,'frmFeedPopup','{$view_id}'); } "><span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=feed&id={$model->id}-{$model->name|devblocks_permalink}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		var $textarea = $(this).find('textarea[name=comment]');
		
		$(this).dialog('option','title',"{'dao.feed_item.feed_id'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		
		$(this).find('button.chooser_watcher').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','add_watcher_ids', { autocomplete:true });
		});
		
		// @mentions
		
		var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

		$textarea.atwho({
			at: '@',
			{literal}displayTpl: '<li>${name} <small style="margin-left:10px;">${title}</small> <small style="margin-left:10px;">@${at_mention}</small></li>',{/literal}
			{literal}insertTpl: '@${at_mention}',{/literal}
			data: atwho_workers,
			searchKey: '_index',
			limit: 10
		});
	});
</script>
