<?php

namespace Drupal\address_vn\EventSubscriber;

use Drupal\address\Event\AddressEvents;
use Drupal\address\Event\AddressFormatEvent;
use Drupal\address\Event\SubdivisionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to Address events for testing.
 *
 * @see \Drupal\Tests\address\FunctionalJavascript\AddressDefaultWidgetTest::testEvents()
 */
class AddressVnEventSubscriber implements EventSubscriberInterface
{

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    $events[AddressEvents::ADDRESS_FORMAT][] = ['onAddressFormat'];
    $events[AddressEvents::SUBDIVISIONS][] = ['onSubdivisions'];
    return $events;
  }

  /**
   * Alters the available countries.
   *
   * @param \Drupal\address\Event\AvailableCountriesEvent $event
   *   The available countries event.
   */
  public function onAddressFormat(AddressFormatEvent $event)
  {
    $definition = $event->getDefinition();
    if ($definition['country_code'] == 'VN') {
      $definition['format'] = str_replace("%locality", "%dependentLocality\n%locality", $definition['format']);
      $definition['administrative_area_type'] = 'province';
      $definition['locality_type'] = 'district';
      $definition['dependent_locality_type'] = 'neighborhood';
      $definition['subdivision_depth'] = 3;
      $event->setDefinition($definition);
    }
  }

  /**
   * Provides the subdivisions for Great Britain.
   *
   * Note: Provides just the Welsh counties. A real subscriber would include
   * the full list, sourced from the CLDR "Territory Subdivisions" listing.
   *
   * @param \Drupal\address\Event\SubdivisionsEvent $event
   *   The subdivisions event.
   */
  public function onSubdivisions(SubdivisionsEvent $event)
  {
    // For administrative areas $parents is an array with just the country code.
    // Otherwise it also contains the parent subdivision codes. For example,
    // if we were defining cities in California, $parents would be ['US', 'CA'].
    $parents = $event->getParents();
    $country_code = $parents[0];
    if ($country_code != 'VN') {
      return;
    }

    $definitions = [
      'country_code' => $country_code,
      'parents' => $event->getParents()
    ];

    $file = @file_get_contents(__DIR__ . "/../../lib/VN.json");
    $data = @json_decode($file);

    if (empty($data)) {
      return;
    }

    $data = (object)[$country_code => $data];

    $subdivisions = [];
    foreach ($parents as $parent) {
      if ($subdivisions) {
        $subdivisions = $subdivisions->{$parent};
      } else {
        $subdivisions = $data->{$parent};
      }
    }
    if ($subdivisions) {
      foreach ($subdivisions as $name => $subdivision) {
        if (is_array($subdivision) || is_object($subdivision)) {
          $definitions['subdivisions'][$name] = ['has_children' => TRUE];
        } else {
          $definitions['subdivisions'][$subdivision] = ['has_children' => FALSE];
        }
      }
    }

    $event->setDefinitions($definitions);
  }
}
