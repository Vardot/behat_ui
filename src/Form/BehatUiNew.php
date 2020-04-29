<?php

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Render\Markup;

/**
 *
 */
class BehatUiNew extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_new_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'behat_ui/style';
    $form['#attached']['library'][] = 'behat_ui/new-test-scripts';

    $origin_url = \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getBaseUrl();

    $form['behat_ui_new_scenario'] = [
      '#type' => 'markup',
      '#markup' => '<div class="layout-row clearfix">'
      . '  <div class="layout-column layout-column--half">'
      . '    <div id="behat-ui-new-scenario" class="panel">'
      . '      <h3 class="panel__title">' . $this->t('New scenario') . '</h3>'
      . '      <div class="panel__content">',
    ];

    $form['behat_ui_new_scenario']['behat_ui_steps_link'] = [
      '#type' => 'markup',
      '#markup' => '<a class="button use-ajax"
            data-dialog-options="{&quot;width&quot;:400}" 
            data-dialog-renderer="off_canvas" 
            data-dialog-type="dialog"
            href="' . $origin_url . '/admin/config/development/behat_ui/behat_dl" >' . $this->t('Check available steps') . '</a>',
    ];

    $form['behat_ui_new_scenario']['behat_ui_steps_link_with_info'] = [
      '#type' => 'markup',
      '#markup' => '<a class="button use-ajax"
            data-dialog-options="{&quot;width&quot;:400}" 
            data-dialog-renderer="off_canvas" 
            data-dialog-type="dialog"
            href="' . $origin_url . '/admin/config/development/behat_ui/behat_di" >' . $this->t('Full steps with info') . '</a>',
    ];

    $form['behat_ui_new_scenario']['behat_ui_title'] = [
      '#type' => 'textfield',
      '#title' => 'Title of this scenario',
      '#required' => TRUE,
    ];

    $form['behat_ui_new_scenario']['behat_ui_steps'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Steps'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
      '#prefix' => '<div id="behat-ui-new-steps">',
      '#suffix' => '</div>',
    ];
    $storage = $form_state->getValues();
    $stepCount = isset($storage['behat_ui_steps']) ? (count($storage['behat_ui_steps']) + 1) : 1;
    if (isset($storage)) {
      for ($i = 0; $i < $stepCount; $i++) {
        $form['behat_ui_new_scenario']['behat_ui_steps'][$i] = [
          '#type' => 'fieldset',
          '#collapsible' => FALSE,
          '#collapsed' => FALSE,
          '#tree' => TRUE,
        ];

        $form['behat_ui_new_scenario']['behat_ui_steps'][$i]['type'] = [
          '#type' => 'select',
          '#options' => [
            '' => '',
            'Given' => 'Given',
            'When' => 'When',
            'Then' => 'Then',
            'And' => 'And',
            'But' => 'But',
          ],
          '#default_value' => '',
        ];

        $form['behat_ui_new_scenario']['behat_ui_steps'][$i]['step'] = [
          '#type' => 'textfield',
          '#autocomplete_path' => 'behat-ui/autocomplete',
        ];
      }
    }

    $form['behat_ui_new_scenario']['behat_ui_add_step'] = [
      '#type' => 'button',
      '#value' => $this->t('Add'),
      '#href' => '',
      '#ajax' => [
        'callback' => '::ajaxAddStep',
        'wrapper' => 'behat-ui-new-steps',
      ],
    ];

    $form['behat_ui_new_scenario']['behat_ui_javascript'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Needs a real browser'),
      '#default_value' => 0,
      '#description' => $this->t('Check this if this test needs a real browser, which supports JavaScript, in order to perform actions that happen without reloading the page.'),
    ];

    $form['behat_ui_new_scenario']['behat_ui_feature'] = [
      '#type' => 'radios',
      '#title' => $this->t('Feature'),
      '#options' => $this->getExistingFeatures(),
      '#suffix' => '</div></div></div>',
    ];

    $form['behat_ui_scenario_output'] = [
      '#markup' => '<div class="layout-column layout-column--half">'
      . '    <div class="panel">'
      . '      <h3 class="panel__title">' . $this->t('Scenario output') . '</h3>'
      . '      <div id="behat-ui-scenario-output" class="panel__content">',
    ];

    $form['behat_ui_run'] = [
      '#type' => 'button',
      '#value' => $this->t('Run >>'),
      '#ajax' => [
        'callback' => '::runSingleTest',
    // Or TRUE to prevent re-focusing on the triggering element.
        'disable-refocus' => FALSE,
        'event' => 'click',
        'wrapper' => 'behat-ui-output',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Running the testing feature...'),
        ],
      ],
    ];

    $form['behat_ui_create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download updated feature'),
      '#attribute' => [
        'id' => 'behat-ui-create',
        'classes' => 'button right',
      ],
    ];

    $form['behat_ui_output'] = [
      '#title' => $this->t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="behat-ui-output"><div id="behat-ui-output-inner"></div></div></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggerdElement = $form_state->getTriggeringElement();
    $htmlIdofTriggeredElement = $triggerdElement['#id'];

    $config = \Drupal::config('behat_ui.settings');

    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    if ($htmlIdofTriggeredElement == 'edit-behat-ui-create') {
      $formValues = $form_state->getValues();

      $file = $behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path . '/' . $formValues['behat_ui_feature'] . '.feature';
      $feature = file_get_contents($file);
      $scenario = $this->generateScenario($formValues);
      $content = $feature . "\n" . $scenario;
      $handle = fopen($file, 'w+');
      fwrite($handle, $content);
      fclose($handle);

      $file_name = $formValues['behat_ui_feature'] . '.feature';
      $file_size = filesize($file);
      $response = new Response();
      $response->headers->set('Content-Type', 'text/x-behat');
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $file_name . '"');
      $response->headers->set('Pragma', 'no-cache');
      $response->headers->set('Content-Transfer-Encoding', 'binary');
      $response->headers->set('Content-Length', filesize($file));
      $form_state->disableRedirect();
      readfile($file);
      return $response->send();

    }
  }

  /**
   * Get existing features.
   */
  public function getExistingFeatures() {

    $config = \Drupal::config('behat_ui.settings');

    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    $features = [];
    if ($handle = opendir($behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path)) {
      while (FALSE !== ($file = readdir($handle))) {
        if (preg_match('/\.feature$/', $file)) {
          $feature = preg_replace('/\.feature$/', '', $file);
          $name = ucfirst(str_replace('_', ' ', $feature));
          $features[$feature] = $name;
        }
      }
    }
    return $features;
  }

  /**
   * Run a single test.
   */
  public function runSingleTest($form, &$form_state) {
    $config = \Drupal::config('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');

    $behat_config = "-c " . $behat_ui_behat_config_path;
    $formValues = $form_state->getValues();
    // Write to temporary file.
    $file_user_time = 'user-' . date('Y-m-d_h-m-s');
    $file = $behat_config . '/features/tmp/' . $file_user_time . '.feature';
    $feature = $formValues['behat_ui_feature'];
    $test = "Feature: $feature\n  In order to test \"$feature\"\n\n";
    $test .= $this->generateScenario($formValues);
    $handle = fopen($file, 'w+');
    fwrite($handle, $test);
    fclose($handle);

    // Run file.
    $output = shell_exec("$behat_ui_behat_bin_path $behat_config $file --format html");

    $report_html_file_name_and_path = $behat_ui_html_report_dir . '/' . $behat_ui_html_report_file;

    $report_html_handle = fopen($report_html_file_name_and_path, 'r');
    $report_html = fread($report_html_handle, filesize($report_html_file_name_and_path));
    fclose($report_html_handle);

    unlink($file);

    $form['behat_ui_output'] = [
      '#title' => t('Tests output'),
      '#type' => 'markup',
      '#markup' => Markup::create('<div id="behat-ui-output"' . file_get_contents($report_html_file_name_and_path) . '</div>'),
    ];
    return $form['behat_ui_output'];
  }

  /**
   * Given a form_state, return a Behat scenario.
   */
  public function generateScenario($form_state) {
    $scenario = "@api";
    if ($form_state['behat_ui_javascript']) {
      $scenario .= " @javascript";
    }
    $title = $form_state['behat_ui_title'];
    $scenario .= "\nScenario: $title\n";

    $steps_count = count($form_state['values']['behat_ui_steps']);

    for ($i = 0; $i < $steps_count; $i++) {
      $type = $form_state['behat_ui_steps'][$i]['type'];
      $step = $form_state['behat_ui_steps'][$i]['step'];

      if (!empty($type) && !empty($step)) {
        // Blocks.
        $step = preg_replace('/\n\|/', "\n  |", preg_replace('/([:\|])\|/', "$1\n|", $step));
        $scenario .= "  $type $step\n";
      }
    }

    return $scenario;
  }

  /**
   * Behat Ui add step AJAX.
   */
  public function ajaxAddStep($form, $form_state) {
    return $form['behat_ui_new_scenario']['behat_ui_steps'];
  }

}
