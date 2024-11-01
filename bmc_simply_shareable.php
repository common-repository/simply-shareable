<?php
/*
Plugin Name: Simply Shareable
Plugin URI: https://www.burningmoth.com/creation/simply-shareable/
Description: Automatically generates Open Graph, Schema.org microdata, meta and link HTML tags from existing information and resources that social media platforms like Twitter, Facebook, Google Plus and others read to compose previews for pages shared.
Version: 1.5.2
Author: Burning Moth Creations
Author URI: http://www.burningmoth.com/
Text Domain: bmc-simply-shareable
Domain Path: /lang/
License: GPL2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
namespace BMC\SimplyShareable;

/**
 * Get a plugin value.
 * @see https://codex.wordpress.org/Function_Reference/get_plugin_data
 *
 * @since 1.0
 *
 * @param string $key
 * @param mixed $alt
 * @return mixed
 */
function plugin( $key, $alt = '' ) {
	static $plugin;
	if ( !isset($plugin) ) {
		if ( !function_exists('get_plugin_data') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin = get_plugin_data(__FILE__, false, true);
	}
	return ( array_key_exists($key, $plugin) ? $plugin[ $key ] : $alt );
}


/**
 * Return the WordPress option key name.
 *
 * @since 1.4
 *
 * @return string
 */
function option_key() {
	static $key;
	if ( !isset($key) ) {

		/**
		 * WordPress option key to store plugin options under.
		 *
		 * @since 1.4
		 *
		 * @param string
		 *	- option key
		 *
		 * @return string
		 *	- option key
		 */
		$key = apply_filters(__NAMESPACE__.'\option_key', 'bmc_simply_shareable_options');

	}
	return $key;
}


/**
 * Option getter.
 * @since 1.4
 */
function option_get( $key, $alt = null ) {

	// get options ...
	static $options;
	if ( !isset($options) ) $options = (array) get_option(namespace\option_key(), array());

	// return value from options ...
	if ( array_key_exists($key, $options) ) return $options[ $key ];

	// get defaults ...
	static $defaults;
	if ( !isset($defaults) ) {
		/**
		 * Filter option defaults.
		 *
		 * @since 1.4
		 *
		 * @param array $defaults
		 * @return array
		 */
		$defaults = apply_filters(__NAMESPACE__.'\option_defaults', array(
			// timestamp the last time the system was updated to compare cached values against ...
			'time_modified'			=> 0,

			// current version ...
			'version'				=> namespace\plugin('Version'),

			// whether to cache the meta tags ...
			'cache'					=> 1,

			// whether to unstall these options on uninstall ...
			'uninstall_data'		=> 0,

			// platform whitelists, whether allowed to play streamed content on certain platforms (not implemented!) ...
			'whitelist_facebook'	=> 0,
			'whitelist_twitter'		=> 0,

			'site_google_verify'	=> '',
			'site_facebook'			=> '',
			'site_facebook_appid'	=> '',
			'site_twitter'			=> '',

			// feeds ...
			'feeds'					=> 1,
			'feed_types'			=> array('rss2', 'atom', 'rdf'),	// rss resolves to rss2 anyway ...
			'feed_global'			=> array('posts'), // omit 'comments' by default
			'feed_local'			=> array('posts', 'terms', 'search', 'author'), // omit 'comments' by default
		));
	}

	// return value from defaults ...
	if ( is_null($alt) && array_key_exists($key, $defaults) ) return $defaults[ $key ];

	// return alternate value, whatever it may be ...
	return $alt;

}


/**
 * Option setter.
 * @since 1.4
 */
function option_set( $key, $value ) {
	update_option(namespace\option_key(), [ $key => $value ], true);
}


/**
 * Option updater.
 * Merges option updates with previous options, adds version and timestamp, and removes null values.
 */
add_filter('pre_update_option_' . namespace\option_key(), __NAMESPACE__.'\filter_update_option', 9, 2);
function filter_update_option( $new_options, $old_options ) {

	// merge old options w/updates ...
	$options = array_merge(
		(array) $old_options,
		(array) $new_options,
		array(
			'version'		=> namespace\plugin('Version'),
			'time_modified'	=> time()
		)
	);

	// filter out any null, outdated values ...
	$options = array_filter(
		$options,
		function( $value ){
			return !is_null($value);
		}
	);

	// sanitize data ...
	array_walk(
		$options,
		function( &$value, $key ){
			// trim strings ...
			if ( is_string($value) ) $value = trim($value);
			// filter arrays ...
			elseif ( is_array($value) ) $value = array_filter($value);
		}
	);

	// return updated options ...
	return $options;

}


/**
 * Updates time modified whenever an option, object or object meta in the system is updated, once per session.
 * @note time_modified is used by cache_verify() to check against cache value expires time.
 *
 * @since 1.4
 *
 * @param integer|string $id
 *	- will be option name for updated_option hook, in which case it must not match our option lest infinity loop destroy us all!!!
 */
add_action('updated_option', __NAMESPACE__.'\action_updated', 9);
//add_action('updated_post_meta', __NAMESPACE__.'\action_updated', 9);
//add_action('updated_term_meta', __NAMESPACE__.'\action_updated', 9);
//add_action('updated_user_meta', __NAMESPACE__.'\action_updated', 9);
add_action('edit_terms',  __NAMESPACE__.'\action_updated', 9);
add_action('profile_update',  __NAMESPACE__.'\action_updated', 9);
add_action('save_post',  __NAMESPACE__.'\action_updated', 9);
function action_updated( $id ) {
	static $completed;
	if ( !isset($completed) && $id != namespace\option_key() ) {
		namespace\option_set('time_modified', time());
		$completed = true;
	}
}


/**
 * Return a clean twitter username from whatever's entered.
 *
 * @since 1.0
 *
 * @param string $twitter
 * @param string|null $ret
 * @return string
 */
function clean_twitter( $twitter, $ret = null ) {
	$op = preg_replace('/\W/', '', basename(strval($twitter)));
	if ( $op && $ret ) {
		switch ( $ret ) {
			case '@':
				$op = '@' . $op;
				break;

			case 'url':
				$op = 'https://twitter.com/' . $op;
				break;
		}
	}
	return $op;
}


/**
 * Return a clean facebook username from whatever's entered.
 *
 * @since 1.0
 *
 * @param string $fb
 * @param string|null $ret
 * @return string
 */
function clean_facebook( $fb, $ret = null ) {
	$op = basename(strval($fb));
	if ( $op && $ret ) {
		switch ( $ret ) {
			case 'url':
				$op = 'https://www.facebook.com/' . $op;
				break;
		}
	}
	return $op;
}


/**
 * Return a clean Google username or whatever's entered.
 *
 * @since 1.1
 * @param string $gp
 * @param string|null $ret
 * @return string
 */
function clean_google( $gp, $ret = null ) {

	$op = basename(strval($gp));

	// ensure the + prefix ...
	if ( $op && $op[0] != '+' ) {
		$op = '+' . $op;
	}

	// return formats ...
	if ( $op && $ret ) {
		switch ( $ret ) {
			case 'url':
				$op = 'https://plus.google.com/' . $op;
				break;
		}
	}

	return $op;
}


/**
 * Get the attachment/post id for the current site icon.
 * Adapted from get_site_icon_url()
 * @see https://developer.wordpress.org/reference/functions/get_site_icon_url/
 *
 * @since 1.2
 *
 * return int - post id or 0 if none found ...
 */
function get_site_icon_id( $blog_id = 0 ) {

	if ( is_multisite() && (int) $blog_id !== get_current_blog_id() ) {
		switch_to_blog( $blog_id );
	}

	$site_icon_id = get_option( 'site_icon' );

	if ( is_multisite() && ms_is_switched() ) {
		restore_current_blog();
	}

	return $site_icon_id;
}


/**
 * Get RSS feed title separator.
 * @see wp-includes/general-template.php feed_links() for default separator.
 * @see filter_title_separtor() for the defined page separator (if any).
 *
 * @since 1.5
 *
 * @return string
 */
function title_separator() {
	static $sep;
	if ( !isset($sep) ) $sep = ( defined(__NAMESPACE__.'\WP_TITLE_SEPARATOR') && !empty(namespace\WP_TITLE_SEPARATOR) ? namespace\WP_TITLE_SEPARATOR : _x('&raquo;', 'feed link') );
	return $sep;
}


/**
 * Return post object if is a single post, page or attachment.
 *
 * @since 1.2
 *
 * @return bool|WP_Post
 */
function is_article() {
	return ( is_singular() ? $GLOBALS['post'] : false );
}


/**
 * Return term object or false if taxonomy ...
 *
 * @since 1.4
 *
 * @return bool|WP_Term
 */
function is_term() {

	// category ?
	if ( is_category() ) return get_category( get_query_var('cat') );

	// tag ?
	if( is_tag() ) return get_tag( get_query_var('tag_id') );

	// custom taxonomy ...
	if ( is_tax() ) return get_term_by('slug', get_query_var('term'), get_query_var('taxonomy') );

	// fail !
	return false;
}


/**
 * Returns an array of image properties on success.
 *
 * @since 1.4
 *
 * @param integer $attachment_id
 *	- basically a post id
 * @return array|bool
 *	- if not an image then false
 *	- if an image then an array of the following values: [ string $url, integer $width, integer $height, string $mime_type ]
 */
function is_image( $attachment_id ) {

	if (
		wp_attachment_is_image( $attachment_id )
		&& ( $meta = wp_get_attachment_metadata($attachment_id) )
	) return array( wp_get_attachment_url($attachment_id), $meta['width'], $meta['height'], get_post_mime_type($attachment_id) );

	return false;
}



/**
 * Return true if user agent matches argument ...
 *
 * @since 1.4
 *
 * @param string $str
 *	- user agent string or token ...
 * @return bool
 */
function is_agent( $str ) {

	static $user_agent, $user_agents;
	if ( !isset($user_agent) ) {
		$user_agent = ( isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '' );
		$user_agents = array();
	}

	// stored result ? return now ...
	elseif ( array_key_exists($str, $user_agents) ) return $user_agents[ $str ];

	// determine value ...
	$value = (
		/**
		 * Facebook's bot.
		 * @see https://developers.facebook.com/docs/sharing/webmasters/crawler
		 * @see https://developers.facebook.com/docs/reference/opengraph/object-type/article/
		 */
		(
			$str == 'facebook'
			&& (
				strpos($user_agent, "facebookexternalhit/") !== false
				|| strpos($user_agent, "Facebot") !== false
			)
		)

		/**
		 * Google+ Snippet Bot + Googlebot
		 * @see https://developers.google.com/+/web/snippet/#faq-snippet-useragent
		 */
		|| (
			$str == 'google'
			&& strpos($user_agent, "Google") !== false
		)

		/**
		 * Twitter's bot.
		 * @see https://dev.twitter.com/cards/getting-started#crawling
		 */
		|| (
			$str == 'twitter'
			&& strpos($user_agent, "Twitterbot/") !== false
		)

		/**
		 * Pinterest's bot.
		 * @see http://www.botopedia.org/user-agent-list/service-agents/pinterest-bot
		 */
		|| (
			$str == 'pinterest'
			&& strpos($user_agent, "Pinterest/") !== false
		)

		// match against the string itself ...
		|| strpos($user_agent, $str) !== false
	);

	/**
	 * Return true or false from user agent string or token.
	 *
	 * @since 1.4
	 *
	 * @param bool $value
	 * @param string $str
	 *	- user agent string or token
	 * @return bool
	 */
	$value = apply_filters(__NAMESPACE__.'\is_agent', $value, $str);

	// store value ...
	$user_agents[ $str ] = $value;

	// return value ...
	return $value;

}


/**
 * Return true if caching ...
 * @since 1.4
 * @return bool
 */
function is_cache() {
	static $is_cache;
	if ( !isset($is_cache) ) {

		/**
		 * To cache or not to cache?
		 *
		 * @since 1.4
		 *
		 * @param bool
		 * @return bool
		 */
		$is_cache = apply_filters(__NAMESPACE__.'\is_cache', (

			// not in debug mode ...
			!\WP_DEBUG

			// option set to cache ...
			&& namespace\option_get('cache', true)

			// not a user agent that requires some kind of dynamic action ...
			&& !namespace\is_agent('facebook')
			&& !namespace\is_agent('google')
			&& !namespace\is_agent('pinterest')

		));
	}
	return $is_cache;
}


/**
 * Return a key to cache meta data under.
 * @since 1.4
 * @return string
 */
function cache_key() {
	static $key;
	if ( !isset($key) ) {

		/**
		 * Filters the key that cached meta tags are stored under in meta and options tables.
		 *
		 * @since 1.4
		 *
		 * @param string
		 *	- cache key
		 * @return string
		 */
		$key = apply_filters(__NAMESPACE__.'\cache_key', '_simply_shareable_meta_cache');
	}
	return $key;
}


/**
 * Return a cache array from input.
 * @since 1.4
 * @param string $value
 * @return array
 */
function cache_value( $value ) {

	/**
	 * Filters the cache value array.
	 *
	 * @since 1.4
	 *
	 * @param array
	 *	- version: current version of this plugin
	 *	- time_created: unix timestamp this value was created
	 *	- expires: number of seconds until this value expires
	 *	- data: the payload of meta tags being cached
	 *
	 * @return array
	 */
	return apply_filters(__NAMESPACE__.'\cache_value', [
		'version'		=> namespace\plugin('Version'),
		'time_created'	=> time(),
		'expires'		=> \HOUR_IN_SECONDS,
		'data'			=> $value
	]);

}


/**
 * Return true if passed value is a valid cache array.
 * @since 1.4
 * @param mixed $value
 * @return bool
 */
function cache_verify( $value ) {

	/**
	 * Validates a cache value.
	 *
	 * @since 1.4
	 *
	 * @param bool
	 * @param array $value
	 *	- cache value array
	 * @return bool
	 */
	return apply_filters(__NAMESPACE__.'\cache_verify', (

		// is array w/cached data ?
		!empty($value)
		&& is_array($value)
		&& !empty($value['data'])

		// cache version matches current plugin version ?
		&& array_key_exists('version', $value)
		&& version_compare($value['version'], namespace\plugin('Version'), 'eq')

		// cache has not expired or been superceded by system modified time ...
		&& array_key_exists('expires', $value)
		&& array_key_exists('time_created', $value)
		&& $value['expires'] + $value['time_created'] > time()
		&& $value['time_created'] > namespace\option_get('time_modified')

	), $value);

}


/**
 * render the meta tags.
 *
 * @since 1.0
 *
 * @return string
 *	- HTML tags.
 */
function render_meta() {

	/**
	 * Filters the input. (from cache by default)
	 *
	 * @since 1.4
	 *
	 * @param string
	 * @return string
	 *	- Any non-empty value returned will be used and skip the meta generation.
	 */
	if ( $ip = apply_filters(__NAMESPACE__.'\meta_input', '') ) return $ip;

	// start default meta ...
	$meta = array(
		// site name ...
		'og:site_name' 	=> get_bloginfo('name'),

		// resource type (consider using post format) ...
		'og:type'		=> ( is_singular() && !( is_home() || is_front_page() ) ? 'article' : 'website' ),
		'twitter:card'	=> 'summary',

		// language ...
		'og:locale'		=> str_replace('-', '_', get_bloginfo('language')),

		// image_src array ...
		'image_src'		=> array(),
	);

	// add twitter site ...
	if ( $site_twitter = namespace\option_get('site_twitter') ) {
		$meta['twitter:site'] = namespace\clean_twitter($site_twitter, '@');
	}

	// add google verify ...
	if ( $site_google_verify = namespace\option_get('site_google_verify') ) {
		$meta['google-site-verification'] = $site_google_verify;
	}

	// add facebook app id ...
	if ( $site_facebook_appid = namespace\option_get('site_facebook_appid') ) {
		$meta['fb:app_id'] = $site_facebook_appid;
	}

	// begin the basics ...
	$title = '';
	$description = '';
	$url = '';

	// front page ? set name, description from site ...
	if ( is_front_page() || is_home() ) {

		$title = get_bloginfo('name');
		$description = get_bloginfo('description');
		$url = get_home_url();

	}

	// articles ...
	elseif ( $post = namespace\is_article() ) {

		// add facebook publisher ...
		if ( $site_facebook = namespace\option_get('site_facebook') ) {
			$meta['article:publisher'] = namespace\clean_facebook($site_facebook, 'url');
		}


		// is image attachment or has featured image ? make this the image ...
		if (
			(
				is_attachment()
				&& ( $image = namespace\is_image($post->ID) )
			)
			|| (
				has_post_thumbnail($post->ID)
				&& ( $image = namespace\is_image( get_post_thumbnail_id($post->ID) ) )
			)
		) {
			list($meta['image_src'][], $meta['image_width'][], $meta['image_height'][], $meta['image_type'][]) = $image;
		}


		// parse images from the content if there are no images or format is gallery, image ...
		if (
			empty($meta['image_src'])
			|| strpos($post->post_content, '[gallery') !== false
			|| in_array(get_post_format($post->ID), ['gallery', 'image'])
		) {

			// has attached images ? make those images ...
			if ( $attachments = get_attached_media('image', $post->ID) ) {
				while ( $attachments ) {
					$attachment = array_shift($attachments);
					if ( $image = namespace\is_image($attachment->ID) ) {
						list($meta['image_src'][], $meta['image_width'][], $meta['image_height'][], $meta['image_type'][]) = $image;
					}
				}
			}

			// match all images in the content ...
			if ( preg_match_all(
				'/src\s*=\s*(\'|")(http(|s):.*?\.(gif|jpg|jpeg|png))\1/i',
				do_shortcode($post->post_content),
				$matches,
				PREG_SET_ORDER
			) ) {

				global $wpdb;
				$upload_dir = wp_upload_dir();

				while ( $matches ) {

					// default image properties ...
					$match = array_shift($matches);
					$image_src = $match[2];
					$image_width = 0;
					$image_height = 0;
					$image_type = '';

					// located in local upload directory ? get image dims ...
					if ( false !== strpos($image_src, $upload_dir['baseurl']) ) {

						// get the original image if need be, remove derivative info ...
						$image_src = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $image_src);

						// already processed ? skip ...
						if ( in_array($image_src, $meta['image_src']) ) continue;

						// path relative to the upload directory ...
						$image_path = str_replace(trailingslashit($upload_dir['baseurl']), '', $image_src);

						// get image information ...
						if (
							( $image_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = '%s'", $image_path)) )
							&& ( $image = namespace\is_image($image_id) )
						) {
							list($image_src, $image_width, $image_height, $image_type) = $image;
						}

					}

					// set the image properties ...
					$meta['image_src'][] = $image_src;
					$meta['image_width'][] = $image_width;
					$meta['image_height'][] = $image_height;
					$meta['image_type'][] = $image_type;

				}

			}

		}


		// set gallery and image post formats and image attachments to large image twitter card format ...
		if (
			count($meta['image_src'])
			&& (
				(
					is_attachment()
					&& wp_attachment_is('image', $post)
				)
				|| (
					is_single()
					&& in_array(get_post_format($post->ID), ['gallery', 'image'])
				)
			)
		) {
			$meta['twitter:card'] = 'summary_large_image';
		}


		// get audio attachments ...
		if (
			(
				is_attachment()
				&& wp_attachment_is('audio', $post)
				&& ( $attachments = [ $post ] )
			)
			|| (
				is_single()
				&& get_post_format($post->ID) == 'audio'
				&& ( $attachments = get_attached_media('audio', $post->ID) )
			)
		) {

			$meta['og:type'] = 'music';

			while ( $attachments ) {

				$attachment = array_shift($attachments);
				$attachment_meta = wp_get_attachment_metadata($attachment->ID, true);

				//embedded ...
				$meta['audio_src'][] = get_post_embed_url( $attachment );
				$meta['audio_type'][] = 'text/html';
				$meta['audio_duration'][] = $attachment_meta['length'];
				$meta['audio_artist'][] = $attachment_meta['artist'];
				$meta['audio_album'][] = $attachment_meta['album'];

				// streamed ...
				$meta['audio_src'][] = wp_get_attachment_url( $attachment->ID );
				/**
				 * Facebook only allows certain whitelisted vendors to share audio and the type needs to be "audio/vnd.facebook.bridge" because Zuckerberg
				 * @see https://developers.facebook.com/docs/opengraph/music/
				 */
				$meta['audio_type'][] = 'audio/' . ( namespace\is_agent('facebook') ? 'vnd.facebook.bridge' : $attachment_meta['fileformat'] );
				$meta['audio_duration'][] = $attachment_meta['length'];
				$meta['audio_artist'][] = $attachment_meta['artist'];
				$meta['audio_album'][] = $attachment_meta['album'];

				// set image from attachment if no images ...
				if (
					empty($meta['image_src'])
					&& !is_attachment()
					&& has_post_thumbnail($attachment->ID)
				) {
					list($meta['image_src'][], $meta['image_width'][], $meta['image_height'][]) = wp_get_attachment_image_src(get_post_thumbnail_id($attachment->ID), 'full');
				}

			}

		}


		// get video attachments ...
		if (
			(
				is_attachment()
				&& wp_attachment_is('video', $post)
				&& ( $attachments = [ $post ] )
			)
			|| (
				is_single()
				&& get_post_format($post->ID) == 'video'
				&& ( $attachments = get_attached_media('video', $post->ID) )
			)
		) {

			// set type to video ...
			$meta['og:type'] = 'video';

			while ( $attachments ) {

				$attachment = array_shift($attachments);
				$attachment_meta = wp_get_attachment_metadata($attachment->ID, true);

				// embedded ...
				$meta['video_src'][] = get_post_embed_url( $attachment );
				$meta['video_type'][] = 'text/html';
				$meta['video_width'][] = $attachment_meta['width'];
				$meta['video_height'][] = $attachment_meta['height'];
				$meta['video_duration'][] = $attachment_meta['length'];

				// streamed ...
				$meta['video_src'][] = wp_get_attachment_url( $attachment->ID );
				$meta['video_type'][] = 'video/' . $attachment_meta['fileformat']; //$attachment_meta['mime_type'];
				$meta['video_width'][] = $attachment_meta['width'];
				$meta['video_height'][] = $attachment_meta['height'];
				$meta['video_duration'][] = $attachment_meta['length'];

				// set image from attachment if no images ...
				if (
					empty($meta['image_src'])
					&& !is_attachment()
					&& has_post_thumbnail($attachment->ID)
				) {
					list($meta['image_src'][], $meta['image_width'][], $meta['image_height'][]) = wp_get_attachment_image_src(get_post_thumbnail_id($attachment->ID), 'full');
				}

				/**
				 * Twitter requires HTTPS and MP4 and an image ...
				 * @see https://dev.twitter.com/cards/types/player
				 */
				if (
					namespace\option_get('whitelist_twitter')
					&& is_ssl()
					&& $attachment_meta['fileformat'] == 'mp4'
					&& $meta['twitter:card'] != 'player'
				) {
					$meta['twitter:card'] = 'player';
					$meta['twitter:player']	= get_post_embed_url( $attachment );
					$meta['twitter:player:width'] = $attachment_meta['width'];
					$meta['twitter:player:height'] = $attachment_meta['height'];
					$meta['twitter:player:stream'] = wp_get_attachment_url( $attachment->ID );
					$meta['twitter:player:stream:content_type'] = 'video/mp4';
				}

			}

		}


		// parse embeds from the content ...
		if (
			is_single()
			&& in_array( ( $format = get_post_format($post->ID) ), ['video', 'audio'] )
			&& preg_match_all('#\[embed[^\]]*\](.*?)\[/embed\]#si', $post->post_content, $urls, PREG_PATTERN_ORDER)
		) {

			$urls = end($urls);
			while ( $urls ) {

				$url = array_shift($urls);

				// rip information from iframe markup returned by wp_embed system ...
				if (
					!( $markup = wp_oembed_get($url) )
					|| !preg_match_all('#(src|width|height)\s*=\s*(\'|")(.*?)\2#si', $markup, $attr, PREG_PATTERN_ORDER)
				) continue;

				// combine matched attributes into associative array ...
				$attr = array_combine(array_map('strtolower', $attr[1]), $attr[3]);

				// no source attribute ? skip ...
				if ( !array_key_exists('src', $attr) ) continue;

				// pass to audio ...
				if ( $format == 'audio' ) {

					$meta['og:type'] = 'music';

					$meta['audio_src'][] = $attr['src'];
					$meta['audio_type'][] = ( namespace\is_agent('facebook') ? 'audio/vnd.facebook.bridge' : 'text/html' );
					$meta['audio_duration'][] = 0;
					$meta['audio_artist'][] = '';
					$meta['audio_album'][] = '';

				}

				// pass to video ...
				else {

					$meta['og:type'] = 'video';

					$meta['video_src'][] = $attr['src'];
					$meta['video_type'][] = 'text/html';
					$meta['video_width'][] = ( array_key_exists('width', $attr) ? intval($attr['width']) : 0 );
					$meta['video_height'][] = ( array_key_exists('height', $attr) ? intval($attr['height']) : 0 );
					$meta['video_duration'][] = 0;

					// set twitter card to player if not already done so and passes Twitter muster ...
					if (
						// not already set to player ...
						$meta['twitter:card'] != 'player'

						// site and therefore the required image is https ?
						&& is_ssl()
						&& !empty($meta['image_src'])

						// the embedded content is https ?
						&& strpos($attr['src'], 'https') === 0

						// has required dimensions ...
						&& isset($attr['width'])
						&& isset($attr['height'])
					) {
						$meta['twitter:card'] = 'player';
						$meta['twitter:player']	= $attr['src'];
						$meta['twitter:player:width'] = $attr['width'];
						$meta['twitter:player:height'] = $attr['height'];
					}

				}

			}


		}

		// description ...
		$description = ( $post->post_excerpt ? $post->post_excerpt : $post->post_content );

		// url ...
		$url = get_permalink($post->ID);

		// twitter creator from author ...
		if ( $twitter = get_the_author_meta('twitter', $post->post_author) ) {
			$meta['twitter:creator'] = namespace\clean_twitter($twitter, '@');
		}

		// publish and modify times ...
		$meta['item:datePublished'] = $meta['article:published_time'] = date('c', strtotime($post->post_date_gmt));
		$meta['article:modified_time'] = date('c', strtotime($post->post_modified_gmt));

		// article author name ...
		$meta['author'] = get_the_author_meta('display_name', $post->post_author);

		/**
		 * Pinterest defines the article:author as a name. So give it the author name!
		 * @see https://developers.pinterest.com/docs/rich-pins/articles/
		 */
		if ( namespace\is_agent('pinterest') ) {
			$meta['article:author'] = $meta['author'];
		}

		// Facebook profile link ?
		elseif (
			namespace\is_agent('facebook')
			&& $fb = get_the_author_meta('facebook', $post->post_author)
		) {
			$meta['article:author'] = $meta['link:author'] = namespace\clean_facebook($fb, 'url');
		}

		// Google+ profile link ?
		elseif (
			namespace\is_agent('google')
			&& $gp = get_the_author_meta('google', $post->post_author)
		) {
			$meta['article:author'] = $meta['link:author'] = namespace\clean_google($gp, 'url');
		}

		/**
		 * Open Graph seems to indicate a "profile" og:type which is what we mark the author pages as, so link to that.
		 * @see http://ogp.me/
		 */
		else {
			$meta['article:author'] = $meta['link:author'] = get_author_posts_url($post->post_author);
		}


		// process taxonomy terms ...
		$taxonomies = get_post_taxonomies();
		foreach ( $taxonomies as $taxonomy_name ) {

			// get taxonomy objects ...
			$taxonomy = get_taxonomy($taxonomy_name);

			// skip private or empty taxonomies ...
			if ( !$taxonomy->public || !( $terms = wp_get_post_terms($post->ID, $taxonomy_name) ) ) continue;

			// add terms as sections and tags ...
			foreach ( $terms as $term ) {
				$meta[ $taxonomy->hierarchical ? 'article:section' : 'article:tag' ][] = $term->name;
				$meta['keywords'][] = $term->name;

				// add video tags ?
				if (
					$meta['og:type'] == 'video'
					&& !$taxonomy->hierarchical
				) $meta['og:video:tag'][] = $term->name;

			}

		}


		// set keywords ...
		if ( isset($meta['keywords']) ) {
			$meta['keywords'] = implode(', ', $meta['keywords']);
		}

	}

	// user page ...
	elseif ( is_author() ) {

		// og:type is profile ...
		$meta['og:type'] = 'profile';

		$meta['profile:first_name'] = get_the_author_meta('first_name');
		$meta['profile:last_name'] = get_the_author_meta('last_name');
		$meta['profile:username'] = get_the_author_meta('user_nicename');

		// add gravatar ...
		$meta['image_src'][] = get_avatar_url(get_the_author_meta('ID'), [ 'size' => 300 ]);
		$meta['image_width'][] = 300;
		$meta['image_height'][] = 300;
		$meta['image_type'][] = '';		// could be anything ...

		// add description ...
		$description = get_the_author_meta('description');

		// add url ...
		$url = get_author_posts_url(get_the_author_meta('ID'));

	}

	// taxonomy term ...
	elseif ( $term = namespace\is_term() ) {

		// if no description is set for term, try for registered taxonomy description ...
		if (
			!( $description = $term->description )
			&& ( $taxonomy = get_taxonomy( $term->taxonomy ) )
		) $description = $taxonomy->description;

		$url = get_term_link($term);


	}

	// search ...
	elseif ( is_search() ) {

		$url = get_search_link();

	}

	// post type archive ...
	elseif (
		is_post_type_archive()
		&& ( $post_type_slug = get_query_var('post_type') )
		&& ( $post_type = get_post_type_object($post_type_slug) )
	) {

		$description = $post_type->description;
		$url = get_post_type_archive_link($post_type_slug);

	}


	/**
	 * Pinterest requires og:type to be either blog or article.
	 * @see https://developers.pinterest.com/docs/rich-pins/articles/
	 */
	if ( namespace\is_agent('pinterest') ) {
		$meta['og:type'] = ( is_singular() ? 'article' : 'blog' );
	}


	// no url ? generate ...
	if (
		empty($url)
		&& isset($_SERVER['HTTP_HOST'])
		&& isset($_SERVER['REQUEST_URI'])
	) {
		$url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	// no title ? let wordpress define it ...
	if ( empty($title) ) {

		// captured title defined from wp_get_document_title() ? use that ...
		if ( defined(__NAMESPACE__.'\WP_TITLE') ) {
			$title = namespace\WP_TITLE;
		}

		//generate from old wp_title() ...
		else {

			// get separator ...
			$title_separator = apply_filters('document_title_separator', '/');

			// generate title parts ...
			$title_parts = wp_title($title_separator, false);
			$title_parts = explode($title_separator, $title_parts);
			$title_parts = array_map('trim', $title_parts);
			$title_parts = array_filter($title_parts);

			// no title parts ? use site name ...
			if ( empty($title_parts) ) {
				$title = get_bloginfo('name');
			}

			// join title parts ...
			else {
				$title = implode(" $title_separator ", $title_parts);
			}

		}

	}

	// use site icon for image if there are no images ...
	if (
		empty($meta['image_src'])
		&& ( $icon_id = namespace\get_site_icon_id() )
		&& ( $image = namespace\is_image($icon_id) )
	) {
		list($meta['image_src'][], $meta['image_width'][], $meta['image_height'][], $meta['image_type'][]) = $image;
	}

	// set main twitter and itemprop images from the first image ...
	if ( count($meta['image_src']) ) {

		$meta['twitter:image'] = $meta['item:image'] = current($meta['image_src']);

		/**
		 * Set twitter:card to display larger image format if requirements are met ...
		 * Summary large image should be 280px+ and have a ratio of 3:2 (1.5) or higher (lower is more square or vertical) ...
		 * @see https://www.agorapulse.com/blog/all-twitter-image-sizes-best-practices
		 */
		if (
			current($meta['image_width']) >= 280
			&& current($meta['image_width'])/current($meta['image_height']) >= 1.5
		) {
			$meta['twitter:card'] = 'summary_large_image';
		}

	}

	/**
	 * Filter derived title.
	 *
	 * @since 1.4
	 *
	 * @param string $title
	 * @return string
	 */
	if ( $title = apply_filters(__NAMESPACE__.'\meta_title', $title) ) {

		$title = strip_tags($title);
		$title = trim($title);

		$meta['og:title'] = $title;
		$meta['twitter:title'] = $title;
		$meta['item:name'] = $title;

		// set headline for singular items ...
		if ( is_single() ) {
			$meta['item:headline'] = $title;
		}

	}

	/**
	 * Filter derived description.
	 *
	 * @since 1.4
	 *
	 * @param string $description
	 * @return string
	 */
	if ( $description = apply_filters(__NAMESPACE__.'\meta_description', $description) ) {

		$meta['description'] = $description;
		$meta['og:description'] = $description;
		$meta['twitter:description'] = $description;
		$meta['item:description'] = $description;

	}


	/**
	 * Filter derived url.
	 *
	 * @since 1.4
	 *
	 * @param string $url
	 * @return string
	 */
	if ( $url = apply_filters(__NAMESPACE__.'\meta_url', $url) ) {

		$meta['og:url'] = $url;
		$meta['twitter:url'] = $url;
		$meta['item:url'] = $url;

		/**
		 * Set canonical link on home page.
		 * WP already sets this tag for pages that resolve to is_singular().
		 *
		 * Not appropriate everywhere. Listing type pages probably shouldn't have this set.
		 * @see https://moz.com/blog/canonical-url-tag-the-most-important-advancement-in-seo-practices-since-sitemaps
		 */
		if ( is_front_page() || is_home() ) {
			$meta['link:canonical'] = $url;
		}

	}

	/**
	 * Filter meta tags array.
	 *
	 * @since 1.0
	 *
	 * @param array $meta
	 *	- multi-dim array of meta tags
	 * @return array
	 */
	$meta = apply_filters(__NAMESPACE__.'\meta_array', $meta);

	// generate output html ...
	$op = '';
	while ( list($key, $values) = each($meta) ) {

		/**
		 * Filter meta tag output.
		 *
		 * @since 1.0
		 *
		 * @param string $input
		 *
		 * @param string $key
		 *	- meta tag name
		 *
		 * @param string $value
		 *	- meta tag value
		 *
		 * @param int $index
		 *	- meta tag index
		 *
		 * @param array $meta
		 *	- meta tag array
		 *
		 * @return string
		 *	- should be valid html suitable for <head> section or empty string
		 */
		// process an array of tags ...
		if ( is_array($values) ) {
			while ( list($i, $value) = each($values) ) {
				$op .= apply_filters(__NAMESPACE__.'\meta_string', '', $key, $value, $i, $meta);
			}
		}

		// process a single tag ...
		else {
			$op .= apply_filters(__NAMESPACE__.'\meta_string', '', $key, $values, 0, $meta);
		}

	}


	/**
	 * Process feed links.
	 */
	if (
		// we are handling the feeds ...
		true //namespace\option_get('feeds')

		// reference feed types array ...
		&& ( $feed_types =& namespace\feed_types() )

		// there's a valid feed type ...
		&& current($feed_types)
	) {

		/**
		 * Filter whether or not to render the global site-wide posts feed in this context.
		 *
		 * @since 1.5
		 *
		 * @param bool $render
		 * @return bool
		 */
		$feed_global = apply_filters(__NAMESPACE__.'\feed_global', (bool) namespace\option_get('feed_global') );

		/**
		 * Filter whether or not to render extra local feeds in this context.
		 *
		 * @since 1.5
		 *
		 * @param bool $render
		 * @return bool
		 */
		$feed_local = apply_filters(__NAMESPACE__.'\feed_local', false);

		// are we rendering anything ?
		if ( $feed_global || $feed_local ) {

			// engage default feed filter ...
			add_filter('default_feed', __NAMESPACE__.'\filter_default_feed', 99);

			// engage feed link modifier/corrector to reflect the appropriate syndication markup
			// @see filter_feed_link() for why we must do this !
			$feed_link_filters = array('feed_link', 'post_comments_feed_link', 'post_type_archive_feed_link', 'category_feed_link', 'tag_feed_link', 'taxonomy_feed_link', 'author_feed_link', 'search_feed_link');
			do {
				add_filter(current($feed_link_filters), __NAMESPACE__.'\filter_feed_link', 99, 2);
			} while ( next($feed_link_filters) );

			// start buffering ...
			ob_start();

			do {

				/**
				 * Filter feed_links() and feed_links_extra() arguments.
				 *
				 * @since 1.5
				 *
				 * @param array $args
				 * @return array
				 */
				$args = apply_filters(__NAMESPACE__.'\feed_args', array( 'separator' => trim( namespace\title_separator() . ' ' . namespace\feed_name() ) ) );

				// render global posts feed ...
				if ( $feed_global ) feed_links($args);

				// render local posts feed ?
				if ( $feed_local ) feed_links_extra($args);

			} while ( next($feed_types) );

			// end buffering, append to output ...
			$op .= ob_get_clean();

			// disengage default feed filter ...
			remove_filter('default_feed', __NAMESPACE__.'\filter_default_feed', 99);

			// disengage feed link filters ...
			while ( prev($feed_link_filters) ) {
				remove_filter(current($feed_link_filters), __NAMESPACE__.'\filter_feed_link', 99);
			}

		}

	}


	/**
	 * Filter meta tags output.
	 *
	 * @since 1.4
	 *
	 * @param string $output
	 *	- generated meta tags html output
	 *
	 * @return string
	 */
	return apply_filters(__NAMESPACE__.'\meta_output', $op);

}


/**
 * Capture page title when Wordpress calls wp_get_document_title().
 * @see render_meta() for where this is used.
 * Currently, there's no more practical way to do this.
 *
 * @since 1.4
 */
add_filter('document_title_parts', __NAMESPACE__.'\filter_title_parts', 99);
function filter_title_parts( $parts = array() ) {

	// WP_TITLE not yet set ? define now ...
	if ( !defined(__NAMESPACE__.'\WP_TITLE') ) {
		define(__NAMESPACE__.'\WP_TITLE', $parts['title']);
	}

	// simply return the title parts ...
	return $parts;

}


/**
 * Capture page title separator when Wordpress calls wp_get_document_title().
 * @see title_separator() for how this is used.
 *
 * @since 1.5
 */
add_filter('document_title_separator', __NAMESPACE__.'\filter_title_separator', 99);
function filter_title_separator( $sep ) {
	if ( !defined(__NAMESPACE__.'\WP_TITLE_SEPARATOR') ) {
		define(__NAMESPACE__.'\WP_TITLE_SEPARATOR', $sep);
	}
	return $sep;
}


/**
 * Return a cleaned up description.
 *
 * @since 1.3
 * @since 1.4
 *	- changed function name from clean_description to filter_description
 *	- converted to meta_description filter callback.
 *
 * @param string $description
 * @return string
 */
add_filter(__NAMESPACE__.'\meta_description', __NAMESPACE__.'\filter_description', 9);
function filter_description( $description ) {

	// strip shortcodes ...
	$description = strip_shortcodes($description);

	// grab all content up to a readmore stop ...
	if ( preg_match('/(^.*)<!--more-->/s', $description, $matches) ) {
		$description = trim(end($matches));
		$description = wp_strip_all_tags($description);
		$description = preg_replace(array('/\r|\t|\b/', '/\n{2,}/'), array('', "\n\n"), $description);
	}

	// grab the first full paragraph of content ...
	else {
		$description = wp_strip_all_tags($description);
		$description = preg_split('/[\r\n|\n|\r]{2,}/', $description, -1, PREG_SPLIT_NO_EMPTY);
		$description = array_map('trim', $description);
		$description = array_filter($description);
		$description = array_shift($description);
	}

	// shorten description if necessary ...
	if ( strlen($description) > 240 ) $description = substr($description, 0, 240) . '…';

	// no description ? use site name ...
	elseif ( empty($description) ) $description = get_bloginfo('name');

	return $description;
}


/**
 * Default generator for the tags from the meta array.
 *
 * @since 1.0
 *
 * @return string
 */
add_filter(__NAMESPACE__.'\meta_string', __NAMESPACE__.'\filter_meta_string', 9, 5);
function filter_meta_string( $op = '', $key = '', $value = '', $index = 0, $meta = array() ) {

	// dismiss tags ...
	if ( preg_match('/^(image_(width|height|type)|video_(width|height|duration|type)|audio_(type|duration|artist|album))$/', $key) ) {
		// render nothing ...
	}

	// video ...
	elseif ( $key == 'video_src' ) {

		// add og:video ...
		$op .= sprintf(
			'<meta property="og:video" content="%s"/>' . PHP_EOL
			. '<meta property="og:video:url" content="%s"/>' . PHP_EOL,
			esc_attr($value),
			esc_attr($value)
		);

		// add secure url ?
		if ( strpos($value, 'https:') === 0 ) $op .= sprintf('<meta property="og:video:secure_url" content="%s"/>' . PHP_EOL, esc_attr($value));

		// add video attributes ...
		if ( $value = $meta['video_type'][ $index ] ) $op .= sprintf('<meta property="og:video:type" content="%s"/>' . PHP_EOL, esc_attr($value));
		if ( $value = $meta['video_width'][ $index ] ) $op .= sprintf('<meta property="og:video:width" content="%s"/>' . PHP_EOL, esc_attr($value));
		if ( $value = $meta['video_height'][ $index ] ) $op .= sprintf('<meta property="og:video:height" content="%s"/>' . PHP_EOL, esc_attr($value));
		if ( $value = $meta['video_duration'][ $index ] ) $op .= sprintf('<meta property="og:video:duration" content="%s"/>' . PHP_EOL, esc_attr($value));

	}

	// audio ...
	elseif ( $key == 'audio_src' ) {

		// add og:music ...
		$op .= sprintf(
			'<meta property="og:audio" content="%s"/>' . PHP_EOL
			. '<meta property="og:audio:url" content="%s"/>' . PHP_EOL,
			esc_attr($value),
			esc_attr($value)
		);

		// add secure url ?
		if ( strpos($value, 'https:') === 0 ) $op .= sprintf('<meta property="og:audio:secure_url" content="%s"/>' . PHP_EOL, esc_attr($value));

		// add audio attributes ...
		if ( $value = $meta['audio_type'][ $index ] ) $op .= sprintf('<meta property="og:audio:type" content="%s"/>' . PHP_EOL, esc_attr($value));
		if ( $value = $meta['audio_duration'][ $index ] ) $op .= sprintf('<meta property="music:duration" content="%s"/>' . PHP_EOL, esc_attr($value));
		if ( $value = $meta['audio_artist'][ $index ] ) $op .= sprintf('<meta property="music:musician" content="%s"/>' . PHP_EOL, esc_attr($value));
		if ( $value = $meta['audio_album'][ $index ] ) $op .= sprintf('<meta property="music:album" content="%s"/>' . PHP_EOL, esc_attr($value));

	}

	// images ...
	elseif ( $key == 'image_src' ) {

		// determine properties ...
		$width = $meta['image_width'][ $index ];
		$height = $meta['image_height'][ $index ];
		$type = $meta['image_type'][ $index ];

		// add rel link ...
		$op = sprintf(
			'<link rel="%s" href="%s"%s%s/>' . PHP_EOL,
			esc_attr($key),
			esc_attr($value),
			( $type ? ' type="' . esc_attr($type) . '"' : '' ),
			( $width && $height ? sprintf(' sizes="%dx%d"', $height, $width) : '' ) // Seem backwards? It's not! @see https://www.w3schools.com/tags/att_link_sizes.asp
		);

		// add og:image ...
		$op .= sprintf(
			'<meta property="og:image" content="%s"/>' . PHP_EOL
			. '<meta property="og:image:url" content="%s"/>' . PHP_EOL,
			esc_attr($value),
			esc_attr($value)
		);

		// add secure url ?
		if ( strpos($value, 'https:') === 0 ) $op .= sprintf('<meta property="og:image:secure_url" content="%s"/>' . PHP_EOL, esc_attr($value));

		// add og:image:type ...
		if ( $type ) {
			$op .= sprintf(
				'<meta property="og:image:type" content="%s"/>' . PHP_EOL,
				esc_attr($type)
			);
		}

		// og:image dims follow the og: image ...
		if ( $width ) {
			$op .= sprintf(
				'<meta property="og:image:width" content="%d"/>' . PHP_EOL,
				$width
			);
		}

		if ( $height ) {
			$op .= sprintf(
				'<meta property="og:image:height" content="%d"/>' . PHP_EOL,
				$height
			);
		}

	}

	// use 'link' tag ...
	elseif ( preg_match('/^link:(.*)$/', $key, $matches) ) {
		$key = end($matches);
		$op = sprintf('<link rel="%s" href="%s"/>' . PHP_EOL, esc_attr($key), esc_attr($value));
	}

	// use 'itemprop' attribute ...
	elseif ( preg_match('/^item:(.*)$/', $key, $matches) ) {
		$key = end($matches);
		$op = sprintf('<meta itemprop="%s" content="%s"/>' . PHP_EOL, esc_attr($key), esc_attr($value));
	}

	// use 'property' attribute ...
	elseif ( preg_match('/^property:(.*)$/', $key, $matches) ) {
		$key = end($matches);
		$op = sprintf('<meta property="%s" content="%s"/>' . PHP_EOL, esc_attr($key), esc_attr($value));
	}

	// use 'property' attribute ...
	elseif ( preg_match('/^(fb|og|article|profile):/', $key) ) {
		$op = sprintf('<meta property="%s" content="%s"/>' . PHP_EOL, esc_attr($key), esc_attr($value));
	}

	// normal meta tag ...
	else {
		$op = sprintf('<meta name="%s" content="%s"/>' . PHP_EOL, esc_attr($key), esc_attr($value));
	}

	return $op;
}


/**
 * Initialize plugin functionality.
 */
add_action('init', __NAMESPACE__.'\action_init', 99);
function action_init() {

	// managing the feeds ?
	//if ( namespace\option_get('feeds') ) {

		// ensure theme support for links ...
		add_theme_support('automatic-feed-links');

		// remove feed links, we will call these functions directly in render_meta() w/arguments
		remove_action('wp_head', 'feed_links', 2);
		remove_action('wp_head', 'feed_links_extra', 3);

		// render global posts feed ?
		add_filter('feed_links_show_posts_feed', __NAMESPACE__.'\filter_feed_global', 99);

		// render global comments feed ?
		add_filter('feed_links_show_comments_feed', __NAMESPACE__.'\filter_feed_global_comments', 99);

		// render local (extra) feeds ?
		add_filter(__NAMESPACE__.'\feed_local', __NAMESPACE__.'\filter_feed_local', 99);

	//}

}


/**
 * Return feed name from type.
 *
 * @since 1.5
 *
 * @param string $feed
 *	- feed type id
 *
 * @return string
 */
function feed_name( $feed = null ) {
	if ( empty($feed) ) $feed = get_default_feed();
	$feeds = namespace\feed_names();
	return array_key_exists($feed, $feeds) ? $feeds[$feed] : '';
}


/**
 * Feed type:name array ...
 *
 * @since 1.5
 *
 * @return array
 */
function feed_names() {

	static $feeds;
	/**
	 * Filter feed type:names to render.
	 *
	 * @since 1.5
	 *
	 * @param array $feed_types
	 *	- array of feed types, ex. rss, atom, rdf, etc.
	 */
	if ( !isset($feeds) ) $feeds = apply_filters(__NAMESPACE__.'\feed_names', array(
		//'rss'	=> 'RSS 0.92',
		'rss2'	=> __('RSS 2.0', 'bmc-simply-shareable'),
		'atom'	=> __('ATOM 1.0', 'bmc-simply-shareable'),
		'rdf'	=> __('RDF/RSS 1.0', 'bmc-simply-shareable')
	));

	return $feeds;
}


/**
 * Feed types array reference.
 *
 * @since 1.5
 *
 * @return array $feed_types
 */
function &feed_types() {
	static $feed_types;
	if ( !isset($feed_types) ) $feed_types = namespace\option_get('feed_types');
	return $feed_types;
}


/**
 * Filter temporarily engaged by render_meta() to return current of a list of feed types being cycled through.
 *
 * @since 1.5
 *
 * @param string $feed
 * @return string
 */
function filter_default_feed( $feed ){

	// get current feed type ...
	$feed_types =& namespace\feed_types();
	if ( $feed_type = current($feed_types) ) return $feed_type;

	// return default feed ...
	return $feed;

}


/**
 * Filter temporarily engaged to append feed type to urls.
 * @note WordPress is supposed to do this but due to the way it's setup $feed == get_default_feed() is always true because the passed value $feed = get_default_feed() to begin with, so WP resolves it to the default rss2 regardless of what we've temporarily set the default feed value to.
 *
 * @since 1.5
 *
 * @param string $url
 *	- the feed url that ends in /feed/
 * @param string $feed
 *	- completely useless in this context, WordPress only passes an empty string it's mistakenly resolved earlier as noted.
 * @return string
 *	- proper feed url
 */
function filter_feed_link( $url, $feed = 'rss' ) {
	return $url . get_default_feed() . '/';
}


/**
 * Whether or not to render the global posts feed.
 * @see feed_links() for when this filter is applied.
 *
 * @since 1.5
 *
 * @param bool $render
 * @return bool
 */
function filter_feed_global( $render ) {
	return in_array('posts', (array) namespace\option_get('feed_global'));
}


/**
 * Whether or not to render the global comments feed.
 * @see feed_links() for when this filter is applied.
 *
 * @since 1.5
 *
 * @param bool $render
 * @return bool
 */
function filter_feed_global_comments( $render ) {
	return in_array('comments', (array) namespace\option_get('feed_global'));
}


/**
 * Whether or not to render a local (extra) feed.
 * @see feed_links_extra() for how this is carried out.
 *
 * @since 1.5
 *
 * @param bool $render
 * @return $render
 */
function filter_feed_local( $render ) {

	$feeds = (array) namespace\option_get('feed_local');

	return (
		// post comments feed ...
		( is_singular() && in_array('comments', $feeds) )
		// post type archive feed ...
		|| ( is_post_type_archive() && in_array('posts', $feeds) )
		// category, tag, or other taxonomy feed ...
		|| ( ( is_category() || is_tag() || is_tax() ) && in_array('terms', $feeds) )
		// author posts feed ...
		|| ( is_author() && in_array('author', $feeds) )
		// searched posts feed ...
		|| ( is_search() && in_array('search', $feeds) )
	);
}


/**
 * Output to the page head if status code is in 200 range.
 *
 * @since 1.0
 */
add_action('wp_head', __NAMESPACE__.'\action_head');
function action_head() {

	/**
	 * To output meta tags in the page header ?
	 *
	 * @since 1.4
	 *
	 * @param bool $render
	 * @return bool
	 *	- output meta if true, not if false
	 */
	if ( apply_filters(__NAMESPACE__.'\render_meta', (
		intval(http_response_code()/100) == 2
	)) ) echo PHP_EOL, namespace\render_meta(), PHP_EOL;

}


/**
 * Ammend the Wordpress language_attributes w/additional attributes and microdata ...
 * @see https://developer.wordpress.org/reference/hooks/language_attributes/
 *
 * @since 1.1
 * @since 1.4
 *	- adds both xmlns and prefix attributes regardless of doctype
 *
 * @param string $op
 * @return string
 */
add_filter('language_attributes', __NAMESPACE__.'\filter_html_attributes', 99, 2);
function filter_html_attributes( $op, $doctype = 'html' ) {

	// not applicable to admin panel ...
	if ( is_admin() ) return $op;

	// modify attribute subroutine ...
	$modify_attribute = function( &$str, $key, $value, $amend = false ) {

		$regexp = '/(' . preg_quote($key, '/') . ')\s*=\s*(\'|")(.*?)\2/i';

		// attribute present ? replace/amend ...
		if ( preg_match($regexp, $str, $matches) ) {

			$replacement = esc_attr($matches[1]) . '="';

			if ( $amend ) $replacement .= $matches[3] . $amend;

			$replacement .= esc_attr($value) . '"';

			$str = preg_replace($regexp, $replacement, $str);

		}

		// not present ? add to string ...
		else {
			$str .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
		}

	};

	/**
	 * Filter namespaces/prefixes to add to <html> element ...
	 *
	 * @since 1.4
	 *
	 * @param array
	 *	- [ prefix => uri ] associative array of namespaces.
	 * @return array
	 */
	$namespaces = apply_filters(__NAMESPACE__.'\namespaces', array(
		'og'		=> 'http://ogp.me/ns#',
		'fb'		=> 'http://www.facebook.com/2008/fbml',
		'profile'	=> 'http://ogp.me/ns/profile#',
		'article'	=> 'http://ogp.me/ns/article#',
		'music'		=> 'http://ogp.me/ns/music#',
		'video'		=> 'http://ogp.me/ns/video#'
	));

	// prefixes derived from namespaces to add ...
	$prefixes = array();

	// add xml namespace ...
	$modify_attribute($op, 'xmlns', 'http://www.w3.org/1999/xhtml');

	// process namespaces ...
	foreach ( $namespaces as $ns => $uri ) {

		// add xmlns: attribute ...
		$modify_attribute($op, 'xmlns:' . $ns, $uri);

		// add prefix if one doesn't already exist ...
		$prefix = $ns . ': ';
		if ( stripos($op, $prefix) === false ) $prefixes[] = $prefix . $uri;

	}

	// add/ammend prefixes ...
	if ( $prefixes ) $modify_attribute($op, 'prefix', implode(' ', $prefixes), ' ');

	/**
	 * schema.org microdata (used by Google+, Pinterest) ...
	 * @see https://developers.google.com/+/web/snippet/
	 * @todo Set via Post Type and have global setting ...
	 */
	if ( stripos($op, 'itemscope') === false ) $op .= ' itemscope';

	// open itemtype attribute ...
	$uri = 'http://schema.org/';

	// determine type ...
	if ( is_attachment() ) {
		if ( wp_attachment_is('image') ) $type = 'ImageObject';
		elseif ( wp_attachment_is('audio') ) $type = 'AudioObject';
		elseif ( wp_attachment_is('video') ) $type = 'VideoObject';
		else $type = 'MediaObject';
	}
	elseif ( is_author() ) {
		$type = 'ProfilePage';
	}
	elseif ( is_archive() ) {
		$type = 'CollectionPage';
	}
	elseif ( is_search() ) {
		$type = 'SearchResultsPage';
	}
	elseif ( is_single() ) {
		$type = 'Article';
	}
	else {
		$type = 'WebPage';
	}

	/**
	 * Filter schema microdata itemtype.
	 *
	 * @since 1.4
	 *
	 * @param string $type
	 *	- object type as defined by schema.org
	 * @return string
	 */
	$uri .= apply_filters(__NAMESPACE__.'\itemtype', $type);

	// modify itemtype ...
	$modify_attribute($op, 'itemtype', $uri);

	return $op;
}


/**
 * Register general settings fields and admin side filters, actions.
 *
 * @since 1.0
 * @since 1.4
 *	- added deactivation on versions check fail, must be in this hook for deactivate_plugins() function to exist.
 */
add_action('admin_init', __NAMESPACE__.'\action_admin_init');
function action_admin_init() {

	// version check ...
	if (
		// require PHP 5.4+
		version_compare(phpversion(), '5.4', '<')
		// require WP 4.4+ (supporting embeds forward)
		|| version_compare($GLOBALS['wp_version'], '4.4', '<')
	) {
		trigger_error("Simply Shareable plugin requires PHP 5.4 and WordPress 4.4 or better! It has been disabled until these conditions are met. Sorry.", E_USER_NOTICE);
		deactivate_plugins(plugin_basename(__FILE__));
		return;
	}

	// add site settings ...
    add_settings_section(
        'bmc_simply_shareable_section_general', // Section ID
        __('Simply Shareable', 'bmc-simply-shareable') . sprintf('<a name="%s"></a>', namespace\plugin('TextDomain')), // Section Title
        __NAMESPACE__.'\section_general', // Callback
        'general' // What Page?  This makes the section show up on the General Settings Page
    );

	$settings = array(
		'site_twitter' 			=> __('Site Twitter', 'bmc-simply-shareable'),
		'whitelist_twitter'		=> __('Twitter Whitelisted', 'bmc-simply-shareable'),
		'site_facebook'			=> __('Site Facebook', 'bmc-simply-shareable'),
		'site_facebook_appid' 	=> __('Facebook App ID', 'bmc-simply-shareable'),
		//'whitelist_facebook'	=> __('Facebook Whitelisted', 'bmc-simply-shareable'),
		'site_google_verify' 	=> __('Google Verify', 'bmc-simply-shareable'),
		//'feeds'					=> __('Feed Links', 'bmc-simply-shareable'),
		'cache'					=> __('Cache', 'bmc-simply-shareable'),
		'uninstall_data'		=> __('Uninstall Data', 'bmc-simply-shareable')
	);

	while ( list($id, $label) = each($settings) ) {

		add_settings_field(
			$id, // option id ...
			$label, // label ...
			__NAMESPACE__.'\setting_'.$id, // callback ...
			'general', // page ...
			'bmc_simply_shareable_section_general', // section ...
			[ 'label_for' => $id ] // args ...
		);

	}

    register_setting('general', namespace\option_key());


	// add feed settings ?
	//if ( namespace\option_get('feeds') ) {

		add_settings_section(
			'bmc_simply_shareable_section_reading',
			__('Syndication Feeds', 'bmc-simply-shareable') . sprintf('<a name="%s"></a>', namespace\plugin('TextDomain')), // Section Title
			__NAMESPACE__.'\section_reading',
			'reading'
		);

		$settings = array(
			'feed_types'	=> __('Feed Formats', 'bmc-simply-shareable'),
			'feed_global'	=> __('General Feeds', 'bmc-simply-shareable'),
			'feed_local'	=> __('Specific Feeds', 'bmc-simply-shareable')
		);

		while ( list($id, $label) = each($settings) ) {

			add_settings_field(
				$id, // option id ...
				$label, // label ...
				__NAMESPACE__.'\setting_'.$id, // callback ...
				'reading', // page ...
				'bmc_simply_shareable_section_reading', // section ...
				[ 'label_for' => $id ] // args ...
			);

		}

		register_setting('reading', namespace\option_key());

	//}

}






/**
 * General options section.
 *
 * @since 1.0
 */
function section_general() {

	echo '<p>',
	sprintf(
		wp_kses( __('Site settings for <a href="%1$s">%2$s plugin</a>.', 'bmc-simply-shareable'), array( 'a' => array( 'href' => array() ) ) ),
		esc_url(admin_url( 'plugins.php#' . namespace\plugin('TextDomain') )),
		namespace\plugin('Name')
	),
	'</p>';

}


/**
 * Site Twitter setting.
 *
 * @since 1.0
 */
function setting_site_twitter( $args ) {

	printf(
		'<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text code"/><p id="%s-description" class="description">%s</p>',
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		esc_attr(namespace\option_get($args['label_for'])),
		$args['label_for'],
		sprintf(
			wp_kses(
				__('Sets value of <code>twitter:site</code> meta tag required by <a href="%1$s" target="_blank">Twitter Cards</a>. Should be in the form of <code>https://twitter.com/USERNAME</code>, <code>@USERNAME</code> or <code>USERNAME</code>.', 'bmc-simply-shareable'),
				array(
					'a' => array( 'href' => array(), 'target' => array() ),
					'code' => array()
				)
			),
			esc_url('https://dev.twitter.com/cards/types/summary')
		)
	);

}

/**
 * Site Facebook setting.
 *
 * @since 1.0
 */
function setting_site_facebook( $args ) {

	printf(
		'<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text code"/><p id="%s-description" class="description">%s</p>',
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		esc_attr(namespace\option_get($args['label_for'])),
		$args['label_for'],
		sprintf(
			wp_kses(
				__('Sets value of <code>article:publisher</code> meta tag required by <a href="%1$s" target="_blank">Facebook Open Graph</a>. Should be in the form of <code>https://www.facebook.com/USERNAME</code> or <code>USERNAME</code>.', 'bmc-simply-shareable'),
				array(
					'a' => array( 'href' => array(), 'target' => array() ),
					'code' => array()
				)
			),
			esc_url('https://developers.facebook.com/docs/reference/opengraph/object-type/article')
		)
	);

}

/**
 * Site Google Verification
 * @see https://managewp.com/wordpress-google-plus-authorship
 *
 * @since 1.1
 */
function setting_site_google_verify( $args ) {

	printf(
		'<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text code"/><p id="%s-description" class="description">%s</p>',
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		esc_attr(namespace\option_get($args['label_for'])),
		$args['label_for'],
		sprintf(
			wp_kses(
				__('Google verify token from <a href="%1$s" target="_blank">Search Console > Alternate Methods > Html Tag</a>. This connects Google+ profiles with shared articles.', 'bmc-simply-shareable'),
				array(
					'a' => array( 'href' => array(), 'target' => array() )
				)
			),
			esc_url('https://www.google.com/webmasters/tools/home')
		)
	);

}

/**
 * Site Facebook app id. for the fb:app_id meta ...
 *
 * @since 1.4
 */
function setting_site_facebook_appid( $args ) {

	printf(
		'<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text code"/><p id="%s-description" class="description">%s</p>',
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		esc_attr(namespace\option_get($args['label_for'])),
		$args['label_for'],
		sprintf(
			wp_kses(
				__('<a href="%s" target="_blank">Facebook App ID</a> that links your site with Facebook social plugins, analytics and other services.', 'bmc-simply-shareable'),
				array(
					'a' => array( 'href' => array(), 'target' => array() )
				)
			),
			esc_url('https://developers.facebook.com/')
		)
	);

}


/**
 * Manage feeds.
 *
 * @since 1.5
 */
function setting_feeds( $args ) {

	printf(
		'<input type="hidden" name="%s[%s]" value="0"/><label for="%s"><input type="checkbox" id="%s" name="%s[%s]" value="1"%s/> %s</label><p id="%s-description" class="description">%s</p>',
		namespace\option_key(),
		$args['label_for'],
		$args['label_for'],
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		( namespace\option_get($args['label_for']) ? ' checked="checked"' : '' ),
		__('Manage syndicated news feeds. (RSS, ATOM, etc.)', 'bmc-simply-shareable'),
		$args['label_for'],
		wp_kses(
			sprintf(
				__('Additional feed settings can be found under the <a href="%1$s">%2$s</a> admin page when enabled.', 'bmc-simply-shareable'),
				esc_url(admin_url('options-reading.php#' . namespace\plugin('TextDomain') )),
				__('Reading')
			),
			array(
				'a' => array( 'href' => array() )
			)
		)
	);

}


/**
 * Cache toggle settings.
 *
 * @since 1.4
 */
function setting_cache( $args ) {

	printf(
		'<input type="hidden" name="%s[%s]" value="0"/><label for="%s"><input type="checkbox" id="%s" name="%s[%s]" value="1"%s/> %s</label><p id="%s-description" class="description">%s</p>',
		namespace\option_key(),
		$args['label_for'],
		$args['label_for'],
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		( \WP_DEBUG ? ' disabled="disabled"' : ( namespace\option_get($args['label_for']) ? ' checked="checked"' : '' ) ),
		__('Reuse cached generated meta tags to improve load time?', 'bmc-simply-shareable'),
		$args['label_for'],
		wp_kses(
			__('Overridden to not cache if <code>WP_DEBUG</code> is set to true in <code>wp-config.php</code>.', 'bmc-simply-shareable'),
			array(
				'code' => array()
			)
		)
	);

}


/**
 * Uninstall data toggle settings.
 *
 * @since 1.4
 */
function setting_uninstall_data( $args ) {

	printf(
		'<input type="hidden" name="%s[%s]" value="0"/><label for="%s"><input type="checkbox" id="%s" name="%s[%s]" value="1"%s/> %s</label><p id="%s-description" class="description">%s</p>',
		namespace\option_key(),
		$args['label_for'],
		$args['label_for'],
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		( namespace\option_get($args['label_for']) ? ' checked="checked"' : '' ),
		sprintf(
			__('Delete data upon uninstalling %s?', 'bmc-simply-shareable'),
			namespace\plugin('Name')
		),
		$args['label_for'],
		__('Check if you don\'t plan on reactivating this plugin again.', 'bmc-simply-shareable')
	);

}

/**
 * Whitelist Twitter?
 *
 * @since 1.4
 */
function setting_whitelist_twitter( $args ) {

	printf(
		'<input type="hidden" name="%s[%s]" value="0"/><label for="%s"><input type="checkbox" id="%s" name="%s[%s]" value="1"%s/> %s</label><p id="%s-description" class="description">%s</p>',
		namespace\option_key(),
		$args['label_for'],
		$args['label_for'],
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		( is_ssl() ? ( namespace\option_get($args['label_for']) ? ' checked="checked"' : '' ) : ' disabled="disabled"' ),
		__('Approved (or seeking approval) to share video on Twitter?', 'bmc-simply-shareable'),
		$args['label_for'],
		wp_kses(
			( is_ssl() ? __('<a href="https://dev.twitter.com/cards/types/player" target="_blank">Video requirements must be met and approved</a> before video hosted on your site can be shared on Twitter.', 'bmc-simply-shareable') : __('Twitter <a href="https://dev.twitter.com/cards/types/player" target="_blank">requires SSL</a> for video to be shared from your site.', 'bmc-simply-shareable') ),
			array(
				'a' => array(
					'href' => array(),
					'target' => array()
				)
			)
		)
	);

}


/**
 * Whitelist Facebook?
 *
 * @since 1.4
 */
function setting_whitelist_facebook( $args ) {

	printf(
		'<input type="hidden" name="%s[%s]" value="0"/><label for="%s"><input type="checkbox" id="%s" name="%s[%s]" value="1"%s/> %s</label><p id="%s-description" class="description">%s</p>',
		namespace\option_key(),
		$args['label_for'],
		$args['label_for'],
		$args['label_for'],
		namespace\option_key(),
		$args['label_for'],
		' disabled="disabled"',	// always disabled until Facebook changes it's policy ...
		__('Approved to share audio on Facebook?', 'bmc-simply-shareable'),
		$args['label_for'],
		wp_kses(
			__('Only <a href="https://developers.facebook.com/docs/opengraph/music/" target="_blank">select vendors are permitted to share music</a> on Facebook. No approval process is currently available.', 'bmc-simply-shareable'),
			array(
				'a' => array(
					'href' => array(),
					'target' => array()
				)
			)
		)
	);

}


/**
 * Syndicated feed link options.
 *
 * @since 1.5
 */
function section_reading() {

	echo '<p>',
	sprintf(
		wp_kses( __('Feed settings provided by <a href="%1$s">%2$s plugin</a>.', 'bmc-simply-shareable'), array( 'a' => array( 'href' => array() ) ) ),
		esc_url(admin_url( 'plugins.php#' . namespace\plugin('TextDomain') )),
		namespace\plugin('Name')
	),
	'</p>';

}


/**
 * Manage feed types.
 *
 * @since 1.5
 */
function setting_feed_types( $args ) {

	$feeds = namespace\feed_names();
	$feed_types = namespace\option_get('feed_types');

	// need at least one empty value to ensure an array is saved ...
	printf(
		'<input type="hidden" name="%s[%s][]" value=""/>',
		namespace\option_key(),
		$args['label_for']
	);

	foreach ( $feeds as $feed => $name ) {

		printf(
			'<p><label for="%s-%s"><input type="checkbox" id="%s-%s" name="%s[%s][]" value="%s"%s/> %s</label></p>',
			$args['label_for'],
			$feed,
			$args['label_for'],
			$feed,
			namespace\option_key(),
			$args['label_for'],
			$feed,
			( in_array($feed, $feed_types) ? ' checked="checked"' : '' ),
			$name
		);

	}

}


/**
 * Manage global feeds (aka. feed_links).
 *
 * @since 1.5
 */
function setting_feed_global( $args ) {

	/**
	 * Filter global feeds available.
	 *
	 * @since 1.5
	 *
	 * @param array $feeds
	 *	- array of type:name's ...
	 * @return array
	 */
	$feeds = apply_filters(__NAMESPACE__.'\global_feeds', array(
		'posts'		=> __('Posts Feed', 'bmc-simply-shareable'),
		'comments'	=> __('Comments Feed', 'bmc-simply-shareable')
	));

	$feed_global = namespace\option_get('feed_global');

	// need at least one empty value to ensure an array is saved ...
	printf(
		'<input type="hidden" name="%s[%s][]" value=""/>',
		namespace\option_key(),
		$args['label_for']
	);

	foreach ( $feeds as $feed => $name ) {

		printf(
			'<p><label for="%s-%s"><input type="checkbox" id="%s-%s" name="%s[%s][]" value="%s"%s/> %s</label></p>',
			$args['label_for'],
			$feed,
			$args['label_for'],
			$feed,
			namespace\option_key(),
			$args['label_for'],
			$feed,
			( in_array($feed, $feed_global) ? ' checked="checked"' : '' ),
			$name
		);

	}

}


/**
 * Manage local feeds (aka. feed_links_extra).
 *
 * @since 1.5
 */
function setting_feed_local( $args ) {

	/**
	 * Filter local feeds available.
	 *
	 * @since 1.5
	 *
	 * @param array $feeds
	 *	- array of type:name's ...
	 * @return array
	 */
	$feeds = apply_filters(__NAMESPACE__.'\local_feeds', array(
		'comments'	=> __('Post Comments Feeds', 'bmc-simply-shareable'),
		'posts'		=> __('Posts Archive Feeds', 'bmc-simply-shareable'),
		'terms'		=> __('Category, Tags and Taxonomy Feeds', 'bmc-simply-shareable'),
		'author'	=> __('Author Posts Feeds', 'bmc-simply-shareable'),
		'search'	=> __('Search Results Feeds', 'bmc-simply-shareable')
	));

	$feed_local = namespace\option_get('feed_local');

	// need at least one empty value to ensure an array is saved ...
	printf(
		'<input type="hidden" name="%s[%s][]" value=""/>',
		namespace\option_key(),
		$args['label_for']
	);

	foreach ( $feeds as $feed => $name ) {

		printf(
			'<p><label for="%s-%s"><input type="checkbox" id="%s-%s" name="%s[%s][]" value="%s"%s/> %s</label></p>',
			$args['label_for'],
			$feed,
			$args['label_for'],
			$feed,
			namespace\option_key(),
			$args['label_for'],
			$feed,
			( in_array($feed, $feed_local) ? ' checked="checked"' : '' ),
			$name
		);

	}

}


/**
 * Ensure Twitter, Facebook user contact method exists in user profile.
 * @see https://davidwalsh.name/add-profile-fields
 *
 * @since 1.0
 *
 * @param array $fields
 */
add_filter('user_contactmethods', __NAMESPACE__.'\filter_contact_methods');
function filter_contact_methods( $fields ) {
	$fields['twitter'] = __('Twitter', 'bmc-simply-shareable');
	$fields['facebook'] = __('Facebook', 'bmc-simply-shareable');
	$fields['google'] = __('Google Plus', 'bmc-simply-shareable');
	return $fields;
}


/**
 * Add plugin description links. (under the plugin description)
 *
 * @since 1.0
 *
 * @param array $links
 * @param string $file Basename of plugin file.
 * @return array
 */
//add_filter('plugin_row_meta', __NAMESPACE__.'\filter_plugin_description_links', 10, 2);
function filter_plugin_description_links( $links, $file = '' ) {

	if ( plugin_basename(__FILE__) == $file ) {

		$links = array_merge($links, array(

			'documentation' => sprintf(
				'<a class="thickbox" href="%s">%s</a>',
				plugins_url('README.html?TB_iframe=true&version='.namespace\plugin('Version'), __FILE__),
				__('View details')
			)

		));

	}

	return $links;

}


/**
 * Add plugin action links. (under the plugin name)
 *
 * @since 1.4
 * @since 1.5
 *	- added feeds link when enabled
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__.'\filter_plugin_action_links');
function filter_plugin_action_links( $links ) {

	//if ( namespace\option_get('feeds') ) {

		array_unshift($links, sprintf(
			'<a name="%s" href="%s">%s</a>',
			namespace\plugin('TextDomain'),
			esc_url(admin_url( 'options-reading.php#' . namespace\plugin('TextDomain') )),
			__('Feeds')
		));

	//}

	array_unshift($links, sprintf(
		'<a name="%s" href="%s">%s</a>',
		namespace\plugin('TextDomain'),
		esc_url(admin_url( 'options-general.php#' . namespace\plugin('TextDomain') )),
		__('Settings')
	));

	return $links;
}



/**
 * Filter embed template to our player template for video and audio embeds ...
 * @since 1.4
 */
add_filter('embed_template', __NAMESPACE__.'\filter_embed_template', 99);
function filter_embed_template( $template ) {
	if (
		is_attachment()
		&& (
			wp_attachment_is('video')
			|| wp_attachment_is('audio')
		)
	) $template = plugin_dir_path(__FILE__) . 'embed.php';
	return $template;
}


/**
 * Filter meta (cached) input ...
 * @since 1.4
 */
add_filter(__NAMESPACE__.'\meta_input', __NAMESPACE__.'\filter_meta_input', 9);
function filter_meta_input( $input ) {

	// not caching ? return now ...
	if ( !namespace\is_cache() ) return $input;

	// cache value ...
	$value = false;

	// get from post meta ...
	if ( $post = namespace\is_article() ) {
		$value = get_post_meta($post->ID, namespace\cache_key(), true);
	}

	// get from user meta ...
	elseif ( is_author() ) {
		$value = get_user_meta(get_the_author_meta('ID'), namespace\cache_key(), true);
	}

	// get from term meta ...
	elseif ( $term = namespace\is_term() ) {
		$value = get_term_meta($term->term_id, namespace\cache_key(), true);
	}

	// get from transient ...
	elseif ( is_home() ) {
		$value = get_transient(namespace\cache_key());
	}

	// value valid ? return input from value data ...
	if ( namespace\cache_verify($value) ) {
		$input = $value['data'];
	}

	return $input;
}


/**
 * Filter meta (cache) output ...
 * @since 1.4
 */
add_filter(__NAMESPACE__.'\meta_output', __NAMESPACE__.'\filter_meta_output', 99);
function filter_meta_output( $output ) {

	// prepend brand comment ...
	$output = sprintf(
		'<!-- %s %s <%s> / %s <%s> -->',
		namespace\plugin('Name'),
		namespace\plugin('Version'),
		namespace\plugin('PluginURI'),
		namespace\plugin('Author'),
		namespace\plugin('AuthorURI')
	) . PHP_EOL . $output . sprintf(
		'<!-- / %s %s -->',
		namespace\plugin('Name'),
		namespace\plugin('Version')
	) . PHP_EOL;

	// not caching ? return now ...
	if ( !namespace\is_cache() ) return $output;

	// save to post meta ...
	if ( $post = namespace\is_article() ) {
		update_post_meta($post->ID, namespace\cache_key(), namespace\cache_value($output));
	}

	// save to user meta ...
	elseif ( is_author() ) {
		update_user_meta(get_the_author_meta('ID'), namespace\cache_key(), namespace\cache_value($output));
	}

	// save to term meta ...
	elseif ( $term = namespace\is_term() ) {
		update_term_meta($term->term_id, namespace\cache_key(), namespace\cache_value($output));
	}

	// save to transient ...
	elseif ( is_front_page() || is_home() ) {
		$value = namespace\cache_value($output);
		set_transient(namespace\cache_key(), $value, $value['expires']);
	}

	return $output;

}


/**
 * Clean up database on uninstall.
 *
 * @since 1.4
 */
register_uninstall_hook(__FILE__, __NAMESPACE__.'\action_uninstall');
function action_uninstall() {

	global $wpdb;

	// delete post cache ...
	$wpdb->delete(
		$wpdb->prefix.'postmeta',
		array( 'meta_key' => namespace\cache_key() ),
		array( '%s' )
	);

	// delete author cache ...
	$wpdb->delete(
		$wpdb->prefix.'usermeta',
		array( 'meta_key' => namespace\cache_key() ),
		array( '%s' )
	);

	// delete term cache ...
	$wpdb->delete(
		$wpdb->prefix.'termmeta',
		array( 'meta_key' => namespace\cache_key() ),
		array( '%s' )
	);

	// delete options ?
	if ( namespace\option_get('uninstall_data') ) {
		delete_option(namespace\option_key());
	}

}







### Declare self a Burning Moth Creation ###
add_filter('burning_moth_creations',__NAMESPACE__.'\filter_burning_moth');
function filter_burning_moth( $creations ) {
	array_push($creations,__FILE__);
	return $creations;
}



