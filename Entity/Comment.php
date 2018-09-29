<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Entity;

use XF\Entity\User;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use XF\Entity\LikedContent;
use XF\Mvc\Entity\Structure;
use Truonglv\GroupWall\Listener;
use XF\Entity\QuotableInterface;
use Truonglv\Groups\GlobalStatic;
use XF\BbCode\RenderableContentInterface;

/**
 * Class Comment
 * @package Truonglv\GroupWall\Entity
 *
 * @property int comment_id
 * @property int post_id
 * @property int user_id
 * @property string username
 * @property string message
 * @property array embed_metadata
 * @property int position
 * @property int comment_date
 * @property string message_state
 * @property int likes
 * @property array like_users
 * @property int attach_count
 * @property int ip_id
 *
 * @property Post Post
 * @property User User
 * @property Attachment[] Attachments
 * @property LikedContent[] Likes
 */
class Comment extends Entity implements RenderableContentInterface, QuotableInterface
{
    public function getBbCodeRenderOptions($context, $type)
    {
        $canViewAttachments = false;

        if ($this->getGroup() && $this->getGroup()->canViewContent()) {
            $canViewAttachments = true;
        }

        return [
            'entity' => $this,
            'user' => $this->User,
            'attachments' => $this->attach_count ? $this->Attachments: [],
            'viewAttachments' => $canViewAttachments
        ];
    }

    public function getQuoteWrapper($inner)
    {
        /** @var \XF\Entity\User|null $user */
        $user = $this->User;

        return '[QUOTE="'
            . ($user ? $user->username : $this->username)
            . ', ' . Listener::CONTENT_TYPE_COMMENT . ' ' . $this->comment_id
            . ($user ? ", member: $this->user_id" : '')
            . '"]'
            . $inner
            . "[/QUOTE]\n";
    }

    public function isAttachmentEmbedded($attachmentId)
    {
        if (!$this->embed_metadata) {
            return false;
        }

        if ($attachmentId instanceof \XF\Entity\Attachment) {
            $attachmentId = $attachmentId->attachment_id;
        }

        return isset($this->embed_metadata['attachments'][$attachmentId]);
    }

    public function canView(&$error = null)
    {
        return $this->Post->canView($error);
    }

    public function canEdit(&$error = null)
    {
        $group = $this->getGroup();
        if (!$group) {
            return false;
        }

        $member = $group->getMember();
        $visitor = \XF::visitor();

        if (!$member) {
            return false;
        }

        if ($member->hasRole(GlobalStatic::MEMBER_ROLE_KEY_COMMENT, 'editAny')) {
            return true;
        }

        return ($visitor->user_id == $this->user_id
            && $member->hasRole(GlobalStatic::MEMBER_ROLE_KEY_COMMENT, 'editOwn')
        );
    }

    public function canLike(&$error = null)
    {
        return \XF::visitor()->user_id != $this->user_id;
    }

    public function canReport(&$error = null)
    {
        return false;
    }

    public function canDelete($type = 'soft', &$error = null)
    {
        $group = $this->getGroup();
        if (!$group) {
            return false;
        }

        $member = $group->getMember();
        $visitor = \XF::visitor();

        if (!$member) {
            return false;
        }

        if ($member->hasRole(GlobalStatic::MEMBER_ROLE_KEY_COMMENT, 'deleteAny')) {
            return true;
        }

        return ($visitor->user_id == $this->user_id
            && $member->hasRole(GlobalStatic::MEMBER_ROLE_KEY_COMMENT, 'deleteOwn')
        );
    }

