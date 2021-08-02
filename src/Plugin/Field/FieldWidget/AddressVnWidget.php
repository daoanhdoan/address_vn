<?php

namespace Drupal\address_vn\Plugin\Field\FieldWidget;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatHelper;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use CommerceGuys\Addressing\AddressFormat\FieldOverrides;
use CommerceGuys\Addressing\Locale;
use Drupal\address\FieldHelper;
use Drupal\address\LabelHelper;
use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;

/**
 * Plugin implementation of the 'address_vn' widget.
 *
 * @FieldWidget(
 *   id = "address_vn",
 *   label = @Translation("Address VN"),
 *   field_types = {
 *     "address"
 *   },
 * )
 */
class AddressVnWidget extends AddressDefaultWidget implements ContainerFactoryPluginInterface
{
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
        'hide_country_code' => FALSE,
        'field_options' => [],
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $overrides = $this->getFieldSetting('field_overrides');
    foreach ($overrides as $field => $data) {
      $field_overrides[$field] = $data['override'];
    }
    $field_overrides = new FieldOverrides($field_overrides);
    $address_format = \Drupal::service('address.address_format_repository')->get('VN');
    $locale = \Drupal::languageManager()->getConfigOverrideLanguage()->getId();
    if (Locale::matchCandidates($address_format->getLocale(), $locale)) {
      $format_string = $address_format->getLocalFormat();
    } else {
      $format_string = $address_format->getFormat();
    }

    $elements = [];
    $field_options = $this->getSetting('field_options');

    $grouped_fields = AddressFormatHelper::getGroupedFields($format_string, $field_overrides);
    $labels = LabelHelper::getFieldLabels($address_format);
    foreach ($grouped_fields as $line_index => $line_fields) {
      foreach ($line_fields as $field_index => $field) {
        $elements[$field]['field'] = array(
          '#type' => 'markup',
          '#markup' => $labels[$field],
        );

        $field_weight = !empty($field_options[$field]['weight']) ? $field_options[$field]['weight'] : 0;
        $title = !empty($field_options[$field]['title']) ? $field_options[$field]['title'] : $labels[$field];
        $elements[$field]['title'] = [
          '#type' => 'textfield',
          '#title' => $labels[$field],
          '#title_display' => 'invisible',
          '#default_value' => $title,
          '#required' => TRUE
        ];
        $elements[$field]['weight'] = array(
          '#type' => 'weight',
          '#title' => $labels[$field],
          '#title_display' => 'invisible',
          '#default_value' => $field_weight,
          '#attributes' => ['class' => ['field-weight']],
        );
        $elements[$field]['#attributes']['class'][] = 'draggable';
        $elements[$field]['#weight'] = $field_weight;
      }
    }
    uasort($elements, [SortArray::class, 'sortByWeightProperty']);
    $elements += [
      '#type' => 'table',
      '#header' => [
        'field' => t('Field'),
        'title' => t('Title'),
        'weight' => t('Weight')
      ],
      '#attributes' => [
        'id' => 'field-widget-address-vn-field-options',
        'class' => ['clearfix']
      ],
      '#tree' => TRUE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-weight',
        ]
      ]
    ];

    $form['field_options'] = $elements;
    $form['hide_country_code'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide the country when only one is available'),
      '#default_value' => $this->getSetting('hide_country_code'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $element += parent::formElement($items, $delta, $element, $form, $form_state);
    $element['address']['#field_options'] = [];

    if ($field_options = $this->getSetting('field_options')) {
      foreach ($field_options as $field => $options) {
        $property = FieldHelper::getPropertyName($field);
        $element['address']['#field_options'][$property] = $options;
      }
    }
    $element['address']['#hide_country_code'] = $this->getSetting('hide_country_code');

    $element['address']['#after_build'][] = [get_class($this), 'makeFieldsOptional'];

    return $element;
  }

  /**
   * Form API callback: Makes all address field properties optional.
   */
  public static function makeFieldsOptional(array $element, FormStateInterface $form_state)
  {
    foreach (Element::getVisibleChildren($element) as $key) {
      if (!empty($element[$key]['#required'])) {
        $element[$key]['#required'] = FALSE;
      }
    }

    if ($field_options = $element['#field_options']) {
      foreach ($field_options as $field => $options) {
        if (!empty($element[$field])) {
          $element[$field]['#weight'] = $options['weight'];
          $element[$field]['#title'] = $options['title'];
        }
      }
    }

    uasort($element, [SortArray::class, 'sortByWeightProperty']);

    if (!empty($element['#hide_country_code'])) {
      $element['country_code']['#attributes']['class'][] = 'visually-hidden';
    }

    return $element;
  }
}
