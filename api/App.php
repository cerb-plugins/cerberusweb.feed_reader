<?php
if (class_exists('CerberusCronPageExtension')):
class FeedsCron extends CerberusCronPageExtension {
	public function run() {
		$logger = DevblocksPlatform::getConsoleLog();
		$logger->info("[Feeds] Starting Feed Reader");
			
		$feeds = DAO_Feed::getWhere();
		
		if(is_array($feeds))
		foreach($feeds as $feed_id => $feed) {
			$rss = DevblocksPlatform::parseRss($feed->url);
			
			if(isset($rss['items']) && is_array($rss['items']))
			foreach($rss['items'] as $item) {
				$guid = md5($feed_id.$item['title'].$item['link']);
	
				// Look up by GUID
				$results = DAO_FeedItem::getWhere(sprintf("%s = %s AND %s = %d",
					DAO_FeedItem::GUID,
					Cerb_ORMHelper::qstr($guid),
					DAO_FeedItem::FEED_ID,
					$feed_id
				));
				
				// If we've already inserted this item, skip it
				if(!empty($results))
					continue;
				
				$fields = array(
					DAO_FeedItem::FEED_ID => $feed_id,
					DAO_FeedItem::CREATED_DATE => $item['date'],
					DAO_FeedItem::GUID => $guid,
					DAO_FeedItem::TITLE => DevblocksPlatform::stripHTML($item['title']),
					DAO_FeedItem::URL => $item['link'],
				);
				$item_id = DAO_FeedItem::create($fields);
				
				if(empty($item_id))
					continue;
				
				if(!empty($item['content'])) {
					if(version_compare(APP_VERSION, '6.9.0', '>=')) {
						$comment = DevblocksPlatform::stripHTML($item['content']);
						
					} else {
						$comment = $this->_stripHTML($item['content']);
					}
					
					if(!empty($comment)) {
						$comment_id = DAO_Comment::create(array(
							DAO_Comment::COMMENT => $comment,
							DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_FEED_ITEM,
							DAO_Comment::CONTEXT_ID => $item_id,
							DAO_Comment::CREATED => time(),
							DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
							DAO_Comment::OWNER_CONTEXT_ID => 0,
						));
					}
				}
				
				$logger->info(sprintf("[Feeds] [%s] Imported: %s", $feed->name, $item['title']));
			}
		}
		
		$logger->info("[Feeds] Feed Reader Finished");
	}
	
	public function configure($instance) {
	}
	
	public function saveConfigurationAction() {
	}
	
