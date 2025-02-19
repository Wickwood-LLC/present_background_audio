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
    
    $form['audio_guide'] = [
      '#type' => 'pba_audio_guide',
      '#presentation' => $presentation,
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