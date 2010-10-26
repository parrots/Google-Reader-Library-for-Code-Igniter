<?php
/**
* Reader:: a class for retreiving information from the Google Reader API.
*
* License: BSD License: http://creativecommons.org/licenses/BSD/
*
* @author  Curtis Herbert <me@forgottenexpanse.com>
* @url http://www.consumedbycode.com/code/ci_google_reader
* @version 1.2.2 (2010-10-25)
*/
class Reader {
	//urls needed to interact with google
	var $login_url 			= 	'https://www.google.com/accounts/ClientLogin';
	var $subscriptions_url 	= 	'http://www.google.com/reader/api/0/subscription/list';
	var $feed_url 			= 	'http://www.google.com/reader/atom/';
	var $item_url			=	'http://www.google.com/reader/api/0/item/edit';
	var $edit_url			= 	'http://www.google.com/reader/api/0/edit-tag?client=scroll';
	var $token_url			=	'https://www.google.com/accounts/ClientLogin';
	var $source		 		= 	'Google Reader API for Code Igniter';
	
	//states for items
	var $read_state 		= 	'user/-/state/com.google/read';
	var $unread_state 		= 	'user/-/state/com.google/kept-unread';
	var $starred_state 		= 	'user/-/state/com.google/starred';
	var $shared_state 		= 	'user/-/state/com.google/broadcast';
	var $reading_state		= 	'user/-/state/com.google/reading-list';
	
	//internal variables
	var $_email;
	var $_password;
	var $_channel;
	var $_errors = array();
	var $_token = NULL;
	
	/**
	 * Constructor.
	 * 
	 * @access public
	 * @param array $config		Array of configuration data
	 * @see initialize
	 */
	function Reader($config = array()) {
		$this->initialize($config);
	}
	
