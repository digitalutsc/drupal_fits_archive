<?php

namespace Drupal\fits\Plugin\AdvancedQueue\JobType;

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
class FitsJob extends JobTypeBase
{

  public function process(Job $job)
  {
    // TODO: Implement process() method.
    try {
      $payload = $job->getPayload();
      /** @var \Drupal\file\FileInterface $file */
      $file = \Drupal\file\Entity\File::load($payload['fid']);
      $result = $this->extractFits($file);

      if ($result['result'] === true) {
        return JobResult::success(t($result['outcome']));
      } else {
        return JobResult::failure(t($result['outcome']));
      }

    } catch (\Exception $e) {
      return JobResult::failure($e->getMessage());
    }
  }


  /**
   * Extract Fits
   * @param null $file
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function extractFits($file = NULL)
  {
    /** @var \Drupal\file\FileInterface $file */
    $report = "";
    $sucess = true;
    if (!isset($file)) {
      return;
    }
    // extract Fits from xml
    $fits_result = $this->getFits($file);

    if ($fits_result['code'] === 500) {
      $report .= '<p>Get Fits XML: ' . $fits_result['message'] . "</p>";
      return ['result' => false, "outcome" => $report];
    }

    $report .= "<p>Get Fits XML: " . $fits_result['message'] . "</p>";
    $fits_xml = $fits_result['output'];

    $fits = simplexml_load_string($fits_xml);
    $fit_json = json_encode($fits);

    // store the whole fits to json field, use either one of theme. Choose after determine which one is better.
    $file->field_fits->setValue($fit_json);

    $fits = json_decode($fit_json);

    foreach ($file->getFields() as $field) {
      if ($field->getFieldDefinition()->getType() === "string" && strpos($field->getFieldDefinition()->getName(), "_fits_") !== false) {
        $extractedFitsValue = jmesPathSearch(getJmespath($field->getFieldDefinition()->getDescription()), $fits);
        if (empty($extractedFitsValue)) {
          $report .= "<p>Extract Fits: JMESPath for the field - <code>" . $field->getName() . "</code> seems to be invalid</p>";
          $sucess = false;
        } else {
          $report .= "<p>Extract Fits: Field  <code>" . $field->getName() . ".value = $extractedFitsValue</code>.</p>";
        }
        $field->setValue($extractedFitsValue, $fits);
      }
      else if ($field->getFieldDefinition()->getName() === "field_fits_pronom_puid") {
        $extractedFitsValue = jmesPathSearch(getJmespath($field->getFieldDefinition()->getDescription()), $fits);
        if (empty($extractedFitsValue)) {
          $report .= "<p>Extract Fits: JMESPath for the field - <code>" . $field->getName() . "</code> seems to be invalid</p>";
          $sucess = false;
        } else {
          $report .= "<p>Extract Fits: Field  <code>" . $field->getName() . ".value = $extractedFitsValue</code>.</p>";
        }
        // search PRONOM fitst

        $tid = $this->searchPRONOM($extractedFitsValue);
        if ($tid === -1) {
          $tid = \Drupal\taxonomy\Entity\Term::create([
            'name' => $extractedFitsValue,
            'vid' => "pronom",
          ])->save();
          $tid = $this->searchPRONOM($extractedFitsValue);
        }
        $field->setValue($tid);
      }
    }

    // extract selective fields and save to other fields
    $file->save();
    return ['result' => $sucess, "outcome" => $report];
  }

  /**
   * Search PRONOM  term
   * @param String $pronom
   * @return int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  function searchPRONOM(String $pronom) {
    $tid = -1;
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("pronom");
    foreach ($terms as $term) {
      if ($pronom === $term->name) {
        $tid = $term->tid;
        break;
      }
    }
    return $tid;
  }

  /**
   * Rest call to Fits
   * @param \Drupal\file\Entity\File $file
   * @return string
   */
  function getFits(\Drupal\file\Entity\File $file)
  {
    $config = \Drupal::config('fits.fitsconfig');
    if ($config->get("fits-method") === "remote") {
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
        return [
          "code" => 200,
          "message" => "Get Fits Technical Metadata successfully",
          'output' => $response->getBody()->getContents()
        ];
      } catch (\Exception $e) {
        return ["code" => 500, 'message' => $e->getMessage()];
      }
    } else {
      try {
        $fits_path = $config->get("fits-path");
        //$cmd = $fits_path . " -i " . \Drupal::service('file_system')->realpath((ctype_space($file->getFileUri())) ? escapeshellarg($file->getFileUri()) : $file->getFileUri());

        if (strpos($file->getFileUri(), ' ') !== false) {
          $file_path = \Drupal::service('file_system')->realpath("public://" . escapeshellarg($file->getFilename()));
        } else {
          $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
        }
        $cmd = $fits_path . " -i " . $file_path;

        $xml = `$cmd`;

        //$messenger = \Drupal::messenger();
        //$messenger->addMessage($cmd, $messenger::TYPE_WARNING);

        return [
          "code" => 200,
          "message" => "Get Fits Technical Metadata successfully",
          'output' => $xml
        ];
      } catch (\Exception $e) {
        return ["code" => 500, 'message' => $e->getMessage()];
      }
    }

  }
}
