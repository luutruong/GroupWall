<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Service\Comment;

use XF\Repository\User;
use XF\Service\AbstractService;
use Truonglv\GroupWall\Entity\Comment;

class Preparer extends AbstractService
{
    protected $comment;
    protected $attachmentHash;
    protected $logIp = true;
    protected $quotedPosts = [];
    protected $mentionedUsers = [];

    public function __construct(\XF\App $app, Comment $comment)
    {
        parent::__construct($app);

        $this->comment = $comment;
    }

    public function setAttachmentHash($attachmentHash)
    {
        $this->attachmentHash = $attachmentHash;
    }

    public function logIp($logIp)
    {
        $this->logIp = $logIp;
    }

    public function getQuotedPosts()
    {
        return $this->quotedPosts;
    }

    public function getQuotedUserIds()
    {
        if (!$this->quotedPosts) {
            return [];
        }

        $commentIds = array_keys($this->quotedPosts);
        $quotedUserIds = [];

        $db = $this->db();
        $commentUserMap = $db->fetchPairs('
			SELECT comment_id, user_id
			FROM xf_tl_group_post_comment
			WHERE comment_id IN (' . $db->quote($commentIds) . ')
		');
        foreach ($commentUserMap as $commentId => $userId) {
            if (!isset($this->quotedPosts[$commentId]) || !$userId) {
                continue;
            }

            $quote = $this->quotedPosts[$commentId];
            if (!isset($quote['member']) || $quote['member'] == $userId) {
                $quotedUserIds[] = $userId;
            }
        }

        return $quotedUserIds;
    }

    public function getMentionedUsers($limitPermissions = true)
    {
        if ($limitPermissions) {
            /** @var User $userRepo */
            $userRepo = $this->repository('XF:User');
            /** @var \XF\Entity\User|null $user */
            $user = $this->comment->User;
            if (!$user) {
                $user = $userRepo->getGuestUser();
            }

            return $user->getAllowedUserMentions($this->mentionedUsers);
        } else {
            return $this->mentionedUsers;
        }
    }

    public function getMentionedUserIds($limitPermissions = true)
    {
        return array_keys($this->getMentionedUsers($limitPermissions));
    }

    public function setMessage($message, $format = true)
    {
        $preparer = $this->getMessagePreparer($format);

        $this->comment->message = $preparer->prepare($message, true);
        $this->comment->embed_metadata = $preparer->getEmbedMetadata();

        $this->quotedPosts = $preparer->getQuotesKeyed('tl_group_wall_comment');
        $this->mentionedUsers = $preparer->getMentionedUsers();

        return $preparer->pushEntityErrorIfInvalid($this->comment);
    }

    public function afterInsert()
    {
        if ($this->attachmentHash) {
            $this->associateAttachments($this->attachmentHash);
        }

        if ($this->logIp) {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog($ip);
        }
    }

    public function afterUpdate()
    {
        if ($this->attachmentHash) {
            $this->associateAttachments($this->attachmentHash);
        }
    }

    protected function associateAttachments($hash)
    {
        $comment = $this->comment;

        /** @var \XF\Service\Attachment\Preparer $preparer */
        $preparer = $this->service('XF:Attachment\Preparer');
        $associated = $preparer->associateAttachmentsWithContent(
            $hash,
            'tl_group_wall_comment',
            $comment->comment_id
        );

        if ($associated) {
            $comment->fastUpdate('attach_count', $comment->attach_count + $associated);
        }
    }

    /**
     * @param bool $format
     *
     * @return \XF\Service\Message\Preparer
     */
    protected function getMessagePreparer($format = true)
    {
        /** @var \XF\Service\Message\Preparer $preparer */
        $preparer = $this->service('XF:Message\Preparer', 'tl_group_wall_comment', $this->comment);
        if (!$format) {
            $preparer->disableAllFilters();
        }

        return $preparer;
    }

    protected function writeIpLog($ip)
    {
        $comment = $this->comment;

        /** @var \XF\Repository\IP $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipEnt = $ipRepo->logIp(
            $comment->user_id,
            $ip,
            'tl_group_wall_comment',
            $comment->comment_id
        );

        if ($ipEnt) {
            $comment->fastUpdate('ip_id', $ipEnt->ip_id);
        }
    }
}
