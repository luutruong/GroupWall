<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Groups\GlobalStatic;

class Comment extends Finder
{
    public function onPage($page, $perPage = null)
    {
        if ($perPage === null) {
            $perPage = GlobalStatic::getOption('commentsPerPage');
        }

        $start = ($page - 1) * $perPage;
        $end = $start + $perPage;

        $this->where('position', '>=', $start);
        $this->where('position', '<', $end);

        return $this;
    }

    public function forView()
    {
        $this->with('User');

        return $this;
    }
}
