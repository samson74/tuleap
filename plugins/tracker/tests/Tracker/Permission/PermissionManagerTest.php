<?php
/**
 * Copyright (c) Enalean, 2013. All Rights Reserved.
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

require_once TRACKER_BASE_DIR . '/../tests/bootstrap.php';

abstract class Tracker_Permission_PermissionManager_BaseTest extends TuleapTestCase {
    protected $minimal_ugroup_list;
    protected $permission_setter;
    protected $permission_manager;
    protected $permissions_manager;
    protected $tracker;
    protected $tracker_id;
    protected $project_id;
    protected $permissions;

    public function setUp() {
        parent::setUp();
        $this->minimal_ugroup_list = array(
            UGroup::ANONYMOUS,
            UGroup::REGISTERED,
            UGroup::PROJECT_MEMBERS,
            UGroup::PROJECT_ADMIN
        );

        $this->tracker_id  = 112;
        $this->project_id  = 34;
        $this->tracker     = aTracker()->withId($this->tracker_id)->withProjectId($this->project_id)->build();
        $this->permissions = array(
            UGroup::ANONYMOUS => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::REGISTERED => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::PROJECT_MEMBERS => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::PROJECT_ADMIN => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
        );
        $this->permissions_manager = mock('PermissionsManager');
        $this->permission_setter    = new Tracker_Permission_PermissionSetter($this->tracker, $this->permissions, $this->permissions_manager);
        $this->permission_manager   = new Tracker_Permission_PermissionManager();
    }
}

class Tracker_Permission_PermissionManager_SubmitterOnlyTest extends Tracker_Permission_PermissionManager_BaseTest {

    public function itDoesNothingTryingToGrantAnonymousSubmittedOnly() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itGrantsRegisteredSubmittedOnly() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::REGISTERED => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY
        ));

        expect($this->permissions_manager)->addPermission(Tracker::PERMISSION_SUBMITTER_ONLY, $this->tracker_id, UGroup::REGISTERED)->once();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itCannotGrantRegisterSubmittedOnlyWhenAnonymousHasFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS  => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::REGISTERED => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY
        ));

        expect($this->permissions_manager)->addPermission(Tracker::PERMISSION_FULL, $this->tracker_id, UGroup::ANONYMOUS)->once();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itRaisesAWarningWhenTryingToGrantRegisteredSubmittedOnlyWithAnonymousHasFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS  => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::REGISTERED => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY
        ));
        $this->permissions[UGroup::ANONYMOUS]['permissions'] = array(
            Tracker::PERMISSION_FULL => 1
        );

        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->once();

        $permission_setter = new Tracker_Permission_PermissionSetter($this->tracker, $this->permissions, $this->permissions_manager);
        $this->permission_manager->save($request, $permission_setter);
    }

    public function itGrantsProjectMembersSubmittedOnly() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY
        ));

        expect($this->permissions_manager)->addPermission(Tracker::PERMISSION_SUBMITTER_ONLY, $this->tracker_id, UGroup::PROJECT_MEMBERS)->once();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itRevokesPreviousPermissionWhenGrantsProjectMembersSubmittedOnly() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY
        ));

        $this->permissions[UGroup::PROJECT_MEMBERS]['permissions'] = array(
            Tracker::PERMISSION_FULL => 1
        );

        expect($this->permissions_manager)->addPermission(Tracker::PERMISSION_SUBMITTER_ONLY, $this->tracker_id, UGroup::PROJECT_MEMBERS)->once();
        expect($this->permissions_manager)->revokePermissionForUGroup(Tracker::PERMISSION_FULL, $this->tracker_id, UGroup::PROJECT_MEMBERS)->once();

        $permission_setter = new Tracker_Permission_PermissionSetter($this->tracker, $this->permissions, $this->permissions_manager);
        $this->permission_manager->save($request, $permission_setter);
    }
}

class Tracker_Permission_PermissionManager_AnonymousWithFullAccessTest extends Tracker_Permission_PermissionManager_BaseTest {

    public function setUp() {
        parent::setUp();

        $permissions = array(
            UGroup::ANONYMOUS => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array(
                    Tracker::PERMISSION_FULL => 1
                )
            ),
            UGroup::REGISTERED => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::PROJECT_MEMBERS => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::PROJECT_ADMIN => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
        );

        $this->permission_setter  = new Tracker_Permission_PermissionSetter($this->tracker, $permissions, $this->permissions_manager);
    }

    public function itWarnsWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS  => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::REGISTERED => Tracker_Permission_Command::PERMISSION_FULL,
        ));

        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->once();

        $this->permission_manager->save($request, $this->permission_setter);
    }


    public function itWarnsTwiceWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS     => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::REGISTERED    => Tracker_Permission_Command::PERMISSION_SUBMITTER,
            UGroup::PROJECT_ADMIN => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY,
        ));

        expect($GLOBALS['Response'])->addFeedback()->count(2);
        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->at(0);
        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->at(1);

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantFullAccessToRegisteredWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS  => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::REGISTERED => Tracker_Permission_Command::PERMISSION_FULL,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

     public function itDoesntGrantSubmitterOnlyToRegisteredWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS  => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::REGISTERED => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantFullAccessToProjectMembersWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_FULL,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantSubmitterToProjectMembersWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_SUBMITTER,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantAssigneeToProjectMembersWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_ASSIGNEE,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantAssigneeAndSubmitterToProjectMembersWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_ASSIGNEE_AND_SUBMITTER,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantSubmitterOnlyToProjectMembersWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itRevokesPreExistingPermission() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY,
        ));
        $this->permissions[UGroup::ANONYMOUS]['permissions'] = array(
            Tracker::PERMISSION_FULL => 1
        );
        $this->permissions[UGroup::PROJECT_MEMBERS]['permissions'] = array(
            Tracker::PERMISSION_SUBMITTER_ONLY => 1
        );

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup(Tracker::PERMISSION_SUBMITTER_ONLY, $this->tracker_id, UGroup::PROJECT_MEMBERS)->once();

        $permission_setter = new Tracker_Permission_PermissionSetter($this->tracker, $this->permissions, $this->permissions_manager);
        $this->permission_manager->save($request, $permission_setter);
    }

    public function itRevokesAdminPermission() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS     => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_ADMIN => Tracker_Permission_Command::PERMISSION_NONE,
        ));
        $this->permissions[UGroup::ANONYMOUS]['permissions'] = array(
            Tracker::PERMISSION_FULL => 1
        );
        $this->permissions[UGroup::PROJECT_ADMIN]['permissions'] = array(
            Tracker::PERMISSION_ADMIN => 1
        );

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup(Tracker::PERMISSION_ADMIN, $this->tracker_id, UGroup::PROJECT_ADMIN)->once();

        $permission_setter = new Tracker_Permission_PermissionSetter($this->tracker, $this->permissions, $this->permissions_manager);
        $this->permission_manager->save($request, $permission_setter);
    }
}

class Tracker_Permission_PermissionManager_RegisteredWithFullAccessTest extends Tracker_Permission_PermissionManager_BaseTest {

    public function setUp() {
        parent::setUp();

        $permissions = array(
            UGroup::ANONYMOUS => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::REGISTERED => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array(
                    Tracker::PERMISSION_FULL => 1
                )
            ),
            UGroup::PROJECT_MEMBERS => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
            UGroup::PROJECT_ADMIN => array(
                'ugroup'      => array('name' => 'whatever'),
                'permissions' => array()
            ),
        );

        $this->permission_setter  = new Tracker_Permission_PermissionSetter($this->tracker, $permissions, $this->permissions_manager);
    }


    public function itWarnsWhenRegisteredHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED      => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_FULL,
        ));

        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->once();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itWarnsTwiceWhenRegisteredHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS       => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED      => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_ADMIN   => Tracker_Permission_Command::PERMISSION_FULL,
        ));

        expect($GLOBALS['Response'])->addFeedback()->count(2);
        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->at(0);
        expect($GLOBALS['Response'])->addFeedback(Feedback::WARN, '*')->at(1);

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantFullAccessToProjectMembersWhenAnonymousHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS        => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS  => Tracker_Permission_Command::PERMISSION_FULL,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantSubmitterToProjectMembersWhenRegisteredHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS        => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS  => Tracker_Permission_Command::PERMISSION_SUBMITTER,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantAssigneeToProjectMembersWhenRegisteredHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS        => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS  => Tracker_Permission_Command::PERMISSION_ASSIGNEE,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantAssigneeAndSubmitterToProjectMembersWhenRegisteredHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS        => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS  => Tracker_Permission_Command::PERMISSION_ASSIGNEE_AND_SUBMITTER,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }

    public function itDoesntGrantSubmitterOnlyToProjectMembersWhenRegisteredHaveFullAccess() {
        $request = new Tracker_Permission_PermissionRequest(array(
            UGroup::ANONYMOUS        => Tracker_Permission_Command::PERMISSION_NONE,
            UGroup::REGISTERED       => Tracker_Permission_Command::PERMISSION_FULL,
            UGroup::PROJECT_MEMBERS  => Tracker_Permission_Command::PERMISSION_SUBMITTER_ONLY,
        ));

        expect($this->permissions_manager)->addPermission()->never();
        expect($this->permissions_manager)->revokePermissionForUGroup()->never();

        $this->permission_manager->save($request, $this->permission_setter);
    }
}
