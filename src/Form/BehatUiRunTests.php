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

    // Get variables belongs to behat process from user temp storage.
    $tempstore = \Drupal::service('user.private_tempstore')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');
    $outfile = $tempstore->get('behat_ui_output_log');
    $reportdir = $tempstore->get('behat_ui_report_dir');
    $enableHtml = $tempstore->get('behat_ui_enable_html');

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
    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub

    // TODO: Add validation for behat_ui_enable_html checkbox.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tempstore = \Drupal::service('user.private_tempstore')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');
    $message = \Drupal::messenger();

    // Variable $url was never used before.
    //    // HTTP authentication.
    //    \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_http_user', $form_state->getValue('behat_ui_http_user'))->save();
    //    if (!empty($form_state->getValue('behat_ui_http_password'))) {
    //      \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_http_password', $form_state->getValue('behat_ui_http_password'))->save();
    //    }
    //    \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_http_auth_headless_only', $form_state->getValue('behat_ui_http_auth_headless_only'))->save();
    //    $username = \Drupal::config('behat_ui.settings')->get('behat_ui_http_user');
    //    $password = \Drupal::config('behat_ui.settings')->get('behat_ui_http_password');
    //
    //    $url = $base_root;
    //    if (!empty($username) && !empty($password) && !\Drupal::config('behat_ui.settings')->get('behat_ui_http_auth_headless_only')) {
    //      $url = preg_replace('/^(https?:\/\/)/', "$1$username:$password@", $url);
    //      $url = preg_replace('/([^\/])$/', "$1/", $url);
    //    }

    if (!$pid) {
      $account = \Drupal::currentUser();
      $config = \Drupal::config('behat_ui.settings');

      $behat_bin = $config->get('behat_bin_path');
      $behat_config_path = $config->get('behat_config_path');

      // TODO: Move to BehatUiSettings form.
      $filePath = \Drupal::service('file_system')->realpath(file_default_scheme() . "://") . '/behat_ui';
      if (!\Drupal::service('file_system')->prepareDirectory($filePath, FileSystemInterface::CREATE_DIRECTORY)) {
        $message->addError(t('Output directory does not exists or is not writable.'));
      }
      $fileUserTime = 'user-' . $account->id() . '-' . date('Y-m-d_h-m-s');

      $outfile = $filePath . '/behat-ui-' . $fileUserTime . '.log';
      $report_dir = $filePath . '/reports/report-' . $fileUserTime;
      $enableHtml = $form_state->getValue('behat_ui_enable_html');

      $command = "$behat_bin -c $behat_config_path -f pretty --out std > $outfile&";
      if ($enableHtml) {
        $command = "$behat_bin -c $behat_config_path --format pretty --out std --format html --out $report_dir > $outfile &";
      }
      $process = new Process($command);
      $process->enableOutput();
      $process->start();
      $message->addMessage($process->getExitCodeText());

      // TODO: Check why we have to use +1 to get correct PID.
      $tempstore->set('behat_ui_pid', $process->getPid() + 1);
      $tempstore->set('behat_ui_output_log', $outfile);
      $tempstore->set('behat_ui_report_dir', $report_dir);
      $tempstore->set('behat_ui_enable_html', $enableHtml);

    }
    else {
      $message->addMessage(t('Tests are already running.'));
    }
  }

}
