<?php

namespace Drupal\sophron;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sophron\Event\MapEvent;
use Drupal\sophron\Map\DrupalMap;
use FileEye\MimeMap\Extension;
use FileEye\MimeMap\Map\AbstractMap;
use FileEye\MimeMap\Map\DefaultMap;
use FileEye\MimeMap\MapHandler;
use FileEye\MimeMap\MalformedTypeException;
use FileEye\MimeMap\MappingException;
use FileEye\MimeMap\Type;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a sensible mapping between filename extensions and MIME types.
 */
class MimeMapManager implements MimeMapManagerInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module configuration settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $sophronSettings;

  /**
   * The FQCN of the map currently in use.
   *
   * @var string
   */
  protected $currentMapClass;

  /**
   * The array of initialized map classes.
   *
   * Keyed by FQCN, each value stores the array of initialization errors.
   *
   * @var array
   */
  protected $initializedMapClasses = [];

  /**
   * Constructs a MimeMapManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EventDispatcherInterface $dispatcher) {
    $this->configFactory = $config_factory;
    $this->sophronSettings = $this->configFactory->get('sophron.settings');
    $this->eventDispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function isMapClassValid($map_class) {
    if (class_exists($map_class) && in_array(AbstractMap::class, class_parents($map_class))) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMapClass() {
    if (!$this->currentMapClass) {
      switch ($this->sophronSettings->get('map_option')) {
        case static::DRUPAL_MAP:
          $this->setMapClass(DrupalMap::class);
          break;

        case static::DEFAULT_MAP:
          $this->setMapClass(DefaultMap::class);
          break;

        case static::CUSTOM_MAP:
          $map_class = $this->sophronSettings->get('map_class');
          $this->setMapClass($this->isMapClassValid($map_class) ? $map_class : DrupalMap::class);
          break;

      }
    }
    return $this->currentMapClass;
  }

  /**
   * {@inheritdoc}
   */
  public function setMapClass($map_class) {
    $this->currentMapClass = $map_class;
    if (!isset($this->initializedMapClasses[$map_class])) {
      $event = new MapEvent($map_class);
      $this->eventDispatcher->dispatch(MapEvent::INIT, $event);
      $this->initializedMapClasses[$map_class] = $event->getErrors();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingErrors($map_class) {
    $this->setMapClass($map_class);
    return isset($this->initializedMapClasses[$map_class]) ? $this->initializedMapClasses[$map_class] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function listTypes() {
    return MapHandler::map($this->getMapClass())->listTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function getType($type) {
    try {
      return new Type($type, $this->getMapClass());
    }
    catch (MalformedTypeException $e) {
      return NULL;
    }
    catch (MappingException $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listExtensions() {
    return MapHandler::map($this->getMapClass())->listExtensions();
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension($extension) {
    try {
      return new Extension($extension, $this->getMapClass());
    }
    catch (MappingException $e) {
      return NULL;
    }
  }

}
