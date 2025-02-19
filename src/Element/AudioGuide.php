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
      '#options' => [],
      '#attributes' => [],
      '#theme' => 'pba_audio_guide',
      '#attached' => [
        'library' => ['present_background_audio/audio_guide'],
      ],
    ];
  }

  public static function preRender($element) {
    
    
    $element['audio_track'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => 'audio-guide-track',
        'data-audio-guide' => 'true',
        'class' => ['audio-guide-track'],
        'data-configs' => json_encode([
          'waveColor' => 'rgb(200, 0, 200)',
          'progressColor' => 'rgb(100, 0, 100)',
          'url' => $element['#audio_url'],
          'minPxPerSec' => 100,
        ]),
        'data-regions' => $element['#default_value'],
      ],
    ];
    return $element;
  }

}
