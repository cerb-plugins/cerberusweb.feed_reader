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

class PageSection_ProfilesFeed extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$request = DevblocksPlatform::getHttpRequest();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		$stack = $request->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // calendar_event
		@$id = intval(array_shift($stack));
		
		if(null == ($feed = DAO_Feed::get($id)))
			return;
		
		$tpl->assign('feed', $feed);

		// Remember the last tab/URL

		@$selected_tab = array_shift($stack);
		
		$point = 'profiles.feed.tab';
		$tpl->assign('point', $point);
		
		$visit = CerberusApplication::getVisit();
		if(null == $selected_tab) {
			$selected_tab = $visit->get($point, '');
		}
		$tpl->assign('selected_tab', $selected_tab);
		
		// Properties
		
		$translate = DevblocksPlatform::getTranslationService();

		$properties = array();

		$properties['url'] = array(
			'label' => ucfirst($translate->_('common.url')),
			'type' => Model_CustomField::TYPE_URL,
			'value' => $feed->url,
		);
		
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_FEED, $feed->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields(CerberusContexts::CONTEXT_FEED, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Field Groups

		$properties_custom_field_groups = Page_Profiles::getProfilePropertiesCustomFieldSets(CerberusContexts::CONTEXT_FEED, $feed->id, $values);
		$tpl->assign('properties_custom_field_groups', $properties_custom_field_groups);
		
		// Properties
		
		$tpl->assign('properties', $properties);
		
		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.feed');
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_FEED);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/feed/profile.tpl');
	}
};