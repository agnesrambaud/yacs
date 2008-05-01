<?php
/**
 * the index page for tables
 *
 * @todo allow for download as CSV to anonymous surfers
 *
 * Within YACS context a table is a SQL statement used to create dynamic lists of records.
 *
 * For a comprehensive description of tables, you should check the database abstraction script
 * at [script]tables/tables.php[/script].
 *
 * This page list tables available in the system.
 *
 * Note that because table records have no active field, as other items of the database, they
 * cannot be protected individually.
 * Because of that only associates can access this page.
 * Other surfers will have to go through related pages to access tables.
 * Therefore, tables will be protected by any security scheme applying to related pages.
 *
 * Let take for example a table inserted in a page restricted to logged members.
 * Only authenticated users will be able to read the page, and the embedded table as well.
 * Through this index associates will have an additional access link to all tables.
 *
 * The main menu has navigation links to browse tables by page, for sites that have numerous tables.
 *
 * Tables are displayed using the default decorated layout.
 *
 * A list of most recent articles is displayed as a sidebar.
 *
 * Accept following invocations:
 * - index.php (view the 20 top tables)
 * - index.php/2 (view tables 41 to 60)
 * - index.php?page=2 (view tables 41 to 60)
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @author GnapZ
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

// common definitions and initial processing
include_once '../shared/global.php';
include_once 'tables.php';

// which page should be displayed
$page = 1;
if(isset($_REQUEST['page']))
	$page = $_REQUEST['page'];
elseif(isset($context['arguments'][0]))
	$page = $context['arguments'][0];
$page = strip_tags($page);

// load the skin
load_skin('tables');

// the maximum number of tables per page
if(!defined('TABLES_PER_PAGE'))
	define('TABLES_PER_PAGE', 50);

// the title of the page
$context['page_title'] = i18n::s('Tables');

// this page is really only for associates
if(!Surfer::is_associate())
	$context['text'] .= '<p>'.i18n::s('Because of our security policy you are not allowed to list tables.').'</p>'
		.'<p>'.sprintf(i18n::s('Please browse %s to visualize any table that could be embedded.'), Skin::build_link('articles/index.php', i18n::s('published pages'), 'basic')).'</p>'."\n";

// display the index
else {

	// count tables in the database
	$stats = Tables::stat();
	if($stats['count'])
		$context['page_menu'] = array_merge($context['page_menu'], array('_count' => sprintf(i18n::ns('1 table', '%d tables', $stats['count']), $stats['count'])));

	// navigation commands for tables, if necessary
	if($stats['count'] > TABLES_PER_PAGE) {
		$home = 'tables/index.php';
		if($context['with_friendly_urls'] == 'Y')
			$prefix = $home.'/';
		elseif($context['with_friendly_urls'] == 'R')
			$prefix = $home.'/';
		else
			$prefix = $home.'?page=';
		$context['page_menu'] = array_merge($context['page_menu'], Skin::navigate($home, $prefix, $stats['count'], TABLES_PER_PAGE, $page));
	}

	// associates may check the database
	if(Surfer::is_associate())
		$context['page_menu'] = array_merge($context['page_menu'], array( 'tables/check.php' => i18n::s('Maintenance') ));

	// associates may import data
	if(Surfer::is_associate())
		$context['page_menu'] = array_merge($context['page_menu'], array( 'tables/import.php' => i18n::s('Import') ));

	// page main content
	$cache_id = 'tables/index.php#text#'.$page;
	if(!$text =& Cache::get($cache_id)) {

		// query the database and layout that stuff
		$offset = ($page - 1) * TABLES_PER_PAGE;
		if(!$text = Tables::list_by_date($offset, TABLES_PER_PAGE, 'full'))
			$context['text'] .= '<p>'.i18n::s('No table has been created yet.').'</p>';

		// we have an array to format
		if(is_array($text))
			$text =& Skin::build_list($text, 'decorated');

		// cache this to speed subsequent queries
		Cache::put($cache_id, $text, 'tables');
	}
	$context['text'] .= $text;

}

// page extra content
$cache_id = 'tables/index.php#extra';
if(!$text =& Cache::get($cache_id)) {

	// side bar with the list of most recent pages
	if($items = Articles::list_by_date(0, COMPACT_LIST_SIZE, 'compact'))
		$text =& Skin::build_box(i18n::s('Recent pages'), Skin::build_list($items, 'compact'), 'extra');

	Cache::put($cache_id, $text, 'articles');
}
$context['extra'] .= $text;

// referrals, if any
$context['extra'] .= Skin::build_referrals('tables/index.php');

// render the skin
render_skin();

?>