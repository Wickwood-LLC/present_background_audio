<?php

namespace Drupal\present_background_audio\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\present\Entity\Presentation;
use Drupal\present\Plugin\RevealJSPlugin\RevealJSPluginManager;
use Drupal\present_background_audio\AudioTrackRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding/editing Presentation entities.
 */
class PresentationAudioGuideForm extends EntityForm {

  /**
   * @var \Drupal\present\Plugin\RevealJSPlugin\RevealJSPluginManager
   */
  protected $pluginManager;

  /**
   * Constructs a PresentationForm instance.
   *
   * @param \Drupal\present\Plugin\RevealJSPlugin\RevealJSPluginManager $revealjs_plugin_manager
   *   The RevealJS plugin manager.
   */
  public function __construct(RevealJSPluginManager $revealjs_plugin_manager) {
    $this->pluginManager = $revealjs_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.revealjs_plugins')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if (!$presentation = $form_state->get('presentation')) {
      $presentation = $this->entity;
      $form_state->set('presentation', $presentation);
    }

    /** @var \Drupal\present\Entity\Presentation $presentation */

    if ($this->operation == 'audio_guide') {
      $form['#title'] = $this->t('<em>Presentation Audio Sync Studio for</em> @title', [
        '@title' => $presentation->label(),
      ]);
    }

    if ($form_state->get('preview')) {
      $this->messenger()->addMessage($this->t('You have unsaved changes.'), MessengerInterface::TYPE_WARNING);
    }

    $audio_guide_element_id = Html::getUniqueId('audio_guide');

    $presentation_plugins = $presentation->getPluginInstances();
    /** @var \Drupal\present_background_audio\Plugin\RevealJSPlugin\BackgroundAudio $background_audio */
    $background_audio = $presentation_plugins['background_audio'];
    $background_audio->setConfiguration(['audio_guide_id' => $audio_guide_element_id]);

    $slides = $presentation->getSlides();

    $transition_widths = [
      'default' => 0.8,
      'fast' => 0.4,
      'slow' => 1.2,
    ];

    $start_buttons = $this->scanStartButtons($presentation);

    $start_button_slide_indxes = array_keys($start_buttons);
    $slides_indexes_original = array_keys($slides);
    $slides_indexes = array_keys($slides);
    do {
      // Take off the first start button and use it as the active one.
      $start_button_slide_index = array_shift($start_button_slide_indxes);
      
      // $slide = array_shift($slides_copy);
      $slide_position = array_search($start_button_slide_index, $slides_indexes);
      $slides_indexes = array_slice($slides_indexes, $slide_position);
      $start_buttons[$start_button_slide_index]['slides'][] = array_shift($slides_indexes);
      while ($current_slide = reset($slides_indexes)) {
        if (in_array($current_slide, $start_button_slide_indxes)) {
          break;
        }
        $start_buttons[$start_button_slide_index]['slides'][] = array_shift($slides_indexes);
      }

    } while (!empty($start_button_slide_indxes));

