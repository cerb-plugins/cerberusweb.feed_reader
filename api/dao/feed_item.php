<?php
class DAO_FeedItem extends Cerb_ORMHelper {
	const ID = 'id';
	const FEED_ID = 'feed_id';
	const GUID = 'guid';
	const TITLE = 'title';
	const URL = 'url';
	const CREATED_DATE = 'created_date';
	const IS_CLOSED = 'is_closed';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO feed_item () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_FEED_ITEM, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'feed_item', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.feed_item.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_FEED_ITEM, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('feed_item', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_FeedItem[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, feed_id, guid, title, url, created_date, is_closed ".
			"FROM feed_item ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_FeedItem
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_FeedItem[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_FeedItem();
			$object->id = $row['id'];
			$object->feed_id = $row['feed_id'];
			$object->guid = $row['guid'];
			$object->title = $row['title'];
			$object->url = $row['url'];
			$object->created_date = $row['created_date'];
			$object->is_closed = $row['is_closed'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM feed_item WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_FEED_ITEM,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	static function maint() {
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.maint',
				array(
					'context' => CerberusContexts::CONTEXT_FEED_ITEM,
					'context_table' => 'feed_item',
					'context_key' => 'id',
				)
			)
		);
	}
	
	public static function random() {
		return self::_getRandom('feed_item');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_FeedItem::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"feed_item.id as %s, ".
			"feed_item.feed_id as %s, ".
			"feed_item.guid as %s, ".
			"feed_item.title as %s, ".
			"feed_item.url as %s, ".
			"feed_item.created_date as %s, ".
			"feed_item.is_closed as %s ",
				SearchFields_FeedItem::ID,
				SearchFields_FeedItem::FEED_ID,
				SearchFields_FeedItem::GUID,
				SearchFields_FeedItem::TITLE,
				SearchFields_FeedItem::URL,
				SearchFields_FeedItem::CREATED_DATE,
				SearchFields_FeedItem::IS_CLOSED
			);
			
		$join_sql = "FROM feed_item ".
		
		// [JAS]: Dynamic table joins
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.feed.item' AND context_link.to_context_id = feed_item.id) " : " ")
			;
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'feed_item.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_FeedItem', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'feed_item',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
	
		$from_context = CerberusContexts::CONTEXT_FEED_ITEM;
		$from_index = 'feed_item.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_FeedItem::FULLTEXT_COMMENT_CONTENT:
				$search = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID);
				$query = $search->getQueryFromParam($param);
				$ids = $search->query($query, array('context_crc32' => sprintf("%u", crc32($from_context))));
				
				if(is_array($ids)) {
					$from_ids = DAO_Comment::getContextIdsByContextAndIds($from_context, $ids);
					
					$args['where_sql'] .= sprintf('AND %s IN (%s) ',
						$from_index,
						implode(', ', (!empty($from_ids) ? $from_ids : array(-1)))
					);
					
				} elseif(is_string($ids)) {
					$db = DevblocksPlatform::getDatabaseService();
					$temp_table = sprintf("_tmp_%s", uniqid());
					
					$db->ExecuteSlave(sprintf("CREATE TEMPORARY TABLE %s (PRIMARY KEY (id)) SELECT DISTINCT context_id AS id FROM comment INNER JOIN %s ON (%s.id=comment.id)",
						$temp_table,
						$ids,
						$ids
					));
					
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=feed_item.id) ",
						$temp_table,
						$temp_table
					);
				}
				
				break;
			
			case SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
			
			case SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY feed_item.id ' : '').
			$sort_sql;
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_FeedItem::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT feed_item.id) " : "SELECT COUNT(feed_item.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_FeedItem implements IDevblocksSearchFields {
	const ID = 'fi_id';
	const FEED_ID = 'fi_feed_id';
	const GUID = 'fi_guid';
	const TITLE = 'fi_title';
	const URL = 'fi_url';
	const CREATED_DATE = 'fi_created_date';
	const IS_CLOSED = 'fi_is_closed';
	
	// Context links
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	// Comment Content
	const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';

	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'feed_item', 'id', $translate->_('common.id')),
			self::FEED_ID => new DevblocksSearchField(self::FEED_ID, 'feed_item', 'feed_id', $translate->_('dao.feed_item.feed_id')),
			self::GUID => new DevblocksSearchField(self::GUID, 'feed_item', 'guid', $translate->_('dao.feed_item.guid')),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'feed_item', 'title', $translate->_('common.title'), Model_CustomField::TYPE_SINGLE_LINE),
			self::URL => new DevblocksSearchField(self::URL, 'feed_item', 'url', $translate->_('common.url'), Model_CustomField::TYPE_SINGLE_LINE),
			self::CREATED_DATE => new DevblocksSearchField(self::CREATED_DATE, 'feed_item', 'created_date', $translate->_('common.created'), Model_CustomField::TYPE_DATE),
			self::IS_CLOSED => new DevblocksSearchField(self::IS_CLOSED, 'feed_item', 'is_closed', $translate->_('dao.feed_item.is_closed'), Model_CustomField::TYPE_CHECKBOX),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
				
			self::FULLTEXT_COMMENT_CONTENT => new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT'),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_COMMENT_CONTENT]->ft_schema = Search_CommentContent::ID;
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_FEED_ITEM,
		));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_FeedItem {
	public $id;
	public $feed_id;
	public $guid;
	public $title;
	public $url;
	public $created_date;
	public $is_closed;
};

