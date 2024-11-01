<?php
/**
 * @package YD_Wordpressom-stats-integration
 * @author Yann Dubois
 * @version 0.1.1
 */

/*
 Plugin Name: YD Wordpress.com Stats Integration
 Plugin URI: http://www.yann.com/en/wp-plugins/yd-wordpresscom-stats-integration
 Description: Automatically imports Wordpress.com stats data on a regular basis and integrates them in the Wordpress database directly. | Funded by <a href="http://www.abc.fr">ABC.FR</a>
 Version: 0.1.1
 Author: Yann Dubois
 Author URI: http://www.yann.com/
 License: GPL2
 */

/**
 * @copyright 2010  Yann Dubois  ( email : yann _at_ abc.fr )
 *
 *  Original development of this plugin was kindly funded by http://www.abc.fr
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 Revision 0.1.0:
 - Original beta release
 Revision 0.1.1:
 - Cron scheduling bugfix + framework upgrade
 */

include_once( 'inc/yd-widget-framework.inc.php' );

$junk = new YD_Plugin( 
	array(
		'name' 				=> 'YD Wordpress.com Stats Integration',
		'version'			=> '0.1.1',
		'has_option_page'	=> true,
		'has_shortcode'		=> false,
		'has_widget'		=> false,
		'widget_class'		=> '',
		'has_cron'			=> true,
		'crontab'			=> array(
			'daily'			=> array( 'YDWPCSI', 'daily_update' ),
			'hourly'		=> array( 'YDWPCSI', 'hourly_update' )
		),
		'has_stylesheet'	=> false,
		'stylesheet_file'	=> 'css/yd.css',
		'has_translation'	=> false,
		'translation_domain'=> '', // must be copied in the widget class!!!
		'translations'		=> array(
			array( 'English', 'Yann Dubois', 'http://www.yann.com/' ),
			array( 'French', 'Yann Dubois', 'http://www.yann.com/' )
		),		'initial_funding'	=> array( 'Yann.com', 'http://www.yann.com' ),
		'additional_funding'=> array(),
		'form_blocks'		=> array(
			//'bloc1' => array( 
				//'blog_id'	=> 'text'
			//)
		),
		'option_field_labels'=>array(
				//'blog_id' 	=> 'Your Wordpress.com stats blog ID'
		),
		'form_add_actions'	=> array(
				'Manually update hourly stats'	=> array( 'YDWPCSI', 'hourly_update' ),
				'Manually update daily stats'	=> array( 'YDWPCSI', 'daily_update' ),
				'Check latest updates'			=> array( 'YDWPCSI', 'check_update' )
		),
		'has_cache'			=> false,
		'option_page_text'	=> 'Welcome to the plugin settings page. ',
							//	. 'Your Wordpress API key is: ' . get_option( 'wordpress_api_key' ),
		'backlinkware_text' => 'Features Stats Integration Plugin developed by YD',
		'plugin_file'		=> __FILE__		
 	)
);

class YDWPCSI {
	function update_stats( $op, $days = 30 ) {
		
		//TODO: verify that stats_get_csv() is defined, or exit with error
		if ( ! function_exists( 'stats_get_api_key' ) 
			|| ! function_exists( 'stats_get_option' ) 
			|| ! function_exists( 'stats_get_csv' )
		) {
			$op->error_msg = '<p>Wordpress.com Stats Plugin is not installed!</p>';
			return;
		}
		
		//$option_key	= 'yd-wordpresscom-stats-integration';
		$api_domain	= 'stats.wordpress.com';
		$api_path	= 'csv.php';
		$api_req 	= 'days=' . $days . '&limit=-1';
		$api_key	= stats_get_api_key();
		$blog_id	= stats_get_option('blog_id');
		
		if ( ! $api_key || $api_key == ''
			|| ! $blog_id || $blog_id == ''
		) {
			$op->error_msg = '<p>Wordpress.com Stats Plugin is not configured!</p>';
			return;
		}
		
		$url = 'http://' . $api_domain . '/' . $api_path;
		$url .= '?api_key=' . $api_key;
		$url .= '&blog_id=' . $blog_id;
		$url .= '&table=postviews';
		$url .= '&' . $api_req;
		$url .= '&summarize';
		$op->update_msg .= '<p>Importing Stats...<br/>';
		$op->update_msg .= 'API Key: ' . $api_key . '<br/>';
		$op->update_msg .= 'Blog ID: ' . $blog_id . '<br/>';
		$op->update_msg .= 'Request URI: <a href="' . $url . '">' . $url . '</a><br/>';
		
		$top_posts = stats_get_csv( 'postviews', $api_req );
		
		if( $top_posts ) {
			$op->update_msg .= 'Successfully fetched CSV file.<br/>';
			$op->update_msg .= count( $top_posts ) . ' lines in data.<br/>';
			$op->update_msg .= 'Updating post meta information...<br/>';
			
			/* data field structure is as follows:
			 * [0]=> array(4) { 
			 	["post_id"]=> string(1) "0" 
			 	["post_title"]=> string(9) "Home page" 
			 	["post_permalink"]=> string(30) "http://www.abc.fr/faits-divers" 
			 	["views"]=> string(4) "5758" 
			 } */
			foreach( $top_posts as $data ) {
				update_post_meta( $data['post_id'], 'yd_views_' . $days, $data['views'] );
			}
			$op->update_msg .= 'Data was successfully imported.';
			
		} else {
			$op->error_msg .= '<p>Failed fetching CSV file.</p>';
			$op->update_msg .= 'Failed fetching CSV file.';
		}
		$op->update_msg .= '</p>';
		update_option( 'YD_WPCSI_last_updated', time() );
	}
	function hourly_update( $op ) {
		if( !$op || !is_object( $op ) ) {
			$op = new YD_OptionPage(); //dummy object
		}
		self::update_stats( &$op, 1 );
		update_option( 'YD_WPCSI_hourly', time() );
	}
	function daily_update( $op ) {
		if( !$op || !is_object( $op ) ) {
			$op = new YD_OptionPage(); //dummy object
		}
		self::update_stats( &$op, 365 );
		self::update_stats( &$op, 90 );
		self::update_stats( &$op, 30 );
		self::update_stats( &$op, 7 );
		self::update_stats( &$op, 1 );
		update_option( 'YD_WPCSI_daily', time() );
	}
	function check_update( $op ) {
		$op->update_msg .= '<p>';
		if( $last = get_option( 'YD_WPCSI_daily' ) ) {
			$op->update_msg .= 'Last daily update was on: ' 
				. date( DATE_RSS, $last ) . '<br/>';
		} else { 
			$op->update_msg .= 'No daily update yet.<br/>';
		}
		if( $last = get_option( 'YD_WPCSI_hourly' ) ) {
			$op->update_msg .= 'Last hourly update was on: ' 
				. date( DATE_RSS, $last ) . '<br/>';
		} else { 
			$op->update_msg .= 'No hourly update yet.<br/>';
		}
		if( $last = get_option( 'YD_WPCSI_last_updated' ) ) {
			$op->update_msg .= 'Last update was on: ' 
				. date( DATE_RSS, $last ) . '<br/>';
		} else { 
			$op->update_msg .= 'No recorded update yet.<br/>';
		}
		$op->update_msg .= '</p>';
	}
}
?>