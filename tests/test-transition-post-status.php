<?php

class TransitionPostStatusTest extends WP_UnitTestCase {
  function setUp() {
    parent::setUp();

    WebPush_DB::add_subscription('endpoint', 'aKey');

    $_REQUEST['webpush_meta_box_nonce'] = wp_create_nonce('webpush_send_notification');
    $_REQUEST['webpush_send_notification'] = 1;
  }

  function tearDown() {
    parent::tearDown();
    remove_all_filters('pre_http_request');
  }

  function test_empty_post() {
    add_filter('pre_http_request', function() {
      $self->assertTrue(false);
    });

    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', null);
  }

  function test_non_post() {
    add_filter('pre_http_request', function() {
      $self->assertTrue(false);
    });

    $post = get_post($this->factory->post->create(array('post_type' => 'page'))); 
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);
  }

  function test_non_published() {
    add_filter('pre_http_request', function() {
      $self->assertTrue(false);
    });

    $post = get_post($this->factory->post->create());
    $main = new WebPush_Main();
    $main->on_transition_post_status('draft', 'draft', $post);
  }

  function test_nonce_not_set() {
    add_filter('pre_http_request', function() {
      $self->assertTrue(false);
    });

    unset($_REQUEST['webpush_meta_box_nonce']);

    $post = get_post($this->factory->post->create());
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);
  }

  function test_invalid_nonce() {
    add_filter('pre_http_request', function() {
      $self->assertTrue(false);
    });

    $_REQUEST['webpush_meta_box_nonce'] = 'invalid';

    $post = get_post($this->factory->post->create());
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);
  }

  function test_checkbox_not_set() {
    add_filter('pre_http_request', function() {
      $self->assertTrue(false);
    });

    unset($_REQUEST['webpush_send_notification']);

    $post = get_post($this->factory->post->create());
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);
  }

  function test_success() {
    $self = $this;
    add_filter('pre_http_request', function() use ($self) {
      $self->assertTrue(true);

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => 201,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    });

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'Test Blog');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], '');
    $this->assertEquals($payload['url'], 'http://example.org/?p=8');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 1);
  }

  function test_success_custom_title() {
    $self = $this;
    add_filter('pre_http_request', function() use ($self) {
      $self->assertTrue(true);

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => 201,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    });

    update_option('webpush_title', 'A Custom Title');

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'A Custom Title');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], '');
    $this->assertEquals($payload['url'], 'http://example.org/?p=9');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 1);
  }

  function test_success_custom_icon() {
    $self = $this;
    add_filter('pre_http_request', function() use ($self) {
      $self->assertTrue(true);

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => 201,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    });

    update_option('webpush_icon', 'https://www.mozilla.org/icon.svg');

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'Test Blog');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], 'https://www.mozilla.org/icon.svg');
    $this->assertEquals($payload['url'], 'http://example.org/?p=10');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 1);
  }

  function test_success_multiple_subscribers() {
    WebPush_DB::add_subscription('endpoint2', 'aKey2');

    $self = $this;
    add_filter('pre_http_request', function() use ($self) {
      $self->assertTrue(true);

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => 201,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    });

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'Test Blog');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], '');
    $this->assertEquals($payload['url'], 'http://example.org/?p=11');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 2);
  }

  function test_success_no_gcm_key() {
    WebPush_DB::add_subscription('https://android.googleapis.com/gcm/send/endpoint', '');

    $self = $this;
    add_filter('pre_http_request', function() use ($self) {
      $self->assertTrue(true);

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => 201,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    });

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'Test Blog');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], '');
    $this->assertEquals($payload['url'], 'http://example.org/?p=12');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 1);
  }

  function test_success_with_gcm_key() {
    WebPush_DB::add_subscription('https://android.googleapis.com/gcm/send/endpoint', '');

    $self = $this;
    add_filter('pre_http_request', function($url, $r) use ($self) {
      $self->assertTrue(true);

      $code = isset($r['headers']['TTL']) ? 201 : 200;

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => $code,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    }, 10, 2);

    update_option('webpush_gcm_key', 'ASD');

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'Test Blog');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], '');
    $this->assertEquals($payload['url'], 'http://example.org/?p=13');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 2);
  }

  function test_success_remove_invalid_subscription() {
    global $i;
    $i = 0;

    WebPush_DB::add_subscription('endpoint2', 'aKey2');

    $self = $this;
    add_filter('pre_http_request', function() use ($self) {
      global $i;

      $self->assertTrue(true);

      $code = 201;
      if ($i++ === 1) {
        $code = 404;
      }

      return array(
        'headers' => array(),
        'body' => '',
        'response' => array(
          'code' => $code,
        ),
        'cookies' => array(),
        'filename' => '',
      );
    });

    $post = get_post($this->factory->post->create(array('post_title' => 'Test Post Title')));
    $main = new WebPush_Main();
    $main->on_transition_post_status('publish', 'draft', $post);

    $payload = get_option('webpush_payload');
    $this->assertEquals($payload['title'], 'Test Blog');
    $this->assertEquals($payload['body'], 'Test Post Title');
    $this->assertEquals($payload['icon'], '');
    $this->assertEquals($payload['url'], 'http://example.org/?p=14');
    $this->assertEquals($payload['postID'], $post->ID);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_clicked', true), 0);
    $this->assertEquals(get_post_meta($post->ID, '_notifications_sent', true), 1);
  }
}

?>
