<?php
/**
 * display one action in situation
 *
 * If several actions have been posted to a single anchor, a navigation bar will be built to jump
 * directly to previous and next neighbours.
 * This is displayed as a sidebar box in the extra panel.
 *
 * The extra panel also features top popular referrals in a sidebar box, if applicable.
 *
 * Access is granted only if the surfer is allowed to view the anchor page.
 *
 * Accept following invocations:
 * - view.php/12
 * - view.php?id=12
 *
 * If the anchor for this item specifies a specific skin (option keyword '[code]skin_xyz[/code]'),
 * or a specific variant (option keyword '[code]variant_xyz[/code]'), they are used instead default values.
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @tester GnapZ
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

// common definitions and initial processing
include_once '../shared/global.php';

// look for the id
$id = NULL;
if(isset($_REQUEST['id']))
	$id = $_REQUEST['id'];
elseif(isset($context['arguments'][0]))
	$id = $context['arguments'][0];
$id = strip_tags($id);

// get the item from the database
include_once 'actions.php';
$item =& Actions::get($id);

// get the related anchor, if any
$anchor = NULL;
if(isset($item['anchor']) && $item['anchor'])
	$anchor = Anchors::get($item['anchor']);

// the anchor has to be viewable by this surfer
if(!is_object($anchor) || $anchor->is_viewable())
	$permitted = TRUE;
else
	$permitted = FALSE;

// load localized strings
i18n::bind('actions');

// load the skin, maybe with a variant
load_skin('actions', $anchor);

// clear the tab we are in, if any
if(is_object($anchor))
	$context['current_focus'] = $anchor->get_focus();

// the path to this page
if(is_object($anchor) && $anchor->is_viewable())
	$context['path_bar'] = $anchor->get_path_bar();
else
	$context['path_bar'] = array( 'actions/' => i18n::s('Actions') );

// the title of the page
if($item['title'])
	$context['page_title'] = $item['title'];
else
	$context['page_title'] = i18n::s('View an action');

// back to the anchor page
if(is_object($anchor) && $anchor->is_viewable())
	$context['page_menu'] = array_merge($context['page_menu'], array( $anchor->get_url() => i18n::s('Go back to main page') ));

// the edit command is available to associates, editors, target member, and poster
if($item['id'] && (Surfer::is_associate()
	|| (is_object($anchor) && $anchor->is_editable())
	|| (Surfer::is_member() && ($item['anchor'] == 'user:'.Surfer::get_id()))
	|| Surfer::is_creator($item['create_id']))) {

	$context['page_menu'] = array_merge($context['page_menu'], array( Actions::get_url($item['id'], 'edit') => i18n::s('Edit') ));
}

// the delete command is available to associates, editors, target member, and poster
if($item['id'] && (Surfer::is_associate()
	|| (is_object($anchor) && $anchor->is_editable())
	|| (Surfer::is_member() && ($item['anchor'] == 'user:'.Surfer::get_id()))
	|| Surfer::is_creator($item['create_id']))) {

	$context['page_menu'] = array_merge($context['page_menu'], array( Actions::get_url($item['id'], 'delete') => i18n::s('Delete') ));
}

// not found -- help web crawlers
if(!isset($item['id'])) {
	Safe::header('Status: 404 Not Found', TRUE, 404);
	Skin::error(i18n::s('No item has the provided id.'));

// permission denied
} elseif(!$permitted) {

	// anonymous users are invited to log in or to register
	if(!Surfer::is_logged())
		Safe::redirect($context['url_to_home'].$context['url_to_root'].'users/login.php?url='.urlencode(Actions::get_url($item['id'])));

	// permission denied to authenticated user
	Safe::header('Status: 403 Forbidden', TRUE, 403);
	Skin::error(i18n::s('You are not allowed to perform this operation.'));

// display the action
} else {

	// initialize the rendering engine
	Codes::initialize(Actions::get_url($item['id']));

	// insert anchor prefix
	if(is_object($anchor))
		$context['text'] .= $anchor->get_prefix();

	// display the full text
	if($item['description']) {

		// beautify the text
		$text = Codes::beautify($item['description']);

		// show the description
		$context['text'] .= '<p></p>'.$text."<p></p>\n";
	}

	// action status
	switch($item['status']) {

	// on-going -- add buttons to complete or to reject
	case 'O':
		$context['text'] .= '<p>'.i18n::s('Action is on-going');

		// let action owner and associates change action status
		if(($item['anchor'] == 'user:'.Surfer::get_id()) || Surfer::is_associate()) {

			if(isset($context['with_friendly_urls']) && ($context['with_friendly_urls'] == 'Y'))
				$complete_link = 'actions/accept.php/'.$item['id'].'/completed';
			else
				$complete_link = 'actions/accept.php?id='.$item['id'].'&status=completed';

			if(isset($context['with_friendly_urls']) && ($context['with_friendly_urls'] == 'Y'))
				$reject_link = 'actions/accept.php/'.$item['id'].'/rejected';
			else
				$reject_link = 'actions/accept.php?id='.$item['id'].'&status=rejected';

			$context['text'] .= ' '.Skin::build_link($complete_link, i18n::s('Completed'), 'button')
				.' '.Skin::build_link($reject_link, i18n::s('Rejected'), 'button');
		}
		$context['text'] .= '</p>'."\n";
		break;

	// completed
	case 'C':
		$context['text'] .= '<p>'.i18n::s('Action has been completed');

		// let action owner and associates change action status
		if(($item['anchor'] == 'user:'.Surfer::get_id()) || Surfer::is_associate()) {

			if(isset($context['with_friendly_urls']) && ($context['with_friendly_urls'] == 'Y'))
				$reset_link = 'actions/accept.php/'.$item['id'].'/on-going';
			else
				$reset_link = 'actions/accept.php?id='.$item['id'].'&status=on-going';

			$context['text'] .= ' '.Skin::build_link($reset_link, i18n::s('Reset'), 'button');
		}
		$context['text'] .= '</p>'."\n";
		break;

	// rejected
	case 'R':
		$context['text'] .= '<p>'.i18n::s('Action has been rejected');

		// let action owner and associates change action status
		if(($item['anchor'] == 'user:'.Surfer::get_id()) || Surfer::is_associate()) {

			if(isset($context['with_friendly_urls']) && ($context['with_friendly_urls'] == 'Y'))
				$reset_link = 'actions/accept.php/'.$item['id'].'/on-going';
			else
				$reset_link = 'actions/accept.php?id='.$item['id'].'&status=on-going';

			$context['text'] .= ' '.Skin::build_link($reset_link, i18n::s('Reset'), 'button');
		}
		$context['text'] .= '</p>'."\n";
		break;

	}

	$details = array();

	// information to members
	if(Surfer::is_member()) {

		// action poster
		$details[] = sprintf(i18n::s('posted by %s %s'), Users::get_link($item['create_name'], $item['create_address'], $item['create_id']), Skin::build_date($item['create_date']));

		// last editor
		if($item['edit_name'] != $item['create_name'])
			$details[] = sprintf(i18n::s('edited by %s %s'), Users::get_link($item['edit_name'], $item['edit_address'], $item['edit_id']), Skin::build_date($item['edit_date']));

	}

	// all details
	if(count($details))
		$context['text'] .= '<p class="details">'.ucfirst(implode(', ', $details))."</p>\n";

	// insert anchor suffix
	if(is_object($anchor))
		$context['text'] .= $anchor->get_suffix();

	//
	// the navigation sidebar
	//
	$cache_id = 'actions/view.php?id='.$item['id'].'#navigation';
	if(!$text =& Cache::get($cache_id)) {

		// buttons to display previous and next pages, if any
		if(is_object($anchor)) {
			$neighbours = $anchor->get_neighbours('action', $item);
			$text .= Skin::neighbours($neighbours, 'sidebar');
		}

		// build a nice sidebar box
		if($text)
			$text =& Skin::build_box(i18n::s('Navigation'), $text, 'navigation', 'neighbours');

		// save in cache
		Cache::put($cache_id, $text, 'actions');
	}
	$context['extra'] .= $text;

	//
	// referrals, if any, in a sidebar
	//
	if(Surfer::is_associate() || (isset($context['with_referrals']) && ($context['with_referrals'] == 'Y'))) {

		$cache_id = 'actions/view.php?id='.$item['id'].'#referrals#';
		if(!$text =& Cache::get($cache_id)) {

			// box content
			include_once '../agents/referrals.php';
			$text = Referrals::list_by_hits_for_url($context['url_to_root_parameter'].Actions::get_url($item['id']));

			// in a sidebar box
			if($text)
				$text =& Skin::build_box(i18n::s('Referrals'), $text, 'navigation', 'referrals');

			// save in cache for one hour 60 * 60 = 3600
			Cache::put($cache_id, $text, 'referrals', 3600);

		}

		// in the extra panel
		$context['extra'] .= $text;

	}
}

// render the skin
render_skin();

?>