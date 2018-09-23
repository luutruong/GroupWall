<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Repository\Attachment;
use XF\ControllerPlugin\Editor;
use Truonglv\GroupWall\Listener;
use Truonglv\Groups\GlobalStatic;
use XF\Pub\Controller\AbstractController;
use Truonglv\GroupWall\Service\Post\Creator;
use Truonglv\GroupWall\Service\Post\Commenter;

class Post extends AbstractController
{
    public function actionIndex(ParameterBag $paramBag)
    {
        $post = $this->assertPostViewable($paramBag->post_id);

        $page = $this->filterPage();
        $perPage = GlobalStatic::getOption('commentsPerPage');

        $commentFinder = $this->postRepo()->findCommentsForList($post);

        $commentFinder->onPage($page, $perPage);

        $total = $post->comment_count;
        $comments = $commentFinder->fetch();

        /** @var Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $attachRepo->addAttachmentsToContent($comments, Listener::CONTENT_TYPE_COMMENT);

        $post->hydrateRelation('Comments', $comments);

        return $this->view('Truonglv\GroupWall:Post\Show', 'tl_group_wall_post_show', [
            'post' => $post,
            'group' => $post->getGroup(),
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page
        ]);
    }

    public function actionComment(ParameterBag $paramBag)
    {
        $this->assertPostOnly();

        if ($paramBag->post_id > 0) {
            $post = $this->assertPostViewable($paramBag->post_id);
            if (!$post->canComment($error)) {
                return $this->noPermission($error);
            }

            $group = $post->Group;
        } else {
            $group = GlobalStatic::assertionPlugin($this)
                ->assertGroupViewable($this->filter('group_id', 'uint'), [], true);
            $post = null;
        }

        if ($post === null) {
            $categoryId = $this->filter('category_id', 'uint');
            if (empty($categoryId)) {
                $categoryId = Listener::DEFAULT_POST_CATEGORY_ID;
            }

            $categoryList = $this->postRepo()->getCategoryList($group);
            if (!isset($categoryList[$categoryId])) {
                return $this->noPermission();
            }

            /** @var Creator $service*/
            $service = $this->service('Truonglv\GroupWall:Post\Creator', $group, $categoryList[$categoryId]);
        } else {
            /** @var Commenter $commenter */
            $service = $this->service('Truonglv\GroupWall:Post\Commenter', $post);
        }

        if (!($service instanceof Creator)
            && !($service instanceof Commenter)
        ) {
            throw new \LogicException('Invalid service.');
        }

        /** @var Editor $editorPlugin */
        $editorPlugin = $this->plugin('XF:Editor');

        $service->setMessage($editorPlugin->fromInput('message'));
        if ($group->canUploadAndManageAttachments()) {
            $service->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        if (!$service->validate($errors)) {
            return $this->error($errors);
        }

        $entity = $service->save();
        if ($entity instanceof \Truonglv\GroupWall\Entity\Post) {
            $entity->hydrateRelation('Comments', $this->em()->getEmptyCollection());

            return $this->view('Truonglv\GroupWall:Post\NewPost', 'tl_group_wall_post', [
                'post' => $entity
            ]);
        }

        return $this->view('Truonglv\GroupWall:Comment\NewComment', 'tl_group_wall_comment', [
            'comment' => $entity
        ]);
    }

    public function actionLoadComments(ParameterBag $paramBag)
    {
        $post = $this->assertPostViewable($paramBag->post_id);

        $before = $this->filter('before', 'uint');
        $commentFinder = $this->postRepo()->findCommentsForList($post);

        $commentFinder->where('position', '<', $before);
        $commentFinder->where('position', '>', 0);

        $commentFinder->order('comment_date', 'DESC');
        $commentFinder->limit(10);

        $comments = $commentFinder->fetch()->reverse();

        /** @var Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $attachRepo->addAttachmentsToContent($comments, Listener::CONTENT_TYPE_COMMENT);

        /** @var \Truonglv\GroupWall\Entity\Comment|null $firstComment */
        $firstComment = $comments->first();
        $showLoadMore = false;

        if ($firstComment) {
            $showLoadMore = $firstComment->position > 1;
        }

        return $this->view('Truonglv\GroupWall:Post\Comments', 'tl_group_wall_comments', [
            'comments' => $comments,
            'post' => $post,
            'showLoadMore' => $showLoadMore,
            'firstComment' => $firstComment
        ]);
    }

    /**
     * @return \Truonglv\GroupWall\Repository\Post
     */
    protected function postRepo()
    {
        /** @var \Truonglv\GroupWall\Repository\Post $postRepo */
        $postRepo = $this->repository('Truonglv\GroupWall:Post');

        return $postRepo;
    }

    /**
     * @param int $postId
     * @param array $extraWith
     * @return \Truonglv\GroupWall\Entity\Post
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertPostViewable($postId, array $extraWith = [])
    {
        $extraWith[] = 'Group';

        /** @var \Truonglv\GroupWall\Entity\Post $post */
        $post = $this->assertRecordExists('Truonglv\GroupWall:Post', $postId, $extraWith);
        if (!$post->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $post;
    }
}
