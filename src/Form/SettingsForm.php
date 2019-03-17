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
use Symfony\Component\Yaml\Yaml;
use FileEye\MimeMap\Extension as MimeTypeExtension;
use FileEye\MimeMap\Type as MimeType;
use FileEye\MimeMap\MappingException as MimeTypeMappingException;
use FileEye\MimeMap\MapHandler;

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

    // Map handling.
    $map_commands = $config->get('map_commands');
    $map = MapHandler::map();
    foreach ($map_commands as $command) {
        try {
            call_user_func_array([$map, $command[0]], $command[1]);
        } catch (MimeTypeMappingException $e) {
            // Do nothing?
        }
    }
    $map->sort();



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
    $form['lookup']['table'] = $this->buildGuessResultTable($extension);

    $form['extensions_table'] = $this->buildExtensionsTable();

    // Image formats map.
    $form['extra_mapping'] = [
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#open' => TRUE,
      '#title' => $this->t('Enable/disable image formats'),
      '#description' => $this->t("Edit the map below to enable/disable image formats. Enabled image file extensions will be determined by the enabled formats, through their MIME types. More information in the module's README.txt"),
    ];
    $form['extra_mapping']['map_commands'] = [
      '#type' => 'textarea',
      '#rows' => 15,
      '#default_value' => Yaml::dump($config->get('map_commands'), 1),
    ];

    return parent::buildForm($form, $form_state);
  }

/*  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setErrorByName('extra_mapping][map_commands', var_export(Yaml::parse($form_state->getValue('map_commands')), TRUE));
  }*/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('sophron.settings');
    $config
      ->set('map_commands', Yaml::parse($form_state->getValue('map_commands')))
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
    $guess_result = $this->guesser->guess('a.' . $extension);
    try {
      $ext = new MimeTypeExtension($extension);
      $default_type = $ext->getDefaultType();
      $mimemap_result = implode(', ', $ext->getTypes());
      $type = new MimeType($default_type);
      $mimemap_aliases = implode(', ', $type->getAliases());
    }
    catch (MimeTypeMappingException $e) {
      $mimemap_result = '';
      $mimemap_aliases = '';
    }
    return [
      '#type' => 'table',
      '#id' => 'sophron-parse-results-table',
      '#header' => [
        ['data' => $this->t('User-agent lookup results'), 'colspan' => 2],
      ],
      '#rows' => [
        ['MIME Type:', $guess_result],
        ['MIME map:', $mimemap_result],
        ['MIME aliases:', $mimemap_aliases],
      ],
    ];
  }

  protected function buildExtensionsTable() {
    // Guess a fake file name just to ensure the guesser loads any mapping
    // alteration through the hooks.
    $this->guesser->guess('fake.png');
        // Use Reflection to get a copy of the protected $mapping property in the
    // guesser class. Get the proxied service first, then the actual mapping.
    $reflection = new \ReflectionObject($this->guesser);
    $proxied_service = $reflection->getProperty('service');
    $proxied_service->setAccessible(TRUE);
    $service = $proxied_service->getValue(clone $this->guesser);
    $reflection = new \ReflectionObject($service);
    $reflection_mapping = $reflection->getProperty('mapping');
    $reflection_mapping->setAccessible(TRUE);
    $mapping = $reflection_mapping->getValue(clone $service);

    $exts = $mapping['extensions'];
    ksort($exts);

    $rows = [];
    foreach ($exts as $ext => $mime_id) {
      $d_guess = $this->guesser->guess('a.' . $ext);
      try {
        $m_guess = (new MimeTypeExtension($ext))->getDefaultType();
      }
      catch (MimeTypeMappingException $e) {
        $m_guess = '';
      }

      if ($m_guess === '') {
        $dd = '+++ MISS';
      }
      elseif (mb_strtolower($d_guess) != mb_strtolower($m_guess)) {
        $dd = '*** diff';
      }
      else {
        $dd = '';
      }

      $rows[] = [$ext, $d_guess, $m_guess,  $dd];
    }

    return [
      '#type' => 'table',
      '#id' => 'sophron-extensions-table',
      '#header' => [
        ['data' => $this->t('Extension')],
      ],
      '#rows' => $rows,
    ];
  }

}
