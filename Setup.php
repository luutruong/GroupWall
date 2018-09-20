<?php

namespace Truonglv\GroupWall;

use XF\Db\Schema\Create;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $sm = $this->schemaManager();
        foreach ($this->getTables() as $tableName => $callback) {
            $sm->createTable($tableName, $callback);
        }
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        foreach (array_keys($this->getTables()) as $tableName) {
            $sm->dropTable($tableName);
        }
    }

    private function getTables()
    {
        $tables = [];

        $tables += $this->getTables1();

        return $tables;
    }

    private function getTables1()
    {
        $tables = [];

        $tables['xf_tl_group_wall_post'] = function (Create $table) {
            $table->addColumn('post_id', 'int')->unsigned()->autoIncrement()->primaryKey();

            $table->addColumn('group_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('username', 'varchar', 50);

            $table->addColumn('comment_count', 'int')->unsigned()->setDefault(0);

            $table->addColumn('first_comment_id', 'int')->unsigned();
            $table->addColumn('first_comment_date', 'int')->unsigned();

            $table->addColumn('last_comment_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('last_comment_date', 'int')->unsigned()->setDefault(0);

            $table->addColumn('post_date', 'int')->unsigned();
            $table->addColumn('comment_cache', 'mediumblob');

            $table->addKey(['group_id', 'last_comment_date']);
            $table->addKey('last_comment_date');
            $table->addKey('user_id');
        };

        $tables['xf_tl_group_wall_post_comment'] =  function (Create $table) {
            $table->addColumn('comment_id', 'int')->unsigned()->autoIncrement()->primaryKey();
            $table->addColumn('post_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('username', 'varchar', 50);

            $table->addColumn('message', 'mediumtext');
            $table->addColumn('embed_metadata', 'mediumblob');

            $table->addColumn('position', 'int')->unsigned()->setDefault(0);
            $table->addColumn('message_state', 'enum', ['visible', 'moderated', 'deleted'])
                ->setDefault('visible');

            $table->addColumn('likes', 'int')->unsigned()->setDefault(0);
            $table->addColumn('like_users', 'mediumblob');

            $table->addColumn('attach_count', 'int')->unsigned()->setDefault(0);

            $table->addColumn('comment_date', 'int')->unsigned();
            $table->addColumn('ip_id', 'int')->unsigned()->setDefault(0);

            $table->addKey(['post_id', 'position']);
            $table->addKey('user_id');
        };

        return $tables;
    }
}
