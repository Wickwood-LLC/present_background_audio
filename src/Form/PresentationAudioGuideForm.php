<?php

namespace Drupal\present_background_audio\Form;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
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

    /** @var \Drupal\present\Plugin\RevealJSPlugin\RevealJSPluginManager */
    $plugin_manager = \Drupal::service('plugin.manager.revealjs_plugins');
    /** @var \Drupal\present\Plugin\RevealJSPlugin\ConfigurableRevealJSPluginBase */
    $background_audio = $plugin_manager->getPlugin('background_audio', $presentation);

    $slides = $presentation->getSlides();
    $regions = [];
    $transition_widths = [
      'default' => 0.8,
      'fast' => 0.4,
      'slow' => 1.2,
    ];

    $start = 0;
    $slide_number = 1;
    foreach ($slides as $slide_index => $slide) {
      $slide_duration = !empty($slide['autoslide']) ? intval($slide['autoslide']) / 1000 : 0;
      $end = $start + $slide_duration;
      $regions[] = AudioTrackRegion::create([
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
        $regions[] = AudioTrackRegion::create([
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
      if ($slide_number != count($slides)) {
        $start = $end;
        $end = $start + $transition_width;
        $regions[] = AudioTrackRegion::create([
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

      $slide_number++;
    }

    $form['audio_guide'] = [
      '#type' => 'audio_track_regions',
      '#audio_url' => $background_audio->getConfiguration()['audio_source'],
      '#default_value' => $regions,
    ];
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
    /** @var \Drupal\present\Entity\Presentation $presentation */
    $presentation = $this->entity;
    $regions = $form_state->getValue('audio_guide');
    $uuid_pattern = Uuid::VALID_PATTERN;
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

    $status = $presentation->save();

    $this->messenger()->addMessage($this->t('Updated audio guide settings for %label presentaiton.', [
      '%label' => $presentation->label(),
    ]));

    $form_state->setRedirectUrl($presentation->toUrl('edit-form'));
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

}