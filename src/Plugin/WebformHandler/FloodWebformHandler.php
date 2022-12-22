<?php

namespace Drupal\psu_webform_flood\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A type of handler that implements IP based submission flood control.
 *
 * @WebformHandler(
 *   id = "psu_webform_flood",
 *   label = @Translation("Flood control"),
 *   category = @Translation("Spam prevention"),
 *   description = @Translation("Taps into the flood control system to limit submissions based on IP."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class FloodWebformHandler extends WebformHandlerBase {

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->flood = $container->get('flood');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'threshold' => '3',
      'window' => '60',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['flood'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Flood Settings'),
    ];

    $form['flood']['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Threshold'),
      '#required' => TRUE,
      '#description' => $this->t('The number of submissions allowed per time-window.'),
      '#default_value' => $this->configuration['threshold'],
    ];

    $form['flood']['window'] = [
      '#type' => 'number',
      '#title' => $this->t('Window'),
      '#required' => TRUE,
      '#description' => $this->t('The number of seconds to remember a submission event for.'),
      '#default_value' => $this->configuration['window'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['threshold'] = $form_state->getValue('threshold');
    $this->configuration['window'] = $form_state->getValue('window');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {

    $key = "psu_webform_flood_{$this->getWebform()->id()}_submitted";
    $threshold = $this->configuration['threshold'];
    $window = $this->configuration['window'];

    if (!$this->flood->isAllowed($key, $threshold, $window)) {

      // Exit immediately to stop all processing for this submission.
      header('HTTP/1.1 429 Too Many Requests');
      header('Content-Type: text/html');
      header("Retry-After: $window");
      exit;
    }

    $this->flood->register($key, $window);
  }

}
