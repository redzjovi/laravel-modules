<?php

namespace Modules\Posts\Models;

use Illuminate\Database\Eloquent\Builder;
use Modules\Postmetas\Models\Postmetas;
use Modules\Users\Models\Users;
use Modules\Terms\Models\Terms;

class Posts extends \Illuminate\Database\Eloquent\Model
{
    use \Dimsav\Translatable\Translatable;
    use \Modules\Posts\Traits\AttributesTrait;
    use \Modules\Posts\Traits\HelperTrait;
    use \Modules\Posts\Traits\PostmetasTrait;

    protected $attributes = [
        'type' => 'post',
        'status' => 'publish'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // 'id',
        'author_id',
        'type',
        'mime_type',
        'parent_id',

        'status',
        'comment_status',
        'comment_count',
    ];

    protected $table = 'posts';

    protected $with = ['translations'];

    public $translatedAttributes = ['title', 'name', 'excerpt', 'content', 'content_2', 'content_3', 'content_4', 'content_5'];
    public $translationForeignKey = 'post_id';
    public $translationModel = '\Modules\PostTranslations\Models\PostTranslations';

    protected static function boot()
    {
        parent::boot();
        $table = (new self)->getTable();

        self::saved(function ($model) {
            \Cache::forget('posts-'.$model->id);
            \Cache::forget('posts-name-'.$model->name);
            \Cache::forget('posts-postmetas-'.$model->id);
            \Cache::forget('posts-post_testimonials.post_id-'.$model->id);
        });

        self::deleted(function ($model) {
            \Cache::forget('posts-'.$model->id);
            \Cache::forget('posts-name-'.$model->name);
            \Cache::forget('posts-postmetas-'.$model->id);
            \Cache::forget('posts-post_testimonials.post_id-'.$model->id);
            \Storage::deleteDirectory('media/original/'.$model->id);
            \Storage::deleteDirectory('media/thumbnail/'.$model->id);
            $model->postmetas->each(function ($postmeta) { $postmeta->delete(); });
            $model->translations->each(function ($translation) { $translation->delete(); });
        });

        static::addGlobalScope(new \Modules\Cms\Scopes\TypeScope);
        static::addGlobalScope('status_deleted', function (Builder $builder) use ($table) { \Auth::check() && \Auth::user()->can('backend posts trash') ?: $builder->where($table.'.status', '<>', 'trash'); });
    }

    public function author()
    {
        return $this->hasOne('\Modules\Users\Models\Users', 'id', 'author_id');
    }

    public function getTemplateOptions()
    {
        $templateOptions['default'] = trans('cms::cms.default');

        if ($additionalTemplateOptions = \Config::get('cms.posts.postmetas.template_options')) {
            foreach ($additionalTemplateOptions as $template => $enable) {
                $enable ? $templateOptions[$template] = trans('cms::cms.'.$template) : '';
            }
        }

        return $templateOptions;
    }

    public function parent()
    {
        return $this->belongsTo('\Modules\Posts\Models\Posts', 'parent_id');
    }

    public function postmetas()
    {
        return $this->hasMany('\Modules\Postmetas\Models\Postmetas', 'post_id', 'id');
    }

    public function postTestimonial()
    {
        return $this->hasOne('\Modules\PostTestimonials\Models\PostTestimonials', 'post_id');
    }

    public function scopeAction($query, $params)
    {
        if (isset($params['action_id'])) {
            if (array_key_exists($params['action'], $this->getStatusOptions())) {
                $this->search(['id_in' => $params['action_id']])->update(['status' => $params['action']]);
                flash(trans('cms::cms.data_has_been_updated'))->success()->important();
            } else if ($params['action'] == 'delete' ) {
                if ($posts = self::whereIn('id', $params['action_id'])->get()) {
                    $posts->each(function ($post) { $post->delete(); });
                }
                flash(trans('cms::cms.data_has_been_deleted').' ('.$posts->count().')')->success()->important();
            }
        }
        return $query;
    }

