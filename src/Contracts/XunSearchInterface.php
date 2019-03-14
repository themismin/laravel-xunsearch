<?php
namespace ThemisMin\LaravelXunSearch\Contracts;

/**
 * Interface XunSearchInterface
 *
 * type 字段类型
 * string 字符型，适用多数情况，也是默认值
 * numeric 数值型，包含整型和浮点数，仅当字段需用于以排序或区间检索时才设为该类型，否则请使用 string　即可
 * date 日期型，形式为 YYYYmmdd 这样固定的 8 字节，如果没有区间检索或排序需求不建议使用
 * id 主键型，确保每条数据具备唯一值，是索引更新和删除的凭据，每个搜索项目必须有且仅有一个 id 字段，该字段的值不区分大小写
 * title 标题型，标题或名称字段，至多有一个该类型的字段
 * body 内容型，主内容字段, 即本搜索项目中内容最长的字段，至多只有一个该类型字段，本字段不支持字段检索
 * type = string
 * *
 * *
 * index 索引方式
 * xunsearch 的索引有 2 种模式：其一是不标明字段的检索，称之为“混合区检索”；其二是标明特定字段的“字段检索”。 例如：搜索 XXX YYY 表示在混合区检索，返回的结果可能是 title 也有可能是 body 字段符合匹配； 而搜索 title:XXX 则表示仅检索 title 匹配 XXX 的数据。每个字段可以指定的索引方式的值如下：
 * none 不做索引，所有的搜索匹配均与本字段无关，这个字段只用于排序或搜索结果展示用到。
 * self 字段索引，可以在搜索时用 field:XXX 来检索本字段
 * mixed 混合区索引，不标明字段的默认搜索也可以检索本字段
 * both 相当于 self + mixed，两种情况均索引
 * 通常情况默认值为 none ，但 id 型字段默认是 self ，title 型字段是 both ，body 型字段则固定为 mixed 。
 * index = none
 * *
 * *
 * tokenizer 分词器
 * 默认为 default 采用内置的功能强大的 scws 分词，适合绝大多数字符串字段。也可以指定自定义分词器， 格式为 name 或 name(arg) 两种形式，其中 name 是分词器名称，arg 则是传递给分词器构造函数的参数。 自定义分词器需要在 lib/ 目录下编写名为 XSTokenizerName 的分词类并实现接口 XSTokenizer， 内置支持的分词器有以下几种：
 * full 表示本字段的值整体作为一个检索词，像各种 ID 都适合这种情况
 * none 表示本字段没有任何词汇用于索引
 * split([ ]) 表示根据参数分割内容，默认参数为空格，若参数以 / 开头并以 / 结尾则 内部调用 preg_split(arg, ..) 来分割取词，以支持正则或其它特殊字符分割
 * xlen([2]) 表示根据指定参数长度分段取词，如 ABCDEF => AB + CD + EF
 * xstep([2]) 表示根据指定参数步长逐段取词，如 ABCDEF => AB + ABCD + ABCDEF
 * scws([3]) 表示采用指定参数为复合等级的 scws 分词，（若无特殊复合需求，无需指定）
 * tokenizer = default
 *
 * @package ThemisMin\LaravelXunSearch\Contracts
 */
interface XunSearchInterface
{
    const XUNSEARCH_TYPE_STRING = 'string';
    const XUNSEARCH_TYPE_NUMERIC = 'numeric';
    const XUNSEARCH_TYPE_DATE = 'date';
    const XUNSEARCH_TYPE_ID = 'id';
    const XUNSEARCH_TYPE_TITLE = 'title';
    const XUNSEARCH_TYPE_BODY = 'body';

    const XUNSEARCH_INDEX_NONE = 'none';
    const XUNSEARCH_INDEX_SELF = 'self';
    const XUNSEARCH_INDEX_MIXED = 'mixed';
    const XUNSEARCH_INDEX_BOTH = 'both';

    const XUNSEARCH_TOKENIZER_DEFAULT = 'default';
    const XUNSEARCH_TOKENIZER_NONE = 'none';
    const XUNSEARCH_TOKENIZER_FULL = 'full';
    const XUNSEARCH_TOKENIZER_SPLIT = 'split';
    const XUNSEARCH_TOKENIZER_XLEN = 'xlen';
    const XUNSEARCH_TOKENIZER_XSTEP = 'xstep';
    const XUNSEARCH_TOKENIZER_SCWS = 'scws';

    /**
     * Setting Fields.
     *
     * @see http://www.xunsearch.com/doc/php/guide/ini.guide
     * @return array
     * @example
     * return [
     *      'id' => [
     *          'type'=>self::XUNSEARCH_TYPE_NUMERIC,
     *      ],
     *      'title' => [
     *          'type'=>self::XUNSEARCH_TYPE_TITLE,
     *      ],
     *      'body' => [
     *          'type'=>self::XUNSEARCH_TYPE_BODY,
     *      ],
     *      'field' => [
     *          'tokenizer'=>self::XUNSEARCH_TOKENIZER_XLEN,
     *          'tokenizer_value'=>2,
     *      ],
     *      'data' => [
     *          'type'=>self::XUNSEARCH_TYPE_DATE,
     *          'index'=>self::XUNSEARCH_INDEX_NONE,
     *      ],
     * ]
     */
    public function xunSearchFieldsType();
}
