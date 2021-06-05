<?php

namespace Drupal\fits\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;

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

    } catch (\Exception $e) {

    }
  }
}
