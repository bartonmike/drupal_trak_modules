<?php

namespace Drupal\study_manager\Service;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads the index of shared studies from global.yaml.
 *
 * global.yaml lives at the root of the study_data directory (the same
 * "private://{config file_path}/study_data/" root study uploads are written
 * under; see study_manager_file_download()). It's produced outside Drupal,
 * so every field is treated as optional - most are null in practice.
 */
class SharedStudyIndex {

  /**
   * Metadata keys shown on the shared studies page, keyed to their labels.
   */
  const METADATA_LABELS = [
    'sampler_technology' => 'Sampler technology',
    'exposure_category' => 'Exposure category',
    'exposure_of_interest' => 'Exposure of interest',
    'measurement_interest' => 'Measurement interest',
    'health_outcome_of_interest' => 'Health outcome',
  ];

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Parsed projects for this request, keyed by project ID.
   *
   * @var array|null
   */
  protected $projects;

  /**
   * Constructs the index.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Gets the URI of global.yaml.
   *
   * Uses the "shared_index_path" setting (a path within the private file
   * system) when set, otherwise global.yaml at the study_data root.
   */
  public function getIndexUri(): string {
    $config = $this->configFactory->get('study_manager.settings');
    $index_path = trim((string) $config->get('shared_index_path'));
    if ($index_path !== '') {
      return 'private://' . $index_path;
    }
    $file_path = $config->get('file_path') ?: 'studies';
    return 'private://' . $file_path . '/study_data/global.yaml';
  }

  /**
   * Gets all shared studies, keyed by project ID.
   *
   * @return array
   *   Each project is an array with keys: project_id, name, description,
   *   encrypted, start, end, locations (list of strings), metadata (label =>
   *   list of strings), and file_name (relative to the study_data root).
   */
  public function getProjects(): array {
    if ($this->projects !== NULL) {
      return $this->projects;
    }

    $this->projects = [];
    $uri = $this->getIndexUri();
    if (!is_readable($uri)) {
      $this->logger->warning('Shared study index @uri is missing or unreadable.', ['@uri' => $uri]);
      return $this->projects;
    }

    try {
      $data = Yaml::decode(file_get_contents($uri));
    }
    catch (InvalidDataTypeException $e) {
      $this->logger->error('Failed to parse shared study index @uri: @message', [
        '@uri' => $uri,
        '@message' => $e->getMessage(),
      ]);
      return $this->projects;
    }

    foreach ($data['projects'] ?? [] as $project) {
      $id = isset($project['project_id']) ? trim((string) $project['project_id']) : '';
      if ($id === '') {
        continue;
      }
      $this->projects[$id] = $this->normalize($id, $project);
    }

    return $this->projects;
  }

  /**
   * Gets a single shared study by project ID, or NULL if it isn't indexed.
   */
  public function getProject(string $project_id): ?array {
    return $this->getProjects()[$project_id] ?? NULL;
  }

  /**
   * Flattens one raw global.yaml project entry into display-ready values.
   */
  protected function normalize(string $id, array $project): array {
    $locations = [];
    foreach ($project['locations'] ?? [] as $location) {
      $name = trim((string) ($location['location_name'] ?? ''));
      $specified = trim((string) ($location['specified_location'] ?? ''));
      if ($name !== '' && $specified !== '' && $specified !== $name) {
        $locations[] = $name . ' (' . $specified . ')';
      }
      elseif ($name !== '' || $specified !== '') {
        $locations[] = $name !== '' ? $name : $specified;
      }
    }

    $metadata = [];
    foreach (self::METADATA_LABELS as $key => $label) {
      $values = $this->toList($project['metadata'][$key] ?? NULL);
      if ($values) {
        $metadata[$label] = $values;
      }
    }

    return [
      'project_id' => $id,
      'name' => trim((string) ($project['study_name'] ?? '')) ?: $id,
      'description' => trim((string) ($project['study_description'] ?? '')),
      'encrypted' => !empty($project['encrypted']),
      'start' => $this->toDate($project['date_range']['start'] ?? NULL),
      'end' => $this->toDate($project['date_range']['end'] ?? NULL),
      'locations' => $locations,
      'metadata' => $metadata,
      'file_name' => trim((string) ($project['dataset']['file_name'] ?? '')),
    ];
  }

  /**
   * Normalizes a date_range value to a string.
   *
   * The YAML parser turns unquoted dates (e.g. 2023-01-01) into Unix
   * timestamps, so integers are converted back; quoted strings pass through.
   */
  protected function toDate($value): string {
    if (is_int($value)) {
      return gmdate('Y-m-d', $value);
    }
    return trim((string) $value);
  }

  /**
   * Normalizes a metadata value (null, a scalar, a delimited string, or a
   * YAML list) into a de-duplicated list of non-empty strings.
   */
  protected function toList($value): array {
    if ($value === NULL || $value === '') {
      return [];
    }
    $parts = is_array($value) ? $value : preg_split('/[;,]+/', (string) $value);

    $out = [];
    foreach ($parts as $part) {
      $part = trim((string) $part);
      if ($part !== '' && !isset($out[strtolower($part)])) {
        $out[strtolower($part)] = $part;
      }
    }
    return array_values($out);
  }

}
