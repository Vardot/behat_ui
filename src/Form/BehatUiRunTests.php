<?php

/**
 * @file
 * Contains \Drupal\behat_ui\Form\BehatUiRunTests.
 */

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BehatUiRunTests extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_run_tests';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    
    $config = $this->config('behat_ui.settings');

    $pid = $config->get('behat_ui_pidfile');
    $outfile = $config->get('behat_ui_outfile');
    $reportdir = $config->get('behat_ui_html_report_dir');
    $enableHtml = $config->get('behat_ui_enable_html');

    $form['#attached']['library'][] = 'behat_ui/behat_ui';
    $form['behat_ui_enable_html'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTML output'),
      '#description' =>$this->t('Switch behat output formatter from progress to HTML.'),
      '#default_value' => $enableHtml,
    );

    $label = t('Not running');
    $class = '';
    if ($pid && behat_ui_process_running($pid)) {
      $label = t('Running <small><a href="#" id="behat-ui-kill">(kill)</a></small>');
      $class = 'running';
    }
    elseif ($pid && !behat_ui_process_running($pid)) {
      $tempstore->delete('behat_ui_pid');
    }
    $form['behat_ui_status'] = [
      '#type' => 'markup',
      '#markup' => '<p id="behat-ui-status" class="' . $class . '">' . t('Status:') . ' <span>' . $label . '</span></p>',
    ];

    if ($enableHtml && $reportdir) {
      $output = file_get_contents($reportdir . '/index.html');
    }
    elseif ($outfile && file_exists($outfile)) {
      $output = nl2br(htmlentities(file_get_contents($outfile)));
    }
    $form['behat_ui_output'] = [
      '#title' => t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="behat-ui-output">' . $output . '</div>',
    ];

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run behat tests'),
    ];
    return $form;
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
    global $base_url;
    $config = $this->config('behat_ui.settings');
    $pid = $config->get('behat_ui_pidfile');

    $message = \Drupal::messenger();

    if (!$pid) {
      $account = \Drupal::currentUser();
      $config = \Drupal::config('behat_ui.settings');

      $behat_bin = $config->get('behat_bin_path');
      $behat_config_path = "-c " . $config->get('behat_config_path');

      // TODO: Move to BehatUiSettings form.
      $filePath = $base_url . '/behat_ui';
      if (!\Drupal::service('file_system')->prepareDirectory($filePath, FileSystemInterface::CREATE_DIRECTORY)) {
        $message->addError(t('Output directory does not exists or is not writable.'));
      }
      $fileUserTime = 'user-' . $account->id() . '-' . date('Y-m-d_h-m-s');

      $outfile = $filePath . '/behat-ui-' . $fileUserTime . '.log';
      $report_dir = $filePath;
      $enableHtml = $form_state->getValue('behat_ui_enable_html');

      $command = "$behat_bin $behat_config_path -f pretty --out std > $outfile&";
      if ($enableHtml) {
        $command = "$behat_bin $behat_config_path --format pretty --out std --format html --out > $outfile &";
      }
      $process = new Process($command);
      $process->enableOutput();
      $process->start();
      $message->addMessage($process->getExitCodeText());
    }
    else {
      $message->addMessage(t('Tests are already running.'));
    }
  }

}
