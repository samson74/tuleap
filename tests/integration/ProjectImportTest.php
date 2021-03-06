<?php
/**
 * Copyright (c) Enalean, 2012 - 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

use Tuleap\Project\UgroupDuplicator;

require_once 'exit.php';
require_once 'html.php';
require_once 'user.php';

class ProjectImportTest_SystemEventRunner extends Tuleap\Project\SystemEventRunner {

}

class ProjectImportTest extends TuleapDbTestCase {

    public function __construct() {
        parent::__construct();

        // Uncomment this during development to avoid aweful 50" setUp
        // $this->markThisTestUnderDevelopment();
    }

    public function setUp() {
        parent::setUp();
        PluginManager::instance()->invalidateCache();
        PluginFactory::clearInstance();
        UserManager::clearInstance();
        $this->old_globals = $GLOBALS;
        $GLOBALS['feedback'] = '';
        $GLOBALS['svn_prefix'] = '/tmp';
        $GLOBALS['cvs_prefix'] = '/tmp';
        $GLOBALS['grpdir_prefix'] = '/tmp';
        $GLOBALS['ftp_frs_dir_prefix'] = '/tmp';
        $GLOBALS['ftp_anon_dir_prefix'] = '/tmp';
        $GLOBALS['sys_default_domain'] = '';
        $GLOBALS['sys_cookie_prefix'] = '';
        $GLOBALS['sys_force_ssl'] = 0;
        ForgeConfig::store();
        $this->old_sys_pluginsroot = $GLOBALS['sys_pluginsroot'];
        $this->old_sys_custompluginsroot = $GLOBALS['sys_custompluginsroot'];
        $GLOBALS['sys_pluginsroot'] = dirname(__FILE__) . '/../../plugins/';
        $GLOBALS['sys_custompluginsroot'] = "/tmp";
        ForgeConfig::set('tuleap_dir', __DIR__.'/../../');
        ForgeConfig::set('codendi_log', "/tmp/");
        /**
         * HACK
         */
        require_once dirname(__FILE__).'/../../plugins/fusionforge_compat/include/fusionforge_compatPlugin.class.php';
        $ff_plugin = new fusionforge_compatPlugin();
        $ff_plugin->loaded();

        PluginManager::instance()->installAndActivate('mediawiki');

        $plugin = PluginManager::instance()->getPluginByName('mediawiki');
        EventManager::instance()->addListener(
            Event::IMPORT_XML_PROJECT,
            $plugin,
            'importXmlProject',
            false
        );
        EventManager::instance()->addListener(
            'register_project_creation',
            $plugin,
            'register_project_creation',
            false
        );
        EventManager::instance()->addListener(
            Event::SERVICES_ALLOWED_FOR_PROJECT,
            $plugin,
            'services_allowed_for_project',
            false
        );

        $this->sys_command = new System_Command();

        putenv('TULEAP_LOCAL_INC='.dirname(__FILE__).'/_fixtures/local.inc');
    }

    public function tearDown() {
        ForgeConfig::restore();
        $this->mysqli->query('DELETE FROM groups WHERE unix_group_name = "short-name"');
        unset($GLOBALS['svn_prefix']);
        unset($GLOBALS['cvs_prefix']);
        unset($GLOBALS['grpdir_prefix']);
        unset($GLOBALS['ftp_frs_dir_prefix']);
        unset($GLOBALS['ftp_anon_dir_prefix']);
        unset($GLOBALS['sys_default_domain']);
        unset($GLOBALS['sys_cookie_prefix']);
        unset($GLOBALS['sys_force_ssl']);
        $GLOBALS['sys_pluginsroot'] = $this->old_sys_pluginsroot;
        $GLOBALS['sys_custompluginsroot'] = $this->old_sys_custompluginsroot;
        EventManager::clearInstance();
        PluginManager::instance()->invalidateCache();
        PluginFactory::clearInstance();
        UserManager::clearInstance();
        $GLOBALS = $this->old_globals;
        parent::tearDown();
    }

    public function testImportProjectCreatesAProject() {
        $ugroup_user_dao    = new UGroupUserDao();
        $ugroup_manager     = new UGroupManager();
        $ugroup_duplicator  = new UgroupDuplicator(
            new UGroupDao(),
            $ugroup_manager,
            new UGroupBinding($ugroup_user_dao, $ugroup_manager),
            $ugroup_user_dao,
            EventManager::instance()
        );

        $project_manager = ProjectManager::instance();
        $user_manager    = UserManager::instance();
        $importer        = new ProjectXMLImporter(
            EventManager::instance(),
            $project_manager,
            UserManager::instance(),
            new XML_RNGValidator(),
            new UGroupManager(),
            new XMLImportHelper($user_manager),
            ServiceManager::instance(),
            new Log_ConsoleLogger(),
            $ugroup_duplicator
        );

        $system_event_runner = mock('ProjectImportTest_SystemEventRunner');
        $archive = new Tuleap\Project\XML\Import\DirectoryArchive(__DIR__.'/_fixtures/fake_project');

        $importer->importNewFromArchive(new Tuleap\Project\XML\Import\ImportConfig(), $archive, $system_event_runner);

        // Reset Project Manager (and its cache)
        ProjectManager::clearInstance();
        $project_manager = ProjectManager::instance();

        // Check the project was created
        $project = $project_manager->getProjectByUnixName('toto123');
        $this->assertEqual($project->getPublicName(), 'Toto 123');
        $this->assertEqual($project->getDescription(), '123 Soleil');
        $this->assertEqual($project->usesSVN(), true);
        $this->assertEqual($project->usesCVS(), false);
        $this->assertEqual($project->usesService('plugin_mediawiki'), true);
        $system_event_runner->expectCallCount('runSystemEvents', 1);
        $system_event_runner->expectCallCount('checkPermissions', 1);

        $this->mediawikiTests($project);
    }

    private function mediawikiTests(Project $project) {
        $ugroup_manager        = new UGroupManager();
        $mediawiki_dao         = new MediawikiDao();
        $mediawiki_manager     = new MediawikiManager($mediawiki_dao);
        $mediawikilanguage_dao = new MediawikiLanguageDao();

        $res = $mediawiki_dao->getMediawikiPagesNumberOfAProject($project);
        $this->assertEqual(4, $res['result']);

        $res = $mediawikilanguage_dao->getUsedLanguageForProject($project->getGroupId());
        $this->assertEqual('fr_FR', $res['language']);

        $mediawiki_storage_path = forge_get_config('projects_path', 'mediawiki') . "/". $project->getID();
        $escaped_mw_st_path = escapeshellarg($mediawiki_storage_path);
        $find_cmd = "find $escaped_mw_st_path -type 'f' -iname 'tuleap.png' -printf '%f'";
        $find_res = $this->sys_command->exec($find_cmd);
        $this->assertEqual(1, count($find_res[0]));

        $owner = posix_getpwuid(fileowner($mediawiki_storage_path));
        $this->assertEqual("codendiadm", $owner['name']);
        $group = posix_getgrgid(filegroup($mediawiki_storage_path));
        $this->assertEqual("codendiadm", $group['name']);

        $project_members_id = $ugroup_manager->getUGroupByName($project, 'project_members')->getId();
        $project_admins_id  = $ugroup_manager->getUGroupByName($project, 'project_admins')->getId();

        $group_ids = $mediawiki_manager->getReadAccessControl($project);
        $this->assertEqual(array($project_members_id), $group_ids);
        $group_ids = $mediawiki_manager->getWriteAccessControl($project);
        $this->assertEqual(array($project_admins_id), $group_ids);
    }
}
