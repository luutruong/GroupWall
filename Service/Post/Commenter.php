<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Service\Post;

use XF\Entity\User;
use XF\Service\AbstractService;
use Truonglv\GroupWall\Entity\Post;
use Truonglv\GroupWall\Entity\Comment;
use XF\Service\ValidateAndSavableTrait;
use Truonglv\GroupWall\Service\Comment\Preparer;

class Commenter extends AbstractService
{
    use ValidateAndSavableTrait;

    protected $post;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Comment
     */
    protected $comment;

    /**
     * @var Preparer
     */
    protected $commentPreparer;

    public function __construct(\XF\App $app, Post $post)
    {
        parent::__construct($app);

        $this->post = $post;
        $this->setUser(\XF::visitor());

        $this->setupDefaults();
    }

    public function setAttachmentHash($attachmentHash)
    {
        $this->commentPreparer->setAttachmentHash($attachmentHash);
    }

    public function setMessage($message, $format = true)
    {
        return $this->commentPreparer->setMessage($message, $format);
    }

    protected function finalSetup()
    {
        $this->comment->user_id = $this->user->user_id;
        $this->comment->username = $this->user->username;
        $this->comment->comment_date = time();
    }

    protected function _validate()
    {
        $this->finalSetup();
        $comment = $this->comment;
        $comment->preSave();

        return $comment->getErrors();
    }

    protected function _save()
    {
        $db = $this->db();
        $comment = $this->comment;

        $db->beginTransaction();

        $postLatest = $this->db()->fetchRow('
			SELECT *
			FROM xf_tl_group_wall_post
			WHERE post_id = ?
			FOR UPDATE
		', $this->post->post_id);

        $this->setPostPosition($postLatest);

        $comment->save(true, false);

        $this->commentPreparer->afterInsert();

        $db->commit();

        return $comment;
    }

    protected function setPostPosition(array $postInfo)
    {
        $comment = $this->comment;

        if ($comment->comment_date < $postInfo['last_comment_date']) {
            throw new \LogicException('Replier can only add posts at the end of a thread');
        }

        if ($comment->message_state == 'visible') {
            $position = $postInfo['comment_count'] + 1;
        } else {
            $position = $postInfo['comment_count'];
        }

        $comment->set('position', $position, ['forceSet' => true]);
    }

    protected function setUser(User $user)
    {
        if (!$user->user_id) {
            throw new \LogicException('User must be saved');
        }

        $this->user = $user;
    }

    protected function setupDefaults()
    {
        /** @var Comment $comment */
        $comment = $this->em()->create('Truonglv\GroupWall:Comment');
        $comment->post_id = $this->post->post_id;

        $this->comment = $comment;

        /** @var Preparer $preparer */
        $preparer = $this->service('Truonglv\GroupWall:Comment\Preparer', $comment);
        $this->commentPreparer = $preparer;
    }
}
