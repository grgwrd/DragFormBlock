<?php

namespace Drupal\drupal_form_examples\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Link;

/**
 * Provides a 'LinksBlock' block.
 *
 * @Block(
 *  id = "custom_links_block",
 *  admin_label = @Translation("List of links for page block."),
 * )
 *
 * @LinksBlock
 * Block for website links.
 *
 */
class LinksBlock extends BlockBase {


  public function __construct(array $configuration, $plugin_id, $plugin_definition) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);

  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    // Empty array for links
    return  parent::defaultConfiguration() + [
        'linksBlockConfig' => [],
        'label_display' => '',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

   parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $rows = [];

    // Check for already configured links
    if($config['linksBlockConfig']){
      $rows = $config['linksBlockConfig'];
    }

    $form =  $this->generateDraggableTable($rows, $form_state);

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

    // Add table items from url and title fields
    foreach ($rowValues as $key => $tableRow) {
      if ($key == 'dragtablerow_tableset') {
        if ($tableRow['table-row'] !== "") {

          // Link rows from table.
          $rows = $tableRow['table-row'];

          // Validate rows for each url.
          for ($i = 0; $i < count($rows); $i++) {

            $url = $rows[$i]['link']['url'];
            $title = $rows[$i]['link']['title'];

            $invalidUrl = FALSE;

            if(($title !== null || $title !== "") && ($url !== null || $url !== "")){

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

                  // Route pattern
                  $routePath = "/^<[a-z]+>$/";
                  // One last check for drupal users using <front> and <nolink>
                  if(!preg_match($routePath, $url)){
                    $invalidUrl = TRUE;
                  }

                }
              }

              if($invalidUrl){
                $form_state->setErrorByName('dragtablerow_tableset][table-row][' . $i . '][link][url',
                  $this->t("Please enter a valid Url with https:// "
                    . "Or enter a valid internal path starting with forward slash, "
                    . "i.e. /path. You may also enter &lt;front&gt; to link to homepage."));
              }

            }else if($url !== null || $url !== ""){
              // Set error no title for url.
              $form_state->setErrorByName('dragtablerow_tableset][table-row][' . $i . '][link][title', $this->t("Please enter a title for url."));
            }

          } // end validate rows.
        }
      }
    }

    //call back function to sort array weights
    uasort($rows, array($this, "sortTableRows"));

    //reset sort array indexes
    $rows = array_values($rows);

    // Provide error message to add links when none have been added.
    if (!$rows) {
      $form_state->setErrorByName('dragtablerow_tableset', t('Please provide one or more links.'));
    }

  }

  /**
   * {@inheritdoc}
   * Submit Departmental Links Table
   */
  public function blockSubmit($form, FormStateInterface $form_state)
  {

    //form values from submit
    $rows = $form_state->getValues();

    $linksBlockConfig = [];
    //loop through table rows and set link values
    foreach ($rows as $key => $row) {
      if ($key == 'dragtablerow_tableset') {
        if ($row['table-row'] !== "") {
          $links = $row['table-row'];
          foreach ($links as $link) {

            //Set the footer link values
            $url = $link['link']['url'];
            $title = $link['link']['title'];

            // Only add links to array with title and url.
            if (($title !== null || $title !== "") && ($url !== null || $url !== "")) {
              array_push($linksBlockConfig, $link);
            }

          }
        }
      }

      if (count($linksBlockConfig) > 0) {
        //Create footerlinks configuration
        $this->configuration['linksBlockConfig'] = $linksBlockConfig;
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function build()
  {

    $rows = $this->configuration['linksBlockConfig'];

    // Return links for template
    $links = [];

    // Rows have values
    if (isset($rows)) {

      // Create links for Block
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
            if($title !== "" && $formUrl instanceof Url){
              //links to return to block
              $links[] = Link::fromTextAndUrl($title, $formUrl);
            }
          }
        }

      }
    }

    // return template block page
    return array(
      '#theme' => 'custom_links_block',
      '#links' => $links,
      '#title' => ''
    );
  }

  public function generateDraggableTable($links, FormStateInterface $form_state)
  {


    $form['info'] = [
      '#type' => 'item',
      '#markup' =>
        $this->t('<h3>Create links with the table below.</h3>
      <p>The first link is required. Use the Add Link and Remove Link buttons
      to add/remove links after the first required link. </p>')
    ];

    //create tableset wrapper
    $form['dragtablerow_tableset'] = [
      '#type' => 'fieldset',
      '#title' => 'Add a link and enter a title and url.',
      '#description' => 'Add or remove link at the bottom of your list.',
      '#prefix' => '<div id="drag-table-row-link">',
      '#suffix' => '</div>',
    ];

    //create first row for table header
    $form['dragtablerow_tableset']['table-row'] = [
      '#type' => 'table',
      '#empty' => 'No links to display.',

      // TableDrag: Each array value is a list of callback arguments for
      // drupal_add_tabledrag(). The #id of the table is automatically
      // prepended; if there is none, an HTML ID is auto-generated.
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    // Check to see the num of items in form
    if (!$form_state->has('num_rows')) {
      $form_state->set('num_rows', count($links));
    }

    // Get num of items and set form display for fields
    $num_rows = $form_state->get('num_rows');

    // To keep track of top weight
    $topWeight = 0;

    for ($i = 0; $i < $num_rows; $i++) {

      //On last loop
      if ($i == $num_rows - 1) {

        // Set last variable for first time
        if (!$form_state->has('last_weight')) {
          $form_state->set('last_weight', $links[$i]['weight']);
        }

        // Get the last weight for add more when ''
        $topWeight = $form_state->get('last_weight');

      }

      // Set values for link rows.
      $title = $links[$i]['link']['title'] ?: '';
      $url = $links[$i]['link']['url'] ?: '';
      $weight = $links[$i]['weight'] ?: ($topWeight);

      $form['dragtablerow_tableset']['table-row'][$i] = $this->generateRow($i, $title, $url, $weight);

    }

    // Set actions for form submit
    $form['dragtablerow_tableset']['actions'] = [
      '#type' => 'actions'
    ];

    // Add more item button
    $form['dragtablerow_tableset']['actions']['add_item'] = [
      '#type' => 'submit',
      '#value' => t('Add link'),
      '#submit' => [[$this, 'addOne']],
      '#attributes' => [
        'class' => ['use-ajax']],
      '#ajax' => [
        'callback' => [$this, 'addmoreCallback'],
        'wrapper' => 'drag-table-row-link',
      ],
      '#limit_validation_errors' => [],
    ];

    // Remove item when items have been added
    if ($num_rows > 1) {
      $form['dragtablerow_tableset']['actions']['remove_item'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove link'),
        '#submit' => [[$this, 'removeCallback']],
        '#attributes' => [
          'class' => ['use-ajax']],
        '#ajax' => [
          'callback' => [$this, 'addmoreCallback'],
          'wrapper' => 'drag-table-row-link',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }


  public function generateRow($index, $title, $url, $weight = null)
  {

    $row['name'] = [
      '#markup' =>'Drag',
    ];

    //Add draggable class for row
    $row['#attributes']['class'][] = 'draggable';
    // TableDrag: Sort the table row according to its existing/configured
    // weight.
    //set the weight for current row
    $row['#weight'] =  $weight;

    //Second row for link fields
    $row['link'] = [
      // Title
      'title' => ['#type' => 'textfield',
        '#title' => 'Title',
        '#description' => 'Enter a title for website url.',
        '#default_value' =>  isset($title) ? $title : '',
        '#required' => $first_row = TRUE ? $index == 0 : FALSE
      ],
      // Url
      'url' => ['#type' => 'linkit',
        '#title' => 'Url',
        '#description' => 'Start typing the title of a piece of content to select it. You can also enter an internal path such as /node/add or an external URL such as http://example.com. Enter &lt;front&gt; to link to the homepage.',
        '#autocomplete_route_name' => 'linkit.autocomplete',
        '#autocomplete_route_parameters' => [
          'linkit_profile_id' => 'all_users'
        ],
        '#default_value' => isset($url) ? $url : '',
        '#required' => $first_row = TRUE ? $index == 0 : FALSE
      ]
    ];

    //Third row for weight drop down option
    $row['weight'] = [
      '#type' => 'weight',
      '#title' => 'Weight for '.$title,
      '#title_display' => 'invisible',
      '#default_value' => $weight,
      // Classify the weight element for #tabledrag set in Table head
      '#attributes' => ['class' => ['table-sort-weight']
      ],
    ];

    return $row;

  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addOne(array &$form, FormStateInterface $form_state) {

    $name_field = $form_state->get('num_rows');

    $add_button = $name_field + 1;
    $form_state->set('num_rows', $add_button);

    $last_weight = $form_state->get('last_weight');
    $last_weight = $last_weight + 1;
    $form_state->set('last_weight', $last_weight);

    $form_state->setRebuild();
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @return mixed
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    // The form passed here is the entire form, not the subform that is
    // passed to non-AJAX callback.
    return $form['settings']['dragtablerow_tableset'];

  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {

    $name_field = $form_state->get('num_rows');

    if ($name_field > 1) {
      $remove_button = $name_field - 1;
      $form_state->set('num_rows', $remove_button);
    }

    $last_weight = $form_state->get('last_weight');
    $last_weight = $last_weight - 1;
    $form_state->set('last_weight', $last_weight);

    $form_state->setRebuild();

  }

  // Callback function to better sort weights on display
  function sortTableRows($a, $b){

    if (isset($a['weight']) && isset($b['weight'])) {
      return $a['weight'] < $b['weight'] ? -1 : 1;
    }

    return 0;

  }

}
