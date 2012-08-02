<?php
namespace Drupal\sps\Plugins\Condition;
define('SPS_CONFIG_WRAPPER_CONDITION_SUB_CONDITIONS', "wrapper_condition_sub_conditions");

use Drupal\sps\Plugins\AbstractPlugin;
use Drupal\sps\Plugins\ConditionInterface;

class WrapperCondition extends BasicCondition {
  protected $conditions = array();
  protected $manager;
  protected $active_condition;
  protected $override_set = FALSE;
  protected $override;
  protected $form_state;
  protected $conditions_config = array();

  /**
   * Implements PluginInterface::__construct().
   *
   * Create a new BasicCondition.
   *
   * @param $config
   *  An array of configuration which includes the widget to use
   *  These should be specified as the 'widget' key.
   *  The widget key may be specified as class names or instantiated
   *  classes.
   * @param $manager
   *  The current instance of the sps manager.
   */
  public function __construct(array $config, \Drupal\sps\Manager $manager) {
    $this->manager = $manager;
    if (!$this->manager->getConfigController()->exists(SPS_CONFIG_WRAPPER_CONDITION_SUB_CONDITIONS)) {
      $this->setDefaultConditions();
    }
    else {
      $this->conditions_config = $this->manager->getConfigController()->get(SPS_CONFIG_WRAPPER_CONDITION_SUB_CONDITIONS);
      foreach($this->conditions_config as $name => $config) {
        $this->conditions[$name] = $this->manager->getPlugin('condition', $name);
      }
    }

  }

  /**
   * Pull Conditions from the plugin system and load them all in as sub conditions
   *
   * @return \Drupal\sps\Plugins\Condition\WrapperCondition
   *  Self
   */
  protected function setDefaultConditions() {
    foreach($this->manager->getPluginInfo('condition') as $name => $info) {
      if(!isset($info["root_condition"])) {
        $this->conditions[$name] = $this->manager->getPlugin('condition', $name);
      }
    }
    return $this;
  }

  /**
   * Implements ConditionInterface::getOverride().
   *
   * Retrieve the override if it is set.
   *
   * @return bool|\Drupal\sps\Plugins\OverrideInterface
   *  The override with its values set or FALSE if the form has not been
   */
  public function getOverride() {
    return $this->override;
  }

  /**
  * generate a key to use as the basis for the form items
  *
  * This is here so that when recusion is added this can be change to
  * something that varies for each instance.
  *
  * @return String
  */
  public function getActiveConditionKey() {
    return 'active_condition';
  }
  public function getContainerId() {
    return $this->getActiveConditionKey() .'_container';
  }
  public function getSelectorId() {
    return $this->getActiveConditionKey() .'_selector';
  }
  public function getResetId() {
    return $this->getActiveConditionKey() .'_wrapper_reset';
  }
  public function getContainerWrapperId() {
    return $this->getContainerId() .'_wrapper';
    
  }

  /**
   * Implements ConditionInterface::getElement().
   *
   * Gets the form for the condition.
   * This uses ajax to allow the user to select from the other conditions
   * and then submit the settings of that sub condition
   *
   * @see sps_condition_form_validate_callback
   * @see sps_condition_form_submit_callback
   */
  public function getElement($element, &$form_state) {

    //check and see if we have a form_state from previous runs
    if(!isset($form_state['values']) &&
       isset($this->form_state['values'])) {
      $form_state['values'] = $this->form_state['values'];
    }

    $container_id = $this->getContainerId();
    $selector_id = $this->getSelectorId();

    $element[$container_id] = array(
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => "<div id = '".$this->getCOntainerwrapperId()."'>",
      '#suffix' => "</div>",
    );

    // this should be set after an ajax call to select a condition
    if(isset($form_state['values'][$container_id][$selector_id])) {
      $this->active_condition = $form_state['values'][$container_id][$selector_id];
      $condition = $this->conditions[$this->active_condition];
      $sub_state = $form_state;
      $sub_state['values'] = isset($form_state['values'][$container_id][$this->active_condition]) ? $form_state['values'][$container_id][$this->active_condition] : array();
      $element[$container_id][$this->active_condition] = $condition->getElement(array(), $sub_state);
      $element[$container_id][$this->active_condition]['#tree'] = TRUE;

      $element[$container_id][$this->getResetId()] = array(
        '#type' => 'button',
        '#value' => t('Change Condition'),
        '#ajax' => array(
          'callback' => 'sps_wrapper_condition_ajax_callback',
          'wrapper' => $this->getContainerWrapperId(),
          'method' => 'replace',
          'effect' => 'fade',
        ),
        '#attributes' => array(
          'class' => array('sps-change-condition'),
        ),
      );
    }
    else {
      $element[$container_id][$selector_id] = array(
        '#type' => 'select',
        '#title' => 'Condition',
        '#options' => array('none' => 'Select Condition'),
        '#ajax' => array(
          'callback' => 'sps_wrapper_condition_ajax_callback',
          'wrapper' => $this->getCOntainerWrapperId(),
          'method' => 'replace',
          'effect' => 'fade',
        ),
        '#tree' => TRUE,
      );
      foreach($this->conditions as $name => $condition) {
        if ($condition->hasOverrides()) {
          $element[$container_id][$selector_id]['#options'][$name] = $condition->getTitle();
        }
      }
    }
    return $element;
  }


  /**
   * @param $element
   * @param $form_state
   *
   * @return array
   */
  protected function extractSubState($element, $form_state) {

    $container_id= $this->getContainerId();


    $sub_state = $form_state;
    $sub_state['values'] = isset($form_state['values'][$container_id][$this->active_condition]) ?
      $form_state['values'][$container_id][$this->active_condition] : array();

    $sub_element = $element[$container_id][$this->active_condition];

    return array($sub_element, $sub_state);
  }

  /**
   * Implements ConditionInterface::validateElement().
   *
   * Validates the form for the condition by calling the widget's validate function.
   * The widget will be passed only its portion of the form and the values section of
   * $form_state.
   */
  public function validateElement($element, &$form_state) {
    list($sub_element, $sub_state) = $this->extractSubState($element, $form_state);
    if($this->active_condition) {
      $this->conditions[$this->active_condition]->validateElement($sub_element, $sub_state);
    }

    return $this;
  }

  /**
   * Implements ConditionInterface::submitElement().
   *
   * Submits the form for the condition by calling the widget's submit function.
   * The widget will be passed only its portion of the form and the values section of
   * $form_state.
   */
  public function submitElement($element, &$form_state) {

    list($sub_element, $sub_state) = $this->extractSubState($element, $form_state);
    $this->conditions[$this->active_condition]->submitElement($sub_element, $sub_state);
    $this->override = $this->conditions[$this->active_condition]->getOverride();

    $this->override_set = TRUE;

    $this->form_state = $form_state;
    $this->form_state['values'][$this->getContainerId()][$this->getSelectorId()] = $this->active_condition;
    return $this;
  }

}
