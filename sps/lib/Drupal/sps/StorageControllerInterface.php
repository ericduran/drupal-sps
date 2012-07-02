<?php
namespace Drupal\sps;

interface StorageControllerInterface {
 /**
  * Cache away a object
  *
  * @param $name
  *   A string name use for retrieval
  * @param $cache
  *   an object to be cached
  * @return NULL
  */
 public function set($name, $cache);
 /**
  * Test if we have an object cached
  * This should be less expensive then using get
  *
  * @param $name
  *   A string name use for retrieval
  * @return bool
  */
 public function is_set($name);
 /**
  * Retrieve a cached object
  *
  * @param $name
  *   A string name use for retrieval
  * @return the object that was cached
  */
 public function get($name);
}