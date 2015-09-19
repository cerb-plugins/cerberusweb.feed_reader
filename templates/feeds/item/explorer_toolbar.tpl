{$model = DAO_FeedItem::get({$item->params.id})}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmExploreFeedItem">
	<input type="hidden" name="c" value="feeds">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	
	{if !$model->is_closed}
		<button type="button" class="status close"><span class="glyphicons glyphicons-circle-ok"></span> <label>{'common.close'|devblocks_translate|capitalize}</label></button>
	{else}
		<button type="button" class="status reopen"><span class="glyphicons glyphicons-circle-arrow-top"></span> <label>{'common.reopen'|devblocks_translate|capitalize}</label></button>
	{/if}
	
	<button type="button" class="edit"><span class="glyphicons glyphicons-edit"></span> {'common.edit'|devblocks_translate|capitalize}</button>
	
	<button type="button" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_TASK}&context_id=0&context={'cerberusweb.contexts.feed.item'}&context_id={$model->id}',null,false,'500');"><span class="glyphicons glyphicons-cogwheel"></span> {'tasks.add'|devblocks_translate|capitalize}</button>
	
	{* [TODO] HACK!! *}
	{if DevblocksPlatform::isPluginEnabled('cerberusweb.feedback')}
	<button type="button" onclick="genericAjaxPopup('peek','c=feedback&a=showEntry&quote='+encodeURIComponent(Devblocks.getSelectedText())+'&url={$model->url|escape:'url'}',null,false,'500');"><img src="{devblocks_url}c=resource&p=cerberusweb.feedback&f=images/question_and_answer.png{/devblocks_url}" align="top"> {'feedback.button.capture'|devblocks_translate|capitalize}</button>
	{/if}
</form>

<script type="text/javascript">
	$('#frmExploreFeedItem BUTTON.status').click(function() {
		var $btn = $(this);
		if($btn.hasClass('close')) {
			$btn.find('label').text('{'common.reopen'|devblocks_translate|capitalize|escape:'javascript'}');
			$btn.removeClass('close').addClass('reopen').find('span').removeClass('glyphicons-circle-ok').addClass('glyphicons-circle-arrow-top');
			genericAjaxGet('','c=feeds&a=exploreItemStatus&id={$model->id}&is_closed=1');
		} else {
			$btn.find('label').text('{'common.close'|devblocks_translate|capitalize|escape:'javascript'}');
			$btn.removeClass('reopen').addClass('close').find('span').removeClass('glyphicons-circle-arrow-top').addClass('glyphicons-circle-ok');
			genericAjaxGet('','c=feeds&a=exploreItemStatus&id={$model->id}&is_closed=0');
		}
	});
	
	$('#frmExploreFeedItem BUTTON.edit').click(function() {
		var $popup = genericAjaxPopup('peek','c=internal&a=showPeekPopup&context=cerberusweb.contexts.feed.item&context_id={$model->id}',null,true,'550');
		$popup.one('feeditem_save', function(event) {
			event.stopPropagation();
			document.location.reload();
		});
	});
</script>
