<?php

namespace Drupal\present_background_audio\Plugin\RevealJSPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\present\Attribute\RevealJSPlugin;
use Drupal\present\Plugin\RevealJSPlugin\ConfigurableRevealJSPluginBase;

#[RevealJSPlugin(
  id: 'background_audio',
  label: new TranslatableMarkup('Background Auido'),
  revealjs_plugin_name: 'RevealBackgroundAudio',
)]
class BackgroundAudio extends ConfigurableRevealJSPluginBase {

  public function defaultConfiguration(): array {
    return [
      'audio_source' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraryName(): string {
    return 'present_background_audio/reveal-bg-audio';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['audio_source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Audio File Path'),
      '#description' => $this->t('URL to the audio file to use for bacground playing. You may omit this and set "data-bg-audio-src" attribute of individual audio start buttons.'),
      '#default_value' => $this->configuration['audio_source'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['audio_source'] = $form_state->getValue('audio_source');
  }

  /**
   * {@inheritdoc}
   */
  public function alterRevealJSConfig(&$config) {
    $config['background_audio'] = $this->configuration['audio_source'];
  }
}
