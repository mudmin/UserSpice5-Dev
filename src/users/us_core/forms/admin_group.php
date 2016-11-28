<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com
*/

require_once 'init.php';
require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/header.php';

//Secures the page...required for page group/permission management
if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}
checkToken();


$errors = $successes = [];
if (isset($mode) && $mode == 'role') {
    $modeName = 'Group Role';
    $parentScript = 'admin_roles.php';
} else {
    $modeName = 'Group';
    $mode = 'normal';
    $parentScript = 'admin_groups.php';
}

// If requested group does not exist, redirect to $parentScript (admin_groups.php or admin_roles.php)
if (!($group_id = @$_GET['id']) || !groupIdExists($group_id)) {
    Redirect::to($parentScript, "?mode=$mode");
    die();
}

$validation = new Validate([
    'groupname' => ['alias' => 'name',
         'action' => 'update',
         'update_id' => $group_id, ],
    'groupshortname' => ['alias' => 'short_name',
         'action' => 'update',
         'update_id' => $group_id, ],
]);
//Fetch information specific to this group
$groupDetails = fetchGroupDetails($group_id);
//Forms posted
if (Input::exists('post')) {
    //Delete selected group
    if ($deletions = Input::get('deleteGroup', 'post')) {
        if ($deletion_count = deleteGroups($deletions)) {
            $successes[] = lang('GROUP_DELETIONS_SUCCESSFUL', array($deletion_count));
            Redirect::to($parentScript);
        } else {
            $errors[] = 'SQL Error';
        }
    } else {
        //Update group columns
        $changed = false;
        foreach (['name', 'short_name', 'grouptype_id'] as $f) {
            $fields[$f] = Input::get($f);
            if ($fields[$f] != $groupDetails->$f) {
                $changed = true;
            }
        }
        if ($changed) {
            $msg['name'] = 'GROUP_NAME_UPDATE';
            $msg['short_name'] = 'GROUP_NAME_UPDATE';
            $msg['grouptype_id'] = 'GROUP_TYPE_UPDATE';
            $validation->check($fields);
            if ($validation->passed()) {
                $db->update('groups', $group_id, $fields);
                foreach ($msg as $f=>$const) {
                    if ($groupDetails->$f != $fields[$f]) {
                        $successes[] = lang($const, $fields[$f]);
                    }
                }
            } else {
                $errors = $validation->stackErrorMessages($errors);
            }
        }

        //Add new roles
        if (($role_id = Input::get('newRole')) && ($roleuser_id = Input::get('newRoleUser'))) {
            # Add this user to this role for this group
            addGroupsRolesUsers($group_id, $role_id, $roleuser_id);
            # Add this user to the role-group in groups_users_raw
            addGroupsUsers_raw($role_id, $roleuser_id);
            $successes[] = lang('GROUP_ROLE_ADD_SUCCESSFUL');
        } elseif (@$_POST['newRole'] || @$_POST['newRoleUser']) {
            $errors[] = lang('GROUP_NEED_ROLE_AND_USER');
        }

        //Delete roles
        if ($deletes = Input::get('deleteRoles')) {
            # 2nd arg indicates that db integrity should be maintained
            # by deleting from groups_users_raw if needed
            if (deleteGroupsRolesUsers($deletes, true)) {
                $successes[] = lang('GROUP_ROLE_DELETE_SUCCESSFUL');
            } else {
                $errors[] = lang('GROUP_ROLE_DELETE_FAILED');
            }
        }

        //Remove user(s) from group
        if ($remove = Input::get('removeUsers', 'post')) {
            if ($deletion_count = deleteGroupsUsers_raw($group_id, $remove)) {
                $successes[] = lang('GROUP_REMOVE_USERS', array($deletion_count));
            } else {
                $errors[] = lang('SQL_ERROR');
            }
        }

        //Remove nested group(s) from group
        if ($remove = Input::get('removeGroupGroups', 'post')) {
            if ($deletion_count = deleteGroupsUsers_raw($group_id, $remove, 1)) {
                $successes[] = lang('GROUP_REMOVE_GROUPS', array($deletion_count));
            } else {
                $errors[] = lang('SQL_ERROR');
            }
        }

        //Add users to group
        if ($add = Input::get('addUsers', 'post')) {
            if ($addition_count = addGroupsUsers_raw($group_id, $add)) {
                $successes[] = lang('GROUP_ADD_USERS', array($addition_count));
            } else {
                $errors[] = lang('SQL_ERROR');
            }
        }

        //Add nested groups to group
        if ($add = Input::get('addGroupGroups', 'post')) {
            if ($addition_count = addGroupsUsers_raw($group_id, $add, 1)) {
                $successes[] = lang('GROUP_ADD_GROUPS', array($addition_count));
            } else {
                $errors[] = lang('SQL_ERROR');
            }
        }

        //Remove pages from group
        if ($remove = Input::get('removePage', 'post')) {
            if ($deletion_count = deleteGroupsPages($remove, $group_id)) {
                $successes[] = lang('GROUP_REMOVE_PAGES', array($deletion_count));
            } else {
                $errors[] = lang('SQL_ERROR');
            }
        }

        //Add access to pages
        if ($add = Input::get('addPage', 'post')) {
            if ($addition_count = addGroupsPages($add, $group_id)) {
                $successes[] = lang('GROUP_ADD_PAGES', array($addition_count));
            } else {
                $errors[] = lang('SQL_ERROR');
            }
        }
        $groupDetails = fetchGroupDetails($group_id);
    }
}

