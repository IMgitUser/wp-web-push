<?php

load_plugin_textdomain('wpwebpush');

class WebPush_Admin {
  private static $instance;

  public function __construct() {
    add_action('admin_menu', array($this, 'on_admin_menu'));
    add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
  }

  function add_dashboard_widgets() {
    wp_add_dashboard_widget('wp-web-push_dashboard_widget', __('Web Push', 'wpwebpush'), array($this, 'dashboard_widget'));
  }

  function dashboard_widget() {
    $notification_count = get_option('webpush_notification_count');
    $opened_notification_count = get_option('webpush_opened_notification_count');
    printf(_n('%s notification sent.', '%s notifications sent.', $notification_count, 'wpwebpush'), number_format_i18n($notification_count));
    echo '<br>';
    printf(_n('%s notification clicked.', '%s notifications clicked.', $opened_notification_count, 'wpwebpush'), number_format_i18n($opened_notification_count));
  }

  public static function init() {
    if (!self::$instance) {
      self::$instance = new self();
    }
  }

  public function on_admin_menu() {
    add_options_page(__('Web Push Options', 'wpwebpush'), __('Web Push', 'wpwebpush'), 'manage_options', 'web-push-options', array($this, 'options'));
  }

  // http://php.net/manual/en/function.array-values.php
  private function array_values_recursive($array) {
    $flat = array();

    foreach ($array as $value) {
      if (is_array($value)) {
          $flat = array_merge($flat, $this->array_values_recursive($value));
      }
      else {
          $flat[] = $value;
      }
    }
    return $flat;
  }

