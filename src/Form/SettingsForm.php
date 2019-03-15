<?php

namespace Drupal\sophron\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

/**
 * Main Sophron settings admin form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The MIME type guesser service.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $guesser;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sophron_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'sophron.settings',
    ];
  }

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $guesser
   *   The MIME type guesser service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatterInterface $date_formatter, MimeTypeGuesserInterface $guesser, StateInterface $state, RequestStack $request_stack) {
    parent::__construct($config_factory);
    $this->dateFormatter = $date_formatter;
    $this->guesser = $guesser;
    $this->state = $state;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('file.mime_type.guesser.extension'),
      $container->get('state'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sophron.settings');
    $form = [];

    $extension = 'jpeg';
    $form['lookup'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('MIME type lookup'),
    ];
    $form['lookup']['entry'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('File extension'),
      '#attributes' => ['class' => ['fieldgroup', 'form-composite']],
      '#description' => $this->t("@todo."),
    ];
    $form['lookup']['entry']['comp'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => [
          'container-inline',
          'fieldgroup',
          'form-composite',
        ],
      ],
    ];
    $form['lookup']['entry']['comp']['extension_string'] = [
      '#type' => 'textfield',
      '#default_value' => $extension,
      '#required' => FALSE,
      '#size' => 20,
      '#maxlength' => 20,
    ];
    $form['lookup']['entry']['comp']['do_lookup'] = [
      '#type'  => 'button',
      '#value' => $this->t('Lookup'),
      '#name' => 'do_lookup',
      '#ajax'  => ['callback' => [$this, 'processAjaxLookup']],
    ];
    $form['lookup']['table'] = $this->buildGuessResultTable($ua);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sophron.settings')
      ->set('enable_automatic_updates',false)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Guesses the file extension and returns results.
   */
  public function processAjaxLookup($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#sophron-parse-results-table', $this->buildGuessResultTable($form_state->getValue(['extension_string']))));
    return $response;
  }

  /**
   * Builds a table render array with results of guessed file extension.
   *
   * @param string $extension
   *   The file extension to be guessed.
   *
   * @return array
   *   A table-type render array.
   */
  protected function buildGuessResultTable($extension) {
    $guess_result = $this->guesser->guess($extension);
    return [
      '#type' => 'table',
      '#id' => 'sophron-parse-results-table',
      '#header' => [
        ['data' => $this->t('User-agent lookup results'), 'colspan' => 2],
      ],
      '#rows' => [
        ['MIME Type:', $guess_result],
      ],
    ];
  }

}
