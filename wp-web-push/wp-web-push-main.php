<?php

require_once(plugin_dir_path(__FILE__) . 'web-push.php' );
require_once(plugin_dir_path(__FILE__) . 'wp-web-push-db.php');

load_plugin_textdomain('wpwebpush', false, dirname(plugin_basename(__FILE__)) . '/lang');

class WebPush_Main {
  private static $instance;
  public static $ALLOWED_TRIGGERS;

  public function __construct() {
    self::$ALLOWED_TRIGGERS = array(
      array('text' => __('New Post', 'wpwebpush'), 'key' => 'new-post', 'enable_by_default' => true, 'hook' => 'transition_post_status', 'action' => 'on_transition_post_status'),
      array('text' => __('On Subscription', 'wpwebpush'), 'key' => 'on-subscription', 'enable_by_default' => true),
    );
    self::add_trigger_handlers();

    add_action('wp_head', array($this, 'add_manifest'));

    add_action('wp_footer', array($this, 'add_notification_button'), 9999);

    add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    add_filter('query_vars', array($this, 'on_query_vars'), 10, 1);
    add_action('parse_request', array($this, 'on_parse_request'));

    add_action('wp_ajax_nopriv_webpush_register', array($this, 'handle_webpush_register'));
    add_action('wp_ajax_nopriv_webpush_get_payload', array($this, 'handle_webpush_get_payload'));
  }

  public static function init() {
    if (!self::$instance) {
      self::$instance = new self();
    }
  }

  public static function add_manifest() {
    echo '<link rel="manifest" href="' . home_url('/') . '?webpush_file=manifest">';
  }

  public static function add_notification_button() {
    echo '<div id="webpush-notification-button"><img id="webpush-notification-button-image" src="' . plugins_url('lib/bell.svg', __FILE__) . '" alt="" /></div>';
  }

  public function enqueue_frontend_scripts() {
    wp_enqueue_script('localforage-script', plugins_url('lib/js/localforage.min.js', __FILE__));

    $title_option = get_option('webpush_title');

    $icon = get_option('webpush_icon');
    if ($icon === 'blog_icon') {
      $icon = get_site_icon_url();
    } else if ($icon === 'post_icon') {
      // We don't have a post here.
      $icon = '';
    }

    wp_register_script('sw-manager-script', plugins_url('lib/js/sw-manager.js', __FILE__));
    wp_localize_script('sw-manager-script', 'ServiceWorker', array(
      'url' => home_url('/') . '?webpush_file=worker',
      'register_url' => admin_url('admin-ajax.php'),
      'min_visits' => get_option('webpush_min_visits'),
      'welcome_enabled' => in_array('on-subscription', get_option('webpush_triggers')),
      'welcome_title' => $title_option === 'blog_title' ? get_bloginfo('name') : $title_option,
      'welcome_body' => __('Successfully subscribed to notifications'),
      'welcome_icon' => $icon,
      'notification_enabled_icon' => plugins_url('lib/bell.svg', __FILE__),
      'notification_disabled_icon' => plugins_url('lib/bell_disabled.svg', __FILE__),
    ));
    wp_enqueue_script('sw-manager-script');

    wp_enqueue_style('notification-button-style', plugins_url('lib/style/notification_button.css', __FILE__));
  }

  public static function handle_webpush_register() {
    WebPush_DB::add_subscription($_POST['endpoint'], $_POST['key']);
    wp_die();
  }

  public static function handle_webpush_get_payload() {
    wp_send_json(get_option('webpush_payload'));
  }

  public static function on_query_vars($qvars) {
    $qvars[] = 'webpush_file';
    $qvars[] = 'webpush_from_notification';
    return $qvars;
  }

  public static function on_parse_request($query) {
    if (array_key_exists('webpush_from_notification', $query->query_vars)) {
      update_option('webpush_opened_notification_count', get_option('webpush_opened_notification_count') + 1);
    }

    if (!array_key_exists('webpush_file', $query->query_vars)) {
      return;
    }

    $file = $query->query_vars['webpush_file'];

    if ($file === 'worker') {
      header('Content-Type: application/javascript');
      require_once(plugin_dir_path(__FILE__) . 'lib/js/sw.php');
      exit;
    }

    if ($file === 'manifest') {
      header('Content-Type: application/json');
      require_once(plugin_dir_path(__FILE__) . 'lib/manifest.php');
      exit;
    }
  }

  public static function on_transition_post_status($new_status, $old_status, $post) {
    if (empty($post) || $new_status !== 'publish' ||
        !in_array('new-post', get_option('webpush_triggers'))) {
      return;
    }

    $title_option = get_option('webpush_title');

    $icon = get_option('webpush_icon');
    if ($icon === 'blog_icon') {
      $icon = get_site_icon_url();
    } else if ($icon === 'post_icon') {
      $post_thumbnail_id = get_post_thumbnail_id($post->ID);
      $icon = $post_thumbnail_id ? wp_get_attachment_url($post_thumbnail_id) : '';
    }

    update_option('webpush_payload', array(
      'title' => $title_option === 'blog_title' ? get_bloginfo('name') : $title_option,
      'body' => get_the_title($post->ID),
      'icon' => $icon,
      'url' => get_permalink($post->ID),
    ));

    $notification_count = get_option('webpush_notification_count');

    $gcmKey = get_option('webpush_gcm_key');

    $subscriptions = WebPush_DB::get_subscriptions();
    foreach ($subscriptions as $subscription) {
      if (!sendNotification($subscription->endpoint, $gcmKey)) {
        // If there's an error while sending the push notification,
        // the subscription is no longer valid, hence we remove it.
        WebPush_DB::remove_subscription($subscription->endpoint);
      } else {
        $notification_count++;
      }
    }

    update_option('webpush_notification_count', $notification_count);
  }

  public static function get_trigger_by_key_value($key, $value) {
    foreach(self::$ALLOWED_TRIGGERS as $trigger) {
      if($trigger[$key] === $value) {
        return $trigger;
      }
    }
    return false;
  }

  public static function get_triggers_by_key_value($key, $value) {
    $matches = array();

    foreach(self::$ALLOWED_TRIGGERS as $trigger) {
      if($trigger[$key] === $value) {
        $matches[] = $trigger;
      }
    }
    return $matches;
  }

  public function add_trigger_handlers() {
    $enabled_triggers = get_option('webpush_triggers');
    if(!$enabled_triggers) {
      $enabled_triggers = array();
    }
    foreach($enabled_triggers as $trigger) {
      $trigger_detail = self::get_trigger_by_key_value('key', $trigger);

      if ($trigger_detail and array_key_exists('hook', $trigger_detail)) {
        add_action($trigger_detail['hook'], array($this, $trigger_detail['action']), 10, 3);
      }
    }
  }
}

?>
