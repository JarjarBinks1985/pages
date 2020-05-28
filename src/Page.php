<?php

namespace Netflex\Pages;

use Closure;
use Throwable;

use Netflex\API\Facades\API;

use Netflex\Query\Builder;
use Netflex\Query\QueryableModel;

use Netflex\Foundation\Template;
use Netflex\Foundation\Variable;

use Netflex\Pages\Traits\CastsDefaultFields;
use Netflex\Pages\Traits\HidesDefaultFields;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;

/**
 * @property int $id
 * @property int $directory_id
 * @property string $name
 * @property string|null $title
 * @property string $url
 * @property int $revision
 * @property string $created
 * @property string $updated
 * @property bool $published
 * @property string|null $author
 * @property int $userid
 * @property bool $use_time
 * @property string|null $start
 * @property string|null $stop
 * @property array $tags
 * @property bool $public
 * @property mixed $authgroups
 * @property array $variants
 * @property Page|null $master
 * @property string|null $lang
 * @property-read string|null $domain
 */
class Page extends QueryableModel implements Responsable
{
  use CastsDefaultFields;
  use HidesDefaultFields;

  /** @var string */
  const TEMPLATE_DOMAIN = 'd';

  /** @var string */
  const TEMPLATE_EXTERNAL = 'e';

  /** @var string */
  const TEMPLATE_INTERAL = 'i';

  /** @var string */
  const TEMPLATE_FOLDER = 'f';

  /**
   * The relation associated with the model.
   *
   * @var string
   */
  protected $relation = 'page';

  /**
   * The resolvable field associated with the model.
   *
   * @var string
   */
  protected $resolvableField = 'url';

  /**
   * Determines the default field to order the query by
   *
   * @var string
   */
  protected $defaultOrderByField = 'sorting';

  /**
   * Determines the default direction to order the query by
   *
   * @var string
   */
  protected $defaultSortDirection = Builder::DIR_ASC;

  /**
   * Indicates if we should automatically publish the model on save.
   *
   * @var bool
   */
  protected $autoPublishes = false;

  /**
   * Indicates if we should respect the models publishing status when retrieving it.
   *
   * @var bool
   */
  protected $respectPublishingStatus = false;

  /**
   * The number of models to return for pagination. Also determines chunk size for LazyCollection
   *
   * @var int
   */
  protected $perPage = -4000;

  /**
   * Indicates if the model should be timestamped.
   *
   * @var bool
   */
  public $timestamps = true;

