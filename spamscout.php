<?php
/**
 * Plugin Name:       SpamScout
 * Plugin URI:        https://spamscout.net/
 * Description:       Light and invisible method to block spam when spam is posted.
 * Version:           1.0.2
 * Requires at least: 2.7.0
 * Requires PHP:      7.3
 * Author:            SpamScout
 * Author URI:		  https://spamscout.net/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if(!defined('ABSPATH')) exit;

class SpamScout
{
    private $spamscout_options;

    public function __construct()
    {
        add_action('init', array($this, 'spamscout_init'), 1); // 1.5.0	Introduced.
		add_action('transition_comment_status', array($this, 'spamscout_comment_report'), 10, 3);
    }

    function spamscout_init()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $spamscout = new SpamScout();
			
			$ip = $spamscout->get_user_ip();
			
			if($ip == false){
				//Invalid IP?
				wp_die('Invalid IP', 'Invalid IP', ['response' => 403]);
				
			}else{
				$result = $spamscout->is_spam($ip);
			}

            if(isset($result['spam']) && $result['spam'] == true)
            {
                wp_die('Your IP ' . $ip . ' has been blocked for security reason.', 'Your IP ' . $ip . ' has been blocked for security reason.', ['response' => 403]);
            }
        }
    }

    public function is_spam($ip)
    {
        $spamscout_options = get_option('spamscout_option_name');

		// 2.7.0	Introduced.
        $request = wp_remote_get('https://spamscout.net/api/check/' . $ip, [
            'sslverify' => false,
            'timeout' => 60,
			'headers' => ['Authorization' => $spamscout_options['api_key']]
        ]);

        if (is_wp_error($request))
        {
            return false;
        }

        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);

        return $data;
    }
	
	function spamscout_comment_report($new_status, $old_status, $comment)
{
    if ($old_status != $new_status)
    {
        if ($new_status == 'spam')
        {
			$spamscout_options = get_option('spamscout_option_name');

			// 2.7.0	Introduced.
			$request = wp_remote_get('https://spamscout.net/api/report/' . $comment->comment_author_IP, [
				'sslverify' => false,
				'timeout' => 60,
				'headers' => ['Authorization' => $spamscout_options['api_key']]
             ));
			]);
        }
    }
}

    public function get_user_ip()
    {
        $ip = $_SERVER['HTTP_CLIENT_IP'] 
			?? $_SERVER["HTTP_CF_CONNECTING_IP"] # when behind cloudflare
			?? $_SERVER['HTTP_X_FORWARDED'] 
			?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
			?? $_SERVER['HTTP_FORWARDED'] 
			?? $_SERVER['HTTP_FORWARDED_FOR'] 
			?? $_SERVER['REMOTE_ADDR'] 
			?? '0.0.0.0';
		
		$ip = sanitize_text_field($ip);
		
		if(filter_var($ip, FILTER_VALIDATE_IP))
		{
            return $ip;
        }else{
			return false;
		}
		
    }

    public function spamscout_admin_plugin_page()
    {
        add_action('admin_menu', array(
            $this,
            'spamscout_add_plugin_page'
        ));
        add_action('admin_init', array(
            $this,
            'spamscout_page_init'
        ));
    }

    public function spamscout_add_plugin_page()
    {
        add_menu_page('SpamScout', // page_title
        'SpamScout', // menu_title
        'manage_options', // capability
        'spamscout', // menu_slug
        array(
            $this,
            'spamscout_create_admin_page'
        ) , // function
        'dashicons-admin-generic', // icon_url
        75
        // position
        );
    }

    public function spamscout_create_admin_page()
    {
        $this->spamscout_options = get_option('spamscout_option_name'); ?>

		<div class="wrap">
			<h2>SpamScout</h2>
			<p><a target="_blank" href="https://spamscout.net/">Get API Key</a></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
        settings_fields('spamscout_option_group');
        do_settings_sections('spamscout-admin');
        submit_button();
?>
			</form>
		</div>
	<?php
    }

    public function spamscout_page_init()
    {
        register_setting('spamscout_option_group', // option_group
        'spamscout_option_name', // option_name
        array(
            $this,
            'spamscout_sanitize'
        ) // sanitize_callback
        );

        add_settings_section('spamscout_setting_section', // id
        'Settings', // title
        array(
            $this,
            'spamscout_section_info'
        ) , // callback
        'spamscout-admin'
        // page
        );

        add_settings_field('api_key', // id
        'API Key', // title
        array(
            $this,
            'api_key_callback'
        ) , // callback
        'spamscout-admin', // page
        'spamscout_setting_section'
        // section
        );
    }

    public function spamscout_sanitize($input)
    {
        $sanitary_values = array();
        if (isset($input['api_key']))
        {
            $sanitary_values['api_key'] = sanitize_text_field($input['api_key']);
        }

        return $sanitary_values;
    }

    public function spamscout_section_info()
    {

    }

    public function api_key_callback()
    {
        printf('<input class="regular-text" type="text" name="spamscout_option_name[api_key]" id="api_key" value="%s">', isset($this->spamscout_options['api_key']) ? esc_attr($this->spamscout_options['api_key']) : '');
    }

}
if (is_admin())
{
    $spamscout = new SpamScout();
    $spamscout->spamscout_admin_plugin_page();
}
else
{
    $spamscout = new SpamScout();
}