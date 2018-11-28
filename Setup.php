<?php

namespace Truonglv\GroupWall;

use Truonglv\GroupWall\DevHelper\SetupTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

class Setup extends AbstractSetup
{
    use SetupTrait;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->doCreateTables($this->getTables());
        $this->doAlterTables($this->getAlters());
    }

    public function installStep2()
    {
        $db = $this->db();
        $db->query('TRUNCATE TABLE xf_tl_group_wall_category');
        $db->insert('xf_tl_group_wall_category', [
            'group_id' => 0,
            'category_title' => 'Default'
        ]);

        $db->update('xf_tl_group_wall_post', ['category_id' => 1], 'category_id = ?', 0);
    }

    public function uninstallStep1()
    {
        $this->doDropColumns($this->getAlters());
        $this->doDropTables($this->getTables());
    }

    public function upgrade1000300Step1()
    {
        $this->doCreateTables($this->getTables2());
        $this->doAlterTables($this->getAlters1());
    }

    protected function getTables1()
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

    protected function getTables2()
    {
        $tables = [];

        $tables['xf_tl_group_wall_category'] = function (Create $table) {
            $table->addColumn('category_id', 'int')
                ->unsigned()
                ->autoIncrement()
                ->primaryKey();

            $table->addColumn('category_title', 'varchar', 50);
            $table->addColumn('group_id', 'int')->unsigned();

            $table->addKey('group_id');
        };

        return $tables;
    }

    protected function getAlters1()
    {
        $alters = [];

        $alters['xf_tl_group_wall_post'] = [
            'category_id' => function (Alter $table) {
                $table->addColumn('category_id', 'int')
                    ->unsigned()
                    ->setDefault(0);
            }
        ];

        return $alters;
    }
}
