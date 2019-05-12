<?php

namespace Drupal\sophron;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sophron\Event\MapEvent;
use FileEye\MimeMap\Extension;
use FileEye\MimeMap\MapHandler;
use FileEye\MimeMap\MalformedTypeException;
use FileEye\MimeMap\MappingException;
use FileEye\MimeMap\Type;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a sensible mapping between filename extensions and MIME types.
 */
class MimeMapManager {

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
   * @todo
   */
  protected $currentMapClass;

  /**
   * @todo
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
   * @todo
   */
  public function getMapClass() {
    if (!$this->currentMapClass) {
      $this->setMapClass($this->sophronSettings->get('map_class'));
    }
    return $this->currentMapClass;
  }

  /**
   * @todo
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
   * @todo
   */
  public function getMappingErrors($map_class) {
    $this->setMapClass($map_class);
    return isset($this->initializedMapClasses[$map_class]) ? $this->initializedMapClasses[$map_class] : [];
  }

  /**
   * @todo
   */
  public function listTypes() {
    return MapHandler::map($this->getMapClass())->listTypes();
  }

  /**
   * @todo
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
   * @todo
   */
  public function listExtensions() {
    return MapHandler::map($this->getMapClass())->listExtensions();
  }

  /**
   * @todo
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
