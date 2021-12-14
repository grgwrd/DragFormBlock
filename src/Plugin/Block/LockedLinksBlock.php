<?php

namespace Drupal\drupal_form_examples\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Link;
use Drupal\Core\Url;


/**
 * Provides a 'LockedLinksBlock' block.
 *
 * @Block(
 *  id = "locked_footer_links",
 *  admin_label = @Translation("Footer links to turn on and off for all websites."),
 * )
 *
 * @LockedLinksBlock
 * Block for footer region.
 */
class LockedLinksBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    // Configuration variable for global footer links
    return ['footerlinks' => [],
       'label_display' => ''
      ] + parent::defaultConfiguration() ;

  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    // Inherit block parent form class
    $form = parent::blockForm($form, $form_state);

    // Get the current config
    $config = $this->getConfiguration();

    $form['info'] = [
      '#type' => 'item',
      '#markup' =>
        $this->t('<h4>Edit the links in the table below.</h4><h4>Use the Enabled column to remove from display.</h4>
      ')
    ];


    // Create tableset wrapper
    $form['tablerow_tableset'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="table-row-link">',
      '#suffix' => '</div>',
    ];

    //create first row for table header
    $form['tablerow_tableset']['table-row'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Global Footer Links'),
        $this->t('Enabled'),
      ],
      '#empty' => 'Sorry, There are no items.',
    ];


    $links = [];
    //check for already configured links
    if($config['footerlinks']){
      $links = $config['footerlinks'];
    }

    /* Default values for footer links for KU CMS
     *
     * Block is setup to let user edit the url
     * and is able to enable/disable link from display.
     * Otherwise the footerLinks are used by default.
     *
     */
    $footerLinks = array(
      ["link" => ["title"=>"W3C", "url" => "https://www.w3.org/"]],
      ["link" => ["title"=>"Google", "url" => "https://www.google.com/"]],
    );

    // Create rows for global footer links
    for($i = 0; $i < count($footerLinks); $i++){

      // Set config footer links else default links
      $title = $footerLinks[$i]['link']['title'];
      $url = $links[$i]['link']['url'] !== "" ? $links[$i]['link']['url'] : $footerLinks[$i]['link']['url'];
      $enabled = isset($links[$i]['enabled']) ? $links[$i]['enabled'] : TRUE;

      //Create table row.
      $form['tablerow_tableset']['table-row'][$i] = $this->generateRow($title, $url, $enabled);

    }

    return $form;

  }

  /**
   * {@inheritdoc}
   * Validate the form before submitting
   */
  public function blockValidate($form, FormStateInterface $form_state) {

    parent::blockValidate($form, $form_state);

    $rowValues = $form_state->getValues();

    $rows = [];

    $urlHelper = new UrlHelper();

    // Add table items from url and title fields
    foreach ($rowValues as $key => $tableRow) {

      if ($key == 'tablerow_tableset') {

        if ($tableRow['table-row'] !==  "") {

          $rows = $tableRow['table-row'];

          // Validate rows for each url.
          for ($i = 0; $i < count($rows); $i++) {

            $url = $rows[$i]['link']['url'];

            $invalidUrl = FALSE;

            if($url !== null || $url !== ""){

              $isAbsolute = UrlHelper::isValid($url, TRUE);

              // Check to validate url for external, relative, and routes such as <front>
              if (!$isAbsolute){

                $isRelative = UrlHelper::isValid($url);

                // Check to see if valid relative path
                if ($isRelative){

                  // Double check relative path to start with user input characters.
                  if(strpos($url, '/') !== 0 &&
                    strpos($url, '?') !== 0 &&
                    strpos($url, '#') !== 0) {
                    $invalidUrl = TRUE;
                  }

                  //Invalid relative path.
                }else {

                  // One last check for drupal users using <front> and <nolink>
                  // Route pattern
                  $routePath = "/^<[a-z]+>$/";

                  if(!preg_match($routePath, $url)){
                    $invalidUrl = TRUE;
                  }

                }
              }

              if($invalidUrl){
                $form_state->setErrorByName('tablerow_tableset][table-row][' . $i . '][link][url',
                  $this->t("Please enter a valid Url such as https://ku.edu. "
                    . "Or enter a valid internal path starting with forward slash, "
                    . "i.e. /path. You may also enter &lt;front&gt; to link to homepage."));
              }

            }

          } // end validate rows.
        }
      }
    }

    // Provide error message to add links when none have been added.
    if (!$rows) {
      $form_state->setErrorByName('tablerow_tableset', $this->t('Please provide one or more links.'));
    }

  }



  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {

    //form values from submit
    $rows = $form_state->getValues();

    $footerlinks = [];
    //loop through table rows and set link values
    foreach ($rows as $key => $row) {
      if ($key == 'tablerow_tableset') {
        if ($row['table-row'] !==  "") {
          $links = $row['table-row'];
          foreach($links as $link){
            //Set the footer link values
            array_push($footerlinks, $link);

          }
        }
      }
    }

    if(count($footerlinks) > 0){
      //Create footerlinks configuration
      $this->configuration['footerlinks'] = $footerlinks;
    }


  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Configuration items for global footer links
    $config = $this->getConfiguration();

    $rows = [];

    // Check for already configured departmental links
    if($config['footerlinks']){
      $rows = $config['footerlinks'];
    }

    // Return links for template
    $links = [];

    // Rows have values
    if (isset($rows)) {
      // Create links for Info for Block
      if (count($rows) > 0) {

        foreach($rows as $key=>$row){

          $url = $row['link']['url'];

          // Check to make sure link is not blank.
          if($url !== "" || $url !== null){

            // Boolean check if url is http or https url
            $external = UrlHelper::isExternal($url);

            // Route pattern for things like <front>
            $routePath = "/^<[a-z]+>$/";

            // Set value for blank
            $formUrl = '';

            // Use URI for external URL and from User Input for relative path
            if($external){

              // Url with http or https
              $formUrl = Url::fromUri($url);

            }else if(preg_match($routePath, $url)){

              // Internal Drupal route such as <front>
              $formUrl = Url::fromRoute($url);

            }else if(strpos($url, '/') === 0 ||
                    strpos($url, '?') === 0 ||
                    strpos($url, '#') === 0)  {

              // User input has to begin with forward slash like /node/1
              $formUrl = Url::fromUserInput($url);

            }

            $title = $row['link']['title'];

            // Make sure title is not blank and string and url is instance to avoid error.
            if($title !== ""  && $formUrl instanceof Url){
              //links to return to block
              $links[] = Link::fromTextAndUrl($title, $formUrl);
            }
          }
        }

      }
    }


    // Return template to front block display
    $build = [];
    $build['#theme'] = 'locked_footer_links';
    $build['#locked_footer_links'] = $links;

    // Hide title from display
    $build['#title'] = '';

    return $build;

  }

  //generate rows for form table
  public function generateRow($title, $url, $enabled)
  {

    // Set the weight for current row
    $row['#weight'] =  0;

    // Second row for link fields
    $row['link'] = [
      // Title
      'title' => ['#type' => 'hidden',
        '#title' => 'Title',
        '#description' => 'Url label',
        '#disabled' => true,
        '#default_value' =>  $title ],
      // Url
      'url' => ['#type' => 'linkit',
        '#title' => $title,
        '#description' => 'Start typing the title of a piece of content to select it. You can also enter an internal path such as /node/add or an external URL such as http://example.com. Enter &lt;front&gt; to link to the homepage.',
        '#autocomplete_route_name' => 'linkit.autocomplete',
        '#autocomplete_route_parameters' => [
          'linkit_profile_id' => 'all_users'
        ],
        '#default_value' => isset($url) ? $url : ''
      ]
    ];

    // Boolean to enable and disable link from display
    $row['enabled'] = [
          '#type' => 'checkbox',
          '#title' => t('Enable/Disable link'),
          '#title_display' => 'invisible',
          '#default_value' => $enabled,
     ];

    return $row;

  }

}