	/**
	 * Loads configuration data for later use.  Expected keys in array are
	 * email and password. Logs the user in assuming both are provided.
	 * 
	 * @access public
	 * @param mixed $config		Array of configuration data
	 * @return void
	 */
	function initialize($config) {
		if (isset($config['email'])) $this->_email = $config['email'];
		if (isset($config['password'])) $this->_password = $config['password'];
		
		if (isset($config['email']) && isset($config['password'])) {
			$this->_load_token();
		}
	}
	
	
	/**
	 * Shares a URL through Google Reader's notes.  Use this function if you'd like 
	 * to share a page, but that page is not in any of your feeds.
	 * 
	 * @access public
	 * @param mixed $url url of the page to share
	 * @param mixed $title title of the page
	 * @param mixed $note note to associate with the shared page
	 * @param mixed $snippet. (default: NULL) optional annottion for the note -- shown as summary of shared item
	 * @param mixed $share. (default: TRUE) if the item should be added to your shared items, 
	 *										false just creates an unshared note for your own use
	 * @return true/false based on the success of sharing the page
	 */
	function share_page($url, $title, $note, $snippet = NULL, $share = TRUE) {
		$this->_clear_errors();
		if (!$this->_load_token()) return;
		
		$post_data = array();
		$post_data['T'] = $this->_token;
		$post_data['annotation'] = $note;
		$post_data['snippet'] = $snippet;
		$post_data['linkify'] = 'false';
		$post_data['share'] = (($share)?'true':'false');
		$post_data['title'] = $title;
		$post_data['url'] = $url;
		
		//need to build up some data about the URL being shared
		$url_components = parse_url($url);
		if (empty($url_components['scheme']) || empty($url_components['host'])) {
			$this->_set_error('note_error', 'The URL you provided for the note is invalid.');
			return FALSE;
		}
		$post_data['srcUrl'] = $url_components['scheme'] . '://' . $url_components['host'];
		$post_data['srcTitle'] = $url_components['host'];
		
		if ($this->_fetch($this->item_url, $post_data) != 'OK') {
			$this->_set_error('note_error', 'There was an unknown error while creating the note.');
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	/**
	 * Returns an array of all items for the logged in user.  This function
	 * returns an cleaned up version of the data returned from Google Reader.
	 * If you'd prefer the raw XML, use all_items_raw().
	 * 
	 * @access public
	 * @return array of all items
	 */
	function all_items() {
		$unread_xml = $this->all_items_raw();
		if ($this->errors_exist()) return array();
		return $this->_parse_entries($unread_xml);	
	}
	
	/**
	 * Returns an array of shared items for the logged in user.  This function
	 * returns an cleaned up version of the data returned from Google Reader.
	 * If you'd prefer the raw XML, use shared_items_raw().
	 *
	 * Unlike the other functions, you do not need to provide an email and 
	 * password to use this function.  Instead you can provide the userid
	 * (string of numbers) for the user as this information is made
	 * available through a public URL.
	 *
	 * To get the userid, log into Google Reader, go to your shared items,
	 * and then click "See your shared items page in a new window."  You'll
	 * see the numbers in the URL you are taken to.
	 * 
	 * @access public
	 * @param string $userid (optional) the userid to get the shared items for
	 * @return array of shared items
	 */
	function shared_items($userid = NULL) {
		$feed_xml = $this->shared_items_raw($userid);
		if ($this->errors_exist()) return array();
		return $this->_parse_entries($feed_xml);
	}

	/**
	 * Returns an array of starred items for the logged in user.  This function
	 * returns an cleaned up version of the data returned from Google Reader.
	 * If you'd prefer the raw XML, use starred_items_raw().
	 * 
	 * @access public
	 * @return array of starred items
	 */
	function starred_items() {
		$feed_xml = $this->starred_items_raw();
		if ($this->errors_exist()) return array();
		return $this->_parse_entries($feed_xml);
	}
	
	/**
	 * Returns an array of subscriptions for the logged in user.  This function
	 * returns an cleaned up version of the data returned from Google Reader.
	 * If you'd prefer the raw XML, use subscriptions_raw().
	 * 
	 * @access public
	 * @return array of subscriptions
	 */
	function subscriptions() {
		$subscriptions_xml = $this->subscriptions_raw();
		if ($this->errors_exist()) return array();
		return $this->_parse_subscriptions($subscriptions_xml);
	}
		
	/**
	 * The XML of all items for the logged in user.  If you prefer
	 * a cleaned up version of the data, use all_items().
	 * 
	 * @access public
	 * @return string of raw XML returned from the all items feed
	 */
	function all_items_raw() {
		$this->_clear_errors();
		
		$this->_load_token();
		if ($this->errors_exist()) return '';
		
		return $this->_fetch($this->feed_url . $this->reading_state);
	}
	
	/**
	 * The XML of shared items for the logged in user.  If you prefer
	 * a cleaned up version of the data, use shared_items().
	 *
	 * Unlike the other functions, you do not need to provide an email and 
	 * password to use this function.  Instead you can provide the userid
	 * (string of numbers) for the user as this information is made
	 * available through a public URL.
	 *
	 * To get the userid, log into Google Reader, go to your shared items,
	 * and then click "See your shared items page in a new window."  You'll
	 * see the numbers in the URL you are taken to.
	 * 
	 * @access public
	 * @param string $userid (optional) the userid to get the shared items for
	 * @return string of raw XML returned from the shared items feed
	 */
	function shared_items_raw($userid = NULL) {
		$this->_clear_errors();
		
		//If they provided a user id to use, switch out the URL to use the public feed instead
		$url = $this->feed_url . $this->shared_state;
		if ($userid != NULL) {
			$url = str_replace('/atom/user/-/', '/public/atom/user/' . $userid . '/', $this->feed_url . $this->shared_state);
		} else {
			$this->_load_token();
			if ($this->errors_exist()) return '';
		}
		return $this->_fetch($url);
	}
	
	/**
	 * The XML of starred items for the logged in user.  If you prefer
	 * a cleaned up version of the data, use starred_items().
	 * 
	 * @access public
	 * @return string of raw XML returned from the starred items feed
	 */
	function starred_items_raw() {
		$this->_clear_errors();
		
		$this->_load_token();
		if ($this->errors_exist()) return '';
		
		return $this->_fetch($this->feed_url . $this->starred_state);
	}
	
	/**
	 * The XML of subscriptions for the logged in user.  If you prefer
	 * a cleaned up version of the data, use subscriptions().
	 * 
	 * @access public
	 * @return string of raw XML returned from the subscriptions feed
	 */
	function subscriptions_raw() {
		$this->_clear_errors();
		
		$this->_load_token();
		if ($this->errors_exist()) return '';
		
		return $this->_fetch($this->subscriptions_url);
	}
	
	/**
	 * Marks an item as read.
	 * 
	 * @access public
	 * @param mixed $entry_id the item ID to mark as read
	 * @return true/false based on success of update
	 */
	function mark_item_read($entry_id) {
		$post_data = array('i' => $entry_id);
		
		$post_data['r'] = $this->unread_state;
		$post_data['a'] = $this->read_state;

		return $this->_edit_entry($post_data);
	}
	
	/**
	 * Marks an item as unread.
	 * 
	 * @access public
	 * @param mixed $entry_id the item ID to mark as unread
	 * @return true/false based on success of update
	 */
	function mark_item_unread($entry_id) {
		$post_data = array('i' => $entry_id);
		
		$post_data['r'] = $this->read_state;
		$post_data['a'] = $this->unread_state;

		return $this->_edit_entry($post_data);
	}
	
	/**
	 * Shares an item.
	 * 
	 * @access public
	 * @param mixed $entry_id the item ID to share
	 * @return true/false based on success of update
	 */
	function share_item($entry_id) {
		$post_data = array('i' => $entry_id);
		
		$post_data['a'] = $this->shared_state;

		return $this->_edit_entry($post_data);
	}
	
	/**
	 * Stars an item.
	 * 
	 * @access public
	 * @param mixed $entry_id the item ID to star
	 * @return true/false based on success of update
	 */
	function star_item($entry_id) {
		$post_data = array('i' => $entry_id);
		
		$post_data['a'] = $this->starred_state;

		return $this->_edit_entry($post_data);
	}
	
	/**
	 * Unshared an item.
	 * 
	 * @access public
	 * @param mixed $entry_id the item ID to unshare
	 * @return true/false based on success of update
	 */
	function unshare_item($entry_id) {
		$post_data = array('i' => $entry_id);
		
		$post_data['r'] = $this->shared_state;

		return $this->_edit_entry($post_data);
	}
	
	/**
	 * Unstars an item.
	 * 
	 * @access public
	 * @param mixed $entry_id the item ID to unstar
	 * @return true/false based on success of update
	 */
	function unstar_item($entry_id) {
		$post_data = array('i' => $entry_id);
		
		$post_data['r'] = $this->starred_state;

		return $this->_edit_entry($post_data);
	}
	
	/**
	 * Gets an array of any errors that have occured with the latest call.
	 *
	 * @access public
	 * @return array of errors {string => string}
	 */
	function errors() {
		return $this->_errors;
	}
	
	/**
	 * Checks to see if any errors have occured with the latest call.
	 * 
	 * @access public
	 * @return boolean
	 */
	function errors_exist() {
		return (count($this->_errors) > 0);
	}
	
	/**
	 * Gets a token from google to allow editing of data.  This function requires a
	 * logged in user.
	 * 
	 * @access private
	 * @return true/false based on success of getting token
	 */
	function _load_token() {
		if ($this->_token == NULL) {
			if (!isset($this->_email) || !isset($this->_password)) {
				$this->_set_error('READER_CREDENTIALS_NOT_PROVIDED', "User credentials were not provided.");
				return FALSE;
			}
			
			$post_data = array();
			$post_data['Email'] = $this->_email;
			$post_data['Passwd'] = $this->_password;
			$post_data['continue'] = 'http://www.google.com/';
			$post_data['accountType'] = 'HOSTED_OR_GOOGLE';
			$post_data['source'] = $this->source;
			$post_data['service'] = 'reader';
			
			$this->_channel = curl_init($this->login_url);
			curl_setopt($this->_channel, CURLOPT_POST, TRUE);
			curl_setopt($this->_channel, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($this->_channel, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($this->_channel, CURLOPT_RETURNTRANSFER, TRUE);
			$result = curl_exec($this->_channel);
			curl_close($this->_channel);
			
			if (strpos($result, 'BadAuthentication') !== FALSE) {
				$this->_set_error('READER_CREDENTIALS_INVALID', "User credentials were rejected by Google.");
				return FALSE;
			} else if (strpos($result, 'Auth') === FALSE) {
				$this->_set_error('TOKEN_ERROR', 'Unable to get token from google.');
				$this->_token = NULL;
				return FALSE;
			} else {
				$token_start = strpos($result, 'Auth=');
				$this->_token = substr($result, $token_start + 5);
				return TRUE;
			}
		}
	}
	
	/**
	 * Helper function to parse an XML string of feed entries into an
	 * array containing just the used data for easier consumption.  Returned
	 * array will contain a lastupdated value for the last time the feed
	 * was updated and an items value which is the array of items.
	 * 
	 * @access private
	 * @see _parse_entry
	 * @param string $entries_xml XML to parse
	 * @return array of entries
	 */
	function _parse_entries($entries_xml) {
		$feed_object = simplexml_load_string($entries_xml);
		$results = array();
		$feed_items = array();
		foreach ($feed_object->entry as $entry) {			
			$feed_items[] = $this->_parse_entry($entry);
		}
		$results['lastupdated'] = $this->_get_date($feed_object->updated);
		$feed_object = NULL;
		$results['items'] = $feed_items;
		return $results;
	}
	
	/**
	 * Given a SimpleXML representation of an entry from Google Reader
	 * this function will parse out the appropriate data into an array for
	 * easier consumption.
	 *
	 * Information returned is title, url, published, updated, and summary.
	 * 
	 * @access private
	 * @param mixed $entry SimpleXML representation of an entry
	 * @return array of data for an entry
	 */
	function _parse_entry($entry) {
		$item = array();
		$item['title'] = strval($entry->title);
		$item['url'] =  $entry->link['href'];
		$item['published'] = $this->_get_date($entry->published);
		$item['updated'] = $this->_get_date($entry->updated);
		$item['summary'] = strval($entry->summary);
		$item['id'] = strval($entry->id);
		return $item;
	}
	
	/**
	 * Helper function to parse an XML string of subscriptions into an
	 * array containing just the used data for easier consumption.
	 * 
	 * @access private
	 * @see _parse_subscription
	 * @param string $subscriptions_xml XML to parse
	 * @return array of subscriptions
	 */
	function _parse_subscriptions($subscriptions_xml) {
		$subscriptions_object = simplexml_load_string($subscriptions_xml);
		
		$items = array();
		foreach ($subscriptions_object->list->object as $feed) {
			$items[] = $this->_parse_subscription($feed);
		}
		$subscriptions_object = NULL;	
		
		return $items;
	}
	
	/**
	 * Given a SimpleXML representation of a subscription from Google Reader
	 * this function will parse out the appropriate data into an array for
	 * easier consumption.
	 *
	 * Information returned is title, url, and a sub-array of labels
	 * 
	 * @access private
	 * @param mixed $subscription SimpleXML representation of a subscription
	 * @return array of data for an entry
	 */
	function _parse_subscription($subscription) {
		$item = array();
		$item['title'] = strval($subscription->string[1]);
		$item['url'] = str_replace('feed/','', $subscription->string[0]);
		$item['labels'] = array();
		for ($i = 1; $i < count($subscription->list->object->string); $i = $i + 2) {
			$item['labels'][] = strval($subscription->list->object->string[$i]);
		}
		return $item;
	}
	
	/**
	 * Cleans up the date string returned by Google Reader into a PHP
	 * date-time object.  Expected format is YYYY-MM-DDTHH:MM:SSZ.
	 * 
	 * @access private
	 * @param string $date date as reported from Google Reader
	 * @return void
	 */
	function _get_date($date) {
		return strtotime(str_replace('Z', '', str_replace('T',' ', $date)));
	}
	
	/**
	 * Fetches a URL.  Will use the token for login if it is set.
	 * 
	 * @access private
	 * @param string $url URL to fetch
	 * @param array $post post data to include in the request
	 * @return string contents of URL
	 */
	function _fetch($url, $post = NULL) {
		$this->_channel = curl_init($url);
		curl_setopt($this->_channel, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->_channel, CURLOPT_FOLLOWLOCATION, TRUE);
		if ($this->_token != NULL) {
			curl_setopt($this->_channel, CURLOPT_HTTPHEADER, array('Authorization:GoogleLogin auth=' . $this->_token));
		}
		
		if ($post != NULL) {
			$post_string = '';
			foreach($post as $key=>$value) { 
				$post_string .= $key . '=' . $value . '&'; 
			}
			$post_string = substr($post_string, 0, -1);
			curl_setopt($this->_channel, CURLOPT_POST, TRUE);
			curl_setopt($this->_channel, CURLOPT_POSTFIELDS, $post_string);
		}
		$result = curl_exec($this->_channel);
		
		curl_close($this->_channel);

		return $result;
	}
	
	/**
	 * Edits the tags for an item.  Assumes that the parameter contains
	 * the tags to add/remove from an item and the item ID.
	 *
	 * The tag to add should be under the 'a' key, and to remove should
	 * be under the 'r' key.  They should be one of the states defined 
	 * at the top of the file.
	 * 
	 * @access private
	 * @param mixed $post_data
	 * @return true/false based up ability to update item
	 */	
	function _edit_entry($post_data) {
		$this->_clear_errors();
		
		if (!$this->_load_token()) return;
		
		$post_data['ac'] = 'edit-tags';
		$post_data['T'] = $this->_token;
		
		if ($this->_fetch($this->edit_url, $post_data) != 'OK') {
			if (isset($post_data['i'])) {
				$this->_set_error('edit_error', 'There was an unknown error while editing the item ' . $post_data['i'] . '.');
			} else {
				$this->_set_error('edit_error', 'Item id not provided for editing.');
			}
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	/**
	 * Sets an error for the current call.
	 * 
	 * @access private
	 * @param string $name		key for the error
	 * @param string $message	error description
	 * @return void
	 */
	function _set_error($name, $message) {
		$this->_errors[$name] = $message;
	}
	
	/**
	 * Clears all errors.
	 * 
	 * @access private
	 * @return void
	 */
	function _clear_errors() {
		$this->_errors = array();
	}
}
?>