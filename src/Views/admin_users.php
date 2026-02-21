<header class="page-header">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <h1>Manage Users</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createUserModal').showModal()">Create User</button>
    </div>
</header>

<?php if ($success): ?>
    <div class="alert alert-success" style="background:#32e68f33; color:#32e68f; padding:1rem; border-radius:6px; margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error" style="background:#ff4d4d33; color:#ff4d4d; padding:1rem; border-radius:6px; margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                <th style="padding: 1rem;">Username</th>
                <th style="padding: 1rem;">Email</th>
                <th style="padding: 1rem;">Role</th>
                <th style="padding: 1rem; text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $username => $userData): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 1rem;">
                    <strong><?= htmlspecialchars($username) ?></strong>
                    <?php if ($username === \App\Core\UserContext::get()->getUsername()): ?>
                        <span style="font-size: 0.8rem; background: var(--surface-3); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">You</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 1rem; color: var(--text-secondary);"><?= htmlspecialchars($userData['email']) ?></td>
                <td style="padding: 1rem;">
                    <span style="font-size: 0.8rem; background: <?= $userData['role'] === 'superadmin' ? '#e6328f33' : 'var(--surface-3)' ?>; color: <?= $userData['role'] === 'superadmin' ? '#e6328f' : 'inherit' ?>; padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars(ucfirst($userData['role'])) ?></span>
                </td>
                <td style="padding: 1rem; text-align: right;">
                    <button class="btn btn-secondary btn-sm" onclick="openPasswordModal('<?= htmlspecialchars($username) ?>')">Set Password</button>
                    <form action="/admin/users/delete" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.')">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="background: #ff4d4d33; color: #ff4d4d;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create User Modal -->
<dialog id="createUserModal" style="border:none; border-radius:8px; background:var(--surface-2); color:var(--text-primary); padding:2rem; width:100%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.5);">
    <h3 style="margin-top:0; margin-bottom:1.5rem;">Create New User</h3>
    <form action="/admin/users/create" method="post">
        <div class="form-group" style="margin-bottom:1rem;">
            <label style="display:block; margin-bottom:0.5rem;">Username</label>
            <input type="text" name="username" class="form-control" style="width:100%; padding:0.5rem; background:var(--surface-1); color:inherit; border:1px solid var(--border-color); border-radius:4px;" required>
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
            <label style="display:block; margin-bottom:0.5rem;">Email</label>
            <input type="email" name="email" class="form-control" style="width:100%; padding:0.5rem; background:var(--surface-1); color:inherit; border:1px solid var(--border-color); border-radius:4px;" required>
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
            <label style="display:block; margin-bottom:0.5rem;">Role</label>
            <select name="role" class="form-control" style="width:100%; padding:0.5rem; background:var(--surface-1); color:inherit; border:1px solid var(--border-color); border-radius:4px;">
                <option value="user">User</option>
                <option value="superadmin">Superadmin</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
            <label style="display:block; margin-bottom:0.5rem;">Password</label>
            <input type="password" name="password" class="form-control" style="width:100%; padding:0.5rem; background:var(--surface-1); color:inherit; border:1px solid var(--border-color); border-radius:4px;" required>
        </div>
        <div class="form-group" style="margin-bottom:1.5rem;">
            <label style="display:block; margin-bottom:0.5rem;">Confirm Password</label>
            <input type="password" name="password_confirm" class="form-control" style="width:100%; padding:0.5rem; background:var(--surface-1); color:inherit; border:1px solid var(--border-color); border-radius:4px;" required>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <button type="button" class="btn btn-secondary" style="padding:0.5rem 1rem; border-radius:4px; background:var(--surface-3); color:inherit; border:none; cursor:pointer;" onclick="document.getElementById('createUserModal').close()">Cancel</button>
            <button type="submit" class="btn btn-primary" style="padding:0.5rem 1rem; border-radius:4px; background:var(--accent-color); color:var(--accent-contrast); border:none; cursor:pointer; font-weight:bold;">Create</button>
        </div>
    </form>
</dialog>

<!-- Update Password Modal -->
<dialog id="updatePasswordModal" style="border:none; border-radius:8px; background:var(--surface-2); color:var(--text-primary); padding:2rem; width:100%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.5);">
    <h3 style="margin-top:0; margin-bottom:1.5rem;">Change Password</h3>
    <p style="margin-bottom:1rem; font-size:0.9rem; color:var(--text-secondary);">Setting new password for <strong id="pwdUsernameDisplay"></strong></p>
    <form action="/admin/users/password" method="post">
        <input type="hidden" name="username" id="pwdUsernameInput">
        <div class="form-group" style="margin-bottom:1.5rem;">
            <label style="display:block; margin-bottom:0.5rem;">New Password</label>
            <input type="password" name="new_password" class="form-control" style="width:100%; padding:0.5rem; background:var(--surface-1); color:inherit; border:1px solid var(--border-color); border-radius:4px;" required>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <button type="button" class="btn btn-secondary" style="padding:0.5rem 1rem; border-radius:4px; background:var(--surface-3); color:inherit; border:none; cursor:pointer;" onclick="document.getElementById('updatePasswordModal').close()">Cancel</button>
            <button type="submit" class="btn btn-primary" style="padding:0.5rem 1rem; border-radius:4px; background:var(--accent-color); color:var(--accent-contrast); border:none; cursor:pointer; font-weight:bold;">Update Password</button>
        </div>
    </form>
</dialog>

<script>
function openPasswordModal(username) {
    document.getElementById('pwdUsernameDisplay').textContent = username;
    document.getElementById('pwdUsernameInput').value = username;
    document.getElementById('updatePasswordModal').showModal();
}
</script>
