<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Groups\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Repository\Attachment;
use Truonglv\Groups\GlobalStatic;
use Truonglv\GroupWall\Repository\Post;

class Group extends XFCP_Group
{
    public function actionFeeds(ParameterBag $params)
    {
        $group = GlobalStatic::assertionPlugin($this)
            ->assertGroupViewable($params, $this->getGroupViewExtraWith(), true);

        $this->assertCanonicalUrl($this->buildLink('groups/feeds', $group));

        $page = $this->filterPage();
        $perPage = 20;

        /** @var Post $postRepo */
        $postRepo = $this->repository('Truonglv\GroupWall:Post');

        $finder = $postRepo->findPostsForList($group);

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


//        $attachmentRepo->addAttachmentsToContent($posts, 'tl_group_wall_comment');

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
            'attachmentData' => $attachmentData
        ]);
    }
}
