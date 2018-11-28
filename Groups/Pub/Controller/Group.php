<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Groups\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Repository\Attachment;
use Truonglv\GroupWall\Listener;
use Truonglv\Groups\GlobalStatic;
use Truonglv\GroupWall\Repository\Post;
use Truonglv\GroupWall\Entity\PostCategory;

class Group extends XFCP_Group
{
    public function actionFeeds(ParameterBag $params)
    {
        $group = GlobalStatic::assertionPlugin($this)
            ->assertGroupViewable($params, $this->getGroupViewExtraWith(), true);

        $this->assertCanonicalUrl($this->buildLink('groups/feeds', $group));

        $page = $this->filterPage();
        $perPage = 20;

        $selectedCategory = $this->filter('category_id', 'uint');
        if (empty($selectedCategory)) {
            $selectedCategory = Listener::DEFAULT_POST_CATEGORY_ID;
        }

        /** @var Post $postRepo */
        $postRepo = $this->repository('Truonglv\GroupWall:Post');

        $categoryList = $postRepo->getCategoryList($group);
        if (!isset($categoryList[$selectedCategory])) {
            if ($selectedCategory === Listener::DEFAULT_POST_CATEGORY_ID) {
                throw new \LogicException('Default category not exists.');
            }

            return $this->noPermission();
        }

        $finder = $postRepo->findPostsForList($group, $categoryList[$selectedCategory]);

        $total = $finder->total();
        $posts = $finder->limitByPage($page, $perPage)->fetch();

        $postRepo->addCommentsIntoPosts($posts);

        $this->assertValidPage($page, $perPage, $total, 'groups/feeds', $group);

        $attachmentHash = md5(
            $this->app()->config('globalSalt')
            . \XF::visitor()->user_id
            . $group->group_id
        );

        $attachmentData = null;
        /** @var Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');

        if ($group->canUploadAndManageAttachments()) {
            $attachmentData = $attachmentRepo->getEditorData(
                'tl_group_wall_comment',
                null,
                $attachmentHash,
                ['group_id' => $group->group_id]
            );
        }

        return $this->view('Truonglv\GroupWall:Group\Wall', 'tl_group_wall', [
            'group' => $group,
            'total' => $total,
            'posts' => $posts,
            'page' => $page,
            'perPage' => $perPage,
            'attachmentHash' => $attachmentHash,
            'attachmentData' => $attachmentData,
            'categoryList' => $categoryList,
            'selectedCategory' => $selectedCategory
        ]);
    }

    public function actionPostCategory(ParameterBag $paramBag)
    {
        /** @var \Truonglv\GroupWall\Groups\Entity\Group $group */
        $group = GlobalStatic::assertionPlugin($this)
            ->assertGroupViewable($paramBag);

        $error = null;
        if (!$group->canManagePostCategory($error)) {
            return $this->noPermission();
        }

        /** @var Post $postRepo */
        $postRepo = $this->repository('Truonglv\GroupWall:Post');
        $categoryList = $postRepo->getCategoryList($group);

        unset($categoryList[Listener::DEFAULT_POST_CATEGORY_ID]);

        if ($this->isPost()) {
            $filtered = $this->filter([
                'existing' => 'array',
                'new' => 'array'
            ]);

            foreach ($filtered['existing'] as $categoryId => $categoryTitle) {
                if (!isset($categoryList[$categoryId])) {
                    continue;
                }

                /** @var PostCategory $category */
                $category = $categoryList[$categoryId];
                $categoryTitle = utf8_trim($categoryTitle);

                if (empty($categoryTitle)) {
                    $category->delete();
                } else {
                    $category->category_title = $categoryTitle;
                    $category->save();
                }
            }

            // save new items
            foreach ($filtered['new'] as $newCategoryTitle) {
                $newCategoryTitle = utf8_trim($newCategoryTitle);
                if (empty($newCategoryTitle)) {
                    continue;
                }

                /** @var PostCategory $category */
                $category = $this->em()->create('Truonglv\GroupWall:PostCategory');
                $category->category_title = $newCategoryTitle;
                $category->group_id = $group->group_id;
                $category->save();
            }

            return $this->redirect($this->buildLink('groups/feeds', $group));
        }

        return $this->view('Truonglv\GroupWall:Post\Category', 'tl_group_wall_post_category', [
            'group' => $group,
            'categoryList' => $categoryList
        ]);
    }
}
