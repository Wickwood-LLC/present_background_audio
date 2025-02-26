<?php

namespace Drupal\present_background_audio\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\present_background_audio\AudioTrackRegion;
use Exception;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Markup;

/**
 * Provides a render element for an auido guide studio.
 */
#[RenderElement('audio_track_regions')]
class AudioTrackRegions extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#process' => [
        [$class, 'processAudioGuide'],
      ],
      '#audio_url' => NULL,
      '#default_value' => NULL, // This should be an array of AudioTrackRegion objects.
      '#configs' => [],
      '#attributes' => [],
      '#track_attributes' => [],
      '#theme_wrappers' => ['form_element'],
      '#attached' => [
        'library' => ['present_background_audio/audio_guide'],
      ],
      '#value_callback' => [
        [$class, 'valueCallback'],
      ],
    ];
  }

  /**
   * Processes the slide form element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   *
   * @throws \InvalidArgumentException
   *   Thrown when #field_overrides is malformed.
   */
  public static function processAudioGuide(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#tree'] = TRUE;

    $value = $element['#value'];
    foreach ($value as $region) {
      if (!$region instanceof AudioTrackRegion) {
        throw new Exception('#default_value must be an array of AudioTrackRegion objects.');
      }
    }

    $element['audio_track'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
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
      ],
    ];

    $element['audio_track']['#attributes'] = NestedArray::mergeDeep($element['audio_track']['#attributes'], $element['#track_attributes']);

    $element['region_data'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($value),
    ];

    $element['controls'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['audio-controls'],
      ],
    ];

    $element['controls']['play_from_start'] = [
      '#type' => 'button',
      '#value' => t('⏮'),
      '#attributes' => [
        'class' => ['audio-play-from-start'],
        'title' => t('Play from start'),
      ],
    ];
    $element['controls']['play'] = [
      '#type' => 'button',
      '#value' => t('⏯'),
      '#attributes' => [
        'class' => ['audio-play'],
        'title' => t('Play/Pause'),
      ],
    ];

    $element['controls']['play_step_back'] = [
      '#type' => 'button',
      '#value' => t('Play from start of region with cursor'),
      '#attributes' => [
        'class' => ['audio-play-step-back'],
        'title' => t('Play step back'),
      ],
    ];

    $play_rate_options = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
    $play_rate_datalist_id = Html::getUniqueId('play-rate-datalist');
    $play_rate_datalist = Markup::create("<datalist id='$play_rate_datalist_id'>" . implode('', array_map(function ($value) {
      return '<option value="' . $value . '">' . $value . '</option>';
    }, $play_rate_options)) . '</datalist>');

    $element['play_rate'] = [
      '#type' => 'range',
      '#title' => t('Play rate'),
      '#min' => 0.25,
      '#max' => 2,
      '#step' => 0.25,
      '#default_value' => 1,
      '#attributes' => [
        'class' => ['audio-play-rate'],
        'list' => $play_rate_datalist_id,
      ],
      '#suffix' => $play_rate_datalist,
      '#field_suffix' => '<span class="audio-play-rate-value">1x</span>',
    ];

    $element['#element_validate'] = [[static::class, 'validateAudioGuide']];
    $element['#description'] = t('Scroll mouse to zoom in and out. Click and drag regions. Resize the region by dragging the edges.');

    return $element;
  }

  /**
   * Validates the element.
   */
  public static function validateAudioGuide(&$element, FormStateInterface $form_state, &$complete_form) {
    $regions_data = $element['#value'];
    $form_state->setValueForElement($element['region_data'], NULL);
    $form_state->setValueForElement($element, $regions_data);

    $previous_region = NULL;
    foreach ($regions_data as $region_data) {
      /** @var \Drupal\present_background_audio\AudioTrackRegion $region_data */
      if ($previous_region && $previous_region->end != $region_data->start) {
        /** @var \Drupal\present_background_audio\AudioTrackRegion $previous_region */
        $form_state->setError(
          $element['audio_track'],
          t('Regions must not have gaps or overlaps. End of "@previous" region does not match start of "@current" region.', [
            '@previous' => $previous_region->content,
            '@current' => $region_data->content,
          ])
        );
      }
      $previous_region = $region_data;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (is_array($input) && isset($input['region_data'])) {
      $data = [];
      foreach (json_decode($input['region_data']) as $region_data) {
        $data[] = AudioTrackRegion::create((array) $region_data);
      }
      return $data;
    }
    return $element['#default_value'];
  }

}
