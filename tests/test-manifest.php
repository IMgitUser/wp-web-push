<?php

// TODO: Some of these tests should be moved to the WebAppManifestGenerator repository (https://github.com/marco-c/wp-web-app-manifest-generator).

class ManifestTest extends WP_Ajax_UnitTestCase {
  function setUp() {
    parent::setUp();
    remove_all_actions('wp_head');
  }

  function test_generate_manifest_link() {
    $this->assertEquals('<link rel="manifest" href="http://example.org/wp-content/uploads/wpservefile_files/manifest.json">', get_echo(array(WebAppManifestGenerator::getInstance(), 'add_manifest')));
  }

  function test_WebPush_Main_doesnt_initialize_WebAppManifestGenerator_if_no_gcm_sender_id() {
    $main = new WebPush_Main();
    $this->assertFalse(has_action('wp_head'));
  }

  function test_WebAppManifestGenerator_registers_wp_head() {
    $main = new WebAppManifestGenerator();
    $this->assertTrue(has_action('wp_head'));
  }

  function test_manifest_generation() {
    WebAppManifestGenerator::getInstance()->set_field('test', 'Marco');

    $result = WebAppManifestGenerator::getInstance()->manifestJSONGenerator();

    $this->assertEquals('{"start_url":"\/","test":"Marco"}', $result['content']);
    $this->assertEquals('application/json', $result['contentType']);
  }

  function test_manifest_request_multiple_users() {
    require dirname(dirname(__FILE__)) . '/build/vendor/marco-c/wp-web-app-manifest-generator/WebAppManifestGenerator.php';
  }
}

?>