  public function options() {
    $ALLOWED_TRIGGERS = array(
      array('text' => __('New Post'), 'key' => 'new-post'),
      array('text' => __('New Comment'), 'key' => 'new-comment'), // To Do:  Is this useless without saying which post?  Surely not all posts...
      array('text' => __('Updated Post'), 'key' => 'update-post')
    );

    $title_option = get_option('webpush_title');
    $icon_option = get_option('webpush_icon');
    $min_visits_option = intval(get_option('webpush_min_visits'));
    $triggers_option = get_option('webpush_triggers');
    $gcm_key_option = get_option('webpush_gcm_key');
    $gcm_sender_id_option = get_option('webpush_gcm_sender_id');

    if (isset($_POST['webpush_form']) && $_POST['webpush_form'] === 'submitted') {
      if ($_POST['webpush_title'] === 'blog_title') {
        $title_option = 'blog_title';
      } else if ($_POST['webpush_title'] === 'custom') {
        $title_option = $_POST['webpush_title_custom'];
      } else {
        wp_die(__('Invalid value for the Notification Title', 'wpwebpush'));
      }

      // Update icon.
      if ($_POST['webpush_icon'] === 'blog_icon') {
        $icon_option = 'blog_icon';
      } else if ($_POST['webpush_icon'] === 'custom') {
        if (isset($_FILES['webpush_icon_custom']) && ($_FILES['webpush_icon_custom']['size'] > 0)) {
          $file_type = wp_check_filetype(basename($_FILES['webpush_icon_custom']['name']));

          if (!in_array($file_type['type'], array('image/jpg', 'image/jpeg', 'image/gif', 'image/png'))) {
            wp_die(__('The notification icon should be an image', 'wpwebpush'));
          }

          $file = wp_handle_upload($_FILES['webpush_icon_custom'], array('test_form' => false));

          if (isset($file['error'])) {
            wp_die(sprintf(__('Error in handling upload for the notification icon: %s', 'wpwebpush'), $file['error']));
          }

          $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
          );

          $attach_id = wp_insert_attachment($attachment, $file['file']);
          require_once(ABSPATH . 'wp-admin/includes/image.php');
          $attach_data = wp_generate_attachment_metadata($attach_id, $file['file']);
          wp_update_attachment_metadata($attach_id,  $attach_data);

          $icon_option = wp_get_attachment_url($attach_id);
        } else if ($icon_option === 'blog_icon') {
          // If it was set to use the blog icon and there's no file selected, die.
          wp_die(__('Invalid value for the Notification Icon', 'wpwebpush'));
        }
      } else {
        wp_die(__('Invalid value for the Notification Icon', 'wpwebpush'));
      }

      if ($_POST['webpush_min_visits'] === '0') {
        $min_visits_option = 0;
      } else if ($_POST['webpush_min_visits'] === 'custom') {
        $min_visits_option = intval($_POST['webpush_min_visits_custom']);
      } else {
        wp_die(__('Invalid value for `Registration Behavior`', 'wpwebpush'));
      }

      $triggers_option = array_key_exists('webpush_triggers', $_POST) ? $_POST['webpush_triggers'] : array();
      $allowed_trigger_values = $this->array_values_recursive($ALLOWED_TRIGGERS);
      foreach ($triggers_option as $trigger_option) {
        if (!in_array($trigger_option, $allowed_trigger_values)) {
          wp_die(sprintf(__('Invalid value in Push Triggers: %s', 'wpwebpush'), $trigger_option));
        }
      }

      $gcm_key_option = $_POST['webpush_gcm_key'];
      $gcm_sender_id_option = $_POST['webpush_gcm_sender_id'];

      update_option('webpush_title', $title_option);
      update_option('webpush_icon', $icon_option);
      update_option('webpush_min_visits', $min_visits_option);
      update_option('webpush_triggers', $triggers_option);
      update_option('webpush_gcm_key', $gcm_key_option);
      update_option('webpush_gcm_sender_id', $gcm_sender_id_option);

?>
<div class="updated"><p><strong><?php _e('Settings saved.'); ?></strong></p></div>
<?php
    }
?>

<div class="wrap">
<h2><?php _e('Web Push', 'wpwebpush'); ?></h2>

<form method="post" action="" enctype="multipart/form-data">
<table class="form-table">

<input type="hidden" name="webpush_form" value="submitted" />

<tr>
<th scope="row"><?php _e('Notification Title', 'wpwebpush'); ?></th>
<td>
<fieldset>
<label><input type="radio" name="webpush_title" value="blog_title" <?php echo $title_option === 'blog_title' ? 'checked' : '' ?> /> <?php _e('Use the Site Title', 'wpwebpush'); ?>: <b><?php echo get_bloginfo('name'); ?></b></label><br />
<label><input type="radio" name="webpush_title" value="custom" <?php echo $title_option !== 'blog_title' ? 'checked' : '' ?> /> <?php _e('Custom:'); ?></label>
<input type="text" name="webpush_title_custom" value="<?php echo $title_option !== 'blog_title' ? $title_option : esc_attr__('Your custom title', 'wpwebpush') ?>" class="long-text" />
</fieldset>
</td>
</tr>

<tr>
<th scope="row"><?php _e('Notification Icon', 'wpwebpush'); ?></th>
<td>
<fieldset>
<label><input type="radio" name="webpush_icon" value="blog_icon" <?php echo $icon_option === 'blog_icon' ? 'checked' : '' ?> /> <?php _e('Use the Site Icon', 'wpwebpush'); ?></label>
<?php
  $site_icon_url = get_site_icon_url();
  if ($site_icon_url) {
    echo '<img src="' . $site_icon_url . '">';
  }
?>
<br />
<label><input type="radio" name="webpush_icon" value="custom" <?php echo $icon_option !== 'blog_icon' ? 'checked' : '' ?> /> <?php _e('Custom'); ?></label>
<?php
  if ($icon_option !== 'blog_icon') {
    echo '<img src="' . $icon_option . '">';
  }
?>
<input type="file" name="webpush_icon_custom" id="webpush_icon_custom" />
</fieldset>
</td>
</tr>

<tr>
<th scope="row"><?php _e('Registration Behavior', 'wpwebpush'); ?></th>
<td>
<fieldset>
<label><input type="radio" name="webpush_min_visits" value="0" <?php echo $min_visits_option === 0 ? 'checked' : '' ?> /> <?php _e('Ask the user to register as soon as he visits the site.', 'wpwebpush'); ?></label><br />
<label><input type="radio" name="webpush_min_visits" value="custom" <?php echo $min_visits_option !== 0 ? 'checked' : '' ?> /> <?php _e('Ask the user to register after N visits:'); ?></label>
<input type="text" name="webpush_min_visits_custom" value="<?php echo $min_visits_option !== 0 ? $min_visits_option : 3 ?>" class="small-text" />
</fieldset>
</td>
</tr>

<tr>
<th scope="row"><?php _e('Push Triggers', 'wpwebpush'); ?></th>
<td>
<fieldset>
  <?php foreach($ALLOWED_TRIGGERS as $trigger): ?>
  <label><input type="checkbox" name="webpush_triggers[]" value="<?php echo esc_attr($trigger['key']); ?>" <?php echo in_array($trigger['key'], $triggers_option) ? 'checked' : '' ?> /> <?php _e($trigger['text'], 'wpwebpush'); ?></label><br />
  <?php endforeach; ?>
</fieldset>
</td>
</tr>

</table>

<table class="form-table">

<h2 class="title"><?php _e('GCM Configuration', 'wpwebpush'); ?></h2>

<tr>
<th scope="row"><label for="webpush_gcm_key"><?php _e('GCM Key', 'wpwebpush'); ?></label></th>
<td><input name="webpush_gcm_key" type="text" value="<?php echo $gcm_key_option; ?>" class="regular-text code" /></td>
</tr>

<tr>
<th scope="row"><label for="webpush_gcm_sender_id"><?php _e('GCM Sender ID', 'wpwebpush'); ?></label></th>
<td><input name="webpush_gcm_sender_id" type="text" value="<?php echo $gcm_sender_id_option; ?>" class="code" /></td>
</tr>

</table>

<?php submit_button(__('Save Changes'), 'primary'); ?>

</form>

</div>

<?php
  }
}
?>
