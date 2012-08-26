<?php
/***********************************************************************
| Cerb(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2012, WebGroup Media LLC
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

		// Custom fields

		$custom_fields = DAO_CustomField::getAll();
		$tpl->assign('custom_fields', $custom_fields);

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

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.feed.item', $item->id)) or array();

		foreach($custom_fields as $cf_id => $cfield) {
			if(!isset($values[$cf_id]))
				continue;

			$properties['cf_' . $cf_id] = array(
					'label' => $cfield->name,
					'type' => $cfield->type,
					'value' => $values[$cf_id],
			);
		}

		$tpl->assign('properties', $properties);

		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.feeditem');
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, 'cerberusweb.contexts.feed.item');
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/item/profile.tpl');
	}
};