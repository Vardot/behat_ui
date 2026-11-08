/**
 * @file
 * Behaviors Behat UI run tests scripts.
 */

(function ($, _, Drupal, drupalSettings) {
  Drupal.behaviors.BehatUiRunTests = {
    attach(context, settings) {
      let checkStatus;

      const killProcess = function () {
        $('#behat-ui-kill', context).click(function () {
          $.ajax({
            url: `${drupalSettings.path.baseUrl}behat-ui/kill?${parseInt(
              Math.random() * 1000000000,
              10,
            )}`,
            dataType: 'json',
            success(data) {
              if (data.response) {
                console.log(Drupal.t('Process killed'));
                checkStatus();
              } else {
                console.log(Drupal.t('Could not kill process'));
              }
            },
            error(xhr, textStatus, error) {
              console.log(
                Drupal.t('An error happened on trying to kill the process.'),
              );
            },
          });
          return false;
        });
      };

      checkStatus = function () {
        const behatUiStatus = $('#behat-ui-status', context);
        const behatUiOutput = $('#behat-ui-output', context);

        $.ajax({
          url: `${drupalSettings.path.baseUrl}behat-ui/status?${parseInt(
            Math.random() * 1000000000,
            10,
          )}`,
          dataType: 'json',
          success(data) {
            behatUiStatus.removeClass('running');

            if (data.running) {
              behatUiStatus.addClass('running');
              behatUiStatus
                .find('span')
                .html(
                  `${Drupal.t('Process:') + data.pid} ${Drupal.t(
                    'Running <small><a href="#" id="behat-ui-kill">(kill)</a></small>',
                  )}`,
                );
              killProcess();
              setTimeout(checkStatus, 10000);
            } else {
              behatUiStatus.find('span').html(Drupal.t('Not running'));
            }

            behatUiOutput.html(data.output);
          },
          error(xhr, textStatus, error) {
            console.log(
              Drupal.t('An error happened on checking tests status.'),
            );
            setTimeout(checkStatus, 10000);
          },
        });
      };

      checkStatus();
      killProcess();
    },
  };
})(window.jQuery, window._, window.Drupal, window.drupalSettings);
