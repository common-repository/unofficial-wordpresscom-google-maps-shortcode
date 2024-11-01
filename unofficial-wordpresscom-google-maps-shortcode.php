<?php
/*
Plugin Name: Unofficial WordPress.com Google Maps Shortcode
Plugin URI: http://wpadventures.wordpress.com/plugins/wordpress-com-google-maps-shortcode/
Description: Unofficial plugin to replicate the Google Maps shortcode function on WordPress.com sites. Useful for users who are migrating from WordPress.com to self hosted WordPress.org sites and want their maps to continue working. This feature is explained at http://en.support.wordpress.com/google-maps/
Version: 1.1
Author: fonglh
Author URI: https://wpadventures.wordpress.com
License: GPLv2
*/

/*  Copyright 2011  fonglh  (email : fonglh@msn.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


//run the shortcode before wpautop so the necessary linebreaks are added
//between maps
//code from http://www.viper007bond.com/tag/wpautop/
add_filter( 'the_content', 'flh_wpcomgms_run_shortcode', 7 );

function flh_wpcomgms_run_shortcode( $content ) {
	global $shortcode_tags;

	//Backup registered shortcodes and clear them all out
	$orig_shortcode_tags = $shortcode_tags;
	remove_all_shortcodes();

	add_shortcode( 'googlemaps', 'flh_wpcomgms_shortcode_handler' );

	//do the shortcode (only the one above is registered now)
	$content = do_shortcode( $content );

	//replace original shortcodes
	$shortcode_tags = $orig_shortcode_tags;

	return $content;
}

function flh_wpcomgms_shortcode_handler( $attr ) {
	//sanitize URL
	$safe_url = esc_url( $attr[0] );
	
	//extract the width and height from the URL
	preg_match_all( '/w=(?P<width>\d+).*h=(?P<height>\d+)/', $safe_url, $mapsize );

	//set default values of map width and height
	$width = 425;
	$height = 350;
	//set map width and height if those values are given 
	if( isset( $mapsize[ 'width' ][ 0 ] ) )
		$width = $mapsize[ 'width' ][ 0 ];

	if( isset( $mapsize[ 'height' ][ 0 ] ) )
		$height = $mapsize[ 'height' ][ 0 ];
		
	//replace the shortcode with the Google Maps iframe block
	$output = '<iframe width="' . $width . '" height="' . $height . '" frameborder="0" scrolling="no" ';
	$output .= 'marginheight="0" marginwidth="0" src="';
	$output .= $safe_url;
	$output .= '"></iframe><br />';

	//add the 'view larger map' link
	$safe_url = preg_replace( '!output=embed!', 'source=embed', $safe_url );
	$output .= '<small><a href="' . $safe_url . '" ';
	$output .= '>View Larger Map</a></small>';

	return $output;
}

//filters the data before insertion to the database
//replaces iframe Google Maps code with the [googlemaps] shortcode
add_filter( 'wp_insert_post_data', 'flh_wpcomgms_search_map_code', 99, 2 );

function flh_wpcomgms_search_map_code( $data, $postarr ) {
	$post_content = $data[ 'post_content' ];
	//search for <iframe> google maps code and get the src URLs
	if( preg_match_all( '!<iframe.* src=(?P<mapsrc>.*)></iframe>.*</small>!', $post_content, $matches ) ) {
		$mapsrc = $matches[ 'mapsrc' ];
		$iframes = $matches[ 0 ];			//get all the iframes, will need to check later to see if they're from google maps

		$loop_cnt = 0;
		$googlemaps_sc = array();

		//go through the list of URLs and check if each is a valid Google Map URL
		foreach( $mapsrc as $url ) {
			//remove the 1st and last characters which are the " marks
			$url = substr( $url, 2, -1 );
			//check that src is from google maps
			if ( preg_match( '!https?://maps.google.com!', $url ) ) {
				//get the width and height of the map
				preg_match_all( '/width.{2}"(?P<width>[^"]+?)" height.{2}"(?P<height>[^"]+?)"/', $iframes[ $loop_cnt ], $mapsize );
				//form the shortcode
				$googlemaps_sc[] = '[googlemaps ' . $url . '&amp;w=' . $mapsize[ 'width' ][ 0 ] . '&amp;h=' . $mapsize[ 'height' ][ 0 ] . ']';
			}
			else		//if not, don't process the iframe
				unset( $iframes[ $loop_cnt ] );

			$loop_cnt++;
		}
		$post_content = str_replace( $iframes, $googlemaps_sc, $post_content );
	}
	
	//replace iframe Google Map code with the [googlemaps] shortcode
	$data[ 'post_content' ] = $post_content;
	return $data;
}

