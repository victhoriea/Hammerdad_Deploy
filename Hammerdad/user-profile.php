<?php 
require_once 'auth.php';
require 'db.php';
$pageTitle = "@" . $_SESSION['username'] . "'s Profile";

$headerBtnClass = "hamburger-btn";
$headerBtnAction = "openSidePanel()";
$headerBtnIcon = "☰";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">
    <title>User Profile | Hammerdad</title>

    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="loader.css">
    <link rel="icon" type="image/png" href="images/Hammerdad-Logo.png">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <style>
        body {
            font-family: Tahoma, sans-serif; 
            background-color: #d9d9d9;
            display: flex;
            flex-direction: column;
        } 

        .main-panel {
            min-height: unset;
            overflow: visible;
            position: relative;  
            z-index: 20;  
        }

        .user-details {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            gap: 10px;
            
            font-size: 20px; 
            color: #212328;
        }

        .user-details p {
            margin: 0;
            padding-bottom: 10px;
        }

        .user-details img {
            height: 100px; 
            border-style: solid; 
            border-color: #d1d1d1; 
            border-width: 1px; 
            border-radius: 100px; 
            margin-right: 10px;
        }

        .user-details-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-wrapper-1 {
            display: flex;
            flex-direction: column;
            text-align: left;
            margin-top: 15px;
        }

        .name-row {
            display: flex;
            gap: 10px;
        }

        .name {
            font-weight: bold;
        }

        .username {
            color: #757575;
        }

        .actions-wrapper {
            display: flex;
            flex-direction: row;
            margin: 0 10px 0 auto;
            gap: 3px;
        }

        .edit-container {
            position: relative;
            display: inline-block;
            overflow: visible;
        }

        #EditUserPopup {
            display: none;
            position: absolute;
            right: 0;
            z-index: 101;
            width: 310px;
        }

        #EditUserPopup.show {
            display: block;
        }

        .edit-container form label {
            font-size: 16px;
        }

        .action-btn {
            border: none;
            background-color: #ffffff00;
            color: #a4a4a4;
            display: flex;
            flex-direction: column;
            justify-self: center;
            align-self: flex-start;
            padding: 5px 5px;
        }

        .action-btn:hover {
            color: #1e1f22;
            cursor: pointer;
        }

        .bottom-panel {
            background: #fff;
            width: clamp(400px, 78%, 1200px);
            margin-top: 3px;
            padding: clamp(16px, 2.5vw, 35px);
            
            display: flex;
            flex-direction: column;
            align-self: center;
            border-radius: 5px;
            transition: transform 0.5s;  
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }

        .bottom-panel.move {
            transform: translateX(calc(var(--sidebar-width) / 2));
        }

        .bottom2-panel {
            background: #fff;
            width: clamp(400px, 78%, 1200px);
            margin-top: 3px;
            padding: clamp(16px, 2.5vw, 35px);
            
            display: flex;
            flex-direction: column;
            align-self: center;
            border-radius: 5px;
            transition: transform 0.5s;  
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }

        .bottom2-panel.move {
            transform: translateX(calc(var(--sidebar-width) / 2));
        }

        .add-btn {
            margin: 15px 0 0 0;
        }

        .adduser-popup-box, .changepw-popup-box {
            background: #f8f8f8;
            width: 340px;
            padding: 10px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 10px;
        }

        .edituser-popup-box {
            width: 300px;
        }

        .changepw-popup-box {
            width: 370px;
        }

        .changepw-popup-box .adduser-wrapper input, select {
            width: 200px;
        }

        .adduser-wrapper {
            background-color: #fff;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            padding: 20px 20px;
            display: flex;
            flex-direction: column;
        }

        .adduser-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-2 {
            font-size: 25px;
            font-weight: bold;
            color: #b71513;
            margin: 0;
            padding: 5px 0 10px 20px;
        }

        .x-btn {
            font-size: 22px;
            color: #b0b0b0;
            background: none;
            border: none;
            padding: 0 2px;
            margin-right: 8px;
            margin-bottom: auto;
        }

        .x-btn:hover {
            color: #575757;
        }

        .adduser-wrapper p {
            margin: 0;
            padding-top: 15px;
            color: #757575;
            zoom: 0.85;
        }

        .adduser-wrapper input, select {
            width: 170px;
            box-sizing: border-box;
            height: 35px;
            padding: 2px 15px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin: 2px 0 7px 0;
        }

        .adduser-btn-row {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .adduser-popup-btn {
            width: 35%;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .adduser-save-btn { background-color: #b71513; color: white; }
        .adduser-save-btn:hover { background-color: #d31512; }
        .adduser-cancel-btn { background-color: #CBCBCB; color: #000; }
        .adduser-cancel-btn:hover { background-color: rgb(189, 189, 189); }

        /* Password section divider in edit popup */
        .pw-divider {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 10px 0 8px;
        }

        .pw-section-label, .pw-section-button {
            color: #999;
            letter-spacing: 0.5px;
            padding-top: 4px !important;
            padding-bottom: 4px;
        }

        .pw-section-button {
            border: none;
            background: none;
        }

        .pw-section-button:hover {
            color: #636363;
            cursor: pointer;
        }

        /* User list table */
        .user-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 15px;
        }

        .user-list-table th {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 2px solid #e0e0e0;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .user-list-table td {
            padding: 10px 10px;
            border-bottom: 1px solid #f0f0f0;
            color: #212328;
            vertical-align: middle;
        }

        .user-list-table tr:last-child td {
            border-bottom: none;
        }

        .user-list-table tr:hover td {
            background-color: #fafafa;
        }

        .role-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background-color: #fdecea;
            color: #b71513;
        }

        .role-badge.staff {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .table-action-btn {
            border: none;
            background: none;
            color: #a4a4a4;
            padding: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }

        .table-action-btn:hover {
            color: #b71513;
        }

        /* Change password popup for user list */
        #ChangeUserPasswordPopup .adduser-popup-box {
            width: 320px;
        }

        #EditOtherUserPopup input {
            width: 200px;
        }

    </style>
    
</head>

<body>

    <!-- ESSENTIALS -->

    <div class="loader-wrapper">
        <div class="loader"></div>
    </div>

    <?php include 'page-essentials.php'; ?>

    <!-- MAIN BODY -->

    <div class = "main-panel">
        <div class="user-details">
            <div class="user-details-wrapper">
                <img src="images/user-icon.png">

                <div class="info-wrapper-1">
                    <div class="name-row">
                        <p class="name"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></p>
                        <p class="username">@<?= htmlspecialchars($_SESSION['username']) ?></p>
                    </div>
                    <p><?= strtoupper($_SESSION['role']) ?></p>
                </div>
            </div>

            <div class="actions-wrapper">
                <div class="edit-container">
                    <button class="action-btn" onclick="event.stopPropagation(); openEditUserPopup()">
                        <span class="material-symbols-outlined">edit</span>
                    </button>

                    <div class="adduser-popup-box" id="EditUserPopup" onclick="event.stopPropagation()">
                        <div class="header-row">
                            <p class="header-2">Edit Profile</p>
                            <button class="x-btn" onclick="closeEditUserPopup()">✕</button>
                        </div>

                        <div class="adduser-wrapper">
                            <form id="editUserForm">
                                <div class="adduser-row">
                                    <label>Username</label>
                                    <input disabled id="editUserName" value="<?= htmlspecialchars($_SESSION['username']) ?>">
                                </div>
                                <div class="adduser-row">
                                    <label>First Name</label>
                                    <input id="editFirstName" value="<?= htmlspecialchars($_SESSION['first_name']) ?>">
                                </div>
                                <div class="adduser-row">
                                    <label>Last Name</label>
                                    <input id="editLastName" value="<?= htmlspecialchars($_SESSION['last_name']) ?>">
                                </div>

                                <!-- Password change section -->
                                <hr class="pw-divider" style="margin-top: 30px;">
                                <button type="button" class="pw-section-button" onclick="ChangePasswordPopup(event)">
                                    <span style="font-weight: bold; font-size:18px;">&#9432;</span> Change Password?
                                </button>
                            </form>

                            <div id="editUserMessage" style="display:none; font-size:13px; text-align:center; margin-top:6px;"></div>

                            <div class="adduser-btn-row">
                                <button class="adduser-popup-btn adduser-cancel-btn" onclick="closeEditUserPopup()">Cancel</button>
                                <button id="editUserBtn" onclick="saveUserEdit()" class="adduser-popup-btn adduser-save-btn">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="popup-overlay" id="ChangePasswordPopup">
        <div class="changepw-popup-box" onclick="event.stopPropagation()">
            <div class="header-row">
                <p class="header-2">Change Password</p>
                <button class="x-btn" onclick="closeChangePasswordPopup()">✕</button>
            </div>

            <div class="adduser-wrapper">
                <div class="adduser-row">
                    <label>New Password</label>
                    <input type="password" id="editNewPassword" placeholder="New password">
                </div>
                <div class="adduser-row">
                    <label>Confirm</label>
                    <input type="password" id="editConfirmPassword" placeholder="Repeat new password">
                </div>

                <div id="changePasswordError" style="color:#c62828; font-size:13px; margin-bottom:8px; display:none;"></div>

                <div class="adduser-btn-row">
                    <button class="popup-msg-btn gray-btn" onclick="closeChangePasswordPopup()">Cancel</button>
                    <button class="popup-msg-btn red-btn" onclick="submitNewPassword()">Confirm</button>
                </div>
            </div>
        </div>
    </div>


    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="bottom-panel">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
            <p style="font-weight: bold; margin: 0;">USERS LIST</p>
            <button class="add-btn" style="margin:0;" onclick="openAddUserPopup()">Add New User</button>
        </div>

        <!-- User list -->
        <?php
            $stmt = $conn->prepare("SELECT user_id, username, first_name, last_name, role FROM users WHERE username != ? ORDER BY role, last_name");
            $stmt->bind_param('s', $_SESSION['username']);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $mechanics = [];
            if ($_SESSION['role'] === 'admin') {
                $mechanics = $conn->query("SELECT mechanic_id, first_name, last_name FROM mechanics ORDER BY last_name ASC")->fetch_all(MYSQLI_ASSOC);
            }            
        ?>
        <?php if (!empty($users)): ?>
        <table class="user-list-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td style="color:#757575;">@<?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <span class="role-badge <?= strtolower($u['role']) ?>">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <button
                            class="table-action-btn"
                            title="Edit <?= htmlspecialchars($u['username']) ?>"
                            onclick="openEditOtherUserPopup(
                                <?= $u['user_id'] ?>,
                                '<?= htmlspecialchars($u['username'],   ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($u['first_name'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($u['last_name'],  ENT_QUOTES) ?>'
                            )">
                            <span class="material-symbols-outlined" style="font-size:20px;">edit</span>
                        </button>
                        <button
                            class="table-action-btn"
                            title="Delete <?= htmlspecialchars($u['username']) ?>"
                            onclick="openDeleteUserPopup(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                            <span class="material-symbols-outlined" style="font-size:20px;">delete</span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#999; font-size:14px; margin-top:12px;">No other users found.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="bottom2-panel" style="margin-top: 10px; margin-bottom: 10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
            <p style="font-weight: bold; margin: 0;">MECHANICS LIST</p>
            <button class="add-btn" style="margin:0;" onclick="openAddMechanicPopup()">Add Mechanic</button>
        </div>

        <?php if (!empty($mechanics)): ?>
        <table class="user-list-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mechanics as $mc): ?>
                <tr>
                    <td style="color:#757575;"><?= htmlspecialchars($mc['mechanic_id']) ?></td>
                    <td><?= htmlspecialchars($mc['first_name'] . ' ' . $mc['last_name']) ?></td>
                    <td style="text-align:center;">
                        <button
                            class="table-action-btn"
                            title="Edit <?= htmlspecialchars($mc['first_name']) ?>"
                            onclick="openEditMechanicPopup(
                                '<?= htmlspecialchars($mc['mechanic_id'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($mc['first_name'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($mc['last_name'],  ENT_QUOTES) ?>'
                            )">
                            <span class="material-symbols-outlined" style="font-size:20px;">edit</span>
                        </button>
                        <button
                            class="table-action-btn"
                            title="Delete <?= htmlspecialchars($mc['first_name']) ?>"
                            onclick="openDeleteMechanicPopup('<?= htmlspecialchars($mc['mechanic_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mc['first_name'] . ' ' . $mc['last_name'], ENT_QUOTES) ?>')">
                            <span class="material-symbols-outlined" style="font-size:20px;">delete</span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#999; font-size:14px; margin-top:12px;">No mechanics found.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ADD USER POPUP -->
    <div class="popup-overlay" id="AddUserPopup">
        <div class="adduser-popup-box" onclick="event.stopPropagation()">
            <div class="header-row">
                <p class="header-2">Add New User</p>
                <button class="x-btn" onclick="closeAddUserPopup()">✕</button>
            </div>

            <div class="adduser-wrapper">
                <form id="addUserForm">
                    <div class="adduser-row">
                        <label>First Name</label>
                        <input id="firstName" name="first_name" required placeholder="ex. Juan">
                    </div>
                    <div class="adduser-row">
                        <label>Last Name</label>
                        <input id="lastName" name="last_name" required placeholder="ex. Dela Cruz">
                    </div>
                    <div class="adduser-row">
                        <label>Username</label>
                        <input id="username" name="username" required placeholder="ex. juandc">
                    </div>
                    <div class="adduser-row">
                        <label>Password</label>
                        <input id="password" name="password" required type="password">
                    </div>
                    <div class="adduser-row">
                        <label>Role</label>
                        <select id="role" name="role" required>
                            <option disabled selected>-- Role --</option>
                            <option>Admin</option>
                            <option>Staff</option>
                        </select>
                    </div>

                    <div class="adduser-btn-row">
                        <button class="adduser-popup-btn adduser-cancel-btn" onclick="closeAddUserPopup()">Cancel</button>
                        <button type="button" id="addUserBtn" onclick="openConfirmPasswordPopup(event)" class="adduser-popup-btn adduser-save-btn">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CONFIRM PASSWORD POPUP (for Add User) -->
    <div class="popup-overlay" id="ConfirmPasswordPopup">
        <div class="msg-box" onclick="event.stopPropagation()">
            <img src="images/Hammerdad-Logo.png" alt="Logo" class="logout-logo">
            <h3>Confirm Action</h3>
            <p>An admin's password is needed to make these changes.
                Enter your password to confirm adding this new user.</p>

            <input type="password" id="adminConfirmPassword" placeholder="Your password"
                style="width:100%; box-sizing:border-box; height:36px; padding:2px 12px; border:1px solid #DDDDDD; border-radius:7px; margin:10px 0;">

            <div id="confirmPasswordError" style="color:#c62828; font-size:13px; margin-bottom:8px; display:none;"></div>

            <button class="popup-msg-btn gray-btn" onclick="closeConfirmPasswordPopup()">Cancel</button>
            <button class="popup-msg-btn red-btn" id="confirmAddUserBtn" onclick="submitAddUser()">Confirm</button>
        </div>
    </div>

    <!-- EDIT OTHER USER POPUP -->
    <div class="popup-overlay" id="EditOtherUserPopup">
        <div class="adduser-popup-box" onclick="event.stopPropagation()" style="width:360px;">
            <div class="header-row">
                <p class="header-2">Edit User</p>
                <button class="x-btn" onclick="closeEditOtherUserPopup()">✕</button>
            </div>

            <div class="adduser-wrapper">
                <input type="hidden" id="editOtherUserId">

                <div class="adduser-row">
                    <label>Username</label>
                    <input id="editOtherUsername" placeholder="Username">
                </div>
                <div class="adduser-row">
                    <label>First Name</label>
                    <input id="editOtherFirstName" placeholder="First name">
                </div>
                <div class="adduser-row">
                    <label>Last Name</label>
                    <input id="editOtherLastName" placeholder="Last name">
                </div>

                <hr class="pw-divider">
                <p class="pw-section-label">Change Password (optional)</p>

                <div class="adduser-row">
                    <label>New Password</label>
                    <input type="password" id="editOtherNewPassword" placeholder="Leave blank to keep">
                </div>
                <div class="adduser-row">
                    <label>Confirm</label>
                    <input type="password" id="editOtherConfirmPassword" placeholder="Repeat new password">
                </div>

                <div id="editOtherUserMessage" style="display:none; font-size:13px; text-align:center; margin-top:8px;"></div>

                <hr class="pw-divider" style="margin-top:12px;">
                <p class="pw-section-label">Your password to confirm</p>
                <div class="adduser-row">
                    <label>Your Password</label>
                    <input type="password" id="editOtherAdminPw" placeholder="Your password">
                </div>

                <div class="adduser-btn-row">
                    <button class="adduser-popup-btn adduser-cancel-btn" onclick="closeEditOtherUserPopup()">Cancel</button>
                    <button id="editOtherUserBtn" onclick="submitEditOtherUser()" class="adduser-popup-btn adduser-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE USER POPUP -->
    <div class="popup-overlay" id="DeleteUserPopup">
        <div class="msg-box" onclick="event.stopPropagation()">
            <img src="images/trash-icon.png" class="msg-box-trash-img">
            <h3>Delete User</h3>
            <p id="deleteUserMsg">Are you sure you want to delete this user? This cannot be undone.</p>

            <input type="password" id="deleteAdminPw" placeholder="Your admin password"
                style="width:100%; box-sizing:border-box; height:36px; padding:2px 12px; border:1px solid #DDDDDD; border-radius:7px; margin:10px 0; z-index:10;">

            <div id="deleteUserError" style="color:#c62828; font-size:13px; margin-bottom:8px; display:none;"></div>

            <button class="popup-msg-btn gray-btn" onclick="closeDeleteUserPopup()">Cancel</button>
            <button class="popup-msg-btn red-btn" id="confirmDeleteUserBtn" onclick="submitDeleteUser()">Delete</button>
        </div>
    </div>

    <!-- ADD MECHANIC POPUP -->
    <div class="popup-overlay" id="AddMechanicPopup">
        <div class="adduser-popup-box" onclick="event.stopPropagation()">
            <div class="header-row">
                <p class="header-2">Add Mechanic</p>
                <button class="x-btn" onclick="closeAddMechanicPopup()">✕</button>
            </div>
            <div class="adduser-wrapper">
                <form id="addMechanicForm">
                    <div class="adduser-row">
                        <label>First Name</label>
                        <input id="mechanicFirstName" required placeholder="ex. Juan">
                    </div>
                    <div class="adduser-row">
                        <label>Last Name</label>
                        <input id="mechanicLastName" required placeholder="ex. Dela Cruz">
                    </div>
                    <div id="addMechanicMsg" style="display:none; font-size:13px; text-align:center; margin-top:6px;"></div>
                    <div class="adduser-btn-row">
                        <button type="button" class="adduser-popup-btn adduser-cancel-btn" onclick="closeAddMechanicPopup()">Cancel</button>
                        <button type="button" id="addMechanicBtn" onclick="submitAddMechanic()" class="adduser-popup-btn adduser-save-btn">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT MECHANIC POPUP -->
    <div class="popup-overlay" id="EditMechanicPopup">
        <div class="adduser-popup-box" onclick="event.stopPropagation()">
            <div class="header-row">
                <p class="header-2">Edit Mechanic</p>
                <button class="x-btn" onclick="closeEditMechanicPopup()">✕</button>
            </div>
            <div class="adduser-wrapper">
                <input type="hidden" id="editMechanicId">
                <div class="adduser-row">
                    <label>First Name</label>
                    <input id="editMechanicFirstName" placeholder="First name">
                </div>
                <div class="adduser-row">
                    <label>Last Name</label>
                    <input id="editMechanicLastName" placeholder="Last name">
                </div>
                <div id="editMechanicMsg" style="display:none; font-size:13px; text-align:center; margin-top:6px;"></div>
                <div class="adduser-btn-row">
                    <button class="adduser-popup-btn adduser-cancel-btn" onclick="closeEditMechanicPopup()">Cancel</button>
                    <button id="editMechanicBtn" onclick="submitEditMechanic()" class="adduser-popup-btn adduser-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE MECHANIC POPUP -->
    <div class="popup-overlay" id="DeleteMechanicPopup">
        <div class="msg-box" onclick="event.stopPropagation()">
            <img src="images/trash-icon.png" class="msg-box-trash-img">
            <h3>Delete Mechanic</h3>
            <p id="deleteMechanicMsg">Are you sure you want to delete this mechanic?</p>
            <div id="deleteMechanicError" style="color:#c62828; font-size:13px; margin-bottom:8px; display:none;"></div>
            <button class="popup-msg-btn gray-btn" onclick="closeDeleteMechanicPopup()">Cancel</button>
            <button class="popup-msg-btn red-btn" id="confirmDeleteMechanicBtn" onclick="submitDeleteMechanic()">Delete</button>
        </div>
    </div>


    <script src="layout.js"></script>