	// [TODO] Remove this after 6.9 release and new plugin requirements
	private function _stripHTML($str, $strip_whitespace=true, $skip_blockquotes=false) {
		
		// Pre-process some HTML entities that confuse UTF-8
		
		$str = str_ireplace(
			array(
				'&rsquo;',     // '
				'&#8217;',
				'&#x2019;',
				'&hellip;',    // ...
				'&#8230;',
				'&#x2026;',
				'&ldquo;',     // "
				'&#8220;',
				'&#x201c;',
				'&rdquo;',     // "
				'&#8221;',
				'&#x201d;',
			),
			array(
				"'",
				"'",
				"'",
				'...',
				'...',
				'...',
				'"',
				'"',
				'"',
				'"',
				'"',
				'"',
			),
			$str
		);
		
		// Pre-process blockquotes
		if(!$skip_blockquotes) {
			$dom = new DOMDocument('1.0', LANG_CHARSET_CODE);
			$dom->strictErrorChecking = false;
			$dom->recover = true;
			$dom->validateOnParse = false;
			
			libxml_use_internal_errors(true);
			
			$dom->loadHTML(sprintf('<?xml encoding="%s">', LANG_CHARSET_CODE) . $str);
			
			$errors = libxml_get_errors();
			libxml_clear_errors();
			
			$xpath = new DOMXPath($dom);
			
			while(($blockquotes = $xpath->query('//blockquote')) && $blockquotes->length) {
			
				foreach($blockquotes as $blockquote) { /* @var $blockquote DOMElement */
					$nested = $xpath->query('.//blockquote', $blockquote);
					
					// If the blockquote contains another blockquote, ignore it for now
					if($nested->length > 0)
						continue;
					
					// Change the blockquote tags to DIV, prefixed with '>'
					$div = $dom->createElement('span');
					
					$plaintext = DevblocksPlatform::stripHTML($dom->saveXML($blockquote), $strip_whitespace, true);
					
					$out = explode("\n", trim($plaintext));
					
					array_walk($out, function($line) use ($dom, $div) {
						$text = $dom->createTextNode('> ' . $line);
						$div->appendChild($text);
						$div->appendChild($dom->createElement('br'));
					});
					
					$blockquote->parentNode->replaceChild($div, $blockquote);
				}
			}
			
			$html = $dom->saveXML();
			
			// Make sure it's not blank before trusting it.
			if(!empty($html)) {
				$str = $html;
				unset($html);
			}
		}
		
		// Convert hyperlinks to plaintext
		
		$str = preg_replace_callback(
			'@<a[^>]*?>(.*?)</a>@si',
			function($matches) {
				if(!isset($matches[0]))
					return false;
				
				$out = '';
				
				$dom = new DOMDocument('1.0', LANG_CHARSET_CODE);
				$dom->strictErrorChecking = false;
				$dom->recover = false;
				$dom->validateOnParse = false;
				
				libxml_use_internal_errors(true);
				
				$dom->loadXML($matches[0]);
				
				libxml_get_errors();
				libxml_clear_errors();
				
				@$href_link = $dom->documentElement->getAttribute('href');
				@$href_label = trim($dom->documentElement->nodeValue);
				
				// Skip if there is no label text (images, etc)
				if(empty($href_label)) {
					$out = null;
					
				// If the link and label are the same, ignore label
				} elseif($href_label == $href_link) {
					$out = $href_link;
					
				// Otherwise, format like Markdown
				} else {
					$out = sprintf("[%s](%s)",
						$href_label,
						$href_link
					);
				}
				
				return $out;
			},
			$str
		);
		
		// Code blocks to plaintext
		
		$str = preg_replace_callback(
			'@<code[^>]*?>(.*?)</code>@si',
			function($matches) {
				if(isset($matches[1])) {
					$out = $matches[1];
					$out = str_replace(" ","&nbsp;", $out);
					return $out;
				}
			},
			$str
		);
		
		// Preformatted blocks to plaintext
		
		$str = preg_replace_callback(
			'#<pre.*?/pre\>#si',
			function($matches) {
				if(isset($matches[0])) {
					$out = $matches[0];
					$out = str_replace("\n","<br>", trim($out));
					return '<br>' . $out . '<br>';
				}
			},
			$str
		);
		
		// Strip all CRLF and tabs, spacify </TD>
		if($strip_whitespace) {
			$str = str_ireplace(
				array("\r","\n","\t","</td>"),
				array('','',' ',' '),
				trim($str)
			);
			
		} else {
			$str = str_ireplace(
				array("\t","</td>"),
				array(' ',' '),
				trim($str)
			);
		}
		
		// Convert Unicode nbsp to space
		$str = preg_replace(
			'#\xc2\xa0#',
			' ',
			$str
		);
		
		// Handle XHTML variations
		$str = preg_replace(
			'@<br[^>]*?>@si',
			"<br>",
			$str
		);
		
		// Turn block tags into a linefeed
		$str = str_ireplace(
			array(
				'<BR>',
				'<P>',
				'</P>',
				'</PRE>',
				'<HR>',
				'<TR>',
				'</H1>',
				'</H2>',
				'</H3>',
				'</H4>',
				'</H5>',
				'</H6>',
				'</DIV>',
				'<UL>',
				'</UL>',
				'<OL>',
				'</OL>',
				'</LI>',
				'</OPTION>',
				'<TABLE>',
				'</TABLE>',
			),
			"\n",
			$str
		);

		$str = str_ireplace(
			array(
				'<LI>',
			),
			"<LI>* ",
			$str
		);
		
		// Strip non-content tags
		$search = array(
			'@<head[^>]*?>.*?</head>@si',
			'@<style[^>]*?>.*?</style>@si',
			'@<script[^>]*?.*?</script>@si',
			'@<object[^>]*?.*?</object>@si',
			'@<embed[^>]*?.*?</embed>@si',
			'@<applet[^>]*?.*?</applet>@si',
			'@<noframes[^>]*?.*?</noframes>@si',
			'@<noscript[^>]*?.*?</noscript>@si',
			'@<noembed[^>]*?.*?</noembed>@si',
		);
		$str = preg_replace($search, '', $str);
		
		// Strip tags
		$str = strip_tags($str);
		
		// Flatten multiple spaces into a single
		$str = preg_replace('# +#', ' ', $str);

		// Flatten multiple linefeeds into a single
		$str = preg_replace("#\n{2,}#", "\n\n", $str);
		
		// Translate HTML entities into text
		$str = html_entity_decode($str, ENT_COMPAT, LANG_CHARSET_CODE);

		// Wrap quoted lines
		// [TODO] This should be more reusable
		$str = _DevblocksTemplateManager::modifier_devblocks_email_quote($str);
		
		// Clean up bytes (needed after HTML entities)
		$str = mb_convert_encoding($str, LANG_CHARSET_CODE, LANG_CHARSET_CODE);
		
		return ltrim($str);
	}
};
endif;

