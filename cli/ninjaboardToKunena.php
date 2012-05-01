<?php

// Make sure we're being called from the command line, not a web interface
if (array_key_exists('REQUEST_METHOD', $_SERVER))
	die();

// Set flag that this is a parent file.
define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__FILE__) . '/defines.php')) {
	require_once dirname(dirname(__FILE__)) . '/defines.php';
}

if (!defined('_JDEFINES')) {
	define('JPATH_BASE', dirname(dirname(__FILE__)));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_LIBRARIES . '/import.php';
require_once JPATH_LIBRARIES . '/cms.php';

// Force library to be in JError legacy mode
JError::$legacy = true;

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

class MigrateNinjaBoard extends JApplicationCli {

	/**
	 * Entry point for the script
	 *
	 * @return  void
	 *
	 * @since   2.5
	 */
	function updateUserTable() {
		// this is just in case we want the alias the users added
		$query = 'ALTER TABLE `#__kunena_users` ADD COLUMN `alias` text AFTER `showOnline`';

		$db = JFactory::getDBO();
		$db -> setQuery($query);
		$result = $db -> query();

	}

	public function insertUser($NBuser) {

		$KunenaUser = NEW stdClass;
		$KunenaUser -> userid = $NBuser -> ninjaboard_person_id;
		$KunenaUser -> view = 'flat';
		$KunenaUser -> signature = $NBuser -> signature;
		$KunenaUser -> moderator = '0';
		//$KunenaUser -> banned = NULL;
		//$KunenaUser->ordering = '';
		$KunenaUser -> posts = $NBuser -> posts;

		if ($NBuser -> avatar != '/media/com_ninjaboard/images/avatar.png' && !empty($NBuser -> avatar)) {
			$KunenaUser -> avatar = $NBuser -> avatar;
		}
		$KunenaUser -> alias = $NBuser -> alias;
		$KunenaUser -> karma = '0';
		/*$KunenaUser -> karma_time = NULL;
		 $KunenaUser -> group_id = NULL;
		 $KunenaUser -> uhits = NULL;
		 $KunenaUser -> personalText = NULL;
		 $KunenaUser -> gender = NULL;
		 $KunenaUser -> birthdate = NULL;
		 $KunenaUser -> location = NULL;
		 $KunenaUser -> ICQ = NULL;
		 $KunenaUser -> AIM = NULL;
		 $KunenaUser -> YIM = NULL;
		 $KunenaUser -> MSN = NULL;
		 $KunenaUser -> SKYPE = NULL;
		 $KunenaUser -> TWITTER = NULL;
		 $KunenaUser -> FACEBOOK = NULL;
		 $KunenaUser -> GTALK = NULL;
		 $KunenaUser -> LINKEDIN = NULL;
		 $KunenaUser -> DELICIOUS = NULL;
		 $KunenaUser -> FRIENDFEED = NULL;
		 $KunenaUser -> DIGG = NULL;
		 $KunenaUser -> BLOGPOST = NULL;
		 $KunenaUser -> FLICKR = NULL;
		 $KunenaUser -> BEBO = NULL;
		 $KunenaUser -> rank = NULL;*/
		$KunenaUser -> hideEmail = '1';
		$KunenaUser -> showOnline = '1';

		$db = JFactory::getDBO();
		if (!$db -> insertObject('#__kunena_users', $KunenaUser, 'userid')) {
			echo $db -> stderr(TRUE);
			return false;
		}

		//return $db -> insertid();
		return $KunenaUser -> userid;
	}

	public function insertForum($NBForum) {

		/*id
		 parent
		 name
		 cat_emoticon
		 locked
		 alert_admin
		 moderated
		 moderators
		 accesstype
		 access
		 pub_access
		 pub_recurse
		 admin_access
		 admin_recurse
		 ordering
		 future2
		 published
		 checked_out
		 checked_out_time
		 review
		 allow_anonymous
		 post_anonymous
		 hits
		 description
		 headerdesc
		 class_sfx
		 allow_polls
		 id_last_msg
		 numTopics
		 numPosts
		 time_last_msg
		 * */

		$KunenaForum = NEW stdClass;
		$KunenaForum -> id = $NBForum -> ninjaboard_forum_id;
		//make sure this actually works with deep paths
		$parent = preg_split('/\//', $NBForum -> path, NULL, PREG_SPLIT_NO_EMPTY);
		$KunenaForum -> parent = stripcslashes(end($parent));

		$KunenaForum -> name = $NBForum -> title;
		$KunenaForum -> accesstype = 'joomla.level';
		$KunenaForum -> access = '1';
		$KunenaForum -> pub_access = '1';
		$KunenaForum -> pub_recurse = '1';
		$KunenaForum -> published = '1';
		$KunenaForum -> description = $NBForum -> description;

		$db = JFactory::getDBO();
		if (!$db -> insertObject('#__kunena_categories', $KunenaForum, 'id')) {
			echo $db -> stderr(TRUE);
			return false;
		}

	}

	function addMotherCategory($title = "Forum") {
		$db = JFactory::getDBO();
		$query = "insert into `#__kunena_categories` ( `name`) values ( 'Forum')";
		$db -> setQuery($query);
		$result = $db -> query();

		if (!empty($result)) {
			$id = $db -> insertid();
			$query = "Update #__kunena_categories set parent = '{$id}' where parent = '0'";
			$db -> setQuery($query);
			$result = $db -> query();

		}

	}

	function insertMessage($topic, $post) {
		$db = JFactory::getDBO();
		/*id
		 parent
		 thread
		 catid
		 name
		 userid
		 email
		 subject
		 time
		 ip
		 topic_emoticon
		 locked
		 hold
		 ordering
		 hits
		 moved
		 modified_by
		 modified_time
		 modified_reason*/

		$Message = new stdClass;
		$Message -> id = $post -> ninjaboard_post_id;
		if ($topic -> first_post_id == $post -> ninjaboard_post_id) {
			$Message -> parent = '0';
		} else {
			$Message -> parent = $topic -> first_post_id;
		}

		$Message -> thread = $topic -> first_post_id;
		$Message -> subject = $post -> subject;
		$Message -> locked = $post -> locked;

		$Message -> catid = $topic -> forum_id;
		$Message -> userid = $post -> created_user_id;
		$Message -> ip = $post -> user_ip;
		$Message -> name = $post -> guest_name;
		$Message -> email = $post -> guest_email;
		$Message -> modified_by = $post -> modified_user_id;
		if ($Message -> modified_by) {
			$Message -> modified_time = JFactory::getDate($post -> modified) -> toUnix();
		}

		$Message -> modified_reason = $post -> edit_reason;
		$Message -> hits = $topic -> hits;
		$Message -> time = JFactory::getDate($post -> created_time) -> toUnix();

		if (!$db -> insertObject('#__kunena_messages', $Message, 'id')) {
			echo $db -> stderr(TRUE);
			return false;
		}

		$text = new stdClass;
		$text -> mesid = $post -> ninjaboard_post_id;
		$text -> message = $post -> text;

		if (!$db -> insertObject('#__kunena_messages_text', $text, 'mesid')) {
			echo $db -> stderr(TRUE);
			return false;
		}
	}

	function clearTables() {
		$db = JFactory::getDBO();
		$query = "TRUNCATE #__kunena_categories";
		$db -> setQuery($query);
		$result = $db -> query();
		if ($result) {
			$this -> out('TRUNCATE #__kunena_categories success');
		}

		$query = "TRUNCATE #__kunena_messages";
		$db -> setQuery($query);
		$result = $db -> query();
		if ($result) {
			$this -> out('TRUNCATE #__kunena_messages success');
		}

		$query = "TRUNCATE #__kunena_messages_text";
		$db -> setQuery($query);
		$result = $db -> query();
		if ($result) {
			$this -> out('TRUNCATE #__kunena_messages_text success');
		}
		$query = "TRUNCATE #__kunena_users";
		$db -> setQuery($query);
		$result = $db -> query();
		if ($result) {
			$this -> out('TRUNCATE #__kunena_users success');
		}
	}

	public function execute() {

		$this -> out('MIGRATING NINJABOARD');
		$this -> out('============================');
		jimport('joomla.database.database');
		jimport('joomla.utilities.date');

		// Purge all old records
		$db = JFactory::getDBO();

		$this -> updateUserTable();

		$this -> clearTables();

		//getting users in chunks
		$query = "SELECT COUNT(*) FROM jos_ninjaboard_people";
		$db -> setQuery($query);
		$limit = $db -> loadResult();
		$this -> out('MIGRATING NINJABOARD - USERS');
		$this -> out('------------------------------');
		$this -> out($limit . ' user found');

		$user_id = 0;

		// processing the users 100 at a time to avoid memory limit errors
		$limit = $limit / 100;

		for ($i = 0; $i <= $limit; $i++) {
			$query = "select * from `jos_ninjaboard_people` WHERE ninjaboard_person_id > {$user_id} LIMIT 100";

			$db -> setQuery($query);
			$users = $db -> loadObjectList();

			foreach ($users as $user) {
				$user_id = $this -> insertUser($user);
			}
			$this -> out($i * 100 . ' / ' . $limit * 100 . ' Users Processed');
		}

		// Get the Forums
		$this -> out('MIGRATING NINJABOARD - FORUMS');
		$query = "Select * from `jos_ninjaboard_forums`";
		$db -> setQuery($query);
		$forums = $db -> loadObjectList();

		foreach ($forums as $forum) {
			$this -> insertForum($forum);

			// OK getting Ninjaboard Topics and messages and converting and adding them to discussions

			$this -> out('Getting Ninjaboard Topics and Posts for forum  ' . $forum -> title);
			$query = "Select * from `jos_ninjaboard_topics` WHERE forum_id = " . $forum -> ninjaboard_forum_id;
			$db -> setQuery($query);
			$topics = $db -> loadObjectList();

			foreach ($topics as $topic) {
				$postQuery = "SELECT * FROM jos_ninjaboard_posts where  ninjaboard_topic_id = " . $topic -> ninjaboard_topic_id;
				$db -> setQuery($postQuery);
				$posts = $db -> loadObjectList();

				foreach ($posts as $post) {

					$this -> insertMessage($topic, $post);

				}

			}

		}

		$this -> addMotherCategory();
	}

}

// DO THE DO

JApplicationCli::getInstance('MigrateNinjaBoard') -> execute();
