<?php
/*
Plugin Name: FeedWordPress: UNCC Fixes
Plugin URI: https://github.com/clas-web/feedwordpress-uncc-fixes
Description: Includes changes to FeedWordPress to add UNCC fixes, such as thumbnails and other excerpt creation changes.
Version: 1.0
Author: Crystal Barton
Author URI: https://www.linkedin.com/in/crystalbarton
*/


add_action( 'after_setup_theme', 'fwp_uncc_fixes_setup' );
add_filter( 'get_the_excerpt', 'fwp_uncc_fixes_get_the_excerpt' );
add_filter( 'syndicated_post', 'fwp_uncc_fixes_save_post_thumbnail', 2, 2 );
add_filter( 'syndicated_post', 'fwp_uncc_fixes_create_excerpt', 10, 2 );
add_action( 'wp_head', 'fwp_uncc_fixes_build_stylesheet_url' );


/**
 * Remove the Translucence "get_the_excerpt" filter.
 */
if( !function_exists('fwp_uncc_fixes_setup') ):
function fwp_uncc_fixes_setup() 
{
	remove_filter( 'get_the_excerpt', 'translucence_get_the_excerpt' );
}
endif;


/**
 * Add a new "get_the_excerpt" filter to include post thumbnail.
 * @param  string  $excerpt  The current excerpt.
 * @return  string  The modified excerpt.
 */
if( !function_exists('fwp_uncc_fixes_get_the_excerpt') ):
function fwp_uncc_fixes_get_the_excerpt( $excerpt )
{
	global $post;

	$src = get_post_meta($post->ID, 'thumbnail', TRUE);
	if( $src !== '' )
		$excerpt = '<div class="fwp_uncc_thumbnail"><img src="'.$src.'" /></div><div class="fwp_uncc_excerpt">'.$excerpt."</div>";

	if( has_excerpt() )
		return $excerpt.'<a href="'.get_permalink().'" class="continue-reading">'.__( 'More &rarr;', '2010-translucence' ).'</a>';;
		
	if( strlen($output) < strlen($post->post_content) )
		return $excerpt.'&hellip; <a href="'.get_permalink().'" class="continue-reading">'.__( 'More &rarr;', '2010-translucence' ).'</a>';

	return $excerpt;
}
endif;


/**
 * Add "syndicated_post" filter found in the FeedWordPress plugin.
 * Grab the first image in the content and save as the post's thumbnail.
 * @param  WP_Post  $post  The post object.
 * @param  SyndicatedPost  $syndicatedpost  The FeedWordPress syndicated post.
 * @return  WP_Post  The modified post object.
 */
if( !function_exists('fwp_uncc_fixes_save_post_thumbnail') ):
function fwp_uncc_fixes_save_post_thumbnail( $post, $syndicatedpost )
{

	$content = '';
	if (isset($syndicatedpost->item['atom_content'])) :
		$content = $syndicatedpost->item['atom_content'];
	elseif (isset($syndicatedpost->item['xhtml']['body'])) :
		$content = $syndicatedpost->item['xhtml']['body'];
	elseif (isset($syndicatedpost->item['xhtml']['div'])) :
		$content = $syndicatedpost->item['xhtml']['div'];
	elseif (isset($syndicatedpost->item['content']['encoded']) and $syndicatedpost->item['content']['encoded']):
		$content = $syndicatedpost->item['content']['encoded'];
	elseif (isset($syndicatedpost->item['description'])) :
		$content = $syndicatedpost->item['description'];
	endif;

	$blacklist_images = array(
		'feed-icon32x32.png'
	);

	$src = '';
	$matches = NULL;
	$num_matches = preg_match_all("/<img ([^>]+)>/", $content, $matches, PREG_SET_ORDER);
	
	if( ($num_matches !== FALSE) && ($num_matches > 0) )
	{
		for( $i = 0; $i < $num_matches; $i++ )
		{
			$m = NULL;
			
			if( preg_match("/src=\"([^\"]+)\"/", $matches[$i][0], $m) )
				$src = trim($m[1]);
				
			if( empty($src) )
				continue;
				
			for( $j = 0; $j < count($blacklist_images); $j++ )
			{
				if( strpos($src, $blacklist_images[$j]) !== FALSE )
				{
					$src = '';
					break;
				}
			}
			
			break;
		}
	}
	
	if( ! empty($src) )
	{
		// determine if the source is relative.
		if( 1 !== preg_match('/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w-_]*)?\??(?:[-\+=&;%@.\w_]*)#?(?:[\w]*))?)/', $src) )
		{
			if( $src[0] == '.' )
				$src = $post['meta']['syndication_source_uri'].'/'.$src;
			else if( $src[0] == '/' || $src[0] == '\\' )
				$src = $post['meta']['syndication_source_uri'].$src;
			else
				$src = $post['meta']['syndication_source_uri'].'/'.$src;
		}
		$post['meta']['thumbnail'] = $src;
	}
	
	return $post;
}
endif;