class View_FeedItem extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'feed_items';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Headlines');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_FeedItem::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_FeedItem::URL,
			SearchFields_FeedItem::FEED_ID,
			SearchFields_FeedItem::CREATED_DATE,
		);
		$this->addColumnsHidden(array(
			SearchFields_FeedItem::GUID,
			SearchFields_FeedItem::ID,
			SearchFields_FeedItem::FULLTEXT_COMMENT_CONTENT,
			SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK,
			SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET,
			SearchFields_FeedItem::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_FeedItem::GUID,
			SearchFields_FeedItem::ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_FeedItem::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_FeedItem', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_FeedItem', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Feed
				case SearchFields_FeedItem::FEED_ID:
					$pass = true;
					break;
					
				// Booleans
				case SearchFields_FeedItem::IS_CLOSED:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK:
				case SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET:
				case SearchFields_FeedItem::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_FeedItem::FEED_ID:
				$feeds = DAO_Feed::getWhere(); // [TODO] Cache!!
				$label_map = array();
				foreach($feeds as $feed_id => $feed) { /* @var $feed Model_Feed */
					$label_map[$feed_id] = $feed->name;
				}
				$counts = $this->_getSubtotalCountForStringColumn('DAO_FeedItem', $column, $label_map, 'in', 'options[]');
				break;

			case SearchFields_FeedItem::IS_CLOSED:
				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_FeedItem', $column);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_FeedItem', CerberusContexts::CONTEXT_FEED_ITEM, $column);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_FeedItem', CerberusContexts::CONTEXT_FEED_ITEM, $column);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_FeedItem', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_FeedItem', $column, 'feed_item.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_FeedItem::TITLE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'comments' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_FeedItem::FULLTEXT_COMMENT_CONTENT),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_FeedItem::CREATED_DATE),
				),
			'feed' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_FeedItem::FEED_ID),
					'examples' => array(
						'"Feed Name"',
					),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_FeedItem::ID),
				),
			'isClosed' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_FeedItem::IS_CLOSED),
				),
			'title' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_FeedItem::TITLE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'url' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_FeedItem::URL, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_FeedItem::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_FEED_ITEM, $fields, null);
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_FEED, $fields, 'feed');
		
		// Engine/schema examples: Comments
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples))
			$fields['comments']['examples'] = $ft_examples;
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				case 'feed':
					$field_keys = array(
						'feed' => SearchFields_FeedItem::FEED_ID,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$patterns = DevblocksPlatform::parseCsvString($v);
					$feeds = DAO_Feed::getWhere();
					$values = array();
					
					if(is_array($patterns))
					foreach($patterns as $pattern) {
						foreach($feeds as $feed_id => $feed) {
							if(false !== stripos($feed->name, $pattern))
								$values[$feed_id] = true;
						}
					}
					
					$param = new DevblocksSearchCriteria(
						$field_key,
						$oper,
						array_keys($values)
					);
					$params[$field_key] = $param;					
					break;
			}
		}
		
		$this->renderPage = 0;
		$this->addParams($params, true);
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$tpl->assign('workers', DAO_Worker::getAll());
		
		// [TODO] Cache getAll()
		$feeds = DAO_Feed::getWhere();
		$tpl->assign('feeds', $feeds);
		
		// Custom fields
		//$custom_fields = DAO_CustomField::getByContext(CerberusContexts::XXX);
		//$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.feed_reader::feeds/item/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_FeedItem::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}
	
	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		switch($field) {
			case SearchFields_FeedItem::GUID:
			case SearchFields_FeedItem::TITLE:
			case SearchFields_FeedItem::URL:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_FeedItem::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_FeedItem::IS_CLOSED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_FeedItem::CREATED_DATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_FEED_ITEM);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			case SearchFields_FeedItem::FEED_ID:
				$options = array();
				$feeds = DAO_Feed::getWhere();
				
				if(is_array($feeds))
				foreach($feeds as $feed_id => $feed) { /* @var $feed Model_FeedItem */
					$options[$feed_id] = $feed->name;
				}
				
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_FeedItem::FULLTEXT_COMMENT_CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_FeedItem::IS_CLOSED:
				$this->_renderCriteriaParamBoolean($param);
				break;
				
			case SearchFields_FeedItem::FEED_ID:
				$feeds = DAO_Feed::getWhere();
				$strings = array();

				if(empty($values)) {
					echo DevblocksPlatform::strEscapeHtml("(blank)");
					break;
				}
				
				foreach($values as $val) {
					if(empty($val))
						$strings[] = "";
					elseif(!isset($feeds[$val]))
						continue;
					else
						$strings[] = DevblocksPlatform::strEscapeHtml($feeds[$val]->name);
				}
				echo implode(", ", $strings);
				break;
					
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_FeedItem::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_FeedItem::GUID:
			case SearchFields_FeedItem::TITLE:
			case SearchFields_FeedItem::URL:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_FeedItem::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_FeedItem::CREATED_DATE:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_FeedItem::IS_CLOSED:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_FeedItem::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			case SearchFields_FeedItem::FEED_ID:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$options);
				break;
				
			case SearchFields_FeedItem::FULLTEXT_COMMENT_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
		
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				case 'is_closed':
					$change_fields[DAO_FeedItem::IS_CLOSED] = !empty($v) ? 1 : 0;
					break;
					
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_FeedItem::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_FeedItem::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			DAO_FeedItem::update($batch_ids, $change_fields);

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_FEED_ITEM, $custom_fields, $batch_ids);
			
			// Scheduled behavior
			if(isset($do['behavior']) && is_array($do['behavior'])) {
				$behavior_id = $do['behavior']['id'];
				@$behavior_when = strtotime($do['behavior']['when']) or time();
				@$behavior_params = isset($do['behavior']['params']) ? $do['behavior']['params'] : array();
				
				if(!empty($batch_ids) && !empty($behavior_id))
				foreach($batch_ids as $batch_id) {
					DAO_ContextScheduledBehavior::create(array(
						DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
						DAO_ContextScheduledBehavior::CONTEXT => CerberusContexts::CONTEXT_FEED_ITEM,
						DAO_ContextScheduledBehavior::CONTEXT_ID => $batch_id,
						DAO_ContextScheduledBehavior::RUN_DATE => $behavior_when,
						DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
					));
				}
			}
			
			// Watchers
			if(isset($do['watchers']) && is_array($do['watchers'])) {
				$watcher_params = $do['watchers'];
				foreach($batch_ids as $batch_id) {
					if(isset($watcher_params['add']) && is_array($watcher_params['add']))
						CerberusContexts::addWatchers(CerberusContexts::CONTEXT_FEED_ITEM, $batch_id, $watcher_params['add']);
					if(isset($watcher_params['remove']) && is_array($watcher_params['remove']))
						CerberusContexts::removeWatchers(CerberusContexts::CONTEXT_FEED_ITEM, $batch_id, $watcher_params['remove']);
				}
			}
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_FeedItem extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	function getRandom() {
		return DAO_FeedItem::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=feed_item&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$item = DAO_FeedItem::get($context_id);
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($item->title);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $item->id,
			'name' => $item->title,
			'permalink' => $url,
			'updated' => $item->created_date,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'feed__label',
			'created_date',
			'is_closed',
			'url',
		);
	}
	
	function getContext($item, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Feed:Item:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_FEED_ITEM);

		// Polymorph
		if(is_numeric($item)) {
			$item = DAO_FeedItem::get($item);
		} elseif($item instanceof Model_FeedItem) {
			// It's what we want already.
		} elseif(is_array($item)) {
			$item = Cerb_ORMHelper::recastArrayToModel($item, 'Model_FeedItem');
		} else {
			$item = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'created_date' => $prefix.$translate->_('common.created'),
			'guid' => $prefix.$translate->_('dao.feed_item.guid'),
			'is_closed' => $prefix.$translate->_('dao.feed_item.is_closed'),
			'title' => $prefix.$translate->_('common.title'),
			'url' => $prefix.$translate->_('common.url'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'created_date' => Model_CustomField::TYPE_DATE,
			'guid' => Model_CustomField::TYPE_SINGLE_LINE,
			'is_closed' => Model_CustomField::TYPE_CHECKBOX,
			'title' => Model_CustomField::TYPE_SINGLE_LINE,
			'url' => Model_CustomField::TYPE_URL,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_FEED_ITEM;
		$token_values['_types'] = $token_types;
		
		// Feed item token values
		if($item) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $item->title;
			$token_values['id'] = $item->id;
			$token_values['created_date'] = $item->created_date;
			$token_values['guid'] = $item->guid;
			$token_values['is_closed'] = $item->is_closed;
			$token_values['title'] = $item->title;
			$token_values['url'] = $item->url;

			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($item, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=feed_item&id=%d-%s",$item->id, DevblocksPlatform::strToPermalink($item->title)), true);
			
			// Feed
			@$feed_id = $item->feed_id;
			$token_values['feed_id'] = $feed_id;
		}
		
		// Feed
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext('cerberusweb.contexts.feed', null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'feed_',
			$prefix.'Feed:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);

		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_FEED_ITEM;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->addParams(array(
			SearchFields_FeedItem::IS_CLOSED => new DevblocksSearchCriteria(SearchFields_FeedItem::IS_CLOSED,'=',0),
		), true);
		$view->addParamsDefault(array(
			SearchFields_FeedItem::IS_CLOSED => new DevblocksSearchCriteria(SearchFields_FeedItem::IS_CLOSED,'=',0),
		), true);
		$view->renderSortBy = SearchFields_FeedItem::CREATED_DATE;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		//$view->name = 'Calls';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_FeedItem::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_FeedItem::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($context_id) && null != ($item = DAO_FeedItem::get($context_id))) {
			$tpl->assign('model', $item);
		}
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_FEED_ITEM, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_FEED_ITEM, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}
		
		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_FEED_ITEM, $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/item/peek.tpl');
	}
};