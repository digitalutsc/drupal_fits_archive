<?php

namespace Drupal\fits\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\Client;

/**
 * Provides a 'FitsAction' action.
 *
 * @Action(
 *  id = "fits_action",
 *  label = @Translation("FITS - Generate and Extract Technical metadata for File"),
 *  type = "file",
 *  category = @Translation("Custom")
 * )
 */
class FitsAction extends ActionBase {


  public function access($file, AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    /** @var \Drupal\file\FileInterface $file */
    $access = $file->access('update', $account, TRUE)
      ->andIf($file->access('edit', $account, TRUE));
    return $return_as_object ? $access : $access->isAllowed();
  }

  public function execute($file = NULL)
  {
    /** @var \Drupal\file\FileInterface $file */
    if (!isset($file)) {
      return;
    }
    // extract Fits from xml
    $fits_xml = $this->getFits($file);
    $fits = simplexml_load_string($fits_xml);
    $fit_json = json_encode($fits);

    // store the whole fits to json field, use either one of theme. Choose after determine which one is better.
    $file->field_fits->setValue($fit_json);

    $fits = json_decode($fit_json);

    foreach ($file->getFields() as $field) {
      if ($field->getFieldDefinition()->getType() === "string" && strpos($field->getFieldDefinition()->getName(), "_fits_") !== false)  {
        $field->setValue(jmesPathSearch(getJmespath($field->getFieldDefinition()->getDescription()), $fits));
      }
    }

    // extract selective fields and save to other fields
    $file->save();
  }


  /**
   * Rest call to Fits
   * @param \Drupal\file\Entity\File $file
   * @return string
   */
  function getFits(\Drupal\file\Entity\File $file)
  {
    $config = \Drupal::config('fits.fitsconfig');
    try {
      $options = [
        'base_uri' => $config->get("fits-server-url")
      ];
      $client = new Client($options);
      $response = $client->post($config->get("fits-server-endpoint"), [
        'multipart' => [
          [
            'name' => 'datafile',
            'filename' => $file->label(),
            'contents' => file_get_contents($file->getFileUri()),
          ],
        ]
      ]);

      return $response->getBody()->getContents();
    } catch (Exception $e) {
      return null;
    }

  }

}
