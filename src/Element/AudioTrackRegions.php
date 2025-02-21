<?php

namespace Drupal\present_background_audio\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\present_background_audio\AudioTrackRegion;
use Exception;

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

    $element['play_mode'] = [
      '#type' => 'radios',
      '#title' => t('Play mode'),
      '#options' => [
        'full' => t('Play rest of the audio track from where clicked'),
        'region' => t('Play only the clicked region'),
      ],
      '#default_value' => $element['#play_mode'] ?? 'full',
    ];

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

    $element['region_data'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($value),
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
