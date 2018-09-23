<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use Truonglv\GroupWall\Listener;
use Truonglv\Groups\Entity\Group;

/**
 * Class Post
 * @package Truonglv\GroupWall\Entity
 *
 * @property int post_id
 * @property int group_id
 * @property int user_id
 * @property string username
 * @property int comment_count
 * @property int first_comment_id
 * @property int first_comment_date
 * @property int last_comment_id
 * @property int last_comment_date
 * @property int post_date
 * @property array comment_cache
 * @property int category_id
 *
 * @property Group Group
 * @property User User
 * @property Comment FirstComment
 * @property Comment LastComment
 */
class Post extends Entity
{
    public function canView(&$error = null)
    {
        /** @var Group|null $group */
        $group = $this->Group;
        if (!$group) {
            return false;
        }

        return $group->canViewContent($error);
    }

    public function canComment(&$error = null)
    {
        return true;
    }

    public function isIgnored()
    {
        return \XF::visitor()->isIgnoring($this->user_id);
    }

    public function rebuildFirstCommentInfo()
    {
        $firstComment = $this->db()->fetchRow('
			SELECT comment_id, comment_date, user_id, username, likes
			FROM xf_tl_group_wall_post_comment
			WHERE post_id = ?
			ORDER BY comment_date
			LIMIT 1
		', $this->post_id);
        if (!$firstComment) {
            return false;
        }

        $this->first_comment_id = $firstComment['comment_id'];
        $this->post_date = $firstComment['comment_date'];
        $this->user_id = $firstComment['user_id'];
        $this->username = $firstComment['username'] ?: '-';

        return true;
    }

    public function rebuildLastCommentInfo()
    {
        $lastComment = $this->db()->fetchRow("
			SELECT comment_id, comment_date, user_id, username
			FROM xf_tl_group_wall_post_comment
			WHERE post_id = ?
				AND message_state = 'visible'
			ORDER BY comment_date DESC
			LIMIT 1
		", $this->post_id);
        if (!$lastComment) {
            return false;
        }

        $this->last_comment_id = $lastComment['comment_id'];
        $this->last_comment_date = $lastComment['comment_date'];

        return true;
    }

    public function onCommentRemoved(Comment $comment)
    {
        $this->comment_count--;

        if ($comment->comment_id == $this->first_comment_id) {
            $this->rebuildFirstCommentInfo();
        }

        if ($comment->comment_id == $this->last_comment_id) {
            $this->rebuildLastCommentInfo();
        }

        $comments = $this->comment_cache;
        if (isset($comments[$comment->comment_id])) {
            unset($comments[$comment->comment_id]);
            $this->comment_cache = $comments;
        }

        unset($this->_getterCache['comment_ids']);
    }

    public function onCommentAdded(Comment $comment)
    {
        if (!$this->first_comment_id) {
            $this->first_comment_id = $comment->comment_id;
        } else {
            $this->comment_count++;
        }

        if ($comment->comment_date >= $this->last_comment_date) {
            $this->last_comment_date = $comment->comment_date;
            $this->last_comment_id = $comment->comment_id;
        }

        $comments = $this->comment_cache;
        $comments[$comment->comment_id] = [
            'message_state' => $comment->message_state,
            'comment_date' => $comment->comment_date,
            'position' => $comment->position
        ];

        $this->comment_cache = $comments;

        unset($this->_getterCache['comment_ids']);
    }

    /**
     * @return Comment
     */
    public function getNewComment()
    {
        /** @var Comment $comment */
        $comment = $this->em()->create('Truonglv\GroupWall:Comment');
        $comment->post_id = $this->_getDeferredValue(function () {
            return $this->post_id;
        }, 'save');

        return $comment;
    }

    /**
     * @return Group|null
     */
    public function getGroup()
    {
        return $this->Group;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tl_group_wall_post';
        $structure->primaryKey = 'post_id';
        $structure->shortName = 'Truonglv\GroupWall:Post';

        $structure->columns = [
            'post_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'group_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'comment_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
            'first_comment_id' => ['type' => self::UINT, 'default' => 0],
            'first_comment_date' => ['type' => self::UINT, 'default' => 0],
            'last_comment_id' => ['type' => self::UINT, 'default' => 0],
            'last_comment_date' => ['type' => self::UINT, 'default' => 0],

            'post_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'comment_cache' => ['type' => self::JSON_ARRAY, 'default' => []],

            'category_id' => ['type' => self::UINT, 'default' => Listener::DEFAULT_POST_CATEGORY_ID]
        ];

        $structure->relations = [
            'Group' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\Groups:Group',
                'conditions' => 'group_id',
                'primary' => true
            ],
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ],
            'FirstComment' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\GroupWall:Comment',
                'conditions' => [
                    ['comment_id', '=', '$first_comment_id']
                ]
            ],
            'LastComment' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\GroupWall:Comment',
                'conditions' => [
                    ['comment_id', '=', '$last_comment_id']
                ]
            ],
            'Comments' => [
                'type' => self::TO_MANY,
                'entity' => 'Truonglv\GroupWall:Comment',
                'conditions' => [
                    ['post_id', '=', '$post_id']
                ],
                'order' => 'comment_date'
            ]
        ];

        return $structure;
    }

    protected function _postDelete()
    {
        $db = $this->db();
        $commentIds = $db->fetchAllColumn('
            SELECT comment_id
            FROM xf_tl_group_wall_post_comment
            WHERE post_id = ?
        ', $this->post_id);

        $db->delete('xf_tl_group_wall_post_comment', 'post_id = ?', $this->post_id);
        if ($commentIds) {
            /** @var \XF\Repository\UserAlert $userAlertRepo */
            $userAlertRepo = $this->repository('XF:UserAlert');
            $userAlertRepo->fastDeleteAlertsForContent(Listener::CONTENT_TYPE_COMMENT, $commentIds);

            /** @var \XF\Repository\Attachment $attachRepo */
            $attachRepo = $this->repository('XF:Attachment');
            $attachRepo->fastDeleteContentAttachments(Listener::CONTENT_TYPE_COMMENT, $commentIds);
        }
    }
}
