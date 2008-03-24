<?php
/**
 * layout articles for manual review
 *
 * @see articles/articles.php
 * @see articles/review.php
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @author GnapZ
 * @author Thierry Pinelli (ThierryP)
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */
Class Layout_articles_as_review extends Layout_interface {

	/**
	 * list articles for manual review
	 *
	 * @param resource the SQL result
	 * @return string the rendered text
	 *
	 * @see skins/layout.php
	**/
	function &layout(&$result) {
		global $context;

		// we return an array of ($url => $attributes)
		$items = array();

		// empty list
		if(!SQL::count($result))
			return $items;

		// load localized strings
		i18n::bind('articles');

		// flag articles updated recently
		if($context['site_revisit_after'] < 1)
			$context['site_revisit_after'] = 2;
		$dead_line = gmstrftime('%Y-%m-%d %H:%M:%S', mktime(0,0,0,date("m"),date("d")-$context['site_revisit_after'],date("Y")));
		$now = gmstrftime('%Y-%m-%d %H:%M:%S');

		// process all items in the list
		include_once $context['path_to_root'].'comments/comments.php';
		include_once $context['path_to_root'].'files/files.php';
		include_once $context['path_to_root'].'links/links.php';
		include_once $context['path_to_root'].'overlays/overlay.php';
		while($item =& SQL::fetch($result)) {

			// get the related overlay, if any
			$overlay = Overlay::load($item);

			// get the anchor
			$anchor = Anchors::get($item['anchor']);

			// the url to view this item
			$url = Articles::get_url($item['id'], 'view', $item['title'], $item['nick_name']);

			// reset the rendering engine between items
			Codes::initialize($url);

			// use the title to label the link
			if(is_object($overlay) && is_callable(array($overlay, 'get_live_title')))
				$title = $overlay->get_live_title($item);
			else
				$title = ucfirst(Codes::strip(strip_tags($item['title'], '<br><div><img><p><span>')));

			// initialize variables
			$prefix = $suffix = '';

			// flag sticky pages
			if($item['rank'] < 10000)
				$prefix .= STICKY_FLAG;

			// flag articles that are dead, or created or updated very recently
			if(($item['expiry_date'] > NULL_DATE) && ($item['expiry_date'] <= $now))
				$prefix .= EXPIRED_FLAG;
			elseif($item['create_date'] >= $dead_line)
				$suffix .= NEW_FLAG;
			elseif($item['edit_date'] >= $dead_line)
				$suffix .= UPDATED_FLAG;

			// signal articles to be published
			if(($item['publish_date'] <= NULL_DATE) || ($item['publish_date'] > gmstrftime('%Y-%m-%d %H:%M:%S')))
				$prefix .= DRAFT_FLAG;

			// signal locked articles
			if(isset($item['locked']) && ($item['locked'] == 'Y'))
				$prefix .= LOCKED_FLAG;

			// signal restricted and private articles
			if($item['active'] == 'N')
				$prefix .= PRIVATE_FLAG;
			elseif($item['active'] == 'R')
				$prefix .= RESTRICTED_FLAG;

			// details
			$details = array();

			// the author(s)
			if($item['create_name'] != $item['edit_name'])
				$details[] = sprintf(i18n::s('by %s, %s'), $item['create_name'], $item['edit_name']);
			else
				$details[] = sprintf(i18n::s('by %s'), $item['create_name']);

			// the last action
			$details[] = get_action_label($item['edit_action']).' '.Skin::build_date($item['edit_date']);

			// the number of hits
			if(Surfer::is_logged() && ($item['hits'] > 1))
				$details[] = sprintf(i18n::s('%d&nbsp;hits'), $item['hits']);

			// info on related files
			if($count = Files::count_for_anchor('article:'.$item['id'], TRUE))
				$details[] = sprintf(i18n::ns('1&nbsp;file', '%d&nbsp;files', $count), $count);

			// info on related links
			if($count = Links::count_for_anchor('article:'.$item['id'], TRUE))
				$details[] = sprintf(i18n::ns('1&nbsp;link', '%d&nbsp;links', $count), $count);

			// info on related comments
			if($count = Comments::count_for_anchor('article:'.$item['id'], TRUE))
				$details[] = sprintf(i18n::ns('1&nbsp;comment', '%d&nbsp;comments', $count), $count);

			// append details to the suffix
			$suffix .= ' -&nbsp;<span class="details">'.ucfirst(trim(implode(', ', $details))).'</span>';

			// commands to review the article
			$menu = array();

			// read the page
			$menu = array_merge($menu, array($url => i18n::s('Read')));

			// validate the page
			$menu = array_merge($menu, array('articles/stamp.php?id='.$item['id'].'&amp;confirm=review' => i18n::s('Validate')));

			// add a menu
			$suffix .= ' '.Skin::build_list($menu, 'menu');

			// list all components for this item
			$items[$url] = array($prefix, $title, $suffix, 'basic', NULL);

		}

		// end of processing
		SQL::free($result);
		return $items;
	}

}

?>