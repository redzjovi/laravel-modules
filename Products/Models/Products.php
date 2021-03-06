<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Builder;
use Modules\PostProducts\Models\PostProducts;

class Products extends \Modules\Posts\Models\Posts
{
    use \Modules\Products\Traits\AttributesTrait;

    protected $attributes = [
        'type' => 'product',
        'status' => 'publish'
    ];

    protected static function boot()
    {
        parent::boot();
        $table = (new self)->getTable();

        self::saved(function ($model) {
            \Cache::forget('posts-post_products-post_id-'.$model->id);
        });

        self::deleting(function ($model) {
            \Cache::forget('posts-post_products-post_id-'.$model->id);
            $model->postProduct()->delete();
        });

        static::addGlobalScope('status_deleted', function (Builder $builder) use ($table) { \Auth::check() && \Auth::user()->can('backend products trash') ?: $builder->where($table.'.status', '<>', 'trash'); });
    }

    public function getAuthorId()
    {
        $authorId = 0;

        if ($this->id) {
            $authorId = $this->author_id;
        } else if (\Auth::check()) {
            $authorId = \Auth::user()->store ? \Auth::user()->store->id : 0;
        }

        return $authorId;
    }

    public function getTemplateOptions()
    {
        $templateOptions['default'] = trans('cms::cms.default');

        if ($additionalTemplateOptions = \Config::get('cms.products.postmetas.template_options')) {
            foreach ($additionalTemplateOptions as $template => $enable) {
                $enable ? $templateOptions[$template] = trans('cms::cms.'.$template) : '';
            }
        }

        return $templateOptions;
    }

    public function postProduct()
    {
        return $this->hasOne('\Modules\PostProducts\Models\PostProducts', 'post_id', 'id');
    }

    public function productWishlist()
    {
        return $this->hasOne('\Modules\ProductsWishlist\Models\ProductsWishlist', 'post_id');
    }

    public function scopeSearch($query, $params)
    {
        $query = parent::scopeSearch($query, $params);

        if (isset($params['product_ownership']) && $params['product_ownership'] === true) {
            if (\Auth::check() && \Auth::user()->can('backend products all') === false) {
                $query->where('author_id', $this->getAuthorId());
            }
        }

        // post_products
        if (isset($params['post_product_stock'])) {
            $query = $query->whereHas('postProduct', function ($postProduct) use ($params) {
                if (isset($params['post_product_stock_operator'])) {
                    $postProduct->where('stock', $params['post_product_stock_operator'], $params['post_product_stock']);
                } else {
                    $postProduct->where('stock', $params['post_product_stock']);
                }
            });
        }
        if (isset($params['post_product_sell_price'])) {
            $query = $query->whereHas('postProduct', function ($postProduct) use ($params) {
                if (isset($params['post_product_sell_price_operator'])) {
                    $postProduct->where('sell_price', $params['post_product_sell_price_operator'], $params['post_product_sell_price']);
                } else {
                    $postProduct->where('sell_price', $params['post_product_sell_price']);
                }
            });
        }
        if (isset($params['post_product_sell_price_max'])) {
            $query = $query->whereHas('postProduct', function ($postProduct) use ($params) {
                $postProduct->where('sell_price', '<=', $params['post_product_sell_price_max']);
            });
        }
        if (isset($params['post_product_sell_price_min'])) {
            $query = $query->whereHas('postProduct', function ($postProduct) use ($params) {
                $postProduct->where('sell_price', '>=', $params['post_product_sell_price_min']);
            });
        }

        if (isset($params['sort']) && $sort = explode(':', $params['sort'])) {
            if (in_array($sort[0], ['post_product_stock', 'post_product_sell_price'])) {
                $query->join((new PostProducts)->getTable().' AS post_product', function ($join) {
                    $join->on('post_product.post_id', '=', self::getTable().'.id');
                })
                ->groupBy(self::getTable().'.id')
                ->orderBy($sort[0], $sort[1])
                ->select([
                    self::getTable().'.*',
                    'post_product.status AS post_product_status',
                    'post_product.stock AS post_product_stock',
                    'post_product.sell_price AS post_product_sell_price',
                ]);
            }
        }

        return $query;
    }
}
