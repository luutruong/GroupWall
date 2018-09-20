<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Attachment;

use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use Truonglv\Groups\Entity\Group;
use XF\Attachment\AbstractHandler;
use Truonglv\GroupWall\Entity\Post;

class Comment extends AbstractHandler
{
    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        if (!($container instanceof \Truonglv\GroupWall\Entity\Comment)) {
            return false;
        }

        return $container->canView($error);
    }

    public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
    {
        if (!($container instanceof \Truonglv\GroupWall\Entity\Comment)) {
            return;
        }

        $container->attach_count--;
        $container->save();
    }

    public function canManageAttachments(array $context, &$error = null)
    {
        $em = \XF::em();

        if (!empty($context['comment_id'])) {
            /** @var \Truonglv\GroupWall\Entity\Comment|null $comment */
            $comment = $em->find('Truonglv\GroupWall:Comment', $context['comment_id']);
            if (!$comment || !$comment->canEdit()) {
                return false;
            }

            $group = $comment->getGroup();
        } elseif (!empty($context['post_id'])) {
            /** @var Post|null $post */
            $post = $em->find('Truonglv\GroupWall:Post', $context['post_id']);
            if (!$post || !$post->canView()) {
                return false;
            }

            $group = $post->getGroup();
        } elseif (!empty($context['group_id'])) {
            /** @var Group|null $group */
            $group = $em->find('Truonglv\Groups:Group', $context['group_id']);
        } else {
            throw new \InvalidArgumentException('Unknown context data');
        }

        if (!$group || !$group->canView($error)) {
            return false;
        }

        return $group->canUploadAndManageAttachments($error);
    }

    public function getContainerIdFromContext(array $context)
    {
        return isset($context['comment_id']) ? intval($context['comment_id']) : null;
    }

    public function getConstraints(array $context)
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = \XF::repository('XF:Attachment');

        return $attachRepo->getDefaultAttachmentConstraints();
    }

    public function getContainerLink(Entity $container, array $extraParams = [])
    {
        return $container
            ->app()
            ->router('public')
            ->buildLink('groups/wall/posts', $container, $extraParams);
    }

    public function getContext(Entity $entity = null, array $extraContext = [])
    {
        if ($entity instanceof \Truonglv\GroupWall\Entity\Comment) {
            $extraContext['comment_id'] = $entity->comment_id;
        } elseif ($entity instanceof Post) {
            $extraContext['post_id'] = $entity->post_id;
        } elseif ($entity instanceof Group) {
            $extraContext['group_id'] = $entity->group_id;
        }

        return $extraContext;
    }
}
