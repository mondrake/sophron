<?php

namespace Drupal\sophron\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sophron\Event\MapEvent;
use FileEye\MimeMap\MapHandler;
use FileEye\MimeMap\MalformedTypeException;
use FileEye\MimeMap\MappingException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Sophron's module Event Subscriber.
 */
class SophronEventSubscriber implements EventSubscriberInterface {

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
   * Constructs a SophronEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->sophronSettings = $this->configFactory->get('sophron.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MapEvent::INIT => 'initializeMap',
    ];
  }

  public function initializeMap(MapEvent $event) {
    // Run additional commands mapping only for PHP 7+. This is because running
    // the mapping routine for lower version expose the module to fatal error
    // risks that cannot be caught before PHP 7.
    if (PHP_VERSION_ID < 70000) {
      return;
    }
    $map_commands = $this->sophronSettings->get('map_commands');
    $map = MapHandler::map($event->getMapClass());
    foreach ($map_commands as $command) {
      $method = isset($command[0]) ? $command[0] : '';
      $args = isset($command[1]) ? $command[1] : [];
      try {
        if (!is_callable([$map, $method])) {
          throw new \InvalidArgumentException("Non-existing mapping method '{$method}'");
        }
        call_user_func_array([$map, $method], $args);
      }
      catch (MappingException $e) {
        $event->addError((string) $method, (array) $args, 'Mapping', $e->getMessage());
      }
      catch (MalformedTypeException $e) {
        $event->addError((string) $method, (array) $args, 'Invalid MIME type syntax', $e->getMessage());
      }
      catch (\Exception $e) {
        $event->addError((string) $method, (array) $args, 'Other', $e->getMessage());
      }
      catch (\Error $e) {
        $event->addError((string) $method, (array) $args, 'Error', $e->getMessage());
      }
    }
    $map->sort();
  }

}