    public function isLiked()
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return false;
        }

        return empty($this->Likes[$visitor->user_id]) ? false : true;
    }

    public function isIgnored()
    {
        return \XF::visitor()->isIgnoring($this->user_id);
    }

    public function isFirstComment()
    {
        return $this->position === 0;
    }

    public function getGroup()
    {
        /** @var Post|null $post */
        $post = $this->Post;
        if (!$post) {
            return null;
        }

        return $post->getGroup();
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tl_group_wall_post_comment';
        $structure->primaryKey = 'comment_id';
        $structure->shortName = 'Truonglv\GroupWall:Comment';
        $structure->contentType = Listener::CONTENT_TYPE_COMMENT;

        $structure->columns = [
            'comment_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'post_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'username' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'message' => ['type' => self::STR, 'required' => true],
            'embed_metadata' => ['type' => self::SERIALIZED_ARRAY, 'default' => []],
            'position' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
            'message_state' => ['type' => self::STR, 'default' => 'visible', 'allowedValues' => ['visible', 'moderated', 'deleted']],
            'likes' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
            'like_users' => ['type' => self::SERIALIZED_ARRAY, 'default' => []],
            'attach_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0],
            'comment_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'ip_id' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->behaviors = [
            'Truonglv\Groups:Activity' => [
                'groupIdField' => function ($entity) {
                    if (!($entity instanceof Comment)) {
                        return 0;
                    }

                    return $entity->Post->group_id;
                },

                'checkForUpdates' => ['message_state']
            ],
            'XF:Indexable' => [
                'checkForUpdates' => ['message', 'user_id', 'post_id', 'comment_date', 'message_state']
            ]
        ];

        $structure->relations = [
            'Post' => [
                'type' => self::TO_ONE,
                'entity' => 'Truonglv\GroupWall:Post',
                'conditions' => 'post_id',
                'primary' => true
            ],
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Attachments' => [
                'type' => self::TO_MANY,
                'entity' => 'XF:Attachment',
                'conditions' => [
                    ['content_type', '=', Listener::CONTENT_TYPE_COMMENT],
                    ['content_id', '=', '$comment_id']
                ],
                'with' => 'Data',
                'order' => 'attach_date'
            ],
            'Likes' => [
                'type' => self::TO_MANY,
                'entity' => 'XF:LikedContent',
                'conditions' => [
                    ['content_type', '=', Listener::CONTENT_TYPE_COMMENT],
                    ['content_id', '=', '$comment_id']
                ],
                'key' => 'like_user_id',
                'order' => 'like_date'
            ]
        ];

        return $structure;
    }

    protected function updatePostRecord()
    {
        /** @var Post|null $post */
        $post = $this->Post;
        if (!$post || !$post->exists()) {
            return;
        }

        $visibleChanges = $this->isStateChanged('message_state', 'visible');
        if ($visibleChanges === 'enter') {
            $post->onCommentAdded($this);
        } elseif ($visibleChanges === 'leave') {
            $post->onCommentRemoved($this);
        }

        $post->saveIfChanged();
    }

    protected function _postSave()
    {
        $this->updatePostRecord();
    }

    protected function _preDelete()
    {
        $expectedPosition = $this->db()->fetchOne(
            'SELECT position FROM xf_tl_group_wall_post_comment WHERE comment_id = ?',
            $this->comment_id
        );

        if ($expectedPosition != $this->position) {
            $this->setAsSaved('position', $expectedPosition);
        }
    }

    protected function _postDelete()
    {
        $this->Post->onCommentRemoved($this);
        $this->Post->saveIfChanged();

        $db = $this->db();

        $db->query('
			UPDATE xf_tl_group_wall_post_comment
			SET position = IF(position > 0, position - 1, 0)
			WHERE post_id = ?
				AND position >= ?
				AND comment_id <> ?
		', [$this->post_id, $this->position, $this->comment_id]);

        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = $this->repository('XF:UserAlert');
        $userAlertRepo->fastDeleteAlertsForContent(Listener::CONTENT_TYPE_COMMENT, $this->comment_id);

        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $attachRepo->fastDeleteContentAttachments(Listener::CONTENT_TYPE_COMMENT, $this->comment_id);
    }
}
