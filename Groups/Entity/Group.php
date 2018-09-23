<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Groups\Entity;

use Truonglv\Groups\GlobalStatic;
use Truonglv\Groups\Entity\Member;

class Group extends XFCP_Group
{
    public function canManagePostCategory(&$error = null)
    {
        if ($this->group_state !== 'visible') {
            return false;
        }

        if (GlobalStatic::hasPermission('editGroupAny')) {
            return true;
        }

        /** @var Member|null $member */
        $member = $this->Member;
        if (!$member) {
            return false;
        }

        return $member->isOwner();
    }
}