class Page_Feeds extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	function render() {
	}
	
	function saveFeedPopupAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
	
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
		@$url = DevblocksPlatform::importGPC($_REQUEST['url'], 'string', '');
		@$comment = DevblocksPlatform::importGPC($_REQUEST['comment'], 'string', '');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
	
		$active_worker = CerberusApplication::getActiveWorker();
	
		if(!empty($id) && !empty($do_delete)) { // Delete
			DAO_Feed::delete($id);
				
		} else {
			$fields = array(
				DAO_Feed::NAME => $name,
				DAO_Feed::URL => $url,
			);
			
			if(empty($id)) { // New
				$id = DAO_Feed::create($fields);
	
				// Watchers
				@$add_watcher_ids = DevblocksPlatform::sanitizeArray(DevblocksPlatform::importGPC($_REQUEST['add_watcher_ids'],'array',array()),'integer',array('unique','nonzero'));
				if(!empty($add_watcher_ids))
					CerberusContexts::addWatchers('cerberusweb.contexts.feed', $id, $add_watcher_ids);
				
				// View marquee
				if(!empty($id) && !empty($view_id)) {
					C4_AbstractView::setMarqueeContextCreated($view_id, 'cerberusweb.contexts.feed', $id);
				}
				
			} else { // Edit
				DAO_Feed::update($id, $fields);
			}
	
			// If we're adding a comment
			if(!empty($comment)) {
				$also_notify_worker_ids = array_keys(CerberusApplication::getWorkersByAtMentionsText($comment));
	
				$fields = array(
						DAO_Comment::CREATED => time(),
						DAO_Comment::CONTEXT => 'cerberusweb.contexts.feed',
						DAO_Comment::CONTEXT_ID => $id,
						DAO_Comment::COMMENT => $comment,
						DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
						DAO_Comment::OWNER_CONTEXT_ID => $active_worker->id,
				);
				$comment_id = DAO_Comment::create($fields, $also_notify_worker_ids);
			}
				
			// Custom fields
			@$field_ids = DevblocksPlatform::importGPC($_REQUEST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost('cerberusweb.contexts.feed', $id, $field_ids);
		}
	}
	
	function saveFeedItemPopupAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$is_closed = DevblocksPlatform::importGPC($_REQUEST['is_closed'], 'integer', 0);
		@$comment = DevblocksPlatform::importGPC($_REQUEST['comment'], 'string', '');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!empty($id) && !empty($do_delete)) { // Delete
			DAO_FeedItem::delete($id);
			
		} else {
			if(empty($id)) { // New
				$fields = array(
					DAO_FeedItem::IS_CLOSED => $is_closed,
				);
				$id = DAO_FeedItem::create($fields);
				
			} else { // Edit
				$fields = array(
					DAO_FeedItem::IS_CLOSED => $is_closed,
				);
				DAO_FeedItem::update($id, $fields);
				
			}

			// If we're adding a comment
			if(!empty($comment)) {
				$also_notify_worker_ids = array_keys(CerberusApplication::getWorkersByAtMentionsText($comment));
								
				$fields = array(
					DAO_Comment::CREATED => time(),
					DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_FEED_ITEM,
					DAO_Comment::CONTEXT_ID => $id,
					DAO_Comment::COMMENT => $comment,
					DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
					DAO_Comment::OWNER_CONTEXT_ID => $active_worker->id,
				);
				$comment_id = DAO_Comment::create($fields, $also_notify_worker_ids);
			}
			
			// Custom fields
			@$field_ids = DevblocksPlatform::importGPC($_REQUEST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_FEED_ITEM, $id, $field_ids);
		}
	}
	
	function showFeedItemBulkUpdateAction() {
		@$ids = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);

		if(!empty($ids)) {
			$id_list = DevblocksPlatform::parseCsvString($ids);
			$tpl->assign('ids', implode(',', $id_list));
		}
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_FEED_ITEM, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		// Macros
		
		$macros = DAO_TriggerEvent::getReadableByActor(
			$active_worker,
			'event.macro.feeditem'
		);
		$tpl->assign('macros', $macros);
		
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/item/bulk.tpl');
	}
	
	function doFeedItemBulkUpdateAction() {
		// Filter: whole list or check
		@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
		$ids = array();
		
		// View
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);
		
		// Call fields
		$is_closed = trim(DevblocksPlatform::importGPC($_POST['is_closed'],'string',''));

		// Scheduled behavior
		@$behavior_id = DevblocksPlatform::importGPC($_POST['behavior_id'],'string','');
		@$behavior_when = DevblocksPlatform::importGPC($_POST['behavior_when'],'string','');
		@$behavior_params = DevblocksPlatform::importGPC($_POST['behavior_params'],'array',array());
		
		$do = array();
		
		// Do: Due
		if(0 != strlen($is_closed))
			$do['is_closed'] = !empty($is_closed) ? 1 : 0;

		// Do: Scheduled Behavior
		if(0 != strlen($behavior_id)) {
			$do['behavior'] = array(
				'id' => $behavior_id,
				'when' => $behavior_when,
				'params' => $behavior_params,
			);
		}
		
		// Watchers
		$watcher_params = array();
		
		@$watcher_add_ids = DevblocksPlatform::importGPC($_REQUEST['do_watcher_add_ids'],'array',array());
		if(!empty($watcher_add_ids))
			$watcher_params['add'] = $watcher_add_ids;
			
		@$watcher_remove_ids = DevblocksPlatform::importGPC($_REQUEST['do_watcher_remove_ids'],'array',array());
		if(!empty($watcher_remove_ids))
			$watcher_params['remove'] = $watcher_remove_ids;
		
		if(!empty($watcher_params))
			$do['watchers'] = $watcher_params;
			
		// Do: Custom fields
		$do = DAO_CustomFieldValue::handleBulkPost($do);

		switch($filter) {
			// Checked rows
			case 'checks':
				@$ids_str = DevblocksPlatform::importGPC($_REQUEST['ids'],'string');
				$ids = DevblocksPlatform::parseCsvString($ids_str);
				break;
			case 'sample':
				@$sample_size = min(DevblocksPlatform::importGPC($_REQUEST['filter_sample_size'],'integer',0),9999);
				$filter = 'checks';
				$ids = $view->getDataSample($sample_size);
				break;
			default:
				break;
		}
		
		$view->doBulkUpdate($filter, $do, $ids);
		
		$view->render();
		return;
	}
	
	function viewFeedItemCloseAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		@$ids = DevblocksPlatform::sanitizeArray(
			DevblocksPlatform::importGPC($_REQUEST['row_id'],'array',array()),
			'integer',
			array('nonzero','unique')
		);
		
		if(null == ($view = C4_AbstractViewLoader::getView($view_id)))
			return;
		
		DAO_FeedItem::update($ids, array(
			DAO_FeedItem::IS_CLOSED => 1,
		));
		
		$view->render();
		return;
	}
	
	function viewFeedItemExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}
		
		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
					//'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=feed_item', true),
