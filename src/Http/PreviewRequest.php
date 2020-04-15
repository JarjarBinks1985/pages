<?php

namespace Netflex\Pages;

use Netflex\Support\Accessors;

/**
 * @property-read int $structure_id
 * @property-read int $entry_id
 * @property-read int $revision_id
 */
class PreviewRequest
{
  use Accessors;

  /** @var array */
  protected $attributes = [];

  public function __construct($attributes = [])
  {
    $this->attributes = $attributes;
  }

  public function get($attribute, $default = null)
  {
    return $this->__get($attribute) ?? $default;
  }

  /**
   * Undocumented function
   *
   * @return \App\User|null
   */
  public function user ()
  {
    return null;
  }
}
