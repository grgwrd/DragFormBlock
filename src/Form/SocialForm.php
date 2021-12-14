<?php

namespace Drupal\drupal_form_examples\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SocialForm.
 */
class SocialForm extends ConfigFormBase {


  //social media keys for social settings
  private $socials = array('facebook', 'instagram', 'linkedin', 'youtube', 'twitter', 'pinterest');

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_form';
  }

  protected function getEditableConfigNames() {
    return [
      'drupal_form_examples.social',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $social_config = $this->config('drupal_form_examples.social');

    $form = [];

    $form['#tree'] = TRUE;

    foreach($this->socials as $key) {

      $social_name = ucfirst($key);

      $form[$key] = [
        '#type' => 'url',
        '#title' => $this->t($social_name),
        '#default_value' => $social_config->get($key),
        '#description' => $this->t('Enter the URL for '.$social_name.' account. Leave blank if no account available.'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('drupal_form_examples.social');

    foreach($this->socials as $key){
        $config->set($key, $form_state->getValue($key));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }


}
