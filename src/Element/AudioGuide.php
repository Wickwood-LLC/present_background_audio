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
      '#presentation' => NULL,
      '#transition_width' => 0.8,
      '#options' => [],
      '#attributes' => [],
      '#theme' => 'pba_audio_guide',
      '#attached' => [
        'library' => ['present_background_audio/audio_guide'],
      ],
    ];
  }

  public static function preRender($element) {
    $presentation = $element['#presentation'];
    
    
    /** @var \Drupal\present\Plugin\RevealJSPlugin\RevealJSPluginManager */
    $plugin_manager = \Drupal::service('plugin.manager.revealjs_plugins');
    /** @var \Drupal\present\Plugin\RevealJSPlugin\ConfigurableRevealJSPluginBase */
    $background_audio = $plugin_manager->getPlugin('background_audio', $presentation);

    $slides = $presentation->getSlides();
    $regions = [];

    $start = 0;
    $slide_number = 1;
    foreach ($slides as $slide) {
      $slide_duration = !empty($slide['autoslide']) ? intval($slide['autoslide']) / 1000 : 0;
      $end = $start + $slide_duration;
      $regions[] = [
        'start' => $start,
        'end' => $end,
        'content' => 'Slide #' . $slide_number,
        'drag' => $slide_number === 1 ? false : true,
        'resize' => true,
        'type' => 'slide',
      ];

      $dom = new \DOMDocument();
      $dom->loadHTML($slide['content']);

      $xpath = new \DOMXPath($dom);
      // Query elements with the class "fragment"
      $fragments = $xpath->query('//*[contains(@class, "fragment")]');
      foreach ($fragments as $fragment_index => $fragment) {
        /** @var \DOMElement $fragment */
        $fragment_duration = !empty($fragment->getAttribute('data-autoslide')) ? intval($fragment->getAttribute('data-autoslide')) / 1000 : 0;
        $start = $end;
        $end = $start + $fragment_duration;
        $regions[] = [
          'start' => $start,
          'end' => $end,
          'content' => 'Fragment #' . $fragment_index + 1,
          'drag' => true,
          'resize' => true,
          'type' => 'fragment',
        ];
      }

      if ($slide_number != count($slides)) {
        $start = $end;
        $end = $start + $element['#transition_width'];
        $regions[] = [
          'start' => $start,
          'end' => $end,
          'content' => '⇝ Transition #' . $slide_number,
          'drag' => false,
          'resize' => false,
          'color' => 'rgba(100, 100, 100, 0.8)',
          'type' => 'transition',
          'fixed_size' => $element['#transition_width'],
          'minLength' => $element['#transition_width'], // This may not be needed.
          'maxLength' => $element['#transition_width'], // This may not be needed.
        ];
      }
      $start = $end;
      
      $slide_number++;
    }
    
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
          'url' => $background_audio->getConfiguration()['audio_source'],
          'minPxPerSec' => 100,
        ]),
        'data-regions' => json_encode($regions),
      ],
    ];
    return $element;
  }

}
