<?php
/**
 * the database abstraction layer for images
 *
 * Images are saved into the file system of the web server. Each image also has a related record in the database.
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @author Florent
 * @author GnapZ
 * @author Christophe Battarel [email]christophe.battarel@altairis.fr[/email]
 * @tester Guillaume Perez
 * @tester Anatoly
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

Class Images {

	/**
	 * check if new images can be added
	 *
	 * This function returns TRUE if images can be added to some place,
	 * and FALSE otherwise.
	 *
	 * The function prevents the creation of new images when:
	 * - surfer cannot upload
	 * - the global parameter 'users_without_submission' has been set to 'Y'
	 * - provided item has been locked
	 * - item has some option 'no_images' that prevents new images
	 * - the anchor has some option 'no_images' that prevents new images
	 *
	 * Then the function allows for new images when:
	 * - surfer has been authenticated as a valid member
	 * - or parameter 'users_without_teasers' has not been set to 'Y'
	 *
	 * Then, ultimately, the default is not allow for the creation of new
	 * images.
	 *
	 * @param object an instance of the Anchor interface, if any
	 * @param array a set of item attributes, if any
	 * @return TRUE or FALSE
	 */
	function are_allowed($anchor=NULL, $item=NULL) {
		global $context;

		// images are prevented in anchor
		if(is_object($anchor) && is_callable(array($anchor, 'has_option')) && $anchor->has_option('no_images'))
			return FALSE;

		// images are prevented in item
		if(isset($item['options']) && is_string($item['options']) && preg_match('/\bno_images\b/i', $item['options']))
			return FALSE;

		// surfer is not allowed to upload a file
		if(!Surfer::may_upload())
			return FALSE;

		// surfer is an associate
		if(Surfer::is_associate())
			return TRUE;

		// submissions have been disallowed
		if(isset($context['users_without_submission']) && ($context['users_without_submission'] == 'Y'))
			return FALSE;

		// item has been locked -- we do not care about the anchor
		if(isset($item['locked']) && is_string($item['locked']) && ($item['locked'] == 'Y'))
			return FALSE;

		// surfer has special privileges
		if(Surfer::is_empowered())
			return TRUE;

		// surfer screening
		if(isset($item['active']) && ($item['active'] == 'N') && !Surfer::is_empowered())
			return FALSE;
		if(isset($item['active']) && ($item['active'] == 'R') && !Surfer::is_logged())
			return FALSE;

		// authenticated members can always add images
		if(Surfer::is_member())
			return TRUE;

		// anonymous contributions are allowed for this anchor
		if(is_object($anchor) && $anchor->is_editable())
			return TRUE;

		// anonymous contributions are allowed for this section
		if(isset($item['content_options']) && preg_match('/\banonymous_edit\b/i', $item['content_options']))
			return TRUE;

		// anonymous contributions are allowed for this item
		if(isset($item['options']) && preg_match('/\banonymous_edit\b/i', $item['options']))
			return TRUE;

		// teasers are activated
		if(!isset($context['users_without_teasers']) || ($context['users_without_teasers'] != 'Y'))
			return TRUE;

		// the default is to not allow for new images
		return FALSE;
	}

	/**
	 * delete one image in the database and in the file system
	 *
	 * @param int the id of the image to delete
	 * @return boolean TRUE on success, FALSE otherwise
	 */
	function delete($id) {
		global $context;

		// load the row
		$item =& Images::get($id);
		if(!$item['id']) {
			Skin::error(i18n::s('No item has been found.'));
			return FALSE;
		}

		// delete the image files silently
		list($anchor_type, $anchor_id) = explode(':', $item['anchor'], 2);
		Safe::unlink($context['path_to_root'].'images/'.$context['virtual_path'].$anchor_type.'/'.$anchor_id.'/'.$item['image_name']);
		Safe::unlink($context['path_to_root'].'images/'.$context['virtual_path'].$anchor_type.'/'.$anchor_id.'/'.$item['thumbnail_name']);
		Safe::rmdir($context['path_to_root'].'images/'.$context['virtual_path'].$anchor_type.'/'.$anchor_id.'/thumbs');
		Safe::rmdir($context['path_to_root'].'images/'.$context['virtual_path'].$anchor_type.'/'.$anchor_id);
		Safe::rmdir($context['path_to_root'].'images/'.$context['virtual_path'].$anchor_type);

		// delete related items
		Anchors::delete_related_to('image:'.$id);

		// delete the record in the database
		$query = "DELETE FROM ".SQL::table_name('images')." WHERE id = ".SQL::escape($item['id']);
		if(SQL::query($query) === FALSE)
			return FALSE;

		// clear the cache for images
		Cache::clear(array('images', 'image:'.$item['id']));

		// job done
		return TRUE;
	}

	/**
	 * delete all images for a given anchor
	 *
	 * @param the anchor to check
	 *
	 * @see shared/anchors.php
	 */
	function delete_for_anchor($anchor) {
		global $context;

		// seek all records attached to this anchor
		$query = "SELECT id FROM ".SQL::table_name('images')." AS images "
			." WHERE images.anchor LIKE '".SQL::escape($anchor)."'";
		if(!$result =& SQL::query($query))
			return;

		// delete silently all matching images
		while($row =& SQL::fetch($result))
			Images::delete($row['id']);
	}

	/**
	 * duplicate all images for a given anchor
	 *
	 * This function duplicates records in the database, and changes anchors
	 * to attach new records as per second parameter.
	 *
	 * @param string the source anchor
	 * @param string the target anchor
	 * @return int the number of duplicated records
	 *
	 * @see shared/anchors.php
	 */
	function duplicate_for_anchor($anchor_from, $anchor_to) {
		global $context;

		// look for records attached to this anchor
		$count = 0;
		$query = "SELECT * FROM ".SQL::table_name('images')." WHERE anchor LIKE '".SQL::escape($anchor_from)."'";
		if(($result =& SQL::query($query)) && SQL::count($result)) {

			// create target folders
			$file_path = 'images/'.$context['virtual_path'].str_replace(':', '/', $anchor_to);
			if(!Safe::make_path($file_path.'/thumbs'))
				Skin::error(sprintf(i18n::s('Impossible to create path %s.'), $file_path.'/thumbs'));

			$file_path = $context['path_to_root'].$file_path.'/';

			// the list of transcoded strings
			$transcoded = array();

			// process all matching records one at a time
			while($item =& SQL::fetch($result)) {

				// duplicate image file
				if(!copy($context['path_to_root'].'images/'.$context['virtual_path'].str_replace(':', '/', $anchor_from).'/'.$item['image_name'],
					$file_path.$item['image_name'])) {
					Skin::error(sprintf(i18n::s('Impossible to copy file %s.'), $item['image_name']));
					continue;
				}

				// this will be filtered by umask anyway
				Safe::chmod($file_path.$item['image_name'], $context['file_mask']);

				// copy the thumbnail as well
				Safe::copy($context['path_to_root'].'images/'.$context['virtual_path'].str_replace(':', '/', $anchor_from).'/'.$item['thumbnail_name'],
					$file_path.$item['thumbnail_name']);

				// this will be filtered by umask anyway
				Safe::chmod($file_path.$item['thumbnail_name'], $context['file_mask']);

				// a new id will be allocated
				$old_id = $item['id'];
				unset($item['id']);

				// target anchor
				$item['anchor'] = $anchor_to;

				// actual duplication
				if($new_id = Images::post($item)) {

					// more pairs of strings to transcode --no automatic solution for [images=...]
					$transcoded[] = array('/\[image='.preg_quote($old_id, '/').'/i', '[image='.$new_id);

					// duplicate elements related to this item
					Anchors::duplicate_related_to('image:'.$old_id, 'image:'.$new_id);

					// stats
					$count++;
				}
			}

			// transcode in anchor
			if($anchor = Anchors::get($anchor_to))
				$anchor->transcode($transcoded);

		}

		// number of duplicated records
		return $count;
	}

	/**
	 * get one image by id
	 *
	 * @param int the id of the image
	 * @return the resulting $item array, with at least keys: 'id', 'title', etc.
	 */
	function &get($id) {
		global $context;

		// sanity check
		if(!$id) {
			$output = NULL;
			return $output;
		}

		// select among available items -- exact match
		$query = "SELECT * FROM ".SQL::table_name('images')." AS images "
			." WHERE (images.id LIKE '".SQL::escape($id)."')";

		$output =& SQL::query_first($query);
		return $output;
	}

	/**
	 * get one image by anchor and name
	 *
	 * @param string the anchor
	 * @param string the image name
	 * @return the resulting $item array, with at least keys: 'id', 'title', etc.
	 */
	function &get_by_anchor_and_name($anchor, $name) {
		global $context;

		// select among available items
		$query = "SELECT * FROM ".SQL::table_name('images')." AS images "
			." WHERE images.anchor LIKE '".SQL::escape($anchor)."' AND images.image_name='".SQL::escape($name)."'";

		$output =& SQL::query_first($query);
		return $output;
	}

	/**
	 * get the web address of an image
	 *
	 * @param array the image description (i.e., Images::get($id))
	 * @return string the target href, or FALSE if there is an error
	 */
	function get_icon_href($item) {
		global $context;

		// sanity check
		if(!isset($item['anchor']) || !isset($item['image_name']))
			return FALSE;

		// target name
		$target = 'images/'.$context['virtual_path'].str_replace(':', '/', $item['anchor']).'/'.$item['image_name'];

		// file does not exist
		if(!file_exists($context['path_to_root'].$target))
			return FALSE;

		// web address of the image
		return $context['url_to_root'].$target;
	}

	/**
	 * get the web address of one thumbnail
	 *
	 * @param array the image description (i.e., Images::get($id))
	 * @return string the thumbnail href, or FALSE on error
	 */
	function get_thumbnail_href($item) {
		global $context;

		// sanity check
		if(!isset($item['anchor']) || !isset($item['thumbnail_name']))
			return FALSE;

		// target file name
		$target = 'images/'.$context['virtual_path'].str_replace(':', '/', $item['anchor']).'/'.$item['thumbnail_name'];

		// file does not exist
		if(!file_exists($context['path_to_root'].$target))
			return FALSE;

		// returns href
		return $context['url_to_root'].$target;

	}

	/**
	 * build a reference to a image
	 *
	 * Depending on parameter '[code]with_friendly_urls[/code]' and on action,
	 * following results can be observed:
	 *
	 * - view - images/view.php?id=123 or images/view.php/123 or image-123
	 *
	 * - other - images/edit.php?id=123 or images/edit.php/123 or image-edit/123
	 *
	 * @param int the id of the image to handle
	 * @param string the expected action ('view', 'print', 'edit', 'delete', ...)
	 * @return string a normalized reference
	 *
	 * @see control/configure.php
	 */
	function get_url($id, $action='view') {
		global $context;

		// check the target action
		if(!preg_match('/^(delete|edit|set_as_bullet|set_as_icon|set_as_thumbnail|view)$/', $action))
			$action = 'view';

		// normalize the link
		return normalize_url(array('images', 'image'), $action, $id);
	}

	/**
	 * list newest images
	 *
	 * To build a simple box of the newest images in your main index page, just use
	 * the following example:
	 * [php]
	 * // side bar with the list of most recent images
	 * include_once 'images/images.php';
	 * $items = Images::list_by_date(0, 10, '');
	 * $text = Skin::build_list($items, 'compact');
	 * $context['text'] .= Skin::build_box($title, $text, 'navigation');
	 * [/php]
	 *
	 * You can also display the newest image separately, using [code]Images::get_newest()[/code]
	 * In this case, skip the very first image in the list by using
	 * [code]Images::list_by_date(1, 10, '')[/code]
	 *
	 * @param int the offset from the start of the list; usually, 0 or 1
	 * @param int the number of items to display
	 * @param string the list variant, if any
	 * @return NULL on error, else an ordered array with $url => ($prefix, $label, $suffix, $icon)
	 * @see images/images.php#list_selected for $variant description
	 */
	function &list_by_date($offset=0, $count=10, $variant='full') {
		global $context;

		// limit the scope of the request
		$query = "SELECT * FROM ".SQL::table_name('images')." AS images"
			." ORDER BY images.edit_date DESC, images.title LIMIT ".$offset.','.$count;

		// the list of images
		$output =& Images::list_selected(SQL::query($query), $variant);
		return $output;
	}

	/**
	 * list newest images for one anchor
	 *
	 * Example:
	 * [php]
	 * include_once 'images/images.php';
	 * $items = Images::list_by_date_for_anchor('section:12', 0, 10);
	 * $context['text'] .= Skin::build_list($items, 'compact');
	 * [/code]
	 *
	 * @param string the anchor (e.g., 'article:123')
	 * @param int the offset from the start of the list; usually, 0 or 1
	 * @param int the number of items to display
	 * @param string the list variant, if any
	 * @return NULL on error, else an ordered array with $url => ($prefix, $label, $suffix, $icon)
	 * @see images/images.php#list_selected for $variant description
	 */
	function &list_by_date_for_anchor($anchor, $offset=0, $count=20, $variant=NULL) {
		global $context;

		// use the anchor itself as the default variant
		if(!$variant)
			$variant = $anchor;

		// the request
		$query = "SELECT * FROM ".SQL::table_name('images')." AS images "
			." WHERE (images.anchor LIKE '".SQL::escape($anchor)."') "
			." ORDER BY images.edit_date DESC, images.title LIMIT ".$offset.','.$count;

		// the list of images
		$output =& Images::list_selected(SQL::query($query), $variant);
		return $output;
	}

	/**
	 * list newest images for one author
	 *
	 * Example:
	 * [code]
	 * include_once 'images/images.php';
	 * $items = Images::list_by_date_for_author(12, 0, 10);
	 * $context['text'] .= Skin::build_list($items, 'compact');
	 * [/code]
	 *
	 * @param int the id of the author of the image
	 * @param int the offset from the start of the list; usually, 0 or 1
	 * @param int the number of items to display
	 * @param string the list variant, if any
	 * @return NULL on error, else an ordered array with $url => ($prefix, $label, $suffix, $icon)
	 * @see images/images.php#list_selected for $variant description
	 */
	function &list_by_date_for_author($author_id, $offset=0, $count=20, $variant='date') {
		global $context;

		// limit the scope of the request
		$query = "SELECT * FROM ".SQL::table_name('images')." AS images "
			." WHERE (images.edit_id LIKE '".SQL::escape($author_id)."') "
			." ORDER BY images.edit_date DESC, images.title LIMIT ".$offset.','.$count;

		// the list of images
		$output =& Images::list_selected(SQL::query($query), $variant);
		return $output;
	}

	/**
	 * list biggest images
	 *
	 * @param int the offset from the start of the list; usually, 0 or 1
	 * @param int the number of items to display
	 * @param string the list variant, if any
	 * @return NULL on error, else an ordered array with $url => ($prefix, $label, $suffix, $icon)
	 */
	function &list_by_size($offset=0, $count=10, $variant='full') {
		global $context;

		// limit the scope of the request
		$query = "SELECT * FROM ".SQL::table_name('images')." AS images"
			." ORDER BY images.image_size DESC, images.title LIMIT ".$offset.','.$count;

		// the list of images
		$output =& Images::list_selected(SQL::query($query), $variant);
		return $output;
	}

	/**
	 * list selected images
	 *
	 * Accept following variants:
	 * - 'compact' - to build short lists in boxes and sidebars (this is the default)
	 * - 'no_anchor' - to build detailed lists in an anchor page
	 * - 'full' - include anchor information
	 *
	 * @param resource result of database query
	 * @param string 'full', etc or object, i.e., an instance of Layout_Interface
	 * @return NULL on error, else an ordered array with $url => ($prefix, $label, $suffix, $icon)
	 */
	function &list_selected(&$result, $layout='compact') {
		global $context;

		// no result
		if(!$result) {
			$output = NULL;
			return $output;
		}

		// special layouts
		if(is_object($layout)) {
			$output =& $layout->layout($result);
			return $output;
		}

		// one of regular layouts
		switch($layout) {

		case 'compact':
			include_once $context['path_to_root'].'images/layout_images_as_compact.php';
			$variant =& new Layout_images_as_compact();
			$output =& $variant->layout($result);
			return $output;

		case 'dates':
			include_once $context['path_to_root'].'images/layout_images_as_dates.php';
			$variant =& new Layout_images_as_dates();
			$output =& $variant->layout($result);
			return $output;

		case 'feeds':
			include_once $context['path_to_root'].'images/layout_images_as_feed.php';
			$variant =& new Layout_images_as_feed();
			$output =& $variant->layout($result);
			return $output;

		case 'hits':
			include_once $context['path_to_root'].'images/layout_images_as_hits.php';
			$variant =& new Layout_images_as_hits();
			$output =& $variant->layout($result);
			return $output;

		case 'raw':
			include_once $context['path_to_root'].'images/layout_images_as_raw.php';
			$variant =& new Layout_images_as_raw();
			$output =& $variant->layout($result);
			return $output;

		default:
			include_once $context['path_to_root'].'images/layout_images.php';
			$variant =& new Layout_images();
			$output =& $variant->layout($result, $layout);
			return $output;

		}

	}

	/**
	 * post a new image or an updated image
	 *
	 * Accept following situations:
	 * - id+image: update an existing entry in the database
	 * - id+no image: only update the database
	 * - no id+image: create a new entry in the database
	 * - no id+no image: create a new entry in the database
	 *
	 * This function populates the error context, where applicable.
	 *
	 * @param array an array of fields
	 * @return the id of the image, or FALSE on error
	**/
	function post($fields) {
		global $context;

		// no anchor reference
		if(!isset($fields['anchor']) || !$fields['anchor']) {
			Skin::error(i18n::s('No anchor has been found.'));
			return FALSE;
		}

		// get the anchor
		if(!$anchor = Anchors::get($fields['anchor'])) {
			Skin::error(i18n::s('No anchor has been found.'));
			return FALSE;
		}

		// set default values
		if(!isset($fields['use_thumbnail']) || !Surfer::is_empowered())
			$fields['use_thumbnail'] = 'Y'; 	// only associates can select to not moderate image sizes

		// set default values for this editor
		$fields = Surfer::check_default_editor($fields);

		// update the existing record
		if(isset($fields['id'])) {

			// id cannot be empty
			if(!isset($fields['id']) || !is_numeric($fields['id'])) {
				Skin::error(i18n::s('No item has the provided id.'));
				return FALSE;
			}

			$query = "UPDATE ".SQL::table_name('images')." SET ";
			if(isset($fields['image_name']) && ($fields['image_name'] != 'none')) {
				$query .= "image_name='".SQL::escape($fields['image_name'])."',"
					."thumbnail_name='".SQL::escape($fields['thumbnail_name'])."',"
					."image_size='".SQL::escape($fields['image_size'])."',"
					."edit_name='".SQL::escape($fields['edit_name'])."',"
					."edit_id='".SQL::escape($fields['edit_id'])."',"
					."edit_address='".SQL::escape($fields['edit_address'])."',"
					."edit_date='".SQL::escape($fields['edit_date'])."',";
			}
			$query .= "title='".SQL::escape(isset($fields['title']) ? $fields['title'] : '')."',"
				."use_thumbnail='".SQL::escape($fields['use_thumbnail'])."',"
				."description='".SQL::escape(isset($fields['description']) ? $fields['description'] : '')."',"
				."source='".SQL::escape(isset($fields['source']) ? $fields['source'] : '')."',"
				."link_url='".SQL::escape(isset($fields['link_url']) ? $fields['link_url'] : '')."'"
				." WHERE id = ".SQL::escape($fields['id']);

			// actual update
			if(SQL::query($query) === FALSE)
				return FALSE;

			// remember the id of the updated item
			$id = $fields['id'];

		// insert a new record
		} elseif(isset($fields['image_name']) && $fields['image_name'] && isset($fields['image_size']) && $fields['image_size']) {

			$query = "INSERT INTO ".SQL::table_name('images')." SET ";
			$query .= "anchor='".SQL::escape($fields['anchor'])."',"
				."image_name='".SQL::escape($fields['image_name'])."',"
				."image_size='".SQL::escape($fields['image_size'])."',"
				."title='".SQL::escape(isset($fields['title']) ? $fields['title'] : '')."',"
				."use_thumbnail='".SQL::escape($fields['use_thumbnail'])."',"
				."description='".SQL::escape(isset($fields['description']) ? $fields['description'] : '')."',"
				."source='".SQL::escape(isset($fields['source']) ? $fields['source'] : '')."',"
				."thumbnail_name='".SQL::escape(isset($fields['thumbnail_name']) ? $fields['thumbnail_name'] : '')."',"
				."link_url='".SQL::escape(isset($fields['link_url']) ? $fields['link_url'] : '')."',"
				."edit_name='".SQL::escape($fields['edit_name'])."',"
				."edit_id='".SQL::escape($fields['edit_id'])."',"
				."edit_address='".SQL::escape($fields['edit_address'])."',"
				."edit_date='".SQL::escape($fields['edit_date'])."'";

			// actual update
			if(SQL::query($query) === FALSE)
				return FALSE;

			// remember the id of the new item
			$id = SQL::get_last_id($context['connection']);

		// nothing done
		} else {
			Skin::error(i18n::s('No image has been uploaded.'));
			return FALSE;
		}

		// clear the cache, the image can appear anywhere
		Cache::clear();

		// end of job
		return $id;
	}

	/**
	 * create or alter tables for images
	 */
	function setup() {
		global $context;

		$fields = array();
		$fields['id']			= "MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT";
		$fields['anchor']		= "VARCHAR(64) DEFAULT 'section:1' NOT NULL";				// up to 64 chars
		$fields['image_name']	= "VARCHAR(255) DEFAULT '' NOT NULL";						// up to 255 chars
		$fields['image_size']	= "INTEGER DEFAULT '0' NOT NULL";
		$fields['title']		= "VARCHAR(255) DEFAULT '' NOT NULL";						// up to 255 chars
		$fields['description']	= "TEXT NOT NULL";											// up to 64k chars
		$fields['source']		= "VARCHAR(255) DEFAULT '' NOT NULL";						// up to 255 chars
		$fields['thumbnail_name']= "VARCHAR(255) DEFAULT '' NOT NULL";						// up to 255 chars
		$fields['link_url'] 	= "VARCHAR(255) DEFAULT '' NOT NULL";						// up to 255 chars
		$fields['use_thumbnail']= "ENUM('A', 'Y','N') DEFAULT 'Y' NOT NULL";				// Always, Yes or No
		$fields['edit_name']	= "VARCHAR(128) DEFAULT '' NOT NULL";						// item modification
		$fields['edit_id']		= "MEDIUMINT DEFAULT '0' NOT NULL";
		$fields['edit_address'] = "VARCHAR(128) DEFAULT '' NOT NULL";
		$fields['edit_date']	= "DATETIME";

		$indexes = array();
		$indexes['PRIMARY KEY'] 	= "(id)";
		$indexes['INDEX anchor']	= "(anchor)";
		$indexes['INDEX title'] 	= "(title(255))";
		$indexes['INDEX image_size']= "(image_size)";
		$indexes['INDEX edit_name'] = "(edit_name)";
		$indexes['INDEX edit_id']	= "(edit_id)";
		$indexes['INDEX edit_date'] = "(edit_date)";
		$indexes['FULLTEXT INDEX']	= "full_text(title, source, description)";

		return SQL::setup_table('images', $fields, $indexes);
	}

	/**
	 * get some statistics
	 *
	 * @return the resulting ($count, $oldest_date, $newest_date, $total_size) array
	 */
	function &stat() {
		global $context;

		// select among available items
		$query = "SELECT COUNT(*) as count, MIN(edit_date) as oldest_date, MAX(edit_date) as newest_date"
			.", SUM(image_size) as total_size"
			." FROM ".SQL::table_name('images')." AS images";

		$output =& SQL::query_first($query);
		return $output;
	}

	/**
	 * get some statistics for one anchor
	 *
	 * @param the selected anchor (e.g., 'article:12')
	 * @return the resulting ($count, $oldest_date, $newest_date, $total_size) array
	 */
	function &stat_for_anchor($anchor) {
		global $context;

		// select among available items
		$query = "SELECT COUNT(*) as count, MIN(edit_date) as oldest_date, MAX(edit_date) as newest_date"
			.", SUM(image_size) as total_size"
			." FROM ".SQL::table_name('images')." AS images "
			." WHERE images.anchor LIKE '".SQL::escape($anchor)."'";

		$output =& SQL::query_first($query);
		return $output;
	}

}

// ensure this library has been fully localized
i18n::bind('images');

?>