/**
 * Add "syndicated_post" filter found in the FeedWordPress plugin.
 * Create a custom excerpt for the FeedWordPress post.
 * -- Copied and altered from Advanced Excerpt plugin --
 * @param  WP_Post  $post  The post object.
 * @param  SyndicatedPost  $syndicatedpost  The FeedWordPress syndicated post.
 * @return  WP_Post  The modified post object.
 */
if( !function_exists('fwp_uncc_fixes_create_excerpt') ):
function fwp_uncc_fixes_create_excerpt($post, $syndicatedpost)
{

	$allowed_tags = array(
		'p',
		'b',
		'i',
		'u',
		'strong',
		'em',
		'br',
		'blockquote',
		'pre',
		'code'
	);

	$content = '';
	if (isset($syndicatedpost->item['atom_content'])) :
		$content = $syndicatedpost->item['atom_content'];
	elseif (isset($syndicatedpost->item['xhtml']['body'])) :
		$content = $syndicatedpost->item['xhtml']['body'];
	elseif (isset($syndicatedpost->item['xhtml']['div'])) :
		$content = $syndicatedpost->item['xhtml']['div'];
	elseif (isset($syndicatedpost->item['content']['encoded']) and $syndicatedpost->item['content']['encoded']):
		$content = $syndicatedpost->item['content']['encoded'];
	elseif (isset($syndicatedpost->item['description'])) :
		$content = $syndicatedpost->item['description'];
	endif;

	// From the default wp_trim_excerpt():
	// Some kind of precaution against malformed CDATA in RSS feeds I suppose
	$content = str_replace(']]>', ']]&gt;', $content);

	// Strip HTML if allow-all is not set
	if (!in_array('_all', $allowed_tags))
	{
		if (count($allowed_tags) > 0)
			$tag_string = '<' . implode('><', $allowed_tags) . '>';
		else
			$tag_string = '';
		$content = strip_tags($content, $tag_string);
	}
      
	$tokens = array();
	$out = '';
	$w = 0;
	$length = 400;
	$finish_sentence = FALSE;
	$finish_word = TRUE;
      
	// Divide the string into tokens; HTML tags, or words, followed by any whitespace
    // (<[^>]+>|[^<>\s]+\s*)
    preg_match_all('/(<[^>]+>|[^<>\s]+)\s*/u', $content, $tokens);

	// Parse each token
	foreach ($tokens[0] as $t)
	{
		// Limit reached
		if ($w >= $length && !$finish_sentence)
		{
			break;
		}
		
        // Token is not a tag
        if ($t[0] != '<')
        {
        	// Limit reached, continue until ? . or ! occur at the end
        	if ($w >= $length && $finish_sentence && preg_match('/[\?\.\!]\s*$/uS', $t) == 1)
			{
				$out .= trim($t);
				break;
			}
          
			if (1 == $use_words)
			{ // Count words
				$w++;
			}
			else
			{ // Count/trim characters
				$chars = trim($t); // Remove surrounding space
				$c = strlen($chars);
				if ($c + $w > $length && !$finish_sentence)
				{ // Token is too long
					$c = ($finish_word) ? $c : $length - $w; // Keep token to finish word
					$t = substr($t, 0, $c);
				}
				$w += $c;
			}
		}

		$out .= $t;
	}
	
	// Append what's left of the token
    $out = trim($out).'...';
    $out = force_balance_tags($out);
    
    $post['post_excerpt'] = $out;
    return $post;
}
endif;


/**
 * Add the plugin's custom stylesheet.
 */
if( !function_exists('fwp_uncc_fixes_build_stylesheet_url') ):
function fwp_uncc_fixes_build_stylesheet_url()
{
    echo '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'styles.css" />';
}
endif;

