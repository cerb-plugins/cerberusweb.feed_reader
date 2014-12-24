<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

class PageSection_ProfilesFeedItem extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$request = DevblocksPlatform::getHttpRequest();
		$translate = DevblocksPlatform::getTranslationService();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		$stack = $request->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // feed_item
		@$id = intval(array_shift($stack));
		
		if(null != ($item = DAO_FeedItem::get($id)))
			$tpl->assign('item', $item);
		
		// Remember the last tab/URL

		@$selected_tab = array_shift($stack);
		
		$point = 'profiles.feed.item.tab';
		$tpl->assign('point', $point);
		
		if(null == $selected_tab) {
			$selected_tab = $visit->get($point, '');
		}
		$tpl->assign('selected_tab', $selected_tab);

		// Properties

		$properties = array();

		if(!empty($item->feed_id)) {
			$properties['feed_id'] = array(
				'label' => ucfirst($translate->_('dao.feed_item.feed_id')),
				'type' => Model_CustomField::TYPE_LINK,
				'params' => array('context' => CerberusContexts::CONTEXT_FEED),
				'value' => $item->feed_id,
			);
		}

		$properties['is_closed'] = array(
			'label' => ucfirst($translate->_('dao.feed_item.is_closed')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $item->is_closed,
		);

		$properties['created_date'] = array(
			'label' => ucfirst($translate->_('common.created')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $item->created_date,
		);

		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_FEED_ITEM, $item->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields(CerberusContexts::CONTEXT_FEED_ITEM, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets(CerberusContexts::CONTEXT_FEED_ITEM, $item->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			CerberusContexts::CONTEXT_FEED_ITEM => array(
				$item->id => 
					DAO_ContextLink::getContextLinkCounts(
						CerberusContexts::CONTEXT_FEED_ITEM,
						$item->id,
						array(CerberusContexts::CONTEXT_WORKER, CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		if(isset($item->feed_id)) {
			$properties_links[CerberusContexts::CONTEXT_FEED] = array(
				$item->feed_id => 
					DAO_ContextLink::getContextLinkCounts(
						CerberusContexts::CONTEXT_FEED,
						$item->feed_id,
						array(CerberusContexts::CONTEXT_WORKER, CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			);
		}
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);

		// Macros
		
		$macros = DAO_TriggerEvent::getReadableByActor(
			$active_worker,
			'event.macro.feeditem'
		);
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_FEED_ITEM);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/item/profile.tpl');
	}
};