//Retrieve list of accessible pages
$groupPages = fetchPagesByGroup($group_id);

//Retrieve list of users with and without membership
$groupMembers = fetchGroupMembers_raw($group_id);
$nonGroupMembers = fetchGroupMembers_raw($group_id, true);
// dump($groupMembers);
// dump($nonGroupMembers);

$userData = fetchAllUsers();
$pageData = fetchAllPages();
$groupTypeData = fetchAllGroupTypes();
// get all roles for the current grouptype and for grouptype==null
$groupRoleData = fetchRolesByType($groupDetails->grouptype_id, true);
$groupRoles = fetchRolesByGroup($groupDetails->id);
$groupUsers = fetchUsersByGroup($groupDetails->id);

?>
    <div class="row">
	<div class="col-xs-12">
	<h1 class="text-center">UserSpice Dashboard <?=configGet('version')?></h1>
	<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/admin_nav.php'; ?>
	</div>
	<div class="col-xs-12">
		<?php resultBlock($errors, $successes); ?>
          <h1>Configure Details for <?= $modeName ?> `<?= $groupDetails->name ?>`</h1>

        <ul class="nav nav-tabs" id="myTab">
          <li class="active"><a href="#groupInfo" data-toggle="tab">Group Information</a></li>
          <li><a href="#groupMembers" data-toggle="tab">Group Membership</a></li>
          <li><a href="#groupAccess" data-toggle="tab">Page Access</a></li>
        </ul>
			<form name='adminGroup' method='post'>
                <input type="hidden" name="id" value="<?=$group_id?>" />
    			<input type="hidden" name="csrf" value="<?=Token::generate(); ?>" >
    <div class="tab-content">
      <div class="tab-pane active col-xs-12 col-md-offset-2 col-md-8 col-lg-offset-3 col-lg-6" id="groupInfo">
			<h3><?= $modeName ?> Information</h3>
			<div id='regbox'>
        	<div class="form-group">
			<label>ID:</label>
			<?=$groupDetails->id?>
            </div>
        	<div class="form-group">
			<label>Name:</label>
			<span class="glyphicon glyphicon-info-sign" title="<?= $validation->describe('name') ?>"></span>
			<input class='form-control' type='text' name='name' value='<?=$groupDetails->name?>' />
            </div>
        	<div class="form-group">
			<label>Short Name:</label>
			<span class="glyphicon glyphicon-info-sign" title="<?= $validation->describe('short_name') ?>"></span>
			<input class='form-control' type='text' name='short_name' value='<?=$groupDetails->short_name?>' />
            </div>
            <?php
            if ($groupTypeData) { // don't show option if no group types set up
            ?>
        	<div class="form-group">
                <label><?= ($mode == 'role') ? "Role configurable for this " :'' ?>Group Type:</label>
    			<span class="glyphicon glyphicon-info-sign" title="Choose from the List below"></span>
                <br />
                <select class='form-control' name="grouptype_id" style="min-width: 90%; max-width:90%;">
                    <?php
                    if ($groupDetails->grouptype_id === null) {
                        $selected = 'selected="selected"';
                    } else {
                        $selected = '';
                    }
                    $firstOpt = "<option value=\"\" $selected >No Group Type</option>\n";
                    foreach ($groupTypeData as $idx => $gt) {
                        $selected = ($gt->id == $groupDetails->grouptype_id) ?
                                        $selected = 'selected="selected"' :
                                        $selected = '';
                        echo "$firstOpt"; ?>
                        <option value=<?= $gt->id ?> <?= $selected ?>><?= $gt->name ?></option>
                        <?php
                        $firstOpt = '';
                    } ?>
                </select>
            </div>
            <?php
            }
            if ($groupRoleData && $mode != 'role') { // don't show option if no group roles set up
            ?>
                <p>
                <label>Group Roles:</label>
                <?php
                if ($groupRoles) {
                ?>
                	<div class="form-group">
                    <label>Remove Group Roles:</label>
        			<span class="glyphicon glyphicon-info-sign" title="Check those you wish to delete, then update the group"></span>
                    <table class="table table-hover">
                    <tr>
                        <th>Delete&nbsp;&nbsp;</th><th>Role Name / User</th>
                    </tr>
                    <?php
                    foreach ($groupRoles as $gru) {
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="deleteRoles[<?=$gru->id?>]" value="<?=$gru->id?>" />
                        </td>
                        <td>
                            <?=$gru->name." (".$gru->short_name.'): '?>
                            <?=$gru->fname." ".$gru->lname.' ('.$gru->username.')'?>
                        </td>
                    </tr>
                    <?php
                    }
                    ?>
                    </table>
                    </div>
                <?php
                }
                ?>
                <?php
                if ($groupUsers) {
                ?>
                	<div class="form-group">
                    <label>Add new Role:</label>
        			<span class="glyphicon glyphicon-info-sign" title="Choose both a role and a group member from the lists below"></span>
                    <select class='form-control' name="newRole" style="min-width: 90%; max-width:90%;">
                        <option value="">Choose a Role</option>
                        <?php
                        foreach ($groupRoleData as $gr) {
                        ?>
                            <option value="<?=$gr->id?>"><?=$gr->name?> (<?=$gr->short_name?>)</option>
                        <?php
                        }
                        ?>
                    </select>
                    <select class='form-control' name="newRoleUser" style="min-width: 90%; max-width:90%;">
                        <option value="">Choose a Group Member</option>
                        <?php foreach ($groupUsers as $gu) { ?>
                        <option value="<?=$gu->user_id?>"><?= $gu->fname.' '.$gu->lname.' ('.$gu->username.')' ?></option>
                        <?php } ?>
                    </select>
                    </div> <!-- form-group for 'add new role' -->
                <?php
                } else {
                ?>
                    <p><i>(You must have users as group members to add new roles)</i></p>
                <?php
                }
                ?>
            </p>
            <?php
            }
            ?>
			</p>
			</div>
        </div>
        <div class="tab-pane col-xs-12" id="groupMembers">
			<h3>Group Membership</h3>
			<div id='regbox'>
          <div class="col-xs-12 col-sm-6">
			<h4>Remove Members:</h4>
			<?php
            //Display list of groups with access
            $nested = false;
            $first = true;
            foreach ($groupMembers as $gm) {
                if ($gm->group_or_user == 'group') {
                    $nested = true;
                    continue;
                }
                if ($first) {
                    echo "Users:\n";
                    $first = false;
                }
                echo "<br><label><input type='checkbox' name='removeUsers[]' id='removeUsers[]' value='$gm->id'> $gm->name</label>\n";
            }
            if ($nested) {
                echo '<br />Nested Groups:';
                foreach ($groupMembers as $gm) {
                    if ($gm->group_or_user != 'group') {
                        continue;
                    }
                    echo "<br><label><input type='checkbox' name='removeGroupGroups[]' id='removeGroupGroups[]' value='$gm->id'> $gm->name</label>\n";
                }
            }
            ?>
          </div>
          <div class="tab-pane col-xs-12 col-sm-6">
            <h4>Add Members:</h4>
			<?php
            //List users NOT in this group
            $nested = false;
            $first = true;
            foreach ($nonGroupMembers as $ngm) {
                if ($ngm->group_or_user == 'group') {
                    $nested = true;
                    continue;
                }
                if ($first) {
                    echo "Users:\n";
                    $first = false;
                }
                echo "<br><label><input type='checkbox' name='addUsers[]' id='addUsers[]' value='$ngm->id'> $ngm->name</label>\n";
            }
            if ($nested) {
                echo '<br />Nested Groups:';
                foreach ($nonGroupMembers as $ngm) {
                    if ($ngm->group_or_user != 'group') {
                        continue;
                    }
                    echo "<br><label><input type='checkbox' name='addGroupGroups[]' id='addGroupGroups[]' value='$ngm->id'> $ngm->name</label>\n";
                }
            }
            ?>
          </div>

			</div>
        </div>
        <div class="tab-pane col-xs-12" id="groupAccess">
			<h3>Page Access</h3>
			<div id='regbox'>
            <div class="col-xs-12 col-sm-4">
			<h4>Remove Access From This Group:</h4>
			<?php
            //Display list of pages with this group
            $page_ids = [];
            foreach ($groupPages as $gp) {
                $page_ids[] = $gp->page_id;
            }
            foreach ($pageData as $v1) {
                if (in_array($v1->id, $page_ids)) {
                    ?>
				<input type='checkbox' name='removePage[]' id='removePage[]' value='<?=$v1->id; ?>'> <?=$v1->page; ?><br />
			  <?php
                }
            }  ?>
            </div>
            <div class="col-xs-12 col-sm-4">
			<h4>Add Access To This Group:</h4>
			<?php
            //Display list of pages NOT accessible by this group
            foreach ($pageData as $v1) {
                if (!in_array($v1->id, $page_ids) && $v1->private == 1) {
                    ?>
				<input type='checkbox' name='addPage[]' id='addPage[]' value='<?=$v1->id; ?>'> <?=$v1->page; ?><br />
			  <?php
                }
            }  ?>
            </div>
            <div class="col-xs-12 col-sm-4">
			<h4>Public Pages:</h4>
			<?php
            //List public pages
            foreach ($pageData as $v1) {
                if ($v1->private != 1) {
                    echo $v1->page.'<br />';
                }
            }
            ?>
			</div>
			</div>
      </div>
      </div>
    </div>
		<input class='btn btn-primary' type='submit' value='Update <?= $modeName ?>' class='submit' />
		<button class="btn btn-primary btn-danger" name="deleteGroup" value="<?= $group_id ?>">Delete <?= $modeName ?></button>
		</form>
    </div>
    <!-- /.row -->
    <!-- footers -->
<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls?>

    <!-- Place any per-page javascript here -->
<?php require_once ABS_US_ROOT.US_URL_ROOT.'users/includes/html_footer.php'; // currently just the closing /body and /html?>