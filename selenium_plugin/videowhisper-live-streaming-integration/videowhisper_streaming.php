<?php
/*
Plugin Name: VideoWhisper Live Streaming
Plugin URI: https://videowhisper.com/?p=WordPress+Live+Streaming
Description: <strong>Live Streaming / Broadcast Live Video : HTML5, WebRTC, HLS, RTSP, RTMP</strong> solution powers a turnkey live streaming channels site including web based webcam broadcasting app and player with chat, support for external apps, 24/7 RTSP ip cameras, WebRTC, video playlist scheduler, video archiving & vod, HLS & MPEG-DASH delivery for mobile including AJAX chat, membership and access control, pay per view channels and tips for broadcasters.
Version: 5.2.16
Author: VideoWhisper.com
Author URI: https://videowhisper.com/
Contributors: videowhisper, VideoWhisper.com, BroadcastLiveVideo.com
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists("VWliveStreaming"))
{
	class VWliveStreaming {

		public function __construct()
		{
		}

		public function VWliveStreaming() { //constructor
			self::__construct();

		}

		static function install() {
			// do not generate any output here

			VWliveStreaming::channel_post();
			flush_rewrite_rules();
		}

		function settings_link($links) {
			$settings_link = '<a href="admin.php?page=live-streaming">'.__("Settings").'</a>';
			array_unshift($links, $settings_link);
			return $links;
		}

		function init()
		{
			//setup post
			VWliveStreaming::channel_post();

			//prevent wp from adding <p> that breaks JS
			remove_filter ('the_content',  'wpautop');

			//move wpautop filter to BEFORE shortcode is processed
			add_filter( 'the_content', 'wpautop' , 1);

			//then clean AFTER shortcode
			add_filter( 'the_content', 'shortcode_unautop', 100 );

		}


		function plugins_loaded()
		{
			$plugin = plugin_basename(__FILE__);
			add_filter("plugin_action_links_$plugin",  array('VWliveStreaming','settings_link') );


			//widget
			wp_register_sidebar_widget('liveStreamingWidget','VideoWhisper Streaming', array('VWliveStreaming', 'widget') );

			//channel page
			add_filter('the_title', array('VWliveStreaming','the_title'));
			add_filter('the_content', array('VWliveStreaming','channel_page'));
			add_filter('query_vars', array('VWliveStreaming','query_vars'));
			add_filter('pre_get_posts', array('VWliveStreaming','pre_get_posts'));

			//admin channels
			add_filter('manage_channel_posts_columns', array( 'VWliveStreaming', 'columns_head_channel') , 10);
			add_filter( 'manage_edit-channel_sortable_columns', array('VWliveStreaming', 'columns_register_sortable') );
			add_action('manage_channel_posts_custom_column', array( 'VWliveStreaming', 'columns_content_channel') , 10, 2);
			add_filter( 'request', array('VWliveStreaming', 'duration_column_orderby') );

			//shortcodes

			add_shortcode('videowhisper_stream_setup', array( 'VWliveStreaming', 'videowhisper_stream_setup'));


			add_shortcode('videowhisper_livesnapshots', array( 'VWliveStreaming', 'shortcode_livesnapshots'));
			add_shortcode('videowhisper_broadcast', array( 'VWliveStreaming', 'videowhisper_broadcast'));

			add_shortcode('videowhisper_external', array( 'VWliveStreaming', 'videowhisper_external'));
			add_shortcode('videowhisper_external_broadcast', array( 'VWliveStreaming', 'videowhisper_external_broadcast'));
			add_shortcode('videowhisper_external_playback', array( 'VWliveStreaming', 'videowhisper_external_playback'));

			add_shortcode('videowhisper_watch', array( 'VWliveStreaming', 'videowhisper_watch'));
			add_shortcode('videowhisper_video', array( 'VWliveStreaming', 'videowhisper_video'));

			add_shortcode('videowhisper_hls', array( 'VWliveStreaming', 'videowhisper_hls'));
			add_shortcode('videowhisper_mpeg', array( 'VWliveStreaming', 'videowhisper_mpeg'));

			add_shortcode('videowhisper_channel_manage',array( 'VWliveStreaming', 'videowhisper_channel_manage'));
			add_shortcode('videowhisper_channels',array( 'VWliveStreaming', 'videowhisper_channels'));

			add_shortcode('videowhisper_webrtc_broadcast', array( 'VWliveStreaming', 'videowhisper_webrtc_broadcast'));
			add_shortcode('videowhisper_webrtc_playback', array( 'VWliveStreaming', 'videowhisper_webrtc_playback'));

			add_shortcode('videowhisper_htmlchat_playback', array( 'VWliveStreaming', 'videowhisper_htmlchat_playback'));


			add_action( 'before_delete_post',  array( $this,'before_delete_post') );

			//ajax

			//ip camera / re-stream setup
			add_action( 'wp_ajax_vwls_stream_setup', array('VWliveStreaming','vwls_stream_setup'));
			add_action( 'wp_ajax_nopriv_vwls_stream_setup', array('VWliveStreaming','vwls_stream_setup'));

			add_action( 'wp_ajax_vwls_playlist', array('VWliveStreaming','vwls_playlist') );
			add_action( 'wp_ajax_nopriv_vwls_playlist', array('VWliveStreaming','vwls_playlist'));

			add_action( 'wp_ajax_vwls_trans', array('VWliveStreaming','vwls_trans') );
			add_action( 'wp_ajax_nopriv_vwls_trans', array('VWliveStreaming','vwls_trans'));

			add_action( 'wp_ajax_vwls_broadcast', array('VWliveStreaming','vwls_broadcast'));

			add_action( 'wp_ajax_vwls', array('VWliveStreaming','vwls_calls'));
			add_action( 'wp_ajax_nopriv_vwls', array('VWliveStreaming','vwls_calls'));

			add_action( 'wp_ajax_vwls_channels', array('VWliveStreaming','vwls_channels'));
			add_action( 'wp_ajax_nopriv_vwls_channels', array('VWliveStreaming','vwls_channels'));


			add_action( 'wp_ajax_vwls_htmlchat', array('VWliveStreaming','wp_ajax_vwls_htmlchat') );
			add_action( 'wp_ajax_nopriv_vwls_htmlchat', array('VWliveStreaming','wp_ajax_vwls_htmlchat') );



			//jquery for ajax
			add_action( 'wp_enqueue_scripts', array('VWliveStreaming','wp_enqueue_scripts') );

			//update page if not exists or deleted
			$page_id = get_option("vwls_page_manage");
			$page_id2 = get_option("vwls_page_channels");

			if (!$page_id || $page_id == "-1" || !$page_id2 || $page_id2 == "-1")  add_action('wp_loaded', array('VWliveStreaming','updatePages'));

			//check db and update if necessary
			$vw_db_version = "1.4";

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_sessions";
			$table_name2 = $wpdb->prefix . "vw_lwsessions";
			$table_name3 = $wpdb->prefix . "vw_lsrooms";


			$table_chatlog = $wpdb->prefix . "vw_vwls_chatlog";

			$installed_ver = get_option( "vwls_db_version" );

			if( $installed_ver != $vw_db_version )
			{

				//echo "---$installed_ver != $vw_db_version---";

				$wpdb->flush();

				$sql = "DROP TABLE IF EXISTS `$table_name`;
		CREATE TABLE `$table_name` (
		  `id` int(11) NOT NULL auto_increment,
		  `session` varchar(64) NOT NULL,
		  `username` varchar(64) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `message` text NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `room` (`room`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Broadcaster Sessions - 2009@videowhisper.com' AUTO_INCREMENT=1 ;

		DROP TABLE IF EXISTS `$table_name2`;
		CREATE TABLE `$table_name2` (
		  `id` int(11) NOT NULL auto_increment,
		  `session` varchar(64) NOT NULL,
		  `username` varchar(64) NOT NULL,
		  `uid` int(11) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `rid` int(11) NOT NULL,
		  `rsdate` int(11) NOT NULL,
		  `redate` int(11) NOT NULL,
		  `roptions` text NOT NULL,
		  `rmode` tinyint(4) NOT NULL,
		  `message` text NOT NULL,
		  `ip` text NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `rid` (`rid`),
		  KEY `uid` (`uid`),
		  KEY `rmode` (`rmode`),
		  KEY `rsdate` (`rsdate`),
		  KEY `redate` (`redate`),
		  KEY `sdate` (`sdate`),
		  KEY `edate` (`edate`),
		  KEY `room` (`room`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Sessions - 2015@videowhisper.com' AUTO_INCREMENT=1 ;


		DROP TABLE IF EXISTS `$table_name3`;
		CREATE TABLE `$table_name3` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` varchar(64) NOT NULL,
		  `owner` int(11) NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `btime` int(11) NOT NULL,
		  `wtime` int(11) NOT NULL,
		  `rdate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  `options` TEXT,
		  PRIMARY KEY  (`id`),
		  KEY `name` (`name`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `owner` (`owner`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Rooms - 2014@videowhisper.com' AUTO_INCREMENT=1 ;

		DROP TABLE IF EXISTS `$table_chatlog`;
		CREATE TABLE `$table_chatlog` (
		  `id` int(11) unsigned NOT NULL auto_increment,
		  `username` varchar(64) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `message` text NOT NULL,
		  `mdate` int(11) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `room` (`room`),
		  KEY `mdate` (`mdate`),
		  KEY `type` (`type`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Chat Logs - 2018@videowhisper.com' AUTO_INCREMENT=1;

		";

				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);

				if (!$installed_ver) add_option("vwls_db_version", $vw_db_version);
				else update_option( "vwls_db_version", $vw_db_version );

				$wpdb->flush();
			}


		}
		/*
		function delTree($dir) {
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				(is_dir("$dir/$file")) ? VWliveStreaming::delTree("$dir/$file") : unlink("$dir/$file");
			}
			return rmdir($dir);
		}
*/

		function before_delete_post($postID)
		{
			$options = get_option('VWliveStreamingOptions');
			if (get_post_type( $postID ) != $options['custom_post']) return;

			$post = get_post( $postID );


			//delete from room table
			$room = sanitize_file_name($post->post_title);

			global $wpdb;
			$table_name3 = $wpdb->prefix . "vw_lsrooms";
			$sql = "DELETE FROM $table_name3 where name='$room'";

			$wpdb->query($sql);




		}


		function updatePages()
		{

			$options = get_option('VWliveStreamingOptions');

			if ($options['disablePage']=='0' || $options['disablePageC']=='0')
			{
				//create a menu to add pages
				$menu_name = 'VideoWhisper';
				$menu_exists = wp_get_nav_menu_object( $menu_name );

				if (!$menu_exists) $menu_id = wp_create_nav_menu($menu_name);
				else $menu_id = $menu_exists->term_id;
			}



			//if not disabled create
			if ($options['disablePage']=='0')
			{
				global $user_ID;
				$page = array();
				$page['post_type']    = 'page';
				$page['post_content'] = '[videowhisper_channel_manage]';
				$page['post_parent']  = 0;
				$page['post_author']  = $user_ID;
				$page['post_status']  = 'publish';
				$page['post_title']   = 'Broadcast Live';
				$page['comment_status'] = 'closed';

				$page_id = get_option("vwls_page_manage");
				if ($page_id>0) $page['ID'] = $page_id;

				$pageid = wp_insert_post ($page);
				update_option( "vwls_page_manage", $pageid);

				$link = get_permalink( $pageid);

				if ($menu_id) wp_update_nav_menu_item($menu_id, 0, array(
							'menu-item-title' =>  'Broadcast Live',
							'menu-item-url' => $link,
							'menu-item-status' => 'publish'));

			}

			if ($options['disablePageC']=='0')
			{
				global $user_ID;
				$page = array();
				$page['post_type']    = 'page';
				$page['post_content'] = '[videowhisper_channels]';
				$page['post_parent']  = 0;
				$page['post_author']  = $user_ID;
				$page['post_status']  = 'publish';
				$page['post_title']   = 'Channels';
				$page['comment_status'] = 'closed';

				$page_id = get_option("vwls_page_channels");
				if ($page_id>0) $page['ID'] = $page_id;

				$pageid = wp_insert_post ($page);
				update_option( "vwls_page_channels", $pageid);

				$link = get_permalink( $pageid);

				if ($menu_id) wp_update_nav_menu_item($menu_id, 0, array(
							'menu-item-title' =>  'Channels',
							'menu-item-url' => $link,
							'menu-item-status' => 'publish'));
			}

		}

		function deletePages()
		{
			$options = get_option('VWliveStreamingOptions');

			if ($options['disablePage'])
			{
				$page_id = get_option("vwls_page_manage");
				if ($page_id > 0)
				{
					wp_delete_post($page_id);
					update_option( "vwls_page_manage", -1);
				}
			}

			if ($options['disablePageC'])
			{
				$page_id = get_option("vwls_page_channels");
				if ($page_id > 0)
				{
					wp_delete_post($page_id);
					update_option( "vwls_page_channels", -1);
				}
			}

		}

		function login_headerurl($url) {

			return get_bloginfo( "url" ) . "/";
		}

		function login_enqueue_scripts() {

			$options = get_option('VWliveStreamingOptions');

			if ($options['loginLogo'])
			{
?>
    <style type="text/css">
         #login h1 a, .login h1 a  {
            background-image: url(<?php echo $options['loginLogo']; ?>);
			background-size: 320px 54px;
			width: 320px;
			height: 54px;
        }
    </style>
  	<?php
			}
			/*			else
			{
?>
	    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/images/site-login-logo.png);
            padding-bottom: 30px;
        }
    </style>
	    <?php

			}*/

		}



		//! set fc

		//string contains any term for list (ie. banning)
		function containsAny($name, $list)
		{
			$items = explode(',', $list);
			foreach ($items as $item) if (stristr($name, trim($item))) return $item;

				return 0;
		}


		//if any key matches any listing
		function inList($keys, $data)
		{
			if (!$keys) return 0;
			if (!$data) return 0;
			if (strtolower(trim($data)) == 'all') return 1;
			if (strtolower(trim($data)) == 'none') return 0;

			$list=explode(",", strtolower(trim($data)));
			if (in_array('all', $list)) return 1;

			foreach ($keys as $key)
				foreach ($list as $listing)
					if ( strtolower(trim($key)) == trim($listing) ) return 1;

					return 0;
		}

		//! room fc
		function roomURL($room)
		{

			$options = get_option('VWliveStreamingOptions');

			if ($options['channelUrl'] == 'post')
			{
				global $wpdb;

				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($room) . "' and post_type='channel' LIMIT 0,1" );

				if ($postID) return get_post_permalink($postID);
			}

			if ($options['channelUrl'] == 'full') return site_url('/fullchannel/' . urlencode($room));

			return plugin_dir_url(__FILE__) . 'ls/channel.php?n=' . urlencode(sanitize_file_name($room));

		}

		function count_user_posts_by_type( $userid, $post_type = 'channel' )
		{
			global $wpdb;
			$where = get_posts_by_author_sql( $post_type, true, $userid );
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where" );
			return apply_filters( 'get_usernumposts', $count, $userid );
		}


		//! Channel Validation

		function channelInvalid( $channel, $broadcast =false)
		{
			//check if online channel is invalid for any reason

			if (!function_exists('fm'))
			{

				function fm($t, $item = null)
				{
					$img = '';

					if ($item)
					{
						$options = get_option('VWliveStreamingOptions');
						$dir = $options['uploadsPath']. "/_thumbs";
						$age = VWliveStreaming::format_age(time() -  $item->edate);
						$thumbFilename = "$dir/" . $item->name . ".jpg";

						$noCache = '';
						if ($age=='LIVE') $noCache='?'.((time()/10)%100);

						if (file_exists($thumbFilename)) $img = '<IMG ALIGN="RIGHT" src="' . VWliveStreaming::path2url($thumbFilename) . $noCache .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"><br style="clear:both">';
					}

					//format message
					return  '<div class="w-actionbox color_alternate">'. $t . $img . '</div><br>';
				}
			}

			$channel = sanitize_file_name($channel);
			if (!$channel) return fm('No channel name!');

			global $wpdb;
			$table_name3 = $wpdb->prefix . "vw_lsrooms";

			$sql = "SELECT * FROM $table_name3 where name='$channel'";
			$channelR = $wpdb->get_row($sql);

			if (!$channelR) if ($broadcast) return; //first broadcast
				else return fm('Channel was not found! Live channel is only accessible on broadcast.', $channelR);

				$options = get_option('VWliveStreamingOptions');

			if ($channelR->type >=2) //premium
				{
				$poptions = VWliveStreaming::channelOptions($channelR->type, $options);

				$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
				$maximumWatchTime =  60 * $poptions['pWatchTime'];

				$canWatch = $poptions['canWatchPremium'];
				$watchList = $poptions['watchListPremium'];
			}
			else
			{
				$maximumBroadcastTime =  60 * $options['broadcastTime'];
				$maximumWatchTime =  60 * $options['watchTime'];

				$canWatch = $options['canWatch'];
				$watchList = $options['watchList'];
			}

			if (!$broadcast)
			{
				if ($maximumWatchTime) if ($channelR->wtime >= $maximumWatchTime) return fm('Channel watch time exceeded!', $channelR);

			}
			else if ($maximumBroadcastTime) if ($channelR->btime >= $maximumBroadcastTime) return fm('Channel broadcast time exceeded!');


					//user access validation

					$current_user = wp_get_current_user();


				if ($current_user->ID != 0) //logged in
					{
					//access keys
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->user_login;
				}
			else $userkeys[] = 'Guest';

			//global access settings
			switch ($canWatch)
			{
			case "members":
				if (!$current_user->ID) return fm('Only registered members can access!');
				break;

			case "list";
				if (!$current_user->ID || !VWliveStreaming::inList($userkeys, $watchList))
					return fm('Access restricted by global access list!');
				break;
			}



			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $channel . "' and post_type='channel' LIMIT 0,1" );

			if ($postID)    //post validations
				{
				//accessPassword
				if (post_password_required($postID)) return fm('Access to channel is restricted by password!');

				// channel access list
				$accessList = get_post_meta($postID, 'vw_accessList', true);
				if ($accessList) if (!VWliveStreaming::inList($userkeys, $accessList)) return fm('Access restricted by channel access list!');
					//playlist active or ip camera
					$playlistActive = get_post_meta( $postID, 'vw_playlistActive', true );
				$ipCamera = get_post_meta( $postID, 'vw_ipCamera', true );
			}

			if (!$broadcast)  if (!VWliveStreaming::userPaidAccess($current_user->ID, $postID)) return fm('Access restricted: channel access needs to be purchased!');


				if (!$broadcast) if (!$options['alwaysWatch']) if (!$playlistActive && !$ipCamera)
							if (time() - $channelR->edate > 45)
							{
								$age = VWliveStreaming::format_age(time() -  $channelR->edate);

								$htmlCode ='This channel is currently offline. ';

								$eventCode = VWliveStreaming::eventInfo($postID);

								if ($eventCode)
									$eventCode = 'Come back and reload page when event starts!' . $eventCode;
								else $eventCode .= ' Try again later! Time offline: ' . $age;

								return fm($htmlCode . $eventCode, $channelR );
							}

						//valid then
						return ;

		}

		function getCurrentURL()
		{
			/*
			$currentURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
			$currentURL .= $_SERVER["SERVER_NAME"];

			if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
			{
				$currentURL .= ":".$_SERVER["SERVER_PORT"];
			}

			$uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);

			$currentURL .= $uri_parts[0];

			return $currentURL;
			*/
			global $wp;
			return home_url(add_query_arg(array(),$wp->request));
		}

		//! Shortcodes


		function videowhisper_stream_setup($atts)
		{
			$options = get_option('VWliveStreamingOptions');

			$atts = shortcode_atts(
				array(
					'include_css' => '1',
					'channel_id' => '-1',
					'id' => ''
				),
				$atts, 'videowhisper_stream_setup');
			$id = $atts['id'];
			if (!$id) $id = uniqid();
			
			$postID = intval($atts['channel_id']);
			if ($postID<0) $postID = 0;

			$current_user = wp_get_current_user();

			$address = 'rtsp://[user:password]@public-IP-or-Domain[:port]/[stream-path]';
			$channelTitle = 'New';
			
			$addButton = 'Setup Stream';
			 
			if ($postID && $current_user) 
			{
				
				$channel = get_post( $postID);
				
				if ($channel) 
					if ($channel->post_author == $current_user->ID)
					{
						$address = get_post_meta( $postID, 'vw_ipCamera',true );
						$channelTitle = $channel->post_title;
						$addButton = 'Update Stream Address';

					}
					else 
					{
						$postID = 0;
						$htmlCode .= 'Only owner can edit existing channel address.';
					}
			}

			$streamInfoCode = '<H4>IP Camera/Stream Access Requirements</H4>
<UL>
<LI>You will need the stream address of your ip camera or stream. Address should start with RTSP, RTMP. For IP cameras, you can find RTSP address in its documentation: ask the camera provider or <a target="_blank" rel="nofollow" href="http://www.soleratec.com/support/rtsp/rtsp_listing">find it online</a>.</LI>
<LI>If device/stream does not have a public address, your local network administrator will need to <a target="_blank" rel="nofollow" href="https://portforward.com/">Forward Camera Port trough Router</a>.</LI>
<LI>If your network is not publicly accessible (your ISP did not allocate a static public IP), your local network administrator may need to setup <a target="_blank" rel="nofollow"  href="http://www.howtogeek.com/66438/how-to-easily-access-your-home-network-from-anywhere-with-ddns/">Dynamic DNS</a> for external access.</LI>
</UL>';



			VWliveStreaming::enqueueUI();

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_stream_setup&channel=' . $postID .'&id='.$id;
			
			$htmlCode = <<<HTMLCODE
<H4>Setup IP Camera / Existing Stream : $channelTitle</H4>
<div id="videowhisperResponse$id">
Stream Address <input name="address" type="text" id="address" value="$address" size="80" maxlength="250"/>
<BR><input class="ui button" type="submit" name="button" id="button" value="$addButton" onClick="loadResponse$id('Trying to connect. Please wait...', '$ajaxurl&address=' + escape(document.getElementById('address').value))"/>
</div>

<script type="text/javascript">
var \$j = jQuery.noConflict();
var loader$id;

	function loadResponse$id(message, request_url){

	if (message)
	if (message.length > 0)
	{
	  \$j("#videowhisperResponse$id").html(message);
	}
		if (loader$id) loader$id.abort();

		loader$id = \$j.ajax({
			url: request_url,
			success: function(data) {
				\$j("#videowhisperResponse$id").html(data);
			}
		});
	}
</script>
$streamInfoCode
HTMLCODE;

			if ($atts['include_css']) $htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;
		}



		function videowhisper_channel_manage()
		{
			//can user create room?
			$options = get_option('VWliveStreamingOptions');

			$maxChannels = $options['maxChannels'];

			$canBroadcast = $options['canBroadcast'];
			$broadcastList = $options['broadcastList'];
			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			$loggedin=0;

			$current_user = wp_get_current_user();

			if ($current_user->$userName) $username = $current_user->$userName;

			//access keys
			$userkeys = $current_user->roles;
			$userkeys[] = $current_user->user_login;
			$userkeys[] = $current_user->ID;
			$userkeys[] = $current_user->user_email;

			switch ($canBroadcast)
			{
			case "members":
				if ($username) $loggedin=1;
				else $htmlCode .= 'Login required: Please login first or register an account if you do not have one!<BR><a class="ui button" href="' . wp_login_url() . '">Login</a>  <a class="ui button" href="' . wp_registration_url() . '">Register</a>';
				break;
			case "list";
				if ($username)
					if (VWliveStreaming::inList($userkeys, $broadcastList)) $loggedin=1;
					else $htmlCode .= "<a href=\"/\">$username, you are not allowed to setup rooms.</a>";
					else $htmlCode .= 'Login required: Please login first or register an account if you do not have one! <BR><a class="ui button" href="' . wp_login_url() . '">Login</a> <a class="ui button" href="' . wp_registration_url() . '">Register</a>';
					break;
			}

			if (!$loggedin)
			{
				$htmlCode .='<p>This pages allows creating and managing broadcasting channels for registered members that have this feature enabled.</p>';
				return $htmlCode;
			}

			$this_page    =   VWliveStreaming::getCurrentURL();
			$channels_count = VWliveStreaming::count_user_posts_by_type($current_user->ID, 'channel');

			//! save channel
			$postID = $_POST['editPost']; //-1 for new

			if ($postID) //create or update
				{

				$name = sanitize_file_name($_POST['newname']);

				global $wpdb;
				$existID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `ID` <> $postID AND `post_title` = '$name' AND `post_type`='".$options['custom_post']."' LIMIT 0,1" ); //same name, diff than postID


				if ($postID <= 0 && $channels_count >= $maxChannels)
					$htmlCode .= "<div class='error'>Could not create another channel: Maximum ". $options['maxChannels']." channels allowed per user!</div>";
				elseif ($existID) $htmlCode .= "<div class='error'>Could not create the new channel: A channel with name '".$name."' already exists. Please use a different channel name!</div>";
				else
				{
					//$name = preg_replace("/[^\s\w]+/", '', $name);

					if ($_POST['ipCamera']) if (!strstr($name,'.stream')) $name .= '.stream';

						$comments = sanitize_file_name($_POST['newcomments']);

					//accessPassword
					$accessPassword ='';
					if (VWliveStreaming::inList($userkeys, $options['accessPassword']))
					{
						$accessPassword = sanitize_text_field($_POST['accessPassword']);
					}


					$post = array(
						'post_content'   => sanitize_text_field($_POST['description']),
						'post_name'      => $name,
						'post_title'     => $name,
						'post_author'    => $current_user->ID,
						'post_type'      => $options['custom_post'],
						'post_status'    => 'publish',
						'comment_status' => $comments,
						'post_password' => $accessPassword
					);

					$category = (int) $_POST['newcategory'];

					if ($postID>0)
					{
						$channel = get_post( $postID );
						if ($channel->post_author == $current_user->ID) $post['ID'] = $postID; //update
						else return "<div class='error'>Not allowed!</div>";
						$htmlCode .= "<div class='update'>Channel $name was updated!</div>";
					}
					else $htmlCode .= "<div class='update'>Channel $name was created!</div>";

					$postID = wp_insert_post($post);
					if ($postID) wp_set_post_categories($postID, array($category));

					$channels_count = VWliveStreaming::count_user_posts_by_type($current_user->ID, 'channel');


					//roomTags
					if (VWliveStreaming::inList($userkeys, $options['roomTags']))
					{
						$roomTags = sanitize_text_field($_POST['roomTags']);
						wp_set_post_tags( $postID, $roomTags, false);
					}


					//uploadPicture
					if (VWliveStreaming::inList($userkeys, $options['uploadPicture']))
					{

						if ($filename = $_FILES['uploadPicture']['tmp_name'])
						{

							$ext = strtolower(pathinfo($_FILES['uploadPicture']['name'], PATHINFO_EXTENSION));
							$allowed = array('jpg','jpeg','png','gif');
							if (!in_array($ext,$allowed)) return 'Unsupported file extension!';

							list($width, $height) = getimagesize($filename);

							if ($width && $height)
							{

								//delete previous image(s)
								VWliveStreaming::delete_associated_media($postID, true);

								//$htmlCode .= 'Generating thumb... ';
								$thumbWidth = $options['thumbWidth'];
								$thumbHeight = $options['thumbHeight'];

								$src = imagecreatefromstring(file_get_contents($filename));
								$tmp = imagecreatetruecolor($thumbWidth, $thumbHeight);

								$dir = $options['uploadsPath']. "/_pictures";
								if (!file_exists($dir)) mkdir($dir);

								$room_name = sanitize_file_name($channel->post_title);
								$thumbFilename = "$dir/$room_name.jpg";
								imagecopyresampled($tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
								imagejpeg($tmp, $thumbFilename, 95);

								//detect tiny images without info
								if (filesize($thumbFilename)>5000) $picType = 1;
								else $picType = 2;

								//update post meta
								if ($postID)
								{
									update_post_meta($postID, 'hasPicture', $picType);
									update_post_meta($postID, 'hasSnapshot', 1); //so it gets listed
									update_post_meta($postID, 'edate', time() - 60);
								}

								//$htmlCode .= ' Updating picture... ' . $thumbFilename;

								//update post image
								if (!function_exists('wp_generate_attachment_metadata')) require ( ABSPATH . 'wp-admin/includes/image.php' );

								$wp_filetype = wp_check_filetype(basename($thumbFilename), null );

								$attachment = array(
									'guid' => $thumbFilename,
									'post_mime_type' => $wp_filetype['type'],
									'post_title' => $room_name,
									'post_content' => '',
									'post_status' => 'inherit'
								);

								$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
								set_post_thumbnail($postID, $attach_id);




								//update post imaga data
								$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
								wp_update_attachment_metadata( $attach_id, $attach_data );


							}
						}

						$showImage = sanitize_file_name($_POST['showImage']);
						update_post_meta($postID, 'showImage', $showImage);

					}

					if (VWliveStreaming::inList($userkeys, $options['eventDetails']))
					{
						update_post_meta($postID, 'eventTitle', sanitize_text_field($_POST['eventTitle']));
						update_post_meta($postID, 'eventStart', sanitize_text_field($_POST['eventStart']));
						update_post_meta($postID, 'eventEnd', sanitize_text_field($_POST['eventEnd']));
						update_post_meta($postID, 'eventStartTime', sanitize_text_field($_POST['eventStartTime']));
						update_post_meta($postID, 'eventEndTime', sanitize_text_field($_POST['eventEndTime']));
						update_post_meta($postID, 'eventDescription', sanitize_text_field($_POST['eventDescription']));
					}

					//disable sidebar for themes that support this
					update_post_meta($postID, 'disableSidebar', true);

					//transcode
					if (VWliveStreaming::inList($userkeys, $options['transcode']))
						update_post_meta($postID, 'vw_transcode', '1');
					else update_post_meta($postID, 'vw_transcode', '0');


					//logoHide
					if (VWliveStreaming::inList($userkeys, $options['logoHide']))
						update_post_meta($postID, 'vw_logo', 'hide');
					else update_post_meta($postID, 'vw_logo', 'global');

					//logoCustom
					if (VWliveStreaming::inList($userkeys, $options['logoCustom']))
					{
						$logoImage = sanitize_text_field($_POST['logoImage']);
						update_post_meta($postID, 'vw_logoImage', $logoImage);

						$logoLink = sanitize_text_field($_POST['logoLink']);
						update_post_meta($postID, 'vw_logoLink', $logoLink);

						update_post_meta($postID, 'vw_logo', 'custom');
					}

					//adsHide
					if (VWliveStreaming::inList($userkeys, $options['adsHide']))
						update_post_meta($postID, 'vw_ads', 'hide');
					else update_post_meta($postID, 'vw_ads', 'global');


					//adsCustom
					if (VWliveStreaming::inList($userkeys, $options['adsCustom']))
					{
						$logoImage = sanitize_text_field($_POST['adsServer']);
						update_post_meta($postID, 'vw_adsServer', $logoImage);

						update_post_meta($postID, 'vw_ads', 'custom');
					}

					//ipCameras
					if (VWliveStreaming::inList($userkeys, $options['ipCameras']))
					{
						if (file_exists($options['streamsPath']))
						{
							$ipCamera = sanitize_text_field($_POST['ipCamera']);


							if ($ipCamera)
							{
								list($firstWord) = explode(':', $ipCamera);
								if (!in_array($firstWord, array('rtsp','udp','rtmp','rtmps','wowz','wowzs', 'http', 'https')))
								{
									$htmlCode .= "<BR>Address format not supported ($firstWord). Address should use one of these protocols: rtsp://, udp://, rtmp://, rtmps://, wowz://, wowzs://, http://, https:// .";
									$ipCamera = '';

								}
							}

							if ($ipCamera)  if (!strstr($name,'.stream'))
								{
									$htmlCode .= "<BR>Channel name must end in .stream when re-streaming!";
									$ipCamera = '';
								}

							$file = $options['streamsPath'] . '/' . $name;

							if ($ipCamera)
							{

								$myfile = fopen($file, "w");
								if ($myfile)
								{
									fwrite($myfile, $ipCamera);
									fclose($myfile);
									$htmlCode .= '<BR>Stream file created/updated:<br>' . $name . ' = ' . $ipCamera;
								}
								else
								{
									$htmlCode .= '<BR>Could not write file: '. $file;
									$ipCamera = '';
								}

							}
							else
							{
								if (file_exists($file))
								{
									unlink($file);
									$htmlCode .= '<BR>Stream file removed: '. $file;
								}
							}

							update_post_meta($postID, 'vw_ipCamera', $ipCamera);
						}
						else
						{
							$htmlCode .= '<BR>Stream file could not be setup. Streams folder not found: '. $options['streamsPath'];
						}
					}
					else update_post_meta($postID, 'vw_ipCamera', '');

					//schedulePlaylists
					if (!$options['playlists'] || !VWliveStreaming::inList($userkeys, $options['schedulePlaylists']))
						update_post_meta($postID, 'vw_playlistActive', '');


					//permission lists: access, chat, write, participants, private
					foreach (array('access','chat','write','participants','privateChat') as $field)
						if (VWliveStreaming::inList($userkeys, $options[$field .'List']))
						{
							$value = sanitize_text_field($_POST[$field . 'List']);
							update_post_meta($postID, 'vw_'.$field.'List', $value);
						}


					//accessPrice
					if (VWliveStreaming::inList($userkeys, $options['accessPrice']))
					{
						$accessPrice = round($_POST['accessPrice'],2);
						update_post_meta($postID, 'vw_accessPrice', $accessPrice);

						$mCa = array(
							'status'       => 'enabled',
							'price'        => $accessPrice,
							'button_label' => 'Buy Access Now', // default button label
							'expire'       => 0 // default no expire
						);

						if ($options['mycred'] && $accessPrice) update_post_meta($postID, 'myCRED_sell_content', $mCa);
						else delete_post_meta($postID, 'myCRED_sell_content');

					}

				}

			}

			//! Playlist Edit
			if ( (int) $editPlaylist = $_GET['editPlaylist'])
			{

				$channel = get_post( $editPlaylist );
				if (!$channel)
				{
					return "Channel not found!";
				}

				if ($channel->post_author != $current_user->ID)
				{
					return "Access not permitted (different channel owner)!";
				}

				$stream = sanitize_file_name($channel->post_title);

				wp_enqueue_script( 'jquery');
				wp_enqueue_script( 'jquery-ui-core');
				wp_enqueue_script( 'jquery-ui-widget');
				wp_enqueue_script( 'jquery-ui-dialog');

				//wp_enqueue_script( 'jquery-ui-datepicker');



				//css
				wp_enqueue_style( 'jtable-green', plugin_dir_url(  __FILE__ ) . '/scripts/jtable/themes/lightcolor/green/jtable.min.css');

				wp_enqueue_style( 'jtable-flick', plugin_dir_url(  __FILE__ ) . '/scripts/jtable/themes/flick/jquery-ui.min.css');

				//js
				wp_enqueue_script( 'jquery-ui-jtable', plugin_dir_url(  __FILE__ ) . '/scripts/jtable/jquery.jtable.min.js', array('jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog'));

				// wp_enqueue_script( 'jtable', plugin_dir_url(  __FILE__ ) . '/scripts/jtable/jquery.jtable.js', array('jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog'));

				$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_playlist&channel=' . $editPlaylist;


				$htmlCode .= '<h3>Playlist Scheduler: ' .$channel->post_title.'</h3>';

				$currentDate = date('Y-m-j h:i:s');

				if ($_POST['updatePlaylist'])
				{
					update_post_meta( $editPlaylist, 'vw_playlistActive', $playlistActive = (int) $_POST['playlistActive']);
					VWliveStreaming::updatePlaylist($stream, $playlistActive);
					update_post_meta( $editPlaylist, 'vw_playlistUpdated', time());
				}

				//playlistActive
				$value = get_post_meta( $editPlaylist, 'vw_playlistActive', true );

				$activeCode .= '<select id="playlistActive" name="playlistActive">';
				$activeCode .= '<option value="0" ' . (!$value ? 'selected' : '') . '>Inactive</option>';
				$activeCode .= '<option value="1" ' . ($value ? 'selected' : '') . '>Active</option>';
				$activeCode .= '</select>';

				$value = get_post_meta( $editPlaylist, 'vw_playlistUpdated', true );
				$playlistUpdated = date('Y-m-j h:i:s', (int) $value);

				$value = get_post_meta( $editPlaylist, 'vw_playlistLoaded', true );
				$playlistLoaded = date('Y-m-j h:i:s', (int) $value);


				$playlistPage = add_query_arg(array('editPlaylist'=>$editPlaylist), $this_page);

				$videosImg =  plugin_dir_url( __FILE__ ) . 'scripts/jtable/themes/lightcolor/edit.png';

				$channelURL = get_permalink($channel->ID);

				//! jTable
				$htmlCode .= <<<HTMLCODE
<form method="post" action="$playlistPage" name="adminForm" class="w-actionbox">
Playlist Status: $activeCode
<input class="videowhisperButtonLS g-btn type_primary" type="submit" name="button" id="button" value="Update" />
<input type="hidden" name="updatePlaylist" id="updatePlaylist" value="$editPlaylist" />
<BR>After editing playlist contents, update it to apply changes. Last Updated: $playlistUpdated
<BR>Playlist is loaded with web application (on access) and reloaded if necessary when users access <a href='$channelURL'>watch interface</a> (last time reloaded:  $playlistLoaded).
</form>
<BR>
First create a Schedule (Add new record), then Edit Videos (Add new record under Videos):
	<div id="PlaylistTableContainer" style="width: 600px;"></div>
	<script type="text/javascript">

		jQuery(document).ready(function () {

		    //Prepare jTable
			jQuery('#PlaylistTableContainer').jtable({
				title: 'Playlist Contents for Channel',
				defaultSorting: 'Order ASC',
				toolbar: {hoverAnimation: false},
				actions: {
					listAction: '$ajaxurl&task=list',
					createAction: '$ajaxurl&task=create',
					updateAction: '$ajaxurl&task=update',
					deleteAction: '$ajaxurl&task=delete'
				},
				fields: {
					Id: {
						key: true,
						create: false,
						edit: false,
						list: false,
					},
					//CHILD TABLE DEFINITION
					Videos: {
                    title: 'Videos',
                    sorting: false,
                    edit: false,
                    create: false,
                    display: function (playlist) {
                        //Create an image that will be used to open child table
                        var vButton = jQuery('<IMG src="$videosImg" /><I>Edit Videos</I>');
                        //Open child table when user clicks the image
                        vButton.click(function () {
                            jQuery('#PlaylistTableContainer').jtable('openChildTable',
                                    vButton.closest('tr'),
                                    {
                                        title: 'Videos for Schedule ' + playlist.record.Scheduled,
                                        actions: {
                                            listAction: '$ajaxurl&task=videolist&item=' + playlist.record.Id,
                                            deleteAction: '$ajaxurl&task=videoremove&item=' + playlist.record.Id,
                                            updateAction: '$ajaxurl&task=videoupdate',
                                            createAction: '$ajaxurl&task=videoadd'
                                        },
                                        fields: {
                                            ItemId: {
                                                type: 'hidden',
                                                defaultValue: playlist.record.Id
                                            },
                                            Id: {
                                                key: true,
                                                create: false,
                                                edit: false,
                                                list: false
                                            },
											Video: {
												title: 'Video',
												options: '$ajaxurl&task=source',
												sorting: false
											},
											Start: {
												title: 'Start',
												defaultValue: '0',
											},
											Length: {
												title: 'Length',
												defaultValue: '-1',
											},
											Order: {
												title: 'Order',
												defaultValue: '0',
											},
	                                    }
                                    }, function (data) { //opened handler
                                        data.childTable.jtable('load');
                                    });
                        });
                        //Return image to show on the person row
                        return vButton;
                    }

                    },
					Scheduled: {
						title: 'Scheduled',
						defaultValue: '$currentDate',
						sorting: false
					},
					Repeat: {
						title: 'Repeat',
						type: 'checkbox',
						defaultValue: '0',
						values: { '0' : 'Disabled', '1' : 'Enabled' },
						sorting: false
					},
					Order: {
						title: 'Order',
						defaultValue: '0',
					}
				}
			});

			//Load item list from server
			jQuery('#PlaylistTableContainer').jtable('load');
		});
	</script>
	<STYLE>
	.ui-front
	{
		z-index: 1000;
	}
	</STYLE>

HTMLCODE;

				$htmlCode .= '<BR>Schedule playlist items as: Year-Month-Day Hours:Minutes:Seconds. In example, current server time: ' . date('Y-m-j h:i:s');
				if (date_default_timezone_get()) {
					$htmlCode .= '<BR>If the schedule time is in the past, each video is loaded in order and immediately replaces the previous video for the stream. Repeat will cause that videos to repeat in loop. Scheduling must be based on server timezone: ' . date_default_timezone_get() . '<br />';
				}
			}

			//! list channels
			if (!$_GET['editChannel'] && !$_GET['editPlaylist'] && !$_GET['reStream'])
			{

				$args = array(
					'author'           => $current_user->ID,
					'orderby'          => 'post_date',
					'order'            => 'DESC',
					'post_type'        => 'channel',
				);

				$channels = get_posts( $args );


				$htmlCode .= apply_filters("vw_ls_manage_channels_head", '');
				$htmlCode .= "<h3>My Channels ($channels_count/$maxChannels)</h3>";

				if ($channels_count <$maxChannels)
				{
					$htmlCode .= '<a href="'. add_query_arg( 'editChannel', -1, $this_page).'" class="ui primary button"> <i class="icon plus"></i> Setup New Channel</a>';

					if (VWliveStreaming::inList($userkeys, $options['ipCameras']))
						$htmlCode .= '<a href="'. add_query_arg( 'reStream', -1, $this_page).'" class="ui primary button"> <i class="icon plus"></i> Setup New IP Camera / Stream</a>';
				}
				if (count($channels))
				{
					global $wpdb;
					$table_name3 = $wpdb->prefix . "vw_lsrooms";

					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					$htmlCode .= '<table>';

					foreach ($channels as $channel)
					{
						$postID = $channel->ID;

						$stream = sanitize_file_name(get_the_title($postID));

						//update room
						//setup/update channel, premium & time reset

						$room = $stream;
						$ztime = time();

						$poptions = VWliveStreaming::premiumOptions($userkeys, $options);

						if ($poptions) //premium room
							{
							$rtype = 1 + $poptions['level'];
							$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
							$maximumWatchTime =  60 * $poptions['pWatchTime'];

							// $camBandwidth=$options['pCamBandwidth'];
							// $camMaxBandwidth=$options['pCamMaxBandwidth'];
							// if (!$options['pLogo']) $options['overLogo']=$options['overLink']='';

						}else
						{
							$rtype=1;
							//$camBandwidth=$options['camBandwidth'];
							//$camMaxBandwidth=$options['camMaxBandwidth'];

							$maximumBroadcastTime =  60 * $options['broadcastTime'];
							$maximumWatchTime =  60 * $options['watchTime'];
						}


						$sql = "SELECT * FROM $table_name3 where owner='$username' and name='$room'";
						$channelR = $wpdb->get_row($sql);

						if (!$channelR)
							$sql="INSERT INTO `$table_name3` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('$username', '$room', $ztime, $ztime, $ztime, 0, $rtype)";
						elseif ($options['timeReset'] && $channelR->rdate < $ztime - $options['timeReset']*24*3600) //time to reset in days
							$sql="UPDATE `$table_name3` set type=$rtype, rdate=$ztime, wtime=0, btime=0 where owner='$username' and name='$room'";
						else
							$sql="UPDATE `$table_name3` set type=$rtype where owner='$username' and name='$room'";

						$wpdb->query($sql);



						if ($stream)
							if (VWliveStreaming::timeTo($stream . '/updateThumb', 300, $options))  //not too often
								{
								//update thumb
								$dir = $options['uploadsPath']. "/_snapshots";
								$thumbFilename = "$dir/$stream.jpg";

								//ip camera or playlist : update snapshot
								if (get_post_meta( $postID, 'vw_ipCamera', true ) || get_post_meta( $postID, 'vw_playlistActive', true ))
								{
									VWliveStreaming::streamSnapshot($stream, true);
									//$htmlCode .= 'Updating IP Cam Snapshot: ' . $stream;
								}




								//only if snapshot exists but missing post thumb (not uploaded or generated previously)
								if ( file_exists($thumbFilename) && !get_post_thumbnail_id( $postID ))
								{
									if ( !get_post_thumbnail_id( $postID ) ) //insert
										{
										$wp_filetype = wp_check_filetype(basename($thumbFilename), null );

										$attachment = array(
											'guid' => $thumbFilename,
											'post_mime_type' => $wp_filetype['type'],
											'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $thumbFilename, ".jpg" ) ),
											'post_content' => '',
											'post_status' => 'inherit'
										);

										$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
										set_post_thumbnail($postID, $attach_id);
									}
									else //update
										{
										$attach_id = get_post_thumbnail_id($postID );
										$thumbFilename = get_attached_file($attach_id);
									}

									//cleanup any relics
									if ($postID && $attach_id) VWliveStreaming::delete_associated_media($postID, false, $attach_id);

									//update
									$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
									wp_update_attachment_metadata( $attach_id, $attach_data );
								}
							}

						//snapshot
						$dir = $options['uploadsPath']. "/_snapshots";
						$thumbFilename = "$dir/$stream.jpg";

						$showImage=get_post_meta( $postID, 'showImage', true );

						if (!file_exists($thumbFilename) || $showImage =='all') //show thumb instead
							{
							$attach_id = get_post_thumbnail_id($postID );
							if ($attach_id) $thumbFilename = get_attached_file($attach_id);
						}

						$noCache = '';
						if ($age=='LIVE') $noCache='?'.((time()/10)%100);
						if (file_exists($thumbFilename) && !strstr($thumbFilename, '/.jpg') ) $thumbCode = '<IMG src="' . VWliveStreaming::path2url($thumbFilename) . $noCache .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px">';
						else $thumbCode = '<IMG SRC="' . plugin_dir_url(__FILE__). 'screenshot-3.jpg" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px">';

						//channel url
						$url = get_permalink($postID);


						$htmlCode .= '<tr><td><a href="' . $url . '"><h4>' . $channel->post_title . '</h4><div class="ui bordered rounded image">' .  $thumbCode . '</div></a>';


						//Features Info


						//transcode quick update (if settigns changed for role)
						$vw_transcode = get_post_meta( $postID, 'vw_transcode', true );
						if (VWliveStreaming::inList($userkeys, $options['transcode'])) $new_vw_transcode = 1;
						else $new_vw_transcode =0;
						if ($vw_transcode != $new_vw_transcode) update_post_meta($postID, 'vw_transcode', $new_vw_transcode);

						//info under channel snapshot
						$htmlCode .= '
<div class="ui message">';

					$edate = intval(get_post_meta( $postID, 'edate', true ));
	
					$htmlCode .= '<br> Last Broadcast: ' . ($edate ? date(DATE_RFC2822, $edate) : 'Never.');

						if ($channelR) $htmlCode .= '<br>Total Broadcast Time: ' . VWliveStreaming::format_time($channelR->btime) . ' / ' . VWliveStreaming::format_time($maximumBroadcastTime) .  '<br>Total Watch Time: ' . VWliveStreaming::format_time($channelR->wtime) . ' / ' . VWliveStreaming::format_time($maximumWatchTime);

						$htmlCode .= '<br> Level: ' . ($channelR->type>1?'Premium '. ($channelR->type-1):'Regular '. $channelR->type);


						if ($options['transcoding']) $htmlCode .= '<br> Transcoding: ' . ($vw_transcode?'Enabled':'Disabled');

						$htmlCode .= '<br> Logo: ' . get_post_meta( $postID, 'vw_logo', true );
						$htmlCode .= '<br> Ads: ' . get_post_meta( $postID, 'vw_ads', true );

						if (get_post_meta( $postID, 'vw_ipCamera', true )) $htmlCode .= '<br>IP Camera';
						if (get_post_meta( $postID, 'vw_playlistActive', true )) $htmlCode .= '<br>Playlist Scheduled';


						foreach (array('access','chat','write','participants','privateChat') as $field)
							if ($value = get_post_meta($postID, 'vw_'.$field.'List', true))
								$htmlCode .= '<br>' . ucwords($field) . ': ' . $value;
							$htmlCode .= '</div>';

						$htmlCode .= '<div>' . apply_filters("vw_ls_manage_channel", '', $channel->ID) . '</div>';


						$htmlCode .= '</td>';
						$htmlCode .= '<td width="210px; text-align=left">';


						//semantic ui
						VWliveStreaming::enqueueUI();


						$htmlCode .=  '
<div class="ui vertical menu">
      <a class="item" href="' . add_query_arg( 'editChannel', $channel->ID, $this_page) . '">Setup</a>
</div>

<div class="ui vertical menu">
 <div class="item header">Broadcast</div>
      <a class="item" href="' . add_query_arg(array('broadcast'=>''), get_permalink($channel->ID)) . '">Web Broadcast (Auto)</a>
      <a class="item" href="' . add_query_arg(array('flash-broadcast'=>''), get_permalink($channel->ID)) . '">Advanced (PC Flash)</a>
      ';

						if ($options['webrtc']) $htmlCode .= '
      <a class="item" href="' . add_query_arg(array('webrtc-broadcast'=>''), get_permalink($channel->ID)) . '">WebRTC (HTML5)</a>';

						if ($options['externalKeys']) $htmlCode .= '
      <a class="item" href="' . add_query_arg(array('external-broadcast'=>''), get_permalink($channel->ID)) . '">External Encoders (Apps)</a>';

						if ($options['playlists']) if (VWliveStreaming::inList($userkeys, $options['schedulePlaylists'])) $htmlCode .= '
      <a class="item" href="' . add_query_arg( 'editPlaylist', $channel->ID, $this_page) . '">Playlist</a>';

		if (VWliveStreaming::inList($userkeys, $options['ipCameras']))
						$htmlCode .= '<a href="'. add_query_arg( 'reStream', $channel->ID, $this_page).'" class="item">IP Camera / Stream</a>';

							$htmlCode .=  '
</div>

<div class="ui vertical menu">
 <div class="item header">Playback</div>
	  <a class="item" href="' . get_permalink($channel->ID) . '">Web View (Auto)</a>
      <a class="item" href="' . add_query_arg(array('flash-view'=>''), get_permalink($channel->ID)) . '">Advanced (PC Flash)</a>';

						if ( $options['transcoding'] || $options['webrtc'] ) $htmlCode .= '
      <a class="item" href="' . add_query_arg(array('html5-view'=>''), get_permalink($channel->ID)) . '">Web View (HTML5)</a>';


						$htmlCode .= '
      <a class="item" href="' . add_query_arg(array('video'=>''), get_permalink($channel->ID)) . '">Only Video (Auto)</a>
      <a class="item" href="' . add_query_arg(array('flash-video'=>''), get_permalink($channel->ID)) . '">Video (PC Flash)</a>
      ';

						if ($options['transcoding']) if (VWliveStreaming::inList($userkeys, $options['transcode'])) $htmlCode .= '
      <a class="item" href="' . add_query_arg(array('hls'=>''), get_permalink($channel->ID)) . '">Video HLS (iOS/Safari)</a>
      <a class="item" href="' . add_query_arg(array('mpeg'=>''), get_permalink($channel->ID)) . '">MPEG DASH (Android/Chrome)</a>';

							if ($options['webrtc']) $htmlCode .= '
      <a class="item" href="' . add_query_arg(array('webrtc-playback'=>''), get_permalink($channel->ID)) . '">Video WebRTC (HTML5)</a>';

							if ($options['externalKeys']) $htmlCode .= '
      <a class="item" href="' . add_query_arg(array('external-playback'=>''), get_permalink($channel->ID)) . '">Other Players, Embed</a>';

							$htmlCode .=  '
</div>';


						/*
						$htmlCode .= '<BR><BR><a class="videowhisperButtonLS g-btn type_red" href="' . add_query_arg(array('broadcast'=>''), get_permalink($channel->ID)) . '">Broadcast</a>';
						if ($options['webrtc']) $htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_red" href="' . add_query_arg(array('webrtc-broadcast'=>''), get_permalink($channel->ID)) . '">WebRTC Broadcast</a>';

						if ($options['externalKeys']) $htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_pink" href="' . add_query_arg(array('external'=>''), get_permalink($channel->ID)) . '">External Apps</a>';
						$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_green" href="' . get_permalink($channel->ID) . '">Chat &amp; Video</a>';
						$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_green" href="' . add_query_arg(array('video'=>''), get_permalink($channel->ID)) . '">Video Only</a>';
						if ($options['webrtc']) $htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_green" href="' . add_query_arg(array('webrtc-playback'=>''), get_permalink($channel->ID)) . '">WebRTC Playback</a>';


						$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_yellow" href="' . add_query_arg( 'editChannel', $channel->ID, $this_page) . '">Setup</a>';

						if ($options['playlists'])
							if (VWliveStreaming::inList($userkeys, $options['schedulePlaylists']))
								$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_yellow" href="' . add_query_arg( 'editPlaylist', $channel->ID, $this_page) . '">Playlist</a>';
*/

						$htmlCode .= '</td></tr>';
						//filter under channel

					}
					$htmlCode .= '</table>';

					$htmlCode .= '<small>Only administrator can delete channels. Associated data may be needed for report review, moderation purposes.</small>';

				}
				else
					$htmlCode .= "<div class='warning'>You don't have any channels, yet!</div>";

				$htmlCode .= apply_filters("vw_ls_manage_channels_foot", '');
			}


			//! Setup IP Camera / Re-Stream
			$reStream = intval($_GET['reStream']);

			if ($reStream) $htmlCode .= do_shortcode('[videowhisper_stream_setup channel_id="' . $reStream . '"]');
			//! Edit Channel Form

			//setup
			$editPost = intval($_GET['editChannel']);

			if ($editPost)
			{

				$newCat = -1;

				if ($editPost > 0)
				{
					$channel = get_post( $editPost );
					if ($channel->post_author != $current_user->ID) return "<div class='ui error segment'>Not allowed (different owner)!</div>";

					$newDescription = $channel->post_content;
					$newName = $channel->post_title;
					$newComments = $channel->comment_status;

					$cats = wp_get_post_categories( $editPost);
					if (count($cats)) $newCat = array_pop($cats);
				}

				if ($editPost<1)
				{
					$editPost = -1;

					$newTitle = 'New';

					$newName = sanitize_file_name($username);
					if ($channels_count) $newName .= '_' . base_convert(time()-1225000000,10,36);
					$nameField = 'text';
					$newNameL = '';
				}
				else
				{
					$nameField = 'hidden';
					$newNameL = $newName;
				}

				//semantic ui
				VWliveStreaming::enqueueUI();


				$commentsCode = '';
				$commentsCode .= '<select class="ui dropdown" id="newcomments" name="newcomments">';
				$commentsCode .= '<option value="closed" ' . ($newComments=='closed'?'selected':'') . '>Closed</option>';
				$commentsCode .= '<option value="open" ' . ($newComments=='open'?'selected':'') . '>Open</option>';
				$commentsCode .= '</select>';


				$categories = wp_dropdown_categories('show_count=1&echo=0&class=ui+dropdown&name=newcategory&hide_empty=0&selected=' . $newCat);

				//! channel features
				$extraRows = '';

				//roomTags
				if (VWliveStreaming::inList($userkeys, $options['roomTags']))
				{
					if ($editPost)  $tags = wp_get_post_tags($editPost, array( 'fields' => 'names' ));
					//var_dump($tags);
					$value = '';

					if ( ! empty( $tags ) ) if ( ! is_wp_error( $tags ) )
							foreach( $tags as $tag )  $value .= ($value?', ':'') . $tag;

							$extraRows .= '<tr><td>' . __('Tags', 'ppv-live-webcams') . '</td><td><textarea rows=2 cols="80" name="roomTags" id="roomTags">' . $value . '</textarea><BR>' . __('Tags separated by comma.', 'ppv-live-webcams') . '</td></tr>';
				}



				//accessPassword
				if (VWliveStreaming::inList($userkeys, $options['accessPassword']))
				{
					if ($editPost) $value = $channel->post_password;
					else $value = '';

					$extraRows .= '<tr><td>Access Password</td><td><input size=16 name="accessPassword" id="accessPassword" value="' . $value . '"><BR>Password to protect channel. Leave blank to not require password.</td></tr>';
				}

				//permission lists
				$permInfo = array(
					'access'=>'Can access channel.',
					'chat'=>'Can view public chat.',
					'write'=>'Can write in public chat.',
					'participants'=>'Can view participants list.',
					'privateChat'=>'Can initiate private chat with users from participants list.'
				);

				foreach (array('access','chat','write','participants','privateChat') as $field)
					if (VWliveStreaming::inList($userkeys, $options[$field . 'List']))
					{
						if ($editPost) $value = get_post_meta( $editPost, 'vw_'.$field.'List', true );
						else $value = '';

						$extraRows .= '<tr><td>'.ucwords($field).' List</td><td><textarea rows=2 cols=60 name="'.$field.'List" id="'.$field.'List">' . $value . '</textarea><BR>' .$permInfo[$field]. ' Define user list as roles, logins, emails separated by comma. Leave empty to allow everybody or set None to disable.</td></tr>';
					}

				//accessPrice
				if (VWliveStreaming::inList($userkeys, $options['accessPrice']))
				{
					if ($editPost>0) $value = get_post_meta( $editPost, 'vw_accessPrice', true );
					else $value = '0.00';

					$extraRows .= '<tr><td>Access Price</td><td><input size=5 name="accessPrice" id="accessPrice" value="' . $value . '"><BR>Channel access price. Leave 0 for free access.</td></tr>';
				}

				//logoCustom
				if (VWliveStreaming::inList($userkeys, $options['logoCustom']))
				{
					if ($editPost>0) $value = get_post_meta( $editPost, 'vw_logoImage', true );
					else $value =  $options['overLogo'];

					$extraRows .= '<tr><td>Logo Image</td><td><input size=64 name="logoImage" id="logoImage" value="' . $value . '"><BR>Channel floating logo URL (preferably a transparent PNG image). Leave blank to hide.</td></tr>';
					if ($editPost>0) $value = get_post_meta( $editPost, 'vw_logoLink', true );
					else $value = $options['overLink'];

					$extraRows .= '<tr><td>Logo Link</td><td><input size=64 name="logoLink" id="logoImage" value="' . $value . '"><BR>URL to open on logo click.</td></tr>';
				}


				//ipCameras
				if (VWliveStreaming::inList($userkeys, $options['ipCameras']))
				{
					if ($editPost>0) $value = get_post_meta( $editPost, 'vw_ipCamera', true );
					else $value = '';

					$extraRows .= '<tr><td>IP Camera Stream</td><td><input size=64 name="ipCamera" id="ipCamera" value="' . $value . '"><BR>Insert address exactly as it works in <a target="_blank" href="http://www.videolan.org/vlc/index.html">VLC</a> or other player. For increased playback support, H264 video with AAC audio encoded streams should be used. Address should use one of these protocols: rtsp://, udp://, rtmp://, rtmps://, wowz://, wowzs://, http://, https:// .</td></tr>';
				}



				//adsCustom
				if (VWliveStreaming::inList($userkeys, $options['adsCustom']))
				{
					if ($editPost>0) $value = get_post_meta( $editPost, 'vw_adsServer', true );
					else $value = $options['adServer'];

					$extraRows .= '<tr><td>Ads Server</td><td><input size=64 name="adsServer" id="adsServer" value="' . $value . '"><BR>See <a href="http://www.adinchat.com" target="_blank"><U><b>AD in Chat</b></U></a> compatible ad management server. Leave blank to disable.</td></tr>';
				}

				//uploadPicture
				if (VWliveStreaming::inList($userkeys, $options['uploadPicture']))
				{

					$extraRows .= '<tr><td>Picture</td><td><input class="ui button" type="file" name="uploadPicture" id="uploadPicture"><BR>Update channel picture.</td></tr>';


					$value=get_post_meta( $editPost, 'showImage', true );

					$extraRows .= '<tr><td>Show Picture</td><td><select class="ui dropdown" name="showImage" id="showImage">';
					$extraRows .= '<option value="event" '.($value=='event'?'selected':'').'>Event Info</option>';
					$extraRows .= '<option value="all" '.($value=='all'?'selected':'').'>Everywhere</option>';
					$extraRows .= '<option value="no" '.($value=='no'?'selected':'').'>No</option>';
					$extraRows .= '</select><BR>Configure picture display.</td></tr>';
				}

				if (VWliveStreaming::inList($userkeys, $options['eventDetails']))
				{
					if ($editPost>0) $value = get_post_meta( $editPost, 'eventTitle', true );

					$extraRows .= '<tr><td>Event Title</td><td><input size=64 name="eventTitle" id="eventTitle" value="' . $value . '"></td></tr>';

					if ($editPost>0) $value = get_post_meta( $editPost, 'eventStart', true );
					if ($editPost>0) $valueTime = get_post_meta( $editPost, 'eventStartTime', true );
					$extraRows .= '<tr><td>Event Start</td><td>Date: <input size=32 name="eventStart" id="eventStart" value="' . $value . '"> Time: <input size=32 name="eventStartTime" id="eventStartTime" value="' . $valueTime . '"></td></tr>';

					if ($editPost>0) $value = get_post_meta( $editPost, 'eventEnd', true );
					if ($editPost>0) $valueTime = get_post_meta( $editPost, 'eventEndTime', true );
					$extraRows .= '<tr><td>Event End</td><td>Date: <input size=32 name="eventEnd" id="eventEnd" value="' . $value . '"> Time: <input size=32 name="eventEndTime" id="eventEndTime" value="' . $valueTime . '"></td></tr>';

					if ($editPost>0) $value = get_post_meta( $editPost, 'eventDescription', true );
					$extraRows .= '<tr><td>Event Description</td><td><textarea rows=3 cols=60 name="eventDescription" id="eventDescription">' . $value . '</textarea><br>Event details also show when channel is offline or inaccessible.</td></tr>';

				}


				if ($editPost > 0 || $channels_count < $maxChannels)
					$htmlCode .= <<<HTMLCODE
<script language="JavaScript">
		function censorName()
			{
				document.adminForm.room.value = document.adminForm.room.value.replace(/^[\s]+|[\s]+$/g, '');
				document.adminForm.room.value = document.adminForm.room.value.replace(/[^0-9a-zA-Z_\-]+/g, '-')
				document.adminForm.room.value = document.adminForm.room.value.replace(/\-+/g, '-');
				document.adminForm.room.value = document.adminForm.room.value.replace(/^\-+|\-+$/g, '');
				if (document.adminForm.room.value.length>0) return true;
				else
				{
				alert("A channel name is required!");
				return false;
				}
			}
</script>

<div class="ui form">
<form method="post" enctype="multipart/form-data"  action="$this_page" name="adminForm" class="w-actionbox">
<h3>Setup $newTitle Channel</h3>
<table class="ui celled table selectable">
<tr><td>Name</td><td><input name="newname" type="$nameField" id="newname" value="$newName" size="20" maxlength="64" onChange="censorName()"/>$newNameL <input class="ui button small" type="submit" name="button" id="button" value="Setup" /></td></tr>
<tr><td>Description</td><td><textarea rows=3 cols=60 name='description' id='description'>$newDescription</textarea></td></tr>
<tr><td>Category</td><td>$categories</td></tr>
<tr><td>Comments</td><td>$commentsCode</td></tr>
$extraRows
<tr><td></td><td><input class="ui button primary" type="submit" name="button" id="button" value="Setup" /></td></tr>
</table>
<input type="hidden" name="editPost" id="editPost" value="$editPost" />
</form>
</div>

<script>


jQuery(document).ready(function(){
				jQuery(".ui.dropdown").dropdown();
});

</script>
HTMLCODE;
			}

			$htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;

		}


		static function enqueueUI()
		{
			//semantic ui
			wp_enqueue_script( 'jquery' );
			wp_enqueue_style( 'semantic', plugin_dir_url(  __FILE__ ) . '/scripts/semantic/semantic.min.css');
			wp_enqueue_script( 'semantic', plugin_dir_url(  __FILE__ ) . '/scripts/semantic/semantic.min.js', array('jquery'));

		}

		function videowhisper_channels($atts)
		{
			$options = get_option('VWliveStreamingOptions');

			$atts = shortcode_atts(
				array(
					'per_page' => $options['perPage'],
					'ban' => '0',
					'perrow' => '',
					'order_by' => 'edate',
					'category_id' => '',
					'tags' => '',
					'name' => '',
					'select_category' => '1',
					'select_tags' => '1',
					'select_name' => '1',
					'select_order' => '1',
					'select_page' => '1',
					'include_css' => '1',
					'url_vars' => '1',
					'url_vars_fixed' => '1',
					'id' => ''
				), $atts, 'videowhisper_channels');

			$id = $atts['id'];
			if (!$id) $id = uniqid();

			if ($atts['url_vars'])
			{
				$cid = (int) $_GET['cid'];
				if ($cid)
				{
					$atts['category_id'] = $cid;
					if ($atts['url_vars_fixed']) $atts['select_category'] = '0';
				}
			}

			//semantic ui : listings
			VWliveStreaming::enqueueUI();



			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_channels&pp=' . $atts['per_page']. '&pr=' . $atts['perrow'] . '&ob=' . $atts['order_by'] . '&cat=' . $atts['category_id'] . '&sc=' . $atts['select_category'] . '&sn=' . $atts['select_name'] .  '&sg=' . $atts['select_tags'] . '&so=' . $atts['select_order'] . '&sp=' . $atts['select_page']. '&id=' .$id . '&tags=' . urlencode($atts['tags']) . '&name=' . urlencode($atts['name']);

			if ($atts['ban']) $ajaxurl .= '&ban=' . $atts['ban'];

			$htmlCode = <<<HTMLCODE
<script>
var aurl$id = '$ajaxurl';
var \$j = jQuery.noConflict();
var loader$id;

	function loadChannels$id(message){

	if (message)
	if (message.length > 0)
	{
	  \$j("#videowhisperChannels$id").html(message);
	}

		if (loader$id) loader$id.abort();

		loader$id = \$j.ajax({
			url: aurl$id,
			success: function(data) {
				\$j("#videowhisperChannels$id").html(data);
				jQuery(".ui.dropdown").dropdown();
				jQuery(".ui.rating.readonly").rating("disable");
			}
		});
	}

jQuery(document).ready(function(){
	loadChannels$id();
	setInterval("loadChannels$id()", 10000);
});

</script>

<div id="videowhisperChannels$id">

<div class="ui active inline text large loader">Loading channels...</div>

</div>
HTMLCODE;

			$htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;
		}

		function flash_warn()
		{

			$htmlCode = <<<HTMLCODE

<div id="flashWarning"></div>

<script>
var hasFlash = ((typeof navigator.plugins != "undefined" && typeof navigator.plugins["Shockwave Flash"] == "object") || (window.ActiveXObject && (new ActiveXObject("ShockwaveFlash.ShockwaveFlash")) != false));

var flashWarn = '<small>Using the Flash web based interface requires <a rel="nofollow" target="_flash" href="https://get.adobe.com/flashplayer/">latest Flash plugin</a> and <a rel="nofollow" target="_flash" href="https://helpx.adobe.com/flash-player.html">activating plugin in your browser</a>. Flash apps are recommended on PC for best latency and most advanced features.</small>'

if (!hasFlash) document.getElementById("flashWarning").innerHTML = flashWarn;</script>
HTMLCODE;


			return $htmlCode;
		}

		function flash_watch($stream, $width='100%', $height='100%')
		{
			$stream = sanitize_file_name($stream);

			$streamLabel = preg_replace('/[^A-Za-z0-9\-\_]/', '', $stream);

			$swfurl = plugin_dir_url(__FILE__) . "ls/live_watch.swf?ssl=1&n=" . urlencode($stream);
			$swfurl .= "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vwls&task=');
			$swfurl .= '&extension='.urlencode('_none_');
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'ls/');

			$bgcolor="#333333";

			$htmlCode = <<<HTMLCODE
<div id="videowhisper_container_$streamLabel">
<object id="videowhisper_watch_$streamLabel" width="$width" height="$height" type="application/x-shockwave-flash" data="$swfurl">
<param name="movie" value="$swfurl"></param><param bgcolor="$bgcolor"><param name="scale" value="noscale" /> </param><param name="salign" value="lt"></param><param name="allowFullScreen"
value="true"></param><param name="allowscriptaccess" value="always"></param>
</object>
</div>
HTMLCODE;

			$htmlCode .= VWliveStreaming::flash_warn();

			return $htmlCode ;
		}


		function videowhisper_watch($atts)
		{
			$stream = '';

			$options = get_option('VWliveStreamingOptions');


			if (is_single())
				if (get_post_type( get_the_ID() ) ==  $options['custom_post'] ) $stream = get_the_title(get_the_ID());

				$atts = shortcode_atts(array(
						'channel' => $stream,
						'width' => '100%',
						'height' => '100%',
						'flash' => '0',
						'html5' => 'auto'
					), $atts, 'videowhisper_watch');

			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['n'];
			$stream = sanitize_file_name($stream);

			if (!$stream)
			{
				return "Watch Error: Missing channel name!";
			}

			//used by flash container
			$width=$atts['width']; if (!$width) $width = "100%";
			$height=$atts['height']; if (!$height) $height = "100%";

			/*
			if ($iOS || $Safari) return $htmlCode.'<!--H5-HLS-->'. do_shortcode("[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" webstatus=\"1\"]");
			else return $htmlCode.'<!--H5-MPEG-->'. do_shortcode("[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" webstatus=\"1\"]");
			*/

			//detect for video interface
			/*
			if ( ($Android && in_array($options['detect_mpeg'], array('android', 'all'))) || (!$iOS && in_array($options['detect_mpeg'], array('all'))) || (!$iOS && !$Safari && in_array($options['detect_mpeg'], array('nonsafari'))) )
				return $htmlCode .'<!--MPEG-->'. do_shortcode("[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" webstatus=\"1\"]");

			if ( (($Android||$iOS) && in_array($options['detect_hls'], array('mobile','safari', 'all'))) || ($iOS && $options['detect_hls'] == 'ios') || ($Safari && in_array($options['detect_hls'], array('safari', 'all'))) ) return $htmlCode .'<!--HLS-->'. do_shortcode("[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" webstatus=\"1\"]");
			*/

			if (!$atts['flash'])
			{
				//HLS if iOS/Android detected
				$agent = $_SERVER['HTTP_USER_AGENT'];
				$Android = stripos($agent,"Android");
				$iOS = ( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'));
				$Safari = (strstr($agent,"Safari") && !strstr($agent,"Chrome"));
				$Firefox = stripos($agent,"Firefox");

				$htmlCode .= "<!--VideoWhisper-Agent-Watch:$agent|A:$Android|I:$iOS|S:$Safari|F:$Firefox-->";

				$showHTML5 = 0;

				//always
				if ($atts['html5'] == 'always') $showHTML5 = 1;

				//preferred transcoded playback
				if ($options['transcoding'] >= 3) $showHTML5 = 1;
				if ($options['webrtc'] >= 3) $showHTML5 = 1;

				if ( ($Android && in_array($options['detect_mpeg'], array('android', 'all'))) || (!$iOS && in_array($options['detect_mpeg'], array('all'))) || (!$iOS && !$Safari && in_array($options['detect_mpeg'], array('nonsafari'))) ) $showHTML5 = 1;

				if ( (($Android||$iOS) && in_array($options['detect_hls'], array('mobile','safari', 'all'))) || ($iOS && $options['detect_hls'] == 'ios') || ($Safari && in_array($options['detect_hls'], array('safari', 'all'))) ) $showHTML5 = 1;

				if ($showHTML5) return $htmlCode.'<!--H5-HLS-->' . do_shortcode("[videowhisper_htmlchat_playback channel=\"$stream\"]");

			} else $htmlCode .= "<!--VideoWhisper-Watch:Flash-->";


			//show flash_watch

			$options = get_option('VWliveStreamingOptions');
			$watchStyle = html_entity_decode($options['watchStyle']);

			$streamLabel = preg_replace('/[^A-Za-z0-9\-\_]/', '', $stream);


			$afterCode = <<<HTMLCODE
<br style="clear:both" />

<style type="text/css">
<!--

#videowhisper_container_$streamLabel
{
$watchStyle
}

-->
</style>

HTMLCODE;

			//Available HTML5
			if ($options['transcoding'] >= 2)
			{
				global $wpdb ;
				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $stream . '\' and post_type=\'' . $options['custom_post'] . '\' LIMIT 0,1' );
				if ($postID) $afterCode .= '<p><a class="ui button secondary" href="' . add_query_arg(array('html5-view'=>''), get_permalink($postID)) . '">Try HTML5 View</a></p>';
			}


			return VWliveStreaming::flash_watch($stream, $width, $height) . $afterCode ;

		}

		function transcodeStreamWebRTC($stream, $postID, $options = null, $detect=2)
		{
			//transcode for WebRTC usage: RTMP/RTSP as necessary

			if (!$stream) return;
			if (!$options)  $options = get_option('VWliveStreamingOptions');

			if (!$options['webrtc']) return;

			if (!VWliveStreaming::timeTo($stream . '/transcodeCheckWebRTC-Flood', 3, $options)) return; //prevent duplicate checks

			// check every 59s
			$tooSoon = 0;
			if (!VWliveStreaming::timeTo($stream . '/transcodeCheckWebRTC', 59, $options)) $tooSoon = 1;

			$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);

			if (!$sourceProtocol) $sourceProtocol = 'rtmp'; //assuming plain wowza stream

			if ($sourceProtocol == 'rtmp') //source available as RTMP (flash, external)
				{

				//RTMP to RTSP (h264/opus)
				$stream_webrtc = $stream . '_webrtc';

				if ($tooSoon) return $stream_webrtc;

				//detect transcoding process - cancel if already started
				$cmd = "ps aux | grep '/$stream_webrtc -i rtmp'";
				exec($cmd, $output, $returnvalue);

				$transcoding = 0;
				foreach ($output as $line)
					if (strstr($line, "ffmpeg"))
					{
						$transcoding = 1;
						break;
					}

				//rtmp keys
				if ($options['externalKeysTranscoder'])
				{
					$current_user = wp_get_current_user();
					$keyView = md5('vw' . $options['webKey']. $postID);
					$rtmpAddressView = $options['rtmp_server'] . '?'. urlencode('ffmpeg_' . $stream_webrtc) .'&'. urlencode($stream) .'&'. $keyView . '&0&videowhisper';

				}
				else
				{
					$rtmpAddress = $options['rtmp_server'];
					$rtmpAddressView = $options['rtmp_server'];
				}


				//paths
				$uploadsPath = $options['uploadsPath'];
				if (!file_exists($uploadsPath)) mkdir($uploadsPath);
				$upath = $uploadsPath . "/$stream/";
				if (!file_exists($upath)) mkdir($upath);


				if (!$transcoding) //transcode to rtsp
					{

					//start transcoding process
					$log_file =  $upath . "transcode_rtmp-webrtc.log";

					$cmd = $options['ffmpegPath'] .' ' .  $options['ffmpegTranscodeRTC'] .
						" -threads 1 -f rtsp \"" . $options['rtsp_server_publish'] . '/'. $stream_webrtc .
						"\" -i \"" . $rtmpAddressView ."/". $stream . "\" >&$log_file & ";


					//echo $cmd;
					exec($cmd, $output, $returnvalue);
					exec("echo '$cmd' >> $log_file.cmd", $output, $returnvalue);

					update_post_meta( $postID, 'stream-webrtc',  $stream_webrtc );
				}
				else update_post_meta($postID, 'transcoding-webrtc', time());
			}


			if ($sourceProtocol == 'rtsp') //source available as RTSP (WebRTC)
				{
				//RTSP to HLS/RTMP (h264/aac)
				$stream_hls = 'i_' . $stream;

				if ($tooSoon) return $stream_hls;


				//detect transcoding process - cancel if already started
				$cmd = "ps aux | grep '/$stream_hls -i rtsp'";
				exec($cmd, $output, $returnvalue);

				$transcoding = 0;
				foreach ($output as $line)
					if (strstr($line, "ffmpeg"))
					{
						$transcoding = 1;
						break;
					}

				//rtmp keys
				if ($options['externalKeysTranscoder'])
				{
					$channel = get_post($postID);
					$userID = $channel->post_author;

					$key = md5('vw' . $options['webKey'] . $userID . $postID);
					$rtmpAddress = $options['rtmp_server'] . '?'. urlencode($stream_hls) .'&'. urlencode($stream) .'&'. $key . '&1&' . $userID . '&videowhisper';
				}
				else
				{
					$rtmpAddress = $options['rtmp_server'];
					$rtmpAddressView = $options['rtmp_server'];
				}

				//paths
				$uploadsPath = $options['uploadsPath'];
				if (!file_exists($uploadsPath)) mkdir($uploadsPath);
				$upath = $uploadsPath . "/$stream/";
				if (!file_exists($upath)) mkdir($upath);


				if (!$transcoding) //transcode to rtmp
					{

					if ($detect == 2 || ($detect == 1 && !$videoCodec))
					{

						//detect webrtc stream info
						$log_file =  $upath . "streaminfo-webrtc.log";

						$cmd = 'timeout -s KILL 3 ' . $options['ffmpegPath'] .' -y -i "' . $options['rtsp_server'] . '/' . $stream . '" 2>&1 ';
						$info = shell_exec($cmd);

						//video
						if (!preg_match('/Stream #(?:[0-9\.]+)(?:.*)\: Video: (?P<videocodec>.*)/',$info,$matches))
							preg_match('/Could not find codec parameters \(Video: (?P<videocodec>.*)/',$info,$matches);
						list($videoCodec) = explode(' ',$matches[1]);
						if ($videoCodec && $postID) update_post_meta( $postID, 'stream-codec-video', strtolower($videoCodec) );

						//audio
						$matches = array();
						if (!preg_match('/Stream #(?:[0-9\.]+)(?:.*)\: Audio: (?P<audiocodec>.*)/',$info,$matches))
							preg_match('/Could not find codec parameters \(Audio: (?P<audiocodec>.*)/',$info,$matches);

						list($audioCodec) = explode(' ',$matches[1]);
						$audioCodec = trim($audioCodec, " ,.\t\n\r\0\x0B");
						if ($audioCodec && $postID) update_post_meta( $postID, 'stream-codec-audio', strtolower($audioCodec) );
						if (($videoCodec || $audioCodec) && $postID) update_post_meta( $postID, 'stream-codec-detect', time() );

						exec("echo '". "$stream|$stream_hls|$stream_webrtc|$transcodeEnabled|$detect|$videoCodec|$audioCodec" ."' >> $log_file", $output, $returnvalue);
						exec("echo \"". addslashes($info)."\" >> $log_file", $output, $returnvalue);

						//
					}


					//start transcoding process
					$log_file =  $upath . "transcode_webrtc-hls.log";

					if ($videoCodec && $audioCodec) //if incomplete, transcode later
						{
						//convert command
						$cmd = $options['ffmpegPath'] .' ' .  $options['ffmpegTranscode'] . " -threads 1 -f flv \"" .
							$rtmpAddress . "/". $stream_hls . "\" -i \"" . $options['rtsp_server'] ."/". $stream . "\" >&$log_file & ";

						//echo $cmd;
						exec($cmd, $output, $returnvalue);
						exec("echo '$cmd' >> $log_file.cmd", $output, $returnvalue);

						update_post_meta( $postID, 'stream-hls',  $stream_hls );
					}
					else exec("echo 'Stream incomplete. Will check again later... ' >> $log_file", $output, $returnvalue);

				}
				else update_post_meta($postID, 'transcoding-hls', time());

			}

		}

		function transcodeStream($stream, $required=0, $detect=2, $convert=1)
		{
			//$stream = room name
			//$detect: 0 = no, 1 = auto, 2 = always (update)
			//$convert: 0 = no, 1 = auto , 2 = always

			if (!$stream) return;

			$options = get_option('VWliveStreamingOptions');

			if ( !$options['transcodingAuto'] && $convert != 2) return; //disabled

			if (!VWliveStreaming::timeTo($stream . '/transcodeCheckRTMP-Flood', 3, $options)) return; //prevent duplicate checks

			$stream_hls = 'i_'. $stream;

			// check every 59s
			if (!$required)
				if (!VWliveStreaming::timeTo($stream . '/transcodeCheckRTMP', 59, $options)) return $stream_hls;


				//is it a post channel?
				global $wpdb;
			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='channel' LIMIT 0,1" );

			//is feature enabled?
			if ($postID)
			{
				$transcodeEnabled = get_post_meta($postID, 'vw_transcode', true);
				$videoCodec = get_post_meta($postID, 'stream-codec-video', true);
			}
			else
			{
				if ($options['anyChannels'] || $options['userChannels']) $transcodeEnabled = 1;
			}

			//also transcode for webrtc if enabled
			if ($options['webrtc']) VWliveStreaming::transcodeStreamWebRTC($stream, $postID, $options);


			//RTMP to HLS/RTMP (H264/AAC)

			$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);
			if ($sourceProtocol == 'rtsp') return $stream_hls; //transcoded by transcodeStreamWebRTC() - use that


			//HLS

			//detect transcoding process - cancel if already started
			$cmd = "ps aux | grep '/$stream_hls -i rtmp'";
			exec($cmd, $output, $returnvalue);
			//var_dump($output);

			$transcoding = 0;
			foreach ($output as $line)
				if (strstr($line, "ffmpeg"))
				{
					$transcoding = 1;
					break;
				}

			if ($transcoding) return $stream_hls; //already transcoding - nothing to do


			//rtmp keys
			if ($options['externalKeysTranscoder'])
			{
				$current_user = wp_get_current_user();

				$key = md5('vw' . $options['webKey'] . $current_user->ID . $postID);

				$keyView = md5('vw' . $options['webKey']. $postID);

				//?session&room&key&broadcaster&broadcasterid
				$rtmpAddress = $options['rtmp_server'] . '?'. urlencode($stream_hls) .'&'. urlencode($stream) .'&'. $key . '&1&' . $current_user->ID . '&videowhisper';
				$rtmpAddressView = $options['rtmp_server'] . '?'. urlencode('ffmpeg_' . $stream) .'&'. urlencode($stream) .'&'. $keyView . '&0&videowhisper';
				$rtmpAddressViewI = $options['rtmp_server'] . '?'. urlencode('ffmpegInfo_' . $stream) .'&'. urlencode($stream) .'&'. $keyView . '&0&videowhisper';

			}
			else
			{
				$rtmpAddress = $options['rtmp_server'];
				$rtmpAddressView = $options['rtmp_server'];
			}

			//paths
			$uploadsPath = $options['uploadsPath'];
			if (!file_exists($uploadsPath)) mkdir($uploadsPath);

			$upath = $uploadsPath . "/$stream/";
			if (!file_exists($upath)) mkdir($upath);


			//detect codecs - do transcoding only if necessary
			if ($detect == 2 || ($detect == 1 && !$videoCodec))
			{

				$log_file =  $upath . "streaminfo-rtmp.log";

				$cmd = 'timeout -s KILL 3 ' . $options['ffmpegPath'] .' -y -i "' . $rtmpAddressViewI .'/'. $stream . '" 2>&1 ';
				$info = shell_exec($cmd);

				//video
				if (!preg_match('/Stream #(?:[0-9\.]+)(?:.*)\: Video: (?P<videocodec>.*)/',$info,$matches))
					preg_match('/Could not find codec parameters \(Video: (?P<videocodec>.*)/',$info,$matches);
				list($videoCodec) = explode(' ',$matches[1]);
				if ($videoCodec && $postID) update_post_meta( $postID, 'stream-codec-video', strtolower($videoCodec) );

				//audio
				$matches = array();
				if (!preg_match('/Stream #(?:[0-9\.]+)(?:.*)\: Audio: (?P<audiocodec>.*)/',$info,$matches))
					preg_match('/Could not find codec parameters \(Audio: (?P<audiocodec>.*)/',$info,$matches);

				list($audioCodec) = explode(' ',$matches[1]);
				$audioCodec = trim($audioCodec, " ,.\t\n\r\0\x0B");
				if ($audioCodec && $postID) update_post_meta( $postID, 'stream-codec-audio', strtolower($audioCodec) );

				if (($videoCodec || $audioCodec) && $postID) update_post_meta( $postID, 'stream-codec-detect', time() );

				exec("echo '". "$stream|$stream_hls|$transcodeEnabled|$required|$detect|$convert|$videoCodec|$audioCodec" ."' >> $log_file", $output, $returnvalue);

				exec("echo \"". addslashes($info)."\" >> $log_file", $output, $returnvalue);

				exec("echo '$cmd' >> $log_file.cmd", $output, $returnvalue);
			}


			//do any conversions after detection
			if ($convert)
			{


				if ($postID)
				{

					if (!$videoCodec) $videoCodec = get_post_meta($postID, 'stream-codec-video', true);
					if (!$audioCodec) $audioCodec = get_post_meta($postID, 'stream-codec-audio', true);

					//valid mp4 for html5 playback?
					if (($videoCodec == 'h264') && ($audioCodec == 'aac')) $isMP4 =1;
					else $isMP4 = 0;

					if ($isMP4 && $convert == 1)
					{
						update_post_meta( $postID, 'stream-hls', $stream ); //stream is good for hls (when broadcast directly AAC with OBS)
						return $stream; //present format is fine - no conversion required
					}

				}

				if (!$transcodeEnabled) return ''; //transcoding disabled


				//start transcoding process
				$log_file =  $upath . "transcode-rtmp.log";

				if  ($videoCodec && $audioCodec) //if incomplete, transcode later
					{

					//convert command
					$cmd = $options['ffmpegPath'] .' ' .  $options['ffmpegTranscode'] . " -threads 1 -f flv \"" .
						$rtmpAddress . "/". $stream_hls . "\" -i \"" . $rtmpAddressView ."/". $stream . "\" >&$log_file & ";

					//log and executed cmd
					exec("echo '$convert|$transcodeEnabled|$stream|$stream_hls|$postID|$isMP4:: $cmd' >> $log_file.cmd", $output, $returnvalue);
					exec($cmd, $output, $returnvalue);

					//$cmd = "ps aux | grep '/i_$stream -i rtmp'";
					//exec($cmd, $output, $returnvalue);

					update_post_meta( $postID, 'stream-hls', $stream_hls );

				}
				else exec("echo 'Stream incomplete. Will check again later... ' >> $log_file", $output, $returnvalue);



				return $stream_hls;
			}


		}

		function videowhisper_hls($atts)
		{
			$stream = '';

			$options = get_option('VWliveStreamingOptions');

			if (is_single())
				if (get_post_type( $postID = get_the_ID() ) == $options['custom_post']) $stream = get_the_title($postID);

				$atts = shortcode_atts(array('channel' => $stream, 'width' => '480px', 'height' => '360px', 'silent' => '0'), $atts, 'videowhisper_hls');

			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['n'];

			$stream = sanitize_file_name($stream);

			$width=$atts['width']; if (!$width) $width = "480px";
			$height=$atts['height']; if (!$height) $height = "360px";

			if (!$stream)
			{
				return "Watch HLS Error: Missing channel name!";
			}

			//get channel id $postID
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			//auto transcoding (on request)
			if ($options['transcodingAuto'])
			{
				$streamName = VWliveStreaming::transcodeStream($stream, 1); //require transcoding name
			}

			//get compatible stream
			$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);
			if ($sourceProtocol == 'rtsp') //requires transcoding
				{
				$stream_hls = get_post_meta( $postID, 'stream-hls',  true );
				if ($stream_hls) $streamName = $stream_hls;
			}

			if (!$streamName) $streamName = $stream;


			if ($streamName)
			{
				$streamURL = $options['httpstreamer'] . $streamName . '/playlist.m3u8';


				$dir = $options['uploadsPath']. "/_thumbs";
				$thumbFilename = "$dir/" . $stream . ".jpg";
				$thumbUrl =  VWliveStreaming::path2url($thumbFilename);

				$codecAudio = get_post_meta($postID, 'stream-codec-audio', true);

				$htmlCode = <<<HTMLCODE
<!--HLS:$postID:$sourceProtocol:$stream:$stream_hls:$codecAudio-->
<video id="videowhisper_hls_$stream" class="videowhisper_htmlvideo" width="$width" height="$height" autobuffer autoplay playsinline muted controls poster="$thumbUrl">
 <source src="$streamURL" type='video/mp4'>
    <div class="fallback" style="display:none">
        <IMG SRC="$thumbUrl">
	    <p>You must have an HTML5 capable browser with HLS support (Ex. Safari) to open this live stream: $streamURL</p>
	</div>
</video>
HTMLCODE;
			}
			else $htmlCode = 'HLS format is not available and can not be transcoded for stream: '. $stream;

			if ($options['transcodingWarning']>=2 & !$atts['silent']) $htmlCode .= '<p class="info"><small>HLS Playback: for iOS and Safari. Enable sound from controls. Transcoding and HTTP based delivery technology involve high latency and availability delay (may take dozens of seconds for transcoder to start stream to become available, after broadcast starts).</small></p>';

			return $htmlCode;
		}

		function videowhisper_mpeg($atts)
		{

			$stream = '';

			$options = get_option('VWliveStreamingOptions');

			if (is_single())
				if (get_post_type( $postID = get_the_ID() ) == $options['custom_post']) $stream = get_the_title($postID);

				$atts = shortcode_atts(array('channel' => $stream, 'width' => '480px', 'height' => '360px','silent' => '0'), $atts, 'videowhisper_mpeg');


			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['n'];

			$width=$atts['width']; if (!$width) $width = "480px";
			$height=$atts['height']; if (!$height) $height = "360px";

			if (!$stream)
			{
				return "Watch MPEG Dash Error: Missing channel name!";
			}

			//get channel id $postID
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			//auto transcoding
			if ($options['transcodingAuto'])
			{
				$streamName = VWliveStreaming::transcodeStream($stream, 1); //require transcoding name
			}

			//get compatible stream
			$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);
			if ($sourceProtocol == 'rtsp') //requires transcoding
				{
				$stream_hls = get_post_meta( $postID, 'stream-hls',  true );
				if ($stream_hls) $streamName = $stream_hls;
			}

			if (!$streamName) $streamName = $stream;

			if ($streamName)
			{
				$streamURL = $options['httpstreamer'] . $streamName .'/manifest.mpd';


				$dir = $options['uploadsPath']. "/_thumbs";
				$thumbFilename = "$dir/" . $stream . ".jpg";
				$thumbUrl =  VWliveStreaming::path2url($thumbFilename);


				if (strstr($streamURL,'http://')) wp_enqueue_script('dashjs', 'http://cdn.dashjs.org/latest/dash.all.min.js');
				else wp_enqueue_script('dashjs', 'https://cdn.dashjs.org/latest/dash.all.min.js');

				$codecVideo = get_post_meta($postID, 'stream-codec-video', true);
				$codecAudio = get_post_meta($postID, 'stream-codec-audio', true);

				$htmlCode = <<<HTMLCODE
<!--MPEG:$postID:$sourceProtocol:$stream:stream_hls=$stream_hls:$codecVideo:$codecAudio-->
<video id="videowhisper_mpeg_$stream" class="videowhisper_htmlvideo" width="$width" height="$height" data-dashjs-player autobuffer autoplay muted playsinline controls="true" poster="$thumbUrl" src="$streamURL">
    <div class="fallback" style="display:none">
    <IMG SRC="$thumbUrl">
	    <p>HTML5 MPEG Dash capable browser (i.e. Chrome) is required to open this live stream: $streamURL</p>
	</div>
</video>
HTMLCODE;
			}
			else $htmlCode = '<div class="warning">MPEG Dash format is not currently available for this stream: '. $stream.'</div>';

			if ($options['transcodingWarning']>=2 && !$atts['silent']) $htmlCode .= '<p><small>MPEG-DASH Playback: For Android and Chrome. Autoplay starts muted to prevent pausing by browser: enable sound from controls. Transcoding and HTTP based delivery technology involve high latency and availability delay (may take dozens of seconds for transcoder to start stream to become available, after broadcast starts).</small></p>';

			return $htmlCode;
		}



		function flash_video($stream, $width = "100%", $height = '360px', $streamName = '')
		{

			$stream = sanitize_file_name($stream);

			if (!$streamName) $streamName = $stream;

			$swfurl = plugin_dir_url(__FILE__) . "ls/live_video.swf?ssl=1&n=" . urlencode($stream). '&s=' . urlencode($streamName);
			$swfurl .= "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vwls&task=');
			$swfurl .= '&extension='.urlencode('_none_');
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'ls/');

			$bgcolor="#333333";

			$htmlCode = <<<HTMLCODE
<div id="videowhisper_container_$stream">
<object id="videowhisper_video_$stream" width="$width" height="$height" type="application/x-shockwave-flash" data="$swfurl">
<param name="movie" value="$swfurl"></param><param bgcolor="$bgcolor"><param name="scale" value="noscale" /> </param><param name="salign" value="lt"></param><param name="allowFullScreen"
value="true"></param><param name="allowscriptaccess" value="always"></param>
</object>
</div>
HTMLCODE;

			$htmlCode .= VWliveStreaming::flash_warn();

			return $htmlCode;

		}

		function videowhisper_video($atts)
		{
			$stream = '';

			$options = get_option('VWliveStreamingOptions');

			if (is_single())
				if (get_post_type( $postID = get_the_ID() ) == $options['custom_post']) $stream = get_the_title($postID);

				$atts = shortcode_atts(array(
						'channel' => $stream,
						'width' => '480px',
						'height' => '360px',
						'flash' => '0',
						'html5' => 'auto',
						'silent' => '0'
					), $atts, 'videowhisper_video');

			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['n'];

			$stream = sanitize_file_name($stream);

			$width=$atts['width']; if (!$width) $width = "100%";
			$height=$atts['height'];
			if (!$height)  $height = '360px';

			if (!$stream)
			{
				return "Watch Video Error: Missing channel name!";

			}

			//channel post id
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );


			if (!$atts['flash']) //html5
				{

				//source info
				$streamProtocol = get_post_meta($postID, 'stream-protocol', true); // rtsp/rtmp
				$streamType = get_post_meta($postID, 'stream-type', true); // webrtc
				$streamWebRTC =  get_post_meta($postID, 'stream-webrtc', true); // direct/safari_pic

				$directWebRTC = 0;
				if ($streamProtocol == 'rtsp' && $streamWebRTC =='direct') $directWebRTC = 1; //preferred html5 for low latency


				//HLS if iOS/Android detected
				$agent = $_SERVER['HTTP_USER_AGENT'];
				$Android = stripos($agent,"Android");
				$iOS = ( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'));
				$Safari = (strstr($agent,"Safari") && !strstr($agent,"Chrome"));

				$htmlCode .= "<!--VideoWhisper-Stream:$streamProtocol|$streamType|$streamWebRTC|$directWebRTC|Agent:$agent|A:$Android|I:$iOS|S:$Safari|F:$Firefox-->";

				$silent = $atts['silent'];



				$showHTML5 = 0;
				if ($options['transcoding'] >= 3 || $options['webrtc'] >= 3) $showHTML5 = 1; //html5 preferred

				//always
				if ($atts['html5'] == 'always' || $showHTML5)
				{
					if ($directWebRTC) return $htmlCode.'<!--H5-WebRTC-->'. do_shortcode("[videowhisper_webrtc_playback channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]");

					if ($iOS || $Safari) return $htmlCode.'<!--H5-HLS-->'. do_shortcode("[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]");
					else return $htmlCode.'<!--H5-MPEG-->'. do_shortcode("[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]");
				}

				if ($directWebRTC) return $htmlCode.'<!--H5-WebRTC-->'. do_shortcode("[videowhisper_webrtc_playback channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]");

				//detect delivery mode for video interface
				if ( ($Android && in_array($options['detect_mpeg'], array('android', 'all'))) || (!$iOS && in_array($options['detect_mpeg'], array('all'))) || (!$iOS && !$Safari && in_array($options['detect_mpeg'], array('nonsafari'))) )
					return $htmlCode .'<!--MPEG-->'. do_shortcode("[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]");

				if ( (($Android||$iOS) && in_array($options['detect_hls'], array('mobile','safari', 'all'))) || ($iOS && $options['detect_hls'] == 'ios') || ($Safari && in_array($options['detect_hls'], array('safari', 'all'))) ) return $htmlCode .'<!--HLS-->'. do_shortcode("[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]");
			}

			//flash
			$afterCode = <<<HTMLCODE
<br style="clear:both" />

<style type="text/css">
<!--

#videowhisper_container_$stream
{
position: relative;
width: $width;
height: $height;
border: solid 1px #999;
}

-->
</style>
HTMLCODE;



			//default stream to play
			$streamName = $stream;

			if ($postID)
			{
				//get compatible stream
				$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);
				if ($sourceProtocol == 'rtsp') //requires transcoding
					{
					$stream_hls = get_post_meta( $postID, 'stream-hls',  true );
					if ($stream_hls) $streamName = $stream_hls;
				}
			}


			return VWliveStreaming::flash_video($stream, $width, $height, $streamName) . $afterCode;

		}

		//! WebRTC


		function videowhisper_webrtc_broadcast($atts)
		{
			$stream = '';

			if (!is_user_logged_in()) return "<div class='error'>" . __('Broadcasting not allowed: Only logged in users can broadcast!', 'livestreaming') . '</div>';

			$options = get_option('VWliveStreamingOptions');

			//username used with application
			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			$current_user = wp_get_current_user();
			if ($current_user->$userName) $username = sanitize_file_name($current_user->$userName);

			$postID = 0;
			if ($options['postChannels']) //1. channel post
				{
				if (is_single())
				{
					$postID = get_the_ID();
					if (get_post_type( $postID ) ==  $options['custom_post'] ) $stream = get_the_title($postID);
					else $postID = 0;

				}
			}

			$atts = shortcode_atts(array(
					'channel' => $stream,
					'channel_id' => $postID,
				), $atts, 'videowhisper_webrtc_broadcast');

			$postID = $atts['channel_id'];

			if ($stream && !$postID)
			{
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			if (!$stream) $stream = $atts['channel']; //2. shortcode param

			if ($options['anyChannels']) if (!$stream) $stream = $_GET['n']; //3. GET param

				if ($options['userChannels']) if (!$stream) $stream = $username; //4. username

					$stream = sanitize_file_name($stream);

				if (!$stream) return "<div class='error'>Can't load WebRTC broadcasting interface: Missing channel name!</div>";

				if ($postID>0 && $options['postChannels'])
				{
					$channel = get_post( $postID );
					if ($channel->post_author != $current_user->ID) return "<div class='error'>Only owner can broadcast his channel (#$postID)!</div>";
				}

			$keyBroadcast = md5('vw' . $options['webKey'] . $current_user->ID  . $postID);
			$streamQuery = $stream . '?channel_id=' . $postID . '&userID=' . urlencode($current_user->ID ) . '&key=' . urlencode($keyBroadcast);

			//detect browser
			$agent = $_SERVER['HTTP_USER_AGENT'];
			$Android = stripos($agent,"Android");
			$iOS = ( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'));
			$Safari = (strstr($agent,"Safari") && !strstr($agent,"Chrome"));
			$Firefox = stripos($agent,"Firefox");

			//publishing WebRTC - save info
			update_post_meta($postID, 'stream-protocol', 'rtsp');
			update_post_meta($postID, 'stream-type', 'webrtc');

			if (!$iOS && $Safari) update_post_meta($postID, 'stream-webrtc', 'safari_pc'); //safari on pc encoding profile issues
			else update_post_meta($postID, 'stream-webrtc', 'direct');

			VWliveStreaming::enqueueUI();

			wp_enqueue_script( 'webrtc-adapter', plugin_dir_url(  __FILE__ ) . 'scripts/adapter.js', array('jquery'));
			wp_enqueue_script( 'videowhisper-webrtc-broadcast', plugin_dir_url(  __FILE__ ) . 'scripts/vwrtc-publish.js', array('jquery', 'webrtc-adapter'));


			$wsURLWebRTC = $options['wsURLWebRTC'];
			$applicationWebRTC = $options['applicationWebRTC'];

			$videoCodec = $options['webrtcVideoCodec']; //42e01f
			$audioCodec = $options['webrtcAudioCodec']; //opus

			$videoBitrate = (int) $options['webrtcVideoBitrate'];
			if (!$videoBitrate) $videoBitrate = 400; //400 max for tcp with Wowza

			$htmlCode .= "<!--WebRTC_Broadcast|$agent|i:$iOS|a:$Android|Sa:$Safari|Ff:$Firefox-->";

			$broadcastCode = <<<HTMLCODE
		<div class="videowhisper-webrtc-camera">
		<video id="localVideo" class="videowhisper_htmlvideo" autoplay playsinline muted style="widht:640px;height:480px;"></video>
		</div>

		<div class="ui segment">
			<span id="sdpDataTag">Connecting...</span>

<hr class="divider">
    <div class="select">
        <label for="videoSource">Video source: </label><select class="ui dropdown" id="videoSource"></select>
    </div>


	 <div class="select">
        <label for="audioSource">Audio source: </label><select class="ui dropdown" id="audioSource"></select>
    </div>
    		</div>


		<script type="text/javascript">

			var userAgent = navigator.userAgent;
		    var wsURL = "$wsURLWebRTC";
			var streamInfo = {applicationName:"$applicationWebRTC", streamName:"$streamQuery", sessionId:"[empty]"};
			var userData = {param1:"value1","videowhisper":"webrtc-broadcast"};
			var videoBitrate = $videoBitrate;
			var audioBitrate = 64;
			var videoFrameRate = "29.97";
			var videoChoice = "$videoCodec";
			var audioChoice = "$audioCodec";

		jQuery( document ).ready(function() {
 		browserReady();
 		jQuery(".ui.dropdown").dropdown();

});
		</script>
HTMLCODE;


			//AJAX Chat for WebRTC broadcasting

			//htmlchat ui
			//css
			wp_enqueue_style( 'jScrollPane', plugin_dir_url(__FILE__) .'/htmlchat/js/jScrollPane/jScrollPane.css');
			wp_enqueue_style( 'htmlchat', plugin_dir_url(__FILE__) .'/htmlchat/css/chat-broadcast.css');

			//js
			wp_enqueue_script("jquery");
			wp_enqueue_script( 'jScrollPane-mousewheel', plugin_dir_url(  __FILE__ ) . '/htmlchat/js/jScrollPane/jquery.mousewheel.js');
			wp_enqueue_script( 'jScrollPane', plugin_dir_url(  __FILE__ ) . '/htmlchat/js/jScrollPane/jScrollPane.min.js');
			wp_enqueue_script( 'htmlchat', plugin_dir_url(  __FILE__ ) . '/htmlchat/js/script.js', array('jquery','jScrollPane'));

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_htmlchat&room=' . urlencode(sanitize_file_name($stream));

			$htmlCode .= <<<HTMLCODE
<div id="videochatContainer">
<!--Room:$stream-->
<div id="streamContainer">
$broadcastCode
</div>

<div id="chatContainer">

    <div id="chatUsers" class="ui segment"></div>

    <div id="chatLineHolder"></div>

    <div id="chatBottomBar" class="ui segment">
    	<div class="tip"></div>

        <form id="loginForm" method="post" action="" class="ui form">
Login is required to chat!
		</form>

        <form id="submitForm" method="post" action="" class="ui form">
            <input id="chatText" name="chatText" class="rounded" maxlength="255" />
            <input type="submit" class="ui button" value="Submit" />
        </form>

    </div>
</div>
</div>
<script>
var vwChatAjax= '$ajaxurl';
</script>

HTMLCODE;

			if ($options['transcodingWarning']>=1) $htmlCode .= '<p class="info"><small>Warning: WebRTC will play directly where possible, depending on settings and viewer device. If transcoding is needed for playback, it may take up to a couple of minutes for transcoder to start and WebRTC published stream to become available for RTMP and HLS/MPEG DASH playback.
<BR>For advanced features use advanced web broadcasting interface available in PC browser with Flash plugin.</small></p>' .
					'<p><a class="ui button secondary" href="' . add_query_arg(array('flash-broadcast'=>''), get_permalink($postID)) . '">Try Advanced Flash Broadcast (PC)</a></p>';

			return $htmlCode;


		}

		function videowhisper_webrtc_playback($atts)
		{
			$stream = '';
			$postID = 0;
			$options = get_option('VWliveStreamingOptions');

			if (is_single())
			{
				$postID = get_the_ID();
				if (get_post_type( $postID ) ==  $options['custom_post'] ) $stream = get_the_title($postID);
				else $postID = 0;
			}

			if (!$postID)
			{
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			$atts = shortcode_atts(array(
					'channel' => $stream,
					'width' => '480px',
					'height' => '360px',
					'channel_id' => $postID,
					'silent' => 0,
				), $atts, 'videowhisper_webrtc_playback');

			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['n'];

			$stream = sanitize_file_name($stream);

			$width=$atts['width']; if (!$width) $width = "100%";
			$height=$atts['height']; if (!$height)  $height = '360px';

			if (!$stream)
			{
				return "WebRTC Playback Error: Missing channel name!";
			}

			$userID = 0;
			if (is_user_logged_in())
			{
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
				$current_user = wp_get_current_user();
				if ($current_user->$userName) $username = sanitize_file_name($current_user->$userName);
				$userID = $current_user->ID;
			}

			$postID = $atts['channel_id'];
			if (!$postID)
			{
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}




			//detect browser
			$agent = $_SERVER['HTTP_USER_AGENT'];
			$Android = stripos($agent,"Android");
			$iOS = ( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'));
			$Safari = (strstr($agent,"Safari") && !strstr($agent,"Chrome"));
			$Firefox = stripos($agent,"Firefox");

			$codeMuted = '';
			if ($Safari)
			{
				$codeMuted = 'muted';
			}

			//WebRTC playback: detect source type and transcode if necessary
			$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);

			if ($sourceProtocol == 'rtsp') $stream_webrtc = $stream;
			else $stream_webrtc = VWliveStreaming::transcodeStreamWebRTC($stream, $postID, $options);

			$keyPlayback = md5('vw' . $options['webKey']. $postID . $userID);
			$streamQuery = $stream_webrtc . '?channel_id=' . $postID . '&userID=' . urlencode($userID) . '&key=' . urlencode($keyPlayback);


			wp_enqueue_script( 'jquery');
			wp_enqueue_script( 'webrtc-adapter', plugin_dir_url(  __FILE__ ) . 'scripts/adapter.js', array('jquery'));
			wp_enqueue_script( 'videowhisper-webrtc-playback', plugin_dir_url(  __FILE__ ) . 'scripts/vwrtc-playback.js', array('jquery', 'webrtc-adapter'));

			$wsURLWebRTC = $options['wsURLWebRTC'];
			$applicationWebRTC = $options['applicationWebRTC'];

			$htmlCode .= <<<HTMLCODE
		<div class="videowhisper-webrtc-video">
		<video id="remoteVideo" class="videowhisper_htmlvideo" autoplay playsinline $codeMuted style="width:$width; height:$height"></video>
		<!--$sourceProtocol:$stream_webrtc-->
		</div>

		<div>
			<span id="sdpDataTag"></span>
		</div>

		<script type="text/javascript">

			var videoBitrate = 360;
			var audioBitrate = 64;
			var videoFrameRate = "29.97";
			var videoChoice = "$videoCodec";
			var audioChoice = "$audioCodec";

			var userAgent = navigator.userAgent;
		    var wsURL = "$wsURLWebRTC";
			var streamInfo = {applicationName:"$applicationWebRTC", streamName:"$streamQuery", sessionId:"[empty]"};
			var userData = {param1:"value1","videowhisper":"webrtc-playback"};

		jQuery( document ).ready(function() {
 		browserReady();
});

		</script>
HTMLCODE;

			if (!$atts['silent'])
			{
				if ($Safari)
				{
					$htmlCode .=  "WebRTC playback is not currently fully supported for Safari (may take longer to start) if not broadcasting with Chrome. If live video does not play or freezes, try opening this URL in Chrome or Firefox!<BR>Additionally playback is muted to allow auto play: enable audio from player controls.";
				}
			}
			return $htmlCode;
		}



		function videowhisper_htmlchat_playback($atts)
		{
			//! playback with html5 video and ajax chat

			$stream = '';
			$options = get_option('VWliveStreamingOptions');

			if (is_single())
			{
				$postID = get_the_ID();
				if (get_post_type( $postID ) ==  $options['custom_post'] ) $stream = get_the_title($postID);
				else $postID = 0;
			}

			if (!$postID)
			{
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			$atts = shortcode_atts(array(
					'channel' => $stream,
					'post_id'=> $postID,
					'videowidth' => '480px',
					'videoheight' => '360px'
				), $atts, 'videowhisper_htmlchat_playback');

			if (!$stream) $stream = $atts['channel']; //parameter channel="name"
			if (!$stream) $stream = $_GET['room'];
			if (!$stream) $stream = $_GET['n'];

			$stream = sanitize_file_name($stream);

			$room = $stream;
			/*
			$postID = (int) $atts['post_id'];

			if (!$postID)
			{
				global $wpdb;

				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $room . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );

			}
*/
			$videowidth=$atts['videowidth']; if (!$videowidth) $videowidth = "480px";
			$videoheight=$atts['videoheight']; if (!$videoheight)  $videoheight = '360px';

			if (!$room)
			{
				return "HTML AJAX Chat Error: Missing room name!";
			}

			//ui
			VWliveStreaming::enqueueUI();

			//AJAS Chat for viewers

			//htmlchat ui
			//css
			wp_enqueue_style( 'jScrollPane', plugin_dir_url(__FILE__) .'/htmlchat/js/jScrollPane/jScrollPane.css');
			wp_enqueue_style( 'htmlchat', plugin_dir_url(__FILE__) .'/htmlchat/css/chat-watch.css');

			//js
			wp_enqueue_script("jquery");
			wp_enqueue_script( 'jScrollPane-mousewheel', plugin_dir_url(  __FILE__ ) . '/htmlchat/js/jScrollPane/jquery.mousewheel.js');
			wp_enqueue_script( 'jScrollPane', plugin_dir_url(  __FILE__ ) . '/htmlchat/js/jScrollPane/jScrollPane.min.js');
			wp_enqueue_script( 'htmlchat', plugin_dir_url(  __FILE__ ) . '/htmlchat/js/script.js', array('jquery','jScrollPane'));

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_htmlchat&room=' . urlencode($room);


			$videoCode = do_shortcode('[videowhisper_video channel="'.$room.'" width="640px" height="480px" silent="1" html5="always"]');

			$htmlCode = <<<HTMLCODE
<div id="videochatContainer">
<!--$room-->
<div id="streamContainer">
$videoCode
</div>

<div id="chatContainer">
    <div id="chatUsers" class="ui segment"></div>

    <div id="chatLineHolder"></div>

    <div id="chatBottomBar" class="ui segment">

    	<div class="tip"></div>

        <form id="loginForm" method="post" action="" class="ui form">
Login is required to chat!
		</form>

        <form id="submitForm" method="post" action="" class="ui form">
            <input id="chatText" name="chatText" class="rounded" maxlength="255" />
            <input id="submit" type="submit" class="ui button" value="Submit" />
        </form>

    </div>

</div>

</div>
<script>
var vwChatAjax= '$ajaxurl';
</script>
HTMLCODE;

			if ($options['transcodingWarning']>=2) $htmlCode .= '<p><small>HTML5 Video Stream Playback: Transcoding and HTTP based delivery technology involve extra latency and availability delay related to processing video stream. If stream is live but not showing, please wait or reload page if it does not start in few seconds.</small></p>';

			if ($options['transcodingWarning']>=1)
				if ($options['transcoding']) $htmlCode .= '<p><a class="ui button secondary" href="' . add_query_arg(array('flash-view'=>''), get_permalink($postID)) . '">Try Flash View (PC)</a></p>';

				return $htmlCode;

		}

		//
		function rtmp_address($userID, $postID, $broadcaster, $session, $room)
		{

			//?session&room&key&broadcaster&broadcasterid

			$options = get_option('VWliveStreamingOptions');


			if ($broadcaster)
			{
				$key = md5('vw' . $options['webKey'] . $userID . $postID);
				return $options['rtmp_server'] . '?'. urlencode($session) .'&'. urlencode($room) .'&'. $key . '&1&' . $userID . '&videowhisper';
			}
			else
			{
				$keyView = md5('vw' . $options['webKey']. $postID);
				return $options['rtmp_server'] . '?'. urlencode('-name-') .'&'. urlencode($room) .'&'. $keyView . '&0' . '&videowhisper';
			}

			return $options['rtmp_server'];

		}

		function videowhisper_external($atts)
		{

			if (!is_user_logged_in()) return "<div class='error'>Only logged in users can broadcast!</div>";

			$options = get_option('VWliveStreamingOptions');

			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			//username
			$current_user = wp_get_current_user();

			if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

			$postID = 0;
			if ($options['postChannels']) //1. channel post
				{

				$postID = get_the_ID();
				if (is_single())
					if (get_post_type( $postID ) ==  $options['custom_post']) $stream = get_the_title($postID);

			}

			$atts = shortcode_atts(
				array('channel' => $stream,
				),
				$atts, 'videowhisper_external');


			if (!$stream) $stream = $atts['channel']; //2. shortcode param

			if ($options['anyChannels']) if (!$stream) $stream = $_GET['n']; //3. GET param

				if ($options['userChannels']) if (!$stream) $stream = $username; //4. username

					$stream = sanitize_file_name($stream);

				if (!$stream) return "<div class='error'>Can't load broadcasting details: Missing channel name!</div>";

				if ($postID>0 && $options['postChannels'])
				{
					$channel = get_post( $postID );
					if ($channel->post_author != $current_user->ID) return "<div class='error'>Only owner can broadcast (#$postID)!</div>";
				}

			$rtmpAddress = VWliveStreaming::rtmp_address($current_user->ID, $postID, true, $stream, $stream);
			$rtmpAddressView = VWliveStreaming::rtmp_address($current_user->ID, $postID, false, $stream, $stream);

			$codeWatch = htmlspecialchars(do_shortcode("[videowhisper_watch channel=\"$stream\"]"));
			$roomLink = VWliveStreaming::roomURL($stream);

			$application = substr(strrchr($rtmpAddress, '/'),1);

			$adrp1 = explode('://', $rtmpAddress);
			$adrp2 = explode('/', $adrp1[1]);
			$adrp3 = explode(':', $adrp2[0]);

			$server = $adrp3[0];
			$port = $adrp3[1]; if (!$port) $port = 1935;

			$htmlCode = <<<HTMLCODE
<h3>Broadcast Video</h3>
<div class="ui segment info w-actionbox color_alternate">
<P>After reviewing your encoder setting fields, retrieve settings you need from strings below.</P>
<p>RTMP Address / URL (full address, contains server, port if different than default 1935, application, parameters):<BR><I>$rtmpAddress</I></p>
<p>Application (contains application name and parameters):<BR><I>$application</I></p>
<p>Stream Name / Key (name of channel):<BR><I>$stream</I></p>
<p>Server:<BR><I>$server</I></p>
<p>Port:<BR><I>$port</I></p>
<p>Stream Address (RTMP Address with Stream Name):<BR><I>$rtmpAddress/$stream</I></p>
</div>
<p>Use specs above to broadcast channel '$stream' using external applications (GoCoder iOS/Android app, OBS Open Broadcaster Software, XSplit, Adobe Flash Media Live Encoder, Wirecast).<br>Keep your secret broadcasting rtmp address safe as anyone having it may broadcast to your channel. As external encoders don't comunicate with site scripts, externally broadcast channel shows as online only if RTMP Session Control is enabled.</p>

<p>Copy and paste strings: For mobile encoders send the strings above in an email or notes sharing app. In GoCoder copy and paste each string and save settings before switching between apps to get next string.</p>
<p>Warning: If advanced session control is enabled you can't connect at same time with web broadcasting interface and external encoder (duplicate named session will be refused by server). Connect with external encoder using details above and participate in chat with Watch interface.</p>

<h3>Playback Video</h3>
<div class="ui segment info w-actionbox color_alternate">
<p>RTMP Address / URL (full address, contains server, port if different than default 1935, application, parameters):<BR><I>$rtmpAddressView</I></p>
<p>Stream Name:<BR><I>$stream</I></p>
<p>Stream Address (RTMP Address with Stream Name, for players that require these settings in 1 string):<BR><I>$rtmpAddressView/$stream</I></p>
</div>
<p>Use specs above to setup playback using 3rd party RTMP players (Strobe, JwPlayer, FlowPlayer), restreaming servers or apps.</p>
<h3>Chat &amp; Video Embed</h3>
<div class="ui segment info w-actionbox color_alternate">
<p><I>$codeWatch</I></p>
</div>
HTMLCODE;

			return   $htmlCode;

		}


		function videowhisper_external_playback($atts)
		{

			if (!is_user_logged_in()) return "<div class='error'>Only logged in users can access info!</div>";

			$options = get_option('VWliveStreamingOptions');

			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			//username
			$current_user = wp_get_current_user();

			if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

			$postID = 0;
			if ($options['postChannels']) //1. channel post
				{

				$postID = get_the_ID();
				if (is_single())
					if (get_post_type( $postID ) ==  $options['custom_post']) $stream = get_the_title($postID);

			}

			$atts = shortcode_atts(
				array('channel' => $stream,
				),
				$atts, 'videowhisper_external_playback');


			if (!$stream) $stream = $atts['channel']; //2. shortcode param

			if ($options['anyChannels']) if (!$stream) $stream = $_GET['n']; //3. GET param

				if ($options['userChannels']) if (!$stream) $stream = $username; //4. username

					$stream = sanitize_file_name($stream);

				if (!$stream) return "<div class='error'>Can't load channel details: Missing channel name!</div>";

				if ($postID>0 && $options['postChannels'])
				{
					$channel = get_post( $postID );
					if ($channel->post_author != $current_user->ID) return "<div class='error'>Only owner can access channel (#$postID)!</div>";
				}

			$rtmpAddress = VWliveStreaming::rtmp_address($current_user->ID, $postID, true, $stream, $stream);
			$rtmpAddressView = VWliveStreaming::rtmp_address($current_user->ID, $postID, false, $stream, $stream);

			$codeWatch = htmlspecialchars(do_shortcode("[videowhisper_watch channel=\"$stream\"]"));
			$roomLink = VWliveStreaming::roomURL($stream);

			$application = substr(strrchr($rtmpAddress, '/'),1);

			$adrp1 = explode('://', $rtmpAddress);
			$adrp2 = explode('/', $adrp1[1]);
			$adrp3 = explode(':', $adrp2[0]);

			$server = $adrp3[0];
			$port = $adrp3[1]; if (!$port) $port = 1935;

			$htmlCode = <<<HTMLCODE
<h3>Playback Video</h3>
<div class="ui segment info w-actionbox color_alternate">
<p>RTMP Address / URL (full address, contains server, port if different than default 1935, application, parameters):<BR><I>$rtmpAddressView</I></p>
<p>Stream Name:<BR><I>$stream</I></p>
<p>Stream Address (RTMP Address with Stream Name, for players that require these settings in 1 string):<BR><I>$rtmpAddressView/$stream</I></p>
</div>
<p>Use specs above to setup playback using 3rd party RTMP players (Strobe, JwPlayer, FlowPlayer), restreaming servers or apps.</p>
<h3>Chat &amp; Video Embed</h3>
<div class="ui segment info w-actionbox color_alternate">
<p><I>$codeWatch</I></p>
</div>
HTMLCODE;

			VWliveStreaming::enqueueUI();

			return   $htmlCode;

		}

		function videowhisper_external_broadcast($atts)
		{

			if (!is_user_logged_in()) return "<div class='error'>Only logged in users can broadcast!</div>";

			$options = get_option('VWliveStreamingOptions');

			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			//username
			$current_user = wp_get_current_user();

			if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

			$postID = 0;
			if ($options['postChannels']) //1. channel post
				{

				$postID = get_the_ID();
				if (is_single())
					if (get_post_type( $postID ) ==  $options['custom_post']) $stream = get_the_title($postID);

			}

			$atts = shortcode_atts(
				array('channel' => $stream,
				),
				$atts, 'videowhisper_external_broadcast');


			if (!$stream) $stream = $atts['channel']; //2. shortcode param

			if ($options['anyChannels']) if (!$stream) $stream = $_GET['n']; //3. GET param

				if ($options['userChannels']) if (!$stream) $stream = $username; //4. username

					$stream = sanitize_file_name($stream);

				if (!$stream) return "<div class='error'>Can't load broadcasting details: Missing channel name!</div>";

				if ($postID>0 && $options['postChannels'])
				{
					$channel = get_post( $postID );
					if ($channel->post_author != $current_user->ID) return "<div class='error'>Only owner can broadcast (#$postID)!</div>";
				}

			$rtmpAddress = VWliveStreaming::rtmp_address($current_user->ID, $postID, true, $stream, $stream);

			$roomLink = VWliveStreaming::roomURL($stream);

			$application = substr(strrchr($rtmpAddress, '/'),1);

			$adrp1 = explode('://', $rtmpAddress);
			$adrp2 = explode('/', $adrp1[1]);
			$adrp3 = explode(':', $adrp2[0]);

			$server = $adrp3[0];
			$port = $adrp3[1]; if (!$port) $port = 1935;

			$htmlCode = <<<HTMLCODE
<h3>Broadcast Video</h3>
<div class="ui segment info w-actionbox color_alternate">
<P>After reviewing your encoder setting fields, retrieve settings you need from strings below.</P>
<p>RTMP Address / URL (full address, contains server, port if different than default 1935, application, parameters):<BR><I>$rtmpAddress</I></p>
<p>Application (contains application name and parameters):<BR><I>$application</I></p>
<p>Stream Name / Key (name of channel):<BR><I>$stream</I></p>
<p>Server:<BR><I>$server</I></p>
<p>Port:<BR><I>$port</I></p>
<p>Stream Address (RTMP Address with Stream Name):<BR><I>$rtmpAddress/$stream</I></p>
</div>
<p>Use specs above to broadcast channel '$stream' using external applications (GoCoder iOS/Android app, OBS Open Broadcaster Software, XSplit, Adobe Flash Media Live Encoder, Wirecast).<br>Keep your secret broadcasting rtmp address safe as anyone having it may broadcast to your channel. As external encoders don't comunicate with site scripts, externally broadcast channel shows as online only if RTMP Session Control is enabled.</p>

<p>Copy and paste strings: For mobile encoders send the strings above in an email or notes sharing app. In GoCoder copy and paste each string and save settings before switching between apps to get next string.</p>
<p>Warning: If advanced session control is enabled you can't connect at same time with web broadcasting interface and external encoder (duplicate named session will be refused by server). Connect with external encoder using details above and if you want to participate in chat you can do that from website with Watch interface.</p>
HTMLCODE;

			VWliveStreaming::enqueueUI();

			return   $htmlCode;

		}




		function videowhisper_broadcast($atts)
		{
			$stream = '';

			if (!is_user_logged_in())
				return "<div class='error'>" . __('Broadcasting not allowed: Only logged in users can broadcast!', 'livestreaming') . '</div>'
					. '<BR><a class="ui button primary qbutton" href="' . wp_login_url() . '">' . __('Login', 'ppv-live-webcams') . '</a>  <a class="ui button secondary qbutton" href="' . wp_registration_url() . '">' . __('Register', 'ppv-live-webcams') . '</a>';

			$options = get_option('VWliveStreamingOptions');

			//username used with application
			$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

			$current_user = wp_get_current_user();

			if ($current_user->$userName) $username=sanitize_file_name($current_user->$userName);

			$postID = 0;
			if ($options['postChannels']) //1. channel post
				{
				$postID = get_the_ID();
				if (is_single())
					if (get_post_type( $postID ) ==  $options['custom_post'] ) $stream = get_the_title($postID);
			}

			$atts = shortcode_atts(
				array('channel' => $stream,
					'flash' => '0',
				),
				$atts, 'videowhisper_broadcast');


			if (!$stream) $stream = $atts['channel']; //2. shortcode param

			if ($options['anyChannels']) if (!$stream) $stream = $_GET['n']; //3. GET param

				if ($options['userChannels']) if (!$stream) $stream = $username; //4. username

					$stream = sanitize_file_name($stream);

				if (!$stream) return "<div class='error'>Can't load broadcasting interface: Missing channel name!</div>";

				if ($postID>0 && $options['postChannels'])
				{
					$channel = get_post( $postID );
					if ($channel->post_author != $current_user->ID) return "<div class='error'>Only owner can broadcast his channel (#$postID)!</div>";
				}



			if (!$atts['flash'])
			{

				//HLS if iOS/Android detected
				$agent = $_SERVER['HTTP_USER_AGENT'];
				$Android = stripos($agent,"Android");
				$iOS = ( strstr($agent,'iPhone') || strstr($agent,'iPod') || strstr($agent,'iPad'));
				$Safari = (strstr($agent,"Safari") && !strstr($agent,"Chrome"));
				$Firefox = stripos($agent,"Firefox");

				$htmlCode .= "<!--VideoWhisper-Broadcast-Agent:$agent|A:$Android|I:$iOS|S:$Safari|F:$Firefox-->";

				$showHTML5 = 0;

				if ($Android || $iOS) $showHTML5 = 1;
				if ($options['webrtc'] >= 3) $showHTML5 = 1; //preferred

				if ($showHTML5)
				{
					$htmlCode .= do_shortcode( '[videowhisper_webrtc_broadcast channel="' . $stream . '"]');
					return $htmlCode;
				}
			}

			//flash web broadcast

			$swfurl = plugin_dir_url(__FILE__) . "ls/live_broadcast.swf?ssl=1&room=" . urlencode($stream);
			$swfurl .= "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vwls&task=');
			$swfurl .= '&extension='.urlencode('_none_');
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'ls/');

			$bgcolor="#333333";

			$htmlCode = <<<HTMLCODE
<div id="videowhisper_container">
<object width="100%" height="100%" type="application/x-shockwave-flash" data="$swfurl">
<param name="movie" value="$swfurl"></param><param bgcolor="$bgcolor"><param name="scale" value="noscale" /> </param><param name="salign" value="lt"></param><param name="allowFullScreen"
value="true"></param><param name="allowscriptaccess" value="always"></param>
</object>
</div>

<br style="clear:both" />

<style type="text/css">
<!--

#videowhisper_container
{
width: 100%;
height: 500px;
border: solid 3px #999;
}

-->
</style>
HTMLCODE;

			$htmlCode .=  VWliveStreaming::flash_warn();

			if ($options['webrtc'] >= 2)
			{
				global $wpdb ;
				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $stream . '\' and post_type=\'' . $options['custom_post'] . '\' LIMIT 0,1' );
				if ($postID) $htmlCode .= '<p><a class="ui button secondary" href="' . add_query_arg(array('webrtc-broadcast'=>''), get_permalink($postID)) . '">Try HTML5 WebRTC Broadcast</a></p>';
			}

			if (!$options['transcoding']) return $htmlCode; //done

			//transcoding interface
			if ($stream)
			{

				//access keys
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->display_name;
				}

				$admin_ajax = admin_url() . 'admin-ajax.php';

				if (VWliveStreaming::inList($userkeys, $options['transcode'])) //transcode feature enabled
					if ($options['transcoding']) if ($options['transcodingManual'])
							$htmlCode .= <<<HTMLCODE
<div id="vwinfo">
Stream Transcoding<BR>
<a href='#' class="button" id="transcoderon">ENABLE</a>
<a href='#' class="button" id="transcoderoff">DISABLE</a>
<div id="videowhisperTranscoder">A stream must be broadcast for transcoder to start. Activate to make stream available for iOS HLS.</div>
<p align="right">(<a href="javascript:void(0)" onClick="vwinfo.style.display='none';">hide</a>)</p>
</div>

<style type="text/css">
<!--

#vwinfo
{
	float: right;
	width: 25%;
	position: absolute;
	bottom: 10px;
	right: 10px;
	text-align:left;
	font-size: 14px;
	padding: 10px;
	margin: 10px;
	background-color: #666;
	border: 1px dotted #AAA;
	z-index: 1;

	filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#999', endColorstr='#666'); /* for IE */
	background: -webkit-gradient(linear, left top, left bottom, from(#999), to(#666)); /* for webkit browsers */
	background: -moz-linear-gradient(top,  #999,  #666); /* for firefox 3.6+ */

	box-shadow: 2px 2px 2px #333;


	-moz-border-radius: 9px;
	border-radius: 9px;
}

#vwinfo > a {
	color: #F77;
	text-decoration: none;
}

#vwinfo > .button {
	-moz-box-shadow:inset 0px 1px 0px 0px #f5978e;
	-webkit-box-shadow:inset 0px 1px 0px 0px #f5978e;
	box-shadow:inset 0px 1px 0px 0px #f5978e;
	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #db4f48), color-stop(1, #944038) );
	background:-moz-linear-gradient( center top, #db4f48 5%, #944038 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#db4f48', endColorstr='#944038');
	background-color:#db4f48;
	border:1px solid #d02718;
	display:inline-block;
	color:#ffffff;
	font-family:Verdana;
	font-size:12px;
	font-weight:normal;
	font-style:normal;
	text-decoration:none;
	text-align:center;
	text-shadow:1px 1px 0px #810e05;
	padding: 5px;
	margin: 2px;
}
#vwinfo > .button:hover {
	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #944038), color-stop(1, #db4f48) );
	background:-moz-linear-gradient( center top, #944038 5%, #db4f48 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#944038', endColorstr='#db4f48');
	background-color:#944038;
}

-->
</style>

<script type="text/javascript">
	var \$j = jQuery.noConflict();
	var loaderTranscoder;
	var transcodingOn = false;


	\$j.ajaxSetup ({
		cache: false
	});
	var ajax_load = "Loading...";

	\$j("#transcoderon").click(function(){
	transcodingOn = true;
	if (loaderTranscoder) if (loaderTranscoder.abort === 'function') loaderTranscoder.abort();
	loaderTranscoder = \$j("#videowhisperTranscoder").html(ajax_load).load("$admin_ajax?action=vwls_trans&task=enable&stream=$stream");
	});

	\$j("#transcoderoff").click(function(){
	transcodingOn = false;
	if (loaderTranscoder) if (loaderTranscoder.abort === 'function') loaderTranscoder.abort();
	loaderTranscoder = \$j("#videowhisperTranscoder").html(ajax_load).load("$admin_ajax?action=vwls_trans&task=close&stream=$stream");
	});
</script>
HTMLCODE;
			}

			return $htmlCode ;
		}



		function path2url($file, $Protocol='http://')
		{
			$url = $Protocol.$_SERVER['HTTP_HOST'];


			//on godaddy hosting uploads is in different folder like /var/www/clients/ ..
			$upload_dir = wp_upload_dir();
			if (strstr($file, $upload_dir['basedir']))
				return  $upload_dir['baseurl'] . str_replace($upload_dir['basedir'], '', $file);

			if (strstr($file, $_SERVER['DOCUMENT_ROOT']))
				return  $url . str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);


			return $url . $file;
		}


		function format_time($t,$f=':') // t = seconds, f = separator
			{
			return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
		}

		function format_age($t)
		{
			if ($t<30) return "LIVE";
			return sprintf("%d%s%d%s%d%s", floor($t/86400), 'd ', ($t/3600)%24,'h ', ($t/60)%60,'m');
		}


		//! Watcher Online Status for App + AJAX chat
		function updateOnline($username, $room, $postID = 0, $type = 2, $current_user = '', $options ='')
		{
			//$type: 1 = flash full, 2 = html5 chat, 3 = flash video, 4 = html5 video, 5 = voyeur flash, 6 = voyeur html5

			if (!$room && !$postID) return; //no room, no update

			$s = $u = $username;
			$r = $room;
			$ztime = time();

			if (!$options) $options = get_option('VWliveStreamingOptions');

			if (!$current_user) $current_user = wp_get_current_user();

			$uid = 0;
			if ($current_user) $uid = $current_user->ID;

			global $wpdb ;
			$table_name = $wpdb->prefix . "vw_lwsessions";

			if (!$postID)
				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $room . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );


			//create or update session
			//status: 0 current, 1 closed, 2 billed

			$sqlS = "SELECT * FROM `$table_name` WHERE session='$s' AND status='0' AND room='$room' AND type='$type' AND rmode='$rmode' LIMIT 1";
			$session = $wpdb->get_row($sqlS);

			if (!$session)
			{

				if ($ztime - $redate > $options['onlineExpiration1']) $rsdate = 0; //broadcaster offline
				else $rsdate = $redate; //broadcaster online: mark room start date

				$clientIP = VWliveStreaming::get_ip_address();

				$sql="INSERT INTO `$table_name` ( `session`, `username`, `uid`, `room`, `rid`, `roptions`, `rsdate`, `redate`, `rmode`, `message`, `sdate`, `edate`, `status`, `type`, `ip`) VALUES ('$s', '$u', '$uid', '$r', '$postID', '$roptions', '$rsdate', '$redate', '$rmode', '$m', '$ztime', '$ztime', 0, $type, '$clientIP')";
				$wpdb->query($sql);
				$session = $wpdb->get_row($sqlS);
			}
			else
			{
				$id = $session->id;

				//broadcaster was offline and came online: update room start time (rsdate)
				if ($session->rsdate == 0 && $redate > $session->sdate) $rsdate = $redate;
				else $rsdate = $session->rsdate; //keep unchanged (0 or start time)

				$sql="UPDATE `$table_name` set edate='$ztime', rsdate='$rsdate', redate='$redate', roptions = '$roptions' WHERE id='$id' LIMIT 1";
				$wpdb->query($sql);
			}

			return $disconnect;

		}
		//! AJAX
		function wp_enqueue_scripts()
		{
			wp_enqueue_script("jquery");
		}

		//! AJAX IP Camera / Stream Setup
		function vwls_stream_setup()
		{
			$options = get_option('VWliveStreamingOptions');

			ob_clean();


			function respond($msg, $request ='')
			{
				echo $msg;
				die;
			}

			$id = sanitize_text_field($_GET['id']);
			$postID = intval($_GET['channel']);	
			if (!is_user_logged_in()) $postID = 0;


			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_stream_setup&channel=' . $postID . '&id='.$id;

			$address = sanitize_text_field($_GET['address']);

			$label = sanitize_file_name($_GET['label']);
			$username = sanitize_file_name($_GET['username']);
			$email = sanitize_email($_GET['email']);

			if ($postID) //editing existing channel
			{	
				$current_user = wp_get_current_user();
				$channel = get_post( $postID);
				
				if (!$channel) $error .= ($error?'<br>':'') . 'Channel not found!';
				elseif ($channel->post_author != $current_user->ID)
					{
						$postID = 0;
						$error .= ($error?'<br>':'') . 'Only owner can edit existing channel address.';
					}		
			}
			
			if (!$label) //first step: check address
				{
				
				if (!$address) 
				{
					$error = 'Stream address is required';

				}
				else
				{
					
					//protocol
					list($addressProtocol) = explode(':', $address);
					if (!in_array($addressProtocol, array('rtsp','udp','rtmp','rtmps','wowz','wowzs')))
					{
						$error .= ($error?'<br>':'') . "Address protocol not supported ($addressProtocol). Address should use one of these protocols: rtsp://, udp://, rtmp://, rtmps://, wowz://, wowzs://";

					}

					//demo
					if (strstr($address,'[') || strstr($address,'stream-path'))
					{
						$error .= ($error?'<br>':'') . 'Address should not contain special characters or sample path provided as demo. You need your own address to test. Insert address exactly as it works in <a target="_blank" rel="nofollow" href="http://www.videolan.org/vlc/index.html">VLC</a> or other player.';
					}

					//local
					if (strstr($address,'192.168.') || strstr($address, 'localhost'))
					{
						$error .= ($error?'<br>':'') . 'Address host should point to a publicly accessible device (a <a target="_blank"  href="https://www.iplocation.net/public-vs-private-ip-address">public IP</a> or domain). Your address points to a local (intranet) address. Stream is not accessible from internet.';

					}


				}


				$retryCode = <<<HTMLCODE
<BR>Stream Address <input name="address" type="text" id="address" value="$address" size="80" maxlength="250"/>
<BR><input type="submit" name="button" id="button" value="Try Stream" onClick="loadResponse$id('Trying to connect. Please wait...', '$ajaxurl&address=' + escape(document.getElementById('address').value))"/>
$streamCode
HTMLCODE;

				if ($error) respond($error . $retryCode);

				//try to retrieve a snapshot

				$dir = $options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir .= "/_setup";
				if (!file_exists($dir)) mkdir($dir);

				if (!file_exists($dir))
				{
					$error = error_get_last();
					respond('Error - Folder does not exist and could not be created: ' . $dir . ' - '.  $error['message']);
				}

				$filename = "$dir/$id.jpg";
				$log_file = $filename . '.txt';
				$log_file_cmd = $filename . '-cmd.txt';

				$cmd = $options['ffmpegPath'] ." -vframes 1 -q:v 2 \"$filename\" -y  -i \"" . $address . "\" >&$log_file  ";

				//echo $cmd;
				exec($cmd, $output, $returnvalue);
				exec("echo '$cmd' >> $log_file_cmd", $output, $returnvalue);

				//failed
				if (!file_exists($filename)) respond('Snapshot could not be retrieved.' . $retryCode);


				if (!is_user_logged_in())
				{
					$regCode .= <<<HTMLCODE
<BR>Also provide an username and email to quickly setup an account for managing your camera securely.
<BR>Username<input name="username" type="text" id="username" value="" size="32" maxlength="64"/>
<BR>Email<input name="email" type="text" id="email" value="" size="64" maxlength="64"/>
HTMLCODE;
					$extraGET = "+ '&username=' + escape(document.getElementById('username').value)+ '&email=' + escape(document.getElementById('email').value)";

				}
				
				$previewSrc = VWliveStreaming::path2url($filename);
				$addButton = 'Add Stream Channel';


				$channelTitle ='';
				if ($channel) 
				{
				$channelTitle = $channel->post_title;
				//re-stream channel ends in .stream
				$suffix = '.stream';
				$suffixLen = strlen($suffix);
				if (substr($channelTitle,-$suffixLen, $suffixLen) != $suffix) $channelTitle .= $suffix;		
				
				$addButton = 'Update Channel';
			
				}
				
				
				$addCode .= <<<HTMLCODE
<BR>IP Camera/Stream is accessible: you can setup this channel stream.
<BR>Camera Label
<input name="label" type="text" id="label" value="$channelTitle" size="32" maxlength="64"/>
<BR>Stream channels end in ".stream" (will be added if missing).
$regCode
<input name="address" type="hidden" id="address" value="$address"/>
<BR><input class="ui button" type="submit" name="button" id="button" value="$addButton" onClick="loadResponse$id('Trying to connect. Please wait...', '$ajaxurl&address=' + escape(document.getElementById('address').value) + '&label=' + escape(document.getElementById('label').value) $extraGET )"/>
<BR><BR>
HTMLCODE;
				respond($addCode . '<IMG class="ui round image big" SRC="'.$previewSrc.'">' );
			}
			else //second step : add camera
				{


				if (is_user_logged_in()) //logged in
					{
					global $current_user;
					get_currentuserinfo();
					$userID = $current_user->ID;
				}
				else //register new user
					{
					if (!$email || !$username) respond('You must provide a valid username and email to register and setup your camera.');

					$userID = register_new_user($username, $password);
					if( is_wp_error( $userID ) ) respond('Registration failed:' . $userID->get_error_message());
				}


				//re-stream channel ends in .stream
				$suffix = '.stream';
				$suffixLen = strlen($suffix);
				if (substr($label,-$suffixLen, $suffixLen) != $suffix) $label .= $suffix;

				//setup new channel post
				$post = array(
					'post_name'      => $label,
					'post_title'     => $label,
					'post_author'    => $userID,
					'post_type'      => $options['custom_post'],
					'post_status'    => 'publish',
				);

				if ($postID) 
				{
					$post['ID'] = $postID;
					wp_update_post($post);
				}
				else $postID = wp_insert_post($post);
				
				//copy snapshot
				$dir = $options['uploadsPath'];
				$dir .= "/_setup";
				$filename = "$dir/$id.jpg";
				$timestamp = filemtime($filename);

				$dir = $options['uploadsPath'];
				$dir .= "/$label";
				if (!file_exists($dir)) mkdir($dir);

				$dir .= "/snapshots";
				if (!file_exists($dir)) mkdir($dir);

				$snapshot = "$dir/$timestamp.jpg";
				copy($filename, $snapshot);
				update_post_meta($postID, 'vw_lastSnapshot', $snapshot);

				$url = get_permalink($postID);

				//
				if (file_exists($options['streamsPath']))
				{

					if ($address) if (!strstr($label,'.stream'))
						{
							$htmlCode .= "<BR>Channel name must end in .stream when re-streaming!";
							$address = '';
						}

					$file = $options['streamsPath'] . '/' . $label;

					if ($address)
					{

						$myfile = fopen($file, "w");
						if ($myfile)
						{
							fwrite($myfile, $address);
							fclose($myfile);
							$htmlCode .= '<BR>Stream file setup:<br>' . $label . ' = ' . $address .  '<BR> <a class="ui button" href="'.$url.'">Watch Channel '.$label.'</a>';
						}
						else
						{
							$htmlCode .= '<BR>Could not write file: '. $file;
							$address = '';
						}

					}
					
					update_post_meta($postID, 'vw_ipCamera', $address);
					
					list($addressProtocol) = explode(':', $address);	
					update_post_meta($postID, 'stream-protocol', $addressProtocol); //source required for transcoding

					update_post_meta($postID, 'edate', time()); //detected and setup: ready to go live
					VWliveStreaming::streamSnapshot($label, true); //update channel snapshot

				}
				else
				{
					$htmlCode .= '<BR>Stream file could not be setup. Streams folder not found: '. $options['streamsPath'];
				}

				respond($htmlCode );

			}
			//output end
			die;
		}


		//! AJAX HTML Chat

		function wp_ajax_vwls_htmlchat()
		{

			$options = get_option('VWliveStreamingOptions');
			//output clean
			ob_clean();

			// Handling the supported tasks:

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_lwsessions"; //viewers
			$table_chatlog = $wpdb->prefix . "vw_vwls_chatlog";

			/*
TABLE `$table_chatlog` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `username` varchar(64) NOT NULL,
  `room` varchar(64) NOT NULL,
  `message` text NOT NULL,
  `mdate` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `room` (`room`),
  KEY `mdate` (`mdate`)
*/

			$room = sanitize_file_name($_GET['room']);

			//user
			$username = '';

			if (is_user_logged_in())
			{
				$current_user = wp_get_current_user();

				if (isset($current_user) )
				{
					$userName =  $options['userName'];

					if (!$userName) $userName='user_nicename';
					if ($current_user->$userName) $username = urlencode(sanitize_file_name($current_user->$userName));
				}
			}else
			{
				if ($_COOKIE['htmlchat_username']) $username = $_COOKIE['htmlchat_username'];
				else
				{
					$username =  'H_' . base_convert(time()%36 * rand(0,36*36),10,36);
					setcookie('htmlchat_username', $username);
				}
			}

			$ztime = time();

			switch($_GET['task']){

			case 'checkLogged':
				$response = array('logged' => false);

				if (isset($current_user) )
				{
					$response['logged'] = true;

					$response['loggedAs'] = array(
						'name'        => $username,
						'avatar'    => get_avatar_url($current_user->ID)
					);

				}

				$disconnected = VWliveStreaming::updateOnline($username, $room);

				if ($disconnected)
				{
					$response['disconnect'] = $disconnected;
					$response['logged'] = false;
				}

				break;

			case 'submitChat':
				//$response = Chat::submitChat();

				if (!isset($current_user) ) throw new Exception('You are not logged in!');

				$message = sanitize_text_field($_POST['chatText']);
				$message = preg_replace('/([^\s]{12})(?=[^\s])/', '$1'.'<wbr>', $message); //break long words <wbr>:Word Break Opportunity

				$sql="INSERT INTO `$table_chatlog` ( `username`, `room`, `message`, `mdate`, `type`) VALUES ('$username', '$room', '$message', $ztime, '2')";
				$wpdb->query($sql);

				$response = array(
					'status'    => 1,
					'insertID'    => $wpdb->insert_id
				);

				//also update chat log file
				if ($message)
				{

					$message = strip_tags($message,'<p><a><img><font><b><i><u>');

					$message = date("F j, Y, g:i a", $ztime) . " <b>$username</b>: $message";

					//generate same private room folder for both users
					if ($private)
					{
						if ($private > $session) $proom=$session ."_". $private;
						else $proom=$private ."_". $session;
					}

					$dir=$options['uploadsPath'];
					if (!file_exists($dir)) mkdir($dir);

					$dir.="/$room";
					if (!file_exists($dir)) mkdir($dir);

					if ($proom)
					{
						$dir.="/$proom";
						if (!file_exists($dir)) mkdir($dir);
					}

					$day=date("y-M-j",time());

					$dfile = fopen($dir."/Log$day.html","a");
					fputs($dfile,$message."<BR>");
					fclose($dfile);
				}

				break;

			case 'getUsers':

				//old session cleanup

				//close sessions
				$closeTime = time() - $options['onlineExpiration0']; // > client statusInterval
				$sql="UPDATE `$table_name` SET status = 1 WHERE status = 0 AND edate < $closeTime";
				$wpdb->query($sql);

				$users = array();

				//type 5,6 voyeur: do not show
				$sql = "SELECT * FROM `$table_name` where room='$room' and status='0' AND type < 5";
				$userRows = $wpdb->get_results($sql);

				if ($wpdb->num_rows>0)
					foreach ($userRows as $userRow)
					{
						$user = [];
						$user['name'] = $userRow->session;

						//avatar
						$wpUser = get_user_by('login', $userRow->session);
						if ($wpUser)
						{
							$user['avatar'] = get_avatar_url($wpUser->ID);
						}

						$users [] = $user;
					}
				$response = array(
					'users' => $users,
					'total' => count($userRows)
				);
				break;

			case 'getChats':
				$disconnect = VWliveStreaming::updateOnline($username, $room);

				if (!$disconnect)
				{
					//clean old chat logs
					$closeTime = time() - 900; //only keep for 15min
					$sql="DELETE FROM `$table_chatlog` WHERE mdate < $closeTime";
					$wpdb->query($sql);

					//retrieve only messages since user came online
					$sdate = 0;
					if ($session) $sdate = $session->sdate;


					$chats = array();

					$lastID = (int) $_GET['lastID'];

					$sql = "SELECT * FROM `$table_chatlog` WHERE room='$room' AND id > $lastID AND mdate > $sdate ORDER BY mdate DESC LIMIT 0,20";
					$sql = "SELECT * FROM ($sql) items ORDER BY mdate ASC";

					$chatRows = $wpdb->get_results($sql);


					if ($wpdb->num_rows>0) foreach ($chatRows as $chatRow)
						{
							$chat = [];

							$chat['id'] = $chatRow->id;
							$chat['author'] = $chatRow->username;
							$chat['text'] = $chatRow->message;

							$chat['time'] =  array(
								'hours'        => gmdate('H',$chatRow->mdate),
								'minutes'    => gmdate('i',$chatRow->mdate)
							);

							//avatar
							$wpUser = get_user_by('login', $chatRow->username);
							if ($wpUser)
							{
								$chat['avatar'] = get_avatar_url($wpUser->ID);
							}

							$chats[] = $chat;
						}

					$response = array('chats' => $chats);
				}
				else
				{
					$response = array('chats' => array(), 'disconnect' => $disconnect);

				}

				break;

			default:
				throw new Exception('HTML Chat: Wrong task');
			}

			echo json_encode($response);

			die();
		}


		//! channels list ajax handler

		function vwls_channels() //list channels
			{
			//ajax called

			//channel meta:
			//edate s
			//btime s
			//wtime s
			//viewers n
			//maxViewers n
			//maxDate s
			//hasSnapshot 1

			$options = get_option('VWliveStreamingOptions');

			//widget id
			$id = sanitize_file_name($_GET['id']);

			//pagination
			$perPage = (int) $_GET['pp'];
			if (!$perPage) $perPage = $options['perPage'];

			$page = (int) $_GET['p'];
			$offset = $page * $perPage;

			$perRow = (int) $_GET['pr'];

			//admin side
			$ban = (int) $_GET['ban'];

			//
			$category = (int) $_GET['cat'];

			//order
			$order_by = sanitize_file_name($_GET['ob']);
			if (!$order_by) $order_by = $options['order_by'];
			if (!$order_by) $order_by = 'edate';

			//options
			$selectCategory = (int) $_GET['sc'];
			$selectOrder = (int) $_GET['so'];
			$selectPage = (int) $_GET['sp'];

			$selectName = (int) $_GET['sn'];
			$selectTags = (int) $_GET['sg'];

			//tags,name search
			$tags = sanitize_text_field($_GET['tags']);
			$name = sanitize_file_name($_GET['name']);
			if ($name == 'undefined') $name = '';
			if ($tags == 'undefined') $tags = '';

			//output clean
			ob_clean();

			//thumbs dir
			$dir = $options['uploadsPath']. "/_thumbs";

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_channels&pp=' . $perPage .  '&pr=' .$perRow. '&sc=' . $selectCategory . '&sn=' . $selectName .  '&sg=' . $selectTags . '&so=' . $selectOrder . '&sp=' . $selectPage .  '&id=' . $id . '&tags=' . urlencode($tags) . '&name=' . urlencode($name);
			if ($ban) $ajaxurl .= '&ban=' . $ban; //admin side

			if ($options['postChannels']) //channel posts enabled
				{

				//! header option controls

				$ajaxurlP = $ajaxurl . '&p='.$page;
				$ajaxurlPC = $ajaxurl . '&cat=' . $category ;
				$ajaxurlPO = $ajaxurl . '&ob='. $order_by;
				$ajaxurlCO = $ajaxurl . '&cat=' . $category . '&ob='.$order_by ;

				$htmlCode .= '<div class="ui form" style="z-index: 20;"><div class="inline fields">';
				if ($selectCategory)
				{
					$htmlCode .= '<div class="field">' . wp_dropdown_categories('echo=0&name=category' . $id . '&hide_empty=1&class=ui+dropdown&show_option_all=' . __('All', 'livestreaming') . '&selected=' . $category).'</div>';
					$htmlCode .= '<script>var category' . $id . ' = document.getElementById("category' . $id . '"); 			category' . $id . '.onchange = function(){aurl' . $id . '=\'' . $ajaxurlPO.'&cat=\'+ this.value; loadChannels' . $id . '(\'<div class="ui active inline text large loader">Loading category...</div>\')}
			</script>';
				}

				if ($selectOrder)
				{
					$htmlCode .= ' <div class="field"><select class="ui dropdown" id="order_by' . $id . '" name="order_by' . $id . '" onchange="aurl' . $id . '=\'' . $ajaxurlPC.'&ob=\'+ this.value; loadChannels' . $id . '(\'<div class=\\\'ui active inline text large loader\\\'>Ordering channels...</div>\')">';
					$htmlCode .= '<option value="">' . __('Order By', 'livestreaming') . ':</option>';

					$htmlCode .= '<option value="post_date"' . ($order_by == 'post_date'?' selected':'') . '>' . __('Creation Date', 'livestreaming') . '</option>';

					$htmlCode .= '<option value="edate"' . ($order_by == 'edate'?' selected':'') . '>' . __('Broadcast Recently', 'livestreaming') . '</option>';

					$htmlCode .= '<option value="viewers"' . ($order_by == 'viewers'?' selected':'') . '>' . __('Current Viewers', 'livestreaming') . '</option>';

					$htmlCode .= '<option value="maxViewers"' . ($order_by == 'maxViewers'?' selected':'') . '>' . __('Maximum Viewers', 'livestreaming') . '</option>';


					if ($options['rateStarReview'])
					{
						$htmlCode .= '<option value="rateStarReview_rating"' . ($order_by == 'rateStarReview_rating'?' selected':'') . '>' . __('Rating', 'video-share-vod') . '</option>';
						$htmlCode .= '<option value="rateStarReview_ratingNumber"' . ($order_by == 'rateStarReview_ratingNumber'?' selected':'') . '>' . __('Most Rated', 'video-share-vod') . '</option>';
						$htmlCode .= '<option value="rateStarReview_ratingPoints"' . ($order_by == 'rateStarReview_ratingPoints'?' selected':'') . '>' . __('Rate Popularity', 'video-share-vod') . '</option>';

					}

					$htmlCode .= '<option value="rand"' . ($order_by == 'rand'?' selected':'') . '>' . __('Random', 'video-share-vod') . '</option>';

					$htmlCode .= '</select></div>';

				}

				if ($selectTags || $selectName)
				{


					if ($selectTags)
					{
						$htmlCode .= '<div class="field" data-tooltip="Tags, Comma Separated"><div class="ui left icon input"><i class="tags icon"></i><INPUT class="videowhisperInput" type="text" size="12" name="tags" id="tags" placeholder="' . __('Tags', 'video-share-vod')  . '" value="' .htmlspecialchars($tags). '">
					</div></div>';
					}

					if ($selectName)
					{
						$htmlCode .= '<div class="field"><div class="ui left corner labeled input"><INPUT class="videowhisperInput" type="text" size="12" name="name" id="name" placeholder="' . __('Name', 'video-share-vod')  . '" value="' .htmlspecialchars($name). '">
  <div class="ui left corner label">
    <i class="asterisk icon"></i>
  </div>
					</div></div>';
					}

					//search button
					$htmlCode .= '<div class="field" data-tooltip="Search by Tags and/or Name"><button class="ui icon button" type="submit" name="submit" id="submit" value="' . __('Search', 'video-share-vod') . '" onclick="aurl' . $id . '=\'' . $ajaxurlCO .'&tags=\' + document.getElementById(\'tags\').value +\'&name=\' + document.getElementById(\'name\').value; loadChannels' . $id . '(\'<div class=\\\'ui active inline text large loader\\\'>Searching Channels...</div>\')"><i class="search icon"></i></button></div>';

				}

				$htmlCode .= '</div></div>';


				//! query args
				$args=array(
					'post_type' => 'channel',
					'post_status' => 'publish',
					'posts_per_page' => $perPage,
					'offset'           => $offset,
					'order'            => 'DESC',
					'meta_query' => array(
						array( 'key' => 'hasSnapshot', 'value' => '1'),
					)
				);

				switch ($order_by)
				{
				case 'post_date':
					$args['orderby'] = 'post_date';
					break;

				case 'rand':
					$args['orderby'] = 'rand';
					break;

				default:
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = $order_by;
					break;
				}

				if ($category)  $args['category'] = $category;

				if ($tags)
				{
					$tagList = explode(',', $tags);
					foreach ($tagList as $key=>$value) $tagList[$key] = trim($tagList[$key] );

					$args['tax_query'] = array(
						array(
							'taxonomy'  => 'post_tag',
							'field'     => 'slug',
							'operator' => 'AND',
							'terms'     => $tagList
						)
					);
				}

				if ($name)
				{
					$args['s'] = $name;
				}


				$postslist = get_posts( $args );

				//! list channels
				if (count($postslist)>0)
				{
					//echo '<div class="ui grid">';

					$k = 0;
					foreach ( $postslist as $item )
					{
						if ($perRow) if ($k) if ($k % $perRow == 0) $htmlCode .= '<br>';

								$edate =  get_post_meta($item->ID, 'edate', true);
							$age = VWliveStreaming::format_age(time() -  $edate);
						$name = sanitize_file_name($item->post_title);

						if ($ban) $banLink = '<a class = "button" href="admin.php?page=live-streaming-live&ban=' . urlencode( $name ) . '">Ban This Channel</a><br>';

						$htmlCode .= '<div class="videowhisperChannel">';
						$htmlCode .= '<div class="videowhisperTitle">' . $name  . '</div>';
						$htmlCode .= '<div class="videowhisperTime">' . $banLink . $age . '</div>';

						$ratingCode = '';
						if ($options['rateStarReview'])
						{
							$rating = get_post_meta($item->ID, 'rateStarReview_rating', true);
							$max = 5;
							if ($rating > 0) $ratingCode = '<div class="ui star rating readonly" data-rating="' . round($rating * $max) . '" data-max-rating="' . $max . '"></div>'; // . number_format($rating * $max,1)  . ' / ' . $max
							$htmlCode .= '<div class="videowhisperChannelRating">' . $ratingCode . '</div>';
						}


						$thumbFilename = "$dir/" . $name . ".jpg";
						$url = VWliveStreaming::roomURL($name);

						$noCache = '';
						if ($age=='LIVE') $noCache='?'.((time()/10)%100);

						$showImage=get_post_meta( $item->ID, 'showImage', true );

						if (!file_exists($thumbFilename) || $showImage =='all') //show thumb instead
							{
							$attach_id = get_post_thumbnail_id($item->ID );
							if ($attach_id) $thumbFilename = get_attached_file($attach_id);
						}

						if (file_exists($thumbFilename) && !strstr($thumbFilename, '/.jpg')) $htmlCode .= '<a href="' . $url . '"><IMG src="' . VWliveStreaming::path2url($thumbFilename) . $noCache .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';
						else $htmlCode .= '<a href="' . $url . '"><IMG SRC="' . plugin_dir_url(__FILE__). 'screenshot-3.jpg" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';


						$htmlCode .= "</div>";

					}

					//echo '</div>';
				}
				else $htmlCode .= "No channels match current selection. Channels get listed after being broadcast or configured as events, with snapshot/picture.";

				//! pagination
				if ($selectPage)
				{
					$htmlCode .= '<div class="ui form"><div class="inline fields">';
					if ($page>0) $htmlCode .= ' <a class="ui labeled icon button" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page-1). '\'; loadChannels' . $id . '(\'<div class=\\\'ui active inline text large loader\\\'>Loading previous page...</div>\');"><i class="left arrow icon"></i> Previous</a> ';

					if (count($postslist) == $perPage) $htmlCode .= ' <a class="ui right labeled icon button" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page+1). '\'; loadChannels' . $id . '(\'<div class=\\\'ui active inline text large loader\\\'>Loading next page...</div>\');"> Next <i class="right arrow icon"></i></a> ';

					$htmlCode .= '</div></div>';

				}


			}
			else // channel post disabled - check db --
				{
				global $wpdb;
				$table_name3 = $wpdb->prefix . "vw_lsrooms";

				$items =  $wpdb->get_results("SELECT * FROM `$table_name3` WHERE status=1 ORDER BY edate DESC LIMIT $offset, ". $perPage);
				if ($items) foreach ($items as $item)
					{
						$age = VWliveStreaming::format_age(time() -  $item->edate);

						if ($ban) $banLink = '<a class = "button" href="admin.php?page=live-streaming-live&ban=' . urlencode( $item->name ) . '">Ban This Channel</a><br>';

						$htmlCode .= '<div class="videowhisperChannel">';
						$htmlCode .= '<div class="videowhisperTitle">' . $item->name  . '</div>';
						$htmlCode .= '<div class="videowhisperTime">' . $banLink . $age . '</div>';

						$thumbFilename = "$dir/" . $item->name . ".jpg";

						$url = VWliveStreaming::roomURL($item->name);

						$noCache = '';
						if ($age=='LIVE') $noCache='?'.((time()/10)%100);

						if (file_exists($thumbFilename)) $htmlCode .= '<a href="' . $url . '"><IMG src="' . VWliveStreaming::path2url($thumbFilename) . $noCache .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';
						else $htmlCode .= '<a href="' . $url . '"><IMG SRC="' . plugin_dir_url(__FILE__). 'screenshot-3.jpg" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';
						$htmlCode .= "</div>";
					}

				//pagination
				if ($selectPage)
				{
					$htmlCode .= "<BR>";
					if ($page>0) $htmlCode .= ' <a class="ui labeled icon button" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page-1). '\'; loadChannels' . $id . '(\'Loading previous page...\');">><i class="left arrow icon"></i> Previous</a> ';

					if (count($items) == $perPage) $htmlCode .= ' <a class="ui right labeled icon button" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page+1). '\'; loadChannels' . $id . '(\'Loading next page...\');">Next  <i class="right arrow icon"></i></a> ';
				}
			}


			echo $htmlCode;

			die;
		}

		//! broadcast ajax handler
		function vwls_broadcast() //dedicated broadcasting page
			{
			ob_clean();
?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>VideoWhisper Live Broadcast</title>
</head>
<body bgcolor="<?php echo $bgcolor?>">
<style type="text/css">
<!--
BODY
{
	padding-right: 6px;
	margin: 0px;
	background: #333;
	font-family: Arial, Helvetica, sans-serif;
	font-size: 12px;
	color: #EEE;
}
-->
</style>
<?php
			include(plugin_dir_path( __FILE__ ) . "ls/flash_detect.php");

			echo do_shortcode('[videowhisper_broadcast]');

			die;
		}

		function fixPath($p) {

			//adds ending slash if missing

			//    $p=str_replace('\\','/',trim($p));
			return (substr($p,-1)!='/') ? $p.='/' : $p;
		}


		function varSave($path, $var)
		{
			file_put_contents($path, serialize($var));
		}

		function varLoad($path)
		{
			if (!file_exists($path)) return false;

			return unserialize(file_get_contents($path));
		}

		function updatePlaylist($stream, $active = true)
		{
			//updates playlist for channel $stream in global playlist
			if (!$stream) return;

			$options = get_option('VWliveStreamingOptions');

			$uploadsPath = $options['uploadsPath'];
			if (!file_exists($uploadsPath)) mkdir($uploadsPath);
			$playlistPathGlobal = $uploadsPath . '/playlist_global.txt';
			if (!file_exists($playlistPathGlobal)) VWliveStreaming::varSave($playlistPathGlobal, array());

			$upath = $uploadsPath . "/$stream/";
			if (!file_exists($upath)) mkdir($upath);
			$playlistPath = $upath . 'playlist.txt';
			if (!file_exists($playlistPath)) VWliveStreaming::varSave($playlistPath, array());

			$playlistGlobal = VWliveStreaming::varLoad($playlistPathGlobal);
			$playlist = VWliveStreaming::varLoad($playlistPath);

			if ($active) $playlistGlobal[$stream] = $playlist;
			else unset($playlistGlobal[$stream]);

			VWliveStreaming::varSave($playlistPathGlobal, $playlistGlobal);

			VWliveStreaming::updatePlaylistSMIL();
		}

		function updatePlaylistSMIL()
		{
			$options = get_option('VWliveStreamingOptions');

			//! update Playlist SMIL
			$streamsPath = VWliveStreaming::fixPath($options['streamsPath']);
			$smilPath = $streamsPath . 'playlist.smil';

			$smilCode .= <<<HTMLCODE
<smil>
    <head>
    </head>
    <body>

HTMLCODE;

			if ($options['playlists'])
			{

				$uploadsPath = $options['uploadsPath'];
				if (!file_exists($uploadsPath)) mkdir($uploadsPath);
				$playlistPathGlobal = $uploadsPath . '/playlist_global.txt';
				if (!file_exists($playlistPathGlobal)) VWliveStreaming::varSave($playlistPathGlobal, array());
				$playlistGlobal = VWliveStreaming::varLoad($playlistPathGlobal);


				$streams = array_keys($playlistGlobal);
				foreach ($streams as $stream)
					$smilCode .= '<stream name="' . $stream . '"></stream>
				';

				foreach ($streams as $stream)
					foreach ($playlistGlobal[$stream] as $item)
					{
						$smilCode .= '
        <playlist name="' . $stream . $item['Id'] . '" playOnStream="' . $stream . '" repeat="'. ($item['Repeat']?'true':'false') .'" scheduled="' . $item['Scheduled']. '">';

						if ($item['Videos']) if (is_array($item['Videos'])) foreach ($item['Videos'] as $video)
									$smilCode .= '
		<video src="'. $video['Video'] . '" start="' . $video['Start'] . '" length="' . $video['Length'] . '"/>';

								$smilCode .= '
		</playlist>';
					}
			}
			$smilCode .= <<<HTMLCODE

    </body>
</smil>
HTMLCODE;

			file_put_contents($smilPath, $smilCode);
		}


		function path2stream($path, $withExtension=true, $withPrefix=true)
		{
			$options = get_option( 'VWliveStreamingOptions' );

			$stream = substr($path, strlen($options['streamsPath']));
			if ($stream[0]=='/') $stream = substr($stream, 1);

			if ($withPrefix)
			{
				$ext = pathinfo($stream, PATHINFO_EXTENSION);
				$prefix = $ext . ':';
			}else $prefix = '';

			if (!file_exists($options['streamsPath'] . '/' . $stream)) return '';
			elseif ($withExtension) return $prefix.$stream;
			else return $prefix.pathinfo($stream, PATHINFO_FILENAME);
		}

		//! Playlist AJAX handler

		function vwls_playlist()
		{
			ob_clean();

			$postID = (int) $_GET['channel'];

			if (!$postID)
			{
				echo "No channel ID provided!";
				die;
			}

			$channel = get_post( $postID );
			if (!$channel)
			{
				echo "Channel not found!";
				die;
			}

			$current_user = wp_get_current_user();

			if ($channel->post_author != $current_user->ID)
			{
				echo "Access not permitted (different channel owner)!";
				die;
			}

			$stream = sanitize_file_name($channel->post_title);

			$options = get_option('VWliveStreamingOptions');

			$uploadsPath = $options['uploadsPath'];
			if (!file_exists($uploadsPath)) mkdir($uploadsPath);

			$upath = $uploadsPath . "/$stream/";
			if (!file_exists($upath)) mkdir($upath);

			$playlistPath = $upath . 'playlist.txt';

			if (!file_exists($playlistPath)) VWliveStreaming::varSave($playlistPath, array());

			switch ($_GET['task'])
			{
			case 'list':
				$rows = VWliveStreaming::varLoad($playlistPath);



				//sort rows by order
				if (count($rows))
				{
					//sort
					function cmp_by_order($a, $b) {

						if ($a['Order'] == $b['Order']) return 0;
						return ($a['Order'] < $b['Order']) ? -1 : 1;
					}

					usort($rows,  'cmp_by_order'); //sort

					//update Ids to match keys (order)
					$updated = 0;
					foreach ($rows as $key => $value)
						if ($rows[$key]['Id'] != $key)
						{
							$rows[$key]['Id'] = $key;
							$updated = 1;
						}
					if ($updated) VWliveStreaming::varSave($playlistPath, $rows);

				}


				//Return result to jTable
				$jTableResult = array();
				$jTableResult['Result'] = "OK";
				$jTableResult['Records'] = $rows;
				print json_encode($jTableResult);

				break;

			case 'videolist':
				$ItemId = (int) $_GET['item'];
				$jTableResult = array();

				$playlist = VWliveStreaming::varLoad($playlistPath);

				if ($schedule = $playlist[$ItemId])
				{
					if (!$schedule['Videos']) $schedule['Videos'] = array();

					//sort videos



					//sort rows by order
					if (count($schedule['Videos']))
					{

						//sort
						function cmp_by_order($a, $b) {

							if ($a['Order'] == $b['Order']) return 0;
							return ($a['Order'] < $b['Order']) ? -1 : 1;
						}

						usort($schedule['Videos'],  'cmp_by_order'); //sort

						//update Ids to match keys (order)
						$updated = 0;
						foreach ($schedule['Videos'] as $key => $value)
							if ($schedule['Videos'][$key]['Id'] != $key)
							{
								$schedule['Videos'][$key]['Id'] = $key;
								$updated = 1;
							}

						$playlist[$ItemId] = $schedule;
						if ($updated) VWliveStreaming::varSave($playlistPath, $playlist);

					}

					$jTableResult['Records'] = $schedule['Videos'];
					$jTableResult['Result'] = "OK";
				}
				else
				{
					$jTableResult['Result'] = "ERROR";
					$jTableResult['Message'] = "Schedule $ItemId not found!";
				}

				print json_encode($jTableResult);
				break;

			case 'videoupdate':
				//delete then add new

				$playlist = VWliveStreaming::varLoad($playlistPath);
				$ItemId = (int) $_POST['ItemId'];
				$Id = (int) $_POST['Id'];

				$jTableResult = array();
				if ($playlist[$ItemId])
				{

					//find and remove record with that Id
					foreach ($playlist[$ItemId]['Videos'] as $key => $value)
						if ($value['Id'] == $Id)
						{
							unset($playlist[$ItemId]['Videos'][$key]);
							break;
						}

					VWliveStreaming::varSave($playlistPath,$playlist);
				}

			case 'videoadd':
				$playlist = VWliveStreaming::varLoad($playlistPath);
				$ItemId = (int) $_POST['ItemId'];

				$jTableResult = array();
				if ($schedule = $playlist[$ItemId])
				{
					if (!$schedule['Videos']) $schedule['Videos'] = array();

					$maxOrder = 0; $maxId = 0;
					foreach ($schedule['Videos'] as $item)
					{
						if ($item['Order'] > $maxOrder) $maxOrder = $item['Order'];
						if ($item['Id'] > $maxId) $maxId = $item['Id'];
					}

					$item = array();
					$item['Video'] = sanitize_text_field($_POST['Video']);
					$item['Id'] = (int) $_POST['Id'];
					$item['Order'] = (int) $_POST['Order'];
					$item['Start'] = (int) $_POST['Start'];
					$item['Length'] = (int) $_POST['Length'];

					if (!$item['Order']) $item['Order'] = $maxOrder + 1;
					if (!$item['Id']) $item['Id'] = $maxId + 1;

					$playlist[$ItemId]['Videos'][] = $item;

					VWliveStreaming::varSave($playlistPath,$playlist);

					$jTableResult['Result'] = "OK";
					$jTableResult['Record'] = $item;
				}
				else
				{
					$jTableResult['Result'] = "ERROR";
					$jTableResult['Message'] = "Schedule $ItemId not found!";
				}

				//Return result to jTable
				print json_encode($jTableResult);

				break;

			case 'videoremove':
				$playlist = VWliveStreaming::varLoad($playlistPath);
				$ItemId = (int) $_GET['item'];
				$Id = (int) $_POST['Id'];

				$jTableResult = array();
				if ($schedule = $playlist[$ItemId])
				{

					//find and remove record with that Id
					foreach ($playlist[$ItemId]['Videos'] as $key => $value)
						if ($value['Id'] == $Id)
						{
							unset($playlist[$ItemId]['Videos'][$key]);
							break;
						}

					VWliveStreaming::varSave($playlistPath,$playlist);

					$jTableResult['Result'] = "OK";
					$jTableResult['Remaining'] = $playlist[$ItemId]['Videos'];
				}
				else
				{
					$jTableResult['Result'] = "ERROR";
					$jTableResult['Message'] = "Schedule $ItemId not found!";
				}

				//Return result to jTable
				print json_encode($jTableResult);

				break;

			case 'source':

				//retrieve videos owned by user (from all channels)

				//query
				$args=array(
					'post_type' =>  $options['custom_post_video'],
					'author'        =>  $current_user->ID,
					'orderby'       =>  'post_date',
					'order'            => 'DESC',
				);

				$postslist = get_posts( $args );
				$rows = array();

				if (count($postslist)>0)
				{
					foreach ( $postslist as $item )
					{
						$row = array();
						$row['DisplayText'] = $item->post_title;

						$video_id = $item->ID;

						//retrieve video stream
						$streamPath = '';
						$videoPath = get_post_meta($video_id, 'video-source-file', true);
						$ext = pathinfo($videoPath, PATHINFO_EXTENSION);

						//use conversion if available
						$videoAdaptive = get_post_meta($video_id, 'video-adaptive', true);
						if ($videoAdaptive) $videoAlts = $videoAdaptive;
						else $videoAlts = array();

						foreach (array('high', 'mobile') as $frm)
							if ($alt = $videoAlts[$frm])
								if (file_exists($alt['file']))
								{
									$ext = pathinfo($alt['file'], PATHINFO_EXTENSION);
									$streamPath = VWliveStreaming::path2stream($alt['file']);
									break;
								};

						//user original
						if (!$streamPath)
							if (in_array($ext, array('flv','mp4','m4v')))
							{
								//use source if compatible
								$streamPath = VWliveStreaming::path2stream($videoPath);
							}

						$row['Value'] = $streamPath;
						$rows[] = $row;
					}
				}
				//Return result to jTable
				$jTableResult = array();
				$jTableResult['Result'] = "OK";
				$jTableResult['Options'] = $rows;
				print json_encode($jTableResult);

				break;

			case 'update':
				//delete then create new
				$Id = (int) $_POST['Id'];

				$playlist = VWliveStreaming::varLoad($playlistPath);
				if (!is_array($playlist)) $playlist = array();

				foreach ($playlist as $key => $value)
					if ($value['Id'] == $Id)
					{
						unset($playlist[$key]);
						break;
					}

				VWliveStreaming::varSave($playlistPath,$playlist);

			case 'create':

				$playlist = VWliveStreaming::varLoad($playlistPath);
				if (!is_array($playlist)) $playlist = array();

				$maxOrder = 0; $maxId = 0;
				foreach ($playlist as $item)
				{
					if ($item['Order'] > $maxOrder) $maxOrder = $item['Order'];
					if ($item['Id'] > $maxId) $maxId = $item['Id'];
				}

				$item = array();
				$item['Id'] = (int) $_POST['Id'];
				$item['Video'] = sanitize_text_field($_POST['Video']);
				$item['Repeat'] = (int) $_POST['Repeat'];
				$item['Scheduled'] = sanitize_text_field($_POST['Scheduled']);
				$item['Order'] = (int) $_POST['Order'];
				if (!$item['Order']) $item['Order'] = $maxOrder + 1;
				if (!$item['Id']) $item['Id'] = $maxId + 1;
				if (!$item['Scheduled']) $item['Scheduled']  = date('Y-m-j h:i:s');

				$playlist[$item['Id']] = $item;

				VWliveStreaming::varSave($playlistPath, $playlist);

				//Return result to jTable
				$jTableResult = array();
				$jTableResult['Result'] = "OK";
				$jTableResult['Record'] = $item;
				print json_encode($jTableResult);
				break;

			case 'delete':
				$Id = (int) $_POST['Id'];

				$playlist = VWliveStreaming::varLoad($playlistPath);
				if (!is_array($playlist)) $playlist = array();

				foreach ($playlist as $key => $value)
					if ($value['Id'] == $Id)
					{
						unset($playlist[$key]);
						break;
					}

				VWliveStreaming::varSave($playlistPath, $playlist);

				//Return result to jTable
				$jTableResult = array();
				$jTableResult['Result'] = "OK";
				print json_encode($jTableResult);
				break;

			default:
				echo 'Action not supported!';
			}

			die;

		}

		//! manual transcoding ajax handler

		function vwls_trans()
		{

			ob_clean();

			$stream = sanitize_file_name($_GET['stream']);

			if (!$stream)
			{
				echo "No stream name provided!";
				return;
			}

			$options = get_option('VWliveStreamingOptions');

			$uploadsPath = $options['uploadsPath'];
			if (!file_exists($uploadsPath)) mkdir($uploadsPath);

			$upath = $uploadsPath . "/$stream/";
			if (!file_exists($upath)) mkdir($upath);

			$rtmp_server=$options['rtmp_server'];

			switch ($_GET['task'])
			{
			case 'enable':

				if ( !is_user_logged_in() )
				{
					echo "Not authorised!";
					exit;
				}

				$cmd = "ps aux | grep '/i_$stream -i rtmp'";
				exec($cmd, $output, $returnvalue);
				//var_dump($output);

				$admin_ajax = admin_url() . 'admin-ajax.php';

				$transcoding = 0;

				foreach ($output as $line) if (strstr($line, "ffmpeg"))
					{
						$columns = preg_split('/\s+/',$line);
						echo "Transcoder is currently Active (".$columns[1]." CPU: ".$columns[2]." Mem: ".$columns[3].")";
						$transcoding = 1;
					}

				if ($transcoding)
				{
					echo '<script>

				setTimeout(\' if (loaderTranscoder) if (loaderTranscoder.abort === \\\'function\\\') loaderTranscoder.abort(); if (transcodingOn) loaderTranscoder = $j("#videowhisperTranscoder").html(ajax_load).load("'.$admin_ajax.'?action=vwls_trans&task=enable&stream='.$stream.'");\', 120000 );

				</script>';
				}

				if (!$transcoding)
				{

					$current_user = wp_get_current_user();


					global $wpdb;
					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

					if ($options['externalKeysTranscoder'])
					{
						$key = md5('vw' . $options['webKey'] . $current_user->ID . $postID);

						$keyView = md5('vw' . $options['webKey']. $postID);

						//?session&room&key&broadcaster&broadcasterid
						$rtmpAddress = $options['rtmp_server'] . '?'. urlencode('i_' . $stream) .'&'. urlencode($stream) .'&'. $key . '&1&' . $current_user->ID . '&videowhisper';
						$rtmpAddressView = $options['rtmp_server'] . '?'. urlencode('ffmpeg_' . $stream) .'&'. urlencode($stream) .'&'. $keyView . '&0&videowhisper';

						//VWliveStreaming::webSessionSave("/i_". $stream, 1);
					}
					else
					{
						$rtmpAddress = $options['rtmp_server'];
						$rtmpAddressView = $options['rtmp_server'];
					}

					echo "Transcoding process currently not active for '$stream'.<BR>";
					$log_file =  $upath . "videowhisper_transcode.log";


					exec("tail -n 1 $log_file", $output1, $returnvalue);
					echo "Logs: ". substr($output1[0],0,100) . " ...<br>";

					//-vcodec copy
					$cmd = $options['ffmpegPath'] .' ' .  $options['ffmpegTranscode'] . " -threads 1 -f flv \"" .
						$rtmpAddress . "/i_". $stream . "\" -i \"" . $rtmpAddressView ."/". $stream . "\" >&$log_file & ";


					//echo $cmd;
					exec($cmd, $output, $returnvalue);
					exec("echo '$cmd' >> $log_file.cmd", $output, $returnvalue);

					$cmd = "ps aux | grep '/i_$stream -i rtmp'";
					exec($cmd, $output, $returnvalue);
					//var_dump($output);

					foreach ($output as $line) if (strstr($line, "ffmpeg"))
						{
							$columns = preg_split('/\s+/',$line);
							echo "Launching transcoder process #".$columns[1]." ...";
						}


					echo '<script>

				setTimeout(\' if (loaderTranscoder) if (loaderTranscoder.abort === \\\'function\\\') loaderTranscoder.abort(); if (transcodingOn) loaderTranscoder = $j("#videowhisperTranscoder").html(ajax_load).load("'.$admin_ajax.'?action=vwls_trans&task=enable&stream='.$stream.'");\', 120000 );

				</script>';

				}

				$admin_ajax = admin_url() . 'admin-ajax.php';

				echo "<BR><a target='_blank' href='".$admin_ajax . "?action=vwls_trans&task=html5&stream=$stream'> Preview </a> (open in Safari)";
				break;


			case 'close':
				if ( !is_user_logged_in() )
				{
					echo "Not authorised!";
					exit;
				}

				$cmd = "ps aux | grep '/i_$stream -i rtmp'";
				exec($cmd, $output, $returnvalue);
				//var_dump($output);

				$transcoding = 0;
				foreach ($output as $line) if (strstr($line, "ffmpeg"))
					{
						$columns = preg_split('/\s+/',$line);
						$cmd = "kill -9 " . $columns[1];
						exec($cmd, $output, $returnvalue);
						echo "<BR>Closing #".$columns[1]." CPU: ".$columns[2]." Mem: ".$columns[3];
						$transcoding = 1;
					}

				if (!$transcoding)
				{
					echo "Transcoder not found for '$stream'! Nothing to close.";
				}

				break;


			case "html5";
?>
<p>iOS live stream link (open with Safari or test with VLC): <a href="<?php echo $options['httpstreamer']?>i_<?php echo $stream?>/playlist.m3u8"><br />
  <?php echo $stream?> Video</a></p>


<p>HTML5 live video embed below should be accessible <u>only in <B>Safari</B> browser</u> (PC or iOS):</p>
<?php
				echo do_shortcode('[videowhisper_hls channel="'.$stream.'"]');
?>
<p> Due to HTTP based live streaming technology limitations, video can have 15s or more latency. Use a browser with flash support for faster interactions based on RTMP. </p>
<p>Most devices other than iOS, support regular flash playback for live streams.</p>

<style type="text/css">
<!--
BODY
{
	margin:0px;
	background: #333;
	font-family: Arial, Helvetica, sans-serif;
	font-size: 14px;
	color: #EEE;
	padding: 20px;
}

a {
	color: #F77;
	text-decoration: none;
}
-->
</style>
<?php

				break;
			}
			die;
		}



		function shortcode_livesnapshots()
		{

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_sessions";
			$table_name2 = $wpdb->prefix . "vw_lwsessions";

			$root_url = get_bloginfo( "url" ) . "/";

			//clean recordings
			VWliveStreaming::cleanSessions(0);
			VWliveStreaming::cleanSessions(1);


			$items =  $wpdb->get_results("SELECT * FROM `$table_name` where status='1' and type='1'");

			$livesnapshotsCode .=  "<div>Live Channels";
			if ($items) foreach ($items as $item)
				{
					$count =  $wpdb->get_results("SELECT count(*) as no FROM `$table_name2` where status='1' and type='1' and room='".$item->room."'");


					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item->room . "' and post_type='channel' LIMIT 0,1" );
					if ($postID) $url = get_post_permalink($postID);
					else $url = plugin_dir_url(__FILE__) . 'ls/channel.php?n=' . urlencode($item->name);


					$urli = $root_url . "wp-content/plugins/videowhisper-live-streaming-integration/ls/snapshots/".urlencode($item->room). ".jpg";
					if (!file_exists("wp-content/plugins/videowhisper-live-streaming-integration/ls/snapshots/".urlencode($item->room). ".jpg")) $urli = $root_url .
							"wp-content/plugins/videowhisper-live-streaming-integration/ls/snapshots/no_video.png";

					$livesnapshotsCode .= "<div style='border: 1px dotted #390; width: 240px; padding: 1px'><a href='$urlc'><IMG width='240px' SRC='$urli'><div ><B>".$item->room."</B>
(".($count[0]->no+1).") ".($item->message?": ".$item->message:"") ."</div></a></div>";
				}
			else  $livesnapshotsCode .= "<div>No broadcasters online.</div>";

			$livesnapshotsCode .=  "</div> ";

			$options = get_option('VWliveStreamingOptions');
			$state = 'block' ;
			if (!$options['videowhisper']) $state = 'none';
			$livesnapshotsCode .= '<div id="VideoWhisper" style="display: ' . $state . ';"><p>Powered by VideoWhisper <a href="https://videowhisper.com/?p=WordPress+Live+Streaming">Live Video
Streaming Software</a>.</p></div>';


			echo $livesnapshotsCode;
		}

		//! Widget

		function widget($args) {
			extract($args);
			echo $before_widget;
			echo $before_title;?>Live Streaming<?php echo $after_title;
			VWliveStreaming::widgetContent();
			echo $after_widget;
		}

		function widgetContent()
		{
			global $wpdb;
			$table_name = $wpdb->prefix . "vw_sessions";
			$table_name2 = $wpdb->prefix . "vw_lwsessions";

			$root_url = get_bloginfo( "url" ) . "/";

			//clean recordings
			VWliveStreaming::cleanSessions(0);
			VWliveStreaming::cleanSessions(1);

			$items =  $wpdb->get_results("SELECT * FROM `$table_name` where status='1' and type='1'");

			echo "<ul>";
			if ($items) foreach ($items as $item)
				{
					$count =  $wpdb->get_results("SELECT count(id) as no FROM `$table_name2` where status='1' and type='1' and room='".$item->room."'");

					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item->room . "' and post_type='channel' LIMIT 0,1" );
					if ($postID) $url = get_post_permalink($postID);
					else $url = plugin_dir_url(__FILE__) . 'ls/channel.php?n=' . urlencode($item->name);


					echo "<li><a href='" . $url . "'><B>".$item->room."</B>
(".($count[0]->no+1).") ".($item->message?": ".$item->message:"") ."</a></li>";
				}
			else echo "<li>No broadcasters online.</li>";
			echo "</ul>";

			$options = get_option('VWliveStreamingOptions');

			if ($options['userChannels']||$options['anyChannels'])
				if (is_user_logged_in())
				{
					$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

					$current_user = wp_get_current_user();

					if ($current_user->$userName) $username = $current_user->$userName;
					$username = sanitize_file_name($username);
					?><a href="<?php echo plugin_dir_url(__FILE__); ?>ls/?n=<?php echo $username ?>"><img src="<?php echo plugin_dir_url(__FILE__);
					?>ls/templates/live/i_webcam.png" align="absmiddle" border="0">Video Broadcast</a>
	<?php
				}

			$state = 'block' ;
			if (!$options['videowhisper']) $state = 'none';
			echo '<div id="VideoWhisper" style="display: ' . $state . ';"><p>Powered by VideoWhisper <a href="https://videowhisper.com/?p=WordPress+Live+Streaming">Live Video Streaming
Software</a>.</p></div>';
		}


		function delete_associated_media($id, $unlink=false, $except=0) {

			$htmlCode .= "Removing... ";

			$media = get_children(array(
					'post_parent' => $id,
					'post_type' => 'attachment'
				));
			if (empty($media)) return $htmlCode;

			foreach ($media as $file) {

				if ($except) if ($file->ID == $except) break;

					if ($unlink)
					{
						$filename = get_attached_file($file->ID);
						$htmlCode .=  " Removing $filename #" . $file->ID;
						if (file_exists($filename)) unlink($filename);
					}

				wp_delete_attachment($file->ID);
			}

			return $htmlCode;
		}


		//! Channel Post

		function the_title($title) {
			$title = esc_attr($title);
			$findthese = array(
				'#Protected:#',
				'#Private:#'
			);
			$replacewith = array(
				'', // What to replace "Protected:" with
				'' // What to replace "Private:" with
			);
			$title = preg_replace($findthese, $replacewith, $title);
			return $title;
		}


		function channel_page($content)
		{

			$options = get_option('VWliveStreamingOptions');

			if (!$options['postChannels']) return $content;

			if (!is_single()) return $content;
			$postID = get_the_ID() ;

			if (get_post_type( $postID ) != $options['custom_post']) return $content;

			//   global $wpdb;
			//   $stream = $wpdb->get_var( "SELECT post_name FROM $wpdb->posts WHERE ID = '" . $postID . "' and post_type='channel' LIMIT 0,1" );


			$stream = sanitize_file_name(get_the_title($postID));

			global $wp_query;

			$showBroadcastInterface = 0;
			if ($options['broadcasterRedirect']
				&& !array_key_exists( 'broadcast' , $wp_query->query_vars )
				&& !array_key_exists( 'flash-broadcast' , $wp_query->query_vars )
				&& !array_key_exists( 'flash-view' , $wp_query->query_vars )
				&& !array_key_exists( 'flash-video' , $wp_query->query_vars )
				&& !array_key_exists( 'webrtc-broadcast' , $wp_query->query_vars )
				&& !array_key_exists( 'webrtc-playback' , $wp_query->query_vars )
				&& !array_key_exists( 'external' , $wp_query->query_vars )
				&& !array_key_exists( 'external-broadcast' , $wp_query->query_vars )
				&& !array_key_exists( 'external-playback' , $wp_query->query_vars )
				&& !array_key_exists( 'hls' , $wp_query->query_vars )
				&& !array_key_exists( 'mpeg' , $wp_query->query_vars )
				&& !array_key_exists( 'html5-view' , $wp_query->query_vars ) )  //don't redirect from broadcast page or specific interfaces
				{
				$user = wp_get_current_user();
				if ( $user->exists()) //loggedin
					{
					$post = get_post($postID);

					if ($user->ID == $post->post_author) //owner
						{
						if ($options['broadcasterRedirect'] == 'broadcast') $showBroadcastInterface = 1;

						if ($options['broadcasterRedirect'] == 'dashboard')
						{
							$url = get_permalink(get_option("vwls_page_manage"));
							$string = '<script type="text/javascript">';
							$string .= 'window.location = "' . $url . '"';
							$string .= '</script>';

							return $string;
						}
					}
				}
			}

			$offline = '';

			if( array_key_exists( 'broadcast' , $wp_query->query_vars ) || $showBroadcastInterface )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream, true))
					$addCode = '[videowhisper_broadcast]';
				$showBroadcastInterface = 1;
			}
			elseif( array_key_exists( 'webrtc-broadcast' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream, true))
					$addCode = '[videowhisper_webrtc_broadcast]';
				$showBroadcastInterface = 1;
			}
			elseif( array_key_exists( 'flash-broadcast' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream, true))
					$addCode = '[videowhisper_broadcast flash="1"]';
				$showBroadcastInterface = 1;
			}
			elseif( array_key_exists( 'flash-view' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_watch flash="1"]';
			}
			elseif( array_key_exists( 'video' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_video]';
			}
			elseif( array_key_exists( 'flash-video' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_video flash="1"]';
			}
			elseif( array_key_exists( 'hls' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_hls]';
			}
			elseif( array_key_exists( 'mpeg' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_mpeg]';
			}
			elseif( array_key_exists( 'webrtc-playback' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_webrtc_playback]';
			}
			elseif( array_key_exists( 'html5-view' , $wp_query->query_vars ) )
			{
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = '[videowhisper_htmlchat_playback]';
			}
			elseif( array_key_exists( 'external' , $wp_query->query_vars ) )
			{
				$addCode = '[videowhisper_external]';
				$content = '';
			}
			elseif( array_key_exists( 'external-broadcast' , $wp_query->query_vars ) )
			{
				$addCode = '[videowhisper_external_broadcast]';
				$content = '';
			}
			elseif( array_key_exists( 'external-playback' , $wp_query->query_vars ) )
			{
				$addCode = '[videowhisper_external_playback]';
				$content = '';
			}
			else
				{ //default
				if (! $addCode = $offline = VWliveStreaming::channelInvalid($stream))
					$addCode = "" . '[videowhisper_watch]';
			}

			//ip camera or playlist: update snapshot
			if (get_post_meta( $postID, 'vw_ipCamera', true ) || get_post_meta( $postID, 'vw_playlistActive', true ))
			{
				VWliveStreaming::streamSnapshot($stream, true);
			}


			//other data
			if (    !array_key_exists( 'external' , $wp_query->query_vars )
				&& !array_key_exists( 'external-broadcast' , $wp_query->query_vars )
				&& !array_key_exists( 'external-playback' , $wp_query->query_vars )
			)
			{


				if ($stream)
					if (VWliveStreaming::timeTo($stream . '/updateThumb', 300, $options))
					{
						//set thumb
						$dir = $options['uploadsPath']. "/_snapshots";
						$thumbFilename = "$dir/$stream.jpg";

						$attach_id = get_post_thumbnail_id($postID);

						//update post thumb  if file exists and missing post thumb
						if ( file_exists($thumbFilename) && !get_post_thumbnail_id( $postID ))
						{
							$wp_filetype = wp_check_filetype(basename($thumbFilename), null );

							$attachment = array(
								'guid' => $thumbFilename,
								'post_mime_type' => $wp_filetype['type'],
								'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $thumbFilename, ".jpg" ) ),
								'post_content' => '',
								'post_status' => 'inherit'
							);

							$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
							set_post_thumbnail($postID, $attach_id);

							require_once( ABSPATH . 'wp-admin/includes/image.php' );
							$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
							wp_update_attachment_metadata( $attach_id, $attach_data );
						}

						//clean other media
						if ($postID && $attach_id) VWliveStreaming::delete_associated_media($postID, false, $attach_id);
					}


				$maxViewers =  get_post_meta($postID, 'maxViewers', true);
				if (!is_array($maxViewers)) if ($maxViewers>0)
					{
						$maxDate = (int) get_post_meta($postID, 'maxDate', true);
						$addCode .= __('Maximum viewers','livestreaming') . ': ' . $maxViewers;
						if ($maxDate) $addCode .= ' on ' . date("F j, Y, g:i a", $maxDate);
					}


				if (!$offline) $addCode .= VWliveStreaming::eventInfo($postID);

				//! show reviews
				if ($options['rateStarReview'])
				{
					//tab : reviews
					if (shortcode_exists("videowhisper_review"))
						$addCode .= '<h3>' . __('My Review', 'video-share-vod') . '</h3>' . do_shortcode('[videowhisper_review content_type="channel" post_id="' . $postID . '" content_id="' . $postID . '"]' );
					else $addCode .= 'Warning: shortcodes missing. Plugin <a target="_plugin" href="https://wordpress.org/plugins/rate-star-review/">Rate Star Review</a> should be installed and enabled or feature disabled.';

					if (shortcode_exists("videowhisper_reviews"))
						$addCode .= '<h3>' . __('Reviews', 'video-share-vod') . '</h3>' . do_shortcode('[videowhisper_reviews post_id="' . $postID . '"]' );

				}
			}


			return $addCode . $content;
		}

		function eventInfo($postID)
		{
			$eventTitle = get_post_meta($postID, 'eventTitle', true);
			if ($eventTitle)
			{
				$eventStart = get_post_meta($postID, 'eventStart', true);
				$eventEnd = get_post_meta($postID, 'eventEnd', true);
				$eventStartTime = get_post_meta($postID, 'eventStartTime', true);
				$eventEndTime = get_post_meta($postID, 'eventEndTime', true);

				$eventDescription= get_post_meta($postID, 'eventDescription', true);

				$showImage=get_post_meta( $postID, 'showImage', true );
				if ($showImage == 'event' || $showImage == 'all')
				{
					//get post thumb
					$attach_id = get_post_thumbnail_id($postID);
					if ($attach_id) $thumbFilename = get_attached_file($attach_id);

					if (file_exists($thumbFilename)) $snapshot = VWliveStreaming::path2url($thumbFilename);

					$eventCode .= '<IMG style="padding: 10px" SRC="'.$snapshot.'" ALIGN="LEFT">';

				}
				$eventCode .= '<BR>';
				$eventCode .= '<H3>' . $eventTitle. '</H3>';
				if ($eventStart||$eventStartTime) $eventCode .= 'Starts: '.$eventStart . ' ' . $eventStartTime;
				if ($eventEnd||$eventEndTime) $eventCode .= '<BR>Ends: '.$eventEnd . ' ' . $eventEndTime ;
				if ($eventDescription) $eventCode .= '<p>'.$eventDescription.'</p>';
				$eventCode .= '<BR style="clear:both">';
			} else return '';

			return $eventCode;

		}
		public static function pre_get_posts($query)
		{

			//add channels to post listings
			if(is_category() || is_tag())
			{
				$query_type = get_query_var('post_type');

				if($query_type)
					if (is_array($query_type))
					{
						if (in_array('post',$query_type) && !in_array('channel',$query_type))
							$query_type[] = 'channel';
						$query->set('post_type', $query_type);
					}
				//else  //default
				// $query_type = array('post', 'channel');

			}

			return $query;
		}

		function columns_head_channel($defaults) {
			$defaults['featured_image'] = 'Snapshot';
			$defaults['edate'] = 'Last Online';

			return $defaults;
		}

		function columns_register_sortable( $columns ) {
			$columns['edate'] = 'edate';

			return $columns;
		}


		function columns_content_channel($column_name, $post_id)
		{

			if ($column_name == 'featured_image')
			{

				global $wpdb;
				$postName = $wpdb->get_var( "SELECT post_title FROM $wpdb->posts WHERE ID = '" . $post_id . "' and post_type='channel' LIMIT 0,1" );

				if ($postName)
				{
					$options = get_option('VWliveStreamingOptions');
					$dir = $options['uploadsPath']. "/_thumbs";
					$thumbFilename = "$dir/" . $postName . ".jpg";

					$url = VWliveStreaming::roomURL($postName);

					if (file_exists($thumbFilename)) echo '<a href="' . $url . '"><IMG src="' . VWliveStreaming::path2url($thumbFilename) .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"></a>';

				}



			}

			if ($column_name == 'edate')
			{
				$edate = get_post_meta($post_id, 'edate', true);
				if ($edate)
				{
					echo ' ' . VWliveStreaming::format_age(time() - $edate);

				}


			}

		}

		public static function duration_column_orderby( $vars ) {
			if ( isset( $vars['orderby'] ) && 'edate' == $vars['orderby'] ) {
				$vars = array_merge( $vars, array(
						'meta_key' => 'edate',
						'orderby' => 'meta_value_num'
					) );
			}

			return $vars;
		}


		public static function query_vars( $query_vars ){
			// array of recognized query vars
			$query_vars[] = 'broadcast';
			$query_vars[] = 'flash-broadcast';
			$query_vars[] = 'flash-view';
			$query_vars[] = 'flash-video';
			$query_vars[] = 'video';
			$query_vars[] = 'hls';
			$query_vars[] = 'mpeg';
			$query_vars[] = 'webrtc-broadcast';
			$query_vars[] = 'webrtc-playback';
			$query_vars[] = 'html5-view';
			$query_vars[] = 'external';
			$query_vars[] = 'external-broadcast';
			$query_vars[] = 'external-playback';
			$query_vars[] = 'vwls_eula';
			$query_vars[] = 'vwls_crossdomain';
			$query_vars[] = 'vwls_fullchannel';

			return $query_vars;
		}

		function parse_request( &$wp )
		{
			if ( array_key_exists( 'vwls_eula', $wp->query_vars ) ) {
				$options = get_option('VWliveStreamingOptions');
				echo html_entity_decode(stripslashes($options['eula_txt']));
				exit();
			}

			if ( array_key_exists( 'vwls_crossdomain', $wp->query_vars ) ) {
				$options = get_option('VWliveStreamingOptions');
				echo html_entity_decode(stripslashes($options['crossdomain_xml']));
				exit();
			}

			if ( array_key_exists( 'vwls_fullchannel', $wp->query_vars ) ) {

				$stream = sanitize_file_name($wp->query_vars['vwls_fullchannel']);

				if (!$stream)
				{
					echo "No channel name provided!";
					exit;

				}

				echo '<title>' . $stream . '</title>
<body style="margin:0; padding:0; width:100%; height:100%">
';
				echo VWliveStreaming::flash_watch($stream);

				exit();
			}

		}

		// Register Custom Post Type
		function channel_post() {

			$options = get_option('VWliveStreamingOptions');
			if (!$options['postChannels']) return;

			//only if missing
			if (post_type_exists($options['custom_post'])) return;

			$labels = array(
				'name'                => _x( 'Channels', 'Post Type General Name', 'text_domain' ),
				'singular_name'       => _x( 'Channel', 'Post Type Singular Name', 'text_domain' ),
				'menu_name'           => __( 'Channels', 'text_domain' ),
				'parent_item_colon'   => __( 'Parent Channel:', 'text_domain' ),
				'all_items'           => __( 'All Channels', 'text_domain' ),
				'view_item'           => __( 'View Channel', 'text_domain' ),
				'add_new_item'        => __( 'Add New Channel', 'text_domain' ),
				'add_new'             => __( 'New Channel', 'text_domain' ),
				'edit_item'           => __( 'Edit Channel', 'text_domain' ),
				'update_item'         => __( 'Update Channel', 'text_domain' ),
				'search_items'        => __( 'Search Channels', 'text_domain' ),
				'not_found'           => __( 'No Channels found', 'text_domain' ),
				'not_found_in_trash'  => __( 'No Channels found in Trash', 'text_domain' ),
			);
			$args = array(
				'label'               => __( 'channel', 'text_domain' ),
				'description'         => __( 'Video Channels', 'text_domain' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', ),
				'taxonomies'          => array( 'category', 'post_tag' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 5,
				'can_export'          => true,
				'has_archive'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'menu_icon' => 'dashicons-video-alt',
				'capability_type'     => 'post',
				'capabilities' => array(
					'create_posts' => 'do_not_allow', // false < WP 4.5
					'edit_posts'   => 'edit_posts',
					'edit_post'   => 'edit_post',
					'edit_other_posts'   => 'edit_other_posts',
					'delete_post'        => 'delete_post',

				),
				'map_meta_cap' => true, // Set to `false`, if users are not allowed to edit/delete existing posts
			);
			register_post_type( $options['custom_post'], $args );

			add_rewrite_endpoint( 'broadcast', EP_ALL );
			add_rewrite_endpoint( 'flash-broadcast', EP_ALL );
			add_rewrite_endpoint( 'flash-view', EP_ALL );
			add_rewrite_endpoint( 'flash-video', EP_ALL );
			add_rewrite_endpoint( 'video', EP_ALL );
			add_rewrite_endpoint( 'hls', EP_ALL );
			add_rewrite_endpoint( 'mpeg', EP_ALL );
			add_rewrite_endpoint( 'external', EP_ALL );
			add_rewrite_endpoint( 'external-broadcast', EP_ALL );
			add_rewrite_endpoint( 'external-playback', EP_ALL );
			add_rewrite_endpoint( 'webrtc-broadcast', EP_ALL );
			add_rewrite_endpoint( 'webrtc-playback', EP_ALL );
			add_rewrite_endpoint( 'html5-view', EP_ALL );

			add_rewrite_rule( 'eula.txt$', 'index.php?vwls_eula=1', 'top' );
			add_rewrite_rule( 'crossdomain.xml$', 'index.php?vwls_crossdomain=1', 'top' );
			add_rewrite_rule( '^fullchannel/([\w]*)?', 'index.php?vwls_fullchannel=$matches[1]', 'top' );


			//flush_rewrite_rules();

		}


		//! Billing Integration

		function balance($userID)
		{
			//get current user balance

			if (!$userID) return 0;

			if (function_exists( 'mycred_get_users_cred')) return mycred_get_users_cred($userID);

			return 0;
		}

		function transaction($ref = "ppv_live_webcams", $user_id = 1, $amount = 0, $entry = "PPV Live Webcams transaction.", $ref_id = null, $data = null)
		{
			//ref = explanation ex. ppv_client_payment
			//entry = explanation ex. PPV client payment in room.
			//utils: ref_id (int|string|array) , data (int|string|array|object)

			if ($amount == 0) return; //nothing

			if ($amount>0)
			{
				if (function_exists('mycred_add')) mycred_add($ref, $user_id, $amount, $entry, $ref_id, $data);
			}
			else
			{
				if (function_exists('mycred_subtract')) mycred_subtract( $ref, $user_id, $amount, $entry, $ref_id, $data );
			}
		}

		function userPaidAccess($userID, $postID)
		{
			//checks if user has access to content that may be fore sale

			if (!class_exists( 'myCRED_Sell_Content_Module' ) ) return true; //sell content disabled

			$meta = get_post_meta($postID, 'myCRED_sell_content', true);

			if (!$meta) return true; // not for sale
			if (!$meta['price']) return true; //or no price

			if (!$userID) return false; //not logged in: did not purchase

			//check transaction log
			global $wpdb;

			$table_nameC = $wpdb->prefix . "myCRED_log";
			$isBuyer = $wpdb->get_col( $sql = "SELECT user_id FROM {$table_nameC} WHERE user_id={$userID} AND ref = 'buy_content' AND ref_id = {$postID} AND creds < 0" );
			if (!$isBuyer) return false; //did not purchase
			else return true;
		}

		//! Admin


		function admin_init()
		{
			add_meta_box(
				'vwls-nav-menus',
				'Channel Categories',
				array('VWliveStreaming', 'nav_menus'),
				'nav-menus',
				'side',
				'default');
		}

		function nav_menus()
		{

			//$object, $taxonomy

			global $nav_menu_selected_id;
			$taxonomy_name = 'category';

			// Paginate browsing for large numbers of objects.
			$per_page = 50;
			$pagenum = isset( $_REQUEST[$taxonomy_name . '-tab'] ) && isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
			$offset = 0 < $pagenum ? $per_page * ( $pagenum - 1 ) : 0;

			$args = array(
				'child_of' => 0,
				'exclude' => '',
				'hide_empty' => false,
				'hierarchical' => 1,
				'include' => '',
				'number' => $per_page,
				'offset' => $offset,
				'order' => 'ASC',
				'orderby' => 'name',
				'pad_counts' => false,
			);

			$terms = get_terms( $taxonomy_name, $args );

			if ( ! $terms || is_wp_error($terms) ) {
				echo '<p>' . __( 'No items.' ) . '</p>';
				return;
			}

			$num_pages = ceil( wp_count_terms( $taxonomy_name , array_merge( $args, array('number' => '', 'offset' => '') ) ) / $per_page );

			$page_links = paginate_links( array(
					'base' => add_query_arg(
						array(
							$taxonomy_name . '-tab' => 'all',
							'paged' => '%#%',
							'item-type' => 'taxonomy',
							'item-object' => $taxonomy_name,
						)
					),
					'format' => '',
					'prev_text' => __('&laquo;'),
					'next_text' => __('&raquo;'),
					'total' => $num_pages,
					'current' => $pagenum
				));

			$db_fields = false;
			if ( is_taxonomy_hierarchical( $taxonomy_name ) ) {
				$db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );
			}

			$walker = new Walker_Nav_Menu_Checklist( $db_fields );

			$current_tab = 'most-used';
			if ( isset( $_REQUEST[$taxonomy_name . '-tab'] ) && in_array( $_REQUEST[$taxonomy_name . '-tab'], array('all', 'most-used', 'search') ) ) {
				$current_tab = $_REQUEST[$taxonomy_name . '-tab'];
			}

			if ( ! empty( $_REQUEST['quick-search-taxonomy-' . $taxonomy_name] ) ) {
				$current_tab = 'search';
			}

			$removed_args = array(
				'action',
				'customlink-tab',
				'edit-menu-item',
				'menu-item',
				'page-tab',
				'_wpnonce',
			);

?>
	<div id="taxonomy-<?php echo $taxonomy_name; ?>" class="taxonomydiv">
		<ul id="taxonomy-<?php echo $taxonomy_name; ?>-tabs" class="taxonomy-tabs add-menu-item-tabs">
			<li <?php echo ( 'most-used' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-pop" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($taxonomy_name . '-tab', 'most-used', remove_query_arg($removed_args))); ?>#tabs-panel-<?php echo $taxonomy_name; ?>-pop">
					<?php _e( 'Most Used' ); ?>
				</a>
			</li>
			<li <?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-all" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($taxonomy_name . '-tab', 'all', remove_query_arg($removed_args))); ?>#tabs-panel-<?php echo $taxonomy_name; ?>-all">
					<?php _e( 'View All' ); ?>
				</a>
			</li>
			<li <?php echo ( 'search' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-search-taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>" href="<?php if ( $nav_menu_selected_id ) echo esc_url(add_query_arg($taxonomy_name . '-tab', 'search', remove_query_arg($removed_args))); ?>#tabs-panel-search-taxonomy-<?php echo $taxonomy_name; ?>">
					<?php _e( 'Search' ); ?>
				</a>
			</li>
		</ul><!-- .taxonomy-tabs -->

		<div id="tabs-panel-<?php echo $taxonomy_name; ?>-pop" class="tabs-panel <?php
			echo ( 'most-used' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
			?>">
			<ul id="<?php echo $taxonomy_name; ?>checklist-pop" class="categorychecklist form-no-clear" >
				<?php
			$popular_terms = get_terms( $taxonomy_name, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );
			$args['walker'] = $walker;
			echo walk_nav_menu_tree( array_map(array('VWliveStreaming', 'nav_menu_item'), $popular_terms), 0, (object) $args );
?>
			</ul>
		</div><!-- /.tabs-panel -->

		<div id="tabs-panel-<?php echo $taxonomy_name; ?>-all" class="tabs-panel tabs-panel-view-all <?php
			echo ( 'all' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
			?>">
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
			<ul id="<?php echo $taxonomy_name; ?>checklist" data-wp-lists="list:<?php echo $taxonomy_name?>" class="categorychecklist form-no-clear">
				<?php
			$args['walker'] = $walker;
			echo walk_nav_menu_tree( array_map(array('VWliveStreaming', 'nav_menu_item'), $terms), 0, (object) $args );
?>
			</ul>
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo $page_links; ?>
				</div>
			<?php endif; ?>
		</div><!-- /.tabs-panel -->

		<div class="tabs-panel <?php
			echo ( 'search' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
			?>" id="tabs-panel-search-taxonomy-<?php echo $taxonomy_name; ?>">
			<?php
			if ( isset( $_REQUEST['quick-search-taxonomy-' . $taxonomy_name] ) ) {
				$searched = esc_attr( $_REQUEST['quick-search-taxonomy-' . $taxonomy_name] );
				$search_results = get_terms( $taxonomy_name, array( 'name__like' => $searched, 'fields' => 'all', 'orderby' => 'count', 'order' => 'DESC', 'hierarchical' => false ) );
			} else {
				$searched = '';
				$search_results = array();
			}
?>
			<p class="quick-search-wrap">
				<input type="search" class="quick-search input-with-default-title" title="<?php esc_attr_e('Search'); ?>" value="<?php echo $searched; ?>" name="quick-search-taxonomy-<?php echo $taxonomy_name; ?>" />
				<span class="spinner"></span>
				<?php submit_button( __( 'Search' ), 'button-small quick-search-submit button-secondary hide-if-js', 'submit', false, array( 'id' => 'submit-quick-search-taxonomy-' . $taxonomy_name ) ); ?>
			</p>

			<ul id="<?php echo $taxonomy_name; ?>-search-checklist" data-wp-lists="list:<?php echo $taxonomy_name?>" class="categorychecklist form-no-clear">
			<?php if ( ! empty( $search_results ) && ! is_wp_error( $search_results ) ) : ?>
				<?php
				$args['walker'] = $walker;
			echo walk_nav_menu_tree( array_map(array('VWliveStreaming', 'nav_menu_item'), $search_results), 0, (object) $args );
?>
			<?php elseif ( is_wp_error( $search_results ) ) : ?>
				<li><?php echo $search_results->get_error_message(); ?></li>
			<?php elseif ( ! empty( $searched ) ) : ?>
				<li><?php _e('No results found.'); ?></li>
			<?php endif; ?>
			</ul>
		</div><!-- /.tabs-panel -->

		<p class="button-controls">
			<span class="list-controls">
				<a href="<?php
			echo esc_url(add_query_arg(
					array(
						$taxonomy_name . '-tab' => 'all',
						'selectall' => 1,
					),
					remove_query_arg($removed_args)
				));
			?>#taxonomy-<?php echo $taxonomy_name; ?>" class="select-all"><?php _e('Select All'); ?></a>
			</span>

			<span class="add-to-menu">
				<input type="submit"<?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu' ); ?>" name="add-taxonomy-menu-item" id="<?php echo esc_attr( 'submit-taxonomy-' . $taxonomy_name ); ?>" />
				<span class="spinner"></span>
			</span>
		</p>

	</div><!-- /.taxonomydiv -->
	        <?php
		}


		function single_template($single_template)
		{

			if (!is_single())  return $single_template;

			$options = get_option('VWliveStreamingOptions');
			//if (!$options['custom_post']) $options['custom_post'] = 'channel';

			$postID = get_the_ID();

			if ( get_post_type( $postID ) != $options['custom_post']) return $single_template;

			if ($options['postTemplate'] == '+plugin')
			{
				$single_template_new = dirname( __FILE__ ) . '/template-channel.php';
				if (file_exists($single_template_new)) return $single_template_new;
			}


			$single_template_new = get_stylesheet_directory() . '/' . $options['postTemplate'];

			if (file_exists($single_template_new)) return $single_template_new;
			else return $single_template;
		}

		function nav_menu_item( $menu_item )
		{

			$menu_item->ID = $menu_item->term_id;
			$menu_item->db_id = 0;
			$menu_item->menu_item_parent = 0;
			$menu_item->object_id = (int) $menu_item->term_id;
			$menu_item->post_parent = (int) $menu_item->parent;
			$menu_item->type = 'custom';

			$object = get_taxonomy( $menu_item->taxonomy );
			$menu_item->object = $object->name;
			$menu_item->type_label = $object->labels->singular_name;

			$menu_item->title = $menu_item->name;

			$options = get_option('VWliveStreamingOptions');
			if ($options['disablePageC']=='0')
			{
				$page_id = get_option("vwls_page_channels");
				$permalink = get_permalink( $page_id);
				$menu_item->url = add_query_arg(array('cid' => $menu_item->object_id, 'category' => $menu_item->name), $permalink);
			} else $menu_item->url = get_term_link( $menu_item, $menu_item->taxonomy ) . '?channels=1' ;

			$menu_item->target = '';
			$menu_item->attr_title = '';
			$menu_item->description = get_term_field( 'description', $menu_item->term_id, $menu_item->taxonomy );
			$menu_item->classes = array();
			$menu_item->xfn = '';

			/**
			 * @param object $menu_item The menu item object.
			 */
			return $menu_item;
		}


		function getDirectorySize($path)
		{
			$totalsize = 0;
			$totalcount = 0;
			$dircount = 0;

			if (!file_exists($path))
			{
				$total['size'] = $totalsize;
				$total['count'] = $totalcount;
				$total['dircount'] = $dircount;
				return $total;
			}

			if ($handle = opendir($path))
			{
				while (false !== ($file = readdir($handle)))
				{
					$nextpath = $path . '/' . $file;
					if ($file != '.' && $file != '..' && !is_link($nextpath))
					{
						if (is_dir($nextpath))
						{
							$dircount++;
							$result = VWliveStreaming::getDirectorySize($nextpath);
							$totalsize += $result['size'];
							$totalcount += $result['count'];
							$dircount += $result['dircount'];
						}
						elseif (is_file($nextpath))
						{
							$totalsize += filesize($nextpath);
							$totalcount++;
						}
					}
				}
			}
			closedir($handle);
			$total['size'] = $totalsize;
			$total['count'] = $totalcount;
			$total['dircount'] = $dircount;
			return $total;
		}

		function sizeFormat($size)
		{
			//echo $size;
			if($size<1024)
			{
				return $size." bytes";
			}
			else if($size<(1024*1024))
				{
					$size=round($size/1024,2);
					return $size." KB";
				}
			else if($size<(1024*1024*1024))
				{
					$size=round($size/(1024*1024),2);
					return $size." MB";
				}
			else
			{
				$size=round($size/(1024*1024*1024),2);
				return $size." GB";
			}

		}

		function admin_menu() {

			add_menu_page('Live Streaming', 'Live Streaming', 'manage_options', 'live-streaming', array('VWliveStreaming', 'options'), 'dashicons-video-alt',82);
			add_submenu_page("live-streaming", "Live Streaming", "Settings", 'manage_options', "live-streaming", array('VWliveStreaming', 'options'));
			add_submenu_page("live-streaming", "Live Streaming", "Statistics", 'manage_options', "live-streaming-stats", array('VWliveStreaming', 'adminStats'));
			add_submenu_page("live-streaming", "Live Streaming", "Live & Ban", 'manage_options', "live-streaming-live", array('VWliveStreaming', 'adminLive'));
			add_submenu_page("live-streaming", "Live Streaming", "Documentation", 'manage_options', "live-streaming-docs", array('VWliveStreaming', 'adminDocs'));

			//hide add submenu
			global $submenu;
			unset($submenu['edit.php?post_type=channel'][10]);
		}

		function admin_head() {
			if( get_post_type() != 'channel') return;

			//hide add button
			echo '<style type="text/css">
    #favorite-actions {display:none;}
    .add-new-h2{display:none;}
    .tablenav{display:none;}
    </style>';
		}


		function adminStats()
		{
			$options = get_option('VWliveStreamingOptions');

?>
	<h3>Channels Statistics</h3>
<?php



			if ($_GET['regenerateThumbs'])
			{
				$dir=$options['uploadsPath'];
				$dir .= "/_snapshots";
				echo '<div class="info">Regenerating thumbs for listed channels.</div>';
			}

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_sessions";
			$table_name2 = $wpdb->prefix . "vw_lwsessions";
			$table_name3 = $wpdb->prefix . "vw_lsrooms";

			$items =  $wpdb->get_results("SELECT * FROM `$table_name3` ORDER BY edate DESC LIMIT 0, 200");
			echo "<table class='wp-list-table widefat'><thead><tr><th>Channel</th><th>Last Access</th><th>Broadcast Time</th><th>Watch Time</th><th>Last Reset</th><th>Type</th><th>Logs</th></tr></thead>";



			if ($items) foreach ($items as $item)
				{
					echo "<tr><th>".$item->name;

					if ($_GET['regenerateThumbs'])
					{
						//
						$stream=$item->name;
						$filename = "$dir/$stream.jpg";

						if (file_exists($filename))
						{
							//generate thumb
							$thumbWidth = $options['thumbWidth'];
							$thumbHeight = $options['thumbHeight'];

							$src = imagecreatefromjpeg($filename);
							list($width, $height) = getimagesize($filename);
							$tmp = imagecreatetruecolor($thumbWidth, $thumbHeight);

							$dir = $options['uploadsPath']. "/_thumbs";
							if (!file_exists($dir)) mkdir($dir);

							$thumbFilename = "$dir/$stream.jpg";
							imagecopyresampled($tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
							imagejpeg($tmp, $thumbFilename, 95);

							$sql="UPDATE `$table_name3` set status='1' WHERE name ='$stream'";
							$wpdb->query($sql);


						} else
						{
							echo "<div class='warning'>Snapshot missing!</div>";
							$sql="UPDATE `$table_name3` set status='0' WHERE name ='$stream'";
							$wpdb->query($sql);

						}
					}

					global $wpdb;
					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item->name . "' and post_type='channel' LIMIT 0,1" );

					if (!$options['anyChannels'] && !$options['userChannels'])
					{
						if (!$postID)
						{
							$wpdb->query( "DELETE FROM `$table_name3` WHERE name ='".$item->name."'");
							echo "<br>DELETED: No channel post.";
						}
					}


					echo "</th><td>". VWliveStreaming::format_age(time() - $item->edate)."</td><td>". VWliveStreaming::format_time($item->btime) . "</td><td>". VWliveStreaming::format_time($item->wtime)."</td><td>" . VWliveStreaming::format_age(time() - $item->rdate)."</td><td>".($item->type>1?"Premium " . ($item->type-1) :"Standard")."</td>";

					//channel text logs
					$upload_c = VWliveStreaming::getDirectorySize($options['uploadsPath'] . '/'.$item->name);
					$upload_size = VWliveStreaming::sizeFormat($upload_c['size']);
					$logsurl = VWliveStreaming::path2url($options['uploadsPath'] . '/'.$item->name);

					echo '<td>'."<a target='_blank' href='$logsurl'>$upload_size ($upload_c[count] files)</a>".'</td></tr>';

					$broadcasting = $wpdb->get_results("SELECT * FROM `$table_name` WHERE room = '".$item->name."' ORDER BY edate DESC LIMIT 0, 100");
					if ($broadcasting)
						foreach ($broadcasting as $broadcaster)
						{
							echo "<tr><td colspan='7'> - " . $broadcaster->username . " Type: " . $broadcaster->type . " Status: " . $broadcaster->status . " Started: " . VWliveStreaming::format_age(time() -$broadcaster->sdate). "</td></tr>";
						}

					if ($postID)
					{
						$videoCodec = get_post_meta($postID, 'stream-codec-video', true);
						if ($videoCodec) echo "<tr><td colspan='7'> -  Video Codec: " . $videoCodec . " Audio Codec: " . get_post_meta($postID, 'stream-codec-audio', true) . " Detection time: " . VWliveStreaming::format_age(time() - get_post_meta($postID, 'stream-codec-detect', true)). "</td></tr>";
					}

					//

				}
			echo "</table>";
?>
<p>This page shows latest accessed channels (maximum 200).</p>
                <p>External players and encoders (if enabled) are not monitored or controlled by this plugin, unless special <a href="https://videowhisper.com/?p=RTMP-Session-Control">rtmp side session control</a> is available.</p>


                <?php

			//channel text logs
			$upload_c = VWliveStreaming::getDirectorySize($options['uploadsPath'] );
			$upload_size = VWliveStreaming::sizeFormat($upload_c['size']);
			$logsurl = VWliveStreaming::path2url($options['uploadsPath']);

			echo '<p>Total temporary file usage (logs, snapshots, session info): '." <a target='_blank' href='$logsurl'>$upload_size (in $upload_c[count] files and $upload_c[dircount] folders)</a>".'</p>';

		}

		function adminLive()
		{
			$options = get_option('VWliveStreamingOptions');

			$ban = sanitize_file_name($_GET['ban']);

			if ($ban)
			{
?>
<h3>Banning Channel</h3>
<?php
				global $wpdb;

				//delete post
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $ban . "' and post_type='channel' LIMIT 0,1" );
				if (!$postID) echo "<br>Channel post '$ban' not found!";
				else
				{
					wp_delete_post($postID, true);
					echo "<br>Channel post '$ban' was deleted.";
				}

				//delete room
				$table_name = $wpdb->prefix . "vw_lsrooms";
				$sql="DELETE FROM `$table_name` WHERE name = '$ban'";
				$wpdb->query($sql);
				echo "<br>Channel room '$ban' was deleted.";

				//ban
				$options['bannedNames'] .= ($options['bannedNames']?',':'') . $ban;
				update_option('VWliveStreamingOptions', $options);
				echo '<br>Current ban list: ' . $options['bannedNames'] . ' <a href="admin.php?page=live-streaming&tab=broadcaster" class="button button-primary">Edit</a>';
			}

			//broadcast link if allowed by settings
			if ($options['userChannels']||$options['anyChannels'])
			{

				$root_url = get_bloginfo( "url" ) . "/";
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

				$current_user = wp_get_current_user();

				if ($current_user->$userName) $username = $current_user->$userName;
				$username = sanitize_file_name($username);

				$broadcast_url = admin_url() . 'admin-ajax.php?action=vwls_broadcast&n=';

?>

<h3>Channel '<?php echo $username; ?>': Go Live</h3>
<ul>
<li>
<a href="<?php echo $broadcast_url . urlencode($username); ?>"><img src="<?php echo $root_url; ?>wp-content/plugins/videowhisper-live-streaming-integration/ls/templates/live/i_webcam.png"
align="absmiddle" border="0">Start Broadcasting</a>
</li>
<li>
<a href="<?php echo $root_url; ?>wp-content/plugins/videowhisper-live-streaming-integration/ls/channel.php?n=<?php echo $username; ?>"><img src="<?php echo $root_url;
				?>wp-content/plugins/videowhisper-live-streaming-integration/ls/templates/live/i_uvideo.png" align="absmiddle" border="0">View Channel</a>
</li>
</ul>
<p>To allow users to broadcast from frontend (as configured in settings), <a href='widgets.php'>enable the widget</a> and/or channel posts and frontend management page.
<br>On some templates/setups you also need to add the page to site menu.
</p>
<?php
			}
?>
<h3>Recent Channels</h3>
<?php

			echo do_shortcode('[videowhisper_channels ban="1" per_page="24"]');

		}

		function adminDocs()
		{
?>
<h2>VideoWhisper Live Streaming</h2>

<h3>Quick Setup Tutorial</h3>
<ol>
<li>Install and activate the VideoWhisper Live Streaming Integration plugin </li>
<li>From <a href="admin.php?page=live-streaming&tab=server">Live Streaming > Settings : Server</a>  in WP backend and configure settings (it's compulsory to fill a valid RTMP hosting address)</li>
<li>From <a href="options-permalink.php">Settings > Permalinks</a> enable a SEO friendly structure (ex. Post name)</li>
<li>From <a href="nav-menus.php">Appearance > Menus</a> add Channels and Broadcast Live pages to main site menu.
<ul>
	<li>Users can setup their channels and start broadcast from Broadcast Live page:
	<br><?php echo get_permalink(get_option("vwls_page_manage"))?></li>
	<li>After broadcasting, channels show in Channels list:
	<br><?php echo get_permalink(get_option("vwls_page_channels"))?></li>
</ul>
</li>
<li>Install and enable a <a href="admin.php?page=live-streaming&tab=billing">billing plugin</a> to allow owners to sell channel access</li>
<li>Install and enable the <a href="https://videosharevod.com/">VideoShareVOD</a> plugin to enable video broadcast archiving, video publishing, management</li>
</ol>

<h3>ShortCodes</h3>
<ul>
  <li><h4>[videowhisper_watch channel=&quot;Channel Name&quot; width=&quot;100%&quot; height=&quot;100%&quot; flash=&quot;0&quot;]</h4>
    Displays watch interface with video and discussion. If iOS is detected it shows HLS instead. Container style can be configured from plugin settings. Auto detection unless flash="1" forced.</li>

  <li><h4>[videowhisper_htmlchat_playback channel=&quot;Channel Name&quot; 	post_id=&quot;Channel Post ID&quot; videowidth=&quot;480px&quot; videoheight=&quot;360px&quot;]</h4>
	  Displays html chat with HTML5 live stream.</li>

  <li><h4>[videowhisper_video channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot; html5=&quot;auto&quot;]</h4>
  Displays video only interface. Depending on device and settings will show HTML5. Set html5=&quot;always&quot; to force html5 video.</li>

  <li><h4>[videowhisper_hls channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot;]</h4>
  Displays HTML5 HLS (HTTP Live Streaming) video interface. Shows istead of watch and video interfaces if iOS is detected. Stream must be published in compatible format (H264,AAC) or transcoding must be enabled and active for stream to show.</li>

 <li><h4>[videowhisper_mpeg channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot;]</h4>
  Displays HTML5 MPEG DASH video interface. Shows instead of watch and video interfaces if Android is detected. Stream must be published in compatible format (H264,AAC) or transcoding must be enabled and active for stream to show.</li>

  <li><h4>[videowhisper_webrtc_playback channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot;]</h4>
  Displays WebRTC video playback interface.</li>

  <li>
    <h4>[videowhisper_broadcast channel=&quot;Channel Name&quot; flash=&quot;0&quot;]</h4>
    Shows broadcasting interface. If not provided, channel name is detected depending on settings, post type, user. Only owner can access for channel posts. Auto detection unless flash="1" forced.
   </li>

  <li>
    <h4>[videowhisper_webrtc_broadcast channel=&quot;Channel Name&quot;]</h4>
    Shows WebRTC broadcasting interface. If not provided, channel name is detected depending on settings, post type, user. Only owner can access for channel posts.
   </li>

    <li>
    <h4>[videowhisper_external channel=&quot;Channel Name&quot;] [videowhisper_external_broadcast channel=&quot;Channel Name&quot;][videowhisper_external_playback channel=&quot;Channel Name&quot;]</h4>
    Shows settings for broadcasting/playback with external applications. Channel name is detected depending on settings, post type, user. Only owner can access for channel posts.
   </li>
     <li>
	     <h4>[videowhisper_channels per_page="8" perrow="" order_by="edate" category_id="" select_category="1" select_order="1" select_page="1" include_css="1" ban="0" id=""]</h4>
	     Lists channels with snapshots, ordered by most recent online and with pagination.
     </li>
     <li>
	     <h4>[videowhisper_livesnapshots]</h4>
	     Older shortcode for backward compatibility. Displays full size snapshots of online channels. No pagination.
     </li>
     <li>
     <h4>
     [videowhisper_channel_manage]
     </h4>
	     Displays channel management page.
     </li>
</ul>
<h3>Documentation, Support, Customizations</h3>
<ul>
<li>Home Page and Documentation: <a href="https://videowhisper.com/?p=WordPress+Live+Streaming">VideoWhisper - WordPress Live Streaming</a></li>
<li>WordPress Plugin Page: <a href="https://wordpress.org/plugins/videowhisper-live-streaming-integration/">VideoWhisper Live Streaming Integration</a></li>
<li>Contact Page: <a href="https://videowhisper.com/tickets_submit.php">Contact VideoWhisper</a></li>
</ul>
<p>After ordering solution and setting up existing editions, VideoWhisper.com developers can customize these for additional fees depending on exact requirements.</p>
  <?php
		}


		//! Channel Features List

		function roomFeatures()
		{
			return array(
				'roomTags' => array(
					'name'=>'Room Tags',
					'description' =>'Can specify room tags.',
					'installed' => 1,
					'default' => 'All'),
				'accessPassword' => array(
					'name'=>'Access Password',
					'description' =>'Can specify a password to protect channel access.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator, Editor'),
				'accessList' => array(
					'name'=>'Access List',
					'description' =>'Channel owner can specify list of user logins, roles, emails that can access the channel.',
					'installed' => 1,
					'default' => 'None'),
				'accessPrice' => array(
					'name'=>'Access Price',
					'description' =>'Can setup a price per channel. Requires myCRED plugin installed and integration enabled from Billing.',
					'type' => 'number',
					'installed' => 1,
					'default' => 'None'),
				'chatList' => array(
					'name'=>'Chat List',
					'description' =>'Channel owner can specify list of user logins, roles, emails that can access the public chat.',
					'installed' => 1,
					'default' => 'None'),
				'writeList' => array(
					'name'=>'Chat Write List',
					'description' =>'Channel owner can specify list of user logins, roles, emails that can write in public chat.',
					'installed' => 1,
					'default' => 'None'),
				'participantsList' => array(
					'name'=>'Participants List',
					'description' =>'Channel owner can specify list of user logins, roles, emails that can view participants list.',
					'installed' => 1,
					'default' => 'None'),
				'privateChatList' => array(
					'name'=>'Private Chat List',
					'description' =>'Channel owner can specify list of user logins, roles, emails that can initiate private chat.',
					'installed' => 1,
					'default' => 'None'),
				'uploadPicture' => array(
					'name'=>'Upload Picture',
					'description' =>'Upload channel picture.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator, Editor, Subscriber'),
				'eventDetails' => array(
					'name'=>'Event Details',
					'description' =>'Specify event title, start, end, description to show when show is offline.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator, Editor, Subscriber'),
				'logoHide' => array(
					'name'=>'Hide Logo',
					'description' =>'Hides logo from channel.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator, Editor'),
				'logoCustom' => array(
					'name'=>'Custom Logo',
					'description' =>'Can setup a custom logo. Overrides hide logo feature.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator'),
				'adsHide' => array(
					'name'=>'Hide Ads',
					'description' =>'Hides ads from channel.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator, Editor'),
				'ipCameras' => array(
					'name'=>'IP Cameras',
					'description' =>'Can configure re-streaming, including for IP cameras.',
					'installed' => 1,
					'default' => 'None'),
				'schedulePlaylists' => array(
					'name'=>'Playlist Scheduler',
					'description' =>'Can schedule channel playlist from VideoShareVOD videos.',
					'installed' => 1,
					'default' => 'None'),
				'adsCustom' => array(
					'name'=>'Custom Ads',
					'description' =>'Can setup a custom ad server. Overrides hide ads feature.',
					'installed' => 1,
					'default' => 'None'),
				'transcode' => array(
					'name'=>'Transcode',
					'description' =>'Enable transcoding for user channels.',
					'installed' => 1,
					'default' => 'Super Admin, Administrator, Editor'),
				'privateList' => array(
					'name'=>'Private Channels',
					'description' =>'Hide channels from public listings. Can be accessed by channel links.',
					'installed' => 0),
				'privateChat' => array(
					'name'=>'Private Chat',
					'description' =>'Disable chat from site watch interface.',
					'installed' => 0),
				'privateVideos' => array(
					'name'=>'Private Videos',
					'description' =>'Channel videos do not show in public listings. Only show on channel page.',
					'installed' => 0),
				'hiddenVideos' => array(
					'name'=>'Hidden Videos',
					'description' =>'Channel videos do not show in public or channel listings. Only owner can browse.',
					'installed' => 0),
			);
		}

		//! Settings
		function adminOptionsDefault()
		{
			$root_url = get_bloginfo( "url" ) . "/";
			$upload_dir = wp_upload_dir();

			return array(
				'rateStarReview' => '1',

				'userName' => 'user_nicename',
				'userPicture' => 'avatar',
				'profilePrefix' => $root_url.'author/',
				'profilePrefixChannel' => $root_url.'channel/',
				'loginLogo' => plugin_dir_url(__FILE__) .'login-logo.png',

				'postChannels' => '1',
				'userChannels' => '0',
				'anyChannels' => '0',

				'custom_post' => 'channel',
				'custom_post_video' => 'video',

				'postTemplate' => '+plugin',
				'channelUrl' => 'post',

				'disablePage' => '0',
				'disablePageC' => '0',
				'thumbWidth' => '240',
				'thumbHeight' => '180',
				'perPage' =>'6',

				'postName' => 'custom',


				'rtmp_server' => 'rtmp://localhost/videowhisper',
				'rtmp_restrict_ip'=>'',
				'webStatus'=> 'auto',

				'rtmp_amf' => 'AMF3',
				'httpstreamer' => 'https://[your-server]:1935/videowhisper-x/',
				'rtsp_server' => 'rtsp://[your-server]/videowhisper-x', //access WebRTC stream with sound from here
				'rtsp_server_publish' => 'rtsp://[user:password@][your-server]/videowhisper-x', //publish WebRTC stream here
				'ffmpegPath' => '/usr/local/bin/ffmpeg',
				'ffmpegTranscode' => '-c:v copy -c:a libfdk_aac -b:a 96',
				'ffmpegTranscodeRTC' => '-c:v libx264 -profile:v baseline -c:a libopus', //transcode for RTC like ffmpeg -re -i source -acodec opus -vcodec libx264 -vprofile baseline -f rtsp rtsp://<wowza-instance>/rtsp-to-webrtc/my-stream

				'ffmpegTimeout' => '60',

				'streamsPath' => '/home/account/public_html/streams',

				'webrtc' =>'0', //enable webrtc
				'wsURLWebRTC' => 'wss://[wowza-server-with-ssl]:[port]/webrtc-session.json', // Wowza WebRTC WebSocket URL (wss with SSL certificate)
				'applicationWebRTC' => '[application-name]', // Wowza Application Name (configured or WebRTC usage)

				'webrtcVideoCodec' =>'42e01f',
				'webrtcAudioCodec' =>'opus',

				'webrtcVideoBitrate' => 400,

				'ipcams' =>'0',
				'playlists' =>'0',

				'canBroadcast' => 'members',
				'broadcastList' => 'Super Admin, Administrator, Editor, Author',
				'maxChannels' => '3',
				'externalKeys' => '1',
				'externalKeysTranscoder' => '1',
				'rtmpStatus' => '0',


				'canWatch' => 'all',
				'watchList' => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber',
				'onlyVideo' => '0',
				'noEmbeds' => '0',

				'userWatchLimit' => '1',
				'userWatchInterval' => '2592000',
				'userWatchLimitDefault' => '108000',
				'userWatchLimits' => '',
				'userWatchLimitsConfig' => 'Administrator = 0
Super Admin = 0
Editor = 72000
Subscriber = 36000',
				'watchRoleParameters' => '',
				'watchRoleParametersConfig' =>'[disableChat]
Administrator = 0
Editor = 0

[disableUsers]
Administrator = 0
Editor = 0

[disableVideo]
Administrator = 0
Editor = 0

[writeText]
Administrator = 1
Editor = 1

[privateTextchat]
Administrator = 1
Editor = 1
				',

				'broadcasterRedirect' => '0',

				'premiumList' => 'Super Admin, Administrator, Editor, Author',
				'canWatchPremium' => 'all',
				'watchListPremium' => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber',

				'premiumLevelsNumber' =>'2',
				'premiumLevels' =>'',

				// 'pLogo' => '1',
				'broadcastTime' => '600',
				'watchTime' => '3000',
				'pBroadcastTime' => '6000',
				'pWatchTime' => '30000',
				'timeReset' => '30',
				'bannedNames' => 'bann1, bann2',

				'camResolution' => '640x480',
				'camFPS' => '15',

				'camBandwidth' => '60000',
				'camMaxBandwidth' => '100000',
				'pCamBandwidth' => '75000',
				'pCamMaxBandwidth' => '200000',

				'transcoding' => '0',
				'transcodingAuto' => '2',
				'transcodingManual' => '0',
				'transcodingWarning' => '2',

				'detect_hls' => 'ios',
				'detect_mpeg' => 'android',

				'videoCodec'=>'H264',
				'codecProfile' => 'baseline',
				'codecLevel' => '3.1',

				'soundCodec'=> 'Nellymoser',
				'soundQuality' => '9',
				'micRate' => '22',

				//! mobile settings
				'camResolutionMobile' => '480x360',
				'camFPSMobile' => '15',

				'camBandwidthMobile' => '40000',

				'videoCodecMobile'=>'H263',
				'codecProfileMobile' => 'baseline',
				'codecLevelMobile' => '3.1',

				'soundCodecMobile'=> 'Speex',
				'soundQualityMobile' => '9',
				'micRateMobile' => '22',
				//mobile:end

				'broadcastTime' => '600',
				'watchTime' => '3000',
				'pBroadcastTime' => '6000',
				'pWatchTime' => '30000',
				'timeReset' => '30',
				'bannedNames' => 'bann1, bann2',

				'onlineExpiration0' =>'310',
				'onlineExpiration1' =>'40',
				'parameters' => '&bufferLive=1&bufferFull=1&showCredit=1&disconnectOnTimeout=1&offlineMessage=Channel+Offline&disableVideo=0&fillWindow=0&adsTimeout=15000&externalInterval=17000&statusInterval=59000&loaderProgress=1',
				'parametersBroadcaster' => '&bufferLive=2&bufferFull=2&showCamSettings=1&advancedCamSettings=1&configureSource=1&generateSnapshots=1&snapshotsTime=60000&room_limit=500&showTimer=1&showCredit=1&disconnectOnTimeout=1&externalInterval=11000&statusInterval=29000&loaderProgress=1&selectCam=1&selectMic=1',
				'layoutCode' => 'id=0&label=Video&x=10&y=45&width=325&height=298&resize=true&move=true; id=1&label=Chat&x=340&y=45&width=293&height=298&resize=true&move=true; id=2&label=Users&x=638&y=45&width=172&height=298&resize=true&move=true',
				'layoutCodeBroadcaster' => 'id=0&label=Webcam&x=10&y=40&width=242&height=235&resize=true&move=true; id=1&label=Chat&x=260&y=40&width=340&height=235&resize=true&move=true; id=2&label=Users&x=610&y=40&width=180&height=235&resize=true&move=true',
				'watchStyle' => 'width: 100%;
height: 400px;
border: solid 3px #999;',

				'overLogo' => $root_url .'wp-content/plugins/videowhisper-live-streaming-integration/ls/logo.png',
				'loaderImage' => '',

				'overLink' => 'https://videowhisper.com',
				'adServer' => 'ads',
				'adsInterval' => '20000',
				'adsCode' => '<B>Sample Ad</B><BR>Edit ads from plugin settings. Also edit  Ads Interval in milliseconds (0 to disable ad calls).  Also see <a href="http://www.adinchat.com" target="_blank"><U><B>AD in Chat</B></U></a> compatible ad management server for setting up ad rotation. Ads do not show on premium channels.',

				'cssCode' =>'title {
    font-family: Arial, Helvetica, _sans;
    font-size: 11;
    font-weight: bold;
    color: #FFFFFF;
    letter-spacing: 1;
    text-decoration: none;
}

story {
    font-family: Verdana, Arial, Helvetica, _sans;
    font-size: 14;
    font-weight: normal;
    color: #FFFFFF;
}',
				'translationCode' => '<t text="Video is Disabled" translation="Video is Disabled"/>
<t text="Bold" translation="Bold"/>
<t text="Sound is Enabled" translation="Sound is Enabled"/>
<t text="Publish a video stream using the settings below without any spaces." translation="Publish a video stream using the settings below without any spaces."/>
<t text="Click Preview for Streaming Settings" translation="Click Preview for Streaming Settings"/>
<t text="DVD NTSC" translation="DVD NTSC"/>
<t text="DVD PAL" translation="DVD PAL"/>
<t text="Video Source" translation="Video Source"/>
<t text="Send" translation="Send"/>
<t text="Cinema" translation="Cinema"/>
<t text="Update Show Title" translation="Update Show Title"/>
<t text="Public Channel: Click to Copy" translation="Public Channel: Click to Copy"/>
<t text="Channel Link" translation="Channel Link"/>
<t text="Kick" translation="Kick"/>
<t text="Embed Channel HTML Code" translation="Embed Channel HTML Code"/>
<t text="Open In Browser" translation="Open In Browser"/>
<t text="Embed Video HTML Code" translation="Embed Video HTML Code"/>
<t text="Snapshot Image Link" translation="Snapshot Image Link"/>
<t text="SD" translation="SD"/>
<t text="External Encoder" translation="External Encoder"/>
<t text="Source" translation="Source"/>
<t text="Very Low" translation="Very Low"/>
<t text="Low" translation="Low"/>
<t text="HDTV" translation="HDTV"/>
<t text="Webcam" translation="Webcam"/>
<t text="Resolution" translation="Resolution"/>
<t text="Emoticons" translation="Emoticons"/>
<t text="HDCAM" translation="HDCAM"/>
<t text="FullHD" translation="FullHD"/>
<t text="Preview Shows as Compressed" translation="Preview Shows as Compressed"/>
<t text="Rate" translation="Rate"/>
<t text="Very Good" translation="Very Good"/>
<t text="Preview Shows as Captured" translation="Preview Shows as Captured"/>
<t text="Framerate" translation="Framerate"/>
<t text="High" translation="High"/>
<t text="Toggle Preview Compression" translation="Toggle Preview Compression"/>
<t text="Latency" translation="Latency"/>
<t text="CD" translation="CD"/>
<t text="Your connection performance:" translation="Your connection performance:"/>
<t text="Small Delay" translation="Small Delay"/>
<t text="Sound Effects" translation="Sound Effects"/>
<t text="Username" translation="Nickname"/>
<t text="Medium Delay" translation="Medium Delay"/>
<t text="Toggle Microphone" translation="Toggle Microphone"/>
<t text="Video is Enabled" translation="Video is Enabled"/>
<t text="Radio" translation="Radio"/>
<t text="Talk" translation="Talk"/>
<t text="Viewers" translation="Viewers"/>
<t text="Toggle External Encoder" translation="Toggle External Encoder"/>
<t text="Sound is Disabled" translation="Sound is Disabled"/>
<t text="Sound Fx" translation="Sound Effects"/>
<t text="Good" translation="Good"/>
<t text="Toggle Webcam" translation="Toggle Webcam"/>
<t text="Bandwidth" translation="Bandwidth"/>
<t text="Underline" translation="Underline"/>
<t text="Select Microphone Device" translation="Select Microphone Device"/>
<t text="Italic" translation="Italic"/>
<t text="Select Webcam Device" translation="Select Webcam Device"/>
<t text="Big Delay" translation="Big Delay"/>
<t text="Excellent" translation="Excellent"/>
<t text="Apply Settings" translation="Apply Settings"/>
<t text="Very High" translation="Very High"/>',

				'customCSS' => <<<HTMLCODE
<style type="text/css">

.videowhisperChannel
{
position: relative;
display:inline-block;

	border:1px solid #aaa;
	background-color:#777;
	padding: 0px;
	margin: 2px;

	width: 240px;
    height: 180px;
}

.videowhisperChannel:hover {
	border:1px solid #fff;
}

.videowhisperChannel IMG
{
padding: 0px;
margin: 0px;
border: 0px;
}

.videowhisperTitle
{
position: absolute;
top:5px;
left:5px;
font-size: 20px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperTime
{
position: absolute;
bottom:5px;
left:5px;
font-size: 15px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperChannelRating
{
position: absolute;
bottom: 5px;
right:5px;
font-size: 15px;
color: #FFF;
text-shadow:1px 1px 1px #333;
z-index: 10;
}

</style>

HTMLCODE
				,
				'uploadsPath' => $upload_dir['basedir'] . '/vwls',

				'tokenKey' => 'VideoWhisper',
				'webKey' => 'VideoWhisper',
				'manualArchiving' => '',

				'serverRTMFP' => 'rtmfp://stratus.adobe.com/f1533cc06e4de4b56399b10d-1a624022ff71/',
				'p2pGroup' => 'VideoWhisper',
				'supportRTMP' => '1',
				'supportP2P' => '0',
				'alwaysRTMP' => '1',
				'alwaysP2P' => '0',
				'alwaysWatch' => '1',
				'disableBandwidthDetection' => '1',
				'mycred' => '1',
				'tips' => 1,
				'tipRatio' => '0.90',
				'tipOptions' => '<tips>
<tip amount="1" label="1$ Like!" note="Like!" sound="coins1.mp3" />
<tip amount="2" label="2$ Big Like!" note="Big Like!" sound="coins2.mp3" />
<tip amount="5" label="5$ Great!" note="Great!" sound="coins2.mp3" />
<tip amount="10" label="10$ Excellent!" note="Excellent!" sound="register.mp3"/>
<tip amount="20" label="20$ Ultimate!" note="Ultimate!" sound="register.mp3"/>
</tips>',
				'eula_txt' =>'The following Terms of Use (the "Terms") is a binding agreement between you, either an individual subscriber, customer, member, or user of at least 18 years of age or a single entity ("you", or collectively "Users") and owners of this application, service site and networks that allow for the distribution and reception of video, audio, chat and other content (the "Service").

By accessing the Service and/or by clicking "I agree", you agree to be bound by these Terms of Use. You hereby represent and warrant to us that you are at least eighteen (18) years of age or and otherwise capable of entering into and performing legal agreements, and that you agree to be bound by the following Terms and Conditions. If you use the Service on behalf of a business, you hereby represent to us that you have the authority to bind that business and your acceptance of these Terms of Use will be treated as acceptance by that business. In that event, "you" and "your" will refer to that business in these Terms of Use.

Prohibited Conduct

The Services may include interactive areas or services (" Interactive Areas ") in which you or other users may create, post or store content, messages, materials, data, information, text, music, sound, photos, video, graphics, applications, code or other items or materials on the Services ("User Content" and collectively with Broadcaster Content, " Content "). You are solely responsible for your use of such Interactive Areas and use them at your own risk. BY USING THE SERVICE, INCLUDING THE INTERACTIVE AREAS, YOU AGREE NOT TO violate any law, contract, intellectual property or other third-party right or commit a tort, and that you are solely responsible for your conduct while on the Service. You agree that you will abide by these Terms of Service and will not:

use the Service for any purposes other than to disseminate or receive original or appropriately licensed content and/or to access the Service as such services are offered by us;

rent, lease, loan, sell, resell, sublicense, distribute or otherwise transfer the licenses granted herein;

post, upload, or distribute any defamatory, libelous, or inaccurate Content;

impersonate any person or entity, falsely claim an affiliation with any person or entity, or access the Service accounts of others without permission, forge another persons digital signature, misrepresent the source, identity, or content of information transmitted via the Service, or perform any other similar fraudulent activity;

delete the copyright or other proprietary rights notices on the Service or Content;

make unsolicited offers, advertisements, proposals, or send junk mail or spam to other Users of the Service, including, without limitation, unsolicited advertising, promotional materials, or other solicitation material, bulk mailing of commercial advertising, chain mail, informational announcements, charity requests, petitions for signatures, or any of the foregoing related to promotional giveaways (such as raffles and contests), and other similar activities;

harvest or collect the email addresses or other contact information of other users from the Service for the purpose of sending spam or other commercial messages;

use the Service for any illegal purpose, or in violation of any local, state, national, or international law, including, without limitation, laws governing intellectual property and other proprietary rights, and data protection and privacy;

defame, harass, abuse, threaten or defraud Users of the Service, or collect, or attempt to collect, personal information about Users or third parties without their consent;

remove, circumvent, disable, damage or otherwise interfere with security-related features of the Service or Content, features that prevent or restrict use or copying of any content accessible through the Service, or features that enforce limitations on the use of the Service or Content;

reverse engineer, decompile, disassemble or otherwise attempt to discover the source code of the Service or any part thereof, except and only to the extent that such activity is expressly permitted by applicable law notwithstanding this limitation;

modify, adapt, translate or create derivative works based upon the Service or any part thereof, except and only to the extent that such activity is expressly permitted by applicable law notwithstanding this limitation;

intentionally interfere with or damage operation of the Service or any user enjoyment of them, by any means, including uploading or otherwise disseminating viruses, adware, spyware, worms, or other malicious code;

relay email from a third party mail servers without the permission of that third party;

use any robot, spider, scraper, crawler or other automated means to access the Service for any purpose or bypass any measures we may use to prevent or restrict access to the Service;

manipulate identifiers in order to disguise the origin of any Content transmitted through the Service;

interfere with or disrupt the Service or servers or networks connected to the Service, or disobey any requirements, procedures, policies or regulations of networks connected to the Service;use the Service in any manner that could interfere with, disrupt, negatively affect or inhibit other users from fully enjoying the Service, or that could damage, disable, overburden or impair the functioning of the Service in any manner;

use or attempt to use another user account without authorization from such user and us;

attempt to circumvent any content filtering techniques we employ, or attempt to access any service or area of the Service that you are not authorized to access; or

attempt to indicate in any manner that you have a relationship with us or that we have endorsed you or any products or services for any purpose.

Further, BY USING THE SERVICE, INCLUDING THE INTERACTIVE AREAS YOU AGREE NOT TO post, upload to, transmit, distribute, store, create or otherwise publish through the Service any of the following:

Content that would constitute, encourage or provide instructions for a criminal offense, violate the rights of any party, or that would otherwise create liability or violate any local, state, national or international law or regulation;

Content that may infringe any patent, trademark, trade secret, copyright or other intellectual or proprietary right of any party. By posting any Content, you represent and warrant that you have the lawful right to distribute and reproduce such Content;

Content that is unlawful, libelous, defamatory, obscene, pornographic, indecent, lewd, suggestive, harassing, threatening, invasive of privacy or publicity rights, abusive, inflammatory, fraudulent or otherwise objectionable;

Content that impersonates any person or entity or otherwise misrepresents your affiliation with a person or entity;

private information of any third party, including, without limitation, addresses, phone numbers, email addresses, Social Security numbers and credit card numbers;

viruses, corrupted data or other harmful, disruptive or destructive files; and

Content that, in the sole judgment of Service moderators, is objectionable or which restricts or inhibits any other person from using or enjoying the Interactive Areas or the Service, or which may expose us or our users to any harm or liability of any type.

Service takes no responsibility and assumes no liability for any Content posted, stored or uploaded by you or any third party, or for any loss or damage thereto, nor is liable for any mistakes, defamation, slander, libel, omissions, falsehoods, obscenity, pornography or profanity you may encounter. Your use of the Service is at your own risk. Enforcement of the user content or conduct rules set forth in these Terms of Service is solely at Service discretion, and failure to enforce such rules in some instances does not constitute a waiver of our right to enforce such rules in other instances. In addition, these rules do not create any private right of action on the part of any third party or any reasonable expectation that the Service will not contain any content that is prohibited by such rules. As a provider of interactive services, Service is not liable for any statements, representations or Content provided by our users in any public forum, personal home page or other Interactive Area. Service does not endorse any Content or any opinion, recommendation or advice expressed therein, and Service expressly disclaims any and all liability in connection with Content. Although Service has no obligation to screen, edit or monitor any of the Content posted in any Interactive Area, Service reserves the right, and has absolute discretion, to remove, screen or edit any Content posted or stored on the Service at any time and for any reason without notice, and you are solely responsible for creating backup copies of and replacing any Content you post or store on the Service at your sole cost and expense. Any use of the Interactive Areas or other portions of the Service in violation of the foregoing violates these Terms and may result in, among other things, termination or suspension of your rights to use the Interactive Areas and/or the Service.
',
				'crossdomain_xml' =>'<cross-domain-policy>
<allow-access-from domain="*"/>
<site-control permitted-cross-domain-policies="master-only"/>
</cross-domain-policy>',
				'videowhisper' => 0
			);

		}

		function setupOptions()
		{

			$adminOptions = VWliveStreaming::adminOptionsDefault();

			$features = VWliveStreaming::roomFeatures();
			foreach ($features as $key=>$feature) if ($feature['installed'])  $adminOptions[$key] = $feature['default'];

				$options = get_option('VWliveStreamingOptions');
			if (!empty($options)) {
				foreach ($options as $key => $option)
					$adminOptions[$key] = $option;
			}
			update_option('VWliveStreamingOptions', $adminOptions);


			return $adminOptions;
		}



		function options()
		{
			$options = VWliveStreaming::setupOptions();
			$optionsDefault = VWliveStreaming::adminOptionsDefault();

			if (isset($_POST))
			{
				foreach ($options as $key => $value)
					if (isset($_POST[$key])) $options[$key] = $_POST[$key];

					//config parsing
					if (isset($_POST['userWatchLimitsConfig']))
						$options['userWatchLimits'] = parse_ini_string(sanitize_textarea_field($_POST['userWatchLimitsConfig']));

					if (isset($_POST['watchRoleParametersConfig']))
						$options['watchRoleParameters'] = parse_ini_string(sanitize_textarea_field($_POST['watchRoleParametersConfig']), true);


					update_option('VWliveStreamingOptions', $options);
			}

			$page_id = get_option("vwls_page_manage");
			if ($page_id != '-1' && $options['disablePage']!='0') VWliveStreaming::deletePages();

			$page_idC = get_option("vwls_page_channels");
			if ($page_idC != '-1' && $options['disablePageC']!='0') VWliveStreaming::deletePages();


			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'support';
?>


<div class="wrap">
<?php screen_icon(); ?>
<h2>VideoWhisper Live Streaming Settings</h2>

<h2 class="nav-tab-wrapper">
	<a href="admin.php?page=live-streaming&tab=server" class="nav-tab <?php echo $active_tab=='server'?'nav-tab-active':'';?>">Server</a>
	<a href="admin.php?page=live-streaming&tab=general" class="nav-tab <?php echo $active_tab=='general'?'nav-tab-active':'';?>">Integration</a>
    <a href="admin.php?page=live-streaming&tab=broadcaster" class="nav-tab <?php echo $active_tab=='broadcaster'?'nav-tab-active':'';?>">Broadcaster</a>
    <a href="admin.php?page=live-streaming&tab=broadcast-flash" class="nav-tab <?php echo $active_tab=='broadcast-flash'?'nav-tab-active':'';?>">Broadcast Web</a>
    <a href="admin.php?page=live-streaming&tab=premium" class="nav-tab <?php echo $active_tab=='premium'?'nav-tab-active':'';?>">Premium Channels</a>
    <a href="admin.php?page=live-streaming&tab=features" class="nav-tab <?php echo $active_tab=='features'?'nav-tab-active':'';?>">Channel Features</a>
    <a href="admin.php?page=live-streaming&tab=playlists" class="nav-tab <?php echo $active_tab=='playlists'?'nav-tab-active':'';?>">Playlists Scheduler</a>
    <a href="admin.php?page=live-streaming&tab=watcher" class="nav-tab <?php echo $active_tab=='watcher'?'nav-tab-active':'';?>">Watch Players</a>
    <a href="admin.php?page=live-streaming&tab=watch-limit" class="nav-tab <?php echo $active_tab=='watch-limit'?'nav-tab-active':'';?>">Watch Limit</a>
    <a href="admin.php?page=live-streaming&tab=watch-params" class="nav-tab <?php echo $active_tab=='watch-params'?'nav-tab-active':'';?>">Watch Params</a>
    <a href="admin.php?page=live-streaming&tab=billing" class="nav-tab <?php echo $active_tab=='billing'?'nav-tab-active':'';?>">Billing</a>
    <a href="admin.php?page=live-streaming&tab=tips" class="nav-tab <?php echo $active_tab=='tips'?'nav-tab-active':'';?>">Tips</a>
    <a href="admin.php?page=live-streaming&tab=hls" class="nav-tab <?php echo $active_tab=='hls'?'nav-tab-active':'';?>">HTML5 Transcoding</a>
    <a href="admin.php?page=live-streaming&tab=webrtc" class="nav-tab <?php echo $active_tab=='webrtc'?'nav-tab-active':'';?>">WebRTC</a>
    <a href="admin.php?page=live-streaming&tab=app" class="nav-tab <?php echo $active_tab=='app'?'nav-tab-active':'';?>">Custom App</a>
  	<a href="admin.php?page=live-streaming&tab=reset" class="nav-tab <?php echo $active_tab=='reset'?'nav-tab-active':'';?>"><?php _e('Reset','video-share-vod'); ?></a>
    <a href="admin.php?page=live-streaming&tab=support" class="nav-tab <?php echo $active_tab=='support'?'nav-tab-active':'';?>">Support</a>
</h2>

<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">

<?php
			switch ($active_tab)
			{

			case 'reset':
?>
<h3><?php _e('Reset Options','video-share-vod'); ?></h3>
This resets some options to defaults. Useful when upgrading plugin and new defaults are available for new features and for fixing broken installations.
<?php

				$confirm = $_GET['confirm'];



				if ($confirm) echo '<h4>Resetting...</h4>';
				else echo '<p><A class="button" href="'.get_permalink().'admin.php?page=live-streaming&tab=reset&confirm=1">Yes, Reset These Settings!</A></p>';

				$resetOptions = array('customCSS', 'custom_post', 'supportP2P', 'alwaysP2P');

				foreach ($resetOptions as $opt)
				{
					echo '<BR> - ' . $opt;
					if ($confirm) $options[$opt] = $optionsDefault[$opt];
				}

				if ($confirm)  update_option('VWliveStreamingOptions', $options);

				break;


			case 'watch-params':
				$options['watchRoleParametersConfig'] = htmlentities(stripslashes($options['watchRoleParametersConfig']));

?>
<h3>Watch Parameters: Advanced Configuration by Role</h3>
This permits advanced configuration for watch interface parameters based on user role.
<br>For more details about available parameters and possible values see <a href="https://videowhisper.com/?p=php+live+streaming#integrate">PHP Live Streaming documentation</a>.

<h4>Watch Role Parameters Configuration</h4>
<textarea name="watchRoleParametersConfig" id="watchRoleParametersConfig" cols="100" rows="5"><?php echo $options['watchRoleParametersConfig']?></textarea>
<BR>This overwrites parameters defined as permissions by channel owner.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['watchRoleParametersConfig']?></textarea>

<BR>Parsed configuration (should be an array of arrays):
<?php

				echo '<br><textarea readonly cols="100" rows="4">';
				var_dump($options['watchRoleParameters']);
				echo '</textarea>';


				if (!$current_user) $current_user = wp_get_current_user();
				echo '<h4>Testing</h4>Your role(s): '; var_dump($current_user->roles);

				echo '<BR>Role Parameters: ';
				var_dump(VWliveStreaming::userParameters($current_user, $options['watchRoleParameters']));

				break;

			case 'watch-limit':
				$options['userWatchLimitsConfig'] = htmlentities(stripslashes($options['userWatchLimitsConfig']));

?>
<h3>Watch Limit</h3>
Limit watch time per user (by keeping track for each user of total watch time on site).
<br>Works for Flash app for PC browser RTMP based clients. RTMP Session Control also enables for external RTMP apps and HTML5 WebRTC sessions. Does not monitor HTTP based mobile streaming (HLS / MPEG DASH).
<br>Only works for registered users as this records info as user metas. Does not work for site visitors: you should disable visitor access when using this.
<br>User watch time in Flash app is updated based on <a href="admin.php?page=live-streaming&tab=watcher">statusInterval parameter</a>. If configured at 60000 (ms), will update once per minute. Warning: A low value can highly impact web server load when multiple users are online. Recommended interval is 1-5 minutes depending on average content length and acceptable grace time.

<h4>Enable Watch Limit per User</h4>
<select name="userWatchLimit" id="userWatchLimit">
  <option value="1" <?php echo $options['userWatchLimit']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['userWatchLimit']?"":"selected"?>>No</option>
</select>

<h4>Limit Interval</h4>
<input name="userWatchInterval" type="text" id="userWatchInterval" size="12" maxlength="32" value="<?php echo $options['userWatchInterval']?>"/>s
<BR>Specify interval for limits in seconds (in example 2592000 = 1 month, Default: <?php echo $optionsDefault['userWatchInterval']?>).

<h4>Default Limit</h4>
<input name="userWatchLimitDefault" type="text" id="userWatchLimitDefault" size="12" maxlength="32" value="<?php echo $options['userWatchLimitDefault']?>"/>s
<br>Default limit for user in seconds (Ex: 108000 = 30h, Default: <?php echo $optionsDefault['userWatchLimitDefault']?>).

<h4>User Watch Limits Configuration</h4>
<textarea name="userWatchLimitsConfig" id="userWatchLimitsConfig" cols="100" rows="5"><?php echo $options['userWatchLimitsConfig']?></textarea>
<BR>Assign limit in hours, by role, one per line. Set 0 for unlimited.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['userWatchLimitsConfig']?></textarea>

<BR>Parsed configuration (should be an array):<BR>
<?php

				var_dump($options['userWatchLimits']);

				if (!$current_user) $current_user = wp_get_current_user();
				echo '<h4>Testing</h4>Your role(s): '; var_dump($current_user->roles);
				echo '<BR>Your Watch Time: ' . get_user_meta( $current_user->ID, 'vwls_watch', true ) . 's';
				echo '<BR>Since: ' .date("F j, Y, g:i a", get_user_meta( $current_user->ID, 'vwls_watch_update', true ));
				echo '<BR>Your Limit: ' . $limit = VWliveStreaming::userWatchLimit($current_user, $options)?$limit.'s':'unlimited';

				break;


			case 'playlists':
?>

<h3>Playlist Scheduler Settings</h3>
This section is for configuring settings related to SMIL playlists. Playlist can be used to schedule videos to play as a live stream (on a channel).
Playlist support can be configured on <a href='https://www.wowza.com/forums/content.php?145-How-to-schedule-streaming-with-Wowza-Streaming-Engine-(StreamPublisher)#installation'>Wowza Streaming Engine</a> and requires web and rtmp on same servers (so web scripts can write playlists).

<h4>Video Share VOD</h4>
<?php
				if (is_plugin_active('video-share-vod/video-share-vod.php'))
				{
					echo 'Detected.';
					$optionsVSV = get_option('VWvideoShareOptions');
					$custom_post_video = $optionsVSV['custom_post'];

					echo ' Post type name: ' . $optionsVSV['custom_post'];

				} else echo 'Not detected. Please install, activate and configure <a target="_blank" href="https://wordpress.org/plugins/video-share-vod/">Video Share VOD</a>!';

?>

<h4>Video Post Type Name</h4>
<input name="custom_post_video" type="text" id="custom_post_video" size="16" maxlength="32" value="<?php echo $options['custom_post_video']?>"/>
<br>Should be same as Video Share VOD post type name. Ex: video


<h4>Enable Playlists</h4>
<select name="playlists" id="playlists">
  <option value="1" <?php echo $options['playlists']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['playlists']?"":"selected"?>>No</option>
</select>
<BR>Allows users to schedule playlists. Feature also needs to be enabled for channels owners from <a href='admin.php?page=live-streaming&tab=features'>Channel Features</a> : Playlist Scheduler .
<BR>This feature requires Wowza Streaming Engine and <a href="https://www.wowza.com/forums/content.php?145-How-to-schedule-streaming-with-Wowza-Streaming-Engine-(StreamPublisher)#installation">specific setup</a>: for VideoWhisper managed <a href="https://videowhisper.com/?p=wowza+media+server+hosting">hosting plans</a> and <a href="https://videowhisper.com/?p=Dedicated+Servers">servers</a> submit a support request for setting this up.

<h4>Streams Path</h4>
<input name="streamsPath" type="text" id="streamsPath" size="100" maxlength="256" value="<?php echo $options['streamsPath']?>"/>
<BR>Used for .smil playlists (should be same as streams path configured in VideoShareVOD for RTMP delivery).
<BR> <?php
				echo $options['streamsPath'] . ' : ';
				if (file_exists($options['streamsPath']))
				{
					echo 'Found. ';
					if (is_writable($options['streamsPath'])) echo 'Writable. (OK)';
					else echo 'NOT writable.';
				}
				else echo '<b>NOT found!</b>';

				// update when saving
				if (isset($_POST['playlists']))
				{
					echo '<BR><BR>SMIL updated on settings save.';
					VWliveStreaming::updatePlaylistSMIL();
				}

				$streamsPath = VWliveStreaming::fixPath($options['streamsPath']);
				$smilPath = $streamsPath . 'playlist.smil';

				if (file_exists($smilPath))
				{
					echo '<br><br>Playlist found: ' . $smilPath;
					$smil = file_get_contents($smilPath);
					echo '<br><textarea readonly cols="100" rows="10">' .htmlentities($smil). '</textarea>';
				}

?>

<?php

				break;
			case 'app':
				$options['eula_txt'] = htmlentities(stripslashes($options['eula_txt']));
				$options['crossdomain_xml'] = htmlentities(stripslashes($options['crossdomain_xml']));

				$eula_url = site_url() . '/eula.txt';
				$crossdomain_url = site_url() . '/crossdomain.xml';

				//TEST: wp-admin/admin-ajax.php?action=vwls&task=vw_extlogin&videowhisper=1
?>
<h3>Application Settings</h3>
<p>This section is for configuring settings related to custom remote apps (iOS/Android/Desktop) that can be used in combination with this web based solution. Such apps can be <a href="https://videowhisper.com/?p=iPhone-iPad-Apps">custom made</a> for each site. Broadcasting camera as RTMP from mobile devices is only possible with mobile apps, due to mobile browser limitations.</p>

<h4>Default Webcam Resolution</h4>
<select name="camResolutionMobile" id="camResolutionMobile">
<?php
				foreach (array('160x120','240x180','320x240','426x240','480x360', '640x360', '640x480', '720x480', '720x576', '854x480', '1280x720', '1440x1080', '1920x1080') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['camResolutionMobile']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
 <br>Higher resolution will require <a target="_blank" href="https://videochat-scripts.com/recommended-h264-video-bitrate-based-on-resolution/">higher bandwidth</a> to avoid visible blocking and quality loss (ex. 1Mbps required for 640x360). Webcam capture resolution should be similar to video size in player/watch interface (capturing higher resolution will require more resources without visible quality improvement and lower will display pixelation when zoomed in player).

<h4>Webcam Frames Per Second</h4>
<select name="camFPSMobile" id="camFPSMobile">
<?php
				foreach (array('1','8','10','12','15','29','30','60') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['camFPSMobile']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>

<h4>Video Stream Bandwidth</h4>
<input name="camBandwidthMobile" type="text" id="camBandwidthMobile" size="7" maxlength="7" value="<?php echo $options['camBandwidthMobile']?>"/> (bytes/s)
<br>This sets size of video stream (without audio) and therefore the video quality.
<br>Total stream size should be less than maximum broadcaster upload speed (multiply by 8 to get bps, ex. 50000b/s requires connection higher than 400kbps).
<br>Do a speed test from broadcaster computer to a location near your streaming (rtmp) server using a tool like <a href="http://www.speedtest.net" target="_blank">SpeedTest.net</a> . Drag and zoom to a server in contry/state where you host (Ex: central US if you host with VideoWhisper) and select it. The upload speed is the maximum data you'll be able to broadcast.

<?php
				/*

<h4>Video Codec</h4>
<select name="videoCodecMobile" id="videoCodecMobile">
  <option value="H264" <?php echo $options['videoCodecMobile']=='H264'?"selected":""?>>H264</option>
  <option value="H263" <?php echo $options['videoCodecMobile']=='H263'?"selected":""?>>H263</option>
</select>
<BR>Mobile apps don't currently support H264 (due to Adobe Air limitations).


<h4>H264 Video Codec Profile</h4>
<select name="codecProfileMobile" id="codecProfileMobile">
  <option value="main" <?php echo $options['codecProfileMobile']=='main'?"selected":""?>>main</option>
  <option value="baseline" <?php echo $options['codecProfileMobile']=='baseline'?"selected":""?>>baseline</option>
</select>
<br>Recommended: Baseline

<h4>H264 Video Codec Level</h4>
<select name="codecLevelMobile" id="codecLevelMobile">
<?php
				foreach (array('1', '1b', '1.1', '1.2', '1.3', '2', '2.1', '2.2', '3', '3.1', '3.2', '4', '4.1', '4.2', '5', '5.1') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['codecLevelMobile']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
<br>Recommended: 3.1

<h4>Sound Codec</h4>
<select name="soundCodecMobile" id="soundCodecMobile">
  <option value="Speex" <?php echo $options['soundCodecMobile']=='Speex'?"selected":""?>>Speex</option>
  <option value="Nellymoser" <?php echo $options['soundCodecMobile']=='Nellymoser'?"selected":""?>>Nellymoser</option>
</select>
<BR>Speex is recommended for voice audio.
<BR>Current web codecs used by Flash plugin are not currently supported by iOS. For delivery to iOS, audio should be transcoded to AAC (HE-AAC or AAC-LC up to 48 kHz, stereo audio).

<h4>Speex Sound Quality</h4>
<select name="soundQualityMobile" id="soundQualityMobile">
<?php
				foreach (array('0', '1','2','3','4','5','6','7','8','9','10') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['soundQualityMobile']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
 <br>Higher quality requires more <a href="http://www.videochat-scripts.com/speex-vs-nellymoser-bandwidth/" target="_blank" >bandwidth</a>.
<br>Speex quality 9 requires 34.2kbps and generates 4275 b/s transfer. Quality 10 requires 42.2 kbps.

<h4>Nellymoser Sound Rate</h4>
<select name="micRateMobile" id="micRateMobile">
<?php
				foreach (array('5', '8', '11', '22','44') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['micRateMobile']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
<br>Higher quality requires more <a href="http://www.videochat-scripts.com/speex-vs-nellymoser-bandwidth/" target="_blank" >bandwidth</a>.
<br>NellyMoser rate 22 requires 44.1kbps and generates 5512b/s transfer. Rate 44 requires 88.2 kbps.

*/
?>

<h4><?php _e('End User License Agreement','vw2wvc'); ?></h4>
<textarea name="eula_txt" id="eula_txt" cols="100" rows="8"><?php echo $options['eula_txt']?></textarea>
<br>Users are required to accept this agreement before registering from app.
<br>After updating permalinks (<a href="options-permalink.php">Save Changes on Permalinks page</a>) this should become available as <a href="<?php echo $eula_url ?>"><?php echo $eula_url ?></a>.
<br>This works if file doesn't already exist. You can also create the file for faster serving.

<h4><?php _e('Cross Domain Policy','vw2wvc'); ?></h4>
<textarea name="crossdomain_xml" id="crossdomain_xml" cols="100" rows="4"><?php echo $options['crossdomain_xml']?></textarea>
<br>This is required for applications to access interface and scripts on site.
<br>After updating permalinks (<a href="options-permalink.php">Save Changes on Permalinks page</a>) this should become available as <a href="<?php echo $crossdomain_url ?>"><?php echo $crossdomain_url ?></a>.
<br>This works if file doesn't already exist. You can also create the file for faster serving.
<?php

				break;

			case 'support':
				//! Support
?>
<h3>Hosting Requirements</h3>
<UL>
<LI><a href="https://videowhisper.com/?p=Requirements">Hosting Requirements</a> This advanced software requires web hosting and rtmp hosting.</LI>
<LI><a href="https://videowhisper.com/?p=RTMP+Hosting">Estimate Hosting Needs</a> Evaluate hosting needs: volume and features.</LI>
<LI><a href="http://hostrtmp.com/compare/">Compare Hosting Options</a> Hosting options starting from $9/month.</LI>
</UL>

<h3>Software Documentation</h3>
<UL>
<LI><a href="admin.php?page=live-streaming-docs">Backend Documentation</a> Includes tutorial with local links to configure main features, menus, pages.</LI>
<LI><a href="http://broadcastlivevideo.com/setup-tutorial/">BroadcastLiveVideo Tutorial</a> Setup a turnkey live video broadcasting site.</LI>
<LI><a href="https://videowhisper.com/?p=wordpress+live+streaming">VideoWhisper Plugin Homepage</a> Plugin and application documentation.</LI>
</UL>

<h3>Contact and Feedback</h3>
<a href="https://videowhisper.com/tickets_submit.php">Sumit a Ticket</a> with your questions, inquiries and VideoWhisper support staff will try to address these as soon as possible.
<br>Although the free license does not include any services (as installation and troubleshooting), VideoWhisper staff can clarify requirements, features, installation steps or suggest additional services like customisations, hosting you may need for your project.

<h3>Review and Discuss</h3>
You can publicly <a href="https://wordpress.org/support/view/plugin-reviews/videowhisper-live-streaming-integration">review this WP plugin</a> on the official WordPress site (after <a href="https://wordpress.org/support/register.php">registering</a>). You can describe how you use it and mention your site for visibility. You can also post on the <a href="https://wordpress.org/support/plugin/videowhisper-live-streaming-integration">WP support forums</a> - these are not monitored by support so use a <a href="https://videowhisper.com/tickets_submit.php">ticket</a> if you want to contact VideoWhisper.
<BR>If you like this plugin and decide to order a commercial license or other services from <a href="http://videowhisper.com/">VideoWhisper</a>, use this coupon code for 5% discount: giveme5

<h3>News and Updates</h3>
You can also get connected with VideoWhisper and follow updates using <a href="http://twitter.com/videowhisper"> Twitter </a>, <a href="http://www.facebook.com/pages/VideoWhisper/121234178858"> Facebook </a>, <a href="https://plus.google.com/105178389419893112810?prsrc=3" >Google+</a>


				<?php
				break;

			case 'general':

				$broadcast_url = admin_url() . 'admin-ajax.php?action=vwls_broadcast&n=';
				$root_url = get_bloginfo( "url" ) . "/";


				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

				$current_user = wp_get_current_user();

				if ($current_user->$userName) $username = $current_user->$userName;
				$username = sanitize_file_name($username);


				$options['translationCode'] = htmlentities(stripslashes($options['translationCode']));
				$options['adsCode'] = htmlentities(stripslashes($options['adsCode']));
				$options['customCSS'] = htmlentities(stripslashes($options['customCSS']));
				$options['cssCode'] = htmlentities(stripslashes($options['cssCode']));


?>
<h3>General Integration Settings</h3>
Settings for integration with WordPress framework and other plugins.

<h4>Username</h4>
<select name="userName" id="userName">
  <option value="display_name" <?php echo $options['userName']=='display_name'?"selected":""?>>Display Name</option>
  <option value="user_login" <?php echo $options['userName']=='user_login'?"selected":""?>>Login (Username)</option>
  <option value="user_nicename" <?php echo $options['userName']=='user_nicename'?"selected":""?>>Nicename</option>
</select>

<h4>User Profile Link</h4>
<input name="profilePrefix" type="text" id="profilePrefix" size="100" maxlength="200" value="<?php echo $options['profilePrefix']?>"/>
<BR>Specify a url prefix for listing user profile.
 Default:<br><textarea readonly cols="100" rows="1"><?php echo $optionsDefault['profilePrefix']?></textarea>

<h4>Channel Profile Link</h4>
<input name="profilePrefixChannel" type="text" id="profilePrefixChannel" size="100" maxlength="200" value="<?php echo $options['profilePrefixChannel']?>"/>
<BR>Specify a url prefix for listing channel profile (a broadcaster can have multiple channels). If blank will link to default channel page.
 Default:<br><textarea readonly cols="100" rows="1"><?php echo $optionsDefault['profilePrefixChannel']?></textarea>

<h4>User Picture</h4>
<select name="userPicture" id="userPicture">
  <option value="0" <?php echo !$options['userPicture']?"selected":""?>>Disabled</option>
  <option value="avatar" <?php echo $options['userPicture']=='avatar'?"selected":""?>>WordPress Avatar</option>
</select>
<BR>Broadcaster will have channel thumbnail (snapshot) as avatar.

<h4>Registration and Login Logo</h4>
<input name="loginLogo" type="text" id="loginLogo" size="100" maxlength="200" value="<?php echo $options['loginLogo']?>"/>
<br>Logo image to show on registration & login form (320x54px). Leave blank to disable.
<?php echo $options['loginLogo']?"<BR><img src='".$options['loginLogo']."'>":'';?>


<h4>Channel Page Layout URL</h4>
<select name="channelUrl" id="channelUrl">
  <option value="post" <?php echo $options['channelUrl']=='post'?"selected":""?>>Post (Theme)</option>
  <option value="full" <?php echo $options['channelUrl']=='full'?"selected":""?>>Full Page</option>
</select>
<br>URL where to show channels from listings (implemented in listings).

<h4>Post Channels</h4>
<select name="postChannels" id="postChannels">
  <option value="1" <?php echo $options['postChannels']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['postChannels']?"":"selected"?>>No</option>
</select>
<BR>Enables special post types (channels) and static urls for easy access to broadcast, watch and preview video.
<BR>This is required by other features like frontend channel management.
<BR><?php echo $root_url; ?>channel/chanel-name/broadcast
<BR><?php echo $root_url; ?>channel/chanel-name/
<BR><?php echo $root_url; ?>channel/chanel-name/video
<BR><?php echo $root_url; ?>channel/chanel-name/hls - Video must be transcoded to HLS format for iOS or published directly in such format with external encoder.
<BR><?php echo $root_url; ?>channel/chanel-name/external - Shows rtmp settings to use with external applications (if supported).

<h4>Post Template Filename</h4>
<input name="postTemplate" type="text" id="postTemplate" size="20" maxlength="64" value="<?php echo $options['postTemplate']?>"/>
<br>Template file located in current theme folder, that should be used to render channel post page. Ex: page.php, single.php
<br><?php
				if ($options['postTemplate'] != '+plugin')
				{
					$single_template = get_stylesheet_directory() . '/' . $options['postTemplate'];
					echo $single_template . ' : ';
					if (file_exists($single_template)) echo 'Found.';
					else echo 'Not Found! Use another theme file!';
				}
?>
<br>Set "+plugin" to use a template provided by this plugin, instead of theme templates.

<h4>User Channels</h4>
<select name="userChannels" id="userChannels">
  <option value="1" <?php echo $options['userChannels']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['userChannels']?"":"selected"?>>No</option>
</select>
<BR>Enables users to start channel with own name by accessing a common static broadcasting link.
<BR><a href="<?php echo $broadcast_url; ?>"><img src="<?php echo $root_url; ?>wp-content/plugins/videowhisper-live-streaming-integration/ls/templates/live/i_webcam.png" align="absmiddle"
border="0"><?php echo $broadcast_url; ?></a>

<h4>Custom Channels</h4>
<select name="anyChannels" id="anyChannels">
  <option value="1" <?php echo $options['anyChannels']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['anyChannels']?"":"selected"?>>No</option>
</select>
<BR>Enables users to start channel by passing any channel name in link.
<BR><a href="<?php echo $broadcast_url . urlencode($username); ?>"><img src="<?php echo $root_url; ?>wp-content/plugins/videowhisper-live-streaming-integration/ls/templates/live/i_webcam.png"
align="absmiddle" border="0"><?php echo $broadcast_url . urlencode($username); ?></a>

<h4>Floating Logo / Watermark</h4>
<input name="overLogo" type="text" id="overLogo" size="80" maxlength="256" value="<?php echo $options['overLogo']?>"/>
<?php echo $options['overLogo']?"<BR><img src='".$options['overLogo']."'>":'';?>
<h4>Logo Link</h4>
<input name="overLink" type="text" id="overLink" size="80" maxlength="256" value="<?php echo $options['overLink']?>"/>

<h4>App Loader Image</h4>
<input name="loaderImage" type="text" id="loaderImage" size="80" maxlength="256" value="<?php echo $options['loaderImage']?>"/>
<br>Ex: <?php echo $root_url .'wp-content/plugins/videowhisper-live-streaming-integration/ls/loader.png'; ?>
<br>Leave blank to disable.
<?php echo $options['loaderImage']?"<BR><img src='".$options['loaderImage']."'>":'';?>


<h4>Chat Advertising Server</h4>
<input name="adServer" type="text" id="adServer" size="80" maxlength="256" value="<?php echo $options['adServer']?>"/>
<br>Use 'ads' for local content. See <a href="http://www.adinchat.com" target="_blank"><U><b>AD in Chat</b></U></a> compatible ad management server. This can be controlled by channel owners based on features setup.

<h4>Chat Advertising Interval</h4>
<input name="adsInterval" type="text" id="adsInterval" size="6" maxlength="6" value="<?php echo $options['adsInterval']?>"/>
<BR>Setup adsInterval in milliseconds (0 to disable ad calls).

<h4>Chat Advertising Content</h4>
<textarea name="adsCode" id="adsCode" cols="64" rows="8"><?php echo $options['adsCode']?></textarea>
<br>Shows from time to time in chat, if internal 'ads' server is enabled.

<h4>App CSS</h4>
<textarea name="cssCode" id="cssCode" cols="100" rows="5"><?php echo $options['cssCode']?></textarea>
<BR>Some texts from flash application can be styled (title, story).
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['cssCode']?></textarea>

<h4>Translation Code</h4>
<textarea name="translationCode" id="translationCode" cols="100" rows="5"><?php echo $options['translationCode']?></textarea>
<br>Generate by writing and sending "/videowhisper translation" in chat (contains xml tags with text and translation attributes). Texts are added to list only after being shown once in interface. If any texts don't show up in generated list you can manually add new entries for these. Same translation file is used for interfaces so setting should cumulate all translations.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['translationCode']?></textarea>

<h4>Custom CSS</h4>
<textarea name="customCSS" id="customCSS" cols="100" rows="5"><?php echo $options['customCSS']?></textarea>
<BR>Used in elements added by this plugin. Include &lt;style type=&quot;text/css&quot;&gt; &lt;/style&gt; container.
Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['customCSS']?></textarea>

<h4><a target="_plugin" href="https://wordpress.org/plugins/rate-star-review/">Rate Star Review</a> - Enable Star Reviews</h4>
<?php
				if (is_plugin_active('rate-star-review/rate-star-review.php')) echo 'Detected:  <a href="admin.php?page=rate-star-review">Configure</a>'; else echo 'Not detected. Please install and activate Rate Star Review by VideoWhisper.com from <a href="plugin-install.php?s=videowhisper+rate+star+review&tab=search&type=term">Plugins > Add New</a>!';
?>
<BR><select name="rateStarReview" id="rateStarReview">
  <option value="0" <?php echo $options['rateStarReview']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['rateStarReview']?"selected":""?>>Yes</option>
</select>
<br>Enables Rate Star Review integration. Shows star ratings on listings and review form, reviews on item pages.

<h4>Page for Management</h4>
<p>Add channel management page (Page ID <a href='post.php?post=<?php echo get_option("vwls_page_manage"); ?>&action=edit'><?php echo get_option("vwls_page_manage"); ?></a>) with shortcode [videowhisper_channel_manage]</p>
<select name="disablePage" id="disablePage">
  <option value="0" <?php echo $options['disablePage']=='0'?"selected":""?>>Yes</option>
  <option value="1" <?php echo $options['disablePage']=='1'?"selected":""?>>No</option>
</select>


<h4>Page for Channels</h4>
<p>Add channel list page (Page ID <a href='post.php?post=<?php echo get_option("vwls_page_channels"); ?>&action=edit'><?php echo get_option("vwls_page_channels"); ?></a>) with shortcode [videowhisper_channels]</p>
<select name="disablePageC" id="disablePageC">
  <option value="0" <?php echo $options['disablePageC']=='0'?"selected":""?>>Yes</option>
  <option value="1" <?php echo $options['disablePageC']=='1'?"selected":""?>>No</option>
</select>

<h4>Channel Thumb Width</h4>
<input name="thumbWidth" type="text" id="thumbWidth" size="4" maxlength="4" value="<?php echo $options['thumbWidth']?>"/>

<h4>Channel Thumb Height</h4>
<input name="thumbHeight" type="text" id="thumbHeight" size="4" maxlength="4" value="<?php echo $options['thumbHeight']?>"/>
<BR><a href="admin.php?page=live-streaming&tab=stats&regenerateThumbs=1">Regenerate Thumbs</a>

<h4>Default Channels Per Page</h4>
<input name="perPage" type="text" id="perPage" size="3" maxlength="3" value="<?php echo $options['perPage']?>"/>



<h4>Show VideoWhisper Powered by</h4>
<select name="videowhisper" id="videowhisper">
  <option value="0" <?php echo $options['videowhisper']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['videowhisper']?"selected":""?>>Yes</option>
</select>

<?php
				break;

			case 'webrtc':

				/*
	//?? profile-level-id=64C029

	 "avc1.66.30": {profile:"Baseline", level:3.0, max_bit_rate:10000}
	 //iOS friendly variation (iOS 3.0-3.1.2)
	 "avc1.42001e": {profile:"Baseline", level:3.0, max_bit_rate:10000} ,
	 "avc1.42001f": {profile:"Baseline", level:3.1, max_bit_rate:14000}
	 //other variations ,
	 "avc1.77.30": {profile:"Main", level:3.0, max_bit_rate:10000}
	 //iOS friendly variation (iOS 3.0-3.1.2) ,
	 "avc1.4d001e": {profile:"Main", level:3.0, max_bit_rate:10000} ,
	 "avc1.4d001f": {profile:"Main", level:3.1, max_bit_rate:14000} ,
	 "avc1.4d0028": {profile:"Main", level:4.0, max_bit_rate:20000} ,
	 "avc1.64001f": {profile:"High", level:3.1, max_bit_rate:17500} ,
	 "avc1.640028": {profile:"High", level:4.0, max_bit_rate:25000} ,
	 "avc1.640029": {profile:"High", level:4.1, max_bit_rate:62500}
*/
?>
<h3>WebRTC</h3>
WebRTC can be used to broadcast and playback live video in HTML5 browsers. WebRTC is a new real time video communication technology under development and with specific requirements and limitations. Warning: Enabling this without proper server configuration will make streaming functionality unavailable.

<h4>WebRTC</h4>
<select name="webrtc" id="webrtc">
  <option value="0" <?php echo $options['webrtc']?"":"selected"?>>Disabled</option>
  <option value="1" <?php echo $options['webrtc']=='1'?"selected":""?>>Enabled</option>
  <option value="2" <?php echo $options['webrtc']=='2'?"selected":""?>>Available</option>
  <option value="3" <?php echo $options['webrtc']=='3'?"selected":""?>>Preferred</option>
</select>
<BR>Enable after configuring and testing WebRTC. Current WebRTC does not offer advanced interactions like chat.
<BR>Showing WebRTC published channels as live and snapshots requires RTMP Session Control feature.
<BR>Web Broadcasting: Enabled shows this option for iOS/Android (Auto). Available will show option for broadcast under Flash interface. If Preferred will be used instead of Flash, in Auto mode.

<h4>Wowza WebRTC WebSocket URL</h4>
<input name="wsURLWebRTC" type="text" id="wsURLWebRTC" size="100" maxlength="256" value="<?php echo $options['wsURLWebRTC']?>"/>
<BR>Wowza WebRTC WebSocket URL (wss with SSL certificate). Formatted as wss://[wowza-server-with-ssl]:[port]/webrtc-session.json .
<BR>Requires latest Wowza Streaming Engine server configured for WebRTC support and with a SSL certificate. Such setup is available with <a href="http://videowhisper.com/?p=Wowza+Media+Server+Hosting#features" target="_vwhost">VideoWhisper Wowza Turnkey Managed Hosting</a>.

<h4>Wowza WebRTC WebSocket URL</h4>
<input name="applicationWebRTC" type="text" id="applicationWebRTC" size="100" maxlength="256" value="<?php echo $options['applicationWebRTC']?>"/>
<BR>Wowza Application Name (configured or WebRTC usage). Ex: videowhisper-webrtc
<BR>Server and application must match RTMP server settings, for streams to be available across protocols. Streams published with WebRTC can be played using advanced Flash player watch interface or as plain live video in browsers that support that.

<h4>RTSP Playback Address</h4>
<input name="rtsp_server" type="text" id="rtsp_server" size="100" maxlength="256" value="<?php echo $options['rtsp_server']?>"/>
<BR>For retrieving WebRTC streams. Ex: rtsp://[your-server]/videowhisper-x
<BR>Access WebRTC (RTSP) stream for snapshots, transcoding for RTMP/HLS/MPEGDASH playback.

<h4>RTSP Publish Address</h4>
<input name="rtsp_server_publish" type="text" id="rtsp_server_publish" size="100" maxlength="256" value="<?php echo $options['rtsp_server_publish']?>"/>
<BR>For publishing WebRTC streams. Usually requires publishing credentials (for Wowza configured in conf/publish.password). Ex: rtsp://[user:password@][your-server]/videowhisper-x

<h4>Video Codec</h4>
<select name="webrtcVideoCodec" id="webrtcVideoCodec">
  <option value="42e01f" <?php echo $options['webrtcVideoCodec']=='42e01f'?"selected":""?>>H.264 Profile 42e01f</option>
  <option value="VP8" <?php echo $options['webrtcVideoCodec']=='VP8'?"selected":""?>>VP8</option>
 <!--

     <option value="VP8" <?php echo $options['webrtcVideoCodec']=='VP8'?"selected":""?>>VP8</option>
  <option value="VP9" <?php echo $options['webrtcVideoCodec']=='VP9'?"selected":""?>>VP9</option>

  <option value="420010" <?php echo $options['webrtcVideoCodec']=='420010'?"selected":""?>>H.264 420010</option>
  <option value="420029" <?php echo $options['webrtcVideoCodec']=='420029'?"selected":""?>>H.264 420029</option>

  -->
</select>
<br>Safari currently supports only H264 (recommended). H264 can also playback directly in HLS, MPEG, Flash without additional transcoding (only audio is transcoded).

<h4>Video Bitrate</h4>
<input name="webrtcVideoBitrate" type="text" id="webrtcVideoBitrate" size="10" maxlength="16" value="<?php echo $options['webrtcVideoBitrate']?>"/>
<BR>Ex: 400. Max 400 for TCP.

<h4>Audio Codec</h4>
<select name="webrtcAudioCodec" id="webrtcAudioCodec">
  <option value="opus" <?php echo $options['webrtcAudioCodec']=='opus'?"selected":""?>>Opus</option>
  <option value="vorbis" <?php echo $options['webrtcAudioCodec']=='vorbis'?"selected":""?>>Vorbis</option>
</select>
<BR>Used with 64 audio bitrate.

<h4>FFMPEG Transcoding Parameters for WebRTC Playback (H264 + Opus)</h4>
<input name="ffmpegTranscodeRTC" type="text" id="ffmpegTranscodeRTC" size="100" maxlength="256" value="<?php echo $options['ffmpegTranscodeRTC']?>"/>
<BR>This should convert RTMP stream to H264 baseline restricted video and Opus audio, compatible with most WebRTC supporting browsers.
<br>For most browsers including Chrome, Safari, Firefox: -c:v libx264 -profile:v baseline -level 3.0 -c:a libopus -tune zerolatency
<br>For some browsers like Chrome, Firefox, not Safari, when broadcasting H264 baseline from flash client video can play as is: -c:v copy -c:a libopus


<h4>WebRTC Implementation</h4>
WebRTC streaming is done trough media server, as relay, for reliability and scalability needed for these solutions.
Conventional out-of-the-box WebRTC solutions require each client to establish and maintain separate connections with every other participant in a complicated network where the bandwidth load increases exponentially as each additional participant is added. For P2P, streaming broadcasters need server grade connections to live stream to multiple users and using a regular home ADSL connection (that has has higher download and bigger upload) causes real issues. These solutions use the powerful streaming server as WebRTC node to overcome scalability and reliability limitations. Solution combines WebRTC HTML5 streaming with relay server streaming for a production ready setup.

<h4>Current Implementation Support and Limitations</h4>
As WebRTC is a new technology under development, implementation support varies depending on browsers and settings. These may change with solution development and technology improvements. Here is current status:
<UL>
	<LI>Chrome: Functional on Android and PC. Supports broadcast and playback. Stream broadcast with Chrome is available in most HTML5 browsers, including Safari as WebRTC.</LI>
	<LI>Safari: Functional on iOS. On PC, supports broadcast to server (for transcoding and playback as RTMP, HLS, MPEG).<LI>
	<LI>Firefox: Functional, supports broadcasting and playback over UDP. </LI>
	<LI>Transcoding from WebRTC: Video and audio published with WebRTC is available in RTMP/HLS/MPEGDASH after transcoding, with some latency and availability delay.</LI>
	<LI>Transcoding from RTMP: Video and audio published with RTMP is available for WebRTC playback after transcoding, with some latency and availability delay.</LI>
</UL>
Implementation Limitations:
<UL>
	<LI>Advanced interactions specific to VideoWhisper Flash apps (like kick, tips) are not available, yet.</LI>
	<LI>Different chat system show messages with some external update delays between flash and html chat. Users list do not sync (external htmlchat users don't show in flash application).</LI>
</UL>

<?php
				break;

			case 'hls':
?>
<h3>Transcoding, HTML5, HLS, MPEG DASH</h3>
Configure transcoding and HTML5 based HLS, MPEG DASH delivery. HTTP Live Streaming is a great option for streaming to mobile browsers. Transcoding is required to convert between specific encoding formats required by HTML5 HLS, MPEG, WebRTC or Flash.
<p>Special Requirements: This functionality requires FFMPEG with necessary codecs on web host and publishing trough Wowza Streaming Engine server to deliver transcoded streams as HLS.
<BR>Recommended Hosting: <a href="http://videowhisper.com/?p=Wowza+Media+Server+Hosting#features" target="_vwhost">VideoWhisper Wowza Turnkey Managed Hosting</a> - turnkey rtmp address, configuration for archiving, transcoding streams, delivery to mobiles as HLS, playlists scheduler, IP cameras, advanced external encoder support.
</p>


<h4>Enable HTML5 Transcoding</h4>
<select name="transcoding" id="transcoding">
  <option value="0" <?php echo $options['transcoding']?"":"selected"?>>Disabled</option>
  <option value="1" <?php echo $options['transcoding'] == 1 ?"selected":""?>>Enabled</option>
  <option value="2" <?php echo $options['transcoding'] == 2 ?"selected":""?>>Available</option>
  <option value="3" <?php echo $options['transcoding'] == 3 ?"selected":""?>>Preferred</option>
</select>
<BR>This enables account level transcoding based on FFMPEG (if requirements are present). <BR>Transcoding is required for re-encoding live streams broadcast using web client to new re-encoded streams accessible by mobile HTML5 browsers using HLS / MPEG DASH. This requires high server processing power for each stream.
<BR>Transcoding is also required when converting streams between RTMP and WebRTC.
<BR>HLS support is also required on RTMP server and this is usually available with <a href="https://videowhisper.com/?p=Wowza+Media+Server+Hosting">Wowza Hosting</a> .
<BR>Account level transcoding is not required when stream is already broadcast with external encoders in appropriate formats (H264, AAC with supported settings) or using Wowza Transcoder Addon (usually on dedicated servers).
<BR>HTML5 Playback: If transcoding is enabled will be played on mobiles (Auto). If Available will be also shown to PC users as option. If Preferred will be used instead of Flash, in Auto mode.


<h4>Live Transcoding</h4>
<?php

				$processUser = get_current_user();
				$processUid = getmyuid();

				echo "This section shows FFMPEG transcoding and snapshot retrieval processes currently run by account '$processUser' (#$processUid). Transcoding starts some time after stream is published for VideoWhisper web apps or when RTMP Session Control is enabled.<BR>";

				$cmd = "ps aux | grep 'ffmpeg'";
				exec($cmd, $output, $returnvalue);
				//var_dump($output);

				$transcoders = 0;
				foreach ($output as $line) if (strstr($line, "ffmpeg"))
					{
						$columns = preg_split('/\s+/',$line);
						if (($processUser == $columns[0] || $processUid == $columns[0]) && (!in_array($columns[10],array('sh','grep'))))
						{

							echo " + Process #".$columns[1]." CPU: ".$columns[2]." Mem: ".$columns[3].' Start: '.$columns[8].' CPU Time: '.$columns[9]. ' Cmd: ';
							for ($n=10; $n<24; $n++) echo $columns[$n].' ';

							if ($_GET['kill']== $columns[1])
							{
								$kcmd = 'kill -KILL ' . $columns[1];
								exec($kcmd, $koutput, $kreturnvalue);
								echo ' <B>Killing process...</B>';
							}
							else echo ' <a href="admin.php?page=live-streaming&tab=hls&kill='.$columns[1].'">Kill</a>';

							echo '<br>';
							$transcoders++;
						}
					}

				if (!$transcoders) echo 'No live transcoding/snapshot processes detected.';
				else echo '<BR>Total processes for transcoding/snapshot: ' . $transcoders;

?>


<h4>FFMPEG Path</h4>
<input name="ffmpegPath" type="text" id="ffmpegPath" size="100" maxlength="256" value="<?php echo $options['ffmpegPath']?>"/>
<BR> Path to latest FFMPEG. Required for transcoding of web based streams, generating snapshots for external broadcasting applications (requires <a href="https://videowhisper.com/?p=RTMP-Session-Control">rtmp session control</a> to notify plugin about these streams).
<?php
				echo "<BR>exec: ";
				if(function_exists('exec'))
				{
					echo "function is enabled";

					if(exec('echo EXEC') == 'EXEC')
					{
						echo ' and works';
						$fexec =1;
					}
					else echo ' <b>but does not work</b>';

				}else echo '<b>function is not enabled</b><BR>PHP function "exec" is required to run FFMPEG. Current hosting settings are not compatible with this functionality.';


				echo "<BR>FFMPEG: ";
				$cmd ='timeout -s KILL 3 ' . $options['ffmpegPath'] . ' -version';
				$output="";
				exec($cmd, $output, $returnvalue);
				if ($returnvalue == 127)  echo "<b>Warning: not detected: $cmd</b>";
				else
				{
					echo "found";

					if ($returnvalue != 126)
					{
						echo '<BR>' . $output[0];
						echo '<BR>' . $output[1];
					}else
						echo ' but is NOT executable by current user: ' . $processUser;
				}

				$cmd =$options['ffmpegPath'] . ' -codecs';
				exec($cmd, $output, $returnvalue);

				//detect codecs
				if ($output) if (count($output))
					{
						echo "<br>Codec libraries:";
						foreach (array('h264', 'vp6','speex', 'nellymoser', 'fdk_aac', 'faac', 'vp8', 'vp9', 'opus') as $cod)
						{
							$det=0; $outd="";
							echo "<BR>$cod : ";
							foreach ($output as $outp) if (strstr($outp,$cod)) { $det=1; $outd=$outp; };
							if ($det) echo "detected ($outd)"; else echo "<b>missing: configure and install FFMPEG with lib$cod if you don't have another library for that codec</b>";
						}
					}
?>
<BR>You need only 1 AAC codec for transcoding to AAC. Depending on <a href="https://trac.ffmpeg.org/wiki/Encode/AAC#libfaac">AAC library available on your system</a> you may need to update transcoding parameters. Latest FFMPEG also includes a native encoder (aac).

<h4>Clarifications on Transcoding</h4>
Flash and RTMP camera streaming applications are not supported in mobile browsers. Special solutions are required for mobile users to implement support for this type of features (<A href="https://videowhisper.com/?p=iPhone-iPad-Apps">read more</a>).
<BR>Plain streaming is possible in mobile browser with HTML5 as HLS (HTTP Live Streaming).
<BR>Broadcasting from mobile is possible with generic RTMP mobile encoders like Wowza GoCoder for <a href="https://itunes.apple.com/us/app/wowza-gocoder/id640338185?mt=8">iOS</a> / <a href="https://play.google.com/store/apps/details?id=com.wowza.gocoder&hl=en">Android</a> that can be used to publish plain stream (no chat or interactions). Generic encoders require user to copy and paste rtmp address, channel name, settings and also <a href="https://videowhisper.com/?p=RTMP-Session-Control">RTMP Session Control</a> (included with VideoWhisper recommended Wowza hosting) to show external published streams as active channels on site.
<BR>For advanced interactions and easy usage/access with site credentials login, <a href="https://videowhisper.com/?p=iPhone-iPad-Apps#apps">custom apps can be developed</a> and then <a href="admin.php?page=live-streaming&tab=app">configured from this plugin</a>.
</p>

<h4>FFMPEG Transcoding Parameters for HLS / MPEG-DASH / Flash Playback (H264 + AAC)</h4>
<input name="ffmpegTranscode" type="text" id="ffmpegTranscode" size="100" maxlength="256" value="<?php echo $options['ffmpegTranscode']?>"/>
<BR>For lower server load and higher performance, web clients should be configured to broadcast video already suitable for target device (H.264 Baseline 3.1 for most iOS devices) so only audio needs to be encoded.

<BR>Ex.(transcode audio using latest FFMPEG with libfdk_aac): -c:v copy -c:a libfdk_aac -b:a 96k
<BR>Ex.(transcode audio using latest FFMPEG with native aac): -c:v copy -c:a aac -b:a 96k
<BR>Ex.(transcode video+audio in latest FFMPEG with libfdk_aac): -c:v libx264 -profile:v baseline -level 3.0 -c:a libfdk_aac -b:a 96k -tune zerolatency
<BR>Ex.(transcode audio using older FFMPEG with libfaac): -vcodec copy -acodec libfaac -ac 2 -ar 22050 -ab 96k
<BR>Ex.(transcode video+audio using older FFMPEG): -vcodec libx264 -s 480x360 -r 15 -vb 512k -x264opts vbv-maxrate=364:qpmin=4:ref=4 -coder 0 -bf 0 -analyzeduration 0 -level 3.1 -g 30 -maxrate 768k -acodec libfaac -ac 2 -ar 22050 -ab 96k
<BR>For advanced settings see <a href="https://developer.apple.com/library/ios/technotes/tn2224/_index.html#//apple_ref/doc/uid/DTS40009745-CH1-SETTINGSFILES">iOS HLS Supported Codecs<a> and <a href="https://trac.ffmpeg.org/wiki/Encode/AAC">FFMPEG AAC Encoding Guide</a>.

<h4>HTTP Streaming Base URL</h4>
This is used for accessing transcoded streams on HLS playback. Usually available with <a href="https://videowhisper.com/?p=Wowza+Media+Server+Hosting">Wowza Hosting</a> .<br>
<input name="httpstreamer" type="text" id="httpstreamer" size="100" maxlength="256" value="<?php echo $options['httpstreamer']?>"/>
<BR>External players and encoders (if enabled) are not monitored or controlled by this plugin, unless special <a href="https://videowhisper.com/?p=RTMP-Session-Control">rtmp side session control</a> is available.
<BR>Application folder must match rtmp application (ex: videowhisper-x)
<BR>Ex: https://[your-server]:1935/videowhisper-x/ works when publishing to rtmp://[your-server]/videowhisper-x
<BR>HTTPS Recommended: Some browsers will require a SSL certificate for MPEG DASH / HLS streaming and show warnings/errors if using mixed or unsecure urls.

<h4>FFMPEG Transcoding Parameters for WebRTC Playback (H264+Opus)</h4>
<input name="ffmpegTranscodeRTC" type="text" id="ffmpegTranscodeRTC" size="100" maxlength="256" value="<?php echo $options['ffmpegTranscodeRTC']?>"/>
<BR>This should convert RTMP stream to H264 baseline restricted and Opus, compatible with most browsers.
<br>For most browsers including Chrome, Safari, Firefox: -c:v libx264 -profile:v baseline -level 3.0 -c:a libopus -tune zerolatency
<br>For some browsers like Chrome, Firefox, not Safari, when broadcasting H264 baseline from flash client: -c:v copy -c:a libopus

<h4>Auto Transcoding</h4>
<select name="transcodingAuto" id="transcodingAuto">
  <option value="0" <?php echo $options['transcodingAuto']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['transcodingAuto']=='1'?"selected":""?>>On Request</option>
  <option value="2" <?php echo $options['transcodingAuto']=='2'?"selected":""?>>Always</option>
</select>
<BR>On Request starts transcoder when HLS / MPEG DASH is requested (by a mobile user) and Always when broadcast occurs. As HLS latency is usually several seconds, first viewer may not be able to access stream when using On Request.
<BR>Always will also check transcoding status from time to time (when broadcaster updates status). For external broadcasters (desktop/mobile), <a href="https://videowhisper.com/?p=RTMP-Session-Control#configure">RTMP Session Control</a> is required to activate web transcoding.
<BR>Auto transcoding will work only if channel post <a href="admin.php?page=live-streaming&tab=features">Transcode Feature</a> is enabled.

<h4>Manual Transcoding</h4>
<select name="transcodingManual" id="transcodingManual">
  <option value="0" <?php echo $options['transcodingManual']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['transcodingManual']=='1'?"selected":""?>>Yes</option>
</select>
<BR>Shows transcoding panel to broadcaster for manually toggling transcoding at runtime (for use when automated transcoding is disabled).

<h4>Transcoding Warning</h4>
<select name="transcodingWarning" id="transcodingWarning">
  <option value="0" <?php echo $options['transcodingWarning']?"":"selected"?>>Disabled</option>
  <option value="1" <?php echo $options['transcodingWarning']=='1'?"selected":""?>>Broadcaster</option>
  <option value="2" <?php echo $options['transcodingWarning']=='2'?"selected":""?>>Broadcaster and Viewers</option>
</select>
<BR>Warn users about latency and delay related to the extra operation of transcoding the stream.

<h4>MPEG-Dash Device Target</h4>
<select name="detect_mpeg" id="detect_mpeg">
  <option value="" <?php echo $options['detect_mpeg']?"":"selected"?>>None</option>
  <option value="android" <?php echo $options['detect_mpeg']=='android'?"selected":""?>>Android</option>
  <option value="nonsafari" <?php echo $options['detect_mpeg']=='nonsafari'?"selected":""?>>Except Safari</option>
  <option value="all" <?php echo $options['detect_mpeg']=='all'?"selected":""?>>Android & PC</option>
</select>
<BR>Show MPEG Dash for certain types of devices. Most browsers will require HTTPS.

<h4>HLS Device Target</h4>
<select name="detect_hls" id="detect_hls">
  <option value="" <?php echo $options['detect_hls']?"":"selected"?>>None</option>
  <option value="ios" <?php echo $options['detect_hls']=='ios'?"selected":""?>>iOS</option>
  <option value="mobile" <?php echo $options['detect_hls']=='mobile'?"selected":""?>>iOS & Android</option>
  <option value="safari" <?php echo $options['detect_hls']=='safari'?"selected":""?>>iOS & PC Safari</option>
  <option value="all" <?php echo $options['detect_hls']=='all'?"selected":""?>>Mobile & PC Safari</option>
</select>
<BR>Show HLS for certain types of devices. Does not overwrite MPEG Dash if enabled. Mobile covers iOS & Android.

<h4>FFMPEG RTMP Timeout</h4>
<input name="ffmpegTimeout" type="text" id="ffmpegTimeout" size="5" maxlength="20" value="<?php echo $options['ffmpegTimeout']?>"/>s
<BR>Disconnect quick ffmpeg connections for stream info or snapshots after this timeout. Implemented by RTMP Session control.

<h4>Support RTMP Streaming</h4>
<select name="supportRTMP" id="supportRTMP">
  <option value="0" <?php echo $options['supportRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportRTMP']?"selected":""?>>Yes</option>
</select>
<BR>Recommended: Yes. Streaming trough the relay RTMP server is most reliable and compulsory for some features like HLS, external player delivery.

<h4>Always do RTMP Streaming</h4>
<p>Enable this if you want all streams to be published to server, no matter if there are registered subscribers or not (in example if you're using server side video archiving and need all streams
published for recording).</p>
<select name="alwaysRTMP" id="alwaysRTMP">
  <option value="0" <?php echo $options['alwaysRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysRTMP']?"selected":""?>>Yes</option>
</select>
<BR>Recommended: Yes. Warning: Disabling this can disable HLS delivery and increase starting latency for streams. This should be available as backup streaming solution even if P2P is used (in specific conditions).


<?php
				break;
				
case 'ipcamera':
?>
<h3>IP Camera / Re-Streaming Settings</h3>
Configuring different streaming server settings is useful when you don't want to archive these streams.

<?
break;
			case 'server':

?>
<h3>Server Settings</h3>
Configure server settings for RTMP (live interactions and streaming) and HTTP (web scripts).
<br>To run this, make sure your hosting environment meets all <a href="https://videowhisper.com/?p=Requirements" target="_vwrequirements">requirements</a>.
<BR>Recommended hosting: <a href="http://videowhisper.com/?p=Wowza+Media+Server+Hosting#features" target="_vwhost">VideoWhisper Wowza turnkey managed plans</a> - turnkey rtmp address, configuration for archiving, transcoding streams, delivery to mobiles as HLS, playlists scheduler, IP cameras, advanced external encoder support.

<h4>RTMP Address</h4>
<input name="rtmp_server" type="text" id="rtmp_server" size="100" maxlength="256" value="<?php echo $options['rtmp_server']?>"/>
<BR>If you don't have a videowhisper rtmp address
yet (from a managed rtmp host), go to <a href="https://videowhisper.com/?p=RTMP+Applications" target="_blank">RTMP Application   Setup</a> for installation details.
<BR>A public accessible rtmp hosting server is required with custom videowhisper rtmp side. Ex: rtmp://your-server/videowhisper
<BR>The custom VideoWhisper rtmp side functionality is compulsory as it manages advanced functionality like chat, online user lists, interactions, webcam/microphone status, advanced session control.

<h4>Streams Path (IP Camera Streams /  Playlists)</h4>
<input name="streamsPath" type="text" id="streamsPath" size="100" maxlength="256" value="<?php echo $options['streamsPath']?>"/>
<BR>Path to .stream files monitored by streaming server for restreaming.
<BR>Such functionality requires Wowza Streaming Engine 4.2+, web and rtmp on same sever, <a href='https://www.wowza.com/forums/content.php?39-How-to-re-stream-video-from-an-IP-camera-(RTSP-RTP-re-streaming)#config_xml'>specific setup</a>. Streaming server loads configuration from web files, connects to IP camera stream or video file, loads stream and delivers in format suitable for web publishing.
<BR>This functionality is available with <a href="http://videowhisper.com/?p=Wowza+Media+Server+Hosting#plans" target="_vwhost">VideoWhisper Wowza plans</a> and servers, when hosting both web and rtmp on same plan/server so web scripts can access streaming configuration files.
If custom ports are used, server firewall must be configured to allow connections.
<BR>Can be same as streams path configured in VideoShareVOD.
<BR> <?php
				echo $options['streamsPath'] . ' : ';
				if (file_exists($options['streamsPath']))
				{
					echo 'Found. ';
					if (is_writable($options['streamsPath'])) echo 'Writable. (OK)';
					else echo 'NOT writable.';
				}
				else echo '<b>NOT found!</b>';
?>

<h4>Disable Bandwidth Detection</h4>
<p>Required on some rtmp servers that don't support bandwidth detection and return a Connection.Call.Fail error.</p>
<select name="disableBandwidthDetection" id="disableBandwidthDetection">
  <option value="0" <?php echo $options['disableBandwidthDetection']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['disableBandwidthDetection']?"selected":""?>>Yes</option>
</select>

<h4>Token Key</h4>
<input name="tokenKey" type="text" id="tokenKey" size="32" maxlength="64" value="<?php echo $options['tokenKey']?>"/>
<BR>A <a href="https://videowhisper.com/?p=RTMP+Applications#settings">secure token</a> can be used with Wowza Media Server.

<h4>Web Key</h4>
<input name="webKey" type="text" id="webKey" size="32" maxlength="64" value="<?php echo $options['webKey']?>"/>
<BR>A web key can be used for <a href="http://www.videochat-scripts.com/videowhisper-rtmp-web-authetication-check/">VideoWhisper RTMP Web Session Check</a>. Configure as documented on <a href="https://videowhisper.com/?p=RTMP-Session-Control#configure">RTMP Session Control Configuration</a>. Application.xml settings in &lt;Root&gt;&lt;Application&gt;&lt;Properties&gt; :<br>

<textarea readonly cols="100" rows="4">
<?php
				$admin_ajax = admin_url() . 'admin-ajax.php';
				$webLogin = htmlentities($admin_ajax."?action=vwls&task=rtmp_login&s=");
				$webLogout = htmlentities($admin_ajax."?action=vwls&task=rtmp_logout&s=");
				$webStatus = htmlentities($admin_ajax."?action=vwls&task=rtmp_status");

				echo  htmlspecialchars("<!-- VideoWhisper.com: RTMP Session Control https://videowhisper.com/?p=rtmp-session-control -->
<Property>
<Name>acceptPlayers</Name>
<Value>true</Value>
</Property>
<Property>
<Name>webLogin</Name>
<Value>$webLogin</Value>
</Property>
<Property>
<Name>webKey</Name>
<Value>".$options['webKey']."</Value>
</Property>
<Property>
<Name>webLogout</Name>
<Value>$webLogout</Value>
</Property>
<Property>
<Name>webStatus</Name>
<Value>$webStatus</Value>
</Property>
")
?>
</textarea>
<BR>Warnings: webStatus will not work on 3rd party servers without a full mode license for RTMP side (channel online status will not update). Test if functional by monitoring if external broadcast remains LIVE in <a href="admin.php?page=live-streaming-stats">Statistics</a>.
<BR>Broadcaster can't connect at same time from web broadcasting interface and external encoder with session control (as session name will be rejected as duplicate).

<h4>Web Status</h4>
<select name="webStatus" id="webStatus">
  <option value="auto" <?php echo $options['webStatus']=='auto'?"selected":""?>>Auto</option>
  <option value="enabled" <?php echo $options['webStatus']=='enabled'?"selected":""?>>Enabled</option>
  <option value="disabled" <?php echo $options['webStatus']=='disabled'?"selected":""?>>Disabled</option>
</select>
<BR>Auto will automatically enable first time webLogin successful authentication occurs for a broadcaster. Will also configure the server IP restriction.

<h4>Web Status Server IP Restriction</h4>
<input name="rtmp_restrict_ip" type="text" id="rtmp_restrict_ip" size="100" maxlength="512" value="<?php echo $options['rtmp_restrict_ip']?>"/>
<BR>Allow status updates only from configured IP(s). If not defined will configure automatically when first successful webLogin authorisation occurs for a broadcaster. Web status will not work if this is empty or not configured right.
<BR>Some streaming servers use different IPs. All must be added as comma separated values.
<?php

				if ($options['webStatus'] == 'enabled')
					if (file_exists($path = $options['uploadsPath']. '/_rtmpStatus.txt'))
					{
						$url = VWliveStreaming::path2url($path);
						echo 'Found: <a target=_blank href="'.$url.'">last status request</a> ' . date("D M j G:i:s T Y", filemtime($path)) ;
					}
?>
<!--
<h4>Session Status</h4>
<select name="rtmpStatus" id="rtmpStatus">
  <option value="0" <?php echo $options['rtmpStatus']=='0'?"":"selected"?>>Auto</option>
  <option value="1" <?php echo $options['rtmpStatus']=='1'?"selected":""?>>RTMP</option>
</select>
<BR>Session status allows monitoring and controlling online users sessions.
<BR>Auto: Will monitor web sessions based on requests from HTTP clients (VideoWhisper web applications) and other clients by RTMP.
<BR>RTMP: Will monitor all clients by RTMP, including web clients. Web monitoring is disabled.
-->

<h4>External Transcoder Keys</h4>
<select name="externalKeysTranscoder" id="externalKeysTranscoder">
  <option value="0" <?php echo $options['externalKeysTranscoder']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['externalKeysTranscoder']?"selected":""?>>Yes</option>
</select>
<BR>Direct authentication parameters will be used for transcoder, external stream thumbnails in case webLogin is enabled. RTMP server will pass these parameters to webLogin scripts for direct authentication without website access.

<h4>On Demand Archiving</h4>
<input name="manualArchiving" type="text" id="manualArchiving" size="100" maxlength="200" value="<?php echo $options['manualArchiving']?>"/>
<BR>URL to control archiving by web. Leave blank to disable. Sample setting: http://[username]:[password]@[wowza-ip-address]:8086/livestreamrecord?app=videowhisper
<BR>On demand archiving can be enabled on Wowza server as documented at https://www.wowza.com/forums/content.php?123-How-to-record-live-streams-(HTTPLiveStreamRecord) . Also requires crossdomain.xml on Wowza web space.

<h4>RTMFP Address</h4>
<p> Get your own independent RTMFP address by registering for a free <a href="https://www.adobe.com/cfusion/entitlement/index.cfm?e=cirrus" target="_blank">Adobe Cirrus developer key</a>. This is
required for P2P support.</p>
<input name="serverRTMFP" type="text" id="serverRTMFP" size="80" maxlength="256" value="<?php echo $options['serverRTMFP']?>"/>
<h4>P2P Group</h4>
<input name="p2pGroup" type="text" id="p2pGroup" size="32" maxlength="64" value="<?php echo $options['p2pGroup']?>"/>
<h4>Support RTMP Streaming</h4>
<select name="supportRTMP" id="supportRTMP">
  <option value="0" <?php echo $options['supportRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportRTMP']?"selected":""?>>Yes</option>
</select>
<BR>Recommended: Yes. Streaming trough the relay RTMP server is most reliable and compulsory for some features like HLS, external player delivery.

<h4>Always do RTMP Streaming</h4>
<p>Enable this if you want all streams to be published to server, no matter if there are registered subscribers or not (in example if you're using server side video archiving and need all streams
published for recording).</p>
<select name="alwaysRTMP" id="alwaysRTMP">
  <option value="0" <?php echo $options['alwaysRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysRTMP']?"selected":""?>>Yes</option>
</select>
<BR>Recommended: Yes. Warning: Disabling this can disable HLS delivery and increase starting latency for streams. This should be available as backup streaming solution even if P2P is used (in specific conditions).

<h4>Support P2P RTMFP Streaming</h4>
<select name="supportP2P" id="supportP2P">
  <option value="0" <?php echo $options['supportP2P']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['supportP2P']?"selected":""?>>Yes</option>
</select>
<BR>Recommended: No. Warning: P2P is not reliable for most users with regular home connections (most users with regular connections will not be able to broadcast or watch video if that's enabled). P2P is great for users with server grade connections (public IP, high upload) or users in same network.
<BR>Warning: Streaming only P2P over RTMFP disables archiving, transcoding and HTML5 delivery (HLS MPEG WebRTC) as streams no longer go trough server.

<h4>Always do P2P RTMFP Streaming</h4>
<select name="alwaysP2P" id="alwaysP2P">
  <option value="0" <?php echo $options['alwaysP2P']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysP2P']?"selected":""?>>Yes</option>
</select>
<BR>Recommended: No.

<h4>Uploads Path</h4>
<p>Path where logs and snapshots will be uploaded. Make sure you use a location outside plugin folder to avoid losing logs on updates and plugin uninstallation.</p>
<input name="uploadsPath" type="text" id="uploadsPath" size="80" maxlength="256" value="<?php echo $options['uploadsPath']?>"/>
<?php
				if (!file_exists($options['uploadsPath'])) echo '<br><b>Warning: Folder does not exist. If this warning persists after first access check path permissions:</b> ' . $options['uploadsPath'];
				if (!strstr($options['uploadsPath'], get_home_path() )) echo '<br><b>Warning: Uploaded files may not be accessible by web (path is not within WP installation path).</b>';

				echo '<br>WordPress Path: ' . get_home_path();
				echo '<br>WordPress URL: ' . get_site_url();
?>
<br>wp_upload_dir()['basedir'] : <?php $wud= wp_upload_dir(); echo $wud['basedir'] ?>
<br>$_SERVER['DOCUMENT_ROOT'] : <?php echo $_SERVER['DOCUMENT_ROOT'] ?>

<h4>Show Channel Watch when Offline</h4>
<p>Display channel watch interface even if channel is not detected as broadcasting.</p>
<select name="alwaysWatch" id="alwaysWatch">
  <option value="0" <?php echo $options['alwaysWatch']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysWatch']?"selected":""?>>Yes</option>
</select>
<br>Useful when broadcasting with external apps and <a href="https://videowhisper.com/?p=RTMP-Session-Control">rtmp side session control</a> is not available.
<br>Watch interface always shows for channels that stream from IP cameras or playlists (not affected by this setting).
<BR>Warning: Enabling this disables event details, that show on channel page while channel is offline. Disabling this, requires broadcast to be started before viewers come to page.
<?php
				break;

			case 'broadcaster':
				$options['parametersBroadcaster'] = htmlentities(stripslashes($options['parametersBroadcaster']));
				$options['layoutCodeBroadcaster'] = htmlentities(stripslashes($options['layoutCodeBroadcaster']));

?>
<h3>Video Broadcasting</h3>
Options for video broadcasting.
<h4>Who can broadcast video channels</h4>
<select name="canBroadcast" id="canBroadcast">
  <option value="members" <?php echo $options['canBroadcast']=='members'?"selected":""?>>All Members</option>
  <option value="list" <?php echo $options['canBroadcast']=='list'?"selected":""?>>Members in List</option>
</select>
<br>These users will be able to use broadcasting interface for managing channels (Broadcast Live) and have access to rtmp address keys for using external applications, if enabled.

<h4>Members allowed to broadcast video (comma separated user names, roles, emails, IDs)</h4>
<textarea name="broadcastList" cols="64" rows="3" id="broadcastList"><?php echo $options['broadcastList']?>
</textarea>


<h4>Maximum Number of Broadcasting Channels (per User)</h4>
<input name="maxChannels" type="text" id="maxChannels" size="2" maxlength="4" value="<?php echo $options['maxChannels']?>"/>
<BR>Maximum channels users are allowed to create from frontend if channel posts are enabled.


<h4>Maximum Broadcating Time (0 = unlimited)</h4>
<input name="broadcastTime" type="text" id="broadcastTime" size="7" maxlength="7" value="<?php echo $options['broadcastTime']?>"/> (minutes/period)

<h4>Maximum Channel Watch Time (total cumulated view time, 0 = unlimited)</h4>
<input name="watchTime" type="text" id="watchTime" size="10" maxlength="10" value="<?php echo $options['watchTime']?>"/> (minutes/period)

<h4>Usage Period Reset (0 = never)</h4>
<input name="timeReset" type="text" id="timeReset" size="4" maxlength="4" value="<?php echo $options['timeReset']?>"/> (days)

<h4>Banned Words in Names</h4>
<textarea name="bannedNames" cols="64" rows="3" id="bannedNames"><?php echo $options['bannedNames']?>
</textarea>
<br>Users trying to broadcast channels using these words will be disconnected.


<h4>Redirect broadcaster from own channel page</h4>
<select name="broadcasterRedirect" id="broadcasterRedirect">
  <option value="0" <?php echo $options['broadcasterRedirect']?"":"selected"?>>No</option>
  <option value="dashboard" <?php echo $options['broadcasterRedirect']=='dashboard'?"selected":""?>>Broadcast Live Dashboard</option>
  <option value="broadcast" <?php echo $options['broadcasterRedirect']=='broadcast'?"selected":""?>>Broadcast Channel</option>
</select>
<BR>Redirect broadcaster when accessing own channel page to dashboard or broadcasting interface instead of watch/video interface. Does not redirect when accessing specific interfaces with parameter like hls, mpeg, webrtc.


<h4>External Application Addresses</h4>
<select name="externalKeys" id="externalKeys">
  <option value="0" <?php echo $options['externalKeys']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['externalKeys']?"selected":""?>>Yes</option>
</select>
<BR>Shows "External Apps" button for each channel in Broadcast Live section. Channel owners will receive access to their secret publishing and playback addresses for each channel.
<BR>Enables external application support by inserting authentication info (username, channel name, key for broadcasting/watching) directly in RTMP address. RTMP server will pass these parameters to webLogin scripts for direct authentication without website access. This feature requires special RTMP side support for managing these parameters.
<br>Advanced external app session control requires <a href="https://videowhisper.com/?p=RTMP-Session-Control">rtmp side session control</a> setup.
<?php
				break;

			case 'broadcast-flash':
				$options['parametersBroadcaster'] = htmlentities(stripslashes($options['parametersBroadcaster']));
				$options['layoutCodeBroadcaster'] = htmlentities(stripslashes($options['layoutCodeBroadcaster']));

?>
<h3>Advanced Flash Web Broadcasting Interface</h3>
Settings for the advanced web based broadcasting interface (VideoWhisper Flash based application for PC browser). These settings do not apply for external apps or HTML5 alternatives.
<h4>Default Webcam Resolution</h4>
<select name="camResolution" id="camResolution">
<?php
				foreach (array('160x120','320x240','426x240','480x360', '640x360', '640x480', '720x480', '720x576', '854x480', '1280x720', '1440x1080', '1920x1080') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['camResolution']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
 <br>Higher resolution will require <a target="_blank" href="http://www.videochat-scripts.com/recommended-h264-video-bitrate-based-on-resolution/">higher bandwidth</a> to avoid visible blocking and quality loss (ex. 1Mbps required for 640x360) .Webcam capture resolution should be same as video size in player/watch interface.

<h4>Default Webcam Frames Per Second</h4>
<select name="camFPS" id="camFPS">
<?php
				foreach (array('1','8','10','12','15','29','30','60') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['camFPS']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>


<h4>Video Stream Bandwidth</h4>
<input name="camBandwidth" type="text" id="camBandwidth" size="7" maxlength="7" value="<?php echo $options['camBandwidth']?>"/> (bytes/s)
<br>This sets size of video stream (without audio) and therefore the video quality.
<br>Total stream size should be less than maximum broadcaster upload speed (multiply by 8 to get bps, ex. 50000b/s requires connection higher than 400kbps).
<br>Do a speed test from broadcaster computer to a location near your streaming (rtmp) server using a tool like <a href="http://www.speedtest.net" target="_blank">SpeedTest.net</a> . Drag and zoom to a server in contry/state where you host (Ex: central US if you host with VideoWhisper) and select it. The upload speed is the maximum data you'll be able to broadcast.

<h4>Maximum Video Stream Bandwidth (at runtime)</h4>
<input name="camMaxBandwidth" type="text" id="camMaxBandwidth" size="7" maxlength="7" value="<?php echo $options['camMaxBandwidth']?>"/> (bytes/s)

<h4>Video Codec</h4>
<select name="videoCodec" id="videoCodec">
  <option value="H264" <?php echo $options['videoCodec']=='H264'?"selected":""?>>H264</option>
  <option value="H263" <?php echo $options['videoCodec']=='H263'?"selected":""?>>H263</option>
</select>
<BR>H264 provides better quality at same bandwidth but may not be supported by older RTMP server versions (ex. Red5).
<BR>When publishing to iOS with HLS, for lower server load and higher performance, web clients should be configured to broadcast video suitable for target device (H.264 Baseline 3.1) so only audio needs to be encoded.


<h4>H264 Video Codec Profile</h4>
<select name="codecProfile" id="codecProfile">
  <option value="baseline" <?php echo $options['codecProfile']=='baseline'?"selected":""?>>baseline</option>
  <option value="main" <?php echo $options['codecProfile']=='main'?"selected":""?>>main</option>
  <option value="high" <?php echo $options['codecProfile']=='high'?"selected":""?>>high</option>
</select>
<br>Recommended: Baseline

<h4>H264 Video Codec Level</h4>
<select name="codecLevel" id="codecLevel">
<?php
				foreach (array('1', '1b', '1.1', '1.2', '1.3', '2', '2.1', '2.2', '3', '3.1', '3.2', '4', '4.1', '4.2', '5', '5.1') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['codecLevel']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
<br>Recommended: 3.1

<h4>Sound Codec</h4>
<select name="soundCodec" id="soundCodec">
  <option value="Speex" <?php echo $options['soundCodec']=='Speex'?"selected":""?>>Speex</option>
  <option value="Nellymoser" <?php echo $options['soundCodec']=='Nellymoser'?"selected":""?>>Nellymoser</option>
</select>
<BR>Speex is recommended for voice audio.
<BR>Current web codecs used by Flash plugin are not currently supported by iOS. For delivery to iOS, audio should be transcoded to AAC (HE-AAC or AAC-LC up to 48 kHz, stereo audio).

<h4>Speex Sound Quality</h4>
<select name="soundQuality" id="soundQuality">
<?php
				foreach (array('0', '1','2','3','4','5','6','7','8','9','10') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['soundQuality']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
 <br>Higher quality requires more <a href="http://www.videochat-scripts.com/speex-vs-nellymoser-bandwidth/" target="_blank" >bandwidth</a>.
<br>Speex quality 9 requires 34.2kbps and generates 4275 b/s transfer. Quality 10 requires 42.2 kbps.

<h4>Nellymoser Sound Rate</h4>
<select name="micRate" id="micRate">
<?php
				foreach (array('5', '8', '11', '22','44') as $optItm)
				{
?>
  <option value="<?php echo $optItm;?>" <?php echo $options['micRate']==$optItm?"selected":""?>> <?php echo $optItm;?> </option>
  <?php
				}
?>
 </select>
<br>Higher quality requires more <a href="http://www.videochat-scripts.com/speex-vs-nellymoser-bandwidth/" target="_blank" >bandwidth</a>.
<br>NellyMoser rate 22 requires 44.1kbps and generates  5512b/s transfer. Rate 44 requires 88.2 kbps.


<h4>Disable Embed/Link Codes</h4>
<select name="noEmbeds" id="noEmbeds">
  <option value="0" <?php echo $options['noEmbeds']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['noEmbeds']?"selected":""?>>Yes</option>
</select>
<h4>Show only Video</h4>
<select name="onlyVideo" id="onlyVideo">
  <option value="0" <?php echo $options['onlyVideo']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['onlyVideo']?"selected":""?>>Yes</option>
</select>
<BR>Disable all interactive elements (show title, users list, chat). Only plain video broadcasting is possible. During troubleshooting/development this should be disabled to get additional info from chat box.

<h4>Custom Layout Code for Broadcaster Interface</h4>
<textarea name="layoutCodeBroadcaster" id="layoutCodeBroadcaster" cols="100" rows="4"><?php echo $options['layoutCodeBroadcaster']?></textarea>
<br>Generate by writing and sending "/videowhisper layout" in chat, in broadcasting interface. Contains panel positions, sizes, move and resize toggles. Copy and paste code here.
 Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['layoutCodeBroadcaster']?></textarea>
<br>All products load interface (skins, icons, sounds) from files (jpg, png, mp3) in the templates folder. After setting up existing editions, VideoWhisper developers can customize features and options for additional fees, on request, depending on exact requirements.

<h4>Parameters for Broadcaster Interface</h4>
<textarea name="parametersBroadcaster" id="parametersBroadcaster" cols="64" rows="8"><?php echo $options['parametersBroadcaster']?></textarea>
<br>For more details see <a href="https://videowhisper.com/?p=php+live+streaming#integrate">PHP Live Streaming documentation</a>.
<br>When using HTML AJAX chat, use short i.e. externalInterval=11000 to update application chat with messages from external chat.
<br>Ex: &snapshotsTime=60000&room_limit=500&externalInterval=360000&statusInterval=30000
 Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['parametersBroadcaster']?></textarea>

<h4>Online Expiration</h4>
<p>How long to consider broadcaster online if no web status update occurs.</p>
<input name="onlineExpiration1" type="text" id="onlineExpiration1" size="5" maxlength="6" value="<?php echo $options['onlineExpiration1']?>"/>s
<br>Should be 10s higher than maximum statusInterval (ms) configured in parameters. A higher statusInterval decreases web server load caused by status updates.
<br>If lower than statusInterval that can cause web server online session sync errors and online users showing offline.

<?php
				break;

				// ! Premium channels
			case 'premium':
?>
<h3>Premium Channels</h3>
Options for premium channels. Premium channels have special settings and features that can be defined here.
Use in combination with <a href='admin.php?page=live-streaming&tab=features'>Channel Features</a> to define specific capabilities depending on role.

<h4>Number of Premium Levels</h4>
<input name="premiumLevelsNumber" type="text" id="premiumLevelsNumber" size="7" maxlength="7" value="<?php echo $options['premiumLevelsNumber']?>"/>
<br>Number of premium membership levels.

<?php

				$premiumLev = unserialize($options['premiumLevels']);

				for ($i=0; $i < $options['premiumLevelsNumber']; $i++)
				{

					$premiumLev[$i]['level'] = $i+1;

					foreach (array('premiumList','canWatchPremium','watchListPremium','pBroadcastTime','pWatchTime','pCamBandwidth','pCamMaxBandwidth') as $varName)
					{
						if (isset($_POST[$varName . $i])) $premiumLev[$i][$varName] = $_POST[$varName . $i];
						if (!isset($premiumLev[$i][$varName])) $premiumLev[$i][$varName] = $options[$varName]; //default from options
					}
?>

<h3>Premium Level <?php echo ($i+1); ?></h3>

<h4>Members that broadcast premium channels (Premium members: comma separated user names, roles, emails, IDs)</h4>
<textarea name="premiumList<?php echo $i ?>" cols="64" rows="3" id="premiumList<?php echo $i ?>"><?php echo $premiumLev[$i]['premiumList']?>
</textarea>
<br>Highest level match is selected.
<br>Warning: Certain plugins may implement roles that have a different label than role name. Ex: s2member_level1

<h4>Who can watch premium channels</h4>
<select name="canWatchPremium<?php echo $i ?>" id="canWatchPremium<?php echo $i ?>">
  <option value="all" <?php echo $premiumLev[$i]['canWatchPremium']=='all'?"selected":""?>>Anybody</option>
  <option value="members" <?php echo $premiumLev[$i]['canWatchPremium']=='members'?"selected":""?>>All Members</option>
  <option value="list" <?php echo $premiumLev[$i]['canWatchPremium']=='list'?"selected":""?>>Members in List</option>
</select>

<h4>Members allowed to watch premium channels (comma separated usernames, roles, emails, IDs)</h4>
<textarea name="watchListPremium<?php echo $i ?>" cols="64" rows="3" id="watchListPremium<?php echo $i ?>"><?php echo $premiumLev[$i]['watchListPremium']?>
</textarea>

<h4>Maximum Broadcasting Time (0 = unlimited)</h4>
<input name="pBroadcastTime<?php echo $i ?>" type="text" id="pBroadcastTime<?php echo $i ?>" size="7" maxlength="7" value="<?php echo $premiumLev[$i]['pBroadcastTime']?>"/> (minutes/period)

<h4>Maximum Channel Watch Time (total cumulated view time, 0 = unlimited)</h4>
<input name="pWatchTime<?php echo $i ?>" type="text" id="pWatchTime<?php echo $i ?>" size="10" maxlength="10" value="<?php echo $premiumLev[$i]['pWatchTime']?>"/> (minutes/period)

<h4>Video Stream Bandwidth</h4>
<input name="pCamBandwidth<?php echo $i ?>" type="text" id="pCamBandwidth<?php echo $i ?>" size="7" maxlength="7" value="<?php echo $premiumLev[$i]['pCamBandwidth']?>"/> (bytes/s)
<br>Default stream size for web broadcasting interface.

<h4>Maximum Video Stream Bandwidth (at runtime)</h4>
<input name="pCamMaxBandwidth<?php echo $i ?>" type="text" id="pCamMaxBandwidth<?php echo $i ?>" size="7" maxlength="7" value="<?php echo $premiumLev[$i]['pCamMaxBandwidth']?>"/> (bytes/s)
<br>Maximum stream size for web broadcasting interface.
<?php
				}



				$options['premiumLevels'] = serialize($premiumLev);
				update_option('VWliveStreamingOptions', $options);

				/*
<h4>Show Floating Logo/Watermark</h4>
<select name="pLogo" id="pLogo">
  <option value="0" <?php echo $options['pLogo']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['pLogo']?"selected":""?>>Yes</option>
</select>

<h4>Always do RTMP Streaming (required for Transcoding)</h4>
<p>Enable this if you want all streams to be published to server, no matter if there are registered subscribers or not. Stream on server is required for transcoding to start.</p>
<select name="alwaysRTMP" id="alwaysRTMP">
  <option value="0" <?php echo $options['alwaysRTMP']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['alwaysRTMP']?"selected":""?>>Yes</option>
</select>
*/
?>

<h3>Common Settings</h3>

<h4>Usage Period Reset (same as for regular channels, 0 = never)</h4>
<input name="timeReset" type="text" id="timeReset" size="4" maxlength="4" value="<?php echo $options['timeReset']?>"/> (days)
<?php
				break;
			case 'features':

				//! Channel Features
?>
<h3>Channel Features</h3>
Enable channel features, accessible by owner (broadcaster).
<br>Specify comma separated list of user roles, emails, logins able to setup these features for their channels.
<br>Use All to enable for everybody and None or blank to disable.
<?php

				$features = VWliveStreaming::roomFeatures();

				foreach ($features as $key=>$feature) if ($feature['installed'])
					{
						echo '<h3>' . $feature['name'] . '</h3>';
						echo '<textarea name="'.$key.'" cols="64" rows="2" id="'.$key.'">' . trim($options[$key]) . '</textarea>';
						echo '<br>' . $feature['description'];
					}


				break;

			case 'watcher':
				$options['parameters'] = htmlentities(stripslashes($options['parameters']));
				$options['layoutCode'] = htmlentities(stripslashes($options['layoutCode']));
				$options['watchStyle'] = htmlentities(stripslashes($options['watchStyle']));


?>
<h3>Video Watch / Viewer</h3>
Settings for video subscribers that watch the live channels using the advanced watch video & chat or plain video interface (VideoWhisper Flash based applications for PC browsers). These settings do not apply for external apps or HTML5 alternatives (HLS, MPEG-DASH, WebRTC).

<h4>Who can watch video</h4>
<select name="canWatch" id="canWatch">
  <option value="all" <?php echo $options['canWatch']=='all'?"selected":""?>>Anybody</option>
  <option value="members" <?php echo $options['canWatch']=='members'?"selected":""?>>All Members</option>
  <option value="list" <?php echo $options['canWatch']=='list'?"selected":""?>>Members in List</option>
</select>
<h4>Members allowed to watch video (comma separated usernames, roles, IDs)</h4>
<textarea name="watchList" cols="100" rows="3" id="watchList"><?php echo $options['watchList']?>
</textarea>

<h3>Flash Video Watch / Viewer</h3>
These settings apply to VideoWhisper Flash player apps, for PC Flash plugin.

<h4>Parameters for Watch and Video Interfaces</h4>
<textarea name="parameters" id="parameters" cols="100" rows="4"><?php echo $options['parameters']?></textarea>
<br>For more details see <a href="https://videowhisper.com/?p=php+live+streaming#integrate">PHP Live Streaming documentation</a>.
<br>When using HTML AJAX chat, use short i.e. externalInterval=17000 to update application chat with messages from external chat.
<br>Ex: &externalInterval=360000&statusInterval=30000
 Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['parameters']?></textarea>
<br>Warning: Some parameters are controlled by plugin integration (user and room name, chat and participants panel) and should not be defined here again.

<h4>Online Expiration</h4>
<p>How long to consider viewer online if no web status update occurs.</p>
<input name="onlineExpiration0" type="text" id="onlineExpiration0" size="5" maxlength="6" value="<?php echo $options['onlineExpiration0']?>"/>s
<br>Should be 10s higher than maximum statusInterval (ms) configured in parameters. A higher statusInterval decreases web server load caused by status updates.

<h4>Custom Layout Code for Watch Interface</h4>
<textarea name="layoutCode" id="layoutCode" cols="100" rows="4"><?php echo $options['layoutCode']?></textarea>
<br>Generate by writing and sending "/videowhisper layout" in chat (contains panel positions, sizes, move and resize toggles). Copy and paste code here.
 Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['layoutCode']?></textarea>

<h4>Container Style</h4>
<textarea name="watchStyle" id="watchStyle" cols="100" rows="4"><?php echo $options['watchStyle']?></textarea>
<br>Ex: width:100%; height:400px;
 Default:<br><textarea readonly cols="100" rows="3"><?php echo $optionsDefault['watchStyle']?></textarea>

<?php
				break;

			case 'billing':
?>
<h3>Billing Settings</h3>

<h4>Enable myCRED Integration</h4>
<select name="mycred" id="mycred">
  <option value="0" <?php echo $options['mycred']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['mycred']?"selected":""?>>Yes</option>
</select>
<br>Enables interface for channel owners to setup a price and sell access to channels (as configured in Features section). Requires myCRED plugin (see below).


<h3>Setup and Configure myCRED</h3>
Follow steps below to make sure myCRED is setup and configured to manage channel access sales.

<h4>1) myCRED</h4>
<?php
				if (is_plugin_active('mycred/mycred.php')) echo 'Detected'; else echo 'Not detected. Please install and activate <a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a>!';

				if (function_exists( 'mycred_get_users_cred')) echo '<br>Testing balance: You have ' . mycred_get_users_cred() . ' points.';
?>

<p><a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a> is an adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website. The Buy Content add-on allows you to sell any publicly available post types, including video presentation posts created by this plugin. You can select to either charge users to view the content or pay the post's author either the whole sum or a percentage.<p>
<h4>2) myCRED buyCRED Module</h4>
  <?php
				if (class_exists( 'myCRED_buyCRED_Module' ) )
				{
					echo 'Detected';
?>
	<ul>
		<li>* <a href="edit.php?post_type=buycred_payment">Pending Payments</a></li>
	</ul>
					<?php
				} else echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=mycred-addons">buyCRED addon</a>!';
?>
<p>
myCRED <a href="admin.php?page=mycred-addons">buyCRED addon</a> should be enabled and at least 1 <a href="admin.php?page=mycred-gateways">payment gateway</a> configured for users to be able to buy credits.
<br>Setup a page for users to buy credits with shortcode <a target="mycred" href="http://codex.mycred.me/shortcodes/mycred_buy_form/">[mycred_buy_form]</a>.
<br>Also "Thank You Page" should be set to "Channels" and "Cancellation Page" to "Buy Credits" from <a href="admin.php?page=mycred-settings">buyCred settings</a>.</p>
<h4>3) myCRED Sell Content Module</h4>
 <?php
				if (class_exists( 'myCRED_Sell_Content_Module' ) ) echo 'Detected'; else echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=mycred-addons">Sell Content addon</a>!';
?>
<p>
myCRED <a href="admin.php?page=mycred-addons">Sell Content addon</a> should be enabled as it's required to enable certain stat shortcodes. Optionally select "<?php echo ucwords($options['custom_post'])?>" - I Manually Select as Post Types you want to sell in <a href="admin.php?page=mycred-settings">Sell Content settings tab</a> so access to channels can be sold. You can also configure payout to content author from there (Profit Share) and expiration, if necessary.
<?php
				break;

			case 'tips':
				//! Pay Per View Settings
?>
<h3>Tips</h3>
Allows viewers to send tips from watch interface. Requires billing setup.

<h4>Enable Tips</h4>
<select name="tips" id="tips">
  <option value="1" <?php echo $options['tips']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['tips']?"":"selected"?>>No</option>
</select>
<br>Allows clients to tip performers.

<h4>Tip Options</h4>
<?php
				$options['tipOptions'] = htmlentities(stripslashes($options['tipOptions']));
?>
<textarea name="tipOptions" id="tipOptions" cols="100" rows="8"><?php echo $options['tipOptions']?></textarea>
<br>List of tip options as XML. Sounds must be deployed in videowhisper/templates/live/tips folder.

<h4>Broadcaster Earning Ratio</h4>
<input name="tipRatio" type="text" id="tipRatio" size="10" maxlength="16" value="<?php echo $options['tipRatio']?>"/>
<br>Performer receives this ratio from client tip.
<br>Ex: 0.9; Set 0 to disable (performer receives nothing). Set 1 for performer to get full amount paid by client.


	<?php
				break;

			}

			if (!in_array($active_tab, array('live','stats', 'shortcodes', 'support', 'reset')) ) submit_button(); ?>

</form>
</div>
	 <?php
		}


		//! App Calls / integration, auxiliary

		function editParameters($default = '', $update = array(), $remove = array())
		{
			//adjust parameters string by update(add)/remove

			parse_str(substr($default,1), $params);

			//remove

			if (count($update)) foreach ($params as $key => $value)
					if (in_array($key, $update)) unset($params[$key]);

					if (count($remove)) foreach ($params as $key => $value)
							if (in_array($key, $remove)) unset($params[$key]);


							//add updated
							if (count($update)) foreach ($update as $key => $value) $params[$key] = $value;

								return '&' . http_build_query($params);
		}



		function webSessionSave($username, $canKick=0, $debug = "0")
		{
			//this fc generates a session file record for rtmp login check

			$username = sanitize_file_name($username);

			if ($username)
			{

				$options = get_option('VWliveStreamingOptions');
				$webKey = $options['webKey'];
				$ztime = time();

				$ztime=time();
				$info = "VideoWhisper=1&login=1&webKey=$webKey&start=$ztime&canKick=$canKick&debug=$debug";

				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);
				$dir.="/_sessions";
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);

				$dfile = fopen($dir."/$username","w");
				fputs($dfile,$info);
				fclose($dfile);
			}

		}

		function sessionUpdate($username='', $room='', $broadcaster=0, $type=1, $strict=1)
		{

			//type 1=http, 2=rtmp
			//strict = create new if not that type

			if (!$username) return;
			$ztime = time();

			global $wpdb;
			if ($broadcaster) $table_name = $wpdb->prefix . "vw_sessions";
			else $table_name = $wpdb->prefix . "vw_lwsessions";

			$cnd = '';
			if ($strict) $cnd = " AND `type`='$type'";

			//online broadcasting session
			$sqlS = "SELECT * FROM $table_name where session='$username' and status='1' $cnd ORDER BY edate DESC LIMIT 0,1";
			$session = $wpdb->get_row($sqlS);

			if (!$session)
				$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$username', '$username', '$room', '', $ztime, $ztime, 1, $type)";
			else $sql="UPDATE `$table_name` set edate=$ztime, room='$room', username='$username' where id ='".$session->id."'";
			$wpdb->query($sql);


			if ($broadcaster)
			{
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $room . "' and post_type='channel' LIMIT 0,1" );
				if ($postID) update_post_meta($postID, 'edate', $ztime);
			}

			VWliveStreaming::cleanSessions($broadcaster);

			$session = $wpdb->get_row($sqlS);
			return $session;
		}

		function cleanSessions($broadcaster=0)
		{

			$options = get_option('VWliveStreamingOptions');

			if (!VWliveStreaming::timeTo('cleanSessions'.$broadcaster, 25, $options)) return;

			$ztime = time();
			global $wpdb;

			if ($broadcaster) $table_name = $wpdb->prefix . "vw_sessions";
			else $table_name = $wpdb->prefix . "vw_lwsessions";

			if (!$options['onlineExpiration' . $broadcaster]) $options['onlineExpiration' . $broadcaster] = 310;
			$exptime=$ztime-$options['onlineExpiration' . $broadcaster];
			$sql="DELETE FROM `$table_name` WHERE edate < $exptime";
			$wpdb->query($sql);

		}

		function streamSnapshot($stream, $ipcam = false)
		{
			$stream = sanitize_file_name($stream);
			if (strstr($stream,'.php')) return;
			if (!$stream) return;

			$options = get_option('VWliveStreamingOptions');

			$dir = $options['uploadsPath'];
			if (!file_exists($dir)) mkdir($dir);
			$dir .= "/_snapshots";
			if (!file_exists($dir)) mkdir($dir);

			if (!file_exists($dir))
			{
				$error = error_get_last();
				echo 'Error - Folder does not exist and could not be created: ' . $dir . ' - '.  $error['message'];

			}

			$filename = "$dir/$stream.jpg";
			if (file_exists($filename)) if (time()-filemtime($filename) < 15) return; //do not update if fresh (15s)

				$log_file = $filename . '.txt';


			//get channel id $postID
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			//get primary stream source (rtmp/rtsp)
			$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);
			if ($sourceProtocol == 'rtsp')
				$cmd = 'timeout -s KILL 3 ' . $options['ffmpegPath'] . " -f image2 -vframes 1 \"$filename\" -y -i \"" . $options['rtsp_server'] ."/". $stream . "\" >&$log_file & ";
			else
			{
				if ($options['externalKeysTranscoder'])
				{
					$keyView = md5('vw' . $options['webKey']. $postID);
					$rtmpAddressView = $options['rtmp_server'] . '?'. urlencode('ffmpegSnap_' . $stream) .'&'. urlencode($stream) .'&'. $keyView . '&0&videowhisper';
				}
				else $rtmpAddressView = $options['rtmp_server'];

				$cmd = 'timeout -s KILL 3 ' . $options['ffmpegPath'] . " -f image2 -vframes 1 \"$filename\" -y -i \"" . $rtmpAddressView ."/". $stream . "\" >&$log_file & ";
			}


			//echo $cmd;
			exec($cmd, $output, $returnvalue);
			exec("echo '$cmd' >> $log_file.cmd", $output, $returnvalue);

			//failed
			if (!file_exists($filename)) return;

			//if snapshot successful update edate
			if ($ipcam) update_post_meta($postID, 'edate', time());

			//generate thumb
			$thumbWidth = $options['thumbWidth'];
			$thumbHeight = $options['thumbHeight'];

			$src = imagecreatefromjpeg($filename);
			list($width, $height) = getimagesize($filename);
			$tmp = imagecreatetruecolor($thumbWidth, $thumbHeight);

			$dir = $options['uploadsPath']. "/_thumbs";
			if (!file_exists($dir)) mkdir($dir);

			$thumbFilename = "$dir/$stream.jpg";
			imagecopyresampled($tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
			imagejpeg($tmp, $thumbFilename, 95);

			//update room status to 1 or 2
			$table_name3 = $wpdb->prefix . "vw_lsrooms";

			//detect tiny images without info
			if (filesize($thumbFilename)>5000) $picType = 1;
			else $picType = 2;

			$sql="UPDATE `$table_name3` set status='$picType' where name ='$stream'";
			$wpdb->query($sql);

			//update post meta
			if ($postID) update_post_meta($postID, 'hasSnapshot', $picType);

		}

		function rtmpSnapshot($session)
		{

			VWliveStreaming::streamSnapshot($session->session);
		}

		function premiumOptions($userkeys, $options)
		{

			$premiumLev = unserialize($options['premiumLevels']);

			if ($options['premiumLevelsNumber'])
				for ($i= ($options['premiumLevelsNumber']-1) ; $i >= 0 ; $i--)
				if ($premiumLev[$i]['premiumList'])
					if (VWliveStreaming::inList($userkeys, $premiumLev[$i]['premiumList'])) return $premiumLev[$i];

					//not found
					return false;
		}

		function channelOptions($type, $options)
		{
			$premiumLev = unserialize($options['premiumLevels']);

			$i = $type-2;
			if ($premiumLev[$i]) return $premiumLev[$i];

			//regular channel
			return $options;
		}

		/*
		function premiumLevel($userkeys, $options)
		{

			$premiumLev = unserialize($options['premiumLevels']);

			if ($options['premiumLevelsNumber'])
				for ($i=$options['premiumLevelsNumber'] - 1 ; $i >= 0 ; $i--)
				if ($premiumLev[$i]['premiumList'])
					if (!VWliveStreaming::inList($userkeys, $premiumLev[$i]['premiumList'])) return ($i+1);

			return 0;
		}
*/

		//! Online user functions


		function updateViewers($postID, $room, $options)
		{
			if (!VWliveStreaming::timeTo($room . '/updateViewers', 30, $options)) return;

			if (!$options) $options = get_option('VWliveStreamingOptions');

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_vmls_sessions";

			VWliveStreaming::cleanSessions(1);

			//update viewers

			$table_name2 = $wpdb->prefix . "vw_lwsessions";
			$viewers =  $wpdb->get_results("SELECT count(id) as no FROM `$table_name2` where status='1' and type='1' and room='" . $r . "'");

			update_post_meta($postID, 'viewers', $viewers);
			$maxViewers = get_post_meta($postID, 'maxViewers', true);
			if ($viewers >= $maxViewers)
			{
				update_post_meta($postID, 'maxViewers', $viewers);
				update_post_meta($postID, 'maxDate', $ztime);
			}


		}

		function timeTo($action, $expire = 60, $options='')
		{
			//if $action was already done in last $expire, return false

			if (!$options) $options = get_option('VWliveStreamingOptions');

			$cleanNow = false;


			$ztime = time();

			$lastClean = 0;
			$lastCleanFile = $options['uploadsPath'] . '/' . $action . '.txt';

			if (!file_exists($dir = dirname($lastCleanFile))) mkdir($dir);
			elseif (file_exists($lastCleanFile)) $lastClean = file_get_contents($lastCleanFile);

			if (!$lastClean) $cleanNow = true;
			else if ($ztime - $lastClean > $expire) $cleanNow = true;

				if ($cleanNow)
					file_put_contents($lastCleanFile, $ztime);


				return $cleanNow;

		}



		function userWatchLimit($user, $options)
		{
			$userLimit = $options['userWatchLimitDefault'];

			foreach ($options['userWatchLimits'] as $role => $limit)
				if (in_array(strtolower($role), $user->roles))
				{
					if (!$limit) //unlimited
						{
						$userLimit = 0;
						break; //no more search
					}

					if ($limit > $userLimit) $userLimit = $limit; //upgrade limit (best applies)

				}

			return $userLimit;
		}

		function updateUserWatchtime($user, $dS, $options)
		{
			if (!$user) return;
			if (!$user->ID) return;

			if (!$options) $options = get_option('VWliveStreamingOptions');

			//update watch time
			//check if new interval
			$lastUpdate = get_user_meta( $user->ID, 'vwls_watch_update', true );

			if ($lastUpdate < time() - $options['userWatchInterval']) //older that interval refresh
				{
				update_user_meta($user->ID, 'vwls_watch_update', time());
				update_user_meta($user->ID, 'vwls_watch', $dS);
				$currentWatch = $dS;

			}else
			{
				$currentWatch = get_user_meta( $user->ID, 'vwls_watch', true );
				$currentWatch += $dS;
				update_user_meta($user->ID, 'vwls_watch', $currentWatch);
			}

			$userLimit = VWliveStreaming::userWatchLimit($user, $options);

			if (!$userLimit) return; //unlimited

			if ($currentWatch > $userLimit) return 1; //return 1 if exceeded

			return; //limit not reached


		}


		function userParameters($user, $config)
		{
			if (!$user) return;
			if (!$user->ID) return;

			$parameters = array();

			if (is_array($config))
				foreach ($config as $parameter => $roleValue)
					foreach ($roleValue as $role => $value)
						if (in_array(strtolower($role), $user->roles)) $parameters[$parameter] = $value;

						return $parameters;

		}
		function rexit($output)
		{
			echo $output;
			exit;
		}

		/**
		 * Retrieves the best guess of the client's actual IP address.
		 * Takes into account numerous HTTP proxy headers due to variations
		 * in how different ISPs handle IP addresses in headers between hops.
		 */
		function get_ip_address() {
			$ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
			foreach ($ip_keys as $key) {
				if (array_key_exists($key, $_SERVER) === true) {
					foreach (explode(',', $_SERVER[$key]) as $ip) {
						// trim for safety measures
						$ip = trim($ip);
						// attempt to validate IP
						if (VWliveStreaming::validate_ip($ip)) {
							return $ip;
						}
					}
				}
			}
			return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
		}

		/**
		 * Ensures an ip address is both a valid IP and does not fall within
		 * a private network range.
		 */
		function validate_ip($ip)
		{
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
				return false;
			}
			return true;
		}


		function currentUserSession($room)
		{

			if (!is_user_logged_in()) return 0;

			global $current_user;
			get_currentuserinfo();

			$options = get_option('VWliveStreamingOptions');

			$username1 = $current_user->${$options['userName']};

			global $wpdb;
			$table_name = $wpdb->prefix . "vw_vwls_sessions";

			$sql = "SELECT * FROM `$table_name` WHERE session='$username1' AND room='$room' AND status='1' LIMIT 1";
			$session = $wpdb->get_row($sql);

			return $session;

		}

		//! Ajax App Calls

		static function vwls_calls()
		{
			function sanV(&$var, $file=1, $html=1, $mysql=1) //sanitize variable depending on use
				{
				if (!$var) return;

				if (get_magic_quotes_gpc()) $var = stripslashes($var);

				if ($file) $var = sanitize_file_name($var);

				if ($html&&!$file)
				{
					$var=strip_tags($var);
				}

				if ($mysql&&!$file)
				{
					$forbidden=array("'", "\"", "´", "`", "\\", "%");
					foreach ($forbidden as $search)  $var=str_replace($search,"",$var);

					$search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
					$replace = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");
					$var = str_replace($search, $replace, $var);

				}
			}

			global $wpdb;
			global $current_user;

			ob_clean();

			switch ($_GET['task'])
			{
				//! vw_snapshots
			case 'vw_snapshots':
				$options = get_option('VWliveStreamingOptions');

				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				$dir .= "/_snapshots";
				if (!file_exists($dir)) mkdir($dir);

				//get jpg bytearray
				$jpg = $GLOBALS["HTTP_RAW_POST_DATA"];
				if (!$jpg) $jpg = file_get_contents("php://input");

				if ($jpg)
				{
					$stream = $_GET['name'];
					sanV($stream);
					if (strstr($stream,'.php')) exit;
					if (!$stream) exit;

					// save file
					$filename = "$dir/$stream.jpg";
					$fp=fopen($filename ,"w");
					if ($fp)
					{
						fwrite($fp,$jpg);
						fclose($fp);
					}

					//generate thumb
					$thumbWidth = $options['thumbWidth'];
					$thumbHeight = $options['thumbHeight'];

					$src = imagecreatefromjpeg($filename);
					list($width, $height) = getimagesize($filename);
					$tmp = imagecreatetruecolor($thumbWidth, $thumbHeight);

					$dir = $options['uploadsPath']. "/_thumbs";
					if (!file_exists($dir)) mkdir($dir);

					$thumbFilename = "$dir/$stream.jpg";
					imagecopyresampled($tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
					imagejpeg($tmp, $thumbFilename, 95);

					//update room status to 1 or 2
					$table_name3 = $wpdb->prefix . "vw_lsrooms";

					//detect tiny images without info
					$snapSize = filesize($thumbFilename);
					if ($snapSize>2000) $picType = 1;
					else $picType = 2;

					$sql="UPDATE `$table_name3` set status='$picType' where name ='$stream'";
					$wpdb->query($sql);

					//update post meta
					$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($stream) . "' and post_type='channel' LIMIT 0,1" );
					if ($postID) update_post_meta($postID, 'hasSnapshot', $picType);

				}else echo 'missingJpgData=1&';

				?>loadstatus=1&snapSize=<?php echo urlencode($snapSize);
				break;

				//! lb_logout
			case 'lb_logout':
				wp_redirect( get_home_url() .'?msg='. urlencode($_GET['message']) );
				break;

				//! vw_logout
			case 'vw_logout':
				?>loggedout=1<?php
				break;

				//! vw_extregister
			case 'vw_extregister':

				$options = get_option('VWliveStreamingOptions');

				$user_name = base64_decode($_GET['u']);
				$password =  base64_decode($_GET['p']);
				$user_email = base64_decode($_GET['e']);
				if (!$_GET['videowhisper']) exit;

				$msg = '';

				$user_name = sanitize_file_name($user_name);

				$loggedin=0;
				if (username_exists($user_name)) $msg .= __('Username is not available. Choose another!');
				if (email_exists($user_email)) $msg .= __('Email is already registered.');

				if (!is_email( $user_email )) $msg .= __('Email is not valid.');


				if ($msg=='' && $user_name && $user_email && $password)
				{
					$user_id = wp_create_user( $user_name, $password, $user_email );
					$loggedin = 1;

					//create channel
					$post = array(
						'post_content'   => sanitize_text_field($_POST['description']),
						'post_name'      => $user_name,
						'post_title'     => $user_name,
						'post_author'    => $user_id,
						'post_type'      => $options['custom_post'],
						'post_status'    => 'publish',
					);

					$postID = wp_insert_post($post);

					$msg .= __('Username and channel created: ') . $user_name ;
				} else $msg .= __('Could not register account.');

				?>firstParameter=fix&msg=<?php echo urlencode($msg); ?>&loggedin=<?php echo $loggedin;?><?php

				break;

				//! vw_extlogin
			case 'vw_extlogin':

				//external login GET u=user, p=password

				$options = get_option('VWliveStreamingOptions');
				$rtmp_server = $options['rtmp_server'];
				$rtmp_amf = $options['rtmp_amf'];
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';

				$camRes = explode('x',$options['camResolutionMobile']);

				$canBroadcast = $options['canBroadcast'];
				$broadcastList = $options['broadcastList'];

				$tokenKey = $options['tokenKey'];
				$webKey = $options['webKey'];

				$loggedin=0;
				$msg="";

				$creds = array();
				$creds['user_login'] = base64_decode($_GET['u']);
				$creds['user_password'] = base64_decode($_GET['p']);
				$creds['remember'] = true;
				if (!$_GET['videowhisper']) exit;


				remove_all_actions('wp_login'); //disable redirects or other output
				$current_user = wp_signon( $creds, false );

				if( is_wp_error($current_user))
				{
					$msg = urlencode($current_user->get_error_message()) ;
					$debug = $msg;
				}
				else
				{
					//logged in
				}

				$current_user = wp_get_current_user();


				//username
				if ($current_user->$userName) $username=urlencode($current_user->$userName);
				sanV($username);


				if ($username)
				{
					switch ($canBroadcast)
					{

					case "members":
						$loggedin=1;
						break;

					case "list";
						if (VWliveStreaming::inList($username, $broadcastList)) $loggedin=1;
						else $msg .= urlencode("$username, you are not in the broadcasters list.");
						break;
					}

				}else $msg .= urlencode("Login required to broadcast.");

				if ($loggedin)
				{

					$args = array(
						'author'           => $current_user->ID,
						'orderby'          => 'post_date',
						'order'            => 'DESC',
						'post_type'        => 'channel',
					);

					$channels = get_posts( $args );
					if (count($channels))
					{

						foreach ($channels as $channel)
						{
							$username = $room = sanitize_file_name(get_the_title($channel->ID));
							$rtmp_server = VWliveStreaming::rtmp_address($current_user->ID, $channel->ID, true, $room, $room);
							break;
						}

						$canKick = 1;
						VWliveStreaming::webSessionSave($username, $canKick);
						VWliveStreaming::sessionUpdate($username, $room, 1, 2, 1);
					}
					else
					{
						$msg .= urlencode("You don't have a channel to broadcast.");
						$loggedin = 0;
					}


				}



				?>firstParameter=fix&server=<?php echo urlencode($rtmp_server); ?>&serverAMF=<?php echo $rtmp_amf?>&tokenKey=<?php echo $tokenKey?>&room=<?php echo $room?>&welcome=Welcome!&username=<?php echo $username?>&userlabel=<?php echo $userlabel?>&overLogo=<?php echo urlencode($options['overLogo'])?>&overLink=<?php echo urlencode($options['overLink'])?>&camWidth=<?php echo $camRes[0];?>&camHeight=<?php echo $camRes[1];?>&camFPS=<?php echo
				$options['camFPSMobile']?>&camBandwidth=<?php echo $options['camBandwidthMobile']?>&videoCodec=<?php echo $options['videoCodecMobile']?>&codecProfile=<?php echo $options['codecProfileMobile']?>&codecLevel=<?php echo
				$options['codecLevelMobile']?>&soundCodec=<?php echo $options['soundCodecMobile']?>&soundQuality=<?php echo $options['soundQualityMobile']?>&micRate=<?php echo
				$options['micRateMobile']?>&userType=3&msg=<?php echo $msg?>&loggedin=<?php echo $loggedin?>&loadstatus=1&debug=<?php echo $debug?><?php
				break;


				//! vw_extchat
			case 'vw_extchat':
				$options = get_option('VWliveStreamingOptions');

				$updated = $_POST['t'];
				$room = $_POST['r'];

				//do not allow uploads to other folders
				sanV($room);
				sanV($updated);

				if (!$room) exit;

				$session =  VWliveStreaming::currentUserSession($room);
				if ($session) $updated = max($session->sdate, $updated); //since user session started

				$table_chatlog = $wpdb->prefix . "vw_vwls_chatlog";

				//clean old chat logs
				$closeTime = time() - 86400; //keep for 24h
				$sql="DELETE FROM `$table_chatlog` WHERE mdate < $closeTime";
				$wpdb->query($sql);

				$chatText ='';

				$sql = "SELECT * FROM `$table_chatlog` WHERE room='$room' AND type ='2' AND mdate > $updated ORDER BY mdate DESC LIMIT 0,20";
				$sql = "SELECT * FROM ($sql) items ORDER BY mdate ASC";

				$chatRows = $wpdb->get_results($sql);

				if ($wpdb->num_rows>0) foreach ($chatRows as $chatRow)
						$chatText .= ($chatText?'<BR>':'') .'<font color="#77777">'. date('H:i:s',$chatRow->mdate) . '</font> <B>' . $chatRow->username .'</B>: '. $chatRow->message;

					?>chatText=<?php echo urlencode($chatText)?>&updateTime=<?php echo time()?><?php
					break;

			case 'vv_login':

				//! vv_login - live_video.swf
				//live_video.swf - plain video interface login

				$options = get_option('VWliveStreamingOptions');
				$rtmp_server = $options['rtmp_server'];
				$rtmp_amf = $options['rtmp_amf'];
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
				$canWatch = $options['canWatch'];
				$watchList = $options['watchList'];

				$tokenKey = $options['tokenKey'];
				$serverRTMFP = $options['serverRTMFP'];
				$p2pGroup = $options['p2pGroup'];
				$supportRTMP = $options['supportRTMP'];
				$supportP2P = $options['supportP2P'];
				$alwaysRTMP = $options['alwaysRTMP'];
				$alwaysP2P = $options['alwaysP2P'];
				$disableBandwidthDetection = $options['disableBandwidthDetection'];

				$current_user = wp_get_current_user();

				$loggedin=0;
				$msg="";
				$visitor=0;

				//username
				if ($current_user->$userName) $username=urlencode($current_user->$userName);
				$username=preg_replace("/[^0-9a-zA-Z]/","-",$username);

				//access keys
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
				}

				$roomName=$_GET['room_name'];
				sanV($roomName);
				if ($username==$roomName) $username.="_".rand(10,99);//allow viewing own room - session names must be different

				//check room
				global $wpdb;
				$table_name3 = $wpdb->prefix . "vw_lsrooms";
				$wpdb->flush();

				$sql = "SELECT * FROM $table_name3 where name='$roomName'";
				$channel = $wpdb->get_row($sql);
				// $wpdb->query($sql);

				if (!$channel)
				{
					$msg = urlencode("Channel $roomName not found. Owner must broadcast first first!");
				}
				else
				{

					if ($channel->type>=2) //premium
						{

						$poptions = VWliveStreaming::channelOptions($channel->type, $options);

						$canWatch = $poptions['canWatchPremium'];
						$watchList = $poptions['watchListPremium'];
						$msgp = urlencode(" This is a premium channel.");
					}

					switch ($canWatch)
					{
					case "all":
						$loggedin=1;
						if (!$username)
						{
							$username="VW".base_convert((time()-1224350000).rand(0,10),10,36);
							$visitor=1; //ask for username
						}
						break;
					case "members":
						if ($username) $loggedin=1;
						else $msg=urlencode("<a href=\"" . wp_login_url() . "\">Please login first or register an account if you don't have one! Click here to return to website.</a>") . $msgp;
						break;
					case "list";
						if ($username)
							if (VWliveStreaming::inList($userkeys, $watchList)) $loggedin=1;
							else $msg=urlencode("<a href=\"" . wp_login_url() . "\">$username, you are not in the allowed watchers list.</a>") . $msgp;
							else $msg=urlencode("<a href=\"" . wp_login_url() . "\">Please login first or register an account if you don't have one! Click here to return to website.</a>") . $msgp;
							break;
					}

					//channel post

					if ($loggedin) $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $roomName . "' and post_type='channel' LIMIT 0,1" );

					if ($postID)
					{
						$accessList = get_post_meta($postID, 'vw_accessList', true);
						if ($accessList) if (!VWliveStreaming::inList($userkeys, $accessList))
							{
								$loggedin = 0;
								$msg .= urlencode("<a href=\"/\">You are not in channel access list.</a>");
							}

						$vw_logo = get_post_meta( $postID, 'vw_logo', true );
						if (!$vw_logo) $vw_logo = 'global';

						switch ($vw_logo)
						{
						case 'global':
							$overLogo = $options['overLogo'];
							$overLink = $options['overLink'];
							break;

						case 'hide':
							$overLogo = '';
							$overLink = '';
							break;

						case 'custom':
							$overLogo = get_post_meta( $postID, 'vw_logoImage', true );
							$overLink = get_post_meta( $postID, 'vw_logoLink', true );
							break;
						}
					}
					else
					{
						$overLogo = $options['overLogo'];
						$overLink = $options['overLink'];
					}



				}



				$s = $username;
				$u = $username;
				$r = $roomName;
				$m = '';
				if ($loggedin) VWliveStreaming::sessionUpdate($u, $r, 0, 1, 1);

				$userType=0;
				if ($loggedin) VWliveStreaming::webSessionSave($username, 0); //approve session for rtmp check

				$parameters = html_entity_decode($options['parameters']);

				?>firstParameter=fix&server=<?php echo $rtmp_server?>&serverAMF=<?php echo $rtmp_amf?>&tokenKey=<?php echo $tokenKey?>&serverRTMFP=<?php echo urlencode($serverRTMFP)?>&p2pGroup=<?php echo
				$p2pGroup?>&supportRTMP=<?php echo $supportRTMP?>&supportP2P=<?php echo $supportP2P?>&alwaysRTMP=<?php echo $alwaysRTMP?>&alwaysP2P=<?php echo $alwaysP2P?>&disableBandwidthDetection=<?php echo
				$disableBandwidthDetection?>&username=<?php echo $username?>&userType=<?php echo $userType?>&msg=<?php echo $msg?>&loggedin=<?php echo
				$loggedin?>&visitor=<?php echo $visitor?>&overLogo=<?php echo urlencode($overLogo)?>&overLink=<?php echo
				urlencode($overLink); echo $parameters; ?>&loadstatus=1&debug=<?php echo $debug;  ?><?php
				break;

			case 'css':
				$options = get_option('VWliveStreamingOptions');
				echo html_entity_decode(stripslashes($options['cssCode']));
				break;

			case 'vs_login':
				//! vs_login - live_watch.swf

				//vs_login.php controls watch interface (video & chat & user list) login

				$options = get_option('VWliveStreamingOptions');
				$rtmp_server = $options['rtmp_server'];
				$rtmp_amf = $options['rtmp_amf'];
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
				$canWatch = $options['canWatch'];
				$watchList = $options['watchList'];

				$tokenKey = $options['tokenKey'];
				$serverRTMFP = $options['serverRTMFP'];
				$p2pGroup = $options['p2pGroup'];
				$supportRTMP = $options['supportRTMP'];
				$supportP2P = $options['supportP2P'];
				$alwaysRTMP = $options['alwaysRTMP'];
				$alwaysP2P = $options['alwaysP2P'];
				$disableBandwidthDetection = $options['disableBandwidthDetection'];

				$sendTip = $options['tips'];


				$current_user = wp_get_current_user();

				$loggedin=0;
				$msg="";
				$visitor=0;

				//username
				if ($current_user->$userName) $username=urlencode($current_user->$userName);
				$username=preg_replace("/[^0-9a-zA-Z]/","-",$username);

				//access keys
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->display_name;
				}

				$roomName=$_GET['room_name'];
				sanV($roomName);

				if ($username==$roomName) $username.="_".rand(10,99);//allow viewing own room - session names must be different

				$ztime=time();

				//check room
				global $wpdb;
				$table_name3 = $wpdb->prefix . "vw_lsrooms";
				$wpdb->flush();

				$sql = "SELECT * FROM $table_name3 where name='$roomName'";
				$channel = $wpdb->get_row($sql);
				$wpdb->query($sql);

				if (!$channel)
				{
					$msg = urlencode("Channel $roomName not found!");
				}
				else
				{

					if ($channel->type>=2) //premium
						{
						$poptions = VWliveStreaming::channelOptions($channel->type, $options);

						$canWatch = $poptions['canWatchPremium'];
						$watchList = $poptions['watchListPremium'];
						$msgp = urlencode(" This is a premium channel.");
					}


					switch ($canWatch)
					{
					case "all":
						$loggedin=1;
						if (!$username)
						{
							$username="VW".base_convert((time()-1224350000).rand(0,10),10,36);
							$visitor=1; //ask for username
							$sendTip=0;
						}
						break;
					case "members":
						if ($username) $loggedin=1;
						else $msg=urlencode("<a href=\"" . wp_login_url() . "\">Please login first or register an account if you don't have one! Click here to return to website.</a>") . $msgp;
						break;
					case "list";
						if ($username)
							if (VWliveStreaming::inList($userkeys, $watchList)) $loggedin=1;
							else $msg=urlencode("<a href=\"" . wp_login_url() . "\">$username, you are not in the allowed watchers list.</a>") . $msgp;
							else $msg=urlencode("<a href=\"" . wp_login_url() . "\">Please login first or register an account if you don't have one! Click here to return to website.</a>") . $msgp;
							break;
					}

					//channel features

					$disableChat = 0;
					$disableUsers = 0;
					$writeText = 1;
					$privateTextchat = 1;


					if ($loggedin) $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $roomName . "' and post_type='channel' LIMIT 0,1" );

					if ($postID)
					{
						$accessList = get_post_meta($postID, 'vw_accessList', true);
						if ($accessList) if (!VWliveStreaming::inList($userkeys, $accessList))
							{
								$loggedin = 0;
								$msg .= urlencode("<a href=\"/\">You are not in channel access list.</a>");
							}

						//reload playlist if updated
						$reloadPlaylist = 0;

						if ($loggedin)
						{
							$playlistActive = get_post_meta( $postID, 'vw_playlistActive', true );
							$playlistLoaded = get_post_meta( $postID, 'vw_playlistLoaded', true );

							//activated or loaded and inactive
							if ($playlistActive || $playlistLoaded)
							{
								$streamsPath = VWliveStreaming::fixPath($options['streamsPath']);
								$smilPath = $streamsPath . 'playlist.smil';

								if (filemtime($smilPath) > $playlistLoaded)
									if (VWliveStreaming::timeTo($roomName . '/playlistReload', 5, $options))
									{
										$reloadPlaylist = 1;
										update_post_meta( $postID, 'vw_playlistLoaded', time() );

									}
							}
						}

						//other permissions
						foreach (array('chat','write','participants','privateChat') as $field)
						{
							$value = get_post_meta($postID, 'vw_'.$field.'List', true);
							if ($value) if (!VWliveStreaming::inList($userkeys, $value))
									switch ($field)
									{
									case 'chat':
										$disableChat = 1;
										break;

									case 'write':
										$writeText = 0;
										break;

									case 'participants':
										$disableUsers = 1;
										break;

									case 'privateChat':
										$privateTextchat = 0;
										break;
									}
						}


						$vw_logo = get_post_meta( $postID, 'vw_logo', true );
						if (!$vw_logo) $vw_logo = 'global';

						switch ($vw_logo)
						{
						case 'global':
							$overLogo = $options['overLogo'];
							$overLink = $options['overLink'];
							break;

						case 'hide':
							$overLogo = '';
							$overLink = '';
							break;

						case 'custom':
							$overLogo = get_post_meta( $postID, 'vw_logoImage', true );
							$overLink = get_post_meta( $postID, 'vw_logoLink', true );
							break;
						}

						$vw_ads = get_post_meta( $postID, 'vw_ads', true );
						if (!$vw_ads) $vw_ads = 'global';

						switch ($vw_ads)
						{
						case 'global':
							$adsServer =$options['adServer'];
							break;

						case 'hide':
							$adsServer = '';

							break;

						case 'custom':
							$adsServer = get_post_meta( $postID, 'vw_adsServer', true );
							break;
						}

					}
					else
					{
						$overLogo = $options['overLogo'];
						$overLink = $options['overLink'];
						$adsServer =$options['adServer'];
					}


				}

				if ($loggedin)
				{
					//user picture and  profile link
					if ($current_user->ID > 0)
					{
						if ($options['userPicture'] == 'avatar') $userPicture = urlencode(get_avatar_url($current_user->ID, array('size' => 150) ));
						if ($options['profilePrefix']) $userLink = urlencode($options['profilePrefix'] . $username);
					}

				}

				//default stream to play
				$streamName = $roomName;

				//get compatible stream
				$sourceProtocol = get_post_meta($postID, 'stream-protocol', true);
				if ($sourceProtocol == 'rtsp') //requires transcoding
					{
					$stream_hls = get_post_meta( $postID, 'stream-hls',  true );
					if ($stream_hls) $streamName = $stream_hls;
				}

				$s = $username;
				$u = $username;
				$m = '';
				$r = $roomName;
				if ($loggedin) VWliveStreaming::sessionUpdate($u, $r, 0, 1, 1);


				$userType=0;
				$canKick = 0;
				if ($loggedin) VWliveStreaming::webSessionSave($username, 0); //approve session for rtmp check

				//replace bad words or expressions
				$filterRegex=urlencode("(?i)(fuck|cunt)(?-i)");
				$filterReplace=urlencode(" ** ");

				if (!$welcome) $welcome="Welcome on <B>".$roomName."</B> live streaming channel!";

				$parameters = html_entity_decode($options['parameters']);
				$layoutCode = html_entity_decode($options['layoutCode']);

				//user notifications
				if ($current_user) if ($current_user->ID)
					{

						$watchRoleParameters = VWliveStreaming::userParameters($current_user, $options['watchRoleParameters']);
						$parametersCode = VWliveStreaming::editParameters($parametersCode, $watchRoleParameters);

						if ($sendTip)
						{
							$balance = VWliveStreaming::balance($current_user->ID);

							if ($balance>0) $welcome.= '<BR>* You can send tips. Your starting balance is: ' . $balance;
							else
							{
								$welcome.= '<BR>* You can not send tips because you do not have any credits.';
								$sendTip = 0;
							}
						}

						if ($options['userWatchLimit'])
						{
							$userWatchTime = get_user_meta( $current_user->ID, 'vwls_watch', true );
							if ($userWatchTime) $welcome.= '<BR>* You watched ' . number_format($userWatchTime/60,2) . ' minutes since ' . date("F j, Y, g:i a", get_user_meta( $current_user->ID, 'vwls_watch_update', true )) .'.';

						}
					}

				$parametersCode ='&disableChat=' . $disableChat . '&disableUsers=' . $disableUsers . '&writeText=<' . $writeText . '&privateTextchat=' . $privateTextchat . '&overLogo=' . urlencode($overLogo) . '&overLink=' . urlencode($overLink) . '&layoutCode=' . urlencode($layoutCode) . '&filterRegex=' . $filterRegex . '&filterReplace=' .$filterReplace . '&ws_ads=' . urlencode($adsServer) . '&sendTip=' . $sendTip . '&reloadPlaylist=' . $reloadPlaylist . '&loaderImage=' . urlencode($options['loaderImage']) . '&adsInterval=' . $options['adsInterval'].'&streamName=' . urlencode($streamName). $parameters;

				if ($current_user) if ($current_user->ID)
					{

						$watchRoleParameters = VWliveStreaming::userParameters($current_user, $options['watchRoleParameters']);
						$parametersCode = VWliveStreaming::editParameters($parametersCode, $watchRoleParameters);
					}

				?>firstParameter=fix&server=<?php echo $rtmp_server?>&serverAMF=<?php echo $rtmp_amf?>&tokenKey=<?php echo $tokenKey?>&serverRTMFP=<?php echo urlencode($serverRTMFP)?>&p2pGroup=<?php echo
				$p2pGroup?>&supportRTMP=<?php echo $supportRTMP?>&supportP2P=<?php echo $supportP2P?>&alwaysRTMP=<?php echo $alwaysRTMP?>&alwaysP2P=<?php echo $alwaysP2P?>&disableBandwidthDetection=<?php echo
				$disableBandwidthDetection?>&welcome=<?php echo urlencode($welcome)?>&username=<?php echo $username?>&userType=<?php echo $userType?>&userPicture=<?php echo $userPicture?>&userLink=<?php echo $userLink?>&msg=<?php echo $msg?>&loggedin=<?php
				echo $loggedin?>&visitor=<?php echo $visitor;  echo $parametersCode; ?>&loadstatus=1<?php
				break;


			case 'tips':
				$options = get_option('VWliveStreamingOptions');

				echo html_entity_decode(stripslashes($options['tipOptions']));
				break;

			case 'tip':
				$room_name = sanitize_file_name($_POST['r']);
				$caller = sanitize_file_name($_POST['s']);
				$target = sanitize_file_name($_POST['t']);

				$username = sanitize_file_name($_POST['u']);
				$private = sanitize_file_name($_POST['p']);
				$amount = floatval($_POST['a']);
				$label = sanitize_text_field($_POST['l']);
				$message = sanitize_text_field($_POST['m']);

				$sound = sanitize_file_name($_POST['snd']);

				$options = get_option('VWliveStreamingOptions');

				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_name = \'' . $room_name . '\' and post_type=\'' . $options['custom_post']. '\' LIMIT 0,1' );

				if (!$postID) VWliveStreaming::rexit('success=0&failed=RoomNotFound-' . urlencode($room_name));
				$post = get_post( $postID );

				$current_user = wp_get_current_user();


				$balance = VWliveStreaming::balance($current_user->ID);
				if ($amount > $balance) VWliveStreaming::rexit('success=0&failed=NotEnoughFunds-' . $balance);

				$ztime = time();

				//client cost
				$paid = number_format($amount, 2, '.', '');
				VWliveStreaming::transaction('ppv_tip', $current_user->ID, - $paid, 'Tip for <a href="' . VWliveStreaming::roomURL($room_name) . '">' . $room_name.'</a>. (' .$label.')' , $ztime);

				//performer earning
				$received = number_format($amount * $options['tipRatio'], 2, '.', '');
				VWliveStreaming::transaction('ppv_tip_earning', $post->post_author, $received , 'Tip from ' . $caller .' ('.$label.')', $ztime);

				//update balance and report
				$balance = VWliveStreaming::balance($current_user->ID);

				$ownMessage = 'After tip, your balance is: ' . $balance;

				if ($sound) $soundCode = "sound://$sound;;";
				$publicMessage = $soundCode. '<B>Tip from ' . $username . '</B>: ' . $label . " ($paid)";

				$privateMessage = '<B>' . $username . ' (Tip '.$paid.')</B>: ' . $message;

				echo 'success=1&amount=' . $paid . '&balance=' . $balance. '&sound=' .urlencode($sound) . '&privateMessage=' .urlencode($privateMessage). '&publicMessage=' .urlencode($publicMessage) . '&ownMessage=' .urlencode($ownMessage);

				break;

			case 'vc_login':
				//! vc_login - live_broadcast.swf
				$options = get_option('VWliveStreamingOptions');

				$rtmp_server = $options['rtmp_server'];
				$rtmp_amf = $options['rtmp_amf'];
				$canBroadcast = $options['canBroadcast'];
				$broadcastList = $options['broadcastList'];

				$tokenKey = $options['tokenKey'];
				$webKey = $options['webKey'];

				$serverRTMFP = $options['serverRTMFP'];
				$p2pGroup = $options['p2pGroup'];
				$supportRTMP = $options['supportRTMP'];
				$supportP2P = $options['supportP2P'];
				$alwaysRTMP = $options['alwaysRTMP'];
				$alwaysP2P = $options['alwaysP2P'];
				$disableBandwidthDetection = $options['disableBandwidthDetection'];

				$camRes = explode('x',$options['camResolution']);

				$current_user = wp_get_current_user();

				$loggedin=0;
				$msg="";

				//username
				$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
				if ($current_user->$userName) $username=urlencode($current_user->$userName);
				sanV($username);


				//broadcaster room
				$userlabel="";
				$room_name=$_GET['room_name'];
				sanV($room_name);

				if ($room_name&&$room_name!=$username)
				{
					$userlabel=$username;
					$username=$room_name;
					$room=$room_name;
				}

				if (!$room) $room = $username;

				//access keys
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->display_name;
				}

				switch ($canBroadcast)
				{
				case "members":
					if ($username) $loggedin=1;
					else $msg=urlencode("<a href=\"" . wp_login_url() . "\">Please login first or register an account if you don't have one! Click here to return to website.</a>");
					break;
				case "list";
					if ($username)
						if (VWliveStreaming::inList($userkeys, $broadcastList)) $loggedin=1;
						else $msg=urlencode("<a href=\"" . wp_login_url() . "\">$username, you are not in the broadcasters list.</a>");
						else $msg=urlencode("<a href=\"" . wp_login_url() . "\">Please login first or register an account if you don't have one! Click here to return to website.</a>");
						break;
				}

				//channel features
				if ($loggedin) $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $room . "' and post_type='channel' LIMIT 0,1" );

				if ($postID)
				{
					$vw_logo = get_post_meta( $postID, 'vw_logo', true );
					if (!$vw_logo) $vw_logo = 'global';

					switch ($vw_logo)
					{
					case 'global':
						$overLogo = $options['overLogo'];
						$overLink = $options['overLink'];
						break;

					case 'hide':
						$overLogo = '';
						$overLink = '';
						break;

					case 'custom':
						$overLogo = get_post_meta( $postID, 'vw_logoImage', true );
						$overLink = get_post_meta( $postID, 'vw_logoLink', true );
						break;
					}
				}
				else
				{
					$overLogo = $options['overLogo'];
					$overLink = $options['overLink'];
				}


				$debug = "$postID-$vw_logo";

				if (!$room)
				{
					$loggedin=0;
					$msg=urlencode("<a href=\"/\">Can't enter: Room missing!</a>");
				}

				if (!$username)
				{
					$loggedin=0;
					$msg=urlencode("<a href=\"/\">Can't enter: Username missing!</a>");
				}


				//channel name
				if ($loggedin)
				{
					global $wpdb;
					$table_name3 = $wpdb->prefix . "vw_lsrooms";

					$wpdb->flush();
					$ztime=time();

					//setup/update channel, premium & time reset

					$poptions = VWliveStreaming::premiumOptions($userkeys, $options);

					if ($poptions) //premium room
						{
						$rtype = 1 + $poptions['level'];
						$camBandwidth = $poptions['pCamBandwidth'];
						$camMaxBandwidth = $poptions['pCamMaxBandwidth'];
						//if (!$options['pLogo']) $options['overLogo']=$options['overLink']='';
					}else
					{
						$rtype=1;
						$camBandwidth=$options['camBandwidth'];
						$camMaxBandwidth=$options['camMaxBandwidth'];
					}

					$sql = "SELECT * FROM $table_name3 where owner='$username' and name='$room'";
					$channel = $wpdb->get_row($sql);

					if (!$channel)
						$sql="INSERT INTO `$table_name3` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('$username', '$room', $ztime, $ztime, $ztime, 0, $rtype)";
					elseif ($options['timeReset'] && $channel->rdate < $ztime - $options['timeReset']*24*3600) //time to reset in days
						$sql="UPDATE `$table_name3` set edate=$ztime, type=$rtype, rdate=$ztime, wtime=0, btime=0 where owner='$username' and name='$room'";
					else
						$sql="UPDATE `$table_name3` set edate=$ztime, type=$rtype where owner='$username' and name='$room'";

					$wpdb->query($sql);
				}


				if ($loggedin) VWliveStreaming::sessionUpdate($username, $room, 1, 1, 1);

				if ($loggedin) VWliveStreaming::webSessionSave($username, 1); //approve session for rtmp check

				if ($loggedin && $postID)
				{
					update_post_meta($postID, 'stream-protocol', 'rtmp');
					update_post_meta($postID, 'stream-type', 'flash');
				}

				$uploadsPath = $options['uploadsPath'];
				if (!$uploadsPath) { $upload_dir = wp_upload_dir(); $uploadsPath = $upload_dir['basedir'] . '/vwls'; }

				$day = date("y-M-j",time());
				$chatlog_url = VWliveStreaming::path2url($uploadsPath."/$room/Log$day.html");

				$swfurlp = "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vwls&task=');
				$swfurlp .= '&extension='.urlencode('_none_');
				$swfurlp .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'ls/');

				$linkcode= VWliveStreaming::roomURL($username);

				$imagecode=VWliveStreaming::path2url($uploadsPath."/_snapshots/".urlencode($username).".jpg");

				$base = plugin_dir_url(__FILE__) . "ls/";
				$swfurl= plugin_dir_url(__FILE__) . "ls/live_watch.swf?ssl=1&n=".urlencode($username) . $swfurlp;
				$swfurl2=plugin_dir_url(__FILE__) . "ls/live_video.swf?ssl=1&n=".urlencode($username) . $swfurlp;

				$embedcode = VWliveStreaming::flash_watch($username);
				$embedvcode = VWliveStreaming::flash_video($username);


				if ($options['externalKeys']) $rtmp_server = VWliveStreaming::rtmp_address($current_user->ID, $postID, true, $stream, $stream);


				$chatlog="The transcript log of this chat is available at <U><A HREF=\"$chatlog_url\" TARGET=\"_blank\">$chatlog_url</A></U>.";
				if (!$welcome) $welcome="Welcome to broadcasting interface for channel '$room'! . $chatlog";

				$parameters = html_entity_decode($options['parametersBroadcaster']);

				if ($options['manualArchiving'])
				{
					$manualArchivingStart = $options['manualArchiving'] . '&action=startRecording&streamname=' . urlencode($username);
					$manualArchivingStop = $options['manualArchiving'] . '&action=stopRecording&streamname=' . urlencode($username);
				}
				if ($current_user->ID > 0)
				{
					$userPicture = urlencode(get_the_post_thumbnail_url($postID));
					if ($options['profilePrefixChannel']) $userLink = urlencode($options['profilePrefixChannel'] . $username);
					else $userLink = $linkcode;
				}

				$layoutCodeBroadcaster = html_entity_decode($options['layoutCodeBroadcaster']);


				//warn if HTTPS missing
				if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off")
					$welcome.= '<br><B>Warning: HTTPS not detected. Some browsers like Chrome will not permit webcam access when accessing without SSL!</B>';


				?>firstParameter=fix&server=<?php echo urlencode($rtmp_server)?>&serverAMF=<?php echo $rtmp_amf?>&tokenKey=<?php echo $tokenKey?>&serverRTMFP=<?php echo urlencode($serverRTMFP)?>&p2pGroup=<?php
				echo $p2pGroup?>&supportRTMP=<?php echo $supportRTMP?>&supportP2P=<?php echo $supportP2P?>&alwaysRTMP=<?php echo $alwaysRTMP?>&alwaysP2P=<?php echo $alwaysP2P?>&disableBandwidthDetection=<?php echo
				$disableBandwidthDetection?>&room=<?php echo $username?>&welcome=<?php echo urlencode($welcome); ?>&username=<?php echo $username?>&userlabel=<?php echo $userlabel?>&userPicture=<?php echo $userPicture?>&userLink=<?php echo $userLink?>&overLogo=<?php echo
				urlencode($overLogo)?>&overLink=<?php echo urlencode($overLink)?>&userType=3&webserver=&msg=<?php echo $msg?>&loggedin=<?php echo $loggedin?>&linkcode=<?php echo
				urlencode($linkcode)?>&embedcode=<?php echo urlencode($embedcode)?>&embedvcode=<?php echo urlencode($embedvcode)?>&imagecode=<?php echo
				urlencode($imagecode)?>&camWidth=<?php echo $camRes[0];?>&camHeight=<?php echo $camRes[1];?>&camFPS=<?php echo
				$options['camFPS']?>&camBandwidth=<?php echo $camBandwidth?>&videoCodec=<?php echo $options['videoCodec']?>&codecProfile=<?php echo $options['codecProfile']?>&codecLevel=<?php echo
				$options['codecLevel']?>&soundCodec=<?php echo $options['soundCodec']?>&soundQuality=<?php echo $options['soundQuality']?>&micRate=<?php echo
				$options['micRate']?>&camMaxBandwidth=<?php echo
				$camMaxBandwidth?>&manualArchivingStart=<?php echo urlencode($manualArchivingStart)?>&manualArchivingStop=<?php echo urlencode($manualArchivingStop)?>&onlyVideo=<?php echo $options['onlyVideo']?>&loaderImage=<?php echo  urlencode($options['loaderImage'])?>&noEmbeds=<?php echo $options['noEmbeds'];  echo $parameters; ?>&layoutCode=<?php echo urlencode($layoutCodeBroadcaster)?>&loadstatus=1&debug=<?php echo $debug; ?><?php
				break;

				//! vc_chatlog
			case 'vc_chatlog':

				//Public and private chat logs
				$private=$_POST['private']; //private chat username, blank if public chat
				$username=$_POST['u'];
				$session=$_POST['s'];
				$room=$_POST['r'];
				$message=$_POST['msg'];
				$time=$_POST['msgtime'];

				//do not allow uploads to other folders
				sanV($room);
				sanV($private);
				sanV($session);
				if (!$room) exit;

				$message = strip_tags($message,'<p><a><img><font><b><i><u>');

				//generate same private room folder for both users
				if ($private)
				{
					if ($private>$session) $proom=$session ."_". $private; else $proom=$private ."_". $session;
				}

				$options = get_option('VWliveStreamingOptions');
				$dir=$options['uploadsPath'];
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);
				$dir.="/$room";
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);
				if ($proom) $dir.="/$proom";
				if (!file_exists($dir)) mkdir($dir);
				@chmod($dir, 0777);

				$day=date("y-M-j",time());

				$dfile = fopen($dir."/Log$day.html","a");
				fputs($dfile,$message."<BR>");
				fclose($dfile);


				//update html chat log
				$pos = strpos($message,': ')+1;
				$message = substr($message, $pos); //message without username

				if ($message)
				{
					$table_chatlog = $wpdb->prefix . "vw_vwls_chatlog";
					$ztime = time();

					$sql="INSERT INTO `$table_chatlog` ( `username`, `room`, `message`, `mdate`, `type`) VALUES ('$username', '$room', '$message', $ztime, '1')";
					$wpdb->query($sql);
				}

				?>loadstatus=1<?php
				break;

			case 'v_status':
				//watch and video interface

				/*
POST Variables:
u=Username
s=Session, usually same as username
r=Room
ct=session time (in milliseconds)
lt=last session time received from this script in (milliseconds)
*/

				$cam=$_POST['cam'];
				$mic=$_POST['mic'];

				$timeUsed=$currentTime=$_POST['ct'];
				$lastTime=$_POST['lt'];

				$s=$_POST['s'];
				$u=$_POST['u'];
				$r=$_POST['r'];
				$m=$_POST['m'];

				//sanitize variables
				sanV($s);
				sanV($u);
				sanV($r);
				sanV($m,0, 0);

				$timeUsed = (int) $timeUsed;
				$currentTime = (int) $currentTime;
				$lastTime = (int) $lastTime;

				//exit if no valid session name or room name
				if (!$s) exit;
				if (!$r) exit;

				global $wpdb;
				$table_name = $wpdb->prefix . "vw_lwsessions";
				$table_name3 = $wpdb->prefix . "vw_lsrooms";
				$wpdb->flush();

				$ztime=time();

				//room info
				$sql = "SELECT * FROM $table_name3 where name='$r'";
				$channel = $wpdb->get_row($sql);
				$wpdb->query($sql);

				if (!$channel) $disconnect = urlencode("Channel $r not found!");
				else
				{
					$ztime=time();

					//update viewer online
					$sql = "SELECT * FROM $table_name where session='$s' and status='1'";
					$session = $wpdb->get_row($sql);
					if (!$session)
					{
						$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 1)";
						$wpdb->query($sql);
						$session = $wpdb->get_row($sql);
					}
					else
					{
						$sql="UPDATE `$table_name` set edate=$ztime, room='$r', username='$u', message='$m' where session='$s' and status='1' and `type`='1'";
						$wpdb->query($sql);
					}

					VWliveStreaming::cleanSessions(0);

					//room usage
					// options in minutes
					// mysql in s
					// flash in ms (minimise latency errors)

					$options = get_option('VWliveStreamingOptions');

					if ($channel->type>=2) //premium
						{
						$poptions = VWliveStreaming::channelOptions($channel->type, $options);

						$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
						$maximumWatchTime =  60 * $poptions['pWatchTime'];
					}
					else
					{
						$maximumBroadcastTime =  60 * $options['broadcastTime'];
						$maximumWatchTime =  60 * $options['watchTime'];
					}

					$maximumSessionTime = $maximumWatchTime;


					//update time
					$expTime = $options['onlineExpiration0']+60;
					$dS = floor(($currentTime-$lastTime)/1000);

					if ($dS > $expTime || $dS<0) $disconnect = urlencode("Web server out of sync compared to online expiration setting: $dS/$expTime"); //Updates should be faster; fraud attempt?
					else
					{
						$channel->wtime += $dS;
						$timeUsed = $channel->wtime * 1000;

						if ($maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) $disconnect = urlencode("Allocated broadcasting time ended!");
						if ($maximumWatchTime && $maximumWatchTime < $channel->wtime ) $disconnect = urlencode("Allocated watch time ended!");

						$maximumSessionTime *=1000;

						//update
						$sql="UPDATE `$table_name3` set wtime = " . $channel->wtime . " where name='$r'";
						$wpdb->query($sql);

						//update post
						$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='".$options['custom_post']."' LIMIT 0,1" );
						if ($postID)
						{
							update_post_meta($postID, 'wtime', $channel->wtime);
						}

						//update user watch time, disconnect if exceeded limit
						$current_user = wp_get_current_user();
						if ($current_user)
							if (VWliveStreaming::updateUserWatchtime($current_user, $dS, $options))
								$disconnect = urlencode('Your user watch time limit was exceeded!');

					}



				}

				?>timeTotal=<?php echo $maximumSessionTime?>&timeUsed=<?php echo $timeUsed?>&lastTime=<?php echo $currentTime?>&disconnect=<?php echo $disconnect?>&dS=<?php echo $dS?>&loadstatus=1<?php
				break;

				//! rtmp_status
			case 'rtmp_status':

				$options = get_option('VWliveStreamingOptions');

				//allow such requests only if feature is enabled (by default is not)
				if ($options['webStatus'] != 'enabled') VWliveStreaming::rexit('denied=webStatusNotEnabled-' . $options['webStatus']);

				//allow only status updates from configured server IP
				if ($options['rtmp_restrict_ip'])
				{
					$allowedIPs = explode(',', $options['rtmp_restrict_ip']);
					$requestIP = VWliveStreaming::get_ip_address();

					$found = 0;
					foreach ($allowedIPs as $allowedIP)
						if ($requestIP == trim($allowedIP)) $found = 1;

						if (!$found) VWliveStreaming::rexit('denied=NotFromAllowedIP-' . $requestIP);

				} else VWliveStreaming::rexit('denied=StatusServerIPnotConfigured');


				//start logging
				$dir = $options['uploadsPath'];
				$filename1 = $dir ."/_rtmpStatus.txt";
				$dfile = fopen($filename1,"w");

				fputs($dfile,'VideoWhisper Log for RTMP Session Control'. "\r\n");
				fputs($dfile,"Server Date: ". "\r\n" . date("D M j G:i:s T Y"). "\r\n" );
				fputs($dfile, '$_POST:'. "\r\n" . serialize($_POST));


				//sessions table
				global $wpdb;
				$table_name3 = $wpdb->prefix . "vw_lsrooms";

				$wpdb->flush();
				$ztime=time();

				$controlUsers = array();
				$controlSessions = array();



				//rtpsessions
				$rtpsessiondata = stripslashes($_POST['rtpsessions']);

				if (version_compare(phpversion(), '7.0', '<')) $rtpsessions = unserialize($rtpsessiondata);  //request is from trusted server
				else $rtpsessions = unserialize($rtpsessiondata, array());


				if (is_array($rtpsessions))
					foreach ($rtpsessions as $rtpsession)
					{

						$disconnect = "";

						if (!$options['webrtc']) $disconnect = 'WebRTC is disabled.';

						$stream = $rtpsession['streamName'];
						$streamQuery = array();

						if ($rtpsession['streamQuery'])
						{

							parse_str($rtpsession['streamQuery'], $streamQuery);

							if ($userID = (int) $streamQuery['userID'] )
							{
								$user = get_userdata($userID);

								$userName =  $options['userName']; if (!$userName) $userName='user_nicename';
								if ($user->$userName) $username = urlencode($user->$userName);
							}
						}

						if ($channel_id = (int) $streamQuery['channel_id'] )
						{
							$post = get_post($channel_id);

						} else $disconnect = 'No channel ID.';

						//WebRTC session vars

						$r = $stream;
						$u = $username;

						if ($rtpsession['streamPublish'] == 'true' && $userID && !$disconnect) //WebRTC broadcaster session
							{
							$s = $stream;
							$m = 'WebRTC Broadcaster';


							$keyBroadcast = md5('vw' . $options['webKey'] . $userID  . $channel_id);
							if ($streamQuery['key'] != $keyBroadcast) $disconnect = 'WebRTC broadcast key mismatch.';

							if (!$post) $disconnect = 'Channel post not found.';
							elseif ($post->post_author != $userID) $disconnect = 'Only channel owner can broadcast.';

							if (!$disconnect)
							{
								//user online
								$table_name = $wpdb->prefix . "vw_sessions";

								$sqlS = "SELECT * FROM $table_name WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";
								$session = $wpdb->get_row($sqlS);

								if (!$session)
								{
									$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 2)";
									$wpdb->query($sql);
									$session = $wpdb->get_row($sqlS);
								}

								//generate external snapshot for external broadcaster ??
								VWliveStreaming::rtmpSnapshot($session);

								$sqlC = "SELECT * FROM $table_name3 WHERE name='" . $session->room . "' LIMIT 0,1";
								$channel = $wpdb->get_row($sqlC);

								//update session
								$sql="UPDATE `$table_name` set edate=$ztime where id='".$session->id."'";
								$wpdb->query($sql);

								if ($ban =  VWliveStreaming::containsAny($channel->name, $options['bannedNames'])) $disconnect = "Room banned ($ban)!";

								//calculate time in ms based on previous request
								$lastTime =  $session->edate * 1000;
								$currentTime = $ztime * 1000;

								//update time
								$expTime = $options['onlineExpiration1']+40;
								$dS = floor(($currentTime-$lastTime)/1000);
								if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync for broadcaster ($dS > $expTime) !"; //Updates should be faster; fraud attempt?

								$channel->btime += $dS;

								//update room
								$sql="UPDATE `$table_name3` set edate=$ztime, btime = " . $channel->btime . " where id = '" . $channel->id. "'";
								$wpdb->query($sql);

								//update post
								$postID = $channel_id;

								if ($postID)
								{
									update_post_meta($postID, 'edate', $ztime);
									update_post_meta($postID, 'btime', $channel->btime);

									update_post_meta($postID, 'stream-protocol', 'rtsp');

									VWliveStreaming::updateViewers($postID, $r, $options);
								}

								//transcode stream (from RTSP)
								if (!$disconnect) if ($options['transcodingAuto']>=2) VWliveStreaming::transcodeStreamWebRTC($session->room, $postID, $options);

									// room usage
									// options in minutes
									// mysql in s
									// flash in ms (minimise latency errors)

									if ($channel->type>=2) //premium
										{
										$poptions = VWliveStreaming::channelOptions($channel->type, $options);

										$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
										$maximumWatchTime =  60 * $poptions['pWatchTime'];
									}
								else
								{
									$maximumBroadcastTime =  60 * $options['broadcastTime'];
									$maximumWatchTime =  60 * $options['watchTime'];
								}

								$maximumSessionTime = $maximumBroadcastTime; //broadcaster

								$timeUsed = $channel->btime * 1000;

								if ($maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) $disconnect = "Allocated broadcasting time ended!";
								if ($maximumWatchTime && $maximumWatchTime < $channel->wtime ) $disconnect = "Allocated watch time ended!";

								$maximumSessionTime *=1000;
							}


							//end WebRTC broadcaster session
						}

						if ($rtpsession['streamPlay'] == 'true' && !$disconnect)  //webRTC playback
							{

							$s = $username .'_'. $stream;

							$table_name = $wpdb->prefix . "vw_lwsessions";

							//update viewer online
							$sqlS = "SELECT * FROM $table_name WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";

							$session = $wpdb->get_row($sqlS);
							if (!$session) //insert external viewer type=2
								{
								$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '', $ztime, $ztime, 1, 2)";
								$wpdb->query($sql);
								$session = $wpdb->get_row($sqlS);
							};


							if ($session->type == '2') //external viewer session: update here
								{

								$sqlC = "SELECT * FROM $table_name3 WHERE name='" . $session->room . "' LIMIT 0,1";
								$channel = $wpdb->get_row($sqlC);


								$sql="UPDATE `$table_name` set edate=$ztime where id='".$session->id."'";
								$wpdb->query($sql);

								//calculate time in ms based on previous request
								$lastTime =  $session->edate * 1000;
								$currentTime = $ztime * 1000;

								//update room time
								$expTime = $options['onlineExpiration0']+40;

								$dS = floor(($currentTime-$lastTime)/1000);
								if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync ($dS > $expTime)!"; //Updates should be faster than 3 minutes; disconnected and returned on same session?

								$channel->wtime += $dS;

								//update
								$sql="UPDATE `$table_name3` set wtime = " . $channel->wtime . " where id = '" . $channel->id. "'";
								$wpdb->query($sql);

								//update post
								$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='channel' LIMIT 0,1" );
								if ($postID)
								{
									update_post_meta($postID, 'wtime', $channel->wtime);
								}

								//update user watch time, disconnect if exceeded limit
								$user = get_user_by('login', $u);
								if ($user)
									if (VWliveStreaming::updateUserWatchtime($user, $dS, $options))
										$disconnect = urlencode('User watch time limit exceeded!');


							}
							// room usage
							// options in minutes
							// mysql in s
							// flash in ms (minimise latency errors)

							if ($channel->type>=2) //premium
								{
								$poptions = VWliveStreaming::channelOptions($channel->type, $options);

								$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
								$maximumWatchTime =  60 * $poptions['pWatchTime'];
							}
							else
							{
								$maximumBroadcastTime =  60 * $options['broadcastTime'];
								$maximumWatchTime =  60 * $options['watchTime'];
							}

							$maximumSessionTime = $maximumWatchTime;

							$timeUsed = $channel->wtime * 1000;

							if ($maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) $disconnect = "Allocated broadcasting time ended!";
							if ($maximumWatchTime && $maximumWatchTime < $channel->wtime ) $disconnect = "Allocated watch time ended!";

							$maximumSessionTime *=1000;

							//end WebRTC playback
						}

						fputs($dfile, "\r\n"  . 'streamPublish3: '. $s );


						$controlSession['disconnect'] = $disconnect;

						$controlSession['session'] = $s;
						$controlSession['dS'] = $dS;
						$controlSession['type'] = $session->type;
						$controlSession['room'] = $r;
						$controlSession['username'] = $u;

						$controlSessions[$rtpsession['sessionId']] = $controlSession;

						//end  foreach ($rtpsessions as $rtpsession)
					}

				$controlSessionsS = serialize($controlSessions);

				//debug update
				fputs($dfile,"\r\nControl RTP Sessions: " . "\r\n" . $controlSessionsS);



				//users - rtmp clients
				$userdata = stripslashes($_POST['users']);

				if (version_compare(phpversion(), '7.0', '<'))
					$users = unserialize($userdata);  //request is from trusted server
				else $users = unserialize($userdata, array());


				if (is_array($users))
					foreach ($users as $user)
					{

						//$rooms = explode(',',$user['rooms']); $r = $rooms[0];
						$r = $user['rooms'];
						$s = $user['session'];
						$u = $user['username'];

						$ztime=time();
						$disconnect = "";

						if ($ban =  VWliveStreaming::containsAny($s, $options['bannedNames'])) $disconnect = "Name banned ($s,$ban)!";

						//kill snap/info sessions
						if ($options['ffmpegTimeout'])
							if ($user['runSeconds']) if ($user['runSeconds']>$options['ffmpegTimeout'])
									if ( in_array(substr($user['session'],0,11), array('ffmpegSnap_', 'ffmpegInfo_')) )
									{
										$disconnect = 'FFMPEG timeout.';
									}

								if ($user['role'] == '1') //channel broadcaster
									{

									//user online
									$table_name = $wpdb->prefix . "vw_sessions";
									$sqlS = "SELECT * FROM $table_name WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";
									$session = $wpdb->get_row($sqlS);

									if (!$session) //insert as external type=2
										{
										$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 2)";
										$wpdb->query($sql);
										$session = $wpdb->get_row($sqlS);
									}


									if ($session->type == 2) //external broadcaster: update here, otherwise updates on calls from flash app
										{
										//generate external snapshot for external broadcaster
										VWliveStreaming::rtmpSnapshot($session);

										$sqlC = "SELECT * FROM $table_name3 WHERE name='" . $session->room . "' LIMIT 0,1";
										$channel = $wpdb->get_row($sqlC);

										//update session
										$sql="UPDATE `$table_name` set edate=$ztime where id='".$session->id."'";
										$wpdb->query($sql);

										if ($ban =  VWliveStreaming::containsAny($channel->name,$options['bannedNames'])) $disconnect = "Room banned ($ban)!";

										//calculate time in ms based on previous request
										$lastTime =  $session->edate * 1000;
										$currentTime = $ztime * 1000;

										//update time
										$expTime = $options['onlineExpiration1']+30;
										$dS = floor(($currentTime-$lastTime)/1000);
										if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync for broadcaster ($dS > $expTime) !"; //Updates should be faster; fraud attempt?

										$channel->btime += $dS;

										//update room
										$sql="UPDATE `$table_name3` set edate=$ztime, btime = " . $channel->btime . " where id = '" . $channel->id. "'";
										$wpdb->query($sql);

										//update post
										$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='channel' LIMIT 0,1" );
										if ($postID)
										{
											update_post_meta($postID, 'edate', $ztime);
											update_post_meta($postID, 'btime', $channel->btime);

											update_post_meta($postID, 'stream-protocol', 'rtmp');

											VWliveStreaming::updateViewers($postID, $r, $options);
										}

										//transcode stream (from RTMP)
										if (!$disconnect) if ($options['transcodingAuto']>=2) VWliveStreaming::transcodeStream($session->room);
									}

									// room usage
									// options in minutes
									// mysql in s
									// flash in ms (minimise latency errors)

									if ($channel->type>=2) //premium
										{
										$poptions = VWliveStreaming::channelOptions($channel->type, $options);

										$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
										$maximumWatchTime =  60 * $poptions['pWatchTime'];
									}
									else
									{
										$maximumBroadcastTime =  60 * $options['broadcastTime'];
										$maximumWatchTime =  60 * $options['watchTime'];
									}

									$maximumSessionTime = $maximumBroadcastTime; //broadcaster

									$timeUsed = $channel->btime * 1000;

									if ($maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) $disconnect = "Allocated broadcasting time ended!";
									if ($maximumWatchTime && $maximumWatchTime < $channel->wtime ) $disconnect = "Allocated watch time ended!";

									$maximumSessionTime *=1000;


								}
							else //subscriber viewer
								{
								$table_name = $wpdb->prefix . "vw_lwsessions";

								//update viewer online
								$sqlS = "SELECT * FROM $table_name WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";

								$session = $wpdb->get_row($sqlS);
								if (!$session) //insert external viewer type=2
									{
									$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '', $ztime, $ztime, 1, 2)";
									$wpdb->query($sql);
									$session = $wpdb->get_row($sqlS);
								};


								if ($session->type == '2') //external viewer session: update here
									{

									$sqlC = "SELECT * FROM $table_name3 WHERE name='" . $session->room . "' LIMIT 0,1";
									$channel = $wpdb->get_row($sqlC);


									$sql="UPDATE `$table_name` set edate=$ztime where id='".$session->id."'";
									$wpdb->query($sql);

									//calculate time in ms based on previous request
									$lastTime =  $session->edate * 1000;
									$currentTime = $ztime * 1000;

									//update room time
									$expTime = $options['onlineExpiration0']+30;

									$dS = floor(($currentTime-$lastTime)/1000);
									if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync ($dS > $expTime)!"; //Updates should be faster than 3 minutes; fraud attempt?

									$channel->wtime += $dS;

									//update
									$sql="UPDATE `$table_name3` set wtime = " . $channel->wtime . " where id = '" . $channel->id. "'";
									$wpdb->query($sql);

									//update post
									$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='channel' LIMIT 0,1" );
									if ($postID)
									{
										update_post_meta($postID, 'wtime', $channel->wtime);
									}

									//update user watch time, disconnect if exceeded limit
									$user = get_user_by('login', $u);
									if ($user)
										if (VWliveStreaming::updateUserWatchtime($user, $dS, $options))
											$disconnect = urlencode('User watch time limit exceeded!');


								}
								// room usage
								// options in minutes
								// mysql in s
								// flash in ms (minimise latency errors)

								if ($channel->type>=2) //premium
									{
									$poptions = VWliveStreaming::channelOptions($channel->type, $options);

									$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
									$maximumWatchTime =  60 * $poptions['pWatchTime'];
								}
								else
								{
									$maximumBroadcastTime =  60 * $options['broadcastTime'];
									$maximumWatchTime =  60 * $options['watchTime'];
								}

								$maximumSessionTime = $maximumWatchTime;

								$timeUsed = $channel->wtime * 1000;

								if ($maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) $disconnect = "Allocated broadcasting time ended!";
								if ($maximumWatchTime && $maximumWatchTime < $channel->wtime ) $disconnect = "Allocated watch time ended!";

								$maximumSessionTime *=1000;


							}

						$controlUser['disconnect'] = $disconnect;

						$controlUser['session'] = $s;
						$controlUser['dS'] = $dS;
						$controlUser['type'] = $session->type;
						$controlUser['room'] = $session->room;
						$controlUser['username'] = $session->username;

						$controlUsers[$user['session']] = $controlUser;

					}

				$controlUsersS = serialize($controlUsers);

				//fputs($dfile,"\r\n rtpsessiondata: " . $rtpsessiondata );
				//    fputs($dfile,"\r\n rtpsessions rebuild 3: " . serialize(unserialize($rtpsessiondata, array())) );

				//fputs($dfile,"\r\n rtpsessions: " . serialize($rtpsessions) );

				fputs($dfile,"\r\nControl RTMP Users: ". "\r\n" . $controlUsersS);

				fclose($dfile);

				echo 'VideoWhisper=1&usersCount='.count($users)."&controlUsers=$controlUsersS&controlSessions=$controlSessions";

				break;
				//! rtmp_logout
			case 'rtmp_logout':

				//rtmp server notifies client disconnect here
				$session = $_GET['s'];
				sanV($session);
				if (!$session) exit;

				$options = get_option('VWliveStreamingOptions');
				$dir=$options['uploadsPath'];

				echo "logout=";
				$filename1 = $dir ."/_sessions/$session";
				if (file_exists($filename1))
				{
					echo unlink($filename1);
				}
				?><?php
				break;
				//! rtmp_login
			case 'rtmp_login':


				//rtmp server should check login like rtmp_login.php?s=$session&p[]=..
				//p[] = params sent with rtmp address (key, channel)

				$session = $_GET['s'];
				sanV($session);
				if (!$session) exit;

				$p =  $_GET['p'];

				if (count($p))
				{
					$username = $p[0];
					$room = $channel = $p[1];
					$key = $p[2];
					$broadcaster = $p[3];
					$broadcasterID = $p[4];
				}

				$postID = 0;
				$ztime = time();

				global $wpdb;
				$wpdb->flush();
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name($channel) . "' and post_type='channel' LIMIT 0,1" );

				$options = get_option('VWliveStreamingOptions');

				//global $current_user;
				//get_currentuserinfo();

				$verified = 0;

				//rtmp key login for external apps
				if ($broadcaster=='1') //external broadcaster
					{
					$validKey = md5('vw' . $options['webKey'] . $broadcasterID . $postID);
					if ($key == $validKey)
					{
						$verified = 1;

						VWliveStreaming::webSessionSave($session, 1, $key);

						//setup/update channel in sql
						global $wpdb;
						$table_name3 = $wpdb->prefix . "vw_lsrooms";
						$wpdb->flush();

						$sql = "SELECT * FROM $table_name3 where owner='$username' and name='$room'";
						$channelR = $wpdb->get_row($sql);

						if (!$channelR)
							$sql="INSERT INTO `$table_name3` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('$username', '$room', $ztime, $ztime, $ztime, 0, 1)";
						elseif ($options['timeReset'] && $channelR->rdate < $ztime - $options['timeReset']*24*3600) //time to reset in days
							$sql="UPDATE `$table_name3` set edate=$ztime, type=1, rdate=$ztime, wtime=0, btime=0 where owner='$username' and name='$room'";
						else
							$sql="UPDATE `$table_name3` set edate=$ztime where owner='$username' and name='$room'";

						$wpdb->query($sql);

						VWliveStreaming::sessionUpdate($username, $room, 1, 2, 1);
					}

				}
				elseif ($broadcaster=='0') //external watcher
					{
					$validKeyView = md5('vw' . $options['webKey']. $postID);
					if ($key == $validKeyView)
					{
						//$verified = 1;

						VWliveStreaming::webSessionSave($session, 0, $key);
						VWliveStreaming::sessionUpdate($username, $room, 0, 2, 1);
					}
					//VWliveStreaming::webSessionSave('error-'.$session, 0, "$channel-$session-$key-$postID-$validKeyView-".sanitize_file_name($channel) );

				}

				//validate web login to rtmp
				$dir = $options['uploadsPath'];
				$filename1 = $dir ."/_sessions/$session";
				if (file_exists($filename1)) //web login
					{
					echo implode('', file($filename1));
					if ($broadcaster) echo '&role=' . $broadcaster;
				}
				else
				{
					echo "VideoWhisper=1&login=0";
				}

				//also update RTMP server IP in settings after authentication
				if ($verified)
				{

					//if IP restriction not defined: configure it
					if (!$options['rtmp_restrict_ip'])
					{
						$options['rtmp_restrict_ip'] = VWliveStreaming::get_ip_address();
						$updateOptions=1;
						echo '&rtmp_restrict_ip=' . $options['rtmp_restrict_ip'];
					}

					//also enable webStatus if on auto (now secure with IP restriction enabled)
					if ($options['webStatus'] == 'auto')
					{
						$options['webStatus'] = 'enabled';
						$updateOptions=1;
						echo '&webStatus=' . $options['webStatus'];
					}

					if ($updateOptions) update_option('VWliveStreamingOptions', $options);

				}


				?><?php
				break;

			case 'lb_status':
				//! lb_status
				/*
Broadcaster status updates.

POST Variables:
u=Username
s=Session, usually same as username
r=Room
ct=session time (in milliseconds)
lt=last session time received from this script in (milliseconds)
cam, mic = 0 none, 1 disabled, 2 enabled
*/

				$cam=$_POST['cam'];
				$mic=$_POST['mic'];

				$timeUsed=$currentTime=$_POST['ct'];
				$lastTime=$_POST['lt'];

				$s=$_POST['s'];
				$u=$_POST['u'];
				$r=$_POST['r'];
				$m=$_POST['m'];

				//sanitize variables
				sanV($s);
				sanV($u);
				sanV($r);
				sanV($m,0);

				$timeUsed = (int) $timeUsed;
				$currentTime = (int) $currentTime;
				$lastTime = (int) $lastTime;

				//exit if no valid session name or room name
				if (!$s) exit;
				if (!$r) exit;

				//only registered users can broadcast
				if (!is_user_logged_in()) exit;

				$table_name = $wpdb->prefix . "vw_sessions";
				$table_name3 = $wpdb->prefix . "vw_lsrooms";
				$wpdb->flush();

				$ztime=time();

				//room info
				$sql = "SELECT * FROM $table_name3 where owner='$u' and name='$r'";
				$channel = $wpdb->get_row($sql);
				$wpdb->query($sql);

				if (!$channel) $disconnect = urlencode("Channel $r not found!");
				else
				{
					//user online
					$sql = "SELECT * FROM $table_name where session='$s' AND status='1' AND `type`='1'";
					$session = $wpdb->get_row($sql);
					if (!$session)
					{
						$sql="INSERT INTO `$table_name` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 1)";
						$wpdb->query($sql);
					}
					else
					{
						$sql="UPDATE `$table_name` set edate=$ztime, room='$r', username='$u', message='$m' where session='$s' AND status='1' AND `type`='1'";
						$wpdb->query($sql);
					}

					VWliveStreaming::cleanSessions(1);

					//room usage
					// options in minutes
					// mysql in s
					// flash in ms (minimise latency errors)

					$options = get_option('VWliveStreamingOptions');
					if ($ban =  VWliveStreaming::containsAny($s, $options['bannedNames'])) $disconnect = "Name banned ($s, $ban)!";
					if ($ban =  VWliveStreaming::containsAny($r, $options['bannedNames'])) $disconnect = "Room banned ($r, $ban)!";

					if ($channel->type>=2) //premium
						{
						$poptions = VWliveStreaming::channelOptions($channel->type, $options);

						$maximumBroadcastTime =  60 * $poptions['pBroadcastTime'];
						$maximumWatchTime =  60 * $poptions['pWatchTime'];
					}
					else
					{
						$maximumBroadcastTime =  60 * $options['broadcastTime'];
						$maximumWatchTime =  60 * $options['watchTime'];
					}

					$maximumSessionTime = $maximumBroadcastTime; //broadcaster

					//update time
					$expTime = $options['onlineExpiration1']+30;
					$dS = floor(($currentTime-$lastTime)/1000);

					if ($dS>$expTime || $dS<0) $disconnect = urlencode("Web server out of sync! ($dS>$expTime)" ); //Updates should be faster than 3 minutes; fraud attempt?
					else
					{
						$channel->btime += $dS;
						$timeUsed = $channel->btime * 1000;

						if ($maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) $disconnect = urlencode("Allocated broadcasting time ended!");
						if ($maximumWatchTime && $maximumWatchTime < $channel->wtime ) $disconnect = urlencode("Allocated watch time ended!");

						$maximumSessionTime *=1000;

						//update
						$sql="UPDATE `$table_name3` set edate=$ztime, btime = " . $channel->btime . " where owner='$u' and name='$r'";
						$wpdb->query($sql);

						//transcode if necessary
						if (!$disconnect) if ($options['transcodingAuto']>=2) VWliveStreaming::transcodeStream($r);

							//update post
							$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='channel' LIMIT 0,1" );
						if ($postID)
						{
							update_post_meta($postID, 'edate', $ztime);
							update_post_meta($postID, 'btime', $channel->btime);

							VWliveStreaming::updateViewers($postID, $r, $options);

						}

					}

				}


				?>timeTotal=<?php echo $maximumSessionTime?>&timeUsed=<?php echo $timeUsed?>&lastTime=<?php echo $currentTime?>&disconnect=<?php echo $disconnect?>&loadstatus=1<?php
				break;
				//! translation
			case 'translation':
?>

               <translations>
<?php
				$options = get_option('VWliveStreamingOptions');
				echo html_entity_decode(stripslashes($options['translationCode']));
?>
</translations>
			<?php
				break;
				//! ads
			case 'ads':

				/* Sample local ads serving script ; Or use http://adinchat.com compatible ads server to setup http://adinchat.com/v/your-campaign-id

POST Variables:
u=Username
s=Session, usually same as username
r=Room
ct=session time (in milliseconds)
lt=last session time received (from web status script)

*/

				$room=$_POST[r];
				$session=$_POST[s];
				$username=$_POST[u];

				$currentTime=$_POST[ct];
				$lastTime=$_POST[lt];

				$ztime=time();

				$options = get_option('VWliveStreamingOptions');

				global $wpdb;
				$table_name3 = $wpdb->prefix . "vw_lsrooms";

				$sql = "SELECT * FROM $table_name3 where name='$room'";
				$channel = $wpdb->get_row($sql);
				// $wpdb->query($sql);

				if ($channel)
					if ($channel->type>=2)
					{
						$ad = '';
						$debug='premiumChannel';
					}
				else $ad = urlencode(html_entity_decode(stripslashes($options['adsCode'])));
				else $debug='noChannel';


				?>x=1&ad=<?php echo $ad; ?>&loadstatus=1<?php echo '&debug=' . $debug;
				break;
			} //end case
			die();
		}
	}

}

//instantiate
if (class_exists("VWliveStreaming")) {
	$liveStreaming = new VWliveStreaming();
}

//Actions and Filters
if (isset($liveStreaming)) {

	register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	register_activation_hook( __FILE__, array(&$liveStreaming, 'install' ) );

	add_action( 'init', array(&$liveStreaming, 'init'));
	add_action( 'parse_request', array(&$liveStreaming, 'parse_request'));

	add_action("plugins_loaded", array(&$liveStreaming, 'plugins_loaded'));
	add_action('admin_menu', array(&$liveStreaming, 'admin_menu'));
	add_action('admin_head', array(&$liveStreaming, 'admin_head'));
	add_action( 'admin_init', array(&$liveStreaming, 'admin_init'));

	add_action( 'login_enqueue_scripts', array('VWliveStreaming','login_enqueue_scripts') );
	add_filter( 'login_headerurl', array('VWliveStreaming','login_headerurl'));


	/* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
	function liveStreamingBP_init()
	{
		if (class_exists('BP_Group_Extension')) require( dirname( __FILE__ ) . '/bp.php' );
	}

	add_action( 'bp_init', 'liveStreamingBP_init' );

	add_filter( "single_template", array(&$liveStreaming,'single_template') );

}
?>
