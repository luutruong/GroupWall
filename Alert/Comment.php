<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Alert;

use XF\Alert\AbstractHandler;

class Comment extends AbstractHandler
{
    public function getEntityWith()
    {
        return ['Post', 'Post.Group'];
    }

    public function getTemplateName($action)
    {
        return 'public:tl_group_wall_alert_comment_' . $action;
    }
}
