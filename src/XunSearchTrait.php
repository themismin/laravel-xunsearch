<?php
namespace ThemisMin\LaravelXunSearch;

use Laravel\Scout\Builder;

trait XunSearchTrait
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootXunSearchTrait()
    {
        (new static)->registerXunSearchMacros();
    }

    public function registerXunSearchMacros()
    {
        $this->registerSearchableRangeSearch();
        $this->registerSearchableFuzzy();
        $this->registerSearchableAddWeight();
        $this->registerSearchableGetSearch();
    }

    public function registerSearchableRangeSearch()
    {
        Builder::macro('range', function ($word, $from, $to) {
            $this->ranges[$word]['from'] = $from;
            $this->ranges[$word]['to'] = $to;

            return $this;
        });
    }

    public function registerSearchableFuzzy()
    {
        Builder::macro('fuzzy', function ($fuzzy = true) {
            $this->fuzzy = (bool) $fuzzy;

            return $this;
        });
    }

    public function registerSearchableAddWeight()
    {
        Builder::macro('addWeight', function (string $field, string $term, float $weight=1.0) {
            $this->weights[$field]['term'] = $term;
            $this->weights[$field]['weight'] = $weight;

            return $this;
        });
    }

    /**
     * 获取搜索对象
     */
    public function registerSearchableGetSearch()
    {
        Builder::macro('getSearch', function () {
            return $this->engine()->getSearch($this);
        });
    }
}
