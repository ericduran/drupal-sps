<?php
namespace Drupal\sps\Override;

use \Drupal\sps\Plugins\Override\Override;

class NodeDateOverride extends Override {
  protected $timestamp;

  /**
   *  Create our NodeDateOverride.
   *  Defaults timestamp to jan 1, 1970.
   */
  public function __construct(array $settings, \Drupal\sps\Manager $manager) {
    parent::__construct($settings, $manager);
    $this->timestamp = 0;
  }

	/**
   * Returns a list of vid's to override the default vids to load.
   *
   * @return
   *  An array of override vids.
   */
  public function getOverrides() {
    //for right now just load node vids that are set to be published in the future
    $results = db_select('node_revision', 'v')
      ->fields('v', array('nid, vid'))
      ->condition('status', 0)
      ->condition('timestamp', $this->timestamp, '>')
      ->execute()
      ->fetchAllAssoc('nid');

    return $results;
  }

  /**
   * Set the data for this override.
   *
   * This method should be called before get overrides and provides the
   * data which the override will use to find the available overrides.
   *
   * @param $variables
   *  A unix timestamp
   *
   * @return \Drupal\sps\Override\NodeDateOverride
   *           Self
   */
  public function setData($variables) {
    $this->timestamp = $variables;

    return $this;
  }

  /**
   * Overrides Override::getDataConsumerApi()
   * Provides the data type for this override.
   *
   * @return string
   *   A string defining the data type
   */
  public function getDataConsumerApi() {
    return 'unixtimestamp';
  }
}
