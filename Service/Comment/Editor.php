<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Service\Comment;

use XF\Service\AbstractService;
use Truonglv\GroupWall\Entity\Comment;
use XF\Service\ValidateAndSavableTrait;

class Editor extends AbstractService
{
    use ValidateAndSavableTrait;

    protected $comment;

    /**
     * @var Preparer
     */
    protected $commentPreparer;

    public function __construct(\XF\App $app, Comment $comment)
    {
        parent::__construct($app);

        $this->comment = $comment;

        /** @var Preparer $preparer */
        $preparer = $this->service('Truonglv\GroupWall:Comment\Preparer', $comment);
        $this->commentPreparer = $preparer;
    }

    public function setAttachmentHash($attachmentHash)
    {
        $this->commentPreparer->setAttachmentHash($attachmentHash);
    }

    public function setMessage($message, $format = true)
    {
        return $this->commentPreparer->setMessage($message, $format);
    }

    protected function _validate()
    {
        $comment = $this->comment;
        $comment->preSave();

        return $comment->getErrors();
    }

    protected function _save()
    {
        $db = $this->db();
        $comment = $this->comment;

        $db->beginTransaction();

        $comment->save(true, false);

        $this->commentPreparer->afterUpdate();

        $db->commit();

        return $comment;
    }
}
