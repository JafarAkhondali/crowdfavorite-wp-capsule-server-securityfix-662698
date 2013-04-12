<?php 

include_once('ui/functions.php');
include_once('capsule-server-import-export.php');

class Capsule_Server {
	
	public $api_meta_key;
	public $user_api_key;

	protected $api_endpoint;

	function __construct($user_id = null) {
		
		$this->user_id = $user_id === null ? get_current_user_id() : $user_id;
		
		$this->api_endpoint = home_url();

		$this->api_meta_key = capsule_server_api_meta_key();

		$this->user_api_key = get_user_meta($this->user_id, $this->api_meta_key, true);
	}

	public function add_actions() {
		add_action('user_register', array(&$this, 'user_register'));
		add_action('show_user_profile', array(&$this, 'user_profile'));
		add_action('edit_user_profile', array(&$this, 'user_profile'));
	}

	public function user_register($user_id) {
		$cap_server = new Capsule_Server($user_id);
		// This sets a new api key and returns it
		$cap_server->user_api_key();
	}

	public function user_profile($user_data) {
		// Add API Key to User's Profile
		$cap_server = new Capsule_Server($user_data->ID);		
		$api_key = $cap_server->user_api_key();

		// Just a request handler
		$api_endpoint = $cap_server->api_endpoint;
?>
<div class="capsule-profile">
<h3><?php _e('Capsule', 'capsule-server'); ?></h3>
<table class="form-table">
	<tr>
		<th></th>
		<td><span class="description"><?php _e('To publish to this Capsule server, add the following information as a Server in your Capsule client.', 'capsule-server'); ?></td>
	</tr>
	<tr id="capsule-endpoint">
		<th><label for="cap-endpoint"><?php _e('Capsule API Endpoint', 'capsule-server'); ?></label></th>
		<td><span id="cap-endpoint"><?php echo $api_endpoint; ?><span/></td>
	</tr>
	<tr id="capsule-api-key">
		<th><label for="cap-api-key"><?php _e('Capsule API Key', 'capsule-server'); ?></label></th>
		<td><span id="cap-api-key"><?php echo esc_html($api_key); ?></span></td>
	</tr>
	<tr>
		<th></th>
		<td><a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'cap-regenerate-key'); ?>" id="cap-regenerate-key" class="button" data-user-id="<?php echo esc_attr($user_data->ID); ?>"><?php _e('Change Capsule API Key', 'capsule-server'); ?></a></td>
	</tr>
</table>
</div>
<script type="text/javascript">
(function($) {
	// move into place
	$profile = $('.capsule-profile');
	$profile.prependTo($profile.closest('form'));
	// reset API key
	$('#cap-regenerate-key').on('click', function(e) {
		var id = $(this).data('user-id');
		var url = $(this).attr('href');
		e.preventDefault();
		$.post(
			url, { 
				action: 'cap_new_api_key',
				user_id: id 
			},
			function(data) {
				if (data) {
					$('#cap-api-key').html(data);
				}
			});
	});
})(jQuery);
</script>
<?php 
	}

	 function generate_api_key() {
		// Generate unique keys on a per blog basis
		if (is_multisite()) {
			global $blog_id;
			$key = AUTH_KEY.$blog_id;
		}
		else {
			$key = AUTH_KEY;
		}

		return sha1($this->user_id.$key.microtime());
	}


	 function set_api_key($key = null) {
		if ($key == null) {
			$key = $this->user_api_key;
		}
		update_user_meta($this->user_id, $this->api_meta_key, $key);
	}

	// Gets an api key for a user, generates a new one and sets it if the user doesn't have a key
	 function user_api_key() {
		if (empty($this->user_api_key)) {
			$this->user_api_key = $this->generate_api_key();
			$this->set_api_key();
		}

		return $this->user_api_key;
	}
}
$cap_server = new Capsule_Server();
$cap_server->add_actions();

