<?php
/**
 * @package    Forum Migrate SMF 2.0 RC2 to Kunena 4.0.10
 *
 * @copyright  Copyright (C) 2016 Emir Sakic, http://www.sakic.net All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This is a CLI script which should be called from the command-line,
 * not from the browser. Use it like:
 * php forum_migrate.php
 */

// Set flag that this is a parent file.
const _JEXEC = 1;

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

/**
 * This script will fetch the update information for all extensions and store
 * them in the database, speeding up your administrator.
 *
 * @package  Joomla.CLI
 * @since    2.5
 */
class ForumMigrate extends JApplicationCli
{
	/**
	 * Entry point for the script
	 *
	 * @return  void
	 *
	 * @since   2.5
	 */
	public function doExecute()
	{
		try
		{
			//$this->out('Migrating users...');
			//$this->_migrateUsers();
			//$this->out('Done migrating users.');

			// Now sync users with their Kunena profiles in Kunena component

			//$this->out('Migrating user profiles...');
			//$this->_migrateUserProfiles();
			//$this->out('Done migrating user profiles.');

			// Create categories manually in Kunena component

			//$this->out('Migrating posts...');
			//$this->_migratePosts();
			//$this->out('Done migrating posts.');
		}
		catch (Exception $e)
		{
			$this->out($e->getMessage(), true);
			$this->close($e->getCode());
		}
	}

	private function _migratePosts() {
		$db = JFactory::getDbo();

		/* TOPICS */

		$query = $db->getQuery(true);
		$query->select('
			t.*,
			fm.subject AS first_subject, fm.poster_time AS first_poster_time, fm.body AS first_body, fm.poster_name AS first_poster_name,
			lm.subject AS last_subject, lm.poster_time AS last_poster_time, lm.body AS last_body, lm.poster_name AS last_poster_name
			')->from('smf_topics AS t');
		$query->leftJoin('smf_messages AS fm ON fm.id_msg=t.id_first_msg');
		$query->leftJoin('smf_messages AS lm ON lm.id_msg=t.id_last_msg');
		$query->order('t.id_topic');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$board_associations = $this->_getBoardAssociations();
		$user_associations = $this->_getUserAssociations();
		$topic_associations = array();

		foreach ($rows as $row) {
			$obj = new JObject;
			$obj->category_id = $board_associations[$row->id_board];
			$obj->subject = $this->_string_decode($row->first_subject);
			$obj->locked = $row->locked;
			$obj->posts = $row->num_replies + 1;
			$obj->hits = $row->num_views;
			// TODO attachments
			$obj->first_post_id = $row->id_first_msg;	// old ID at this point
			$obj->first_post_time = $row->first_poster_time;
			$obj->first_post_userid = isset($user_associations[$row->id_member_started]) ? $user_associations[$row->id_member_started] : 0;
			$obj->first_post_message = $this->_string_decode($row->first_body);
			$obj->first_post_guest_name = $row->first_poster_name;
			$obj->last_post_id = $row->id_last_msg;	// old ID at this point
			$obj->last_post_time = $row->last_poster_time;
			$obj->last_post_userid = isset($user_associations[$row->id_member_updated]) ? $user_associations[$row->id_member_updated] : 0;
			$obj->last_post_message = $this->_string_decode($row->last_body);
			$obj->last_post_guest_name = $row->last_poster_name;

			$db->insertObject('#__kunena_topics', $obj);

			$topic_associations[$row->id_topic] = $db->insertid();
		}

		/* MESSAGES */
		$query->clear();
		$query->select('m.*, t.id_first_msg')->from('smf_messages AS m');
		$query->leftJoin('smf_topics AS t ON t.id_topic=m.id_topic');
		$query->order('m.id_msg');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$msg_associations = array();
		foreach ($rows as $row) {
			$obj = new JObject;
			$obj->parent = isset($msg_associations[$row->id_first_msg]) ? $msg_associations[$row->id_first_msg] : 0;
			$obj->thread = isset($topic_associations[$row->id_topic]) ? $topic_associations[$row->id_topic] : 0;
			$obj->catid = isset($board_associations[$row->id_board]) ? $board_associations[$row->id_board] : 0;
			$obj->name = $row->poster_name;
			$obj->userid = isset($user_associations[$row->id_member]) ? $user_associations[$row->id_member] : 0;
			$obj->email = $row->poster_email;
			$obj->subject = $this->_string_decode($row->subject);
			$obj->time = $row->poster_time;
			$obj->ip = $row->poster_ip;
			if ($row->modified_time!=0) {
				$obj->modified_by = $obj->userid;
				$obj->modified_time = $row->modified_time;
			}

			$db->insertObject('#__kunena_messages', $obj);
			$insert_id = $db->insertid();

			$msg_associations[$row->id_msg] = $insert_id;

			// insert message text
			$obj = new JObject;
			$obj->mesid = $insert_id;
			$obj->message = $this->_string_decode($row->body);
			$db->insertObject('#__kunena_messages_text', $obj);
		}

		/* update TOPICS with first and last post ID */
		$query->clear();
		$query->select('id, first_post_id, last_post_id')->from('#__kunena_topics')->order('id');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row) {
			$obj = new JObject;
			$obj->id = $row->id;
			$obj->first_post_id = isset($msg_associations[$row->first_post_id]) ? $msg_associations[$row->first_post_id] : $row->first_post_id;
			$obj->last_post_id = isset($msg_associations[$row->last_post_id]) ? $msg_associations[$row->last_post_id] : $row->last_post_id;
			$db->updateObject('#__kunena_topics', $obj, 'id');
		}

		/* CATEGORIES */
		$query->clear();
		$query->select('*')->from('smf_boards')->order('id_board');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row) {
			$obj = new JObject;
			$obj->id = $board_associations[$row->id_board];
			$obj->numTopics = $row->num_topics;
			$obj->numPosts = $row->num_posts;
			$obj->last_topic_id = 0;
			$obj->last_post_id = isset($msg_associations[$row->id_last_msg]) ? $msg_associations[$row->id_last_msg] : 0;
			$obj->last_post_time = 0;

			$db->updateObject('#__kunena_categories', $obj, 'id');
		}

