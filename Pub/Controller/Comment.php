<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\ControllerPlugin\Like;
use XF\Repository\Attachment;
use Truonglv\GroupWall\Listener;
use Truonglv\Groups\GlobalStatic;
use XF\Pub\Controller\AbstractController;
use Truonglv\GroupWall\Service\Comment\Editor;
use Truonglv\Groups\ControllerPlugin\Assistant;

class Comment extends AbstractController
{
    public function actionIndex(ParameterBag $paramBag)
    {
        $comment = $this->assertCommentViewable($paramBag->comment_id);

        $params = [];

        $commentsPerPage = GlobalStatic::getOption('commentsPerPage');
        $page = ceil($comment->position / $commentsPerPage);

        if ($page > 1) {
            $params['page'] = $page;
        }

        return $this->redirectPermanently(
            $this->buildLink('groups/wall-posts', $comment->Post, $params)
            . '#js-comment-' . $comment->comment_id
        );
    }

    public function actionEdit(ParameterBag $paramBag)
    {
        $comment = $this->assertCommentViewable($paramBag->comment_id);
        if (!$comment->canEdit($error)) {
            return $this->noPermission($error);
        }

        /** @var Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');

        if ($this->isPost()) {
            /** @var Editor $editor */
            $editor = $this->service('Truonglv\GroupWall:Comment\Editor', $comment);
            /** @var \XF\ControllerPlugin\Editor $editorPlugin */
            $editorPlugin = $this->plugin('XF:Editor');

            $editor->setMessage($editorPlugin->fromInput('message'));
            if ($comment->getGroup()->canUploadAndManageAttachments()) {
                $editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
            }

            if (!$editor->validate($errors)) {
                return $this->error($errors);
            }

            /** @var \Truonglv\GroupWall\Entity\Comment $comment */
            $comment = $editor->save();

            if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool')) {
                $comments = [
                    $comment->comment_id => $comment
                ];
                $attachRepo->addAttachmentsToContent($comments, Listener::CONTENT_TYPE_COMMENT);

                if ($comment->isFirstComment()) {
                    $reply = $this->view('Truonglv\GroupWall:Post\Edit', 'tl_group_wall_post', [
                        'post' => $comment->Post
                    ]);
                } else {
                    $reply = $this->view('Truonglv\GroupWall:Comment\Edit', 'tl_group_wall_comment', [
                        'comment' => $comment
                    ]);
                }

                $reply->setJsonParams([
                    'message' => \XF::phrase('your_changes_have_been_saved')
                ]);

                return $reply;
            } else {
                return $this->redirect($this->buildLink('groups/wall-posts', $comment->Post));
            }
        }

        $attachmentHash = md5(
            $this->app()->config('globalSalt')
            . $comment->comment_id
            . \XF::visitor()->user_id
        );

        $attachmentData = null;

        if ($comment->getGroup()->canUploadAndManageAttachments()) {
            $attachmentData = $attachRepo->getEditorData(Listener::CONTENT_TYPE_COMMENT, $comment, $attachmentHash, [
                'comment_id' => $comment->comment_id
            ]);
        }

        return $this->view('Truonglv\GroupWall:Comment\Edit', 'tl_group_wall_comment_edit', [
            'comment' => $comment,
            'group' => $comment->getGroup(),
            'quickEdit' => $this->filter('_xfWithData', 'bool'),
            'attachmentData' => $attachmentData
        ]);
    }

    public function actionDelete(ParameterBag $paramBag)
    {
        $comment = $this->assertCommentViewable($paramBag->comment_id);
        if (!$comment->canDelete('soft', $error)) {
            return $this->noPermission($error);
        }

        $redirectUrl = $comment->isFirstComment()
            ? $this->buildLink('groups', $comment->getGroup())
            : $this->buildLink('groups/wall-posts', $comment->Post);

        /** @var User|null $user */
        $user = $comment->User;

        $params = [
            'formAction' => $this->buildLink('groups/wall-comments/delete', $comment),
            'deleteNote' => $comment->isFirstComment() ? \XF::phrase('tl_group_wall_delete_first_comment_warning') : '',
            'message' => \XF::phrase('tl_group_wall_delete_comment_created_by_x', [
                'user' => $user ? $user->username : $comment->username
            ])
        ];

        /** @var Assistant $assistantPlugin */
        $assistantPlugin = $this->plugin('Truonglv\Groups:Assistant');

        return $assistantPlugin->simpleDelete(
            $comment,
            $params,
            $redirectUrl,
            function (\Truonglv\GroupWall\Entity\Comment $comment) {
                if ($comment->isFirstComment()) {
                    $comment->Post->delete();
                } else {
                    $comment->delete();
                }
            }
        );
    }

    public function actionLike(ParameterBag $paramBag)
    {
        $comment = $this->assertCommentViewable($paramBag->comment_id);
        if (!$comment->canLike($error)) {
            return $this->noPermission($error);
        }

        /** @var Like $likePlugin */
        $likePlugin = $this->plugin('XF:Like');

        return $likePlugin->actionToggleLike(
            $comment,
            $this->buildLink('groups/wall-comments/like', $comment),
            $this->buildLink('groups/wall-comments', $comment),
            $this->buildLink('groups/wall-comments/likes', $comment)
        );
    }

    public function actionLikes(ParameterBag $paramBag)
    {
        $comment = $this->assertCommentViewable($paramBag->comment_id);

        $title = \XF::phrase('members_who_liked_message_x', ['position' => $comment->position + 1]);

        /** @var Like $likePlugin */
        $likePlugin = $this->plugin('XF:Like');

        return $likePlugin->actionLikes(
            $comment,
            ['groups/wall-comments/likes', $comment],
            $title
        );
    }

    /**
     * @param int $commentId
     * @param array $extraWith
     * @return \Truonglv\GroupWall\Entity\Comment
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCommentViewable($commentId, array $extraWith = [])
    {
        $extraWith[] = 'Post';
        $extraWith[] = 'Post.Group';

        /** @var \Truonglv\GroupWall\Entity\Comment $comment */
        $comment = $this->assertRecordExists('Truonglv\GroupWall:Comment', $commentId, $extraWith);
        if (!$comment->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $comment;
    }
}
