<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use Truonglv\GroupWall\Listener;
use Truonglv\Groups\Entity\Group;

/**
 * Class PostCategory
 * @package Truonglv\GroupWall\Entity
 *
 * @property int category_id
 * @property string category_title
 * @property int group_id
 *
 * @property Group|null Group
 */
class PostCategory extends Entity
{
    public function canEdit(&$error = null)
    {
        return $this->category_id !== Listener::DEFAULT_POST_CATEGORY_ID;
    }

    public function getTitle()
    {
        if ($this->category_id === Listener::DEFAULT_POST_CATEGORY_ID) {
            return \XF::phrase('tl_group_wall_default_category_title');
        }

        return $this->category_title;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tl_group_wall_category';
        $structure->primaryKey = 'category_id';
        $structure->shortName = 'Truonglv\GroupWall:PostCategory';

        $structure->columns = [
            'category_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'category_title' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
            'group_id' => ['type' => self::UINT, 'required' => true]
        ];

        $structure->relations = [
            'Group' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\Groups:Group',
                'conditions' => 'group_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    protected function _preDelete()
    {
        if ($this->category_id == Listener::DEFAULT_POST_CATEGORY_ID) {
            throw new \LogicException('Cannot delete default category.');
        }
    }

    protected function _postDelete()
    {
        $this->db()
            ->update(
                'xf_tl_group_wall_post',
                ['category_id' => Listener::DEFAULT_POST_CATEGORY_ID],
                'category_id = ?',
                $this->category_id
            );
    }
}
