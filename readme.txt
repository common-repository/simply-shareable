=== Simply Shareable ===
Contributors: tarraccas
Tags: social media, sharing, syndication, seo
Requires at least: 4.4
Tested up to: 4.9
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Auto-generates information social media platforms and feed readers use to compose previews for shared pages with minimal setup.

== Description ==

*Content previews of content shared on social media missing descriptive pictures, headlines or summaries are ugly!* Or worse, resort to using an advertisement image parsed from the page! And who wants to spend time manually, painstakingly configuring SEO and social media options for each and every page, better spent on actually producing content worth sharing?

Simply Shareable automatically generates Open Graph, Schema.org microdata, meta and link HTML tags from existing information and resources that social media platforms like Twitter, Facebook, Google Plus and others read to compose previews for content shared from your WordPress site.

The purpose of this plugin is to make content shared from any page on your WordPress site as presentable as possible with the least amount of time and effort. No muss. No fuss.

= Features =
* Sets preview images from attached images (including post thumbnails aka. featured image), author gravatars or the site icon.
* Serves different author profile links for different social media platforms.
* Derives summaries from post excerpts or content, media captions or descriptions, term or post type descriptions, author biographies or site tagline.
* Derives tags and keywords from associated taxonomies.
* Makes embedded media available from attachments and content using video or audio post formats.
* Provides an interface to manage which syndication feeds and formats are linked.
* Includes a number of hooks developers can use to customize the output.
* Caches output for better performance.
* Improves search engine optimization (SEO).
* Unobtrusive. Minimal setup. Begins working immediately on activation.

= Considerations =
* Presentation of the information this plugin provides is at the discretion of social media platforms content is shared on which may or may not support features supported on other platforms.
* This plugin does _not_ install share buttons of any sort.
* No visual interface is provided to manage or override tags automatically generated on a per object (post, attachment, term, author, etc.) basis.


== Installation ==

1. Go to your WordPress Admin panel.
1. From the menu select Plugins > Add New
1. Search for \"Simply Shareable\" and click the \"Install Now\" and then the \"Activate\" buttons and on the result or, to install a zip file of the plugin, click the \"Upload Plugin\" button at the top of the screen, follow the instructions that appear to install and activate the plugin. The plugin takes instant effect upon activation.
1. To configure the plugin select the menu Settings > General Settings or click the \"Settings\" link under the listing on the Plugins screen and scroll down to the Simply Shareable section.
1. To configure the syndication feeds select Settings > Reading or click the \"Feeds\" link under the listing on the Plugins screen and scroll down to the Syndication Feeds section.
1. To configure author information shared select Users > Your Profile and update the name, contact and biography information there.


== Screenshots ==

1. Simply Shareable derives a number of images from which social media platforms select from posts that are either gallery format or that contain galleries. Some platforms like Google Plus and LinkedIn provide users a choice for the image shared.


== Changelog ==

= 2017 Sep 19 / Version 1.5.2 / Build 11 =
* Fixed: Subcategory descriptions weren't being queried.
* Fixed: Parsed descriptions could include line breaks.

= 2017 Jun 12 / Version 1.5.1 / Build 10 =
* Updated: Bump to WordPress 4.8

= 2017 Apr 24 / Version 1.5 / Build 9 =
* Added: RSS feed [en|dis]abling functionality.
* Fixed: filter_html_attributes() was processing on the admin side.

= 2017 Apr 05 / Version 1.4.1 / Build 8 =
* Fixed: is_term() was only returning term objects on custom taxonomies, not tags or categories.

= 2017 Apr 04 / Version 1.4 / Build 7 =
* Changed: name from BMC Social Tags to Simply Shareable
* Added: video and audio meta tags for attachments.
* Added: embed.php attachment template override for embedded video/audio attachment urls.
* Added: Integrated WP Embeds system with post format audio and video hints.
* Added: caching system.
* Updated: options to array under one option name.
* Added: translation support.
* Removed: depreciated twitter:card=gallery support. Sad!

= 2017 Feb 19 / Version 1.3 =
* Updated: twitter:card summary_large_image determination to take image width and width:height ratio into consideration.
* Replaced: is_category() and is_tax() checks for descriptions w/generic is_tax() and subsequent operations.
* Updated: language_attributes filter to take doctype into consideration before adding xmlns attributes.
* Updated: Now uses static front page post info like articles.
* Added: author page information including avatar and profile info.

= 2016 Aug 19 / Version 1.2 =
* Added: get_site_icon_id() function to get current site icon attachment id.
* Updated: Refined meta functionality for attachment pages.

= 2016 Aug 04 / Version 1.1 =
* Added: itemprop, schema.org microdata values for Google+ sharing.
* Added: html attributes via language_attributes filter.
* Added: Google+ contact field in profile.

= Version 1.0 =
* Maiden voyage.



