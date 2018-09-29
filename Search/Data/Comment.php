<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\GroupWall\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use Truonglv\GroupWall\Listener;
use XF\Search\Data\AbstractData;
use XF\Search\MetadataStructure;

class Comment extends AbstractData
{
    public function getEntityWith($forView = false)
    {
        $with = ['Post', 'Post.Group'];
        if ($forView) {
            $with[] = 'User';
        }

        return $with;
    }

    public function getTemplateName()
    {
        return 'public:tl_group_wall_search_result_comment';
    }

    public function getResultDate(Entity $entity)
    {
        if (!($entity instanceof \Truonglv\GroupWall\Entity\Comment)) {
            return 0;
        }

        return $entity->comment_date;
    }

    public function getIndexData(Entity $entity)
    {
        if (!($entity instanceof \Truonglv\GroupWall\Entity\Comment)) {
            return null;
        }

        if (!$entity->isFirstComment()) {
            return null;
        }

        $index = IndexRecord::create(Listener::CONTENT_TYPE_COMMENT, $entity->comment_id, [
            'message' => $entity->message_,
            'date' => $entity->comment_date,
            'user_id' => $entity->user_id,
            'discussion_id' => $entity->post_id,
            'metadata' => $this->getMetaData($entity)
        ]);

        if (!$entity->message_state !== 'visible') {
            $index->setHidden();
        }

        return $index;
    }

    public function getTemplateData(Entity $entity, array $options = [])
    {
        return [
            'comment' => $entity,
            'options' => $options
        ];
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('group', MetadataStructure::INT);
        $structure->addField('post', MetadataStructure::INT);
    }

    protected function getMetaData(\Truonglv\GroupWall\Entity\Comment $comment)
    {
        $metadata = [
            'post' => $comment->post_id
        ];

        $group = $comment->getGroup();
        if ($group) {
            $metadata['group'] = $group->group_id;
        }

        return $metadata;
    }
}
