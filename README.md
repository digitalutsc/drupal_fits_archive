# Fits for Drupal

## Introduction

This Drupal 8/9 module consumes File Information Tool Set (Fits) to retrieve and extract technical metadata for fieldable files.

## Installation

- Install JMESPath library (needed for the extraction) by `composer require 'mtdowling/jmespath.php'`.
- [Fits XML](https://projects.iq.harvard.edu/fits/get-started-using-fits) (either REST endpoint or from command-line).
- Drupal modules dependencies, so highly recommend install this module by composer: `composer require 'drupal/fits'`.
  - [JSON Field](https://www.drupal.org/project/json_field)
  - [File Entity (fieldable files)](https://www.drupal.org/project/file_entity)
  - [Field Permissions](https://www.drupal.org/project/field_permissions)
  - [Advanced Queue](https://www.drupal.org/project/advancedqueue)

## Configuration

- Visit  `/admin/config/system/fits` as screenshot below:

![Fits config](https://www.drupal.org/files/project-images/Screen%20Shot%202021-06-23%20at%2011.10.11%20PM.png)

- Visit `/admin/structure/file-types`, then click `Edit` in each File type for further detail and configure on Technical metadata fields.

![Fits fields config](https://www.drupal.org/files/project-images/Screen%20Shot%202021-06-23%20at%2011.17.44%20PM.png)

- To add more field(s) for extracting technical metadata, click `+ Add field` button. Filling out all main fields.
  * In the **Helper text** field (screenshot below), make sure to enter one or multiple JMESPath(s) **each wrapped between "[{ }]"** (ie. [{fileinfo.md5checksum}]).
  * For further details about JMESPath, please visit: https://jmespath.org/tutorial.html

![Jmespath config](https://www.drupal.org/files/project-images/Screen%20Shot%202021-06-23%20at%2011.54.52%20PM.png)

## Usage

- Upload a file at `/file/add` or add a Media at `/media/add`.
- After a file uploaded completed, a job will be added to a queue which can be visited at `admin/config/system/queues`. Then, select `List Jobs` of the designated queue for Fits (select above) to monitor the progress.
- To run the queues, there are 3 ways:
  - By Cron at `admin/config/system/cron`
  - By drush/drupal by `drush advancedqueue:queue:process [{ queue name }]` in terminal.
  - By [Advanced Queue Runner](https://www.drupal.org/project/advancedqueue_runner)
- When a job is executed, there are 2 main operations:
  * Retrieve Fits: It will consume Fits XML to retrieve technical metadata in XML, then convert it to JSON and save it in the `field_fits`.
  * Extraction: After the technical metadata is saved in the json field `field_fits`, the extraction for each field based on JMESPaths which are defined in each Fits fields.
- To check the outcome of the Fits metadata and extracted fields in a file, visit `/file/[{ just uploaded File ID }]/edit`