		// find last topic and last post
		$query->clear();
		$query->select('id')->from('#__kunena_categories')->order('id');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row) {
			// last topic id
			$query->clear();
			$query->select('id')->from('#__kunena_topics')->where('category_id='.$row->id)->order('id DESC');
			$db->setQuery($query, 0, 1);
			$last_topic_id = $db->loadResult();

			// last post time
			$query->clear();
			$query->select('time')->from('#__kunena_messages')->where('catid='.$row->id)->order('time DESC');
			$db->setQuery($query, 0, 1);
			$last_post_time = $db->loadResult();

			if ($last_topic_id && $last_post_time) {
				$obj = new JObject;
				$obj->id = $row->id;
				$obj->last_topic_id = $last_topic_id;
				$obj->last_post_time = $last_post_time;

				$db->updateObject('#__kunena_categories', $obj, 'id');
			}
		}
	}

	private function _migrateUserProfiles() {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select('*')->from('smf_members AS m');
		$query->leftJoin('#__users AS u ON u.username=m.member_name');
		$query->order('id_member');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row) {
			//$this->out('Importing ' . $row->member_name . '...');
			$obj = new JObject;

			$obj->userid = $row->id;
			$obj->signature = str_replace("<br />", "\n", $row->signature);
			$obj->posts = $row->posts;
			$obj->avatar = $row->avatar;
			$obj->personalText = $row->personal_text;
			$obj->gender = $row->gender;
			$obj->birthdate = $row->birthdate;
			$obj->location = $row->location;
			$obj->icq = $row->icq;
			$obj->aim = $row->aim;
			$obj->yim = $row->yim;
			$obj->msn = $row->msn;
			$obj->websitename = $row->website_title;
			$obj->websiteurl = $row->website_url;
			$obj->hideEmail = $row->hide_email;
			$obj->showOnline = $row->show_online;

			$db->updateObject('#__kunena_users', $obj, 'userid');

			//$this->out($row->member_name . ' successfully imported.');
		}
	}

	private function _migrateUsers() {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select('*')->from('smf_members')->order('id_member');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row) {
			//$this->out('Importing ' . $row->member_name . '...');

			$user = new JUser;
			$data = array();
			$data['name'] = $row->real_name;
			$data['username'] = $row->member_name;
			$data['email'] = $row->email_address;
			$data['password'] = '';
			$data['registerDate'] = gmdate("Y-m-d H:i:s", $row->date_registered);
			$data['lastvisitDate'] = gmdate("Y-m-d H:i:s", $row->last_login);

			$data['block'] = 0;
			$data['activation'] = 0;
			$data['groups'] = array(2);

			if (!$user->bind($data))
			{
				$this->out('Bind failed, skipping ' . $row->member_name . ': ' . $user->getError());
				continue;
			}

			$table = $user->getTable();
			$table->bind($user->getProperties());

			if (!$table->check())
			{
				$this->out('Check failed, skipping ' . $row->member_name . ': ' . $table->getError());
				continue;
			}

			// Store the user data in the database
			$result = $table->store();

			$insert_id = $table->id;

			if (!$result)
			{
				$this->out('Save failed, skipping ' . $row->member_name . ': ' . $table->getError());
				continue;
			}

			// update the password with the hash (to be changed on successful login)
			/*
			$hash = sha1(strtolower($row->member_name) . $password_clear);
			$this->out($hash);
			$this->out($row->passwd);
			*/
			$obj = new JObject;
			$obj->id = $insert_id;
			$obj->password = $row->passwd;
			$db->updateObject('#__users', $obj, 'id');

			//$this->out($row->member_name . ' successfully imported.');

		}

	}

	private function _getBoardAssociations() {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select(array('b.id_board AS old_id', 'c.id AS new_id'))->from('smf_boards AS b')->leftJoin('#__kunena_categories AS c ON c.name=b.name')->order('b.id_board');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$arr = array();
		foreach ($rows as $row) {
			$arr[$row->old_id] = $row->new_id;
		}

		return $arr;
	}

	private function _getUserAssociations() {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select('m.id_member AS old_id, u.id AS new_id')->from('smf_members AS m');
		$query->leftJoin('#__users AS u ON u.username=m.member_name');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$arr = array();
		foreach ($rows as $row) {
			$arr[$row->old_id] = $row->new_id;
		}

		return $arr;
	}

	private function _string_decode($str) {
		$str = html_entity_decode($str);
		$str = str_replace('<br />', "\n", $str);
		return $str;
	}
}

JApplicationCli::getInstance('ForumMigrate')->execute();
