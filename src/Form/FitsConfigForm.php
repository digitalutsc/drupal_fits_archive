<?php

namespace Drupal\fits\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FitsConfigForm.
 */
class FitsConfigForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return [
      'fits.fitsconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'fits_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form =  parent::buildForm($form, $form_state);

    $config = $this->config('fits.fitsconfig');

    $form['container'] = array(
      '#type' => 'container',
    );

    $form['container']['fits-services-config'] = array(
      '#type' => 'details',
      '#title' => 'General Settings',
      '#open' => true,

    );

    $form['container']['fits-services-config']['method'] = array(
        '#type' => 'select',
        '#title' => 'Select Fits method:',
        '#options' => array(
          0 => '-- Select --',
          'remote' => 'FITS Web Service',
          'local' => 'FITS from the command-line'
        ),
      '#required' => true,
      '#ajax' => [
        'callback' => '::textfieldsCallback',
        'wrapper' => 'textfields-container',
        'effect' => 'fade',
      ],
      '#default_value' => ($config->get("fits-method") !== null) ? $config->get("fits-method") : "",
    );


    $form['container']['fits-services-config']['textfields_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'textfields-container'],
    ];

    if ($form_state->getValues()['method'] === "remote" || (empty($form_state->getValues()['method']) && $config->get("fits-method") === "remote")) {

      $form['container']['fits-services-config']['textfields_container']['server-url'] = array(
        '#type' => 'textfield',
        '#name' => 'server-url',
        '#title' => $this
          ->t('Fits XML Services URL:'),
        '#default_value' => ($config->get("fits-server-url") !== null) ? $config->get("fits-server-url") : "",
        '#description' => $this->t('For example: <code>http://localhost:8080/fits/</code>')
      );

      $form['container']['fits-services-config']['textfields_container']['endpoint'] = array(
        '#type' => 'textfield',
        '#name' => 'endpoint',
        '#title' => $this
          ->t('Fits XML Services Endpoint:'),
        '#default_value' => ($config->get("fits-server-endpoint") !== null) ? $config->get("fits-server-endpoint") : "",
        '#description' => $this->t('For example: <code>examine</code>')
      );
    }
    else if ($form_state->getValues()['method'] === "local" || (empty($form_state->getValues()['method']) && $config->get("fits-method") === "local")) {
      $form['container']['fits-services-config']['textfields_container']['fits-path'] = array(
        '#type' => 'textfield',
        '#title' => $this
          ->t('System path to FITS processor:'),
        '#default_value' => ($config->get("fits-path") !== null) ? $config->get("fits-path") : "",
        '#description' => $this->t('Example: <code>/usr/bin/fits.sh</code>')
      );
    }

    $form['container']['fits-services-config']['op-config'] = [
      '#type' => 'container',
    ];

    $queues = array('0' => "-- Select --");
    $queues = array_merge($queues, \Drupal::entityQuery('advancedqueue_queue')->execute());
    $form['container']['fits-services-config']['op-config']['advancedqueue-id'] = array(
      '#type' => 'select',
      '#name' => 'advancedqueue-id',
      '#title' => $this->t('Advanced queues:'),
      '#required' => TRUE,
      '#default_value' => ($config->get("fits-advancedqueue_id") !== null) ? $config->get("fits-advancedqueue_id") : 0,
      '#options' => $queues,
    );
    $form['container']['fits-services-config']['op-config']['link-to-add-queue'] = [
      '#markup' => $this->t('To create a new queue, <a href="/admin/config/system/queues/add" target="_blank">Click here</a>'),
    ];

    $form['container']['fits-services-config']['extact-fits-while-ingesting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable extracting Fits during Ingest'),
      '#default_value' => ($config->get("fits-extract-ingesting") !== null) ? $config->get("fits-extract-ingesting") : 0,
    ];

    return $form;
  }

  public function textfieldsCallback($form, FormStateInterface $form_state) {
    return $form['container']['fits-services-config']['textfields_container'];
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $configFactory = $this->configFactory->getEditable('fits.fitsconfig');
    $configFactory->set("fits-method", $form_state->getValues()['method']);

    if ($form_state->getValues()['method'] === "local") {
      $configFactory->set("fits-path", $form_state->getValues()['fits-path']);
      $configFactory->set("fits-server-url", "");
      $configFactory->set("fits-server-endpoint", "");
    }
    else {
      $configFactory->set("fits-server-url", $form_state->getValues()['server-url']);
      $configFactory->set("fits-server-endpoint", $form_state->getValues()['endpoint']);
      $configFactory->set("fits-path", "");
    }

    $configFactory->set("fits-advancedqueue_id",  $form_state->getValues()['advancedqueue-id']);
    $configFactory->set("fits-extract-ingesting", $form_state->getValues()['extact-fits-while-ingesting']);

    // save the config
    $configFactory->save();

    parent::submitForm($form, $form_state);
  }

}
