<?php

namespace Drupal\fits\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;


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
    $config = \Drupal::config('fits.fitsconfig');
    // Create a job and add to Advanced Queue
    $payload = [
      'fid' => $file->id(),
      'type' => $file->getEntityTypeId(),
      'action' => "extract_Fits"
    ];

    $job = Job::create('fits_job', $payload);
    if ($job instanceof Job) {
      $q = Queue::load($config->get("fits-advancedqueue_id"));
      $q->enqueueJob($job);
    }
  }


}
