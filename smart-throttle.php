<?php
/*
Plugin Name: Smart Throttle
Plugin URI: http://mohanjith.com/wordpress/smart-throttle.html
Description: Smart Throttle plugin dynamically throttles comment flood.
Author: S H Mohanjith
Version: 1.0.2
Author URI: http://mohanjith.com/
License: GPL
*/

class smart_throttle_plugin {

	private static $translation_domain = 'smart_throttle_trans_domain';

	public function __construct() {
		add_option('smart_throttle_tier1_gt', 0);
        add_option('smart_throttle_tier1_lt', 6);
        add_option('smart_throttle_tier1_timeout', 15);
        add_option('smart_throttle_tier1_timeout_increment', 'false');
        add_option('smart_throttle_tier2_gt', 5);
        add_option('smart_throttle_tier2_lt', 15);
        add_option('smart_throttle_tier2_timeout', 5);
        add_option('smart_throttle_tier2_timeout_increment', 'true');
        add_option('smart_throttle_tier3_gt', 14);
        add_option('smart_throttle_tier3_lt', '∞');
        add_option('smart_throttle_tier3_timeout', 60);
        add_option('smart_throttle_tier3_timeout_increment', 'true');

        add_action('comment_flood_filter', array(&$this, 'smart_throttle_comment_flood'), 9, 4);
		add_action('check_comment_flood', array(&$this, 'smart_throttle_check_comment_flood'), 9, 3);

		add_filter('admin_menu', array(&$this, 'admin_menu'));

		load_plugin_textdomain(self::$translation_domain, PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)), dirname(plugin_basename(__FILE__)));
	}

	/**
	 * Determine comment should be blocked because of comment flood (Determined by comments/h).
	 *
	 * Upto 5 comments/h - 15s
	 * From 6-14 comments/h 5s increment
	 * Every comment/h there after 60s increment.
	 *
	 * @param bool $block Whether plugin has already blocked comment.
	 * @param int $time_lastcomment Timestamp for last comment.
	 * @param int $time_newcomment Timestamp for new comment.
	 * @param int $comments_per_hour Rate of comments for the last hour.
	 * @return bool Whether comment should be blocked.
	 */
	public function smart_throttle_comment_flood($block, $time_lastcomment, $time_newcomment, $comments_per_hour=0) {
		if ( $block ) // a plugin has already blocked... we'll let that decision stand
			return $block;

		$timeout = $this->_calculate_timeout($comments_per_hour);

		if ( ($time_newcomment - $time_lastcomment) < $timeout )
			return true;
		return false;
	}

	private function _calculate_timeout($comments_per_hour) {
		$_timeout = 15;
		for ($i=0; $i<=3; $i++) {
			if (get_option('smart_throttle_tier'.($i+1).'_lt') == '∞') {
				if ($comments_per_hour > intval(get_option('smart_throttle_tier'.($i+1).'_gt'))) {
					if (get_option('smart_throttle_tier'.($i+1).'_timeout_increment') == 'true') {
						$_timeout = $this->_calculate_timeout(get_option('smart_throttle_tier'.($i+1).'_gt'))+
								   (($comments_per_hour-intval(get_option('smart_throttle_tier'.($i+1).'_gt')))*intval(get_option('smart_throttle_tier'.($i+1).'_timeout')));
					} else {
						$_timeout = intval(get_option('smart_throttle_tier'.($i+1).'_timeout'));
					}
					break;
				}
			} else {
				if ($comments_per_hour > intval(get_option('smart_throttle_tier'.($i+1).'_gt')) and $comments_per_hour < intval(get_option('smart_throttle_tier'.($i+1).'_lt'))) {
					if (get_option('smart_throttle_tier'.($i+1).'_timeout_increment') == 'true') {
						$_timeout = $this->_calculate_timeout(get_option('smart_throttle_tier'.($i+1).'_gt'))+
								   (($comments_per_hour-intval(get_option('smart_throttle_tier'.($i+1).'_gt')))*intval(get_option('smart_throttle_tier'.($i+1).'_timeout')));
					} else {
						$_timeout = intval(get_option('smart_throttle_tier'.($i+1).'_timeout'));
					}
					break;
				}
			}
		}
		return $_timeout;
	}

	/**
	 * Check whether comment flooding is occurring.
	 *
	 * Won't run, if current user can manage options, so to not block
	 * administrators.
	 *
	 * @uses $wpdb
	 * @uses apply_filters() Calls 'comment_flood_filter' filter with first
	 *		parameter false, last comment timestamp, new comment timestamp.
	 * @uses do_action() Calls 'comment_flood_trigger' action with parameters with
	 *		last comment timestamp and new comment timestamp.
	 *
	 * @param string $ip Comment IP.
	 * @param string $email Comment author email address.
	 * @param string $date MySQL time string.
	 */
	public function smart_throttle_check_comment_flood( $ip, $email, $date ) {
		global $wpdb;
		if ( current_user_can( 'manage_options' ) )
			return; // don't throttle admins
		if ( $lasttime = $wpdb->get_var( $wpdb->prepare("SELECT comment_date_gmt FROM $wpdb->comments WHERE comment_author_IP = %s OR comment_author_email = %s ORDER BY comment_date DESC LIMIT 1", $ip, $email) ) ) {
			$time_lastcomment = mysql2date('U', $lasttime);
			$time_newcomment  = mysql2date('U', $date);
			$hour_before = date('Y-m-d H:i:s', time()-3600);
			$comments_per_hour = $wpdb->get_var( $wpdb->prepare("SELECT count(comment_ID) FROM $wpdb->comments WHERE (comment_author_IP = %s OR comment_author_email = %s) AND comment_date > '%s'", $ip, $email, $hour_before) );
			$flood_die = apply_filters('comment_flood_filter', false, $time_lastcomment, $time_newcomment, $comments_per_hour);
			if ( $flood_die ) {
				do_action('comment_flood_trigger', $time_lastcomment, $time_newcomment);

				if ( defined('DOING_AJAX') )
					die( __('You are posting comments too quickly.  Slow down.') );

				wp_die( __('You are posting comments too quickly.  Slow down.'), '', array('response' => 403) );
			}
		}
	}

	public function admin_menu() {
		add_options_page('Smart Throttle Plugin Options', 'Smart Throttle', 8, __FILE__, array(&$this, 'plugin_options'));
	}

	public function plugin_options() {
?>
		<div class="wrap">
		    <h2>Smart Throttle</h2>

		    <form method="post" action="options.php">
			    <?php wp_nonce_field('update-options'); ?>

				<i><?php _e("Do not edit unless you know what you are doing. We believe our time out break down is well balanced ;)", self::$translation_domain ); ?></i>
			    <table class="form-table">
			    	<tr valign="top">
			    		<th scope="row"><?php _e("Tier 1:", self::$translation_domain ); ?></th>
			    	</tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Comment rate >:", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier1_gt" value="<?php echo get_option('smart_throttle_tier1_gt'); ?>" /></td>
			        </tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Comment rate <:", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier1_lt" value="<?php echo get_option('smart_throttle_tier1_lt'); ?>" /></td>
			        </tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Time out (in seconds):", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier1_timeout" value="<?php echo get_option('smart_throttle_tier1_timeout'); ?>" />
			       			<label>
			       				<input type="checkbox" name="smart_throttle_tier1_timeout_increment" value="true" <?php echo (get_option('smart_throttle_tier1_timeout_increment') == 'true' ? 'checked' : ''); ?> />
			       				<?php _e("Increment by", self::$translation_domain ); ?>
			       			</label>
			       		</td>
			        </tr>
			    	<tr valign="top">
			    		<th scope="row"><?php _e("Tier 2:", self::$translation_domain ); ?></th>
			    	</tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Comment rate >:", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier2_gt" value="<?php echo get_option('smart_throttle_tier2_gt'); ?>" /></td>
			        </tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Comment rate <:", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier2_lt" value="<?php echo get_option('smart_throttle_tier2_lt'); ?>" /></td>
			        </tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Time out (in seconds):", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier2_timeout" value="<?php echo get_option('smart_throttle_tier2_timeout'); ?>" />
			            	<label>
			       				<input type="checkbox" name="smart_throttle_tier2_timeout_increment" value="true" <?php echo (get_option('smart_throttle_tier2_timeout_increment') == 'true' ? 'checked' : ''); ?> />
			       				<?php _e("Increment by", self::$translation_domain ); ?>
			       			</label>
			       		</td>
			        </tr>
			    	<tr valign="top">
			    		<th scope="row"><?php _e("Tier 3:", self::$translation_domain ); ?></th>
			    	</tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Comment rate >:", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier3_gt" value="<?php echo get_option('smart_throttle_tier3_gt'); ?>" /></td>
			        </tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Comment rate <:", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier3_lt" value="<?php echo get_option('smart_throttle_tier3_lt'); ?>" /></td>
			        </tr>
			    	<tr valign="top">
			            <th scope="row"><?php _e("Time out (in seconds):", self::$translation_domain ); ?></th>
			            <td><input type="text" name="smart_throttle_tier3_timeout" value="<?php echo get_option('smart_throttle_tier3_timeout'); ?>" />
			            	<label>
			       				<input type="checkbox" name="smart_throttle_tier3_timeout_increment" value="true" <?php echo (get_option('smart_throttle_tier3_timeout_increment') == 'true' ? 'checked' : ''); ?>/>
			       				<?php _e("Increment by", self::$translation_domain ); ?>
			       			</label>
			       		</td>
			        </tr>
			    </table>

			    <input type="hidden" name="action" value="update" />
    			<input type="hidden" name="page_options" value="smart_throttle_tier1_gt,smart_throttle_tier1_lt,smart_throttle_tier1_timeout,smart_throttle_tier1_timeout_increment,smart_throttle_tier2_gt,smart_throttle_tier2_lt,smart_throttle_tier2_timeout,smart_throttle_tier2_timeout_increment,smart_throttle_tier3_gt,smart_throttle_tier3_lt,smart_throttle_tier3_timeout,smart_throttle_tier3_timeout_increment"/>

			    <p class="submit">
			    	<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
			    </p>
	    	</form>
	    </div>
<?php
	}
}

// If we're not running in PHP 4, initialize
if (strpos(phpversion(), '4') !== 0) {
    $smart_throttle &= new smart_throttle_plugin();
}
