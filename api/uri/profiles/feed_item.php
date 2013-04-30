<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2013, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
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
			if(null != ($feed = DAO_Feed::get($item->feed_id))) {
				$properties['feed'] = array(
						'label' => ucfirst($translate->_('dao.feed_item.feed_id')),
						'type' => null,
						'feed' => $feed,
				);
			}
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
		
		// Custom Field Groups

		$properties_custom_field_groups = Page_Profiles::getProfilePropertiesCustomFieldSets(CerberusContexts::CONTEXT_FEED_ITEM, $item->id, $values);
		$tpl->assign('properties_custom_field_groups', $properties_custom_field_groups);
		
		// Properties
		
		$tpl->assign('properties', $properties);

		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.feeditem');
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_FEED_ITEM);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/item/profile.tpl');
	}
};