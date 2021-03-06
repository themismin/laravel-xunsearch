<?php
namespace ThemisMin\LaravelXunSearch\Engines;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Builder;
use XS as XunSearch;
use XSDocument as XunSearchDocument;
use ThemisMin\LaravelXunSearch\Contracts\XunSearchInterface;
use ThemisMin\LaravelXunSearch\IniBuilder;

class XunSearchEngine extends Engine
{
    protected $config = [
        'server_host' => 'localhost',
        'server_index_host' => null,
        'server_index_port' => 8383,
        'server_search_host' => null,
        'server_search_port' => 8384,
        'default_charset' => 'utf-8'
    ];

    protected $doc_key_name = 'xun_search_object_id';

    protected $xss = [];

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);

        if (isset($config['doc_key_name']) && $config['doc_key_name']) {
            $this->doc_key_name = $config['doc_key_name'];
        }
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     * @throws
     */
    public function update($models)
    {
        if ($this->checkUsesSoftDelete($models->first()))
            $models = $this->addSoftDeleteData($models);

        foreach ($models as $model) {
            $doc = new XunSearchDocument();
            $doc->setField($this->doc_key_name, $model->getScoutKey());
            $doc->setFields(array_merge(
                $model->toSearchableArray(), $model->scoutMetadata()
            ));
            $this->getXS($model)->index->update($doc);
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        if (!$models->isEmpty())
            $this->getXS($models->first())->index->del(
                $models->map(function ($model) {
                    return $model->getScoutKey();
                })->values()->all()
            );
    }

    /**
     * Delete all data.
     *
     * @param Model $model
     */
    public function flush($model)
    {
        $this->getXS($model)->index->clean();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'hitsPerPage' => $builder->limit,
        ]));
    }

    /**
     * 获取搜索对象
     * @param Builder $builder
     * @return \XSSearch
     */
    public function getSearch(Builder $builder) {
        return $this->getXS($builder->model)->search;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, array_filter([
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
        ]));
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $search = $this->getXS($builder->model)->search;

        if (isset($options['hitsPerPage'])) {
            if (isset($options['page']) && $options['page'] > 0) {
                $search->setLimit($options['hitsPerPage'], $options['hitsPerPage'] * $options['page']);
            }else{
                $search->setLimit($options['hitsPerPage']);
            }
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $search,
                $builder->query,
                $options
            );
        }

        $search->setFuzzy(boolval(isset($builder->fuzzy) && $builder->fuzzy))
            ->setQuery($this->buildQuery($builder));

        if (isset($builder->ranges))
            collect($builder->ranges)->map(function ($value, $key) use ($search) {
                $search->addRange($key, $value['from'], $value['to']);
            });

        if (isset($builder->weights))
            collect($builder->weights)->map(function ($value, $key) use ($search) {
                $search->addWeight($key, $value['term'], $value['weight']);
            });

        return ['docs' => $search->search(), 'total' => $search->getLastCount()];
    }

    protected function buildQuery(Builder $builder)
    {
        $query = $builder->query;

        collect($builder->wheres)->map(function ($value, $key) use (&$query) {
            $query .= ' ' . $key.':'.$value;
        });

        return $query;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['docs'])->pluck($this->doc_key_name)->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (count($results['docs']) === 0) {
            return Collection::make();
        }

        $keys = collect($results['docs'])->pluck($this->doc_key_name)->values()->all();

        $models = $model->getScoutModelsByIds(
            $builder, $keys
        )->keyBy(function ($model) {
            return $model->getScoutKey();
        });

        return Collection::make($results['docs'])->map(function ($doc) use ($models) {
            $key = $doc[$this->doc_key_name];

            if (isset($models[$key])) {
                return $models[$key];
            }

            return false;
        })->filter()->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['total'];
    }

    /**
     * Get Xun Search Object.
     *
     * @param Model $model
     * @return XunSearch
     * @throws
     */
    protected function getXS(Model $model)
    {
        $app_name = $model->searchableAs();

        if (isset($this->xss[$app_name]))
            return $this->xss[$app_name];

        return $this->xss[$app_name] = new XunSearch($this->buildIni($app_name, $model));
    }

    /**
     * Build ini.
     *
     * @param string $app_name
     * @param XunSearchInterface|Model $model
     * @return string
     * @throws \Error
     */
    protected function buildIni(string $app_name, XunSearchInterface $model)
    {
        $ini = IniBuilder::buildIni($app_name, $this->doc_key_name, $model, $this->config);

        if ($this->checkUsesSoftDelete($model))
            $ini .= $this->softDeleteFieldIni();

        return $ini;
    }

    /**
     * @return string
     * @throws \Error
     */
    protected function softDeleteFieldIni()
    {
        return IniBuilder::softDeleteField('__soft_deleted');
    }

    protected function addSoftDeleteData($models)
    {
        $models->each->pushSoftDeleteMetadata();

        return $models;
    }

    /**
     * @param $model
     * @return bool
     */
    protected function checkUsesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model))
             && config('scout.soft_delete', false);
    }
}
