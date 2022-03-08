<?php

use Module\Cms\Util\CmsCatUtil;
use Module\Cms\Util\CmsContentUtil;
use Module\Member\Auth\MemberUser;

/**
 * Class MCms
 *
 * @Util CMS操作
 */
class MCms
{

    /**
     * @param $catUrl string 栏目URL
     * @return array
     *
     * @Util 获取栏目
     */
    public static function getCatByUrl($catUrl)
    {
        return CmsCatUtil::getByUrl($catUrl);
    }

    /**
     * @param $catId integer 栏目ID
     * @return array
     *
     * @Util 获取栏目
     */
    public static function getCat($catId)
    {
        return CmsCatUtil::get($catId);
    }

    /**
     * @param $catUrl string 栏目URL
     * @return array
     *
     * @Util 根据栏目URL获取子栏目
     */
    public static function listChildrenCatByUrl($catUrl)
    {
        $cat = CmsCatUtil::getByUrl($catUrl);
        return self::listChildrenCat($cat['id']);
    }

    /**
     * @param $catId integer 栏目ID
     * @return array
     *
     * @Util 根据栏目ID获取子栏目
     */
    public static function listChildrenCat($catId)
    {
        return CmsCatUtil::children($catId);
    }

    /**
     * @param $catUrl string 栏目URL
     * @param $page int 页码
     * @param $pageSize int 分页大小
     * @param $option array 其他选项
     * @return array
     *
     * @Util 根据栏目URL获取列表
     */
    public static function paginateCatByUrl($catUrl, $page = 1, $pageSize = 10, $option = [])
    {
        $cat = CmsCatUtil::getByUrl($catUrl);
        if (empty($cat)) {
            return [];
        }
        $paginateData = CmsContentUtil::paginateCat($cat['id'], $page, $pageSize, $option);
        return $paginateData['records'];
    }

    /**
     * @param $catId int 栏目ID
     * @param $page int 页码
     * @param $pageSize int 分页大小
     * @param $option array 其他选项
     * @return array
     *
     * @Util 根据栏目ID获取列表
     */
    public static function paginateCat($catId, $page = 1, $pageSize = 10, $option = [])
    {
        $paginateData = CmsContentUtil::paginateCat($catId, $page, $pageSize, $option);
        return $paginateData['records'];
    }

    /**
     * @param $cateUrl string 栏目URL
     * @param $limit int 数量
     *
     * @Util 根据栏目URL获取最近记录
     */
    public static function latestContentByCatUrl($cateUrl, $limit = 10)
    {
        $cat = self::getCatByUrl($cateUrl);
        return self::latestCat($cat['id'], $limit);
    }

    /**
     * @param $catId int 栏目ID
     * @param $limit int 数量
     * @return array
     *
     * @Util 根据栏目ID获取最近记录
     */
    public static function latestContentByCat($catId, $limit = 10)
    {
        $paginateData = CmsContentUtil::paginateCat($catId, 1, $limit);
        $latestRecords = $paginateData['records'];
        return $latestRecords;
    }

    public static function latestCat($catId, $limit = 10)
    {
        return self::latestContentByCat($catId, $limit);
    }

    /**
     * @param $catId int 栏目ID
     * @param $recordId int 记录ID
     * @return array|null
     *
     * @Util 获取下一条记录
     */
    public static function nextOne($catId, $recordId)
    {
        return CmsContentUtil::nextOne($catId, $recordId);
    }

    /**
     * @param $catId int 栏目ID
     * @param $recordId int 记录ID
     * @return array|null
     *
     * @Util 获取上一条记录
     */
    public static function prevOne($catId, $recordId)
    {
        return CmsContentUtil::prevOne($catId, $recordId);
    }

    /**
     * @param $cat array 栏目
     * @return bool
     *
     * @Util 判断是否可以访问栏目内容
     */
    public static function canAccessCatContent($cat)
    {
        if ($cat['visitMemberGroupEnable']) {
            if (!MemberUser::isGroup($cat['visitMemberGroups'])) {
                return false;
            }
        }
        if ($cat['visitMemberVipEnable']) {
            if (!MemberUser::isVip($cat['visitMemberVips'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $cat array
     * @return bool
     *
     * @Util 判断用户是否可以发布到该栏目
     */
    public static function canPostCat($cat)
    {
        if (!$cat['memberUserPostEnable']) {
            return false;
        }
        if ($cat['postMemberGroupEnable']) {
            if (!MemberUser::isGroup($cat['postMemberGroups'])) {
                return false;
            }
        }
        if ($cat['postMemberVipEnable']) {
            if (!MemberUser::isVip($cat['postMemberVips'])) {
                return false;
            }
        }
        return true;
    }


    public static function getCatTreeWithPost()
    {

    }
}
