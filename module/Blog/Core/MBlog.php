<?php

use ModStart\Core\Assets\AssetsUtil;
use ModStart\Core\Dao\ModelUtil;
use ModStart\Core\Util\HtmlUtil;
use ModStart\Core\Util\TagUtil;
use Module\Blog\Util\BlogCategoryUtil;
use Module\Blog\Util\BlogTagUtil;

/**
 * @Util 博客操作
 */
class MBlog
{
    public static function categoryTree()
    {
        return BlogCategoryUtil::categoryTree();
    }

    /**
     * @Util 获取最新博客
     * @param $limit int 限制条数
     * @return array 数组
     * @returnExample
     * [
     *   {
     *     "id": 19,
     *     "created_at": "2017-12-20 16:35:24",
     *     "updated_at": "2022-09-21 14:59:09",
     *     "title": "何处才是尽头",
     *     "tag": [
     *       "博客系统",
     *       "魔众系统",
     *       "旅行"
     *     ],
     *     "summary": "黑夜，多么寒凉又孤独的词。",
     *     "images": [
     *       "https://xxx.com/xxx.jpg"
     *     ],
     *     "content": "内容HTML",
     *     "isPublished": 1,
     *     "postTime": "2017-12-20 16:34:44",
     *     "clickCount": 215048,
     *     "seoKeywords": "何处才是尽头",
     *     "seoDescription": "黑夜，多么寒凉又孤独的词",
     *     "isTop": null,
     *     "commentCount": 16,
     *     "categoryId": 1,
     *     "_category": {
     *       "id": 1,
     *       "created_at": "2022-05-27 14:51:09",
     *       "updated_at": "2022-09-21 14:55:33",
     *       "pid": 0,
     *       "sort": 1,
     *       "title": "旅行",
     *       "blogCount": 1,
     *       "cover": "https://xx.com/xx.jpg",
     *       "keywords": "啊啊",
     *       "description": "版本"
     *     },
     *     "_cover": "https://xx.com/xx.jpg"
     *   },
     *   // ...
     * ]
     */
    public static function latestBlog($limit)
    {
        $paginateData = self::paginateBlog(0, 1, $limit);
        return $paginateData['records'];
    }

    /**
     * @Util 获取博客分页
     * @param $categoryId int 分类ID
     * @param $page int 分页，默认为1
     * @param $pageSize int 分页大小，默认为10
     * @param $option array 分页高级参数
     * @return array 数组
     * @returnExample
     * {
     *   "records": [
     *     {
     *       "id": 19,
     *       "created_at": "2017-12-20 16:35:24",
     *       "updated_at": "2022-09-21 14:59:09",
     *       "title": "何处才是尽头",
     *       "tag": [
     *         "博客系统",
     *         "魔众系统",
     *         "旅行"
     *       ],
     *       "summary": "黑夜，多么寒凉又孤独的词。",
     *       "images": [
     *         "https://xxx.com/xxx.jpg"
     *       ],
     *       "content": "内容HTML",
     *       "isPublished": 1,
     *       "postTime": "2017-12-20 16:34:44",
     *       "clickCount": 215048,
     *       "seoKeywords": "何处才是尽头",
     *       "seoDescription": "黑夜，多么寒凉又孤独的词",
     *       "isTop": null,
     *       "commentCount": 16,
     *       "categoryId": 1,
     *       "_category": {
     *         "id": 1,
     *         "created_at": "2022-05-27 14:51:09",
     *         "updated_at": "2022-09-21 14:55:33",
     *         "pid": 0,
     *         "sort": 1,
     *         "title": "旅行",
     *         "blogCount": 1,
     *         "cover": "https://xx.com/xx.jpg",
     *         "keywords": "啊啊",
     *         "description": "版本"
     *       },
     *       "_cover": "https://xx.com/xx.jpg"
     *     },
     *     // ...
     *   ],
     *   "total": 1
     * }
     * @example
     * // $option 说明
     * // 发布时间倒序
     * $option = [ 'order'=>['postTime', 'desc'] ];
     * // 发布时间顺序
     * $option = [ 'order'=>['postTime', 'asc'] ];
     * // 增加检索条件
     * $option = [ 'where'=>['id'=>1] ];
     */
    public static function paginateBlog($categoryId, $page = 1, $pageSize = 10, $option = [])
    {
        if ($categoryId > 0) {
            $option['whereIn'][] = ['categoryId', BlogCategoryUtil::childrenIds($categoryId)];
        }
        $option['where']['isPublished'] = true;
        if (!isset($option['order'])) {
            $option['order'] = ['postTime', 'desc'];
        }
        if (!isset($option['whereOperate'])) {
            $option['whereOperate'] = [];
        }
        $option['whereOperate'] = array_merge([
            ['postTime', '<', date('Y-m-d H:i:s')],
        ], $option['whereOperate']);

        $paginateData = ModelUtil::paginate('blog', $page, $pageSize, $option);
        $records = $paginateData['records'];
        ModelUtil::decodeRecordsJson($records, 'images');
        TagUtil::recordsString2Array($records, 'tag');
        foreach ($records as $i => $v) {
            $records[$i]['_category'] = BlogCategoryUtil::get($v['categoryId']);
            $records[$i]['images'] = AssetsUtil::fixFull($v['images']);
            $records[$i]['_cover'] = null;
            if (isset($records[$i]['images'][0])) {
                $records[$i]['_cover'] = $records[$i]['images'][0];
            }
            if (empty($records[$i]['_cover'])) {
                $ret = HtmlUtil::extractTextAndImages($v['content']);
                if (isset($ret['images'][0])) {
                    $records[$i]['_cover'] = AssetsUtil::fixFull($ret['images'][0]);
                }
            }
        }
        return [
            'records' => $records,
            'total' => $paginateData['total'],
        ];
    }

    /**
     * @Util 获取分类信息
     * @param $categoryId int 分类ID
     * @return array 数组
     * @returnExample
     * {
     *   "id": 1,
     *   "created_at": "2022-05-27 14:51:09",
     *   "updated_at": "2022-09-21 14:55:33",
     *   "pid": 0,
     *   "sort": 1,
     *   "title": "旅行",
     *   "blogCount": 1,
     *   "cover": "https://xxx.com/xxx.jpg",
     *   "keywords": "啊啊",
     *   "description": "版本"
     * }
     */
    public static function getCategory($categoryId)
    {
        return BlogCategoryUtil::get($categoryId);
    }

    /**
     * @Util 获取所有博客标签信息
     * @return array 数组，标签→ID映射
     * @returnExample
     * {
     *   "AA": 9,
     *   "测试": 2
     * }
     */
    public static function tags()
    {
        return BlogTagUtil::all();
    }
}
