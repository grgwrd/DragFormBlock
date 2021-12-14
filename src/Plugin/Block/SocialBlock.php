<?php

namespace Drupal\drupal_form_examples\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal;
/**
 * Provides a 'SocialBlock' block.
 *
 * @Block(
 *  id = "social_block",
 *  admin_label = @Translation("Social block"),
 * )
 *
 * @SocialBlock
 * Block social media links.
 */
class SocialBlock extends BlockBase {




  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'label_display' => '',
      ] + parent::defaultConfiguration();
  }


  private $socials = array("facebook", "instagram", "twitter", "youtube", "pinterest", "linkedin");


  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $socialConfig = $config = Drupal::config('drupal_form_examples.social');

    $social_settings = Link::fromTextAndUrl(t('Social Media'),
      Url::fromRoute('drupal_form_examples.social_form'))->toString();

    $form['#tree'] = TRUE;

    $form['heading']['#markup'] = '<h3>The below fields are configured in '.$social_settings.'.</h3>';

    foreach($this->socials as $key) {
      $form[$key] = [
        '#type' => 'url',
        '#title' => $this->t(ucfirst($key)),
        '#default_value' => $socialConfig->get($key),
        '#disabled' => 'true',

      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
//  public function blockSubmit($form, FormStateInterface $form_state) {
//      foreach($this->socialLinks as $key) {
//          $this->configuration[$key] = $form_state->getValue($key);
//      }
//  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    //get settings from kute site settings config
    $socialConfig = Drupal::config('drupal_form_examples.social');

    $socialLinks = [];

    //create array for template
    foreach($this->socials as $key){

      // Social link.
      $link = $socialConfig->get($key);

      // Only let filled in fields pass to avoid error from Url function.
      if($link != null || $link != ""){

        // Font awesome classes for each social icon.
        $iconClass = "fa-".$key ? $key != 'facebook' : "fa-".$key."-square";

        // Create social links for block.
        $socialLinks[$key] = array(
          '#type' => 'link',
          '#title' => array('#type' => 'html_tag',
            '#tag' => 'i',
            '#attributes' => [
              'class'=>'fab '.$iconClass,
              'title' => $this->t(ucfirst($key))
            ],
            array('#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => [
                'class' => 'sr-only'
              ],
              '#value' => $key
            )
          ),
          '#attributes' => [
            'class' => ['pr-3'],
          ],
          '#url' => Url::fromUri($link),
        );
      }
    }

    //return block template
    $build = [];
    $build['#theme'] = 'social_block';
    $build['#social_links'] = $socialLinks;

    // Invalidate tags for config.
    $build['#cache'] = [
      'tags' => $socialConfig->getCacheTags()
    ];

    // Hide title from display
    $build['#title'] = '';

    return $build;
  }

}
