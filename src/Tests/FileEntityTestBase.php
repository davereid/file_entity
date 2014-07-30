<?php

/**
 * @file
 * Contains \Drupal\file_entity\Tests\FileEntityTestBase.
 */

namespace Drupal\file_entity\Tests;

use Drupal\file\Entity\File;
use Drupal\file_entity\Entity\FileType;
use Drupal\file_entity\FileEntity;
use Drupal\simpletest\WebTestBase;

/**
 * Base class for file entity tests.
 */
abstract class FileEntityTestBase extends WebTestBase {

  /**
   * @var array
   */
  public static $modules = array('file_entity');

  protected $files = array();

  protected function setUpFiles($defaults = array()) {
    // Populate defaults array.
    $defaults += array(
      'uid' => 1,
      'status' => FILE_STATUS_PERMANENT,
    );

    $types = array('text', 'image');
    foreach ($types as $type) {
      foreach ($this->drupalGetTestFiles($type) as $file) {
        foreach ($defaults as $key => $value) {
          $file->$key = $value;
        }
        $file = File::create((array) $file);
        $file->save();
        $this->files[$type][] = $file;
      }
    }
  }

  /**
   * Creates a test file type.
   *
   * @param array $overrides
   *   (optional) An array of values indexed by FileType property names.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   */
  protected function createFileType($overrides = array()) {
    $type = array(
      'id' => strtolower($this->randomName()),
      'label' => 'Test',
      'mimetypes' => array('image/jpeg', 'image/gif', 'image/png', 'image/tiff'),
    );
    foreach ($overrides as $k => $v) {
      $type[$k] = $v;
    }
    $entity = FileType::create($type);
    $entity->save();
    return $entity;
  }

  /**
   * Helper for testFileEntityPrivateDownloadAccess() test.
   *
   * Defines several cases for accesing private files.
   *
   * @return array
   *   Array of associative arrays, each one having the next keys:
   *   - "message" string with the assertion message.
   *   - "permissions" array of permissions or NULL for anonymous user.
   *   - "expect" expected HTTP response code.
   *   - "owner" Optional boolean indicating if the user is a file owner.
   */
  protected function getPrivateDownloadAccessCases() {
    return array(
      array(
        'message' => "File owners cannot download their own files unless they are granted the 'view own private files' permission.",
        'permissions' => array(),
        'expect' => 403,
        'owner' => TRUE,
      ),
      array(
        'message' => "File owners can download their own files as they have been granted the 'view own private files' permission.",
        'permissions' => array('view own private files'),
        'expect' => 200,
        'owner' => TRUE,
      ),
      array(
        'message' => "Anonymous users cannot download private files.",
        'permissions' => NULL,
        'expect' => 403,
      ),
      array(
        'message' => "Authenticated users cannot download each other's private files.",
        'permissions' => array(),
        'expect' => 403,
      ),
      array(
        'message' => "Users who can view public files are not able to download private files.",
        'permissions' => array('view files'),
        'expect' => 403,
      ),
      array(
        'message' => "Users who bypass file access can download any file.",
        'permissions' => array('bypass file access'),
        'expect' => 200,
      ),
    );
  }

  /**
   * Retrieves a sample file of the specified type.
   */
  function getTestFile($type_name, $size = NULL) {
    // Get a file to upload.
    $file = current($this->drupalGetTestFiles($type_name, $size));

    // Add a filesize property to files as would be read by file_load().
    $file->filesize = filesize($file->uri);

    return $file;
  }

  /**
   * Get a file from the database based on its filename.
   *
   * @param $filename
   *   A file filename, usually generated by $this->randomName().
   * @param $reset
   *   (optional) Whether to reset the internal file_load() cache.
   *
   * @return
   *   A file object matching $filename.
   */
  function getFileByFilename($filename, $reset = FALSE) {
    $files = file_load_multiple(array(), array('filename' => $filename), $reset);
    // Load the first file returned from the database.
    $returned_file = reset($files);
    return $returned_file;
  }

  protected function createFileEntity($settings = array()) {
    $file = new \stdClass();

    // Populate defaults array.
    $settings += array(
      'filepath' => 'Файл для тестирования ' . $this->randomName(), // Prefix with non-latin characters to ensure that all file-related tests work with international filenames.
      'filemime' => 'text/plain',
      'uid' => 1,
      'timestamp' => REQUEST_TIME,
      'status' => FILE_STATUS_PERMANENT,
      'contents' => "file_put_contents() doesn't seem to appreciate empty strings so let's put in some data.",
      'scheme' => file_default_scheme(),
      'type' => NULL,
    );

    $filepath = $settings['scheme'] . '://' . $settings['filepath'];

    file_put_contents($filepath, $settings['contents']);
    $this->assertTrue(is_file($filepath), t('The test file exists on the disk.'), 'Create test file');

    $file = new \stdClass();
    $file->uri = $filepath;
    $file->filename = drupal_basename($file->uri);
    $file->filemime = $settings['filemime'];
    $file->uid = $settings['uid'];
    $file->timestamp = $settings['timestamp'];
    $file->filesize = filesize($file->uri);
    $file->status = $settings['status'];
    $file->type = $settings['type'];

    // The file type is used as a bundle key, and therefore, must not be NULL.
    if (!isset($file->type)) {
      $file->type = FILE_TYPE_NONE;
    }

    // If the file isn't already assigned a real type, determine what type should
    // be assigned to it.
    if ($file->type === FILE_TYPE_NONE) {
      $file->type = $file->filemime;
    }

    // Save the file and assert success.
    $result = FileEntity::create((array) $file)->save();
    $this->assertIdentical(SAVED_NEW, $result, t('The file was added to the database.'), 'Create test file');

    return $file;
  }

  /**
   * Overrides DrupalWebTestCase::drupalGetToken() to support the hash salt.
   *
   * @todo Remove when http://drupal.org/node/1555862 is fixed in core.
   */
  protected function drupalGetToken($value = '') {
    $private_key = drupal_get_private_key();
    return drupal_hmac_base64($value, $this->session_id . $private_key . drupal_get_hash_salt());
  }
}