    public function scopeSearch($query, $params)
    {
        isset($params['id']) ? $query->where('id', $params['id']) : '';
        isset($params['id_in']) ? $query->whereIn(self::getTable().'.id', $params['id_in']) : '';
        isset($params['id_not']) ? $query->where('id', '<>', $params['id_not']) : '';
        isset($params['author_id']) ? $query->where('author_id', $params['author_id']) : '';
        isset($params['type']) ? $query->where('type', $params['type']) : '';
        isset($params['mime_type']) ? $query->where('mime_type', $params['mime_type']) : '';
        isset($params['mime_type_like']) ? $query->where('mime_type', 'like', '%'.$params['mime_type_like'].'%') : '';
        if (isset($params['mime_type_like_in'])) {
            $mimeTypeLikes = explode(',', $params['mime_type_like_in']);
            $query->where(function ($query) use ($mimeTypeLikes) {
                foreach ($mimeTypeLikes as $mimeTypeLike) {
                    $query->orWhere('mime_type', 'like', '%'.$mimeTypeLike.'%');
                }
            });
        }
        isset($params['parent_id']) ? $query->where('parent_id', $params['parent_id']) : '';
        isset($params['parent_id_in']) ? $query->whereIn('parent_id', $params['parent_id_in']) : '';
        isset($params['status']) ? $query->where(self::getTable().'.status', $params['status']) : '';
        isset($params['created_at']) ? $query->where(self::getTable().'.created_at', 'like', '%'.$params['created_at'].'%') : '';
        isset($params['created_at_date']) ? $query->whereDate(self::getTable().'.created_at', '=', $params['created_at_date']) : '';
        isset($params['updated_at_date']) ? $query->whereDate(self::getTable().'.updated_at', '=', $params['updated_at_date']) : '';

        // postmetas
        isset($params['category_id']) ? $query->join((new Postmetas)->getTable().' AS postmetas_category_id', 'postmetas_category_id.post_id', '=', self::getTable().'.id')->where('postmetas_category_id.key', 'categories')->where('postmetas_category_id.value', 'LIKE', '%"'.$params['category_id'].'"%') : ('');
        if (isset($params['category_id_in'])) {
            $query->join((new Postmetas)->getTable().' AS postmetas_category_id_in', 'postmetas_category_id_in.post_id', '=', self::getTable().'.id')->where('postmetas_category_id_in.key', 'categories');
            $categoryIdIn = $params['category_id_in'];
            $query = $query->where(function($query) use ($categoryIdIn) {
                foreach ($categoryIdIn as $categoryId) {
                    $query->orWhere('postmetas_category_id_in.value', 'LIKE', '%"'.$categoryId.'"%');
                }
            });
        }
        if (isset($params['category_ids'])) {
            $query->join((new Postmetas)->getTable().' AS postmetas_category_ids', 'postmetas_category_ids.post_id', '=', self::getTable().'.id')->where('postmetas_category_ids.key', 'categories');
            $categoryIds = $params['category_ids'];
            $query = $query->where(function($query) use ($categoryIds) {
                foreach ($categoryIds as $categoryId) {
                    $query->where('postmetas_category_ids.value', 'LIKE', '%"'.$categoryId.'"%');
                }
            });
        }
        if (isset($params['category_slug'])) {
            $query->join((new Postmetas)->getTable().' AS postmetas_category_slug', 'postmetas_category_slug.post_id', '=', self::getTable().'.id')->where('postmetas_category_slug.key', 'categories')->where('postmetas_category_slug.value', 'LIKE', '%"'.Terms::getBySlug($params['category_slug'])->id.'"%');
        }
        if (isset($params['category_slugs'])) {
            $query->join((new Postmetas)->getTable().' AS postmetas_category_slugs', 'postmetas_category_slugs.post_id', '=', self::getTable().'.id')->where('postmetas_category_slugs.key', 'categories');
            $categorySlugs = $params['category_slugs'];
            $query = $query->where(function($query) use ($categorySlugs) {
                foreach ($categorySlugs as $categorySlug) {
                    $query->where('postmetas_category_slugs.value', 'LIKE', '%"'.Terms::getBySlug($categorySlug)->id.'"%');
                }
            });
        }
        if (isset($params['tag_id_in']) && is_array($params['tag_id_in'])) {
            $query->join((new Postmetas)->getTable().' AS postmetas_tag_id_in', 'postmetas_tag_id_in.post_id', '=', self::getTable().'.id')->where('postmetas_tag_id_in.key', 'tags');
            $tagIdIn = $params['tag_id_in'];
            $query = $query->where(function($query) use ($tagIdIn) {
                foreach ($tagIdIn as $tagId) {
                    $query->orWhere('postmetas_tag_id_in.value', 'LIKE', '%"'.$tagId.'"%');
                }
            });
        }
        isset($params['template']) ? $query->join((new Postmetas)->getTable().' AS postmeta_template', 'postmeta_template.post_id', '=', self::getTable().'.id')->where('postmeta_template.key', 'template')->where('postmeta_template.value', $params['template']) : ('');

        // post_testimonials
        if (isset($params['post_testimonial_rating'])) {
            $query = $query->whereHas('postTestimonial', function ($postTestimonial) use ($params) {
                if (isset($params['post_testimonial_rating_operator'])) {
                    $postTestimonial->where('rating', $params['post_testimonial_rating_operator'], $params['post_testimonial_rating']);
                } else {
                    $postTestimonial->where('rating', $params['post_testimonial_rating']);
                }
            });
        }
        if (isset($params['post_testimonial_rating_average'])) {
            $query = $query->whereHas('postTestimonial', function ($postTestimonial) use ($params) {
                if (isset($params['post_testimonial_rating_average_operator'])) {
                    $postTestimonial->where('rating_average', $params['post_testimonial_rating_average_operator'], $params['post_testimonial_rating_average']);
                } else {
                    $postTestimonial->where('rating_average', $params['post_testimonial_rating_average']);
                }
            });
        }

        // post_translations
        isset($params['locale']) ? $query->whereTranslation('locale', $params['locale']) : '';
        isset($params['title']) ? $query->whereTranslation('title', $params['title']) : '';
        isset($params['title_like']) ? $query->whereTranslationLike('title', '%'.$params['title_like'].'%') : '';
        isset($params['name']) ? $query->whereTranslation('name', $params['name']) : '';
        isset($params['name_like']) ? $query->whereTranslationLike('name', '%'.$params['name_like'].'%') : '';
        isset($params['excerpt']) ? $query->whereTranslationLike('excerpt', '%'.$params['excerpt'].'%') : '';
        isset($params['content']) ? $query->whereTranslationLike('content', '%'.$params['content'].'%') : '';
        isset($params['content_like']) ? $query->whereTranslationLike('content', '%'.$params['content_like'].'%') : '';

        if (isset($params['sort']) && $sort = explode(':', $params['sort'])) {
            if (in_array($sort[0], ['created_at', 'updated_at'])) {
                $query->orderBy(self::getTable().'.'.$sort[0], $sort[1]);
            } else if (in_array($sort[0], ['title', 'name', 'excerpt', 'content'])) {
                $query->join($this->getTranslationsTable().' AS translation', function ($join) {
                    $join->on('translation.post_id', '=', self::getTable().'.id');
                    isset($params['locale']) ? $query->where('translation.locale', $params['locale']) : '';
                })
                ->groupBy(self::getTable().'.id')
                ->orderBy('translation.'.$sort[0], $sort[1])
                ->select(self::getTable().'.*');
            } else if (in_array($sort[0], ['author_name'])) {
                $query->join((new Users)->getTable().' AS author', function ($join) {
                    $join->on('author.id', '=', self::getTable().'.author_id');
                })
                ->orderBy($sort[0], $sort[1])
                ->select([
                    self::getTable().'.*',
                    'author.name AS author_name',
                ]);
            } else if (in_array($sort[0], ['post_testimonial_rating_average'])) {
                $query->leftJoin((new \Modules\PostTestimonials\Models\PostTestimonials)->getTable().' AS post_testimonial', function ($join) {
                    $join->on('post_testimonial.post_id', '=', self::getTable().'.id');
                })
                ->orderBy($sort[0], $sort[1])
                ->select([
                    self::getTable().'.*',
                    'post_testimonial.rating_average AS post_testimonial_rating_average',
                ]);
            } else if (str_contains($sort[0], 'postmetas.')) {
                $key = explode('.', $sort[0]);
                $key = $key[1];
                $query->join((new Postmetas)->getTable().' AS postmetas', function ($join) use ($key) {
                    $join->on('postmetas.post_id', '=', self::getTable().'.'.self::getKeyName());
                    $join->where('postmetas.key', '=', $key);
                })
                ->orderBy('postmetas.value', $sort[1]);
            } else {
                count($sort) == 2 ? $query->orderBy($sort[0], $sort[1]) : '';
            }
        }

        return $query;
    }
}
