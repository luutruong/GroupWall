<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Like;

use XF\Mvc\Entity\Entity;
use XF\Like\AbstractHandler;

class Comment extends AbstractHandler
{
    public function likesCounted(Entity $entity)
    {
        if (!($entity instanceof \Truonglv\GroupWall\Entity\Comment)) {
            return false;
        }

        return $entity->message_state === 'visible';
    }

    public function getEntityWith()
    {
        return ['Post', 'Post.Group'];
    }

    public function getTemplateName()
    {
        return 'public:tl_group_wall_alert_comment_like';
    }
}
