<?php
class Event_FeedItemMacro extends AbstractEvent_FeedItem {
	const ID = 'event.macro.feeditem';
	
	function __construct() {
		$this->_event_id = self::ID;
	}
	
	static function trigger($trigger_id, $item_id, $variables=array()) {
		$events = DevblocksPlatform::getEventService();
		$events->trigger(
	        new Model_DevblocksEvent(
	            self::ID,
                array(
                    'item_id' => $item_id,
                    '_variables' => $variables,
                	'_whisper' => array(
                		'_trigger_id' => array($trigger_id),
                	),
                )
            )
		);
	}
};