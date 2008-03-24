<?php
/**
 * list attached files
 *
 * This script can be invoked remotely in AJAX to update a compact list
 * of files attached to a page.
 *
 * On error it will provide both a HTTP status code, and explanations in HTML.
 * Else a 200 status code is provided, and a list of fresh files is provided.
 *
 * Accept following invocations:
 * - thread.php/12 (visit article #12)
 * - thread.php/article/12 (visit article #12)
 * - thread.php/section/2 (visit section #2)
 * - thread.php?id=12 (visit article #12)
 * - thread.php?id=article:12 (visit article #12)
 * - thread.php?id=section:2 (visit section #2)
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

// common definitions and initial processing
include_once '../shared/global.php';
include_once 'files.php';

// ensure browser always look for fresh data
Safe::header("Cache-Control: no-cache, must-revalidate");
Safe::header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// visited item
$anchor = NULL;
if(isset($_REQUEST['id']))
	$anchor = $_REQUEST['id'];
elseif(isset($context['arguments'][1]))
	$anchor = $context['arguments'][0].':'.$context['arguments'][1];
elseif(isset($context['arguments'][0]))
	$anchor = $context['arguments'][0];
$anchor = strip_tags($anchor);

// default anchor type is article
if(!strpos($anchor, ':'))
	$anchor = 'article:'.$anchor;

// get the related anchor, if any
if($anchor)
    $anchor = Anchors::get($anchor);

// load localized strings
i18n::bind('files');

// load the skin
load_skin('files');

// clear the tab we are in, if any
if(is_object($anchor))
	$context['current_focus'] = $anchor->get_focus();

// the path to this page
if(is_object($anchor) && $anchor->is_viewable())
	$context['path_bar'] = $anchor->get_path_bar();
else
	$context['path_bar'] = array( 'files/' => i18n::s('Files') );

// page title
$context['page_title'] = i18n::s('Files');

// an anchor is mandatory
if(!is_object($anchor)) {
	Safe::header('Status: 404 Not Found', TRUE, 404);
	Skin::error(i18n::s('No anchor has been found.'));

// provide updated information for this anchor
} else {

	// list files by date (default) or by title (option files_by_title)
	if($anchor->has_option('files_by_title'))
		$output = Files::list_by_title_for_anchor($anchor->get_reference(), 0, 20, 'compact');
	else
		$output = Files::list_by_date_for_anchor($anchor->get_reference(), 0, 20, 'compact');

	// ensure we are producing some text
	if(is_array($output))
		$output =& Skin::build_list($output, 'compact');

	// actual transmission except on a HEAD request
	if(isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] != 'HEAD'))
		echo $output;

	// the post-processing hook, then exit
	finalize_page(TRUE);

}

// render the skin on error
render_skin();

?>