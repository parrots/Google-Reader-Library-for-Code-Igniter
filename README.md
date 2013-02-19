#Google Reader Library for CodeIgniter
**License:** BSD License
**Version:** 1.2.2 (2010-10-25)

Google Reader is a popular RSS feed reader, and this library allows you to tap into the functionality exposed by their API without having to get your hands too dirty. It supports two methods of calling: anonymous and authenticated. Most API calls (like getting a list of starred items for a user) require that you log in, but not all calls require that. This is detailed below in the how-to.

In addition this library can parse the XML returned from the Google Reader API for you into nicely formatted objects for easier consumption. If you prefer to do your own dirty work, no worries, the raw XML is also available.

##Using the Library — Anonymous Calls
Well, more *call* than *calls*.

Google Reader allows anyone to access a user's shared items feed, assuming you know their userid. Please note this is not their email address, it is a unique set of numbers used to identify the user. To get the userid, log into Google Reader, go to your shared items, and then click "See your shared items page in a new window." You'll see the numbers in the URL you are taken to.

Once you have userid you can simply load the library in your code and then call the **shared_items** function. From there you can loop through the **items** within the returned array to see all the shared items:

	$this->load->library('reader');
	$shared_items = $this->reader->shared_items('12006118737470781753');

	foreach ($shared_items['items'] as $entry) {
		echo $entry['title'];
	}

##Using the Library — Get User Feeds
The majority of calls to Google Reader require you to be logged in. Once the library is loaded you call the **initialize** function with your login information. You could also provide the array of login information when loading the library as a second parameter, assuming you are auto-loading the library.

	$this->load->library('reader');
	$credentials = array('email' => 'me@gmail.com', 'password' => 'mypassword');
	$this->reader->initialize($credentials);

From there all API calls are available to you. With regards to items from feeds this includes **shared_items**, **starred_items**, and **all_items**. All three are called in the same way, and return data formatted the same.

	$this->load->library('reader');
	$credentials = array('email' => 'me@gmail.com', 'password' => 'mypassword');
	$this->reader->initialize($credentials);
	
	$shared_items = $this->reader->shared_items();
	foreach ($shared_items['items'] as $entry) {
		echo $entry['title'];
	}
	
	$starred_items = $this->reader->starred_items();
	foreach ($starred_items['items'] as $entry) {
		echo $entry['title'];
	}
	
	$all_items = $this->reader->all_items();
	foreach ($all_items['items'] as $entry) {
		echo $entry['title'];
	}

In addition to items from a user's feed, logged in users have access to a user's list of subscriptions by calling the aptly named function **subscriptions**.

	$this->load->library('reader');
	$credentials = array('email' => 'me@gmail.com', 'password' => 'mypassword');
	$this->reader->initialize($credentials);
	
	$subscriptions = $this->reader->subscriptions();
	
	foreach ($subscriptions as $subscription) {
		echo $subscription['title'];
	}

##Using the Library — Feed and Subscription Objects
The default methods in the library will return objects that are easier to handle than the raw XML returned by Google Reader. If you'd like the raw XML, simply call the appropriate _raw function (ex. **shared_items_raw** instead of **shared_items**).

For functions that return feed item data the returned array will be an array containing a **lastupdated** item that is a timestamp of the last time the feed was updated and an **items** array containing all the feed items. A simple example of the returned data looks like this:

	{'lastupdated' => 1240191453, //timestamp
	  'items' => {
	    {'title' => 'Item Title',
	      'id' => 'tag:google.com,2005:reader/item/d061bdc3110c5f38',
	      'url' => 'http://www.example.com',
	      'published' => 1240191453, //timestamp
	      'updated' => 1240191453, //timestamp
	      'summary' => 'item summary'} , 
	    {'title' => 'Item Title 2',
	      'id' => 'tag:google.com,2005:reader/item/e192bdc4310e5f90',
	      'url' => 'http://www.example2.com',
	      'published' => 1240191453, //timestamp
	      'updated' => 1240191453, //timestamp
	      'summary' => 'item summary 2'}
	  }
	}

An example of the array returned by the **subscriptions** function looks like this:

	{ 
	  {'title' => 'Gizmodo',
	    'url' => 'http://gizmodo.com',
	    'labels' => {'technology','news'} },
	  {'title' => 'Kotaku',
	    'url' => 'http://kotaku.com',
	    'labels' => {'gaming','news'} }
	}

##Using the Library — Manipulating Items (Sharing, etc)
You can also use this library to modify the items in your feed. Once you are logged in you have the ability to share items, star items, and toggle their read status. You can do so with the following functions: **mark_item_read**, **mark_item_unread**, **share_item**, **star_item**, **unshare_item**, and **unstar_item**. All of these functions take the entry id as a parameter, which you can get by accessing the **id** key on any item returned from the other methods.

	$this->load->library('reader');
	$credentials = array('email' => 'me@gmail.com', 'password' => 'mypassword');
	$this->reader->initialize($credentials);
	
	$reading_list = $this->reader->all_items();
	foreach ($reading_list['items'] as $entry) {
		echo 'Marking item ' . $entry['title'] . ' as read';
		$this->reader->mark_item_read($entry['id']);
	}

##Using the Library — Using Notes To Share Other Pages
In addition to being able to share items that come up in your feeds, you can also create a note and attach a page to it. This will allow you to add pages to your shared items that didn't come up through Google Reader to being with. To do this use the **share_page** function. The function requires you to be logged in, and requires a valid full URL, the title of the page, and a note to attach to the shared item.

	$this->load->library('reader');
	$credentials = array('email' => 'me@gmail.com', 'password' => 'mypassword');
	$this->reader->initialize($credentials);
	
	$this->reader->share_page('http://stackoverflow.com', 'Stack Overflow', 'Has everyone else seen this site?  I love it!');

##Using the Library — Error Handling
The library has some basic error handling for when things go wrong. Instead of checking for NULL or FALSE on the results of a call, simply call the **errors_exist** function which returns TRUE/FALSE based on if the call was successful. If it returns true you can get the error(s) by calling the **errors** function to get an array of errors.

	$this->load->library('reader');
	$credentials = array('email' => 'me@gmail.com', 'password' => 'wrongpassword');
	$this->reader->initialize($credentials);
	
	$shared_items = $this->reader->shared_items();
	if ($this->reader->errors_exist()) {
		//oh no, errors!
		foreach ($this->reader->errors() as $key => $description) {
			echo 'Sorry there was an error: ' . $description;
		}
	} else {
		foreach ($shared_items['items'] as $entry) {
			echo $entry['title'];
		}
	}