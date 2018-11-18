<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Service\Post;

use XF\Entity\User;
use XF\Service\AbstractService;
use Truonglv\Groups\Entity\Group;
use Truonglv\GroupWall\Entity\Post;
use Truonglv\GroupWall\Entity\Comment;
use XF\Service\ValidateAndSavableTrait;
use Truonglv\GroupWall\Entity\PostCategory;
use Truonglv\GroupWall\Service\Comment\Preparer;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    protected $group;

    protected $postCategory;

    /**
     * @var Post
     */
    protected $post;

    /**
     * @var Comment
     */
    protected $comment;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Preparer
     */
    protected $commentPreparer;

    public function __construct(\XF\App $app, Group $group, PostCategory $category)
    {
        parent::__construct($app);

        $this->group = $group;
        $this->postCategory = $category;

        $this->setUser(\XF::visitor());

        $this->setupDefaults();
    }

    /**
     * @return Post
     */
    public function getPost()
    {
        return $this->post;
    }

    public function setAttachmentHash($attachmentHash)
    {
        $this->commentPreparer->setAttachmentHash($attachmentHash);
    }

    public function setMessage($message, $format = true)
    {
        return $this->commentPreparer->setMessage($message, $format);
    }

    public function setPostState($state)
    {
        switch ($state) {
            case 'visible':
            case 'moderated':
            case 'deleted':
                $this->comment->message_state = $state;
                break;
            default:
                throw new \InvalidArgumentException('Unknown post state (' . $state . ')');
        }
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
        /** @var Post $post */
        $post = $this->em()->create('Truonglv\GroupWall:Post');
        $post->group_id = $this->group->group_id;
        $post->category_id = $this->postCategory->category_id;

        $comment = $post->getNewComment();
        $post->addCascadedSave($comment);

        $this->post = $post;
        $this->comment = $comment;

        /** @var Preparer $commentPreparer */
        $commentPreparer = $this->service('Truonglv\GroupWall:Comment\Preparer', $comment);
        $this->commentPreparer = $commentPreparer;
    }

    protected function finalSetup()
    {
        $post = $this->post;
        $comment = $this->comment;

        $time = time();

        $post->user_id = $this->user->user_id;
        $post->username = $this->user->username;
        $post->post_date = $time;
        $post->first_comment_date = $time;
        $post->last_comment_date = $time;

        $comment->user_id = $this->user->user_id;
        $comment->username = $this->user->username;
        $comment->comment_date = $time;
        $comment->position = 0;
    }

    protected function _validate()
    {
        $this->finalSetup();
        $post = $this->post;
        $post->preSave();

        return $post->getErrors();
    }

    protected function _save()
    {
        $db = $this->db();
        $post = $this->post;

        $db->beginTransaction();

        $post->save(true, false);

        $post->fastUpdate([
            'first_comment_id' => $this->comment->comment_id,
            'last_comment_id' => $this->comment->comment_id
        ]);

        $this->commentPreparer->afterInsert();

        $db->commit();

        return $post;
    }
}
