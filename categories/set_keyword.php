<?php
/**
 * update a category from a search
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @author GnapZ
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

// common definitions and initial processing
include_once '../shared/global.php';
include_once '../categories/categories.php';

// what we are looking for
$search = NULL;
if(isset($_REQUEST['search']))
	$search = strip_tags($_REQUEST['search']);

// load localized strings
i18n::bind('categories');

// load the skin
load_skin('categories');

// the title of the page
$context['page_title'] = i18n::s('Keywords update');

// create a category to host keywords if none exists
if(!$root_category =& Categories::lookup('keywords')) {
	$fields = array();
	$fields['nick_name'] = 'keywords';
	$fields['title'] = i18n::c('Keywords');
	$fields['introduction'] = i18n::c('Classified pages');
	$fields['description'] = i18n::c('This category is a specialized glossary of terms, made out of tags added to pages, and out of search requests.');
	$fields['rank'] = 29000;
	$fields['options'] = 'no_links';
	if($id = Categories::post($fields))
		$root_category = 'category:'.$id;
}

// ensure we have a valid category to host keywords
if(!$root_category)
	Skin::error(i18n::s('No item has been found.'));

// operation is restricted to members
elseif(!Surfer::is_member())
	Skin::error(i18n::s('You are not allowed to perform this operation.'));

// ensure we have a keyword
elseif(!$search)
	Skin::error(i18n::s('No keyword to search for.'));

// search in articles
elseif(!$articles = Articles::search($search, 0, 50, 'raw')) {
	Skin::error(i18n::s('No item has been found.'));

// create a category for this keyword if none exists yet
} elseif(!$category =& Categories::get_by_keyword($search)) {
	$fields = array();
	$fields['keywords'] = $search;
	$fields['anchor'] = $root_category;
	$fields['title'] = ucfirst($search);
	if($id = Categories::post($fields))
		$category =& Categories::get($id);
}

// ensure we have a valid category for found articles
if(isset($articles) && (!isset($category) || !$category))
	Skin::error(i18n::s('No item has been found.'));

// link articles to this category
elseif(isset($articles) && is_array($articles)) {

	foreach($articles as $id => $not_used)
		if($error = Members::assign('category:'.$category['id'], 'article:'.$id)) {
			Skin::error($error);
			break;
		}

	// redirect to the updated category, if no error has happened
	if(!count($context['error']))
		Safe::redirect($context['url_to_home'].$context['url_to_root'].Categories::get_url($category['id'], 'view', $category['title']));

}

// failed operation
$context['text'] .= '<p>'.i18n::s('Impossible to update the item.').'</p>';

// render the skin
render_skin();

?>