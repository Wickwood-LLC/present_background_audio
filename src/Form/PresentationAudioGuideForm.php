<?php

namespace Drupal\present_background_audio\Form;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\present\Element\RevealJSPresentation;
use Drupal\present\Entity\Presentation;
use Drupal\present\Plugin\RevealJSPlugin\RevealJSPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

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
      $form['#title'] = $this->t('<em>Presentation Audio Guide Studio for</em> @title', [
        '@title' => $presentation->label(),
      ]);
    }   

    /** @var \Drupal\present\Plugin\RevealJSPlugin\ConfigurableRevealJSPluginBase */
    $background_audio = $this->pluginManager->getPlugin('background_audio', $presentation);

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

      $start = $end;
      $end = $start + 0.8;
      $regions[] = [
        'start' => $start,
        'end' => $end,
        'content' => 'Transition #' . $slide_number,
        'drag' => false,
        'resize' => false,
        'color' => 'rgba(100, 100, 100, 0.5)',
        'type' => 'transition',
        'fixed_size' => 0.8,
        'minLength' => 0.8, // This may not be needed.
        'maxLength' => 0.8, // This may not be needed.
      ];
      $start = $end;
      
      $slide_number++;
    }
    
    $form['audio_guide'] = [
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

    $form['#attached']['library'][] = 'present_background_audio/audio_guide';
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
    $entity = $this->entity;
    $status = $entity->save();

    $this->messenger()->addMessage($this->t('Updated audio guide settings for %label presentaiton.', [
      '%label' => $entity->label(),
    ]));

    $form_state->setRedirectUrl($entity->toUrl('collection'));
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