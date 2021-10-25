<?php

namespace Drupal\fits\Plugin\AdvancedQueue\JobType;

use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use GuzzleHttp\Client;
use Drupal\advancedqueue\JobResult;

/**
 * @AdvancedQueueJobType(
 *   id = "fits_job",
 *   label = @Translation("Fits"),
 * )
 */
class FitsJob extends JobTypeBase {

  /**
   * Implements process().
   */
  public function process(Job $job) {
    try {
      $payload = $job->getPayload();

      // Set retry config.
      $this->pluginDefinition['max_retries'] = $payload['max_tries'];
      $this->pluginDefinition['retry_delay'] = $payload['retry_delay'];

      /** @var \Drupal\file\FileInterface $file */
      $file = File::load($payload['fid']);
      $result = $this->extractFits($file);

      if ($result['result'] === TRUE) {
        return JobResult::success($this->t($result['outcome']));
      }
      else {
        return JobResult::failure($this->t($result['outcome']));
      }

    }
    catch (\Exception $e) {
      return JobResult::failure($e->getMessage());
    }
  }

  /**
   * Extract Fits.
   */
  public function extractFits($file = NULL) {
    /** @var \Drupal\file\FileInterface $file */
    $config = \Drupal::config('fits.fitsconfig');
    $report = "";
    $sucess = TRUE;
    if (!isset($file)) {
      return;
    }
    // Extract Fits from xml.
    $fits_result = $this->getFits($file);

    if ($fits_result['code'] === 500) {
      $report .= 'Get Fits XML: ' . $fits_result['message'] . "\n";
      return ['result' => FALSE, "outcome" => $report];
    }

    $report .= "Get Fits XML: " . $fits_result['message'] . "\n";
    $fits_xml = $fits_result['output'];

    $fits = simplexml_load_string($fits_xml);
    $fit_json = json_encode($fits);

    // Store the whole fits to json field.
    $file->field_fits->setValue($fit_json);

    $fits = json_decode($fit_json);
    foreach ($file->getFields() as $field) {
      if ($field->getFieldDefinition()->getType() === "string" && strpos($field->getFieldDefinition()->getName(), "_fits_") !== FALSE) {
        $extractedFitsValue = $this->jmesPathSearch($this->getJmespath($field->getFieldDefinition()->getDescription()), $fits);
        if (empty($extractedFitsValue)) {
          $report .= "Extract Fits: JMESPath for the field - " . $field->getName() . " seems to be invalid\n";
          if (in_array($field->getFieldDefinition()->getName(), $config->get("fits-default-fields"))) {
            $sucess = FALSE;
          }
        }
        else {
          $report .= "Extract Fits: Field  " . $field->getName() . ".value = $extractedFitsValue.\n";
        }
        $field->setValue($extractedFitsValue, $fits);
      }
      elseif ($field->getFieldDefinition()->getName() === "field_fits_pronom_puid") {
        $extractedFitsValue = $this->jmesPathSearch($this->getJmespath($field->getFieldDefinition()->getDescription()), $fits);
        if (empty($extractedFitsValue)) {
          $report .= "Extract Fits: JMESPath for the field - " . $field->getName() . " seems to be invalid\n";
          if (in_array($field->getFieldDefinition()->getName(), $config->get("fits-default-fields"))) {
            $sucess = FALSE;
          }
        }
        else {
          $report .= "Extract Fits: Field  " . $field->getName() . ".value = $extractedFitsValue.\n";
        }
        // Search PRONOM fitst.
        if (!empty($extractedFitsValue)) {
          $tid = $this->searchPronom($extractedFitsValue);
          if ($tid === -1) {
            $tid = Term::create([
              'name' => $extractedFitsValue,
              'vid' => "pronom",
            ])->save();
            $tid = $this->searchPronom($extractedFitsValue);
          }
          $field->setValue($tid);
        }

      }
    }

    // Extract selective fields and save to other fields.
    $file->save();
    return ['result' => $sucess, "outcome" => $report];
  }

  /**
   * Analyze field's description text and get Jmespath.
   */
  public function getJmespath($desc) {
    preg_match_all("/\[\{(.*?)\}\]/", $desc, $matches);
    $jmespath = $matches[1];
    if (is_array($jmespath) && count($jmespath) > 1) {
      return $jmespath;
    }
    elseif (is_array($jmespath) && count($jmespath) == 1) {
      return $jmespath[0];
    }
    else {
      return "";
    }

  }

  /**
   * From Jmespath(s), Get value of field from Fits json.
   */
  public function jmesPathSearch($path, $fits) {
    if (is_array($path)) {
      $value = "";
      foreach ($path as $p) {
        $value = \JmesPath\search($p, $fits);
        if (!empty($value)) {
          break;
        }
      }
      return $value;
    }
    return \JmesPath\search($path, $fits);
  }

  /**
   * Search PRONOM term.
   */
  public function searchPronom($pronom) {
    $tid = -1;
    if (isset($pronom)) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("pronom");
      foreach ($terms as $term) {
        if ($pronom === $term->name) {
          $tid = $term->tid;
          break;
        }
      }
    }
    return $tid;
  }

  /**
   * Rest call to Fits.
   */
  public function getFits(File $file) {
    $config = \Drupal::config('fits.fitsconfig');
    if ($config->get("fits-method") === "remote") {
      try {
        $options = [
          'base_uri' => $config->get("fits-server-url"),
        ];
        $client = new Client($options);
        $response = $client->post($config->get("fits-server-url"), [
          'multipart' => [
            [
              'name' => 'datafile',
              'filename' => $file->label(),
              'contents' => file_get_contents($file->getFileUri()),
            ],
          ],
        ]);
        if (isset($response)) {
          return [
            "code" => 200,
            "message" => "Get Fits Technical Metadata successfully",
            'output' => $response->getBody()->getContents(),
          ];
        }
        else {
          return [
            "code" => 417,
            "message" => "Failed Get Fits Technical Metadata.",
            'output' => $response->getBody()->getContents(),
          ];
        }

      }
      catch (\Exception $e) {
        return ["code" => 500, 'message' => $e->getMessage()];
      }
    }
    else {
      try {
        $fits_path = $config->get("fits-path");
        if (strpos($file->getFilename(), ' ') !== FALSE) {
          $file_path = str_replace($file->getFilename(), escapeshellarg($file->getFilename()), $file->getFileUri());
          $file_path = \Drupal::service('file_system')->realpath($file_path);
        }
        else {
          $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
        }
        $cmd = $fits_path . " -i " . $file_path;
        $xml = `$cmd`;
        // drupal_log($cmd);
        if (isset($xml)) {
          return [
            "code" => 200,
            "message" => "Get Fits Technical Metadata successfully",
            'output' => $xml,
          ];
        }
        else {
          return [
            "code" => 417,
            "message" => "Failed to get Fits Technical Metadata.",
            'output' => $xml,
          ];
        }
      }
      catch (\Exception $e) {
        return ["code" => 500, 'message' => $e->getMessage()];
      }
    }
  }

}