//					'toolbar_extension_id' => 'cerberusweb.explorer.toolbar.',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $id => $row) {
				if($id==$explore_from)
					$orig_pos = $pos;
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $id,
					'url' => $url_writer->writeNoProxy(sprintf("c=profiles&type=feed_item&id=%d", $row[SearchFields_FeedItem::ID]), true),
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	function viewFeedItemsUrlExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=feed', true),
					'toolbar_extension_id' => 'cerberusweb.feed_reader.item.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_FeedItem::ID],
					'url' => $row[SearchFields_FeedItem::URL],
					'is_closed' => $row[SearchFields_FeedItem::IS_CLOSED],
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}

	function exploreItemStatusAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$is_closed = DevblocksPlatform::importGPC($_REQUEST['is_closed'], 'integer', 0);
		
		if(empty($id))
			return;
		
		DAO_FeedItem::update($id, array(
			DAO_FeedItem::IS_CLOSED => ($is_closed) ? 1 : 0,
		));
	}
};

class ExplorerToolbar_FeedReaderItem extends Extension_ExplorerToolbar {
	function render(Model_ExplorerSet $item) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('item', $item);
		$tpl->display('devblocks:cerberusweb.feed_reader::feeds/item/explorer_toolbar.tpl');
	}
};

if (class_exists('DevblocksEventListenerExtension')):
class EventListener_FeedReader extends DevblocksEventListenerExtension {
	/**
	 * @param Model_DevblocksEvent $event
	 */
	function handleEvent(Model_DevblocksEvent $event) {
		switch($event->id) {
			case 'cron.maint':
				//DAO_Feed::maint();
				DAO_FeedItem::maint();
				break;
		}
	}
};
endif;