function capsule_server_ajax_new_api() {
	$nonce = $_GET['_wpnonce'];
	$user_id = $_POST['user_id'];
	if ($user_id && wp_verify_nonce($nonce, 'cap-regenerate-key') && (current_user_can('edit_users') || $user_id == get_current_user_id())) {
		$cap = new Capsule_Server($user_id);
		$key = $cap->generate_api_key();
		$cap->set_api_key($key);
		echo $key;
	}
	die();
}
add_action('wp_ajax_cap_new_api_key', 'capsule_server_ajax_new_api');

/** 
 * Generate the user meta key for the api key value.
 * This generates a different key for each blog if it is a multisite install
 *
 * @return string meta key
 **/
function capsule_server_api_meta_key() {
	if (is_multisite()) {
		global $blog_id;
		$api_meta_key = ($blog_id == 1) ? '_capsule_api_key' : '_capsule_api_key_'.$blog_id;
	}
	else {
		$api_meta_key = '_capsule_api_key';
	}
	
	return $api_meta_key;
}

/**
 * Validates a user's existance in the db against an api key.
 * 
 * @param string $api_key The api key to use for the validation
 * @return int|null user ID or null if none can be found. 
 */
function capsule_server_validate_user($api_key) {
	global $wpdb;

	$meta_key = capsule_server_api_meta_key();
	$sql = $wpdb->prepare("
		SELECT `user_id`
		FROM $wpdb->usermeta
		WHERE `meta_key` = %s
		AND `meta_value` = %s", 
		$meta_key,
		$api_key
	);

	return $wpdb->get_var($sql);
}

function capsule_server_admin_notice(){
	if (strpos($_GET['page'], 'capsule') !== false) {
		return;
	}
?>
<style type="text/css">
.capsule-welcome {
	background: #222;
	color: #fff;
	margin: 30px 10px 10px 0;
	padding: 15px;
}
.capsule-welcome h1 {
	font-weight: normal;
	line-height: 100%;
	margin: 0 0 10px 0;
}
.capsule-welcome p {
	font-weight: normal;
	line-height: 100%;
	margin: 0;
}
.capsule-welcome a,
.capsule-welcome a:visited {
	color: #f8f8f8;
}
</style>
<section class="capsule-welcome">
	<h1><?php _e('Welcome to Capsule Server', 'capsule-server'); ?></h1>
	<p><?php printf(__('Please read the overview, FAQs and more about <a href="%s">how Capsule Server works</a>.', 'capsule-server'), esc_url(admin_url('admin.php?page=capsule'))); ?></p>
</section>
<?php
}
add_action('admin_notices', 'capsule_server_admin_notice');

// Add menu pages
function capsule_server_menu() {
	global $menu;
	$menu['3'] = array( '', 'read', 'separator-capsule', '', 'wp-menu-separator' );
	add_menu_page(__('Capsule', 'capsule-server'), __('Capsule', 'capsule-server'), 'manage_options', 'capsule', 'capsule_server_page', '', '3.1' );
	// needed to make separator show up
	ksort($menu);
 	add_submenu_page('capsule', __('Projects', 'capsule-server'), __('Projects', 'capsule-server'), 'manage_options', 'capsule-projects', 'capsule_server_admin_page_projects');
 	add_submenu_page('capsule', __('Users', 'capsule-server'), __('Users', 'capsule-server'), 'manage_options', 'capsule-users', 'capsule_server_admin_page_users');
}
add_action('admin_menu', 'capsule_server_menu');

function capsule_server_menu_js() {
?>
<script type="text/javascript">
// TODO
jQuery(function($) {
	$('#adminmenu').find('a[href*="admin.php?page=capsule-projects"]')
		.attr('href', 'edit-tags.php?taxonomy=projects')
		.end()
		.find('a[href*="admin.php?page=capsule-users"]')
		.attr('href', 'users.php');
});
</script>
<?php
}
add_action('admin_head', 'capsule_server_menu_js');

function capsule_server_page() {
// TODO
}
function capsule_server_admin_page_projects() {}
function capsule_server_admin_page_users() {}
