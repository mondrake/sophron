<?php

namespace Drupal\sophron\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sophron\MimeMapManager;
use Drupal\sophron\CoreExtensionMimeTypeGuesserExtended;
use Drupal\sophron\Map\DrupalMap;
use FileEye\MimeMap\Map\DefaultMap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Main Sophron settings admin form.
 */
class SettingsForm extends ConfigFormBase {

  use SchemaCheckTrait;

  /**
   * @todo
   */
  protected $mimeMapManager;

  /**
   * The typed config service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

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
   * @todo
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MimeMapManager $mime_map_manager, TypedConfigManagerInterface $typed_config) {
    parent::__construct($config_factory);
    $this->mimeMapManager = $mime_map_manager;
    $this->typedConfig = $typed_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sophron.mime_map.manager'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sophron.settings');

    // Vertical tabs.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#tree' => FALSE,
    ];

    // Mapping.
    $form['mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Mapping'),
      '#description' => $this->t("Manage additional MIME types and mapping issues."),
      '#group' => 'tabs',
    ];
    $options = [
      DrupalMap::class => $this->t("Drupal map."),
      DefaultMap::class => $this->t("MimeMap default map."),
    ];
    $form['mapping']['map_class'] = [
      '#type' => 'radios',
      '#title' => $this->t('Map'),
      '#default_value' => $config->get('map_class'),
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $this->t("Select the map to use."),
    ];

    // Allow mapping commands in the admin UI only for PHP 7+. This is because
    // running the mapping routine for lower version expose the module to
    // fatal error risks that cannot be caught before PHP 7.
    if (PHP_VERSION_ID >= 70000) {
      $commands = $config->get('map_commands');
      $form['mapping']['map_commands'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Mapping commands'),
        '#description' => $this->t("The commands below alter the default MIME type mapping. More information in the module's README.md file."),
        '#description_display' => 'before',
        '#rows' => 5,
        '#default_value' => empty($commands) ? '' : Yaml::dump($commands, 1),
      ];
    }

    // Mapping errors.
    if ($errors = $this->mimeMapManager->getMappingErrors($config->get('map_class'))) {
      $form['mapping']['mapping_errors'] = [
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#open' => TRUE,
        '#title' => $this->t("Mapping errors"),
        '#description' => $this->t("The list below shows the errors occurring in applying mapping commands to the map. Correct them to clean up the list."),
      ];

      $rows = [];
      foreach ($errors as $error) {
        $rows[] = [
          $error['method'],
          "'" . implode("', '", $error['args']) . "'",
          $error['type'],
          $error['message'],
        ];
      }

      $form['mapping']['mapping_errors']['table'] = [
        '#type' => 'table',
        '#id' => 'sophron-mapping-errors-table',
        '#header' => [
          ['data' => $this->t('Method')],
          ['data' => $this->t('Arguments')],
          ['data' => $this->t('Error')],
          ['data' => $this->t('Description')],
        ],
        '#rows' => $rows,
      ];
    }

    // Mapping gaps.
    if ($gaps = $this->determineMapGaps($config->get('map_class'))) {
      $form['mapping']['gaps'] = [
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#open' => TRUE,
        '#title' => $this->t("Mapping gaps"),
        '#description' => $this->t("The list below shows the gaps of the current map vs. Drupal's core MIME type mapping. Overcome the gaps by adding additional mapping commands."),
      ];
      $form['mapping']['gaps']['table'] = [
        '#type' => 'table',
        '#id' => 'sophron-mapping-gaps-table',
        '#header' => [
          ['data' => $this->t('File extension')],
          ['data' => $this->t('Drupal core MIME type')],
          ['data' => $this->t('Gap')],
        ],
        '#rows' => $gaps,
      ];
    }

    // Mime types.
    $form['types'] = [
      '#type' => 'details',
      '#title' => $this->t('MIME types'),
      '#description' => $this->t("List of MIME types and their file extensions."),
      '#group' => 'tabs',
    ];
    $rows = [];
    $i = 1;
    foreach ($this->mimeMapManager->listTypes() as $type_string) {
      if ($type = $this->mimeMapManager->getType($type_string)) {
        $rows[] = [
          $i++,
          $type_string,
          implode(', ', $type->getExtensions()),
          $type->getDescription(),
          implode(', ', $type->getAliases()),
        ];
      }
    }
    $form['types']['table'] = [
      '#type' => 'table',
      '#id' => 'sophron-mime-types-table',
      '#header' => [
        ['data' => $this->t('#')],
        ['data' => $this->t('MIME Type')],
        ['data' => $this->t('File extensions')],
        ['data' => $this->t('Description')],
        ['data' => $this->t('Aliases')],
      ],
      '#rows' => $rows,
    ];

    // File extensions.
    $form['extensions'] = [
      '#type' => 'details',
      '#title' => $this->t('File extensions'),
      '#description' => $this->t("List of file extensions and their MIME types."),
      '#group' => 'tabs',
    ];
    $rows = [];
    $i = 1;
    foreach ($this->mimeMapManager->listExtensions() as $extension_string) {
      if ($extension = $this->mimeMapManager->getExtension($extension_string)) {
        $rows[] = [
          $i++,
          $extension_string,
          implode(', ', $extension->getTypes()),
          $this->mimeMapManager->getType($extension->getDefaultType())->getDescription(),
        ];
      }
    }
    $form['extensions']['table'] = [
      '#type' => 'table',
      '#id' => 'sophron-extensions-table',
      '#header' => [
        ['data' => $this->t('#')],
        ['data' => $this->t('File extension')],
        ['data' => $this->t('MIME types')],
        ['data' => $this->t('Description')],
      ],
      '#rows' => $rows,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (PHP_VERSION_ID >= 70000 && $form_state->getValue('map_commands') !== '') {
      try {
        $map_commands = Yaml::parse($form_state->getValue('map_commands'));
        $data = $this->configFactory->get('sophron.settings')->get();
        $data['map_commands'] = $map_commands;
        $schema_errors = $this->checkConfigSchema($this->typedConfig, 'sophron.settings', $data);
        if (is_array($schema_errors)) {
          $fail_items = [];
          foreach ($schema_errors as $key => $value) {
            $matches = [];
            if (preg_match('/sophron\.settings\:map\_commands\.(\d+)/', $key, $matches)) {
              $item = (int) $matches[1] + 1;
              $fail_items[$item] = $item;
            }
          }
          $form_state->setErrorByName('map_commands', $this->t("The items at line(s) @lines are wrongly typed. Make sure they follow the pattern '- [method, [arg1, ..., argN]]'.", [
            '@lines' => implode(', ', $fail_items),
          ]));
        }
      }
      catch (\Exception $e) {
        $form_state->setErrorByName('map_commands', $this->t("YAML syntax error: @error", ['@error' => $e->getMessage()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('sophron.settings');

    try {
      $config->set('map_class', $form_state->getValue('map_class'));
      if (PHP_VERSION_ID >= 70000) {
        $commands = Yaml::parse($form_state->getValue('map_commands'));
        $config->set('map_commands', $commands ?: []);
      }
      $config->save();
    }
    catch (\Exception $e) {
      // Do nothing.
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * @todo
   *
   * @param string $map_class
   *   @todo ADD IT!
   *
   * @return array
   *   A table-type render array.
   */
  protected function determineMapGaps() {
    $core_extended_guesser = new CoreExtensionMimeTypeGuesserExtended();

    $exts = $core_extended_guesser->listExtensions();
    sort($exts);

    $rows = [];
    foreach ($exts as $ext) {
      $d_guess = $core_extended_guesser->guess('a.' . $ext);
      $m_guess = '';
      $exto = $this->mimeMapManager->getExtension($ext);
      if ($exto) {
        try {
          $m_guess = $exto->getDefaultType();
        }
        catch (\Exception $e) {
          // Do nothing.
        }
      }

      if ($m_guess === '') {
        $gap = $this->t('No MIME type mapped to this file extension in Sophron.');
      }
      elseif (mb_strtolower($d_guess) != mb_strtolower($m_guess)) {
        $gap = $this->t("File extension mapped to '@type' in Sophron instead.", ['@type' => $m_guess]);
      }
      else {
        $gap = '';
      }

      if ($gap !== '') {
        $rows[] = [$ext, $d_guess, $gap];
      }
    }

    return $rows;
  }

}
