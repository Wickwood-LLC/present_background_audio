<?php

namespace Drupal\present_background_audio\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a render element for an auido guide studio.
 */
#[RenderElement('pba_audio_guide')]
class AudioGuide extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#pre_render' => [
        [$class, 'preRender'],
      ],
      '#audio_url' => NULL,
      '#configs' => [],
      '#attributes' => [],
      '#theme' => 'pba_audio_guide',
      '#theme_wrappers' => ['form_element'],
      '#attached' => [
        'library' => ['present_background_audio/audio_guide'],
      ],
    ];
  }

  public static function preRender($element) {
    
    $encoded_region_data = json_encode($element['#default_value']);
    $element['audio_track'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'data-audio-guide' => 'true',
        'class' => ['audio-guide-track'],
        'data-configs' => json_encode(
          $element['#configs'] + [
            'waveColor' => 'rgb(200, 0, 200)',
            'progressColor' => 'rgb(100, 0, 100)',
            'minPxPerSec' => 100,
          ] + [
            'url' => $element['#audio_url'],
          ]
        ),
        'data-regions' => $encoded_region_data,
      ],
    ];

    $element['form_field'] = [
      '#type' => 'hidden',
      '#value' => $encoded_region_data,
    ];
    return $element;
  }

}
