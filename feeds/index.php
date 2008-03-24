<?php
/**
 * use feeds to exchange news with other web servers
 *
 * @todo add other web subscriptions http://feeds.atwonline.com/AtwDailyNews
 *
 * Configuring feeding channels between servers is the very first mean to expand a YACS community.
 *
 * The patterns that are supported at the moment are quite straightforward, as usual:
 * - outbound feeding pattern
 * - inbound feeding pattern
 *
 * The outbound feeding pattern provides the newest articles to other polling servers.
 * You only have to configure some descriptive information (into [code]parameters/feeds.include.php[/code]) that will be embedded into
 * generated files, and that's it.
 *
 * Resources are available in several formats:
 * - atom_0.3.php supports the version 0.3 of the ATOM standard
 * - rss_2.0.php supports the version 2.0 of the RSS standard
 * - rss_1.0.php supports the version 1.0 of the RDF/RSS standard
 * - rss_0.92.php is for older pieces of software
 * - describe.php is an OPML list of most important feeds at this site
 *
 * More specific outbound feeds are available at [script]sections/feed.php[/script],
 * [script]categories/feed.php[/script], and [script]users/feed.php[/script].
 * You can also build a feed on particular keywords, at [script]services/search.php[/script].
 *
 * Bloggers will use another feed to download full contents, at [script]articles/feed.php[/script].
 * Comments are available as RSS at [script]comments/feed.php[/script].
 *
 * YACS also builds a feed to list most recent public files at [script]files/feed.php[/script].
 *
 * Associates can benefit from another specific feed to monitor the event log, at [script]agents/feed.php[/script].
 *
 * Moreover, this page also features links to add the main RSS feed either to Yahoo! or to NewsGator, in an extra box.
 *
 * The inbound feeding pattern is used to fetch fresh information from targeted servers.
 * Once the list of feeding servers has been configured (into parameters/feeds.include.php), everything
 * happens automatically.
 * Feeders are polled periodically (see [script]feeds/configure.php[/script])
 * and items are put in the database as news links.
 * Items may be listed into any part of your server using the Feeds class.
 *
 * @link http://www.atomenabled.org/developers/syndication/atom-format-spec.php The Atom Syndication Format 0.3 (PRE-DRAFT)
 * @link http://blogs.law.harvard.edu/tech/rss RSS 2.0 Specification
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @author GnapZ
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

// common definitions and initial processing
include_once '../shared/global.php';
include_once 'feeds.php';

// load localized strings
i18n::bind('feeds');

// load the skin
load_skin('feeds');

// the title of the page
$context['page_title'] = i18n::s('Information channels');

// configure feeds
if(Surfer::is_associate())
	$context['page_menu'] = array_merge($context['page_menu'], array( 'feeds/configure.php' => i18n::s('Configure') ));

// page main content
$cache_id = 'feeds/index.php#text';
if(!$text =& Cache::get($cache_id)) {

	// outbound feeds
	$text .= Skin::build_block(i18n::s('Outbound feeds'), 'title');

	// splash -- largely inspired by ALA
	$text .= '<p>'.sprintf(i18n::s('If you are unfamiliar with news feeds, they are easy to use. First, download a newsreader application like %s or %s. Then, copy and paste the URL of any news feed below into the application subscribe dialogue. See Google for more about %s.'), Skin::build_link(i18n::s('http://www.rssbandit.org/'), i18n::s('Rss Bandit'), 'external'), Skin::build_link(i18n::s('http://www.feedreader.com/'), i18n::s('FeedReader'), 'external'), Skin::build_link(i18n::s('http://www.google.com/search?q=RSS+newsreaders'), i18n::s('RSS newsreaders'), 'external'))."</p>\n";

	// a list of feeds
	$text .= '<p>'.i18n::s('Regular individuals will feed their preferred news reader with one of the links below:')."</p>\n";

	$links = array(
			Feeds::get_url('rss')	=> array(NULL, i18n::s('RSS 2.0 format'), NULL, 'xml'),
			Feeds::get_url('atom')	=> array(NULL, i18n::s('ATOM 0.3 format'), NULL, 'xml'),
			'feeds/rss_1.0.php' 	=> array(NULL, i18n::s('RDF/RSS 1.0 format'), NULL, 'xml'),
			'feeds/rss_0.92.php'	=> array(NULL, i18n::s('RSS 0.92 format'), NULL, 'xml'),
			Feeds::get_url('opml')	=> array(NULL, i18n::s('Index of main channels in OPML format'), NULL, 'xml')
			);

	$text .= Skin::build_list($links, 'bullets');

	// feeds for power users
	$text .= '<p>'.i18n::s('Advanced bloggers can also use (heavy) RSS 2.0 feeds:').'</p>';

	$links = array(
			Feeds::get_url('articles')	=> array(NULL, i18n::s('recent articles, integral version'), NULL, 'xml'),
			Feeds::get_url('comments')	=> array(NULL, i18n::s('all comments'), NULL, 'xml'),
			);

	$text .= Skin::build_list($links, 'bullets');

	// feeding files
	$text .= '<p>'.sprintf(i18n::s('YACS enables automatic downloads and %s through a feed dedicated to %s (RSS 2.0).'), Skin::build_link(i18n::s('http://en.wikipedia.org/wiki/Podcasting'), i18n::s('podcasting'), 'external'), Skin::build_link(Feeds::get_url('files'), i18n::s('public files'), 'xml')).'</p>';

	// other outbound feeds
	$text .= '<p>'.i18n::s('More specific outbound feeds are also available. Look for the XML button at other places:').'</p>'."\n<ul>\n"
		.'<li>'.sprintf(i18n::s('Browse the %s; each section has its own feed.'), Skin::build_link('sections/', i18n::s('site map'), 'shortcut')).'</li>'
		.'<li>'.sprintf(i18n::s('Or browse %s to get a more focused feed.'), Skin::build_link('categories/', i18n::s('categories'), 'shortcut')).'</li>'
		.'<li>'.sprintf(i18n::s('Visit any of the available %s to list articles from your preferred author.'), Skin::build_link('users/', i18n::s('user profiles'), 'shortcut')).'</li>'
		.'<li>'.sprintf(i18n::s('You can even use our %s to build one customised feed.'), Skin::build_link('search.php', i18n::s('search engine'), 'shortcut')).'</li>';

	// help Google
	if(Surfer::is_associate())
		$text .= '<li>'.sprintf(i18n::s('To help %s we also maintain a %s to be indexed.'), Skin::build_link(i18n::s('https://www.google.com/webmasters/sitemaps/'), i18n::s('Google'), 'external'), Skin::build_link('sitemap.php', i18n::s('Sitemap list of important pages'), 'xml')).'</li>';

	// feeding events
	if(Surfer::is_associate())
		$text .= '<li>'.sprintf(i18n::s('As a an associate, you can also access the %s (RSS 2.0).'), Skin::build_link('agents/feed.php', i18n::s('event log'), 'xml')).'</li>';

	// end of outbound feeds
	$text .= "\n</ul>\n";

	// get local news
	include_once 'feeds.php';
	$rows = Feeds::get_local_news();
	if(is_array($rows)) {
		$text .= Skin::build_block(i18n::s('Current local news'), 'title');
		$text .= "<ul>\n";
		foreach($rows as $url => $attributes) {
			list($time, $title, $author, $section, $image, $description) = $attributes;
			$text .= '<li>'.$title.' ('.$url.")</li>\n";
		}
		$text .= "</ul>\n";
	}

	// inbound feeds
	$text .= Skin::build_block(i18n::s('Inbound feeds'), 'title');

	// list existing feeders
	include_once $context['path_to_root'].'servers/servers.php';
	if($items = Servers::list_for_feed(0, COMPACT_LIST_SIZE, 'full')) {

		// link to the index of server profiles
		$text .= '<p>'.sprintf(i18n::s('To extend the list of feeders create adequate %s.'), Skin::build_link('servers/', i18n::s('server profiles'), 'shortcut'))."</p>\n";

		// list of profiles used as news feeders
		$text .= Skin::build_list($items, 'decorated');

	// no feeder defined
	} else
		$text .= sprintf(i18n::s('No feeder has been defined. If you need to create some external RSS %s.'), Skin::build_link('servers/edit.php', i18n::s('create an adequate server profile')));

	// get news from remote feeders
	include_once 'feeds.php';
	$news = Feeds::get_remote_news();
	if(is_array($news)) {
		$text .= Skin::build_block(i18n::s('Most recent external news'), 'title');

		// list of profiles used as news feeders
		$text .= Skin::build_list($news, 'compact');

	}

	// cache, whatever change, for 5 minutes
	Cache::put($cache_id, $text, 'stable', 300);
}
$context['text'] .= $text;

//
// extra panel
//

// an extra box to help people configure their feeders
$link = $context['url_to_home'].$context['url_to_root'].Feeds::get_url('rss');
$context['extra'] .= Skin::build_box(i18n::s('Aggregate this site'), '<p>'.join(BR, Skin::build_subscribers($link)).'</p>', 'extra');

// an extra box with popular standard icons for newsfeeds
$text = '';
if(OPML_STANDARD_IMG)
	$text .= Skin::build_link(Feeds::get_url('opml'), OPML_STANDARD_IMG, '').BR;
if(ATOM_0_3_STANDARD_IMG)
	$text .= Skin::build_link(Feeds::get_url('atom'), ATOM_0_3_STANDARD_IMG, '').BR;
if(RSS_2_0_STANDARD_IMG)
	$text .= Skin::build_link(Feeds::get_url('rss'), RSS_2_0_STANDARD_IMG, '').BR;
if(RSS_1_0_STANDARD_IMG)
	$text .= Skin::build_link('feeds/rss_1.0.php', RSS_1_0_STANDARD_IMG, '').BR;
if(RSS_0_9_STANDARD_IMG)
	$text .= Skin::build_link('feeds/rss_0.92.php', RSS_0_9_STANDARD_IMG, '').BR;
if($text)
	$context['extra'] .= Skin::build_box(i18n::s('Pick a feed'), '<p>'.$text.'</p>', 'extra');

// referrals, if any
$context['extra'] .= Skin::build_referrals('feeds/index.php');

// render the skin
render_skin();

?>