    foreach ($start_buttons as $start_button_slide_index => &$start_button) {
      $start_button['regions'] = [];
      $start = 0;
      // $slide_number = 1;
      foreach ($start_button['slides'] as $slide_index) {
        $slide = $slides[$slide_index];
        $slide_number = array_search($slide_index, $slides_indexes_original) + 1;

        $slide_duration = !empty($slide['autoslide']) ? intval($slide['autoslide']) / 1000 : 0;
        $end = $start + $slide_duration;
        $start_button['regions'][] = AudioTrackRegion::create([
          'id' => "slide_" . $slide_index,
          'start' => $start,
          'end' => $end,
          'content' => 'Slide #' . $slide_number,
          'drag' => $slide_number === 1 ? false : true,
          'resize' => true,
          'type' => 'slide',
        ]);

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
          $start_button['regions'][] = AudioTrackRegion::create([
            'id' => "slide_" . $slide_index . "::fragment_" . $fragment_index,
            'start' => $start,
            'end' => $end,
            'content' => 'Fragment #' . $fragment_index + 1,
            'drag' => true,
            'resize' => true,
            'type' => 'fragment',
          ]);
        }

        $transition_width = $transition_widths[$slide['transition']['speed']];
        if (!$background_audio->getConfiguration()['pause_during_transition'] && $slide_number != count($slides)) {
          $start = $end;
          $end = $start + $transition_width;
          $start_button['regions'][] = AudioTrackRegion::create([
            'id' => "slide_" . $slide_index . "::transition",
            'start' => $start,
            'end' => $end,
            'content' => '⇝ Transition #' . $slide_number,
            'drag' => TRUE,
            'resize' => false,
            'color' => 'rgba(100, 100, 100, 0.8)',
            'type' => 'transition',
            'fixed_size' => $transition_width,
            // 'minLength' => $transition_width, // This may not be needed.
            // 'maxLength' => $transition_width, // This may not be needed.
          ]);
        }
        $start = $end;
      }
    }

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview'),
      '#open' => TRUE,
    ];
    $form['preview']['presentation'] = [
      '#type' => 'revealjs_presentation',
      '#presentation' => $presentation,
      '#attributes' => [
        'style' => ['margin: auto; resize: both;'],
      ]
    ];

    $form['audio_guides'] = [
      '#type' => 'details',
      '#title' => $this->t('Audio'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    foreach ($start_buttons as $start_button) {
      $form['audio_guides'][$start_button['slide_index']]['audio_guide'] = [
        '#type' => 'audio_track_regions',
        '#audio_url' => !empty($start_button['audio']) ? $start_button['audio'] : $background_audio->getConfiguration()['audio_source'],
        '#default_value' => $start_button['regions'],
        '#track_attributes' => [
          'id' => $audio_guide_element_id,
        ],
      ];
    }
    
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    $this->applyChangesToPresentation($form, $form_state);
    /** @var \Drupal\present\Entity\Presentation $presentation */
    $presentation = $this->entity;

    $status = $presentation->save();

    $this->messenger()->addMessage($this->t('Updated Auto-Slide settings for slides to sync with the audio track for %label presentaiton.', [
      '%label' => $presentation->label(),
    ]));

    if ($button['#value'] == $this->t('Save')) {
      $form_state->setRedirectUrl($presentation->toUrl('edit-form'));
    }
  }

  /**
   * Method to apply sync times to presentation.
   */
  protected function applyChangesToPresentation(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\present\Entity\Presentation $presentation */
    $presentation = $this->entity;
    $audio_guides = $form_state->getValue('audio_guides');
    $uuid_pattern = Uuid::VALID_PATTERN;
    foreach ($audio_guides as $slide_index => $audio_guide) {
      $regions = $audio_guide['audio_guide'];
      foreach ($regions as $region) {
        /** @var \Drupal\present_background_audio\AudioTrackRegion $region*/
        $match = NULL;
        preg_match("/slide_(?<slide_index>$uuid_pattern)(\:\:fragment_(?<fragment_index>.+)|\:\:(?<transition>transition))?/", $region->id, $match);
        $slide_index = $match['slide_index'];
        $region_fragment_index = $match['fragment_index'] ?? NULL;
        $transition = $match['transition'] ?? NULL;
  
        $slide = $presentation->getSlide($slide_index);
        if ($slide && !$transition) {
          if (isset($region_fragment_index) && $region_fragment_index !== '') {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = TRUE;
            $dom->formatOutput = TRUE;
            // @ is to suppress warnings about malformed HTML.
            @$dom->loadHTML($slide['content']);
  
            $xpath = new \DOMXPath($dom);
            // Query elements with the class "fragment"
            $fragments = $xpath->query('//*[contains(@class, "fragment")]');
            foreach ($fragments as $fragment_index => $fragment) {
              /** @var \DOMElement $fragment */
              if ($fragment_index == $region_fragment_index) {
                $fragment->setAttribute('data-autoslide', $region->getDuration() * 1000);
                /** @var \DOMElement $body */
                $body = $dom->getElementsByTagName('body')->item(0);
                $children  = $body->childNodes;
                $innerHTML = '';
                foreach ($children as $child) {
                    $innerHTML .= $dom->saveHTML($child);
                }
                $slide['content'] = $innerHTML;
                $presentation->setSlide($slide_index, $slide);
                break;
              }
            }
          }
          else {
            $slide['autoslide'] = $region->getDuration() * 1000;
            $presentation->setSlide($slide_index, $slide);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preview(array $form, FormStateInterface $form_state) {
    $this->applyChangesToPresentation($form, $form_state);
    /** @var \Drupal\present\Entity\Presentation $presentation */
    $presentation = $this->entity;

    $form_state->set('presentation', $presentation);
    $form_state->set('preview', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Returns the action form element for the current entity form.
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);
    // Don't allow the user to delete the entity with this form.
    unset($element['delete']);
    return $element;
  }

  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['save_continue'] = $actions['submit'];
    $actions['save_continue']['#value'] = $this->t('Save and Continue');

    $actions['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Preview'),
      '#submit' => ['::submitForm', '::preview'],
    ];
    return $actions;
  }

  /**
   * Scan the presentation for start buttons.
   *
   */
  protected function scanStartButtons(Presentation $presentation): array {
    $start_buttons = [];
    foreach ($presentation->getSlides() as $slide_index =>$slide) {
      $dom = new \DOMDocument();
      $dom->loadHTML($slide['content']);

      $buttons_xpath = new \DOMXPath($dom);
      // Query elements for start buttons.
      $start_buttons_elements = $buttons_xpath->query('//*[@data-bg-audio-start-button]');
      foreach ($start_buttons_elements as $button_index => $start_buttons_element) {
        /** @var \DOMElement $start_buttons_element */
        $start_buttons[$slide_index] = [
          'slide_index' => $slide_index,
          'button_index' => $button_index,
          // Get button specific audio if set.
          'audio' => $start_buttons_element->getAttribute('data-bg-audio-src'),
          'label' => $start_buttons_element->textContent,
        ];
      }
    }
    return $start_buttons;
  }

}