<script>

    // ── Edit own profile ──────────────────────────────────────────────────────

    function openEditUserPopup() {
        document.getElementById("EditUserPopup").classList.toggle("show");
    }

    function closeEditUserPopup() {
        document.getElementById("EditUserPopup").classList.remove("show");
        hideMsg("editUserMessage");
    }

    function ChangePasswordPopup(e) {
        e.stopPropagation();
        document.getElementById("editNewPassword").value = '';
        document.getElementById("editConfirmPassword").value = '';
        document.getElementById("changePasswordError").style.display = 'none';
        document.getElementById("ChangePasswordPopup").style.display = 'flex';
    }

    function closeChangePasswordPopup() {
        document.getElementById("ChangePasswordPopup").style.display = 'none';
    }

    function submitNewPassword() {
        const newPw    = document.getElementById("editNewPassword").value;
        const confirmPw = document.getElementById("editConfirmPassword").value;
        const errorEl  = document.getElementById("changePasswordError");

        if (!newPw) {
            errorEl.textContent = "Please enter a new password.";
            errorEl.style.display = 'block';
            return;
        }
        if (newPw !== confirmPw) {
            errorEl.textContent = "Passwords do not match.";
            errorEl.style.display = 'block';
            return;
        }
        if (newPw.length < 6) {
            errorEl.textContent = "Password must be at least 6 characters.";
            errorEl.style.display = 'block';
            return;
        }

        const btn = document.querySelector("#ChangePasswordPopup .red-btn");
        setButtonLoading(btn, true, "Saving...");

        fetch('edit-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_password: newPw })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeChangePasswordPopup();
                closeEditUserPopup();
                location.reload();
            } else {
                errorEl.textContent = data.message || "Something went wrong.";
                errorEl.style.display = 'block';
            }
        })
        .catch(() => {
            errorEl.textContent = "Request failed.";
            errorEl.style.display = 'block';
        })
        .finally(() => setButtonLoading(btn, false, "Confirm"));
    }

    function saveUserEdit() {
        const firstName   = document.getElementById("editFirstName").value.trim();
        const lastName    = document.getElementById("editLastName").value.trim();
        const newPassword = document.getElementById("editNewPassword").value;
        const confirmPw   = document.getElementById("editConfirmPassword").value;

        if (!firstName || !lastName) {
            showMsg("editUserMessage", "First and last name cannot be empty.", "error");
            return;
        }

        if (newPassword || confirmPw) {
            if (newPassword !== confirmPw) {
                showMsg("editUserMessage", "Passwords do not match.", "error");
                return;
            }
            if (newPassword.length < 6) {
                showMsg("editUserMessage", "Password must be at least 6 characters.", "error");
                return;
            }
        }

        const payload = {
            first_name: firstName,
            last_name:  lastName,
        };
        if (newPassword) payload.new_password = newPassword;

        const btn = document.getElementById("editUserBtn");
        setButtonLoading(btn, true, "Saving...");

        fetch('edit-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMsg("editUserMessage", "Saved!", "success");
                setTimeout(() => { closeEditUserPopup(); location.reload(); }, 800);
            } else {
                showMsg("editUserMessage", data.message || "Something went wrong.", "error");
            }
        })
        .catch(() => showMsg("editUserMessage", "Request failed.", "error"))
        .finally(() => setButtonLoading(btn, false, "Save"));
    }

    // ── Add new user ──────────────────────────────────────────────────────────

    function openAddUserPopup() {
        document.getElementById("AddUserPopup").classList.add("show");
        document.getElementById("addUserForm").reset();
    }

    function closeAddUserPopup() {
        document.getElementById("AddUserPopup").classList.remove("show");
        document.getElementById("addUserForm").reset();
    }

    function openConfirmPasswordPopup(e) {
        e.preventDefault();

        const firstName = document.getElementById("firstName").value.trim();
        const lastName  = document.getElementById("lastName").value.trim();
        const username  = document.getElementById("username").value.trim();
        const password  = document.getElementById("password").value.trim();
        const role      = document.getElementById("role").value;

        if (!firstName || !lastName || !username || !password || !role || role === '-- Role --') {
            showPopupMessage("addUserMessage", "Please fill in all required fields.", "error");
            return;
        }

        document.getElementById("adminConfirmPassword").value = '';
        document.getElementById("confirmPasswordError").style.display = 'none';
        document.getElementById("ConfirmPasswordPopup").style.display = 'flex';
    }

    function closeConfirmPasswordPopup() {
        document.getElementById("ConfirmPasswordPopup").style.display = 'none';
    }

    function showPopupMessage(id, msg, type) {
        let el = document.getElementById(id);
        if (!el) {
            el = document.createElement("p");
            el.id = id;
            el.style.cssText = "margin: 8px 0 0; font-size: 13px; text-align: center; font-weight: 500;";
            const btnRow = document.querySelector("#AddUserPopup .adduser-btn-row");
            if (btnRow) btnRow.parentNode.insertBefore(el, btnRow);
        }
        el.textContent = msg;
        el.style.color = type === "success" ? "#2e7d32" : "#c62828";
    }

    function submitAddUser() {
        const adminPassword = document.getElementById("adminConfirmPassword").value;
        const errorEl = document.getElementById("confirmPasswordError");

        if (!adminPassword) {
            errorEl.textContent = "Please enter your password.";
            errorEl.style.display = 'block';
            return;
        }

        const payload = {
            first_name:     document.getElementById("firstName").value.trim(),
            last_name:      document.getElementById("lastName").value.trim(),
            username:       document.getElementById("username").value.trim(),
            password:       document.getElementById("password").value,
            role:           document.getElementById("role").value,
            admin_password: adminPassword
        };

        const btn = document.getElementById("confirmAddUserBtn");
        setButtonLoading(btn, true, "Adding...");

        fetch('add-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeConfirmPasswordPopup();
                closeAddUserPopup();
                location.reload();
            } else {
                errorEl.textContent = data.message || "Incorrect password.";
                errorEl.style.display = 'block';
            }
        })
        .catch(() => {
            errorEl.textContent = "Something went wrong.";
            errorEl.style.display = 'block';
        })
        .finally(() => setButtonLoading(btn, false, "Confirm"));
    }

    // ── Edit another user (name, username, password) ──────────────────────────

    function openEditOtherUserPopup(userId, username, firstName, lastName) {
        document.getElementById("editOtherUserId").value      = userId;
        document.getElementById("editOtherUsername").value    = username;
        document.getElementById("editOtherFirstName").value   = firstName;
        document.getElementById("editOtherLastName").value    = lastName;
        document.getElementById("editOtherNewPassword").value = '';
        document.getElementById("editOtherConfirmPassword").value = '';
        document.getElementById("editOtherAdminPw").value     = '';
        hideMsg("editOtherUserMessage");
        document.getElementById("EditOtherUserPopup").style.display = 'flex';
    }

    function closeEditOtherUserPopup() {
        document.getElementById("EditOtherUserPopup").style.display = 'none';
    }

    function submitEditOtherUser() {
        const userId    = document.getElementById("editOtherUserId").value;
        const username  = document.getElementById("editOtherUsername").value.trim();
        const firstName = document.getElementById("editOtherFirstName").value.trim();
        const lastName  = document.getElementById("editOtherLastName").value.trim();
        const newPw     = document.getElementById("editOtherNewPassword").value;
        const confirmPw = document.getElementById("editOtherConfirmPassword").value;
        const adminPw   = document.getElementById("editOtherAdminPw").value;

        if (!username || !firstName || !lastName) {
            showMsg("editOtherUserMessage", "Username and name fields cannot be empty.", "error");
            return;
        }
        if (newPw || confirmPw) {
            if (newPw !== confirmPw) {
                showMsg("editOtherUserMessage", "Passwords do not match.", "error");
                return;
            }
            if (newPw.length < 6) {
                showMsg("editOtherUserMessage", "Password must be at least 6 characters.", "error");
                return;
            }
        }
        if (!adminPw) {
            showMsg("editOtherUserMessage", "Enter your admin password to confirm.", "error");
            return;
        }

        const payload = {
            user_id:        userId,
            username:       username,
            first_name:     firstName,
            last_name:      lastName,
            admin_password: adminPw
        };
        if (newPw) payload.new_password = newPw;

        const btn = document.getElementById("editOtherUserBtn");
        setButtonLoading(btn, true, "Saving...");

        fetch('edit-other-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMsg("editOtherUserMessage", "Saved!", "success");
                setTimeout(() => { closeEditOtherUserPopup(); location.reload(); }, 800);
            } else {
                showMsg("editOtherUserMessage", data.message || "Something went wrong.", "error");
            }
        })
        .catch(() => showMsg("editOtherUserMessage", "Request failed.", "error"))
        .finally(() => setButtonLoading(btn, false, "Save"));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function showMsg(id, msg, type) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg;
        el.style.color = type === "success" ? "#2e7d32" : "#c62828";
        el.style.display = 'block';
    }

    function hideMsg(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // ── Delete user ───────────────────────────────────────────────────────────
    let deleteTargetUserId = null;

    function openDeleteUserPopup(userId, username) {
        deleteTargetUserId = userId;
        document.getElementById("deleteUserMsg").textContent = 
            `Are you sure you want to delete @${username}? This cannot be undone.`;
        document.getElementById("deleteAdminPw").value = '';
        document.getElementById("deleteUserError").style.display = 'none';
        document.getElementById("DeleteUserPopup").style.display = 'flex';
    }

    function closeDeleteUserPopup() {
        document.getElementById("DeleteUserPopup").style.display = 'none';
        deleteTargetUserId = null;
    }

    function submitDeleteUser() {
        const adminPw  = document.getElementById("deleteAdminPw").value;
        const errorEl  = document.getElementById("deleteUserError");

        if (!adminPw) {
            errorEl.textContent = "Please enter your admin password.";
            errorEl.style.display = 'block';
            return;
        }

        const btn = document.getElementById("confirmDeleteUserBtn");
        setButtonLoading(btn, true, "Deleting...");

        fetch('delete-user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id:        deleteTargetUserId,
                admin_password: adminPw
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeDeleteUserPopup();
                location.reload();
            } else {
                errorEl.textContent = data.message || "Failed to delete user.";
                errorEl.style.display = 'block';
            }
        })
        .catch(() => {
            errorEl.textContent = "Something went wrong.";
            errorEl.style.display = 'block';
        })
        .finally(() => setButtonLoading(btn, false, "Delete"));
    }

    // ── Add Mechanic ──────────────────────────────────────────────────────────
    function openAddMechanicPopup() {
        document.getElementById("AddMechanicPopup").classList.add("show");
        document.getElementById("addMechanicForm").reset();
        hideMsg("addMechanicMsg");
    }

    function closeAddMechanicPopup() {
        document.getElementById("AddMechanicPopup").classList.remove("show");
        document.getElementById("addMechanicForm").reset();
    }

    function submitAddMechanic() {
        const firstName = document.getElementById("mechanicFirstName").value.trim();
        const lastName  = document.getElementById("mechanicLastName").value.trim();

        if (!firstName || !lastName) {
            showMsg("addMechanicMsg", "Please fill in all fields.", "error");
            return;
        }

        const btn = document.getElementById("addMechanicBtn");
        setButtonLoading(btn, true, "Adding...");

        fetch('mechanic-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', first_name: firstName, last_name: lastName })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMsg("addMechanicMsg", "Mechanic added!", "success");
                setTimeout(() => { closeAddMechanicPopup(); location.reload(); }, 800);
            } else {
                showMsg("addMechanicMsg", data.message || "Failed.", "error");
            }
        })
        .catch(() => showMsg("addMechanicMsg", "Something went wrong.", "error"))
        .finally(() => setButtonLoading(btn, false, "Add"));
    }

    // ── Edit Mechanic ─────────────────────────────────────────────────────────
    function openEditMechanicPopup(mechanicId, firstName, lastName) {
        document.getElementById("editMechanicId").value        = mechanicId;
        document.getElementById("editMechanicFirstName").value = firstName;
        document.getElementById("editMechanicLastName").value  = lastName;
        hideMsg("editMechanicMsg");
        document.getElementById("EditMechanicPopup").classList.add("show");
    }

    function closeEditMechanicPopup() {
        document.getElementById("EditMechanicPopup").classList.remove("show");
    }

    function submitEditMechanic() {
        const mechanicId = document.getElementById("editMechanicId").value;
        const firstName  = document.getElementById("editMechanicFirstName").value.trim();
        const lastName   = document.getElementById("editMechanicLastName").value.trim();

        if (!firstName || !lastName) {
            showMsg("editMechanicMsg", "Please fill in all fields.", "error");
            return;
        }

        const btn = document.getElementById("editMechanicBtn");
        setButtonLoading(btn, true, "Saving...");

        fetch('mechanic-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'edit', mechanic_id: mechanicId, first_name: firstName, last_name: lastName })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMsg("editMechanicMsg", "Saved!", "success");
                setTimeout(() => { closeEditMechanicPopup(); location.reload(); }, 800);
            } else {
                showMsg("editMechanicMsg", data.message || "Failed.", "error");
            }
        })
        .catch(() => showMsg("editMechanicMsg", "Something went wrong.", "error"))
        .finally(() => setButtonLoading(btn, false, "Save"));
    }

    // ── Delete Mechanic ───────────────────────────────────────────────────────
    let deleteMechanicTargetId = null;

    function openDeleteMechanicPopup(mechanicId, name) {
        deleteMechanicTargetId = mechanicId;
        document.getElementById("deleteMechanicMsg").textContent =
            `Are you sure you want to delete ${name}? This cannot be undone.`;
        document.getElementById("deleteMechanicError").style.display = 'none';
        document.getElementById("DeleteMechanicPopup").classList.add("show");
    }

    function closeDeleteMechanicPopup() {
        document.getElementById("DeleteMechanicPopup").classList.remove("show");
        deleteMechanicTargetId = null;
    }

    function submitDeleteMechanic() {
        const errorEl = document.getElementById("deleteMechanicError");
        const btn = document.getElementById("confirmDeleteMechanicBtn");
        setButtonLoading(btn, true, "Deleting...");

        fetch('mechanic-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', mechanic_id: deleteMechanicTargetId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeDeleteMechanicPopup();
                location.reload();
            } else {
                errorEl.textContent = data.message || "Failed to delete mechanic.";
                errorEl.style.display = 'block';
            }
        })
        .catch(() => {
            errorEl.textContent = "Something went wrong.";
            errorEl.style.display = 'block';
        })
        .finally(() => setButtonLoading(btn, false, "Delete"));
    }   

</script>

</body>
</html>