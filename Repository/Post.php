<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Repository;

use XF\Mvc\Entity\Repository;
use XF\Repository\Attachment;
use Truonglv\GroupWall\Listener;
use Truonglv\Groups\Entity\Group;
use Truonglv\Groups\GlobalStatic;
use XF\Mvc\Entity\ArrayCollection;
use Truonglv\GroupWall\Entity\Comment;
use Truonglv\GroupWall\Entity\PostCategory;

class Post extends Repository
{
    public function findPostsForList(Group $group, PostCategory $category = null)
    {
        $finder = $this->finder('Truonglv\GroupWall:Post');

        $finder->where('group_id', $group->group_id);
        if (GlobalStatic::getOption('enableWallCategories')) {
            $finder->where('category_id', $category ? $category->category_id : Listener::DEFAULT_POST_CATEGORY_ID);
        } else {
            $finder->where('category_id', Listener::DEFAULT_POST_CATEGORY_ID);
        }

        $finder->with('User');

        $finder->setDefaultOrder('last_comment_date', 'DESC');

        return $finder;
    }

    /**
     * @param \Truonglv\GroupWall\Entity\Post $post
     * @return \Truonglv\GroupWall\Finder\Comment
     */
    public function findCommentsForList(\Truonglv\GroupWall\Entity\Post $post)
    {
        /** @var \Truonglv\GroupWall\Finder\Comment $finder */
        $finder = $this->finder('Truonglv\GroupWall:Comment');
        $finder->forView();

        $finder->where('post_id', $post->post_id);

        return $finder;
    }

    public function addCommentsIntoPosts(ArrayCollection $posts)
    {
        $commentIdsMap = [];
        /** @var \Truonglv\GroupWall\Entity\Post $post */
        foreach ($posts as $post) {
            $commentIdsMap[$post->first_comment_id] = $post->post_id;

            $comments = array_slice($post->comment_cache, -5, 5, true);
            foreach ($comments as $commentId => $comment) {
                $commentIdsMap[$commentId] = $post->post_id;
            }
        }

        if (empty($commentIdsMap)) {
            return;
        }

        $comments = $this->finder('Truonglv\GroupWall:Comment')
            ->with('User')
            ->whereIds(array_keys($commentIdsMap))
            ->fetch();

        /** @var Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');

        $attachmentRepo->addAttachmentsToContent($comments, Listener::CONTENT_TYPE_COMMENT);

        $comments = $comments->groupBy('post_id');

        foreach ($posts as $post) {
            $postComments = isset($comments[$post->post_id])
                ? $this->em->getBasicCollection($comments[$post->post_id])
                : $this->em->getEmptyCollection();
            /** @var Comment $postComment */
            foreach ($postComments as $postComment) {
                $postComment->hydrateRelation('Post', $post);
            }

            $post->hydrateRelation('FirstComment', $postComments[$post->first_comment_id]);
            $postComments->offsetUnset($post->first_comment_id);

            $post->hydrateRelation('Comments', $postComments);
        }
    }

    public function getCategoryList(Group $group)
    {
        $defaultCategoryId = Listener::DEFAULT_POST_CATEGORY_ID;

        if (!GlobalStatic::getOption('enableWallCategories')) {
            return $this->em->getBasicCollection([
                $defaultCategoryId => $this->em->find('Truonglv\GroupWall:PostCategory', $defaultCategoryId)
            ]);
        }

        $finder = $this->finder('Truonglv\GroupWall:PostCategory');

        $finder->whereOr(
            ['category_id', '=', $defaultCategoryId],
            ['group_id', '=', $group->group_id]
        );

        return $finder->fetch();
    }
}
