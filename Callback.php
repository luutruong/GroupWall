<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall;

class Callback
{
    public static function getFormPost($html)
    {
        $html = str_replace('data-min-height="100"', 'data-min-height="50"', $html);

        return $html;
    }
}
