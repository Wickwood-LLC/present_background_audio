<?php

namespace Drupal\present_background_audio;

class AudioTrackRegion {
  /**
   * Start of the region in milliseconds
   */
  public float $start;

  /**
   * End of the region in milliseconds
   */
  public float $end;

  /**
   * Title of the region
   */
  public string $content;

  /**
   * Whether the region is draggable
   */
  public bool $drag;

  /**
   * Whether the region is resizable
   */
  public bool $resize;

  /**
   * Color of the region
   */
  public string $color;

  /**
   * Type of the region
   */
  public string $type;

  /**
   * Fixed size of the region in seconds.
   * Fixed sized region cannot be resized.
   */
  public float $fixed_size;

  /**
   * ID of the region
   */
  public string $id;

  /**
   * Minimum length of the region in seconds
   */
  public float $minLength;

  /**
   * Maximum length of the region in seconds
   */
  public float $maxLength;

  /**
   * Create a new AudioTrackRegion object from an array of data.
   */
  public static function create(array $data): self {
    $instance = new self();
    foreach ($data as $key => $value) {
      $instance->$key = $value;
    }
    return $instance;
  }

  /**
   * Get the duration of the region in milliseconds.
   */
  public function getDuration(): float {
    return $this->end - $this->start;
  }
}