  /**
   * The attributes that should be mutated to dates.
   *
   * @var array
   */
  protected $dates = [
    'created',
    'updated',
    'start',
    'stop'
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [];

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['*'];

  /**
   * The attributes that aren't mass assignable.
   *
   * @var array
   */
  protected $guarded = ['id', 'parent_id'];

  /**
   * The accessors to append to the model's array form.
   *
   * @var array
   */
  protected $appends = ['type'];

  /**
   * Retrieves a record by key
   *
   * @param int|null $relationId
   * @param mixed $key
   * @return array|null
   */
  protected function performRetrieveRequest(?int $relationId = null, $key)
  {
    return API::get('builder/pages/' . $key, true);
  }

  /**
   * Inserts a new record, and returns its id
   *
   * @property int|null $relationId
   * @property array $attributes
   * @return mixed
   */
  protected function performInsertRequest(?int $relationId = null, array $attributes = [])
  {
    $response = API::post('builder/pages', $attributes);

    return $response->page_id;
  }

  /**
   * Updates a record
   *
   * @param int|null $relationId
   * @param mixed $key
   * @param array $attributes
   * @return void
   */
  protected function performUpdateRequest(?int $relationId = null, $key, $attributes = [])
  {
    return API::put('builder/pages/' . $key, $attributes);
  }

  /**
   * Deletes a record
   *
   * @param int|null $relationId
   * @param mixed $key
   * @return bool
   */
  protected function performDeleteRequest(?int $relationId = null, $key)
  {
    return !!API::delete('builder/pages/' . $key);
  }

  /**
   * Retrieves the component names of the given block
   *
   * @param string $area
   * @return string
   */
  public function getBlocks($area)
  {
    $blocks = $this->content->filter(function ($content) use ($area) {
      return $content->area === $area;
    })->map(function ($block) {
      if ($template = Template::retrieve((int) $block->text)) {
        return [$template->alias, $block->title ? $block->title : null];
      };
    });

    return $blocks->filter();
  }

  /**
   * @return bool
   */
  public function hasTemplate()
  {
    return (bool) ($this->attributes['template'] ?? null)
      && is_numeric($this->attributes['template']);
  }

  public function getMasterAttribute()
  {
    if (!$this->parent) {
      return $this;
    }

    return $this->parent->master;
  }

  /**
   * Gets the domain name of the page, using its master page as the base
   *
   * @return string|null
   */
  public function getDomainAttribute()
  {
    $master = $this->master;
    if ($master && $master !== $this) {
      if ($master->type === 'domain' && preg_match('/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/', $master->name) !== false) {
        return $master->name;
      }
    }
  }

  public function getLangAttribute()
  {
    if (!$this->attributes['lang']) {
      $parent = $this->parent;
      while ($parent && !$parent->lang) {
        $parent = $parent->parent;
      }

      if ($parent) {
        return $parent->lang;
      }

      return App::getLocale();
    }

    return $this->attributes['lang'];
  }

  /**
   * @param int $template
   * @return Template
   */
  public function getTemplateAttribute($template = null)
  {
    if ($this->hasTemplate()) {
      return Template::retrieve((int) $template);
    }
  }

  /**
   * @param string $title
   * @return string
   */
  public function getTitleAttribute($title)
  {
    if ($title) {
      return $title;
    }

    return $this->name;
  }

  /**
   * Create an HTTP response that represents the object.
   *
   * @param  \Illuminate\Http\Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function toResponse($request)
  {
    $current_page = current_page();
    $blockhash = blockhash();

    current_page($this);
    blockhash(null);

    $rendered = $this->template ? $this->template->toResponse() : null;

    current_page($current_page);
    blockhash($blockhash);

    return $rendered;
  }

  /**
   * Gets the parent page
   *
   * @return static|null
   */
  public function getParentAttribute()
  {
    return static::find((int) $this->parent_id);
  }

  /**
   * Gets this page's children
   *
   * @return collection
   */
  public function getChildrenAttribute()
  {
    return static::all()
      ->filter(function ($page) {
        return (int) $page->parent_id === (int) $this->id;
      })
      ->values();
  }

  /**
   * Retrieves all instances
   *
   * @return Collection|LazyCollection
   * @throws NotQueryableException If object not queryable
   * @throws QueryException On invalid query
   */
  public static function all()
  {
    return Cache::rememberForever('pages', function () {
      return static::raw('*')->get();
    });
  }

  /**
   * Finds an instance by its primary field
   *
   * @param int|string $id
   * @return static|null
   * @throws NotQueryableException If object not queryable
   * @throws QueryException On invalid query
   */
  public static function find($id)
  {
    return static::all()->first(function (self $page) use ($id) {
      return $page->getKey() === (int) $id;
    });
  }

  /**
   * Resolves an instance
   *
   * @param mixed $resolveBy
   * @param string|null $field
   * @return static|Collection|null
   * @throws NotQueryableException If object not queryable
   * @throws QueryException On invalid query
   */
  public static function resolve($resolveBy, $field = null)
  {
    $resolveBy = Collection::make([$resolveBy])
      ->flatten()
      ->toArray();

    foreach ($resolveBy as $value) {
      $value = $value === '/' ? '/index' : $value;
      $resolveBy[] = $value;
      $resolveBy[] = "$value/";
      $resolveBy[] = Str::replaceFirst('/', '', "$value/");
    }

    $resolveBy = array_map('strtolower', array_unique($resolveBy));

    $resolved = static::all()->filter(function (self $page) use ($resolveBy, $field) {
      $key = strtolower($page->{$field ?? $page->resolvableField});
      return in_array($key, $resolveBy);
    });

    return count($resolveBy) === 1 ? $resolved->first() : $resolved;
  }

  /**
   * Create a new model instance that is existing.
   *
   * @param array $attributes
   * @return static
   */
  public function newFromBuilder($attributes = [])
  {
    $model = parent::newFromBuilder($attributes);

    if ($model->getKey() && Cache::has('pages')) {
      $pages = static::all()->filter(function (self $page) use ($model) {
        return $page->getKey() !== $model->getKey();
      });

      $pages->push($model);

      Cache::forget('pages');
      Cache::rememberForever('pages', function () use ($pages) {
        return $pages;
      });
    }

    return $model;
  }

  /**
   * Loads the given revision
   *
   * @param int $revisionId
   * @return static
   */
  public function loadRevision($revisionId = null)
  {
    if (!$revisionId) {
      return $this;
    }

    try {
      $content = API::get("builder/pages/{$this->getKey()}/content/{$revisionId}", true);
      $this->attributes['revision'] = $revisionId;
      $this->attributes['content'] = $content;
      return $this;
    } catch (Throwable $e) {
      return null;
    }
  }

  /**
   * Retrieves the current matched Page
   *
   * @return static|null
   */
  public static function current()
  {
    return current_page();
  }
}
