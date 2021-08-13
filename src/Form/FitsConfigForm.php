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
        $form = parent::buildForm($form, $form_state);

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

        if ((array_key_exists("method", $form_state->getValues()) && $form_state->getValues()['method'] === "remote")
            || (empty($form_state->getValues()['method']) && $config->get("fits-method") === "remote")) {

            $form['container']['fits-services-config']['textfields_container']['server-url'] = array(
                '#type' => 'textfield',
                '#name' => 'server-url',
                '#title' => $this
                    ->t('Fits XML Services URL:'),
                '#default_value' => ($config->get("fits-server-url") !== null) ? $config->get("fits-server-url") : "",
                '#description' => $this->t('For example: <code>http://localhost:8080/fits/examine</code>')
            );
        } else if ($form_state->getValues()['method'] === "local" || (empty($form_state->getValues()['method']) && $config->get("fits-method") === "local")) {
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
            '#title' => $this->t('Extracting Fits while a file is being uploaded'),
            '#default_value' => ($config->get("fits-extract-ingesting") !== null) ? $config->get("fits-extract-ingesting") : 0,
        ];


        // select default fits fields
        $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
        $node_field_map = $field_map['file'];
        $fields = array_keys($node_field_map);
        $fits_fields = [];
        foreach ($fields as $f) {
            if (strpos($f, "_fits") !== false || strpos($f, "_fits_") !== false) {
                $fits_fields[$f] = $f;
            }
        }
        $form['container']['fits-fields-config'] = array(
            '#type' => 'details',
            '#title' => 'Optional Configuration for Extracting Fits',
            '#open' => false,

        );

        $form['container']['fits-fields-config']['default-fits-fields'] = array(
            '#type' => 'checkboxes',
            '#title' => $this
                ->t('Enable validation for Fits fields during extraction:'),
            "#options" => $fits_fields,
            '#default_value' => ($config->get("fits-default-fields") !== null && is_array($config->get("fits-default-fields")) && count($config->get("fits-default-fields")) >0 ) ? $config->get("fits-default-fields") : ['field_fits', 'field_fits_checksum', 'field_fits_file_format'],
            '#description' => 'Select Fits fields which mainly determine success or failure of extracting Fits jobs'
        );

        return $form;
    }

    public function textfieldsCallback($form, FormStateInterface $form_state)
    {
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
        } else {
            $configFactory->set("fits-server-url", $form_state->getValues()['server-url']);
            $configFactory->set("fits-path", "");
        }

        $configFactory->set("fits-advancedqueue_id", $form_state->getValues()['advancedqueue-id']);
        $configFactory->set("fits-extract-ingesting", $form_state->getValues()['extact-fits-while-ingesting']);
        $configFactory->set("fits-default-fields", array_keys(array_filter($form_state->getValues()['default-fits-fields'])));

        // save the config
        $configFactory->save();

        parent::submitForm($form, $form_state);
    }

    public function getFileTypes()
    {
        $contentTypes = \Drupal::service('entity_type.manager')->getStorage('file_type')->loadMultiple();
        $types = [];
        foreach ($contentTypes as $contentType) {
            $types[$contentType->id()] = $contentType->label();
        }
        return $types;
    }

}
