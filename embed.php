<?php
/**
 * Attachment embed template override.
 * Should conform to twitter player guidelines, be responsive and not use Flash.
 * @see https://dev.twitter.com/cards/types/player
 *
 * @filter embed_template
 * @since 1.4
 */
namespace BMC\SimplyShareable;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php wp_head(); ?>
<style type="text/css">
	html, body { 
		margin: 0px !important; 
		height: 100% !important; 
		overflow: hidden !important; 
	}
	video, audio, .mejs-container, .wp-video { 
		width: 100% !important; 
		height: 100% !important; 
		background-size: cover;
		background-position: 50% 50%;
	}
</style>
</head>
<body <?php body_class(); ?>>
<?php

// setup post data ...
the_post();

// get the meta data ...	
$attachment_meta = wp_get_attachment_metadata(get_the_ID(), true);
	
// get image source, set background image ...
if ( has_post_thumbnail() ) {
	list($attachment_image) = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'full');
	printf('<style type="text/css">video, audio { background-image: url(%s); }</style>', $attachment_image);
}

if ( wp_attachment_is('video') ) {
	
	$attr = [
		'src'		=> wp_get_attachment_url( get_the_ID() ),
		'width'		=> $attachment_meta['width'],
		'height'	=> $attachment_meta['height'],
		'loop'		=> 'off',
		'autoplay'	=> 'off',
		'preload'	=> 'none'
	];
	
	// 10 seconds or less ? set autoplay and loop to on ...
	if ( $attachment_meta['length'] <= 10 ) {
		$attr['autoplay'] = $attr['loop'] = 'on';
	}
	
	// add poster image ...
	if ( isset($attachment_image) ) {
		$attr['poster'] = $attachment_image;
	}
	
	$op = wp_video_shortcode($attr);
	
	// set to muted by default if autoplay is on (no option yet via wordpress) ...
	if ( $attr['autoplay'] == 'on' ) {
		$op = str_replace('<video', '<video muted="muted"', $op);
	}
	
}
	
elseif ( wp_attachment_is('audio') ) {
	$op = wp_audio_shortcode([
		'src'		=> wp_get_attachment_url( get_the_ID() ),
		'preload'	=> 'none'
	]);
}

// output ...
if ( isset($op) ) echo $op;	

// output footer content ...		
wp_footer();

?>
</body>
</html>